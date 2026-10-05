<?php
/**
 * Lechon Logs - Livestock Owner
 * Shows all lechon orders made from their pigs
 */
$currentPage = 'lechon-logs';
$user = $_SESSION['user'] ?? null;
if (!$user) { header('Location: /login'); exit; }

global $conn;

// Get livestock owner
$stmt = $conn->prepare("SELECT id, farm_name FROM livestock_owners WHERE user_id = ?");
$stmt->bind_param('i', $user['id']);
$stmt->execute();
$owner = $stmt->get_result()->fetch_assoc();
$stmt->close();

if (!$owner) {
    $_SESSION['error'] = 'Profile not found';
    header('Location: /dashboard'); exit;
}

// Filters
$filter_customer = trim($_GET['customer'] ?? '');
$filter_status = trim($_GET['status'] ?? '');
$filter_from = trim($_GET['from'] ?? '');
$filter_to = trim($_GET['to'] ?? '');
$filter_category = trim($_GET['category'] ?? ''); // NEW: Category filter for WHOLE_LECHON vs OTHER

// Build query to get lechon orders from this livestock owner's pigs
$where = ['otc.livestock_owner_id = ?'];
$params = [$owner['id']];
$types = 'i';

if ($filter_customer !== '') {
    $where[] = 'otc.customer_name LIKE ?';
    $params[] = '%' . $filter_customer . '%';
    $types .= 's';
}
if ($filter_status !== '') {
    $where[] = 'sos.order_status = ?';
    $params[] = $filter_status;
    $types .= 's';
}
if ($filter_from !== '') {
    $where[] = 'DATE(po.created_at) >= ?';
    $params[] = $filter_from;
    $types .= 's';
}
if ($filter_to !== '') {
    $where[] = 'DATE(po.created_at) <= ?';
    $params[] = $filter_to;
    $types .= 's';
}

// Check if cooking_schedule table exists
$table_exists = false;
$check_table = $conn->query("SHOW TABLES LIKE 'cooking_schedule'");
if ($check_table && $check_table->num_rows > 0) {
    $table_exists = true;
}

$sql = "SELECT 
            otc.order_number,
            otc.customer_name,
            COALESCE(pd.pig_tag_id, otc.pig_tag_id) as pig_tag_id,
            COALESCE(pp.cage_number, otc.pin_number) as pin_number,
            otc.pig_base_amount,
            otc.include_laman_loob,
            otc.laman_loob_price,
            otc.include_boopes,
            otc.boopes_price,
            otc.include_dinuguan,
            otc.dinuguan_price,
            otc.delivery_method,
            otc.delivery_address,
            otc.delivery_fee,
            otc.labor_cost,
            otc.payment_type,
            otc.amount_paid,
            otc.remaining_balance,
            sos.order_status,
            sos.payment_status,
            sos.weight_kg,
            sos.pickup_date as delivery_date,
            po.created_at as order_date,
            po.delivery_notes," .
            ($table_exists ? "cs.start_time, cs.end_time, cs.date_to_butcher, cs.time_to_butcher," : "NULL as start_time, NULL as end_time, NULL as date_to_butcher, NULL as time_to_butcher,") . "
            (otc.pig_base_amount + 
             IFNULL(otc.laman_loob_price, 0) + 
             IFNULL(otc.boopes_price, 0) + 
             IFNULL(otc.dinuguan_price, 0) + 
             IFNULL(otc.delivery_fee, 0) + 
             IFNULL(otc.labor_cost, 0)) as total_amount
        FROM order_total_cost otc
        LEFT JOIN swine_order_status sos ON sos.id = otc.swine_order_id
        LEFT JOIN placed_orders po ON po.swine_order_id = sos.id
        LEFT JOIN pig_details pd ON pd.id = sos.pig_detail_id
        LEFT JOIN pig_pins pp ON pp.id = pd.cage_id" .
        ($table_exists ? " LEFT JOIN cooking_schedule cs ON cs.order_number COLLATE utf8mb4_unicode_ci = otc.order_number COLLATE utf8mb4_unicode_ci" : "") . "
        WHERE " . implode(' AND ', $where) . "
        ORDER BY po.created_at DESC";

$stmt = $conn->prepare($sql);
if (!$stmt) {
    error_log("SQL prepare error: " . $conn->error);
    error_log("SQL query: " . $sql);
    die("Database error: " . $conn->error);
}
if ($params) {
    $stmt->bind_param($types, ...$params);
}
if (!$stmt->execute()) {
    error_log("SQL execute error: " . $stmt->error);
    die("Database execution error: " . $stmt->error);
}
$pig_logs = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
$stmt->close();

// ========== NEW: ADD LECHON_ORDERS (marketplace lechon orders) ==========
// Build separate query for lechon_orders from marketplace
$lechon_where = ['lo.livestock_owner_id = ?'];
$lechon_params = [$owner['id']];
$lechon_types = 'i';

// Only include orders that are past 'pending' stage (confirmed, preparing, etc.)
// UPDATE: Now including 'pending' orders so livestock owner can confirm/decline from Lechon Logs
$lechon_where[] = "lo.order_status IN ('pending', 'confirmed', 'preparing', 'ready_for_pickup', 'completed')";

if ($filter_customer !== '') {
    $lechon_where[] = 'u.name LIKE ?';
    $lechon_params[] = '%' . $filter_customer . '%';
    $lechon_types .= 's';
}
if ($filter_status !== '') {
    $lechon_where[] = 'lo.order_status = ?';
    $lechon_params[] = $filter_status;
    $lechon_types .= 's';
}
if ($filter_from !== '') {
    $lechon_where[] = 'DATE(lo.created_at) >= ?';
    $lechon_params[] = $filter_from;
    $lechon_types .= 's';
}
if ($filter_to !== '') {
    $lechon_where[] = 'DATE(lo.created_at) <= ?';
    $lechon_params[] = $filter_to;
    $lechon_types .= 's';
}

$lechon_sql = "SELECT 
            lo.id as lechon_order_id,
            lo.order_number,
            u.name as customer_name,
            lo.listing_name as pig_tag_id,
            lo.category as pin_number,
            lo.price as pig_base_amount,
            0 as include_laman_loob,
            0 as laman_loob_price,
            0 as include_boopes,
            0 as boopes_price,
            0 as include_dinuguan,
            0 as dinuguan_price,
            COALESCE(lo.seller_feedback, 'Pickup') as delivery_method,
            '' as delivery_address,
            0 as delivery_fee,
            0 as labor_cost,
            'full' as payment_type,
            CASE WHEN lo.payment_status = 'paid' THEN lo.price ELSE 0 END as amount_paid,
            0 as remaining_balance,
            lo.order_status,
            lo.payment_status,
            lo.weight_kg,
            lo.pickup_date as delivery_date,
            lo.created_at as order_date,
            COALESCE(lo.inquiry_message, '') as delivery_notes,
            lo.inquiry_message as customer_inquiry," .
            ($table_exists ? "NULL as start_time, NULL as end_time, NULL as date_to_butcher, NULL as time_to_butcher," : "NULL as start_time, NULL as end_time, NULL as date_to_butcher, NULL as time_to_butcher,") . "
            lo.price as total_amount,
            'LECHON_ORDER' as source_type,
            lo.category as lechon_category
        FROM lechon_orders lo
        LEFT JOIN users u ON u.id = lo.customer_id
        WHERE " . implode(' AND ', $lechon_where) . "
        ORDER BY lo.created_at DESC";

$lechon_stmt = $conn->prepare($lechon_sql);
if (!$lechon_stmt) {
    error_log("Lechon SQL prepare error: " . $conn->error);
    $lechon_logs = []; // Fail gracefully
} else {
    if ($lechon_params) {
        $lechon_stmt->bind_param($lechon_types, ...$lechon_params);
    }
    if (!$lechon_stmt->execute()) {
        error_log("Lechon SQL execute error: " . $lechon_stmt->error);
        $lechon_logs = []; // Fail gracefully
    } else {
        $lechon_logs = $lechon_stmt->get_result()->fetch_all(MYSQLI_ASSOC);
    }
    $lechon_stmt->close();
}

// Mark pig-based logs with source_type
foreach ($pig_logs as &$log) {
    $log['source_type'] = 'PIG_ORDER';
    $log['lechon_category'] = 'PIG_LECHON'; // Default category for pig orders
}

// Merge both arrays
$logs = array_merge($pig_logs, $lechon_logs);

// Sort by order_date descending
usort($logs, function($a, $b) {
    return strtotime($b['order_date']) - strtotime($a['order_date']);
});

// ========== Separate other orders BEFORE filtering $logs ==========
// Pull "other" category lechon orders directly from the raw merged array
$all_other_orders = array_filter($logs, function($log) {
    return $log['source_type'] === 'LECHON_ORDER' && isset($log['lechon_category']) && $log['lechon_category'] !== 'WHOLE_LECHON';
});

// Apply category filter if set
if ($filter_category !== '') {
    $logs = array_filter($logs, function($log) use ($filter_category) {
        if ($filter_category === 'WHOLE_LECHON') {
            return isset($log['lechon_category']) && $log['lechon_category'] === 'WHOLE_LECHON';
        } elseif ($filter_category === 'PIG') {
            return $log['source_type'] === 'PIG_ORDER';
        }
        // Note: OTHER category filter removed - other orders not shown in Lechon Logs
        return true; // 'all' shows pig + whole lechon only
    });
} else {
    // DEFAULT: Only show PIG orders and WHOLE_LECHON orders
    // Filter out OTHER category orders (they go to a separate "Other Orders" page)
    $logs = array_filter($logs, function($log) {
        if ($log['source_type'] === 'PIG_ORDER') {
            return true; // Always show pig orders
        }
        if ($log['source_type'] === 'LECHON_ORDER' && isset($log['lechon_category']) && $log['lechon_category'] === 'WHOLE_LECHON') {
            return true; // Show whole lechon marketplace orders
        }
        return false; // Hide other orders (Pancit, etc.)
    });
}
// ========== END NEW CODE ==========

// Calculate totals
$totalOrders = count($logs);
$totalWeight = array_sum(array_column($logs, 'weight_kg'));
$totalRevenue = array_sum(array_column($logs, 'total_amount'));

// ========== DELIVERY REMINDERS ==========
// Find orders with delivery_date = today or tomorrow (across both tabs)
$today     = date('Y-m-d');
$tomorrow  = date('Y-m-d', strtotime('+1 day'));

$reminders_today    = [];
$reminders_tomorrow = [];

$all_logs_for_reminder = array_merge(array_values($logs), array_values($all_other_orders));
foreach ($all_logs_for_reminder as $rl) {
    if (empty($rl['delivery_date'])) continue;
    // Only remind for active (non-completed/cancelled) orders
    $os = $rl['order_status'] ?? '';
    if (in_array($os, ['completed', 'cancelled'])) continue;
    $d = date('Y-m-d', strtotime($rl['delivery_date']));
    $entry = [
        'order_number'  => $rl['order_number'],
        'customer_name' => $rl['customer_name'],
        'delivery_date' => $rl['delivery_date'],
        'source_type'   => $rl['source_type'],
        'lechon_category' => $rl['lechon_category'] ?? '',
        'order_status'  => $os,
    ];
    if ($d === $today)    $reminders_today[]    = $entry;
    if ($d === $tomorrow) $reminders_tomorrow[] = $entry;
}
// ========== END DELIVERY REMINDERS ==========
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Order Logs - LechGO</title>
    <link rel="stylesheet" href="/styles.css">
    <style>
        .tl-wrap { max-width: 1400px; margin: 0 auto; padding: 1.5rem; }
        .tl-header { margin-bottom: 1.5rem; }
        .tl-header h1 { font-size: 1.8rem; margin: 0; color: #333; }
        .tl-header p { margin: 6px 0 0; color: #888; font-size: 1rem; }

        /* Summary cards */
        .tl-summary { display: flex; gap: 1.5rem; margin-bottom: 1.5rem; flex-wrap: wrap; }
        .tl-sum-card {
            flex: 1; min-width: 180px;
            background: #fff; border-radius: 12px;
            box-shadow: 0 3px 10px rgba(0,0,0,.08);
            padding: 1.5rem 1.75rem;
        }
        .tl-sum-card .val { font-size: 1.6rem; font-weight: 800; color: var(--primary-color, #c0392b); }
        .tl-sum-card .lbl { font-size: .85rem; color: #888; text-transform: uppercase; letter-spacing: .04em; margin-top: 4px; }

        /* Filter bar */
        .tl-filters {
            background: #fff; border-radius: 12px;
            box-shadow: 0 3px 10px rgba(0,0,0,.08);
            padding: 1.5rem 1.75rem; margin-bottom: 1.5rem;
            display: flex; gap: 1rem; flex-wrap: wrap; align-items: flex-end;
        }
        .tl-filter-group { display: flex; flex-direction: column; min-width: 140px; }
        .tl-filter-group label { font-size: .85rem; color: #666; margin-bottom: 6px; text-transform: uppercase; letter-spacing: .03em; }
        .tl-filter-group input, .tl-filter-group select {
            padding: 8px 12px; border: 1px solid #ddd; border-radius: 8px;
            font-size: .95rem; background: #fff;
        }
        .tl-filter-actions { display: flex; gap: .75rem; align-items: flex-end; }
        .tl-filter-btn { padding: 9px 16px; border: none; border-radius: 8px; font-size: .9rem; cursor: pointer; text-decoration: none; display: inline-block; }
        .tl-filter-btn.primary { background: var(--primary-color, #c0392b); color: #fff; }
        .tl-filter-btn.secondary { background: #f8f9fa; color: #666; border: 1px solid #ddd; }

        /* Table */
        .tl-table-container {
            background: #fff; border-radius: 12px;
            box-shadow: 0 3px 10px rgba(0,0,0,.08);
            overflow-x: auto; /* Always allow horizontal scroll */
        }
        .tl-table { width: 100%; border-collapse: collapse; min-width: 1200px; }
        .tl-table th { 
            background: #f8f9fa; padding: 16px 12px; text-align: left; 
            font-size: .9rem; color: #666; text-transform: uppercase; 
            letter-spacing: .03em; border-bottom: 1px solid #eee; 
            white-space: nowrap; /* Prevent text wrapping */
        }
        .tl-table td { 
            padding: 16px 12px; border-bottom: 1px solid #f5f5f5; 
            font-size: .95rem; 
            white-space: nowrap; /* Prevent text wrapping */
        }
        .tl-table tr:hover { background: #fafbfc; }
        
        /* Actions column specific styling */
        .tl-table th:last-child,
        .tl-table td:last-child {
            min-width: 140px;
            text-align: center;
        }

        /* Status badges */
        .status-badge {
            padding: 6px 12px; border-radius: 15px; font-size: .8rem;
            font-weight: 600; text-transform: uppercase; letter-spacing: .04em;
            display: inline-block;
        }
        .status-pending { background: #fff3cd; color: #856404; }
        .status-confirmed { background: #d4edda; color: #155724; }
        .status-preparing { background: #cce5ff; color: #004085; }
        .status-cooking { background: #ffe6cc; color: #cc5500; }
        .status-delivering { background: #e2e3e5; color: #383d41; }
        .status-completed { background: #d1ecf1; color: #0c5460; }
        .status-cancelled { background: #f8d7da; color: #721c24; }

        /* Cooking time inputs */
        .cooking-time-input {
            width: 100px;
            padding: 4px 8px;
            border: 1px solid #ddd;
            border-radius: 4px;
            font-size: .8rem;
            text-align: center;
        }
        .cooking-time-input:focus {
            outline: none;
            border-color: var(--primary-color, #c0392b);
            box-shadow: 0 0 0 2px rgba(192, 57, 43, 0.1);
        }

        /* Delivery date input */
        .delivery-date-input {
            width: 120px;
            padding: 4px 8px;
            border: 1px solid #ddd;
            border-radius: 4px;
            font-size: .8rem;
            text-align: center;
        }
        .delivery-date-input:focus {
            outline: none;
            border-color: var(--primary-color, #c0392b);
            box-shadow: 0 0 0 2px rgba(192, 57, 43, 0.1);
        }

        /* View button */
        .btn {
            display: inline-block;
            padding: 6px 12px;
            margin: 2px;
            border: none;
            border-radius: 4px;
            text-decoration: none;
            font-size: 0.8rem;
            cursor: pointer;
            transition: background-color 0.2s;
        }
        
        .btn-sm {
            padding: 3px 6px;
            font-size: 0.7rem;
            line-height: 1.2;
        }
        
        .btn-success {
            background-color: #28a745;
            color: white;
        }
        
        .btn-success:hover {
            background-color: #218838;
        }
        
        .btn-info {
            background-color: #17a2b8;
            color: white;
        }
        
        .btn-info:hover {
            background-color: #138496;
        }
        
        .btn-secondary {
            background-color: #6c757d;
            color: white;
        }
        
        .btn-secondary:hover {
            background-color: #545b62;
        }
        
        .form-control {
            border: 1px solid #ddd;
            border-radius: 4px;
        }
        
        .form-control:focus {
            outline: none;
            border-color: var(--primary-color, #c0392b);
            box-shadow: 0 0 0 2px rgba(192, 57, 43, 0.1);
        }
        
        .view-btn {
            background: var(--primary-color, #c0392b);
            color: white;
            border: none;
            padding: 6px 12px;
            border-radius: 6px;
            font-size: .8rem;
            cursor: pointer;
            transition: background-color 0.2s;
        }
        .view-btn:hover {
            background: #a93226;
        }

        /* Modal styles */
        .modal {
            display: none;
            position: fixed;
            z-index: 1000;
            left: 0;
            top: 0;
            width: 100%;
            height: 100%;
            background-color: rgba(0,0,0,0.5);
        }
        .modal-content {
            background-color: #fefefe;
            margin: 5% auto;
            padding: 2rem;
            border-radius: 12px;
            width: 90%;
            max-width: 600px;
            max-height: 80vh;
            overflow-y: auto;
            position: relative;
        }
        .modal-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 1.5rem;
            padding-bottom: 1rem;
            border-bottom: 1px solid #eee;
        }
        .modal-header h3 {
            margin: 0;
            color: #333;
            font-size: 1.3rem;
        }
        .close {
            color: #aaa;
            font-size: 28px;
            font-weight: bold;
            cursor: pointer;
            line-height: 1;
        }
        .close:hover {
            color: #000;
        }
        .modal-body {
            line-height: 1.6;
        }
        .modal-body .detail-row {
            display: flex;
            margin-bottom: 0.75rem;
            padding: 0.5rem 0;
            border-bottom: 1px solid #f5f5f5;
        }
        .modal-body .detail-label {
            font-weight: 600;
            width: 150px;
            color: #555;
        }
        .modal-body .detail-value {
            flex: 1;
            color: #333;
        }

        /* Responsive */
        @media (max-width: 768px) {
            .tl-wrap { padding: 1rem; }
            .tl-filters { flex-direction: column; align-items: stretch; }
            .tl-filter-group { min-width: auto; }
            .tl-filter-actions { justify-content: stretch; }
            .tl-filter-btn { flex: 1; }
            
            .tl-table-container { overflow-x: auto; }
            .tl-table { min-width: 1200px; } /* Reduced for fewer columns */
            .cooking-time-input { width: 80px; }
        }

        .empty-state {
            text-align: center; padding: 4rem 2rem; color: #888;
        }
        .empty-state h3 { margin: 0 0 .75rem; color: #666; font-size: 1.2rem; }
        .empty-state p { margin: 0; font-size: 1rem; }
        
        /* Tab Navigation */
        .tab-navigation {
            display: flex;
            gap: 8px;
            margin-bottom: 1.5rem;
            border-bottom: 2px solid #e5e7eb;
        }
        .tab-btn {
            padding: 12px 24px;
            background: transparent;
            border: none;
            border-bottom: 3px solid transparent;
            font-size: 0.95rem;
            font-weight: 600;
            color: #6b7280;
            cursor: pointer;
            transition: all 0.2s;
            margin-bottom: -2px;
        }
        .tab-btn:hover {
            color: var(--primary-color, #c0392b);
            background: #f9fafb;
        }
        .tab-btn.active {
            color: var(--primary-color, #c0392b);
            border-bottom-color: var(--primary-color, #c0392b);
        }
        .tab-content {
            display: none;
        }
        .tab-content.active {
            display: block;
        }

        /* Delivery Reminder Banners */
        .delivery-reminders { margin-bottom: 1.5rem; display: flex; flex-direction: column; gap: .75rem; }
        .reminder-banner {
            display: flex; align-items: flex-start; gap: 1rem;
            padding: 1rem 1.25rem; border-radius: 10px;
            border-left: 5px solid;
            font-size: .92rem; line-height: 1.5;
        }
        .reminder-banner.today {
            background: #fff3cd; border-color: #e6a817; color: #7a5000;
        }
        .reminder-banner.tomorrow {
            background: #e8f4fd; border-color: #2196f3; color: #1a5276;
        }
        .reminder-banner .rb-icon { font-size: 1.4rem; flex-shrink: 0; margin-top: 1px; }
        .reminder-banner .rb-body strong { display: block; font-size: .95rem; margin-bottom: .25rem; }
        .reminder-banner .rb-items { margin: 0; padding: 0; list-style: none; display: flex; flex-wrap: wrap; gap: .4rem .9rem; }
        .reminder-banner .rb-items li { font-size: .85rem; }
        .reminder-badge {
            display: inline-block; padding: 2px 8px; border-radius: 8px;
            font-size: .72rem; font-weight: 700; text-transform: uppercase; letter-spacing: .04em;
            margin-left: 5px;
        }
        .rb-today  { background: #e6a817; color: #fff; }
        .rb-tmrw   { background: #2196f3; color: #fff; }

        /* Send to Logistics button */
        .btn-logistics {
            background: #6f42c1; color: white;
            border: none; border-radius: 4px;
            padding: 3px 8px; font-size: 0.7rem;
            cursor: pointer; display: block; width: 100%; margin-bottom: 3px;
            transition: background 0.2s;
        }
        .btn-logistics:hover { background: #5a32a3; }
        .btn-logistics:disabled { background: #b8a9d9; cursor: not-allowed; }
    </style>
</head>
<body>
<div class="dashboard-layout">
    <?php include __DIR__ . '/../layouts/sidebar.php'; ?>
    <main class="dashboard-main">
        <!-- Top Bar with Hamburger Menu -->
        <div class="dashboard-topbar">
            <button class="dashboard-mobile-toggle" id="sidebarToggle">☰</button>
            <h1 class="dashboard-topbar-title">Order Logs</h1>
            <div class="dashboard-topbar-actions">
                <span class="dashboard-topbar-date"><?php echo date('l, F j, Y'); ?></span>
            </div>
        </div>

        <div class="dashboard-content">
        <div class="tl-wrap">
            <div class="tl-header">
                <h1>Order Logs</h1>
                <p>All your pig-to-lechon order transactions</p>
            </div>

            <!-- Delivery Reminder Banners -->
            <?php if (!empty($reminders_today) || !empty($reminders_tomorrow)): ?>
            <div class="delivery-reminders">
                <?php if (!empty($reminders_today)): ?>
                <div class="reminder-banner today">
                    <div class="rb-icon"></div>
                    <div class="rb-body">
                        <strong>Delivery Today — <?php echo date('F j, Y'); ?></strong>
                        <ul class="rb-items">
                            <?php foreach ($reminders_today as $r): ?>
                            <li>
                                <strong><?php echo htmlspecialchars($r['customer_name']); ?></strong>
                                &nbsp;·&nbsp; #<?php echo htmlspecialchars($r['order_number']); ?>
                                &nbsp;·&nbsp; <?php echo $r['lechon_category'] ?: 'Pig Order'; ?>
                                <span class="reminder-badge rb-today">TODAY</span>
                            </li>
                            <?php endforeach; ?>
                        </ul>
                    </div>
                </div>
                <?php endif; ?>
                <?php if (!empty($reminders_tomorrow)): ?>
                <div class="reminder-banner tomorrow">
                    <div class="rb-icon"> </div>
                    <div class="rb-body">
                        <strong>Delivery Tomorrow — <?php echo date('F j, Y', strtotime('+1 day')); ?></strong>
                        <ul class="rb-items">
                            <?php foreach ($reminders_tomorrow as $r): ?>
                            <li>
                                <strong><?php echo htmlspecialchars($r['customer_name']); ?></strong>
                                &nbsp;·&nbsp; #<?php echo htmlspecialchars($r['order_number']); ?>
                                &nbsp;·&nbsp; <?php echo $r['lechon_category'] ?: 'Pig Order'; ?>
                                <span class="reminder-badge rb-tmrw">TOMORROW</span>
                            </li>
                            <?php endforeach; ?>
                        </ul>
                    </div>
                </div>
                <?php endif; ?>
            </div>
            <?php endif; ?>

            <!-- Tab Navigation -->
            <?php 
            $active_tab = $_GET['tab'] ?? 'lechon';
            // Lechon production logs (already filtered in $logs)
            $lechon_production_logs = array_values($logs);
            // Other orders: use $all_other_orders computed before filtering $logs
            $other_orders_logs = array_values($all_other_orders);
            $lechon_count = count($lechon_production_logs);
            $other_count = count($other_orders_logs);
            ?>
            <div class="tab-navigation">
                <button class="tab-btn <?php echo $active_tab === 'lechon' ? 'active' : ''; ?>" onclick="switchTab('lechon')">
                     Lechon Production <span style="background: #c0392b; color: white; padding: 2px 8px; border-radius: 10px; font-size: 0.75rem; margin-left: 6px;"><?php echo $lechon_count; ?></span>
                </button>
                <button class="tab-btn <?php echo $active_tab === 'other' ? 'active' : ''; ?>" onclick="switchTab('other')">
                     Other Orders <span style="background: #059669; color: white; padding: 2px 8px; border-radius: 10px; font-size: 0.75rem; margin-left: 6px;"><?php echo $other_count; ?></span>
                </button>
            </div>

            <!-- LECHON PRODUCTION TAB CONTENT -->
            <div id="tab-lechon" class="tab-content <?php echo $active_tab === 'lechon' ? 'active' : ''; ?>">
                <?php 
                // Use lechon_production_logs for this tab
                $display_logs = array_values($lechon_production_logs);
                $totalOrders = count($display_logs);
                $totalWeight = array_sum(array_column($display_logs, 'weight_kg'));
                $totalRevenue = array_sum(array_column($display_logs, 'total_amount'));
                ?>

            <!-- Summary Cards -->
            <div class="tl-summary">
                <div class="tl-sum-card">
                    <div class="val"><?php echo $totalOrders; ?></div>
                    <div class="lbl">Total Lechon Orders</div>
                </div>
                <div class="tl-sum-card">
                    <div class="val"><?php echo number_format($totalWeight, 1); ?> kg</div>
                    <div class="lbl">Total Weight Processed</div>
                </div>
                <div class="tl-sum-card">
                    <div class="val">₱<?php echo number_format($totalRevenue, 2); ?></div>
                    <div class="lbl">Total Revenue</div>
                </div>
            </div>

            <!-- Filters -->
            <form method="GET" class="tl-filters">
                <div class="tl-filter-group">
                    <label>Customer Name</label>
                    <input type="text" name="customer" value="<?php echo htmlspecialchars($filter_customer); ?>" placeholder="Search customer...">
                </div>
                <div class="tl-filter-group">
                    <label>Order Status</label>
                    <select name="status">
                        <option value="">All Status</option>
                        <option value="pending" <?php echo $filter_status === 'pending' ? 'selected' : ''; ?>>Pending</option>
                        <option value="confirmed" <?php echo $filter_status === 'confirmed' ? 'selected' : ''; ?>>Confirmed</option>
                        <option value="preparing" <?php echo $filter_status === 'preparing' ? 'selected' : ''; ?>>Preparing</option>
                        <option value="cooking" <?php echo $filter_status === 'cooking' ? 'selected' : ''; ?>>Cooking</option>
                        <option value="delivering" <?php echo $filter_status === 'delivering' ? 'selected' : ''; ?>>Delivering</option>
                        <option value="completed" <?php echo $filter_status === 'completed' ? 'selected' : ''; ?>>Completed</option>
                        <option value="cancelled" <?php echo $filter_status === 'cancelled' ? 'selected' : ''; ?>>Cancelled</option>
                    </select>
                </div>
                <div class="tl-filter-group">
                    <label>Category</label>
                    <select name="category">
                        <option value="">All Orders</option>
                        <option value="PIG" <?php echo $filter_category === 'PIG' ? 'selected' : ''; ?>>Pig-Based Lechon</option>
                        <option value="WHOLE_LECHON" <?php echo $filter_category === 'WHOLE_LECHON' ? 'selected' : ''; ?>>Whole Lechon (Marketplace)</option>
                    </select>
                </div>
                <div class="tl-filter-group">
                    <label>From Date</label>
                    <input type="date" name="from" value="<?php echo htmlspecialchars($filter_from); ?>">
                </div>
                <div class="tl-filter-group">
                    <label>To Date</label>
                    <input type="date" name="to" value="<?php echo htmlspecialchars($filter_to); ?>">
                </div>
                <div class="tl-filter-actions">
                    <button type="submit" class="tl-filter-btn primary">Filter</button>
                    <a href="/livestock-owner/lechon-logs" class="tl-filter-btn secondary">✕ Clear</a>
                </div>
            </form>

            <!-- Table -->
            <div class="tl-table-container">
                <?php if (empty($display_logs)): ?>
                    <div class="empty-state">
                        <h3>No Lechon Orders Found</h3>
                        <p>No lechon orders have been made from your pigs yet, or none match your current filters.</p>
                    </div>
                <?php else: ?>
                    <table class="tl-table">
                        <thead>
                            <tr>
                                <th>#</th>
                                <th>Order Date</th>
                                <th>Type</th>
                                <th>Customer Name</th>
                                <th>Pig Details</th>
                                <th>Additionals</th>
                                <th>Weight (KG)</th>
                                <th>Delivery Date</th>
                                <th>Amount</th>
                                <th>Status</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($display_logs as $index => $log): ?>
                                <?php
                                // Build additionals list
                                $additionals = [];
                                if ($log['include_laman_loob']) $additionals[] = 'Laman Loob';
                                if ($log['include_boopes']) $additionals[] = 'Boopes';
                                if ($log['include_dinuguan']) $additionals[] = 'Dinuguan';
                                $additionals_text = !empty($additionals) ? implode(', ', $additionals) : 'None';
                                
                                // Determine payment status for display
                                $payment_display = $log['payment_status'] === 'paid' ? 'Paid' : 'Pending';
                                if (isset($log['payment_type']) && $log['payment_type'] === 'down_payment' && $log['payment_status'] === 'paid') {
                                    $payment_display = 'Down Payment';
                                }

                                // Friendly order status label
                                $status_labels = [
                                    'pending'          => 'Pending',
                                    'confirmed'        => 'Confirmed',
                                    'preparing'        => 'Ready to Cook',
                                    'cost_computed'    => 'Cost Computed',
                                    'cooking'          => 'Cooking 🔥',
                                    'ready_for_pickup' => 'Ready for Pickup',
                                    'delivering'       => 'Delivering 🛵',
                                    'completed'        => 'Completed ✓',
                                    'cancelled'        => 'Cancelled',
                                ];
                                $friendly_status = $status_labels[$log['order_status']] ?? ucfirst($log['order_status']);

                                // Payment badge colour
                                $pay_color = match($log['payment_status']) {
                                    'paid'           => '#155724',
                                    'partially_paid' => '#0c5460',
                                    default          => '#721c24',
                                };
                                $pay_bg = match($log['payment_status']) {
                                    'paid'           => '#d4edda',
                                    'partially_paid' => '#d1ecf1',
                                    default          => '#f8d7da',
                                };
                                $payment_display = match($log['payment_status']) {
                                    'paid'           => 'Paid ✓',
                                    'partially_paid' => 'Partial',
                                    'refunded'       => 'Refunded',
                                    default          => 'Unpaid',
                                };
                                // Override for down-payment pig orders
                                if (isset($log['payment_type']) && $log['payment_type'] === 'down_payment' && $log['payment_status'] === 'paid') {
                                    $payment_display = 'Down Payment';
                                }
                                ?>
                                <tr>
                                    <td><?php echo $index + 1; ?></td>
                                    <td><small><?php echo date('M d, Y', strtotime($log['order_date'])); ?></small></td>
                                    <td>
                                        <?php if ($log['source_type'] === 'LECHON_ORDER'): ?>
                                            <span style="display:inline-block;padding:3px 9px;border-radius:20px;font-size:.72rem;font-weight:700;background:#fce4ec;color:#880e4f;">🍖 Marketplace</span>
                                        <?php else: ?>
                                            <span style="display:inline-block;padding:3px 9px;border-radius:20px;font-size:.72rem;font-weight:700;background:#e3f2fd;color:#1565c0;">🐷 Pig Order</span>
                                        <?php endif; ?>
                                    </td>
                                    <td><strong><?php echo htmlspecialchars($log['customer_name']); ?></strong>
                                        <?php if (!empty($log['customer_inquiry']) && $log['source_type'] === 'LECHON_ORDER'): ?>
                                            <br><span style="font-size: 0.75rem; color: #666; font-style: italic;">"<?php echo htmlspecialchars($log['customer_inquiry']); ?>"</span>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <div style="font-size: 0.9rem;">
                                            <?php if ($log['source_type'] === 'PIG_ORDER'): ?>
                                                <strong>Tag:</strong> <?php echo htmlspecialchars($log['pig_tag_id']); ?><br>
                                                <strong>Pen:</strong> <?php echo htmlspecialchars($log['pin_number']); ?>
                                            <?php else: ?>
                                                <strong>Product:</strong> <?php echo htmlspecialchars($log['pig_tag_id']); ?><br>
                                                <strong>Type:</strong> <?php echo htmlspecialchars($log['pin_number']); ?>
                                            <?php endif; ?>
                                        </div>
                                    </td>
                                    <td><?php echo $additionals_text; ?></td>
                                    <td><strong><?php echo number_format($log['weight_kg'], 1); ?></strong></td>
                                    <td>
                                        <input type="date" 
                                               class="delivery-date-input" 
                                               id="delivery_date_<?php echo $log['order_number']; ?>"
                                               value="<?php echo $log['delivery_date'] ?? ''; ?>"
                                               readonly
                                               title="Delivery date is automatically set from pickup date">
                                    </td>
                                    <td><strong>₱<?php echo number_format($log['total_amount'], 2); ?></strong></td>
                                    <td>
                                        <span class="status-badge status-<?php echo $log['order_status']; ?>">
                                            <?php echo $friendly_status; ?>
                                        </span>
                                        <div style="margin-top:5px;">
                                            <span style="display:inline-block;padding:3px 9px;border-radius:20px;font-size:.72rem;font-weight:700;background:<?php echo $pay_bg; ?>;color:<?php echo $pay_color; ?>;">
                                                <?php echo $payment_display; ?>
                                            </span>
                                        </div>
                                    </td>
                                    <td>
                                        <?php if ($log['order_status'] === 'pending' && $log['source_type'] === 'LECHON_ORDER'): ?>
                                            <!-- Pending lechon orders: Show Confirm/Decline buttons -->
                                            <form method="POST" action="/livestock-owner/confirm-lechon-order" style="display: inline-block; width: 100%; margin-bottom: 3px;">
                                                <input type="hidden" name="order_id" value="<?php echo $log['lechon_order_id'] ?? 0; ?>">
                                                <button type="submit" class="btn btn-sm btn-success" style="display: block; width: 100%;">
                                                    ✓ Confirm Order
                                                </button>
                                            </form>
                                            <form method="POST" action="/livestock-owner/decline-lechon-order" style="display: inline-block; width: 100%;">
                                                <input type="hidden" name="order_id" value="<?php echo $log['lechon_order_id'] ?? 0; ?>">
                                                <button type="submit" class="btn btn-sm btn-secondary" style="display: block; width: 100%; background: #dc3545; border-color: #dc3545;">
                                                    ✕ Decline
                                                </button>
                                            </form>
                                        <?php elseif (in_array($log['order_status'], ['confirmed', 'preparing', 'cooking', 'delivering']) && !empty($log['delivery_date'])): ?>
                                            <!-- Active orders: Show Send to Logistics + Schedule/View -->
                                            <?php if (in_array($log['order_status'], ['confirmed', 'preparing', 'cooking'])): ?>
                                            <button class="btn-logistics" onclick="sendToLogistics('<?php echo htmlspecialchars($log['order_number']); ?>', '<?php echo $log['source_type'] === 'LECHON_ORDER' ? ($log['lechon_order_id'] ?? 0) : 0; ?>', '<?php echo $log['source_type']; ?>')">
                                                 Send to Logistics
                                            </button>
                                            <?php else: ?>
                                            <button class="btn-logistics" disabled title="Already sent to logistics">
                                                ✓ Sent to Logistics
                                            </button>
                                            <?php endif; ?>
                                            <button class="btn btn-sm <?php echo !empty($log['date_to_butcher']) ? 'btn-secondary' : 'btn-success'; ?>" 
                                                <?php if (!empty($log['date_to_butcher'])): ?>
                                                    disabled title="Already scheduled: <?php echo date('M d, Y', strtotime($log['date_to_butcher'])); ?>"
                                                <?php else: ?>
                                                    onclick="setButcherSchedule('<?php echo $log['order_number']; ?>', '<?php echo $log['date_to_butcher'] ?? ''; ?>', '<?php echo $log['time_to_butcher'] ?? ''; ?>', '<?php echo $log['start_time'] ?? ''; ?>', '<?php echo $log['end_time'] ?? ''; ?>', '<?php echo $log['delivery_date'] ?? ''; ?>')"
                                                <?php endif; ?>
                                                style="display: block; width: 100%; margin-bottom: 3px;">
                                                <?php echo !empty($log['date_to_butcher']) ? '✔ Scheduled' : 'Schedule'; ?>
                                            </button>
                                            <button class="btn btn-sm btn-info" onclick="showOrderDetails(<?php echo htmlspecialchars(json_encode($log)); ?>)" style="display: block; width: 100%;">
                                                View Details
                                            </button>
                                        <?php else: ?>
                                            <!-- Confirmed/Preparing orders: Show Schedule/View buttons -->
                                            <button class="btn btn-sm <?php echo !empty($log['date_to_butcher']) ? 'btn-secondary' : 'btn-success'; ?>"
                                                <?php if (!empty($log['date_to_butcher'])): ?>
                                                    disabled title="Already scheduled: <?php echo date('M d, Y', strtotime($log['date_to_butcher'])); ?>"
                                                <?php else: ?>
                                                    onclick="setButcherSchedule('<?php echo $log['order_number']; ?>', '<?php echo $log['date_to_butcher'] ?? ''; ?>', '<?php echo $log['time_to_butcher'] ?? ''; ?>', '<?php echo $log['start_time'] ?? ''; ?>', '<?php echo $log['end_time'] ?? ''; ?>', '<?php echo $log['delivery_date'] ?? ''; ?>')"
                                                <?php endif; ?>
                                                style="display: block; width: 100%; margin-bottom: 3px;">
                                                <?php echo !empty($log['date_to_butcher']) ? '✔ Scheduled' : 'Schedule'; ?>
                                            </button>
                                            <button class="btn btn-sm btn-info" onclick="showOrderDetails(<?php echo htmlspecialchars(json_encode($log)); ?>)" style="display: block; width: 100%;">
                                                View Details
                                            </button>
                                        <?php endif; ?>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                <?php endif; ?>
            </div>
            </div>
            <!-- END LECHON PRODUCTION TAB -->

            <!-- OTHER ORDERS TAB CONTENT -->
            <div id="tab-other" class="tab-content <?php echo $active_tab === 'other' ? 'active' : ''; ?>">
                <?php 
                // Use other_orders_logs for this tab
                $display_logs_other = array_values($other_orders_logs);
                $totalOrdersOther = count($display_logs_other);
                $totalRevenueOther = array_sum(array_column($display_logs_other, 'total_amount'));
                ?>

                <!-- Summary Cards for Other Orders -->
                <div class="tl-summary">
                    <div class="tl-sum-card">
                        <div class="val"><?php echo $totalOrdersOther; ?></div>
                        <div class="lbl">Total Other Orders</div>
                    </div>
                    <div class="tl-sum-card">
                        <div class="val">₱<?php echo number_format($totalRevenueOther, 2); ?></div>
                        <div class="lbl">Total Revenue</div>
                    </div>
                </div>

                <!-- Other Orders Table -->
                <div class="tl-table-container">
                    <?php if (empty($display_logs_other)): ?>
                        <div class="empty-state">
                            <h3>No Other Orders Found</h3>
                            <p>No other orders (Pancit, Dinuguan, etc.) have been placed yet.</p>
                        </div>
                    <?php else: ?>
                        <table class="tl-table">
                            <thead>
                                <tr>
                                    <th>#</th>
                                    <th>Order Date</th>
                                    <th>Customer Name</th>
                                    <th>Product</th>
                                    <th>Category</th>
                                    <th>Delivery Date</th>
                                    <th>Amount</th>
                                    <th>Status</th>
                                    <th>Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($display_logs_other as $index => $log): ?>
                                    <?php
                                    // Determine payment status for display
                                    $payment_display = $log['payment_status'] === 'paid' ? 'Paid' : 'Pending';
                                    ?>
                                    <tr>
                                        <td><?php echo $index + 1; ?></td>
                                        <td>
                                            <?php echo date('M d, Y', strtotime($log['order_date'])); ?>
                                            <br><span style="font-size: 0.7rem; color: #059669; background: #d1fae5; padding: 2px 6px; border-radius: 4px;">Marketplace</span>
                                        </td>
                                        <td>
                                            <strong><?php echo htmlspecialchars($log['customer_name']); ?></strong>
                                            <?php if (!empty($log['customer_inquiry'])): ?>
                                                <br><span style="font-size: 0.75rem; color: #666; font-style: italic;">"<?php echo htmlspecialchars($log['customer_inquiry']); ?>"</span>
                                            <?php endif; ?>
                                        </td>
                                        <td><strong><?php echo htmlspecialchars($log['pig_tag_id']); ?></strong></td>
                                        <td><?php echo htmlspecialchars($log['pin_number']); ?></td>
                                        <td>
                                            <input type="date" 
                                                   class="delivery-date-input" 
                                                   value="<?php echo $log['delivery_date'] ?? ''; ?>"
                                                   readonly
                                                   title="Delivery date">
                                        </td>
                                        <td><strong>₱<?php echo number_format($log['total_amount'], 2); ?></strong></td>
                                        <td>
                                            <span class="status-badge status-<?php echo $log['order_status']; ?>">
                                                <?php echo ucfirst($log['order_status']); ?>
                                            </span>
                                            <div style="font-size: 0.8rem; margin-top: 4px; color: <?php echo $log['payment_status'] === 'paid' ? '#28a745' : '#dc3545'; ?>;">
                                                <?php echo $payment_display; ?>
                                            </div>
                                        </td>
                                        <td>
                                            <?php if ($log['order_status'] === 'pending'): ?>
                                                <form method="POST" action="/livestock-owner/confirm-lechon-order" style="margin-bottom:4px;">
                                                    <input type="hidden" name="order_id" value="<?php echo $log['lechon_order_id'] ?? 0; ?>">
                                                    <button type="submit" class="btn btn-sm btn-success" style="display: block; width: 100%;">
                                                        ✓ Confirm
                                                    </button>
                                                </form>
                                                <form method="POST" action="/livestock-owner/decline-lechon-order">
                                                    <input type="hidden" name="order_id" value="<?php echo $log['lechon_order_id'] ?? 0; ?>">
                                                    <button type="submit" class="btn btn-sm btn-secondary" style="display: block; width: 100%; background: #dc3545; border-color: #dc3545;">
                                                        ✕ Decline
                                                    </button>
                                                </form>
                                            <?php elseif (in_array($log['order_status'], ['confirmed', 'preparing', 'cooking', 'delivering']) && !empty($log['delivery_date'])): ?>
                                                <?php if (in_array($log['order_status'], ['confirmed', 'preparing', 'cooking'])): ?>
                                                <button class="btn-logistics" onclick="sendToLogistics('<?php echo htmlspecialchars($log['order_number']); ?>', '<?php echo $log['lechon_order_id'] ?? 0; ?>', 'LECHON_ORDER')">
                                                    🚚 Send to Logistics
                                                </button>
                                                <?php else: ?>
                                                <button class="btn-logistics" disabled title="Already sent to logistics">
                                                    ✓ Sent to Logistics
                                                </button>
                                                <?php endif; ?>
                                                <button class="btn btn-sm btn-info" onclick="showOrderDetails(<?php echo htmlspecialchars(json_encode($log)); ?>)" style="display: block; width: 100%;">
                                                    View Details
                                                </button>
                                            <?php else: ?>
                                                <button class="btn btn-sm btn-info" onclick="showOrderDetails(<?php echo htmlspecialchars(json_encode($log)); ?>)" style="display: block; width: 100%;">
                                                    View Details
                                                </button>
                                            <?php endif; ?>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    <?php endif; ?>
                </div>
            </div>
            <!-- END OTHER ORDERS TAB -->

        </div>
    </main>
</div>

<!-- Order Details Modal -->
<div id="orderModal" class="modal">
    <div class="modal-content">
        <div class="modal-header">
            <h3>Order Details</h3>
            <span class="close" onclick="closeModal()">&times;</span>
        </div>
        <div class="modal-body" id="modalBody">
            <!-- Order details will be populated here -->
        </div>
    </div>
</div>

<!-- Set Butcher Schedule Modal -->
<div id="scheduleModal" class="modal">
    <div class="modal-content">
        <div class="modal-header">
            <h3>Set Schedule</h3>
            <span class="close" onclick="closeScheduleModal()">&times;</span>
        </div>
        <div class="modal-body">
            <div id="scheduleOrderInfo"></div>
            <div style="margin-top: 20px;">
                <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 15px; margin-bottom: 20px;">
                    <div>
                        <label for="scheduleDate"><strong>Date to Butcher:</strong></label>
                        <input type="date" id="scheduleDate" class="form-control" min="<?php echo date('Y-m-d'); ?>" style="width: 100%; padding: 8px; margin-top: 5px;">
                    </div>
                     <div style="margin-bottom: 20px;">
                    <label for="scheduleDeliveryDate"><strong>Delivery Date:</strong></label>
                    <input type="date" id="scheduleDeliveryDate" class="form-control" readonly style="width: 100%; padding: 8px; margin-top: 5px; background-color: #f8f9fa;" title="Delivery date is automatically set from pickup date">
                </div>
                    <div>
                        <label for="scheduleTime"><strong>Time to Butcher:</strong></label>
                        <input type="time" id="scheduleTime" class="form-control" style="width: 100%; padding: 8px; margin-top: 5px;">
                    </div>
                            </div>
                <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 15px; margin-bottom: 20px;">
                    <div>
                        <label for="scheduleStartCook"><strong>Start Cook Time:</strong></label>
                        <input type="time" id="scheduleStartCook" class="form-control" style="width: 100%; padding: 8px; margin-top: 5px;">
                    </div>
                    <div>
                        <label for="scheduleEndCook"><strong>End Cook Time:</strong></label>
                        <input type="time" id="scheduleEndCook" class="form-control" style="width: 100%; padding: 8px; margin-top: 5px;">
                    </div>
                </div>
                <div style="text-align: right;">
                    <button type="button" class="btn btn-secondary" onclick="closeScheduleModal()" style="margin-right: 10px;">Cancel</button>
                    <button type="button" class="btn btn-success" onclick="saveAllSchedule()" id="saveScheduleBtn">Save Schedule</button>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
// Tab switching function
function switchTab(tabName) {
    // Hide all tab contents
    document.querySelectorAll('.tab-content').forEach(tab => {
        tab.classList.remove('active');
    });
    
    // Remove active class from all tab buttons
    document.querySelectorAll('.tab-btn').forEach(btn => {
        btn.classList.remove('active');
    });
    
    // Show selected tab content
    document.getElementById('tab-' + tabName).classList.add('active');
    
    // Add active class to clicked button
    event.target.closest('.tab-btn').classList.add('active');
    
    // Update URL parameter without reload
    const url = new URL(window.location);
    url.searchParams.set('tab', tabName);
    window.history.pushState({}, '', url);
}

function showOrderDetails(order) {
    const modal = document.getElementById('orderModal');
    const modalBody = document.getElementById('modalBody');
    
    // Build additionals list
    const additionals = [];
    if (order.include_laman_loob) additionals.push(`Laman Loob: ₱${parseFloat(order.laman_loob_price).toFixed(2)}`);
    if (order.include_boopes) additionals.push(`Boopes: ₱${parseFloat(order.boopes_price).toFixed(2)}`);
    if (order.include_dinuguan) additionals.push(`Dinuguan: ₱${parseFloat(order.dinuguan_price).toFixed(2)}`);
    
    const additionalsText = additionals.length > 0 ? additionals.join(', ') : 'None';
    
    // Calculate total amount
    const totalAmount = parseFloat(order.total_amount);
    
    // Payment status display
    let paymentDisplay = order.payment_status === 'paid' ? 'Paid' : 'Pending';
    if (order.payment_type === 'down_payment' && order.payment_status === 'paid') {
        paymentDisplay = 'Down Payment';
    }
    
    modalBody.innerHTML = `
        <div class="detail-row">
            <div class="detail-label">Order Number:</div>
            <div class="detail-value">${order.order_number}</div>
        </div>
        <div class="detail-row">
            <div class="detail-label">Customer Name:</div>
            <div class="detail-value">${order.customer_name}</div>
        </div>
        <div class="detail-row">
            <div class="detail-label">Order Date:</div>
            <div class="detail-value">${new Date(order.order_date).toLocaleDateString()}</div>
        </div>
        <div class="detail-row">
            <div class="detail-label">Pig Tag ID:</div>
            <div class="detail-value">${order.pig_tag_id}</div>
        </div>
        <div class="detail-row">
            <div class="detail-label">Pen Number:</div>
            <div class="detail-value">${order.pin_number}</div>
        </div>
        <div class="detail-row">
            <div class="detail-label">Weight:</div>
            <div class="detail-value">${parseFloat(order.weight_kg).toFixed(1)} kg</div>
        </div>
        <div class="detail-row">
            <div class="detail-label">Base Amount:</div>
            <div class="detail-value">₱${parseFloat(order.pig_base_amount).toFixed(2)}</div>
        </div>
        <div class="detail-row">
            <div class="detail-label">Additionals:</div>
            <div class="detail-value">${additionalsText}</div>
        </div>
        <div class="detail-row">
            <div class="detail-label">Delivery Method:</div>
            <div class="detail-value">${order.delivery_method}</div>
        </div>
        <div class="detail-row">
            <div class="detail-label">Delivery Fee:</div>
            <div class="detail-value">₱${parseFloat(order.delivery_fee || 0).toFixed(2)}</div>
        </div>
        <div class="detail-row">
            <div class="detail-label">Labor Cost:</div>
            <div class="detail-value">₱${parseFloat(order.labor_cost || 0).toFixed(2)}</div>
        </div>
        <div class="detail-row">
            <div class="detail-label">Total Amount:</div>
            <div class="detail-value"><strong>₱${totalAmount.toFixed(2)}</strong></div>
        </div>
        <div class="detail-row">
            <div class="detail-label">Payment Status:</div>
            <div class="detail-value">${paymentDisplay}</div>
        </div>
        <div class="detail-row">
            <div class="detail-label">Order Status:</div>
            <div class="detail-value">${order.order_status.charAt(0).toUpperCase() + order.order_status.slice(1)}</div>
        </div>
        ${order.customer_inquiry ? `
        <div class="detail-row">
            <div class="detail-label">Customer Message:</div>
            <div class="detail-value" style="color: #059669; font-style: italic;">"${order.customer_inquiry}"</div>
        </div>
        ` : ''}
    `;
    
    modal.style.display = 'block';
}

function closeModal() {
    document.getElementById('orderModal').style.display = 'none';
}

function updateCookingTime(orderNumber, type, value) {
    // AJAX call to update cooking time in database
    fetch('/livestock-owner/update-cooking-time', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/json',
        },
        body: JSON.stringify({
            order_number: orderNumber,
            type: type, // 'start', 'end', 'butcher_date', or 'butcher_time'
            time: value
        })
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            console.log('Cooking time updated successfully');
        } else {
            console.error('Error updating cooking time:', data.message);
            alert('Error updating cooking time: ' + data.message);
        }
    })
    .catch(error => {
        console.error('Error:', error);
        alert('Error updating cooking time');
    });
}

// Close modal when clicking outside of it
window.onclick = function(event) {
    const modal = document.getElementById('orderModal');
    const scheduleModal = document.getElementById('scheduleModal');
    if (event.target == modal) {
        closeModal();
    }
    if (event.target == scheduleModal) {
        closeScheduleModal();
    }
}

let currentOrderNumber = null;

function setButcherSchedule(orderNumber, currentDate, currentTime, currentStartCook, currentEndCook, deliveryDate) {
    console.log('setButcherSchedule called with:', orderNumber, currentDate, currentTime, currentStartCook, currentEndCook, deliveryDate);
    
    currentOrderNumber = orderNumber;
    
    // Show order info
    document.getElementById('scheduleOrderInfo').innerHTML = `
        <div style="background-color: #f8f9fa; padding: 15px; border-radius: 8px; margin-bottom: 15px;">
            <h4 style="margin: 0 0 10px 0; color: #333;">Order: ${orderNumber}</h4>
            <p style="margin: 0; color: #666;">Set the butcher schedule and cooking times for this order</p>
        </div>
    `;
    
    // Pre-fill current values if they exist
    document.getElementById('scheduleDate').value = currentDate || '';
    document.getElementById('scheduleTime').value = currentTime || '';
    document.getElementById('scheduleStartCook').value = currentStartCook || '';
    document.getElementById('scheduleEndCook').value = currentEndCook || '';
    document.getElementById('scheduleDeliveryDate').value = deliveryDate || '';
    
    // Show modal
    const modal = document.getElementById('scheduleModal');
    console.log('Modal element:', modal);
    
    if (modal) {
        modal.style.display = 'block';
        console.log('Modal should be visible now');
    } else {
        console.error('Modal element not found!');
        alert('Error: Modal not found. Please refresh the page.');
    }
}

function closeScheduleModal() {
    document.getElementById('scheduleModal').style.display = 'none';
    currentOrderNumber = null;
}

function saveAllSchedule() {
    if (!currentOrderNumber) {
        alert('Error: No order selected');
        return;
    }
    
    const date = document.getElementById('scheduleDate').value;
    const time = document.getElementById('scheduleTime').value;
    const startCook = document.getElementById('scheduleStartCook').value;
    const endCook = document.getElementById('scheduleEndCook').value;
    
    if (!date || !time) {
        alert('Please select both butcher date and time.');
        return;
    }
    
    console.log('Saving schedule for order:', currentOrderNumber);
    console.log('Date:', date, 'Time:', time, 'Start Cook:', startCook, 'End Cook:', endCook);
    
    // Show loading message
    const saveButton = document.getElementById('saveScheduleBtn');
    const originalText = saveButton.textContent;
    saveButton.textContent = 'Saving...';
    saveButton.disabled = true;
    
    // Array to store all the save operations (only for non-empty values)
    const saveOperations = [
        { type: 'butcher_date', value: date },
        { type: 'butcher_time', value: time }
    ];
    
    // Add cooking times only if they have values
    if (startCook && startCook.trim() !== '') {
        saveOperations.push({ type: 'start', value: startCook });
    }
    if (endCook && endCook.trim() !== '') {
        saveOperations.push({ type: 'end', value: endCook });
    }
    
    // Execute all save operations sequentially
    let currentIndex = 0;
    
    function saveNext() {
        if (currentIndex >= saveOperations.length) {
            // All operations completed successfully
            closeScheduleModal();
            alert('Schedule saved successfully! This order is now available for the pig slaughter team.');
            location.reload();
            return;
        }
        
        const operation = saveOperations[currentIndex];
        
        console.log(`Saving ${operation.type}: "${operation.value}"`);
        
        fetch('/livestock-owner/update-cooking-time', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
            },
            body: JSON.stringify({
                order_number: currentOrderNumber,
                type: operation.type,
                time: operation.value
            })
        })
        .then(response => response.json())
        .then(data => {
            console.log(`Response for ${operation.type}:`, data);
            if (data.success) {
                console.log(`${operation.type} saved successfully`);
                currentIndex++;
                saveNext(); // Process next operation
            } else {
                throw new Error(`Failed to save ${operation.type}: ${data.message}`);
            }
        })
        .catch(error => {
            console.error('Error:', error);
            alert('Error saving schedule: ' + error.message);
            saveButton.textContent = originalText;
            saveButton.disabled = false;
        });
    }
    
    // Start the save operations
    saveNext();
}

// New functions for pig processing stages

function markAsSlaughtered(orderNumber) {
    if (confirm('Mark this pig as slaughtered? This will update the order status to "preparing".')) {
        // Update order status to preparing (slaughtered)
        fetch('/livestock-owner/update-order-status', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
            },
            body: JSON.stringify({
                order_number: orderNumber,
                status: 'preparing'
            })
        })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                alert('Pig marked as slaughtered! Status updated to "preparing".');
                location.reload();
            } else {
                alert('Error updating status: ' + data.message);
            }
        })
        .catch(error => {
            console.error('Error:', error);
            alert('Error updating status');
        });
    }
}

function setStartCookTime(orderNumber) {
    const currentTime = new Date().toTimeString().slice(0, 5); // Get current time HH:MM
    
    if (confirm('Start cooking this lechon now? Current time: ' + currentTime)) {
        // Set start cooking time and update status to cooking
        Promise.all([
            fetch('/livestock-owner/update-cooking-time', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({
                    order_number: orderNumber,
                    type: 'start',
                    time: currentTime
                })
            }),
            fetch('/livestock-owner/update-order-status', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({
                    order_number: orderNumber,
                    status: 'cooking'
                })
            })
        ])
        .then(responses => Promise.all(responses.map(r => r.json())))
        .then(results => {
            if (results.every(r => r.success)) {
                alert('Cooking started! Time recorded: ' + currentTime);
                location.reload();
            } else {
                alert('Error starting cook time');
            }
        })
        .catch(error => {
            console.error('Error:', error);
            alert('Error starting cook time');
        });
    }
}

function setEndCookTime(orderNumber) {
    const currentTime = new Date().toTimeString().slice(0, 5); // Get current time HH:MM
    
    if (confirm('Finish cooking this lechon? Current time: ' + currentTime)) {
        // Set end cooking time and update status to delivering
        Promise.all([
            fetch('/livestock-owner/update-cooking-time', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({
                    order_number: orderNumber,
                    type: 'end',
                    time: currentTime
                })
            }),
            fetch('/livestock-owner/update-order-status', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({
                    order_number: orderNumber,
                    status: 'delivering'
                })
            })
        ])
        .then(responses => Promise.all(responses.map(r => r.json())))
        .then(results => {
            if (results.every(r => r.success)) {
                alert('Cooking finished! Lechon is ready for delivery. Time: ' + currentTime);
                location.reload();
            } else {
                alert('Error ending cook time');
            }
        })
        .catch(error => {
            console.error('Error:', error);
            alert('Error ending cook time');
        });
    }
}

// ── Send to Logistics ──────────────────────────────────────────────────────
function sendToLogistics(orderNumber, lechonOrderId, sourceType) {
    if (!confirm('Send this order to logistics for delivery?\n\nOrder: ' + orderNumber)) return;

    const btn = event.currentTarget;
    const originalHTML = btn.innerHTML;
    btn.disabled = true;
    btn.innerHTML = '⏳ Sending...';

    fetch('/livestock-owner/send-to-logistics', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({
            order_number:    orderNumber,
            lechon_order_id: lechonOrderId,
            source_type:     sourceType
        })
    })
    .then(r => r.json())
    .then(data => {
        if (data.success) {
            btn.innerHTML = '✓ Sent to Logistics';
            btn.style.background = '#28a745';
            setTimeout(() => location.reload(), 800);
        } else {
            alert('Error: ' + (data.message || 'Could not send to logistics.'));
            btn.disabled = false;
            btn.innerHTML = originalHTML;
        }
    })
    .catch(() => {
        alert('Network error. Please try again.');
        btn.disabled = false;
        btn.innerHTML = originalHTML;
    });
}
</script>
</body>
</html>
<?php
/**
 * Lechonero Schedule - View and manage cooking orders
 * Shows pigs ready for cooking and allows completion tracking
 */
$currentPage = 'lechonero-schedule';
$user = $_SESSION['user'] ?? null;
if (!$user) { header('Location: /login'); exit; }

global $conn;

// Get orders ready for cooking (status: preparing = slaughtered and ready to cook)
// Source 1: pig-based orders via swine_order_status
// ── Two separate queries instead of UNION to avoid cross-table collation conflicts ──────────

// Query 1: pig-based orders (swine_order_status / order_total_cost)
// Note: lechon_status is NOT joined here — it uses a different collation (general_ci vs unicode_ci)
// cooking_status is derived from order_status alone; completed quality data loaded separately if needed
$sql_pig = "SELECT DISTINCT
            otc.order_number,
            otc.customer_name,
            COALESCE(pd.pig_tag_id, otc.pig_tag_id)  AS pig_tag_id,
            COALESCE(pp.cage_number, otc.pin_number)  AS pin_number,
            sos.weight_kg,
            sos.order_status,
            sos.pickup_date  AS delivery_date,
            po.created_at    AS order_date,
            cs.date_to_butcher,
            cs.time_to_butcher,
            cs.start_time    AS cook_start_time,
            cs.end_time      AS cook_end_time,
            cs.initial_weight,
            cs.final_weight,
            cs.estimated_cook_time,
            cs.actual_cook_time,
            cs.cooking_method,
            cs.initial_notes,
            NULL             AS lechon_status_id,
            NULL             AS cooked_image,
            NULL             AS internal_temperature,
            NULL             AS skin_texture,
            NULL             AS meat_tenderness,
            NULL             AS completed_at,
            'PIG_ORDER'      AS source_type,
            NULL             AS lechon_order_id,
            CASE
                WHEN sos.order_status IN ('delivering','completed') THEN 'completed'
                WHEN sos.order_status = 'cooking'   THEN 'cooking'
                WHEN sos.order_status IN ('preparing','confirmed') THEN 'ready_to_cook'
                ELSE 'not_ready'
            END AS cooking_status
        FROM order_total_cost otc
        LEFT JOIN swine_order_status sos ON sos.id = otc.swine_order_id
        LEFT JOIN placed_orders po       ON po.swine_order_id = sos.id
        LEFT JOIN cooking_schedule cs    ON cs.order_number = otc.order_number
        LEFT JOIN pig_details pd         ON pd.id = sos.pig_detail_id
        LEFT JOIN pig_pins pp            ON pp.id = pd.cage_id
        WHERE sos.payment_status = 'paid'
          AND sos.order_status NOT IN ('pending','cancelled')";

// Query 2: marketplace lechon orders (lechon_orders only — no lechon_status join to avoid collation conflict)
$sql_lechon = "SELECT
            lo.order_number,
            u.name           AS customer_name,
            lo.listing_name  AS pig_tag_id,
            lo.category      AS pin_number,
            lo.weight_kg,
            lo.order_status,
            lo.pickup_date   AS delivery_date,
            lo.created_at    AS order_date,
            NULL             AS date_to_butcher,
            NULL             AS time_to_butcher,
            NULL             AS cook_start_time,
            NULL             AS cook_end_time,
            NULL             AS initial_weight,
            NULL             AS final_weight,
            NULL             AS estimated_cook_time,
            NULL             AS actual_cook_time,
            NULL             AS cooking_method,
            NULL             AS initial_notes,
            NULL             AS lechon_status_id,
            NULL             AS cooked_image,
            NULL             AS internal_temperature,
            NULL             AS skin_texture,
            NULL             AS meat_tenderness,
            NULL             AS completed_at,
            'LECHON_ORDER'   AS source_type,
            lo.id            AS lechon_order_id,
            CASE
                WHEN lo.order_status IN ('delivering','completed') THEN 'completed'
                WHEN lo.order_status = 'cooking'   THEN 'cooking'
                WHEN lo.order_status IN ('preparing','confirmed') THEN 'ready_to_cook'
                ELSE 'not_ready'
            END AS cooking_status
        FROM lechon_orders lo
        LEFT JOIN users u ON u.id = lo.customer_id
        WHERE lo.payment_status = 'paid'
          AND lo.order_status NOT IN ('pending','cancelled')";

$orders = [];
$sql_error = null;

// Run pig query
$stmt = $conn->prepare($sql_pig);
if (!$stmt) {
    $sql_error = $conn->error;
    error_log("schedule.php pig SQL error: " . $conn->error);
} else {
    if (!$stmt->execute()) {
        $sql_error = $stmt->error;
        error_log("schedule.php pig execute error: " . $stmt->error);
    } else {
        $orders = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
    }
    $stmt->close();
}

// Run lechon query (only if lechon_orders table exists)
$tbl_check = $conn->query("SHOW TABLES LIKE 'lechon_orders'");
if ($tbl_check && $tbl_check->num_rows > 0) {
    $stmt2 = $conn->prepare($sql_lechon);
    if ($stmt2) {
        if ($stmt2->execute()) {
            $lo_rows = $stmt2->get_result()->fetch_all(MYSQLI_ASSOC);
            $orders  = array_merge($orders, $lo_rows);
        } else {
            error_log("schedule.php lechon execute error: " . $stmt2->error);
        }
        $stmt2->close();
    } else {
        error_log("schedule.php lechon prepare error: " . $conn->error);
    }
}

// Sort: soonest delivery first
usort($orders, function($a, $b) {
    $aDate = !empty($a['delivery_date']) ? strtotime($a['delivery_date']) : PHP_INT_MAX;
    $bDate = !empty($b['delivery_date']) ? strtotime($b['delivery_date']) : PHP_INT_MAX;
    return $aDate - $bDate;
});

// ── Tab 1: Lechon cooking orders (pig orders + WHOLE_LECHON marketplace) ──────
// These are the orders the lechonero actually roasts — has cooking workflow
$ready_orders    = [];   // ready_to_cook
$cooking_orders  = [];   // currently cooking
$completed_orders = [];  // done

// ── Tab 2: Other marketplace orders (Pancit, Dinuguan, Belly, etc.) ──────────
// These are prepared separately — no cooking start/complete flow needed
$other_orders = [];

foreach ($orders as $order) {
    $isOther = ($order['source_type'] === 'LECHON_ORDER')
               && !in_array($order['pin_number'], ['WHOLE_LECHON', 'WHOLE_PACKAGES']);

    if ($isOther) {
        $other_orders[] = $order;
        continue;
    }

    // Lechon cooking tab
    if ($order['cooking_status'] === 'ready_to_cook') {
        $ready_orders[] = $order;
    } elseif ($order['cooking_status'] === 'cooking') {
        $cooking_orders[] = $order;
    } elseif ($order['cooking_status'] === 'completed') {
        $completed_orders[] = $order;
    } else {
        // still confirmed/active — treat as ready
        $ready_orders[] = $order;
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Lechonero Schedule - LechGO</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.8/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="/styles.css">
    <style>
        
        
        .content-wrapper {
            background: #f8f9fc;
            min-height: calc(100vh - 60px);
            border-radius: 20px 20px 0 0;
            margin-top: 60px;
            padding: 2rem;
        }
        
        .page-header {
            text-align: center;
            margin-bottom: 3rem;
            position: relative;
        }
        
        .page-header h1 {
            font-size: 2.5rem;
            font-weight: 700;
            background: linear-gradient(135deg, #ffffffff 100%, #ffffffff 100%);
            -webkit-background-clip: text;
            background-clip: text;
            margin-bottom: 0.5rem;
        }
        
        .page-header p {
            color: #6c757d;
            font-size: 1.1rem;
            margin: 0;
        }
        
        .section-header {
            display: flex;
            align-items: center;
            margin-bottom: 2rem;
            gap: 1rem;
        }
        
        .section-title {
            font-size: 1.5rem;
            font-weight: 600;
            color: #2d3748;
            margin: 0;
        }
        
        .section-count {
            background: linear-gradient(135deg, #cf2c2cff 0%, #cf2c2cff 100%);
            color: white;
            padding: 0.3rem 0.8rem;
            border-radius: 20px;
            font-size: 0.9rem;
            font-weight: 600;
        }
        
        .order-card {
            background: white;
            border-radius: 16px;
            box-shadow: 0 4px 20px rgba(0,0,0,0.08);
            margin-bottom: 1.5rem;
            transition: all 0.3s ease;
            border: 1px solid rgba(102, 126, 234, 0.1);
            overflow: hidden;
        }
        
        .order-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 8px 30px rgba(0,0,0,0.12);
            border-color: rgba(102, 126, 234, 0.2);
        }
        
        .card-header-custom {
            background: linear-gradient(135deg, #f30b0bff 0%, #e3e704ff 100%);
            color: white;
            padding: 0.75rem 1.2rem;
            border: none;
            position: relative;
            overflow: hidden;
        }
        
        .card-header-custom::before {
            content: '';
            position: absolute;
            top: 0;
            right: 0;
            width: 100px;
            height: 100px;
            background: rgba(255,255,255,0.1);
            border-radius: 50%;
            transform: translate(30px, -30px);
        }
        
        .customer-name {
            font-size: 1.3rem;
            font-weight: 700;
            margin-bottom: 0.3rem;
        }
        
        .order-number {
            font-size: 0.9rem;
            opacity: 0.9;
            font-weight: 500;
        }
        
        .card-body-custom {
            padding: 0.85rem 1.2rem;
        }
        
        .status-badge {
            padding: 0.35rem 1rem;
            border-radius: 25px;
            font-weight: 700;
            text-transform: uppercase;
            font-size: 0.75rem;
            letter-spacing: 0.5px;
            display: inline-block;
            margin-bottom: 0.6rem;
        }
        
        .status-ready {
            background: linear-gradient(135deg, #28a745, #20c997);
            color: white;
            box-shadow: 0 2px 10px rgba(40, 167, 69, 0.3);
        }
        
        .status-completed {
            background: linear-gradient(135deg, #007bff, #6f42c1);
            color: white;
            box-shadow: 0 2px 10px rgba(0, 123, 255, 0.3);
        }
        
        .info-grid {
            display: grid;
            grid-template-columns: 1fr 1fr 1fr 1fr;
            gap: 0.5rem;
            margin-bottom: 0.75rem;
        }
        
        .info-item {
            background: #f8f9fc;
            padding: 0.5rem 0.75rem;
            border-radius: 8px;
            border-left: 4px solid #667eea;
        }
        
        .info-label {
            font-size: 0.8rem;
            color: #6c757d;
            text-transform: uppercase;
            font-weight: 600;
            letter-spacing: 0.5px;
            margin-bottom: 0.3rem;
        }
        
        .info-value {
            font-size: 1rem;
            font-weight: 600;
            color: #2d3748;
        }
        
        .schedule-info {
            background: linear-gradient(135deg, #e3f2fd, #f3e5f5);
            padding: 0.5rem 0.8rem;
            border-radius: 8px;
            margin-bottom: 0.5rem;
            border: 1px solid rgba(102, 126, 234, 0.2);
        }
        
        .schedule-label {
            font-size: 0.8rem;
            color: #6c757d;
            text-transform: uppercase;
            font-weight: 600;
            margin-bottom: 0.5rem;
        }
        
        .schedule-value {
            font-size: 1.1rem;
            font-weight: 700;
            color: #2d3748;
        }
        
        .btn-cook {
            background: linear-gradient(135deg, #28a745, #20c997);
            border: none;
            color: white;
            padding: 0.8rem 2rem;
            border-radius: 25px;
            font-weight: 700;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            width: 100%;
            transition: all 0.3s ease;
            box-shadow: 0 4px 15px rgba(40, 167, 69, 0.3);
        }
        
        .btn-cook:hover {
            transform: translateY(-2px);
            box-shadow: 0 6px 20px rgba(40, 167, 69, 0.4);
            background: linear-gradient(135deg, #218838, #1e7e34);
        }
        
        .quality-info {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(120px, 1fr));
            gap: 0.8rem;
            margin-top: 1rem;
        }
        
        .quality-item {
            text-align: center;
            padding: 1rem 0.5rem;
            background: linear-gradient(135deg, #f8f9fc, #e3f2fd);
            border-radius: 12px;
            border: 1px solid rgba(102, 126, 234, 0.1);
        }
        
        .quality-item strong {
            display: block;
            color: #2d3748;
            font-size: 1rem;
            font-weight: 700;
            margin-bottom: 0.3rem;
        }
        
        .quality-item small {
            color: #6c757d;
            font-size: 0.8rem;
            text-transform: uppercase;
            font-weight: 600;
            letter-spacing: 0.5px;
        }
        
        .image-preview {
            max-width: 100%;
            max-height: 200px;
            border-radius: 12px;
            object-fit: cover;
            box-shadow: 0 4px 15px rgba(0,0,0,0.1);
            margin-bottom: 1rem;
        }
        
        .empty-state {
            text-align: center;
            padding: 4rem 2rem;
            background: white;
            border-radius: 16px;
            box-shadow: 0 4px 20px rgba(0,0,0,0.05);
            border: 2px dashed #dee2e6;
        }
        
        .empty-state .icon {
            font-size: 4rem;
            color: #dee2e6;
            margin-bottom: 1rem;
        }
        
        .empty-state h5 {
            color: #6c757d;
            font-weight: 600;
            margin-bottom: 0.5rem;
        }
        
        .empty-state p {
            color: #9ca3af;
            margin: 0;
        }
        
        .upload-area {
            border: 2px dashed #667eea;
            border-radius: 12px;
            padding: 2rem;
            text-align: center;
            background: linear-gradient(135deg, #f8f9fc, #e3f2fd);
            cursor: pointer;
            transition: all 0.3s ease;
        }
        
        .upload-area:hover {
            border-color: #5a67d8;
            background: linear-gradient(135deg, #e3f2fd, #f3e5f5);
        }
        
        .upload-area.dragover {
            border-color: #4c51bf;
            background: linear-gradient(135deg, #e3f2fd, #f3e5f5);
            transform: scale(1.02);
        }
        
        .upload-area i {
            font-size: 3rem;
            color: #667eea;
            margin-bottom: 1rem;
        }
        
        /* Modal Styling */
        .modal-content {
            border-radius: 16px;
            border: none;
            box-shadow: 0 10px 40px rgba(0,0,0,0.1);
        }
        
        .modal-header {
            background: linear-gradient(135deg, #f00101ff 0%, #f03d06ff 100%);
            color: white;
            border-radius: 16px 16px 0 0;
            padding: 1.5rem 2rem;
            border: none;
        }
        
        .modal-title {
            font-weight: 700;
            font-size: 1.3rem;
        }
        
        .btn-close {
            filter: invert(1);
            opacity: 0.8;
        }
        
        .modal-body {
            padding: 2rem;
        }
        
        .form-label {
            font-weight: 600;
            color: #2d3748;
            margin-bottom: 0.5rem;
        }
        
        .form-control, .form-select {
            border-radius: 8px;
            border: 1px solid #e2e8f0;
            padding: 0.75rem 1rem;
            transition: all 0.3s ease;
        }
        
        .form-control:focus, .form-select:focus {
            border-color: #667eea;
            box-shadow: 0 0 0 3px rgba(102, 126, 234, 0.1);
        }
        
        .btn-success {
            background: linear-gradient(135deg, #28a745, #20c997);
            border: none;
            padding: 0.7rem 1.5rem;
            border-radius: 8px;
            font-weight: 600;
        }
        
        .btn-secondary {
            background: #6c757d;
            border: none;
            padding: 0.7rem 1.5rem;
            border-radius: 8px;
            font-weight: 600;
        }
        
        /* Responsive Design */
        @media (max-width: 768px) {
            .content-wrapper {
                margin-top: 30px;
                padding: 1rem;
                border-radius: 15px 15px 0 0;
            }
            
            .page-header h1 {
                font-size: 2rem;
            }
            
            .info-grid {
                grid-template-columns: 1fr;
            }
            
            .quality-info {
                grid-template-columns: repeat(2, 1fr);
            }
        }
        
        /* Animation */
        @keyframes fadeInUp {
            from {
                opacity: 0;
                transform: translateY(30px);
            }
            to {
                opacity: 1;
                transform: translateY(0);
            }
        }
        
        .order-card {
            animation: fadeInUp 0.5s ease-out;
        }
        
        .order-card:nth-child(2) { animation-delay: 0.1s; }
        .order-card:nth-child(3) { animation-delay: 0.2s; }
        .order-card:nth-child(4) { animation-delay: 0.3s; }

        /* Tab Styling */
        .nav-tabs .nav-link {
            border: none !important;
            background: transparent;
            transition: all 0.3s ease;
            color: #6c757d !important;
            position: relative;
        }

        .nav-tabs .nav-link:hover {
            color: #cf2c2c !important;
            background: rgba(207, 44, 44, 0.05);
        }

        .nav-tabs .nav-link.active {
            color: #cf2c2c !important;
            background: transparent;
            font-weight: 700;
        }

        .nav-tabs .nav-link.active::after {
            display: none;
        }

        /* Tab Content Animation */
        .tab-pane {
            animation: fadeInTab 0.3s ease-in;
        }

        @keyframes fadeInTab {
            from {
                opacity: 0;
                transform: translateY(10px);
            }
            to {
                opacity: 1;
                transform: translateY(0);
            }
        }

        /* Table Responsive */
        .table-responsive {
            border-radius: 12px;
            overflow: hidden;
            box-shadow: 0 4px 20px rgba(0,0,0,0.08);
        }

        .table tbody tr:hover {
            background: rgba(207, 44, 44, 0.02);
        }

        .table tbody tr:last-child {
            border-bottom: none !important;
        }

        /* Badge Styling */
        .nav-link .badge {
            font-size: 0.75rem;
            padding: 0.35rem 0.65rem;
            border-radius: 20px;
        }

        @media (max-width: 768px) {
            .nav-link {
                padding: 0.5rem 0.75rem !important;
                font-size: 0.9rem;
            }

            .nav-link .badge {
                display: none;
            }

            .table {
                font-size: 0.85rem;
            }

            .table th, .table td {
                padding: 0.75rem !important;
            }

            .btn-sm {
                padding: 0.4rem 0.8rem !important;
                font-size: 0.8rem !important;
            }
        }
    </style>
</head>
<body>
    <div class="dashboard-container">
        <!-- Include Sidebar -->
        <?php include __DIR__ . '/../layouts/sidebar.php'; ?>

        <!-- Main Content -->
        <main class="dashboard-main main-container">
            <div class="content-wrapper">
                <!-- Page Header -->
                <div class="page-header">
                    <h1>Lechonero Schedule</h1>
                    <p>Manage lechon cooking orders and quality assessment</p>
                </div>

                <!-- Flash Messages -->
                <?php if (isset($_SESSION['success'])): ?>
                    <div class="alert alert-success show mb-4">
                        <?php echo htmlspecialchars($_SESSION['success']); ?>
                    </div>
                    <?php unset($_SESSION['success']); ?>
                <?php endif; ?>

                <?php if (isset($_SESSION['error'])): ?>
                    <div class="alert alert-danger show mb-4">
                        <?php echo htmlspecialchars($_SESSION['error']); ?>
                    </div>
                    <?php unset($_SESSION['error']); ?>
                <?php endif; ?>

                <!-- Debug Info -->
                <?php if (isset($sql_error) && $sql_error): ?>
                    <div class="alert alert-warning mb-4">
                        <strong>Debug Info:</strong> SQL Error: <?php echo htmlspecialchars($sql_error); ?>
                    </div>
                <?php endif; ?>

                <!-- Tabs Navigation -->
                <ul class="nav nav-tabs mb-0" id="orderTabs" role="tablist" style="background: white; border-radius: 12px 12px 0 0; padding: 1rem 0.5rem 0; border-bottom: none !important;">
                    <li class="nav-item" role="presentation">
                        <button class="nav-link active" id="ready-tab" data-bs-toggle="tab" data-bs-target="#ready-pane" 
                                type="button" role="tab">
                            <i class="fas fa-fire me-2"></i>Lechon Orders
                            <span class="badge bg-danger ms-2"><?php echo count($ready_orders) + count($cooking_orders) + count($completed_orders); ?></span>
                        </button>
                    </li>
                    <li class="nav-item" role="presentation">
                        <button class="nav-link" id="other-tab" data-bs-toggle="tab" data-bs-target="#other-pane" 
                                type="button" role="tab">
                            <i class="fas fa-utensils me-2"></i>Other Orders
                            <span class="badge bg-secondary ms-2"><?php echo count($other_orders); ?></span>
                        </button>
                    </li>
                </ul>
                <div style="height:3px;background:#e0e0e0;margin-bottom:1.5rem;border-radius:0 0 4px 4px;"></div>

                <style>
                    #orderTabs .nav-link {
                        padding: 0.75rem 1.5rem;
                        font-weight: 600;
                        color: #6c757d;
                        border: none !important;
                        border-bottom: 3px solid transparent !important;
                        border-radius: 0;
                        background: transparent;
                        transition: all 0.2s ease;
                        margin-bottom: -1px;
                    }
                    #orderTabs .nav-link:hover { color: #cf2c2c; }
                    #orderTabs .nav-link.active {
                        color: #cf2c2c !important;
                        border-bottom: 3px solid #cf2c2c !important;
                        background: transparent !important;
                    }
                    /* Kill the global ::after pseudo-element that was extending 1rem below the tab and blocking clicks */
                    .nav-tabs .nav-link.active::after,
                    #orderTabs .nav-link.active::after { display: none !important; }
                </style>

                <!-- Tab Content -->
                <div class="tab-content" id="orderTabsContent">

                    <!-- ══ TAB 1: LECHON ORDERS (Pig + Whole Lechon) ══════════════════════ -->
                    <div class="tab-pane fade show active" id="ready-pane" role="tabpanel">

                        <?php if (empty($ready_orders) && empty($cooking_orders) && empty($completed_orders)): ?>
                            <div class="empty-state">
                                <div class="icon"><i class="fas fa-inbox"></i></div>
                                <h5>No lechon orders yet</h5>
                                <p>Paid pig orders and Whole Lechon marketplace orders will appear here.</p>
                            </div>
                        <?php else: ?>

                            <?php if (!empty($ready_orders)): ?>
                            <!-- Ready to Cook sub-section -->
                            <div style="margin-bottom:2rem;">
                                <div style="display:flex;align-items:center;gap:10px;margin-bottom:1rem;">
                                    <span style="background:linear-gradient(135deg,#28a745,#20c997);color:#fff;padding:5px 14px;border-radius:20px;font-size:.85rem;font-weight:700;">
                                        🔪 Ready to Cook &nbsp;<span style="background:rgba(255,255,255,.3);padding:1px 8px;border-radius:10px;"><?php echo count($ready_orders); ?></span>
                                    </span>
                                </div>
                                <div class="table-responsive">
                                    <table class="table table-hover align-middle" style="background:white;border-radius:12px;overflow:hidden;box-shadow:0 4px 20px rgba(0,0,0,.08);">
                                        <thead style="background:linear-gradient(135deg,#28a745,#20c997);color:white;">
                                            <tr>
                                                <th style="padding:1rem;border:none;">Order #</th>
                                                <th style="padding:1rem;border:none;">Customer</th>
                                                <th style="padding:1rem;border:none;">Pig / Listing</th>
                                                <th style="padding:1rem;border:none;">Weight</th>
                                                <th style="padding:1rem;border:none;">Pen / Type</th>
                                                <th style="padding:1rem;border:none;">Delivery Date</th>
                                                <th style="padding:1rem;border:none;text-align:center;">Action</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            <?php foreach ($ready_orders as $order): ?>
                                                <tr style="border-bottom:1px solid #e0e0e0;">
                                                    <td style="padding:1rem;font-weight:600;color:#2d3748;"><?php echo htmlspecialchars($order['order_number']); ?></td>
                                                    <td style="padding:1rem;"><?php echo htmlspecialchars($order['customer_name']); ?></td>
                                                    <td style="padding:1rem;">
                                                        <span style="background:#e3f2fd;padding:.3rem .8rem;border-radius:20px;color:#1976d2;font-weight:600;">
                                                            <?php echo htmlspecialchars($order['pig_tag_id']); ?>
                                                        </span>
                                                        <?php if ($order['source_type'] === 'LECHON_ORDER'): ?>
                                                            <br><small style="color:#c0392b;font-weight:600;">Marketplace</small>
                                                        <?php endif; ?>
                                                    </td>
                                                    <td style="padding:1rem;"><?php echo $order['weight_kg'] ? number_format($order['weight_kg'],1).' kg' : '—'; ?></td>
                                                    <td style="padding:1rem;"><?php echo $order['source_type'] === 'LECHON_ORDER' ? htmlspecialchars(str_replace('_',' ',$order['pin_number'])) : htmlspecialchars($order['pin_number']); ?></td>
                                                    <td style="padding:1rem;"><?php echo $order['delivery_date'] ? date('M d, Y', strtotime($order['delivery_date'])) : '—'; ?></td>
                                                    <td style="padding:1rem;text-align:center;">
                                                        <button class="btn btn-sm" style="background:linear-gradient(135deg,#28a745,#20c997);color:white;border:none;padding:.5rem 1rem;border-radius:20px;font-weight:600;position:relative;z-index:10;cursor:pointer;"
                                                                onclick="startCooking('<?php echo htmlspecialchars($order['order_number']); ?>','<?php echo htmlspecialchars($order['customer_name']); ?>','<?php echo $order['source_type']; ?>','<?php echo (int)($order['lechon_order_id'] ?? 0); ?>')">
                                                            Start <i class="fas fa-arrow-right ms-1"></i>
                                                        </button>
                                                    </td>
                                                </tr>
                                            <?php endforeach; ?>
                                        </tbody>
                                    </table>
                                </div>
                            </div>
                            <?php endif; ?>

                            <?php if (!empty($cooking_orders)): ?>
                            <!-- Currently Cooking sub-section -->
                            <div style="margin-bottom:2rem;">
                                <div style="display:flex;align-items:center;gap:10px;margin-bottom:1rem;">
                                    <span style="background:linear-gradient(135deg,#ff6b6b,#ee5a24);color:#fff;padding:5px 14px;border-radius:20px;font-size:.85rem;font-weight:700;">
                                        🔥 Currently Cooking &nbsp;<span style="background:rgba(255,255,255,.3);padding:1px 8px;border-radius:10px;"><?php echo count($cooking_orders); ?></span>
                                    </span>
                                </div>
                                <div class="table-responsive">
                                    <table class="table table-hover align-middle" style="background:white;border-radius:12px;overflow:hidden;box-shadow:0 4px 20px rgba(0,0,0,.08);">
                                        <thead style="background:linear-gradient(135deg,#ff6b6b,#ee5a24);color:white;">
                                            <tr>
                                                <th style="padding:1rem;border:none;">Order #</th>
                                                <th style="padding:1rem;border:none;">Customer</th>
                                                <th style="padding:1rem;border:none;">Pig / Listing</th>
                                                <th style="padding:1rem;border:none;">Weight</th>
                                                <th style="padding:1rem;border:none;">Started At</th>
                                                <th style="padding:1rem;border:none;">Est. Time</th>
                                                <th style="padding:1rem;border:none;text-align:center;">Action</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            <?php foreach ($cooking_orders as $order): ?>
                                                <tr style="border-bottom:1px solid #e0e0e0;">
                                                    <td style="padding:1rem;font-weight:600;color:#2d3748;"><?php echo htmlspecialchars($order['order_number']); ?></td>
                                                    <td style="padding:1rem;"><?php echo htmlspecialchars($order['customer_name']); ?></td>
                                                    <td style="padding:1rem;">
                                                        <span style="background:#ffe0b2;padding:.3rem .8rem;border-radius:20px;color:#e65100;font-weight:600;">
                                                            <?php echo htmlspecialchars($order['pig_tag_id']); ?>
                                                        </span>
                                                        <?php if ($order['source_type'] === 'LECHON_ORDER'): ?>
                                                            <br><small style="color:#c0392b;font-weight:600;">Marketplace</small>
                                                        <?php endif; ?>
                                                    </td>
                                                    <td style="padding:1rem;"><?php echo $order['weight_kg'] ? number_format($order['weight_kg'],1).' kg' : '—'; ?></td>
                                                    <td style="padding:1rem;"><?php echo $order['cook_start_time'] ? date('g:i A', strtotime($order['cook_start_time'])) : 'N/A'; ?></td>
                                                    <td style="padding:1rem;"><?php echo $order['estimated_cook_time'] ? number_format($order['estimated_cook_time'],1).'h' : 'N/A'; ?></td>
                                                    <td style="padding:1rem;text-align:center;">
                                                        <button class="btn btn-sm" 
                                                                style="background:linear-gradient(135deg,#ff6b6b,#ee5a24);color:white;border:none;padding:.5rem 1rem;border-radius:20px;font-weight:600;position:relative;z-index:10;cursor:pointer;"
                                                                onclick="completeCooking('<?php echo htmlspecialchars($order['order_number']); ?>','<?php echo htmlspecialchars($order['customer_name']); ?>','<?php echo $order['source_type']; ?>','<?php echo (int)($order['lechon_order_id'] ?? 0); ?>')">
                                                            Complete <i class="fas fa-check ms-1"></i>
                                                        </button>
                                                    </td>
                                                </tr>
                                            <?php endforeach; ?>
                                        </tbody>
                                    </table>
                                </div>
                            </div>
                            <?php endif; ?>

                            <?php if (!empty($completed_orders)): ?>
                            <!-- Completed sub-section -->
                            <div>
                                <div style="display:flex;align-items:center;gap:10px;margin-bottom:1rem;">
                                    <span style="background:linear-gradient(135deg,#007bff,#6f42c1);color:#fff;padding:5px 14px;border-radius:20px;font-size:.85rem;font-weight:700;">
                                        ✅ Completed &nbsp;<span style="background:rgba(255,255,255,.3);padding:1px 8px;border-radius:10px;"><?php echo count($completed_orders); ?></span>
                                    </span>
                                </div>
                                <div class="table-responsive">
                                    <table class="table table-hover align-middle" style="background:white;border-radius:12px;overflow:hidden;box-shadow:0 4px 20px rgba(0,0,0,.08);">
                                        <thead style="background:linear-gradient(135deg,#007bff,#6f42c1);color:white;">
                                            <tr>
                                                <th style="padding:1rem;border:none;">Order #</th>
                                                <th style="padding:1rem;border:none;">Customer</th>
                                                <th style="padding:1rem;border:none;">Pig / Listing</th>
                                                <th style="padding:1rem;border:none;">Delivery Date</th>
                                                <th style="padding:1rem;border:none;">Status</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            <?php foreach ($completed_orders as $order): ?>
                                                <tr style="border-bottom:1px solid #e0e0e0;">
                                                    <td style="padding:1rem;font-weight:600;color:#2d3748;"><?php echo htmlspecialchars($order['order_number']); ?></td>
                                                    <td style="padding:1rem;"><?php echo htmlspecialchars($order['customer_name']); ?></td>
                                                    <td style="padding:1rem;">
                                                        <span style="background:#c8e6c9;padding:.3rem .8rem;border-radius:20px;color:#2e7d32;font-weight:600;">
                                                            <?php echo htmlspecialchars($order['pig_tag_id']); ?>
                                                        </span>
                                                        <?php if ($order['source_type'] === 'LECHON_ORDER'): ?>
                                                            <br><small style="color:#c0392b;font-weight:600;">Marketplace</small>
                                                        <?php endif; ?>
                                                    </td>
                                                    <td style="padding:1rem;"><?php echo $order['delivery_date'] ? date('M d, Y', strtotime($order['delivery_date'])) : '—'; ?></td>
                                                    <td style="padding:1rem;">
                                                        <span style="background:#d4edda;color:#155724;padding:4px 10px;border-radius:20px;font-size:.8rem;font-weight:700;">
                                                            <?php echo ucfirst($order['order_status']); ?> ✓
                                                        </span>
                                                    </td>
                                                </tr>
                                            <?php endforeach; ?>
                                        </tbody>
                                    </table>
                                </div>
                            </div>
                            <?php endif; ?>

                        <?php endif; ?>
                    </div>

                    <!-- ══ TAB 2: OTHER MARKETPLACE ORDERS (Pancit, Dinuguan, Belly, etc.) ═ -->
                    <div class="tab-pane fade" id="other-pane" role="tabpanel">
                        <?php if (empty($other_orders)): ?>
                            <div class="empty-state">
                                <div class="icon"><i class="fas fa-inbox"></i></div>
                                <h5>No other orders</h5>
                                <p>Non-lechon marketplace orders (Pancit, Dinuguan, Belly, etc.) will appear here.</p>
                            </div>
                        <?php else: ?>
                            <div class="table-responsive">
                                <table class="table table-hover align-middle" style="background:white;border-radius:12px;overflow:hidden;box-shadow:0 4px 20px rgba(0,0,0,.08);">
                                    <thead style="background:linear-gradient(135deg,#6c757d,#495057);color:white;">
                                        <tr>
                                            <th style="padding:1rem;border:none;">Order #</th>
                                            <th style="padding:1rem;border:none;">Customer</th>
                                            <th style="padding:1rem;border:none;">Product</th>
                                            <th style="padding:1rem;border:none;">Category</th>
                                            <th style="padding:1rem;border:none;">Delivery Date</th>
                                            <th style="padding:1rem;border:none;">Order Status</th>
                                            <th style="padding:1rem;border:none;text-align:center;">Action</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php foreach ($other_orders as $order): ?>
                                            <?php
                                            $statusColors = [
                                                'confirmed'  => ['#0c5460','#d1ecf1'],
                                                'preparing'  => ['#155724','#d4edda'],
                                                'cooking'    => ['#7b2d00','#ffe0b2'],
                                                'delivering' => ['#4a235a','#f3e5f5'],
                                                'completed'  => ['#155724','#d4edda'],
                                            ];
                                            [$sc,$sb] = $statusColors[$order['order_status']] ?? ['#333','#eee'];
                                            $statusLabels = [
                                                'confirmed'  => 'Confirmed',
                                                'preparing'  => 'In Preparation',
                                                'cooking'    => 'Cooking 🔥',
                                                'delivering' => 'Delivering',
                                                'completed'  => 'Completed ✓',
                                            ];
                                            $statusLabel = $statusLabels[$order['order_status']] ?? ucfirst($order['order_status']);
                                            $cs = $order['cooking_status'];
                                            ?>
                                            <tr style="border-bottom:1px solid #e0e0e0;">
                                                <td style="padding:1rem;font-weight:600;color:#2d3748;"><?php echo htmlspecialchars($order['order_number']); ?></td>
                                                <td style="padding:1rem;"><?php echo htmlspecialchars($order['customer_name']); ?></td>
                                                <td style="padding:1rem;font-weight:600;"><?php echo htmlspecialchars($order['pig_tag_id']); ?></td>
                                                <td style="padding:1rem;">
                                                    <span style="background:#f3e5f5;color:#6a1b9a;padding:3px 10px;border-radius:20px;font-size:.8rem;font-weight:600;">
                                                        <?php echo htmlspecialchars(str_replace('_',' ',$order['pin_number'])); ?>
                                                    </span>
                                                </td>
                                                <td style="padding:1rem;">
                                                    <?php if ($order['delivery_date']): ?>
                                                        <?php
                                                        $diff = (strtotime($order['delivery_date']) - strtotime('today')) / 86400;
                                                        $urgent = $diff <= 1;
                                                        ?>
                                                        <span style="<?php echo $urgent ? 'color:#c0392b;font-weight:700;' : ''; ?>">
                                                            <?php echo date('M d, Y', strtotime($order['delivery_date'])); ?>
                                                            <?php if ($diff <= 0): ?><span style="font-size:.7rem;background:#f8d7da;color:#721c24;padding:1px 5px;border-radius:3px;margin-left:4px;">Today</span>
                                                            <?php elseif ($diff <= 1): ?><span style="font-size:.7rem;background:#fff3cd;color:#856404;padding:1px 5px;border-radius:3px;margin-left:4px;">Tomorrow</span>
                                                            <?php endif; ?>
                                                        </span>
                                                    <?php else: ?>—<?php endif; ?>
                                                </td>
                                                <td style="padding:1rem;">
                                                    <span style="background:<?php echo $sb; ?>;color:<?php echo $sc; ?>;padding:4px 10px;border-radius:20px;font-size:.8rem;font-weight:700;">
                                                        <?php echo $statusLabel; ?>
                                                    </span>
                                                </td>
                                                <td style="padding:1rem;text-align:center;">
                                                    <?php if ($cs === 'ready_to_cook'): ?>
                                                        <button class="btn btn-sm" style="background:linear-gradient(135deg,#28a745,#20c997);color:white;border:none;padding:.5rem 1rem;border-radius:20px;font-weight:600;"
                                                                onclick="markOtherPrepared('<?php echo htmlspecialchars($order['order_number']); ?>','<?php echo htmlspecialchars($order['customer_name']); ?>','<?php echo htmlspecialchars($order['pig_tag_id']); ?>','<?php echo (int)($order['lechon_order_id'] ?? 0); ?>')">
                                                            <i class="fas fa-check me-1"></i> Mark Done
                                                        </button>
                                                    <?php elseif ($cs === 'cooking'): ?>
                                                        <button class="btn btn-sm" style="background:linear-gradient(135deg,#28a745,#20c997);color:white;border:none;padding:.5rem 1rem;border-radius:20px;font-weight:600;"
                                                                onclick="markOtherPrepared('<?php echo htmlspecialchars($order['order_number']); ?>','<?php echo htmlspecialchars($order['customer_name']); ?>','<?php echo htmlspecialchars($order['pig_tag_id']); ?>','<?php echo (int)($order['lechon_order_id'] ?? 0); ?>')">
                                                            <i class="fas fa-check me-1"></i> Mark Done
                                                        </button>
                                                    <?php elseif ($cs === 'completed'): ?>
                                                        <span style="color:#28a745;font-weight:700;font-size:.85rem;">✓ Done</span>
                                                    <?php else: ?>
                                                        <span style="color:#aaa;font-size:.8rem;">—</span>
                                                    <?php endif; ?>
                                                </td>
                                            </tr>
                                        <?php endforeach; ?>
                                    </tbody>
                                </table>
                            </div>
                        <?php endif; ?>
                    </div>

                </div>
            </div>
        </main>
    </div>

    <!-- Cooking Completion Modal -->
    <div class="modal fade" id="cookingModal" tabindex="-1">
        <div class="modal-dialog modal-dialog-centered" style="max-width:520px;">
            <div class="modal-content" style="border-radius:14px;border:none;box-shadow:0 10px 40px rgba(0,0,0,.12);">
                <div class="modal-header" style="background:linear-gradient(135deg,#f00101ff,#f03d06ff);color:white;border-radius:14px 14px 0 0;padding:.9rem 1.25rem;border:none;">
                    <h5 class="modal-title" style="font-weight:700;font-size:1rem;margin:0;">Start Cooking Process</h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body" style="padding:1rem 1.25rem;">
                    <div id="cookingOrderInfo" class="mb-2"></div>
                    
                    <form id="cookingForm" enctype="multipart/form-data">
                        <input type="hidden" id="cookingOrderNumber" name="order_number">
                        <input type="hidden" id="actionType" name="action" value="start">
                        
                        <!-- Start Cooking Fields -->
                        <div id="startSection">
                            <div class="row g-2 mb-2">
                                <div class="col-6">
                                    <label class="form-label mb-1" style="font-size:.8rem;font-weight:600;"><i class="fas fa-clock me-1"></i>Cook Time (hrs)</label>
                                    <input type="number" class="form-control form-control-sm" id="estimatedCookTime" name="estimated_cook_time" step="0.5" min="1" max="12" placeholder="e.g. 4.5">
                                </div>
                                <div class="col-6">
                                    <label class="form-label mb-1" style="font-size:.8rem;font-weight:600;"><i class="fas fa-fire me-1"></i>Cooking Method</label>
                                    <select class="form-select form-select-sm" id="cookingMethod" name="cooking_method">
                                        <option value="">Select method...</option>
                                        <option value="charcoal">Charcoal</option>
                                        <option value="wood">Wood Fire</option>
                                        <option value="gas">Gas</option>
                                        <option value="electric">Electric</option>
                                        <option value="combination">Combination</option>
                                    </select>
                                </div>
                            </div>
                            <div class="mb-2">
                                <label class="form-label mb-1" style="font-size:.8rem;font-weight:600;"><i class="fas fa-sticky-note me-1"></i>Initial Notes</label>
                                <textarea class="form-control form-control-sm" id="initialNotes" name="initial_notes" rows="2" placeholder="Pre-cooking prep, seasoning, notes..."></textarea>
                            </div>
                        </div><!-- end #startSection -->

                        <!-- Completion Section (Initially Hidden) -->
                        <div id="completionSection" style="display:none;">
                            <hr class="my-2">
                            <!-- Image Upload/Capture -->
                            <div class="mb-2">
                                <label class="form-label mb-1" style="font-size:.8rem;font-weight:600;"><i class="fas fa-camera me-1"></i>Cooked Lechon Photo <span style="color:#dc3545;">*</span></label>
                                
                                <div class="d-flex gap-2 mb-2">
                                    <!-- Mobile: opens camera directly. Desktop: ignored if no camera -->
                                    <button type="button" class="btn btn-sm btn-primary flex-fill" id="takePhotoBtn"
                                            onclick="document.getElementById('cameraFileInput').click()">
                                        <i class="fas fa-camera me-1"></i>Take Photo
                                    </button>
                                    <!-- Regular file picker (gallery / desktop) -->
                                    <button type="button" class="btn btn-sm btn-outline-primary flex-fill"
                                            onclick="document.getElementById('imageFile').click()">
                                        <i class="fas fa-upload me-1"></i>Upload
                                    </button>
                                </div>

                                <!-- Hidden input: capture=environment opens native camera on mobile -->
                                <input type="file" id="cameraFileInput" accept="image/*" capture="environment" style="display:none;"
                                       onchange="previewPhoto(this)">
                                <!-- Hidden input: regular file picker, no capture -->
                                <input type="file" id="imageFile" name="cooked_image" accept="image/*" style="display:none;"
                                       onchange="previewPhoto(this)">

                                <!-- Preview area -->
                                <div id="uploadArea" style="padding:1rem;text-align:center;border:2px dashed #667eea;border-radius:8px;background:#f8f9fc;cursor:pointer;"
                                     onclick="document.getElementById('imageFile').click()">
                                    <div id="uploadContent">
                                        <i class="fas fa-cloud-upload-alt" style="font-size:1.5rem;color:#667eea;"></i>
                                        <p class="mb-0 mt-1" style="font-size:.8rem;"><strong>Click to upload from gallery</strong></p>
                                        <small class="text-muted" style="font-size:.72rem;">JPG, PNG, GIF (max 5MB)</small>
                                    </div>
                                    <div id="imagePreview" class="d-none"></div>
                                </div>
                            </div>
                            <!-- Quality Assessment -->
                            <div class="row g-2 mb-2">
                                <div class="col-4">
                                    <label class="form-label mb-1" style="font-size:.78rem;font-weight:600;">Temp (°C) <span style="color:#dc3545;">*</span></label>
                                    <input type="number" class="form-control form-control-sm" id="internalTemp" name="internal_temperature" step="0.1" min="60" max="100" placeholder="75.0">
                                </div>
                                <div class="col-4">
                                    <label class="form-label mb-1" style="font-size:.78rem;font-weight:600;">Skin Texture <span style="color:#dc3545;">*</span></label>
                                    <select class="form-select form-select-sm" id="skinTexture" name="skin_texture">
                                        <option value="">Select...</option>
                                        <option value="crispy">Crispy ✓</option>
                                        <option value="soft">Soft</option>
                                        <option value="burnt">Burnt</option>
                                        <option value="undercooked">Undercooked</option>
                                    </select>
                                </div>
                                <div class="col-4">
                                    <label class="form-label mb-1" style="font-size:.78rem;font-weight:600;">Tenderness <span style="color:#dc3545;">*</span></label>
                                    <select class="form-select form-select-sm" id="meatTenderness" name="meat_tenderness">
                                        <option value="">Select...</option>
                                        <option value="very_tender">Very Tender</option>
                                        <option value="tender">Tender</option>
                                        <option value="tough">Tough</option>
                                        <option value="very_tough">Very Tough</option>
                                    </select>
                                </div>
                            </div>
                            <div class="mb-1">
                                <label class="form-label mb-1" style="font-size:.78rem;font-weight:600;">Quality Notes</label>
                                <textarea class="form-control form-control-sm" id="qualityNotes" name="quality_notes" rows="2" placeholder="Final assessment, challenges, overall quality..."></textarea>
                            </div>
                        </div>
                    </form>
                </div>
                <div class="modal-footer" style="padding:.75rem 1.25rem;border-top:1px solid #f0f0f0;">
                    <button type="button" class="btn btn-sm btn-secondary" data-bs-dismiss="modal" style="border-radius:20px;padding:6px 16px;">Cancel</button>
                    <button type="button" class="btn btn-sm btn-success" id="saveCookingBtn" onclick="saveCooking()" style="border-radius:20px;padding:6px 16px;opacity:1 !important;pointer-events:auto !important;">
                        Start Cooking
                    </button>
                    <button type="button" class="btn btn-sm btn-primary" id="completeBtn" onclick="showCompletionForm()" style="display:none;border-radius:20px;padding:6px 16px;">
                        Ready to Complete
                    </button>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.8/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        let currentOrderNumber = null;
        let currentSourceType  = 'PIG_ORDER';
        let currentLechonOrderId = 0;
        let cookingStarted = false;
        let cameraStream   = null;

        // ── Start Cooking ──────────────────────────────────────────────────────
        function startCooking(orderNumber, customerName, sourceType, lechonOrderId) {
            currentOrderNumber   = orderNumber;
            currentSourceType    = sourceType || 'PIG_ORDER';
            currentLechonOrderId = lechonOrderId || 0;
            cookingStarted       = false;
            closeCamera();

            document.getElementById('cookingOrderNumber').value = orderNumber;
            document.getElementById('actionType').value = 'start';
            document.getElementById('cookingOrderInfo').innerHTML =
                `<div style="background:linear-gradient(135deg,#667eea,#764ba2);color:#fff;border-radius:8px;padding:.6rem 1rem;margin-bottom:.5rem;font-size:.85rem;">
                    <strong>${orderNumber}</strong> &nbsp;·&nbsp; ${customerName}
                    ${currentSourceType === 'LECHON_ORDER' ? '&nbsp;<span style="opacity:.8;font-size:.75rem;">📦 Marketplace</span>' : ''}
                 </div>`;

            document.getElementById('cookingForm').reset();
            document.getElementById('startSection').style.display = 'block';
            document.getElementById('completionSection').style.display = 'none';
            document.getElementById('saveCookingBtn').textContent = 'Start Cooking';
            document.getElementById('saveCookingBtn').style.display = 'inline-block';
            document.getElementById('saveCookingBtn').disabled = false;
            document.getElementById('completeBtn').style.display = 'none';
            document.querySelector('#cookingModal .modal-title').textContent = 'Start Cooking';
            document.getElementById('imagePreview').classList.add('d-none');
            document.getElementById('uploadContent').classList.remove('d-none');

            new bootstrap.Modal(document.getElementById('cookingModal')).show();
        }

        // ── Complete Cooking ───────────────────────────────────────────────────
        function completeCooking(orderNumber, customerName, sourceType, lechonOrderId) {
            currentOrderNumber   = orderNumber;
            currentSourceType    = sourceType || 'PIG_ORDER';
            currentLechonOrderId = lechonOrderId || 0;
            closeCamera();

            document.getElementById('cookingOrderNumber').value = orderNumber;
            document.getElementById('actionType').value = 'complete';
            document.getElementById('cookingOrderInfo').innerHTML =
                `<div style="background:linear-gradient(135deg,#ff6b6b,#ee5a24);color:#fff;border-radius:8px;padding:.6rem 1rem;margin-bottom:.5rem;font-size:.85rem;">
                    <strong>${orderNumber}</strong> &nbsp;·&nbsp; ${customerName}
                    ${currentSourceType === 'LECHON_ORDER' ? '&nbsp;<span style="opacity:.8;font-size:.75rem;">📦 Marketplace</span>' : ''}
                 </div>`;

            document.getElementById('cookingForm').reset();
            document.getElementById('startSection').style.display = 'none';
            document.getElementById('completionSection').style.display = 'block';
            document.getElementById('saveCookingBtn').textContent = 'Complete Cooking';
            document.getElementById('saveCookingBtn').style.display = 'inline-block';
            document.getElementById('saveCookingBtn').disabled = false;
            document.getElementById('completeBtn').style.display = 'none';
            document.querySelector('#cookingModal .modal-title').textContent = 'Complete Cooking';
            document.getElementById('imagePreview').classList.add('d-none');
            document.getElementById('uploadContent').classList.remove('d-none');

            new bootstrap.Modal(document.getElementById('cookingModal')).show();
        }

        // ── Show completion form after start ───────────────────────────────────
        function showCompletionForm() {
            document.getElementById('completionSection').style.display = 'block';
            document.getElementById('saveCookingBtn').textContent = 'Complete Cooking';
            document.getElementById('saveCookingBtn').style.display = 'inline-block';
            document.getElementById('completeBtn').style.display = 'none';
            document.querySelector('#cookingModal .modal-title').textContent = 'Complete Cooking';
            document.getElementById('actionType').value = 'complete';
            document.getElementById('completionSection').scrollIntoView({ behavior: 'smooth' });
        }

        // ── Image preview (shared by both file inputs) ────────────────────────
        function previewPhoto(input) {
            const file = input.files[0];
            if (!file) return;
            // Copy to named cooked_image input so FormData includes it
            const dt = new DataTransfer();
            dt.items.add(file);
            document.getElementById('imageFile').files = dt.files;
            const reader = new FileReader();
            reader.onload = function(ev) {
                document.getElementById('imagePreview').innerHTML =
                    `<img src="${ev.target.result}" alt="Preview"
                         style="max-width:100%;max-height:160px;border-radius:8px;object-fit:cover;">
                     <div style="font-size:.75rem;color:#28a745;margin-top:4px;">
                         <i class="fas fa-check-circle me-1"></i>Photo ready
                     </div>`;
                document.getElementById('imagePreview').classList.remove('d-none');
                document.getElementById('uploadContent').classList.add('d-none');
            };
            reader.readAsDataURL(file);
        }

        // Drag & drop
        const uploadArea = document.getElementById('uploadArea');
        uploadArea.addEventListener('dragover',  e => { e.preventDefault(); uploadArea.style.borderColor='#4c51bf'; });
        uploadArea.addEventListener('dragleave', e => { e.preventDefault(); uploadArea.style.borderColor='#667eea'; });
        uploadArea.addEventListener('drop', function(e) {
            e.preventDefault();
            uploadArea.style.borderColor = '#667eea';
            if (e.dataTransfer.files.length > 0) {
                const dt = new DataTransfer();
                dt.items.add(e.dataTransfer.files[0]);
                document.getElementById('imageFile').files = dt.files;
                previewPhoto(document.getElementById('imageFile'));
            }
        });

        // ── Save cooking (start or complete) ──────────────────────────────────
        function saveCooking() {
            const form     = document.getElementById('cookingForm');
            const formData = new FormData(form);
            const action   = document.getElementById('actionType').value;

            formData.append('source_type',     currentSourceType);
            formData.append('lechon_order_id', currentLechonOrderId);

            if (action === 'start') {
                const btn = document.getElementById('saveCookingBtn');
                btn.disabled = true; btn.textContent = 'Starting...';

                fetch('/lechonero/start-cooking', { method: 'POST', body: formData })
                    .then(r => r.json())
                    .then(data => {
                        if (data.success) {
                            cookingStarted = true;
                            document.getElementById('saveCookingBtn').style.display = 'none';
                            document.getElementById('completeBtn').style.display = 'inline-block';
                            document.querySelector('#cookingModal .modal-title').textContent = 'Cooking In Progress';
                            const ok = document.createElement('div');
                            ok.className = 'alert alert-success mt-2 mb-0 py-2';
                            ok.style.fontSize = '.85rem';
                            ok.innerHTML = '<i class="fas fa-check-circle me-1"></i>Started! Click "Ready to Complete" when done.';
                            document.getElementById('cookingOrderInfo').appendChild(ok);
                        } else {
                            alert('Error: ' + data.message);
                            btn.disabled = false; btn.textContent = 'Start Cooking';
                        }
                    })
                    .catch(() => { alert('Network error.'); btn.disabled = false; btn.textContent = 'Start Cooking'; });

            } else if (action === 'complete') {
                const imageFile    = document.getElementById('imageFile').files[0];
                const internalTemp = formData.get('internal_temperature');
                const skinTexture  = formData.get('skin_texture');
                const meatTend     = formData.get('meat_tenderness');

                // Photo is optional on HTTP (camera blocked). Warn but allow submission.
                if (!imageFile) {
                    const proceed = confirm('No photo attached. Do you want to complete without a photo?');
                    if (!proceed) return;
                    // Set skip_image flag so backend skips image requirement
                    formData.append('skip_image', '1');
                }
                if (!internalTemp || !skinTexture || !meatTend) { alert('Fill in all required quality fields (temperature, skin texture, tenderness).'); return; }

                const btn = document.getElementById('saveCookingBtn');
                btn.disabled = true; btn.textContent = 'Completing...';

                fetch('/lechonero/complete-cooking', { method: 'POST', body: formData })
                    .then(r => r.json())
                    .then(data => {
                        if (data.success) { alert('Cooking completed!'); location.reload(); }
                        else { alert('Error: ' + data.message); btn.disabled = false; btn.textContent = 'Complete Cooking'; }
                    })
                    .catch(() => { alert('Network error.'); btn.disabled = false; btn.textContent = 'Complete Cooking'; });
            }
        }

        // ── Camera ─────────────────────────────────────────────────────────────
        // getUserMedia is blocked by InfinityFree's Permissions-Policy header.
        // "Take Photo" button uses a hidden file input with capture="environment"
        // which opens the native camera app on mobile devices directly.
        let cameraStream = null;
        function openCamera() { document.getElementById('cameraFileInput').click(); }
        function closeCamera() { /* no-op — no getUserMedia stream to close */ }

        document.getElementById('cookingModal').addEventListener('hidden.bs.modal', () => closeCamera());

        // ── Other Orders: simple confirm modal ────────────────────────────────
        function markOtherPrepared(orderNumber, customerName, productName, lechonOrderId) {
            document.getElementById('otherOrderInfo').innerHTML =
                `<div style="background:linear-gradient(135deg,#28a745,#20c997);color:#fff;border-radius:8px;padding:.7rem 1rem;margin-bottom:.75rem;">
                    <strong>${productName}</strong><br>
                    <small>${orderNumber} &nbsp;·&nbsp; ${customerName}</small>
                 </div>`;
            document.getElementById('otherOrderNumberInput').value   = orderNumber;
            document.getElementById('otherLechonOrderIdInput').value = lechonOrderId;
            new bootstrap.Modal(document.getElementById('otherOrderModal')).show();
        }

        document.getElementById('confirmOtherDoneBtn').addEventListener('click', function() {
            const orderNumber   = document.getElementById('otherOrderNumberInput').value;
            const lechonOrderId = document.getElementById('otherLechonOrderIdInput').value;
            const btn = this;
            btn.disabled = true; btn.textContent = 'Saving...';

            const fd = new FormData();
            fd.append('order_number',         orderNumber);
            fd.append('source_type',          'LECHON_ORDER');
            fd.append('lechon_order_id',       lechonOrderId);
            fd.append('skip_image',            '1');
            fd.append('internal_temperature',  '75');
            fd.append('skin_texture',          'soft');
            fd.append('meat_tenderness',       'tender');
            fd.append('quality_notes',         'Marked as prepared (non-lechon item)');

            fetch('/lechonero/complete-cooking', { method: 'POST', body: fd })
                .then(r => r.json())
                .then(data => {
                    if (data.success) {
                        bootstrap.Modal.getInstance(document.getElementById('otherOrderModal')).hide();
                        location.reload();
                    } else {
                        alert('Error: ' + data.message);
                        btn.disabled = false; btn.textContent = 'Confirm Done';
                    }
                })
                .catch(() => { alert('Network error.'); btn.disabled = false; btn.textContent = 'Confirm Done'; });
        });

        // Sidebar toggle
        document.getElementById('sidebarToggle')?.addEventListener('click', () =>
            document.getElementById('dashboardSidebar')?.classList.toggle('active')
        );
    </script>

    <!-- Simple "Mark as Done" modal for Other Orders -->
    <div class="modal fade" id="otherOrderModal" tabindex="-1">
        <div class="modal-dialog modal-dialog-centered" style="max-width:420px;">
            <div class="modal-content" style="border-radius:14px;border:none;box-shadow:0 10px 40px rgba(0,0,0,.12);">
                <div class="modal-header" style="background:linear-gradient(135deg,#28a745,#20c997);color:#fff;border-radius:14px 14px 0 0;border:none;padding:.9rem 1.25rem;">
                    <h5 class="modal-title" style="font-weight:700;font-size:1rem;margin:0;"><i class="fas fa-check-circle me-2"></i>Mark as Prepared</h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body" style="padding:1rem 1.25rem;">
                    <div id="otherOrderInfo"></div>
                    <input type="hidden" id="otherOrderNumberInput">
                    <input type="hidden" id="otherLechonOrderIdInput">
                    <p style="color:#555;margin:0;font-size:.9rem;">Confirm this item has been prepared and is ready for pickup or delivery.</p>
                </div>
                <div class="modal-footer" style="border-top:1px solid #f0f0f0;padding:.75rem 1.25rem;">
                    <button type="button" class="btn btn-sm btn-secondary" data-bs-dismiss="modal" style="border-radius:20px;padding:6px 16px;">Cancel</button>
                    <button type="button" id="confirmOtherDoneBtn" class="btn btn-sm" style="background:linear-gradient(135deg,#28a745,#20c997);color:#fff;border:none;border-radius:20px;padding:6px 18px;font-weight:700;">
                        <i class="fas fa-check me-1"></i>Confirm Done
                    </button>
                </div>
            </div>
        </div>
    </div>
</body>
</html>
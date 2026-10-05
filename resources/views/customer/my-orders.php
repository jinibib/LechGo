<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);

$currentPage = 'my-orders';

// Debug session and user info
error_log("My Orders Page - Session: " . json_encode($_SESSION));

$user = $_SESSION['user'] ?? null;
if (!$user) {
    error_log("My Orders Page - No user session, redirecting to login");
    header('Location: /login');
    exit;
}

error_log("My Orders Page - User: " . json_encode($user));

// Get customer ID (user_id is the customer_id for customers)
$customer_id = $user['id'];

// Fetch pig orders from swine_order_status table with computed cost information
$query = "SELECT sos.*, 
                 sos.pin_number,
                 COALESCE(otc.pig_tag_id, sos.pig_tag_id) as pig_tag_id,
                 COALESCE(otc.total_cost, sos.total_price) as total_cost,
                 pd.health_status, pd.age_months, pd.photo_url,
                 u.name AS seller_name, lo.farm_name,
                 hm.description as pig_description,
                 NULL as reservation_status,
                 NULL as confirmed_by_owner_at,
                 CASE WHEN otc.id IS NOT NULL THEN 1 ELSE 0 END as has_total_cost,
                 otc.total_cost as computed_total_cost,
                 otc.pig_base_amount,
                 otc.delivery_method,
                 otc.delivery_fee,
                 otc.labor_cost,
                 otc.include_laman_loob,
                 otc.include_boopes,
                 otc.include_dinuguan,
                 otc.subtotal,
                 otc.computed_at,
                 otc.payment_type,
                 otc.amount_paid,
                 otc.remaining_balance,
                 otc.final_payment_date
          FROM swine_order_status sos
          LEFT JOIN pig_details pd ON pd.id = sos.pig_detail_id
          LEFT JOIN livestock_owners lo ON lo.id = sos.livestock_owner_id
          LEFT JOIN users u ON u.id = lo.user_id
          LEFT JOIN hogs_market hm ON hm.id = sos.hogs_market_id
          LEFT JOIN order_total_cost otc ON otc.swine_order_id = sos.id
          WHERE sos.customer_id = ?
          ORDER BY sos.created_at DESC, sos.id DESC";

$stmt = $GLOBALS['conn']->prepare($query);
if (!$stmt) {
    $prepare_error = $GLOBALS['conn']->error;
    $orders = [];
} else {
    $stmt->bind_param('i', $customer_id);
    if (!$stmt->execute()) {
        $execute_error = $stmt->error;
        $orders = [];
    } else {
        $result = $stmt->get_result();
        $orders = $result->fetch_all(MYSQLI_ASSOC) ?? [];
        $stmt->close();
    }
}

// Debug: Force show count
$order_count = count($orders);

// ── LECHON ORDERS: fetch from lechon_orders table ──────────────────────────
$lechon_orders = [];
// Only attempt query if the table exists (safe guard for first-run)
$tbl_check = $GLOBALS['conn']->query("SHOW TABLES LIKE 'lechon_orders'");
if ($tbl_check && $tbl_check->num_rows > 0) {
    $lch_query = "SELECT lo_ord.*,
                         u.name AS seller_name,
                         owner.farm_name,
                         ll.photo_url
                  FROM lechon_orders lo_ord
                  LEFT JOIN livestock_owners owner ON owner.id = lo_ord.livestock_owner_id
                  LEFT JOIN users u ON u.id = owner.user_id
                  LEFT JOIN lechon_listings ll ON ll.id = lo_ord.lechon_listing_id
                  WHERE lo_ord.customer_id = ?
                  ORDER BY lo_ord.created_at DESC, lo_ord.id DESC";
    $lch_stmt = $GLOBALS['conn']->prepare($lch_query);
    if ($lch_stmt) {
        $lch_stmt->bind_param('i', $customer_id);
        if ($lch_stmt->execute()) {
            $lechon_orders = $lch_stmt->get_result()->fetch_all(MYSQLI_ASSOC) ?? [];
        }
        $lch_stmt->close();
    }
}

// ── Active orders tab (pig vs lechon) from URL ─────────────────────────────
$orders_tab = (isset($_GET['orders_tab']) && $_GET['orders_tab'] === 'lechon') ? 'lechon' : 'pig';

// ── Compute total lechon spending (excludes cancelled orders) ───────────────
$total_lechon_spending = 0;
foreach ($lechon_orders as $lo) {
    $st = $lo['order_status'] ?? 'pending';
    if ($st !== 'cancelled') {
        $total_lechon_spending += (float)($lo['price'] ?? 0);
    }
}
$lechon_order_count = count(array_filter($lechon_orders, fn($lo) => ($lo['order_status'] ?? '') !== 'cancelled'));

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>My Orders - LechGO</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.8/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="/styles.css">
    <style>
        .filter-tabs {
            display: flex;
            gap: 10px;
            margin-bottom: 25px;
            border-bottom: 2px solid #e0e0e0;
            padding-bottom: 0;
            flex-wrap: wrap;
        }

        .filter-tab {
            padding: 10px 20px;
            background: transparent;
            border: none;
            border-bottom: 3px solid transparent;
            cursor: pointer;
            font-weight: 600;
            font-size: 14px;
            color: #666;
            transition: all 0.3s;
            position: relative;
            margin-bottom: -2px;
        }

        .filter-tab:hover {
            color: #D1332D;
            background: #fff5f5;
        }

        .filter-tab.active {
            color: #D1332D;
            border-bottom-color: #D1332D;
            background: #fff5f5;
        }

        .filter-tab .count {
            display: inline-block;
            background: #e0e0e0;
            color: #666;
            padding: 2px 8px;
            border-radius: 12px;
            font-size: 11px;
            margin-left: 6px;
            font-weight: 700;
        }

        .filter-tab.active .count {
            background: #D1332D;
            color: white;
        }

        .status-section {
            margin-bottom: 30px;
        }

        .status-title {
            font-size: 16px;
            font-weight: 600;
            color: var(--dark);
            padding: 12px 15px;
            background: #f5f5f5;
            border-left: 4px solid #3498db;
            border-radius: 5px;
            margin-bottom: 15px;
        }

        .status-title.pending { border-left-color: #f39c12; }
        .status-title.confirmed { border-left-color: #3498db; }
        .status-title.preparing { border-left-color: #9b59b6; }
        .status-title.cost_computed { border-left-color: #e67e22; }
        .status-title.ready_for_pickup { border-left-color: #1abc9c; }
        .status-title.completed { border-left-color: #27ae60; }
        .status-title.cancelled { border-left-color: #e74c3c; }

        .order-card {
            background: white;
            border: 1px solid #e0e0e0;
            border-radius: 8px;
            padding: 15px;
            margin-bottom: 12px;
            display: flex;
            justify-content: space-between;
            align-items: flex-start;
            box-shadow: 0 1px 3px rgba(0,0,0,0.08);
            transition: box-shadow 0.3s;
        }

        .order-card:hover {
            box-shadow: 0 2px 6px rgba(0,0,0,0.12);
        }

        .order-info {
            flex: 1;
        }

        .order-id {
            font-weight: 600;
            color: #2c3e50;
            margin-bottom: 5px;
        }

        .order-lechonero {
            color: #666;
            font-size: 13px;
            margin-bottom: 8px;
        }

        .order-date {
            color: #999;
            font-size: 12px;
            margin-bottom: 10px;
        }

        .order-details {
            display: flex;
            gap: 20px;
            margin-top: 8px;
        }

        .detail {
            font-size: 13px;
        }

        .detail-label {
            color: #999;
            font-size: 11px;
            text-transform: uppercase;
        }

        .detail-value {
            font-weight: 600;
            color: #2c3e50;
        }

        .order-amount {
            font-size: 16px;
            font-weight: 700;
            color: #2ecc71;
            margin-right: 15px;
        }

        .status-badges {
            display: flex;
            gap: 8px;
            margin-bottom: 10px;
            flex-wrap: wrap;
        }

        .status-badge {
            display: inline-block;
            padding: 4px 10px;
            border-radius: 16px;
            font-size: 11px;
            font-weight: 600;
            text-transform: capitalize;
        }

        .badge-pending { background: #fff3cd; color: #856404; }
        .badge-confirmed { background: #d1ecf1; color: #0c5460; }
        .badge-preparing { background: #e2d9f3; color: #692e74; }
        .badge-cost_computed { background: #fdeaa7; color: #b7950b; }
        .badge-ready_for_pickup { background: #d4f4dd; color: #0d6832; }
        .badge-completed { background: #d4edda; color: #155724; }
        .badge-cancelled { background: #f8d7da; color: #721c24; }

        .badge-unpaid { background: #fff3cd; color: #856404; }
        .badge-paid { background: #d4edda; color: #155724; }
        .badge-partially_paid { background: #ffeaa7; color: #d68910; }
        .badge-refunded { background: #e2e3e5; color: #6c757d; }

        .empty-state {
            text-align: center;
            padding: 40px 20px;
            color: #999;
            background: white;
            border-radius: 8px;
        }

        .empty-state-icon {
            font-size: 2.5rem;
            margin-bottom: 15px;
        }

        .container {
            max-width: 1000px;
            margin: 0 auto;
            padding: 20px;
        }

        .btn {
            display: inline-block;
            padding: 8px 14px;
            border: none;
            border-radius: 4px;
            cursor: pointer;
            font-weight: 600;
            text-decoration: none;
            transition: all 0.2s;
            font-size: 12px;
        }

        .btn-primary {
            background-color: #3498db;
            color: white;
        }

        .btn-primary:hover {
            background-color: #2980b9;
        }

        .reservation-info {
            background: #e8f4f8;
            padding: 8px 12px;
            border-radius: 4px;
            margin-top: 8px;
            font-size: 12px;
        }

        .reservation-info strong {
            color: #0c5460;
        }

        /* ── Order-type tabs (Pig Orders / Lechon Orders) ── */
        .ord-type-tabs {
            display: flex;
            gap: 6px;
            margin-bottom: 1.2rem;
            border-bottom: 2px solid #e0e0e0;
            padding-bottom: 0;
        }
        .ord-type-tab {
            padding: 9px 22px;
            background: transparent;
            border: none;
            border-bottom: 3px solid transparent;
            cursor: pointer;
            font-size: .88rem;
            font-weight: 700;
            color: #888;
            margin-bottom: -2px;
            transition: color .15s, border-color .15s;
            border-radius: 6px 6px 0 0;
        }
        .ord-type-tab:hover { color: #D1332D; }
        .ord-type-tab.active {
            color: #D1332D;
            border-bottom-color: #D1332D;
            background: #fff5f5;
        }
        .ord-type-tab .tab-badge {
            display: inline-block;
            background: #e0e0e0;
            color: #555;
            font-size: .62rem;
            font-weight: 800;
            padding: 1px 7px;
            border-radius: 20px;
            margin-left: 5px;
            vertical-align: middle;
        }
        .ord-type-tab.active .tab-badge {
            background: #D1332D;
            color: #fff;
        }
        .ord-type-panel { display: none; }
        .ord-type-panel.active { display: block; }

        /* ── Lechon order card accent ── */
        .lch-order-badge {
            display: inline-block;
            background: rgba(192,57,43,.1);
            color: #c0392b;
            font-size: .65rem;
            font-weight: 700;
            padding: 2px 8px;
            border-radius: 12px;
            text-transform: uppercase;
            letter-spacing: .3px;
            margin-bottom: 4px;
        }
    </style>
</head>
<body>
    <div class="dashboard-layout">
        <?php include __DIR__ . '/../layouts/sidebar.php'; ?>
        
        <main class="dashboard-main">
    <!-- Top Bar with Hamburger Menu -->
    <div class="dashboard-topbar">
        <button class="dashboard-mobile-toggle" id="sidebarToggle">â˜°</button>
        <h1 class="dashboard-topbar-title">My Orders</h1>
        <div class="dashboard-topbar-actions">
            <span class="dashboard-topbar-date"><?php echo date('l, F j, Y'); ?></span>
        </div>
    </div>
    
    <div class="dashboard-content">
        <div class="container">
            <!-- Display Messages -->
            <?php 
            // Check if redirected from PayMongo success
            if (isset($_GET['payment_success']) && $_GET['payment_success'] == '1'): 
            ?>
                <div class="alert alert-success show">
                    Payment completed! Your payment has been received. The webhook will update your order status automatically in a few moments. If not updated, please click "Verify Payment Status" button below.
                </div>
            <?php endif; ?>
            
            <?php if (isset($_GET['payment_cancelled']) && $_GET['payment_cancelled'] == '1'): ?>
                <div class="alert alert-error show">
                    Payment was cancelled or failed. Please try again or contact support if you need assistance.
                </div>
            <?php endif; ?>
            
            <?php if (isset($_SESSION['success'])): ?>
                <div class="alert alert-success show">
                    <?php echo $_SESSION['success']; unset($_SESSION['success']); ?>
                </div>
            <?php endif; ?>

            <?php if (isset($_SESSION['error'])): ?>
                <div class="alert alert-error show">
                    <?php echo $_SESSION['error']; unset($_SESSION['error']); ?>
                </div>
            <?php endif; ?>

            <div style="margin-bottom: var(--spacing-lg); display: flex; justify-content: space-between; align-items: center;">
                <div>
                    <h1>My Orders</h1>
                    <p style="color: #666; margin-top: 5px;">Track all your pig and lechon reservations</p>
                </div>
                <a href="/customer/buy-pig" class="btn btn-primary">+ Buy / Reserve</a>
            </div>

            <!-- Order-type tabs -->
            <div class="ord-type-tabs" role="tablist">
                <button class="ord-type-tab <?php echo $orders_tab === 'pig' ? 'active' : ''; ?>"
                        role="tab"
                        onclick="switchOrdersTab('pig')">
                    Pig Orders
                    <span class="tab-badge"><?php echo count($orders); ?></span>
                </button>
                <button class="ord-type-tab <?php echo $orders_tab === 'lechon' ? 'active' : ''; ?>"
                        role="tab"
                        onclick="switchOrdersTab('lechon')">
                    Lechon Orders
                    <span class="tab-badge"><?php echo count($lechon_orders); ?></span>
                </button>
            </div>

            <!-- ═══════════════════════════════════════════════
                 PANEL — PIG ORDERS  (original — untouched)
            ═══════════════════════════════════════════════ -->
            <div class="ord-type-panel <?php echo $orders_tab === 'pig' ? 'active' : ''; ?>" id="panel-pig-orders">

        <?php if (empty($orders)): ?>
            <div class="empty-state">
                <div class="empty-state-icon"></div>
                <p><strong>No pig orders yet</strong></p>
                <p style="color: #95a5a6; font-size: 14px;">Start buying pigs from our livestock owners</p>
                <br/>
                <a href="/customer/buy-pig" class="btn btn-primary">Browse Available Pigs</a>
            </div>
        <?php else: ?>
            <?php 
                // Group orders by order status
                $grouped_orders = [];
                $status_order = ['confirmed', 'pending', 'preparing', 'cost_computed', 'ready_for_pickup', 'completed', 'cancelled'];
                
                // Count orders by filter category
                $count_all = count($orders);
                $count_new = 0;
                $count_paid = 0;
                $count_unpaid = 0;
                
                foreach ($orders as $order) {
                    // Handle empty or invalid status - default to 'pending'
                    $status = $order['order_status'] ?? 'pending';
                    if (empty($status) || $status === '0' || $status === '') {
                        $status = 'pending';
                    }
                    
                    if (!isset($grouped_orders[$status])) {
                        $grouped_orders[$status] = [];
                    }
                    $grouped_orders[$status][] = $order;
                    
                    // Count for filters
                    if ((in_array($status, ['pending', 'confirmed']) || empty($status) || $status === '0') 
                        && $order['payment_status'] !== 'paid') {
                        $count_new++;
                    }
                    if ($order['payment_status'] === 'paid') {
                        $count_paid++;
                    }
                    // Count partially paid and unpaid orders as pending payment
                    // Only count as "pending payment" if unpaid/partially_paid AND not COD/bank transfer
                    $payment_method = $order['payment_method'] ?? '';
                    if (in_array($order['payment_status'], ['unpaid', 'partially_paid']) && 
                        !in_array($payment_method, ['cash_on_delivery', 'bank_transfer'])) {
                        $count_unpaid++;
                    }
                }
                
                // Sort each group by created_at DESC (newest first)
                foreach ($grouped_orders as $status => $status_orders) {
                    usort($grouped_orders[$status], function($a, $b) {
                        return strtotime($b['created_at']) - strtotime($a['created_at']);
                    });
                }
                
                // Reorder based on status_order
                $sorted_orders = [];
                foreach ($status_order as $status) {
                    if (isset($grouped_orders[$status])) {
                        $sorted_orders[$status] = $grouped_orders[$status];
                    }
                }
            ?>

            <!-- Filter Tabs -->
            <div class="filter-tabs">
                <button class="filter-tab active" data-filter="all">
                     All Orders <span class="count"><?php echo $count_all; ?></span>
                </button>
                <button class="filter-tab" data-filter="new">
                     New Orders <span class="count"><?php echo $count_new; ?></span>
                </button>
                <button class="filter-tab" data-filter="paid">
                     Completed <span class="count"><?php echo $count_paid; ?></span>
                </button>
                <button class="filter-tab" data-filter="unpaid">
                     Pending Payment <span class="count"><?php echo $count_unpaid; ?></span>
                </button>
            </div>

            <div id="ordersContainer">

            <?php foreach ($sorted_orders as $status => $status_orders): ?>
                <div class="status-section" 
                     data-status="<?php echo $status; ?>"
                     data-payment-statuses="<?php echo implode(',', array_unique(array_column($status_orders, 'payment_status'))); ?>">
                    <div class="status-title <?php echo $status; ?>">
                        <?php 
                            $status_icons = [
                                'pending' => '',
                                'confirmed' => '',
                                'preparing' => '',
                                'cost_computed' => '',
                                'ready_for_pickup' => '',
                                'completed' => '',
                                'cancelled' => ''
                            ];
                            echo ($status_icons[$status] ?? '•') . ' ' . ucfirst(str_replace('_', ' ', $status)) . ' (' . count($status_orders) . ')';
                        ?>
                    </div>

                    <?php foreach ($status_orders as $order): ?>
                        <div class="order-card" 
                             data-payment-status="<?php echo $order['payment_status']; ?>"
                             data-order-status="<?php echo $order['order_status']; ?>"
                             data-payment-method="<?php echo htmlspecialchars($order['payment_method'] ?? ''); ?>">
                            <div class="order-info">
                                <div class="order-id">Order #<?php echo htmlspecialchars($order['order_number']); ?></div>  
                                <div class="order-lechonero">
                                    <strong>Seller:</strong> <?php echo htmlspecialchars($order['seller_name'] ?? 'Unknown'); ?>
                                    <?php if (!empty($order['farm_name'])): ?>
                                        - <?php echo htmlspecialchars($order['farm_name']); ?>
                                    <?php endif; ?>
                                </div>
                                <div class="order-date">
                                    Ordered: <?php echo date('M d, Y \a\t H:i', strtotime($order['created_at'])); ?>
                                </div>
                                <div class="order-details">
                                    <?php if (!empty($order['pig_base_amount'])): ?>
                                    <div class="detail">
                                        <div class="detail-label">Base Amount</div>
                                        <div class="detail-value">₱<?php echo number_format($order['pig_base_amount'], 2); ?></div>
                                    </div>
                                    <?php endif; ?>
                                    
                                    <?php if (!empty($order['delivery_fee']) && $order['delivery_fee'] > 0): ?>
                                    <div class="detail">
                                        <div class="detail-label">Delivery Fee</div>
                                        <div class="detail-value">₱<?php echo number_format($order['delivery_fee'], 2); ?></div>
                                    </div>
                                    <?php endif; ?>
                                    
                                    <?php if (!empty($order['labor_cost']) && $order['labor_cost'] > 0): ?>
                                    <div class="detail">
                                        <div class="detail-label">Labor Cost</div>
                                        <div class="detail-value">₱<?php echo number_format($order['labor_cost'], 2); ?></div>
                                    </div>
                                    <?php endif; ?>
                                    
                                    <?php if (!empty($order['pickup_date'])): ?>
                                    <div class="detail">
                                        <div class="detail-label">Pickup Date</div>
                                        <div class="detail-value"><?php echo date('M d, Y', strtotime($order['pickup_date'])); ?></div>
                                    </div>
                                    <?php endif; ?>
                                    
                                    <?php if (!empty($order['has_total_cost'])): ?>
                                    <div class="detail">
                                        <div class="detail-label">Computed</div>
                                        <div class="detail-value"><?php echo date('M d, Y', strtotime($order['computed_at'])); ?></div>
                                    </div>
                                    <?php endif; ?>
                                    
                                    <div class="detail">
                                        <div class="detail-label">Payment</div>
                                        <div class="detail-value">
                                            <?php 
                                            $payment_display = ucfirst($order['payment_status']);
                                            
                                            // Better display names for payment status
                                            $status_names = [
                                                'unpaid' => 'Unpaid',
                                                'paid' => 'Paid',
                                                'partially_paid' => 'Partially Paid',
                                                'refunded' => 'Refunded'
                                            ];
                                            
                                            $payment_display = $status_names[$order['payment_status']] ?? ucfirst($order['payment_status']);
                                            if (!empty($order['payment_method'])) {
                                                $method_names = [
                                                    'cash_on_delivery' => 'COD',
                                                    'bank_transfer' => 'Bank',
                                                    'paymongo' => 'Online'
                                                ];
                                                $method = $method_names[$order['payment_method']] ?? ucfirst($order['payment_method']);
                                                $payment_display .= " ($method)";
                                            }
                                            
                                            // Show payment type if available
                                            if (!empty($order['payment_type'])) {
                                                if ($order['payment_type'] === 'down') {
                                                    $payment_display .= " - Down Payment";
                                                } else {
                                                    $payment_display .= " - Full Payment";
                                                }
                                            }
                                            
                                            echo $payment_display;
                                            ?>
                                        </div>
                                    </div>
                                    
                                    <?php if (!empty($order['payment_type']) && $order['payment_type'] === 'down' && in_array($order['payment_status'], ['paid', 'partially_paid'])): ?>
                                    <div class="detail">
                                        <div class="detail-label">Amount Paid</div>
                                        <div class="detail-value" style="color: #27ae60; font-weight: 600;">
                                            ₱<?php echo number_format($order['amount_paid'] ?? 0, 2); ?>
                                        </div>
                                    </div>
                                    <div class="detail">
                                        <div class="detail-label">Remaining Balance</div>
                                        <div class="detail-value" style="color: #e74c3c; font-weight: 600;">
                                            ₱<?php echo number_format($order['remaining_balance'] ?? 0, 2); ?>
                                        </div>
                                    </div>
                                    <?php endif; ?>
                                </div>

                                <?php if (isset($order['seller_feedback']) && !empty($order['seller_feedback'])): ?>
                                    <div class="reservation-info">
                                        <strong>Message from Seller:</strong> "<?php echo htmlspecialchars($order['seller_feedback']); ?>"
                                    </div>
                                <?php endif; ?>

                                <?php if ($order['reservation_status'] === 'confirmed' && $order['confirmed_by_owner_at']): ?>
                                    <div class="reservation-info" style="background: #d4edda; color: #155724;">
                                        <strong> Confirmed by seller:</strong> 
                                        <?php echo date('M d, Y \a\t H:i', strtotime($order['confirmed_by_owner_at'])); ?>
                                    </div>
                                <?php endif; ?>

                                <?php if (!empty($order['has_total_cost']) && !empty($order['computed_total_cost'])): ?>
                                    <div class="reservation-info" style="background: #fff3cd; color: #856404; border-left-color: #ffc107;">
                                        <strong> Total Cost Computed:</strong> 
                                        ₱<?php echo number_format($order['computed_total_cost'], 2); ?>
                                        <div style="font-size: 11px; margin-top: 4px; color: #666;">
                                            <?php
                                            $cost_breakdown = [];
                                            if (!empty($order['pig_base_amount'])) {
                                                $cost_breakdown[] = 'Base: ₱' . number_format($order['pig_base_amount'], 2);
                                            }
                                            if (!empty($order['delivery_fee']) && $order['delivery_fee'] > 0) {
                                                $cost_breakdown[] = 'Delivery: ₱' . number_format($order['delivery_fee'], 2);
                                            }
                                            if (!empty($order['labor_cost']) && $order['labor_cost'] > 0) {
                                                $cost_breakdown[] = 'Labor: ₱' . number_format($order['labor_cost'], 2);
                                            }
                                            if (!empty($order['include_laman_loob'])) {
                                                $cost_breakdown[] = 'Laman Loob (Free)';
                                            }
                                            if (!empty($order['include_boopes'])) {
                                                $cost_breakdown[] = 'Boopes (Free)';
                                            }
                                            if (!empty($order['include_dinuguan'])) {
                                                $cost_breakdown[] = 'Dinuguan (Free)';
                                            }
                                            if (!empty($cost_breakdown)) {
                                                echo implode(' • ', $cost_breakdown);
                                            }
                                            ?>
                                        </div>
                                    </div>
                                <?php endif; ?>
                            </div>

                                <div style="text-align: right;">
                                <div class="order-amount">
                                    <?php if (!empty($order['has_total_cost']) && !empty($order['computed_total_cost'])): ?>
                                        ₱<?php echo number_format($order['computed_total_cost'], 2); ?>
                                        <?php if ($order['computed_total_cost'] != $order['total_price']): ?>
                                            <div style="font-size: 12px; color: #999; text-decoration: line-through;">
                                                ₱<?php echo number_format($order['total_price'], 2); ?>
                                            </div>
                                        <?php endif; ?>
                                    <?php else: ?>
                                        ₱<?php echo number_format($order['total_cost'], 2); ?>
                                        <?php if (empty($order['has_total_cost'])): ?>
                                            <div style="font-size: 11px; color: #f39c12; margin-top: 2px;">
                                                (Base price - awaiting computation)
                                            </div>
                                        <?php endif; ?>
                                    <?php endif; ?>
                                </div>
                                <div class="status-badges">
                                    <span class="status-badge badge-<?php echo $order['order_status']; ?>">
                                        <?php echo ucfirst(str_replace('_', ' ', $order['order_status'])); ?>
                                    </span>
                                    <span class="status-badge badge-<?php echo $order['payment_status']; ?>">
                                        <?php echo ucfirst($order['payment_status']); ?>
                                    </span>
                                </div>
                                <?php if ($order['order_status'] === 'confirmed'): ?>
                                <div style="margin-top: 10px;">
                                    <button class="btn btn-primary" style="font-size: 12px; padding: 6px 12px;"
                                        onclick="openPlaceOrderModal(
                                            <?php echo $order['id']; ?>,
                                            '<?php echo htmlspecialchars(addslashes($order['order_number'])); ?>',
                                            '<?php echo htmlspecialchars(addslashes($order['pig_tag_id'])); ?>',
                                            <?php echo $order['total_price']; ?>
                                        )">
                                     Place Order
                                    </button>
                                </div>
                                <?php endif; ?>
                                
                                <?php 
                                // Show Pay Now button only if:
                                // 1. Payment status is unpaid
                                // 2. Payment method is NOT COD or bank transfer
                                // 3. Order status is preparing, cost_computed, ready_for_pickup, OR empty/pending with computed cost
                                // 4. Livestock owner has computed the total cost (order_total_cost record exists)
                                $actual_status = $order['order_status'] ?? '';
                                $payment_method = $order['payment_method'] ?? '';
                                $is_cod_or_bank = in_array($payment_method, ['cash_on_delivery', 'bank_transfer']);
                                
                                $can_pay = $order['payment_status'] === 'unpaid' 
                                        && !$is_cod_or_bank
                                        && (in_array($actual_status, ['preparing', 'cost_computed', 'ready_for_pickup']) 
                                            || (empty($actual_status) && !empty($order['has_total_cost'])))
                                        && !empty($order['has_total_cost']);
                                
                                // Show verify payment button if payment method is paymongo and status is unpaid
                                $show_verify_button = (
                                    $payment_method === 'paymongo' &&
                                    $order['payment_status'] === 'unpaid' &&
                                    !empty($order['payment_reference'])
                                );
                                ?>
                                
                                <?php if ($can_pay): ?>
                                <div style="margin-top: 10px;">
                                    <a href="/customer/pay-order?order_id=<?php echo $order['id']; ?>" 
                                       class="btn btn-primary" 
                                       style="font-size: 12px; padding: 6px 12px; background: #27ae60; text-decoration: none; display: inline-block;">
                                        Pay Now
                                    </a>
                                </div>
                                <?php endif; ?>
                                
                                <?php if ($show_verify_button): ?>
                                <div style="margin-top: 10px;">
                                    <button class="btn btn-primary" 
                                       style="font-size: 11px; padding: 5px 10px; background: #3498db;"
                                       onclick="verifyPayment(<?php echo $order['id']; ?>, '<?php echo htmlspecialchars($order['payment_reference']); ?>')">
                                        Verify Payment Status
                                    </button>
                                    <div style="font-size: 10px; color: #999; margin-top: 4px;">
                                        Already paid? Click to check payment status
                                    </div>
                                </div>
                                <?php elseif (in_array($order['payment_status'], ['paid', 'partially_paid']) && !empty($order['payment_type']) && $order['payment_type'] === 'down' && $order['remaining_balance'] > 0): ?>
                                <!-- Show Pay Remaining Balance button for down payment orders -->
                                <div style="margin-top: 10px;">
                                    <a href="/customer/pay-remaining-balance?order_id=<?php echo $order['id']; ?>" 
                                       class="btn btn-warning" 
                                       style="font-size: 12px; padding: 6px 12px; background: #f39c12; text-decoration: none; display: inline-block; color: white;">
                                        Pay Remaining Balance (₱<?php echo number_format($order['remaining_balance'], 2); ?>)
                                    </a>
                                    <div style="font-size: 10px; color: #666; margin-top: 4px; text-align: center;">
                                        Pay when lechon arrives
                                    </div>
                                </div>
                                <?php elseif ($order['payment_status'] === 'paid' && !empty($order['payment_type']) && $order['payment_type'] === 'down' && $order['remaining_balance'] == 0 && !empty($order['final_payment_date'])): ?>
                                <!-- Fully paid down payment order -->
                                <div style="margin-top: 10px;">
                                    <div style="font-size: 11px; color: #27ae60; padding: 6px 12px; background: #d4edda; border-radius: 4px; text-align: center;">
                                         Fully Paid (Final payment: <?php echo date('M d, Y', strtotime($order['final_payment_date'])); ?>)
                                    </div>
                                </div>
                                <?php elseif ($order['payment_status'] === 'unpaid' && $is_cod_or_bank): ?>
                                <div style="margin-top: 10px;">
                                    <div style="font-size: 11px; color: #666; padding: 6px 12px; background: #f0f0f0; border-radius: 4px; text-align: center;">
                                        <?php 
                                        if ($payment_method === 'cash_on_delivery') {
                                            echo ' Pay upon delivery';
                                        } elseif ($payment_method === 'bank_transfer') {
                                            echo ' Awaiting bank transfer';
                                        }
                                        ?>
                                    </div>
                                </div>
                                <?php elseif ($order['payment_status'] === 'unpaid' && empty($order['has_total_cost'])): ?>
                                <div style="margin-top: 10px;">
                                    <button class="btn btn-primary" 
                                            style="font-size: 12px; padding: 6px 12px; background: #95a5a6; cursor: not-allowed;"
                                            disabled
                                            title="Waiting for seller to compute total cost">
                                         Waiting for Cost Computation
                                    </button>
                                </div>
                                <?php endif; ?>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php endforeach; ?>
            </div> <!-- End ordersContainer -->
        <?php endif; ?>

            </div><!-- /panel-pig-orders -->

            <!-- ═══════════════════════════════════════════════
                 PANEL — LECHON ORDERS  (new)
            ═══════════════════════════════════════════════ -->
            <div class="ord-type-panel <?php echo $orders_tab === 'lechon' ? 'active' : ''; ?>" id="panel-lechon-orders">

                <!-- ── Total Lechon Spending Summary Card ── -->
                <div style="
                    background: linear-gradient(135deg, #c0392b, #922b21);
                    color: white;
                    border-radius: 12px;
                    padding: 1.25rem 1.5rem;
                    margin-bottom: 1.5rem;
                    display: flex;
                    align-items: center;
                    justify-content: space-between;
                    flex-wrap: wrap;
                    gap: 1rem;
                    box-shadow: 0 2px 8px rgba(192,57,43,.25);
                ">
                    <div>
                        <div style="font-size: 0.78rem; font-weight: 600; opacity: .85; text-transform: uppercase; letter-spacing: .05em; margin-bottom: .25rem;">
                            <i class="fas fa-crown" style="margin-right:4px;"></i>
                            Total Amount Spent on Lechon Orders
                        </div>
                        <div style="font-size: 2rem; font-weight: 700; line-height: 1;">
                            ₱<?php echo number_format($total_lechon_spending, 2); ?>
                        </div>
                        <div style="font-size: 0.8rem; opacity: .8; margin-top: .25rem;">
                            <?php echo $lechon_order_count; ?> active order<?php echo $lechon_order_count !== 1 ? 's' : ''; ?> (excluding cancelled)
                        </div>
                    </div>
                    <div style="text-align: right;">
                        <div style="font-size: 0.78rem; opacity: .85; margin-bottom: .25rem;">High-Status Category</div>
                        <div style="background: rgba(255,255,255,.15); border-radius: 8px; padding: .5rem 1rem; font-size: .85rem; font-weight: 600;">
                            🔥 Lechon
                        </div>
                        <div style="font-size: 0.75rem; opacity: .75; margin-top: .4rem;">
                            Used in Status Spending %
                        </div>
                    </div>
                </div>

                <?php if (empty($lechon_orders)): ?>
                    <div class="empty-state">
                        <div class="empty-state-icon"></div>
                        <p><strong>No lechon orders yet</strong></p>
                        <p style="color: #95a5a6; font-size: 14px;">Reserve cooked lechon from our sellers</p>
                        <br/>
                        <a href="/customer/buy-pig?tab=lechon" class="btn btn-primary">Browse Lechon Listings</a>
                    </div>
                <?php else: ?>
                    <?php
                    $lch_status_order = ['confirmed','pending','preparing','cost_computed','ready_for_pickup','completed','cancelled'];
                    $lch_grouped = [];
                    foreach ($lechon_orders as $lo) {
                        $st = $lo['order_status'] ?? 'pending';
                        if (empty($st) || $st === '0') $st = 'pending';
                        $lch_grouped[$st][] = $lo;
                    }
                    $lch_sorted = [];
                    foreach ($lch_status_order as $st) {
                        if (isset($lch_grouped[$st])) $lch_sorted[$st] = $lch_grouped[$st];
                    }
                    $lch_status_icons = [
                        'pending'          => '',
                        'confirmed'        => '',
                        'preparing'        => '',
                        'cost_computed'    => '',
                        'ready_for_pickup' => '',
                        'completed'        => '',
                        'cancelled'        => '',
                    ];
                    $lch_cat_labels = [
                        'WHOLE_LECHON'   => 'Whole Lechon',
                        'WHOLE_PACKAGES' => 'w/ Packages',
                        'BELLY_BUNDLES'  => 'Belly Bundles',
                        'WEEKDAY_COMBOS' => 'Weekday Combos',
                        'EVENT_CATERING' => 'Event / Catering',
                        'OTHER'          => 'Other',
                    ];
                    ?>
                    <?php foreach ($lch_sorted as $status => $lch_status_orders): ?>
                        <div class="status-section">
                            <div class="status-title <?php echo $status; ?>">
                                <?php echo ($lch_status_icons[$status] ?? '') . ' ' . ucfirst(str_replace('_', ' ', $status)) . ' (' . count($lch_status_orders) . ')'; ?>
                            </div>

                            <?php foreach ($lch_status_orders as $lo): ?>
                            <div class="order-card">
                                <div class="order-info">
                                    <div class="lch-order-badge">
                                        <?php echo htmlspecialchars($lch_cat_labels[$lo['category']] ?? $lo['category']); ?>
                                    </div>
                                    <div class="order-id">Order #<?php echo htmlspecialchars($lo['order_number']); ?></div>
                                    <div style="font-weight:700;font-size:.92rem;color:#222;margin-bottom:3px;">
                                        <?php echo htmlspecialchars($lo['listing_name']); ?>
                                    </div>
                                    <div class="order-lechonero">
                                        <strong>Seller:</strong> <?php echo htmlspecialchars($lo['seller_name'] ?? 'Unknown'); ?>
                                        <?php if (!empty($lo['farm_name'])): ?>
                                            - <?php echo htmlspecialchars($lo['farm_name']); ?>
                                        <?php endif; ?>
                                    </div>
                                    <div class="order-date">
                                        Ordered: <?php echo date('M d, Y \a\t H:i', strtotime($lo['created_at'])); ?>
                                    </div>

                                    <div class="order-details">
                                        <?php if (!empty($lo['weight_kg'])): ?>
                                        <div class="detail">
                                            <div class="detail-label">Weight</div>
                                            <div class="detail-value"><?php echo number_format($lo['weight_kg'],1); ?> kg</div>
                                        </div>
                                        <?php endif; ?>
                                        <?php if (!empty($lo['serving_capacity'])): ?>
                                        <div class="detail">
                                            <div class="detail-label">Good For</div>
                                            <div class="detail-value"><?php echo htmlspecialchars($lo['serving_capacity']); ?></div>
                                        </div>
                                        <?php endif; ?>
                                        <?php if (!empty($lo['pickup_date'])): ?>
                                        <div class="detail">
                                            <div class="detail-label">Pickup Date</div>
                                            <div class="detail-value"><?php echo date('M d, Y', strtotime($lo['pickup_date'])); ?></div>
                                        </div>
                                        <?php endif; ?>
                                        <div class="detail">
                                            <div class="detail-label">Payment</div>
                                            <div class="detail-value"><?php echo ucfirst($lo['payment_status']); ?></div>
                                        </div>
                                    </div>

                                    <?php if (!empty($lo['inquiry_message'])): ?>
                                    <div class="reservation-info" style="margin-top:8px;">
                                        <strong>Your message:</strong> "<?php echo htmlspecialchars($lo['inquiry_message']); ?>"
                                    </div>
                                    <?php endif; ?>
                                    
                                    <?php if (!empty($lo['delivery_method']) && $lo['delivery_method'] === 'delivery' && !empty($lo['delivery_address'])): ?>
                                    <div class="reservation-info" style="margin-top:8px; background: #e8f4f8; border-left: 3px solid #3498db;">
                                        <strong>🚚 Delivery to:</strong> <?php echo htmlspecialchars($lo['delivery_address']); ?>
                                        <?php if (!empty($lo['delivery_notes'])): ?>
                                            <br><span style="font-size: 0.9em; color: #666;"><em>Note: <?php echo htmlspecialchars($lo['delivery_notes']); ?></em></span>
                                        <?php endif; ?>
                                    </div>
                                    <?php elseif (!empty($lo['delivery_method']) && $lo['delivery_method'] === 'pickup'): ?>
                                    <div class="reservation-info" style="margin-top:8px; background: #fef5e7; border-left: 3px solid #f39c12;">
                                        <strong>📦 For Pickup</strong>
                                        <?php if (!empty($lo['delivery_notes'])): ?>
                                            <br><span style="font-size: 0.9em; color: #666;"><em>Note: <?php echo htmlspecialchars($lo['delivery_notes']); ?></em></span>
                                        <?php endif; ?>
                                    </div>
                                    <?php endif; ?>
                                    
                                    <?php if (!empty($lo['seller_feedback'])): ?>
                                    <div class="reservation-info" style="margin-top:6px;">
                                        <strong>Seller reply:</strong> "<?php echo htmlspecialchars($lo['seller_feedback']); ?>"
                                    </div>
                                    <?php endif; ?>
                                </div>

                                <div style="text-align:right;min-width:120px;">
                                    <div class="order-amount">
                                        ₱<?php echo number_format($lo['price'], 2); ?>
                                    </div>
                                    <div class="status-badges">
                                        <span class="status-badge badge-<?php echo $lo['order_status']; ?>">
                                            <?php echo ucfirst(str_replace('_', ' ', $lo['order_status'])); ?>
                                        </span>
                                        <span class="status-badge badge-<?php echo $lo['payment_status']; ?>">
                                            <?php echo ucfirst($lo['payment_status']); ?>
                                        </span>
                                    </div>
                                    <?php if ($lo['order_status'] === 'pending'): ?>
                                    <div style="margin-top:8px;font-size:11px;color:#f39c12;background:#fff3cd;padding:5px 8px;border-radius:4px;">
                                        Waiting for seller confirmation
                                    </div>
                                    <div style="margin-top:6px;">
                                        <form method="POST" action="/customer/cancel-lechon-order"
                                              onsubmit="return confirm('Cancel this lechon order?')">
                                            <input type="hidden" name="order_id" value="<?php echo (int)$lo['id']; ?>">
                                            <button type="submit"
                                                    style="width:100%;background:#e74c3c;color:#fff;border:none;border-radius:5px;padding:6px 10px;font-size:11px;font-weight:700;cursor:pointer;">
                                                Cancel Order
                                            </button>
                                        </form>
                                    </div>
                                    <?php elseif ($lo['order_status'] === 'confirmed'): ?>
                                    <div style="margin-top:8px;font-size:11px;color:#0c5460;background:#d1ecf1;padding:5px 8px;border-radius:4px;">
                                        Order confirmed — seller is preparing
                                    </div>
                                    <div style="margin-top:8px;">
                                        <button class="btn btn-primary"
                                                style="font-size:12px;padding:6px 12px;width:100%;"
                                                onclick="openLechonPlaceOrderModal(
                                                    <?php echo (int)$lo['id']; ?>,
                                                    '<?php echo htmlspecialchars(addslashes($lo['order_number'])); ?>',
                                                    '<?php echo htmlspecialchars(addslashes($lo['listing_name'])); ?>',
                                                    <?php echo (float)$lo['price']; ?>
                                                )">
                                            Place Order
                                        </button>
                                    </div>
                                    <?php elseif ($lo['order_status'] === 'preparing'): ?>
                                    <div style="margin-top:8px;font-size:11px;color:#6c3483;background:#e8daef;padding:5px 8px;border-radius:4px;">
                                        Order placed — seller is preparing your lechon
                                    </div>
                                    <?php if ($lo['payment_status'] === 'unpaid'): ?>
                                    <div style="margin-top:10px;">
                                        <a href="/customer/pay-order?order_id=<?php echo (int)$lo['id']; ?>&order_type=lechon" 
                                           class="btn btn-primary" 
                                           style="font-size:12px;padding:6px 12px;background:#27ae60;text-decoration:none;display:inline-block;width:100%;text-align:center;">
                                            Pay Now
                                        </a>
                                    </div>
                                    <?php elseif ($lo['payment_status'] === 'paid'): ?>
                                    <div style="margin-top:10px;font-size:11px;color:#27ae60;background:#d4edda;padding:5px 8px;border-radius:4px;">
                                        Paid
                                    </div>
                                    <?php endif; ?>
                                    <?php elseif ($lo['order_status'] === 'cancelled'): ?>
                                    <div style="margin-top:8px;font-size:11px;color:#721c24;background:#f8d7da;padding:5px 8px;border-radius:4px;">
                                        Cancelled<?php echo !empty($lo['cancellation_reason']) ? ': ' . htmlspecialchars($lo['cancellation_reason']) : ''; ?>
                                    </div>
                                    <?php endif; ?>
                                </div>
                            </div>
                            <?php endforeach; ?>
                        </div>
                    <?php endforeach; ?>
                <?php endif; ?>

            </div><!-- /panel-lechon-orders -->

        </div>
    </div> <!-- Close dashboard-content -->
    </main>

    </div>

<!-- Place Order Modal -->
<div class="pm-overlay" id="placeOrderModal" onclick="if(event.target===this)closePlaceOrderModal()">
    <div class="pm-modal">
        <div class="pm-modal-header" style="background:#D1332D;">
            <h3>Place Your Order</h3>
            <button class="pm-modal-close" onclick="closePlaceOrderModal()">✕</button>
        </div>
        <div class="pm-modal-body">
            <div class="pm-order-summary">
                <span id="po_order_number"></span>
                <div class="po_amount" id="po_amount"></div>
            </div>
            <form method="POST" action="/customer/place-order">
                <input type="hidden" name="swine_order_id" id="po_swine_order_id">
                <input type="hidden" name="order_number" id="po_order_number_input">
                
                <div class="pm-field">
                    <label>Delivery Method <span style="color:red;">*</span></label>
                    <div style="display:flex; gap:15px; margin-top:4px;">
                        <label style="display:flex; align-items:center; gap:6px; cursor:pointer; font-weight:400;">
                            <input type="radio" name="delivery_method" value="pickup" checked onchange="toggleDeliveryAddress()">
                            <span> Pickup</span>
                        </label>
                        <label style="display:flex; align-items:center; gap:6px; cursor:pointer; font-weight:400;">
                            <input type="radio" name="delivery_method" value="delivery" onchange="toggleDeliveryAddress()">
                            <span> Delivery</span>
                        </label>
                    </div>
                </div>

                <div class="pm-field" id="delivery_address_field" style="height:0; overflow:hidden; opacity:0; margin-bottom:0; transition:height 0.3s ease, opacity 0.3s ease, margin-bottom 0.3s ease;">
                    <label>Delivery Address <span style="color:red;">*</span></label>
                    <textarea name="delivery_address" id="po_delivery_address" 
                        placeholder="Enter your complete delivery address"></textarea>
                </div>

                <div class="pm-field">
                    <label>Additional Items <span style="color:#aaa;font-weight:400;">(optional)</span></label>
                    <div style="display:grid; grid-template-columns:auto 1fr; gap:6px 10px; margin-top:4px; align-items:center;">
                        <input type="radio" name="additional_item" value="laman_loob" id="cb_laman" style="margin:0;">
                        <label for="cb_laman" style="cursor:pointer; font-weight:400; margin:0;">Laman Loob (Innards)</label>
                        
                        <input type="radio" name="additional_item" value="boopes" id="cb_boopes" style="margin:0;">
                        <label for="cb_boopes" style="cursor:pointer; font-weight:400; margin:0;">Boopes (Intestines)</label>
                        
                        <input type="radio" name="additional_item" value="dinuguan" id="cb_dinuguan" style="margin:0;">
                        <label for="cb_dinuguan" style="cursor:pointer; font-weight:400; margin:0;">Dinuguan (Blood Stew)</label>
                    </div>
                </div>

                <div class="pm-field">
                    <label>Preferred Pickup/Delivery Date <span style="color:red;">*</span></label>
                    <input type="date" name="pickup_date" id="po_pickup_date" 
                        style="width:100%; padding:8px 10px; border:1.5px solid #e0e0e0; border-radius:7px; font-size:.85rem; box-sizing:border-box; outline:none;"
                        required>
                    <small style="color:#666; font-size:.7rem; margin-top:4px; display:block;">
                        Select when you want the pig to be delivered or when you'll pick it up
                    </small>
                </div>

                <div class="pm-field">
                    <label>Delivery Notes <span style="color:#aaa;font-weight:400;">(optional)</span></label>
                    <textarea name="delivery_notes" id="po_delivery_notes" 
                        placeholder="e.g. Landmark, special instructions"></textarea>
                </div>

                <div class="pm-actions">
                    <button type="button" class="pm-btn-cancel" onclick="closePlaceOrderModal()">Cancel</button>
                    <button type="submit" class="pm-btn-submit">Place Order</button>
                </div>
            </form>
        </div>
    </div>
</div>

<style>
.pm-overlay { display:none; position:fixed; inset:0; background:rgba(0,0,0,.45); z-index:1000; align-items:center; justify-content:center; }
.pm-overlay.open { display:flex; }
.pm-modal { background:#fff; border-radius:14px; width:92%; max-width:500px; box-shadow:0 8px 32px rgba(0,0,0,.18); overflow:hidden; }
.pm-modal-header { color:#fff; padding:12px 16px; display:flex; align-items:center; justify-content:space-between; }
.pm-modal-header h3 { margin:0; font-size:1rem; }
.pm-modal-close { background:none; border:none; color:#fff; font-size:1.3rem; cursor:pointer; line-height:1; }
.pm-modal-body { padding:14px 16px; }
.pm-order-summary { background:#FADBD8; border-radius:8px; padding:10px 12px; margin-bottom:12px; }
.pm-order-summary strong { color:#D1332D; font-size:1rem; display:block; margin-bottom:3px; }
.pm-order-summary span { font-size:.8rem; color:#666; }
.po_amount { font-size:1.2rem; font-weight:700; color:#2ecc71; margin-top:4px; }
.pm-field { margin-bottom:10px; }
.pm-field label { display:block; font-size:.78rem; font-weight:700; color:#444; margin-bottom:4px; }
.pm-field textarea { width:100%; padding:8px 10px; border:1.5px solid #e0e0e0; border-radius:7px; font-size:.85rem; box-sizing:border-box; resize:vertical; min-height:70px; outline:none; }
.pm-field textarea:focus { border-color:#D1332D; }
.pm-field input[type="date"] { width:100%; padding:8px 10px; border:1.5px solid #e0e0e0; border-radius:7px; font-size:.85rem; box-sizing:border-box; outline:none; }
.pm-field input[type="date"]:focus { border-color:#D1332D; }
.pm-actions { display:flex; gap:8px; margin-top:12px; }
.pm-btn-submit { flex:1; background:#D1332D; color:#fff; border:none; border-radius:7px; padding:10px; font-size:.88rem; font-weight:700; cursor:pointer; }
.pm-btn-submit:hover { background:#A00D0A; }
.pm-btn-cancel { flex:1; background:#f0f0f0; color:#555; border:none; border-radius:7px; padding:10px; font-size:.88rem; font-weight:700; cursor:pointer; }
</style>

<script>
function toggleDeliveryAddress() {
    const deliveryMethod = document.querySelector('input[name="delivery_method"]:checked').value;
    const addressField = document.getElementById('delivery_address_field');
    const addressTextarea = document.getElementById('po_delivery_address');
    
    if (deliveryMethod === 'delivery') {
        // Show address field with smooth transition
        addressField.style.overflow = 'visible';
        addressField.style.height = 'auto';
        addressField.style.opacity = '1';
        addressField.style.marginBottom = '15px';
        addressTextarea.required = true;
    } else {
        // Hide address field with smooth transition
        addressField.style.overflow = 'hidden';
        addressField.style.height = '0';
        addressField.style.opacity = '0';
        addressField.style.marginBottom = '0';
        addressTextarea.required = false;
        addressTextarea.value = '';
    }
}

function openPlaceOrderModal(orderId, orderNumber, pigTag, totalPrice) {
    document.getElementById('po_swine_order_id').value = orderId;
    document.getElementById('po_order_number_input').value = orderNumber;
    document.getElementById('po_order_number').textContent = 'Order #' + orderNumber;
    document.getElementById('po_amount').textContent = '₱' + totalPrice.toLocaleString('en-PH', {minimumFractionDigits:2});
    document.getElementById('po_delivery_address').value = '';
    document.getElementById('po_pickup_date').value = '';
    document.getElementById('po_delivery_notes').value = '';
    
    // Reset to pickup by default and hide address field
    document.querySelector('input[name="delivery_method"][value="pickup"]').checked = true;
    const addressField = document.getElementById('delivery_address_field');
    addressField.style.height = '0';
    addressField.style.opacity = '0';
    addressField.style.marginBottom = '0';
    document.getElementById('po_delivery_address').required = false;
    
    // Set minimum date to tomorrow
    const tomorrow = new Date();
    tomorrow.setDate(tomorrow.getDate() + 1);
    const minDate = tomorrow.toISOString().split('T')[0];
    document.getElementById('po_pickup_date').min = minDate;
    
    document.getElementById('placeOrderModal').classList.add('open');
}

function closePlaceOrderModal() {
    document.getElementById('placeOrderModal').classList.remove('open');
}

// Filter functionality
document.addEventListener('DOMContentLoaded', function() {
    console.log('Filter tabs initializing...');
    
    const filterTabs = document.querySelectorAll('.filter-tab');
    const statusSections = document.querySelectorAll('.status-section');
    
    console.log('Found tabs:', filterTabs.length);
    console.log('Found sections:', statusSections.length);
    
    if (filterTabs.length === 0) {
        console.error('No filter tabs found!');
        return;
    }
    
    filterTabs.forEach(tab => {
        tab.addEventListener('click', function(e) {
            e.preventDefault();
            const filter = this.getAttribute('data-filter');
            console.log('Filter clicked:', filter);
            
            // Update active tab
            filterTabs.forEach(t => t.classList.remove('active'));
            this.classList.add('active');
            
            let totalVisibleOrders = 0;
            
            // Filter orders
            statusSections.forEach(section => {
                const sectionStatus = section.getAttribute('data-status');
                const orderCards = section.querySelectorAll('.order-card');
                let visibleCount = 0;
                
                orderCards.forEach(card => {
                    const paymentStatus = card.getAttribute('data-payment-status');
                    const orderStatus = card.getAttribute('data-order-status');
                    const paymentMethod = card.getAttribute('data-payment-method');
                    let shouldShow = false;
                    
                    if (filter === 'all') {
                        shouldShow = true;
                    } else if (filter === 'new') {
                        // Show orders with pending or confirmed status, OR empty status, BUT exclude paid orders
                        shouldShow = (orderStatus === 'pending' || 
                                    orderStatus === 'confirmed' || 
                                    orderStatus === '' || 
                                    orderStatus === '0' ||
                                    !orderStatus) && 
                                    paymentStatus !== 'paid';
                    } else if (filter === 'paid') {
                        shouldShow = paymentStatus === 'paid';
                    } else if (filter === 'unpaid') {
                        // Show unpaid and partially paid orders that are NOT COD or bank transfer
                        shouldShow = (paymentStatus === 'unpaid' || paymentStatus === 'partially_paid') && 
                                   paymentMethod !== 'cash_on_delivery' && 
                                   paymentMethod !== 'bank_transfer';
                    }
                    
                    if (shouldShow) {
                        card.style.display = 'flex';
                        visibleCount++;
                        totalVisibleOrders++;
                    } else {
                        card.style.display = 'none';
                    }
                });
                
                // Hide section if no visible orders
                if (visibleCount === 0) {
                    section.style.display = 'none';
                } else {
                    section.style.display = 'block';
                }
            });
            
            console.log('Total visible orders:', totalVisibleOrders);
            
            // Show empty state if no orders visible
            const ordersContainer = document.getElementById('ordersContainer');
            let emptyState = document.getElementById('filterEmptyState');
            
            if (totalVisibleOrders === 0 && filter !== 'all') {
                let emptyMessage = '';
                if (filter === 'new') {
                    emptyMessage = ' No new orders';
                } else if (filter === 'paid') {
                    emptyMessage = ' No paid orders yet';
                } else if (filter === 'unpaid') {
                    emptyMessage = ' No pending payments';
                }
                
                if (!emptyState) {
                    emptyState = document.createElement('div');
                    emptyState.id = 'filterEmptyState';
                    emptyState.className = 'empty-state';
                    if (ordersContainer) {
                        ordersContainer.appendChild(emptyState);
                    }
                }
                if (emptyState) {
                    emptyState.innerHTML = `
                        <div class="empty-state-icon"></div>
                        <p><strong>${emptyMessage}</strong></p>
                    `;
                    emptyState.style.display = 'block';
                }
            } else {
                if (emptyState) {
                    emptyState.style.display = 'none';
                }
            }
        });
    });
    
    console.log('Filter tabs initialized successfully');
});

// Verify payment status function
async function verifyPayment(orderId, paymentReference) {
    const button = event.target;
    const originalText = button.innerHTML;
    button.disabled = true;
    button.innerHTML = '⏳ Verifying...';
    
    try {
        console.log('Verifying payment:', orderId, paymentReference);
        
        const response = await fetch('/api/verify-payment-status', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json'
            },
            body: JSON.stringify({
                order_id: orderId,
                payment_reference: paymentReference
            })
        });
        
        console.log('Response status:', response.status);
        
        if (!response.ok) {
            const errorText = await response.text();
            console.error('Server error:', errorText);
            throw new Error('Server returned error: ' + response.status);
        }
        
        const result = await response.json();
        console.log('Verification result:', result);
        
        if (result.success) {
            if (result.payment_status === 'paid') {
                alert('Payment verified! Your order has been marked as paid. Reloading page...');
                window.location.reload();
            } else {
                alert('Payment status: ' + result.payment_status + '\n\n' + (result.message || 'Payment not yet completed. Please try again later.'));
            }
        } else {
            alert((result.message || result.error || 'Unable to verify payment. Please contact support.'));
        }
    } catch (error) {
        console.error('Payment verification error:', error);
        alert('Error verifying payment: ' + error.message + '\n\nPlease contact support or try the "Pay Now" button again.');
    } finally {
        button.disabled = false;
        button.innerHTML = originalText;
    }
}

// Sidebar toggle for mobile
document.getElementById('sidebarToggle').addEventListener('click', function() {
    document.getElementById('dashboardSidebar').classList.toggle('active');
});

// Order-type tab switching (Pig / Lechon)
function switchOrdersTab(tab) {
    document.querySelectorAll('.ord-type-panel').forEach(p => p.classList.remove('active'));
    document.querySelectorAll('.ord-type-tab').forEach(t => {
        const isPig    = t.textContent.trim().toLowerCase().startsWith('pig');
        const isLechon = t.textContent.trim().toLowerCase().startsWith('lechon');
        t.classList.toggle('active', (tab === 'pig' && isPig) || (tab === 'lechon' && isLechon));
    });
    document.getElementById('panel-' + tab + '-orders').classList.add('active');
    const url = new URL(window.location);
    url.searchParams.set('orders_tab', tab);
    history.replaceState(null, '', url);
}

// ===== AUTO-VERIFY PAYMONGO PAYMENTS ON PAGE LOAD =====
// TEMPORARILY DISABLED due to JSON parsing errors
// Will be re-enabled after webhook is properly configured
/*
window.addEventListener('DOMContentLoaded', function() {
    console.log('Auto-verify temporarily disabled. Please use "Verify Payment Status" button manually.');
});
*/

// ===== LECHON ORDER MODAL FUNCTIONS =====
function openLechonPlaceOrderModal(orderId, orderNumber, name, price) {
    document.getElementById('lpo_order_id').value           = orderId;
    document.getElementById('lpo_order_number_input').value = orderNumber;
    document.getElementById('lpo_name').textContent         = name;
    document.getElementById('lpo_order_number').textContent = 'Order #' + orderNumber;
    document.getElementById('lpo_amount').textContent       = '&#x20B1;' + price.toLocaleString('en-PH', {minimumFractionDigits:2});

    // Reset fields
    document.getElementById('lpo_delivery_address').value = '';
    document.getElementById('lpo_pickup_date').value      = '';
    document.getElementById('lpo_delivery_notes').value   = '';
    document.querySelector('input[name="delivery_method"][value="pickup"]').checked = true;
    const addrField = document.getElementById('lch_delivery_address_field');
    addrField.style.overflow = 'hidden';
    addrField.style.height = '0';
    addrField.style.opacity = '0';
    addrField.style.marginBottom = '0';
    document.getElementById('lpo_delivery_address').required = false;

    // Min date = tomorrow
    const tomorrow = new Date();
    tomorrow.setDate(tomorrow.getDate() + 1);
    document.getElementById('lpo_pickup_date').min = tomorrow.toISOString().split('T')[0];

    document.getElementById('lchPlaceOrderModal').classList.add('open');
}

function closeLechonPlaceOrderModal() {
    document.getElementById('lchPlaceOrderModal').classList.remove('open');
}

function toggleLchDeliveryAddress(method) {
    console.log('🔄 toggleLchDeliveryAddress called');
    console.log('  Method:', method);
    
    const field   = document.getElementById('lch_delivery_address_field');
    const textarea = document.getElementById('lpo_delivery_address');
    
    if (!field) {
        console.error('❌ Field not found: lch_delivery_address_field');
        return;
    }
    if (!textarea) {
        console.error('❌ Textarea not found: lpo_delivery_address');
        return;
    }
    
    if (method === 'delivery') {
        console.log('  ✅ Showing delivery address field');
        field.style.display = 'block';
        field.style.height = 'auto';
        field.style.opacity = '1';
        field.style.marginBottom = '15px';
        textarea.required = true;
    } else {
        console.log('  ✅ Hiding delivery address field');
        field.style.display = 'none';
        field.style.height = '0';
        field.style.opacity = '0';
        field.style.marginBottom = '0';
        textarea.required = false;
        textarea.value = '';
    }
}
</script>

<!-- ═══════════════════════════════════════════════════════
     LECHON PLACE ORDER MODAL  (mirrors pig Place Order modal)
═══════════════════════════════════════════════════════ -->
<div class="pm-overlay" id="lchPlaceOrderModal" onclick="if(event.target===this)closeLechonPlaceOrderModal()">
    <div class="pm-modal">
        <div class="pm-modal-header" style="background:#c0392b;">
            <h3>Place Your Lechon Order</h3>
            <button class="pm-modal-close" onclick="closeLechonPlaceOrderModal()">&#x2715;</button>
        </div>
        <div class="pm-modal-body">
            <div class="pm-order-summary">
                <strong id="lpo_name" style="color:#c0392b;display:block;margin-bottom:3px;"></strong>
                <span id="lpo_order_number" style="font-size:.8rem;color:#666;"></span>
                <div class="po_amount" id="lpo_amount"></div>
            </div>
            <form method="POST" action="/customer/place-lechon-order">
                <input type="hidden" name="lechon_order_id" id="lpo_order_id">
                <input type="hidden" name="order_number"    id="lpo_order_number_input">

                <div class="pm-field">
                    <label>Delivery Method <span style="color:red;">*</span></label>
                    <div style="display:flex;gap:15px;margin-top:4px;">
                        <label style="display:flex;align-items:center;gap:6px;cursor:pointer;font-weight:400;">
                            <input type="radio" name="delivery_method" value="pickup" checked onclick="toggleLchDeliveryAddress('pickup')">
                            <span>Pickup</span>
                        </label>
                        <label style="display:flex;align-items:center;gap:6px;cursor:pointer;font-weight:400;">
                            <input type="radio" name="delivery_method" value="delivery" onclick="toggleLchDeliveryAddress('delivery')">
                            <span>Delivery</span>
                        </label>
                    </div>
                </div>

                <div class="pm-field" id="lch_delivery_address_field"
                     style="height:0;overflow:hidden;opacity:0;margin-bottom:0;transition:height .3s,opacity .3s,margin-bottom .3s;">
                    <label>Delivery Address <span style="color:red;">*</span></label>
                    <textarea name="delivery_address" id="lpo_delivery_address"
                              placeholder="Enter your complete delivery address"></textarea>
                </div>

                <div class="pm-field">
                    <label>Preferred Pickup / Delivery Date <span style="color:red;">*</span></label>
                    <input type="date" name="pickup_date" id="lpo_pickup_date"
                           style="width:100%;padding:8px 10px;border:1.5px solid #e0e0e0;border-radius:7px;font-size:.85rem;box-sizing:border-box;"
                           required>
                </div>

                <div class="pm-field">
                    <label>Notes <span style="color:#aaa;font-weight:400;">(optional)</span></label>
                    <textarea name="delivery_notes" id="lpo_delivery_notes"
                              placeholder="e.g. Landmark, event details, special requests"></textarea>
                </div>

                <div class="pm-actions">
                    <button type="button" class="pm-btn-cancel" onclick="closeLechonPlaceOrderModal()">Cancel</button>
                    <button type="submit" class="pm-btn-submit">Place Order</button>
                </div>
            </form>
        </div>
    </div>
</div>

</body>
</html>

<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);

$currentPage = 'my-orders';

$user = $_SESSION['user'] ?? null;
if (!$user) {
    header('Location: /login');
    exit;
}

// Get order ID and order type from query string
$order_id = (int)($_GET['order_id'] ?? 0);
$order_type = $_GET['order_type'] ?? 'pig'; // Default to pig for backward compatibility

if (!$order_id) {
    $_SESSION['error'] = 'Invalid order ID';
    header('Location: /customer/my-orders');
    exit;
}

// Fetch order details based on order type
if ($order_type === 'lechon') {
    // Fetch lechon order
    $query = "SELECT lo.*, 
                     u.name AS seller_name, 
                     u.email AS seller_email,
                     owner.farm_name,
                     ll.photo_url,
                     lo.price as total_cost,
                     lo.price as computed_total_cost,
                     ll.name as listing_name,
                     ll.category
              FROM lechon_orders lo
              LEFT JOIN livestock_owners owner ON owner.id = lo.livestock_owner_id
              LEFT JOIN users u ON u.id = owner.user_id
              LEFT JOIN lechon_listings ll ON ll.id = lo.lechon_listing_id
              WHERE lo.id = ? AND lo.customer_id = ?";
    
    $stmt = $GLOBALS['conn']->prepare($query);
    if (!$stmt) {
        $_SESSION['error'] = 'Database error: ' . $GLOBALS['conn']->error;
        header('Location: /customer/my-orders?orders_tab=lechon');
        exit;
    }
    
    $stmt->bind_param('ii', $order_id, $user['id']);
    $stmt->execute();
    $result = $stmt->get_result();
    $order = $result->fetch_assoc();
    $stmt->close();
    
    if (!$order) {
        $_SESSION['error'] = 'Lechon order not found';
        header('Location: /customer/my-orders?orders_tab=lechon');
        exit;
    }
    
    // Mark as has total cost (lechon orders have upfront pricing)
    $order['has_total_cost'] = 1;
    
} else {
    // Fetch pig order with computed total cost and all breakdown
    $query = "SELECT sos.*, 
                     u.name AS seller_name, 
                     u.email AS seller_email,
                     lo.farm_name,
                     pd.photo_url,
                     otc.id as has_total_cost,
                     otc.total_cost as computed_total_cost,
                     otc.pig_base_amount,
                     otc.delivery_fee,
                     otc.labor_cost,
                     otc.include_laman_loob,
                     otc.laman_loob_price,
                     otc.include_boopes,
                     otc.boopes_price,
                     otc.include_dinuguan,
                     otc.dinuguan_price,
                     otc.subtotal,
                     otc.delivery_method,
                     otc.computed_at
              FROM swine_order_status sos
              LEFT JOIN livestock_owners lo ON lo.id = sos.livestock_owner_id
              LEFT JOIN users u ON u.id = lo.user_id
              LEFT JOIN pig_details pd ON pd.id = sos.pig_detail_id
              LEFT JOIN order_total_cost otc ON otc.swine_order_id = sos.id
              WHERE sos.id = ? AND sos.customer_id = ?";
    
    $stmt = $GLOBALS['conn']->prepare($query);
    if (!$stmt) {
        $_SESSION['error'] = 'Database error: ' . $GLOBALS['conn']->error;
        header('Location: /customer/my-orders');
        exit;
    }
    
    $stmt->bind_param('ii', $order_id, $user['id']);
    $stmt->execute();
    $result = $stmt->get_result();
    $order = $result->fetch_assoc();
    $stmt->close();
    
    if (!$order) {
        $_SESSION['error'] = 'Order not found';
        header('Location: /customer/my-orders');
        exit;
    }
    
    // Check if total cost has been computed by livestock owner
    if (empty($order['has_total_cost'])) {
        $_SESSION['error'] = 'Please wait for the seller to compute the total cost before making payment';
        header('Location: /customer/my-orders');
        exit;
    }
}

// Check if order is already paid
if ($order['payment_status'] === 'paid') {
    $_SESSION['info'] = 'This order has already been paid';
    $redirect_url = $order_type === 'lechon' ? '/customer/my-orders?orders_tab=lechon' : '/customer/my-orders';
    header('Location: ' . $redirect_url);
    exit;
}

// Check if order is in a payable status
$actual_status = $order['order_status'] ?? '';
$is_payable_status = in_array($actual_status, ['preparing', 'cost_computed', 'ready_for_pickup']) 
                     || empty($actual_status); // Allow empty status if cost is computed

if (!$is_payable_status) {
    $_SESSION['error'] = 'This order is not ready for payment yet';
    $redirect_url = $order_type === 'lechon' ? '/customer/my-orders?orders_tab=lechon' : '/customer/my-orders';
    header('Location: ' . $redirect_url);
    exit;
}

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Pay Order - LechGO</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.8/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="/styles.css">
    <style>
        .payment-container {
            max-width: 900px;
            margin: 10px auto;
            padding: 10px;
        }

        .payment-card {
            background: white;
            border-radius: 8px;
            box-shadow: 0 2px 8px rgba(0,0,0,0.1);
            overflow: hidden;
        }

        .payment-header {
            background: linear-gradient(135deg, #D1332D 0%, #A00D0A 100%);
            color: white;
            padding: 12px 16px;
            text-align: center;
        }

        .payment-header h2 {
            margin: 0;
            font-size: 1.1rem;
            font-weight: 700;
        }

        .payment-header p {
            margin: 2px 0 0 0;
            opacity: 0.9;
            font-size: 0.75rem;
        }

        .payment-body {
            padding: 12px;
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 12px;
        }

        .left-column {
            display: flex;
            flex-direction: column;
            gap: 8px;
        }

        .right-column {
            display: flex;
            flex-direction: column;
            gap: 8px;
        }

        .order-summary {
            background: #f8f9fa;
            border-radius: 6px;
            padding: 10px;
        }

        .order-summary h3 {
            font-size: 0.85rem;
            font-weight: 700;
            color: #2c3e50;
            margin-bottom: 8px;
            padding-bottom: 4px;
            border-bottom: 2px solid #e0e0e0;
        }

        .summary-row {
            display: flex;
            justify-content: space-between;
            padding: 3px 0;
            font-size: 0.75rem;
        }

        .summary-row.total {
            font-size: 0.95rem;
            font-weight: 700;
            color: #27ae60;
            padding-top: 8px;
            margin-top: 4px;
            border-top: 2px solid #e0e0e0;
        }

        .payment-methods {
            background: white;
        }

        .payment-methods h3 {
            font-size: 0.85rem;
            font-weight: 700;
            color: #2c3e50;
            margin-bottom: 8px;
        }

        .payment-method-option {
            border: 2px solid #e0e0e0;
            border-radius: 6px;
            padding: 8px;
            margin-bottom: 6px;
            cursor: pointer;
            transition: all 0.3s;
            display: flex;
            align-items: center;
            gap: 8px;
        }

        .payment-method-option:hover {
            border-color: #D1332D;
            background: #fff5f5;
        }

        .payment-method-option.selected {
            border-color: #D1332D;
            background: #fff5f5;
            box-shadow: 0 0 0 2px rgba(209, 51, 45, 0.1);
        }

        .payment-method-option input[type="radio"] {
            width: 16px;
            height: 16px;
            accent-color: #D1332D;
        }

        .payment-method-info {
            flex: 1;
        }

        .payment-method-name {
            font-weight: 600;
            color: #2c3e50;
            font-size: 0.8rem;
        }

        .payment-method-desc {
            color: #666;
            font-size: 0.7rem;
            margin-top: 2px;
        }

        .payment-icon {
            font-size: 1.2rem;
        }

        .test-card-info {
            background: #e8f4f8;
            border-left: 3px solid #3498db;
            padding: 8px;
            border-radius: 4px;
            margin-top: 8px;
            display: none;
            font-size: 0.7rem;
        }

        .test-card-info.show {
            display: block;
        }

        .test-card-info h4 {
            font-size: 0.75rem;
            font-weight: 700;
            color: #2c3e50;
            margin-bottom: 6px;
        }

        .test-card-info code {
            background: white;
            padding: 2px 4px;
            border-radius: 3px;
            font-size: 0.7rem;
        }

        .btn-pay {
            width: 100%;
            padding: 8px;
            background: #27ae60;
            color: white;
            border: none;
            border-radius: 6px;
            font-size: 0.85rem;
            font-weight: 700;
            cursor: pointer;
            transition: all 0.3s;
        }

        .btn-pay:hover {
            background: #229954;
            transform: translateY(-1px);
            box-shadow: 0 3px 10px rgba(39, 174, 96, 0.3);
        }

        .btn-pay:disabled {
            background: #95a5a6;
            cursor: not-allowed;
            transform: none;
        }

        .btn-cancel {
            width: 100%;
            padding: 6px;
            background: #f0f0f0;
            color: #555;
            border: none;
            border-radius: 6px;
            font-size: 0.75rem;
            font-weight: 600;
            cursor: pointer;
            margin-top: 6px;
            text-decoration: none;
            display: block;
            text-align: center;
        }

        .btn-cancel:hover {
            background: #e0e0e0;
        }

        .security-badge {
            text-align: center;
            padding: 6px;
            color: #666;
            font-size: 0.65rem;
        }

        .security-badge i {
            color: #27ae60;
            margin-right: 3px;
        }

        .pig-image {
            width: 40px;
            height: 40px;
            object-fit: cover;
            border-radius: 6px;
            margin-right: 8px;
        }

        .order-info-header {
            display: flex;
            align-items: center;
            margin-bottom: 10px;
        }

        .section-box {
            background: #fff;
            border: 1px solid #e0e0e0;
            border-radius: 6px;
            padding: 10px;
        }

        .section-title {
            font-weight: 600;
            color: #2c3e50;
            margin-bottom: 6px;
            font-size: 0.8rem;
        }

        @media (max-width: 768px) {
            .payment-body {
                grid-template-columns: 1fr;
            }
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
        <h1 class="dashboard-topbar-title">Dashboard</h1>
        <div class="dashboard-topbar-actions">
            <span class="dashboard-topbar-date"><?php echo date('l, F j, Y'); ?></span>
        </div>
    </div>
    
    <div class="dashboard-content">
            <div class="payment-container">
                <div class="payment-card">
                    <div class="payment-header">
                        <h2> Complete Your Payment</h2>
                        <p>Order #<?php echo htmlspecialchars($order['order_number']); ?></p>
                    </div>

                    <div class="payment-body">
                        <!-- LEFT COLUMN: Order Summary -->
                        <div class="left-column">
                            <div class="order-summary">
                                <h3> Order Receipt</h3>
                                
                                <div class="order-info-header">
                                    <?php if (!empty($order['photo_url'])): ?>
                                        <img src="<?php echo htmlspecialchars($order['photo_url']); ?>" 
                                             alt="<?php echo $order_type === 'lechon' ? 'Lechon' : 'Pig'; ?>" class="pig-image">
                                    <?php endif; ?>
                                    <div>
                                        <div style="font-weight: 600; font-size: 0.9rem; color: #2c3e50;">
                                            Order #<?php echo htmlspecialchars($order['order_number']); ?>
                                        </div>
                                        <div style="color: #666; font-size: 0.75rem;">
                                            <?php if ($order_type === 'lechon'): ?>
                                                Lechon Order ID: <?php echo htmlspecialchars($order['id']); ?>
                                            <?php else: ?>
                                                Swine Order ID: <?php echo htmlspecialchars($order['id']); ?>
                                            <?php endif; ?>
                                        </div>
                                        <div style="color: #666; font-size: 0.7rem; margin-top: 2px;">
                                            <?php echo htmlspecialchars($order['seller_name']); ?>
                                            <?php if (!empty($order['farm_name'])): ?>
                                                - <?php echo htmlspecialchars($order['farm_name']); ?>
                                            <?php endif; ?>
                                        </div>
                                        <?php if (!empty($order['computed_at'])): ?>
                                        <div style="color: #999; font-size: 0.65rem; margin-top: 2px;">
                                            Order Date: <?php echo date('M d, Y', strtotime($order['created_at'] ?? $order['computed_at'])); ?>
                                        </div>
                                        <?php endif; ?>
                                    </div>
                                </div>

                                <hr style="border: none; border-top: 1px solid #e0e0e0; margin: 10px 0;">

                                <?php if ($order_type === 'lechon'): ?>
                                <!-- Lechon Details -->
                                <div style="background: #fff3cd; padding: 8px; border-radius: 4px; margin-bottom: 8px;">
                                    <div style="font-weight: 600; color: #856404; margin-bottom: 6px; font-size: 0.75rem;"> Lechon Details</div>
                                    <div class="summary-row" style="font-size: 0.8rem;">
                                        <span>Product:</span>
                                        <strong><?php echo htmlspecialchars($order['listing_name'] ?? 'Lechon'); ?></strong>
                                    </div>
                                    <?php if (!empty($order['category'])): ?>
                                    <div class="summary-row" style="font-size: 0.75rem;">
                                        <span>Category:</span>
                                        <strong><?php 
                                            $cat_labels = [
                                                'WHOLE_LECHON'   => 'Whole Lechon',
                                                'WHOLE_PACKAGES' => 'w/ Packages',
                                                'BELLY_BUNDLES'  => 'Belly Bundles',
                                                'WEEKDAY_COMBOS' => 'Weekday Combos',
                                                'EVENT_CATERING' => 'Event / Catering',
                                                'OTHER'          => 'Other',
                                            ];
                                            echo $cat_labels[$order['category']] ?? $order['category'];
                                        ?></strong>
                                    </div>
                                    <?php endif; ?>
                                    <?php if (!empty($order['weight_kg'])): ?>
                                    <div class="summary-row" style="font-size: 0.75rem;">
                                        <span>Weight:</span>
                                        <strong><?php echo number_format($order['weight_kg'], 1); ?> kg</strong>
                                    </div>
                                    <?php endif; ?>
                                    <?php if (!empty($order['serving_capacity'])): ?>
                                    <div class="summary-row" style="font-size: 0.75rem;">
                                        <span>Good for:</span>
                                        <strong><?php echo htmlspecialchars($order['serving_capacity']); ?></strong>
                                    </div>
                                    <?php endif; ?>
                                    <?php if (!empty($order['pickup_date'])): ?>
                                    <div class="summary-row" style="font-size: 0.75rem;">
                                        <span>Pickup Date:</span>
                                        <strong><?php echo date('M d, Y', strtotime($order['pickup_date'])); ?></strong>
                                    </div>
                                    <?php endif; ?>
                                </div>
                                <?php else: ?>
                                <!-- Pig Details -->
                                <div style="background: #f8f9fa; padding: 8px; border-radius: 4px; margin-bottom: 8px;">
                                    <div style="font-weight: 600; color: #2c3e50; margin-bottom: 6px; font-size: 0.75rem;"> Pig Details</div>
                                    <div class="summary-row" style="font-size: 0.75rem;">
                                        <span>Weight:</span>
                                        <strong><?php echo number_format($order['weight_kg'], 2); ?> kg</strong>
                                    </div>
                                    <div class="summary-row" style="font-size: 0.75rem;">
                                        <span>Price per kg:</span>
                                        <strong>₱<?php echo number_format($order['price_per_kg'], 2); ?></strong>
                                    </div>
                                    <div class="summary-row" style="font-size: 0.8rem; padding-top: 6px; border-top: 1px solid #dee2e6; margin-top: 6px;">
                                        <span>Pig Base Amount:</span>
                                        <strong>₱<?php echo number_format($order['pig_base_amount'] ?? $order['total_price'], 2); ?></strong>
                                    </div>
                                </div>
                                <?php endif; ?>

                                <!-- Additional Services (Pig Orders Only) -->
                                <?php if ($order_type !== 'lechon' && (!empty($order['delivery_fee']) || !empty($order['labor_cost']) ||
                                          !empty($order['include_laman_loob']) || !empty($order['include_boopes']) || 
                                          !empty($order['include_dinuguan']))): ?>
                                <div style="background: #fff3cd; padding: 8px; border-radius: 4px; margin-bottom: 8px;">
                                    <div style="font-weight: 600; color: #856404; margin-bottom: 6px; font-size: 0.75rem;"> Additional Services</div>
                                    
                                    <?php if (!empty($order['delivery_fee']) && $order['delivery_fee'] > 0): ?>
                                    <div class="summary-row" style="font-size: 0.75rem;">
                                        <span> Delivery Fee (<?php echo ucfirst($order['delivery_method'] ?? 'delivery'); ?>):</span>
                                        <strong>₱<?php echo number_format($order['delivery_fee'], 2); ?></strong>
                                    </div>
                                    <?php endif; ?>

                                    <?php if (!empty($order['labor_cost']) && $order['labor_cost'] > 0): ?>
                                    <div class="summary-row" style="font-size: 0.75rem;">
                                        <span> Labor Cost :</span>
                                        <strong>₱<?php echo number_format($order['labor_cost'], 2); ?></strong>
                                    </div>
                                    <?php endif; ?>

                                    <?php if (!empty($order['include_laman_loob']) && $order['laman_loob_price'] > 0): ?>
                                    <div class="summary-row" style="font-size: 0.75rem;">
                                        <span> Laman Loob:</span>
                                        <strong>₱<?php echo number_format($order['laman_loob_price'], 2); ?></strong>
                                    </div>
                                    <?php endif; ?>

                                    <?php if (!empty($order['include_boopes']) && $order['boopes_price'] > 0): ?>
                                    <div class="summary-row" style="font-size: 0.75rem;">
                                        <span> Boopes:</span>
                                        <strong>₱<?php echo number_format($order['boopes_price'], 2); ?></strong>
                                    </div>
                                    <?php endif; ?>

                                    <?php if (!empty($order['include_dinuguan']) && $order['dinuguan_price'] > 0): ?>
                                    <div class="summary-row" style="font-size: 0.75rem;">
                                        <span> Dinuguan:</span>
                                        <strong>₱<?php echo number_format($order['dinuguan_price'], 2); ?></strong>
                                    </div>
                                    <?php endif; ?>
                                </div>
                                <?php endif; ?>

                                <!-- Delivery Address -->
                                <?php if (!empty($order['delivery_address'])): ?>
                                <div style="background: #e8f4f8; padding: 8px; border-radius: 4px; margin-bottom: 8px;">
                                    <div style="font-weight: 600; color: #0c5460; margin-bottom: 4px; font-size: 0.75rem;"> Delivery Address</div>
                                    <div style="font-size: 0.7rem; color: #0c5460;">
                                        <?php echo nl2br(htmlspecialchars($order['delivery_address'])); ?>
                                    </div>
                                </div>
                                <?php endif; ?>

                                <hr style="border: none; border-top: 1px solid #e0e0e0; margin: 10px 0;">

                                <!-- Total -->
                                <div class="summary-row total">
                                    <span> TOTAL AMOUNT:</span>
                                    <span>₱<?php echo number_format(!empty($order['computed_total_cost']) ? $order['computed_total_cost'] : $order['total_price'], 2); ?></span>
                                </div>
                            </div>
                        </div>

                        <!-- RIGHT COLUMN: Payment Methods -->
                        <div class="right-column">
                            <form method="POST" action="/customer/process-payment" id="paymentForm">
                                <input type="hidden" name="order_id" value="<?php echo $order['id']; ?>">
                                <input type="hidden" name="order_type" value="<?php echo $order_type; ?>">
                                
                                <!-- Payment Type Selection (Hide for Lechon - Full Payment Only) -->
                                <?php if ($order_type !== 'lechon'): ?>
                                <div style="background: #e8f5e9; border: 1px solid #c8e6c9; border-radius: 6px; padding: 8px; margin-bottom: 8px;">
                                    <h3 style="font-size: 0.8rem; font-weight: 700; color: #2d7a2d; margin-bottom: 3px;"> Payment Type</h3>
                                    <p style="font-size: 0.65rem; color: #666; margin-bottom: 6px;">Choose how you want to pay for your order</p>
                                    
                                    <label style="display: flex; align-items: center; padding: 5px 6px; background: white; border: 2px solid #e0e0e0; border-radius: 4px; margin-bottom: 4px; cursor: pointer;" onclick="selectPaymentType('full')">
                                        <input type="radio" name="payment_type" value="full" checked style="margin-right: 6px; width: 14px; height: 14px;">
                                        <div style="flex: 1;">
                                            <div style="font-weight: 600; color: #2c3e50; font-size: 0.75rem;">Full Payment</div>
                                            <div style="font-size: 0.65rem; color: #666;">Pay total: ₱<?php echo number_format($order['computed_total_cost'], 2); ?></div>
                                        </div>
                                    </label>
                                    
                                    <label style="display: flex; align-items: center; padding: 5px 6px; background: white; border: 2px solid #e0e0e0; border-radius: 4px; cursor: pointer;" onclick="selectPaymentType('down')">
                                        <input type="radio" name="payment_type" value="down" style="margin-right: 6px; width: 14px; height: 14px;">
                                        <div style="flex: 1;">
                                            <div style="font-weight: 600; color: #2c3e50; font-size: 0.75rem;">Down Payment (50%)</div>
                                            <div style="font-size: 0.65rem; color: #666;">Pay now: ₱<?php echo number_format($order['computed_total_cost'] * 0.5, 2); ?> | Remaining: ₱<?php echo number_format($order['computed_total_cost'] * 0.5, 2); ?></div>
                                        </div>
                                    </label>
                                    
                                    <div id="paymentAmountDisplay" style="margin-top: 6px; padding: 5px; background: #fff3cd; border-radius: 4px; font-size: 0.7rem; color: #856404;">
                                        <strong>Amount to pay:</strong> <span id="amountToPay">₱<?php echo number_format($order['computed_total_cost'], 2); ?></span>
                                    </div>
                                </div>
                                <?php else: ?>
                                <!-- Lechon: Full Payment Only -->
                                <input type="hidden" name="payment_type" value="full">
                                <div style="background: #e8f5e9; border: 1px solid #c8e6c9; border-radius: 6px; padding: 8px; margin-bottom: 8px;">
                                    <h3 style="font-size: 0.8rem; font-weight: 700; color: #2d7a2d; margin-bottom: 3px;"> Payment Amount</h3>
                                    <div style="font-size: 0.75rem; color: #666; margin-bottom: 6px;">
                                        Lechon orders require full payment upfront
                                    </div>
                                    <div style="padding: 8px; background: white; border: 2px solid #27ae60; border-radius: 4px;">
                                        <div style="font-weight: 600; color: #2c3e50; font-size: 0.8rem;">Full Payment</div>
                                        <div style="font-size: 0.9rem; color: #27ae60; font-weight: 700; margin-top: 4px;">
                                            ₱<?php echo number_format($order['computed_total_cost'], 2); ?>
                                        </div>
                                    </div>
                                </div>
                                <?php endif; ?>
                                
                                <div class="payment-methods">
                                    <h3> Select Payment Method</h3>

                                    <label class="payment-method-option" onclick="selectPaymentMethod('paymongo')">
                                        <input type="radio" name="payment_method" value="paymongo" required>
                                        <span class="payment-icon"></span>
                                        <div class="payment-method-info">
                                            <div class="payment-method-name">PayMongo Online Payment</div>
                                            <div class="payment-method-desc">Pay with Card, GCash, GrabPay, or PayMaya</div>
                                        </div>
                                    </label>

                                    <label class="payment-method-option" onclick="selectPaymentMethod('cod')">
                                        <input type="radio" name="payment_method" value="cod" required>
                                        <span class="payment-icon"></span>
                                        <div class="payment-method-info">
                                            <div class="payment-method-name">Cash on Delivery</div>
                                            <div class="payment-method-desc">Pay when you receive your order</div>
                                        </div>
                                    </label>

                                    <label class="payment-method-option" onclick="selectPaymentMethod('bank')">
                                        <input type="radio" name="payment_method" value="bank" required>
                                        <span class="payment-icon"></span>
                                        <div class="payment-method-info">
                                            <div class="payment-method-name">Bank Transfer</div>
                                            <div class="payment-method-desc">Transfer directly to seller's bank account</div>
                                        </div>
                                    </label>

                                    <!-- Test Payment Option (Development Only) -->
                                    <label class="payment-method-option" onclick="selectPaymentMethod('test')" style="border: 2px dashed #3498db; background: #e8f4f8;">
                                        <input type="radio" name="payment_method" value="test" required>
                                        <span class="payment-icon"></span>
                                        <div class="payment-method-info">
                                            <div class="payment-method-name">Test Payment (Development Only)</div>
                                            <div class="payment-method-desc">Simulate payment completion for testing</div>
                                        </div>
                                    </label>

                                    <!-- Test Card Info (shown when PayMongo is selected) -->
                                    <div class="test-card-info" id="testCardInfo">
                                        <h4> Test Card Information</h4>
                                        <p style="margin-bottom: 8px;">Use these test card details for payment:</p>
                                        <div style="margin-left: 12px;">
                                            <div>Card Number: <code>4343 4343 4343 4345</code></div>
                                            <div>Expiry Date: <code>12/25</code></div>
                                            <div>CVC: <code>123</code></div>
                                        </div>
                                    </div>
                                    
                                    <!-- QR Payment Instructions -->
                                    <div class="test-card-info" id="qrInstructions" style="background: #fff3cd; border-left: 3px solid #f39c12;">
                                        <h4>📱 QR Payment Instructions</h4>
                                        <p style="margin-bottom: 8px;">For QR code payments (GCash, PayMaya, etc.):</p>
                                        <div style="margin-left: 12px;">
                                            <div>1. Scan the QR code with your app</div>
                                            <div>2. Complete the payment</div>
                                            <div>3. <strong>Manually return to this website</strong></div>
                                            <div>4. Go to "My Orders" to see updated status</div>
                                        </div>
                                        <p style="margin-top: 8px; font-weight: bold; color: #856404;">
                                            Note: QR payments don't auto-redirect. Please return manually after payment.
                                        </p>
                                    </div>
                                    
                                    <!-- Test Payment Info -->
                                    <div class="test-card-info" id="testPaymentInfo" style="background: #e8f4f8; border-left: 3px solid #3498db;">
                                        <h4> Test Payment Mode</h4>
                                        <p style="margin-bottom: 8px;">This will simulate a successful payment:</p>
                                        <p style="margin-top: 8px; font-weight: bold; color: #2980b9;">
                                            Perfect for testing the payment flow!
                                        </p>
                                    </div>
                                </div>

                                <button type="submit" class="btn-pay" id="payButton">
                                     Proceed to Payment
                                </button>

                                <a href="/customer/my-orders" class="btn-cancel">
                                    Cancel
                                </a>

                                <div class="security-badge">
                                    <i></i> Your payment information is secure and encrypted
                                </div>
                            </form>
                        </div>
                    </div>
                </div>
            </div>
    </div> <!-- Close dashboard-content -->
        </main>
    </div>

    <script>
        const totalAmount = <?php echo $order['computed_total_cost']; ?>;
        const orderType = '<?php echo $order_type; ?>';
        
        function selectPaymentType(type) {
            // Only for pig orders
            if (orderType === 'lechon') return;
            
            // Remove selected styling from all payment type options
            document.querySelectorAll('label[onclick^="selectPaymentType"]').forEach(label => {
                label.style.borderColor = '#e0e0e0';
                label.style.background = 'white';
            });
            
            // Add selected styling to clicked option
            event.currentTarget.style.borderColor = '#27ae60';
            event.currentTarget.style.background = '#f0f8f0';
            
            // Update amount display
            const amountDisplay = document.getElementById('amountToPay');
            if (type === 'full') {
                amountDisplay.textContent = '₱' + totalAmount.toLocaleString('en-PH', {minimumFractionDigits: 2});
            } else {
                const downPayment = totalAmount * 0.5;
                amountDisplay.textContent = '₱' + downPayment.toLocaleString('en-PH', {minimumFractionDigits: 2});
            }
        }
        
        function selectPaymentMethod(method) {
            // Remove selected class from all options
            document.querySelectorAll('.payment-method-option').forEach(option => {
                option.classList.remove('selected');
            });

            // Add selected class to clicked option
            event.currentTarget.classList.add('selected');

            // Show/hide info sections
            const testCardInfo = document.getElementById('testCardInfo');
            const qrInstructions = document.getElementById('qrInstructions');
            const testPaymentInfo = document.getElementById('testPaymentInfo');
            
            // Hide all info sections first
            testCardInfo.classList.remove('show');
            qrInstructions.classList.remove('show');
            testPaymentInfo.classList.remove('show');
            
            if (method === 'paymongo') {
                testCardInfo.classList.add('show');
                qrInstructions.classList.add('show');
            } else if (method === 'test') {
                testPaymentInfo.classList.add('show');
            }

            // Update button text
            const payButton = document.getElementById('payButton');
            if (method === 'paymongo') {
                payButton.textContent = ' Pay with PayMongo';
            } else if (method === 'cod') {
                payButton.textContent = ' Confirm Cash on Delivery';
            } else if (method === 'bank') {
                payButton.textContent = ' Confirm Bank Transfer';
            } else if (method === 'test') {
                payButton.textContent = ' Process Test Payment';
                payButton.style.background = '#3498db';
            } else {
                payButton.style.background = '#27ae60';
            }
        }

        // Form validation
        document.getElementById('paymentForm').addEventListener('submit', function(e) {
            const selectedMethod = document.querySelector('input[name="payment_method"]:checked');
            if (!selectedMethod) {
                e.preventDefault();
                alert('Please select a payment method');
            }
        });
    </script>

<script>
// Sidebar toggle for mobile
document.getElementById('sidebarToggle').addEventListener('click', function() {
    document.getElementById('dashboardSidebar').classList.toggle('active');
});
</script>
</body>
</html>

<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);

$currentPage = 'my-orders';

$user = $_SESSION['user'] ?? null;
if (!$user) {
    header('Location: /login');
    exit;
}

// Get order ID from query string
$order_id = (int)($_GET['order_id'] ?? 0);

if (!$order_id) {
    $_SESSION['error'] = 'Invalid order ID';
    header('Location: /customer/my-orders');
    exit;
}

// Fetch order details with computed total cost and remaining balance
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
                 otc.computed_at,
                 otc.payment_type,
                 otc.amount_paid,
                 otc.remaining_balance
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

// Check if this is a down payment order with remaining balance
if ($order['payment_type'] !== 'down' || $order['remaining_balance'] <= 0) {
    $_SESSION['error'] = 'No remaining balance to pay for this order';
    header('Location: /customer/my-orders');
    exit;
}

// Check if order is already fully paid
if ($order['payment_status'] === 'paid' && $order['remaining_balance'] <= 0) {
    $_SESSION['info'] = 'This order has already been fully paid';
    header('Location: /customer/my-orders');
    exit;
}

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Pay Remaining Balance - LechGO</title>
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
            background: linear-gradient(135deg, #f39c12 0%, #e67e22 100%);
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

        .balance-summary {
            background: #fff3cd;
            border: 1px solid #ffeaa7;
            border-radius: 6px;
            padding: 10px;
            margin-bottom: 10px;
        }

        .balance-summary h3 {
            font-size: 0.85rem;
            font-weight: 700;
            color: #856404;
            margin-bottom: 8px;
            padding-bottom: 4px;
            border-bottom: 2px solid #ffeaa7;
        }

        .summary-row {
            display: flex;
            justify-content: space-between;
            padding: 3px 0;
            font-size: 0.75rem;
        }

        .summary-row.highlight {
            font-size: 0.95rem;
            font-weight: 700;
            color: #e67e22;
            padding-top: 8px;
            margin-top: 4px;
            border-top: 2px solid #ffeaa7;
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
            border-color: #f39c12;
            background: #fef9e7;
        }

        .payment-method-option.selected {
            border-color: #f39c12;
            background: #fef9e7;
            box-shadow: 0 0 0 2px rgba(243, 156, 18, 0.1);
        }

        .payment-method-option input[type="radio"] {
            width: 16px;
            height: 16px;
            accent-color: #f39c12;
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

        .btn-pay {
            width: 100%;
            padding: 8px;
            background: #f39c12;
            color: white;
            border: none;
            border-radius: 6px;
            font-size: 0.85rem;
            font-weight: 700;
            cursor: pointer;
            transition: all 0.3s;
        }

        .btn-pay:hover {
            background: #e67e22;
            transform: translateY(-1px);
            box-shadow: 0 3px 10px rgba(243, 156, 18, 0.3);
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
                        <h2> Pay Remaining Balance</h2>
                        <p>Order #<?php echo htmlspecialchars($order['order_number']); ?></p>
                    </div>

                    <div class="payment-body">
                        <!-- LEFT COLUMN: Balance Summary -->
                        <div class="left-column">
                            <div class="balance-summary">
                                <h3> Payment Summary</h3>
                                
                                <div class="summary-row">
                                    <span>Total Order Amount:</span>
                                    <strong>₱<?php echo number_format($order['computed_total_cost'], 2); ?></strong>
                                </div>
                                
                                <div class="summary-row">
                                    <span>Amount Already Paid:</span>
                                    <strong>₱<?php echo number_format($order['amount_paid'], 2); ?></strong>
                                </div>
                                
                                <div class="summary-row highlight">
                                    <span>REMAINING BALANCE:</span>
                                    <span>₱<?php echo number_format($order['remaining_balance'], 2); ?></span>
                                </div>
                            </div>
                            
                            <div class="order-summary">
                                <h3> Order Details</h3>
                                
                                <div class="order-info-header">
                                    <?php if (!empty($order['photo_url'])): ?>
                                        <img src="<?php echo htmlspecialchars($order['photo_url']); ?>" 
                                             alt="Pig" class="pig-image">
                                    <?php endif; ?>
                                    <div>
                                        <div style="font-weight: 600; font-size: 0.9rem; color: #2c3e50;">
                                            Order #<?php echo htmlspecialchars($order['order_number']); ?>
                                        </div>
                                        <div style="color: #666; font-size: 0.75rem;">
                                            Swine Order ID: <?php echo htmlspecialchars($order['id']); ?>
                                        </div>
                                        <div style="color: #666; font-size: 0.7rem; margin-top: 2px;">
                                            <?php echo htmlspecialchars($order['seller_name']); ?>
                                            <?php if (!empty($order['farm_name'])): ?>
                                                - <?php echo htmlspecialchars($order['farm_name']); ?>
                                            <?php endif; ?>
                                        </div>
                                    </div>
                                </div>

                                <div class="summary-row" style="font-size: 0.75rem;">
                                    <span>Weight:</span>
                                    <strong><?php echo number_format($order['weight_kg'], 2); ?> kg</strong>
                                </div>
                                <div class="summary-row" style="font-size: 0.75rem;">
                                    <span>Price per kg:</span>
                                    <strong>₱<?php echo number_format($order['price_per_kg'], 2); ?></strong>
                                </div>
                                <div class="summary-row" style="font-size: 0.75rem;">
                                    <span>Payment Type:</span>
                                    <strong>Down Payment (50%)</strong>
                                </div>
                            </div>
                        </div>

                        <!-- RIGHT COLUMN: Payment Methods -->
                        <div class="right-column">
                            <form method="POST" action="/customer/process-remaining-payment" id="paymentForm">
                                <input type="hidden" name="order_id" value="<?php echo $order['id']; ?>">
                                <input type="hidden" name="payment_amount" value="<?php echo $order['remaining_balance']; ?>">
                                
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
                                            <div class="payment-method-desc">Pay remaining balance when you receive your order</div>
                                        </div>
                                    </label>

                                    <label class="payment-method-option" onclick="selectPaymentMethod('bank')">
                                        <input type="radio" name="payment_method" value="bank" required>
                                        <span class="payment-icon"></span>
                                        <div class="payment-method-info">
                                            <div class="payment-method-name">Bank Transfer</div>
                                            <div class="payment-method-desc">Transfer remaining balance to seller's account</div>
                                        </div>
                                    </label>

                                    <!-- Test Payment Option -->
                                    <label class="payment-method-option" onclick="selectPaymentMethod('test')" style="border: 2px dashed #3498db; background: #e8f4f8;">
                                        <input type="radio" name="payment_method" value="test" required>
                                        <span class="payment-icon"></span>
                                        <div class="payment-method-info">
                                            <div class="payment-method-name">Test Payment (Development Only)</div>
                                            <div class="payment-method-desc">Simulate remaining balance payment completion</div>
                                        </div>
                                    </label>

                                    <!-- Test Payment Info -->
                                    <div class="test-card-info" id="testPaymentInfo" style="background: #e8f4f8; border-left: 3px solid #3498db;">
                                        <h4> Test Remaining Balance Payment</h4>
                                        <p style="margin-bottom: 8px;">This will simulate paying the remaining balance:</p>
                                        <div style="margin-left: 12px;">
                                            <div> Remaining balance will be marked as paid</div>
                                            <div> Order will be marked as fully paid</div>
                                            <div> Payment record will be logged</div>
                                            <div> No actual money will be charged</div>
                                        </div>
                                    </div>
                                </div>

                                <button type="submit" class="btn-pay" id="payButton">
                                     Pay Remaining Balance
                                </button>

                                <a href="/customer/my-orders" class="btn-cancel">
                                    Cancel
                                </a>

                                <div style="text-align: center; padding: 6px; color: #666; font-size: 0.65rem;">
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
        function selectPaymentMethod(method) {
            // Remove selected class from all options
            document.querySelectorAll('.payment-method-option').forEach(option => {
                option.classList.remove('selected');
            });

            // Add selected class to clicked option
            event.currentTarget.classList.add('selected');

            // Show/hide test payment info
            const testPaymentInfo = document.getElementById('testPaymentInfo');
            
            if (method === 'test') {
                testPaymentInfo.classList.add('show');
            } else {
                testPaymentInfo.classList.remove('show');
            }

            // Update button text
            const payButton = document.getElementById('payButton');
            if (method === 'paymongo') {
                payButton.textContent = ' Pay with PayMongo';
                payButton.style.background = '#f39c12';
            } else if (method === 'cod') {
                payButton.textContent = ' Confirm Cash on Delivery';
                payButton.style.background = '#f39c12';
            } else if (method === 'bank') {
                payButton.textContent = ' Confirm Bank Transfer';
                payButton.style.background = '#f39c12';
            } else if (method === 'test') {
                payButton.textContent = ' Process Test Payment';
                payButton.style.background = '#3498db';
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
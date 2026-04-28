<?php
$pending_orders = $_SESSION['pending_orders'] ?? null;

if (!$pending_orders) {
    header('Location: /LechGo_Final/public/livestock-owner/checkout');
    exit;
}

$orders_by_supplier = $pending_orders['orders_by_supplier'];
$owner = $pending_orders['owner'];
$delivery_address = $pending_orders['delivery_address'];
$user = $_SESSION['user'] ?? null;

// Calculate total
$total_amount = 0;
foreach ($orders_by_supplier as $supplier_orders) {
    foreach ($supplier_orders as $item) {
        $total_amount += $item['subtotal'];
    }
}

// Get supplier names
$orders_with_names = [];
if (!empty($orders_by_supplier)) {
    foreach ($orders_by_supplier as $supplier_id => $items) {
        $query = "SELECT name FROM suppliers WHERE id = ?";
        $stmt = $GLOBALS['conn']->prepare($query);
        if ($stmt) {
            $stmt->bind_param('i', $supplier_id);
            $stmt->execute();
            $result = $stmt->get_result();
            $supplier = $result->fetch_assoc();
            $orders_with_names[$supplier_id] = [
                'name' => $supplier['name'] ?? 'Unknown',
                'items' => $items,
                'total' => array_sum(array_map(fn($i) => $i['subtotal'], $items))
            ];
            $stmt->close();
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Payment - LechGO</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.8/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="/LechGo_Final/public/styles.css">
    <script src="https://js.paymongo.com/v1/checkout.js"></script>
    <style>
        .payment-container {
            max-width: 950px;
            margin: 0 auto;
            padding: var(--spacing-lg);
        }
        .payment-grid {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: var(--spacing-lg);
        }
        .order-summary {
            background: white;
            padding: var(--spacing-lg);
            border-radius: 8px;
            box-shadow: 0 2px 4px rgba(0,0,0,0.1);
        }
        .supplier-section {
            margin-bottom: var(--spacing-lg);
            padding: var(--spacing-md);
            background: #f9f9f9;
            border-left: 4px solid var(--primary-color);
            border-radius: 4px;
        }
        .supplier-section h3 {
            margin: 0 0 var(--spacing-md) 0;
            color: var(--primary-color);
        }
        .order-item {
            display: grid;
            grid-template-columns: 1fr 80px 80px 100px;
            gap: var(--spacing-md);
            align-items: center;
            padding: var(--spacing-sm) 0;
            border-bottom: 1px solid #eee;
        }
        .order-item:last-child {
            border-bottom: none;
        }
        .item-total {
            text-align: right;
            font-weight: 600;
        }
        .supplier-total {
            text-align: right;
            font-size: 16px;
            font-weight: 700;
            color: var(--primary-color);
            margin-top: var(--spacing-md);
            padding-top: var(--spacing-md);
            border-top: 2px solid var(--primary-color);
        }
        .payment-form {
            background: white;
            padding: var(--spacing-lg);
            border-radius: 5px;
            box-shadow: 0 2px 4px rgba(0,0,0,0.1);
        }
        .payment-method-options {
            margin-bottom: var(--spacing-lg);
        }
        .payment-method-option {
            display: flex;
            align-items: center;
            padding: var(--spacing-md);
            border: 2px solid #ddd;
            border-radius: 6px;
            margin-bottom: var(--spacing-md);
            cursor: pointer;
            transition: all 0.3s;
        }
        .payment-method-option:hover {
            border-color: var(--primary-color);
            background: #fff5f5;
        }
        .payment-method-option input[type="radio"] {
            margin-right: var(--spacing-md);
            width: 18px;
            height: 18px;
            cursor: pointer;
        }
        .payment-method-option input[type="radio"]:checked + label {
            color: var(--primary-color);
            font-weight: 600;
        }
        .payment-method-option label {
            cursor: pointer;
            margin: 0;
            flex: 1;
        }
        .total-amount {
            background: linear-gradient(135deg, #FF6B6B, #FFB347);
            color: white;
            padding: var(--spacing-lg);
            border-radius: 8px;
            text-align: center;
            margin-bottom: var(--spacing-lg);
        }
        .total-amount-label {
            font-size: 14px;
            opacity: 0.9;
        }
        .total-amount-value {
            font-size: 32px;
            font-weight: 700;
        }
        .action-buttons {
            display: flex;
            gap: var(--spacing-md);
        }
        .action-buttons .btn {
            flex: 1;
            padding: var(--spacing-md);
            text-align: center;
            font-weight: 600;
        }
        .test-info {
            background: #e8f4f8;
            border-left: 4px solid #0088cc;
            padding: var(--spacing-md);
            border-radius: 4px;
            margin-bottom: var(--spacing-lg);
            font-size: 14px;
        }
        .test-info strong {
            color: #0066aa;
        }
        #paymentContainer {
            min-height: 400px;
            display: none;
        }
    </style>
</head>
<body>
    <!-- Header/Navigation -->
    <header>
        <div class="header-container">
            <a href="/LechGo_Final/public/" class="no-underline">
                <div class="logo">
                    <img src="/LechGo_Final/public/images/Logo.png" alt="LechGO Logo" class="logo-img">
                    <div class="logo-text">LechGO</div>
                </div>
            </a>
            <nav>
                <div class="user-profile">
                    <div class="user-avatar"><?php echo strtoupper(substr($user['name'] ?? 'U', 0, 1)); ?></div>
                    <div class="user-info">
                        <p class="name"><?php echo htmlspecialchars($user['name'] ?? 'User'); ?></p>
                        <p class="email"><?php echo htmlspecialchars($user['email'] ?? ''); ?></p>
                    </div>
                    <a href="#" class="btn btn-secondary ml-md" id="logoutBtn">Logout</a>
                </div>
            </nav>
        </div>
    </header>

    <main>
        <div class="payment-container">
            <!-- Back Button -->
            <a href="/LechGo_Final/public/livestock-owner/checkout" class="back-button">← Back to Checkout</a>

            <div style="margin-bottom: var(--spacing-lg);">
                <h1>Secure Payment</h1>
            </div>

            <div class="payment-grid">
                <!-- Order Summary -->
                <div>
                    <div class="order-summary">
                        <h2 style="margin: 0 0 var(--spacing-lg) 0;">Order Details</h2>

                        <?php foreach ($orders_with_names as $supplier_id => $order_data): ?>
                            <div class="supplier-section">
                                <h3><?php echo htmlspecialchars($order_data['name']); ?></h3>

                                <?php $itemNum = 1; ?>
                                <?php foreach ($order_data['items'] as $item): ?>
                                    <div class="order-item">
                                        <div>
                                            <strong><?php echo $itemNum; ?>. <?php echo htmlspecialchars($item['product_name']); ?></strong><br>
                                            <small><?php echo htmlspecialchars($item['feed_type']); ?></small>
                                        </div>
                                        <div><?php echo number_format($item['quantity_kg'], 1); ?> kg</div>
                                        <div>₱<?php echo number_format($item['unit_price'], 2); ?></div>
                                        <div class="item-total">₱<?php echo number_format($item['subtotal'], 2); ?></div>
                                    </div>
                                    <?php $itemNum++; ?>
                                <?php endforeach; ?>

                                <div class="supplier-total">
                                    Subtotal: ₱<?php echo number_format($order_data['total'], 2); ?>
                                </div>
                            </div>
                        <?php endforeach; ?>

                        <div style="margin-top: var(--spacing-lg); padding-top: var(--spacing-lg); border-top: 2px solid #FF6B6B;">
                            <div style="display: flex; justify-content: space-between; align-items: center; font-size: 20px; font-weight: 700; color: var(--primary-color);">
                                <span>Total Amount:</span>
                                <span id="totalDisplay">₱<?php echo number_format($total_amount, 2); ?></span>
                            </div>
                        </div>

                        <div style="margin-top: var(--spacing-lg); padding: var(--spacing-md); background: #f5f5f5; border-radius: 4px; font-size: 13px;">
                            <p style="margin: 0 0 var(--spacing-sm) 0;"><strong>Delivery Address:</strong><br><?php echo htmlspecialchars($delivery_address); ?></p>
                        </div>
                    </div>
                </div>

                <!-- Payment Form -->
                <div>
                    <!-- Payment Status Container -->
                    <div id="paymentContainer" class="alert alert-info" style="margin-bottom: var(--spacing-lg);">
                         Initializing payment system...
                    </div>

                    <form id="paymentForm" method="POST" action="/LechGo_Final/public/livestock-owner/payment" class="payment-form">
                        <h2 style="margin: 0 0 var(--spacing-lg) 0;">Payment Method</h2>

                        <div class="test-info">
                            <strong> Test Mode</strong><br>
                            Card: 4343 4343 4343 4345<br>
                            Exp: 12/25 | CVC: 123<br>
                            Amount: Any amount
                        </div>

                        <div class="payment-method-options">
                            <div class="payment-method-option">
                                <input type="radio" id="card" name="payment_type" value="card" checked>
                                <label for="card">💳 Credit/Debit Card</label>
                            </div>
                            <div class="payment-method-option">
                                <input type="radio" id="gcash" name="payment_type" value="gcash">
                                <label for="gcash">📱 GCash</label>
                            </div>
                            <div class="payment-method-option">
                                <input type="radio" id="grab" name="payment_type" value="grab_pay">
                                <label for="grab">🚗 Grab Pay</label>
                            </div>
                            <div class="payment-method-option">
                                <input type="radio" id="cod" name="payment_type" value="cod">
                                <label for="cod">💰 Cash on Delivery</label>
                            </div>
                        </div>

                        <div class="total-amount">
                            <div class="total-amount-label">Total Amount to Pay</div>
                            <div class="total-amount-value">₱<?php echo number_format($total_amount, 2); ?></div>
                        </div>

                        <div class="action-buttons">
                            <a href="/LechGo_Final/public/livestock-owner/checkout" class="btn btn-secondary">Cancel</a>
                            <button type="button" id="payButton" class="btn btn-primary" onclick="processPayment()">Pay Now</button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </main>

    <!-- Footer -->
    <footer>
        <div class="footer-bottom" style="border-top: 1px solid rgba(255,255,255,0.2);"></div>
    </footer>

    <script>
        let paymentIntentId = null;
        let clientKey = null;
        let checkoutUrl = null;

        async function initializePayment() {
            try {
                console.log('Initializing payment...');
                const totalInCentavos = Math.round(<?php echo $total_amount; ?> * 100);
                console.log('Total amount in centavos:', totalInCentavos);
                
                const response = await fetch('/LechGo_Final/public/api/create-payment-intent', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                    },
                    body: JSON.stringify({
                        amount: totalInCentavos,
                        description: 'Feed Order Payment - LechGO'
                    })
                });

                console.log('Response status:', response.status);
                const data = await response.json();
                console.log('Response data:', data);
                
                if (!response.ok) {
                    throw new Error(data.error || `API Error: ${response.status}`);
                }
                
                if (data.success && data.intentId && data.checkout_url) {
                    paymentIntentId = data.intentId;
                    clientKey = data.client_key;
                    checkoutUrl = data.checkout_url;
                    console.log('Payment intent created:', paymentIntentId);
                    console.log('Checkout URL:', checkoutUrl);
                    
                    // Update button and show ready state
                    const statusDiv = document.getElementById('paymentContainer');
                    statusDiv.innerHTML = '✓ <strong>Ready to pay ₱' + (<?php echo $total_amount; ?>).toFixed(2) + '</strong><br><small>Click "Pay Now" to proceed to PayMongo checkout</small>';
                    statusDiv.className = 'alert alert-success';
                } else {
                    throw new Error(data.error || 'Failed to initialize payment');
                }
            } catch (error) {
                console.error('Payment initialization error:', error);
                const statusDiv = document.getElementById('paymentContainer');
                statusDiv.innerHTML = '✗ <strong>Error:</strong> ' + error.message;
                statusDiv.className = 'alert alert-error';
            }
        }

        async function processPayment() {
            const paymentMethod = document.querySelector('input[name="payment_type"]:checked').value;
            
            // Handle Cash on Delivery separately
            if (paymentMethod === 'cod') {
                const payButton = document.getElementById('payButton');
                payButton.disabled = true;
                payButton.innerHTML = '⏳ Processing...';
                
                try {
                    // Redirect directly to payment success (which will mark as unpaid but create the order)
                    window.location.href = '/LechGo_Final/public/livestock-owner/payment-success?method=cod';
                } catch (error) {
                    console.error('COD Error:', error);
                    alert('Error processing COD order: ' + error.message);
                    payButton.disabled = false;
                    payButton.innerHTML = 'Pay Now';
                }
                return;
            }
            
            // Handle PayMongo payments
            if (!paymentIntentId || !checkoutUrl) {
                alert('Payment system not ready. Please refresh the page.');
                return;
            }

            const payButton = document.getElementById('payButton');
            payButton.disabled = true;
            payButton.innerHTML = '💳 Opening PayMongo...';

            try {
                console.log('Processing payment with intent:', paymentIntentId);
                console.log('Checkout URL:', checkoutUrl);
                
                // Redirect to PayMongo checkout
                window.location.href = checkoutUrl;

            } catch (error) {
                console.error('Payment error:', error);
                const statusDiv = document.getElementById('paymentContainer');
                statusDiv.innerHTML = '✗ <strong>Error:</strong> ' + error.message;
                statusDiv.className = 'alert alert-error';
                payButton.disabled = false;
                payButton.innerHTML = 'Pay Now';
            }
        }

        // Initialize on page load
        document.addEventListener('DOMContentLoaded', function() {
            console.log('Payment page loaded, initializing...');
            initializePayment();
        });

        // Handle payment method changes
        document.querySelectorAll('input[name="payment_type"]').forEach(option => {
            option.addEventListener('change', function() {
                console.log('Payment method selected:', this.value);
            });
        });
    </script>

    <!-- Logout Confirmation Modal -->
    <div class="modal" id="logoutModal">
        <div class="modal-content">
            <div class="modal-header">
                <h2>Confirm Logout</h2>
                <button class="modal-close" id="closeLogoutModal">&times;</button>
            </div>
            <div class="modal-body">
                <p>Are you sure you want to logout?</p>
            </div>
            <div class="modal-footer">
                <button class="btn btn-secondary" id="cancelLogout">Cancel</button>
                <button class="btn btn-primary" id="confirmLogout">Yes, Logout</button>
            </div>
        </div>
    </div>

    <script src="/LechGo_Final/public/script.js"></script>
</body>
</html>

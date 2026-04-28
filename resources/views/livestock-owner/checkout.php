<?php
$currentPage = 'checkout';

$user = $_SESSION['user'] ?? null;
$cart = $_SESSION['feed_cart'] ?? [];

// Get livestock owner info including location
$owner_location = '';
$query = "SELECT lo.location FROM livestock_owners lo WHERE lo.user_id = ?";
$stmt = $GLOBALS['conn']->prepare($query);
if ($stmt) {
    $stmt->bind_param('i', $user['id']);
    $stmt->execute();
    $result = $stmt->get_result();
    $owner_data = $result->fetch_assoc();
    $owner_location = $owner_data['location'] ?? '';
    $stmt->close();
}

// Group items by supplier
$orders_by_supplier = [];
$total_all = 0;
foreach ($cart as $item) {
    $supplier_id = $item['supplier_id'];
    if (!isset($orders_by_supplier[$supplier_id])) {
        $orders_by_supplier[$supplier_id] = [
            'items' => [],
            'total' => 0,
            'supplier_name' => ''
        ];
    }
    $orders_by_supplier[$supplier_id]['items'][] = $item;
    $orders_by_supplier[$supplier_id]['total'] += $item['subtotal'];
    $total_all += $item['subtotal'];
}

// Get supplier names
if (!empty($orders_by_supplier)) {
    foreach ($orders_by_supplier as $supplier_id => &$order_data) {
        $query = "SELECT name FROM suppliers WHERE id = ?";
        $stmt = $GLOBALS['conn']->prepare($query);
        if ($stmt) {
            $stmt->bind_param('i', $supplier_id);
            $stmt->execute();
            $result = $stmt->get_result();
            $supplier = $result->fetch_assoc();
            $order_data['supplier_name'] = $supplier['name'] ?? 'Unknown Supplier';
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
    <title>Feed Order Checkout - LechGO</title>   
     <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.8/dist/css/bootstrap.min.css" rel="stylesheet">    <link rel="stylesheet" href="/LechGo_Final/public/styles.css">
    <style>
        .checkout-container {
            max-width: 1200px;
            margin: 0 auto;
            padding: 0.5rem;
        }
        .checkout-grid {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 0.5rem;
        }
        .order-summary {
            background: white;
            padding: 0.75rem;
            border-radius: 8px;
            box-shadow: 0 2px 4px rgba(0,0,0,0.1);
        }
        .supplier-section {
            margin-bottom: 0.5rem;
            padding: 0.5rem;
            background: #f9f9f9;
            border-left: 4px solid var(--primary-color);
            border-radius: 4px;
        }
        .supplier-section h3 {
            margin: 0 0 0.5rem 0;
            color: var(--primary-color);
            font-size: 1rem;
        }
        .order-item {
            display: grid;
            grid-template-columns: 1fr 80px 80px 100px;
            gap: 0.5rem;
            align-items: center;
            padding: 0.25rem 0;
            border-bottom: 1px solid #eee;
            font-size: 0.85rem;
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
            font-size: 0.95rem;
            font-weight: 700;
            color: var(--primary-color);
            margin-top: 0.5rem;
            padding-top: 0.5rem;
            border-top: 2px solid var(--primary-color);
        }
        .checkout-form {
            background: white;
            padding: 0.75rem;
            border-radius: 8px;
            box-shadow: 0 2px 4px rgba(0,0,0,0.1);
        }
        .form-group {
            margin-bottom: 0.5rem;
        }
        .form-group label {
            display: block;
            margin-bottom: 0.25rem;
            font-weight: 600;
            color: var(--dark-gray);
            font-size: 0.8rem;
        }
        .form-group input,
        .form-group textarea {
            width: 100%;
            padding: 0.4rem;
            border: 1px solid #ddd;
            border-radius: 4px;
            font-size: 0.85rem;
        }
        .form-group textarea {
            resize: vertical;
            min-height: 60px;
        }
        .form-group input:focus,
        .form-group textarea:focus {
            outline: none;
            border-color: var(--primary-color);
            box-shadow: 0 0 0 2px rgba(255,107,107,0.1);
        }
        .total-amount {
            background: linear-gradient(135deg, #FF6B6B, #FFB347);
            color: white;
            padding: 0.75rem;
            border-radius: 8px;
            text-align: center;
            margin-bottom: 0.5rem;
        }
        .total-amount-label {
            font-size: 0.8rem;
            opacity: 0.9;
        }
        .total-amount-value {
            font-size: 1.75rem;
            font-weight: 700;
        }
        .action-buttons {
            display: flex;
            gap: 0.5rem;
        }
        .action-buttons .btn {
            flex: 1;
            padding: 0.5rem;
            text-align: center;
            font-weight: 600;
            font-size: 0.85rem;
        }
        .empty-cart {
            text-align: center;
            padding: 1.5rem;
        }
        .empty-cart-icon {
            font-size: 2rem;
            margin-bottom: 0.5rem;
        }
    </style>
</head>
<body>
    <div class="dashboard-layout">
        <?php include __DIR__ . '/../layouts/sidebar.php'; ?>
        
        <main class="dashboard-main">
        <div class="checkout-container">
            <!-- Display Messages -->
            <?php if (isset($_SESSION['success'])): ?>
                <div class="alert alert-success show">
                    ✓ <?php echo htmlspecialchars($_SESSION['success']); ?>
                </div>
                <?php unset($_SESSION['success']); ?>
            <?php endif; ?>

            <?php if (isset($_SESSION['error'])): ?>
                <div class="alert alert-error show">
                    ✗ <?php echo htmlspecialchars($_SESSION['error']); ?>
                </div>
                <?php unset($_SESSION['error']); ?>
            <?php endif; ?>

            <div style="margin-bottom: 0.5rem;">
                <h1 style="font-size: 1.5rem; margin: 0;"> Order Summary & Checkout</h1>
            </div>

            <?php if (empty($cart)): ?>
                <div class="order-summary">
                    <div class="empty-cart">
                        <div class="empty-cart-icon"></div>
                        <h2>Your cart is empty</h2>
                        <p>Add some feed products to get started!</p>
                        <a href="/LechGo_Final/public/livestock-owner/available-feeds" class="btn btn-primary">Continue Shopping</a>
                    </div>
                </div>
            <?php else: ?>
                <div class="checkout-grid">
                    <!-- Order Summary -->
                    <div>
                        <div class="order-summary">
                            <h2 style="margin: 0 0 0.5rem 0; font-size: 1.25rem;">Order Details</h2>

                            <?php foreach ($orders_by_supplier as $supplier_id => $order_data): ?>
                                <div class="supplier-section">
                                    <h3><?php echo htmlspecialchars($order_data['supplier_name']); ?></h3>

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

                            <div style="margin-top: 0.5rem; padding-top: 0.5rem; border-top: 2px solid #FF6B6B;">
                                <div style="display: flex; justify-content: space-between; align-items: center; font-size: 1.1rem; font-weight: 700; color: var(--primary-color);">
                                    <span>Grand Total:</span>
                                    <span>₱<?php echo number_format($total_all, 2); ?></span>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Checkout Form -->
                    <div>
                        <form method="POST" action="/LechGo_Final/public/livestock-owner/checkout" class="checkout-form">
                            <h2 style="margin: 0 0 0.5rem 0; font-size: 1.25rem;">Delivery Information</h2>

                            <div class="form-group">
                                <label for="delivery_address">Delivery Address *</label>
                                <textarea name="delivery_address" id="delivery_address" placeholder="Enter your complete delivery address" required><?php echo htmlspecialchars($owner_location); ?></textarea>
                            </div>

                            <div class="form-group">
                                <label for="payment_method">Payment Method *</label>
                                <select name="payment_method" id="payment_method" required style="width: 100%; padding: 0.4rem; border: 1px solid #ddd; border-radius: 4px; font-size: 0.85rem;">
                                    <option value="">-- Select Payment Method --</option>
                                    <option value="test_payment"> Test Payment (Development Only)</option>
                                    <option value="online_payment">Online Payment (PayMongo - Card, GCash, Grab Pay)</option>
                                    <option value="cash_on_delivery">Cash on Delivery</option>
                                    <option value="bank_transfer">Bank Transfer</option>
                                </select>
                            </div>

                            <div style="background: #f0f0f0; padding: 0.5rem; border-radius: 4px; margin-bottom: 0.5rem;">
                                <p style="margin: 0; font-size: 0.75rem; color: #666;">
                                    <strong> Note:</strong> You are placing <?php echo count($orders_by_supplier); ?> separate order(s), one for each supplier. Each supplier will review and confirm the order independently.
                                </p>
                            </div>

                            <div class="total-amount">
                                <div class="total-amount-label">Total Amount to Pay</div>
                                <div class="total-amount-value">₱<?php echo number_format($total_all, 2); ?></div>
                            </div>

                            <div class="action-buttons">
                                <a href="/LechGo_Final/public/livestock-owner/available-feeds" class="btn btn-secondary">Keep Shopping</a>
                                <button type="submit" class="btn btn-primary">Place Order</button>
                            </div>
                        </form>
                    </div>
                </div>
            <?php endif; ?>
        </div>
    </main>

    </div>

    <!-- PayMongo Script -->
    <script src="https://js.paymongo.com/v1/checkout.js"></script>
    
    <script>
        // Total amount for payment
        const totalAmount = <?php echo $total_all; ?>;
        let paymentIntentId = null;
        let clientKey = null;
        let checkoutUrl = null;

        // Handle form submission
        document.querySelector('form').addEventListener('submit', async function(e) {
            const paymentMethod = document.getElementById('payment_method').value;
            
            // If online payment, handle via PayMongo
            if (paymentMethod === 'online_payment') {
                e.preventDefault();
                await handleOnlinePayment(this);
            }
            // For other methods (COD, Bank Transfer), submit normally
        });

        async function handleOnlinePayment(form) {
            try {
                const payButton = form.querySelector('button[type="submit"]');
                payButton.disabled = true;
                payButton.innerHTML = ' Processing...';

                // Step 1: Save pending orders by submitting form via AJAX
                const formData = new FormData(form);
                const saveResponse = await fetch(form.action, {
                    method: 'POST',
                    body: formData,
                    headers: {
                        'X-Requested-With': 'XMLHttpRequest'
                    }
                });

                if (!saveResponse.ok) {
                    throw new Error('Failed to save order information');
                }

                payButton.innerHTML = ' Initializing payment...';

                // Step 2: Create payment intent with PayMongo
                const totalInCentavos = Math.round(totalAmount * 100);
                const paymentResponse = await fetch('/LechGo_Final/public/api/create-payment-intent', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                    },
                    body: JSON.stringify({
                        amount: totalInCentavos,
                        description: 'Feed Order Payment - LechGO'
                    })
                });

                const paymentData = await paymentResponse.json();
                console.log('Payment API response:', paymentData);

                if (!paymentResponse.ok) {
                    console.error('API Error:', paymentData);
                    throw new Error(paymentData.error || `API Error: ${paymentResponse.status}`);
                }

                if (!paymentData.success) {
                    console.error('Payment creation failed:', paymentData);
                    throw new Error(paymentData.error || 'Failed to create payment session');
                }

                if (!paymentData.checkout_url) {
                    console.error('No checkout URL:', paymentData);
                    throw new Error('No checkout URL received from PayMongo');
                }

                paymentIntentId = paymentData.intentId;
                checkoutUrl = paymentData.checkout_url;

                console.log('Redirecting to:', checkoutUrl);

                // Store payment intent ID for the success page to retrieve
                sessionStorage.setItem('paymentIntentId', paymentIntentId);

                // Step 3: Redirect to PayMongo checkout 
                payButton.innerHTML = ' Opening PayMongo...';
                window.location.href = checkoutUrl;

            } catch (error) {
                console.error('Payment error:', error);
                alert('Error: ' + error.message);
                const payButton = form.querySelector('button[type="submit"]');
                payButton.disabled = false;
                payButton.innerHTML = 'Place Order';
            }
        }
    </script>

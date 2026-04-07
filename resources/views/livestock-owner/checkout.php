<?php
$user = $_SESSION['user'] ?? null;
$cart = $_SESSION['feed_cart'] ?? [];

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
    <link rel="stylesheet" href="/LechGo_Final/public/styles.css">
    <style>
        .checkout-container {
            max-width: 1200px;
            margin: 0 auto;
            padding: var(--spacing-lg);
        }
        .checkout-grid {
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
            font-size: 18px;
            font-weight: 700;
            color: var(--primary-color);
            margin-top: var(--spacing-md);
            padding-top: var(--spacing-md);
            border-top: 2px solid var(--primary-color);
        }
        .checkout-form {
            background: white;
            padding: var(--spacing-lg);
            border-radius: 8px;
            box-shadow: 0 2px 4px rgba(0,0,0,0.1);
        }
        .form-group {
            margin-bottom: var(--spacing-md);
        }
        .form-group label {
            display: block;
            margin-bottom: var(--spacing-sm);
            font-weight: 600;
            color: var(--dark-gray);
        }
        .form-group input,
        .form-group textarea {
            width: 100%;
            padding: var(--spacing-sm);
            border: 1px solid #ddd;
            border-radius: 4px;
            font-size: 14px;
        }
        .form-group textarea {
            resize: vertical;
            min-height: 80px;
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
        .empty-cart {
            text-align: center;
            padding: 40px;
        }
        .empty-cart-icon {
            font-size: 48px;
            margin-bottom: var(--spacing-md);
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
                    <a href="/LechGo_Final/public/logout" class="btn btn-secondary ml-md">Logout</a>
                </div>
            </nav>
        </div>
    </header>

    <main>
        <div class="checkout-container">
            <!-- Back Button -->
            <a href="/LechGo_Final/public/livestock-owner/available-feeds" class="back-button">← Back to Feed Store</a>

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

            <div style="margin-bottom: var(--spacing-lg);">
                <h1>🛒 Order Summary & Checkout</h1>
            </div>

            <?php if (empty($cart)): ?>
                <div class="order-summary">
                    <div class="empty-cart">
                        <div class="empty-cart-icon">📭</div>
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
                            <h2 style="margin: 0 0 var(--spacing-lg) 0;">Order Details</h2>

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

                            <div style="margin-top: var(--spacing-lg); padding-top: var(--spacing-lg); border-top: 2px solid #FF6B6B;">
                                <div style="display: flex; justify-content: space-between; align-items: center; font-size: 20px; font-weight: 700; color: var(--primary-color);">
                                    <span>Grand Total:</span>
                                    <span>₱<?php echo number_format($total_all, 2); ?></span>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Checkout Form -->
                    <div>
                        <form method="POST" action="/LechGo_Final/public/livestock-owner/checkout" class="checkout-form">
                            <h2 style="margin: 0 0 var(--spacing-lg) 0;">Delivery Information</h2>

                            <div class="form-group">
                                <label for="delivery_address">Delivery Address *</label>
                                <textarea name="delivery_address" id="delivery_address" placeholder="Enter your complete delivery address" required></textarea>
                            </div>

                            <div class="form-group">
                                <label for="delivery_notes">Special Instructions (Optional)</label>
                                <textarea name="delivery_notes" id="delivery_notes" placeholder="Any special instructions for delivery..."></textarea>
                            </div>

                            <div class="form-group">
                                <label for="payment_method">Payment Method *</label>
                                <select name="payment_method" id="payment_method" required style="width: 100%; padding: var(--spacing-sm); border: 1px solid #ddd; border-radius: 4px; font-size: 14px;">
                                    <option value="">-- Select Payment Method --</option>
                                    <option value="online_payment">Online Payment (PayMongo - Card, GCash, Grab Pay)</option>
                                    <option value="cash_on_delivery">Cash on Delivery</option>
                                    <option value="bank_transfer">Bank Transfer</option>
                                </select>
                            </div>

                            <div style="background: #f0f0f0; padding: var(--spacing-md); border-radius: 4px; margin-bottom: var(--spacing-lg);">
                                <p style="margin: 0; font-size: 14px; color: #666;">
                                    <strong>💡 Note:</strong> You are placing <?php echo count($orders_by_supplier); ?> separate order(s), one for each supplier. Each supplier will review and confirm the order independently.
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

    <!-- Footer -->
    <footer>
        <div class="footer-bottom" style="border-top: 1px solid rgba(255,255,255,0.2);">
            <p>&copy; 2026 LechGO. All rights reserved.</p>
        </div>
    </footer>
</body>
</html>

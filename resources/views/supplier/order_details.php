<?php
/**
 * Supplier - Order Details
 * View detailed information about a submitted order
 */

// Check if user is authenticated as supplier
$user = $_SESSION['user'] ?? null;
if (!$user || $user['role'] !== 'supplier') {
    header('Location: ' . '/LechGo_Final/public/login');
    exit;
}

$feedOrder = new FeedOrder($GLOBALS['conn']);
$order = $feedOrder->getOrderById($order_id);

if (!$order) {
    $_SESSION['error'] = 'Order not found';
    header('Location: ' . '/LechGo_Final/public/supplier/my-orders');
    exit;
}

// Verify supplier owns this order
$supplier = new FeedSupplier($GLOBALS['conn']);
if (!$supplier->findByUserId($user['id']) || $supplier->id !== $order['supplier_id']) {
    $_SESSION['error'] = 'Unauthorized';
    header('Location: ' . '/LechGo_Final/public/supplier/my-orders');
    exit;
}

?>

<!DOCTYPE html> 
<html lang="en">
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
<body class="body-bg">
    <div class="container mt-lg">
        <div class="order-detail-container">
            <!-- Header -->
            <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: var(--spacing-lg);">
                <div>
                    <h1 class="page-title">Order #<?php echo str_pad($order['id'], 6, "0", STR_PAD_LEFT); ?></h1>
                    <p class="text-gray"><?php echo date('M d, Y g:i A', strtotime($order['created_at'])); ?></p>
                </div>
                <span class="status-badge status-<?php echo $order['order_status']; ?>">
                    <?php echo str_replace('_', ' ', ucfirst($order['order_status'])); ?>
                </span>
            </div>

            <!-- Order Status & Payment Info -->
            <div class="detail-section">
                <div class="section-title">Order Status</div>
                <div class="detail-grid">
                    <div class="detail-box">
                        <div class="detail-label">Order Status</div>
                        <div class="detail-value"><?php echo str_replace('_', ' ', ucfirst($order['order_status'])); ?></div>
                    </div>
                    <div class="detail-box">
                        <div class="detail-label">Payment Status</div>
                        <div class="detail-value"><?php echo str_replace('_', ' ', ucfirst($order['payment_status'])); ?></div>
                    </div>
                    <div class="detail-box">
                        <div class="detail-label">Payment Method</div>
                        <div class="detail-value"><?php echo ucfirst(str_replace('_', ' ', $order['payment_method'] ?? 'Cash on Delivery')); ?></div>
                    </div>
                </div>
            </div>

            <!-- Supplier & Caretaker Info -->
            <div class="detail-section">
                <div class="section-title">Parties Involved</div>
                <div class="detail-grid">
                    <div class="detail-box">
                        <div class="detail-label">📦 From Supplier</div>
                        <div class="detail-value"><?php echo htmlspecialchars($order['supplier_name']); ?></div>
                        <p style="font-size: 0.9rem; color: var(--text-gray); margin-top: 4px;"><?php echo htmlspecialchars($order['supplier_farm']); ?></p>
                        <p style="font-size: 0.9rem; color: var(--text-gray);">📞 <?php echo htmlspecialchars($order['supplier_contact']); ?></p>
                    </div>
                    <div class="detail-box">
                        <div class="detail-label">➡️ To Caretaker</div>
                        <div class="detail-value"><?php echo htmlspecialchars($order['caretaker_name']); ?></div>
                        <p style="font-size: 0.9rem; color: var(--text-gray); margin-top: 4px;"><?php echo htmlspecialchars($order['caretaker_farm']); ?></p>
                        <p style="font-size: 0.9rem; color: var(--text-gray);">📞 <?php echo htmlspecialchars($order['caretaker_contact']); ?></p>
                    </div>
                </div>
            </div>

            <!-- Order Items -->
            <div class="detail-section">
                <div class="section-title">Order Items</div>
                <table class="items-table">
                    <thead>
                        <tr>
                            <th>#</th>
                            <th>Feed Type</th>
                            <th>Quantity</th>
                            <th>Unit Price</th>
                            <th>Subtotal</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php $itemNum = 1; ?>
                        <?php foreach ($order['items'] as $item): ?>
                        <tr>
                            <td><?php echo $itemNum; ?></td>
                            <td><?php echo htmlspecialchars($item['feed_type']); ?></td>
                            <td><?php echo number_format($item['quantity_ordered_kg'], 2); ?> kg</td>
                            <td>₱<?php echo number_format($item['unit_price'], 2); ?>/kg</td>
                            <td>₱<?php echo number_format($item['subtotal'], 2); ?></td>
                        </tr>
                        <?php $itemNum++; ?>
                        <?php endforeach; ?>
                        <tr class="total-row">
                            <td colspan="4" style="text-align: right;">TOTAL</td>
                            <td>₱<?php echo number_format($order['total_amount'], 2); ?></td>
                        </tr>
                    </tbody>
                </table>
            </div>

            <!-- Notes -->
            <?php if ($order['notes']): ?>
            <div class="detail-section">
                <div class="section-title">Order Notes</div>
                <div class="response-box">
                    <p><?php echo htmlspecialchars($order['notes']); ?></p>
                </div>
            </div>
            <?php endif; ?>

            <!-- Caretaker Response -->
            <?php if ($order['caretaker_response']): ?>
            <div class="detail-section">
                <div class="section-title">Caretaker Response</div>
                <div class="response-box">
                    <div style="font-weight: 600; color: var(--primary-red); margin-bottom: var(--spacing-sm);">
                        Status: <?php echo str_replace('_', ' ', ucfirst($order['order_status'])); ?>
                    </div>
                    <div style="font-size: 0.9rem; color: var(--text-gray); margin-bottom: var(--spacing-sm);">
                        <?php echo date('M d, Y g:i A', strtotime($order['caretaker_response_date'])); ?>
                    </div>
                    <div class="response-text">
                        <?php echo htmlspecialchars($order['caretaker_response']); ?>
                    </div>
                </div>
            </div>
            <?php endif; ?>

            <!-- Action Buttons -->
            <div class="action-buttons">
                <?php if ($order['order_status'] === 'accepted' || $order['order_status'] === 'completed'): ?>
                    <a href="/LechGo_Final/public/order-receipt/<?php echo $order['id']; ?>" class="btn btn-primary">📄 View Receipt</a>
                <?php endif; ?>
                <a href="/LechGo_Final/public/supplier/my-orders" class="btn btn-secondary">← Back to Orders</a>
            </div>
        </div>
    </div>
</body>
</html>

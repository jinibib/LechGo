<?php
$currentPage = 'fd-orders';

$sessionMiddleware = new Session();
$user = $sessionMiddleware->getUser();

if (!$sessionMiddleware->isAuthenticated() || $user['role'] !== 'feed_distributor') {
    header('Location: /LechGo_Final/public/login');
    exit;
}

// Get distributor record
$stmt = $GLOBALS['conn']->prepare("SELECT id, business_name FROM feed_distributors WHERE user_id = ?");
$stmt->bind_param('i', $user['id']);
$stmt->execute();
$distributor = $stmt->get_result()->fetch_assoc();
$stmt->close();

if (!$distributor) {
    $_SESSION['error'] = 'Distributor profile not found';
    header('Location: /LechGo_Final/public/dashboard');
    exit;
}

// Get order — must belong to this distributor
$stmt = $GLOBALS['conn']->prepare(
    "SELECT fdo.*, u.name AS buyer_user_name, u.email AS buyer_email
     FROM feed_distributor_orders fdo
     LEFT JOIN users u ON fdo.buyer_user_id = u.id
     WHERE fdo.id = ? AND fdo.distributor_id = ?"
);
$stmt->bind_param('ii', $order_id, $distributor['id']);
$stmt->execute();
$order = $stmt->get_result()->fetch_assoc();
$stmt->close();

if (!$order) {
    $_SESSION['error'] = 'Order not found';
    header('Location: /LechGo_Final/public/feed-distributor/orders');
    exit;
}

// Get order items
$stmt = $GLOBALS['conn']->prepare(
    "SELECT fdoi.*, fdp.image_url
     FROM feed_distributor_order_items fdoi
     LEFT JOIN feed_distributor_products fdp ON fdoi.product_id = fdp.id
     WHERE fdoi.order_id = ?"
);
$stmt->bind_param('i', $order_id);
$stmt->execute();
$items = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
$stmt->close();

$status_colors = [
    'pending'            => ['bg' => '#fff3cd', 'color' => '#856404'],
    'confirmed'          => ['bg' => '#d1ecf1', 'color' => '#0c5460'],
    'processing'         => ['bg' => '#cce5ff', 'color' => '#004085'],
    'delivered'          => ['bg' => '#d4edda', 'color' => '#155724'],
    'cancelled'          => ['bg' => '#f8d7da', 'color' => '#721c24'],
];
$sc = $status_colors[$order['order_status']] ?? ['bg' => '#f0f0f0', 'color' => '#555'];
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Order Details - LechGO</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.8/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="/LechGo_Final/public/styles.css">
    <style>
        .detail-grid { display:grid; grid-template-columns:1fr 1fr; gap:20px; }
        .detail-box { background:white; border:1px solid #e0e0e0; border-radius:8px; padding:20px; }
        .detail-row { display:flex; justify-content:space-between; padding:8px 0; border-bottom:1px solid #f0f0f0; font-size:14px; }
        .detail-row:last-child { border-bottom:none; }
        .detail-label { color:#888; }
        .detail-value { font-weight:600; color:#2c3e50; text-align:right; max-width:60%; }
        .items-table { width:100%; border-collapse:collapse; }
        .items-table th { background:#f8f8f8; padding:10px 14px; text-align:left; font-size:12px; color:#666; text-transform:uppercase; border-bottom:2px solid #eee; }
        .items-table td { padding:12px 14px; border-bottom:1px solid #f0f0f0; font-size:14px; vertical-align:middle; }
        .items-table tr:last-child td { border-bottom:none; }
        .status-badge { display:inline-block; padding:5px 14px; border-radius:20px; font-size:12px; font-weight:700; }
        .action-bar { display:flex; gap:10px; flex-wrap:wrap; margin-top:20px; }
        @media(max-width:768px){ .detail-grid{ grid-template-columns:1fr; } }
    </style>
</head>
<body>
<div class="dashboard-layout">
    <?php include __DIR__ . '/../layouts/sidebar.php'; ?>
    <main class="dashboard-main">
    <div style="max-width:1000px;margin:0;padding:var(--spacing-md) 0;">

        <?php if (isset($_SESSION['success'])): ?>
            <div class="alert alert-success show">✓ <?php echo htmlspecialchars($_SESSION['success']); unset($_SESSION['success']); ?></div>
        <?php endif; ?>
        <?php if (isset($_SESSION['error'])): ?>
            <div class="alert alert-error show">✗ <?php echo htmlspecialchars($_SESSION['error']); unset($_SESSION['error']); ?></div>
        <?php endif; ?>

        <!-- Header -->
        <div style="margin-bottom:20px;display:flex;align-items:center;justify-content:space-between;flex-wrap:wrap;gap:10px;">
            <div>
                <a href="/LechGo_Final/public/feed-distributor/orders" style="font-size:13px;color:#e74c3c;">&larr; Back to Orders</a>
                <h1 style="margin:6px 0 0 0;">Order #<?php echo $order['id']; ?></h1>
                <?php if ($order['order_number']): ?>
                    <p style="color:#888;margin:2px 0 0 0;font-size:13px;"><?php echo htmlspecialchars($order['order_number']); ?></p>
                <?php endif; ?>
            </div>
            <span class="status-badge" style="background:<?php echo $sc['bg']; ?>;color:<?php echo $sc['color']; ?>;">
                <?php echo ucfirst(str_replace('_', ' ', $order['order_status'])); ?>
            </span>
        </div>

        <div class="detail-grid">
            <!-- Order info -->
            <div class="detail-box">
                <h3 style="margin:0 0 14px 0;font-size:15px;">Order Information</h3>
                <div class="detail-row"><span class="detail-label">Order Date</span><span class="detail-value"><?php echo date('M d, Y H:i', strtotime($order['created_at'])); ?></span></div>
                <div class="detail-row"><span class="detail-label">Order Status</span><span class="detail-value"><?php echo ucfirst(str_replace('_', ' ', $order['order_status'])); ?></span></div>
                <div class="detail-row"><span class="detail-label">Payment Status</span><span class="detail-value"><?php echo ucfirst($order['payment_status']); ?></span></div>
                <div class="detail-row"><span class="detail-label">Total Amount</span><span class="detail-value" style="color:#27ae60;font-size:16px;">₱<?php echo number_format($order['total_amount'], 2); ?></span></div>
                <?php if ($order['delivery_address']): ?>
                <div class="detail-row"><span class="detail-label">Delivery Address</span><span class="detail-value"><?php echo htmlspecialchars($order['delivery_address']); ?></span></div>
                <?php endif; ?>
            </div>

            <!-- Buyer info -->
            <div class="detail-box">
                <h3 style="margin:0 0 14px 0;font-size:15px;">Buyer Information</h3>
                <div class="detail-row"><span class="detail-label">Name</span><span class="detail-value"><?php echo htmlspecialchars($order['buyer_name'] ?? $order['buyer_user_name'] ?? 'N/A'); ?></span></div>
                <div class="detail-row"><span class="detail-label">Email</span><span class="detail-value"><?php echo htmlspecialchars($order['buyer_email'] ?? 'N/A'); ?></span></div>
                <?php if ($order['notes']): ?>
                <div class="detail-row"><span class="detail-label">Notes</span><span class="detail-value"><?php echo htmlspecialchars($order['notes']); ?></span></div>
                <?php endif; ?>
            </div>
        </div>

        <!-- Order items -->
        <div class="detail-box" style="margin-top:20px;">
            <h3 style="margin:0 0 16px 0;font-size:15px;">Order Items</h3>
            <?php if (empty($items)): ?>
                <p style="color:#aaa;text-align:center;padding:20px 0;">No items found.</p>
            <?php else: ?>
            <table class="items-table">
                <thead>
                    <tr>
                        <th>Product</th>
                        <th>Feed Type</th>
                        <th>Qty (kg)</th>
                        <th>Unit Price</th>
                        <th style="text-align:right;">Subtotal</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($items as $item): ?>
                    <tr>
                        <td>
                            <div style="display:flex;align-items:center;gap:10px;">
                                <?php if ($item['image_url']): ?>
                                    <img src="<?php echo htmlspecialchars($item['image_url']); ?>" style="width:40px;height:40px;object-fit:cover;border-radius:4px;">
                                <?php else: ?>
                                    <div style="width:40px;height:40px;background:#f0f0f0;border-radius:4px;display:flex;align-items:center;justify-content:center;font-size:18px;">📦</div>
                                <?php endif; ?>
                                <strong><?php echo htmlspecialchars($item['product_name']); ?></strong>
                            </div>
                        </td>
                        <td><?php echo htmlspecialchars($item['feed_type'] ?? '—'); ?></td>
                        <td><?php echo number_format($item['quantity_kg'], 1); ?> kg</td>
                        <td>₱<?php echo number_format($item['unit_price'], 2); ?></td>
                        <td style="text-align:right;font-weight:700;">₱<?php echo number_format($item['subtotal'], 2); ?></td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
                <tfoot>
                    <tr>
                        <td colspan="4" style="text-align:right;font-weight:700;padding:12px 14px;border-top:2px solid #eee;">Total</td>
                        <td style="text-align:right;font-weight:700;color:#27ae60;font-size:16px;padding:12px 14px;border-top:2px solid #eee;">₱<?php echo number_format($order['total_amount'], 2); ?></td>
                    </tr>
                </tfoot>
            </table>
            <?php endif; ?>
        </div>

        <!-- Action buttons -->
        <div class="action-bar">
            <?php if ($order['order_status'] === 'pending'): ?>
                <form method="POST" action="/LechGo_Final/public/feed-distributor/accept-order">
                    <input type="hidden" name="order_id" value="<?php echo $order['id']; ?>">
                    <button type="submit" class="btn btn-primary">Accept Order</button>
                </form>
            <?php endif; ?>
            <?php if (in_array($order['order_status'], ['confirmed','processing',])): ?>
                <form method="POST" action="/LechGo_Final/public/feed-distributor/update-order-status">
                    <input type="hidden" name="order_id" value="<?php echo $order['id']; ?>">
                    <select name="new_status" style="padding:8px 12px;border:1.5px solid #e0e0e0;border-radius:6px;font-size:13px;margin-right:8px;">
                        <option value="processing" <?php echo $order['order_status']==='processing'?'selected':''; ?>>Processing</option>
                        <option value="delivered" <?php echo $order['order_status']==='delivered'?'selected':''; ?>>Delivered</option>
                    </select>
                    <button type="submit" class="btn btn-primary">Update Status</button>
                </form>
            <?php endif; ?>
            <a href="/LechGo_Final/public/feed-distributor/orders" class="btn btn-secondary">Back to Orders</a>
        </div>

    </div>
    </main>

    <div class="modal" id="logoutModal">
        <div class="modal-content">
            <div class="modal-header"><h2>Confirm Logout</h2><button class="modal-close" id="closeLogoutModal">&times;</button></div>
            <div class="modal-body"><p>Are you sure you want to logout?</p></div>
            <div class="modal-footer">
                <button class="btn btn-secondary" id="cancelLogout">Cancel</button>
                <button class="btn btn-primary" id="confirmLogout">Yes, Logout</button>
            </div>
        </div>
    </div>
</div>
<script src="/LechGo_Final/public/script.js"></script>
</body>
</html>

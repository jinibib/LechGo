<?php
$currentPage = 'fd-orders';

$sessionMiddleware = new Session();
$user = $sessionMiddleware->getUser();

if (!$sessionMiddleware->isAuthenticated() || $user['role'] !== 'feed_distributor') {
    header('Location: /LechGo_Final/public/login');
    exit;
}

// Get distributor record
$query = "SELECT id, business_name FROM feed_distributors WHERE user_id = ?";
$stmt = $GLOBALS['conn']->prepare($query);
if (!$stmt) die('Database error: ' . $GLOBALS['conn']->error);
$stmt->bind_param('i', $user['id']);
$stmt->execute();
$distributor = $stmt->get_result()->fetch_assoc();
$stmt->close();

if (!$distributor) {
    $_SESSION['error'] = 'Distributor profile not found';
    header('Location: /LechGo_Final/public/dashboard');
    exit;
}

$distributor_id = $distributor['id'];

// Get all orders for this distributor
$query = "SELECT fdo.id, fdo.order_number, fdo.order_status, fdo.payment_status,
                 fdo.total_amount, fdo.delivery_address, fdo.created_at,
                 fdo.buyer_name, u.name AS buyer_user_name, u.email AS buyer_email,
                 COUNT(fdoi.id) AS item_count
          FROM feed_distributor_orders fdo
          LEFT JOIN users u ON fdo.buyer_user_id = u.id
          LEFT JOIN feed_distributor_order_items fdoi ON fdo.id = fdoi.order_id
          WHERE fdo.distributor_id = ?
          GROUP BY fdo.id
          ORDER BY fdo.created_at DESC";
$stmt = $GLOBALS['conn']->prepare($query);
if (!$stmt) { $orders = []; }
else {
    $stmt->bind_param('i', $distributor_id);
    $stmt->execute();
    $orders = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
    $stmt->close();
}

$orders_by_status = [
    'pending' => [], 'confirmed' => [], 'processing' => [],
    'ready_for_delivery' => [], 'delivered' => [], 'cancelled' => []
];
foreach ($orders as $order) {
    $s = $order['order_status'] ?? 'pending';
    $orders_by_status[$s][] = $order;
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Orders Received - LechGO</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.8/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="/LechGo_Final/public/styles.css">
    <style>
        .status-section { margin-bottom: 30px; }
        .status-title { font-size:16px;font-weight:600;color:#2c3e50;padding:12px 15px;background:#f5f5f5;border-left:4px solid #3498db;border-radius:5px;margin-bottom:15px; }
        .status-title.pending { border-left-color:#f39c12; }
        .status-title.confirmed { border-left-color:#3498db; }
        .status-title.processing { border-left-color:#2ecc71; }
        .status-title.ready_for_delivery { border-left-color:#9b59b6; }
        .status-title.delivered { border-left-color:#27ae60; }
        .status-title.cancelled { border-left-color:#e74c3c; }
        .order-card { background:white;border:1px solid #e0e0e0;border-radius:8px;padding:15px;margin-bottom:12px;display:flex;justify-content:space-between;align-items:flex-start;box-shadow:0 1px 3px rgba(0,0,0,0.08); }
        .order-id { font-weight:600;color:#2c3e50;margin-bottom:5px; }
        .order-buyer { color:#666;font-size:13px;margin-bottom:3px; }
        .order-date { color:#999;font-size:12px;margin-bottom:10px; }
        .order-details { display:flex;gap:20px;margin-top:8px; }
        .detail-label { color:#999;font-size:11px;text-transform:uppercase; }
        .detail-value { font-weight:600;color:#2c3e50;font-size:13px; }
        .order-amount { font-size:16px;font-weight:700;color:#27ae60;margin-right:15px; }
        .status-badge { display:inline-block;padding:4px 10px;border-radius:16px;font-size:11px;font-weight:600;text-transform:capitalize;margin-bottom:8px; }
        .badge-pending { background:#fff3cd;color:#856404; }
        .badge-confirmed { background:#d1ecf1;color:#0c5460; }
        .badge-processing { background:#cce5ff;color:#004085; }
        .badge-ready_for_delivery { background:#e2d9f3;color:#692e74; }
        .badge-delivered { background:#d4edda;color:#155724; }
        .badge-cancelled { background:#f8d7da;color:#721c24; }
        .empty-state { text-align:center;padding:40px 20px;color:#999;background:white;border-radius:8px; }
        .container { max-width:1000px;margin:0 auto;padding:20px; }
    </style>
</head>
<body>
<div class="dashboard-layout">
    <?php include __DIR__ . '/../layouts/sidebar.php'; ?>
    <main class="dashboard-main">
    <div class="container">

        <?php if (isset($_SESSION['success'])): ?>
            <div class="alert alert-success show">✓ <?php echo $_SESSION['success']; unset($_SESSION['success']); ?></div>
        <?php endif; ?>
        <?php if (isset($_SESSION['error'])): ?>
            <div class="alert alert-error show">✗ <?php echo $_SESSION['error']; unset($_SESSION['error']); ?></div>
        <?php endif; ?>

        <div style="margin-bottom:var(--spacing-lg);">
            <h1>Orders Received</h1>
            <p style="color:#666;margin-top:5px;"><?php echo htmlspecialchars($distributor['business_name']); ?> — manage orders from buyers</p>
        </div>

        <?php if (empty($orders)): ?>
            <div class="empty-state">
                <div style="font-size:2.5rem;margin-bottom:15px;"></div>
                <p>No orders received yet.</p>
                <p style="color:#95a5a6;font-size:14px;">Buyers will place orders here when they purchase your feeds.</p>
            </div>
        <?php else: ?>
            <?php foreach ($orders_by_status as $status => $status_orders): ?>
                <?php if (!empty($status_orders)): ?>
                <div class="status-section">
                    <div class="status-title <?php echo $status; ?>">
                        <?php
                            $icons = ['pending'=>'','confirmed'=>'','processing'=>'','ready_for_delivery'=>'','delivered'=>'','cancelled'=>''];
                            echo ($icons[$status] ?? '•') . ' ' . ucfirst(str_replace('_', ' ', $status)) . ' (' . count($status_orders) . ')';
                        ?>
                    </div>
                    <?php foreach ($status_orders as $order): ?>
                    <div class="order-card">
                        <div>
                            <div class="order-id">Order #<?php echo $order['id']; ?><?php if ($order['order_number']): ?> — <?php echo htmlspecialchars($order['order_number']); ?><?php endif; ?></div>
                            <div class="order-buyer">
                                <strong><?php echo htmlspecialchars($order['buyer_name'] ?? $order['buyer_user_name'] ?? 'Unknown Buyer'); ?></strong>
                                <?php if ($order['buyer_email']): ?> · <?php echo htmlspecialchars($order['buyer_email']); ?><?php endif; ?>
                            </div>
                            <div class="order-date">Received: <?php echo date('M d, Y \a\t H:i', strtotime($order['created_at'])); ?></div>
                            <div class="order-details">
                                <div>
                                    <div class="detail-label">Items</div>
                                    <div class="detail-value"><?php echo $order['item_count']; ?> item(s)</div>
                                </div>
                                <div>
                                    <div class="detail-label">Payment</div>
                                    <div class="detail-value"><?php echo ucfirst($order['payment_status']); ?></div>
                                </div>
                                <?php if ($order['delivery_address']): ?>
                                <div>
                                    <div class="detail-label">Delivery Address</div>
                                    <div class="detail-value"><?php echo htmlspecialchars($order['delivery_address']); ?></div>
                                </div>
                                <?php endif; ?>
                            </div>
                        </div>
                        <div style="text-align:right;">
                            <div class="order-amount">₱<?php echo number_format($order['total_amount'], 2); ?></div>
                            <div class="status-badge badge-<?php echo $status; ?>"><?php echo ucfirst(str_replace('_', ' ', $status)); ?></div>
                            <div style="margin-top:10px;display:flex;gap:8px;">
                                <?php if ($status === 'pending'): ?>
                                    <form method="POST" action="/LechGo_Final/public/feed-distributor/accept-order">
                                        <input type="hidden" name="order_id" value="<?php echo $order['id']; ?>">
                                        <button type="submit" class="btn btn-success" style="padding:8px 12px;font-size:13px;">✓ Accept</button>
                                    </form>
                                <?php endif; ?>
                                <a href="/LechGo_Final/public/feed-distributor/order-details/<?php echo $order['id']; ?>" class="btn btn-primary" style="padding:8px 12px;font-size:13px;text-decoration:none;">View Details</a>
                            </div>
                        </div>
                    </div>
                    <?php endforeach; ?>
                </div>
                <?php endif; ?>
            <?php endforeach; ?>
        <?php endif; ?>

    </div>
    </main>

    <!-- Logout Modal -->
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

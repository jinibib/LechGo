<?php
$currentPage = 'feeds-market';

$sessionMiddleware = new Session();
$user = $sessionMiddleware->getUser();

if (!$sessionMiddleware->isAuthenticated() || $user['role'] !== 'supplier') {
    header('Location: /LechGo_Final/public/login');
    exit;
}

// Get all orders this supplier placed with distributors
$query = "SELECT fdo.id, fdo.order_number, fdo.order_status, fdo.payment_status,
                 fdo.total_amount, fdo.delivery_address, fdo.created_at,
                 fdo.imported_to_inventory,
                 fd.business_name AS distributor_name,
                 COUNT(fdoi.id) AS item_count
          FROM feed_distributor_orders fdo
          JOIN feed_distributors fd ON fdo.distributor_id = fd.id
          LEFT JOIN feed_distributor_order_items fdoi ON fdo.id = fdoi.order_id
          WHERE fdo.buyer_user_id = ?
          GROUP BY fdo.id
          ORDER BY fdo.created_at DESC";

$stmt = $GLOBALS['conn']->prepare($query);
$orders = [];
if ($stmt) {
    $stmt->bind_param('i', $user['id']);
    $stmt->execute();
    $orders = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
    $stmt->close();
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>My Feed Orders - LechGO</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.8/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="/LechGo_Final/public/styles.css">
    <style>
        .order-card { background:white; border:1px solid #e0e0e0; border-radius:8px; padding:16px; margin-bottom:12px; display:flex; justify-content:space-between; align-items:flex-start; box-shadow:0 1px 3px rgba(0,0,0,0.07); }
        .order-id { font-weight:700; color:#2c3e50; margin-bottom:4px; }
        .order-dist { color:#666; font-size:13px; margin-bottom:3px; }
        .order-date { color:#999; font-size:12px; margin-bottom:8px; }
        .detail-label { font-size:11px; color:#aaa; text-transform:uppercase; }
        .detail-value { font-weight:600; color:#2c3e50; font-size:13px; }
        .order-amount { font-size:16px; font-weight:700; color:#27ae60; margin-right:12px; }
        .status-badge { display:inline-block; padding:4px 10px; border-radius:16px; font-size:11px; font-weight:600; text-transform:capitalize; margin-bottom:8px; }
        .badge-pending { background:#fff3cd; color:#856404; }
        .badge-confirmed { background:#d1ecf1; color:#0c5460; }
        .badge-processing { background:#cce5ff; color:#004085; }
        .badge-delivered { background:#d4edda; color:#155724; }
        .badge-cancelled { background:#f8d7da; color:#721c24; }
        .empty-state { text-align:center; padding:50px 20px; background:white; border-radius:8px; color:#aaa; }
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

        <div style="margin-bottom:20px;display:flex;align-items:center;justify-content:space-between;">
            <div>
                <h1 style="margin:0;">My Feed Purchases</h1>
                <p style="color:#666;margin:4px 0 0 0;font-size:13px;">Orders placed with Feed Distributors</p>
            </div>
            <a href="/LechGo_Final/public/supplier/feeds-market" class="btn btn-primary" style="text-decoration:none;padding:8px 18px;font-size:13px;">Browse More Feeds</a>
        </div>

        <?php if (empty($orders)): ?>
            <div class="empty-state">
                <p>No orders yet.</p>
                <a href="/LechGo_Final/public/supplier/feeds-market" style="color:#e74c3c;">Browse the Feeds Market</a>
            </div>
        <?php else: ?>
            <?php foreach ($orders as $o): ?>
            <div class="order-card">
                <div>
                    <div class="order-id">Order #<?php echo $o['id']; ?><?php if ($o['order_number']): ?> &mdash; <?php echo htmlspecialchars($o['order_number']); ?><?php endif; ?></div>
                    <div class="order-dist">From: <strong><?php echo htmlspecialchars($o['distributor_name']); ?></strong></div>
                    <div class="order-date">Placed: <?php echo date('M d, Y \a\t H:i', strtotime($o['created_at'])); ?></div>
                    <div style="display:flex;gap:20px;">
                        <div><div class="detail-label">Items</div><div class="detail-value"><?php echo $o['item_count']; ?></div></div>
                        <div><div class="detail-label">Payment</div><div class="detail-value"><?php echo ucfirst($o['payment_status']); ?></div></div>
                        <?php if ($o['delivery_address']): ?>
                        <div><div class="detail-label">Deliver to</div><div class="detail-value" style="max-width:200px;white-space:nowrap;overflow:hidden;text-overflow:ellipsis;"><?php echo htmlspecialchars($o['delivery_address']); ?></div></div>
                        <?php endif; ?>
                    </div>
                </div>
                <div style="text-align:right;">
                    <div class="order-amount">₱<?php echo number_format($o['total_amount'], 2); ?></div>
                    <div class="status-badge badge-<?php echo $o['order_status']; ?>"><?php echo ucfirst(str_replace('_', ' ', $o['order_status'])); ?></div>
                    <?php if ($o['order_status'] === 'delivered' && !($o['imported_to_inventory'] ?? false)): ?>
                    <div style="margin-top:8px;">
                        <button onclick="importOrder(<?php echo $o['id']; ?>, this)"
                                class="btn btn-primary"
                                style="padding:6px 14px;font-size:12px;width:100%;">
                            Import to Inventory
                        </button>
                    </div>
                    <?php elseif ($o['order_status'] === 'delivered' && ($o['imported_to_inventory'] ?? false)): ?>
                    <div style="margin-top:8px;font-size:12px;color:#27ae60;font-weight:600;">✓ Imported</div>
                    <?php endif; ?>
                </div>
            </div>
            <?php endforeach; ?>
        <?php endif; ?>

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
<script>
function importOrder(orderId, btn) {
    if (!confirm('Import all items from this order into your Product Inventory?')) return;
    btn.disabled = true;
    btn.textContent = 'Importing...';

    fetch('/LechGo_Final/public/supplier/import-fd-order', {
        method: 'POST',
        headers: {'Content-Type': 'application/x-www-form-urlencoded'},
        body: 'order_id=' + orderId
    })
    .then(r => r.json())
    .then(data => {
        if (data.success) {
            btn.closest('div').innerHTML = '<div style="font-size:12px;color:#27ae60;font-weight:600;">✓ Imported (' + data.count + ' item' + (data.count !== 1 ? 's' : '') + ')</div>';
        } else {
            alert('Error: ' + data.message);
            btn.disabled = false;
            btn.textContent = 'Import to Inventory';
        }
    })
    .catch(() => {
        alert('Request failed. Please try again.');
        btn.disabled = false;
        btn.textContent = 'Import to Inventory';
    });
}
</script>
</html>

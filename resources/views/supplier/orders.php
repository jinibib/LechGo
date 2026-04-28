<?php
/**
 * Supplier Dashboard - Received Orders
 * View all orders received from livestock owners
 */

$user = $_SESSION['user'] ?? null;
if (!$user || $user['role'] !== 'supplier') {
    header('Location: ' . '/LechGo_Final/public/login');
    exit;
}

// Get supplier
$supplier = new FeedSupplier($GLOBALS['conn']);
if (!$supplier->findByUserId($user['id'])) {
    $_SESSION['error'] = 'Supplier profile not found';
    header('Location: ' . '/LechGo_Final/public/home');
    exit;
}

// Get all orders received by this supplier
$query = "SELECT lfo.id, lfo.created_at, lfo.total_amount, lfo.order_status, lfo.payment_status, lfo.delivery_status,
                 lo.farm_name AS buyer_name, lo.location, u.name AS owner_name, u.email,
                 COUNT(lfoi.id) AS item_count
          FROM livestock_feed_orders lfo
          LEFT JOIN livestock_owners lo ON lfo.livestock_owner_id = lo.id
          LEFT JOIN users u ON lo.user_id = u.id
          LEFT JOIN livestock_feed_order_items lfoi ON lfo.id = lfoi.feed_order_id
          WHERE lfo.supplier_id = ?
          GROUP BY lfo.id
          ORDER BY lfo.created_at DESC";

$stmt = $GLOBALS['conn']->prepare($query);
if (!$stmt) {
    $_SESSION['error'] = 'Database error: ' . $GLOBALS['conn']->error;
    $orders = [];
} else {
    $stmt->bind_param('i', $supplier->id);
    $stmt->execute();
    $result = $stmt->get_result();
    $orders = $result->fetch_all(MYSQLI_ASSOC);
    $stmt->close();
}

// Group orders by status
$orders_by_status = [
    'pending' => [],
    'confirmed' => [],
    'processing' => [],
    'ready_for_delivery' => [],
    'delivered' => [],
    'cancelled' => []
];

foreach ($orders as $order) {
    $status = $order['order_status'] ?? 'pending';
    if (!isset($orders_by_status[$status])) {
        $orders_by_status[$status] = [];
    }
    $orders_by_status[$status][] = $order;
}

?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Received Orders - LechGO</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.8/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="/LechGo_Final/public/styles.css">
    <style>
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
        .status-title.processing { border-left-color: #2ecc71; }
        .status-title.ready_for_delivery { border-left-color: #9b59b6; }
        .status-title.delivered { border-left-color: #27ae60; }
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

        .order-buyer {
            color: #666;
            font-size: 13px;
            margin-bottom: 3px;
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

        .status-badge {
            display: inline-block;
            padding: 4px 10px;
            border-radius: 16px;
            font-size: 11px;
            font-weight: 600;
            text-transform: capitalize;
            margin-bottom: 8px;
        }

        .badge-pending { background: #fff3cd; color: #856404; }
        .badge-confirmed { background: #d1ecf1; color: #0c5460; }
        .badge-processing { background: #cce5ff; color: #004085; }
        .badge-ready_for_delivery { background: #e2d9f3; color: #692e74; }
        .badge-delivered { background: #d4edda; color: #155724; }
        .badge-cancelled { background: #f8d7da; color: #721c24; }

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

        .btn-success {
            background-color: #27ae60;
            color: white;
        }

        .btn-success:hover {
            background-color: #229954;
        }

        .btn-secondary {
            background-color: #95a5a6;
            color: white;
        }

        .btn-secondary:hover {
            background-color: #7f8c8d;
        }

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
    </style>
</head>
<body>
    <div class="dashboard-layout">
        <?php include __DIR__ . '/../layouts/sidebar.php'; ?>
        
        <main class="dashboard-main">
        <div class="container">

            <!-- Display Messages -->
            <?php if (isset($_SESSION['success'])): ?>
                <div class="alert alert-success show">
                    ✓ <?php echo $_SESSION['success']; unset($_SESSION['success']); ?>
                </div>
            <?php endif; ?>

            <?php if (isset($_SESSION['error'])): ?>
                <div class="alert alert-error show">
                    ✗ <?php echo $_SESSION['error']; unset($_SESSION['error']); ?>
                </div>
            <?php endif; ?>

            <div style="margin-bottom: var(--spacing-lg);">
                <h1> Received Orders</h1>
                <p style="color: #666; margin-top: 5px;">Manage orders from livestock owners</p>
            </div>

        <?php if (empty($orders)): ?>
            <div class="empty-state">
                <div class="empty-state-icon"></div>
                <p>No orders received yet.</p>
                <p style="color: #95a5a6; font-size: 14px;">Livestock owners will place orders here when they're ready to purchase feeds.</p>
            </div>
        <?php else: ?>
            <?php foreach ($orders_by_status as $status => $status_orders): ?>
                <?php if (!empty($status_orders)): ?>
                    <div class="status-section">
                        <div class="status-title <?php echo $status; ?>">
                            <?php 
                                $status_icons = [
                                    'pending' => '⏳',
                                    'confirmed' => '✓',
                                    'processing' => 'Processing',
                                    'ready_for_delivery' => 'Ready',
                                    'delivered' => 'Delivered',
                                    'cancelled' => 'Cancelled'
                                ];
                                echo ($status_icons[$status] ?? '•') . ' ' . ucfirst(str_replace('_', ' ', $status)) . ' (' . count($status_orders) . ')';
                            ?>
                        </div>

                        <?php foreach ($status_orders as $order): ?>
                            <div class="order-card">
                                <div class="order-info">
                                    <div class="order-id">Order #<?php echo $order['id']; ?></div>
                                    <div class="order-buyer">
                                        <strong><?php echo htmlspecialchars($order['buyer_name'] ?? 'Unknown Buyer'); ?></strong>
                                        <?php if ($order['location']): ?>
                                            • <?php echo htmlspecialchars($order['location']); ?>
                                        <?php endif; ?>
                                    </div>
                                    <div class="order-date">
                                        Received: <?php echo date('M d, Y \a\t H:i', strtotime($order['created_at'])); ?>
                                    </div>
                                    <div class="order-details">
                                        <div class="detail">
                                            <div class="detail-label">Items</div>
                                            <div class="detail-value"><?php echo $order['item_count']; ?> item(s)</div>
                                        </div>
                                        <div class="detail">
                                            <div class="detail-label">Payment Status</div>
                                            <div class="detail-value"><?php echo ucfirst($order['payment_status']); ?></div>
                                        </div>
                                    </div>
                                </div>

                                <div style="text-align: right;">
                                    <div class="order-amount">₱<?php echo number_format($order['total_amount'], 2); ?></div>
                                    <div class="status-badge badge-<?php echo $status; ?>">
                                        <?php echo ucfirst(str_replace('_', ' ', $status)); ?>
                                    </div>
                                    <div style="margin-top: 10px; display: flex; gap: 8px;">
                                        <a href="/LechGo_Final/public/supplier/order-details/<?php echo $order['id']; ?>" class="btn btn-primary" style="flex: 1; padding: 8px 12px; font-size: 13px;">View Details</a>
                                        <?php if ($status === 'pending'): ?>
                                            <form method="POST" action="/LechGo_Final/public/supplier/accept-order" style="flex: 1;">
                                                <input type="hidden" name="order_id" value="<?php echo $order['id']; ?>">
                                                <button type="submit" class="btn btn-success" style="width: 100%; padding: 8px 12px; font-size: 13px;">✓ Accept</button>
                                            </form>
                                        <?php elseif (in_array($status, ['confirmed', 'processing', 'ready_for_delivery', 'delivered'])): ?>
                                            <a href="/LechGo_Final/public/supplier/receipt/<?php echo $order['id']; ?>" class="btn btn-secondary" style="flex: 1; padding: 8px 12px; font-size: 13px; text-decoration: none;" target="_blank">📄 Receipt</a>
                                        <?php endif; ?>
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
        </div>
    </main>

    </div>

    <script src="/LechGo_Final/public/script.js"></script>
</body>
</html>
    
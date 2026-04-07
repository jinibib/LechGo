<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);

$user = $_SESSION['user'] ?? null;
if (!$user) {
    header('Location: /LechGo_Final/public/login');
    exit;
}

// Get livestock owner ID
$query = "SELECT id FROM livestock_owners WHERE user_id = ?";
$stmt = $GLOBALS['conn']->prepare($query);
if (!$stmt) {
    $_SESSION['error'] = 'Database error: ' . $GLOBALS['conn']->error;
    header('Location: /LechGo_Final/public/dashboard');
    exit;
}
$stmt->bind_param('i', $user['id']);
$stmt->execute();
$result = $stmt->get_result();
$owner = $result->fetch_assoc();
$stmt->close();

if (!$owner) {
    $_SESSION['error'] = 'Livestock owner profile not found';
    header('Location: /LechGo_Final/public/dashboard');
    exit;
}

// Fetch orders
$query = "SELECT lfo.id, lfo.order_number, lfo.created_at, lfo.total_amount, lfo.order_status, 
                 lfo.payment_status, lfo.delivery_status,
                 u.name AS supplier_name, 
                 COUNT(lfoi.id) AS item_count
          FROM livestock_feed_orders lfo
          LEFT JOIN suppliers s ON lfo.supplier_id = s.id
          LEFT JOIN users u ON s.user_id = u.id
          LEFT JOIN livestock_feed_order_items lfoi ON lfo.id = lfoi.feed_order_id
          WHERE lfo.livestock_owner_id = ?
          GROUP BY lfo.id
          ORDER BY lfo.created_at DESC";

$stmt = $GLOBALS['conn']->prepare($query);
if (!$stmt) {
    $_SESSION['error'] = 'Database error: ' . $GLOBALS['conn']->error;
    header('Location: /LechGo_Final/public/dashboard');
    exit;
}
$stmt->bind_param('i', $owner['id']);
$stmt->execute();
$result = $stmt->get_result();
$orders = $result->fetch_all(MYSQLI_ASSOC) ?? [];
$stmt->close();

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>My Orders - LechGO</title>
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

        .order-supplier {
            color: #666;
            font-size: 13px;
            margin-bottom: 8px;
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

        .status-badges {
            display: flex;
            gap: 8px;
            margin-bottom: 10px;
            flex-wrap: wrap;
        }

        .status-badge {
            display: inline-block;
            padding: 4px 10px;
            border-radius: 16px;
            font-size: 11px;
            font-weight: 600;
            text-transform: capitalize;
        }

        .badge-pending { background: #fff3cd; color: #856404; }
        .badge-confirmed { background: #d1ecf1; color: #0c5460; }
        .badge-processing { background: #cce5ff; color: #004085; }
        .badge-ready_for_delivery { background: #e2d9f3; color: #692e74; }
        .badge-delivered { background: #d4edda; color: #155724; }
        .badge-cancelled { background: #f8d7da; color: #721c24; }

        .badge-unpaid { background: #fff3cd; color: #856404; }
        .badge-paid { background: #d4edda; color: #155724; }
        .badge-failed { background: #f8d7da; color: #721c24; }

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
        <div class="container">
            <!-- Back Button -->
            <a href="/LechGo_Final/public/dashboard" class="back-button">← Back to Dashboard</a>

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

            <div style="margin-bottom: var(--spacing-lg); display: flex; justify-content: space-between; align-items: center;">
                <div>
                    <h1>📦 My Orders</h1>
                    <p style="color: #666; margin-top: 5px;">Track all your livestock feed orders and delivery status</p>
                </div>
                <a href="/LechGo_Final/public/livestock-owner/available-feeds" class="btn btn-primary">+ Order More Feeds</a>
            </div>

        <?php if (empty($orders)): ?>
            <div class="empty-state">
                <div class="empty-state-icon">📭</div>
                <p><strong>No orders yet</strong></p>
                <p style="color: #95a5a6; font-size: 14px;">Start ordering feeds from our suppliers</p>
                <br/>
                <a href="/LechGo_Final/public/livestock-owner/available-feeds" class="btn btn-primary">Browse Available Feeds</a>
            </div>
        <?php else: ?>
            <?php 
                // Group orders by order status
                $grouped_orders = [];
                $status_order = ['pending', 'confirmed', 'processing', 'ready_for_delivery', 'delivered', 'cancelled'];
                
                foreach ($orders as $order) {
                    $status = $order['order_status'] ?? 'pending';
                    if (!isset($grouped_orders[$status])) {
                        $grouped_orders[$status] = [];
                    }
                    $grouped_orders[$status][] = $order;
                }
                
                // Reorder based on status_order
                $sorted_orders = [];
                foreach ($status_order as $status) {
                    if (isset($grouped_orders[$status])) {
                        $sorted_orders[$status] = $grouped_orders[$status];
                    }
                }
            ?>

            <?php foreach ($sorted_orders as $status => $status_orders): ?>
                <div class="status-section">
                    <div class="status-title <?php echo $status; ?>">
                        <?php 
                            $status_icons = [
                                'pending' => '⏳',
                                'confirmed' => '✓',
                                'processing' => '🔄',
                                'ready_for_delivery' => '📦',
                                'delivered' => '✅',
                                'cancelled' => '❌'
                            ];
                            echo ($status_icons[$status] ?? '•') . ' ' . ucfirst(str_replace('_', ' ', $status)) . ' (' . count($status_orders) . ')';
                        ?>
                    </div>

                    <?php foreach ($status_orders as $order): ?>
                        <div class="order-card">
                            <div class="order-info">
                                <div class="order-id">Order #<?php echo htmlspecialchars($order['order_number'] ?? $order['id']); ?></div>
                                <div class="order-supplier">
                                    <strong><?php echo htmlspecialchars($order['supplier_name'] ?? 'Unknown Supplier'); ?></strong>
                                </div>
                                <div class="order-date">
                                    Ordered: <?php echo date('M d, Y \a\t H:i', strtotime($order['created_at'])); ?>
                                </div>
                                <div class="order-details">
                                    <div class="detail">
                                        <div class="detail-label">Items</div>
                                        <div class="detail-value"><?php echo $order['item_count']; ?> item(s)</div>
                                    </div>
                                    <div class="detail">
                                        <div class="detail-label">Payment</div>
                                        <div class="detail-value"><?php echo ucfirst($order['payment_status']); ?></div>
                                    </div>
                                    <div class="detail">
                                        <div class="detail-label">Delivery</div>
                                        <div class="detail-value"><?php echo ucfirst(str_replace('_', ' ', $order['delivery_status'])); ?></div>
                                    </div>
                                </div>
                            </div>

                            <div style="text-align: right;">
                                <div class="order-amount">₱<?php echo number_format($order['total_amount'], 2); ?></div>
                                <div class="status-badges">
                                    <span class="status-badge badge-<?php echo $order['order_status']; ?>">
                                        <?php echo ucfirst(str_replace('_', ' ', $order['order_status'])); ?>
                                    </span>
                                    <span class="status-badge badge-<?php echo $order['payment_status']; ?>">
                                        <?php echo ucfirst($order['payment_status']); ?>
                                    </span>
                                </div>
                                <div style="margin-top: 10px; display: flex; gap: 8px;">
                                    <?php if (in_array($status, ['confirmed', 'processing', 'ready_for_delivery', 'delivered'])): ?>
                                        <a href="/LechGo_Final/public/livestock-owner/receipt/<?php echo $order['id']; ?>" class="btn btn-primary" style="flex: 1; padding: 8px 12px; font-size: 13px; text-decoration: none;" target="_blank">📄 Receipt</a>
                                    <?php endif; ?>
                                </div>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php endforeach; ?>
        <?php endif; ?>
        </div>
    </main>
</body>
</html>
                    

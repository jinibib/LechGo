<?php

/**
 * Feed Inventory Management View
 * Pig Caretaker - Feed Inventory Tracking
 */

$sessionMiddleware = new Session();
$user = $sessionMiddleware->getUser();

// Redirect if not authenticated or not pig_caretaker
if (!$sessionMiddleware->isAuthenticated()) {
    header('Location: /LechGo_Final/public/login');
    exit;
}

if ($user['role'] !== 'pig_caretaker') {
    header('Location: /LechGo_Final/public/dashboard');
    exit;
}

// Load pig caretaker data
global $conn;
$pigCaretaker = new PigCaretaker($conn);
if (!$pigCaretaker->findByUserId($user['id'])) {
    // Show detailed error page instead of redirecting
    ?>
    <!DOCTYPE html>
    <html lang="en">
    <head>
        <meta charset="UTF-8">
        <meta name="viewport" content="width=device-width, initial-scale=1.0">
        <title>Setup Required - LechGO</title>
        <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.8/dist/css/bootstrap.min.css" rel="stylesheet">
        <link rel="stylesheet" href="/LechGo_Final/public/styles.css">
    </head>
    <body>
        <header>
            <div class="header-container">
                <a href="/LechGo_Final/public/" class="no-underline">
                    <div class="logo">
                        <img src="/LechGo_Final/public/images/Logo.png" alt="LechGO Logo" class="logo-img">
                        <div class="logo-text">LechGO</div>
                    </div>
                </a>
            </div>
        </header>
        <main class="setup-error-page">
            <div class="setup-error-container">
                <div class="setup-error-icon">!</div>
                <h1 class="setup-error-title">Profile Setup Required</h1>
                <p class="setup-error-message">
                    Before you can access the feed inventory, you need to complete your farm profile.
                </p>
                <div class="setup-error-box">
                    <p>It looks like your farm profile hasn't been set up yet.</p>
                </div>
                <div class="setup-actions">
                    <a href="/LechGo_Final/public/complete-profile" class="btn btn-primary">Complete Profile</a>
                    <a href="/LechGo_Final/public/dashboard" class="btn btn-secondary">Back to Dashboard</a>
                </div>
                <p class="setup-help-link">
                    <a href="/LechGo_Final/public/debug">Need help? Check debug info</a>
                </p>
            </div>
        </main>
    </body>
    </html>
    <?php
    exit;
}

$feedInventory = $pigCaretaker->getFeedInventory();
$totalFeed = $pigCaretaker->getTotalFeedInStock();

// Check if livestock owner is assigned
if (!$pigCaretaker->livestock_owner_id) {
    // Show error if caretaker is not assigned to any livestock owner
    ?>
    <!DOCTYPE html>
    <html lang="en">
    <head>
        <meta charset="UTF-8">
        <meta name="viewport" content="width=device-width, initial-scale=1.0">
        <title>Assignment Required - LechGO</title>
        <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.8/dist/css/bootstrap.min.css" rel="stylesheet">
        <link rel="stylesheet" href="/LechGo_Final/public/styles.css">
    </head>
    <body>
        <header>
            <div class="header-container">
                <a href="/LechGo_Final/public/" class="no-underline">
                    <div class="logo">
                        <img src="/LechGo_Final/public/images/Logo.png" alt="LechGO Logo" class="logo-img">
                        <div class="logo-text">LechGO</div>
                    </div>
                </a>
            </div>
        </header>
        <main class="setup-error-page">
            <div class="setup-error-container">
                <div class="setup-error-icon">!</div>
                <h1 class="setup-error-title">Awaiting Assignment</h1>
                <p class="setup-error-message">
                    You are not yet assigned to a livestock owner.
                </p>
                <div class="setup-error-box">
                    <p>A livestock owner needs to assign you to their farm before you can manage feed inventory. Please contact your livestock owner or farm administrator.</p>
                </div>
                <div class="setup-actions">
                    <a href="/LechGo_Final/public/dashboard" class="btn btn-secondary">Back to Dashboard</a>
                </div>
            </div>
        </main>
    </body>
    </html>
    <?php
    exit;
}

// Get low stock items
$lowStockItems = [];
$result = $conn->query("SELECT id, feed_type, quantity_kg, status FROM feed_inventory WHERE caretaker_id = " . $pigCaretaker->id . " AND status = 'low_stock' ORDER BY quantity_kg ASC");
if ($result) {
    $lowStockItems = $result->fetch_all(MYSQLI_ASSOC) ?? [];
}

// Get received orders from suppliers (orders confirmed by suppliers)
$receivedOrders = [];
$query = "SELECT lfo.id, lfo.order_number, lfo.total_amount, lfo.created_at,
                 u.name AS supplier_name, lfo.order_status
          FROM livestock_feed_orders lfo
          LEFT JOIN suppliers s ON lfo.supplier_id = s.id
          LEFT JOIN users u ON s.user_id = u.id
          WHERE lfo.livestock_owner_id = ? 
          AND lfo.order_status IN ('pending', 'confirmed', 'processing', 'ready_for_delivery')
          ORDER BY lfo.order_status DESC, lfo.created_at DESC
          LIMIT 20";
$stmt = $conn->prepare($query);
if ($stmt) {
    $stmt->bind_param('i', $pigCaretaker->livestock_owner_id);
    $stmt->execute();
    $result = $stmt->get_result();
    $orders_list = $result->fetch_all(MYSQLI_ASSOC) ?? [];
    $stmt->close();
    
    // Get items for each order
    foreach ($orders_list as &$order) {
        $item_query = "SELECT product_name, feed_type, quantity_kg, unit_price, subtotal
                       FROM livestock_feed_order_items WHERE feed_order_id = ?";
        $item_stmt = $conn->prepare($item_query);
        if ($item_stmt) {
            $item_stmt->bind_param('i', $order['id']);
            $item_stmt->execute();
            $item_result = $item_stmt->get_result();
            $order['items'] = $item_result->fetch_all(MYSQLI_ASSOC) ?? [];
            $item_stmt->close();
        }
    }
    $receivedOrders = $orders_list;
}

// Collect feed type suggestions: existing inventory + supplier products
$feedTypeSuggestions = [];

// From existing inventory
$ft_result = $conn->query("SELECT DISTINCT feed_type FROM feed_inventory WHERE caretaker_id = " . $pigCaretaker->id . " ORDER BY feed_type ASC");
if ($ft_result) {
    while ($row = $ft_result->fetch_assoc()) {
        $feedTypeSuggestions[] = $row['feed_type'];
    }
}

// From supplier feed products (via livestock owner's orders)
$sp_query = "SELECT DISTINCT fp.feed_type 
             FROM feed_products fp
             JOIN suppliers s ON fp.supplier_id = s.id
             JOIN livestock_feed_orders lfo ON lfo.supplier_id = s.id
             WHERE lfo.livestock_owner_id = ? AND fp.is_active = 1
             ORDER BY fp.feed_type ASC";
$sp_stmt = $conn->prepare($sp_query);
if ($sp_stmt) {
    $sp_stmt->bind_param('i', $pigCaretaker->livestock_owner_id);
    $sp_stmt->execute();
    $sp_result = $sp_stmt->get_result();
    while ($row = $sp_result->fetch_assoc()) {
        if (!in_array($row['feed_type'], $feedTypeSuggestions)) {
            $feedTypeSuggestions[] = $row['feed_type'];
        }
    }
    $sp_stmt->close();
}
sort($feedTypeSuggestions);

$currentPage = 'feed-inventory';

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Feed Inventory - LechGO</title>
    <link rel="stylesheet" href="/LechGo_Final/public/styles.css">
</head>
<body>
    <div class="dashboard-layout">
        <?php include __DIR__ . '/../layouts/sidebar.php'; ?>
        
        <main class="dashboard-main">
        <div class="inventory-container">

            <!-- Display Flash Messages -->
            <?php if (isset($_SESSION['success'])): ?>
                <div class="alert alert-success show">
                    <?php echo htmlspecialchars($_SESSION['success']); ?>
                </div>
                <?php unset($_SESSION['success']); ?>
            <?php endif; ?>

            <?php if (isset($_SESSION['error'])): ?>
                <div class="alert alert-error show">
                    <?php echo htmlspecialchars($_SESSION['error']); ?>
                </div>
                <?php unset($_SESSION['error']); ?>
            <?php endif; ?>

            <!-- Page Header -->
            <div class="inventory-header">
                <div>
                    <h1>Feed Inventory Management</h1>
                    <p class="text-gray" style="margin: var(--spacing-sm) 0 0 0;">Track and manage your feed supplies</p>
                </div>
            </div>

            <!-- Statistics Cards -->
            <div class="stats-grid">
                <div class="stat-card">
                    <div class="stat-value"><?php echo count($feedInventory); ?></div>
                    <div class="stat-label">Feed Types</div>
                </div>
                <div class="stat-card">
                    <div class="stat-value"><?php echo number_format($totalFeed, 2); ?></div>
                    <div class="stat-label">Total Feed (kg)</div>
                </div>
                <div class="stat-card clickable" onclick="toggleLowStockModal()" style="cursor: pointer;">
                    <div class="stat-value"><?php echo count($lowStockItems); ?></div>
                    <div class="stat-label">Low Stock Items</div>
                </div>
            </div>

            <!-- Import Feed Section -->
            <div class="content-section">
                <div class="section-header">
                    <h2 class="section-title">Add Feed to Inventory</h2>
                    <button class="btn btn-primary" onclick="toggleReceivedOrdersModal()">📦 Import from Orders</button>
                </div>
                <p style="color: #666; font-size: 0.9rem; margin-top: 10px;">
                    Import feed from orders placed by your livestock owner. New orders will appear here automatically.
                </p>
            </div>

            <!-- Feed Inventory Table -->
            <div class="content-section">
                <h2 class="section-title">Current Inventory</h2>

                <?php if (empty($feedInventory)): ?>
                    <div class="empty-state">
                        <div class="empty-state-icon"></div>
                        <p>No feed inventory recorded yet. Click "Add Feed" to get started!</p>
                    </div>
                <?php else: ?>
                    <div class="table-container">
                        <table>
                            <thead>
                                <tr>
                                    <th>Feed Type</th>
                                    <th>Kilograms (kg)</th>
                                    <th>Supplier</th>
                                    <th>Purchase Date</th>
                                    <th>Status</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($feedInventory as $feed): ?>
                                <tr>
                                    <td style="font-weight: 600;"><?php echo htmlspecialchars($feed['feed_type']); ?></td>
                                    <td><?php echo number_format($feed['quantity_kg'], 2); ?></td>
                                    <td><?php echo htmlspecialchars($feed['supplier_name'] ?? 'N/A'); ?></td>
                                    <td><?php echo $feed['purchase_date'] ? htmlspecialchars($feed['purchase_date']) : 'N/A'; ?></td>
                                    <td>
                                        <span class="status-badge status-<?php echo str_replace('_', '-', $feed['status']); ?>">
                                            <?php echo str_replace('_', ' ', ucfirst($feed['status'])); ?>
                                        </span>
                                    </td>
                                </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                <?php endif; ?>
            </div>
        </div>  

            <!-- Low Stock Modal -->
            <div id="lowStockModal" class="modal">
                <div class="modal-content">
                    <div class="modal-header">
                        <h2>Low Stock Alerts</h2>
                        <button class="modal-close" onclick="toggleLowStockModal()">&times;</button>
                    </div>
                    <div class="modal-body">
                        <?php if (empty($lowStockItems)): ?>
                            <div class="empty-state">
                                <div class="empty-state-icon"></div>
                                <p>Great! All feeds have sufficient stock.</p>
                            </div>
                        <?php else: ?>
                            <div class="low-stock-list">
                                <?php foreach ($lowStockItems as $item): ?>
                                    <div class="low-stock-item">
                                        <div class="low-stock-info">
                                            <h3><?php echo htmlspecialchars($item['feed_type']); ?></h3>
                                            <p class="low-stock-qty">Quantity: <strong><?php echo number_format($item['quantity_kg'], 2); ?> kg</strong></p>
                                        </div>
                                        <div class="low-stock-status">
                                            <span class="status-badge status-low-stock">Low Stock</span>
                                        </div>
                                    </div>
                                <?php endforeach; ?>
                            </div>
                            <div style="margin-top: var(--spacing-lg); padding: var(--spacing-md); background: #fff9f0; border-left: 4px solid #FF6B6B; border-radius: 4px;">
                                <p style="margin: 0; color: #FF6B6B; font-weight: 600;">ACTION NEEDED: These feeds need to be replenished soon!</p>
                            </div>
                        <?php endif; ?>
                    </div>
                    <div class="modal-footer">
                        <button class="btn btn-primary" onclick="toggleLowStockModal()">Close</button>
                    </div>
                </div>
            </div>

    <!-- Received Orders Modal -->
    <div id="receivedOrdersModal" class="modal">
        <div class="modal-content" style="width: 80%; max-height: 80vh; overflow-y: auto;">
            <div class="modal-header" style="display: flex; justify-content: space-between; align-items: center; padding: 20px; border-bottom: 2px solid #ecf0f1;">
                <h2 style="margin: 0;">Import from Received Orders</h2>
                <button onclick="toggleReceivedOrdersModal()" style="background: none; border: none; font-size: 24px; cursor: pointer;">&times;</button>
            </div>
            
            <div class="modal-body" style="padding: 20px;">
                <?php if (empty($receivedOrders)): ?>
                    <p style="text-align: center; color: #999; padding: 40px;">No received orders available to import.</p>
                <?php else: ?>
                    <div style="display: grid; gap: 15px;">
                        <?php foreach ($receivedOrders as $order): ?>
                            <div style="background: #f9f9f9; border: 1px solid #e0e0e0; border-radius: 8px; padding: 15px;">
                                <div style="display: flex; justify-content: space-between; align-items: start; margin-bottom: 12px;">
                                    <div style="flex: 1;">
                                        <div style="font-weight: 600; color: #2c3e50; margin-bottom: 5px;">
                                            Order #<?php echo htmlspecialchars($order['order_number'] ?? $order['id']); ?>
                                        </div>
                                        <div style="color: #666; font-size: 14px; margin-bottom: 8px;">
                                            <strong>Supplier:</strong> <?php echo htmlspecialchars($order['supplier_name'] ?? 'Unknown'); ?>
                                        </div>
                                        <div style="color: #666; font-size: 14px;">
                                            <strong>Date:</strong> <?php echo date('M d, Y', strtotime($order['created_at'])); ?>
                                        </div>
                                    </div>
                                </div>
                                
                                <!-- Order Items -->
                                <?php if (!empty($order['items'])): ?>
                                    <div style="background: white; padding: 10px; border-radius: 5px; margin-bottom: 10px;">
                                        <div style="font-weight: 600; font-size: 13px; color: #2c3e50; margin-bottom: 8px;">Items:</div>
                                        <?php $itemNum = 1; ?>
                                        <?php foreach ($order['items'] as $item): ?>
                                            <div style="font-size: 13px; color: #666; padding: 5px 0; border-bottom: 1px solid #ecf0f1;">
                                                <strong><?php echo $itemNum; ?>. <?php echo htmlspecialchars($item['product_name'] ?? $item['feed_type']); ?></strong> - 
                                                <?php echo number_format($item['quantity_kg'], 2); ?> kg @
                                            </div>
                                            <?php $itemNum++; ?>
                                        <?php endforeach; ?>
                                    </div>
                                <?php endif; ?>
                                
                                <button type="button" class="btn btn-primary" style="width: 100%;" onclick="directImportOrder(<?php echo $order['id']; ?>)">Import to Inventory</button>
                            </div>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <style>
        .modal-content {
            background: white;
            border-radius: 8px;
            box-shadow: 0 4px 12px rgba(0, 0, 0, 0.15);
        }

        .modal-header {
            background: #f5f5f5;
            border-radius: 8px 8px 0 0;
        }

        .modal-body {
            background: white;
        }
    </style>

    <script>
        function toggleReceivedOrdersModal() {
            const modal = document.getElementById('receivedOrdersModal');
            modal.classList.toggle('active');
        }

        async function directImportOrder(orderId) {
            try {
                // Show loading
                const button = event.target;
                button.disabled = true;
                button.innerHTML = 'Importing...';

                // Call API to import
                const response = await fetch('/LechGo_Final/public/pig-caretaker/import-from-order', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/x-www-form-urlencoded',
                    },
                    body: new URLSearchParams({
                        'order_id': orderId
                    })
                });

                const data = await response.json();

                if (data.success) {
                    // Close modal
                    toggleReceivedOrdersModal();
                    
                    // Show success message
                    alert(data.message);
                    
                    // Reload page to refresh inventory
                    window.location.reload();
                } else {
                    throw new Error(data.error || 'Import failed');
                }
            } catch (error) {
                console.error('Import error:', error);
                alert('Import failed: ' + error.message);
                event.target.disabled = false;
                event.target.innerHTML = 'Import to Inventory';
            }
        }

        function showAddFromOrder(orderId, supplierName, feedTypes) {
            // Close modal
            toggleReceivedOrdersModal();
            
            // Show the add form  
            const form = document.getElementById('addFeedForm');
            form.classList.add('active');
            
            // Pre-fill the form
            document.getElementById('supplier_name').value = supplierName;
            document.getElementById('purchase_date').valueAsDate = new Date();
            
            // Pre-fill feed type with first item from order
            if (feedTypes && feedTypes.length > 0) {
                document.getElementById('feed_type').value = feedTypes[0];
            }
            
            document.getElementById('quantity_kg').focus();
            
            // Scroll to form
            form.scrollIntoView({ behavior: 'smooth' });
        }

        function toggleLowStockModal() {
            const modal = document.getElementById('lowStockModal');
            modal.classList.toggle('active');
        }

        function deleteFeed(feedId) {
            if (confirm('Are you sure you want to delete this feed record?')) {
                // TODO: Implement delete functionality
                console.log('Delete feed:', feedId);
            }
        }
    </script>

    </main>

    </div>
</body>
</html>

<?php
$user = $_SESSION['user'] ?? null;
if (!$user) {
    header('Location: /login');
    exit;
}

// Get livestock owner ID
$query = "SELECT id FROM livestock_owners WHERE user_id = ?";
$stmt = $GLOBALS['conn']->prepare($query);
if (!$stmt) {
    $_SESSION['error'] = 'Database error: ' . $GLOBALS['conn']->error;
    header('Location: /');
    exit;
}
$stmt->bind_param('i', $user['id']);
$stmt->execute();
$result = $stmt->get_result();
$owner = $result->fetch_assoc();
$stmt->close();

if (!$owner) {
    $_SESSION['error'] = 'Livestock owner profile not found';
    header('Location: /');
    exit;
}

// Get caretaker's feed inventory
$query = "SELECT fi.*, pc.full_name, pc.farm_name, pc.location
          FROM feed_inventory fi
          JOIN pig_caretakers pc ON fi.caretaker_id = pc.id
          WHERE pc.livestock_owner_id = ?
          ORDER BY fi.feed_type, fi.feed_name";

$stmt = $GLOBALS['conn']->prepare($query);
if (!$stmt) {
    $_SESSION['error'] = 'Database error: ' . $GLOBALS['conn']->error;
    header('Location: /');
    exit;
}
$stmt->bind_param('i', $owner['id']);
$stmt->execute();
$result = $stmt->get_result();
$inventory = $result->fetch_all(MYSQLI_ASSOC) ?? [];
$stmt->close();

// Get low stock items for this livestock owner
$lowStockItems = [];
$query = "SELECT fi.id, fi.feed_type, fi.feed_name, fi.quantity_kg, pc.full_name, pc.farm_name
          FROM feed_inventory fi
          JOIN pig_caretakers pc ON fi.caretaker_id = pc.id
          WHERE pc.livestock_owner_id = ? AND fi.status = 'low_stock'
          ORDER BY fi.quantity_kg ASC";
$stmt = $GLOBALS['conn']->prepare($query);
if ($stmt) {
    $stmt->bind_param('i', $owner['id']);
    $stmt->execute();
    $result = $stmt->get_result();
    $lowStockItems = $result->fetch_all(MYSQLI_ASSOC) ?? [];
    $stmt->close();
}

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Caretaker Feed Inventory - LechGO</title>
    <link rel="stylesheet" href="/LechGo_Final/public/styles.css">
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
    <div class="schedule-container">
            <!-- Back Button -->
            <a href="/LechGo_Final/public/dashboard" class="back-button">← Back to Dashboard</a>

<div class="inventory-container">
    <div class="inventory-header">
        <h1>🌾 Caretaker Feed Supply Status</h1>
        <p>View what feeds your caretakers currently have in stock</p>
    </div>

    <!-- Statistics Cards -->
    <div class="stats-grid">
        <div class="stat-card">
            <div class="stat-value"><?php echo count($inventory); ?></div>
            <div class="stat-label">Total Feed Items</div>
        </div>
        <div class="stat-card">
            <div class="stat-value"><?php 
                $total_kg = 0;
                foreach ($inventory as $item) {
                    $total_kg += $item['quantity_kg'];
                }
                echo number_format($total_kg, 1);
            ?></div>
            <div class="stat-label">Total Stock (kg)</div>
        </div>
        <div class="stat-card clickable" onclick="toggleLowStockModal()" style="cursor: pointer;">
            <div class="stat-value"><?php echo count($lowStockItems); ?></div>
            <div class="stat-label">Low Stock Alerts ⚠️</div>
        </div>
    </div>

    <?php if (empty($inventory)): ?>
        <div class="no-data">
            <p>📭 No feed inventory data available</p>
            <p>Your caretakers haven't recorded any feed inventory yet</p>
        </div>
    <?php else: ?>
        <table class="inventory-table">
            <thead>
                <tr>
                    <th>Caretaker</th>
                    <th>Food     name</th>
                    <th>Feed Type</th>
                    <th>Quantity (kg)</th>
                    <th>Unit Price</th>
                    <th>Status</th>
                    <th>Last Updated</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($inventory as $item): ?>
                    <tr>
                        <td>
                            <strong><?php echo htmlspecialchars($item['full_name'] ?? $item['farm_name']); ?></strong><br>
                        </td>
                        <td><?php echo htmlspecialchars($item['feed_name']); ?></td>
                        <td><?php echo htmlspecialchars($item['feed_type']); ?></td>
                        <td><strong><?php echo number_format($item['quantity_kg'], 1); ?> kg</strong></td>
                        <td>₱<?php echo number_format($item['unit_price'], 2); ?></td>
                        <td>
                            <span class="status-badge status-<?php echo strtolower($item['status']); ?>">
                                <?php echo ucfirst($item['status']); ?>
                            </span>
                        </td>
                        <td><?php echo date('M d, Y', strtotime($item['updated_at'])); ?></td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    <?php endif; ?>
</div>

<!-- Low Stock Modal -->
<div id="lowStockModal" class="modal">
    <div class="modal-content">
        <div class="modal-header">
            <h2>⚠️ Low Stock Alerts</h2>
            <button class="modal-close" onclick="toggleLowStockModal()">&times;</button>
        </div>
        <div class="modal-body">
            <?php if (empty($lowStockItems)): ?>
                <div class="empty-state">
                    <div class="empty-state-icon">✓</div>
                    <p>Great! All feeds have sufficient stock.</p>
                </div>
            <?php else: ?>
                <div class="low-stock-list">
                    <?php foreach ($lowStockItems as $item): ?>
                        <div class="low-stock-item">
                            <div class="low-stock-info">
                                <h3><?php echo htmlspecialchars($item['feed_name']); ?> (<?php echo htmlspecialchars($item['feed_type']); ?>)</h3>
                                <p class="low-stock-qty">Caretaker: <strong><?php echo htmlspecialchars($item['full_name'] ?? $item['farm_name']); ?></strong></p>
                                <p class="low-stock-qty">Current Stock: <strong><?php echo number_format($item['quantity_kg'], 2); ?> kg</strong></p>
                            </div>
                            <div class="low-stock-status">
                                <span class="status-badge status-low-stock">⚠️ Low Stock</span>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
                <div style="margin-top: var(--spacing-lg); padding: var(--spacing-md); background: #fff9f0; border-left: 4px solid #FF6B6B; border-radius: 4px;">
                    <p style="margin: 0; color: #FF6B6B; font-weight: 600;">📌 ACTION NEEDED: These feeds need to be replenished soon!</p>
                </div>
            <?php endif; ?>
        </div>
        <div class="modal-footer">
            <button class="btn btn-primary" onclick="toggleLowStockModal()">Close</button>
        </div>
    </div>
</div>

<script>
    function toggleLowStockModal() {
        const modal = document.getElementById('lowStockModal');
        modal.classList.toggle('active');
    }
</script>
    </main>
    <!-- Footer -->
    <footer>
        <div class="footer-bottom" style="border-top: 1px solid rgba(255,255,255,0.2);">
            <p>&copy; 2026 LechGO. All rights reserved.</p>
        </div>
    </footer>
</body>
</html>

<?php

/**
 * Home/Dashboard View
 * Post-login user dashboard with sidebar layout
 */

$sessionMiddleware = new Session();
$user = $sessionMiddleware->getUser();

// Redirect if not authenticated
if (!$sessionMiddleware->isAuthenticated()) {
    header('Location: /LechGo_Final/public/login');
    exit;
}

// Load role-specific data
$roleData = null;
if ($user['role'] === 'lechonero') {
    require_once APP_PATH . '/models/Lechonero.php';
    $lechonero = new Lechonero($GLOBALS['conn']);
    if ($lechonero->findByUserId($user['id'])) {
        $roleData = $lechonero;
    }
} elseif ($user['role'] === 'supplier') {
    require_once APP_PATH . '/models/FeedSupplier.php';
    $supplier = new FeedSupplier($GLOBALS['conn']);
    if ($supplier->findByUserId($user['id'])) {
        $roleData = $supplier;
        
        // Get order statistics
        $query = "SELECT 
                    COUNT(CASE WHEN order_status IN ('confirmed', 'processing', 'ready_for_delivery') THEN 1 END) as active_orders,
                    COUNT(CASE WHEN order_status = 'delivered' THEN 1 END) as completed_orders,
                    COUNT(CASE WHEN order_status = 'pending' THEN 1 END) as pending_orders,
                    COUNT(*) as total_orders
                  FROM livestock_feed_orders 
                  WHERE supplier_id = ?";
        $stmt = $GLOBALS['conn']->prepare($query);
        if ($stmt) {
            $stmt->bind_param('i', $supplier->id);
            $stmt->execute();
            $result = $stmt->get_result();
            $orderStats = $result->fetch_assoc();
            $stmt->close();
        } else {
            $orderStats = ['active_orders' => 0, 'completed_orders' => 0, 'pending_orders' => 0, 'total_orders' => 0];
        }
    }
} elseif ($user['role'] === 'livestock_owner') {
    require_once APP_PATH . '/models/LivestockOwner.php';
    $owner = new LivestockOwner($GLOBALS['conn']);
    if ($owner->findByUserId($user['id'])) {
        $roleData = $owner;
    }
} elseif ($user['role'] === 'pig_caretaker') {
    require_once APP_PATH . '/models/PigCaretaker.php';
    $pigCaretaker = new PigCaretaker($GLOBALS['conn']);
    if ($pigCaretaker->findByUserId($user['id'])) {
        $roleData = $pigCaretaker;
    }
} elseif ($user['role'] === 'feed_distributor') {
    require_once APP_PATH . '/models/FeedDistributor.php';
    $distributor = new FeedDistributor($GLOBALS['conn']);
    if ($distributor->findByUserId($user['id'])) {
        $roleData = $distributor;
        $stmt = $GLOBALS['conn']->prepare(
            "SELECT
               COUNT(*) AS total_products,
               SUM(CASE WHEN is_active = 1 THEN 1 ELSE 0 END) AS active_products,
               SUM(quantity_available_kg) AS total_stock
             FROM feed_distributor_products WHERE distributor_id = ?"
        );
        if ($stmt) {
            $stmt->bind_param('i', $distributor->id);
            $stmt->execute();
            $fdStats = $stmt->get_result()->fetch_assoc();
            $stmt->close();
        }
        $stmt2 = $GLOBALS['conn']->prepare(
            "SELECT COUNT(*) AS pending_orders FROM feed_distributor_orders WHERE distributor_id = ? AND order_status = 'pending'"
        );
        if ($stmt2) {
            $stmt2->bind_param('i', $distributor->id);
            $stmt2->execute();
            $fdOrderStats = $stmt2->get_result()->fetch_assoc();
            $stmt2->close();
        }
    }
}

// Set page title and current page
$pageTitle = 'Dashboard';
$currentPage = 'dashboard';

// Start output buffering for content
ob_start();
?>

<!-- Welcome Section -->
<div class="dashboard-welcome">
    <h2 class="dashboard-welcome-title">Welcome back, <?php echo htmlspecialchars(explode(' ', $user['name'] ?? 'User')[0]); ?>!</h2>
    <p class="dashboard-welcome-subtitle">Here's what's happening with your account today.</p>
</div>

<!-- Dashboard Cards -->
<div class="dashboard-cards">
    <?php if ($user['role'] === 'pig_caretaker' && $roleData): ?>
        <div class="dashboard-card">
            <div class="dashboard-card-header">
                <h3 class="dashboard-card-title">Total Pigs</h3>
            </div>
            <p class="dashboard-card-value"><?php echo $roleData->getTotalPigCount(); ?></p>
            <p class="dashboard-card-label">Active pigs in inventory</p>
        </div>
        <div class="dashboard-card">
            <div class="dashboard-card-header">
                <h3 class="dashboard-card-title">Feed Stock</h3>
            </div>
            <p class="dashboard-card-value"><?php echo number_format($roleData->getTotalFeedInStock(), 1); ?> kg</p>
            <p class="dashboard-card-label">Total feed available</p>
        </div>
        <div class="dashboard-card">
            <div class="dashboard-card-header">
                <h3 class="dashboard-card-title">Cages</h3>
            </div>
            <p class="dashboard-card-value"><?php echo count($roleData->getPigCages()); ?></p>
            <p class="dashboard-card-label">Total pig cages</p>
        </div>
    <?php elseif ($user['role'] === 'supplier' && isset($orderStats)): ?>
        <div class="dashboard-card">
            <div class="dashboard-card-header"><h3 class="dashboard-card-title">Active Orders</h3></div>
            <p class="dashboard-card-value"><?php echo $orderStats['active_orders']; ?></p>
            <p class="dashboard-card-label">Orders in progress</p>
        </div>
        <div class="dashboard-card">
            <div class="dashboard-card-header"><h3 class="dashboard-card-title">Completed</h3></div>
            <p class="dashboard-card-value"><?php echo $orderStats['completed_orders']; ?></p>
            <p class="dashboard-card-label">Total completed orders</p>
        </div>
        <div class="dashboard-card">
            <div class="dashboard-card-header"><h3 class="dashboard-card-title">Pending</h3></div>
            <p class="dashboard-card-value"><?php echo $orderStats['pending_orders']; ?></p>
            <p class="dashboard-card-label">Awaiting action</p>
        </div>
    <?php elseif ($user['role'] === 'feed_distributor' && isset($fdStats)): ?>
        <div class="dashboard-card">
            <div class="dashboard-card-header"><h3 class="dashboard-card-title">My Products</h3></div>
            <p class="dashboard-card-value"><?php echo $fdStats['total_products'] ?? 0; ?></p>
            <p class="dashboard-card-label"><?php echo ($fdStats['active_products'] ?? 0); ?> listed in market</p>
        </div>
        <div class="dashboard-card">
            <div class="dashboard-card-header"><h3 class="dashboard-card-title">Total Stock</h3></div>
            <p class="dashboard-card-value"><?php echo number_format($fdStats['total_stock'] ?? 0, 1); ?> kg</p>
            <p class="dashboard-card-label">Available inventory</p>
        </div>
        <div class="dashboard-card">
            <div class="dashboard-card-header"><h3 class="dashboard-card-title">Pending Orders</h3></div>
            <p class="dashboard-card-value"><?php echo $fdOrderStats['pending_orders'] ?? 0; ?></p>
            <p class="dashboard-card-label">Awaiting your action</p>
        </div>
    <?php else: ?>
        <div class="dashboard-card">
            <div class="dashboard-card-header">
                <h3 class="dashboard-card-title">Active Orders</h3>
            </div>
            <p class="dashboard-card-value">0</p>
            <p class="dashboard-card-label">Orders in progress</p>
        </div>
        <div class="dashboard-card">
            <div class="dashboard-card-header">
                <h3 class="dashboard-card-title">Completed</h3>
            </div>
            <p class="dashboard-card-value">0</p>
            <p class="dashboard-card-label">Total completed orders</p>
        </div>
        <div class="dashboard-card">
            <div class="dashboard-card-header">
                <h3 class="dashboard-card-title">Pending</h3>
            </div>
            <p class="dashboard-card-value">0</p>
            <p class="dashboard-card-label">Awaiting action</p>
        </div>
    <?php endif; ?>
</div>

<!-- Recent Activity -->
<div class="dashboard-section">
    <div class="dashboard-section-header">
        <h3 class="dashboard-section-title">Recent Activity</h3>
        <a href="#" class="dashboard-section-action">View All →</a>
    </div>
    <div class="dashboard-empty-state">
        <div class="dashboard-empty-icon"></div>
        <p class="dashboard-empty-text">No recent activity yet. Start using the platform to see your activity here!</p>
    </div>
</div>

<!-- Account Information -->
<div class="dashboard-section">
    <div class="dashboard-section-header">
        <h3 class="dashboard-section-title">Account Information</h3>
        <a href="#" class="dashboard-section-action">Edit Profile →</a>
    </div>
    <div class="info-grid">
        <div class="info-card">
            <p class="info-label">Full Name</p>
            <p class="info-value"><?php echo htmlspecialchars($user['name'] ?? 'N/A'); ?></p>
        </div>
        <div class="info-card">
            <p class="info-label">Email Address</p>
            <p class="info-value"><?php echo htmlspecialchars($user['email'] ?? 'N/A'); ?></p>
        </div>
        <div class="info-card">
            <p class="info-label">Account Type</p>
            <p class="info-value"><?php echo htmlspecialchars(ucwords(str_replace('_', ' ', $user['role'] ?? 'N/A'))); ?></p>
        </div>
        <?php if ($user['role'] === 'lechonero' && $roleData): ?>
            <div class="info-card">
                <p class="info-label">Business Name</p>
                <p class="info-value"><?php echo htmlspecialchars($roleData->business_name ?? 'N/A'); ?></p>
            </div>
            <div class="info-card">
                <p class="info-label">Specialty</p>
                <p class="info-value"><?php echo htmlspecialchars($roleData->specialty ?? 'N/A'); ?></p>
            </div>
        <?php elseif ($user['role'] === 'pig_caretaker' && $roleData): ?>
            <div class="info-card">
                <p class="info-label">Farm Name</p>
                <p class="info-value"><?php echo htmlspecialchars($roleData->farm_name ?? 'N/A'); ?></p>
            </div>
            <div class="info-card">
                <p class="info-label">Location</p>
                <p class="info-value"><?php echo htmlspecialchars($roleData->location ?? 'N/A'); ?></p>
            </div>
            <div class="info-card">
                <p class="info-label">Contact Number</p>
                <p class="info-value"><?php echo htmlspecialchars($roleData->contact_number ?? 'N/A'); ?></p>
            </div>
        <?php endif; ?>
    </div>
</div>

<?php
// Get the content and include the dashboard layout
$content = ob_get_clean();
include VIEWS_PATH . '/layouts/dashboard-layout.php';
?>
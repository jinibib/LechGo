<?php
/**
 * Reusable Sidebar Component
 * Include this file in any authenticated page to add the sidebar
 */

$sessionMiddleware = new Session();
$user = $sessionMiddleware->getUser();
$currentPage = $currentPage ?? '';
?>

<!-- Sidebar -->
<aside class="dashboard-sidebar" id="dashboardSidebar">
    <!-- Sidebar Header -->
    <div class="dashboard-sidebar-header">
        <img src="/LechGo_Final/public/images/Logo.png" alt="LechGO Logo" class="dashboard-sidebar-logo">
        <h2 class="dashboard-sidebar-title">LechGO</h2>
    </div>

    <!-- User Info -->
    <div class="dashboard-sidebar-user">
        <div class="dashboard-sidebar-avatar"><?php echo strtoupper(substr($user['name'] ?? 'U', 0, 1)); ?></div>
        <div class="dashboard-sidebar-user-info">
            <p class="dashboard-sidebar-user-name"><?php echo htmlspecialchars($user['name'] ?? 'User'); ?></p>
            <p class="dashboard-sidebar-user-role"><?php echo htmlspecialchars(str_replace('_', ' ', $user['role'] ?? 'customer')); ?></p>
        </div>
    </div>

    <!-- Navigation -->
    <nav class="dashboard-sidebar-nav">
        <a href="/LechGo_Final/public/dashboard" class="dashboard-sidebar-nav-item <?php echo $currentPage === 'dashboard' ? 'active' : ''; ?>">
            <span class="dashboard-sidebar-nav-text">Dashboard</span>
        </a>

        <?php if ($user['role'] === 'customer'): ?>
            <a href="/LechGo_Final/public/customer/browse-lechon" class="dashboard-sidebar-nav-item <?php echo $currentPage === 'browse-lechon' ? 'active' : ''; ?>">
                <span class="dashboard-sidebar-nav-text">Browse Lechon</span>
            </a>
            <a href="/LechGo_Final/public/customer/buy-pig" class="dashboard-sidebar-nav-item <?php echo $currentPage === 'buy-pig' ? 'active' : ''; ?>">
                <span class="dashboard-sidebar-nav-text">Buy a Pig</span>
            </a>
            <a href="/LechGo_Final/public/customer/my-orders" class="dashboard-sidebar-nav-item <?php echo $currentPage === 'my-orders' ? 'active' : ''; ?>">
                <span class="dashboard-sidebar-nav-text">My Orders</span>
            </a>
            <a href="/LechGo_Final/public/customer/reviews" class="dashboard-sidebar-nav-item <?php echo $currentPage === 'reviews' ? 'active' : ''; ?>">
                <span class="dashboard-sidebar-nav-text">Reviews</span>
            </a>
            <a href="/LechGo_Final/public/customer/profile" class="dashboard-sidebar-nav-item <?php echo $currentPage === 'profile' ? 'active' : ''; ?>">
                <span class="dashboard-sidebar-nav-text">Profile</span>
            </a>
        <?php elseif ($user['role'] === 'lechonero'): ?>
            <a href="/LechGo_Final/public/lechonero/orders" class="dashboard-sidebar-nav-item <?php echo $currentPage === 'orders' ? 'active' : ''; ?>">
                <span class="dashboard-sidebar-nav-text">Manage Orders</span>
            </a>
            <a href="/LechGo_Final/public/lechonero/schedule" class="dashboard-sidebar-nav-item <?php echo $currentPage === 'schedule' ? 'active' : ''; ?>">
                <span class="dashboard-sidebar-nav-text">Cooking Schedule</span>
            </a>
            <a href="/LechGo_Final/public/lechonero/cooking-status" class="dashboard-sidebar-nav-item <?php echo $currentPage === 'cooking-status' ? 'active' : ''; ?>">
                <span class="dashboard-sidebar-nav-text">Cooking Status</span>
            </a>
            <a href="/LechGo_Final/public/lechonero/reviews" class="dashboard-sidebar-nav-item <?php echo $currentPage === 'reviews' ? 'active' : ''; ?>">
                <span class="dashboard-sidebar-nav-text">My Reviews</span>
            </a>
        <?php elseif ($user['role'] === 'livestock_owner'): ?>
            <a href="/LechGo_Final/public/livestock-owner/caretaker-pig-inventory" class="dashboard-sidebar-nav-item <?php echo $currentPage === 'caretaker-pig-inventory' ? 'active' : ''; ?>">
                <span class="dashboard-sidebar-nav-text">Pig Inventory</span>
            </a>
            <a href="/LechGo_Final/public/livestock-owner/my-pig-market" class="dashboard-sidebar-nav-item <?php echo $currentPage === 'my-pig-market' ? 'active' : ''; ?>">
                <span class="dashboard-sidebar-nav-text">My Pig Market</span>
            </a>
            <a href="/LechGo_Final/public/livestock-owner/available-feeds" class="dashboard-sidebar-nav-item <?php echo $currentPage === 'available-feeds' ? 'active' : ''; ?>">
                <span class="dashboard-sidebar-nav-text">Order Feeds</span>
            </a>
            <a href="/LechGo_Final/public/livestock-owner/my-orders" class="dashboard-sidebar-nav-item <?php echo $currentPage === 'my-orders' ? 'active' : ''; ?>">
                <span class="dashboard-sidebar-nav-text">My Orders</span>
            </a>
            <a href="/LechGo_Final/public/livestock-owner/checkout" class="dashboard-sidebar-nav-item <?php echo $currentPage === 'checkout' ? 'active' : ''; ?>">
                <span class="dashboard-sidebar-nav-text">Checkouts</span>
            </a>
            <a href="/LechGo_Final/public/livestock-owner/caretaker-feed-inventory" class="dashboard-sidebar-nav-item <?php echo $currentPage === 'caretaker-feed-inventory' ? 'active' : ''; ?>">
                <span class="dashboard-sidebar-nav-text">Feed Supply Status</span>
            </a>
            <a href="/LechGo_Final/public/livestock-owner/manage-caretakers" class="dashboard-sidebar-nav-item <?php echo $currentPage === 'manage-caretakers' ? 'active' : ''; ?>">
                <span class="dashboard-sidebar-nav-text">Manage Caretakers</span>
            </a>
        <?php elseif ($user['role'] === 'supplier'): ?>
            <a href="/LechGo_Final/public/supplier/product-inventory" class="dashboard-sidebar-nav-item <?php echo $currentPage === 'product-inventory' ? 'active' : ''; ?>">
                <span class="dashboard-sidebar-nav-text">Product Inventory</span>
            </a>
            <a href="/LechGo_Final/public/supplier/orders" class="dashboard-sidebar-nav-item <?php echo $currentPage === 'orders' ? 'active' : ''; ?>">
                <span class="dashboard-sidebar-nav-text">Orders Received</span>
            </a>
            <a href="/LechGo_Final/public/supplier/feeds-market" class="dashboard-sidebar-nav-item <?php echo $currentPage === 'feeds-market' ? 'active' : ''; ?>">
                <span class="dashboard-sidebar-nav-text">Feeds Market</span>
            </a>
            <a href="/LechGo_Final/public/supplier/fd-orders" class="dashboard-sidebar-nav-item <?php echo $currentPage === 'fd-orders' ? 'active' : ''; ?>">
                <span class="dashboard-sidebar-nav-text">My Feed Purchases</span>
            </a>
            <a href="/LechGo_Final/public/supplier/reports" class="dashboard-sidebar-nav-item <?php echo $currentPage === 'reports' ? 'active' : ''; ?>">
                <span class="dashboard-sidebar-nav-text">Sales Reports</span>
            </a>
        <?php elseif ($user['role'] === 'pig_caretaker'): ?>
            <a href="/LechGo_Final/public/pig-caretaker/pigs" class="dashboard-sidebar-nav-item <?php echo $currentPage === 'pigs' ? 'active' : ''; ?>">
                <span class="dashboard-sidebar-nav-text">Pig Inventory</span>
            </a>
            <a href="/LechGo_Final/public/pig-caretaker/feed-inventory" class="dashboard-sidebar-nav-item <?php echo $currentPage === 'feed-inventory' ? 'active' : ''; ?>">
                <span class="dashboard-sidebar-nav-text">Feed Inventory</span>
            </a>
            <a href="/LechGo_Final/public/pig-caretaker/feeding-schedule" class="dashboard-sidebar-nav-item <?php echo $currentPage === 'feeding-schedule' ? 'active' : ''; ?>">
                <span class="dashboard-sidebar-nav-text">Feeding Schedule</span>
            </a>
            <a href="/LechGo_Final/public/pig-caretaker/farm-profile" class="dashboard-sidebar-nav-item <?php echo $currentPage === 'farm-profile' ? 'active' : ''; ?>">
                <span class="dashboard-sidebar-nav-text">Farm Profile</span>
            </a>
        <?php elseif ($user['role'] === 'logistics'): ?>
            <a href="/LechGo_Final/public/logistics/delivery-status" class="dashboard-sidebar-nav-item <?php echo $currentPage === 'delivery-status' ? 'active' : ''; ?>">
                <span class="dashboard-sidebar-nav-text">Deliveries</span>
            </a>
            <a href="/LechGo_Final/public/logistics/track-orders" class="dashboard-sidebar-nav-item <?php echo $currentPage === 'track-orders' ? 'active' : ''; ?>">
                <span class="dashboard-sidebar-nav-text">Track Orders</span>
            </a>
            <a href="/LechGo_Final/public/logistics/schedule" class="dashboard-sidebar-nav-item <?php echo $currentPage === 'schedule' ? 'active' : ''; ?>">
                <span class="dashboard-sidebar-nav-text">Schedule</span>
            </a>
            <a href="/LechGo_Final/public/logistics/profile" class="dashboard-sidebar-nav-item <?php echo $currentPage === 'profile' ? 'active' : ''; ?>">
                <span class="dashboard-sidebar-nav-text">Driver Profile</span>
            </a>
        <?php elseif ($user['role'] === 'feed_distributor'): ?>
            <a href="/LechGo_Final/public/feed-distributor/product-inventory" class="dashboard-sidebar-nav-item <?php echo $currentPage === 'fd-product-inventory' ? 'active' : ''; ?>">
                <span class="dashboard-sidebar-nav-text">Product Inventory</span>
            </a>
            <a href="/LechGo_Final/public/feed-distributor/market" class="dashboard-sidebar-nav-item <?php echo $currentPage === 'fd-market' ? 'active' : ''; ?>">
                <span class="dashboard-sidebar-nav-text">Feed Market</span>
            </a>
            <a href="/LechGo_Final/public/feed-distributor/orders" class="dashboard-sidebar-nav-item <?php echo $currentPage === 'fd-orders' ? 'active' : ''; ?>">
                <span class="dashboard-sidebar-nav-text">Orders Received</span>
            </a>
        <?php endif; ?>
    </nav>

    <!-- Logout -->
    <div class="dashboard-sidebar-footer">
        <a href="#" class="dashboard-sidebar-logout" id="logoutBtn">Logout</a>
    </div>
</aside>

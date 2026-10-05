    <?php
    /**
     * Reusable Sidebar Component
     * Include this file in any authenticated page to add the sidebar
     */

    $sessionMiddleware = new Session();
    $user = $sessionMiddleware->getUser();
    $currentPage = $currentPage ?? '';

    // Get base URL from global or default to empty string for root deployment
    $baseUrl = $GLOBALS['base_url'] ?? '';
    ?>

    <!-- Sidebar -->
    <aside class="dashboard-sidebar" id="dashboardSidebar">
        <!-- Sidebar Header -->
        <div class="dashboard-sidebar-header">
            <img src="<?php echo $baseUrl; ?>/images/Logo.png" alt="LechGO Logo" class="dashboard-sidebar-logo">
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
            <a href="<?php echo $baseUrl; ?>/dashboard" class="dashboard-sidebar-nav-item <?php echo $currentPage === 'dashboard' ? 'active' : ''; ?>">
                <span class="dashboard-sidebar-nav-text">Dashboard</span>
            </a>

            <?php if ($user['role'] === 'customer'): ?>
                </a>
                 <a href="<?php echo $baseUrl; ?>/customer/budget-planner" class="dashboard-sidebar-nav-item <?php echo $currentPage === 'budget-planner' ? 'active' : ''; ?>">
                    <span class="dashboard-sidebar-nav-text">Budget Planner</span>
                </a>
                <a href="<?php echo $baseUrl; ?>/customer/buy-pig" class="dashboard-sidebar-nav-item <?php echo $currentPage === 'buy-pig' ? 'active' : ''; ?>">
                    <span class="dashboard-sidebar-nav-text">Market Place</span>
                </a>
                <a href="<?php echo $baseUrl; ?>/customer/my-orders" class="dashboard-sidebar-nav-item <?php echo $currentPage === 'my-orders' ? 'active' : ''; ?>">
                    <span class="dashboard-sidebar-nav-text">My Orders</span>
                </a>
                <a href="<?php echo $baseUrl; ?>/customer/reviews" class="dashboard-sidebar-nav-item <?php echo $currentPage === 'reviews' ? 'active' : ''; ?>">
                    <span class="dashboard-sidebar-nav-text">Reviews</span>
                </a>
                <a href="<?php echo $baseUrl; ?>/customer/profile" class="dashboard-sidebar-nav-item <?php echo $currentPage === 'profile' ? 'active' : ''; ?>">
                    <span class="dashboard-sidebar-nav-text">Profile</span>
                </a>
            <?php elseif ($user['role'] === 'lechonero'): ?>
                <a href="<?php echo $baseUrl; ?>/lechonero/orders" class="dashboard-sidebar-nav-item <?php echo $currentPage === 'lechonero-orders' ? 'active' : ''; ?>">
                    <span class="dashboard-sidebar-nav-text">My Orders</span>
                </a>
                <a href="<?php echo $baseUrl; ?>/lechonero/schedule" class="dashboard-sidebar-nav-item <?php echo $currentPage === 'lechonero-schedule' ? 'active' : ''; ?>">
                    <span class="dashboard-sidebar-nav-text">Cooking Schedule</span>
                </a>
                <a href="<?php echo $baseUrl; ?>/lechonero/cooking-status" class="dashboard-sidebar-nav-item <?php echo $currentPage === 'cooking-status' ? 'active' : ''; ?>">
                    <span class="dashboard-sidebar-nav-text">Cooking Status</span>
                </a>
                <a href="<?php echo $baseUrl; ?>/lechonero/profile" class="dashboard-sidebar-nav-item <?php echo $currentPage === 'profile' ? 'active' : ''; ?>">
                    <span class="dashboard-sidebar-nav-text">Profile</span>
                </a>
            <?php elseif ($user['role'] === 'livestock_owner'): ?>
                <a href="<?php echo $baseUrl; ?>/site-administration" class="dashboard-sidebar-nav-item <?php echo $currentPage === 'site-administration' ? 'active' : ''; ?>" style="background-color: #c42121ff; font-weight: 600;">
                    <span class="dashboard-sidebar-nav-text">Site Administration</span>
                </a>
                <div class="sidebar-section-title" style="margin-top: 15px; padding: 10px 15px; color: #ffffffff; font-size: 12px; font-weight: 600; text-transform: uppercase;">
                    Farm Management
                </div>
                <a href="<?php echo $baseUrl; ?>/livestock-owner/caretaker-pig-inventory" class="dashboard-sidebar-nav-item <?php echo $currentPage === 'caretaker-pig-inventory' ? 'active' : ''; ?>">
                    <span class="dashboard-sidebar-nav-text">Pig Inventory</span>
                </a>
                <a href="<?php echo $baseUrl; ?>/livestock-owner/my-pig-market" class="dashboard-sidebar-nav-item <?php echo $currentPage === 'my-pig-market' ? 'active' : ''; ?>">
                    <span class="dashboard-sidebar-nav-text">My Pig Market</span>
                </a>
                <a href="<?php echo $baseUrl; ?>/livestock-owner/lechon-logs" class="dashboard-sidebar-nav-item <?php echo $currentPage === 'lechon-logs' ? 'active' : ''; ?>">
                    <span class="dashboard-sidebar-nav-text">Order Logs</span>
                </a>
                <a href="<?php echo $baseUrl; ?>/livestock-owner/available-feeds" class="dashboard-sidebar-nav-item <?php echo $currentPage === 'available-feeds' ? 'active' : ''; ?>">
                    <span class="dashboard-sidebar-nav-text">My Feeds Order</span>
                </a>
                <a href="<?php echo $baseUrl; ?>/livestock-owner/my-orders" class="dashboard-sidebar-nav-item <?php echo $currentPage === 'my-orders' ? 'active' : ''; ?>">
                    <span class="dashboard-sidebar-nav-text">Feeds Market</span>
                </a>
                <a href="<?php echo $baseUrl; ?>/livestock-owner/transaction-logs" class="dashboard-sidebar-nav-item <?php echo $currentPage === 'transaction-logs' ? 'active' : ''; ?>">
                    <span class="dashboard-sidebar-nav-text">Transaction Logs</span>
                </a>
                <a href="<?php echo $baseUrl; ?>/livestock-owner/delivery-records" class="dashboard-sidebar-nav-item <?php echo $currentPage === 'delivery-records' ? 'active' : ''; ?>">
                    <span class="dashboard-sidebar-nav-text">Delivery Records</span>
                </a>
                <a href="<?php echo $baseUrl; ?>/livestock-owner/inventory-logs" class="dashboard-sidebar-nav-item <?php echo $currentPage === 'inventory-logs' ? 'active' : ''; ?>">
                    <span class="dashboard-sidebar-nav-text">Inventory Logs</span>
                </a>
                <a href="<?php echo $baseUrl; ?>/livestock-owner/checkout" class="dashboard-sidebar-nav-item <?php echo $currentPage === 'checkout' ? 'active' : ''; ?>">
                    <span class="dashboard-sidebar-nav-text">Checkouts</span>
                </a>
                <a href="<?php echo $baseUrl; ?>/livestock-owner/caretaker-feed-inventory" class="dashboard-sidebar-nav-item <?php echo $currentPage === 'caretaker-feed-inventory' ? 'active' : ''; ?>">
                    <span class="dashboard-sidebar-nav-text">Feeds Inventory    </span>
                </a>
                <a href="<?php echo $baseUrl; ?>/livestock-owner/manage-caretakers" class="dashboard-sidebar-nav-item <?php echo $currentPage === 'manage-caretakers' ? 'active' : ''; ?>">
                    <span class="dashboard-sidebar-nav-text">Manage Caretakers</span>
                </a>
                <a href="<?php echo $baseUrl; ?>/market-intelligence" class="dashboard-sidebar-nav-item <?php echo $currentPage === 'market-intelligence' ? 'active' : ''; ?>">
                    <span class="dashboard-sidebar-nav-text">Market Trend</span>
                </a>
            <?php elseif ($user['role'] === 'supplier'): ?>
                <a href="<?php echo $baseUrl; ?>/supplier/product-inventory" class="dashboard-sidebar-nav-item <?php echo $currentPage === 'product-inventory' ? 'active' : ''; ?>">
                    <span class="dashboard-sidebar-nav-text">Product Inventory</span>
                </a>
                <a href="<?php echo $baseUrl; ?>/supplier/orders" class="dashboard-sidebar-nav-item <?php echo $currentPage === 'orders' ? 'active' : ''; ?>">
                    <span class="dashboard-sidebar-nav-text">Orders Received</span>
                </a>
                <a href="<?php echo $baseUrl; ?>/supplier/feeds-market" class="dashboard-sidebar-nav-item <?php echo $currentPage === 'feeds-market' ? 'active' : ''; ?>">
                    <span class="dashboard-sidebar-nav-text">Feeds Market</span>
                </a>
                <a href="<?php echo $baseUrl; ?>/supplier/fd-orders" class="dashboard-sidebar-nav-item <?php echo $currentPage === 'fd-orders' ? 'active' : ''; ?>">
                    <span class="dashboard-sidebar-nav-text">My Feed Purchases</span>
                </a>
                <a href="<?php echo $baseUrl; ?>/supplier/transaction-logs" class="dashboard-sidebar-nav-item <?php echo $currentPage === 'transaction-logs' ? 'active' : ''; ?>">
                    <span class="dashboard-sidebar-nav-text">Transaction Logs</span>
                </a>
                <a href="<?php echo $baseUrl; ?>/supplier/reports" class="dashboard-sidebar-nav-item <?php echo $currentPage === 'reports' ? 'active' : ''; ?>">
                    <span class="dashboard-sidebar-nav-text">Sales Reports</span>
                </a>
            <?php elseif ($user['role'] === 'pig_caretaker'): ?>
                <a href="<?php echo $baseUrl; ?>/pig-caretaker/pigs" class="dashboard-sidebar-nav-item <?php echo $currentPage === 'pigs' ? 'active' : ''; ?>">
                    <span class="dashboard-sidebar-nav-text">Pig Inventory</span>
                </a>
                <a href="<?php echo $baseUrl; ?>/pig-caretaker/feed-inventory" class="dashboard-sidebar-nav-item <?php echo $currentPage === 'feed-inventory' ? 'active' : ''; ?>">
                    <span class="dashboard-sidebar-nav-text">Feed Inventory</span>
                </a>
                <a href="<?php echo $baseUrl; ?>/pig-caretaker/feeding-schedule" class="dashboard-sidebar-nav-item <?php echo $currentPage === 'feeding-schedule' ? 'active' : ''; ?>">
                    <span class="dashboard-sidebar-nav-text">Feeding Schedule</span>
                </a>
                <a href="<?php echo $baseUrl; ?>/pig-caretaker/farm-profile" class="dashboard-sidebar-nav-item <?php echo $currentPage === 'farm-profile' ? 'active' : ''; ?>">
                    <span class="dashboard-sidebar-nav-text">Farm Profile</span>
                </a>
            <?php elseif ($user['role'] === 'logistics'): ?>
                <a href="<?php echo $baseUrl; ?>/logistics/delivery-status" class="dashboard-sidebar-nav-item <?php echo $currentPage === 'delivery-status' ? 'active' : ''; ?>">
                    <span class="dashboard-sidebar-nav-text">Deliveries</span>
                </a>
                <a href="<?php echo $baseUrl; ?>/logistics/track-orders" class="dashboard-sidebar-nav-item <?php echo $currentPage === 'track-orders' ? 'active' : ''; ?>">
                    <span class="dashboard-sidebar-nav-text">Track Orders</span>
                </a>
                <a href="<?php echo $baseUrl; ?>/logistics/schedule" class="dashboard-sidebar-nav-item <?php echo $currentPage === 'schedule' ? 'active' : ''; ?>">
                    <span class="dashboard-sidebar-nav-text">Schedule</span>
                </a>
                <a href="<?php echo $baseUrl; ?>/logistics/profile" class="dashboard-sidebar-nav-item <?php echo $currentPage === 'profile' ? 'active' : ''; ?>">
                    <span class="dashboard-sidebar-nav-text">Driver Profile</span>
                </a>
            <?php elseif ($user['role'] === 'feed_distributor'): ?>
                <a href="<?php echo $baseUrl; ?>/feed-distributor/product-inventory" class="dashboard-sidebar-nav-item <?php echo $currentPage === 'fd-product-inventory' ? 'active' : ''; ?>">
                    <span class="dashboard-sidebar-nav-text">Product Inventory</span>
                </a>
                <a href="<?php echo $baseUrl; ?>/feed-distributor/market" class="dashboard-sidebar-nav-item <?php echo $currentPage === 'fd-market' ? 'active' : ''; ?>">
                    <span class="dashboard-sidebar-nav-text">Feed Market</span>
                </a>
                <a href="<?php echo $baseUrl; ?>/feed-distributor/orders" class="dashboard-sidebar-nav-item <?php echo $currentPage === 'fd-orders' ? 'active' : ''; ?>">
                    <span class="dashboard-sidebar-nav-text">Orders Received</span>
                </a>
            <?php elseif ($user['role'] === 'pig_slaughter'): ?>
                <a href="<?php echo $baseUrl; ?>/pig-slaughter/live-pigs" class="dashboard-sidebar-nav-item <?php echo $currentPage === 'live-pigs' ? 'active' : ''; ?>">
                    <span class="dashboard-sidebar-nav-text">Live Pig for Slaughter</span>
                </a>
                <a href="<?php echo $baseUrl; ?>/pig-slaughter/profile" class="dashboard-sidebar-nav-item <?php echo $currentPage === 'profile' ? 'active' : ''; ?>">
                    <span class="dashboard-sidebar-nav-text">Profile</span>
                </a>
            <?php elseif ($user['role'] === 'admin' || $user['role'] === 'system_admin'): ?>
                <a href="<?php echo $baseUrl; ?>/admin/pending-applications" class="dashboard-sidebar-nav-item <?php echo $currentPage === 'pending-applications' ? 'active' : ''; ?>" style="background-color: #c42121ff; font-weight: 600;">
                    <span class="dashboard-sidebar-nav-text">Pending Applications</span>
                </a>
                <div class="sidebar-section-title" style="margin-top: 15px; padding: 10px 15px; color: #ffffffff; font-size: 12px; font-weight: 600; text-transform: uppercase;">
                    System Management
                </div>
                <a href="<?php echo $baseUrl; ?>/admin/all-users" class="dashboard-sidebar-nav-item <?php echo $currentPage === 'all-users' ? 'active' : ''; ?>">
                    <span class="dashboard-sidebar-nav-text">All Users</span>
                </a>
                <a href="<?php echo $baseUrl; ?>/admin/system-logs" class="dashboard-sidebar-nav-item <?php echo $currentPage === 'system-logos' ? 'active' : ''; ?>">
                    <span class="dashboard-sidebar-nav-text">System Logs</span>
                </a>
            <?php endif; ?>
        </nav>

        <!-- Logout -->
        <div class="dashboard-sidebar-footer">
            <a href="<?php echo $baseUrl; ?>/logout.php" class="dashboard-sidebar-logout" id="logoutBtn">Logout</a>
        </div>
    </aside>

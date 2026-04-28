<?php
/**
 * Dashboard Layout
 * Reusable layout with sidebar for all authenticated pages
 */

$sessionMiddleware = new Session();
$user = $sessionMiddleware->getUser();

// Redirect if not authenticated
if (!$sessionMiddleware->isAuthenticated()) {
    header('Location: /LechGo_Final/public/login');
    exit;
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo $pageTitle ?? 'Dashboard'; ?> - LechGO</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.8/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="/LechGo_Final/public/styles.css">
    <?php if (isset($additionalCSS)): ?>
        <?php echo $additionalCSS; ?>
    <?php endif; ?>
</head>
<body>
    <div class="dashboard-layout">
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
                <a href="/LechGo_Final/public/dashboard" class="dashboard-sidebar-nav-item <?php echo ($currentPage ?? '') === 'dashboard' ? 'active' : ''; ?>">
                    <span class="dashboard-sidebar-nav-text">Dashboard</span>
                </a>

                <?php if ($user['role'] === 'customer'): ?>
                    <a href="/LechGo_Final/public/customer/browse-lechon" class="dashboard-sidebar-nav-item <?php echo ($currentPage ?? '') === 'browse-lechon' ? 'active' : ''; ?>">
                        <span class="dashboard-sidebar-nav-text">Browse Lechon</span>
                    </a>
                    <a href="/LechGo_Final/public/customer/buy-pig" class="dashboard-sidebar-nav-item <?php echo ($currentPage ?? '') === 'buy-pig' ? 'active' : ''; ?>">
                        <span class="dashboard-sidebar-nav-text">Buy a Pig</span>
                    </a>
                    <a href="/LechGo_Final/public/customer/my-orders" class="dashboard-sidebar-nav-item <?php echo ($currentPage ?? '') === 'my-orders' ? 'active' : ''; ?>">
                        <span class="dashboard-sidebar-nav-text">My Orders</span>
                    </a>
                    <a href="/LechGo_Final/public/customer/reviews" class="dashboard-sidebar-nav-item <?php echo ($currentPage ?? '') === 'reviews' ? 'active' : ''; ?>">
                        <span class="dashboard-sidebar-nav-text">Reviews</span>
                    </a>
                    <a href="/LechGo_Final/public/customer/profile" class="dashboard-sidebar-nav-item <?php echo ($currentPage ?? '') === 'profile' ? 'active' : ''; ?>">
                        <span class="dashboard-sidebar-nav-text">Profile</span>
                    </a>
                <?php elseif ($user['role'] === 'lechonero'): ?>
                    <a href="/LechGo_Final/public/lechonero/orders" class="dashboard-sidebar-nav-item <?php echo ($currentPage ?? '') === 'orders' ? 'active' : ''; ?>">
                        <span class="dashboard-sidebar-nav-text">Manage Orders</span>
                    </a>
                    <a href="/LechGo_Final/public/lechonero/schedule" class="dashboard-sidebar-nav-item <?php echo ($currentPage ?? '') === 'schedule' ? 'active' : ''; ?>">
                        <span class="dashboard-sidebar-nav-text">Cooking Schedule</span>
                    </a>
                    <a href="/LechGo_Final/public/lechonero/cooking-status" class="dashboard-sidebar-nav-item <?php echo ($currentPage ?? '') === 'cooking-status' ? 'active' : ''; ?>">
                        <span class="dashboard-sidebar-nav-text">Cooking Status</span>
                    </a>
                    <a href="/LechGo_Final/public/lechonero/reviews" class="dashboard-sidebar-nav-item <?php echo ($currentPage ?? '') === 'reviews' ? 'active' : ''; ?>">
                        <span class="dashboard-sidebar-nav-text">My Reviews</span>
                    </a>
                <?php elseif ($user['role'] === 'livestock_owner'): ?>
                    <a href="/LechGo_Final/public/livestock-owner/caretaker-pig-inventory" class="dashboard-sidebar-nav-item <?php echo ($currentPage ?? '') === 'caretaker-pig-inventory' ? 'active' : ''; ?>">
                        <span class="dashboard-sidebar-nav-text">Pig Inventory</span>
                    </a>
                    <a href="/LechGo_Final/public/livestock-owner/my-pig-market" class="dashboard-sidebar-nav-item <?php echo ($currentPage ?? '') === 'my-pig-market' ? 'active' : ''; ?>">
                        <span class="dashboard-sidebar-nav-text">My Pig Market</span>
                    </a>
                    <a href="/LechGo_Final/public/livestock-owner/available-feeds" class="dashboard-sidebar-nav-item <?php echo ($currentPage ?? '') === 'available-feeds' ? 'active' : ''; ?>">
                        <span class="dashboard-sidebar-nav-text">Order Feeds</span>
                    </a>
                    <a href="/LechGo_Final/public/livestock-owner/my-orders" class="dashboard-sidebar-nav-item <?php echo ($currentPage ?? '') === 'my-orders' ? 'active' : ''; ?>">
                        <span class="dashboard-sidebar-nav-text">My Orders</span>
                    </a>
                    <a href="/LechGo_Final/public/livestock-owner/checkout" class="dashboard-sidebar-nav-item <?php echo ($currentPage ?? '') === 'checkout' ? 'active' : ''; ?>">
                        <span class="dashboard-sidebar-nav-text">Checkouts</span>
                    </a>
                    <a href="/LechGo_Final/public/livestock-owner/caretaker-feed-inventory" class="dashboard-sidebar-nav-item <?php echo ($currentPage ?? '') === 'caretaker-feed-inventory' ? 'active' : ''; ?>">
                        <span class="dashboard-sidebar-nav-text">Feed Supply Status</span>
                    </a>
                    <a href="/LechGo_Final/public/livestock-owner/manage-caretakers" class="dashboard-sidebar-nav-item <?php echo ($currentPage ?? '') === 'manage-caretakers' ? 'active' : ''; ?>">
                        <span class="dashboard-sidebar-nav-text">Manage Caretakers</span>
                    </a>
                <?php elseif ($user['role'] === 'supplier'): ?>
                    <a href="/LechGo_Final/public/supplier/product-inventory" class="dashboard-sidebar-nav-item <?php echo ($currentPage ?? '') === 'product-inventory' ? 'active' : ''; ?>">
                        <span class="dashboard-sidebar-nav-text">Product Inventory</span>
                    </a>
                    <a href="/LechGo_Final/public/supplier/orders" class="dashboard-sidebar-nav-item <?php echo ($currentPage ?? '') === 'orders' ? 'active' : ''; ?>">
                        <span class="dashboard-sidebar-nav-text">Orders Received</span>
                    </a>
                    <a href="/LechGo_Final/public/supplier/feeds-market" class="dashboard-sidebar-nav-item <?php echo ($currentPage ?? '') === 'feeds-market' ? 'active' : ''; ?>">
                        <span class="dashboard-sidebar-nav-text">Feeds Market</span>
                    </a>
                    <a href="/LechGo_Final/public/supplier/fd-orders" class="dashboard-sidebar-nav-item <?php echo ($currentPage ?? '') === 'fd-orders' ? 'active' : ''; ?>">
                        <span class="dashboard-sidebar-nav-text">My Feed Purchases</span>
                    </a>
                    <a href="/LechGo_Final/public/supplier/reports" class="dashboard-sidebar-nav-item <?php echo ($currentPage ?? '') === 'reports' ? 'active' : ''; ?>">
                        <span class="dashboard-sidebar-nav-text">Sales Reports</span>
                    </a>
                <?php elseif ($user['role'] === 'pig_caretaker'): ?>
                    <a href="/LechGo_Final/public/pig-caretaker/pigs" class="dashboard-sidebar-nav-item <?php echo ($currentPage ?? '') === 'pigs' ? 'active' : ''; ?>">
                        <span class="dashboard-sidebar-nav-text">Pig Inventory</span>
                    </a>
                    <a href="/LechGo_Final/public/pig-caretaker/feed-inventory" class="dashboard-sidebar-nav-item <?php echo ($currentPage ?? '') === 'feed-inventory' ? 'active' : ''; ?>">
                        <span class="dashboard-sidebar-nav-text">Feed Inventory</span>
                    </a>
                    <a href="/LechGo_Final/public/pig-caretaker/feeding-schedule" class="dashboard-sidebar-nav-item <?php echo ($currentPage ?? '') === 'feeding-schedule' ? 'active' : ''; ?>">
                        <span class="dashboard-sidebar-nav-text">Feeding Schedule</span>
                    </a>
                    <a href="/LechGo_Final/public/pig-caretaker/farm-profile" class="dashboard-sidebar-nav-item <?php echo ($currentPage ?? '') === 'farm-profile' ? 'active' : ''; ?>">
                        <span class="dashboard-sidebar-nav-text">Farm Profile</span>
                    </a>
                <?php elseif ($user['role'] === 'logistics'): ?>
                    <a href="/LechGo_Final/public/logistics/delivery-status" class="dashboard-sidebar-nav-item <?php echo ($currentPage ?? '') === 'delivery-status' ? 'active' : ''; ?>">
                        <span class="dashboard-sidebar-nav-text">Deliveries</span>
                    </a>
                    <a href="/LechGo_Final/public/logistics/track-orders" class="dashboard-sidebar-nav-item <?php echo ($currentPage ?? '') === 'track-orders' ? 'active' : ''; ?>">
                        <span class="dashboard-sidebar-nav-text">Track Orders</span>
                    </a>
                    <a href="/LechGo_Final/public/logistics/schedule" class="dashboard-sidebar-nav-item <?php echo ($currentPage ?? '') === 'schedule' ? 'active' : ''; ?>">
                        <span class="dashboard-sidebar-nav-text">Schedule</span>
                    </a>
                    <a href="/LechGo_Final/public/logistics/profile" class="dashboard-sidebar-nav-item <?php echo ($currentPage ?? '') === 'profile' ? 'active' : ''; ?>">
                        <span class="dashboard-sidebar-nav-text">Driver Profile</span>
                    </a>
                <?php elseif ($user['role'] === 'feed_distributor'): ?>
                    <a href="/LechGo_Final/public/feed-distributor/product-inventory" class="dashboard-sidebar-nav-item <?php echo ($currentPage ?? '') === 'fd-product-inventory' ? 'active' : ''; ?>">
                        <span class="dashboard-sidebar-nav-text">Product Inventory</span>
                    </a>
                    <a href="/LechGo_Final/public/feed-distributor/market" class="dashboard-sidebar-nav-item <?php echo ($currentPage ?? '') === 'fd-market' ? 'active' : ''; ?>">
                        <span class="dashboard-sidebar-nav-text">Feed Market</span>
                    </a>
                    <a href="/LechGo_Final/public/feed-distributor/orders" class="dashboard-sidebar-nav-item <?php echo ($currentPage ?? '') === 'fd-orders' ? 'active' : ''; ?>">
                        <span class="dashboard-sidebar-nav-text">Orders Received</span>
                    </a>
                <?php endif; ?>
            </nav>

            <!-- Logout -->
            <div class="dashboard-sidebar-footer">
                <a href="#" class="dashboard-sidebar-logout" id="logoutBtn">Logout</a>
            </div>
        </aside>

        <!-- Main Content -->
        <main class="dashboard-main">
            <!-- Top Bar -->
            <div class="dashboard-topbar">
                <button class="dashboard-mobile-toggle" id="sidebarToggle"></button>
                <h1 class="dashboard-topbar-title"><?php echo $pageTitle ?? 'Dashboard'; ?></h1>
                <div class="dashboard-topbar-actions">
                    <span class="dashboard-topbar-date"><?php echo date('l, F j, Y'); ?></span>
                    
                    <!-- Notification Bell -->
                    <div class="notification-wrapper">
                        <button class="notification-bell" id="notificationBell" aria-label="Notifications">
                            <svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                <path d="M18 8A6 6 0 0 0 6 8c0 7-3 9-3 9h18s-3-2-3-9"></path>
                                <path d="M13.73 21a2 2 0 0 1-3.46 0"></path>
                            </svg>
                            <span class="notification-badge" id="notificationBadge" style="display: none;">0</span>
                        </button>
                        
                        <!-- Notification Dropdown -->
                        <div class="notification-dropdown" id="notificationDropdown">
                            <div class="notification-header">
                                <h3>Notifications</h3>
                                <button class="mark-all-read" id="markAllRead">Mark all as read</button>
                            </div>
                            <div class="notification-list" id="notificationList">
                                <div class="notification-loading">Loading...</div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Content -->
            <div class="dashboard-content">
                <!-- Flash Messages -->
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

                <!-- Page Content -->
                <?php echo $content ?? ''; ?>
            </div>
        </main>
    </div>

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

    <script src="/LechGo_Final/public/script.js"></script>
    <script src="/LechGo_Final/public/notifications.js"></script>
    <script>
        // Sidebar toggle for mobile
        document.getElementById('sidebarToggle').addEventListener('click', function() {
            document.getElementById('dashboardSidebar').classList.toggle('active');
        });

        // Close sidebar when clicking outside on mobile
        document.addEventListener('click', function(event) {
            const sidebar = document.getElementById('dashboardSidebar');
            const toggle = document.getElementById('sidebarToggle');
            
            if (window.innerWidth <= 768 && 
                !sidebar.contains(event.target) && 
                !toggle.contains(event.target) && 
                sidebar.classList.contains('active')) {
                sidebar.classList.remove('active');
            }
        });

        // Logout confirmation modal
        const logoutBtn = document.getElementById('logoutBtn');
        const logoutModal = document.getElementById('logoutModal');
        const closeLogoutModal = document.getElementById('closeLogoutModal');
        const cancelLogout = document.getElementById('cancelLogout');
        const confirmLogout = document.getElementById('confirmLogout');

        // Show modal when logout is clicked
        logoutBtn.addEventListener('click', function(e) {
            e.preventDefault();
            logoutModal.classList.add('active');
        });

        // Hide modal functions
        function hideLogoutModal() {
            logoutModal.classList.remove('active');
        }

        // Close modal events
        closeLogoutModal.addEventListener('click', hideLogoutModal);
        cancelLogout.addEventListener('click', hideLogoutModal);

        // Close modal when clicking outside
        logoutModal.addEventListener('click', function(e) {
            if (e.target === logoutModal) {
                hideLogoutModal();
            }
        });

        // Confirm logout
        confirmLogout.addEventListener('click', function() {
            window.location.href = '/LechGo_Final/public/logout';
        });

        // Close modal with Escape key
        document.addEventListener('keydown', function(e) {
            if (e.key === 'Escape' && logoutModal.classList.contains('active')) {
                hideLogoutModal();
            }
        });
    </script>
    <?php if (isset($additionalJS)): ?>
        <?php echo $additionalJS; ?>
    <?php endif; ?>
</body>
</html>

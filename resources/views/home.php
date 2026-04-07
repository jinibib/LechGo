<?php

/**
 * Home/Dashboard View
 * Post-login user dashboard
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
}

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Dashboard - LechGO</title>
    <link rel="stylesheet" href="/LechGo_Final/public/styles.css">
    <style>
        .dashboard-container {
            max-width: 1200px;
            margin: 0 auto;
            padding: var(--spacing-lg);
        }

        .dashboard-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: var(--spacing-xl);
            flex-wrap: wrap;
            gap: var(--spacing-lg);
        }

        .dashboard-header h1 {
            margin-bottom: 0;
        }

        .user-profile {
            display: flex;
            align-items: center;
            gap: var(--spacing-md);
        }

        .user-avatar {
            width: 50px;
            height: 50px;
            border-radius: 50%;
            background-color: var(--primary-red);
            color: var(--white);
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 1.5rem;
            font-weight: bold;
        }

        .quick-actions {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: var(--spacing-lg);
            margin-bottom: var(--spacing-xl);
        }

        .action-card {
            background: linear-gradient(135deg, var(--primary-red) 0%, var(--dark-red) 100%);
            color: var(--white);
            padding: var(--spacing-lg);
            border-radius: var(--radius-lg);
            text-decoration: none;
            transition: transform 0.3s ease, box-shadow 0.3s ease;
            display: flex;
            flex-direction: column;
            gap: var(--spacing-md);
            box-shadow: var(--shadow-md);
        }

        .action-card:hover {
            transform: translateY(-5px);
            box-shadow: var(--shadow-lg);
            color: var(--white);
        }

        .action-icon {
            font-size: 2.5rem;
        }

        .action-title {
            font-size: 1.2rem;
            font-weight: 600;
        }

        .action-text {
            font-size: 0.9rem;
            opacity: 0.9;
        }

        .section-title {
            color: var(--primary-red);
            margin-bottom: var(--spacing-lg);
        }

        .content-section {
            background-color: var(--white);
            padding: var(--spacing-lg);
            border-radius: var(--radius-lg);
            box-shadow: var(--shadow-sm);
            margin-bottom: var(--spacing-xl);
        }

        .empty-state {
            text-align: center;
            padding: var(--spacing-xl);
            color: var(--text-gray);
        }

        .empty-state-icon {
            font-size: 3rem;
            margin-bottom: var(--spacing-md);
        }

        .role-badge {
            display: inline-block;
            background-color: var(--light-red);
            color: var(--primary-red);
            padding: var(--spacing-xs) var(--spacing-md);
            border-radius: var(--radius-md);
            font-size: 0.85rem;
            font-weight: 600;
            text-transform: capitalize;
        }
    </style>
</head>
<body>
    <!-- Header/Navigation -->
    <header>
        <div class="header-container">
            <a href="/LechGo_Final/public/" style="text-decoration: none;">
                <div class="logo">
                    <img src="/LechGo_Final/public/images/Logo.png" alt="LechGO Logo" class="logo-img">
                    <div class="logo-text">LechGO</div>
                </div>
            </a>
            <nav>
                <div class="user-profile">
                    <div class="user-avatar"><?php echo strtoupper(substr($user['name'] ?? 'U', 0, 1)); ?></div>
                    <div>
                        <p style="margin: 0; font-weight: 600; color: var(--dark-gray);"><?php echo htmlspecialchars($user['name'] ?? 'User'); ?></p>
                        <p style="margin: 0; font-size: 0.85rem; color: var(--text-gray);"><?php echo htmlspecialchars($user['email'] ?? ''); ?></p>
                    </div>
                    <a href="/LechGo_Final/public/logout" class="btn btn-secondary" style="margin-left: var(--spacing-md);">Logout</a>
                </div>
            </nav>
        </div>
    </header>

    <main>
        <!-- Display Flash Messages -->
        <?php if (isset($_SESSION['success'])): ?>
            <div style="max-width: 1200px; margin: var(--spacing-lg) auto; padding: 0 var(--spacing-lg);">
                <div class="alert alert-success show">
                    ✓ <?php echo htmlspecialchars($_SESSION['success']); ?>
                </div>
            </div>
            <?php unset($_SESSION['success']); ?>
        <?php endif; ?>

        <div class="dashboard-container">
            <!-- Dashboard Header -->
            <div class="dashboard-header">
                <div>
                    <h1>Welcome, <?php echo htmlspecialchars(explode(' ', $user['name'] ?? 'User')[0]); ?>! 👋</h1>
                    <span class="role-badge"><?php echo htmlspecialchars($user['role'] ?? 'customer'); ?></span>
                </div>
            </div>

            <!-- Quick Action Cards -->
            <h2 class="section-title">Quick Actions</h2>
            <div class="quick-actions">
                <?php if ($user['role'] === 'customer'): ?>
                    <a href="#" class="action-card">
                        <div class="action-icon"></div>
                        <div class="action-title">Browse Lechon</div>
                        <div class="action-text">Discover available orders</div>
                    </a>
                    <a href="#" class="action-card">
                        <div class="action-icon"></div>
                        <div class="action-title">My Orders</div>
                        <div class="action-text">Track active orders</div>
                    </a>
                    <a href="#" class="action-card">
                        <div class="action-icon"></div>
                        <div class="action-title">Reviews</div>
                        <div class="action-text">View ratings & feedback</div>
                    </a>
                    <a href="#" class="action-card">
                        <div class="action-icon"></div>
                        <div class="action-title">Profile</div>
                        <div class="action-text">Manage account settings</div>
                    </a>
                <?php elseif ($user['role'] === 'lechonero'): ?>
                    <a href="#" class="action-card">
                        <div class="action-icon"></div>
                        <div class="action-title">Manage Orders</div>
                        <div class="action-text">View and process orders</div>
                    </a>
                    <a href="#" class="action-card">
                        <div class="action-icon"></div>
                        <div class="action-title">Cooking Schedule</div>
                        <div class="action-text">Manage cooking sessions</div>
                    </a>
                    <a href="#" class="action-card">
                        <div class="action-icon"></div>
                        <div class="action-title">My Reviews</div>
                        <div class="action-text">View customer feedback</div>
                    </a>
                    <a href="#" class="action-card">
                        <div class="action-icon"></div>
                        <div class="action-title">Business Profile</div>
                        <div class="action-text">Update business info</div>
                    </a>
                <?php elseif ($user['role'] === 'livestock_owner'): ?>
                    <a href="/LechGo_Final/public/livestock-owner/available-feeds" class="action-card">
                        <div class="action-icon"></div>
                        <div class="action-title">Order Feeds</div>
                        <div class="action-text">Browse available feeds</div>
                    </a>
                    <a href="/LechGo_Final/public/livestock-owner/checkout" class="action-card">
                        <div class="action-icon"></div>
                        <div class="action-title">Checkouts</div>
                        <div class="action-text">Track your Checkouts</div>
                    </a>

                    <a href="/LechGo_Final/public/livestock-owner/my-orders" class="action-card">
                        <div class="action-icon"></div>
                        <div class="action-title">My Orders</div>
                        <div class="action-text">Track your feed orders</div>
                    </a>
                    <a href="/LechGo_Final/public/livestock-owner/caretaker-feed-inventory" class="action-card">
                        <div class="action-icon"></div>
                        <div class="action-title">Feed Supply Status</div>
                        <div class="action-text">Check caretaker feed inventory</div>
                    </a>
                    <a href="/LechGo_Final/public/livestock-owner/caretaker-pig-inventory" class="action-card">
                        <div class="action-icon"></div>
                        <div class="action-title">Pig Inventory</div>
                        <div class="action-text">View pig status & availability</div>
                    </a>
                <?php elseif ($user['role'] === 'supplier'): ?>
                    <a href="/LechGo_Final/public/supplier/product-inventory" class="action-card">
                        <div class="action-icon"></div>
                        <div class="action-title"> Product Inventory</div>
                        <div class="action-text">Manage your feed products</div>
                    </a>
                    <a href="/LechGo_Final/public/supplier/orders" class="action-card">
                        <div class="action-icon"></div>
                        <div class="action-title">Orders Received</div>
                        <div class="action-text">Manage customer orders</div>
                    </a>
                    <a href="#" class="action-card">
                        <div class="action-icon"></div>
                        <div class="action-title">Sales Reports</div>
                        <div class="action-text">View sales analytics</div>
                    </a>
                <?php elseif ($user['role'] === 'pig_caretaker'): ?>
                    <a href="/LechGo_Final/public/pig-caretaker/pigs" class="action-card">
                        <div class="action-icon"></div>
                        <div class="action-title">Pig Inventory</div>
                        <div class="action-text">Manage pig cages</div>
                    </a>
                    <a href="/LechGo_Final/public/pig-caretaker/feed-inventory" class="action-card">
                        <div class="action-icon"></div>
                        <div class="action-title">Feed Inventory</div>
                        <div class="action-text">Track feed supplies</div>
                    </a>
                    <a href="/LechGo_Final/public/pig-caretaker/feeding-schedule" class="action-card">
                        <div class="action-icon"></div>
                        <div class="action-title">Feeding Schedule</div>
                        <div class="action-text">Record feeding logs</div>
                    </a>
                    <a href="/LechGo_Final/public/pig-caretaker/farm-profile" class="action-card">
                        <div class="action-icon"></div>
                        <div class="action-title">Farm Profile</div>
                        <div class="action-text">Update farm information</div>
                    </a>
                <?php elseif ($user['role'] === 'logistics'): ?>
                    <a href="#" class="action-card">
                        <div class="action-icon"></div>
                        <div class="action-title">Deliveries</div>
                        <div class="action-text">Manage delivery routes</div>
                    </a>
                    <a href="#" class="action-card">
                        <div class="action-icon"></div>
                        <div class="action-title">Track Orders</div>
                        <div class="action-text">Monitor order status</div>
                    </a>
                    <a href="#" class="action-card">
                        <div class="action-icon"></div>
                        <div class="action-title">Schedule</div>
                        <div class="action-text">View delivery schedule</div>
                    </a>
                    <a href="#" class="action-card">
                        <div class="action-icon"></div>
                        <div class="action-title">Driver Profile</div>
                        <div class="action-text">Update driver info</div>
                    </a>
                <?php endif; ?>
            </div>

            <!-- Recent Activity Section -->
            <h2 class="section-title">Recent Activity</h2>
            <div class="content-section">
                <div class="empty-state">
                    <div class="empty-state-icon">📭</div>
                    <p>No recent activity yet. <a href="#">Start ordering</a> to see your activity here!</p>
                </div>
            </div>

            <!-- Pig Caretaker Inventory Section -->
            <?php if ($user['role'] === 'pig_caretaker' && $roleData): ?>
            <h2 class="section-title">Pig Inventory</h2>
            <div class="content-section">
                <div style="overflow-x: auto;">
                    <table style="width: 100%; border-collapse: collapse;">
                        <thead>
                            <tr style="background-color: var(--light-red); border-bottom: 2px solid var(--primary-red);">
                                <th style="padding: var(--spacing-md); text-align: left; color: var(--primary-red); font-weight: 600;">Cage</th>
                                <th style="padding: var(--spacing-md); text-align: left; color: var(--primary-red); font-weight: 600;">Current Pigs</th>
                                <th style="padding: var(--spacing-md); text-align: left; color: var(--primary-red); font-weight: 600;">Max Capacity</th>
                                <th style="padding: var(--spacing-md); text-align: left; color: var(--primary-red); font-weight: 600;">Available Slots</th>
                                <th style="padding: var(--spacing-md); text-align: left; color: var(--primary-red); font-weight: 600;">Status</th>
                                <th style="padding: var(--spacing-md); text-align: left; color: var(--primary-red); font-weight: 600;">Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($roleData->getPigCages() as $cage): ?>
                            <tr style="border-bottom: 1px solid var(--gray);">
                                <td style="padding: var(--spacing-md); font-weight: 600;">Cage <?php echo htmlspecialchars($cage['cage_number']); ?></td>
                                <td style="padding: var(--spacing-md);"><?php echo htmlspecialchars($cage['current_pig_count']); ?></td>
                                <td style="padding: var(--spacing-md);"><?php echo htmlspecialchars($cage['max_capacity']); ?></td>
                                <td style="padding: var(--spacing-md); color: var(--primary-red); font-weight: 600;"><?php echo $cage['max_capacity'] - $cage['current_pig_count']; ?></td>
                                <td style="padding: var(--spacing-md);">
                                    <span style="background-color: var(--light-red); color: var(--primary-red); padding: var(--spacing-xs) var(--spacing-md); border-radius: var(--radius-md); font-size: 0.85rem; font-weight: 600;">
                                        <?php echo ucfirst(htmlspecialchars($cage['status'])); ?>
                                    </span>
                                </td>
                                <td style="padding: var(--spacing-md);">
                                    <a href="#" style="color: var(--primary-red); text-decoration: none; font-weight: 500;">View</a>
                                    <?php if ($cage['current_pig_count'] < 3): ?>
                                    | <a href="#" style="color: var(--primary-red); text-decoration: none; font-weight: 500;">Add Pig</a>
                                    <?php endif; ?>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>

            <h2 class="section-title">Feed Inventory</h2>
            <div class="content-section">
                <?php 
                    $feedInventory = $roleData->getFeedInventory();
                    if (empty($feedInventory)): 
                ?>
                <div class="empty-state">
                    <div class="empty-state-icon">🌾</div>
                    <p>No feed inventory recorded. <a href="#">Add feed supplies</a> to get started!</p>
                </div>
                <?php else: ?>
                <div style="overflow-x: auto;">
                    <table style="width: 100%; border-collapse: collapse;">
                        <thead>
                            <tr style="background-color: var(--light-red); border-bottom: 2px solid var(--primary-red);">
                                <th style="padding: var(--spacing-md); text-align: left; color: var(--primary-red); font-weight: 600;">Feed Type</th>
                                <th style="padding: var(--spacing-md); text-align: left; color: var(--primary-red); font-weight: 600;">Quantity (kg)</th>
                                <th style="padding: var(--spacing-md); text-align: left; color: var(--primary-red); font-weight: 600;">Supplier</th>
                                <th style="padding: var(--spacing-md); text-align: left; color: var(--primary-red); font-weight: 600;">Expiry Date</th>
                                <th style="padding: var(--spacing-md); text-align: left; color: var(--primary-red); font-weight: 600;">Status</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($feedInventory as $feed): ?>
                            <tr style="border-bottom: 1px solid var(--gray);">
                                <td style="padding: var(--spacing-md); font-weight: 600;"><?php echo htmlspecialchars($feed['feed_type']); ?></td>
                                <td style="padding: var(--spacing-md);"><?php echo number_format($feed['quantity_kg'], 2); ?></td>
                                <td style="padding: var(--spacing-md);"><?php echo htmlspecialchars($feed['supplier_name'] ?? 'N/A'); ?></td>
                                <td style="padding: var(--spacing-md);"><?php echo $feed['expiry_date'] ? htmlspecialchars($feed['expiry_date']) : 'N/A'; ?></td>
                                <td style="padding: var(--spacing-md);">
                                    <span style="background-color: <?php echo $feed['status'] === 'low_stock' ? 'var(--pale-red)' : 'var(--light-red)'; ?>; color: var(--primary-red); padding: var(--spacing-xs) var(--spacing-md); border-radius: var(--radius-md); font-size: 0.85rem; font-weight: 600;">
                                        <?php echo str_replace('_', ' ', ucfirst(htmlspecialchars($feed['status']))); ?>
                                    </span>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
                <div style="margin-top: var(--spacing-lg);">
                    <a href="#" class="btn btn-primary">Add Feed Inventory</a>
                </div>
                <?php endif; ?>
            </div>
            <?php endif; ?>

            <!-- Account Info Section -->
            <h2 class="section-title">Account Information</h2>
            <div class="content-section">
                <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(250px, 1fr)); gap: var(--spacing-lg);">
                    <div>
                        <p style="color: var(--text-gray); font-size: 0.9rem; margin-bottom: var(--spacing-xs);">Name</p>
                        <p style="font-weight: 600; font-size: 1.1rem;"><?php echo htmlspecialchars($user['name'] ?? 'N/A'); ?></p>
                    </div>
                    <div>
                        <p style="color: var(--text-gray); font-size: 0.9rem; margin-bottom: var(--spacing-xs);">Email</p>
                        <p style="font-weight: 600; font-size: 1.1rem;"><?php echo htmlspecialchars($user['email'] ?? 'N/A'); ?></p>
                    </div>
                    <div>
                        <p style="color: var(--text-gray); font-size: 0.9rem; margin-bottom: var(--spacing-xs);">Account Type</p>
                        <p style="font-weight: 600; font-size: 1.1rem; text-transform: capitalize;"><?php echo htmlspecialchars($user['role'] ?? 'N/A'); ?></p>
                    </div>
                    <?php if ($user['role'] === 'lechonero' && $roleData): ?>
                        <div>
                            <p style="color: var(--text-gray); font-size: 0.9rem; margin-bottom: var(--spacing-xs);">Business Name</p>
                            <p style="font-weight: 600; font-size: 1.1rem;"><?php echo htmlspecialchars($roleData->business_name ?? 'N/A'); ?></p>
                        </div>
                        <div>
                            <p style="color: var(--text-gray); font-size: 0.9rem; margin-bottom: var(--spacing-xs);">Specialty</p>
                            <p style="font-weight: 600; font-size: 1.1rem;"><?php echo htmlspecialchars($roleData->specialty ?? 'N/A'); ?></p>
                        </div>
                    <?php elseif ($user['role'] === 'supplier' && $roleData): ?>
                        <div>
                            <p style="color: var(--text-gray); font-size: 0.9rem; margin-bottom: var(--spacing-xs);">Farm Name</p>
                            <p style="font-weight: 600; font-size: 1.1rem;"><?php echo htmlspecialchars($roleData->farm_name ?? 'N/A'); ?></p>
                        </div>
                        <div>
                            <p style="color: var(--text-gray); font-size: 0.9rem; margin-bottom: var(--spacing-xs);">Street</p>
                            <p style="font-weight: 600; font-size: 1.1rem;"><?php echo htmlspecialchars($roleData->street ?? 'N/A'); ?></p>
                        </div>
                        <div>
                            <p style="color: var(--text-gray); font-size: 0.9rem; margin-bottom: var(--spacing-xs);">Barangay</p>
                            <p style="font-weight: 600; font-size: 1.1rem;"><?php echo htmlspecialchars($roleData->barangay ?? 'N/A'); ?></p>
                        </div>
                        <div>
                            <p style="color: var(--text-gray); font-size: 0.9rem; margin-bottom: var(--spacing-xs);">Municipality/District</p>
                            <p style="font-weight: 600; font-size: 1.1rem;"><?php echo htmlspecialchars($roleData->municipality ?? 'N/A'); ?></p>
                        </div>
                        <div>
                            <p style="color: var(--text-gray); font-size: 0.9rem; margin-bottom: var(--spacing-xs);">City</p>
                            <p style="font-weight: 600; font-size: 1.1rem;"><?php echo htmlspecialchars($roleData->city ?? 'N/A'); ?></p>
                        </div>
                        <div>
                            <p style="color: var(--text-gray); font-size: 0.9rem; margin-bottom: var(--spacing-xs);">Contact Number</p>
                            <p style="font-weight: 600; font-size: 1.1rem;"><?php echo htmlspecialchars($roleData->contact_number ?? 'N/A'); ?></p>
                        </div>
                    <?php elseif ($user['role'] === 'pig_caretaker' && $roleData): ?>
                        <div>
                            <p style="color: var(--text-gray); font-size: 0.9rem; margin-bottom: var(--spacing-xs);">Farm/Piggery Name</p>
                            <p style="font-weight: 600; font-size: 1.1rem;"><?php echo htmlspecialchars($roleData->farm_name ?? 'N/A'); ?></p>
                        </div>
                        <div>
                            <p style="color: var(--text-gray); font-size: 0.9rem; margin-bottom: var(--spacing-xs);">Location</p>
                            <p style="font-weight: 600; font-size: 1.1rem;"><?php echo htmlspecialchars($roleData->location ?? 'N/A'); ?></p>
                        </div>
                        <div>
                            <p style="color: var(--text-gray); font-size: 0.9rem; margin-bottom: var(--spacing-xs);">Contact Number</p>
                            <p style="font-weight: 600; font-size: 1.1rem;"><?php echo htmlspecialchars($roleData->contact_number ?? 'N/A'); ?></p>
                        </div>
                        <div>
                            <p style="color: var(--text-gray); font-size: 0.9rem; margin-bottom: var(--spacing-xs);">Total Pigs</p>
                            <p style="font-weight: 600; font-size: 1.1rem;"><?php echo $roleData->getTotalPigCount(); ?></p>
                        </div>
                        <div>
                            <p style="color: var(--text-gray); font-size: 0.9rem; margin-bottom: var(--spacing-xs);">Feed in Stock (kg)</p>
                            <p style="font-weight: 600; font-size: 1.1rem;"><?php echo number_format($roleData->getTotalFeedInStock(), 2); ?></p>
                        </div>
                    <?php endif; ?>
                </div>
                <div style="margin-top: var(--spacing-lg); padding-top: var(--spacing-lg); border-top: 1px solid var(--gray);">
                    <a href="#" class="btn btn-secondary">Edit Profile</a>
                    <a href="#" class="btn btn-secondary" style="margin-left: var(--spacing-md);">Change Password</a>
                </div>
            </div>
        </div>
    </main>

    <!-- Footer -->
    <footer>
        <div class="footer-bottom" style="border-top: 1px solid rgba(255,255,255,0.2);">
            <p>&copy; 2026 LechGO. All rights reserved.</p>
        </div>
    </footer>

    <script src="/LechGo_Final/public/script.js"></script>
</body>
</html>

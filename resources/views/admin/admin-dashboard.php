<?php
/**
 * System Admin Dashboard
 * Overview of system statistics and pending actions
 */

$session = new Session();
$user = $session->getUser();

// Get database statistics
try {
    // Total Users by Role
    $userQuery = "SELECT 
                    role,
                    COUNT(*) as count
                  FROM users 
                  WHERE is_email_verified = 1
                  GROUP BY role";
    $userResult = $conn->query($userQuery);
    $userStats = [];
    $totalUsers = 0;
    while ($row = $userResult->fetch_assoc()) {
        $userStats[$row['role']] = $row['count'];
        $totalUsers += $row['count'];
    }

    // Pending Applications Count
    $pendingQuery = "SELECT COUNT(*) as count FROM role_applications WHERE status = 'pending'";
    $pendingResult = $conn->query($pendingQuery);
    $pendingCount = $pendingResult->fetch_assoc()['count'];

    // Recent Registrations (Last 7 days)
    $recentQuery = "SELECT COUNT(*) as count FROM users WHERE created_at >= DATE_SUB(NOW(), INTERVAL 7 DAY)";
    $recentResult = $conn->query($recentQuery);
    $recentUsers = $recentResult->fetch_assoc()['count'];

    // Total Applications (All Time)
    $totalAppsQuery = "SELECT 
                        status,
                        COUNT(*) as count
                       FROM role_applications 
                       GROUP BY status";
    $appsResult = $conn->query($totalAppsQuery);
    $appStats = ['pending' => 0, 'approved' => 0, 'rejected' => 0];
    while ($row = $appsResult->fetch_assoc()) {
        $appStats[$row['status']] = $row['count'];
    }

    // Active Livestock Owners
    $ownersQuery = "SELECT COUNT(*) as count FROM livestock_owners";
    $ownersResult = $conn->query($ownersQuery);
    $activeLivestockOwners = $ownersResult->fetch_assoc()['count'];

    // Employee Assignments Count
    $employeesQuery = "SELECT COUNT(*) as count FROM employee_assignments";
    $employeesResult = $conn->query($employeesQuery);
    $totalEmployees = $employeesResult->fetch_assoc()['count'];

    // Recent Applications (Last 5)
    $recentAppsQuery = "SELECT 
                            ra.id,
                            ra.application_type,
                            ra.status,
                            ra.created_at,
                            u.name as user_name,
                            u.email as user_email
                        FROM role_applications ra
                        JOIN users u ON ra.user_id = u.id
                        ORDER BY ra.created_at DESC
                        LIMIT 5";
    $recentAppsResult = $conn->query($recentAppsQuery);
    $recentApplications = [];
    while ($row = $recentAppsResult->fetch_assoc()) {
        $recentApplications[] = $row;
    }

} catch (Exception $e) {
    error_log("Dashboard error: " . $e->getMessage());
    $userStats = [];
    $totalUsers = 0;
    $pendingCount = 0;
    $recentUsers = 0;
    $appStats = ['pending' => 0, 'approved' => 0, 'rejected' => 0];
    $activeLivestockOwners = 0;
    $totalEmployees = 0;
    $recentApplications = [];
}

// Start output buffering
ob_start();
?>

<style>
    .stat-card {
        background: white;
        border-radius: 12px;
        padding: 24px;
        box-shadow: 0 2px 8px rgba(0,0,0,0.1);
        transition: transform 0.2s, box-shadow 0.2s;
    }
    .stat-card:hover {
        transform: translateY(-4px);
        box-shadow: 0 4px 12px rgba(0,0,0,0.15);
    }
    .stat-card-icon {
        width: 56px;
        height: 56px;
        border-radius: 12px;
        display: flex;
        align-items: center;
        justify-content: center;
        font-size: 28px;
        margin-bottom: 16px;
    }
    .stat-card-value {
        font-size: 32px;
        font-weight: 700;
        color: #1a202c;
        margin-bottom: 4px;
    }
    .stat-card-label {
        font-size: 14px;
        color: #718096;
        font-weight: 500;
    }
    .stat-card-trend {
        font-size: 12px;
        margin-top: 8px;
        padding: 4px 8px;
        border-radius: 4px;
        display: inline-block;
    }
    .stats-grid {
        display: grid;
        grid-template-columns: repeat(auto-fit, minmax(240px, 1fr));
        gap: 20px;
        margin-bottom: 30px;
    }
    .section-title {
        font-size: 20px;
        font-weight: 600;
        color: #1a202c;
        margin-bottom: 20px;
        display: flex;
        align-items: center;
        gap: 10px;
    }
    .role-breakdown {
        background: white;
        border-radius: 12px;
        padding: 24px;
        box-shadow: 0 2px 8px rgba(0,0,0,0.1);
        margin-bottom: 30px;
    }
    .role-item {
        display: flex;
        justify-content: space-between;
        align-items: center;
        padding: 12px 0;
        border-bottom: 1px solid #e2e8f0;
    }
    .role-item:last-child {
        border-bottom: none;
    }
    .role-name {
        font-weight: 500;
        color: #2d3748;
        text-transform: capitalize;
    }
    .role-count {
        font-weight: 600;
        color: #667eea;
        font-size: 18px;
    }
    .recent-applications {
        background: white;
        border-radius: 12px;
        padding: 24px;
        box-shadow: 0 2px 8px rgba(0,0,0,0.1);
    }
    .app-item {
        display: flex;
        justify-content: space-between;
        align-items: center;
        padding: 16px;
        border-bottom: 1px solid #e2e8f0;
        transition: background 0.2s;
    }
    .app-item:hover {
        background: #f7fafc;
    }
    .app-item:last-child {
        border-bottom: none;
    }
    .app-user {
        font-weight: 500;
        color: #2d3748;
    }
    .app-email {
        font-size: 12px;
        color: #718096;
    }
    .app-status {
        padding: 4px 12px;
        border-radius: 12px;
        font-size: 12px;
        font-weight: 600;
        text-transform: uppercase;
    }
    .status-pending {
        background: #fef3cd;
        color: #856404;
    }
    .status-approved {
        background: #d4edda;
        color: #155724;
    }
    .status-rejected {
        background: #f8d7da;
        color: #721c24;
    }
    .quick-actions {
        background: white;
        border-radius: 12px;
        padding: 24px;
        box-shadow: 0 2px 8px rgba(0,0,0,0.1);
        margin-bottom: 30px;
    }
    .action-btn {
        display: block;
        width: 100%;
        padding: 14px 20px;
        margin-bottom: 12px;
        background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
        color: white;
        text-decoration: none;
        border-radius: 8px;
        font-weight: 600;
        text-align: center;
        transition: transform 0.2s, box-shadow 0.2s;
    }
    .action-btn:hover {
        transform: translateY(-2px);
        box-shadow: 0 4px 12px rgba(102, 126, 234, 0.4);
        color: white;
    }
    .action-btn.danger {
        background: linear-gradient(135deg, #dc3545 0%, #c82333 100%);
    }
    .action-btn.success {
        background: linear-gradient(135deg, #28a745 0%, #218838 100%);
    }
</style>

<div style="padding: 20px;">
    <!-- Page Header -->
    <div style="margin-bottom: 30px;">
        <h4 style="font-size: 28px; font-weight: 700; color: #1a202c; margin-bottom: 8px;">
            System Admin Dashboard
        </h4>
        <p style="color: #718096; font-size: 14px;">
            Welcome back, <?php echo htmlspecialchars($user['name']); ?>! Here's what's happening in your system.
        </p>
    </div>

    <!-- Statistics Cards -->
    <div class="stats-grid">
        <!-- Total Users -->
        <div class="stat-card">
            <div class="stat-card-icon" style="background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); color: white; font-weight: bold; font-size: 20px;">
                U
            </div>
            <div class="stat-card-value"><?php echo $totalUsers; ?></div>
            <div class="stat-card-label">Total Users</div>
            <div class="stat-card-trend" style="background: #e6f3ff; color: #0066cc;">
                +<?php echo $recentUsers; ?> this week
            </div>
        </div>

        <!-- Pending Applications -->
        <div class="stat-card">
            <div class="stat-card-icon" style="background: linear-gradient(135deg, #f093fb 0%, #f5576c 100%); color: white; font-weight: bold; font-size: 20px;">
                P
            </div>
            <div class="stat-card-value"><?php echo $pendingCount; ?></div>
            <div class="stat-card-label">Pending Applications</div>
            <?php if ($pendingCount > 0): ?>
                <div class="stat-card-trend" style="background: #fff3cd; color: #856404;">
                    Needs Review
                </div>
            <?php else: ?>
                <div class="stat-card-trend" style="background: #d4edda; color: #155724;">
                    All Cleared
                </div>
            <?php endif; ?>
        </div>

        <!-- Active Piggery Owners -->
        <div class="stat-card">
            <div class="stat-card-icon" style="background: linear-gradient(135deg, #4facfe 0%, #00f2fe 100%); color: white; font-weight: bold; font-size: 20px;">
                O
            </div>
            <div class="stat-card-value"><?php echo $activeLivestockOwners; ?></div>
            <div class="stat-card-label">Active Piggery Owners</div>
        </div>

        <!-- Employee Assignments -->
        <div class="stat-card">
            <div class="stat-card-icon" style="background: linear-gradient(135deg, #43e97b 0%, #38f9d7 100%); color: white; font-weight: bold; font-size: 20px;">
                E
            </div>
            <div class="stat-card-value"><?php echo $totalEmployees; ?></div>
            <div class="stat-card-label">Employee Assignments</div>
        </div>
    </div>

    <!-- Two Column Layout -->
    <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 30px; margin-bottom: 30px;">
        <!-- Role Breakdown -->
        <div class="role-breakdown">
            <h5 class="section-title">
                Users by Role
            </h5>
            <?php
            $roleLabels = [
                'customer' => 'Customer',
                'livestock_owner' => 'Piggery Owner',
                'pig_caretaker' => 'Pig Caretaker',
                'lechonero' => 'Lechonero',
                'logistics' => 'Logistics',
                'pig_slaughter' => 'Pig Slaughter',
                'supplier' => 'Feed Supplier',
                'feed_distributor' => 'Feed Distributor',
                'system_admin' => 'System Admin'
            ];
            foreach ($roleLabels as $roleKey => $roleLabel):
                $count = $userStats[$roleKey] ?? 0;
            ?>
                <div class="role-item">
                    <span class="role-name"><?php echo $roleLabel; ?></span>
                    <span class="role-count"><?php echo $count; ?></span>
                </div>
            <?php endforeach; ?>
        </div>

        <!-- Quick Actions -->
        <div class="quick-actions">
            <h5 class="section-title">
                Quick Actions
            </h5>
            <a href="<?php echo $baseUrl; ?>/admin/pending-applications" class="action-btn">
                Review Pending Applications
                <?php if ($pendingCount > 0): ?>
                    <span style="background: white; color: #667eea; padding: 2px 8px; border-radius: 10px; margin-left: 8px; font-size: 11px;">
                        <?php echo $pendingCount; ?>
                    </span>
                <?php endif; ?>
            </a>
            <a href="<?php echo $baseUrl; ?>/admin/all-users" class="action-btn success">
                View All Users
            </a>
            <a href="<?php echo $baseUrl; ?>/admin/system-logs" class="action-btn">
                System Logs
            </a>
        </div>
    </div>

    <!-- Application Statistics -->
    <div class="role-breakdown" style="margin-bottom: 30px;">
        <h5 class="section-title">
            Application Statistics
        </h5>
        <div class="role-item">
            <span class="role-name">Approved Applications</span>
            <span class="role-count" style="color: #28a745;"><?php echo $appStats['approved']; ?></span>
        </div>
        <div class="role-item">
            <span class="role-name">Pending Applications</span>
            <span class="role-count" style="color: #ffc107;"><?php echo $appStats['pending']; ?></span>
        </div>
        <div class="role-item">
            <span class="role-name">Rejected Applications</span>
            <span class="role-count" style="color: #dc3545;"><?php echo $appStats['rejected']; ?></span>
        </div>
    </div>

    <!-- Recent Applications -->
    <div class="recent-applications">
        <h5 class="section-title">
            Recent Applications
        </h5>
        <?php if (count($recentApplications) > 0): ?>
            <?php foreach ($recentApplications as $app): ?>
                <div class="app-item">
                    <div>
                        <div class="app-user">
                            <?php echo htmlspecialchars($app['user_name']); ?>
                        </div>
                        <div class="app-email">
                            <?php echo htmlspecialchars($app['user_email']); ?> • 
                            <?php echo ucwords(str_replace('_', ' ', $app['application_type'])); ?> • 
                            <?php echo date('M d, Y', strtotime($app['created_at'])); ?>
                        </div>
                    </div>
                    <span class="app-status status-<?php echo $app['status']; ?>">
                        <?php echo $app['status']; ?>
                    </span>
                </div>
            <?php endforeach; ?>
            <div style="margin-top: 20px; text-align: center;">
                <a href="<?php echo $baseUrl; ?>/admin/pending-applications" style="color: #667eea; font-weight: 600; text-decoration: none;">
                    View All Applications →
                </a>
            </div>
        <?php else: ?>
            <div style="text-align: center; padding: 40px; color: #718096;">
                <div style="font-size: 48px; margin-bottom: 16px; font-weight: bold;">N/A</div>
                <p>No applications yet</p>
            </div>
        <?php endif; ?>
    </div>
</div>

<?php
// Capture output
$content = ob_get_clean();

// Set page title and include layout
$pageTitle = 'Admin Dashboard';
$currentPage = 'admin-dashboard';
require_once __DIR__ . '/../layouts/dashboard-layout.php';
?>

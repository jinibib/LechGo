<?php
/**
 * System Logs - System Admin
 */

$session = new Session();
$user = $session->getUser();

// Get recent activities from various tables
$activities = [];

try {
    // Recent user registrations
    $regQuery = "SELECT 
                    'user_registration' as type,
                    u.name as user_name,
                    u.email,
                    u.role,
                    u.created_at as timestamp
                 FROM users u
                 ORDER BY u.created_at DESC
                 LIMIT 20";
    $regResult = $conn->query($regQuery);
    while ($row = $regResult->fetch_assoc()) {
        $activities[] = $row;
    }

    // Recent role applications
    $appQuery = "SELECT 
                    'role_application' as type,
                    u.name as user_name,
                    u.email,
                    ra.application_type as role,
                    ra.status,
                    ra.created_at as timestamp
                 FROM role_applications ra
                 JOIN users u ON ra.user_id = u.id
                 ORDER BY ra.created_at DESC
                 LIMIT 20";
    $appResult = $conn->query($appQuery);
    while ($row = $appResult->fetch_assoc()) {
        $activities[] = $row;
    }

    // Recent employee assignments
    $empQuery = "SELECT 
                    'employee_assignment' as type,
                    u.name as user_name,
                    u.email,
                    ea.assigned_role as role,
                    lo.farm_name,
                    ea.assigned_at as timestamp
                 FROM employee_assignments ea
                 JOIN users u ON ea.employee_user_id = u.id
                 JOIN livestock_owners lo ON ea.livestock_owner_id = lo.id
                 ORDER BY ea.assigned_at DESC
                 LIMIT 20";
    $empResult = $conn->query($empQuery);
    while ($row = $empResult->fetch_assoc()) {
        $activities[] = $row;
    }

    // Sort all activities by timestamp
    usort($activities, function($a, $b) {
        return strtotime($b['timestamp']) - strtotime($a['timestamp']);
    });

    // Limit to 50 most recent
    $activities = array_slice($activities, 0, 50);

} catch (Exception $e) {
    error_log("System logs error: " . $e->getMessage());
}

// Get activity counts by type
$statsQuery = "SELECT 
                (SELECT COUNT(*) FROM users WHERE created_at >= DATE_SUB(NOW(), INTERVAL 7 DAY)) as new_users_week,
                (SELECT COUNT(*) FROM role_applications WHERE created_at >= DATE_SUB(NOW(), INTERVAL 7 DAY)) as applications_week,
                (SELECT COUNT(*) FROM employee_assignments WHERE assigned_at >= DATE_SUB(NOW(), INTERVAL 7 DAY)) as assignments_week";
$statsResult = $conn->query($statsQuery);
$stats = $statsResult->fetch_assoc();

// Start output buffering
ob_start();
?>

<style>
    .log-container {
        background: white;
        border-radius: 12px;
        overflow: hidden;
        box-shadow: 0 2px 8px rgba(0,0,0,0.1);
    }
    .log-header {
        background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
        color: white;
        padding: 20px 24px;
    }
    .log-item {
        padding: 16px 24px;
        border-bottom: 1px solid #e2e8f0;
        display: flex;
        gap: 16px;
        align-items: flex-start;
        transition: background 0.2s;
    }
    .log-item:hover {
        background: #f7fafc;
    }
    .log-icon {
        width: 40px;
        height: 40px;
        border-radius: 50%;
        display: flex;
        align-items: center;
        justify-content: center;
        font-size: 20px;
        flex-shrink: 0;
    }
    .log-content {
        flex: 1;
    }
    .log-title {
        font-weight: 600;
        color: #2d3748;
        margin-bottom: 4px;
    }
    .log-details {
        font-size: 14px;
        color: #718096;
    }
    .log-time {
        font-size: 12px;
        color: #a0aec0;
        white-space: nowrap;
    }
    .activity-stats {
        display: grid;
        grid-template-columns: repeat(3, 1fr);
        gap: 20px;
        margin-bottom: 30px;
    }
    .stat-card {
        background: white;
        padding: 20px;
        border-radius: 12px;
        box-shadow: 0 2px 8px rgba(0,0,0,0.1);
        text-align: center;
    }
    .stat-value {
        font-size: 32px;
        font-weight: 700;
        margin-bottom: 8px;
    }
    .stat-label {
        font-size: 14px;
        color: #718096;
    }
    .filter-tabs {
        display: flex;
        gap: 8px;
        padding: 16px 24px;
        border-bottom: 1px solid #e2e8f0;
        overflow-x: auto;
    }
    .filter-tab {
        padding: 8px 16px;
        border-radius: 8px;
        background: #f7fafc;
        border: none;
        cursor: pointer;
        font-weight: 500;
        font-size: 14px;
        color: #4a5568;
        transition: all 0.2s;
        white-space: nowrap;
    }
    .filter-tab.active {
        background: #667eea;
        color: white;
    }
    .filter-tab:hover {
        background: #e2e8f0;
    }
    .filter-tab.active:hover {
        background: #5568d3;
    }
</style>

<div style="padding: 20px;">
    <!-- Page Header -->
    <div style="margin-bottom: 30px;">
        <h4 style="font-size: 28px; font-weight: 700; color: #1a202c; margin-bottom: 8px;">
            System Activity Logs
        </h4>
        <p style="color: #718096; font-size: 14px;">
            Monitor all system activities and user actions
        </p>
    </div>

    <!-- Activity Statistics -->
    <div class="activity-stats">
        <div class="stat-card">
            <div class="stat-value" style="color: #667eea;"><?php echo $stats['new_users_week']; ?></div>
            <div class="stat-label">New Users (7 days)</div>
        </div>
        <div class="stat-card">
            <div class="stat-value" style="color: #f093fb;"><?php echo $stats['applications_week']; ?></div>
            <div class="stat-label">Applications (7 days)</div>
        </div>
        <div class="stat-card">
            <div class="stat-value" style="color: #43e97b;"><?php echo $stats['assignments_week']; ?></div>
            <div class="stat-label">Assignments (7 days)</div>
        </div>
    </div>

    <!-- Activity Log -->
    <div class="log-container">
        <div class="log-header">
            <h5 style="margin: 0; font-size: 18px; font-weight: 600;">Recent Activity</h5>
            <p style="margin: 4px 0 0 0; font-size: 14px; opacity: 0.9;">Last 50 activities</p>
        </div>

        <!-- Filter Tabs -->
        <div class="filter-tabs">
            <button class="filter-tab active" onclick="filterLogs('all')">All Activity</button>
            <button class="filter-tab" onclick="filterLogs('user_registration')">Registrations</button>
            <button class="filter-tab" onclick="filterLogs('role_application')">Applications</button>
            <button class="filter-tab" onclick="filterLogs('employee_assignment')">Assignments</button>
        </div>

        <!-- Activity Items -->
        <div id="logsList">
            <?php if (count($activities) > 0): ?>
                <?php foreach ($activities as $activity): ?>
                    <?php
                    $icon = 'A';
                    $bgColor = '#e3f2fd';
                    $title = '';
                    $details = '';

                    if ($activity['type'] === 'user_registration') {
                        $icon = 'R';
                        $bgColor = '#e8f5e9';
                        $title = 'New User Registration';
                        $details = htmlspecialchars($activity['user_name']) . ' (' . htmlspecialchars($activity['email']) . ') registered as ' . ucwords(str_replace('_', ' ', $activity['role']));
                    } elseif ($activity['type'] === 'role_application') {
                        $icon = 'A';
                        $bgColor = '#fff3e0';
                        $title = 'Role Application';
                        $status = $activity['status'] ?? 'pending';
                        $statusIcon = $status === 'approved' ? '[✓]' : ($status === 'rejected' ? '[✗]' : '[...]');
                        $details = htmlspecialchars($activity['user_name']) . ' applied for ' . ucwords(str_replace('_', ' ', $activity['role'])) . ' role - ' . $statusIcon . ' ' . ucfirst($status);
                    } elseif ($activity['type'] === 'employee_assignment') {
                        $icon = 'E';
                        $bgColor = '#f3e5f5';
                        $title = 'Employee Assignment';
                        $details = htmlspecialchars($activity['user_name']) . ' assigned as ' . ucwords(str_replace('_', ' ', $activity['role'])) . ' at ' . htmlspecialchars($activity['farm_name']);
                    }
                    ?>
                    <div class="log-item" data-type="<?php echo $activity['type']; ?>">
                        <div class="log-icon" style="background: <?php echo $bgColor; ?>; color: white; font-weight: bold; font-size: 18px;">
                            <?php echo $icon; ?>
                        </div>
                        <div class="log-content">
                            <div class="log-title"><?php echo $title; ?></div>
                            <div class="log-details"><?php echo $details; ?></div>
                        </div>
                        <div class="log-time">
                            <?php 
                            $time = strtotime($activity['timestamp']);
                            $diff = time() - $time;
                            
                            if ($diff < 60) {
                                echo 'Just now';
                            } elseif ($diff < 3600) {
                                echo floor($diff / 60) . ' min ago';
                            } elseif ($diff < 86400) {
                                echo floor($diff / 3600) . ' hrs ago';
                            } else {
                                echo date('M d, Y', $time);
                            }
                            ?>
                        </div>
                    </div>
                <?php endforeach; ?>
            <?php else: ?>
                <div style="text-align: center; padding: 60px 20px; color: #718096;">
                    <div style="font-size: 48px; margin-bottom: 16px; font-weight: bold;">N/A</div>
                    <p style="font-size: 18px; font-weight: 500; margin-bottom: 8px;">No activity logs</p>
                    <p style="font-size: 14px;">System activities will appear here</p>
                </div>
            <?php endif; ?>
        </div>
    </div>
</div>

<script>
function filterLogs(type) {
    // Update active tab
    document.querySelectorAll('.filter-tab').forEach(tab => {
        tab.classList.remove('active');
    });
    event.target.classList.add('active');

    // Filter log items
    const items = document.querySelectorAll('.log-item');
    items.forEach(item => {
        if (type === 'all' || item.dataset.type === type) {
            item.style.display = 'flex';
        } else {
            item.style.display = 'none';
        }
    });
}
</script>

<?php
// Capture output
$content = ob_get_clean();

// Set page title and include layout
$pageTitle = 'System Logs';
$currentPage = 'system-logs';
require_once __DIR__ . '/../layouts/dashboard-layout.php';
?>

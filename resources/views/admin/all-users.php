<?php
/**
 * All Users Management - System Admin
 */

$session = new Session();
$user = $session->getUser();

// Get all users with sorting and filtering
$search = $_GET['search'] ?? '';
$roleFilter = $_GET['role'] ?? '';
$statusFilter = $_GET['status'] ?? '';

// Build query - simplified without optional table joins
$query = "SELECT 
            u.id,
            u.name,
            u.email,
            u.phone,
            u.role,
            u.email_verified,
            u.created_at,
            lo.farm_name as business_name
          FROM users u
          LEFT JOIN livestock_owners lo ON u.id = lo.user_id
          WHERE 1=1";

$params = [];
$types = '';

if (!empty($search)) {
    $query .= " AND (u.name LIKE ? OR u.email LIKE ? OR u.phone LIKE ?)";
    $searchParam = '%' . $search . '%';
    $params[] = &$searchParam;
    $params[] = &$searchParam;
    $params[] = &$searchParam;
    $types .= 'sss';
}

if (!empty($roleFilter)) {
    $query .= " AND u.role = ?";
    $params[] = &$roleFilter;
    $types .= 's';
}

if ($statusFilter === 'verified') {
    $query .= " AND u.email_verified = 1";
} elseif ($statusFilter === 'unverified') {
    $query .= " AND u.email_verified = 0";
}

$query .= " ORDER BY u.created_at DESC";

$stmt = $conn->prepare($query);
if (!empty($params)) {
    array_unshift($params, $types);
    call_user_func_array([$stmt, 'bind_param'], $params);
}

$stmt->execute();
$result = $stmt->get_result();
$users = $result->fetch_all(MYSQLI_ASSOC);
$stmt->close();

// Get statistics
$statsQuery = "SELECT 
                COUNT(*) as total,
                SUM(CASE WHEN email_verified = 1 THEN 1 ELSE 0 END) as verified,
                SUM(CASE WHEN email_verified = 0 THEN 1 ELSE 0 END) as unverified
               FROM users";
$statsResult = $conn->query($statsQuery);
$stats = $statsResult->fetch_assoc();

// Start output buffering
ob_start();
?>

<style>
    .users-table {
        background: white;
        border-radius: 12px;
        overflow: hidden;
        box-shadow: 0 2px 8px rgba(0,0,0,0.1);
    }
    .table-header {
        background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
        color: white;
        padding: 20px 24px;
        display: flex;
        justify-content: space-between;
        align-items: center;
    }
    .search-bar {
        display: flex;
        gap: 10px;
        padding: 20px 24px;
        border-bottom: 1px solid #e2e8f0;
        flex-wrap: wrap;
    }
    .search-input {
        flex: 1;
        min-width: 200px;
        padding: 10px 16px;
        border: 1px solid #cbd5e0;
        border-radius: 8px;
        font-size: 14px;
    }
    .filter-select {
        padding: 10px 16px;
        border: 1px solid #cbd5e0;
        border-radius: 8px;
        font-size: 14px;
        min-width: 150px;
    }
    .btn-search {
        padding: 10px 24px;
        background: #667eea;
        color: white;
        border: none;
        border-radius: 8px;
        font-weight: 600;
        cursor: pointer;
        transition: background 0.2s;
    }
    .btn-search:hover {
        background: #5568d3;
    }
    .users-list {
        max-height: 600px;
        overflow-y: auto;
    }
    .user-row {
        display: grid;
        grid-template-columns: 2fr 2fr 1.5fr 1.5fr 1fr 1fr;
        padding: 16px 24px;
        border-bottom: 1px solid #e2e8f0;
        transition: background 0.2s;
        align-items: center;
        gap: 12px;
    }
    .user-row:hover {
        background: #f7fafc;
    }
    .user-row.header {
        background: #f8f9fa;
        font-weight: 600;
        color: #2d3748;
        border-bottom: 2px solid #cbd5e0;
    }
    .user-row.header:hover {
        background: #f8f9fa;
    }
    .user-name {
        font-weight: 500;
        color: #2d3748;
    }
    .user-email {
        color: #718096;
        font-size: 14px;
    }
    .role-badge {
        display: inline-block;
        padding: 4px 12px;
        border-radius: 12px;
        font-size: 12px;
        font-weight: 600;
        text-transform: capitalize;
    }
    .status-badge {
        display: inline-block;
        padding: 4px 12px;
        border-radius: 12px;
        font-size: 12px;
        font-weight: 600;
    }
    .badge-verified {
        background: #d4edda;
        color: #155724;
    }
    .badge-unverified {
        background: #f8d7da;
        color: #721c24;
    }
    .stats-grid {
        display: grid;
        grid-template-columns: repeat(3, 1fr);
        gap: 20px;
        margin-bottom: 30px;
    }
    .stat-box {
        background: white;
        padding: 20px;
        border-radius: 12px;
        box-shadow: 0 2px 8px rgba(0,0,0,0.1);
        text-align: center;
    }
    .stat-value {
        font-size: 32px;
        font-weight: 700;
        color: #667eea;
        margin-bottom: 8px;
    }
    .stat-label {
        font-size: 14px;
        color: #718096;
        font-weight: 500;
    }
    .btn-reset {
        padding: 10px 24px;
        background: #e2e8f0;
        color: #2d3748;
        border: none;
        border-radius: 8px;
        font-weight: 600;
        cursor: pointer;
        text-decoration: none;
        display: inline-block;
    }
    @media (max-width: 768px) {
        .user-row {
            grid-template-columns: 1fr;
        }
    }
</style>

<div style="padding: 20px;">
    <!-- Page Header -->
    <div style="margin-bottom: 30px;">
        <h4 style="font-size: 28px; font-weight: 700; color: #1a202c; margin-bottom: 8px;">
            All Users Management
        </h4>
        <p style="color: #718096; font-size: 14px;">
            Manage and monitor all registered users in the system
        </p>
    </div>

    <!-- Statistics -->
    <div class="stats-grid">
        <div class="stat-box">
            <div class="stat-value"><?php echo $stats['total']; ?></div>
            <div class="stat-label">Total Users</div>
        </div>
        <div class="stat-box">
            <div class="stat-value" style="color: #28a745;"><?php echo $stats['verified']; ?></div>
            <div class="stat-label">Verified Users</div>
        </div>
        <div class="stat-box">
            <div class="stat-value" style="color: #dc3545;"><?php echo $stats['unverified']; ?></div>
            <div class="stat-label">Unverified Users</div>
        </div>
    </div>

    <!-- Users Table -->
    <div class="users-table">
        <div class="table-header">
            <h5 style="margin: 0; font-size: 18px; font-weight: 600;">User Directory</h5>
            <span><?php echo count($users); ?> users found</span>
        </div>

        <!-- Search and Filters -->
        <form method="GET" action="<?php echo $baseUrl; ?>/admin/all-users" class="search-bar">
            <input 
                type="text" 
                name="search" 
                class="search-input" 
                placeholder="Search by name, email, or phone..."
                value="<?php echo htmlspecialchars($search); ?>"
            >
            <select name="role" class="filter-select">
                <option value="">All Roles</option>
                <option value="customer" <?php echo $roleFilter === 'customer' ? 'selected' : ''; ?>>Customer</option>
                <option value="livestock_owner" <?php echo $roleFilter === 'livestock_owner' ? 'selected' : ''; ?>>Piggery Owner</option>
                <option value="pig_caretaker" <?php echo $roleFilter === 'pig_caretaker' ? 'selected' : ''; ?>>Pig Caretaker</option>
                <option value="lechonero" <?php echo $roleFilter === 'lechonero' ? 'selected' : ''; ?>>Lechonero</option>
                <option value="logistics" <?php echo $roleFilter === 'logistics' ? 'selected' : ''; ?>>Logistics</option>
                <option value="supplier" <?php echo $roleFilter === 'supplier' ? 'selected' : ''; ?>>Feed Supplier</option>
                <option value="system_admin" <?php echo $roleFilter === 'system_admin' ? 'selected' : ''; ?>>System Admin</option>
            </select>
            <select name="status" class="filter-select">
                <option value="">All Status</option>
                <option value="verified" <?php echo $statusFilter === 'verified' ? 'selected' : ''; ?>>Verified</option>
                <option value="unverified" <?php echo $statusFilter === 'unverified' ? 'selected' : ''; ?>>Unverified</option>
            </select>
            <button type="submit" class="btn-search">Search</button>
            <a href="<?php echo $baseUrl; ?>/admin/all-users" class="btn-reset">Clear</a>
        </form>

        <!-- Table Header -->
        <div class="user-row header">
            <div>Name</div>
            <div>Email</div>
            <div>Phone</div>
            <div>Role</div>
            <div>Status</div>
            <div>Joined</div>
        </div>

        <!-- Users List -->
        <div class="users-list">
            <?php if (count($users) > 0): ?>
                <?php foreach ($users as $u): ?>
                    <div class="user-row">
                        <div>
                            <div class="user-name"><?php echo htmlspecialchars($u['name']); ?></div>
                            <?php if ($u['business_name']): ?>
                                <div style="font-size: 12px; color: #718096;">
                                    <?php echo htmlspecialchars($u['business_name']); ?>
                                </div>
                            <?php endif; ?>
                        </div>
                        <div class="user-email"><?php echo htmlspecialchars($u['email']); ?></div>
                        <div class="user-email"><?php echo htmlspecialchars($u['phone']); ?></div>
                        <div>
                            <span class="role-badge" style="background: <?php 
                                $colors = [
                                    'customer' => '#e3f2fd',
                                    'livestock_owner' => '#fff3e0',
                                    'pig_caretaker' => '#e8f5e9',
                                    'lechonero' => '#fce4ec',
                                    'logistics' => '#f3e5f5',
                                    'supplier' => '#e0f2f1',
                                    'system_admin' => '#ffebee'
                                ];
                                echo $colors[$u['role']] ?? '#f5f5f5';
                            ?>; color: #333;">
                                <?php echo ucwords(str_replace('_', ' ', $u['role'])); ?>
                            </span>
                        </div>
                        <div>
                            <?php if ($u['email_verified']): ?>
                                <span class="status-badge badge-verified">Verified</span>
                            <?php else: ?>
                                <span class="status-badge badge-unverified">Unverified</span>
                            <?php endif; ?>
                        </div>
                        <div class="user-email">
                            <?php echo date('M d, Y', strtotime($u['created_at'])); ?>
                        </div>
                    </div>
                <?php endforeach; ?>
            <?php else: ?>
                <div style="text-align: center; padding: 60px 20px; color: #718096;">
                    <div style="font-size: 48px; margin-bottom: 16px; font-weight: bold;">N/A</div>
                    <p style="font-size: 18px; font-weight: 500; margin-bottom: 8px;">No users found</p>
                    <p style="font-size: 14px;">Try adjusting your search or filters</p>
                </div>
            <?php endif; ?>
        </div>
    </div>
</div>

<?php
// Capture output
$content = ob_get_clean();

// Set page title and include layout
$pageTitle = 'All Users';
$currentPage = 'all-users';
require_once __DIR__ . '/../layouts/dashboard-layout.php';
?>

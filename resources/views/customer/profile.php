<?php
/**
 * Customer Profile Page
 */

$pageTitle = 'My Profile';
$currentPage = 'profile';
require_once __DIR__ . '/../layouts/dashboard-layout.php';

$session = new Session();
$user = $session->getUser();

// Check for pending application
require_once APP_PATH . '/models/RoleApplication.php';
$roleApp = new RoleApplication($GLOBALS['conn']);
$hasPending = $roleApp->hasPendingApplication($user['id']);
$applications = $roleApp->getUserApplications($user['id']);
?>

<!-- Profile Content -->
<div class="profile-content">

        <!-- Flash Messages -->
        <?php if (isset($_SESSION['error'])): ?>
            <div class="alert-message error">
                <i class="fas fa-exclamation-circle"></i>
                <span><?php echo htmlspecialchars($_SESSION['error']); unset($_SESSION['error']); ?></span>
                <button class="close-btn" onclick="this.parentElement.remove()">×</button>
            </div>
        <?php endif; ?>

        <?php if (isset($_SESSION['success'])): ?>
            <div class="alert-message success">
                <i class="fas fa-check-circle"></i>
                <span><?php echo htmlspecialchars($_SESSION['success']); unset($_SESSION['success']); ?></span>
                <button class="close-btn" onclick="this.parentElement.remove()">×</button>
            </div>
        <?php endif; ?>

        <?php if (isset($_SESSION['warning'])): ?>
            <div class="alert-message warning">
                <i class="fas fa-exclamation-triangle"></i>
                <span><?php echo htmlspecialchars($_SESSION['warning']); unset($_SESSION['warning']); ?></span>
                <button class="close-btn" onclick="this.parentElement.remove()">×</button>
            </div>
        <?php endif; ?>

        <!-- Main Content Grid -->
        <div class="profile-grid">
            <!-- Profile Information Card -->
            <div class="info-card">
                <div class="card-icon">
                    <i class="fas fa-user"></i>
                </div>
                <h2>Profile Information</h2>
                
                <div class="info-grid">
                    <div class="info-item">
                        <label><i class="fas fa-user-tag"></i> Full Name</label>
                        <span><?php echo htmlspecialchars($user['name']); ?></span>
                    </div>
                    
                    <div class="info-item">
                        <label><i class="fas fa-envelope"></i> Email Address</label>
                        <span><?php echo htmlspecialchars($user['email']); ?></span>
                    </div>
                    
                    <div class="info-item">
                        <label><i class="fas fa-phone"></i> Phone Number</label>
                        <span><?php echo htmlspecialchars($user['phone']); ?></span>
                    </div>
                    
                    <div class="info-item">
                        <label><i class="fas fa-briefcase"></i> Current Role</label>
                        <span class="role-badge <?php echo $user['role']; ?>">
                            <?php echo ucwords(str_replace('_', ' ', $user['role'])); ?>
                        </span>
                    </div>
                </div>
            </div>

            <!-- Role Application Card -->
            <div class="application-card">
                <div class="card-icon">
                    <i class="fas fa-building"></i>
                </div>
                <h2>Expand Your Business</h2>
                
                <?php if ($user['role'] === 'customer'): ?>
                    <?php if ($hasPending): ?>
                        <div class="pending-status">
                            <div class="status-icon">
                                <i class="fas fa-clock"></i>
                            </div>
                            <h3>Application Under Review</h3>
                            <p>Your piggery application is being reviewed by our team. You'll receive a notification once it's processed.</p>
                        </div>
                    <?php else: ?>
                        <p class="app-description">
                            Join our network of successful piggery owners and unlock powerful business management tools.
                        </p>
                        
                        <a href="<?php echo $baseUrl; ?>/apply-piggery" class="apply-button">
                            <i class="fas fa-rocket"></i> Apply as Piggery Owner
                        </a>
                        
                        <div class="features-list">
                            <h4><i class="fas fa-star"></i> What You'll Get:</h4>
                            <ul>
                                <li><i class="fas fa-check"></i> Complete pig inventory management</li>
                                <li><i class="fas fa-check"></i> Sell pigs in online marketplace</li>
                                <li><i class="fas fa-check"></i> Direct ordering from feed suppliers</li>
                                <li><i class="fas fa-check"></i> Employee management system</li>
                                <li><i class="fas fa-check"></i> Real-time transaction tracking</li>
                            </ul>
                        </div>
                    <?php endif; ?>
                <?php else: ?>
                    <div class="active-status">
                        <div class="status-icon">
                            <i class="fas fa-check-circle"></i>
                        </div>
                        <h3>Role Active</h3>
                        <p>You're registered as <strong><?php echo ucwords(str_replace('_', ' ', $user['role'])); ?></strong></p>
                    </div>
                <?php endif; ?>
            </div>
        </div>

        <!-- Application History -->
        <?php if (!empty($applications)): ?>
            <div class="history-section">
                <div class="section-header">
                    <h2><i class="fas fa-history"></i> Application History</h2>
                </div>
                
                <div class="history-table-wrapper">
                    <table class="history-table">
                        <thead>
                            <tr>
                                <th>Type</th>
                                <th>Status</th>
                                <th>Applied</th>
                                <th>Reviewed</th>
                                <th>Remarks</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($applications as $app): ?>
                                <tr>
                                    <td data-label="Type"><?php echo ucwords(str_replace('_', ' ', $app['application_type'])); ?></td>
                                    <td data-label="Status">
                                        <span class="status-badge <?php echo $app['status']; ?>">
                                            <?php echo ucfirst($app['status']); ?>
                                        </span>
                                    </td>
                                    <td data-label="Applied"><?php echo date('M d, Y', strtotime($app['created_at'])); ?></td>
                                    <td data-label="Reviewed"><?php echo $app['reviewed_at'] ? date('M d, Y', strtotime($app['reviewed_at'])) : '—'; ?></td>
                                    <td data-label="Remarks"><?php echo $app['remarks'] ? htmlspecialchars($app['remarks']) : '—'; ?></td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        <?php endif; ?>
    </div>
</div>

<style>

.profile-header {
    margin-bottom: 30px;
}

.profile-header h1 {
    font-size: 32px;
    color: #2c3e50;
    margin-bottom: 8px;
    display: flex;
    align-items: center;
    gap: 12px;
}

.profile-header h1 i {
    color: #dc3545;
}

.profile-header p {
    font-size: 16px;
    color: #7f8c8d;
    margin: 0;
}

/* Alert Messages */
.alert-message {
    padding: 16px 20px;
    border-radius: 12px;
    margin-bottom: 24px;
    display: flex;
    align-items: center;
    gap: 12px;
    animation: slideIn 0.3s ease-out;
}

.alert-message i {
    font-size: 20px;
}

.alert-message.success {
    background: #d4edda;
    border-left: 4px solid #28a745;
    color: #155724;
}

.alert-message.error {
    background: #f8d7da;
    border-left: 4px solid #dc3545;
    color: #721c24;
}

.alert-message.warning {
    background: #fff3cd;
    border-left: 4px solid #ffc107;
    color: #856404;
}

.alert-message .close-btn {
    margin-left: auto;
    background: none;
    border: none;
    font-size: 24px;
    cursor: pointer;
    opacity: 0.6;
    transition: opacity 0.2s;
}

.alert-message .close-btn:hover {
    opacity: 1;
}

/* Profile Grid */
.profile-grid {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(320px, 1fr));
    gap: 24px;
    margin-bottom: 30px;
}

/* Cards */
.info-card, .application-card {
    background: #fff;
    border-radius: 16px;
    padding: 28px;
    box-shadow: 0 2px 12px rgba(0, 0, 0, 0.08);
    transition: transform 0.2s, box-shadow 0.2s;
}

.info-card:hover, .application-card:hover {
    transform: translateY(-4px);
    box-shadow: 0 8px 24px rgba(0, 0, 0, 0.12);
}

.card-icon {
    width: 56px;
    height: 56px;
    background: linear-gradient(135deg, #dc3545, #c82333);
    border-radius: 14px;
    display: flex;
    align-items: center;
    justify-content: center;
    margin-bottom: 20px;
}

.card-icon i {
    font-size: 28px;
    color: #fff;
}

.info-card h2, .application-card h2 {
    font-size: 22px;
    color: #2c3e50;
    margin-bottom: 24px;
    font-weight: 600;
}

/* Info Grid */
.info-grid {
    display: grid;
    gap: 20px;
}

.info-item {
    display: flex;
    flex-direction: column;
    gap: 8px;
    padding-bottom: 16px;
    border-bottom: 1px solid #ecf0f1;
}

.info-item:last-child {
    border-bottom: none;
    padding-bottom: 0;
}

.info-item label {
    font-size: 13px;
    color: #7f8c8d;
    font-weight: 500;
    display: flex;
    align-items: center;
    gap: 8px;
}

.info-item label i {
    color: #dc3545;
}

.info-item span {
    font-size: 16px;
    color: #2c3e50;
    font-weight: 500;
}

.role-badge {
    display: inline-block;
    padding: 6px 14px;
    border-radius: 20px;
    font-size: 13px;
    font-weight: 600;
    background: linear-gradient(135deg, #667eea, #764ba2);
    color: #fff;
    width: fit-content;
}

/* Application Card */
.app-description {
    font-size: 15px;
    color: #555;
    line-height: 1.6;
    margin-bottom: 20px;
}

.apply-button {
    display: flex;
    align-items: center;
    justify-content: center;
    gap: 10px;
    width: 100%;
    padding: 16px 24px;
    background: linear-gradient(135deg, #dc3545, #c82333);
    color: #fff;
    border: none;
    border-radius: 12px;
    font-size: 16px;
    font-weight: 600;
    cursor: pointer;
    transition: all 0.3s;
    text-decoration: none;
    box-shadow: 0 4px 12px rgba(220, 53, 69, 0.3);
}

.apply-button:hover {
    background: linear-gradient(135deg, #c82333, #bd2130);
    transform: translateY(-2px);
    box-shadow: 0 6px 20px rgba(220, 53, 69, 0.4);
    color: #fff;
}

.features-list {
    margin-top: 24px;
    padding: 20px;
    background: #f8f9fa;
    border-radius: 12px;
}

.features-list h4 {
    font-size: 16px;
    color: #2c3e50;
    margin-bottom: 16px;
    display: flex;
    align-items: center;
    gap: 8px;
}

.features-list h4 i {
    color: #ffc107;
}

.features-list ul {
    list-style: none;
    padding: 0;
    margin: 0;
}

.features-list li {
    padding: 10px 0;
    color: #555;
    font-size: 14px;
    display: flex;
    align-items: center;
    gap: 10px;
}

.features-list li i {
    color: #28a745;
    font-size: 12px;
}

/* Status Cards */
.pending-status, .active-status {
    text-align: center;
    padding: 30px 20px;
}

.status-icon {
    width: 80px;
    height: 80px;
    margin: 0 auto 20px;
    background: linear-gradient(135deg, #ffeaa7, #fdcb6e);
    border-radius: 50%;
    display: flex;
    align-items: center;
    justify-content: center;
}

.active-status .status-icon {
    background: linear-gradient(135deg, #55efc4, #00b894);
}

.status-icon i {
    font-size: 36px;
    color: #fff;
}

.pending-status h3, .active-status h3 {
    font-size: 20px;
    color: #2c3e50;
    margin-bottom: 12px;
    font-weight: 600;
}

.pending-status p, .active-status p {
    font-size: 15px;
    color: #7f8c8d;
    line-height: 1.6;
}

/* History Section */
.history-section {
    background: #fff;
    border-radius: 16px;
    padding: 28px;
    box-shadow: 0 2px 12px rgba(0, 0, 0, 0.08);
    margin-top: 24px;
}

.section-header {
    margin-bottom: 24px;
}

.section-header h2 {
    font-size: 22px;
    color: #2c3e50;
    font-weight: 600;
    display: flex;
    align-items: center;
    gap: 10px;
}

.section-header h2 i {
    color: #dc3545;
}

.history-table-wrapper {
    overflow-x: auto;
    border-radius: 12px;
    border: 1px solid #ecf0f1;
}

.history-table {
    width: 100%;
    border-collapse: collapse;
    min-width: 700px;
}

.history-table thead {
    background: #f8f9fa;
}

.history-table th {
    padding: 16px;
    text-align: left;
    font-size: 13px;
    font-weight: 600;
    color: #2c3e50;
    text-transform: uppercase;
    letter-spacing: 0.5px;
}

.history-table td {
    padding: 16px;
    border-top: 1px solid #ecf0f1;
    font-size: 14px;
    color: #555;
}

.history-table tbody tr {
    transition: background-color 0.2s;
}

.history-table tbody tr:hover {
    background: #f8f9fa;
}

.status-badge {
    display: inline-block;
    padding: 6px 12px;
    border-radius: 20px;
    font-size: 12px;
    font-weight: 600;
    text-transform: uppercase;
}

.status-badge.approved {
    background: #d4edda;
    color: #155724;
}

.status-badge.rejected {
    background: #f8d7da;
    color: #721c24;
}

.status-badge.pending {
    background: #fff3cd;
    color: #856404;
}

/* Animations */
@keyframes slideIn {
    from {
        opacity: 0;
        transform: translateY(-10px);
    }
    to {
        opacity: 1;
        transform: translateY(0);
    }
}

/* Responsive Design */
@media (max-width: 768px) {
    .profile-header h2 {
        font-size: 20px;
    }

    .profile-header p {
        font-size: 14px;
    }

    .profile-grid {
        grid-template-columns: 1fr;
        gap: 20px;
    }

    .info-card, .application-card {
        padding: 20px;
    }

    .card-icon {
        width: 48px;
        height: 48px;
    }

    .card-icon i {
        font-size: 24px;
    }

    .info-card h2, .application-card h2 {
        font-size: 18px;
    }

    .apply-button {
        padding: 14px 20px;
        font-size: 15px;
    }

    .features-list {
        padding: 16px;
    }

    .features-list li {
        font-size: 13px;
    }

    .history-section {
        padding: 20px;
    }

    .section-header h2 {
        font-size: 18px;
    }

    /* Mobile Table */
    .history-table {
        min-width: 100%;
    }

    .history-table thead {
        display: none;
    }

    .history-table tbody tr {
        display: block;
        margin-bottom: 16px;
        border: 1px solid #ecf0f1;
        border-radius: 8px;
        overflow: hidden;
    }

    .history-table td {
        display: flex;
        justify-content: space-between;
        align-items: center;
        padding: 12px 16px;
        border-top: none;
        border-bottom: 1px solid #ecf0f1;
    }

    .history-table td:last-child {
        border-bottom: none;
    }

    .history-table td::before {
        content: attr(data-label);
        font-weight: 600;
        color: #2c3e50;
        font-size: 13px;
    }
}

@media (max-width: 480px) {
    .profile-header h2 {
        font-size: 18px;
    }

    .info-item span {
        font-size: 14px;
        word-break: break-word;
    }

    .alert-message {
        padding: 12px 16px;
        font-size: 14px;
    }

    .status-icon {
        width: 60px;
        height: 60px;
    }

    .status-icon i {
        font-size: 28px;
    }

    .pending-status h3, .active-status h3 {
        font-size: 18px;
    }

    .pending-status p, .active-status p {
        font-size: 14px;
    }
}
</style>

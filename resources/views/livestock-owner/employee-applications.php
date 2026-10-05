<?php
/**
 * Employee Applications - For Livestock Owners
 * View and manage employee applications for their piggery
 */

$page_title = 'Employee Applications';
require_once __DIR__ . '/../layouts/dashboard-layout.php';
?>

<div class="content-wrapper">
    <div class="container-fluid">
        <div class="row">
            <div class="col-12">
                <div class="page-header">
                    <h1>Employee Applications</h1>
                    <p>Review and manage applications from people wanting to work at your piggery</p>
                </div>

                <!-- Flash Messages -->
                <?php if (isset($_SESSION['error'])): ?>
                    <div class="alert alert-danger alert-dismissible fade show" role="alert">
                        <?php echo htmlspecialchars($_SESSION['error']); unset($_SESSION['error']); ?>
                        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                    </div>
                <?php endif; ?>

                <?php if (isset($_SESSION['success'])): ?>
                    <div class="alert alert-success alert-dismissible fade show" role="alert">
                        <?php echo htmlspecialchars($_SESSION['success']); unset($_SESSION['success']); ?>
                        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                    </div>
                <?php endif; ?>

                <!-- Pending Applications -->
                <div class="card mb-4">
                    <div class="card-header">
                        <h5 class="mb-0"><i class="fas fa-clock"></i> Pending Applications</h5>
                    </div>
                    <div class="card-body">
                        <?php 
                        $pending_apps = array_filter($applications, function($app) {
                            return $app['status'] === 'pending';
                        });
                        ?>
                        
                        <?php if (empty($pending_apps)): ?>
                            <div class="alert alert-info">
                                <i class="fas fa-info-circle"></i> No pending applications at this time.
                            </div>
                        <?php else: ?>
                            <div class="table-responsive">
                                <table class="table table-hover">
                                    <thead>
                                        <tr>
                                            <th>Applicant</th>
                                            <th>Email</th>
                                            <th>Phone</th>
                                            <th>Position</th>
                                            <th>Applied Date</th>
                                            <th>Actions</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php foreach ($pending_apps as $app): ?>
                                            <tr>
                                                <td><strong><?php echo htmlspecialchars($app['applicant_name']); ?></strong></td>
                                                <td><?php echo htmlspecialchars($app['applicant_email']); ?></td>
                                                <td><?php echo htmlspecialchars($app['applicant_phone']); ?></td>
                                                <td>
                                                    <span class="badge bg-info">
                                                        <?php echo ucwords(str_replace('_', ' ', $app['position'])); ?>
                                                    </span>
                                                </td>
                                                <td><?php echo date('M d, Y', strtotime($app['applied_at'])); ?></td>
                                                <td>
                                                    <button class="btn btn-sm btn-info" onclick="viewApplication(<?php echo $app['id']; ?>, <?php echo htmlspecialchars(json_encode($app)); ?>)">
                                                        <i class="fas fa-eye"></i> View
                                                    </button>
                                                    <button class="btn btn-sm btn-success" onclick="approveApplication(<?php echo $app['id']; ?>, '<?php echo htmlspecialchars($app['applicant_name']); ?>')">
                                                        <i class="fas fa-check"></i> Approve
                                                    </button>
                                                    <button class="btn btn-sm btn-danger" onclick="rejectApplication(<?php echo $app['id']; ?>, '<?php echo htmlspecialchars($app['applicant_name']); ?>')">
                                                        <i class="fas fa-times"></i> Reject
                                                    </button>
                                                </td>
                                            </tr>
                                        <?php endforeach; ?>
                                    </tbody>
                                </table>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>

                <!-- Application History -->
                <div class="card">
                    <div class="card-header">
                        <h5 class="mb-0"><i class="fas fa-history"></i> Application History</h5>
                    </div>
                    <div class="card-body">
                        <?php 
                        $history_apps = array_filter($applications, function($app) {
                            return $app['status'] !== 'pending';
                        });
                        ?>
                        
                        <?php if (empty($history_apps)): ?>
                            <div class="alert alert-info">
                                <i class="fas fa-info-circle"></i> No application history yet.
                            </div>
                        <?php else: ?>
                            <div class="table-responsive">
                                <table class="table table-hover">
                                    <thead>
                                        <tr>
                                            <th>Applicant</th>
                                            <th>Position</th>
                                            <th>Applied Date</th>
                                            <th>Reviewed Date</th>
                                            <th>Status</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php foreach ($history_apps as $app): ?>
                                            <tr>
                                                <td><?php echo htmlspecialchars($app['applicant_name']); ?></td>
                                                <td>
                                                    <span class="badge bg-info">
                                                        <?php echo ucwords(str_replace('_', ' ', $app['position'])); ?>
                                                    </span>
                                                </td>
                                                <td><?php echo date('M d, Y', strtotime($app['applied_at'])); ?></td>
                                                <td><?php echo $app['reviewed_at'] ? date('M d, Y', strtotime($app['reviewed_at'])) : 'N/A'; ?></td>
                                                <td>
                                                    <span class="badge bg-<?php echo $app['status'] === 'approved' ? 'success' : 'danger'; ?>">
                                                        <?php echo ucfirst($app['status']); ?>
                                                    </span>
                                                </td>
                                            </tr>
                                        <?php endforeach; ?>
                                    </tbody>
                                </table>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- View Application Modal -->
<div class="modal fade" id="viewModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Application Details</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <div class="mb-3">
                    <label class="form-label"><strong>Applicant:</strong></label>
                    <p id="view_applicant_name" class="text-muted"></p>
                </div>
                <div class="mb-3">
                    <label class="form-label"><strong>Email:</strong></label>
                    <p id="view_applicant_email" class="text-muted"></p>
                </div>
                <div class="mb-3">
                    <label class="form-label"><strong>Phone:</strong></label>
                    <p id="view_applicant_phone" class="text-muted"></p>
                </div>
                <div class="mb-3">
                    <label class="form-label"><strong>Position:</strong></label>
                    <p id="view_position" class="text-muted"></p>
                </div>
                <div class="mb-3">
                    <label class="form-label"><strong>Applied Date:</strong></label>
                    <p id="view_applied_date" class="text-muted"></p>
                </div>
                <div class="mb-3">
                    <label class="form-label"><strong>Cover Letter:</strong></label>
                    <p id="view_cover_letter" class="text-muted" style="white-space: pre-wrap;"></p>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
            </div>
        </div>
    </div>
</div>

<script>
let viewModal;

document.addEventListener('DOMContentLoaded', function() {
    viewModal = new bootstrap.Modal(document.getElementById('viewModal'));
});

function viewApplication(appId, appData) {
    document.getElementById('view_applicant_name').textContent = appData.applicant_name;
    document.getElementById('view_applicant_email').textContent = appData.applicant_email;
    document.getElementById('view_applicant_phone').textContent = appData.applicant_phone;
    document.getElementById('view_position').textContent = appData.position.replace(/_/g, ' ').replace(/\b\w/g, l => l.toUpperCase());
    document.getElementById('view_applied_date').textContent = new Date(appData.applied_at).toLocaleDateString('en-US', { month: 'long', day: 'numeric', year: 'numeric' });
    document.getElementById('view_cover_letter').textContent = appData.cover_letter || 'No cover letter provided';
    viewModal.show();
}

function approveApplication(appId, applicantName) {
    if (!confirm(`Are you sure you want to approve ${applicantName}'s application?`)) {
        return;
    }

    fetch('/livestock-owner/approve-employee-application', {
        method: 'POST',
        headers: {'Content-Type': 'application/x-www-form-urlencoded'},
        body: `application_id=${appId}`
    })
    .then(res => res.json())
    .then(data => {
        if (data.success) {
            alert(data.message);
            location.reload();
        } else {
            alert('Error: ' + data.message);
        }
    })
    .catch(err => {
        alert('Error: ' + err.message);
    });
}

function rejectApplication(appId, applicantName) {
    const reason = prompt(`Please provide a reason for rejecting ${applicantName}'s application:`);
    
    if (!reason || reason.trim() === '') {
        alert('Rejection reason is required');
        return;
    }

    fetch('/livestock-owner/reject-employee-application', {
        method: 'POST',
        headers: {'Content-Type': 'application/x-www-form-urlencoded'},
        body: `application_id=${appId}&reason=${encodeURIComponent(reason)}`
    })
    .then(res => res.json())
    .then(data => {
        if (data.success) {
            alert(data.message);
            location.reload();
        } else {
            alert('Error: ' + data.message);
        }
    })
    .catch(err => {
        alert('Error: ' + err.message);
    });
}
</script>

<style>
.page-header {
    margin-bottom: 30px;
}

.page-header h1 {
    font-size: 28px;
    color: #333;
    margin-bottom: 5px;
}

.page-header p {
    color: #666;
    font-size: 14px;
}

.card {
    box-shadow: 0 0 10px rgba(0, 0, 0, 0.1);
    border-radius: 8px;
    margin-bottom: 20px;
}

.card-header {
    background-color: #f8f9fa;
    border-bottom: 1px solid #dee2e6;
    padding: 15px 20px;
}

.card-header h5 {
    margin: 0;
    color: #333;
}

.badge {
    padding: 5px 10px;
    font-size: 12px;
}

.btn-sm {
    padding: 4px 8px;
    font-size: 12px;
    margin-right: 3px;
}

.modal-content {
    border-radius: 8px;
}

.modal-header {
    background-color: #f8f9fa;
    border-bottom: 1px solid #dee2e6;
}
</style>

<?php
/**
 * Site Administration - Employee Management for Livestock Owners
 */

$pageTitle = 'Site Administration';
ob_start();
?>

<div class="page-header mb-4">
    <h2>Site Administration</h2>
    <p class="text-muted">Manage your piggery employees and review applications</p>
</div>

<!-- Pending Employee Applications -->
<?php 
$pending_apps = array_filter($applications ?? [], function($app) {
    return $app['status'] === 'pending';
});
?>

<?php if (!empty($pending_apps)): ?>
<div class="card mb-4 border-warning">
    <div class="card-header" style="background: linear-gradient(135deg, #ffc107 0%, #ff9800 100%); color: #000;">
        <h5 class="mb-0"><i class="fas fa-clock"></i> Pending Employee Applications (<?php echo count($pending_apps); ?>)</h5>
    </div>
    <div class="card-body">
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
                            <td><?php echo htmlspecialchars($app['applicant_phone'] ?? 'N/A'); ?></td>
                            <td>
                                <span class="badge bg-info">
                                    <?php echo ucwords(str_replace('_', ' ', $app['position'])); ?>
                                </span>
                            </td>
                            <td><?php echo date('M d, Y', strtotime($app['applied_at'])); ?></td>
                            <td>
                                <button class="btn btn-sm btn-success me-1" onclick="approveApplication(<?php echo $app['id']; ?>, '<?php echo htmlspecialchars($app['applicant_name']); ?>')">
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
    </div>
</div>
<?php endif; ?>

<!-- Assign New Employee Section -->
<div class="card mb-4">
    <div class="card-header" style="background: linear-gradient(135deg, #dc3545 0%, #c82333 100%); color: white;">
        <h5 class="mb-0"><i class="fas fa-user-plus"></i> Assign Piggery Employee</h5>
    </div>
    <div class="card-body">
        <form id="assignEmployeeForm">
            <div class="row">
                <div class="col-md-6">
                    <div class="form-group mb-3">
                        <label for="employee_user_id" class="form-label">Select User <span class="text-danger">*</span></label>
                        <select class="form-control" id="employee_user_id" name="employee_user_id" required>
                            <option value="">-- Select User --</option>
                            <?php foreach ($available_users ?? [] as $user): ?>
                                <option value="<?php echo $user['id']; ?>">
                                    <?php echo htmlspecialchars($user['name']); ?> (<?php echo htmlspecialchars($user['email']); ?>)
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                </div>

                <div class="col-md-4">
                    <div class="form-group mb-3">
                        <label for="assigned_role" class="form-label">Assign Role <span class="text-danger">*</span></label>
                        <select class="form-control" id="assigned_role" name="assigned_role" required>
                            <option value="">-- Select Role --</option>
                            <option value="pig_caretaker">Pig Caretaker</option>
                            <option value="lechonero">Lechonero</option>
                            <option value="pig_slaughter">Pig Slaughter</option>
                            <option value="logistics">Logistics/Driver</option>
                        </select>
                    </div>
                </div>

                <div class="col-md-2">
                    <label class="form-label d-none d-md-block">&nbsp;</label>
                    <button type="submit" class="btn btn-danger w-100">
                        <i class="fas fa-plus"></i> Assign
                    </button>
                </div>
            </div>
        </form>
    </div>
</div>


<!-- Current Employees List -->
<div class="card mb-4">
    <div class="card-header" style="background: linear-gradient(135deg, #dc3545 0%, #c82333 100%); color: white;">
        <h5 class="mb-0"><i class="fas fa-users"></i> Current Employees</h5>
    </div>
    <div class="card-body">
        <?php if (empty($employees)): ?>
            <div class="alert alert-info">
                <i class="fas fa-info-circle"></i> You haven't assigned any employees yet.
            </div>
        <?php else: ?>
            <div class="table-responsive">
                <table class="table table-hover">
                    <thead>
                        <tr>
                            <th>Name</th>
                            <th>Email</th>
                            <th>Phone</th>
                            <th>Role</th>
                            <th>Assigned Date</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($employees as $emp): ?>
                            <tr>
                                <td><?php echo htmlspecialchars($emp['name']); ?></td>
                                <td><?php echo htmlspecialchars($emp['email']); ?></td>
                                <td><?php echo htmlspecialchars($emp['phone'] ?? 'N/A'); ?></td>
                                <td>
                                    <span class="badge bg-<?php
                                        echo $emp['assigned_role'] === 'pig_caretaker' ? 'primary' :
                                            ($emp['assigned_role'] === 'lechonero' ? 'warning' :
                                            ($emp['assigned_role'] === 'pig_slaughter' ? 'danger' : 'info'));
                                    ?>">
                                        <?php echo ucwords(str_replace('_', ' ', $emp['assigned_role'])); ?>
                                    </span>
                                </td>
                                <td><?php echo date('M d, Y', strtotime($emp['assigned_at'])); ?></td>
                                <td>
                                    <button class="btn btn-sm btn-danger" onclick="removeEmployee(<?php echo $emp['id']; ?>, '<?php echo htmlspecialchars($emp['name']); ?>')">
                                        <i class="fas fa-trash"></i> Remove
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
<?php 
$history_apps = array_filter($applications ?? [], function($app) {
    return $app['status'] !== 'pending';
});
?>

<?php if (!empty($history_apps)): ?>
<div class="card mb-4">
    <div class="card-header" style="background-color: #f8f9fa; border-bottom: 2px solid #dee2e6;">
        <h5 class="mb-0"><i class="fas fa-history"></i> Application History</h5>
    </div>
    <div class="card-body">
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
    </div>
</div>
<?php endif; ?>

<script>
document.getElementById('assignEmployeeForm').addEventListener('submit', function(e) {
    e.preventDefault();

    const formData = new FormData(this);

    fetch('/site-administration/assign', {
        method: 'POST',
        body: new URLSearchParams(formData)
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
});

function removeEmployee(assignmentId, employeeName) {
    if (!confirm(`Are you sure you want to remove ${employeeName} from your piggery?`)) {
        return;
    }

    fetch('/site-administration/remove', {
        method: 'POST',
        headers: {'Content-Type': 'application/x-www-form-urlencoded'},
        body: `assignment_id=${assignmentId}`
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

function approveApplication(appId, applicantName) {
    if (!confirm(`Are you sure you want to approve ${applicantName}'s application?`)) {
        return;
    }

    fetch('/site-administration/approve-employee-application', {
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

    fetch('/site-administration/reject-employee-application', {
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
    padding: 4px 12px;
    font-size: 12px;
}

.form-label {
    font-weight: 600;
    color: #333;
}

.text-danger {
    color: #dc3545;
}

.btn-primary {
    background-color: #dc3545;
    border-color: #dc3545;
}

.btn-primary:hover {
    background-color: #c82333;
    border-color: #bd2130;
}
</style>

<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">

<?php
$content = ob_get_clean();
require_once __DIR__ . '/../layouts/dashboard-layout.php';
?>

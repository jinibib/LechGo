<?php
/**
 * Employee Application Form
 * Allows users to apply as piggery employees
 */

$currentPage = 'employee-apply';

// Check if user is already an employee
$user = $_SESSION['user'] ?? null;
if ($user && in_array($user['role'], ['pig_caretaker', 'lechonero', 'pig_slaughter', 'logistics'])) {
    // User is already an employee, redirect to profile
    $_SESSION['info'] = 'You are already employed as ' . ucwords(str_replace('_', ' ', $user['role']));
    header('Location: /customer/profile');
    exit;
}

// Variables are passed from controller:
// - $livestock_owners (array)
// - $my_applications (array)
// - $applied_owner_ids (array)
// - $user is available from $_SESSION
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Apply as Employee - LechGO</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <link rel="stylesheet" href="/styles.css">
    <style>
        /* Page Header Section */
        .page-header-section {
            background: linear-gradient(135deg, #dc3545 0%, #c82333 100%);
            padding: 30px;
            border-radius: 12px;
            margin-bottom: 30px;
            box-shadow: 0 4px 15px rgba(220, 53, 69, 0.3);
            display: flex;
            align-items: center;
            gap: 20px;
        }

        .page-icon {
            background: rgba(255, 255, 255, 0.2);
            width: 80px;
            height: 80px;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            flex-shrink: 0;
        }

        .page-icon i {
            font-size: 36px;
            color: white;
        }

        .page-header-text h2 {
            color: white;
            margin: 0 0 10px 0;
            font-size: 28px;
            font-weight: 700;
        }

        .page-header-text p {
            color: rgba(255, 255, 255, 0.9);
            margin: 0;
            font-size: 15px;
            line-height: 1.6;
        }

        /* Card Header Improvements */
        .card-header-content {
            display: flex;
            justify-content: space-between;
            align-items: center;
            width: 100%;
        }

        .card-header h5 {
            margin: 0;
            display: flex;
            align-items: center;
            gap: 10px;
        }

        .card-header h5 i {
            color: #dc3545;
        }

        /* Badge Styling */
        .badge {
            padding: 6px 12px;
            font-size: 13px;
            font-weight: 600;
            border-radius: 20px;
        }

        .bg-primary {
            background-color: #dc3545 !important;
        }

        /* Table Improvements */
        .table {
            margin-bottom: 0;
        }

        .table thead th {
            background: linear-gradient(to right, #f8f9fa, #ffe6e8);
            border-bottom: 2px solid #dc3545;
            text-transform: uppercase;
            font-size: 12px;
            letter-spacing: 0.5px;
            color: #495057;
        }

        .table tbody tr {
            transition: all 0.3s ease;
        }

        .table tbody tr:hover {
            background-color: #fff5f5;
            transform: scale(1.01);
            box-shadow: 0 2px 8px rgba(220, 53, 69, 0.1);
        }

        /* Alert Improvements */
        .alert {
            border-left: 4px solid;
            border-radius: 8px;
            padding: 15px 20px;
            display: flex;
            align-items: center;
            gap: 10px;
        }

        .alert i {
            font-size: 20px;
        }

        .alert-info {
            background-color: #e3f2fd;
            border-color: #2196f3;
            color: #0d47a1;
        }

        .alert-danger {
            background-color: #ffebee;
            border-color: #f44336;
            color: #c62828;
        }

        .alert-success {
            background-color: #e8f5e9;
            border-color: #4caf50;
            color: #2e7d32;
        }

        /* Button Improvements */
        .btn-primary {
            background: linear-gradient(135deg, #dc3545 0%, #c82333 100%);
            border: none;
            padding: 8px 20px;
            font-weight: 600;
            box-shadow: 0 2px 8px rgba(220, 53, 69, 0.3);
            transition: all 0.3s ease;
        }

        .btn-primary:hover {
            transform: translateY(-2px);
            box-shadow: 0 4px 12px rgba(220, 53, 69, 0.4);
            background: linear-gradient(135deg, #c82333 0%, #bd2130 100%);
        }

        .btn-secondary {
            background: #6c757d;
            border: none;
            padding: 8px 20px;
            font-weight: 600;
            transition: all 0.3s ease;
        }

        .btn-secondary:hover {
            background: #5a6268;
            transform: translateY(-2px);
        }

        /* Modal Styling */
        .modal {
            display: none;
            position: fixed;
            z-index: 9999;
            left: 0;
            top: 0;
            width: 100%;
            height: 100%;
            background-color: rgba(0,0,0,0.5);
            animation: fadeIn 0.3s ease;
        }

        @keyframes fadeIn {
            from { opacity: 0; }
            to { opacity: 1; }
        }

        .modal.show {
            display: block;
        }

        .modal-dialog {
            position: relative;
            margin: 50px auto;
            max-width: 500px;
            animation: slideDown 0.3s ease;
        }

        @keyframes slideDown {
            from {
                transform: translateY(-50px);
                opacity: 0;
            }
            to {
                transform: translateY(0);
                opacity: 1;
            }
        }

        .modal-content {
            background-color: white;
            border-radius: 12px;
            box-shadow: 0 10px 40px rgba(0,0,0,0.3);
            overflow: hidden;
        }

        .modal-header {
            background: linear-gradient(135deg, #dc3545 0%, #c82333 100%);
            color: white;
            padding: 25px;
            border-bottom: none;
        }

        .modal-title {
            margin: 0;
            font-size: 22px;
            font-weight: 700;
        }

        .modal-body {
            padding: 30px;
        }

        .modal-body .mb-3:last-child {
            margin-bottom: 0 !important;
        }

        .modal-body p {
            background: #f8f9fa;
            padding: 12px 15px;
            border-radius: 8px;
            border-left: 4px solid #dc3545;
        }

        .modal-body small {
            display: block;
            margin-top: 8px;
            font-size: 12px;
        }

        .modal-footer {
            padding: 20px 25px;
            border-top: 1px solid #e9ecef;
            display: flex;
            justify-content: flex-end;
            gap: 10px;
            background-color: #f8f9fa;
        }

        .btn-close {
            background: rgba(255, 255, 255, 0.2);
            border: none;
            color: white;
            font-size: 24px;
            cursor: pointer;
            position: absolute;
            right: 20px;
            top: 20px;
            width: 35px;
            height: 35px;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            transition: all 0.3s ease;
        }

        .btn-close:hover {
            background: rgba(255, 255, 255, 0.3);
            transform: rotate(90deg);
        }

        .form-label {
            font-weight: 600;
            color: #495057;
            margin-bottom: 8px;
        }

        .form-control, .form-select {
            border: 2px solid #e9ecef;
            border-radius: 8px;
            padding: 10px 15px;
            transition: all 0.3s ease;
        }

        .form-control:focus, .form-select:focus {
            border-color: #dc3545;
            box-shadow: 0 0 0 3px rgba(220, 53, 69, 0.1);
        }

        /* Empty State */
        .alert-info {
            text-align: center;
            padding: 40px 20px;
        }

        .alert-info i {
            font-size: 48px;
            display: block;
            margin-bottom: 15px;
            opacity: 0.5;
        }
    </style>
</head>
<body>
    <div class="dashboard-layout">
        <?php include __DIR__ . '/../layouts/sidebar.php'; ?>
        
        <main class="dashboard-main">
            <div class="dashboard-topbar">
                <button class="dashboard-mobile-toggle" id="sidebarToggle">☰</button>
                <h1 class="dashboard-topbar-title">Apply as Piggery Employee</h1>
                <div class="dashboard-topbar-actions">
                    <span class="dashboard-topbar-date"><?php echo date('l, F j, Y'); ?></span>
                </div>
            </div>

            <div class="page-content">

                <!-- Page Header with Description -->
                <div class="page-header-section">
                    <div class="page-icon">
                        <i class="fas fa-briefcase"></i>
                    </div>
                    <div class="page-header-text">
                        <h2>Apply as Piggery Employee</h2>
                        <p>Join a piggery farm and start working as an employee. Browse available farms below and submit your application.</p>
                    </div>
                </div>

                <!-- Flash Messages -->
                <?php if (isset($_SESSION['error'])): ?>
                    <div class="alert alert-danger alert-dismissible fade show" role="alert">
                        <i class="fas fa-exclamation-circle"></i>
                        <?php echo htmlspecialchars($_SESSION['error']); unset($_SESSION['error']); ?>
                        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                    </div>
                <?php endif; ?>

                <?php if (isset($_SESSION['success'])): ?>
                    <div class="alert alert-success alert-dismissible fade show" role="alert">
                        <i class="fas fa-check-circle"></i>
                        <?php echo htmlspecialchars($_SESSION['success']); unset($_SESSION['success']); ?>
                        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                    </div>
                <?php endif; ?>

                <!-- Available Piggery Farms -->
                <div class="card">
                    <div class="card-header">
                        <div class="card-header-content">
                            <h5 class="mb-0">
                                <i class="fas fa-store"></i> Available Piggery Farms
                            </h5>
                            <span class="badge bg-primary"><?php echo isset($livestock_owners) ? count($livestock_owners) : 0; ?> Farms</span>
                        </div>
                    </div>
                    <div class="card-body">
                        <?php if (!isset($livestock_owners) || empty($livestock_owners)): ?>
                            <div class="alert alert-info">
                                <i class="fas fa-info-circle"></i> No piggery farms available at this time.
                            </div>
                        <?php else: ?>
                            <div class="table-responsive">
                                <table class="table table-hover">
                                    <thead>
                                        <tr>
                                            <th>Farm Name</th>
                                            <th>Location</th>
                                            <th>Contact</th>
                                            <th>Owner</th>
                                            <th>Action</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php foreach ($livestock_owners as $owner): ?>
                                            <tr>
                                                <td><strong><?php echo htmlspecialchars($owner['farm_name']); ?></strong></td>
                                                <td><?php echo htmlspecialchars($owner['location']); ?></td>
                                                <td><?php echo htmlspecialchars($owner['contact_number']); ?></td>
                                                <td><?php echo htmlspecialchars($owner['owner_name']); ?></td>
                                                <td>
                                                    <?php if (isset($applied_owner_ids) && in_array($owner['owner_id'], $applied_owner_ids)): ?>
                                                        <span class="badge bg-warning">Application Pending</span>
                                                    <?php else: ?>
                                                        <button class="btn btn-sm btn-primary" onclick="showApplicationModal(<?php echo $owner['owner_id']; ?>, '<?php echo htmlspecialchars($owner['farm_name']); ?>')">
                                                            <i class="fas fa-paper-plane"></i> Apply
                                                        </button>
                                                    <?php endif; ?>
                                                </td>
                                            </tr>
                                        <?php endforeach; ?>
                                    </tbody>
                                </table>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>

                <!-- My Applications -->
                <?php if (isset($my_applications) && !empty($my_applications)): ?>
                <div class="card" style="margin-top: 30px;">
                    <div class="card-header">
                        <div class="card-header-content">
                            <h5 class="mb-0">
                                <i class="fas fa-file-alt"></i> My Applications
                            </h5>
                            <span class="badge bg-info"><?php echo count($my_applications); ?> Application<?php echo count($my_applications) > 1 ? 's' : ''; ?></span>
                        </div>
                    </div>
                    <div class="card-body">
                        <div class="table-responsive">
                            <table class="table table-hover">
                                <thead>
                                    <tr>
                                        <th>Farm Name</th>
                                        <th>Position</th>
                                        <th>Applied Date</th>
                                        <th>Status</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($my_applications as $app): ?>
                                        <tr>
                                            <td><?php echo htmlspecialchars($app['farm_name']); ?></td>
                                            <td>
                                                <span class="badge bg-info">
                                                    <?php echo ucwords(str_replace('_', ' ', $app['position'])); ?>
                                                </span>
                                            </td>
                                            <td><?php echo date('M d, Y', strtotime($app['applied_at'])); ?></td>
                                            <td>
                                                <?php 
                                                $status_class = '';
                                                switch ($app['status']) {
                                                    case 'pending':
                                                        $status_class = 'bg-warning';
                                                        break;
                                                    case 'approved':
                                                        $status_class = 'bg-success';
                                                        break;
                                                    case 'rejected':
                                                        $status_class = 'bg-danger';
                                                        break;
                                                }
                                                ?>
                                                <span class="badge <?php echo $status_class; ?>">
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
            </div>
        </main>
    </div>

<!-- Application Modal -->
<div class="modal fade" id="applicationModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title"><i class="fas fa-paper-plane"></i> Apply as Employee</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal">×</button>
            </div>
            <form id="applicationForm">
                <div class="modal-body">
                    <input type="hidden" id="owner_id" name="owner_id">
                    
                    <div class="mb-3">
                        <label class="form-label"><strong>Farm:</strong></label>
                        <p id="farm_name_display" class="text-muted" style="font-size: 16px; margin: 0;"></p>
                    </div>

                    <div class="mb-3">
                        <label for="position" class="form-label">Position Applying For <span class="text-danger">*</span></label>
                        <select class="form-control" id="position" name="position" required>
                            <option value="">-- Select Position --</option>
                            <option value="pig_caretaker">Pig Caretaker</option>
                            <option value="lechonero">Lechonero</option>
                            <option value="pig_slaughter">Pig Slaughter</option>
                            <option value="logistics">Logistics/Driver</option>
                        </select>
                        <small class="text-muted">Choose the role you want to apply for at this farm.</small>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">
                        <i class="fas fa-times"></i> Cancel
                    </button>
                    <button type="submit" class="btn btn-primary">
                        <i class="fas fa-paper-plane"></i> Submit Application
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
let applicationModal;

document.addEventListener('DOMContentLoaded', function() {
    applicationModal = new bootstrap.Modal(document.getElementById('applicationModal'));
});

function showApplicationModal(ownerId, farmName) {
    document.getElementById('owner_id').value = ownerId;
    document.getElementById('farm_name_display').textContent = farmName;
    document.getElementById('position').value = '';
    applicationModal.show();
}

document.getElementById('applicationForm').addEventListener('submit', function(e) {
    e.preventDefault();

    const formData = new FormData(this);
    const submitBtn = this.querySelector('button[type="submit"]');
    submitBtn.disabled = true;
    submitBtn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Submitting...';

    fetch('/employee/submit-application', {
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
            submitBtn.disabled = false;
            submitBtn.innerHTML = 'Submit Application';
        }
    })
    .catch(err => {
        alert('Error: ' + err.message);
        submitBtn.disabled = false;
        submitBtn.innerHTML = 'Submit Application';
    });
});
</script>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>

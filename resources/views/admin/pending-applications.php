<?php
/**
 * System Admin - Pending Role Applications
 */

$pageTitle = 'Pending Applications';
$content = '';
ob_start();
?>

<style>
.page-header {
    margin-bottom: 20px;
}

.page-header h1 {
    font-size: 24px;
    color: #333;
    margin-bottom: 5px;
    font-weight: 600;
}

.page-header p {
    color: #666;
    font-size: 14px;
    margin: 0;
}

.card {
    background: white;
    box-shadow: 0 2px 8px rgba(0, 0, 0, 0.08);
    border-radius: 8px;
    border: none;
    margin-bottom: 20px;
    overflow: hidden;
}

.card-body {
    padding: 0;
}

.table-responsive {
    overflow-x: auto;
    -webkit-overflow-scrolling: touch;
}

.applications-table {
    width: 100%;
    min-width: 100%;
    border-collapse: collapse;
    margin: 0;
}

.applications-table thead {
    background-color: #f8f9fa;
}

.applications-table th {
    padding: 14px 16px;
    text-align: left;
    font-weight: 600;
    font-size: 13px;
    color: #495057;
    white-space: nowrap;
    border-bottom: 2px solid #dee2e6;
}

.applications-table tbody tr {
    border-bottom: 1px solid #dee2e6;
    transition: background-color 0.2s ease;
}

.applications-table tbody tr:hover {
    background-color: #f8f9fa;
}

.applications-table td {
    padding: 14px 16px;
    vertical-align: middle;
    font-size: 14px;
    color: #212529;
}

.badge {
    padding: 6px 12px;
    font-size: 11px;
    font-weight: 600;
    border-radius: 4px;
    display: inline-block;
    white-space: nowrap;
    text-transform: uppercase;
    letter-spacing: 0.3px;
}

.bg-primary {
    background-color: #0d6efd;
    color: white;
}

.bg-secondary {
    background-color: #6c757d;
    color: white;
}

.btn-sm {
    padding: 7px 14px;
    font-size: 13px;
    border-radius: 4px;
    border: none;
    cursor: pointer;
    transition: all 0.2s ease;
    font-weight: 500;
}

.btn-info {
    background-color: #0dcaf0;
    color: #000;
    display: inline-flex;
    align-items: center;
    gap: 5px;
}

.btn-info:hover {
    background-color: #31d2f2;
    transform: translateY(-1px);
    box-shadow: 0 2px 4px rgba(13, 202, 240, 0.3);
}

.alert {
    padding: 12px 16px;
    border-radius: 4px;
    margin-bottom: 20px;
    border-left: 4px solid;
}

.alert-info {
    background-color: #cff4fc;
    border-left-color: #0dcaf0;
    color: #055160;
}

/* Responsive adjustments */
@media (max-width: 768px) {
    .applications-table {
        font-size: 12px;
    }
    
    .applications-table th,
    .applications-table td {
        padding: 10px 12px;
    }
    
    .badge {
        font-size: 10px;
        padding: 4px 8px;
    }
    
    .btn-sm {
        padding: 5px 10px;
        font-size: 11px;
    }
}
</style>

<div class="page-header">
    <h1>Pending Role Applications</h1>
    <p>Review and approve user role applications</p>
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

<div class="card">
    <div class="card-body">
        <?php if (empty($pending_applications)): ?>
            <div class="alert alert-info" style="margin: 20px;">
                <i class="fas fa-info-circle"></i> No pending applications at this time.
            </div>
        <?php else: ?>
            <div class="table-responsive">
                <table class="applications-table">
                    <thead>
                        <tr>
                            <th>Applicant</th>
                            <th>Email</th>
                            <th>Phone</th>
                            <th>Application Type</th>
                            <th>Applied Date</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($pending_applications as $app): ?>
                            <tr>
                                <td><?php echo htmlspecialchars($app['user_name']); ?></td>
                                <td><?php echo htmlspecialchars($app['user_email']); ?></td>
                                <td><?php echo htmlspecialchars($app['user_phone']); ?></td>
                                <td>
                                    <span class="badge bg-<?php echo $app['application_type'] === 'piggery_owner' ? 'primary' : 'secondary'; ?>">
                                        <?php echo ucwords(str_replace('_', ' ', $app['application_type'])); ?>
                                    </span>
                                </td>
                                <td><?php echo date('M d, Y', strtotime($app['created_at'])); ?></td>
                                <td>
                                    <button class="btn btn-sm btn-info" onclick="viewDetails(<?php echo $app['id']; ?>)">
                                        <i class="fas fa-eye"></i> View
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

<!-- Application Details Modal -->
<div class="modal fade" id="applicationModal" tabindex="-1">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Application Details</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body" id="modalContent">
                <!-- Content loaded dynamically -->
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
                <button type="button" class="btn btn-danger" onclick="rejectApplication()">Reject</button>
                <button type="button" class="btn btn-success" onclick="approveApplication()">Approve</button>
            </div>
        </div>
    </div>
</div>

<script>
let currentApplicationId = null;

const applicationsData = <?php echo json_encode($pending_applications); ?>;

function viewDetails(appId) {
    const app = applicationsData.find(a => a.id == appId);
    if (!app) return;

    currentApplicationId = appId;
    
    let content = `
        <div class="row">
            <div class="col-md-6">
                <h6>Applicant Information</h6>
                <table class="table table-sm">
                    <tr><th>Name:</th><td>${app.user_name}</td></tr>
                    <tr><th>Email:</th><td>${app.user_email}</td></tr>
                    <tr><th>Phone:</th><td>${app.user_phone}</td></tr>
                    <tr><th>Type:</th><td><span class="badge bg-primary">${app.application_type.replace('_', ' ').toUpperCase()}</span></td></tr>
                </table>
            </div>
            <div class="col-md-6">
                <h6>Application Date</h6>
                <p>${new Date(app.created_at).toLocaleString()}</p>
            </div>
        </div>
    `;

    if (app.application_type === 'piggery_owner') {
        content += `
            <hr>
            <h6>Farm Details</h6>
            <table class="table table-sm">
                <tr><th>Farm Name:</th><td>${app.farm_name || 'N/A'}</td></tr>
                <tr><th>Location:</th><td>${app.farm_location || 'N/A'}</td></tr>
            </table>
            
            <h6>Proof Documents</h6>
            <div class="row">
        `;

        if (app.proof_documents && app.proof_documents.length > 0) {
            app.proof_documents.forEach(doc => {
                const isImage = doc.match(/\\.(jpg|jpeg|png)$/i);
                if (isImage) {
                    content += `
                        <div class="col-md-4 mb-3">
                            <a href="/${doc}" target="_blank">
                                <img src="/${doc}" class="img-thumbnail" style="max-height: 150px; width: 100%; object-fit: cover;">
                            </a>
                        </div>
                    `;
                } else {
                    content += `
                        <div class="col-md-4 mb-3">
                            <a href="/${doc}" target="_blank" class="btn btn-outline-primary btn-sm">
                                <i class="fas fa-file-pdf"></i> View PDF
                            </a>
                        </div>
                    `;
                }
            });
        } else {
            content += '<p class="text-muted">No documents uploaded</p>';
        }

        content += '</div>';
    }

    content += `
        <hr>
        <div class="form-group">
            <label for="remarks">Remarks (Optional)</label>
            <textarea class="form-control" id="remarks" rows="3" placeholder="Add any remarks or notes..."></textarea>
        </div>
    `;

    document.getElementById('modalContent').innerHTML = content;
    new bootstrap.Modal(document.getElementById('applicationModal')).show();
}

function approveApplication() {
    if (!currentApplicationId) return;

    if (!confirm('Are you sure you want to approve this application?')) return;

    const remarks = document.getElementById('remarks').value;

    fetch('/api_approve_application.php', {
        method: 'POST',
        headers: {'Content-Type': 'application/x-www-form-urlencoded'},
        body: `application_id=${currentApplicationId}&remarks=${encodeURIComponent(remarks)}`
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
        console.error('Fetch error:', err);
        alert('Error: ' + err.message);
    });
}

function rejectApplication() {
    if (!currentApplicationId) return;

    const remarks = document.getElementById('remarks').value;
    if (!remarks.trim()) {
        alert('Please provide a reason for rejection in the remarks field.');
        return;
    }

    if (!confirm('Are you sure you want to reject this application?')) return;

    fetch('/api_reject_application.php', {
        method: 'POST',
        headers: {'Content-Type': 'application/x-www-form-urlencoded'},
        body: `application_id=${currentApplicationId}&remarks=${encodeURIComponent(remarks)}`
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
        console.error('Fetch error:', err);
        alert('Error: ' + err.message);
    });
}
</script>

<?php
$content = ob_get_clean();
require_once __DIR__ . '/../layouts/dashboard-layout.php';
?>

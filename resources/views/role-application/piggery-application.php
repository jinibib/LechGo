<?php
/**
 * Piggery Application Form
 * Users apply to become livestock owners with document verification
 */

$page_title = 'Apply as Piggery Owner';
require_once __DIR__ . '/../layouts/dashboard-layout.php';
?>

<div class="content-wrapper">
    <div class="container-fluid">
        <div class="row">
            <div class="col-12">
                <div class="page-header">
                    <h1>Apply as Piggery Owner</h1>
                    <p>Submit your piggery details and proof documents for verification</p>
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
                        <form method="POST" action="/role-application/piggery/submit" enctype="multipart/form-data">
                            <div class="row">
                                <div class="col-md-6">
                                    <div class="form-group mb-3">
                                        <label for="farm_name" class="form-label">Farm Name <span class="text-danger">*</span></label>
                                        <input type="text" class="form-control" id="farm_name" name="farm_name" required>
                                    </div>
                                </div>

                                <div class="col-md-6">
                                    <div class="form-group mb-3">
                                        <label for="city" class="form-label">City <span class="text-danger">*</span></label>
                                        <input type="text" class="form-control" id="city" name="city" value="Davao City" readonly>
                                    </div>
                                </div>
                            </div>

                            <div class="row">
                                <div class="col-md-6">
                                    <div class="form-group mb-3">
                                        <label for="municipality" class="form-label">Municipality/District <span class="text-danger">*</span></label>
                                        <select class="form-control" id="municipality" name="municipality" required>
                                            <option value="">Select Municipality/District</option>
                                            <option value="Tugbok">Tugbok</option>
                                            <option value="Cabantian">Cabantian</option>
                                            <option value="Toril">Toril</option>
                                            <option value="Bajada">Bajada</option>
                                            <option value="Agdao">Agdao</option>
                                            <option value="Poblacion">Poblacion</option>
                                            <option value="Matina">Matina</option>
                                            <option value="Lanang">Lanang</option>
                                            <option value="Mintal">Mintal</option>
                                            <option value="Talomo">Talomo</option>
                                        </select>
                                    </div>
                                </div>

                                <div class="col-md-6">
                                    <div class="form-group mb-3">
                                        <label for="barangay" class="form-label">Barangay <span class="text-danger">*</span></label>
                                        <input type="text" class="form-control" id="barangay" name="barangay" required>
                                    </div>
                                </div>
                            </div>

                            <div class="form-group mb-3">
                                <label for="street" class="form-label">Street Address <span class="text-danger">*</span></label>
                                <input type="text" class="form-control" id="street" name="street" required>
                            </div>

                            <div class="form-group mb-4">
                                <label for="documents" class="form-label">Proof Documents <span class="text-danger">*</span></label>
                                <input type="file" class="form-control" id="documents" name="documents[]" multiple accept=".jpg,.jpeg,.png,.pdf" required>
                                <small class="form-text text-muted">
                                    Upload documents proving you own a piggery (e.g., business permits, photos of farm, barangay clearance).
                                    Accepted formats: JPG, PNG, PDF. Max 5MB per file.
                                </small>
                                <div id="file-preview" class="mt-2"></div>
                            </div>

                            <div class="form-group">
                                <button type="submit" class="btn btn-primary">Submit Application</button>
                                <a href="/dashboard" class="btn btn-secondary">Cancel</a>
                            </div>
                        </form>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
document.getElementById('documents').addEventListener('change', function(e) {
    const preview = document.getElementById('file-preview');
    preview.innerHTML = '';
    
    const files = e.target.files;
    if (files.length > 0) {
        const list = document.createElement('ul');
        list.className = 'list-group';
        
        for (let i = 0; i < files.length; i++) {
            const item = document.createElement('li');
            item.className = 'list-group-item';
            item.textContent = files[i].name + ' (' + (files[i].size / 1024).toFixed(2) + ' KB)';
            list.appendChild(item);
        }
        
        preview.appendChild(list);
    }
});
</script>

<style>
.card {
    box-shadow: 0 0 10px rgba(0, 0, 0, 0.1);
    border-radius: 8px;
}

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
    padding: 10px 30px;
}

.btn-primary:hover {
    background-color: #c82333;
    border-color: #bd2130;
}

.btn-secondary {
    padding: 10px 30px;
}
</style>

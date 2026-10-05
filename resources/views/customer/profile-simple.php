<?php
/**
 * Customer Profile Page - Simple Version with Modal
 */

$session = new Session();
$user = $session->getUser();

// Check for pending application
require_once APP_PATH . '/models/RoleApplication.php';
$roleApp = new RoleApplication($GLOBALS['conn']);
$hasPending = $roleApp->hasPendingApplication($user['id']);
$applications = $roleApp->getUserApplications($user['id']);

// Check employee status
$employeeStatus = null;
$employeeRole = null;
if (in_array($user['role'], ['pig_caretaker', 'lechonero', 'pig_slaughter', 'logistics'])) {
    require_once APP_PATH . '/models/EmployeeAssignment.php';
    $employeeAssignment = new EmployeeAssignment($GLOBALS['conn']);
    
    // Get employee assignment details
    $query = "SELECT ea.*, lo.farm_name, lo.location, lo.contact_number, u.name as owner_name
              FROM employee_assignments ea
              JOIN livestock_owners lo ON ea.livestock_owner_id = lo.id
              JOIN users u ON lo.user_id = u.id
              WHERE ea.employee_user_id = ? AND ea.status = 'active'
              ORDER BY ea.assigned_at DESC
              LIMIT 1";
    $stmt = $GLOBALS['conn']->prepare($query);
    $stmt->bind_param("i", $user['id']);
    $stmt->execute();
    $result = $stmt->get_result();
    if ($result->num_rows > 0) {
        $employeeStatus = $result->fetch_assoc();
        $employeeRole = $employeeStatus['assigned_role'];
    }
    $stmt->close();
}

// Get Davao City districts from locations table
$districts = [];
try {
    $query = "SELECT DISTINCT municipality FROM locations WHERE city = 'Davao City' ORDER BY municipality";
    $result = $GLOBALS['conn']->query($query);
    if ($result && $result->num_rows > 0) {
        while ($row = $result->fetch_assoc()) {
            $districts[] = $row['municipality'];
        }
    }
    // Debug: check if we got districts
    if (empty($districts)) {
        error_log("No districts found from locations table");
    }
} catch (Exception $e) {
    error_log("Error loading districts: " . $e->getMessage());
}

// Start output buffering
ob_start();
?>

<div style="padding: 20px;">
    <!-- Flash Messages -->
    <?php if (isset($_SESSION['error'])): ?>
        <div style="background: #f8d7da; border-left: 4px solid #dc3545; padding: 15px; border-radius: 4px; margin-bottom: 20px;">
            <strong style="color: #721c24;">Error:</strong><br>
            <span style="color: #721c24;"><?php echo htmlspecialchars($_SESSION['error']); unset($_SESSION['error']); ?></span>
        </div>
    <?php endif; ?>

    <?php if (isset($_SESSION['success'])): ?>
        <div style="background: #d4edda; border-left: 4px solid #28a745; padding: 15px; border-radius: 4px; margin-bottom: 20px;">
            <strong style="color: #155724;">Success!</strong><br>
            <span style="color: #155724;"><?php echo htmlspecialchars($_SESSION['success']); unset($_SESSION['success']); ?></span>
        </div>
    <?php endif; ?>

    <?php if (isset($_SESSION['warning'])): ?>
        <div style="background: #fff3cd; border-left: 4px solid #ffc107; padding: 15px; border-radius: 4px; margin-bottom: 20px;">
            <strong style="color: #856404;">Notice:</strong><br>
            <span style="color: #856404;"><?php echo htmlspecialchars($_SESSION['warning']); unset($_SESSION['warning']); ?></span>
        </div>
    <?php endif; ?>
    
    <?php 
    // Only show success message if no pending application yet AND success=1 in URL
    if (isset($_GET['success']) && $_GET['success'] == '1' && !$hasPending): 
    ?>
        <div style="background: #d4edda; border-left: 4px solid #28a745; padding: 15px; border-radius: 4px; margin-bottom: 20px;">
            <strong style="color: #155724;">Success!</strong><br>
            <span style="color: #155724;">Your piggery application has been submitted successfully! Please wait for admin approval.</span>
        </div>
    <?php endif; ?>

    <!-- Role Application -->
    <div style="background: white; padding: 20px; border-radius: 8px; margin-bottom: 20px; box-shadow: 0 2px 4px rgba(0,0,0,0.1);">
        <h5 style="margin-bottom: 20px; font-size: 18px; font-weight: 600;">
            Expand Your Business
        </h5>
        
        <?php if ($user['role'] === 'customer'): ?>
            <?php if ($hasPending): ?>
                <div style="background: #fff3cd; border-left: 4px solid #ffc107; padding: 15px; border-radius: 4px;">
                    <strong>Application Pending</strong><br>
                    <span style="color: #856404;">Your piggery application is being reviewed by our admin team.</span>
                </div>
            <?php else: ?>
                <p style="color: #666; margin-bottom: 20px;">Join our network of successful piggery owners and unlock powerful management tools.</p>
                
                <button onclick="openApplicationModal()" style="display: block; width: 100%; background: linear-gradient(135deg, #dc3545, #c82333); color: white; padding: 16px; text-align: center; border-radius: 8px; font-weight: 600; margin-bottom: 15px; box-shadow: 0 4px 6px rgba(220, 53, 69, 0.3); transition: all 0.3s; border: none; cursor: pointer; font-size: 16px;">
                    Apply as Piggery Owner
                </button>
                
                <div style="background: #f8f9fa; padding: 15px; border-radius: 8px; border: 1px solid #e9ecef;">
                    <strong style="display: block; margin-bottom: 10px;">What You'll Get:</strong>
                    <ul style="margin: 0; padding-left: 20px; color: #555;">
                        <li style="margin-bottom: 6px;">Complete pig inventory management</li>
                        <li style="margin-bottom: 6px;">Sell pigs in online marketplace</li>
                        <li style="margin-bottom: 6px;">Direct ordering from feed suppliers</li>
                        <li>Employee management system</li>
                    </ul>
                </div>
            <?php endif; ?>
        <?php else: ?>
            <div style="background: #d4edda; border-left: 4px solid #28a745; padding: 15px; border-radius: 4px;">
                <strong>Role Active</strong><br>
                <span style="color: #155724;">You are registered as <?php echo ucwords(str_replace('_', ' ', $user['role'])); ?></span>
            </div>
        <?php endif; ?>
    </div>

    <!-- Apply as Piggery Employee OR Show Employee Status -->
    <?php if ($employeeStatus): ?>
    <!-- Employee Status Section -->
    <div style="background: white; padding: 20px; border-radius: 8px; margin-bottom: 20px; box-shadow: 0 2px 4px rgba(0,0,0,0.1);">
        <h5 style="margin-bottom: 20px; font-size: 18px; font-weight: 600;">
            My Employee Status
        </h5>
        
        <div style="background: linear-gradient(135deg, #28a745 0%, #20c997 100%); color: white; padding: 20px; border-radius: 8px; margin-bottom: 20px;">
            <div style="display: flex; align-items: center; gap: 12px; margin-bottom: 12px;">
                <div style="background: rgba(255,255,255,0.2); padding: 12px; border-radius: 50%; width: 48px; height: 48px; display: flex; align-items: center; justify-content: center; font-size: 24px;">
                    ✓
                </div>
                <div>
                    <h6 style="margin: 0; font-size: 14px; opacity: 0.9;">Currently Working As</h6>
                    <h4 style="margin: 4px 0 0 0; font-size: 20px; font-weight: 600;">
                        <?php echo ucwords(str_replace('_', ' ', $employeeRole)); ?>
                    </h4>
                </div>
            </div>
        </div>
        
        <div style="background: #f8f9fa; padding: 16px; border-radius: 8px; margin-bottom: 16px;">
            <h6 style="font-size: 14px; font-weight: 600; margin-bottom: 12px; color: #495057;">Farm Information</h6>
            
            <div style="margin-bottom: 10px;">
                <strong style="color: #6c757d; font-size: 13px;">Farm Name:</strong><br>
                <span style="font-size: 15px; color: #212529;"><?php echo htmlspecialchars($employeeStatus['farm_name']); ?></span>
            </div>
            
            <div style="margin-bottom: 10px;">
                <strong style="color: #6c757d; font-size: 13px;">Location:</strong><br>
                <span style="font-size: 15px; color: #212529;"><?php echo htmlspecialchars($employeeStatus['location']); ?></span>
            </div>
            
            <div style="margin-bottom: 10px;">
                <strong style="color: #6c757d; font-size: 13px;">Owner:</strong><br>
                <span style="font-size: 15px; color: #212529;"><?php echo htmlspecialchars($employeeStatus['owner_name']); ?></span>
            </div>
            
            <div style="margin-bottom: 10px;">
                <strong style="color: #6c757d; font-size: 13px;">Contact:</strong><br>
                <span style="font-size: 15px; color: #212529;"><?php echo htmlspecialchars($employeeStatus['contact_number'] ?? 'N/A'); ?></span>
            </div>
            
            <div>
                <strong style="color: #6c757d; font-size: 13px;">Employment Date:</strong><br>
                <span style="font-size: 15px; color: #212529;"><?php echo date('F d, Y', strtotime($employeeStatus['assigned_at'])); ?></span>
            </div>
        </div>
        
        <div style="background: #e7f3ff; border-left: 4px solid #4a90e2; padding: 12px; border-radius: 4px;">
            <strong style="color: #0056b3; font-size: 13px;">Employment Active</strong><br>
            <span style="color: #004085; font-size: 13px;">You are currently employed at this piggery farm. Contact your farm owner for any concerns.</span>
        </div>
    </div>
    <?php elseif ($user['role'] === 'customer'): ?>
    <!-- Apply as Piggery Employee Section -->
    <div style="background: white; padding: 20px; border-radius: 8px; margin-bottom: 20px; box-shadow: 0 2px 4px rgba(0,0,0,0.1);">
        <h5 style="margin-bottom: 20px; font-size: 18px; font-weight: 600;">
            Work at a Piggery
        </h5>
        
        <p style="color: #666; margin-bottom: 20px;">Looking for work? Apply as an employee at a piggery farm and start earning today.</p>
        
        <a href="<?php echo $base_url; ?>/employee/apply" style="display: block; width: 100%; background: linear-gradient(135deg, #4a90e2, #357abd); color: white; padding: 16px; text-align: center; border-radius: 8px; font-weight: 600; margin-bottom: 15px; box-shadow: 0 4px 6px rgba(74, 144, 226, 0.3); transition: all 0.3s; text-decoration: none; font-size: 16px;">
            Apply as Piggery Employee
        </a>
        
        <div style="background: #f8f9fa; padding: 15px; border-radius: 8px; border: 1px solid #e9ecef;">
            <strong style="display: block; margin-bottom: 10px;">Available Positions:</strong>
            <ul style="margin: 0; padding-left: 20px; color: #555;">
                <li style="margin-bottom: 6px;">Pig Caretaker - Daily care and feeding</li>
                <li style="margin-bottom: 6px;">Lechonero - Expert in pig roasting</li>
                <li style="margin-bottom: 6px;">Pig Slaughter - Butchery expertise</li>
                <li>Logistics/Driver - Delivery operations</li>
            </ul>
        </div>
    </div>
    <?php endif; ?>

    <!-- Profile Information -->
    <div style="background: white; padding: 20px; border-radius: 8px; margin-bottom: 20px; box-shadow: 0 2px 4px rgba(0,0,0,0.1);">
        <h5 style="margin-bottom: 20px; font-size: 18px; font-weight: 600;">
            Profile Information
        </h5>
        
        <div style="margin-bottom: 15px;">
            <strong>Name:</strong><br>
            <?php echo htmlspecialchars($user['name']); ?>
        </div>
        
        <div style="margin-bottom: 15px;">
            <strong>Email:</strong><br>
            <?php echo htmlspecialchars($user['email']); ?>
        </div>
        
        <div style="margin-bottom: 15px;">
            <strong>Phone:</strong><br>
            <?php echo htmlspecialchars($user['phone']); ?>
        </div>
        
        <div>
            <strong>Current Role:</strong><br>
            <span style="display: inline-block; padding: 6px 14px; background: #667eea; color: white; border-radius: 16px; font-size: 14px; margin-top: 5px;">
                <?php echo ucwords(str_replace('_', ' ', $user['role'])); ?>
            </span>
        </div>
    </div>
</div>

<!-- Application Modal -->
<div id="applicationModal" style="display: none; position: fixed; top: 0; left: 0; width: 100%; height: 100%; background: rgba(0,0,0,0.6); z-index: 9999; overflow-y: auto; padding: 20px;">
    <div style="background: white; max-width: 700px; margin: 0 auto; border-radius: 12px; box-shadow: 0 8px 32px rgba(0,0,0,0.3);">
        <!-- Modal Header -->
        <div style="background: linear-gradient(135deg, #dc3545, #c82333); color: white; padding: 24px; border-radius: 12px 12px 0 0; position: relative;">
            <h4 style="margin: 0; font-size: 24px; font-weight: 600;">Apply as Piggery Owner</h4>
            <p style="margin: 8px 0 0 0; opacity: 0.9; font-size: 14px;">Submit required documents for verification</p>
            <button onclick="closeApplicationModal()" type="button" style="position: absolute; top: 20px; right: 20px; background: transparent; border: none; color: white; font-size: 32px; cursor: pointer; line-height: 1; padding: 0; width: 32px; height: 32px; font-weight: 300;">×</button>
        </div>
        
        <!-- Modal Body -->
        <form action="<?php echo $base_url; ?>/test_ajax_upload.php" method="POST" enctype="multipart/form-data" style="padding: 24px;">
            <!-- Farm Details -->
            <div style="margin-bottom: 24px;">
                <h5 style="font-size: 16px; font-weight: 600; margin-bottom: 16px; color: #2d3748; border-bottom: 2px solid #e2e8f0; padding-bottom: 8px;">Farm Information</h5>
                
                <div style="margin-bottom: 16px;">
                    <label style="display: block; font-weight: 500; margin-bottom: 6px; color: #4a5568;">Farm Name *</label>
                    <input type="text" name="farm_name" required style="width: 100%; padding: 10px 12px; border: 1px solid #cbd5e0; border-radius: 6px; font-size: 14px; box-sizing: border-box;" placeholder="Enter your farm name">
                </div>
                
                <div style="margin-bottom: 16px;">
                    <label style="display: block; font-weight: 500; margin-bottom: 6px; color: #4a5568;">District/Municipality *</label>
                    <select name="municipality" id="municipality" required onchange="loadBarangays(this.value)" style="width: 100%; padding: 10px 12px; border: 1px solid #cbd5e0; border-radius: 6px; font-size: 14px; box-sizing: border-box;">
                        <option value="">Select District</option>
                        <?php if (!empty($districts)): ?>
                            <?php foreach ($districts as $district): ?>
                                <option value="<?php echo htmlspecialchars($district); ?>"><?php echo htmlspecialchars($district); ?></option>
                            <?php endforeach; ?>
                        <?php else: ?>
                            <!-- Fallback if no data from database -->
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
                        <?php endif; ?>
                    </select>
                </div>
                
                <div style="margin-bottom: 16px;">
                    <label style="display: block; font-weight: 500; margin-bottom: 6px; color: #4a5568;">Barangay *</label>
                    <select name="barangay" id="barangay" required onchange="loadStreets(this.value)" style="width: 100%; padding: 10px 12px; border: 1px solid #cbd5e0; border-radius: 6px; font-size: 14px; box-sizing: border-box;">
                        <option value="">Select District First</option>
                    </select>
                </div>
                
                <div style="margin-bottom: 16px;">
                    <label style="display: block; font-weight: 500; margin-bottom: 6px; color: #4a5568;">Street *</label>
                    <select name="street" id="street" required style="width: 100%; padding: 10px 12px; border: 1px solid #cbd5e0; border-radius: 6px; font-size: 14px; box-sizing: border-box;">
                        <option value="">Select Barangay First</option>
                    </select>
                </div>
                
                <!-- Hidden field for complete location -->
                <input type="hidden" name="farm_location" id="farm_location">
            </div>
            
            <!-- Required Documents -->
            <div style="margin-bottom: 24px;">
                <h5 style="font-size: 16px; font-weight: 600; margin-bottom: 16px; color: #2d3748; border-bottom: 2px solid #e2e8f0; padding-bottom: 8px;">Required Documents (All 6 Required)</h5>
                <p style="font-size: 13px; color: #718096; margin-bottom: 16px;">Upload clear copies (JPG, PNG, or PDF, max 2MB each recommended)</p>
                
                <div style="margin-bottom: 14px;">
                    <label style="display: block; font-weight: 500; margin-bottom: 6px; color: #4a5568; font-size: 14px;">
                        1. Business Registration (DTI or SEC) *
                    </label>
                    <input type="file" name="documents[]" accept=".jpg,.jpeg,.png,.pdf" required style="width: 100%; padding: 8px; border: 1px solid #cbd5e0; border-radius: 6px; font-size: 13px; box-sizing: border-box;">
                </div>
                
                <div style="margin-bottom: 14px;">
                    <label style="display: block; font-weight: 500; margin-bottom: 6px; color: #4a5568; font-size: 14px;">
                        2. Barangay Clearance & Resolution *
                    </label>
                    <input type="file" name="documents[]" accept=".jpg,.jpeg,.png,.pdf" required style="width: 100%; padding: 8px; border: 1px solid #cbd5e0; border-radius: 6px; font-size: 13px; box-sizing: border-box;">
                </div>
                
                <div style="margin-bottom: 14px;">
                    <label style="display: block; font-weight: 500; margin-bottom: 6px; color: #4a5568; font-size: 14px;">
                        3. CPDO Locational Clearance *
                    </label>
                    <input type="file" name="documents[]" accept=".jpg,.jpeg,.png,.pdf" required style="width: 100%; padding: 8px; border: 1px solid #cbd5e0; border-radius: 6px; font-size: 13px; box-sizing: border-box;">
                </div>
                
                <div style="margin-bottom: 14px;">
                    <label style="display: block; font-weight: 500; margin-bottom: 6px; color: #4a5568; font-size: 14px;">
                        4. DENR-EMB Environmental Compliance Certificate (ECC) *
                    </label>
                    <input type="file" name="documents[]" accept=".jpg,.jpeg,.png,.pdf" required style="width: 100%; padding: 8px; border: 1px solid #cbd5e0; border-radius: 6px; font-size: 13px; box-sizing: border-box;">
                </div>
                
                <div style="margin-bottom: 14px;">
                    <label style="display: block; font-weight: 500; margin-bottom: 6px; color: #4a5568; font-size: 14px;">
                        5. City Veterinarian's Office (CVO) & Sanitary Permits *
                    </label>
                    <input type="file" name="documents[]" accept=".jpg,.jpeg,.png,.pdf" required style="width: 100%; padding: 8px; border: 1px solid #cbd5e0; border-radius: 6px; font-size: 13px; box-sizing: border-box;">
                </div>
                
                <div style="margin-bottom: 14px;">
                    <label style="display: block; font-weight: 500; margin-bottom: 6px; color: #4a5568; font-size: 14px;">
                        6. Davao City Business Permit *
                    </label>
                    <input type="file" name="documents[]" accept=".jpg,.jpeg,.png,.pdf" required style="width: 100%; padding: 8px; border: 1px solid #cbd5e0; border-radius: 6px; font-size: 13px; box-sizing: border-box;">
                </div>
            </div>
            
            <!-- Additional Notes -->
            <div style="background: #edf2f7; padding: 12px; border-radius: 6px; margin-bottom: 20px;">
                <p style="margin: 0; font-size: 13px; color: #4a5568;">
                    <strong>Note:</strong> All documents will be reviewed by our admin team. You will be notified once your application is approved.
                </p>
            </div>
            
            <!-- Buttons -->
            <div style="display: flex; gap: 12px;">
                <button type="button" onclick="closeApplicationModal()" style="flex: 1; padding: 12px; background: #e2e8f0; color: #4a5568; border: none; border-radius: 6px; font-weight: 600; cursor: pointer; font-size: 14px;">
                    Cancel
                </button>
                <button type="submit" id="submitBtn" style="flex: 2; padding: 12px; background: linear-gradient(135deg, #dc3545, #c82333); color: white; border: none; border-radius: 6px; font-weight: 600; cursor: pointer; font-size: 14px;">
                    Submit Application
                </button>
            </div>
        </form>
    </div>
</div>

<script>
function openApplicationModal() {
    document.getElementById('applicationModal').style.display = 'block';
    document.body.style.overflow = 'hidden';
    console.log('Modal opened');
}

function closeApplicationModal() {
    document.getElementById('applicationModal').style.display = 'none';
    document.body.style.overflow = 'auto';
    console.log('Modal closed');
}

// Load barangays based on selected municipality
function loadBarangays(municipality) {
    const barangaySelect = document.getElementById('barangay');
    const streetSelect = document.getElementById('street');
    
    barangaySelect.innerHTML = '<option value="">Loading...</option>';
    streetSelect.innerHTML = '<option value="">Select Barangay First</option>';
    
    if (!municipality) {
        barangaySelect.innerHTML = '<option value="">Select District First</option>';
        return;
    }
    
    const url = '<?php echo $base_url; ?>/api/locations?municipality=' + encodeURIComponent(municipality);
    console.log('Loading barangays from:', url);
    
    fetch(url)
        .then(response => {
            console.log('Response status:', response.status);
            return response.json();
        })
        .then(data => {
            console.log('Barangays data:', data);
            barangaySelect.innerHTML = '<option value="">Select Barangay</option>';
            if (data.barangays && data.barangays.length > 0) {
                data.barangays.forEach(barangay => {
                    const option = document.createElement('option');
                    option.value = barangay;
                    option.textContent = barangay;
                    barangaySelect.appendChild(option);
                });
            } else {
                barangaySelect.innerHTML = '<option value="">No barangays found</option>';
            }
        })
        .catch(error => {
            console.error('Error loading barangays:', error);
            barangaySelect.innerHTML = '<option value="">Error loading barangays</option>';
        });
}

// Load streets based on selected barangay
function loadStreets(barangay) {
    const streetSelect = document.getElementById('street');
    const municipality = document.getElementById('municipality').value;
    
    streetSelect.innerHTML = '<option value="">Loading...</option>';
    
    if (!barangay || !municipality) {
        streetSelect.innerHTML = '<option value="">Select Barangay First</option>';
        return;
    }
    
    const url = '<?php echo $base_url; ?>/api/locations?municipality=' + encodeURIComponent(municipality) + '&barangay=' + encodeURIComponent(barangay);
    console.log('Loading streets from:', url);
    
    fetch(url)
        .then(response => {
            console.log('Response status:', response.status);
            return response.json();
        })
        .then(data => {
            console.log('Streets data:', data);
            streetSelect.innerHTML = '<option value="">Select Street</option>';
            if (data.streets && data.streets.length > 0) {
                data.streets.forEach(street => {
                    const option = document.createElement('option');
                    option.value = street;
                    option.textContent = street;
                    streetSelect.appendChild(option);
                });
            } else {
                streetSelect.innerHTML = '<option value="">No streets available</option>';
            }
        })
        .catch(error => {
            console.error('Error loading streets:', error);
            streetSelect.innerHTML = '<option value="">Error loading streets</option>';
        });
}

// Build complete location string before submit
document.querySelector('#applicationModal form').addEventListener('submit', function(e) {
    e.preventDefault(); // PREVENT DEFAULT - use AJAX instead
    
    console.log('Form submitting via AJAX...');
    
    const form = this;
    const submitBtn = document.getElementById('submitBtn');
    const cancelBtn = form.querySelector('button[type="button"]');
    const farmName = document.querySelector('input[name="farm_name"]').value;
    const street = document.getElementById('street').value;
    const barangay = document.getElementById('barangay').value;
    const municipality = document.getElementById('municipality').value;
    
    console.log('Form values:', { farmName, street, barangay, municipality });
    
    // Build complete location
    if (!street || !barangay || !municipality) {
        console.log('Missing location fields!');
        alert('Please select District, Barangay, and Street');
        return false;
    }
    
    const completeLocation = street + ', ' + barangay + ', ' + municipality + ', Davao City';
    document.getElementById('farm_location').value = completeLocation;
    console.log('Complete location:', completeLocation);
    
    // Check if all documents are uploaded
    const fileInputs = document.querySelectorAll('#applicationModal input[type="file"]');
    let allFilesUploaded = true;
    let totalSize = 0;
    let fileDetails = [];
    
    fileInputs.forEach((input, index) => {
        if (!input.files || input.files.length === 0) {
            console.log('Missing file at index:', index);
            allFilesUploaded = false;
        } else {
            const file = input.files[0];
            totalSize += file.size;
            fileDetails.push({
                name: file.name,
                size: (file.size / 1024).toFixed(2) + ' KB',
                type: file.type
            });
        }
    });
    
    if (!allFilesUploaded) {
        alert('Please upload all 6 required documents');
        return false;
    }
    
    // Check total file size
    const totalSizeMB = (totalSize / (1024 * 1024)).toFixed(2);
    console.log('Total file size:', totalSizeMB, 'MB');
    console.log('File details:', fileDetails);
    
    if (totalSize > 15 * 1024 * 1024) {
        alert('Total file size (' + totalSizeMB + ' MB) is too large. Please ensure files are under 2MB each.');
        return false;
    }
    
    console.log('Form validation passed, submitting via AJAX...');
    
    // Show loading state
    submitBtn.disabled = true;
    if (cancelBtn) cancelBtn.disabled = true;
    submitBtn.innerHTML = '<span style="display: inline-block; animation: spin 1s linear infinite;">⏳</span> Uploading ' + totalSizeMB + ' MB...';
    submitBtn.style.opacity = '0.7';
    
    // Prepare form data
    const formData = new FormData(form);
    
    // Send AJAX request with timeout
    const xhr = new XMLHttpRequest();
    
    // Set timeout to 45 seconds (InfinityFree usually times out at 30-60s)
    xhr.timeout = 45000;
    
    xhr.open('POST', form.action, true);
    xhr.setRequestHeader('X-Requested-With', 'XMLHttpRequest');
    
    // Track upload progress
    xhr.upload.addEventListener('progress', function(e) {
        if (e.lengthComputable) {
            const percentComplete = ((e.loaded / e.total) * 100).toFixed(0);
            submitBtn.innerHTML = '<span style="display: inline-block; animation: spin 1s linear infinite;">⏳</span> Uploading ' + percentComplete + '%...';
            console.log('Upload progress:', percentComplete + '%');
        }
    });
    
    xhr.onload = function() {
        console.log('Response received. Status:', xhr.status);
        console.log('Response text (first 1000 chars):', xhr.responseText.substring(0, 1000));
        console.log('Response length:', xhr.responseText.length);
        
        if (xhr.status === 200) {
            // Check if response looks like HTML
            if (xhr.responseText.trim().startsWith('<!DOCTYPE') || xhr.responseText.trim().startsWith('<html')) {
                console.error('Server returned HTML instead of JSON!');
                console.log('Full response:', xhr.responseText);
                
                // Try to extract error message from HTML
                const errorMatch = xhr.responseText.match(/error[^<]*:([^<]+)/i);
                const errorMsg = errorMatch ? errorMatch[1].trim() : 'Server returned HTML instead of JSON. Check PHP error logs.';
                
                alert('❌ Server Error:\n\n' + errorMsg + '\n\nFull response logged to console.');
                submitBtn.disabled = false;
                if (cancelBtn) cancelBtn.disabled = false;
                submitBtn.innerHTML = 'Submit Application';
                submitBtn.style.opacity = '1';
                return;
            }
            
            try {
                const response = JSON.parse(xhr.responseText);
                if (response.success) {
                    alert('✅ Success! Your application has been submitted.');
                    window.location.href = '<?php echo $base_url; ?>/customer/profile?success=1';
                } else {
                    alert('❌ Error: ' + response.message);
                    submitBtn.disabled = false;
                    if (cancelBtn) cancelBtn.disabled = false;
                    submitBtn.innerHTML = 'Submit Application';
                    submitBtn.style.opacity = '1';
                }
            } catch (e) {
                console.error('JSON parse error:', e);
                console.log('Attempted to parse:', xhr.responseText);
                alert('❌ Server returned invalid JSON. Check console for details.');
                submitBtn.disabled = false;
                if (cancelBtn) cancelBtn.disabled = false;
                submitBtn.innerHTML = 'Submit Application';
                submitBtn.style.opacity = '1';
            }
        } else {
            alert('❌ Server error (HTTP ' + xhr.status + '). Please try again.');
            submitBtn.disabled = false;
            if (cancelBtn) cancelBtn.disabled = false;
            submitBtn.innerHTML = 'Submit Application';
            submitBtn.style.opacity = '1';
        }
    };
    
    xhr.onerror = function() {
        console.error('Network error occurred');
        alert('❌ Network error. Please check your connection and try again.');
        submitBtn.disabled = false;
        if (cancelBtn) cancelBtn.disabled = false;
        submitBtn.innerHTML = 'Submit Application';
        submitBtn.style.opacity = '1';
    };
    
    xhr.ontimeout = function() {
        console.error('Request timeout after 45 seconds');
        alert('❌ Upload timeout! InfinityFree cannot handle this file size.\n\nPlease try:\n1. Use files under 500KB each\n2. Upload on faster WiFi\n3. Or use paid hosting');
        submitBtn.disabled = false;
        if (cancelBtn) cancelBtn.disabled = false;
        submitBtn.innerHTML = 'Submit Application';
        submitBtn.style.opacity = '1';
    };
    
    xhr.send(formData);
    
    return false;
});

// Close modal when clicking outside
document.getElementById('applicationModal').addEventListener('click', function(e) {
    if (e.target === this) {
        const submitBtn = document.getElementById('submitBtn');
        if (!submitBtn.disabled) {
            closeApplicationModal();
        }
    }
});

// Prevent closing when clicking inside modal content
document.querySelector('#applicationModal > div').addEventListener('click', function(e) {
    e.stopPropagation();
});

// Add spinner animation
const style = document.createElement('style');
style.textContent = '@keyframes spin { from { transform: rotate(0deg); } to { transform: rotate(360deg); } }';
document.head.appendChild(style);

console.log('Application form scripts loaded successfully');
</script>

<?php
// Capture output
$content = ob_get_clean();

// Set page title and include layout
$pageTitle = 'My Profile';
$currentPage = 'profile';
require_once __DIR__ . '/../layouts/dashboard-layout.php';
?>

    <?php

    /**
     * Pig Inventory Management View
     * Pig Caretaker - Manage pig cages and pigs
     */

    $currentPage = 'pigs';

    $sessionMiddleware = new Session();
    $user = $sessionMiddleware->getUser();

    // Redirect if not authenticated or not pig_caretaker
    if (!$sessionMiddleware->isAuthenticated()) {
        header('Location: /login');
        exit;
    }

    if ($user['role'] !== 'pig_caretaker') {
        header('Location: /dashboard');
        exit;
    }

    // Load pig caretaker data
    global $conn;
    $pigCaretaker = new PigCaretaker($conn);
    if (!$pigCaretaker->findByUserId($user['id'])) {
        // Show detailed error page instead of redirecting
        ?>
        <!DOCTYPE html>
        <html lang="en">
        <head>
            <meta charset="UTF-8">
            <meta name="viewport" content="width=device-width, initial-scale=1.0">
            <title>Setup Required - LechGO</title>
            <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.8/dist/css/bootstrap.min.css" rel="stylesheet">
            <link rel="stylesheet" href="/styles.css">
        </head>
        <body>
            <header>
                <div class="header-container">
                    <a href="/" class="no-underline">
                        <div class="logo">
                            <img src="/images/Logo.png" alt="LechGO Logo" class="logo-img">
                            <div class="logo-text">LechGO</div>
                        </div>
                    </a>
                </div>
            </header>
            <main class="setup-error-page">
                <div class="setup-error-container">
                    <div class="setup-error-icon">!</div>
                    <h1 class="setup-error-title">Profile Setup Required</h1>
                    <p class="setup-error-message">
                        Before you can access the pig inventory, you need to complete your farm profile.
                    </p>
                    <div class="setup-error-box">
                        <p>It looks like your farm profile hasn't been set up yet.</p>
                    </div>
                    <div class="setup-actions">
                        <a href="/complete-profile" class="btn btn-primary">Complete Profile</a>
                        <a href="/dashboard" class="btn btn-secondary">Back to Dashboard</a>
                    </div>
                    <p class="setup-help-link">
                        <a href="/debug">Need help? Check debug info</a>
                    </p>
                </div>
        </div> <!-- Close dashboard-content -->
            </main>
        
    <script>
    // Sidebar toggle for mobile
    document.getElementById('sidebarToggle').addEventListener('click', function() {
        document.getElementById('dashboardSidebar').classList.toggle('active');
    });
    </script>
    </body>
        </html>
        <?php
        exit;
    }

    $pigCages = $pigCaretaker->getPigCages();
    $totalPigs = $pigCaretaker->getTotalPigCount();

    ?>
    <!DOCTYPE html>
    <html lang="en">
    <head>
        <meta charset="UTF-8">
        <meta name="viewport" content="width=device-width, initial-scale=1.0">
        <title>Pig Inventory - LechGO</title>
        <link rel="stylesheet" href="/styles.css">
    </head>
    <body>
        <div class="dashboard-layout">
            <?php include __DIR__ . '/../layouts/sidebar.php'; ?>
            
            <main class="dashboard-main">
        <!-- Top Bar with Hamburger Menu -->
        <div class="dashboard-topbar">
            <button class="dashboard-mobile-toggle" id="sidebarToggle">â˜°</button>
            <h1 class="dashboard-topbar-title">Profile Setup Required</h1>
            <div class="dashboard-topbar-actions">
                <span class="dashboard-topbar-date"><?php echo date('l, F j, Y'); ?></span>
            </div>
        </div>
        
        <div class="dashboard-content">
            <div class="inventory-container">
                <!-- Display Flash Messages -->
                <?php if (isset($_SESSION['success'])): ?>
                    <div class="alert alert-success show">
                        ✓ <?php echo htmlspecialchars($_SESSION['success']); ?>
                    </div>
                    <?php unset($_SESSION['success']); ?>
                <?php endif; ?>

                <?php if (isset($_SESSION['error'])): ?>
                    <div class="alert alert-error show">
                        ✗ <?php echo htmlspecialchars($_SESSION['error']); ?>
                    </div>
                    <?php unset($_SESSION['error']); ?>
                <?php endif; ?>

                <!-- Page Header -->
                <div class="inventory-header">
                    <div>
                        <h1> Pig Inventory Management</h1>
                        <p class="text-gray" style="margin: var(--spacing-sm) 0 0 0;">Manage your pig cages and monitor swine inventory</p>
                    </div>
                </div>
                <!-- Pig Pins Section -->
                <div class="content-section">
                    <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:16px;">
                        <h2 class="section-title" style="margin:0;">Pin Management</h2>
                        <button class="btn-add-pin" onclick="toggleAddPinForm()">+ Add New Pin</button>
                    </div>

                    <?php if (empty($pigCages)): ?>
                        <div class="empty-state">
                            <div class="empty-state-icon"></div>
                            <p>No pins available. Please contact administrator.</p>
                        </div>
                    <?php else: ?>
                        <div class="cages-grid">
                            <?php foreach ($pigCages as $cage): 
                                $pigsInCage = $pigCaretaker->getPigsInCage($cage['id']);
                                $isFull = $cage['current_pig_count'] >= 3;
                            ?>
                            <div class="cage-card">
                                <div class="cage-number">Pin <?php echo htmlspecialchars($cage['cage_number']); ?></div>
                                
                                <div class="cage-info">
                                    <div class="cage-stat">
                                        <span class="cage-stat-label">Pigs:</span>
                                        <span class="cage-stat-value"><?php echo $cage['current_pig_count']; ?>/<?php echo $cage['max_capacity']; ?></span>
                                    </div>
                                    <div class="cage-stat">
                                        <span class="cage-stat-label">Available Slots:</span>
                                        <span class="cage-stat-value"><?php echo $cage['max_capacity'] - $cage['current_pig_count']; ?></span>
                                    </div>
                                </div>

                                <div class="cage-status" style="<?php echo $cage['current_pig_count'] == 0 ? 'background:rgba(150,150,150,0.15);color:#888;' : ''; ?>">
                                    <?php echo $cage['current_pig_count'] == 0 ? 'Inactive' : 'Active'; ?>
                                </div>

                                <div class="cage-actions">
                                    <?php if (!$isFull): ?>
                                        <button class="btn-add-pig" onclick="toggleAddPigForm(<?php echo $cage['id']; ?>, <?php echo $cage['current_pig_count']; ?>, '<?php echo $cage['cage_number']; ?>')">+ Add Pig</button>
                                    <?php else: ?>
                                        <button class="btn-add-pig" disabled>Pin Full</button>
                                    <?php endif; ?>
                                    <a href="/pig-caretaker/view-pigs" class="btn-view-pigs">View Pigs</a>
                                </div>
                            </div>
                            <?php endforeach; ?>
                        </div>
                    <?php endif; ?>
                </div>

                <!-- Add Pig Form Modal -->
                <div id="addPigFormContainer"></div>
            </div>
        </div> <!-- Close dashboard-content -->
        </main>

        </div>

        <style>
            .modal-overlay {
                position: fixed; inset: 0;
                background: rgba(0,0,0,.45);
                z-index: 999;
            }
            .add-pig-form {
                position: fixed;
                top: 50%; left: 50%;
                transform: translate(-50%, -50%);
                background: #fff;
                padding: 18px 22px 16px;
                border-radius: 12px;
                box-shadow: 0 8px 32px rgba(0,0,0,.22);
                width: 560px; max-width: 95vw;
                max-height: 90vh; overflow-y: auto;
                z-index: 1000;
            }
            .form-header {
                display: flex; justify-content: space-between; align-items: center;
                border-bottom: 2px solid var(--primary-red);
                padding-bottom: 6px; margin-bottom: 10px;
            }
            .form-header h2 { margin: 0; color: var(--primary-red); font-size: .9rem; }
            .close-btn {
                background: none; border: none; font-size: 1rem;
                cursor: pointer; color: #999; line-height: 1;
            }
            .close-btn:hover { color: var(--primary-red); }
            .pig-form-grid {
                display: grid;
                grid-template-columns: 1fr 1fr;
                gap: 10px 14px;
            }
            .pig-form-grid .full { grid-column: 1 / -1; }
            .pig-form-grid label {
                display: block;
                font-size: .72rem; font-weight: 700;
                color: #555; text-transform: uppercase;
                letter-spacing: .03em; margin-bottom: 3px;
            }
            .pig-form-grid input,
            .pig-form-grid select,
            .pig-form-grid textarea {
                width: 100%; box-sizing: border-box;
                padding: 6px 9px;
                border: 1.5px solid #e0e0e0;
                border-radius: 7px; font-size: .85rem;
                background: #fafafa;
            }
            .pig-form-grid input:focus,
            .pig-form-grid select:focus,
            .pig-form-grid textarea:focus {
                outline: none; border-color: var(--primary-red); background: #fff;
            }
            .pig-form-grid textarea { rows: 1; resize: vertical; min-height: 40px; }
            .photo-upload-box {
                border: 2px dashed #e0e0e0;
                border-radius: 6px; padding: 7px;
                text-align: center; cursor: pointer;
                transition: border-color .2s;
                position: relative;
            }
            .photo-upload-box:hover { border-color: var(--primary-red); }
            .photo-upload-box input[type="file"] {
                position: absolute; inset: 0; opacity: 0; cursor: pointer; width: 100%; height: 100%;
            }
            .photo-preview {
                width: 100%; max-height: 70px;
                object-fit: cover; border-radius: 5px;
                display: none; margin-top: 4px;
            }
            .photo-upload-hint { font-size: .7rem; color: #aaa; margin: 0; }
            .pig-form-actions {
                display: flex; gap: 7px; margin-top: 10px;
            }
            .pig-form-actions button {
                flex: 1; padding: 6px;
                border: none; border-radius: 6px;
                font-weight: 700; font-size: .8rem; cursor: pointer;
            }
            .pig-form-actions .btn-submit { background: var(--primary-red); color: #fff; }
            .pig-form-actions .btn-submit:hover { background: var(--dark-red); }
            .pig-form-actions .btn-cancel { background: #f0f0f0; color: #555; }
            .pig-form-actions .btn-cancel:hover { background: #e0e0e0; }
            
            .btn-add-pin {
                background: var(--primary-red);
                color: #fff;
                border: none;
                padding: 8px 16px;
                border-radius: 7px;
                font-weight: 700;
                font-size: .85rem;
                cursor: pointer;
                transition: background .2s;
            }
            .btn-add-pin:hover {
                background: var(--dark-red);
            }
        </style>

        <script>
            function toggleAddPigForm(cageId, currentPigCount, pinNumber) {
                const container = document.getElementById('addPigFormContainer');
                if (container.innerHTML.trim() !== '') { closeAddPigForm(); return; }

                const today = new Date().toISOString().split('T')[0];
                const nextPigNum = (currentPigCount || 0) + 1;
                const autoTagId = 'PIN' + pinNumber + '-PIG' + nextPigNum;
                container.innerHTML = `
                    <div class="modal-overlay" onclick="closeAddPigForm()"></div>
                    <div class="add-pig-form">
                        <div class="form-header">
                            <h2> Add Pig to Pin</h2>
                            <button class="close-btn" onclick="closeAddPigForm()">✕</button>
                        </div>
                        <form method="POST" action="/pig-caretaker/add-pig" enctype="multipart/form-data">
                            <input type="hidden" name="cage_id" value="${cageId}">
                            <div class="pig-form-grid">
                                <div>
                                    <label>Pig Tag ID <span style="font-weight:400;color:#aaa">(auto-generated)</span></label>
                                    <input type="text" name="pig_tag_id" value="${autoTagId}" readonly style="background:#f5f5f5;">
                                </div>
                                <div>
                                    <label>Age (days) <span style="font-weight:400;color:#aaa;font-size:.68rem;">or auto-estimate from weight</span></label>
                                    <input type="number" id="age_days" name="age_days" placeholder="e.g., 60" min="0" max="500" oninput="updateAgeStageInfo()">
                                    <div id="age_stage_info" style="font-size:.68rem;color:#aaa;margin-top:3px;">
                                        Super Biik: 5–35 · Pre-Starter: 36–50 · Starter: 51–80 · Grower: 81–115 · Grower 2: 116–140
                                    </div>
                                </div>
                                <div>
                                    <label>Weight (kg) <span style="font-weight:400;color:#aaa;font-size:.68rem;">or calculate below</span></label>
                                    <input type="number" id="manual_weight" name="weight_kg" placeholder="e.g., 25.5" step="0.1" min="0" onchange="toggleWeightSource('manual')">
                                </div>
                                <div>
                                    <label>Health Status *</label>
                                    <select name="health_status" required>
                                        <option value="healthy" selected>Healthy</option>
                                        <option value="sick">Sick</option>
                                        <option value="recovering">Recovering</option>
                                    </select>
                                </div>
                                <div class="full" style="border-top:1px solid #e8e8e8;padding-top:10px;margin-top:4px;">
                                    <label style="color:#d62828;"> Weight Calculator <span style="font-weight:400;color:#aaa;font-size:.68rem;">(optional - auto-calculates weight)</span></label>
                                </div>
                                <div>
                                    <label>Heart Girth (m)</label>
                                    <input type="number" id="heart_girth" name="heart_girth" placeholder="e.g., 0.75" step="0.01" min="0" max="5" oninput="calculateWeight()">
                                </div>
                                <div>
                                    <label>Body Length (m)</label>
                                    <input type="number" id="body_length" name="body_length" placeholder="e.g., 0.85" step="0.01" min="0" max="5" oninput="calculateWeight()">
                                </div>
                                <div class="full" style="background:#f8f8f8;padding:10px;border-radius:6px;border:1px solid #e0e0e0;">
                                    <label style="margin-bottom:5px;">Calculated Weight</label>
                                    <div style="font-size:1.3rem;font-weight:700;color:#d62828;" id="calculated_weight_display">-- kg</div>
                                    <div style="font-size:.68rem;color:#777;margin-top:4px;">Formula: (Heart Girth² × Body Length) × 69.3</div>
                                    <input type="hidden" id="calculated_weight" name="calculated_weight">
                                    <input type="hidden" id="weight_source" name="weight_source" value="">
                                </div>
                                <div>
                                    <label>Date Added *</label>
                                    <input type="date" name="date_added" value="${today}" required>
                                </div>
                                <div class="full">
                                    <label>Pig Photo <span style="font-weight:400;color:#aaa">(optional)</span></label>
                                    <div class="photo-upload-box">
                                        <input type="file" name="pig_photo" accept="image/*" onchange="previewPigPhoto(this)">
                                        <p class="photo-upload-hint"> Click to upload pig photo</p>
                                        <img id="pigPhotoPreview" class="photo-preview" src="" alt="Preview">
                                    </div>
                                </div>
                                <div>
                                    <label>AIC <span style="font-weight:400;color:#aaa">(Animal Inspection Cert.)</span></label>
                                    <div class="photo-upload-box" id="aic_box">
                                        <input type="file" name="aic_file" accept="image/*,.pdf" onchange="showFileName(this, 'aic_box', 'aic_hint')">
                                        <p class="photo-upload-hint" id="aic_hint"> Upload AIC</p>
                                    </div>
                                </div>
                                <div>
                                    <label>Barangay Certification</label>
                                    <div class="photo-upload-box" id="brgy_box">
                                        <input type="file" name="brgy_cert_file" accept="image/*,.pdf" onchange="showFileName(this, 'brgy_box', 'brgy_hint')">
                                        <p class="photo-upload-hint" id="brgy_hint"> Upload Brgy. Cert.</p>
                                    </div>
                                </div>
                            </div>
                            <div class="pig-form-actions">
                                <button type="button" class="btn-cancel" onclick="closeAddPigForm()">Cancel</button>
                                <button type="submit" class="btn-submit">+ Add Pig</button>
                            </div>
                        </form>
                    </div>
                `;
            }

            function previewPigPhoto(input) {
                const preview = document.getElementById('pigPhotoPreview');
                const hint = input.closest('.photo-upload-box').querySelector('.photo-upload-hint');
                if (input.files && input.files[0]) {
                    const reader = new FileReader();
                    reader.onload = e => {
                        preview.src = e.target.result;
                        preview.style.display = 'block';
                        hint.style.display = 'none';
                    };
                    reader.readAsDataURL(input.files[0]);
                }
            }

            function showFileName(input, boxId, hintId) {
                const hint = document.getElementById(hintId);
                const box  = document.getElementById(boxId);
                if (input.files && input.files[0]) {
                    const name = input.files[0].name;
                    hint.textContent = '' + name;
                    hint.style.color = '#2d7a2d';
                    box.style.borderColor = '#2d7a2d';
                    box.style.background  = '#f0fff0';
                }
            }

            function closeAddPigForm() {
                document.getElementById('addPigFormContainer').innerHTML = '';
            }

            function calculateWeight() {
                const heartGirthM = parseFloat(document.getElementById('heart_girth').value) || 0;
                const bodyLengthM = parseFloat(document.getElementById('body_length').value) || 0;
                const manualWeight = document.getElementById('manual_weight');
                const display = document.getElementById('calculated_weight_display');
                const hiddenField = document.getElementById('calculated_weight');
                
                if (heartGirthM > 0 && bodyLengthM > 0) {
                    // Formula: (Heart Girth² × Body Length) × 69.3
                    // Using meters directly
                    const girthSquared = heartGirthM * heartGirthM;
                    const calculatedWeight = girthSquared * bodyLengthM * 69.3;
                    const roundedWeight = Math.round(calculatedWeight * 10) / 10; // Round to 1 decimal
                    
                    display.textContent = roundedWeight + ' kg';
                    display.style.color = '#2d7a2d';
                    hiddenField.value = roundedWeight;
                    
                    // If manual weight is empty, fill it with calculated value
                    if (!manualWeight.value) {
                        manualWeight.value = roundedWeight;
                        document.getElementById('weight_source').value = 'calculated';
                    }
                    
                    // Estimate age from weight
                    estimateAgeFromWeight(roundedWeight);
                } else {
                    display.textContent = '-- kg';
                    display.style.color = '#d62828';
                    hiddenField.value = '';
                }
            }

            function estimateAgeFromWeight(weight) {
                const ageInput = document.getElementById('age_days');
                
                // Only auto-fill if age is empty
                if (ageInput && !ageInput.value) {
                    let estimatedAge = 0;
                    
                    // Rough estimates based on average growth rates
                    if (weight < 1.5) {
                        estimatedAge = 5;
                    } else if (weight < 7) {
                        // Super Biik: 1.5-7 kg → 5-35 days
                        estimatedAge = Math.round(5 + ((weight - 1.5) / 5.5) * 30);
                    } else if (weight < 15) {
                        // Pre-Starter: 7-15 kg → 36-50 days
                        estimatedAge = Math.round(36 + ((weight - 7) / 8) * 14);
                    } else if (weight < 30) {
                        // Starter: 15-30 kg → 51-80 days
                        estimatedAge = Math.round(51 + ((weight - 15) / 15) * 29);
                    } else if (weight < 60) {
                        // Grower: 30-60 kg → 81-115 days
                        estimatedAge = Math.round(81 + ((weight - 30) / 30) * 34);
                    } else if (weight < 90) {
                        // Grower 2: 60-90 kg → 116-140 days
                        estimatedAge = Math.round(116 + ((weight - 60) / 30) * 24);
                    } else {
                        // Finisher: 90+ kg → 140+ days
                        estimatedAge = Math.round(140 + ((weight - 90) / 10) * 10);
                    }
                    
                    ageInput.value = estimatedAge;
                    updateAgeStageInfo();
                }
            }

            function updateAgeStageInfo() {
                const ageInput = document.getElementById('age_days');
                const infoDiv = document.getElementById('age_stage_info');
                
                if (ageInput && infoDiv) {
                    const age = parseInt(ageInput.value) || 0;
                    let stage = '';
                    let color = '#aaa';
                    
                    if (age >= 5 && age <= 35) {
                        stage = ' Super Biik stage';
                        color = '#ff6b6b';
                    } else if (age >= 36 && age <= 50) {
                        stage = ' Pre-Starter stage';
                        color = '#ff922b';
                    } else if (age >= 51 && age <= 80) {
                        stage = ' Starter stage';
                        color = '#fab005';
                    } else if (age >= 81 && age <= 115) {
                        stage = ' Grower stage';
                        color = '#74c0fc';
                    } else if (age >= 116 && age <= 140) {
                        stage = ' Grower 2 stage';
                        color = '#4dabf7';
                    } else if (age > 140) {
                        stage = ' Finisher stage';
                        color = '#2d7a2d';
                    }
                    
                    if (stage) {
                        infoDiv.innerHTML = `<strong style="color:${color};">${stage}</strong> (${age} days)`;
                    } else {
                        infoDiv.innerHTML = 'Super Biik: 5–35 · Pre-Starter: 36–50 · Starter: 51–80 · Grower: 81–115 · Grower 2: 116–140';
                    }
                }
            }

            function toggleWeightSource(source) {
                document.getElementById('weight_source').value = source;
            }

            function deletePig(pigId) {
                if (confirm('Are you sure you want to remove this pig?')) {
                    console.log('Delete pig:', pigId);
                }
            }

            function toggleAddPinForm() {
                const container = document.getElementById('addPigFormContainer');
                if (container.innerHTML.trim() !== '') { closeAddPigForm(); return; }
                
                container.innerHTML = `
                    <div class="modal-overlay" onclick="closeAddPigForm()"></div>
                    <div class="add-pig-form" style="width:420px;">
                        <div class="form-header">
                            <h2> Add New Pin</h2>
                            <button class="close-btn" onclick="closeAddPigForm()">✕</button>
                        </div>
                        <form method="POST" action="/pig-caretaker/add-pin">
                            <div class="pig-form-grid">
                                <div class="full">
                                    <label>Pin Number *</label>
                                    <input type="text" name="pin_number" placeholder="e.g., PIN1 or A-01" required style="text-transform:uppercase;">
                                </div>
                                <div>
                                    <label>Max Capacity *</label>
                                    <input type="number" name="max_capacity" value="3" min="1" max="20" required>
                                    <div style="font-size:.68rem;color:#aaa;margin-top:3px;">
                                        Maximum pigs per pin (default: 3)
                                    </div>
                                </div>
                                <div>
                                    <label>Status *</label>
                                    <select name="status" required>
                                        <option value="active">Active</option>
                                        <option value="inactive" selected>Inactive</option>
                                        <option value="maintenance">Maintenance</option>
                                    </select>
                                </div>
                            </div>
                            <div class="pig-form-actions">
                                <button type="button" class="btn-cancel" onclick="closeAddPigForm()">Cancel</button>
                                <button type="submit" class="btn-submit">+ Add Pin</button>
                            </div>
                        </form>
                    </div>
                `;
            }
        </script>

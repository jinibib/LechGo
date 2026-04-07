<?php

/**
 * Pig Inventory Management View
 * Pig Caretaker - Manage pig cages and pigs
 */

$sessionMiddleware = new Session();
$user = $sessionMiddleware->getUser();

// Redirect if not authenticated or not pig_caretaker
if (!$sessionMiddleware->isAuthenticated()) {
    header('Location: /LechGo_Final/public/login');
    exit;
}

if ($user['role'] !== 'pig_caretaker') {
    header('Location: /LechGo_Final/public/dashboard');
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
        <link rel="stylesheet" href="/LechGo_Final/public/styles.css">
    </head>
    <body>
        <header>
            <div class="header-container">
                <a href="/LechGo_Final/public/" class="no-underline">
                    <div class="logo">
                        <img src="/LechGo_Final/public/images/Logo.png" alt="LechGO Logo" class="logo-img">
                        <div class="logo-text">LechGO</div>
                    </div>
                </a>
            </div>
        </header>
        <main class="setup-error-page">
            <div class="setup-error-container">
                <div class="setup-error-icon">⚠️</div>
                <h1 class="setup-error-title">Profile Setup Required</h1>
                <p class="setup-error-message">
                    Before you can access the pig inventory, you need to complete your farm profile.
                </p>
                <div class="setup-error-box">
                    <p>It looks like your farm profile hasn't been set up yet.</p>
                </div>
                <div class="setup-actions">
                    <a href="/LechGo_Final/public/complete-profile" class="btn btn-primary">Complete Profile</a>
                    <a href="/LechGo_Final/public/dashboard" class="btn btn-secondary">Back to Dashboard</a>
                </div>
                <p class="setup-help-link">
                    <a href="/LechGo_Final/public/debug">Need help? Check debug info</a>
                </p>
            </div>
        </main>
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
    <link rel="stylesheet" href="/LechGo_Final/public/styles.css">
</head>
<body>
    <!-- Header/Navigation -->
    <header>
        <div class="header-container">
            <a href="/LechGo_Final/public/" class="no-underline">
                <div class="logo">
                    <img src="/LechGo_Final/public/images/Logo.png" alt="LechGO Logo" class="logo-img">
                    <div class="logo-text">LechGO</div>
                </div>
            </a>
            <nav>
                <div class="user-profile">
                    <div class="user-avatar"><?php echo strtoupper(substr($user['name'] ?? 'U', 0, 1)); ?></div>
                    <div class="user-info">
                        <p class="name"><?php echo htmlspecialchars($user['name'] ?? 'User'); ?></p>
                        <p class="email"><?php echo htmlspecialchars($user['email'] ?? ''); ?></p>
                    </div>
                    <a href="/LechGo_Final/public/logout" class="btn btn-secondary ml-md">Logout</a>
                </div>
            </nav>
        </div>
    </header>

    <main>
        <div class="inventory-container">
            <!-- Back Button -->
            <a href="/LechGo_Final/public/dashboard" class="back-button">← Back to Dashboard</a>

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
                    <h1>🐷 Pig Inventory Management</h1>
                    <p class="text-gray" style="margin: var(--spacing-sm) 0 0 0;">Manage your pig cages and monitor swine inventory</p>
                </div>
            </div>

            <!-- Statistics Cards -->
            <div class="stats-grid">
                <div class="stat-card">
                    <div class="stat-value"><?php echo $totalPigs; ?></div>
                    <div class="stat-label">Total Pigs</div>
                </div>
                <div class="stat-card">
                    <div class="stat-value"><?php echo count($pigCages); ?></div>
                    <div class="stat-label">Total Cages</div>
                </div>
                <div class="stat-card">
                    <div class="stat-value"><?php echo count($pigCages) * 3; ?></div>
                    <div class="stat-label">Total Capacity</div>
                </div>
                <div class="stat-card">
                    <div class="stat-value"><?php echo (count($pigCages) * 3) - $totalPigs; ?></div>
                    <div class="stat-label">Available Slots</div>
                </div>
            </div>

            <!-- Pig Cages Section -->
            <div class="content-section">
                <h2 class="section-title">Cage Management</h2>

                <?php if (empty($pigCages)): ?>
                    <div class="empty-state">
                        <div class="empty-state-icon">🚫</div>
                        <p>No cages available. Please contact administrator.</p>
                    </div>
                <?php else: ?>
                    <div class="cages-grid">
                        <?php foreach ($pigCages as $cage): 
                            $pigsInCage = $pigCaretaker->getPigsInCage($cage['id']);
                            $isFull = $cage['current_pig_count'] >= 3;
                        ?>
                        <div class="cage-card">
                            <div class="cage-number">Cage <?php echo htmlspecialchars($cage['cage_number']); ?></div>
                            
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

                            <div class="cage-status"><?php echo ucfirst(htmlspecialchars($cage['status'])); ?></div>

                            <div class="cage-actions">
                                <?php if (!$isFull): ?>
                                    <button class="btn-add-pig" onclick="toggleAddPigForm(<?php echo $cage['id']; ?>)">+ Add Pig</button>
                                <?php else: ?>
                                    <button class="btn-add-pig" disabled>Cage Full</button>
                                <?php endif; ?>
                                <a href="#" class="btn-view-pigs">View Pigs</a>
                            </div>
                        </div>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>
            </div>

            <!-- Add Pig Form Modal -->
            <div id="addPigFormContainer"></div>
        </div>
    </main>

    <!-- Footer -->
    <footer>
        <div class="footer-bottom">
            <p>&copy; 2026 LechGO. All rights reserved.</p>
        </div>
    </footer>

    <script>
        let currentCageId = null;

        function toggleAddPigForm(cageId) {
            currentCageId = cageId;
            const container = document.getElementById('addPigFormContainer');
            
            if (container.innerHTML.trim() === '') {
                // Show form
                container.innerHTML = `
                    <div class="modal-overlay" onclick="closeAddPigForm()"></div>
                    <div class="add-pig-form">
                        <div class="form-header">
                            <h2>Add Pig to Cage</h2>
                            <button class="close-btn" onclick="closeAddPigForm()">✕</button>
                        </div>
                        <form method="POST" action="/LechGo_Final/public/pig-caretaker/add-pig" id="addPigForm">
                            <input type="hidden" name="cage_id" value="${cageId}">
                            
                            <div class="form-group">
                                <label>Pig Tag ID (Optional)</label>
                                <input type="text" name="pig_tag_id" placeholder="e.g., PIG-001">
                            </div>
                            
                            <div class="form-group">
                                <label>Breed</label>
                                <input type="text" name="breed" placeholder="e.g., Native, Hybrid" required>
                            </div>
                            
                            <div class="form-group">
                                <label>Age (months)</label>
                                <input type="number" name="age_months" placeholder="e.g., 3" min="0">
                            </div>
                            
                            <div class="form-group">
                                <label>Weight (kg)</label>
                                <input type="number" name="weight_kg" placeholder="e.g., 25.5" step="0.1" min="0">
                            </div>
                            
                            <div class="form-group">
                                <label>Health Status</label>
                                <select name="health_status" required>
                                    <option value="">-- Select --</option>
                                    <option value="healthy">Healthy</option>
                                    <option value="sick">Sick</option>
                                    <option value="recovering">Recovering</option>
                                </select>
                            </div>
                            
                            <div class="form-group">
                                <label>Date Added</label>
                                <input type="date" name="date_added" value="${new Date().toISOString().split('T')[0]}" required>
                            </div>
                            
                            <div class="form-group">
                                <label>Notes</label>
                                <textarea name="notes" placeholder="Any additional notes about the pig..." rows="3"></textarea>
                            </div>
                            
                            <div class="form-actions">
                                <button type="submit" class="btn btn-primary">Add Pig</button>
                                <button type="button" class="btn btn-secondary" onclick="closeAddPigForm()">Cancel</button>
                            </div>
                        </form>
                    </div>
                `;
            } else {
                closeAddPigForm();
            }
        }

        function closeAddPigForm() {
            document.getElementById('addPigFormContainer').innerHTML = '';
            currentCageId = null;
        }

        function deletePig(pigId) {
            if (confirm('Are you sure you want to remove this pig?')) {
                console.log('Delete pig:', pigId);
            }
        }
    </script>

    <style>
        .modal-overlay {
            position: fixed;
            top: 0;
            left: 0;
            right: 0;
            bottom: 0;
            background: rgba(0, 0, 0, 0.5);
            z-index: 999;
        }

        .add-pig-form {
            position: fixed;
            top: 50%;
            left: 50%;
            transform: translate(-50%, -50%);
            background: white;
            padding: var(--spacing-lg);
            border-radius: var(--radius-lg);
            box-shadow: 0 10px 40px rgba(0, 0, 0, 0.3);
            max-width: 500px;
            width: 90%;
            max-height: 80vh;
            overflow-y: auto;
            z-index: 1000;
        }

        .form-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: var(--spacing-lg);
            border-bottom: 2px solid var(--primary-red);
            padding-bottom: var(--spacing-md);
        }

        .form-header h2 {
            margin: 0;
            color: var(--primary-red);
            font-size: 20px;
        }

        .close-btn {
            background: none;
            border: none;
            font-size: 24px;
            cursor: pointer;
            color: #7f8c8d;
            padding: 0;
            width: 30px;
            height: 30px;
            display: flex;
            align-items: center;
            justify-content: center;
        }

        .close-btn:hover {
            color: var(--primary-red);
        }

        .add-pig-form .form-group {
            margin-bottom: var(--spacing-lg);
        }

        .add-pig-form label {
            display: block;
            font-weight: 600;
            color: var(--dark-gray);
            margin-bottom: var(--spacing-sm);
            font-size: 14px;
        }

        .add-pig-form input,
        .add-pig-form select,
        .add-pig-form textarea {
            width: 100%;
            padding: var(--spacing-sm);
            border: 1px solid var(--gray);
            border-radius: var(--radius-sm);
            font-size: 14px;
            font-family: var(--font-main);
        }

        .add-pig-form input:focus,
        .add-pig-form select:focus,
        .add-pig-form textarea:focus {
            outline: none;
            border-color: var(--primary-red);
            box-shadow: 0 0 0 3px rgba(209, 51, 45, 0.1);
        }

        .form-actions {
            display: flex;
            gap: var(--spacing-md);
            margin-top: var(--spacing-lg);
        }

        .form-actions button {
            flex: 1;
            padding: var(--spacing-md);
            border: none;
            border-radius: var(--radius-sm);
            font-weight: 600;
            cursor: pointer;
            font-size: 14px;
            transition: background 0.3s ease;
        }

        .btn-primary {
            background: var(--primary-red);
            color: white;
        }

        .btn-primary:hover {
            background: var(--dark-red);
        }

        .btn-secondary {
            background: var(--gray);
            color: var(--dark-gray);
        }

        .btn-secondary:hover {
            background: #d0d0d0;
        }
    </style>

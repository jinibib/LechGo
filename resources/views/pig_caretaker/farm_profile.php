<?php

/**
 * Farm Profile View
 * Pig Caretaker - Update farm/piggery information
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
        <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.8/dist/css/bootstrap.min.css" rel="stylesheet">
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
                <div class="setup-error-icon">!</div>
                <h1 class="setup-error-title">Profile Setup Required</h1>
                <p class="setup-error-message">
                    Before you can access your farm profile, you need to complete your profile setup.
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


$currentPage = 'farm-profile';

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Farm Profile - LechGO</title>
    <link rel="stylesheet" href="/LechGo_Final/public/styles.css">
</head>
<body>
    <div class="dashboard-layout">
        <?php include __DIR__ . '/../layouts/sidebar.php'; ?>
        
        <main class="dashboard-main">
        <div class="profile-container">

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
            <div class="profile-header">
                <div>
                    <h1>👤 Farm Profile</h1>
                    <p class="text-gray profile-subtitle">Manage your farm/piggery information and settings</p>
                </div>
            </div>

            <!-- Statistics -->
            <div class="stat-grid">
                <div class="stat-box">
                    <div class="stat-number"><?php echo $pigCaretaker->getTotalPigCount(); ?></div>
                    <div class="stat-text">Total Pigs</div>
                </div>
                <div class="stat-box">
                    <div class="stat-number"><?php echo number_format($pigCaretaker->getTotalFeedInStock(), 1); ?></div>
                    <div class="stat-text">Feed in Stock (kg)</div>
                </div>
                <div class="stat-box">
                    <div class="stat-number">5</div>
                    <div class="stat-text">Cages</div>
                </div>
            </div>

            <!-- Farm Information Section -->
            <div class="content-section">
                <h2 class="section-title">🏠 Farm Information</h2>

                <div class="info-grid">
                    <div class="info-card">
                        <div class="info-label">Farm/Piggery Name</div>
                        <div class="info-value"><?php echo htmlspecialchars($pigCaretaker->farm_name ?? 'N/A'); ?></div>
                    </div>
                    <div class="info-card">
                        <div class="info-label">Location</div>
                        <div class="info-value"><?php echo htmlspecialchars($pigCaretaker->location ?? 'N/A'); ?></div>
                    </div>
                    <div class="info-card">
                        <div class="info-label">Contact Number</div>
                        <div class="info-value"><?php echo htmlspecialchars($pigCaretaker->contact_number ?? 'N/A'); ?></div>
                    </div>
                </div>
            </div>

            <!-- Account Information Section -->
            <div class="content-section">
                <h2 class="section-title">👤 Account Information</h2>

                <div class="form-grid">
                    <div class="form-group">
                        <label>Full Name</label>
                        <input type="text" value="<?php echo htmlspecialchars($user['name'] ?? ''); ?>" disabled>
                    </div>
                    <div class="form-group">
                        <label>Email Address</label>
                        <input type="email" value="<?php echo htmlspecialchars($user['email'] ?? ''); ?>" disabled>
                    </div>
                    <div class="form-group">
                        <label>Phone Number</label>
                        <input type="tel" value="<?php echo htmlspecialchars($user['phone'] ?? ''); ?>" disabled>
                    </div>
                    <div class="form-group">
                        <label>Account Type</label>
                        <input type="text" value="Pig Caretaker" disabled>
                    </div>
                </div>

                <div class="security-section">
                    <p class="security-description">Account email has been verified on <?php echo htmlspecialchars($user['email_verified_at'] ?? 'N/A'); ?></p>
                </div>
            </div>

            <!-- Security Section -->
            <div class="content-section">
                <h2 class="section-title">Security</h2>

                <div class="security-privacy-section">
                    <p class="security-description">Keep your account secure by regularly changing your password.</p>
                    <a href="#" class="btn btn-secondary">Change Password</a>
                </div>
            </div>

            <!-- Danger Zone -->
            <div class="content-section danger-zone-section">
                <h2 class="section-title danger-zone-title">Danger Zone</h2>

                <div class="calendar-danger-zone">
                    <p class="danger-title">Delete Account</p>
                    <p class="security-description">Once you delete your account, there is no going back. Please be certain. This action will permanently delete all your farm data, pigs inventory, and feeding records.</p>
                    <button class="btn btn-secondary" onclick="alert('Account deletion is not available yet. Contact support.')">Delete Account</button>
                </div>
            </div>
        </div>
    </main>

    </div>
</body>
</html>

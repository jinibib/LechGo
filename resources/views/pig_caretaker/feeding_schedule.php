<?php

/**
 * Feeding Schedule View
 * Pig Caretaker - Record feeding logs
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
                    Before you can access the feeding schedule, you need to complete your farm profile.
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
$feedInventory = $pigCaretaker->getFeedInventory();

// Get recent feeding records
$feedingRecords = [];
try {
    $query = "SELECT fs.id, fs.caretaker_id, fs.cage_id, fs.feed_inventory_id, fs.feeding_date, fs.feeding_time, fs.amount_kg, fs.notes, fs.created_at, pg.cage_number, fi.feed_name, fi.feed_type
              FROM feeding_schedule fs
              LEFT JOIN pig_cages pg ON fs.cage_id = pg.id
              LEFT JOIN feed_inventory fi ON fs.feed_inventory_id = fi.id
              WHERE fs.caretaker_id = ?
              ORDER BY fs.feeding_date DESC, fs.feeding_time DESC
              LIMIT 30";
    $stmt = $GLOBALS['conn']->prepare($query);
    if ($stmt) {
        $stmt->bind_param('i', $pigCaretaker->id);
        $stmt->execute();
        $result = $stmt->get_result();
        $feedingRecords = $result->fetch_all(MYSQLI_ASSOC) ?? [];
        $stmt->close();
    }
} catch (Exception $e) {
    $feedingRecords = [];
}

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Feeding Schedule - LechGO</title>
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
        <div class="schedule-container">
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
            <div class="schedule-header">
                <div>
                    <h1>📋 Feeding Schedule</h1>
                    <p class="text-gray" style="margin: var(--spacing-sm) 0 0 0;">Record and track daily feeding activities</p>
                </div>
            </div>

            <!-- Add Feeding Record Form -->
            <div class="content-section">
                <div class="section-header">
                    <h2 class="section-title">Record Feeding</h2>
                    <button class="btn btn-primary" onclick="toggleFeedingForm()">+ Record Feeding</button>
                </div>

                <form id="feedingForm" class="form-section" method="POST" action="/LechGo_Final/public/pig-caretaker/record-feeding">
                    <div class="form-grid">
                        <div class="form-group">
                            <label for="feeding_date">Feeding Date</label>
                            <input type="date" id="feeding_date" name="feeding_date" required>
                        </div>
                        <div class="form-group">
                            <label for="feeding_time">Feeding Time</label>
                            <input type="time" id="feeding_time" name="feeding_time" required>
                        </div>
                        <div class="form-group">
                            <label for="cage_id">Select Cage</label>
                            <select id="cage_id" name="cage_id" required>
                                <option value="">-- Select a cage --</option>
                                <?php foreach ($pigCages as $cage): ?>
                                    <option value="<?php echo $cage['id']; ?>">Cage <?php echo htmlspecialchars($cage['cage_number']); ?> (<?php echo $cage['current_pig_count']; ?>/3 pigs)</option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="form-group">
                            <label for="feed_inventory_id">Feed Type</label>
                            <select id="feed_inventory_id" name="feed_inventory_id" required>
                                <option value="">-- Select feed --</option>
                                <?php foreach ($feedInventory as $feed): ?>
                                    <option value="<?php echo $feed['id']; ?>"><?php echo htmlspecialchars($feed['feed_type']); ?> (<?php echo number_format($feed['quantity_kg'], 2); ?> kg available)</option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="form-group">
                            <label for="amount_kg">Amount (kg)</label>
                            <input type="number" id="amount_kg" name="amount_kg" step="0.1" placeholder="Amount to feed" required>
                        </div>
                        <div class="form-group">
                            <label for="notes">Notes</label>
                            <textarea id="notes" name="notes" placeholder="Any observations..." rows="1"></textarea>
                        </div>
                    </div>
                    <div class="form-actions">
                        <button type="button" class="btn btn-secondary close-form" onclick="toggleFeedingForm()">Cancel</button>
                        <button type="submit" class="btn btn-primary">Record Feeding</button>
                    </div>
                </form>
            </div>

            <!-- Feeding History Table -->
            <div class="content-section">
                <h2 class="section-title">Recent Feeding Records</h2>

                <?php if (empty($feedingRecords)): ?>
                    <div class="empty-state">
                        <div class="empty-state-icon">🍽️</div>
                        <p>No feeding records yet. Start recording feeding activities!</p>
                    </div>
                <?php else: ?>
                    <table class="feeding-table">
                        <thead>
                            <tr>
                                <th>Date</th>
                                <th>Time</th>
                                <th>Cage</th>
                                <th>Feed Type</th>
                                <th>Quantity (kg)</th>
                                <th>Notes</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($feedingRecords as $record): ?>
                                <tr>
                                    <td><?php echo date('M d, Y', strtotime($record['feeding_date'])); ?></td>
                                    <td><?php echo htmlspecialchars($record['feeding_time']); ?></td>
                                    <td><strong><?php echo htmlspecialchars($record['cage_number'] ?? 'N/A'); ?></strong></td>
                                    <td><?php echo htmlspecialchars($record['feed_type'] ?? 'N/A'); ?></td>
                                    <td><?php echo number_format($record['amount_kg'], 2); ?></td>
                                    <td><?php echo htmlspecialchars(substr($record['notes'] ?? '', 0, 50)); ?></td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                <?php endif; ?>
            </div>

            <!-- Monthly Calendar View -->
            <div class="content-section">
                <h2 class="section-title">Monthly Feeding Overview</h2>
                
                <div id="monthYear" class="month-year-display"></div>

                <div class="calendar-view">
                    <div class="calendar-header" style="grid-column: 1;">Sun</div>
                    <div class="calendar-header" style="grid-column: 2;">Mon</div>
                    <div class="calendar-header" style="grid-column: 3;">Tue</div>
                    <div class="calendar-header" style="grid-column: 4;">Wed</div>
                    <div class="calendar-header" style="grid-column: 5;">Thu</div>
                    <div class="calendar-header" style="grid-column: 6;">Fri</div>
                    <div class="calendar-header" style="grid-column: 7;">Sat</div>
                </div>
            </div>
        </div>
    </main>

    <!-- Footer -->
    <footer>
        <div class="footer-bottom" style="border-top: 1px solid rgba(255,255,255,0.2);">
            <p>&copy; 2026 LechGO. All rights reserved.</p>
        </div>
    </footer>

    <script>
        function toggleFeedingForm() {
            const form = document.getElementById('feedingForm');
            form.classList.toggle('active');
        }

        // Set today's date as default
        document.addEventListener('DOMContentLoaded', function() {
            const today = new Date().toISOString().split('T')[0];
            document.getElementById('feeding_date').value = today;

            // Display current month
            const now = new Date();
            document.getElementById('monthYear').textContent = now.toLocaleDateString('en-US', { month: 'long', year: 'numeric' });
        });
    </script>
</body>
</html>

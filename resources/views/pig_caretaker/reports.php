<?php
/**
 * Pig Caretaker - Submit Report
 * Submit reports about pig status and feeding
 */

$user = $_SESSION['user'] ?? null;
if (!$user || $user['role'] !== 'pig_caretaker') {
    header('Location: /LechGo_Final/public/login');
    exit;
}

// Get caretaker
$caretaker = new PigCaretaker($GLOBALS['conn']);
if (!$caretaker->findByUserId($user['id'])) {
    $_SESSION['error'] = 'Caretaker profile not found';
    header('Location: /LechGo_Final/public/home');
    exit;
}

// Get caretaker's reports
$reports = [];
try {
    $query = "SELECT * FROM caretaker_reports WHERE caretaker_id = ? ORDER BY report_date DESC LIMIT 10";
    $stmt = $GLOBALS['conn']->prepare($query);
    if ($stmt) {
        $stmt->bind_param('i', $caretaker->id);
        $stmt->execute();
        $result = $stmt->get_result();
        $reports = $result->fetch_all(MYSQLI_ASSOC) ?? [];
        $stmt->close();
    }
} catch (Exception $e) {
    // Table might not exist yet
    $reports = [];
}


$currentPage = 'reports';

?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Submit Report - LechGO</title>
    <link rel="stylesheet" href="/LechGo_Final/public/styles.css">
</head>
<body>
    <div class="dashboard-layout">
        <?php include __DIR__ . '/../layouts/sidebar.php'; ?>
        
        <main class="dashboard-main">
        <div style="max-width: 900px; margin: 0 auto;">
            <!-- Header -->
            <div style="margin-bottom: var(--spacing-lg);">
                <h1 class="page-title">Submit Report</h1>
                <p class="text-gray">Share updates about pig status and feeding with your supplier</p>
            </div>

            <!-- Alerts -->
            <?php if (isset($_SESSION['success'])): ?>
            <div style="background-color: var(--success); color: white; padding: var(--spacing-md); border-radius: var(--radius-md); margin-bottom: var(--spacing-lg);">
                <?php echo htmlspecialchars($_SESSION['success']); unset($_SESSION['success']); ?>
            </div>
            <?php endif; ?>

            <!-- Report Form -->
            <div style="background: white; padding: var(--spacing-lg); border-radius: var(--radius-lg); box-shadow: var(--shadow-sm); margin-bottom: var(--spacing-lg);">
                <form method="POST" action="/LechGo_Final/public/pig-caretaker/submit-report">
                    <div class="form-group">
                        <label class="form-label">Report Title *</label>
                        <input type="text" name="title" class="form-input" placeholder="e.g., Weekly Feed Status Report" required>
                    </div>

                    <div class="form-group">
                        <label class="form-label">Report Date *</label>
                        <input type="date" name="report_date" class="form-input" value="<?php echo date('Y-m-d'); ?>" required>
                    </div>

                    <div class="form-group">
                        <label class="form-label">Report Details *</label>
                        <textarea name="content" class="form-input" placeholder="Include:&#10;- Pig health status (normal, sick, recovering)&#10;- Current pig count&#10;- Feed consumption this week&#10;- Any issues or concerns&#10;- Additional notes" rows="10" required></textarea>
                    </div>

                    <div style="display: flex; gap: var(--spacing-md);">
                        <button type="submit" class="btn btn-primary">Submit Report</button>
                        <a href="/LechGo_Final/public/home" class="btn btn-secondary">← Cancel</a>
                    </div>
                </form>
            </div>

            <!-- Recent Reports -->
            <div style="background: white; padding: var(--spacing-lg); border-radius: var(--radius-lg); box-shadow: var(--shadow-sm);">
                <h2 style="color: var(--primary-red); margin-bottom: var(--spacing-lg); font-weight: 700;">Recent Reports</h2>
                
                <?php if (empty($reports)): ?>
                    <div style="padding: var(--spacing-lg); text-align: center; color: var(--text-gray);">
                        <p>No reports submitted yet.</p>
                    </div>
                <?php else: ?>
                    <div style="display: flex; flex-direction: column; gap: var(--spacing-md);">
                        <?php foreach ($reports as $report): ?>
                            <div style="border: 1px solid var(--gray); padding: var(--spacing-md); border-radius: var(--radius-md); background-color: #fafafa;">
                                <div style="display: flex; justify-content: space-between; align-items: start;">
                                    <div>
                                        <h3 style="margin: 0 0 4px 0; font-weight: 700; color: var(--primary-red);">
                                            <?php echo htmlspecialchars($report['title']); ?>
                                        </h3>
                                        <div style="font-size: 0.85rem; color: var(--text-gray);">
                                            📅 <?php echo date('M d, Y', strtotime($report['report_date'])); ?>
                                        </div>
                                    </div>
                                </div>
                                <div style="margin-top: var(--spacing-md); white-space: pre-wrap; font-size: 0.95rem; color: var(--dark-gray);">
                                    <?php echo htmlspecialchars($report['content']); ?>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>
            </div>
        </div>

    </main>

    </div>
</body>
</html>

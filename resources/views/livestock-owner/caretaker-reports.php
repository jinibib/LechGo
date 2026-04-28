<?php
$user = $_SESSION['user'] ?? null;
if (!$user) {
    header('Location: /login');
    exit;
}

// Get livestock owner ID
$query = "SELECT id FROM livestock_owners WHERE user_id = ?";
$stmt = $GLOBALS['conn']->prepare($query);
if (!$stmt) {
    $_SESSION['error'] = 'Database error: ' . $GLOBALS['conn']->error;
    header('Location: /');
    exit;
}
$stmt->bind_param('i', $user['id']);
$stmt->execute();
$result = $stmt->get_result();
$owner = $result->fetch_assoc();
$stmt->close();

if (!$owner) {
    $_SESSION['error'] = 'Livestock owner profile not found';
    header('Location: /');
    exit;
}

// Fetch reports from assigned caretakers
// First get the caretakers assigned to this livestock owner
$query = "SELECT cr.id, cr.title, cr.content, cr.report_date, cr.created_at,
                 COALESCE(pc.full_name, pc.farm_name) AS caretaker_name, pc.location,
                 u.email AS caretaker_email
          FROM caretaker_reports cr
          JOIN pig_caretakers pc ON cr.caretaker_id = pc.id
          JOIN users u ON pc.user_id = u.id
          WHERE pc.livestock_owner_id = ?
          ORDER BY cr.created_at DESC
          LIMIT 50";

$stmt = $GLOBALS['conn']->prepare($query);
if (!$stmt) {
    $_SESSION['error'] = 'Database error: ' . $GLOBALS['conn']->error;
    header('Location: /');
    exit;
}
$stmt->bind_param('i', $owner['id']);
$stmt->execute();
$result = $stmt->get_result();
$reports = $result->fetch_all(MYSQLI_ASSOC) ?? [];
$stmt->close();

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Caretaker Reports - LechGO</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.8/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="/LechGo_Final/public/styles.css">
</head>
<body>
<div class="caretaker-reports-container">
    <div class="caretaker-reports-header">
        <h1>📋 Caretaker Reports</h1>
        <p>View reports from your pig caretakers about livestock status and feed needs</p>
    </div>

    <?php if (isset($_SESSION['success'])): ?>
        <div class="alert-success">
            <?php echo $_SESSION['success']; unset($_SESSION['success']); ?>
        </div>
    <?php endif; ?>

    <?php if (isset($_SESSION['error'])): ?>
        <div class="alert-error">
            <?php echo $_SESSION['error']; unset($_SESSION['error']); ?>
        </div>
    <?php endif; ?>

    <?php if (empty($reports)): ?>
        <div class="no-reports">
            <p>📭 No caretaker reports yet.</p>
            <p>Your assigned caretakers haven't submitted any reports yet. Check back soon!</p>
        </div>
    <?php else: ?>
        <div>
            <p class="reports-count">
                Showing <strong><?php echo count($reports); ?></strong> report(s)
            </p>

            <?php foreach ($reports as $report): ?>
                <div class="report-card">
                    <div class="report-header">
                        <div>
                            <h3 class="report-title">📝 <?php echo htmlspecialchars($report['title']); ?></h3>
                            <div class="report-meta">
                                <strong><?php echo htmlspecialchars($report['caretaker_name']); ?></strong>
                                from <strong><?php echo htmlspecialchars($report['location']); ?></strong>
                            </div>
                        </div>
                        <div class="report-date">
                            <?php echo date('M d, Y', strtotime($report['created_at'])); ?>
                        </div>
                    </div>

                    <div class="caretaker-info">
                        <strong>Reported Date:</strong> <?php echo date('M d, Y', strtotime($report['report_date'])); ?>
                        | <strong>Contact:</strong> <?php echo htmlspecialchars($report['caretaker_email']); ?>
                    </div>

                    <div class="report-content">
                        <?php echo nl2br(htmlspecialchars($report['content'])); ?>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>
</div>
</body>
</html>

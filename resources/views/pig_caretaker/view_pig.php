<?php
/**
 * View Pigs - All pigs across all pins
 * Pig Caretaker
 */

$currentPage = 'pigs';
$sessionMiddleware = new Session();
$user = $sessionMiddleware->getUser();

if (!$sessionMiddleware->isAuthenticated() || $user['role'] !== 'pig_caretaker') {
    header('Location: /LechGo_Final/public/login'); exit;
}

global $conn;
$pigCaretaker = new PigCaretaker($conn);
if (!$pigCaretaker->findByUserId($user['id'])) {
    header('Location: /LechGo_Final/public/complete-profile'); exit;
}

// Fetch all pigs grouped by pin
$pinsWithPigs = [];
$stmt = $conn->prepare(
    "SELECT pp.id as pin_id, pp.cage_number as pin_number, pp.current_pig_count, pp.max_capacity, pp.status as pin_status,
            pd.id as pig_id, pd.pig_tag_id, pd.age_months, pd.weight_kg,
            pd.health_status, pd.date_added, pd.photo_url, pd.aic_file, pd.brgy_cert_file, pd.status as pig_status
     FROM pig_pins pp
     LEFT JOIN pig_details pd ON pd.cage_id = pp.id AND pd.status = 'active'
     WHERE pp.caretaker_id = ?
     ORDER BY pp.cage_number + 0 ASC, pd.id ASC"
);
if ($stmt) {
    $stmt->bind_param('i', $pigCaretaker->id);
    $stmt->execute();
    $rows = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
    $stmt->close();

    foreach ($rows as $row) {
        $pid = $row['pin_id'];
        if (!isset($pinsWithPigs[$pid])) {
            $pinsWithPigs[$pid] = [
                'pin_number'        => $row['pin_number'],
                'current_pig_count' => $row['current_pig_count'],
                'max_capacity'      => $row['max_capacity'],
                'pin_status'        => $row['pin_status'],
                'pigs'              => []
            ];
        }
        if ($row['pig_id']) {
            $pinsWithPigs[$pid]['pigs'][] = $row;
        }
    }
}

$totalPigs = array_sum(array_column($pinsWithPigs, 'current_pig_count'));
$totalPins = count($pinsWithPigs);
$activePins = count(array_filter($pinsWithPigs, fn($p) => $p['current_pig_count'] > 0));
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>View Pigs - LechGO</title>
    <link rel="stylesheet" href="/LechGo_Final/public/styles.css">
</head>
<body>
<div class="dashboard-layout">
    <?php include __DIR__ . '/../layouts/sidebar.php'; ?>
    <main class="dashboard-main">
    <div class="vp-wrapper">

        <?php if (isset($_SESSION['success'])): ?>
            <div class="alert alert-success show"><?php echo htmlspecialchars($_SESSION['success']); unset($_SESSION['success']); ?></div>
        <?php endif; ?>
        <?php if (isset($_SESSION['error'])): ?>
            <div class="alert alert-error show"><?php echo htmlspecialchars($_SESSION['error']); unset($_SESSION['error']); ?></div>
        <?php endif; ?>

        <!-- Header -->
        <div class="vp-header">
            <div style="display:flex; align-items:center; gap:10px;">
                <a href="/LechGo_Final/public/pig-caretaker/pigs"
                   style="display:inline-flex;align-items:center;gap:4px;background:#f0f0f0;color:#555;border-radius:7px;padding:5px 12px;font-size:.82rem;font-weight:700;text-decoration:none;">
                    ← Back
                </a>
                <div>
                    <h1> Pig Inventory</h1>
                    <p style="margin:2px 0 0; color:#888; font-size:.82rem;">All pigs across all pins</p>
                </div>
            </div>
            <button class="btn-report" onclick="openReport()"> Report Piggery Status</button>
        </div>

        <!-- Stats -->
        <div class="vp-stats">
            <div class="vp-stat">
                <div class="val"><?php echo $totalPigs; ?></div>
                <div class="lbl">Total Pigs</div>
            </div>
            <div class="vp-stat">
                <div class="val"><?php echo $activePins; ?>/<?php echo $totalPins; ?></div>
                <div class="lbl">Active Pins</div>
            </div>
            <div class="vp-stat">
                <div class="val"><?php echo ($totalPins * 3) - $totalPigs; ?></div>
                <div class="lbl">Available Slots</div>
            </div>
        </div>

        <!-- Pins + Pigs -->
        <?php foreach ($pinsWithPigs as $pin): ?>
        <div class="pin-section">
            <div class="pin-header">
                <h2 class="pin-title">Pin <?php echo htmlspecialchars($pin['pin_number']); ?></h2>
                <span class="pin-count"><?php echo $pin['current_pig_count']; ?>/<?php echo $pin['max_capacity']; ?> pigs</span>
                <span class="pin-badge <?php echo $pin['current_pig_count'] > 0 ? 'active' : 'inactive'; ?>">
                    <?php echo $pin['current_pig_count'] > 0 ? 'Active' : 'Inactive'; ?>
                </span>
            </div>

            <?php if (empty($pin['pigs'])): ?>
                <div class="pin-empty"> No pigs in this pin yet.</div>
            <?php else: ?>
                <div class="pigs-grid">
                    <?php foreach ($pin['pigs'] as $pig): ?>
                    <div class="pig-card">
                        <?php if ($pig['photo_url']): ?>
                            <img src="<?php echo htmlspecialchars($pig['photo_url']); ?>" class="pig-photo" alt="Pig photo">
                        <?php else: ?>
                            <div class="pig-photo-placeholder"></div>
                        <?php endif; ?>

                        <div class="pig-info">
                            <div class="pig-tag"><?php echo htmlspecialchars($pig['pig_tag_id'] ?? 'No Tag'); ?></div>
                            <div class="pig-detail">Age: <span><?php echo $pig['age_months'] ? $pig['age_months'] . ' mos' : '—'; ?></span></div>
                            <div class="pig-detail">Weight: <span><?php echo $pig['weight_kg'] ? number_format($pig['weight_kg'], 1) . ' kg' : '—'; ?></span></div>
                            <div class="pig-detail">Added: <span><?php echo $pig['date_added'] ? date('M d, Y', strtotime($pig['date_added'])) : '—'; ?></span></div>
                            <span class="health-badge health-<?php echo $pig['health_status']; ?>">
                                <?php echo ucfirst($pig['health_status']); ?>
                            </span>
                        </div>

                        <?php if ($pig['aic_file'] || $pig['brgy_cert_file']): ?>
                        <div class="pig-docs">
                            <?php if ($pig['aic_file']): ?>
                                <a href="<?php echo htmlspecialchars($pig['aic_file']); ?>" target="_blank" class="doc-link"> AIC</a>
                            <?php endif; ?>
                            <?php if ($pig['brgy_cert_file']): ?>
                                <a href="<?php echo htmlspecialchars($pig['brgy_cert_file']); ?>" target="_blank" class="doc-link"> Brgy. Cert</a>
                            <?php endif; ?>
                        </div>
                        <?php endif; ?>
                    </div>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
        </div>
        <?php endforeach; ?>

    </div>
    </main>
</div>

<!-- Report Modal -->
<div class="report-modal-overlay" id="reportOverlay" onclick="closeReport()"></div>
<div class="report-modal" id="reportModal">
    <h2>📋 Report Piggery Status</h2>
    <form method="POST" action="/LechGo_Final/public/pig-caretaker/submit-report">
        <div>
            <label>Report Title *</label>
            <input type="text" name="title" placeholder="e.g., Weekly Health Check" required>
        </div>
        <div>
            <label>Report Date *</label>
            <input type="date" name="report_date" id="reportDate" required>
        </div>
        <div>
            <label>Overall Status</label>
            <select name="overall_status">
                <option value="good"> Good — All pigs healthy</option>
                <option value="concern"> Concern — Some issues noted</option>
                <option value="critical"> Critical — Immediate attention needed</option>
            </select>
        </div>
        <div class="report-actions">
            <button type="button" class="btn-cancel-report" onclick="closeReport()">Cancel</button>
            <button type="submit" class="btn-submit-report">Submit Report</button>
        </div>
    </form>
</div>

<script>
    function openReport() {
        document.getElementById('reportDate').value = new Date().toISOString().split('T')[0];
        document.getElementById('reportOverlay').classList.add('show');
        document.getElementById('reportModal').classList.add('show');
    }
    function closeReport() {
        document.getElementById('reportOverlay').classList.remove('show');
        document.getElementById('reportModal').classList.remove('show');
    }
</script>
</body>
</html>

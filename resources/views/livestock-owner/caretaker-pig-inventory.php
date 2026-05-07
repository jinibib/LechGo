<?php
$currentPage = 'caretaker-pig-inventory';
$user = $_SESSION['user'] ?? null;
if (!$user) { header('Location: /LechGo_Final/public/login'); exit; }

$conn = $GLOBALS['conn'];
$stmt = $conn->prepare("SELECT id FROM livestock_owners WHERE user_id = ?");
$stmt->bind_param('i', $user['id']);
$stmt->execute();
$owner = $stmt->get_result()->fetch_assoc();
$stmt->close();
if (!$owner) { header('Location: /LechGo_Final/public/home'); exit; }

// Get submitted reports from caretakers under this owner
$reports = [];
$stmt = $conn->prepare(
    "SELECT si.*, u.name as caretaker_name, pc.id as caretaker_id
     FROM swine_inventory si
     JOIN pig_caretakers pc ON si.caretaker_id = pc.id
     JOIN users u ON pc.user_id = u.id
     WHERE pc.livestock_owner_id = ?
     ORDER BY si.report_date DESC, si.created_at DESC"
);
if ($stmt) {
    $stmt->bind_param('i', $owner['id']);
    $stmt->execute();
    $reports = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
    $stmt->close();
}

// For each report, fetch the caretaker's current pig data via FK
foreach ($reports as &$report) {
    $pins = [];
    $snap = $conn->prepare(
        "SELECT pp.cage_number as pin_number, pp.current_pig_count, pp.max_capacity,
                pd.id as pig_id, pd.pig_tag_id, pd.age_months, pd.weight_kg,
                pd.health_status, pd.date_added, pd.photo_url, pd.aic_file, pd.brgy_cert_file
         FROM pig_pins pp
         LEFT JOIN pig_details pd ON pd.cage_id = pp.id AND pd.status = 'active'
         WHERE pp.caretaker_id = ?
         ORDER BY pp.cage_number + 0 ASC, pd.id ASC"
    );
    if ($snap) {
        $snap->bind_param('i', $report['caretaker_id']);
        $snap->execute();
        $rows = $snap->get_result()->fetch_all(MYSQLI_ASSOC);
        $snap->close();
        foreach ($rows as $row) {
            $pin = $row['pin_number'];
            if (!isset($pins[$pin])) {
                $pins[$pin] = [
                    'pin_number'        => $pin,
                    'current_pig_count' => $row['current_pig_count'],
                    'max_capacity'      => $row['max_capacity'],
                    'pigs'              => []
                ];
            }
            if ($row['pig_id']) $pins[$pin]['pigs'][] = $row;
        }
    }
    $report['pins'] = array_values($pins);
}
unset($report);

// Fetch ALL active pigs under this owner's caretakers (for the market section)
$allPigs = [];
$pigStmt = $conn->prepare(
    "SELECT pd.id as pig_id, pd.pig_tag_id, pd.age_months, pd.weight_kg,
            pd.health_status, pd.photo_url,
            pp.cage_number as pin_number,
            u.name as caretaker_name,
            (SELECT COUNT(*) FROM hogs_market hm WHERE hm.pig_detail_id = pd.id AND hm.status = 'active') as already_listed
     FROM pig_details pd
     JOIN pig_pins pp ON pd.cage_id = pp.id
     JOIN pig_caretakers pc ON pp.caretaker_id = pc.id
     JOIN users u ON pc.user_id = u.id
     WHERE pc.livestock_owner_id = ? AND pd.status = 'active'
     ORDER BY pp.cage_number + 0 ASC, pd.id ASC"
);
if ($pigStmt) {
    $pigStmt->bind_param('i', $owner['id']);
    $pigStmt->execute();
    $allPigs = $pigStmt->get_result()->fetch_all(MYSQLI_ASSOC);
    $pigStmt->close();
}

$statusColors = [
    'good'     => ['bg' => '#e6f9ee', 'color' => '#2d7a2d', 'label' => 'Good'],
    'concern'  => ['bg' => '#fff8e1', 'color' => '#b8860b', 'label' => 'Concern'],
    'critical' => ['bg' => '#fde8e8', 'color' => '#c0392b', 'label' => 'Critical'],
];
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Pig Inventory Reports - LechGO</title>
    <link rel="stylesheet" href="/LechGo_Final/public/styles.css">
    <style>
        .cpi-wrap { padding: .75rem 1rem; }
        .cpi-header { margin-bottom: .75rem; }
        .cpi-header h1 { font-size: 1.2rem; margin: 0; color: #333; }
        .cpi-header p  { margin: 2px 0 0; color: #888; font-size: .78rem; }

        .report-block { background:#fff; border-radius:10px; box-shadow:0 2px 6px rgba(0,0,0,.07); margin-bottom:.75rem; overflow:hidden; }
        .report-block-header {
            display:flex; align-items:center; gap:10px; padding:10px 14px;
            cursor:pointer; user-select:none; border-bottom:1.5px solid #f0f0f0;
        }
        .report-block-header:hover { background:#fdf8f8; }
        .rb-title  { font-size:.95rem; font-weight:700; color:#333; flex:1; }
        .rb-status { font-size:.68rem; font-weight:700; padding:2px 10px; border-radius:20px; white-space:nowrap; }
        .rb-meta   { font-size:.72rem; color:#888; }
        .rb-toggle { font-size:.8rem; color:#aaa; margin-left:6px; }
        .report-block-body { display:none; padding:10px 14px; }
        .report-block-body.open { display:block; }
        .rb-notes { font-size:.82rem; color:#555; line-height:1.6; white-space:pre-wrap; margin-bottom:12px; padding:8px 10px; background:#fafafa; border-radius:6px; }

        .snap-pin { margin-bottom:10px; }
        .snap-pin-header {
            display:flex; align-items:center; gap:8px; padding:5px 10px;
            background:#fdf0f0; border-radius:6px 6px 0 0;
            border:1.5px solid #f5c6c6; border-bottom:none;
        }
        .snap-pin-title { font-size:.88rem; font-weight:700; color:#c0392b; }
        .snap-pin-count { font-size:.7rem; color:#888; }
        .snap-pin-badge { margin-left:auto; font-size:.62rem; font-weight:700; padding:1px 7px; border-radius:20px; }
        .snap-pin-badge.active   { background:#e6f9ee; color:#2d7a2d; }
        .snap-pin-badge.inactive { background:#f0f0f0; color:#999; }
        .snap-pigs-grid {
            display:grid; grid-template-columns:repeat(3,1fr); gap:8px;
            padding:8px; border:1.5px solid #f5c6c6; border-top:none;
            border-radius:0 0 6px 6px; background:#fff;
        }
        .snap-pig-card { border:1.5px solid #f0e0e0; border-radius:7px; overflow:hidden; background:#fafafa; }
        .snap-pig-photo { width:100%; height:110px; object-fit:cover; }
        .snap-pig-placeholder { width:100%; height:110px; background:linear-gradient(135deg,#fde8e8,#fff0f0); display:flex; align-items:center; justify-content:center; font-size:2rem; }
        .snap-pig-info { padding:6px 8px; }
        .snap-pig-tag { font-size:.88rem; font-weight:700; color:#333; margin-bottom:3px; }
        .snap-pig-detail { font-size:.72rem; color:#666; margin-bottom:2px; }
        .snap-pig-detail span { font-weight:600; color:#444; }
        .snap-health { display:inline-block; margin-top:3px; font-size:.62rem; font-weight:700; padding:2px 7px; border-radius:20px; text-transform:uppercase; }
        .snap-health.healthy   { background:#e6f9ee; color:#2d7a2d; }
        .snap-health.sick      { background:#fde8e8; color:#c0392b; }
        .snap-health.recovering{ background:#fff8e1; color:#b8860b; }
        .snap-docs { padding:0 8px 7px; display:flex; gap:4px; flex-wrap:wrap; }
        .snap-doc  { font-size:.62rem; padding:2px 6px; border-radius:4px; text-decoration:none; font-weight:600; background:#f0f0f0; color:#555; }
        .snap-empty { padding:10px; text-align:center; color:#bbb; font-size:.78rem; border:1.5px solid #f5c6c6; border-top:none; border-radius:0 0 6px 6px; }
        .no-reports { text-align:center; padding:3rem; color:#bbb; font-size:.9rem; background:#fff; border-radius:10px; box-shadow:0 2px 6px rgba(0,0,0,.07); }

        /* Post to Market button */
        .btn-post-market {
            display:block; width:100%; margin-top:6px;
            background:#c0392b; color:#fff; border:none; border-radius:6px;
            padding:5px 0; font-size:.72rem; font-weight:700; cursor:pointer;
            letter-spacing:.3px; transition:background .15s;
        }
        .btn-post-market:hover { background:#a93226; }
        .btn-post-market.listed {
            background:#aaa; cursor:default;
        }

        /* All Pigs Market Section */
        .apm-section { margin-top:1.5rem; }
        .apm-section-title {
            font-size:1rem; font-weight:700; color:#333; margin-bottom:.6rem;
            display:flex; align-items:center; gap:8px;
        }
        .apm-section-title span { font-size:.75rem; color:#888; font-weight:400; }
        .apm-grid {
            display:grid;
            grid-template-columns: repeat(auto-fill, minmax(160px, 1fr));
            gap:12px;
        }
        .apm-card {
            background:#fff; border-radius:10px;
            box-shadow:0 2px 8px rgba(0,0,0,.08); overflow:hidden;
        }
        .apm-card-photo { width:100%; height:120px; object-fit:cover; }
        .apm-card-placeholder {
            width:100%; height:120px;
            background:linear-gradient(135deg,#fde8e8,#fff0f0);
            display:flex; align-items:center; justify-content:center; font-size:2.5rem;
        }
        .apm-card-body { padding:8px 10px; }
        .apm-card-tag { font-size:.88rem; font-weight:700; color:#333; margin-bottom:3px; }
        .apm-card-row { font-size:.72rem; color:#666; margin-bottom:2px; }
        .apm-card-row span { font-weight:600; color:#444; }
        .apm-card-caretaker { font-size:.65rem; color:#aaa; margin-top:3px; }
        .apm-health { display:inline-block; font-size:.6rem; font-weight:700; padding:2px 6px; border-radius:20px; text-transform:uppercase; margin-top:3px; }
        .apm-health.healthy    { background:#e6f9ee; color:#2d7a2d; }
        .apm-health.sick       { background:#fde8e8; color:#c0392b; }
        .apm-health.recovering { background:#fff8e1; color:#b8860b; }
        .apm-card-footer { padding:0 10px 10px; }
        .apm-btn-market {
            display:block; width:100%; background:#c0392b; color:#fff;
            border:none; border-radius:6px; padding:6px 0;
            font-size:.72rem; font-weight:700; cursor:pointer; transition:background .15s;
        }
        .apm-btn-market:hover { background:#a93226; }
        .apm-btn-market.listed {
            background:#e0e0e0; color:#999; cursor:default;
        }

        /* Modal */
        .pm-overlay {
            display:none; position:fixed; inset:0; background:rgba(0,0,0,.45);
            z-index:1000; align-items:center; justify-content:center;
        }
        .pm-overlay.open { display:flex; }
        .pm-modal {
            background:#fff; border-radius:12px; width:92%; max-width:420px;
            box-shadow:0 8px 32px rgba(0,0,0,.18); overflow:hidden;
        }
        .pm-modal-header {
            background:#c0392b; color:#fff; padding:14px 18px;
            display:flex; align-items:center; justify-content:space-between;
        }
        .pm-modal-header h3 { margin:0; font-size:1rem; }
        .pm-modal-close { background:none; border:none; color:#fff; font-size:1.3rem; cursor:pointer; line-height:1; }
        .pm-modal-body { padding:18px; }
        .pm-pig-info {
            background:#fdf0f0; border-radius:8px; padding:10px 14px;
            margin-bottom:14px; font-size:.82rem; color:#555;
        }
        .pm-pig-info strong { color:#c0392b; font-size:.95rem; display:block; margin-bottom:4px; }
        .pm-field { margin-bottom:12px; }
        .pm-field label { display:block; font-size:.78rem; font-weight:700; color:#444; margin-bottom:4px; }
        .pm-field input, .pm-field textarea {
            width:100%; padding:8px 10px; border:1.5px solid #e0e0e0; border-radius:7px;
            font-size:.85rem; box-sizing:border-box; outline:none; transition:border .15s;
        }
        .pm-field input:focus, .pm-field textarea:focus { border-color:#c0392b; }
        .pm-field textarea { resize:vertical; min-height:60px; }
        .pm-total { font-size:.82rem; color:#888; margin-bottom:14px; }
        .pm-total span { font-weight:700; color:#c0392b; font-size:.95rem; }
        .pm-actions { display:flex; gap:8px; }
        .pm-btn-submit {
            flex:1; background:#c0392b; color:#fff; border:none; border-radius:7px;
            padding:9px; font-size:.88rem; font-weight:700; cursor:pointer;
        }
        .pm-btn-submit:hover { background:#a93226; }
        .pm-btn-cancel {
            flex:1; background:#f0f0f0; color:#555; border:none; border-radius:7px;
            padding:9px; font-size:.88rem; font-weight:700; cursor:pointer;
        }
    </style>
</head>
<body>
<div class="dashboard-layout">
    <?php include __DIR__ . '/../layouts/sidebar.php'; ?>
    <main class="dashboard-main">
    <div class="cpi-wrap">

        <?php if (isset($_SESSION['success'])): ?>
            <div class="alert alert-success show"><?php echo htmlspecialchars($_SESSION['success']); unset($_SESSION['success']); ?></div>
        <?php endif; ?>

        <div class="cpi-header">
            <h1> Pig Inventory Reports</h1>
            <p>Reports submitted by your caretakers</p>
        </div>

        <?php if (empty($reports)): ?>
            <div class="no-reports">
                <div style="font-size:2rem;margin-bottom:.5rem;">   </div>
                No reports submitted yet. Your caretaker will send a report from their Pig Inventory page.
            </div>
        <?php else: ?>
            <?php foreach ($reports as $r):
                $sc = $statusColors[$r['overall_status']] ?? $statusColors['good'];
            ?>
            <div class="report-block">
                <div class="report-block-header" onclick="toggleReport(this)">
                    <div class="rb-title"><?php echo htmlspecialchars($r['title']); ?></div>
                    <span class="rb-status" style="background:<?php echo $sc['bg']; ?>;color:<?php echo $sc['color']; ?>"><?php echo $sc['label']; ?></span>
                    <div class="rb-meta">
                        <?php echo htmlspecialchars($r['caretaker_name']); ?> &nbsp;·&nbsp;
                        <?php echo date('M d, Y', strtotime($r['report_date'])); ?>
                    </div>
                    <span class="rb-toggle">▼</span>
                </div>
                <div class="report-block-body">
                    <?php if (!empty($r['content'])): ?>
                        <div class="rb-notes"><?php echo htmlspecialchars($r['content']); ?></div>
                    <?php endif; ?>

                    <?php
                    $activePins = array_filter($r['pins'], fn($p) => !empty($p['pigs']));
                    $emptyPins  = array_filter($r['pins'], fn($p) => empty($p['pigs']));
                    ?>

                    <?php foreach ($activePins as $pin): ?>
                    <div class="snap-pin">
                        <div class="snap-pin-header">
                            <span class="snap-pin-title">Pin <?php echo htmlspecialchars($pin['pin_number']); ?></span>
                            <span class="snap-pin-count"><?php echo $pin['current_pig_count']; ?>/<?php echo $pin['max_capacity']; ?> pigs</span>
                            <span class="snap-pin-badge active">Active</span>
                        </div>
                        <div class="snap-pigs-grid">
                            <?php foreach ($pin['pigs'] as $pig): ?>
                            <div class="snap-pig-card">
                                <?php if ($pig['photo_url']): ?>
                                    <img src="<?php echo htmlspecialchars($pig['photo_url']); ?>" class="snap-pig-photo" alt="Pig">
                                <?php else: ?>
                                    <div class="snap-pig-placeholder">🐷</div>
                                <?php endif; ?>
                                <div class="snap-pig-info">
                                    <div class="snap-pig-tag"><?php echo htmlspecialchars($pig['pig_tag_id'] ?? 'No Tag'); ?></div>
                                    <div class="snap-pig-detail">Age: <span><?php echo $pig['age_months'] ? $pig['age_months'] . ' mos' : '—'; ?></span></div>
                                    <div class="snap-pig-detail">Weight: <span><?php echo $pig['weight_kg'] ? number_format($pig['weight_kg'], 1) . ' kg' : '—'; ?></span></div>
                                    <div class="snap-pig-detail">Added: <span><?php echo $pig['date_added'] ? date('M d, Y', strtotime($pig['date_added'])) : '—'; ?></span></div>
                                    <span class="snap-health <?php echo $pig['health_status']; ?>"><?php echo ucfirst($pig['health_status']); ?></span>
                                </div>
                                <?php if ($pig['aic_file'] || $pig['brgy_cert_file']): ?>
                                <div class="snap-docs">
                                    <?php if ($pig['aic_file']): ?><a href="<?php echo htmlspecialchars($pig['aic_file']); ?>" target="_blank" class="snap-doc">📄 AIC</a><?php endif; ?>
                                    <?php if ($pig['brgy_cert_file']): ?><a href="<?php echo htmlspecialchars($pig['brgy_cert_file']); ?>" target="_blank" class="snap-doc">📄 Brgy. Cert</a><?php endif; ?>
                                </div>
                                <?php endif; ?>
                                <div style="padding:0 8px 8px;">
                                    <button class="btn-post-market"
                                        onclick="openPostModal(
                                            <?php echo (int)$pig['pig_id']; ?>,
                                            '<?php echo htmlspecialchars(addslashes($pig['pig_tag_id'] ?? 'No Tag')); ?>',
                                            '<?php echo htmlspecialchars(addslashes($pin['pin_number'])); ?>',
                                            <?php echo (float)($pig['weight_kg'] ?? 0); ?>
                                        )">
                                        🛒 Post to My Market
                                    </button>
                                </div>
                            </div>
                            <?php endforeach; ?>
                        </div>
                    </div>
                    <?php endforeach; ?>

                    <?php if (!empty($emptyPins)): ?>
                    <div style="font-size:.72rem;color:#bbb;padding:4px 2px;margin-top:4px;">
                        Empty pins: <?php echo implode(', ', array_map(fn($p) => 'Pin '.$p['pin_number'], $emptyPins)); ?>
                    </div>
                    <?php endif; ?>
                </div>
            </div>
            <?php endforeach; ?>
        <?php endif; ?>

    </div>
<script>
function toggleReport(header) {
    const body = header.nextElementSibling;
    const toggle = header.querySelector('.rb-toggle');
    body.classList.toggle('open');
    toggle.textContent = body.classList.contains('open') ? '▲' : '▼';
}

function openPostModal(pigId, pigTag, pinNumber, weightKg) {
    document.getElementById('pm_pig_detail_id').value = pigId;
    document.getElementById('pm_pig_tag_id').value = pigTag;
    document.getElementById('pm_pin_number').value = pinNumber;
    document.getElementById('pm_weight_kg').value = weightKg;
    document.getElementById('pm_info_tag').textContent = pigTag;
    document.getElementById('pm_info_pin').textContent = 'Pin ' + pinNumber;
    document.getElementById('pm_info_weight').textContent = weightKg + ' kg';
    document.getElementById('pm_price_per_kg').value = '';
    document.getElementById('pm_description').value = '';
    updateTotal();
    document.getElementById('pmOverlay').classList.add('open');
}

function closePostModal() {
    document.getElementById('pmOverlay').classList.remove('open');
}

function updateTotal() {
    const weight = parseFloat(document.getElementById('pm_weight_kg').value) || 0;
    const price  = parseFloat(document.getElementById('pm_price_per_kg').value) || 0;
    const total  = weight * price;
    document.getElementById('pm_total_display').textContent = total > 0
        ? '₱' + total.toLocaleString('en-PH', {minimumFractionDigits:2, maximumFractionDigits:2})
        : '—';
}
</script>

<!-- Post to Market Modal -->
<div class="pm-overlay" id="pmOverlay" onclick="if(event.target===this)closePostModal()">
    <div class="pm-modal">
        <div class="pm-modal-header">
            <h3>🛒 Post Pig to Market</h3>
            <button class="pm-modal-close" onclick="closePostModal()">✕</button>
        </div>
        <div class="pm-modal-body">
            <div class="pm-pig-info">
                <strong id="pm_info_tag">—</strong>
                <span>Pin: <b id="pm_info_pin">—</b> &nbsp;·&nbsp; Weight: <b id="pm_info_weight">—</b></span>
            </div>
            <form method="POST" action="/LechGo_Final/public/livestock-owner/post-pig-to-market">
                <input type="hidden" name="pig_detail_id" id="pm_pig_detail_id">
                <input type="hidden" name="pig_tag_id"    id="pm_pig_tag_id">
                <input type="hidden" name="pin_number"    id="pm_pin_number">
                <input type="hidden" name="weight_kg"     id="pm_weight_kg">

                <div class="pm-field">
                    <label>Price per kg (₱)</label>
                    <input type="number" name="price_per_kg" id="pm_price_per_kg"
                           min="1" step="0.01" placeholder="e.g. 180.00"
                           oninput="updateTotal()" required>
                </div>

                <div class="pm-total">
                    Estimated Total: <span id="pm_total_display">—</span>
                </div>

                <div class="pm-field">
                    <label>Description <span style="font-weight:400;color:#aaa">(optional)</span></label>
                    <textarea name="description" id="pm_description" placeholder="e.g. Healthy lechon-ready pig, well-fed..."></textarea>
                </div>

                <div class="pm-actions">
                    <button type="button" class="pm-btn-cancel" onclick="closePostModal()">Cancel</button>
                    <button type="submit" class="pm-btn-submit">Post to Market</button>
                </div>
            </form>
        </div>
    </div>
</div>
</body>
</html>

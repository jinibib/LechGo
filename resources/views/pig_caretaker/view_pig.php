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

// Fetch all pigs grouped by pin (current_pig_count computed live)
$pinsWithPigs = [];
$stmt = $conn->prepare(
    "SELECT pp.id as pin_id, pp.cage_number as pin_number,
            (SELECT COUNT(*) FROM pig_details WHERE cage_id = pp.id AND status = 'active') AS current_pig_count,
            pp.max_capacity, pp.status as pin_status,
            pd.id as pig_id, pd.pig_tag_id, pd.age_months, pd.age_days, pd.weight_kg,
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
                            <div class="pig-detail">Age: <span><?php echo isset($pig['age_days']) && $pig['age_days'] !== null ? $pig['age_days'] . ' days' : ($pig['age_months'] ? $pig['age_months'] . ' mos' : '—'); ?></span></div>
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

                        <!-- Edit / Delete buttons -->
                        <div style="padding:8px 12px 10px; border-top:1px solid #f0f0f0; margin-top:4px; display:flex; gap:6px;">
                            <button onclick="openEditPig(<?php echo htmlspecialchars(json_encode([
                                'id'            => $pig['pig_id'],
                                'pig_tag_id'    => $pig['pig_tag_id'],
                                'age_days'      => $pig['age_days'] ?? ($pig['age_months'] * 30),
                                'weight_kg'     => $pig['weight_kg'],
                                'health_status' => $pig['health_status'],
                                'date_added'    => $pig['date_added'],
                                'photo_url'     => $pig['photo_url'] ?? '',
                            ])); ?>)"
                                style="flex:1;padding:6px;background:#fff3f3;color:#c0392b;border:1.5px solid #f5c6c6;border-radius:7px;font-size:.8rem;font-weight:700;cursor:pointer;">
                                 Edit
                            </button>
                            <form method="POST" action="/LechGo_Final/public/pig-caretaker/delete-pig"
                                  onsubmit="return confirm('Remove <?php echo htmlspecialchars($pig['pig_tag_id'] ?? 'this pig'); ?> from the pin? This cannot be undone.');"
                                  style="flex:1;margin:0;">
                                <input type="hidden" name="pig_id" value="<?php echo (int)$pig['pig_id']; ?>">
                                <button type="submit"
                                    style="width:100%;padding:6px;background:#f8f8f8;color:#888;border:1.5px solid #e0e0e0;border-radius:7px;font-size:.8rem;font-weight:700;cursor:pointer;">
                                     Delete
                                </button>
                            </form>
                        </div>
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

    // ── Edit Pig ────────────────────────────────────────────────────────────
    function openEditPig(pig) {
        document.getElementById('edit_pig_id').value        = pig.id;
        document.getElementById('edit_pig_tag').value       = pig.pig_tag_id;
        document.getElementById('edit_age_days').value      = pig.age_days ?? 0;
        document.getElementById('edit_weight_kg').value     = pig.weight_kg ?? '';
        document.getElementById('edit_health_status').value = pig.health_status ?? 'healthy';
        document.getElementById('edit_date_added').value    = pig.date_added ?? '';
        updateStageHint(pig.age_days ?? 0);

        // Show current photo if exists
        const photoWrap   = document.getElementById('edit_current_photo_wrap');
        const photoImg    = document.getElementById('edit_current_photo');
        const photoPreview = document.getElementById('edit_photo_preview');
        const photoHint   = document.getElementById('edit_photo_hint');
        // Reset new upload preview
        photoPreview.style.display = 'none';
        photoPreview.src = '';
        photoHint.style.display = 'block';
        if (pig.photo_url) {
            photoImg.src = pig.photo_url;
            photoWrap.style.display = 'block';
        } else {
            photoWrap.style.display = 'none';
        }

        document.getElementById('editPigOverlay').classList.add('show');
        document.getElementById('editPigModal').classList.add('show');
    }
    function closeEditPig() {
        document.getElementById('editPigOverlay').classList.remove('show');
        document.getElementById('editPigModal').classList.remove('show');
    }

    function previewEditPhoto(input) {
        const preview = document.getElementById('edit_photo_preview');
        const hint    = document.getElementById('edit_photo_hint');
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

    const FEED_STAGES = [
        { stage: 'Super Biik',  min:   5, max:  35 },
        { stage: 'Pre-Starter', min:  36, max:  50 },
        { stage: 'Starter',     min:  51, max:  80 },
        { stage: 'Grower',      min:  81, max: 115 },
        { stage: 'Grower 2',    min: 116, max: 140 },
    ];
    function updateStageHint(days) {
        const hint = document.getElementById('edit_stage_hint');
        const d = parseInt(days) || 0;
        const found = FEED_STAGES.find(s => d >= s.min && d <= s.max);
        if (found) {
            hint.textContent = '→ ' + found.stage + ' stage';
            hint.style.color = '#2d7a2d';
        } else if (d < 5) {
            hint.textContent = '→ Below Super Biik range';
            hint.style.color = '#aaa';
        } else {
            hint.textContent = '→ Beyond Grower 2 (mature)';
            hint.style.color = '#888';
        }
    }
</script>
<!-- Edit Pig Modal -->
<div class="report-modal-overlay" id="editPigOverlay" onclick="closeEditPig()"></div>
<div class="report-modal" id="editPigModal" style="max-width:440px;">
    <h2> Edit Pig</h2>
    <form method="POST" action="/LechGo_Final/public/pig-caretaker/edit-pig" enctype="multipart/form-data">
        <input type="hidden" name="pig_id" id="edit_pig_id">

        <div style="margin-bottom:.75rem;">
            <label style="font-size:.75rem;font-weight:700;color:#555;text-transform:uppercase;display:block;margin-bottom:3px;">Pig Tag ID</label>
            <input type="text" id="edit_pig_tag" readonly style="width:100%;padding:7px 10px;border:1.5px solid #e0e0e0;border-radius:8px;background:#f5f5f5;font-size:.88rem;box-sizing:border-box;">
        </div>

        <div style="display:grid;grid-template-columns:1fr 1fr;gap:.75rem;margin-bottom:.75rem;">
            <div>
                <label style="font-size:.75rem;font-weight:700;color:#555;text-transform:uppercase;display:block;margin-bottom:3px;">Age (days)</label>
                <input type="number" name="age_days" id="edit_age_days" min="0" max="500"
                       oninput="updateStageHint(this.value)"
                       style="width:100%;padding:7px 10px;border:1.5px solid #e0e0e0;border-radius:8px;font-size:.88rem;box-sizing:border-box;">
                <div id="edit_stage_hint" style="font-size:.7rem;margin-top:3px;font-weight:700;"></div>
            </div>
            <div>
                <label style="font-size:.75rem;font-weight:700;color:#555;text-transform:uppercase;display:block;margin-bottom:3px;">Weight (kg)</label>
                <input type="number" name="weight_kg" id="edit_weight_kg" step="0.1" min="0"
                       style="width:100%;padding:7px 10px;border:1.5px solid #e0e0e0;border-radius:8px;font-size:.88rem;box-sizing:border-box;">
            </div>
        </div>

        <div style="display:grid;grid-template-columns:1fr 1fr;gap:.75rem;margin-bottom:.75rem;">
            <div>
                <label style="font-size:.75rem;font-weight:700;color:#555;text-transform:uppercase;display:block;margin-bottom:3px;">Health Status</label>
                <select name="health_status" id="edit_health_status"
                        style="width:100%;padding:7px 10px;border:1.5px solid #e0e0e0;border-radius:8px;font-size:.88rem;box-sizing:border-box;">
                    <option value="healthy">Healthy</option>
                    <option value="sick">Sick</option>
                    <option value="recovering">Recovering</option>
                </select>
            </div>
            <div>
                <label style="font-size:.75rem;font-weight:700;color:#555;text-transform:uppercase;display:block;margin-bottom:3px;">Date Added</label>
                <input type="date" name="date_added" id="edit_date_added"
                       style="width:100%;padding:7px 10px;border:1.5px solid #e0e0e0;border-radius:8px;font-size:.88rem;box-sizing:border-box;">
            </div>
        </div>

        <!-- Photo upload -->
        <div style="margin-bottom:.75rem;">
            <label style="font-size:.75rem;font-weight:700;color:#555;text-transform:uppercase;display:block;margin-bottom:5px;">Pig Photo</label>
            <div id="edit_current_photo_wrap" style="margin-bottom:6px;display:none;">
                <img id="edit_current_photo" src="" alt="Current photo"
                     style="width:100%;max-height:120px;object-fit:cover;border-radius:8px;border:1.5px solid #e0e0e0;">
                <div style="font-size:.7rem;color:#aaa;margin-top:2px;">Current photo — upload a new one to replace</div>
            </div>
            <label style="display:block;border:2px dashed #e0e0e0;border-radius:8px;padding:10px;text-align:center;cursor:pointer;transition:border-color .2s;"
                   id="edit_photo_box"
                   onmouseover="this.style.borderColor='#c0392b'" onmouseout="this.style.borderColor='#e0e0e0'">
                <input type="file" name="pig_photo" accept="image/*" style="display:none;"
                       onchange="previewEditPhoto(this)">
                <span id="edit_photo_hint" style="font-size:.8rem;color:#aaa;"> Click to upload new photo</span>
                <img id="edit_photo_preview" src="" alt=""
                     style="display:none;width:100%;max-height:100px;object-fit:cover;border-radius:6px;margin-top:6px;">
            </label>
        </div>

        <div style="display:flex;gap:.5rem;margin-top:.5rem;">
            <button type="button" onclick="closeEditPig()"
                    style="flex:1;padding:8px;background:#f0f0f0;color:#555;border:none;border-radius:8px;font-weight:700;cursor:pointer;">
                Cancel
            </button>
            <button type="submit"
                    style="flex:1;padding:8px;background:#c0392b;color:#fff;border:none;border-radius:8px;font-weight:700;cursor:pointer;">
                Save Changes
            </button>
        </div>
    </form>
</div>

</body>
</html>

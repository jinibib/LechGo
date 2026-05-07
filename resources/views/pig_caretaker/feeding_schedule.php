<?php
/**
 * Feeding Schedule View
 * Pig Caretaker - Record feeding logs
 */

$sessionMiddleware = new Session();
$user = $sessionMiddleware->getUser();

if (!$sessionMiddleware->isAuthenticated()) {
    header('Location: /LechGo_Final/public/login'); exit;
}
if ($user['role'] !== 'pig_caretaker') {
    header('Location: /LechGo_Final/public/dashboard'); exit;
}

global $conn;
$pigCaretaker = new PigCaretaker($conn);
if (!$pigCaretaker->findByUserId($user['id'])) {
    $_SESSION['error'] = 'Profile not found. Please complete your profile.';
    header('Location: /LechGo_Final/public/complete-profile'); exit;
}

$pigCages      = $pigCaretaker->getPigCages();
$feedInventory = $pigCaretaker->getFeedInventory();

// Build per-cage pig data for feed guide calculation
// We need each pig's age in days = (age_months * 30) + days since date_added
$cagePigData = [];
$stmtPigs = $conn->prepare(
    "SELECT pd.cage_id,
            pd.pig_tag_id,
            pd.age_days,
            pd.date_added,
            DATEDIFF(CURDATE(), pd.date_added) AS days_since_added
     FROM pig_details pd
     INNER JOIN pig_pins pp ON pd.cage_id = pp.id
     WHERE pp.caretaker_id = ? AND pd.status = 'active'"
);
if ($stmtPigs) {
    $stmtPigs->bind_param('i', $pigCaretaker->id);
    $stmtPigs->execute();
    $pigRows = $stmtPigs->get_result()->fetch_all(MYSQLI_ASSOC);
    $stmtPigs->close();
    foreach ($pigRows as $pr) {
        $ageAtAdding  = (int)($pr['age_days'] ?? 0); // already in days
        $daysSinceAdd = max(0, (int)$pr['days_since_added']);
        $totalDays    = $ageAtAdding + $daysSinceAdd;
        $cagePigData[$pr['cage_id']][] = [
            'tag'  => $pr['pig_tag_id'],
            'days' => $totalDays,
        ];
    }
}

// Recent feeding records
$feedingRecords = [];
$stmt = $conn->prepare(
    "SELECT fs.id, fs.feeding_date, fs.feeding_time, fs.amount_kg,
            pc.cage_number,
            fi.feed_type, fi.feed_name,
            GROUP_CONCAT(pd.pig_tag_id ORDER BY pd.pig_tag_id SEPARATOR ', ') AS pig_numbers
     FROM feeding_schedule fs
     LEFT JOIN pig_pins pc ON fs.cage_id = pc.id
     LEFT JOIN feed_inventory fi ON fs.feed_inventory_id = fi.id
     LEFT JOIN pig_details pd ON pd.cage_id = fs.cage_id AND pd.status = 'active'
     WHERE fs.caretaker_id = ?
     GROUP BY fs.id, fs.feeding_date, fs.feeding_time, fs.amount_kg, pc.cage_number, fi.feed_type, fi.feed_name
     ORDER BY fs.feeding_date DESC, fs.feeding_time DESC
     LIMIT 50"
);
if ($stmt) {
    $stmt->bind_param('i', $pigCaretaker->id);
    $stmt->execute();
    $feedingRecords = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
    $stmt->close();
}

// Build feeding dates map for calendar highlight
$fedDates = [];
foreach ($feedingRecords as $r) {
    $fedDates[$r['feeding_date']] = true;
}

$currentPage = 'feeding-schedule';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Feeding Schedule - LechGO</title>
    <link rel="stylesheet" href="/LechGo_Final/public/styles.css">
    <style>
        .fs-grid { display: grid; grid-template-columns: 1fr 1fr; gap: 1.25rem; }
        @media(max-width:900px){ .fs-grid { grid-template-columns: 1fr; } }

        /* Record form card */
        .record-card {
            background: #fff;
            border-radius: 12px;
            box-shadow: 0 2px 8px rgba(0,0,0,.08);
            padding: 1.25rem;
        }
        .record-card h2 {
            font-size: 1rem;
            font-weight: 700;
            color: var(--primary-color, #c0392b);
            margin: 0 0 1rem;
            text-transform: uppercase;
            letter-spacing: .04em;
        }
        .record-card .form-row {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: .75rem;
            margin-bottom: .75rem;
        }
        .record-card .form-row.full { grid-template-columns: 1fr; }
        .record-card label {
            display: block;
            font-size: .75rem;
            font-weight: 700;
            color: #555;
            text-transform: uppercase;
            letter-spacing: .03em;
            margin-bottom: 4px;
        }
        .record-card input,
        .record-card select,
        .record-card textarea {
            width: 100%;
            padding: .45rem .6rem;
            border: 1.5px solid #e0e0e0;
            border-radius: 8px;
            font-size: .88rem;
            background: #fafafa;
            box-sizing: border-box;
        }
        .record-card input:focus,
        .record-card select:focus,
        .record-card textarea:focus {
            outline: none;
            border-color: var(--primary-color, #c0392b);
            background: #fff;
        }
        .record-card .feed-avail {
            font-size: .7rem;
            color: #999;
            margin-top: 3px;
        }
        .btn-record {
            width: 100%;
            padding: .6rem;
            font-size: .9rem;
            font-weight: 700;
            margin-top: .5rem;
        }

        /* Calendar card */
        .cal-card {
            background: #fff;
            border-radius: 12px;
            box-shadow: 0 2px 8px rgba(0,0,0,.08);
            padding: 1.25rem;
        }
        .cal-card h2 {
            font-size: 1rem;
            font-weight: 700;
            color: var(--primary-color, #c0392b);
            margin: 0 0 .75rem;
            text-transform: uppercase;
            letter-spacing: .04em;
        }
        .cal-nav {
            display: flex;
            align-items: center;
            justify-content: space-between;
            margin-bottom: .75rem;
        }
        .cal-nav button {
            background: none;
            border: 1.5px solid #e0e0e0;
            border-radius: 6px;
            padding: 4px 10px;
            cursor: pointer;
            font-size: .85rem;
            color: #555;
        }
        .cal-nav button:hover { border-color: var(--primary-color, #c0392b); color: var(--primary-color, #c0392b); }
        .cal-month { font-weight: 700; font-size: .95rem; color: #333; }
        .cal-grid {
            display: grid;
            grid-template-columns: repeat(7, 1fr);
            gap: 3px;
        }
        .cal-day-header {
            text-align: center;
            font-size: .68rem;
            font-weight: 700;
            color: #aaa;
            text-transform: uppercase;
            padding: 4px 0;
        }
        .cal-day {
            text-align: center;
            padding: 6px 2px;
            border-radius: 6px;
            font-size: .8rem;
            color: #555;
            cursor: default;
            min-height: 32px;
            display: flex;
            align-items: center;
            justify-content: center;
        }
        .cal-day.empty { background: none; }
        .cal-day.today { border: 2px solid var(--primary-color, #c0392b); font-weight: 700; color: var(--primary-color, #c0392b); }
        .cal-day.fed { background: #fde8e8; color: var(--primary-color, #c0392b); font-weight: 700; }
        .cal-day.fed.today { background: var(--primary-color, #c0392b); color: #fff; }

        /* Records table */
        .records-card {
            background: #fff;
            border-radius: 12px;
            box-shadow: 0 2px 8px rgba(0,0,0,.08);
            padding: 1.25rem;
            margin-top: 1.25rem;
        }
        .records-card h2 {
            font-size: 1rem;
            font-weight: 700;
            color: var(--primary-color, #c0392b);
            margin: 0 0 1rem;
            text-transform: uppercase;
            letter-spacing: .04em;
        }
        .records-card table { width: 100%; border-collapse: collapse; font-size: .85rem; }
        .records-card th {
            background: #fdf0f0;
            color: var(--primary-color, #c0392b);
            font-size: .72rem;
            font-weight: 700;
            text-transform: uppercase;
            letter-spacing: .04em;
            padding: 8px 10px;
            text-align: left;
            border-bottom: 2px solid #f5c6c6;
        }
        .records-card td {
            padding: 10px 10px;
            border-bottom: 1px solid #f5f5f5;
            color: #444;
            vertical-align: middle;
        }
        .records-card tr:last-child td { border-bottom: none; }
        .records-card tr:hover td { background: #fdf8f8; }
        .cage-badge {
            display: inline-block;
            background: #fde8e8;
            color: var(--primary-color, #c0392b);
            font-weight: 700;
            font-size: .78rem;
            padding: 2px 8px;
            border-radius: 20px;
        }
        .empty-records {
            text-align: center;
            padding: 2rem;
            color: #aaa;
            font-size: .9rem;
        }

        /* Session picker */
        .session-picker {
            display: flex;
            gap: 6px;
            margin-top: 2px;
        }
        .session-opt {
            flex: 1;
            cursor: pointer;
        }
        .session-opt input[type="radio"] { display: none; }
        .session-opt span {
            display: flex;
            flex-direction: column;
            align-items: center;
            padding: 7px 4px;
            border: 1.5px solid #e0e0e0;
            border-radius: 8px;
            font-size: .78rem;
            font-weight: 700;
            color: #555;
            background: #fafafa;
            transition: all .15s;
            text-align: center;
            line-height: 1.3;
        }
        .session-opt span small {
            font-weight: 400;
            font-size: .68rem;
            color: #aaa;
            display: block;
        }
        .session-opt input[type="radio"]:checked + span {
            border-color: var(--primary-color, #c0392b);
            background: #fde8e8;
            color: var(--primary-color, #c0392b);
        }
        .session-opt span:hover {
            border-color: #c0392b88;
        }
    </style>
</head>
<body>
<div class="dashboard-layout">
    <?php include __DIR__ . '/../layouts/sidebar.php'; ?>
    <main class="dashboard-main">
    <div class="schedule-container" style="max-width:1100px; margin:0 auto; padding:1rem;">

        <!-- Flash messages -->
        <?php if (isset($_SESSION['success'])): ?>
            <div class="alert alert-success show"><?php echo htmlspecialchars($_SESSION['success']); unset($_SESSION['success']); ?></div>
        <?php endif; ?>
        <?php if (isset($_SESSION['error'])): ?>
            <div class="alert alert-error show"><?php echo htmlspecialchars($_SESSION['error']); unset($_SESSION['error']); ?></div>
        <?php endif; ?>

        <!-- Header -->
        <div style="margin-bottom:1.25rem;">
            <h1 style="font-size:1.5rem; margin:0; color:#333;"> Feeding Schedule</h1>
            <p style="margin:4px 0 0; color:#888; font-size:.88rem;">Record and track daily feeding activities</p>
        </div>

        <div class="fs-grid">
            <!-- Record Feeding Form -->
            <div class="record-card">
                <h2>Record Feeding</h2>
                <form method="POST" action="/LechGo_Final/public/pig-caretaker/record-feeding">
                    <div class="form-row">
                        <div>
                            <label for="feeding_date">Date</label>
                            <input type="date" id="feeding_date" name="feeding_date" required>
                        </div>
                        <div>
                            <label>Feeding Session</label>
                            <div class="session-picker">
                                <label class="session-opt">
                                    <input type="radio" name="feeding_session" value="morning" required>
                                    <span>Morning<small>7:00 AM</small></span>
                                </label>
                                <label class="session-opt">
                                    <input type="radio" name="feeding_session" value="noon">
                                    <span>Noon<small>On demand</small></span>
                                </label>
                                <label class="session-opt">
                                    <input type="radio" name="feeding_session" value="afternoon">
                                    <span>Afternoon<small>5:00 PM</small></span>
                                </label>
                            </div>
                            <!-- hidden time field auto-filled by JS -->
                            <input type="hidden" id="feeding_time" name="feeding_time" value="07:00">
                        </div>
                    </div>
                    <div class="form-row">
                        <div>
                            <label for="cage_id">Pin</label>
                            <select id="cage_id" name="cage_id" required>
                                <option value="">-- Select pin --</option>
                                <?php foreach ($pigCages as $cage): ?>
                                    <?php
                                        $pigsJson = htmlspecialchars(
                                            json_encode($cagePigData[$cage['id']] ?? []),
                                            ENT_QUOTES
                                        );
                                    ?>
                                    <option value="<?php echo $cage['id']; ?>"
                                            data-pigs="<?php echo $cage['current_pig_count']; ?>"
                                            data-pig-info="<?php echo $pigsJson; ?>">
                                        Pin <?php echo htmlspecialchars($cage['cage_number']); ?>
                                        (<?php echo $cage['current_pig_count']; ?>/<?php echo $cage['max_capacity']; ?> pigs)
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div>
                            <label for="amount_kg">Amount (kg) <span id="amount_hint" style="font-weight:400;color:#aaa;font-size:.7rem;"></span></label>
                            <input type="number" id="amount_kg" name="amount_kg" step="0.001" min="0.001" placeholder="Select a pin first" required readonly
                                   style="background:#f0f0f0; cursor:not-allowed;">
                        </div>
                    </div>
                    <div class="form-row full">
                        <div>
                            <label for="feed_inventory_id">Feed</label>
                            <select id="feed_inventory_id" name="feed_inventory_id" required>
                                <option value="">-- Select feed --</option>
                                <?php foreach ($feedInventory as $feed): ?>
                                    <?php
                                        $label = $feed['feed_name'] && $feed['feed_name'] !== 'Feed'
                                            ? $feed['feed_name'] . ' (' . $feed['feed_type'] . ')'
                                            : $feed['feed_type'];
                                    ?>
                                    <option value="<?php echo $feed['id']; ?>" data-max="<?php echo $feed['quantity_kg']; ?>">
                                        <?php echo htmlspecialchars($label); ?> — <?php echo number_format($feed['quantity_kg'], 1); ?> kg left
                                    </option>
                                <?php endforeach; ?>
                            </select>
                            <div class="feed-avail" id="feed_avail_hint"></div>
                        </div>
                    </div>
                    <button type="submit" class="btn btn-primary btn-record"> Record Feeding</button>
                </form>
            </div>

            <!-- Calendar -->
            <div class="cal-card">
                <h2>Monthly Overview</h2>
                <div class="cal-nav">
                    <button onclick="changeMonth(-1)">&#8249;</button>
                    <span class="cal-month" id="calMonthLabel"></span>
                    <button onclick="changeMonth(1)">&#8250;</button>
                </div>
                <div class="cal-grid" id="calGrid">
                    <div class="cal-day-header">Sun</div>
                    <div class="cal-day-header">Mon</div>
                    <div class="cal-day-header">Tue</div>
                    <div class="cal-day-header">Wed</div>
                    <div class="cal-day-header">Thu</div>
                    <div class="cal-day-header">Fri</div>
                    <div class="cal-day-header">Sat</div>
                </div>
                <div style="margin-top:.75rem; display:flex; gap:1rem; font-size:.75rem; color:#888;">
                    <span><span style="display:inline-block;width:12px;height:12px;background:#fde8e8;border-radius:3px;margin-right:4px;vertical-align:middle;"></span>Fed</span>
                    <span><span style="display:inline-block;width:12px;height:12px;background:#c0392b;border-radius:3px;margin-right:4px;vertical-align:middle;"></span>Today (fed)</span>
                    <span><span style="display:inline-block;width:12px;height:12px;border:2px solid #c0392b;border-radius:3px;margin-right:4px;vertical-align:middle;"></span>Today</span>
                </div>
            </div>
        </div>

        <!-- Feed Guide Reference -->
        <div class="records-card" style="margin-top:1.25rem;">
            <h2> Feed Guide Reference</h2>
            <p style="font-size:.82rem;color:#888;margin:0 0 .75rem;">Based on Average Daily Feed Intake — amount auto-calculated when you select a pin.</p>
            <div style="overflow-x:auto;">
                <table style="width:100%;border-collapse:collapse;font-size:.85rem;">
                    <thead>
                        <tr>
                            <th style="background:#fdf0f0;color:#c0392b;font-size:.72rem;font-weight:700;text-transform:uppercase;letter-spacing:.04em;padding:8px 12px;text-align:left;border-bottom:2px solid #f5c6c6;">Stage</th>
                            <th style="background:#fdf0f0;color:#c0392b;font-size:.72rem;font-weight:700;text-transform:uppercase;letter-spacing:.04em;padding:8px 12px;text-align:left;border-bottom:2px solid #f5c6c6;">Age (Days)</th>
                            <th style="background:#fdf0f0;color:#c0392b;font-size:.72rem;font-weight:700;text-transform:uppercase;letter-spacing:.04em;padding:8px 12px;text-align:left;border-bottom:2px solid #f5c6c6;">Daily Feed / Pig</th>
                            <th style="background:#fdf0f0;color:#c0392b;font-size:.72rem;font-weight:700;text-transform:uppercase;letter-spacing:.04em;padding:8px 12px;text-align:left;border-bottom:2px solid #f5c6c6;">Per Session (÷3)</th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr><td style="padding:9px 12px;border-bottom:1px solid #f5f5f5;">Super Biik</td><td style="padding:9px 12px;border-bottom:1px solid #f5f5f5;">5 – 35</td><td style="padding:9px 12px;border-bottom:1px solid #f5f5f5;">0.10 – 0.25 kg</td><td style="padding:9px 12px;border-bottom:1px solid #f5f5f5;"><strong>0.083 kg</strong> (0.25÷3)</td></tr>
                        <tr><td style="padding:9px 12px;border-bottom:1px solid #f5f5f5;">Pre-Starter</td><td style="padding:9px 12px;border-bottom:1px solid #f5f5f5;">36 – 50</td><td style="padding:9px 12px;border-bottom:1px solid #f5f5f5;">0.25 – 0.50 kg</td><td style="padding:9px 12px;border-bottom:1px solid #f5f5f5;"><strong>0.167 kg</strong> (0.50÷3)</td></tr>
                        <tr><td style="padding:9px 12px;border-bottom:1px solid #f5f5f5;">Starter</td><td style="padding:9px 12px;border-bottom:1px solid #f5f5f5;">51 – 80</td><td style="padding:9px 12px;border-bottom:1px solid #f5f5f5;">0.50 – 1.20 kg</td><td style="padding:9px 12px;border-bottom:1px solid #f5f5f5;"><strong>0.400 kg</strong> (1.20÷3)</td></tr>
                        <tr><td style="padding:9px 12px;border-bottom:1px solid #f5f5f5;">Grower</td><td style="padding:9px 12px;border-bottom:1px solid #f5f5f5;">81 – 115</td><td style="padding:9px 12px;border-bottom:1px solid #f5f5f5;">1.20 – 2.20 kg</td><td style="padding:9px 12px;border-bottom:1px solid #f5f5f5;"><strong>0.733 kg</strong> (2.20÷3)</td></tr>
                        <tr><td style="padding:9px 12px;">Grower 2</td><td style="padding:9px 12px;">116 – 140</td><td style="padding:9px 12px;">2.20 – 2.50 kg</td><td style="padding:9px 12px;"><strong>0.833 kg</strong> (2.50÷3)</td></tr>
                    </tbody>
                </table>
            </div>
            <p style="font-size:.75rem;color:#aaa;margin:.6rem 0 0;">* Amount auto-filled uses the <strong>maximum</strong> of the daily range ÷ 3 sessions × number of pigs — para dili ma-lugi ang baboy.</p>
        </div>

        <!-- Recent Records -->
        <div class="records-card">
            <h2>Recent Feeding Records</h2>
            <?php if (empty($feedingRecords)): ?>
                <div class="empty-records">
                    <div style="font-size:2rem; margin-bottom:.5rem;"></div>
                    No feeding records yet. Start recording feeding activities!
                </div>
            <?php else: ?>
                <table>
                    <thead>     
                        <tr>
                            <th>Date</th>
                            <th>Time</th>
                            <th>Pin</th>
                            <th>Pig Number</th>
                            <th>Feed</th>
                            <th>Amount (kg)</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($feedingRecords as $r): ?>
                        <tr>
                            <td><?php echo date('M d, Y', strtotime($r['feeding_date'])); ?></td>
                            <td><?php echo date('h:i A', strtotime($r['feeding_time'])); ?></td>
                            <td><span class="cage-badge">Pin <?php echo htmlspecialchars($r['cage_number'] ?? '?'); ?></span></td>
                            <td style="color:#555; font-size:.85rem;"><?php echo htmlspecialchars($r['pig_numbers'] ?? '—'); ?></td>
                            <td>
                                <?php
                                    $fname = $r['feed_name'] && $r['feed_name'] !== 'Feed' ? $r['feed_name'] . ' (' . $r['feed_type'] . ')' : ($r['feed_type'] ?? 'N/A');
                                    echo htmlspecialchars($fname);
                                ?>
                            </td>
                            <td><strong><?php echo number_format($r['amount_kg'], 2); ?> kg</strong></td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            <?php endif; ?>
        </div>

    </div>
    </main>
</div>

<script>
// Fed dates from PHP
const fedDates = <?php echo json_encode(array_keys($fedDates)); ?>;

// Set defaults
document.addEventListener('DOMContentLoaded', function() {
    const now = new Date();
    document.getElementById('feeding_date').value = now.toISOString().split('T')[0];

    // Auto-select session based on current time
    const hour = now.getHours();
    let defaultSession = 'morning';
    if (hour >= 12 && hour < 17) defaultSession = 'noon';
    else if (hour >= 17) defaultSession = 'afternoon';

    const radio = document.querySelector(`input[name="feeding_session"][value="${defaultSession}"]`);
    if (radio) { radio.checked = true; updateSessionTime(defaultSession); }

    renderCalendar(now.getFullYear(), now.getMonth());
});

// Feed availability hint
document.getElementById('feed_inventory_id').addEventListener('change', function() {
    const opt = this.options[this.selectedIndex];
    const max = opt.dataset.max;
    const hint = document.getElementById('feed_avail_hint');
    hint.textContent = max ? max + ' kg available in inventory' : '';
});

// Map session to time
const sessionTimes = { morning: '07:00', noon: '12:00', afternoon: '17:00' };
function updateSessionTime(val) {
    document.getElementById('feeding_time').value = sessionTimes[val] || '07:00';
}
document.querySelectorAll('input[name="feeding_session"]').forEach(r => {
    r.addEventListener('change', () => updateSessionTime(r.value));
});

// ── Feed Guide (from image) ──────────────────────────────────────────────────
// Stage → age range in days, daily feed intake range in kg
const FEED_GUIDE = [
    { stage: 'Super Biik',  minDay:   5, maxDay:  35, minKg: 0.10, maxKg: 0.25 },
    { stage: 'Pre-Starter', minDay:  36, maxDay:  50, minKg: 0.25, maxKg: 0.50 },
    { stage: 'Starter',     minDay:  51, maxDay:  80, minKg: 0.50, maxKg: 1.20 },
    { stage: 'Grower',      minDay:  81, maxDay: 115, minKg: 1.20, maxKg: 2.20 },
    { stage: 'Grower 2',    minDay: 116, maxDay: 140, minKg: 2.20, maxKg: 2.50 },
];

function getFeedForDays(days) {
    for (const g of FEED_GUIDE) {
        if (days >= g.minDay && days <= g.maxDay) {
            // Use max of range so pigs get full recommended amount (dili ma-lugi)
            return { stage: g.stage, dailyKg: g.maxKg };
        }
    }
    if (days < 5)   return { stage: 'Super Biik',  dailyKg: 0.25 };
    return           { stage: 'Grower 2',           dailyKg: 2.50 };
}

// Auto-fill amount based on feed guide + pig age per pin
function recalcAmount() {
    const sel = document.getElementById('cage_id');
    const opt = sel.options[sel.selectedIndex];
    const amountInput = document.getElementById('amount_kg');
    const hint = document.getElementById('amount_hint');

    if (!opt || !opt.value) {
        amountInput.value = '';
        hint.textContent = '';
        return;
    }

    const pigCount = parseInt(opt.dataset.pigs) || 0;
    if (pigCount === 0) {
        amountInput.value = '';
        amountInput.style.background = '#f0f0f0';
        amountInput.style.borderColor = '#e0e0e0';
        hint.textContent = 'No pigs in this pin';
        return;
    }

    let pigInfo = [];
    try { pigInfo = JSON.parse(opt.dataset.pigInfo || '[]'); } catch(e) {}

    const SESSIONS_PER_DAY = 3; // morning, noon, afternoon
    let totalPerSession = 0;
    let lines = [];

    if (pigInfo.length > 0) {
        for (const pig of pigInfo) {
            const rec = getFeedForDays(pig.days);
            const perSession = rec.dailyKg / SESSIONS_PER_DAY;
            totalPerSession += perSession;
            lines.push(pig.tag + ' → ' + rec.stage + ' (day ' + pig.days + '): '
                + rec.dailyKg.toFixed(2) + ' kg/day ÷ 3 = '
                + perSession.toFixed(3) + ' kg/session');
        }
    } else {
        // Fallback when no age data stored
        totalPerSession = pigCount * (0.5 / SESSIONS_PER_DAY);
        lines.push(pigCount + ' pig(s) — no age data, using 0.5 kg/day default');
    }

    const rounded = Math.round(totalPerSession * 1000) / 1000;
    amountInput.value = rounded;
    amountInput.style.background = '#f0fff0';
    amountInput.style.borderColor = '#2d7a2d';

    const tipText = lines.join('\n') + '\n\nTotal per session: ' + rounded + ' kg';
    hint.innerHTML = '<span title="' + tipText.replace(/"/g, '&quot;') + '" style="cursor:help;text-decoration:underline dotted;color:#2d7a2d;">'
        + pigCount + ' pig' + (pigCount > 1 ? 's' : '') + ' · '
        + rounded + ' kg/session </span>';
}

document.getElementById('cage_id').addEventListener('change', recalcAmount);

// Calendar
let calYear, calMonth;
function changeMonth(dir) {
    calMonth += dir;
    if (calMonth < 0) { calMonth = 11; calYear--; }
    if (calMonth > 11) { calMonth = 0; calYear++; }
    renderCalendar(calYear, calMonth);
}

function renderCalendar(year, month) {
    calYear = year; calMonth = month;
    const label = new Date(year, month, 1).toLocaleDateString('en-US', { month: 'long', year: 'numeric' });
    document.getElementById('calMonthLabel').textContent = label;

    const grid = document.getElementById('calGrid');
    // Remove old day cells (keep headers = first 7)
    while (grid.children.length > 7) grid.removeChild(grid.lastChild);

    const today = new Date();
    const todayStr = today.toISOString().split('T')[0];
    const firstDay = new Date(year, month, 1).getDay();
    const daysInMonth = new Date(year, month + 1, 0).getDate();

    // Empty cells before first day
    for (let i = 0; i < firstDay; i++) {
        const el = document.createElement('div');
        el.className = 'cal-day empty';
        grid.appendChild(el);
    }

    for (let d = 1; d <= daysInMonth; d++) {
        const dateStr = year + '-' + String(month+1).padStart(2,'0') + '-' + String(d).padStart(2,'0');
        const el = document.createElement('div');
        el.className = 'cal-day';
        el.textContent = d;
        if (fedDates.includes(dateStr)) el.classList.add('fed');
        if (dateStr === todayStr) el.classList.add('today');
        grid.appendChild(el);
    }
}
</script>
</body>
</html>

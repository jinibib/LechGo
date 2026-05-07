<?php
$currentPage = 'buy-pig';
$user = $_SESSION['user'] ?? null;
if (!$user) { header('Location: /LechGo_Final/public/login'); exit; }

$conn = $GLOBALS['conn'];

// Fetch all active listings with pig + owner info
$listings = [];
$stmt = $conn->prepare(
    "SELECT hm.id, hm.pig_tag_id, hm.pin_number, hm.weight_kg, hm.price_per_kg,
            hm.total_price, hm.description, hm.created_at, hm.status,
            pd.photo_url, pd.health_status, pd.age_months,
            u.name as owner_name
     FROM hogs_market hm
     LEFT JOIN pig_details pd ON pd.id = hm.pig_detail_id
     LEFT JOIN livestock_owners lo ON lo.id = hm.livestock_owner_id
     LEFT JOIN users u ON u.id = lo.user_id
     WHERE hm.status = 'active'
     ORDER BY hm.status ASC, hm.created_at DESC"
);
if ($stmt) {
    $stmt->execute();
    $listings = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
    $stmt->close();
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Buy a Pig - LechGO</title>
    <link rel="stylesheet" href="/LechGo_Final/public/styles.css">
    <style>
        .bp-wrap { padding: .75rem 1rem; }
        .bp-header { margin-bottom: 1rem; }
        .bp-header h1 { font-size: 1.2rem; margin: 0; color: #333; }
        .bp-header p  { margin: 2px 0 0; color: #888; font-size: .78rem; }

        .bp-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(220px, 1fr));
            gap: 16px;
        }

        .bp-reserved-badge {
            position: absolute; top: 10px; left: 10px;
            background: #e67e22; color: #fff;
            font-size: .7rem; font-weight: 800;
            padding: 3px 10px; border-radius: 20px;
            letter-spacing: .3px;
        }
        .bp-card { position: relative; }
            box-shadow: 0 2px 10px rgba(0,0,0,.09);
            overflow: hidden; display: flex; flex-direction: column;
            transition: transform .15s, box-shadow .15s;
        }
        .bp-card:hover { transform: translateY(-2px); box-shadow: 0 6px 18px rgba(0,0,0,.13); }

        .bp-card-photo { width: 100%; height: 160px; object-fit: cover; }
        .bp-card-placeholder {
            width: 100%; height: 160px;
            background: linear-gradient(135deg, #fde8e8, #fff0f0);
            display: flex; align-items: center; justify-content: center; font-size: 3.5rem;
        }
        .bp-card-body { padding: 12px 14px; flex: 1; }
        .bp-card-tag { font-size: 1rem; font-weight: 800; color: #222; margin-bottom: 5px; }
        .bp-card-row { font-size: .76rem; color: #666; margin-bottom: 3px; }
        .bp-card-row span { font-weight: 600; color: #444; }
        .bp-card-seller { font-size: .68rem; color: #aaa; margin-top: 5px; }

        .bp-health { display: inline-block; font-size: .62rem; font-weight: 700;
            padding: 2px 8px; border-radius: 20px; text-transform: uppercase; margin: 4px 0; }
        .bp-health.healthy    { background: #e6f9ee; color: #2d7a2d; }
        .bp-health.sick       { background: #fde8e8; color: #c0392b; }
        .bp-health.recovering { background: #fff8e1; color: #b8860b; }

        .bp-price-row {
            display: flex; align-items: baseline; gap: 6px;
            margin-top: 8px; padding-top: 8px; border-top: 1px solid #f0f0f0;
        }
        .bp-price-kg  { font-size: .8rem; color: #888; }
        .bp-price-val { font-size: 1.1rem; font-weight: 800; color: #c0392b; }
        .bp-total     { font-size: .72rem; color: #aaa; margin-top: 2px; }

        .bp-card-footer { padding: 0 14px 14px; }
        .bp-btn-buy {
            display: block; width: 100%; background: #c0392b; color: #fff;
            border: none; border-radius: 8px; padding: 9px 0;
            font-size: .85rem; font-weight: 700; cursor: pointer;
            transition: background .15s; text-align: center;
        }
        .bp-btn-buy:hover { background: #a93226; }

        .bp-empty {
            text-align: center; padding: 4rem; color: #bbb;
            background: #fff; border-radius: 12px;
            box-shadow: 0 2px 8px rgba(0,0,0,.07);
        }

        /* Search / Filter Bar */
        .bp-search-bar {
            background: #fff; border-radius: 12px;
            box-shadow: 0 2px 8px rgba(0,0,0,.07);
            padding: 14px 16px; margin-bottom: 16px;
            display: flex; flex-wrap: wrap; gap: 10px; align-items: flex-end;
        }
        .bp-search-group { display: flex; flex-direction: column; gap: 4px; flex: 1; min-width: 140px; }
        .bp-search-group label { font-size: .72rem; font-weight: 700; color: #666; }
        .bp-search-group input, .bp-search-group select {
            padding: 7px 10px; border: 1.5px solid #e0e0e0; border-radius: 7px;
            font-size: .82rem; outline: none; transition: border .15s;
        }
        .bp-search-group input:focus, .bp-search-group select:focus { border-color: #c0392b; }
        .bp-search-btn {
            background: #c0392b; color: #fff; border: none; border-radius: 7px;
            padding: 8px 18px; font-size: .82rem; font-weight: 700; cursor: pointer;
            transition: background .15s; align-self: flex-end;
        }
        .bp-search-btn:hover { background: #a93226; }
        .bp-search-clear {
            background: #f0f0f0; color: #666; border: none; border-radius: 7px;
            padding: 8px 14px; font-size: .82rem; font-weight: 700; cursor: pointer;
            align-self: flex-end;
        }
        .bp-results-count { font-size: .78rem; color: #888; margin-bottom: 10px; }

        /* Inquiry Modal */
        .bpm-overlay {
            display: none; position: fixed; inset: 0;
            background: rgba(0,0,0,.45); z-index: 1000;
            align-items: center; justify-content: center;
        }
        .bpm-overlay.open { display: flex; }
        .bpm-modal {
            background: #fff; border-radius: 14px; width: 92%; max-width: 440px;
            box-shadow: 0 8px 32px rgba(0,0,0,.18); overflow: hidden;
        }
        .bpm-header {
            background: #c0392b; color: #fff; padding: 14px 18px;
            display: flex; align-items: center; justify-content: space-between;
        }
        .bpm-header h3 { margin: 0; font-size: 1rem; }
        .bpm-close { background: none; border: none; color: #fff; font-size: 1.3rem; cursor: pointer; }
        .bpm-body { padding: 18px; }
        .bpm-pig-summary {
            background: #fdf0f0; border-radius: 8px; padding: 12px 14px;
            margin-bottom: 14px;
        }
        .bpm-pig-summary strong { color: #c0392b; font-size: 1rem; display: block; margin-bottom: 4px; }
        .bpm-pig-summary span { font-size: .8rem; color: #666; }
        .bpm-price-big { font-size: 1.3rem; font-weight: 800; color: #c0392b; margin-top: 6px; display: block; }
        .bpm-field { margin-bottom: 12px; }
        .bpm-field label { display: block; font-size: .78rem; font-weight: 700; color: #444; margin-bottom: 4px; }
        .bpm-field textarea {
            width: 100%; padding: 8px 10px; border: 1.5px solid #e0e0e0;
            border-radius: 7px; font-size: .85rem; box-sizing: border-box;
            resize: vertical; min-height: 70px; outline: none;
        }
        .bpm-field textarea:focus { border-color: #c0392b; }
        .bpm-actions { display: flex; gap: 8px; }
        .bpm-btn-submit {
            flex: 1; background: #c0392b; color: #fff; border: none;
            border-radius: 7px; padding: 10px; font-size: .88rem; font-weight: 700; cursor: pointer;
        }
        .bpm-btn-submit:hover { background: #a93226; }
        .bpm-btn-cancel {
            flex: 1; background: #f0f0f0; color: #555; border: none;
            border-radius: 7px; padding: 10px; font-size: .88rem; font-weight: 700; cursor: pointer;
        }
    </style>
</head>
<body>
<div class="dashboard-layout">
    <?php include __DIR__ . '/../layouts/sidebar.php'; ?>
    <main class="dashboard-main">
    <div class="bp-wrap">

        <?php if (isset($_SESSION['success'])): ?>
            <div class="alert alert-success show"><?php echo htmlspecialchars($_SESSION['success']); unset($_SESSION['success']); ?></div>
        <?php endif; ?>
        <?php if (isset($_SESSION['error'])): ?>
            <div class="alert alert-danger show"><?php echo htmlspecialchars($_SESSION['error']); unset($_SESSION['error']); ?></div>
        <?php endif; ?>

        <div class="bp-header">
            <h1> Buy a Pig</h1>
            <p><?php echo count($listings); ?> pig<?php echo count($listings) !== 1 ? 's' : ''; ?> available for sale</p>
        </div>

        <!-- Search & Filter Bar -->
        <div class="bp-search-bar">
            <div class="bp-search-group">
                <label>Weight (kg)</label>
                <input type="number" id="sf_weight" min="0" step="1" placeholder="e.g. 80" oninput="applyFilters()">
            </div>
            <div class="bp-search-group">
                <label>Price (₱)</label>
                <input type="number" id="sf_max_price" min="0" step="1" placeholder="e.g. 200" oninput="applyFilters()">
            </div>
            <button class="bp-search-clear" onclick="clearFilters()">Clear</button>
        </div>
        <div class="bp-results-count" id="bp_count"></div>

        <?php if (empty($listings)): ?>
            <div class="bp-empty">
                <div style="font-size:3rem;margin-bottom:.5rem;"></div>
                <div>No pigs available right now. Check back later!</div>
            </div>
        <?php else: ?>
            <div class="bp-grid" id="bp_grid">
                <?php foreach ($listings as $l): ?>
                <div class="bp-card"
                    data-tag="<?php echo strtolower(htmlspecialchars($l['pig_tag_id'])); ?>"
                    data-seller="<?php echo strtolower(htmlspecialchars($l['owner_name'])); ?>"
                    data-weight="<?php echo (float)$l['weight_kg']; ?>"
                    data-price="<?php echo (float)$l['price_per_kg']; ?>"
                    data-health="<?php echo htmlspecialchars($l['health_status'] ?? 'healthy'); ?>">
                    <?php if (!empty($l['photo_url'])): ?>
                        <img src="<?php echo htmlspecialchars($l['photo_url']); ?>" class="bp-card-photo" alt="Pig">
                    <?php else: ?>
                        <div class="bp-card-placeholder"></div>
                    <?php endif; ?>
                    <?php if ($l['status'] === 'reserved'): ?>
                        <div class="bp-reserved-badge">🔒 Reserved</div>
                    <?php endif; ?>
                    <div class="bp-card-body">
                        <div class="bp-card-tag"><?php echo htmlspecialchars($l['pig_tag_id']); ?></div>
                        <div class="bp-card-row">Pin: <span><?php echo htmlspecialchars($l['pin_number']); ?></span></div>
                        <div class="bp-card-row">Weight: <span><?php echo number_format($l['weight_kg'], 1); ?> kg</span></div>
                        <?php if ($l['age_months']): ?>
                        <div class="bp-card-row">Age: <span><?php echo $l['age_months']; ?> months</span></div>
                        <?php endif; ?>
                        <span class="bp-health <?php echo htmlspecialchars($l['health_status'] ?? 'healthy'); ?>">
                            <?php echo ucfirst($l['health_status'] ?? 'healthy'); ?>
                        </span>
                        <?php if (!empty($l['description'])): ?>
                        <div class="bp-card-row" style="margin-top:5px;font-style:italic;color:#999;">
                            "<?php echo htmlspecialchars($l['description']); ?>"
                        </div>
                        <?php endif; ?>
                        <div class="bp-price-row">
                            <div>
                                <div class="bp-price-kg">Price/kg</div>
                                <div class="bp-price-val">₱<?php echo number_format($l['price_per_kg'], 2); ?></div>
                            </div>
                        </div>
                        <div class="bp-total">Total: ₱<?php echo number_format($l['total_price'], 2); ?></div>
                        <div class="bp-card-seller"> <?php echo htmlspecialchars($l['owner_name']); ?></div>
                    </div>
                    <div class="bp-card-footer">
                        <?php if ($l['status'] === 'reserved'): ?>
                            <button class="bp-btn-buy" disabled style="background:#e67e22;cursor:not-allowed;opacity:.9;">
                                 Reserved
                            </button>
                        <?php else: ?>
                        <button class="bp-btn-buy" onclick="openInquiry(
                            <?php echo $l['id']; ?>,
                            '<?php echo htmlspecialchars(addslashes($l['pig_tag_id'])); ?>',
                            <?php echo (float)$l['weight_kg']; ?>,
                            <?php echo (float)$l['price_per_kg']; ?>,
                            <?php echo (float)$l['total_price']; ?>,
                            '<?php echo htmlspecialchars(addslashes($l['owner_name'])); ?>'
                        )"> RESERVE </button>
                        <?php endif; ?>
                    </div>
                </div>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>

    </div>
    </main>
</div>

<!-- Inquiry / Buy Modal -->
<div class="bpm-overlay" id="bpmOverlay" onclick="if(event.target===this)closeInquiry()">
    <div class="bpm-modal">
        <div class="bpm-header">
            <h3> Buy This Pig</h3>
            <button class="bpm-close" onclick="closeInquiry()">✕</button>
        </div>
        <div class="bpm-body">
            <div class="bpm-pig-summary">
                <strong id="bpm_tag">—</strong>
                <span id="bpm_details">—</span>
                <span class="bpm-price-big" id="bpm_total">—</span>
            </div>
            <form method="POST" action="/LechGo_Final/public/customer/pig-inquiry">
                <input type="hidden" name="listing_id" id="bpm_listing_id">
                <div class="bpm-field">
                    <label>Your Message to the Seller</label>
                    <textarea name="message" placeholder="e.g. I'm interested in this pig. When can I pick it up?" required></textarea>
                </div>
                <div class="bpm-actions">
                    <button type="button" class="bpm-btn-cancel" onclick="closeInquiry()">Cancel</button>
                    <button type="submit" class="bpm-btn-submit">Send Inquiry</button>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
function openInquiry(id, tag, weight, pricePerKg, total, seller) {
    document.getElementById('bpm_listing_id').value = id;
    document.getElementById('bpm_tag').textContent = tag;
    document.getElementById('bpm_details').textContent =
        weight + ' kg · ₱' + pricePerKg.toLocaleString('en-PH', {minimumFractionDigits:2}) + '/kg · Seller: ' + seller;
    document.getElementById('bpm_total').textContent =
        'Total: ₱' + total.toLocaleString('en-PH', {minimumFractionDigits:2});
    document.getElementById('bpmOverlay').classList.add('open');
}
function closeInquiry() {
    document.getElementById('bpmOverlay').classList.remove('open');
}

function applyFilters() {
    const weightFilter = parseFloat(document.getElementById('sf_weight').value) || 0;
    const maxPrice     = parseFloat(document.getElementById('sf_max_price').value) || Infinity;

    const cards = document.querySelectorAll('#bp_grid .bp-card');
    let visible = 0;

    cards.forEach(card => {
        const weight = parseFloat(card.dataset.weight) || 0;
        const price  = parseFloat(card.dataset.price)  || 0;

        const matchWeight = !weightFilter || weight <= weightFilter;
        const matchPrice  = price <= maxPrice;

        const show = matchWeight && matchPrice;
        card.style.display = show ? '' : 'none';
        if (show) visible++;
    });

    const countEl = document.getElementById('bp_count');
    countEl.textContent = visible + ' pig' + (visible !== 1 ? 's' : '') + ' found';
}

function clearFilters() {
    document.getElementById('sf_weight').value    = '';
    document.getElementById('sf_max_price').value = '';
    applyFilters();
}

// Init count on load
window.addEventListener('DOMContentLoaded', applyFilters);
</script>
</body>
</html>

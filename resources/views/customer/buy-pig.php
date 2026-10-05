<?php
$currentPage = 'buy-pig';
$user = $_SESSION['user'] ?? null;
if (!$user) { header('Location: /login'); exit; }

$conn = $GLOBALS['conn'];

// ── TAB: default to 'pigs' ────────────────────────────────────────────────
$activeTab = isset($_GET['tab']) && in_array($_GET['tab'], ['pigs', 'lechon']) ? $_GET['tab'] : 'pigs';

// ── PIGS: fetch active livestock listings (UNCHANGED) ────────────────────
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
     WHERE hm.status IN ('active', 'reserved')
     ORDER BY hm.status ASC, hm.created_at DESC"
);
if ($stmt) {
    $stmt->execute();
    $listings = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
    $stmt->close();
}

// ── LECHON: fetch active lechon listings ─────────────────────────────────
$lechon_listings = [];

// Check which column exists (is_active or status)
$col_check = $conn->query("SHOW COLUMNS FROM lechon_listings LIKE 'status'");
$has_status_column = ($col_check && $col_check->num_rows > 0);

if ($has_status_column) {
    // Use 'status' column
    $lchStmt = $conn->prepare(
        "SELECT ll.id, ll.name, ll.category, ll.listing_type, ll.weight_kg,
                ll.serving_capacity, ll.price, ll.available_date, ll.available_quantity,
                ll.description, ll.photo_url, ll.status,
                u.name as owner_name
         FROM lechon_listings ll
         LEFT JOIN livestock_owners lo ON lo.id = ll.livestock_owner_id
         LEFT JOIN users u ON u.id = lo.user_id
         WHERE ll.status = 'active'
         ORDER BY ll.created_at DESC"
    );
} else {
    // Use 'is_active' column (fallback for older schema)
    $lchStmt = $conn->prepare(
        "SELECT ll.id, ll.name, ll.category, ll.listing_type, ll.weight_kg,
                ll.serving_capacity, ll.price, ll.available_date, ll.available_quantity,
                ll.description, ll.photo_url, ll.is_active as status,
                u.name as owner_name
         FROM lechon_listings ll
         LEFT JOIN livestock_owners lo ON lo.id = ll.livestock_owner_id
         LEFT JOIN users u ON u.id = lo.user_id
         WHERE ll.is_active = 1
         ORDER BY ll.created_at DESC"
    );
}

if ($lchStmt) {
    $lchStmt->execute();
    $lechon_listings = $lchStmt->get_result()->fetch_all(MYSQLI_ASSOC);
    $lchStmt->close();
}

// ── Which lechon listings has THIS customer already ordered (pending/confirmed)? ──
$my_ordered_lechon_ids = [];
$tbl_chk = $conn->query("SHOW TABLES LIKE 'lechon_orders'");
if ($tbl_chk && $tbl_chk->num_rows > 0) {
    $myOrdStmt = $conn->prepare(
        "SELECT lechon_listing_id FROM lechon_orders
         WHERE customer_id = ? AND order_status NOT IN ('cancelled','completed')");
    if ($myOrdStmt) {
        $myOrdStmt->bind_param('i', $user['id']);
        $myOrdStmt->execute();
        $myOrdResult = $myOrdStmt->get_result();
        while ($r = $myOrdResult->fetch_assoc()) {
            $my_ordered_lechon_ids[] = (int)$r['lechon_listing_id'];
        }
        $myOrdStmt->close();
    }
}

// Active-only counts for tab badges
$pig_count    = count($listings);
$lechon_count = count($lechon_listings);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Market Place - LechGO</title>
    <link rel="stylesheet" href="/styles.css">
    <style>
        /* ── shared wrap ── */
        .bp-wrap { padding: .75rem 1rem; }

        /* ── page header ── */
        .bp-page-header { margin-bottom: .8rem; }
        .bp-page-header h1 { font-size: 1.2rem; margin: 0; color: #333; }

        /* ══════════════════════════════════════════
           MARKETPLACE TABS  (Pigs / Lechon)
        ══════════════════════════════════════════ */
        .mkt-tabs {
            display: flex;
            gap: 6px;
            margin-bottom: 1.1rem;
            border-bottom: 2px solid #e8e8e8;
            padding-bottom: 0;
        }
        .mkt-tab {
            padding: 9px 22px;
            background: transparent;
            border: none;
            border-bottom: 3px solid transparent;
            cursor: pointer;
            font-size: .88rem;
            font-weight: 700;
            color: #888;
            margin-bottom: -2px;
            transition: color .15s, border-color .15s;
            border-radius: 6px 6px 0 0;
        }
        .mkt-tab:hover { color: #c0392b; }
        .mkt-tab.active {
            color: #c0392b;
            border-bottom-color: #c0392b;
            background: rgba(192,57,43,.04);
        }
        .mkt-tab-badge {
            display: inline-block;
            background: #e0e0e0;
            color: #555;
            font-size: .62rem;
            font-weight: 800;
            padding: 1px 7px;
            border-radius: 20px;
            margin-left: 5px;
            vertical-align: middle;
        }
        .mkt-tab.active .mkt-tab-badge {
            background: #c0392b;
            color: #fff;
        }

        /* tab panels */
        .mkt-panel { display: none; }
        .mkt-panel.active { display: block; }

        /* ══════════════════════════════════════════
           PIGS — EXISTING STYLES (UNCHANGED)
        ══════════════════════════════════════════ */
        .bp-header { margin-bottom: 1rem; }
        .bp-header h2 { font-size: 1.05rem; margin: 0; color: #333; }
        .bp-header p  { margin: 2px 0 0; color: #888; font-size: .78rem; }

        .bp-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(220px, 1fr));
            gap: 16px;
        }

        .bp-card {
            position: relative;
            background: #fff;
            border-radius: 12px;
            box-shadow: 0 2px 10px rgba(0,0,0,.09);
            overflow: hidden;
            display: flex;
            flex-direction: column;
            transition: transform .15s, box-shadow .15s;
        }
        .bp-card:hover {
            transform: translateY(-2px);
            box-shadow: 0 6px 18px rgba(0,0,0,.13);
        }

        .bp-reserved-badge {
            position: absolute;
            top: 10px; left: 10px;
            background: #e67e22;
            color: #fff;
            font-size: .7rem; font-weight: 800;
            padding: 3px 10px;
            border-radius: 20px; letter-spacing: .3px; z-index: 10;
        }

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

        /* Search / Filter Bar — pigs */
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
        .bp-search-clear {
            background: #f0f0f0; color: #666; border: none; border-radius: 7px;
            padding: 8px 14px; font-size: .82rem; font-weight: 700; cursor: pointer;
            align-self: flex-end;
        }
        .bp-results-count { font-size: .78rem; color: #888; margin-bottom: 10px; }

        /* Inquiry Modal — pigs */
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

        /* ══════════════════════════════════════════
           LECHON — STYLES  (new, mirrors pig style)
        ══════════════════════════════════════════ */

        /* Lechon section header */
        .lch-header { margin-bottom: 1rem; }
        .lch-header h2 { font-size: 1.05rem; margin: 0; color: #333; }
        .lch-header p  { margin: 2px 0 0; color: #888; font-size: .78rem; }

        /* Lechon category filter pills */
        .lch-filter-bar {
            display: flex; flex-wrap: wrap; gap: 8px;
            margin-bottom: 16px;
        }
        .lch-filter-btn {
            background: #f5f5f5; color: #666; border: 1.5px solid #e0e0e0;
            border-radius: 20px; padding: 5px 14px;
            font-size: .76rem; font-weight: 700; cursor: pointer;
            transition: all .15s;
        }
        .lch-filter-btn:hover { border-color: #c0392b; color: #c0392b; }
        .lch-filter-btn.active {
            background: #c0392b; color: #fff; border-color: #c0392b;
        }

        /* Lechon grid — same column sizing as pig grid */
        .lch-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(220px, 1fr));
            gap: 16px;
        }

        /* Lechon card — same base as bp-card */
        .lch-card {
            position: relative;
            background: #fff;
            border-radius: 12px;
            box-shadow: 0 2px 10px rgba(0,0,0,.09);
            overflow: hidden;
            display: flex;
            flex-direction: column;
            transition: transform .15s, box-shadow .15s;
        }
        .lch-card:hover {
            transform: translateY(-2px);
            box-shadow: 0 6px 18px rgba(0,0,0,.13);
        }

        /* Category badge — top-left overlay */
        .lch-category-badge {
            position: absolute;
            top: 10px; left: 10px;
            background: rgba(192,57,43,.88);
            color: #fff;
            font-size: .62rem; font-weight: 800;
            padding: 3px 10px;
            border-radius: 20px; letter-spacing: .3px; z-index: 10;
            text-transform: uppercase;
        }
        .lch-category-badge.WHOLE_LECHON   { background: rgba(192,57,43,.88); }
        .lch-category-badge.WHOLE_PACKAGES { background: rgba(142,68,173,.88); }
        .lch-category-badge.BELLY_BUNDLES  { background: rgba(230,126,34,.88); }
        .lch-category-badge.WEEKDAY_COMBOS { background: rgba(39,174,96,.88); }
        .lch-category-badge.EVENT_CATERING { background: rgba(41,128,185,.88); }
        .lch-category-badge.OTHER          { background: rgba(127,140,141,.88); }

        .lch-card-photo { width: 100%; height: 160px; object-fit: cover; }
        .lch-card-placeholder {
            width: 100%; height: 160px;
            background: linear-gradient(135deg, #fde8e8, #fff8f0);
            display: flex; align-items: center; justify-content: center; font-size: 3.5rem;
        }
        .lch-card-body { padding: 12px 14px; flex: 1; }
        .lch-card-name { font-size: 1rem; font-weight: 800; color: #222; margin-bottom: 5px; }
        .lch-card-row  { font-size: .76rem; color: #666; margin-bottom: 3px; }
        .lch-card-row span { font-weight: 600; color: #444; }
        .lch-card-seller { font-size: .68rem; color: #aaa; margin-top: 5px; }

        /* status badge */
        .lch-status {
            display: inline-block; font-size: .62rem; font-weight: 700;
            padding: 2px 8px; border-radius: 20px; text-transform: uppercase; margin: 4px 0;
        }
        .lch-status.active   { background: #e6f9ee; color: #2d7a2d; }
        .lch-status.inactive { background: #f0f0f0; color: #999; }

        .lch-price-row {
            display: flex; align-items: baseline; gap: 6px;
            margin-top: 8px; padding-top: 8px; border-top: 1px solid #f0f0f0;
        }
        .lch-price-label { font-size: .8rem; color: #888; }
        .lch-price-val   { font-size: 1.15rem; font-weight: 800; color: #c0392b; }

        .lch-card-footer { padding: 0 14px 14px; }
        .lch-btn-reserve {
            display: block; width: 100%; background: #c0392b; color: #fff;
            border: none; border-radius: 8px; padding: 9px 0;
            font-size: .85rem; font-weight: 700; cursor: pointer;
            transition: background .15s; text-align: center;
        }
        .lch-btn-reserve:hover { background: #a93226; }

        .lch-results-count { font-size: .78rem; color: #888; margin-bottom: 10px; }

        .lch-empty {
            text-align: center; padding: 4rem; color: #bbb;
            background: #fff; border-radius: 12px;
            box-shadow: 0 2px 8px rgba(0,0,0,.07);
        }

        /* Lechon inquiry modal — reuses bpm-* classes + a lechon-tinted header */
        .lch-modal-header { background: #a93226; }
    </style>
</head>
<body>
<div class="dashboard-layout">
    <?php include __DIR__ . '/../layouts/sidebar.php'; ?>
    <main class="dashboard-main">

    <!-- Top Bar -->
    <div class="dashboard-topbar">
        <button class="dashboard-mobile-toggle" id="sidebarToggle">☰</button>
        <h1 class="dashboard-topbar-title">Market Place</h1>
        <div class="dashboard-topbar-actions">
            <span class="dashboard-topbar-date"><?php echo date('l, F j, Y'); ?></span>
        </div>
    </div>

    <div class="dashboard-content">
    <div class="bp-wrap">

        <?php if (isset($_SESSION['success'])): ?>
            <div class="alert alert-success show"><?php echo htmlspecialchars($_SESSION['success']); unset($_SESSION['success']); ?></div>
        <?php endif; ?>
        <?php if (isset($_SESSION['error'])): ?>
            <div class="alert alert-danger show"><?php echo htmlspecialchars($_SESSION['error']); unset($_SESSION['error']); ?></div>
        <?php endif; ?>

        <!-- Page heading -->
        <div class="bp-page-header">
            <h1>Market Place</h1>
        </div>

        <!-- ══ MARKETPLACE TABS ═══════════════════════════════════════════ -->
        <div class="mkt-tabs" role="tablist">
            <button class="mkt-tab <?php echo $activeTab === 'pigs' ? 'active' : ''; ?>"
                    role="tab" aria-selected="<?php echo $activeTab === 'pigs' ? 'true' : 'false'; ?>"
                    onclick="switchTab('pigs')">
                Pigs
                <span class="mkt-tab-badge"><?php echo $pig_count; ?></span>
            </button>
            <button class="mkt-tab <?php echo $activeTab === 'lechon' ? 'active' : ''; ?>"
                    role="tab" aria-selected="<?php echo $activeTab === 'lechon' ? 'true' : 'false'; ?>"
                    onclick="switchTab('lechon')">
                Lechon
                <span class="mkt-tab-badge"><?php echo $lechon_count; ?></span>
            </button>
        </div>

        <!-- ══════════════════════════════════════════════════════════════
             TAB PANEL — PIGS  (ORIGINAL FUNCTIONALITY — UNTOUCHED)
        ══════════════════════════════════════════════════════════════ -->
        <div class="mkt-panel <?php echo $activeTab === 'pigs' ? 'active' : ''; ?>" id="panel-pigs">

            <div class="bp-header">
                <h2>Pig Listings</h2>
                <p><?php echo $pig_count; ?> pig<?php echo $pig_count !== 1 ? 's' : ''; ?> available for sale</p>
            </div>

            <!-- Search & Filter Bar — UNCHANGED -->
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
                    <div style="font-size:2rem;margin-bottom:.5rem;color:#ccc;">No Pigs</div>
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
                        <div class="bp-card-placeholder" style="font-size:1rem;color:#ccc;">No Image</div>
                        <?php endif; ?>
                        <?php if ($l['status'] === 'reserved'): ?>
                            <div class="bp-reserved-badge">Reserved</div>
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
                            <div class="bp-card-seller"><?php echo htmlspecialchars($l['owner_name']); ?></div>
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
                            )">RESERVE</button>
                            <?php endif; ?>
                        </div>
                    </div>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>

        </div><!-- /panel-pigs -->

        <!-- ══════════════════════════════════════════════════════════════
             TAB PANEL — LECHON  (NEW)
        ══════════════════════════════════════════════════════════════ -->
        <div class="mkt-panel <?php echo $activeTab === 'lechon' ? 'active' : ''; ?>" id="panel-lechon">

            <div class="lch-header">
                <h2>Lechon Listings</h2>
                <p>Cooked lechon and lechon packages available for order</p>
            </div>

            <!-- Lechon category filter pills -->
            <div class="lch-filter-bar" id="lchFilters">
                <button class="lch-filter-btn active" data-cat="all"           onclick="filterLechon('all',this)">All</button>
                <button class="lch-filter-btn"        data-cat="WHOLE_LECHON"  onclick="filterLechon('WHOLE_LECHON',this)">Whole Lechon</button>
                <button class="lch-filter-btn"        data-cat="WHOLE_PACKAGES" onclick="filterLechon('WHOLE_PACKAGES',this)">w/ Packages</button>
                <button class="lch-filter-btn"        data-cat="BELLY_BUNDLES" onclick="filterLechon('BELLY_BUNDLES',this)">Belly Bundles</button>
                <button class="lch-filter-btn"        data-cat="WEEKDAY_COMBOS" onclick="filterLechon('WEEKDAY_COMBOS',this)">Weekday Combos</button>
                <button class="lch-filter-btn"        data-cat="EVENT_CATERING" onclick="filterLechon('EVENT_CATERING',this)">Event / Catering</button>
                <button class="lch-filter-btn"        data-cat="OTHER"          onclick="filterLechon('OTHER',this)">Other</button>
            </div>
            <div class="lch-results-count" id="lch_count"></div>

            <?php if (empty($lechon_listings)): ?>
                <div class="lch-empty">
                    <div style="font-size:2rem;margin-bottom:.5rem;color:#ccc;">No Listings</div>
                    <div>No lechon listings available right now. Check back later!</div>
                </div>
            <?php else: ?>
                <?php
                // Build human-readable category labels
                $catLabels = [
                    'WHOLE_LECHON'   => 'Whole Lechon',
                    'WHOLE_PACKAGES' => 'w/ Packages',
                    'BELLY_BUNDLES'  => 'Belly Bundles',
                    'WEEKDAY_COMBOS' => 'Weekday Combos',
                    'EVENT_CATERING' => 'Event / Catering',
                    'OTHER'          => 'Other',
                ];
                ?>
                <div class="lch-grid" id="lch_grid">
                    <?php foreach ($lechon_listings as $lc): ?>
                    <div class="lch-card"
                         data-cat="<?php echo htmlspecialchars($lc['category']); ?>">
                        <?php if (!empty($lc['photo_url'])): ?>
                            <img src="<?php echo htmlspecialchars($lc['photo_url']); ?>" class="lch-card-photo" alt="Lechon">
                        <?php else: ?>
                        <div class="lch-card-placeholder" style="font-size:1rem;color:#ccc;">No Image</div>
                        <?php endif; ?>

                        <!-- Category badge -->
                        <div class="lch-category-badge <?php echo htmlspecialchars($lc['category']); ?>">
                            <?php echo htmlspecialchars($catLabels[$lc['category']] ?? $lc['category']); ?>
                        </div>

                        <div class="lch-card-body">
                            <div class="lch-card-name"><?php echo htmlspecialchars($lc['name']); ?></div>

                            <?php if (!empty($lc['weight_kg'])): ?>
                            <div class="lch-card-row">Weight: <span><?php echo number_format($lc['weight_kg'], 1); ?> kg</span></div>
                            <?php endif; ?>

                            <?php if (!empty($lc['serving_capacity'])): ?>
                            <div class="lch-card-row">Good for: <span><?php echo htmlspecialchars($lc['serving_capacity']); ?></span></div>
                            <?php endif; ?>

                            <?php if (!empty($lc['available_date'])): ?>
                            <div class="lch-card-row">Available Until: <span><?php echo date('M d, Y', strtotime($lc['available_date'])); ?></span></div>
                            <?php endif; ?>

                            <?php if (!empty($lc['description'])): ?>
                            <div class="lch-card-row" style="margin-top:4px;font-style:italic;color:#999;">
                                "<?php echo htmlspecialchars($lc['description']); ?>"
                            </div>
                            <?php endif; ?>

                            <div class="lch-price-row">
                                <div>
                                    <div class="lch-price-label">Price</div>
                                    <div class="lch-price-val">₱<?php echo number_format($lc['price'], 2); ?></div>
                                </div>
                            </div>

                            <span class="lch-status active">Active</span>

                            <?php if (!empty($lc['owner_name'])): ?>
                            <div class="lch-card-seller"><?php echo htmlspecialchars($lc['owner_name']); ?></div>
                            <?php endif; ?>
                        </div>

                        <div class="lch-card-footer">
                            <?php $already_ordered = in_array((int)$lc['id'], $my_ordered_lechon_ids); ?>
                            <?php if ($already_ordered): ?>
                                <button class="lch-btn-reserve" disabled
                                        style="background:#e67e22;cursor:not-allowed;opacity:.95;">
                                    Ordered
                                </button>
                            <?php else: ?>
                            <button class="lch-btn-reserve" onclick="openLechonInquiry(
                                <?php echo (int)$lc['id']; ?>,
                                '<?php echo htmlspecialchars(addslashes($lc['name'])); ?>',
                                '<?php echo htmlspecialchars(addslashes($catLabels[$lc['category']] ?? $lc['category'])); ?>',
                                <?php echo !empty($lc['weight_kg']) ? (float)$lc['weight_kg'] : 'null'; ?>,
                                '<?php echo htmlspecialchars(addslashes($lc['serving_capacity'] ?? '')); ?>',
                                <?php echo (float)$lc['price']; ?>,
                                '<?php echo htmlspecialchars(addslashes($lc['owner_name'] ?? '')); ?>'
                            )">ORDER</button>
                            <?php endif; ?>
                        </div>
                    </div>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>

        </div><!-- /panel-lechon -->

    </div>
    </div><!-- /dashboard-content -->
    </main>
</div>

<!-- ════════════════════════════════════════════════════════════════════
     MODAL — PIG INQUIRY  (ORIGINAL — UNTOUCHED)
════════════════════════════════════════════════════════════════════ -->
<div class="bpm-overlay" id="bpmOverlay" onclick="if(event.target===this)closeInquiry()">
    <div class="bpm-modal">
        <div class="bpm-header">
            <h3>Buy This Pig</h3>
            <button class="bpm-close" onclick="closeInquiry()">✕</button>
        </div>
        <div class="bpm-body">
            <div class="bpm-pig-summary">
                <strong id="bpm_tag">—</strong>
                <span id="bpm_details">—</span>
                <span class="bpm-price-big" id="bpm_total">—</span>
            </div>
            <form method="POST" action="/customer/pig-inquiry">
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

<!-- ════════════════════════════════════════════════════════════════════
     MODAL — LECHON INQUIRY  (NEW — same flow, different endpoint)
════════════════════════════════════════════════════════════════════ -->
<div class="bpm-overlay" id="lchOverlay" onclick="if(event.target===this)closeLechonInquiry()">
    <div class="bpm-modal">
        <div class="bpm-header lch-modal-header">
            <h3>Order This Lechon</h3>
            <button class="bpm-close" onclick="closeLechonInquiry()">✕</button>
        </div>
        <div class="bpm-body">
            <div class="bpm-pig-summary">
                <strong id="lch_name">—</strong>
                <span id="lch_details">—</span>
                <span class="bpm-price-big" id="lch_price_display">—</span>
            </div>
            <form method="POST" action="/customer/lechon-inquiry">
                <input type="hidden" name="listing_id" id="lch_listing_id">
                <div class="bpm-field">
                    <label>Your Message to the Seller</label>
                    <textarea name="message"
                        placeholder="e.g. I'd like to reserve this lechon for my event. What's the earliest available date?"
                        required></textarea>
                </div>
                <div class="bpm-actions">
                    <button type="button" class="bpm-btn-cancel" onclick="closeLechonInquiry()">Cancel</button>
                    <button type="submit" class="bpm-btn-submit">Place Order</button>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
/* ─────────────────────────────────────────────
   TAB SWITCHING
───────────────────────────────────────────── */
function switchTab(tab) {
    // Update panels
    document.querySelectorAll('.mkt-panel').forEach(p => p.classList.remove('active'));
    document.querySelectorAll('.mkt-tab').forEach(t => {
        t.classList.toggle('active', t.textContent.toLowerCase().includes(tab === 'pigs' ? 'pig' : 'lechon'));
        t.setAttribute('aria-selected', t.textContent.toLowerCase().includes(tab === 'pigs' ? 'pig' : 'lechon') ? 'true' : 'false');
    });
    document.getElementById('panel-' + tab).classList.add('active');

    // Persist in URL without page reload
    const url = new URL(window.location);
    url.searchParams.set('tab', tab);
    history.replaceState(null, '', url);

    // Refresh counts for whichever panel just became active
    if (tab === 'pigs') applyFilters();
    if (tab === 'lechon') applyLechonFilter();
}

/* ─────────────────────────────────────────────
   PIG MODAL — ORIGINAL FUNCTIONS (UNTOUCHED)
───────────────────────────────────────────── */
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

/* ─────────────────────────────────────────────
   PIG FILTERS — ORIGINAL FUNCTIONS (UNTOUCHED)
───────────────────────────────────────────── */
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
    if (countEl) countEl.textContent = visible + ' pig' + (visible !== 1 ? 's' : '') + ' found';
}

function clearFilters() {
    document.getElementById('sf_weight').value    = '';
    document.getElementById('sf_max_price').value = '';
    applyFilters();
}

/* ─────────────────────────────────────────────
   LECHON MODAL
───────────────────────────────────────────── */
function openLechonInquiry(id, name, category, weight, serving, price, seller) {
    document.getElementById('lch_listing_id').value = id;
    document.getElementById('lch_name').textContent = name;

    let details = category;
    if (weight) details += ' · ' + weight + ' kg';
    if (serving) details += ' · Good for ' + serving;
    if (seller) details += ' · Seller: ' + seller;
    document.getElementById('lch_details').textContent = details;
    document.getElementById('lch_price_display').textContent =
        '₱' + price.toLocaleString('en-PH', {minimumFractionDigits:2});

    document.getElementById('lchOverlay').classList.add('open');
}
function closeLechonInquiry() {
    document.getElementById('lchOverlay').classList.remove('open');
}

/* ─────────────────────────────────────────────
   LECHON CATEGORY FILTER
───────────────────────────────────────────── */
function filterLechon(cat, btn) {
    // Update active pill
    document.querySelectorAll('.lch-filter-btn').forEach(b => b.classList.remove('active'));
    btn.classList.add('active');

    const cards = document.querySelectorAll('#lch_grid .lch-card');
    let visible = 0;
    cards.forEach(card => {
        const show = cat === 'all' || card.dataset.cat === cat;
        card.style.display = show ? '' : 'none';
        if (show) visible++;
    });

    const countEl = document.getElementById('lch_count');
    if (countEl) countEl.textContent = visible + ' listing' + (visible !== 1 ? 's' : '') + ' found';
}

function applyLechonFilter() {
    // Find the currently active filter pill and re-apply it
    const activeBtn = document.querySelector('.lch-filter-btn.active');
    if (activeBtn) filterLechon(activeBtn.dataset.cat, activeBtn);
}

/* ─────────────────────────────────────────────
   INIT
───────────────────────────────────────────── */
window.addEventListener('DOMContentLoaded', function () {
    applyFilters();
    applyLechonFilter();
});
</script>

<script>
// Sidebar toggle for mobile
document.getElementById('sidebarToggle').addEventListener('click', function () {
    document.getElementById('dashboardSidebar').classList.toggle('active');
});
</script>
</body>
</html>

<?php
$currentPage = 'my-pig-market';
$user = $_SESSION['user'] ?? null;
if (!$user) { header('Location: /login'); exit; }

$conn = $GLOBALS['conn'];

// Check if user is a livestock owner
$stmt = $conn->prepare("SELECT id FROM livestock_owners WHERE user_id = ?");
$stmt->bind_param('i', $user['id']);
$stmt->execute();
$owner = $stmt->get_result()->fetch_assoc();
$stmt->close();

// If not a livestock owner, check if user is a caretaker
if (!$owner) {
    $stmt = $conn->prepare("SELECT livestock_owner_id as id FROM pig_caretakers WHERE user_id = ?");
    $stmt->bind_param('i', $user['id']);
    $stmt->execute();
    $owner = $stmt->get_result()->fetch_assoc();
    $stmt->close();
}

if (!$owner) { header('Location: /home'); exit; }

// Determine active tab
$activeTab = isset($_GET['tab']) && in_array($_GET['tab'], ['pig', 'lechon']) ? $_GET['tab'] : 'pig';

$listings = [];
$lechon_listings = [];

// Updated query to properly join with order tables for cost computation
$stmt = $conn->prepare(
    "SELECT pml.*, pd.photo_url, pd.health_status, pd.age_months,
            po.delivery_address, po.delivery_notes, po.delivery_method, 
            po.include_laman_loob, po.include_boopes, po.include_dinuguan,
            po.created_at as placed_at,
            sos.order_status, sos.payment_status, sos.customer_id, sos.id as swine_order_id,
            u.name as customer_name,
            otc.id as has_cost_computation
     FROM hogs_market pml
     LEFT JOIN pig_details pd ON pd.id = pml.pig_detail_id
     LEFT JOIN swine_order_status sos ON sos.hogs_market_id = pml.id
     LEFT JOIN placed_orders po ON po.swine_order_id = sos.id
     LEFT JOIN users u ON u.id = sos.customer_id
     LEFT JOIN order_total_cost otc ON otc.swine_order_id = sos.id
     WHERE pml.livestock_owner_id = ?
     ORDER BY FIELD(pml.status,'reserved','sold','active','removed'), pml.created_at DESC"
);
if ($stmt) {
    $stmt->bind_param('i', $owner['id']);
    if (!$stmt->execute()) {
        error_log("Query error: " . $stmt->error);
        $_SESSION['error'] = "Error loading listings: " . $stmt->error;
    } else {
        $listings = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
    }
    $stmt->close();
} else {
    error_log("Prepare error: " . $conn->error);
    $_SESSION['error'] = "Error preparing query: " . $conn->error;
}

// Load lechon listings
$lechon_stmt = $conn->prepare(
    "SELECT * FROM lechon_listings 
     WHERE livestock_owner_id = ?
     ORDER BY FIELD(status, 'active', 'inactive', 'removed'), created_at DESC"
);
if ($lechon_stmt) {
    $lechon_stmt->bind_param('i', $owner['id']);
    if (!$lechon_stmt->execute()) {
        error_log("Lechon query error: " . $lechon_stmt->error);
        $_SESSION['error'] = "Error loading lechon listings: " . $lechon_stmt->error;
    } else {
        $lechon_listings = $lechon_stmt->get_result()->fetch_all(MYSQLI_ASSOC);
    }
    $lechon_stmt->close();
} else {
    error_log("Lechon prepare error: " . $conn->error);
}

// Load incoming lechon orders (customers who ordered lechon from this owner)
$lechon_orders_incoming = [];
$lo_tbl = $conn->query("SHOW TABLES LIKE 'lechon_orders'");
if ($lo_tbl && $lo_tbl->num_rows > 0) {
    $lo_stmt = $conn->prepare(
        "SELECT lo_ord.*, u.name AS customer_name, ll.name AS listing_name, ll.category
         FROM lechon_orders lo_ord
         LEFT JOIN users u ON u.id = lo_ord.customer_id
         LEFT JOIN lechon_listings ll ON ll.id = lo_ord.lechon_listing_id
         WHERE lo_ord.livestock_owner_id = ?
           AND lo_ord.order_status NOT IN ('cancelled','completed')
         ORDER BY FIELD(lo_ord.order_status,'pending','confirmed','preparing','cost_computed','ready_for_pickup'), lo_ord.created_at DESC"
    );
    if ($lo_stmt) {
        $lo_stmt->bind_param('i', $owner['id']);
        if ($lo_stmt->execute()) {
            $lechon_orders_incoming = $lo_stmt->get_result()->fetch_all(MYSQLI_ASSOC);
        }
        $lo_stmt->close();
    }
}
$lechon_pending_count = count(array_filter($lechon_orders_incoming, fn($o) => $o['order_status'] === 'pending'));
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>My Pig Market - LechGO</title>
    <link rel="stylesheet" href="/styles.css">
    <style>
        .mpm-wrap { padding: .75rem 1rem; }
        .mpm-header { display:flex; align-items:center; justify-content:space-between; margin-bottom:.9rem; }
        .mpm-header h1 { font-size:1.2rem; margin:0; color:#333; }
        .mpm-header p  { margin:2px 0 0; color:#888; font-size:.78rem; }

        /* Reduce alert spacing */
        .alert { margin-bottom: 12px !important; padding: 10px 14px !important; }
        .alert-success { background: #d4edda; border: 1px solid #c3e6cb; color: #155724; border-radius: 8px; }
        .alert-danger { background: #f8d7da; border: 1px solid #f5c6cb; color: #721c24; border-radius: 8px; }

        .mpm-grid { display:grid; grid-template-columns:repeat(auto-fill,minmax(230px,1fr)); gap:14px; }

        .mpm-card { background:#fff; border-radius:10px; box-shadow:0 2px 8px rgba(0,0,0,.08); overflow:hidden; display:flex; flex-direction:column; }
        .mpm-card.reserved { box-shadow:0 0 0 2.5px #f39c12, 0 2px 12px rgba(243,156,18,.15); }
        .mpm-card-photo { width:100%; height:140px; object-fit:cover; }
        .mpm-card-placeholder { width:100%; height:140px; background:linear-gradient(135deg,#fde8e8,#fff0f0); display:flex; align-items:center; justify-content:center; font-size:3rem; }
        .mpm-card-body { padding:10px 12px; flex:1; }
        .mpm-card-tag { font-size:.95rem; font-weight:700; color:#333; margin-bottom:4px; }
        .mpm-card-row { font-size:.75rem; color:#666; margin-bottom:3px; }
        .mpm-card-row span { font-weight:600; color:#444; }
        .mpm-card-price { font-size:1rem; font-weight:800; color:#c0392b; margin:6px 0 4px; }
        .mpm-card-total { font-size:.72rem; color:#888; }

        .mpm-status { display:inline-block; font-size:.62rem; font-weight:700; padding:2px 8px; border-radius:20px; margin-top:5px; }
        .mpm-status.active   { background:#e6f9ee; color:#2d7a2d; }
        .mpm-status.reserved { background:#fff3cd; color:#856404; }
        .mpm-status.sold     { background:#d1ecf1; color:#0c5460; }
        .mpm-status.waiting_payment { background:#fdeaa7; color:#b7950b; }
        .mpm-status.placed   { background:#FADBD8; color:#2d7a2d; }
        .mpm-status.removed  { background:#f0f0f0; color:#999; }

        /* Reserved customer info box */
        .mpm-reserved-box {
            margin:8px 12px 0; padding:8px 10px;
            background:#fff8e1; border-left:3px solid #f39c12;
            border-radius:0 6px 6px 0;
        }
        .mpm-reserved-box .rb-label { font-size:.65rem; font-weight:700; color:#f39c12; text-transform:uppercase; margin-bottom:3px; }

        /* Placed order info box */
        .mmp-placed-box {
            margin:8px 12px 0; padding:8px 10px;
            background:#FADBD8; border-left:3px solid #2d7a2d;
            border-radius:0 6px 6px 0;
        }
        .mmp-placed-box .pb-label { font-size:.65rem; font-weight:700; color:#2d7a2d; text-transform:uppercase; margin-bottom:3px; }
        .mmp-placed-box .pb-name { font-size:.82rem; font-weight:700; color:#333; }
        .mmp-placed-box .pb-address { font-size:.72rem; color:#666; margin-top:3px; line-height:1.4; }
        .mmp-placed-box .pb-notes { font-size:.72rem; color:#666; margin-top:3px; font-style:italic; line-height:1.4; }
        .mmp-placed-box .pb-time { font-size:.65rem; color:#aaa; margin-top:3px; }

        /* Waiting for payment info box */
        .mmp-waiting-box {
            margin:8px 12px 0; padding:8px 10px;
            background:#fef9e7; border-left:3px solid #f39c12;
            border-radius:0 6px 6px 0;
        }
        .mmp-waiting-box .wb-label { font-size:.65rem; font-weight:700; color:#f39c12; text-transform:uppercase; margin-bottom:3px; }
        .mmp-waiting-box .wb-name { font-size:.82rem; font-weight:700; color:#333; }
        .mmp-waiting-box .wb-msg { font-size:.72rem; color:#666; margin-top:3px; line-height:1.4; }
        .mmp-waiting-box .wb-time { font-size:.65rem; color:#aaa; margin-top:3px; }
        .mpm-reserved-box .rb-name  { font-size:.82rem; font-weight:700; color:#333; }
        .mpm-reserved-box .rb-msg   { font-size:.72rem; color:#666; margin-top:3px; font-style:italic; line-height:1.4; }
        .mpm-reserved-box .rb-time  { font-size:.65rem; color:#aaa; margin-top:3px; }

        .mpm-card-footer { padding:8px 12px; border-top:1px solid #f5f5f5; display:flex; gap:6px; }
        .mpm-btn-remove { flex:1; background:#fde8e8; color:#c0392b; border:none; border-radius:6px; padding:6px; font-size:.72rem; font-weight:700; cursor:pointer; }
        .mpm-btn-remove:hover { background:#f5c6c6; }
        .mpm-btn-sold { flex:1; background:#2d7a2d; color:#fff; border:none; border-radius:6px; padding:6px; font-size:.72rem; font-weight:700; cursor:pointer; }
        .mpm-btn-sold:hover { background:#236023; }
        .mpm-btn-compute { flex:1; background:#2d7a2d; color:#fff; border:none; border-radius:6px; padding:6px; font-size:.72rem; font-weight:700; cursor:pointer; }
        .mpm-btn-compute:hover { background:#1a4a1a; }
        .mpm-btn-edit { flex:1; background:#e8f4fd; color:#2980b9; border:none; border-radius:6px; padding:6px; font-size:.72rem; font-weight:700; cursor:pointer; }
        .mpm-btn-edit:hover { background:#d0e8f5; }

        .mpm-empty { text-align:center; padding:3rem; color:#bbb; font-size:.9rem; background:#fff; border-radius:10px; box-shadow:0 2px 6px rgba(0,0,0,.07); }
        .mpm-tabs { display:flex; gap:6px; margin-bottom:14px; flex-wrap:wrap; }
        .mpm-tab { padding:5px 16px; border-radius:20px; font-size:.78rem; font-weight:700; cursor:pointer; border:1.5px solid #e0e0e0; background:#fff; color:#888; transition:all .15s; }
        .mpm-tab.active { background:#c0392b; color:#fff; border-color:#c0392b; }

        .mpm-reserved-count {
            background:#fde8e8; color:#c0392b; border:1.5px solid #f5c6c6;
            border-radius:8px; padding:8px 14px; margin-bottom:14px;
            font-size:.82rem; font-weight:700; display:flex; align-items:center; gap:8px;
        }
        .mpm-status.inactive { background:#f0f0f0; color:#999; }

        /* ── Top-level Pig / Lechon navigation ── */
        .mpm-market-tabs { display:flex; gap:10px; margin-bottom:1.1rem; }
        .mpm-market-tab {
            display:flex; align-items:center; gap:8px;
            padding:10px 18px; border-radius:10px;
            border:2px solid #e0e0e0; background:#fff;
            color:#888; font-size:.88rem; font-weight:700;
            cursor:pointer; transition:all .18s;
        }
        .mpm-market-tab:hover { border-color:#c0392b; color:#c0392b; }
        .mpm-market-tab.active { border-color:#c0392b; background:#c0392b; color:#fff; }
        .mpm-market-tab .tab-sub { font-size:.7rem; font-weight:400; opacity:.85; display:block; }

        .mpm-tab-panel { display:none; }
        .mpm-tab-panel.active { display:block; }

        /* ── Lechon section header ── */
        .lch-section-header {
            display:flex; align-items:center; justify-content:space-between;
            margin-bottom:.85rem;
        }
        .lch-section-title { font-size:1.05rem; font-weight:800; color:#333; margin:0; }
        .lch-section-sub { font-size:.78rem; color:#888; margin:2px 0 0; }
        .lch-btn-add {
            background:#c0392b; color:#fff; border:none;
            border-radius:7px; padding:8px 16px;
            font-size:.82rem; font-weight:700; cursor:pointer;
        }
        .lch-btn-add:hover { background:#a93226; }

        /* ── Lechon category badge ── */
        .lch-badge {
            display:inline-block; font-size:.6rem; font-weight:800;
            padding:2px 8px; border-radius:4px; text-transform:uppercase;
            letter-spacing:.04em; margin-bottom:5px;
        }
        .lch-badge.WHOLE_LECHON     { background:#c0392b; color:#fff; }
        .lch-badge.WHOLE_PACKAGES   { background:#8e44ad; color:#fff; }
        .lch-badge.BELLY_BUNDLES    { background:#16a085; color:#fff; }
        .lch-badge.WEEKDAY_COMBOS   { background:#e67e22; color:#fff; }
        .lch-badge.EVENT_CATERING   { background:#2471a3; color:#fff; }
        .lch-badge.OTHER            { background:#7f8c8d; color:#fff; }

        /* ── Lechon add/edit modal ── */
        .lch-overlay {
            display:none; position:fixed; inset:0;
            background:rgba(0,0,0,.5); z-index:1100;
            align-items:center; justify-content:center;
        }
        .lch-overlay.open { display:flex; }
        .lch-modal {
            background:#fff; border-radius:14px;
            width:95%; max-width:580px;
            box-shadow:0 8px 32px rgba(0,0,0,.22);
            overflow:hidden; max-height:93vh; display:flex; flex-direction:column;
        }
        .lch-modal-header {
            background:#c0392b; color:#fff;
            padding:14px 18px; display:flex;
            align-items:center; justify-content:space-between; flex-shrink:0;
        }
        .lch-modal-header h3 { margin:0; font-size:1rem; }
        .lch-modal-close {
            background:none; border:none; color:#fff;
            font-size:1.3rem; cursor:pointer; line-height:1;
        }
        .lch-modal-body { padding:20px; overflow-y:auto; }
        .lch-step { display:none; }
        .lch-step.active { display:block; }
        .lch-step-indicator {
            display:flex; gap:0; margin-bottom:18px;
        }
        .lch-step-dot {
            flex:1; text-align:center; font-size:.7rem; font-weight:700;
            padding:6px 4px; border-bottom:3px solid #e0e0e0; color:#aaa;
        }
        .lch-step-dot.active { border-color:#c0392b; color:#c0392b; }
        .lch-step-dot.done   { border-color:#2d7a2d; color:#2d7a2d; }

        .lch-type-cards { display:grid; grid-template-columns:1fr 1fr; gap:12px; margin-top:6px; }
        .lch-type-card {
            border:2px solid #e0e0e0; border-radius:10px;
            padding:14px 12px; cursor:pointer; transition:all .15s;
            display:flex; align-items:flex-start; gap:10px;
        }
        .lch-type-card:hover { border-color:#c0392b; }
        .lch-type-card.selected { border-color:#c0392b; background:#fff5f5; }
        .lch-type-card input[type=radio] { margin-top:2px; accent-color:#c0392b; }
        .lch-type-card-body .lch-type-title { font-size:.9rem; font-weight:700; color:#333; }
        .lch-type-card-body .lch-type-desc  { font-size:.75rem; color:#888; margin-top:3px; }
        .lch-type-icon { font-size:1.6rem; }

        .lch-field { margin-bottom:14px; }
        .lch-field label {
            display:block; font-size:.78rem; font-weight:700;
            color:#444; margin-bottom:5px;
        }
        .lch-field label .req { color:#c0392b; }
        .lch-field input, .lch-field select, .lch-field textarea {
            width:100%; padding:8px 10px;
            border:1.5px solid #e0e0e0; border-radius:7px;
            font-size:.85rem; box-sizing:border-box; outline:none;
        }
        .lch-field input:focus, .lch-field select:focus, .lch-field textarea:focus {
            border-color:#c0392b;
        }
        .lch-field textarea { resize:vertical; min-height:70px; }
        .lch-field-row { display:grid; grid-template-columns:1fr 1fr; gap:10px; }
        .lch-modal-footer {
            padding:14px 20px; border-top:1px solid #f0f0f0;
            display:flex; gap:8px; justify-content:flex-end; flex-shrink:0;
        }
        .lch-btn-cancel {
            background:#f0f0f0; color:#555; border:none;
            border-radius:7px; padding:9px 18px;
            font-size:.85rem; font-weight:700; cursor:pointer;
        }
        .lch-btn-next {
            background:#c0392b; color:#fff; border:none;
            border-radius:7px; padding:9px 18px;
            font-size:.85rem; font-weight:700; cursor:pointer;
        }
        .lch-btn-next:hover { background:#a93226; }
        .lch-btn-submit {
            background:#2d7a2d; color:#fff; border:none;
            border-radius:7px; padding:9px 18px;
            font-size:.85rem; font-weight:700; cursor:pointer;
        }
        .lch-btn-submit:hover { background:#1a4a1a; }

        /* ── Lechon card actions dropdown ── */
        .lch-card-menu-wrap { position:relative; }
        .lch-card-menu-btn {
            background:rgba(255,255,255,.85); border:none; border-radius:50%;
            width:28px; height:28px; font-size:.9rem; cursor:pointer;
            display:flex; align-items:center; justify-content:center;
        }
        .lch-card-dropdown {
            display:none; position:absolute; right:0; top:32px;
            background:#fff; border-radius:8px;
            box-shadow:0 4px 16px rgba(0,0,0,.14);
            min-width:150px; z-index:200; overflow:hidden;
        }
        .lch-card-dropdown.open { display:block; }
        .lch-card-dropdown a, .lch-card-dropdown button {
            display:block; width:100%; text-align:left;
            padding:9px 14px; border:none; background:none;
            font-size:.82rem; color:#333; cursor:pointer; text-decoration:none;
        }
        .lch-card-dropdown a:hover, .lch-card-dropdown button:hover { background:#fef2f2; color:#c0392b; }
        .lch-card-dropdown .sep { border-top:1px solid #f0f0f0; margin:2px 0; }

        /* photo upload preview */
        .lch-photo-preview {
            width:100%; height:100px; object-fit:cover;
            border-radius:7px; margin-top:6px; display:none;
        }
    </style>
</head>
<body>
<div class="dashboard-layout">
    <?php include __DIR__ . '/../layouts/sidebar.php'; ?>
    <main class="dashboard-main">
    <!-- Top Bar with Hamburger Menu -->
    <div class="dashboard-topbar">
        <button class="dashboard-mobile-toggle" id="sidebarToggle">☰</button>
        <h1 class="dashboard-topbar-title">My Pig Market</h1>
        <div class="dashboard-topbar-actions">
            <span class="dashboard-topbar-date"><?php echo date('l, F j, Y'); ?></span>
        </div>
    </div>
    
    <div class="dashboard-content">
    <div class="mpm-wrap">

        <?php if (isset($_SESSION['success'])): ?>
            <div class="alert alert-success show"><?php echo htmlspecialchars($_SESSION['success']); unset($_SESSION['success']); ?></div>
        <?php endif; ?>
        <?php if (isset($_SESSION['error'])): ?>
            <div class="alert alert-danger show"><?php echo htmlspecialchars($_SESSION['error']); unset($_SESSION['error']); ?></div>
        <?php endif; ?>

        <div class="mpm-header">
            <div>
                <h1>My Pig Market</h1>
                <p>Pigs you have listed for sale</p>
            </div>
        </div>

        <!-- ====== MARKET TYPE TABS ====== -->
        <div class="mpm-market-tabs">
            <button class="mpm-market-tab <?php echo $activeTab === 'pig' ? 'active' : ''; ?>"
                    onclick="switchMarketTab('pig')" id="tab-btn-pig">
                <span>
                    Pig Livestock
                    <span class="tab-sub">Live pigs for sale</span>
                </span>
            </button>
            <button class="mpm-market-tab <?php echo $activeTab === 'lechon' ? 'active' : ''; ?>"
                    onclick="switchMarketTab('lechon')" id="tab-btn-lechon">
                <span>
                    Lechon
                    <?php if ($lechon_pending_count > 0): ?>
                        <span style="background:#e67e22;color:#fff;border-radius:20px;padding:0 7px;font-size:.65rem;margin-left:4px;"><?php echo $lechon_pending_count; ?></span>
                    <?php endif; ?>
                    <span class="tab-sub">Cooked lechon &amp; products</span>
                </span>
            </button>
        </div>

        <!-- ====== PIG LIVESTOCK PANEL ====== -->
        <div class="mpm-tab-panel <?php echo $activeTab === 'pig' ? 'active' : ''; ?>" id="panel-pig">

        <?php
        $reservedCount = count(array_filter($listings, fn($l) => $l['status'] === 'reserved'));
        if ($reservedCount > 0):
        ?>
        <div class="mpm-reserved-count">
             <?php echo $reservedCount; ?> pending order<?php echo $reservedCount > 1 ? 's' : ''; ?> — review and click "Confirm Order" to reserve.
        </div>
        <?php endif; ?>

        <div class="mpm-tabs">
            <button class="mpm-tab active" onclick="filterListings('all', this)">All</button>
            <button class="mpm-tab" onclick="filterListings('active', this)">Active</button>
            <button class="mpm-tab" onclick="filterListings('reserved', this)">Pending Order <?php if($reservedCount): ?><span style="background:#e67e22;color:#fff;border-radius:20px;padding:0 6px;font-size:.65rem;margin-left:3px;"><?php echo $reservedCount; ?></span><?php endif; ?></button>
            <button class="mpm-tab" onclick="filterListings('waiting_payment', this)">Waiting for Payment</button>
            <button class="mpm-tab" onclick="filterListings('placed', this)">Placed Order</button>
            <button class="mpm-tab" onclick="filterListings('sold', this)">Sold</button>
            <button class="mpm-tab" onclick="filterListings('removed', this)">Removed</button>
        </div>

        <?php if (empty($listings)): ?>
            <div class="mpm-empty">
                <div style="font-size:2.5rem;margin-bottom:.5rem;"></div>
                No pigs listed yet. Go to <a href="/livestock-owner/caretaker-pig-inventory" style="color:#c0392b;font-weight:700;">Pig Inventory</a> and click "Post to My Market".
            </div>
        <?php else: ?>
            <div class="mpm-grid" id="listingsGrid">
                <?php foreach ($listings as $l): 
                    // Determine display status based on market status and order status
                    $displayStatus = $l['status'];
                    
                    if ($l['status'] === 'sold' && !empty($l['order_status'])) {
                        // Check if cost has been computed
                        $has_cost_computation = !empty($l['has_cost_computation']);
                        $payment_status = $l['payment_status'] ?? 'unpaid';
                        $has_placed_order = !empty($l['delivery_address']) || !empty($l['pickup_date']);
                        
                        // If payment is already made, show as sold (completed transaction)
                        if ($payment_status === 'paid') {
                            $displayStatus = 'sold';
                        } elseif ($l['order_status'] === 'confirmed' && $has_placed_order && !$has_cost_computation) {
                            // Order confirmed AND customer placed order - show compute button
                            $displayStatus = 'placed';
                        } elseif (in_array($l['order_status'], ['preparing']) && !$has_cost_computation) {
                            // Order placed but no cost computation yet - show compute button
                            $displayStatus = 'placed';
                        } elseif ($l['order_status'] === 'cost_computed' || 
                                 (in_array($l['order_status'], ['confirmed', 'preparing']) && $has_cost_computation)) {
                            // Cost computed, waiting for payment
                            $displayStatus = 'waiting_payment';
                        } elseif (in_array($l['order_status'], ['ready_for_pickup', 'completed'])) {
                            // Order completed
                            $displayStatus = 'sold';
                        }
                    }
                ?>
                <div class="mpm-card <?php echo $l['status'] === 'reserved' ? 'reserved' : ''; ?>" data-status="<?php echo $displayStatus; ?>">
                    <?php if (!empty($l['photo_url'])): ?>
                        <img src="<?php echo htmlspecialchars($l['photo_url']); ?>" class="mpm-card-photo" alt="Pig">
                    <?php else: ?>
                        <div class="mpm-card-placeholder"></div>
                    <?php endif; ?>
                    <div class="mpm-card-body">
                        <div class="mpm-card-tag"><?php echo htmlspecialchars($l['pig_tag_id']); ?></div>
                        <div class="mpm-card-row">Pin: <span><?php echo htmlspecialchars($l['pin_number']); ?></span></div>
                        <div class="mpm-card-row">Weight: <span><?php echo number_format($l['weight_kg'], 1); ?> kg</span></div>
                        <?php if ($l['age_months']): ?>
                        <div class="mpm-card-row">Age: <span><?php echo $l['age_months']; ?> mos</span></div>
                        <?php endif; ?>
                        <?php if (!empty($l['description'])): ?>
                        <div class="mpm-card-row" style="margin-top:4px;font-style:italic;color:#999;">"<?php echo htmlspecialchars($l['description']); ?>"</div>
                        <?php endif; ?>
                        <div class="mpm-card-price">₱<?php echo number_format($l['price_per_kg'], 2); ?>/kg</div>
                        <div class="mpm-card-total">Total: ₱<?php echo number_format($l['total_price'], 2); ?></div>
                        <span class="mpm-status <?php echo $displayStatus; ?>">
                            <?php 
                                if ($l['status'] === 'reserved') {
                                    echo 'Pending Order';
                                } elseif ($displayStatus === 'waiting_payment') {
                                    echo 'Waiting for Payment';
                                } elseif ($l['status'] === 'sold' && $displayStatus === 'placed') {
                                    echo 'Placed Order';
                                } elseif ($l['status'] === 'sold' && ($l['payment_status'] ?? '') === 'paid') {
                                    echo 'Sold (Paid)';
                                } elseif ($l['status'] === 'sold') {
                                    echo 'Reserved';
                                } else {
                                    echo ucfirst($l['status']);
                                }
                            ?>
                        </span>
                    </div>

                    <?php if ($l['status'] === 'reserved' && !empty($l['reserved_by_name'])): ?>
                    <div class="mpm-reserved-box">
                        <div class="rb-label"> Pending Order from</div>
                        <div class="rb-name"> <?php echo htmlspecialchars($l['reserved_by_name']); ?></div>
                        <?php if (!empty($l['inquiry_message'])): ?>
                        <div class="rb-msg">"<?php echo htmlspecialchars($l['inquiry_message']); ?>"</div>
                        <?php endif; ?>
                        <?php if (!empty($l['reserved_at'])): ?>
                        <div class="rb-time"><?php echo date('M d, Y h:i A', strtotime($l['reserved_at'])); ?></div>
                        <?php endif; ?>
                    </div>
                    <?php endif; ?>

                    <?php if ($displayStatus === 'sold' && ($l['payment_status'] ?? '') === 'paid' && !empty($l['customer_name'])): ?>
                    <div class="mmp-sold-box" style="margin:8px 12px 0; padding:8px 10px; background:#d4edda; border-left:3px solid #27ae60; border-radius:0 6px 6px 0;">
                        <div class="sb-label" style="font-size:.65rem; font-weight:700; color:#27ae60; text-transform:uppercase; margin-bottom:3px;"> Sold & Paid</div>
                        <div class="sb-name" style="font-size:.82rem; font-weight:700; color:#333;"><?php echo htmlspecialchars($l['customer_name']); ?></div>
                        <div class="sb-msg" style="font-size:.72rem; color:#666; margin-top:3px;">Payment completed. Transaction successful!</div>
                        <?php if (!empty($l['placed_at'])): ?>
                        <div class="sb-time" style="font-size:.65rem; color:#aaa; margin-top:3px;">Sold: <?php echo date('M d, Y h:i A', strtotime($l['placed_at'])); ?></div>
                        <?php endif; ?>
                    </div>
                    <?php endif; ?>

                    <?php if ($displayStatus === 'waiting_payment' && !empty($l['customer_name'])): ?>
                    <div class="mmp-waiting-box">
                        <div class="wb-label"> Waiting for Payment from</div>
                        <div class="wb-name"><?php echo htmlspecialchars($l['customer_name']); ?></div>
                        <div class="wb-msg">Receipt and payment instructions have been sent to customer.</div>
                        <?php if (!empty($l['placed_at'])): ?>
                        <div class="wb-time">Receipt sent: <?php echo date('M d, Y h:i A', strtotime($l['placed_at'])); ?></div>
                        <?php endif; ?>
                    </div>
                    <?php endif; ?>

                    <?php if ($displayStatus === 'placed' && !empty($l['customer_name'])): ?>
                    <div class="mmp-placed-box">
                        <div class="pb-label"> Order Placed by</div>
                        <div class="pb-name"> <?php echo htmlspecialchars($l['customer_name']); ?></div>
                        <?php if (!empty($l['delivery_address'])): ?>
                        <div class="pb-address"> <?php echo htmlspecialchars($l['delivery_address']); ?></div>
                        <?php endif; ?>
                        <?php if (!empty($l['delivery_notes'])): ?>
                        <div class="pb-notes">"<?php echo htmlspecialchars($l['delivery_notes']); ?>"</div>
                        <?php endif; ?>
                        <?php if (!empty($l['placed_at'])): ?>
                        <div class="pb-time">Placed: <?php echo date('M d, Y h:i A', strtotime($l['placed_at'])); ?></div>
                        <?php endif; ?>
                    </div>
                    <?php endif; ?>

                    <?php if (in_array($l['status'], ['active', 'reserved']) || in_array($displayStatus, ['placed', 'waiting_payment'])): ?>
                    <div class="mmp-card-footer">
                        <form method="POST" action="/livestock-owner/update-pig-listing" style="flex:1;display:flex;gap:6px;">
                            <input type="hidden" name="listing_id" value="<?php echo $l['id']; ?>">
                            <?php if ($l['status'] === 'reserved'): ?>
                                <button type="button" class="mpm-btn-sold" style="flex:2;"
                                    onclick="openSoldModal(<?php echo $l['id']; ?>, '<?php echo htmlspecialchars(addslashes($l['pig_tag_id'])); ?>', '<?php echo htmlspecialchars(addslashes($l['reserved_by_name'] ?? '')); ?>')">
                                     Reserve for Customer
                                </button>
                                <button type="submit" name="action" value="active"
                                    class="mpm-btn-remove"
                                    onclick="return confirm('Cancel this pending order?')">↩ Cancel</button>
                            <?php elseif ($displayStatus === 'waiting_payment'): ?>
                                <div style="flex:1;padding:8px;text-align:center;background:#fef9e7;border-radius:6px;font-size:.72rem;color:#b7950b;font-weight:700;">
                                     Waiting for Customer Payment
                                </div>
                            <?php elseif ($displayStatus === 'placed'): ?>
                                <button type="button" class="mpm-btn-compute" style="flex:1;"
                                    onclick="openComputeModal(
                                        <?php echo $l['id']; ?>,
                                        '<?php echo htmlspecialchars(addslashes($l['pig_tag_id'])); ?>',
                                        '<?php echo htmlspecialchars(addslashes($l['pin_number'])); ?>',
                                        <?php echo $l['total_price']; ?>,
                                        '<?php echo htmlspecialchars(addslashes($l['customer_name'] ?? '')); ?>',
                                        '<?php echo htmlspecialchars(addslashes($l['delivery_address'] ?? '')); ?>',
                                        <?php echo $l['customer_id'] ?? 0; ?>,
                                        '<?php echo $l['delivery_method'] ?? 'pickup'; ?>',
                                        <?php echo $l['include_laman_loob'] ?? 0; ?>,
                                        <?php echo $l['include_boopes'] ?? 0; ?>,
                                        <?php echo $l['include_dinuguan'] ?? 0; ?>
                                    )">
                                    Compute Total Cost
                                </button>
                            <?php else: ?>
                                <button type="submit" name="action" value="removed" class="mpm-btn-remove" style="flex:1;">Remove</button>
                            <?php endif; ?>
                        </form>
                    </div>
                    <?php endif; ?>
                </div>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>

        </div><!-- /panel-pig -->

        <!-- ====== LECHON PANEL ====== -->
        <div class="mpm-tab-panel <?php echo $activeTab === 'lechon' ? 'active' : ''; ?>" id="panel-lechon">

            <?php if (!empty($lechon_orders_incoming)): ?>
            <!-- Incoming lechon orders from customers -->
            <?php if ($lechon_pending_count > 0): ?>
            <div class="mpm-reserved-count">
                <?php echo $lechon_pending_count; ?> pending lechon order<?php echo $lechon_pending_count > 1 ? 's' : ''; ?> — review and confirm or decline.
            </div>
            <?php endif; ?>
            <div style="margin-bottom:24px;">
                <div style="font-size:.82rem;font-weight:700;color:#444;margin-bottom:12px;text-transform:uppercase;letter-spacing:.4px;">
                    Incoming Lechon Orders
                </div>
                
                <!-- TABLE FORMAT -->
                <div style="background:#fff;border-radius:12px;box-shadow:0 3px 10px rgba(0,0,0,.08);overflow:hidden;">
                    <table style="width:100%;border-collapse:collapse;">
                        <thead>
                            <tr style="background:#f8f9fa;border-bottom:2px solid #e9ecef;">
                                <th style="padding:12px;text-align:left;font-size:.75rem;color:#666;text-transform:uppercase;letter-spacing:.03em;">Order #</th>
                                <th style="padding:12px;text-align:left;font-size:.75rem;color:#666;text-transform:uppercase;letter-spacing:.03em;">Product</th>
                                <th style="padding:12px;text-align:left;font-size:.75rem;color:#666;text-transform:uppercase;letter-spacing:.03em;">Customer</th>
                                <th style="padding:12px;text-align:left;font-size:.75rem;color:#666;text-transform:uppercase;letter-spacing:.03em;">Price</th>
                                <th style="padding:12px;text-align:center;font-size:.75rem;color:#666;text-transform:uppercase;letter-spacing:.03em;">Status</th>
                                <th style="padding:12px;text-align:left;font-size:.75rem;color:#666;text-transform:uppercase;letter-spacing:.03em;">Date</th>
                                <th style="padding:12px;text-align:center;font-size:.75rem;color:#666;text-transform:uppercase;letter-spacing:.03em;">Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php
                            $lco_cat_labels = [
                                'WHOLE_LECHON'   => 'Whole Lechon',
                                'WHOLE_PACKAGES' => 'w/ Packages',
                                'BELLY_BUNDLES'  => 'Belly Bundles',
                                'WEEKDAY_COMBOS' => 'Weekday Combos',
                                'EVENT_CATERING' => 'Event / Catering',
                                'OTHER'          => 'Other',
                            ];
                            foreach ($lechon_orders_incoming as $lo_inc): ?>
                            <tr style="border-bottom:1px solid #f5f5f5;">
                                <td style="padding:14px 12px;">
                                    <div style="font-size:.7rem;font-weight:700;color:#888;">#<?php echo htmlspecialchars($lo_inc['order_number']); ?></div>
                                </td>
                                <td style="padding:14px 12px;">
                                    <div style="font-size:.85rem;font-weight:700;color:#222;margin-bottom:2px;">
                                        <?php echo htmlspecialchars($lo_inc['listing_name']); ?>
                                    </div>
                                    <div style="font-size:.7rem;color:#888;">
                                        <?php echo htmlspecialchars($lco_cat_labels[$lo_inc['category']] ?? $lo_inc['category']); ?>
                                    </div>
                                </td>
                                <td style="padding:14px 12px;">
                                    <div style="font-size:.8rem;font-weight:600;color:#555;">
                                        <?php echo htmlspecialchars($lo_inc['customer_name']); ?>
                                    </div>
                                    <?php if (!empty($lo_inc['inquiry_message'])): ?>
                                    <div style="font-size:.7rem;color:#777;margin-top:3px;font-style:italic;">
                                        "<?php echo htmlspecialchars($lo_inc['inquiry_message']); ?>"
                                    </div>
                                    <?php endif; ?>
                                </td>
                                <td style="padding:14px 12px;">
                                    <div style="font-size:.85rem;font-weight:700;color:#c0392b;">
                                        ₱<?php echo number_format($lo_inc['price'], 2); ?>
                                    </div>
                                </td>
                                <td style="padding:14px 12px;text-align:center;">
                                    <span style="display:inline-block;padding:4px 12px;border-radius:15px;font-size:.7rem;font-weight:700;
                                        background:<?php echo $lo_inc['order_status']==='pending' ? '#fff3cd' : ($lo_inc['order_status']==='preparing' ? '#e8daef' : '#d1ecf1'); ?>;
                                        color:<?php echo $lo_inc['order_status']==='pending' ? '#856404' : ($lo_inc['order_status']==='preparing' ? '#6c3483' : '#0c5460'); ?>;">
                                        <?php echo ucfirst($lo_inc['order_status']); ?>
                                    </span>
                                    <?php if ($lo_inc['order_status'] === 'preparing' && !empty($lo_inc['pickup_date'])): ?>
                                    <div style="font-size:.65rem;color:#888;margin-top:4px;">
                                        Pickup: <?php echo date('M d', strtotime($lo_inc['pickup_date'])); ?>
                                    </div>
                                    <?php endif; ?>
                                </td>
                                <td style="padding:14px 12px;">
                                    <div style="font-size:.7rem;color:#aaa;">
                                        <?php echo date('M d, Y', strtotime($lo_inc['created_at'])); ?>
                                    </div>
                                    <div style="font-size:.65rem;color:#bbb;">
                                        <?php echo date('h:i A', strtotime($lo_inc['created_at'])); ?>
                                    </div>
                                </td>
                                <td style="padding:14px 12px;text-align:center;">
                                    <?php if ($lo_inc['order_status'] === 'pending'): ?>
                                    <form method="POST" action="/livestock-owner/confirm-lechon-order" style="margin-bottom:4px;">
                                        <input type="hidden" name="order_id" value="<?php echo (int)$lo_inc['id']; ?>">
                                        <button type="submit" style="width:100%;background:#28a745;color:#fff;border:none;border-radius:6px;padding:6px 12px;font-size:.75rem;font-weight:600;cursor:pointer;">
                                            ✓ Confirm
                                        </button>
                                    </form>
                                    <form method="POST" action="/livestock-owner/decline-lechon-order" onsubmit="return confirm('Decline this order?')">
                                        <input type="hidden" name="order_id" value="<?php echo (int)$lo_inc['id']; ?>">
                                        <button type="submit" style="width:100%;background:#dc3545;color:#fff;border:none;border-radius:6px;padding:6px 12px;font-size:.75rem;font-weight:600;cursor:pointer;">
                                            ✕ Decline
                                        </button>
                                    </form>
                                    <?php else: ?>
                                    <div style="font-size:.7rem;color:#888;">—</div>
                                    <?php endif; ?>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>
            <?php endif; ?>

            <!-- Lechon section header -->
            <div class="lch-section-header">
                <div>
                    <p class="lch-section-title">Lechon Listings</p>
                    <p class="lch-section-sub">Manage your cooked lechon and lechon products</p>
                </div>
                <button class="lch-btn-add" onclick="openAddLechonModal()">+ Add Lechon</button>
            </div>

            <!-- Lechon sub-filters -->
            <div class="mpm-tabs" id="lechon-filter-tabs">
                <button class="mpm-tab active" onclick="filterLechon('all', this)">All</button>
                <button class="mpm-tab" onclick="filterLechon('WHOLE_LECHON', this)">Whole Lechon</button>
                <button class="mpm-tab" onclick="filterLechon('WHOLE_PACKAGES', this)">w/ Packages</button>
                <button class="mpm-tab" onclick="filterLechon('BELLY_BUNDLES', this)">Belly Bundles</button>
                <button class="mpm-tab" onclick="filterLechon('WEEKDAY_COMBOS', this)">Weekday Combos</button>
                <button class="mpm-tab" onclick="filterLechon('EVENT_CATERING', this)">Event / Catering</button>
                <button class="mpm-tab" onclick="filterLechon('OTHER', this)">Other</button>
                <button class="mpm-tab" onclick="filterLechon('inactive', this)">Inactive</button>
            </div>

            <?php if (empty($lechon_listings)): ?>
                <!-- Empty state -->
                <div class="mpm-empty">
                    <div style="font-size:1rem;font-weight:700;color:#555;margin-bottom:.4rem;">No Lechon Listings Yet</div>
                    <div style="margin-bottom:1rem;">Start selling cooked lechon and lechon products.</div>
                    <button class="lch-btn-add" onclick="openAddLechonModal()">+ Add Lechon</button>
                </div>
            <?php else: ?>
                <!-- Count -->
                <div style="font-size:.78rem;color:#999;margin-bottom:10px;">
                    Showing 1 to <?php echo count($lechon_listings); ?> of <?php echo count($lechon_listings); ?> listing<?php echo count($lechon_listings) !== 1 ? 's' : ''; ?>
                </div>
                <!-- Grid -->
                <div class="mpm-grid" id="lechonGrid">
                    <?php foreach ($lechon_listings as $lc):
                        $badgeClass = htmlspecialchars($lc['category']);
                        $categoryLabels = [
                            'WHOLE_LECHON'   => 'Whole Lechon',
                            'WHOLE_PACKAGES' => 'Whole Lechon w/ Packages',
                            'BELLY_BUNDLES'  => 'Lechon Belly Bundles',
                            'WEEKDAY_COMBOS' => 'Weekday / Daily Combos',
                            'EVENT_CATERING' => 'Event & Catering Promos',
                            'OTHER'          => 'Other',
                        ];
                        $catLabel = $categoryLabels[$lc['category']] ?? ucfirst(strtolower($lc['category']));
                        // data-filter maps: active/inactive categories to filter keys
                        $filterKey = ($lc['status'] === 'inactive' || $lc['status'] === 'removed')
                            ? 'inactive' : $lc['category'];
                    ?>
                    <div class="mpm-card" data-lch-filter="<?php echo $filterKey; ?>" data-lch-status="<?php echo htmlspecialchars($lc['status']); ?>">
                        <!-- Photo -->
                        <?php if (!empty($lc['photo_url'])): ?>
                            <div style="position:relative;">
                                <img src="<?php echo htmlspecialchars($lc['photo_url']); ?>"
                                     class="mpm-card-photo" alt="Lechon"
                                     onerror="this.style.display='none';this.nextElementSibling.style.display='flex';">
                                <div class="mpm-card-placeholder" style="display:none;"></div>
                                <!-- Category badge -->
                                <span class="lch-badge <?php echo $badgeClass; ?>"
                                      style="position:absolute;top:8px;left:8px;">
                                    <?php echo $catLabel; ?>
                                </span>
                                <!-- Actions menu -->
                                <div class="lch-card-menu-wrap" style="position:absolute;top:8px;right:8px;">
                                    <button class="lch-card-menu-btn"
                                            onclick="toggleLchMenu(this, event)"
                                            title="Actions">...</button>
                                    <div class="lch-card-dropdown">
                                        <button onclick="openEditLechonModal(<?php echo htmlspecialchars(json_encode($lc)); ?>)">
                                            Edit
                                        </button>
                                        <div class="sep"></div>
                                        <?php if ($lc['status'] === 'active'): ?>
                                        <form method="POST" action="/livestock-owner/update-lechon-status" style="margin:0;">
                                            <input type="hidden" name="listing_id" value="<?php echo $lc['id']; ?>">
                                            <input type="hidden" name="status" value="inactive">
                                            <button type="submit">Deactivate</button>
                                        </form>
                                        <?php elseif ($lc['status'] === 'inactive'): ?>
                                        <form method="POST" action="/livestock-owner/update-lechon-status" style="margin:0;">
                                            <input type="hidden" name="listing_id" value="<?php echo $lc['id']; ?>">
                                            <input type="hidden" name="status" value="active">
                                            <button type="submit">Activate</button>
                                        </form>
                                        <?php endif; ?>
                                        <div class="sep"></div>
                                        <form method="POST" action="/livestock-owner/delete-lechon" style="margin:0;"
                                              onsubmit="return confirm('Remove this lechon listing?')">
                                            <input type="hidden" name="listing_id" value="<?php echo $lc['id']; ?>">
                                            <button type="submit" style="color:#c0392b;">Remove</button>
                                        </form>
                                    </div>
                                </div>
                            </div>
                        <?php else: ?>
                            <div style="position:relative;">
                                <div class="mpm-card-placeholder"></div>
                                <span class="lch-badge <?php echo $badgeClass; ?>"
                                      style="position:absolute;top:8px;left:8px;">
                                    <?php echo $catLabel; ?>
                                </span>
                                <div class="lch-card-menu-wrap" style="position:absolute;top:8px;right:8px;">
                                    <button class="lch-card-menu-btn"
                                            onclick="toggleLchMenu(this, event)"
                                            title="Actions">...</button>
                                    <div class="lch-card-dropdown">
                                        <button onclick="openEditLechonModal(<?php echo htmlspecialchars(json_encode($lc)); ?>)">
                                            Edit
                                        </button>
                                        <div class="sep"></div>
                                        <?php if ($lc['status'] === 'active'): ?>
                                        <form method="POST" action="/livestock-owner/update-lechon-status" style="margin:0;">
                                            <input type="hidden" name="listing_id" value="<?php echo $lc['id']; ?>">
                                            <input type="hidden" name="status" value="inactive">
                                            <button type="submit">Deactivate</button>
                                        </form>
                                        <?php elseif ($lc['status'] === 'inactive'): ?>
                                        <form method="POST" action="/livestock-owner/update-lechon-status" style="margin:0;">
                                            <input type="hidden" name="listing_id" value="<?php echo $lc['id']; ?>">
                                            <input type="hidden" name="status" value="active">
                                            <button type="submit">Activate</button>
                                        </form>
                                        <?php endif; ?>
                                        <div class="sep"></div>
                                        <form method="POST" action="/livestock-owner/delete-lechon" style="margin:0;"
                                              onsubmit="return confirm('Remove this lechon listing?')">
                                            <input type="hidden" name="listing_id" value="<?php echo $lc['id']; ?>">
                                            <button type="submit" style="color:#c0392b;">Remove</button>
                                        </form>
                                    </div>
                                </div>
                            </div>
                        <?php endif; ?>

                        <!-- Card body -->
                        <div class="mpm-card-body">
                            <div class="mpm-card-tag"><?php echo htmlspecialchars($lc['name']); ?></div>

                            <?php if (!empty($lc['weight_kg'])): ?>
                            <div class="mpm-card-row">
                                <?php echo number_format($lc['weight_kg'], 1); ?> kg
                                <?php if (!empty($lc['serving_capacity'])): ?>
                                &bull; Good for <span><?php echo htmlspecialchars($lc['serving_capacity']); ?></span>
                                <?php endif; ?>
                            </div>
                            <?php elseif (!empty($lc['portion_size'])): ?>
                            <div class="mpm-card-row">
                                <span><?php echo htmlspecialchars($lc['portion_size']); ?></span>
                                <?php if (!empty($lc['serving_capacity'])): ?>
                                &bull; Good for <span><?php echo htmlspecialchars($lc['serving_capacity']); ?></span>
                                <?php endif; ?>
                            </div>
                            <?php elseif (!empty($lc['serving_capacity'])): ?>
                            <div class="mpm-card-row">Good for <span><?php echo htmlspecialchars($lc['serving_capacity']); ?></span></div>
                            <?php endif; ?>

                            <?php if (!empty($lc['description'])): ?>
                            <div class="mpm-card-row" style="font-style:italic;color:#aaa;margin-top:2px;font-size:.72rem;">
                                "<?php echo htmlspecialchars(mb_strimwidth($lc['description'], 0, 60, '…')); ?>"
                            </div>
                            <?php endif; ?>

                            <div class="mpm-card-price">₱<?php echo number_format($lc['price'], 2); ?></div>

                            <span class="mpm-status <?php echo htmlspecialchars($lc['status']); ?>">
                                <?php echo $lc['status'] === 'active' ? 'Active' : ($lc['status'] === 'inactive' ? 'Inactive' : 'Removed'); ?>
                            </span>

                            <div class="mpm-card-row" style="margin-top:5px;">
                                Available: <span><?php echo date('M j, Y', strtotime($lc['available_date'])); ?></span>
                            </div>
                            <?php if ($lc['available_quantity'] > 1): ?>
                            <div class="mpm-card-row">Qty: <span><?php echo $lc['available_quantity']; ?></span></div>
                            <?php endif; ?>
                        </div>
                    </div>
                    <?php endforeach; ?>

                    <!-- Add More card -->
                    <div class="mpm-card" style="align-items:center;justify-content:center;min-height:200px;cursor:pointer;border:2px dashed #e0e0e0;background:#fafafa;box-shadow:none;"
                         onclick="openAddLechonModal()">
                        <div style="text-align:center;color:#bbb;padding:20px;">
                            <div style="font-size:2rem;margin-bottom:.4rem;">+</div>
                            <div style="font-size:.82rem;font-weight:700;">Add Lechon</div>
                            <div style="font-size:.72rem;margin-top:3px;">Create a new listing</div>
                        </div>
                    </div>
                </div>
            <?php endif; ?>
        </div><!-- /panel-lechon -->

    </div>
    </div> <!-- Close dashboard-content -->
    </main>
</div>
<!-- Compute Total Cost Modal -->
<div class="ctc-overlay" id="computeModalOverlay" onclick="if(event.target===this)closeComputeModal()">
    <div class="ctc-modal">
        <div class="ctc-header">
            <h3>Compute Total Cost</h3>
            <button class="ctc-close" onclick="closeComputeModal()">✕</button>
        </div>
        <div class="ctc-body">
            <form method="POST" action="/livestock-owner/compute-total-cost">
                <input type="hidden" name="listing_id" id="ctc_listing_id">
                <input type="hidden" name="customer_id" id="ctc_customer_id">
                
                <!-- Customer Info Section -->
                <div class="ctc-section">
                    <h4>👤 Customer Information</h4>
                    <div class="ctc-info-grid">
                        <div class="ctc-info-item">
                            <label>Ordered by:</label>
                            <span id="ctc_customer_name"></span>
                        </div>
                        <div class="ctc-info-item">
                            <label>Delivery Address:</label>
                            <span id="ctc_delivery_address"></span>
                        </div>
                    </div>
                </div>

                <!-- Pig Details Section -->
                <div class="ctc-section">
                    <h4>Pig Details</h4>
                    <div class="ctc-info-grid">
                        <div class="ctc-info-item">
                            <label>Pig Tag:</label>
                            <span id="ctc_pig_tag"></span>
                        </div>
                        <div class="ctc-info-item">
                            <label>Pin Number:</label>
                            <span id="ctc_pin_number"></span>
                        </div>
                        <div class="ctc-info-item">
                            <label>Base Amount:</label>
                            <span id="ctc_base_amount" class="ctc-amount"></span>
                        </div>
                    </div>
                </div>

                <!-- Delivery Method Section -->
                <div class="ctc-section">
                    <h4>Delivery Method</h4>
                    <div style="padding:8px 10px; background:#e3f2fd; border-radius:6px; margin-bottom:10px; font-size:.85rem; color:#1565c0;">
                        <strong>Customer selected:</strong> <span id="ctc_customer_delivery_method"></span>
                    </div>
                    <input type="hidden" name="delivery_method" id="ctc_delivery_method_hidden">
                    <div class="ctc-delivery-fee" id="ctc_delivery_fee_section" style="display:none;">
                        <label>Delivery Fee (₱):</label>
                        <input type="number" name="delivery_fee" id="ctc_delivery_fee" min="0" step="0.01" placeholder="0.00" oninput="calculateTotal()">
                    </div>
                </div>

                <!-- Additional Items Section -->
                <div class="ctc-section">
                    <h4>Additional Items</h4>
                    <div id="ctc_customer_additional_item" style="padding:8px 10px; background:#e8f5e9; border-radius:6px; font-size:.85rem; color:#2d7a2d;">
                        <strong>Customer requested:</strong> <span id="ctc_additional_item_text">None</span>
                    </div>
                    <input type="hidden" name="additional_item" id="ctc_additional_item_hidden">
                </div>

                <!-- Labor Cost Section -->
                <div class="ctc-section">
                    <h4>Labor Cost</h4>
                    <div class="ctc-delivery-fee">
                        <label>Labor Cost (₱):</label>
                        <input type="number" name="labor_cost" id="ctc_labor_cost" min="0" step="0.01" placeholder="0.00" oninput="calculateTotal()">
                    </div>
                </div>

                <!-- Total Cost Section -->
                <div class="ctc-section ctc-total-section">
                    <div class="ctc-cost-breakdown">
                        <div class="ctc-cost-row">
                            <span>Pig Base Amount:</span>
                            <span id="ctc_display_base">₱0.00</span>
                        </div>
                        <div class="ctc-cost-row" id="ctc_delivery_cost_row" style="display:none;">
                            <span>Delivery Fee:</span>
                            <span id="ctc_display_delivery">₱0.00</span>
                        </div>
                        <div class="ctc-cost-row" id="ctc_additional_item_row" style="display:none;">
                            <span id="ctc_additional_item_label">Additional Item:</span>
                            <span id="ctc_display_additional_item">₱0.00</span>
                        </div>
                        <div class="ctc-cost-row" id="ctc_labor_cost_row" style="display:none;">
                            <span>Labor Cost:</span>
                            <span id="ctc_display_labor_cost">₱0.00</span>
                        </div>
                        <div class="ctc-cost-total">
                            <span>Total Cost:</span>
                            <span id="ctc_display_total">₱0.00</span>
                        </div>
                        
                        <!-- Payment Breakdown -->
                        <div class="ctc-payment-breakdown" id="ctc_payment_breakdown" style="margin-top:15px;padding-top:15px;border-top:2px solid #e0e0e0;">
                            <div class="ctc-payment-row" id="ctc_payment_amount_row">
                                <span id="ctc_payment_label">Amount to Pay:</span>
                                <span id="ctc_payment_amount" style="font-weight:700;color:#2d7a2d;">₱0.00</span>
                            </div>
                            <div class="ctc-payment-row" id="ctc_balance_row" style="display:none;">
                                <span>Remaining Balance:</span>
                                <span id="ctc_balance_amount" style="color:#e67e22;">₱0.00</span>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="ctc-actions">
                    <button type="button" class="ctc-btn-cancel" onclick="closeComputeModal()">Cancel</button>
                    <button type="submit" class="ctc-btn-submit">Generate Receipt &amp; Payment Instructions</button>
                </div>
            </form>
        </div>
    </div>
</div>
<div class="bpm-overlay" id="soldModalOverlay" onclick="if(event.target===this)closeSoldModal()">
    <div class="bpm-modal">
        <div class="bpm-header" style="background:#2d7a2d;">
            <h3>Reserve Pig for Customer</h3>
            <button class="bpm-close" onclick="closeSoldModal()">✕</button>
        </div>
        <div class="bpm-body">
            <div class="bpm-pig-summary" style="background:#f0faf0;border-left:3px solid #2d7a2d;border-radius:0 8px 8px 0;padding:10px 14px;margin-bottom:14px;">
                <strong id="sm_tag" style="color:#2d7a2d;display:block;font-size:1rem;margin-bottom:3px;"></strong>
                <span id="sm_buyer" style="font-size:.8rem;color:#666;"></span>
            </div>
            <form method="POST" action="/livestock-owner/update-pig-listing">
                <input type="hidden" name="listing_id" id="sm_listing_id">
                <input type="hidden" name="action" value="sold">
                <div class="bpm-field">
                    <label style="display:block;font-size:.78rem;font-weight:700;color:#444;margin-bottom:4px;">
                        Message to Customer <span style="color:#aaa;font-weight:400;">(optional)</span>
                    </label>
                    <textarea name="seller_feedback" id="sm_feedback"
                        placeholder="e.g. Thank you! Your pig is now reserved. Please pick it up at our farm on Saturday morning. Contact us at 09XX-XXX-XXXX."
                        style="width:100%;padding:8px 10px;border:1.5px solid #e0e0e0;border-radius:7px;font-size:.85rem;box-sizing:border-box;resize:vertical;min-height:90px;outline:none;"
                        onfocus="this.style.borderColor='#2d7a2d'" onblur="this.style.borderColor='#e0e0e0'"></textarea>
                </div>
                <div style="display:flex;gap:8px;">
                    <button type="button" onclick="closeSoldModal()"
                        style="flex:1;background:#f0f0f0;color:#555;border:none;border-radius:7px;padding:10px;font-size:.88rem;font-weight:700;cursor:pointer;">
                        Cancel
                    </button>
                    <button type="submit"
                        style="flex:1;background:#2d7a2d;color:#fff;border:none;border-radius:7px;padding:10px;font-size:.88rem;font-weight:700;cursor:pointer;">
                         Reserved
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<style>
.bpm-overlay { display:none; position:fixed; inset:0; background:rgba(0,0,0,.45); z-index:1000; align-items:center; justify-content:center; }
.bpm-overlay.open { display:flex; }
.bpm-modal { background:#fff; border-radius:14px; width:92%; max-width:440px; box-shadow:0 8px 32px rgba(0,0,0,.18); overflow:hidden; }
.bpm-header { color:#fff; padding:14px 18px; display:flex; align-items:center; justify-content:space-between; }
.bpm-header h3 { margin:0; font-size:1rem; }
.bpm-close { background:none; border:none; color:#fff; font-size:1.3rem; cursor:pointer; }
.bpm-body { padding:18px; }
.bpm-field { margin-bottom:14px; }

/* Compute Total Cost Modal Styles */
.ctc-overlay { 
    display:none; 
    position:fixed; 
    inset:0; 
    background:rgba(0,0,0,.5); 
    z-index:1000; 
    align-items:center; 
    justify-content:center; 
}
.ctc-overlay.open { 
    display:flex !important; 
}
.ctc-modal { 
    background:#fff; 
    border-radius:14px; 
    width:95%; 
    max-width:700px; 
    box-shadow:0 8px 32px rgba(0,0,0,.2); 
    overflow:hidden; 
    max-height:90vh; 
    overflow-y:auto; 
}
.ctc-header { background:#2d7a2d; color:#fff; padding:14px 18px; display:flex; align-items:center; justify-content:space-between; }
.ctc-header h3 { margin:0; font-size:1.1rem; }
.ctc-close { background:none; border:none; color:#fff; font-size:1.3rem; cursor:pointer; line-height:1; }
.ctc-body { padding:20px; }

.ctc-section { margin-bottom:20px; padding:15px; border:1px solid #e0e0e0; border-radius:8px; }
.ctc-section h4 { margin:0 0 12px 0; font-size:.9rem; color:#2d7a2d; font-weight:700; }

.ctc-info-grid { display:grid; grid-template-columns:1fr 1fr; gap:12px; }
.ctc-info-item { display:flex; flex-direction:column; }
.ctc-info-item label { font-size:.75rem; font-weight:700; color:#666; margin-bottom:4px; }
.ctc-info-item span { font-size:.85rem; color:#333; }
.ctc-amount { font-weight:700; color:#2ecc71; }

.ctc-delivery-options { display:flex; gap:15px; margin-bottom:10px; }
.ctc-radio-option { display:flex; align-items:center; gap:6px; cursor:pointer; font-size:.85rem; }
.ctc-radio-option input[type="radio"] { margin:0; }
.ctc-delivery-fee { margin-top:10px; }
.ctc-delivery-fee label { display:block; font-size:.75rem; font-weight:700; color:#666; margin-bottom:4px; }
.ctc-delivery-fee input { width:100%; padding:6px 8px; border:1px solid #ddd; border-radius:4px; font-size:.85rem; }

.ctc-additional-items { display:flex; flex-direction:column; gap:8px; }
.ctc-additional-items select { width:100%; padding:8px 10px; border:1.5px solid #ddd; border-radius:4px; font-size:.85rem; outline:none; }
.ctc-additional-items select:focus { border-color:#2d7a2d; }

.ctc-payment-options { display:flex; flex-direction:column; gap:8px; }
.ctc-radio-options { display:flex; gap:20px; margin-bottom:10px; }
.ctc-radio-option { display:flex; align-items:center; gap:6px; cursor:pointer; font-size:.85rem; }
.ctc-radio-option input[type="radio"] { margin:0; }

.ctc-payment-breakdown { }
.ctc-payment-row { display:flex; justify-content:space-between; padding:4px 0; font-size:.85rem; }

.ctc-total-section { background:#f8f9fa; border-color:#2d7a2d; }
.ctc-cost-breakdown { }
.ctc-cost-row { display:flex; justify-content:space-between; padding:6px 0; font-size:.85rem; border-bottom:1px solid #eee; }
.ctc-cost-total { display:flex; justify-content:space-between; padding:10px 0; font-size:1rem; font-weight:700; color:#2d7a2d; border-top:2px solid #2d7a2d; margin-top:8px; }

.ctc-actions { display:flex; gap:10px; margin-top:20px; }
.ctc-btn-submit { flex:1; background:#2d7a2d; color:#fff; border:none; border-radius:7px; padding:12px; font-size:.9rem; font-weight:700; cursor:pointer; }
.ctc-btn-submit:hover { background:#1a4a1a; }
.ctc-btn-cancel { flex:1; background:#f0f0f0; color:#555; border:none; border-radius:7px; padding:12px; font-size:.9rem; font-weight:700; cursor:pointer; }
</style>

<script>
function openSoldModal(listingId, tag, buyerName) {
    document.getElementById('sm_listing_id').value = listingId;
    document.getElementById('sm_tag').textContent  = ' ' + tag;
    document.getElementById('sm_buyer').textContent = buyerName
        ? 'Pending order from: ' + buyerName
        : 'Reserving pig directly (no pending order).';
    document.getElementById('sm_feedback').value = '';
    document.getElementById('soldModalOverlay').classList.add('open');
}
function closeSoldModal() {
    document.getElementById('soldModalOverlay').classList.remove('open');
}

// Compute Total Cost Modal Functions
let baseAmount = 0;

function openComputeModal(listingId, pigTag, pinNumber, pigBaseAmount, customerName, deliveryAddress, customerId, deliveryMethod, includeLamanLoob, includeBoopes, includeDinuguan) {
    console.log('Opening compute modal with:', {listingId, pigTag, pinNumber, pigBaseAmount, customerName, deliveryAddress, customerId, deliveryMethod, includeLamanLoob, includeBoopes, includeDinuguan});
    
    try {
        baseAmount = pigBaseAmount;
        
        const el_listing_id = document.getElementById('ctc_listing_id');
        const el_customer_id = document.getElementById('ctc_customer_id');
        const el_pig_tag = document.getElementById('ctc_pig_tag');
        const el_pin_number = document.getElementById('ctc_pin_number');
        const el_base_amount = document.getElementById('ctc_base_amount');
        const el_customer_name = document.getElementById('ctc_customer_name');
        const el_delivery_address = document.getElementById('ctc_delivery_address');
        const el_delivery_fee = document.getElementById('ctc_delivery_fee');
        const el_delivery_fee_section = document.getElementById('ctc_delivery_fee_section');
        const el_labor_cost = document.getElementById('ctc_labor_cost');
        const el_modal_overlay = document.getElementById('computeModalOverlay');
        
        console.log('Modal overlay element:', el_modal_overlay);
        
        if (!el_modal_overlay) {
            console.error('Modal overlay not found!');
            alert('Error: Modal not found. Please refresh the page.');
            return;
        }
        
        if (!el_listing_id || !el_pig_tag) {
            console.error('Required elements not found in DOM');
            alert('Error: Modal elements not found. Please refresh the page.');
            return;
        }
        
        if (el_listing_id) el_listing_id.value = listingId;
        if (el_customer_id) el_customer_id.value = customerId;
        if (el_pig_tag) el_pig_tag.textContent = pigTag;
        if (el_pin_number) el_pin_number.textContent = pinNumber;
        if (el_base_amount) el_base_amount.textContent = '₱' + pigBaseAmount.toLocaleString('en-PH', {minimumFractionDigits:2});
        if (el_customer_name) el_customer_name.textContent = customerName;
        if (el_delivery_address) el_delivery_address.textContent = deliveryAddress;
        
        // Show customer's delivery method selection
        const el_customer_delivery_method = document.getElementById('ctc_customer_delivery_method');
        const el_delivery_method_hidden = document.getElementById('ctc_delivery_method_hidden');
        if (el_customer_delivery_method) {
            el_customer_delivery_method.textContent = deliveryMethod === 'delivery' ? 'Delivery' : 'Pickup';
        }
        if (el_delivery_method_hidden) {
            el_delivery_method_hidden.value = deliveryMethod || 'pickup';
        }
        
        // Show/hide delivery fee input based on customer's choice
        if (deliveryMethod === 'delivery') {
            if (el_delivery_fee_section) el_delivery_fee_section.style.display = 'block';
        } else {
            if (el_delivery_fee_section) el_delivery_fee_section.style.display = 'none';
        }
        if (el_delivery_fee) el_delivery_fee.value = '';
        
        // Show customer's additional item selection
        let additionalItemText = 'None';
        let additionalItemValue = '';
        if (includeLamanLoob) {
            additionalItemText = 'Laman Loob (Innards)';
            additionalItemValue = 'laman_loob';
        } else if (includeBoopes) {
            additionalItemText = 'Boopes (Intestines)';
            additionalItemValue = 'boopes';
        } else if (includeDinuguan) {
            additionalItemText = 'Dinuguan (Blood Stew)';
            additionalItemValue = 'dinuguan';
        }
        
        const el_additional_item_text = document.getElementById('ctc_additional_item_text');
        const el_additional_item_hidden = document.getElementById('ctc_additional_item_hidden');
        if (el_additional_item_text) {
            el_additional_item_text.textContent = additionalItemText;
        }
        if (el_additional_item_hidden) {
            el_additional_item_hidden.value = additionalItemValue;
        }
        
        // Reset labor cost
        if (el_labor_cost) el_labor_cost.value = '';
        
        calculateTotal();
        
        console.log('About to show modal...');
        el_modal_overlay.classList.add('open');
        console.log('Modal should now be visible');
        
    } catch (e) {
        console.error('Error opening compute modal:', e);
        alert('Error opening modal: ' + e.message);
    }
}

function closeComputeModal() {
    console.log('Closing compute modal');
    const modal = document.getElementById('computeModalOverlay');
    if (modal) {
        modal.classList.remove('open');
        console.log('Modal closed');
    } else {
        console.error('Modal overlay not found when trying to close');
    }
}

function updateDeliveryFee() {
    const deliveryMethod = document.getElementById('ctc_delivery_method').value;
    const deliveryFeeSection = document.getElementById('ctc_delivery_fee_section');
    
    if (deliveryMethod === 'delivery') {
        deliveryFeeSection.style.display = 'block';
    } else {
        deliveryFeeSection.style.display = 'none';
        document.getElementById('ctc_delivery_fee').value = '';
    }
    calculateTotal();
}

function onAdditionalItemChange() {
    const selectedItem = document.getElementById('ctc_additional_item').value;
    const priceSection = document.getElementById('ctc_additional_price_section');
    const label = document.getElementById('ctc_additional_item_label');
    
    console.log('Additional item changed to:', selectedItem);
    
    if (selectedItem) {
        // Hide the price section since items are free
        priceSection.style.display = 'none';
        
        // Update the label text
        const itemName = selectedItem.charAt(0).toUpperCase() + selectedItem.slice(1).replace(/_/g, ' ');
        if (label) label.textContent = itemName + ' (Free):';
    } else {
        priceSection.style.display = 'none';
    }
    calculateTotal();
}

function calculateTotal() {
    console.log('calculateTotal() called');
    try {
        // Base amount
        const baseDisplay = document.getElementById('ctc_display_base');
        if (baseDisplay) {
            baseDisplay.textContent = '₱' + baseAmount.toLocaleString('en-PH', {minimumFractionDigits:2});
        }
        
        let total = baseAmount;
        console.log('Starting calculation with base amount:', baseAmount);
        
        // Delivery fee
        const deliveryMethod = document.getElementById('ctc_delivery_method')?.value || 'pickup';
        const deliveryFee = parseFloat(document.getElementById('ctc_delivery_fee')?.value) || 0;
        const deliveryRow = document.getElementById('ctc_delivery_cost_row');
        
        if (deliveryMethod === 'delivery' && deliveryFee > 0) {
            if (deliveryRow) deliveryRow.style.display = 'flex';
            const el_delivery = document.getElementById('ctc_display_delivery');
            if (el_delivery) el_delivery.textContent = '₱' + deliveryFee.toLocaleString('en-PH', {minimumFractionDigits:2});
            total += deliveryFee;
        } else {
            if (deliveryRow) deliveryRow.style.display = 'none';
        }
        
        // Additional items (Free)
        const selectedItem = document.getElementById('ctc_additional_item')?.value;
        const additionalRow = document.getElementById('ctc_additional_item_row');
        
        console.log('Additional item calculation (free):', {selectedItem});
        
        if (selectedItem) {
            if (additionalRow) additionalRow.style.display = 'flex';
            const displayElement = document.getElementById('ctc_display_additional_item');
            if (displayElement) displayElement.textContent = 'Free';
            // No price added to total since it's free
            console.log('Added free additional item:', selectedItem);
        } else {
            if (additionalRow) additionalRow.style.display = 'none';
        }
        
        // Labor cost
        const laborCost = parseFloat(document.getElementById('ctc_labor_cost')?.value) || 0;
        const laborRow = document.getElementById('ctc_labor_cost_row');
        
        if (laborCost > 0) {
            laborRow.style.display = 'flex';
            document.getElementById('ctc_display_labor_cost').textContent = '₱' + laborCost.toLocaleString('en-PH', {minimumFractionDigits:2});
            total += laborCost;
        } else {
            laborRow.style.display = 'none';
        }
        
        // Update total
        const totalDisplay = document.getElementById('ctc_display_total');
        if (totalDisplay) {
            totalDisplay.textContent = '₱' + total.toLocaleString('en-PH', {minimumFractionDigits:2});
            console.log('Final total updated to:', total);
        }
        
        // Update payment calculation
        updatePaymentCalculation();
    } catch (e) {
        console.error('Error calculating total:', e);
    }
}

function filterListings(status, btn) {
    document.querySelectorAll('.mpm-tab').forEach(t => t.classList.remove('active'));
    btn.classList.add('active');
    document.querySelectorAll('.mpm-card').forEach(card => {
        card.style.display = (status === 'all' || card.dataset.status === status) ? '' : 'none';
    });
}

// Test function to debug modal
function testModal() {
    console.log('Testing modal...');
    const modal = document.getElementById('computeModalOverlay');
    console.log('Modal element:', modal);
    if (modal) {
        modal.classList.add('open');
        console.log('Modal should be visible now');
    } else {
        console.error('Modal not found!');
    }
}

function updatePaymentCalculation() {
    try {
        const totalCostText = document.getElementById('ctc_display_total')?.textContent || '₱0.00';
        const totalCost = parseFloat(totalCostText.replace(/[₱,]/g, '')) || 0;
        
        const paymentType = document.querySelector('input[name="payment_type"]:checked')?.value || 'full_payment';
        const downPaymentSection = document.getElementById('ctc_down_payment_section');
        const downPaymentInput = document.getElementById('ctc_down_payment_amount');
        const remainingBalanceSpan = document.getElementById('ctc_remaining_balance');
        
        const paymentLabel = document.getElementById('ctc_payment_label');
        const paymentAmount = document.getElementById('ctc_payment_amount');
        const balanceRow = document.getElementById('ctc_balance_row');
        const balanceAmount = document.getElementById('ctc_balance_amount');
        
        if (paymentType === 'down_payment') {
            // Show down payment section
            if (downPaymentSection) downPaymentSection.style.display = 'block';
            
            // Calculate 50% down payment
            const downPayment = totalCost * 0.5;
            const remainingBalance = totalCost - downPayment;
            
            if (downPaymentInput) downPaymentInput.value = downPayment.toFixed(2);
            if (remainingBalanceSpan) remainingBalanceSpan.textContent = '₱' + remainingBalance.toLocaleString('en-PH', {minimumFractionDigits:2});
            
            // Update payment breakdown
            if (paymentLabel) paymentLabel.textContent = 'Down Payment (50%):';
            if (paymentAmount) paymentAmount.textContent = '₱' + downPayment.toLocaleString('en-PH', {minimumFractionDigits:2});
            if (balanceRow) balanceRow.style.display = 'flex';
            if (balanceAmount) balanceAmount.textContent = '₱' + remainingBalance.toLocaleString('en-PH', {minimumFractionDigits:2});
            
        } else {
            // Hide down payment section
            if (downPaymentSection) downPaymentSection.style.display = 'none';
            
            // Update payment breakdown for full payment
            if (paymentLabel) paymentLabel.textContent = 'Full Payment:';
            if (paymentAmount) paymentAmount.textContent = '₱' + totalCost.toLocaleString('en-PH', {minimumFractionDigits:2});
            if (balanceRow) balanceRow.style.display = 'none';
        }
        
        console.log('Payment calculation updated:', {paymentType, totalCost});
    } catch (e) {
        console.error('Error updating payment calculation:', e);
    }
}
</script>

<script>
// Sidebar toggle for mobile
document.getElementById('sidebarToggle').addEventListener('click', function() {
    document.getElementById('dashboardSidebar').classList.toggle('active');
});
</script>

<!-- ====================================================
     ADD / EDIT LECHON MODAL
===================================================== -->
<div class="lch-overlay" id="lchModalOverlay" onclick="if(event.target===this)closeLchModal()">
  <div class="lch-modal">
    <div class="lch-modal-header">
      <h3 id="lchModalTitle">Add Lechon</h3>
      <button class="lch-modal-close" onclick="closeLchModal()">&#x2715;</button>
    </div>

    <div class="lch-modal-body">
      <!-- Step indicators -->
      <div class="lch-step-indicator" id="lchStepIndicator">
        <div class="lch-step-dot active" id="stepDot1">1 Listing Type</div>
        <div class="lch-step-dot" id="stepDot2">2 Details</div>
        <div class="lch-step-dot" id="stepDot3">3 Review</div>
      </div>

      <form id="lchForm" method="POST" action="/livestock-owner/add-lechon" enctype="multipart/form-data">
        <input type="hidden" name="listing_id" id="lchListingId">

        <!-- STEP 1: Listing type -->
        <div class="lch-step active" id="lchStep1">
          <p style="font-size:.9rem;font-weight:700;color:#333;margin:0 0 4px;">What are you posting?</p>
          <p style="font-size:.78rem;color:#888;margin:0 0 14px;">Choose the type of lechon listing you want to create.</p>
          <div class="lch-type-cards">
            <label class="lch-type-card" id="typeCardWhole">
              <input type="radio" name="listing_type" value="WHOLE_LECHON" onchange="onTypeChange()">
              <span class="lch-type-icon" style="font-size:1.4rem;color:#c0392b;">&#9658;</span>
              <div class="lch-type-card-body">
                <div class="lch-type-title">Whole Lechon</div>
                <div class="lch-type-desc">For whole cooked lechon sold as one whole unit.</div>
              </div>
            </label>
            <label class="lch-type-card" id="typeCardPromo">
              <input type="radio" name="listing_type" value="PROMO_PACKAGE" onchange="onTypeChange()">
              <span class="lch-type-icon" style="font-size:1.4rem;color:#c0392b;">&#9658;</span>
              <div class="lch-type-card-body">
                <div class="lch-type-title">Lechon Promo / Package</div>
                <div class="lch-type-desc">Whole w/ packages, belly bundles, weekday combos, event promos, etc.</div>
              </div>
            </label>
          </div>
        </div>

        <!-- STEP 2: Details -->
        <div class="lch-step" id="lchStep2">
          <!-- Category (promo only) -->
          <div class="lch-field" id="catFieldWrap" style="display:none;">
            <label>Promo Category <span class="req">*</span></label>
            <select id="lchCategorySelect" onchange="onCategoryChange()">
              <option value="">&#8212; Select category &#8212;</option>
              <option value="WHOLE_PACKAGES">Whole Lechon w/ Packages</option>
              <option value="BELLY_BUNDLES">Lechon Belly Bundles</option>
              <option value="WEEKDAY_COMBOS">Weekday / Daily Combos</option>
              <option value="EVENT_CATERING">Event and Catering Tier Promos</option>
              <option value="OTHER">Other</option>
            </select>
          </div>
          <!-- Custom name for OTHER category -->
          <div class="lch-field" id="otherNameWrap" style="display:none;">
            <label>Specify Promo Name <span class="req">*</span></label>
            <input type="text" name="other_promo_name" id="lchOtherName"
                   placeholder="e.g. Lechon sa Bangko Package"
                   oninput="document.getElementById('lchName').value = this.value">
          </div>
          <input type="hidden" name="category" id="lchCategoryHidden">

          <!-- Name -->
          <div class="lch-field">
            <label id="lchNameLabel">Lechon Name <span class="req">*</span></label>
            <input type="text" name="name" id="lchName" placeholder="e.g. Whole Lechon Special" required>
          </div>

          <!-- Weight -->
          <div class="lch-field" id="weightFieldWrap">
            <label>Weight (kg)</label>
            <input type="number" name="weight_kg" id="lchWeight" min="0.1" step="0.1" placeholder="e.g. 25">
          </div>

          <!-- Portion size -->
          <div class="lch-field" id="portionFieldWrap" style="display:none;">
            <label>Portion / Serving Size</label>
            <input type="text" name="portion_size" id="lchPortion" placeholder="e.g. 1/4 Lechon, 500g">
          </div>

          <!-- Serving capacity -->
          <div class="lch-field">
            <label>Good For (serving capacity)</label>
            <input type="text" name="serving_capacity" id="lchServing" placeholder="e.g. 50-60 pax">
          </div>

          <!-- Price + Qty -->
          <div class="lch-field-row">
            <div class="lch-field">
              <label>Price (PHP) <span class="req">*</span></label>
              <input type="number" name="price" id="lchPrice" min="1" step="0.01" placeholder="e.g. 15000" required>
            </div>
            <div class="lch-field">
              <label>Available Quantity <span class="req">*</span></label>
              <input type="number" name="available_quantity" id="lchQty" min="1" step="1" value="1" required>
            </div>
          </div>

          <!-- Date -->
          <div class="lch-field">
            <label>Available Date <span class="req">*</span></label>
            <input type="date" name="available_date" id="lchDate" required>
          </div>

          <!-- Description -->
          <div class="lch-field">
            <label>Description</label>
            <textarea name="description" id="lchDesc" placeholder="Describe your lechon product..."></textarea>
          </div>

          <!-- Photo -->
          <div class="lch-field">
            <label>Product Photo</label>
            <input type="file" name="photo" id="lchPhoto" accept="image/*" onchange="previewLchPhoto(this)">
            <img id="lchPhotoPreview" class="lch-photo-preview" alt="Preview">
          </div>

          <!-- Status -->
          <div class="lch-field">
            <label>Status <span class="req">*</span></label>
            <select name="status" id="lchStatus" required>
              <option value="active">Active</option>
              <option value="inactive">Inactive</option>
            </select>
          </div>
        </div><!-- /lchStep2 -->

        <!-- STEP 3: Review -->
        <div class="lch-step" id="lchStep3">
          <p style="font-size:.88rem;font-weight:700;color:#333;margin:0 0 12px;">Review your listing</p>
          <div id="lchReviewContent"
               style="background:#f8f8f8;border-radius:8px;padding:14px;font-size:.83rem;line-height:1.9;color:#444;">
          </div>
        </div>

      </form>
    </div><!-- /lch-modal-body -->

    <div class="lch-modal-footer">
      <button class="lch-btn-cancel" id="lchBtnBack" onclick="lchBack()" style="display:none;">&laquo; Back</button>
      <button class="lch-btn-cancel" onclick="closeLchModal()">Cancel</button>
      <button class="lch-btn-next" id="lchBtnNext" onclick="lchNext()">Next &raquo;</button>
      <button class="lch-btn-submit" id="lchBtnSubmit" style="display:none;" onclick="lchSubmit()">Save Listing</button>
    </div>
  </div>
</div>

<script>
/* ===================================================
   MARKET TAB SWITCHER
=================================================== */
function switchMarketTab(tab) {
    // Update panel visibility
    document.getElementById('panel-pig').classList.toggle('active', tab === 'pig');
    document.getElementById('panel-lechon').classList.toggle('active', tab === 'lechon');
    // Update button states
    document.getElementById('tab-btn-pig').classList.toggle('active', tab === 'pig');
    document.getElementById('tab-btn-lechon').classList.toggle('active', tab === 'lechon');
    // Update URL without reload
    const url = new URL(window.location.href);
    url.searchParams.set('tab', tab);
    window.history.replaceState({}, '', url.toString());
}

/* ===================================================
   PIG LIVESTOCK FILTER (existing — unchanged)
=================================================== */
function filterListings(status, btn) {
    document.querySelectorAll('#panel-pig .mpm-tab').forEach(t => t.classList.remove('active'));
    btn.classList.add('active');
    document.querySelectorAll('#panel-pig .mpm-card').forEach(card => {
        card.style.display = (status === 'all' || card.dataset.status === status) ? '' : 'none';
    });
}

/* ===================================================
   LECHON FILTER
=================================================== */
function filterLechon(key, btn) {
    document.querySelectorAll('#lechon-filter-tabs .mpm-tab').forEach(t => t.classList.remove('active'));
    btn.classList.add('active');
    document.querySelectorAll('#lechonGrid .mpm-card').forEach(card => {
        if (key === 'all') {
            card.style.display = '';
        } else if (key === 'inactive') {
            card.style.display = card.dataset.lchStatus === 'inactive' ? '' : 'none';
        } else {
            card.style.display = card.dataset.lchFilter === key ? '' : 'none';
        }
    });
}

/* ===================================================
   LECHON CARD ACTION MENU
=================================================== */
function toggleLchMenu(btn, e) {
    e.stopPropagation();
    const dd = btn.nextElementSibling;
    const isOpen = dd.classList.contains('open');
    // Close all
    document.querySelectorAll('.lch-card-dropdown.open').forEach(d => d.classList.remove('open'));
    if (!isOpen) dd.classList.add('open');
}
document.addEventListener('click', function() {
    document.querySelectorAll('.lch-card-dropdown.open').forEach(d => d.classList.remove('open'));
});

/* ===================================================
   ADD LECHON MODAL
=================================================== */
let lchCurrentStep = 1;
let lchIsEdit = false;

function openAddLechonModal() {
    lchIsEdit = false;
    lchCurrentStep = 1;
    document.getElementById('lchModalTitle').textContent = 'Add Lechon';
    document.getElementById('lchForm').action = '/livestock-owner/add-lechon';
    document.getElementById('lchListingId').value = '';
    // Reset form fields
    document.getElementById('lchForm').reset();
    document.getElementById('lchPhotoPreview').style.display = 'none';
    document.getElementById('lchCategoryHidden').value = '';
    // Set today as default date
    document.getElementById('lchDate').value = new Date().toISOString().split('T')[0];
    // Reset type cards
    document.querySelectorAll('.lch-type-card').forEach(c => c.classList.remove('selected'));
    renderLchStep();
    document.getElementById('lchModalOverlay').classList.add('open');
}

function openEditLechonModal(data) {
    lchIsEdit = true;
    lchCurrentStep = 2; // Skip to details for edit
    document.getElementById('lchModalTitle').textContent = 'Edit Lechon';
    document.getElementById('lchForm').action = '/livestock-owner/edit-lechon';
    document.getElementById('lchListingId').value = data.id;

    // Set listing type
    const radios = document.querySelectorAll('input[name="listing_type"]');
    radios.forEach(r => {
        r.checked = r.value === data.listing_type;
        r.closest('.lch-type-card').classList.toggle('selected', r.value === data.listing_type);
    });

    // Set category
    document.getElementById('lchCategoryHidden').value = data.category;
    document.getElementById('lchCategorySelect').value = data.category;

    // Populate fields
    document.getElementById('lchName').value = data.name || '';
    document.getElementById('lchWeight').value = data.weight_kg || '';
    document.getElementById('lchPortion').value = data.portion_size || '';
    document.getElementById('lchServing').value = data.serving_capacity || '';
    document.getElementById('lchPrice').value = data.price || '';
    document.getElementById('lchQty').value = data.available_quantity || 1;
    document.getElementById('lchDate').value = data.available_date || '';
    document.getElementById('lchDesc').value = data.description || '';
    document.getElementById('lchStatus').value = data.status || 'active';
    document.getElementById('lchPhotoPreview').style.display = 'none';

    updateFieldsForType(data.listing_type);
    if (data.listing_type === 'PROMO_PACKAGE') {
        updateFieldsForCategory(data.category);
        // If OTHER, populate the custom name field
        if (data.category === 'OTHER' && data.other_promo_name) {
            document.getElementById('lchOtherName').value = data.other_promo_name;
        }
    }

    // Hide step indicators for edit (not needed)
    document.getElementById('lchStepIndicator').style.display = 'none';
    document.getElementById('lchStep1').classList.remove('active');
    document.getElementById('lchStep2').classList.add('active');
    document.getElementById('lchStep3').classList.remove('active');
    document.getElementById('lchBtnBack').style.display = 'none';
    document.getElementById('lchBtnNext').style.display = 'none';
    document.getElementById('lchBtnSubmit').style.display = 'inline-block';

    document.getElementById('lchModalOverlay').classList.add('open');
}

function closeLchModal() {
    document.getElementById('lchModalOverlay').classList.remove('open');
    document.getElementById('lchStepIndicator').style.display = 'flex';
}

/* ===================================================
   STEP NAVIGATION
=================================================== */
function renderLchStep() {
    // Steps
    ['lchStep1','lchStep2','lchStep3'].forEach((id, i) => {
        document.getElementById(id).classList.toggle('active', i + 1 === lchCurrentStep);
    });
    // Step dots
    ['stepDot1','stepDot2','stepDot3'].forEach((id, i) => {
        const dot = document.getElementById(id);
        dot.classList.remove('active','done');
        if (i + 1 < lchCurrentStep) dot.classList.add('done');
        if (i + 1 === lchCurrentStep) dot.classList.add('active');
    });
    // Buttons
    const btnBack = document.getElementById('lchBtnBack');
    const btnNext = document.getElementById('lchBtnNext');
    const btnSubmit = document.getElementById('lchBtnSubmit');
    btnBack.style.display = lchCurrentStep > 1 ? 'inline-block' : 'none';
    btnNext.style.display = lchCurrentStep < 3 ? 'inline-block' : 'none';
    btnSubmit.style.display = lchCurrentStep === 3 ? 'inline-block' : 'none';

    if (lchCurrentStep === 3) buildReview();
}

function lchNext() {
    if (lchCurrentStep === 1) {
        const type = document.querySelector('input[name="listing_type"]:checked')?.value;
        if (!type) { alert('Please select a listing type.'); return; }
        updateFieldsForType(type);
        // For WHOLE_LECHON set hidden category too
        if (type === 'WHOLE_LECHON') {
            document.getElementById('lchCategoryHidden').value = 'WHOLE_LECHON';
        }
    }
    if (lchCurrentStep === 2) {
        if (!validateStep2()) return;
    }
    if (lchCurrentStep < 3) {
        lchCurrentStep++;
        renderLchStep();
    }
}

function lchBack() {
    if (lchCurrentStep > 1) {
        lchCurrentStep--;
        renderLchStep();
    }
}

function lchSubmit() {
    document.getElementById('lchForm').submit();
}

/* ===================================================
   FIELD VISIBILITY BY TYPE
=================================================== */
function onTypeChange() {
    const type = document.querySelector('input[name="listing_type"]:checked')?.value;
    document.querySelectorAll('.lch-type-card').forEach(c => {
        c.classList.toggle('selected', c.querySelector('input').value === type);
    });
    updateFieldsForType(type);
}

function updateFieldsForType(type) {
    const catWrap    = document.getElementById('catFieldWrap');
    const weightWrap = document.getElementById('weightFieldWrap');
    const portionWrap= document.getElementById('portionFieldWrap');
    const nameLabel  = document.getElementById('lchNameLabel');

    if (type === 'WHOLE_LECHON') {
        catWrap.style.display     = 'none';
        weightWrap.style.display  = 'block';
        portionWrap.style.display = 'none';
        nameLabel.innerHTML       = 'Lechon Name <span class="req">*</span>';
    } else {
        catWrap.style.display     = 'block';
        weightWrap.style.display  = 'none';
        portionWrap.style.display = 'none';
        nameLabel.innerHTML       = 'Product / Promo Name <span class="req">*</span>';
    }
}

function onCategoryChange() {
    const cat = document.getElementById('lchCategorySelect').value;
    document.getElementById('lchCategoryHidden').value = cat;
    updateFieldsForCategory(cat);
}

function updateFieldsForCategory(cat) {
    const portionWrap  = document.getElementById('portionFieldWrap');
    const otherWrap    = document.getElementById('otherNameWrap');
    const otherInput   = document.getElementById('lchOtherName');
    // Show portion/serving field for package-style categories
    portionWrap.style.display = ['WHOLE_PACKAGES','BELLY_BUNDLES','WEEKDAY_COMBOS','EVENT_CATERING'].includes(cat) ? 'block' : 'none';
    // Show custom name field only for OTHER; when shown, clear the main name field
    if (cat === 'OTHER') {
        otherWrap.style.display = 'block';
        if (otherInput) otherInput.required = true;
        // When OTHER is picked, hide the main name field and drive it from the other input
        document.getElementById('lchName').placeholder = 'Auto-filled from promo name below';
    } else {
        otherWrap.style.display = 'none';
        if (otherInput) { otherInput.required = false; otherInput.value = ''; }
        document.getElementById('lchName').placeholder = 'e.g. Whole Lechon Special';
    }
}

/* ===================================================
   VALIDATION
=================================================== */
function validateStep2() {
    const name  = document.getElementById('lchName').value.trim();
    const price = parseFloat(document.getElementById('lchPrice').value);
    const qty   = parseInt(document.getElementById('lchQty').value);
    const date  = document.getElementById('lchDate').value;
    const type  = document.querySelector('input[name="listing_type"]:checked')?.value;
    const cat   = document.getElementById('lchCategoryHidden').value;

    // If OTHER category, copy the custom promo name into the main name field
    if (type === 'PROMO_PACKAGE' && cat === 'OTHER') {
        const otherName = document.getElementById('lchOtherName').value.trim();
        if (!otherName) { alert('Please specify the promo name.'); return false; }
        document.getElementById('lchName').value = otherName;
    }
    if (!name)            { alert('Listing name is required.'); return false; }
    if (isNaN(price) || price <= 0) { alert('Please enter a valid positive price.'); return false; }
    if (isNaN(qty)   || qty < 1)    { alert('Available quantity must be at least 1.'); return false; }
    if (!date)            { alert('Available date is required.'); return false; }
    if (type === 'PROMO_PACKAGE' && !cat) { alert('Please select a promo category.'); return false; }
    return true;
}

/* ===================================================
   REVIEW BUILDER
=================================================== */
function buildReview() {
    const type  = document.querySelector('input[name="listing_type"]:checked')?.value || '';
    const cat   = document.getElementById('lchCategoryHidden').value;
    const name  = document.getElementById('lchName').value;
    const price = parseFloat(document.getElementById('lchPrice').value) || 0;
    const qty   = document.getElementById('lchQty').value;
    const date  = document.getElementById('lchDate').value;
    const weight= document.getElementById('lchWeight').value;
    const portion= document.getElementById('lchPortion').value;
    const serving= document.getElementById('lchServing').value;
    const desc  = document.getElementById('lchDesc').value;
    const status= document.getElementById('lchStatus').value;

    const catLabels = {
        WHOLE_LECHON:'Whole Lechon',
        WHOLE_PACKAGES:'Whole Lechon w/ Packages',
        BELLY_BUNDLES:'Lechon Belly Bundles',
        WEEKDAY_COMBOS:'Weekday / Daily Combos',
        EVENT_CATERING:'Event & Catering Tier Promos',
        OTHER:'Other'
    };

    let html = '<table style="width:100%;border-collapse:collapse;">';
    const row = (label, val) => val
        ? `<tr><td style="padding:3px 8px 3px 0;font-weight:700;white-space:nowrap;color:#555;">${label}</td><td style="padding:3px 0;">${val}</td></tr>`
        : '';

    html += row('Type', type === 'WHOLE_LECHON' ? 'Whole Lechon' : 'Promo / Package');
    if (type === 'PROMO_PACKAGE') html += row('Category', catLabels[cat] || cat);
    html += row('Name', name);
    if (weight) html += row('Weight', weight + ' kg');
    if (portion) html += row('Portion', portion);
    if (serving) html += row('Good For', serving);
    html += row('Price', 'PHP ' + parseFloat(price).toLocaleString('en-PH',{minimumFractionDigits:2}));
    html += row('Available Date', date);
    html += row('Quantity', qty);
    if (desc) html += row('Description', desc);
    html += row('Status', status.charAt(0).toUpperCase() + status.slice(1));
    html += '</table>';

    document.getElementById('lchReviewContent').innerHTML = html;
}

/* ===================================================
   PHOTO PREVIEW
=================================================== */
function previewLchPhoto(input) {
    const preview = document.getElementById('lchPhotoPreview');
    if (input.files && input.files[0]) {
        const reader = new FileReader();
        reader.onload = e => {
            preview.src = e.target.result;
            preview.style.display = 'block';
        };
        reader.readAsDataURL(input.files[0]);
    }
}
</script>
</body>
</html>

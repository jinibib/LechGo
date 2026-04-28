<?php
$currentPage = 'my-pig-market';
$user = $_SESSION['user'] ?? null;
if (!$user) { header('Location: /LechGo_Final/public/login'); exit; }

$conn = $GLOBALS['conn'];
$stmt = $conn->prepare("SELECT id FROM livestock_owners WHERE user_id = ?");
$stmt->bind_param('i', $user['id']);
$stmt->execute();
$owner = $stmt->get_result()->fetch_assoc();
$stmt->close();
if (!$owner) { header('Location: /LechGo_Final/public/home'); exit; }

$listings = [];
$stmt = $conn->prepare(
    "SELECT pml.*, pd.photo_url, pd.health_status, pd.age_months
     FROM hogs_market pml
     LEFT JOIN pig_details pd ON pd.id = pml.pig_detail_id
     WHERE pml.livestock_owner_id = ?
     ORDER BY FIELD(pml.status,'reserved','active','sold','removed'), pml.created_at DESC"
);
if ($stmt) {
    $stmt->bind_param('i', $owner['id']);
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
    <title>My Pig Market - LechGO</title>
    <link rel="stylesheet" href="/LechGo_Final/public/styles.css">
    <style>
        .mpm-wrap { padding: .75rem 1rem; }
        .mpm-header { display:flex; align-items:center; justify-content:space-between; margin-bottom:.9rem; }
        .mpm-header h1 { font-size:1.2rem; margin:0; color:#333; }
        .mpm-header p  { margin:2px 0 0; color:#888; font-size:.78rem; }

        .mpm-grid { display:grid; grid-template-columns:repeat(auto-fill,minmax(230px,1fr)); gap:14px; }

        .mpm-card { background:#fff; border-radius:10px; box-shadow:0 2px 8px rgba(0,0,0,.08); overflow:hidden; display:flex; flex-direction:column; }
        .mpm-card.reserved { box-shadow:0 0 0 2.5px #c0392b, 0 2px 12px rgba(192,57,43,.15); }
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
        .mpm-status.reserved { background:#fde8e8; color:#c0392b; }
        .mpm-status.sold     { background:#e8f0fe; color:#1a56db; }
        .mpm-status.removed  { background:#f0f0f0; color:#999; }

        /* Reserved customer info box */
        .mpm-reserved-box {
            margin:8px 12px 0; padding:8px 10px;
            background:#fdf0f0; border-left:3px solid #c0392b;
            border-radius:0 6px 6px 0;
        }
        .mpm-reserved-box .rb-label { font-size:.65rem; font-weight:700; color:#c0392b; text-transform:uppercase; margin-bottom:3px; }
        .mpm-reserved-box .rb-name  { font-size:.82rem; font-weight:700; color:#333; }
        .mpm-reserved-box .rb-msg   { font-size:.72rem; color:#666; margin-top:3px; font-style:italic; line-height:1.4; }
        .mpm-reserved-box .rb-time  { font-size:.65rem; color:#aaa; margin-top:3px; }

        .mpm-card-footer { padding:8px 12px; border-top:1px solid #f5f5f5; display:flex; gap:6px; }
        .mpm-btn-remove { flex:1; background:#fde8e8; color:#c0392b; border:none; border-radius:6px; padding:6px; font-size:.72rem; font-weight:700; cursor:pointer; }
        .mpm-btn-remove:hover { background:#f5c6c6; }
        .mpm-btn-sold { flex:1; background:#2d7a2d; color:#fff; border:none; border-radius:6px; padding:6px; font-size:.72rem; font-weight:700; cursor:pointer; }
        .mpm-btn-sold:hover { background:#236023; }

        .mpm-empty { text-align:center; padding:3rem; color:#bbb; font-size:.9rem; background:#fff; border-radius:10px; box-shadow:0 2px 6px rgba(0,0,0,.07); }
        .mpm-tabs { display:flex; gap:6px; margin-bottom:14px; flex-wrap:wrap; }
        .mpm-tab { padding:5px 16px; border-radius:20px; font-size:.78rem; font-weight:700; cursor:pointer; border:1.5px solid #e0e0e0; background:#fff; color:#888; transition:all .15s; }
        .mpm-tab.active { background:#c0392b; color:#fff; border-color:#c0392b; }

        .mpm-reserved-count {
            background:#fde8e8; color:#c0392b; border:1.5px solid #f5c6c6;
            border-radius:8px; padding:8px 14px; margin-bottom:14px;
            font-size:.82rem; font-weight:700; display:flex; align-items:center; gap:8px;
        }
    </style>
</head>
<body>
<div class="dashboard-layout">
    <?php include __DIR__ . '/../layouts/sidebar.php'; ?>
    <main class="dashboard-main">
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

        <?php
        $reservedCount = count(array_filter($listings, fn($l) => $l['status'] === 'reserved'));
        if ($reservedCount > 0):
        ?>
        <div class="mpm-reserved-count">
             <?php echo $reservedCount; ?> pig<?php echo $reservedCount > 1 ? 's' : ''; ?> reserved — review and click "Mark Sold" to confirm.
        </div>
        <?php endif; ?>

        <div class="mpm-tabs">
            <button class="mpm-tab active" onclick="filterListings('all', this)">All</button>
            <button class="mpm-tab" onclick="filterListings('active', this)">Active</button>
            <button class="mpm-tab" onclick="filterListings('reserved', this)">Reserved <?php if($reservedCount): ?><span style="background:#e67e22;color:#fff;border-radius:20px;padding:0 6px;font-size:.65rem;margin-left:3px;"><?php echo $reservedCount; ?></span><?php endif; ?></button>
            <button class="mpm-tab" onclick="filterListings('sold', this)">Sold</button>
            <button class="mpm-tab" onclick="filterListings('removed', this)">Removed</button>
        </div>

        <?php if (empty($listings)): ?>
            <div class="mpm-empty">
                <div style="font-size:2.5rem;margin-bottom:.5rem;"></div>
                No pigs listed yet. Go to <a href="/LechGo_Final/public/livestock-owner/caretaker-pig-inventory" style="color:#c0392b;font-weight:700;">Pig Inventory</a> and click "Post to My Market".
            </div>
        <?php else: ?>
            <div class="mpm-grid" id="listingsGrid">
                <?php foreach ($listings as $l): ?>
                <div class="mpm-card <?php echo $l['status'] === 'reserved' ? 'reserved' : ''; ?>" data-status="<?php echo $l['status']; ?>">
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
                        <span class="mpm-status <?php echo $l['status']; ?>"><?php echo ucfirst($l['status']); ?></span>
                    </div>

                    <?php if ($l['status'] === 'reserved' && !empty($l['reserved_by_name'])): ?>
                    <div class="mpm-reserved-box">
                        <div class="rb-label"> Reserved by</div>
                        <div class="rb-name"> <?php echo htmlspecialchars($l['reserved_by_name']); ?></div>
                        <?php if (!empty($l['inquiry_message'])): ?>
                        <div class="rb-msg">"<?php echo htmlspecialchars($l['inquiry_message']); ?>"</div>
                        <?php endif; ?>
                        <?php if (!empty($l['reserved_at'])): ?>
                        <div class="rb-time"><?php echo date('M d, Y h:i A', strtotime($l['reserved_at'])); ?></div>
                        <?php endif; ?>
                    </div>
                    <?php endif; ?>

                    <?php if (in_array($l['status'], ['active', 'reserved'])): ?>
                    <div class="mpm-card-footer">
                        <form method="POST" action="/LechGo_Final/public/livestock-owner/update-pig-listing" style="flex:1;display:flex;gap:6px;">
                            <input type="hidden" name="listing_id" value="<?php echo $l['id']; ?>">
                            <?php if ($l['status'] === 'reserved'): ?>
                                <button type="submit" name="action" value="sold" class="mpm-btn-sold" style="flex:2;"> Mark Sold</button>
                                <button type="submit" name="action" value="active"
                                    class="mpm-btn-remove"
                                    onclick="return confirm('Cancel this reservation?')">↩ Cancel</button>
                            <?php else: ?>
                                <button type="submit" name="action" value="sold" class="mpm-btn-sold">Mark Sold</button>
                                <button type="submit" name="action" value="removed" class="mpm-btn-remove">Remove</button>
                            <?php endif; ?>
                        </form>
                    </div>
                    <?php endif; ?>
                </div>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>

    </div>
    </main>
</div>
<script>
function filterListings(status, btn) {
    document.querySelectorAll('.mpm-tab').forEach(t => t.classList.remove('active'));
    btn.classList.add('active');
    document.querySelectorAll('.mpm-card').forEach(card => {
        card.style.display = (status === 'all' || card.dataset.status === status) ? '' : 'none';
    });
}
</script>
</body>
</html>

<?php
$currentPage = 'fd-market';

$sessionMiddleware = new Session();
$user = $sessionMiddleware->getUser();

if (!$sessionMiddleware->isAuthenticated() || $user['role'] !== 'feed_distributor') {
    header('Location: /LechGo_Final/public/login');
    exit;
}

// Get distributor record
$query = "SELECT id, business_name FROM feed_distributors WHERE user_id = ?";
$stmt = $GLOBALS['conn']->prepare($query);
if (!$stmt) die('Database error: ' . $GLOBALS['conn']->error);
$stmt->bind_param('i', $user['id']);
$stmt->execute();
$distributor = $stmt->get_result()->fetch_assoc();
$stmt->close();

if (!$distributor) {
    $_SESSION['error'] = 'Distributor profile not found';
    header('Location: /LechGo_Final/public/dashboard');
    exit;
}

$distributor_id = $distributor['id'];

// Get active products listed in market
$query = "SELECT id, product_name, feed_type, description, unit_price, quantity_available_kg, image_url, is_active, created_at
          FROM feed_distributor_products
          WHERE distributor_id = ? AND is_active = 1
          ORDER BY created_at DESC";
$stmt = $GLOBALS['conn']->prepare($query);
if (!$stmt) die('Database error: ' . $GLOBALS['conn']->error);
$stmt->bind_param('i', $distributor_id);
$stmt->execute();
$market_products = $stmt->get_result()->fetch_all(MYSQLI_ASSOC) ?? [];
$stmt->close();

// Get all products (for the listing toggle)
$query = "SELECT id, product_name, feed_type, unit_price, quantity_available_kg, is_active
          FROM feed_distributor_products WHERE distributor_id = ? ORDER BY product_name ASC";
$stmt = $GLOBALS['conn']->prepare($query);
if (!$stmt) die('Database error: ' . $GLOBALS['conn']->error);
$stmt->bind_param('i', $distributor_id);
$stmt->execute();
$all_products = $stmt->get_result()->fetch_all(MYSQLI_ASSOC) ?? [];
$stmt->close();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Feed Market - LechGO</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.8/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="/LechGo_Final/public/styles.css">
    <style>
        .market-card {
            background: white;
            border: 1px solid #e0e0e0;
            border-radius: 8px;
            padding: 16px;
            margin-bottom: 12px;
            display: flex;
            align-items: center;
            gap: 16px;
            box-shadow: 0 1px 3px rgba(0,0,0,0.07);
        }
        .market-card img { width: 70px; height: 70px; object-fit: cover; border-radius: 6px; }
        .market-card-placeholder { width: 70px; height: 70px; background: #f0f0f0; border-radius: 6px; display: flex; align-items: center; justify-content: center; font-size: 28px; }
        .market-card-info { flex: 1; }
        .market-card-name { font-weight: 700; font-size: 15px; color: #2c3e50; }
        .market-card-type { color: #888; font-size: 13px; margin-bottom: 4px; }
        .market-card-price { font-size: 16px; font-weight: 700; color: #27ae60; }
        .market-card-stock { font-size: 13px; color: #666; }
        .toggle-table { width: 100%; border-collapse: collapse; }
        .toggle-table th, .toggle-table td { padding: 10px 14px; border-bottom: 1px solid #eee; text-align: left; font-size: 14px; }
        .toggle-table th { background: #f8f8f8; font-weight: 600; color: #555; }
        .toggle-switch { position: relative; display: inline-block; width: 44px; height: 24px; }
        .toggle-switch input { opacity: 0; width: 0; height: 0; }
        .slider { position: absolute; cursor: pointer; top: 0; left: 0; right: 0; bottom: 0; background: #ccc; border-radius: 24px; transition: .3s; }
        .slider:before { position: absolute; content: ""; height: 18px; width: 18px; left: 3px; bottom: 3px; background: white; border-radius: 50%; transition: .3s; }
        input:checked + .slider { background: #27ae60; }
        input:checked + .slider:before { transform: translateX(20px); }
    </style>
</head>
<body>
<div class="dashboard-layout">
    <?php include __DIR__ . '/../layouts/sidebar.php'; ?>
    <main class="dashboard-main">
    <div style="max-width: 100%; margin: 0; padding: var(--spacing-md) 0;">

        <?php if (isset($_SESSION['success'])): ?>
            <div class="alert alert-success show">✓ <?php echo htmlspecialchars($_SESSION['success']); unset($_SESSION['success']); ?></div>
        <?php endif; ?>
        <?php if (isset($_SESSION['error'])): ?>
            <div class="alert alert-error show">✗ <?php echo htmlspecialchars($_SESSION['error']); unset($_SESSION['error']); ?></div>
        <?php endif; ?>

        <div class="inventory-header">
            <h1>Feed Market</h1>
            <p>Manage which products are listed in the market — <?php echo htmlspecialchars($distributor['business_name']); ?></p>
        </div>

        <!-- Toggle listing status -->
        <div style="background:white;border:1px solid #e0e0e0;border-radius:8px;padding:20px;margin-bottom:30px;">
            <h2 style="margin:0 0 16px 0;font-size:16px;">Manage Market Listings</h2>
            <?php if (empty($all_products)): ?>
                <p style="color:#999;">No products yet. <a href="/LechGo_Final/public/feed-distributor/product-inventory">Add products first.</a></p>
            <?php else: ?>
                <table class="toggle-table">
                    <thead>
                        <tr>
                            <th>Product</th>
                            <th>Feed Type</th>
                            <th>Price (₱/kg)</th>
                            <th>Stock (kg)</th>
                            <th>Listed in Market</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($all_products as $p): ?>
                        <tr>
                            <td><strong><?php echo htmlspecialchars($p['product_name']); ?></strong></td>
                            <td><?php echo htmlspecialchars($p['feed_type']); ?></td>
                            <td>
                                <div style="display:flex;align-items:center;gap:6px;">
                                    <span class="price-display-<?php echo $p['id']; ?>" style="font-weight:600;">₱<?php echo number_format($p['unit_price'], 2); ?></span>
                                    <input type="number" class="price-input-<?php echo $p['id']; ?>"
                                           value="<?php echo $p['unit_price']; ?>" step="0.01" min="0"
                                           style="display:none;width:90px;padding:4px 8px;border:1.5px solid #e74c3c;border-radius:6px;font-size:13px;">
                                    <button onclick="editPrice(<?php echo $p['id']; ?>)"
                                            class="price-edit-btn-<?php echo $p['id']; ?>"
                                            style="background:none;border:none;cursor:pointer;color:#888;font-size:12px;padding:2px 6px;border-radius:4px;border:1px solid #ddd;">
                                        Edit
                                    </button>
                                    <button onclick="savePrice(<?php echo $p['id']; ?>)"
                                            class="price-save-btn-<?php echo $p['id']; ?>"
                                            style="display:none;background:#27ae60;color:white;border:none;cursor:pointer;font-size:12px;padding:3px 8px;border-radius:4px;">
                                        Save
                                    </button>
                                    <button onclick="cancelPrice(<?php echo $p['id']; ?>, <?php echo $p['unit_price']; ?>)"
                                            class="price-cancel-btn-<?php echo $p['id']; ?>"
                                            style="display:none;background:#95a5a6;color:white;border:none;cursor:pointer;font-size:12px;padding:3px 8px;border-radius:4px;">
                                        Cancel
                                    </button>
                                </div>
                            </td>
                            <td><?php echo number_format($p['quantity_available_kg'], 1); ?></td>
                            <td>
                                <label class="toggle-switch">
                                    <input type="checkbox" <?php echo $p['is_active'] ? 'checked' : ''; ?>
                                           onchange="toggleListing(<?php echo $p['id']; ?>, this.checked)">
                                    <span class="slider"></span>
                                </label>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            <?php endif; ?>
        </div>

        <!-- Active market listings preview -->
        <h2 style="margin-bottom:16px;">Currently Listed Products (<?php echo count($market_products); ?>)</h2>
        <?php if (empty($market_products)): ?>
            <div style="background:white;border:1px solid #e0e0e0;border-radius:8px;padding:40px;text-align:center;color:#999;">
                <div style="font-size:2.5rem;margin-bottom:12px;">🌾</div>
                <p>No products listed in the market yet. Toggle products above to list them.</p>
            </div>
        <?php else: ?>
            <?php foreach ($market_products as $p): ?>
            <div class="market-card">
                <?php if ($p['image_url']): ?>
                    <img src="<?php echo htmlspecialchars($p['image_url']); ?>" alt="<?php echo htmlspecialchars($p['product_name']); ?>">
                <?php else: ?>
                    <div class="market-card-placeholder">📦</div>
                <?php endif; ?>
                <div class="market-card-info">
                    <div class="market-card-name"><?php echo htmlspecialchars($p['product_name']); ?></div>
                    <div class="market-card-type"><?php echo htmlspecialchars($p['feed_type']); ?></div>
                    <?php if ($p['description']): ?>
                        <div style="font-size:13px;color:#777;margin-top:4px;"><?php echo htmlspecialchars($p['description']); ?></div>
                    <?php endif; ?>
                </div>
                <div style="text-align:right;">
                    <div class="market-card-price">₱<?php echo number_format($p['unit_price'], 2); ?>/kg</div>
                    <div class="market-card-stock"><?php echo number_format($p['quantity_available_kg'], 1); ?> kg available</div>
                </div>
            </div>
            <?php endforeach; ?>
        <?php endif; ?>

    </div>
    </main>

    <!-- Logout Modal -->
    <div class="modal" id="logoutModal">
        <div class="modal-content">
            <div class="modal-header"><h2>Confirm Logout</h2><button class="modal-close" id="closeLogoutModal">&times;</button></div>
            <div class="modal-body"><p>Are you sure you want to logout?</p></div>
            <div class="modal-footer">
                <button class="btn btn-secondary" id="cancelLogout">Cancel</button>
                <button class="btn btn-primary" id="confirmLogout">Yes, Logout</button>
            </div>
        </div>
    </div>
</div>

<script src="/LechGo_Final/public/script.js"></script>
<script>
function toggleListing(productId, isActive) {
    fetch('/LechGo_Final/public/feed-distributor/toggle-listing', {
        method: 'POST',
        headers: {'Content-Type': 'application/x-www-form-urlencoded'},
        body: 'product_id=' + productId + '&is_active=' + (isActive ? 1 : 0)
    }).then(r => r.json()).then(data => {
        if (!data.success) {
            alert('Error: ' + data.message);
            location.reload();
        } else {
            location.reload();
        }
    });
}

function editPrice(id) {
    document.querySelector('.price-display-' + id).style.display = 'none';
    document.querySelector('.price-edit-btn-' + id).style.display = 'none';
    document.querySelector('.price-input-' + id).style.display = 'inline-block';
    document.querySelector('.price-save-btn-' + id).style.display = 'inline-block';
    document.querySelector('.price-cancel-btn-' + id).style.display = 'inline-block';
    document.querySelector('.price-input-' + id).focus();
}

function cancelPrice(id, original) {
    document.querySelector('.price-input-' + id).value = original;
    document.querySelector('.price-display-' + id).style.display = 'inline';
    document.querySelector('.price-edit-btn-' + id).style.display = 'inline-block';
    document.querySelector('.price-input-' + id).style.display = 'none';
    document.querySelector('.price-save-btn-' + id).style.display = 'none';
    document.querySelector('.price-cancel-btn-' + id).style.display = 'none';
}

function savePrice(id) {
    const newPrice = parseFloat(document.querySelector('.price-input-' + id).value);
    if (isNaN(newPrice) || newPrice <= 0) {
        alert('Please enter a valid price.');
        return;
    }

    fetch('/LechGo_Final/public/feed-distributor/update-price', {
        method: 'POST',
        headers: {'Content-Type': 'application/x-www-form-urlencoded'},
        body: 'product_id=' + id + '&unit_price=' + newPrice
    }).then(r => r.json()).then(data => {
        if (data.success) {
            const formatted = '₱' + newPrice.toFixed(2);
            document.querySelector('.price-display-' + id).textContent = formatted;
            cancelPrice(id, newPrice);
        } else {
            alert('Error: ' + data.message);
        }
    });
}
</script>
</body>
</html>

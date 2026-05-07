<?php
$currentPage = 'feeds-market';

$sessionMiddleware = new Session();
$user = $sessionMiddleware->getUser();

if (!$sessionMiddleware->isAuthenticated() || $user['role'] !== 'supplier') {
    header('Location: /LechGo_Final/public/login');
    exit;
}

// Get all active products from feed distributors
$query = "SELECT fdp.id, fdp.product_name, fdp.feed_type, fdp.description,
                 fdp.unit_price, fdp.quantity_available_kg, fdp.image_url,
                 fd.id AS distributor_id, fd.business_name, fd.contact_number,
                 u.name AS distributor_user_name,
                 l.street, l.barangay, l.municipality, l.city
          FROM feed_distributor_products fdp
          JOIN feed_distributors fd ON fdp.distributor_id = fd.id
          JOIN users u ON fd.user_id = u.id
          LEFT JOIN locations l ON fd.location_id = l.location_id
          WHERE fdp.is_active = 1 AND fdp.quantity_available_kg > 0
          ORDER BY fd.business_name, fdp.feed_type, fdp.product_name";

$result = $GLOBALS['conn']->query($query);
$products = $result ? $result->fetch_all(MYSQLI_ASSOC) : [];

// Group by distributor for display
$by_distributor = [];
foreach ($products as $p) {
    $by_distributor[$p['distributor_id']]['info'] = [
        'business_name'       => $p['business_name'],
        'distributor_user_name' => $p['distributor_user_name'],
        'contact_number'      => $p['contact_number'],
        'location'            => implode(', ', array_filter([$p['barangay'], $p['municipality'], $p['city']])),
    ];
    $by_distributor[$p['distributor_id']]['products'][] = $p;
}

// Search / filter
$search = trim($_GET['search'] ?? '');
$filter_type = trim($_GET['type'] ?? '');

if ($search || $filter_type) {
    $filtered = [];
    foreach ($products as $p) {
        $match_search = !$search || stripos($p['product_name'], $search) !== false
                                 || stripos($p['feed_type'], $search) !== false
                                 || stripos($p['business_name'], $search) !== false;
        $match_type   = !$filter_type || strtolower($p['feed_type']) === strtolower($filter_type);
        if ($match_search && $match_type) $filtered[] = $p;
    }
    $products = $filtered;
    // Rebuild grouped
    $by_distributor = [];
    foreach ($products as $p) {
        $by_distributor[$p['distributor_id']]['info'] = [
            'business_name'         => $p['business_name'],
            'distributor_user_name' => $p['distributor_user_name'],
            'contact_number'        => $p['contact_number'],
            'location'              => implode(', ', array_filter([$p['barangay'], $p['municipality'], $p['city']])),
        ];
        $by_distributor[$p['distributor_id']]['products'][] = $p;
    }
}

// Unique feed types for filter dropdown
$all_types_result = $GLOBALS['conn']->query("SELECT DISTINCT feed_type FROM feed_distributor_products WHERE is_active = 1 ORDER BY feed_type");
$all_types = $all_types_result ? $all_types_result->fetch_all(MYSQLI_ASSOC) : [];
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Feeds Market - LechGO</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.8/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="/LechGo_Final/public/styles.css">
    <style>
        .market-header {
            margin-bottom: 24px;
        }
        .market-header h1 { margin: 0 0 4px 0; }
        .market-header p { color: #666; margin: 0; }

        .filter-bar {
            display: flex;
            gap: 12px;
            margin-bottom: 28px;
            flex-wrap: wrap;
            align-items: center;
        }
        .filter-bar input[type="text"],
        .filter-bar select {
            padding: 9px 14px;
            border: 1.5px solid #e0e0e0;
            border-radius: 8px;
            font-size: 14px;
            background: white;
            outline: none;
            transition: border-color .2s;
        }
        .filter-bar input[type="text"] { flex: 1; min-width: 200px; }
        .filter-bar input[type="text"]:focus,
        .filter-bar select:focus { border-color: var(--primary-red, #e74c3c); }
        .filter-bar .btn-filter {
            padding: 9px 20px;
            background: var(--primary-red, #e74c3c);
            color: white;
            border: none;
            border-radius: 8px;
            font-size: 14px;
            font-weight: 600;
            cursor: pointer;
        }
        .filter-bar .btn-clear {
            padding: 9px 16px;
            background: #f0f0f0;
            color: #555;
            border: none;
            border-radius: 8px;
            font-size: 14px;
            cursor: pointer;
            text-decoration: none;
        }

        .distributor-section { margin-bottom: 28px; }
        .distributor-header {
            display: flex;
            align-items: center;
            gap: 12px;
            background: white;
            border: 1px solid #e8e8e8;
            border-radius: 10px 10px 0 0;
            padding: 12px 16px;
            border-bottom: 2px solid var(--primary-red, #e74c3c);
        }
        .distributor-avatar {
            width: 38px; height: 38px;
            background: var(--primary-red, #e74c3c);
            color: white;
            border-radius: 50%;
            display: flex; align-items: center; justify-content: center;
            font-size: 16px; font-weight: 800; flex-shrink: 0;
        }
        .distributor-name { font-weight: 700; font-size: 14px; color: #2c3e50; }
        .distributor-meta { font-size: 11px; color: #999; margin-top: 2px; }

        .products-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(200px, 1fr));
            gap: 12px;
            padding: 16px;
            background: #f8f8f8;
            border: 1px solid #e8e8e8;
            border-top: none;
            border-radius: 0 0 12px 12px;
        }
        .product-card {
            background: white;
            border-radius: 10px;
            border: 1px solid #efefef;
            padding: 0;
            display: flex;
            flex-direction: column;
            overflow: hidden;
            box-shadow: 0 1px 5px rgba(0,0,0,.05);
            transition: box-shadow .2s, transform .15s;
        }
        .product-card:hover {
            box-shadow: 0 4px 14px rgba(0,0,0,.09);
            transform: translateY(-2px);
        }
        .product-img {
            width: 100%; height: 120px;
            object-fit: cover;
        }
        .product-img-placeholder {
            width: 100%; height: 120px;
            background: linear-gradient(135deg, #f5f5f5, #ebebeb);
            display: flex; align-items: center; justify-content: center;
            font-size: 36px; color: #ccc;
        }
        .product-body {
            padding: 10px 12px;
            display: flex;
            flex-direction: column;
            gap: 6px;
            flex: 1;
        }
        .product-name { font-weight: 700; font-size: 13px; color: #2c3e50; line-height: 1.3; }
        .product-type {
            display: inline-block;
            background: #fff3e0; color: #e67e22;
            font-size: 10px; font-weight: 700;
            padding: 2px 8px; border-radius: 20px;
            width: fit-content;
        }
        .product-desc { font-size: 11px; color: #999; line-height: 1.4; display: -webkit-box; -webkit-line-clamp: 2; -webkit-box-orient: vertical; overflow: hidden; }
        .product-stats {
            display: flex; gap: 0;
            background: #f9f9f9;
            border-radius: 6px;
            overflow: hidden;
            border: 1px solid #f0f0f0;
        }
        .product-stat {
            flex: 1;
            padding: 5px 8px;
            text-align: center;
        }
        .product-stat + .product-stat { border-left: 1px solid #f0f0f0; }
        .product-stat-label { font-size: 9px; color: #aaa; text-transform: uppercase; letter-spacing: .03em; }
        .product-stat-value { font-size: 13px; font-weight: 700; color: #2c3e50; margin-top: 1px; }
        .product-price { color: #27ae60 !important; }

        .product-order-form {
            padding: 8px 12px 12px;
            border-top: 1px solid #f5f5f5;
            background: #fafafa;
        }
        .qty-row {
            display: grid;
            grid-template-columns: 1fr 1fr 1fr;
            gap: 5px;
            margin-bottom: 7px;
        }
        .qty-col label {
            font-size: 9px; color: #aaa; text-transform: uppercase;
            letter-spacing: .03em; display: block; margin-bottom: 3px; font-weight: 700;
        }
        .qty-col input {
            width: 100%; padding: 5px 4px;
            border: 1.5px solid #e0e0e0; border-radius: 6px;
            font-size: 13px; text-align: center; background: white;
            box-sizing: border-box; transition: border-color .2s;
        }
        .qty-col input:focus { outline: none; border-color: #e74c3c; }
        .qty-footer {
            display: flex; align-items: center; gap: 6px;
        }
        .qty-total-box {
            flex: 1; background: white; border: 1.5px solid #ffe0e0;
            border-radius: 6px; padding: 4px 8px; text-align: center;
        }
        .qty-total-box .kg { font-size: 13px; font-weight: 800; color: #e74c3c; }
        .qty-total-box .price { font-size: 10px; color: #aaa; }
        .btn-add-cart {
            flex-shrink: 0; padding: 7px 12px;
            background: #e74c3c; color: white;
            border: none; border-radius: 6px;
            font-size: 12px; font-weight: 700;
            cursor: pointer; white-space: nowrap;
            transition: background .2s, opacity .2s;
        }
        .btn-add-cart:disabled { opacity: .45; cursor: not-allowed; }
        .btn-add-cart:not(:disabled):hover { background: #c0392b; }

        .empty-market {
            text-align: center; padding: 60px 20px;
            background: white; border-radius: 10px;
            border: 1px solid #e8e8e8; color: #aaa;
        }
        .empty-market-icon { font-size: 3rem; margin-bottom: 14px; }

        .stats-summary {
            display: flex; gap: 16px; margin-bottom: 24px; flex-wrap: wrap;
        }
        .summary-card {
            background: white; border: 1px solid #e8e8e8;
            border-radius: 10px; padding: 16px 24px;
            flex: 1; min-width: 120px; text-align: center;
            box-shadow: 0 2px 6px rgba(0,0,0,.04);
        }
        .summary-value { font-size: 24px; font-weight: 800; color: #2c3e50; }
        .summary-label { font-size: 12px; color: #888; margin-top: 2px; }
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

        <div class="market-header">
            <h1>Feeds Market</h1>
            <p>Browse feed products available from Feed Distributors</p>
        </div>

        <!-- Cart summary bar -->
        <?php
        $fd_cart = $_SESSION['fd_cart'] ?? [];
        $fd_cart_count = count($fd_cart);
        $fd_cart_total = array_sum(array_column($fd_cart, 'subtotal'));
        ?>
        <?php if ($fd_cart_count > 0): ?>
        <div style="background:#fff8f0;border:1.5px solid #f39c12;border-radius:8px;padding:12px 18px;margin-bottom:20px;display:flex;align-items:center;justify-content:space-between;">
            <div style="font-size:14px;color:#856404;">
                <strong><?php echo $fd_cart_count; ?> item(s)</strong> in cart &mdash; Total: <strong>₱<?php echo number_format($fd_cart_total, 2); ?></strong>
            </div>
            <a href="/LechGo_Final/public/supplier/fd-checkout" class="btn btn-primary" style="padding:8px 20px;font-size:13px;text-decoration:none;">Proceed to Checkout</a>
        </div>
        <?php endif; ?>

        <!-- Summary stats -->
        <div class="stats-summary">
            <div class="summary-card">
                <div class="summary-value"><?php echo count($products); ?></div>
                <div class="summary-label">Products Available</div>
            </div>
            <div class="summary-card">
                <div class="summary-value"><?php echo count($by_distributor); ?></div>
                <div class="summary-label">Distributors</div>
            </div>
            <div class="summary-card">
                <div class="summary-value"><?php echo count($all_types); ?></div>
                <div class="summary-label">Feed Types</div>
            </div>
        </div>

        <!-- Filter bar -->
        <form method="GET" action="/LechGo_Final/public/supplier/feeds-market" class="filter-bar">
            <input type="text" name="search" placeholder="Search product, type, or distributor..."
                   value="<?php echo htmlspecialchars($search); ?>">
            <select name="type">
                <option value="">All Feed Types</option>
                <?php foreach ($all_types as $t): ?>
                    <option value="<?php echo htmlspecialchars($t['feed_type']); ?>"
                        <?php echo $filter_type === $t['feed_type'] ? 'selected' : ''; ?>>
                        <?php echo htmlspecialchars($t['feed_type']); ?>
                    </option>
                <?php endforeach; ?>
            </select>
            <button type="submit" class="btn-filter">Search</button>
            <?php if ($search || $filter_type): ?>
                <a href="/LechGo_Final/public/supplier/feeds-market" class="btn-clear">Clear</a>
            <?php endif; ?>
        </form>

        <?php if (empty($by_distributor)): ?>
            <div class="empty-market">
                <div class="empty-market-icon">🌾</div>
                <?php if ($search || $filter_type): ?>
                    <p>No products match your search.</p>
                    <a href="/LechGo_Final/public/supplier/feeds-market" style="color: var(--primary-red, #e74c3c);">Clear filters</a>
                <?php else: ?>
                    <p>No feed products listed by distributors yet.</p>
                    <p style="font-size: 13px; margin-top: 8px;">Check back later when Feed Distributors list their products.</p>
                <?php endif; ?>
            </div>
        <?php else: ?>
            <?php foreach ($by_distributor as $dist_id => $dist): ?>
            <div class="distributor-section">
                <div class="distributor-header">
                    <div class="distributor-avatar">
                        <?php echo strtoupper(substr($dist['info']['business_name'], 0, 1)); ?>
                    </div>
                    <div>
                        <div class="distributor-name"><?php echo htmlspecialchars($dist['info']['business_name']); ?></div>
                        <div class="distributor-meta">
                            <?php if ($dist['info']['location']): ?>
                                📍 <?php echo htmlspecialchars($dist['info']['location']); ?>
                            <?php endif; ?>
                            <?php if ($dist['info']['contact_number']): ?>
                                &nbsp;·&nbsp; 📞 <?php echo htmlspecialchars($dist['info']['contact_number']); ?>
                            <?php endif; ?>
                        </div>
                    </div>
                    <div style="margin-left: auto; font-size: 13px; color: #aaa;">
                        <?php echo count($dist['products']); ?> product(s)
                    </div>
                </div>

                <div class="products-grid">
                    <?php foreach ($dist['products'] as $p): ?>
                    <div class="product-card">
                        <?php if ($p['image_url']): ?>
                            <img src="<?php echo htmlspecialchars($p['image_url']); ?>"
                                 alt="<?php echo htmlspecialchars($p['product_name']); ?>"
                                 class="product-img">
                        <?php else: ?>
                            <div class="product-img-placeholder">📦</div>
                        <?php endif; ?>

                        <div class="product-body">
                            <div>
                                <div class="product-name"><?php echo htmlspecialchars($p['product_name']); ?></div>
                                <span class="product-type"><?php echo htmlspecialchars($p['feed_type']); ?></span>
                            </div>

                            <?php if ($p['description']): ?>
                                <div class="product-desc"><?php echo htmlspecialchars($p['description']); ?></div>
                            <?php endif; ?>

                            <div class="product-stats">
                                <div class="product-stat">
                                    <div class="product-stat-label">Price</div>
                                    <div class="product-stat-value product-price">₱<?php echo number_format($p['unit_price'], 2); ?>/kg</div>
                                </div>
                                <div class="product-stat">
                                    <div class="product-stat-label">Available</div>
                                    <div class="product-stat-value"><?php echo number_format($p['quantity_available_kg'], 1); ?> kg</div>
                                </div>
                            </div>
                        </div>

                        <!-- Order form -->
                        <form method="POST" action="/LechGo_Final/public/supplier/add-to-fd-cart"
                              class="product-order-form fd-order-form"
                              data-price="<?php echo $p['unit_price']; ?>"
                              data-max="<?php echo $p['quantity_available_kg']; ?>">
                            <input type="hidden" name="product_id"    value="<?php echo $p['id']; ?>">
                            <input type="hidden" name="distributor_id" value="<?php echo $p['distributor_id']; ?>">
                            <input type="hidden" name="unit_price"    value="<?php echo $p['unit_price']; ?>">
                            <input type="hidden" name="product_name"  value="<?php echo htmlspecialchars($p['product_name']); ?>">
                            <input type="hidden" name="feed_type"     value="<?php echo htmlspecialchars($p['feed_type']); ?>">
                            <input type="hidden" name="quantity_kg" class="fd-qty-hidden" value="0">

                            <div class="qty-row">
                                <div class="qty-col">
                                    <label>Sacks<br><span style="font-weight:400;text-transform:none;">50 kg</span></label>
                                    <input type="number" class="fd-sacks" placeholder="0" min="0" step="1" value="0">
                                </div>
                                <div class="qty-col">
                                    <label>Half<br><span style="font-weight:400;text-transform:none;">25 kg</span></label>
                                    <input type="number" class="fd-half" placeholder="0" min="0" step="1" value="0">
                                </div>
                                <div class="qty-col">
                                    <label>Extra<br><span style="font-weight:400;text-transform:none;">kg</span></label>
                                    <input type="number" class="fd-extra" placeholder="0" min="0" step="0.5" value="0">
                                </div>
                            </div>
                            <div class="qty-footer">
                                <div class="qty-total-box">
                                    <div class="kg"><span class="fd-total-kg">0</span> kg</div>
                                    <div class="price">₱<span class="fd-total-price">0.00</span></div>
                                </div>
                                <button type="submit" class="btn-add-cart fd-submit-btn" disabled>
                                    Add to Cart
                                </button>
                            </div>
                        </form>
                    </div>
                    <?php endforeach; ?>
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
document.querySelectorAll('.fd-order-form').forEach(function(form) {
    const price  = parseFloat(form.dataset.price) || 0;
    const maxKg  = parseFloat(form.dataset.max)   || 9999;
    const sacks  = form.querySelector('.fd-sacks');
    const half   = form.querySelector('.fd-half');
    const extra  = form.querySelector('.fd-extra');
    const hidden = form.querySelector('.fd-qty-hidden');
    const totalKgEl    = form.querySelector('.fd-total-kg');
    const totalPriceEl = form.querySelector('.fd-total-price');
    const btn          = form.querySelector('.fd-submit-btn');

    function recalc() {
        const kg = (parseInt(sacks.value)||0)*50 + (parseInt(half.value)||0)*25 + (parseFloat(extra.value)||0);
        totalKgEl.textContent    = kg % 1 === 0 ? kg : kg.toFixed(1);
        totalPriceEl.textContent = (kg * price).toFixed(2);
        hidden.value = kg;
        if (kg <= 0 || kg > maxKg) {
            btn.disabled = true; btn.style.opacity = '0.5';
            btn.textContent = kg > maxKg ? 'Exceeds stock' : 'Add to Cart';
        } else {
            btn.disabled = false; btn.style.opacity = '1';
            btn.textContent = 'Add to Cart';
        }
    }
    [sacks, half, extra].forEach(i => i.addEventListener('input', recalc));
    form.addEventListener('submit', function(e) {
        if ((parseFloat(hidden.value)||0) <= 0) { e.preventDefault(); alert('Enter a quantity first.'); }
    });
});
</script>
</html>

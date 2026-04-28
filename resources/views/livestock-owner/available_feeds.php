<?php
$currentPage = 'available-feeds';

// Get user info for header
$user = $_SESSION['user'] ?? null;

// Get livestock owner ID
$query = "SELECT id FROM livestock_owners WHERE user_id = ?";
$stmt = $GLOBALS['conn']->prepare($query);
if (!$stmt) {
    $_SESSION['error'] = 'Database error: ' . $GLOBALS['conn']->error;
    $feeds = [];
} else {
    $stmt->bind_param('i', $user['id']);
    $stmt->execute();
    $result = $stmt->get_result();
    $owner = $result->fetch_assoc();
    $stmt->close();
    
    // Get available products from all suppliers
    $query = "SELECT fp.id, fp.product_name, fp.feed_type, fp.description, fp.unit_price, 
                     fp.quantity_available_kg, fp.image_url, s.id as supplier_id, s.farm_name as supplier_name
              FROM feed_products fp
              JOIN suppliers s ON fp.supplier_id = s.id
              WHERE fp.is_active = TRUE AND fp.quantity_available_kg > 0
              ORDER BY s.farm_name, fp.feed_type, fp.product_name";
    
    $result = $GLOBALS['conn']->query($query);
    
    if (!$result) {
        $_SESSION['error'] = 'Error fetching feeds: ' . $GLOBALS['conn']->error;
        $feeds = [];
    } else {
        $feeds = $result->fetch_all(MYSQLI_ASSOC) ?? [];
    }
}

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Order Feed - LechGO</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.8/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="/LechGo_Final/public/styles.css">
    <style>
        /* Quantity order form */
        .order-qty-grid {
            display: flex;
            gap: 6px;
            margin-bottom: 8px;
        }
        .qty-col {
            display: flex;
            flex-direction: column;
            align-items: center;
            flex: 1;
        }
        .qty-col label {
            font-size: 0.7rem;
            font-weight: 700;
            color: #555;
            text-transform: uppercase;
            letter-spacing: 0.02em;
            margin-bottom: 3px;
            text-align: center;
            white-space: nowrap;
        }
        .qty-hint {
            display: block;
            font-weight: 400;
            color: #aaa;
            font-size: 0.65rem;
            text-transform: none;
            letter-spacing: 0;
        }
        .qty-col input[type="number"] {
            width: 100%;
            padding: 5px 4px;
            border: 1.5px solid #e0e0e0;
            border-radius: 8px;
            font-size: 0.9rem;
            text-align: center;
            background: #fafafa;
            transition: border-color 0.2s;
        }
        .qty-col input[type="number"]:focus {
            outline: none;
            border-color: #FF6B6B;
            background: #fff;
        }
        .qty-footer {
            display: flex;
            align-items: center;
            gap: 8px;
        }
        .qty-total-badge {
            flex: 1;
            background: #fff5f5;
            border: 1.5px solid #ffd0d0;
            border-radius: 8px;
            padding: 5px 8px;
            text-align: center;
            line-height: 1.3;
        }
        .qty-total-badge .total-kg {
            font-size: 1rem;
            font-weight: 700;
            color: #FF6B6B;
        }
        .qty-total-badge .total-price {
            font-size: 0.72rem;
            color: #888;
        }
        .btn-order-feed {
            flex-shrink: 0;
            padding: 8px 14px;
            font-size: 0.82rem;
            border-radius: 8px;
            white-space: nowrap;
        }
        .btn-order-feed:disabled {
            opacity: 0.45;
            cursor: not-allowed;
        }
    </style>
</head>
<body>
    <div class="dashboard-layout">
        <?php include __DIR__ . '/../layouts/sidebar.php'; ?>
        
        <main class="dashboard-main">
            <div class="container">
    <div class="header">
        <h1>Order Feed</h1>
        <p style=": 5px 0 0 0; opacity: 0.9;">Browse and order quality feeds for your livestock</p>
    </div>

    <?php if (isset($_SESSION['success'])): ?>
        <div class="alert alert-success">
            <?php echo $_SESSION['success']; unset($_SESSION['success']); ?>
        </div>
    <?php endif; ?>

    <?php if (isset($_SESSION['error'])): ?>
        <div class="alert alert-error">
            <?php echo $_SESSION['error']; unset($_SESSION['error']); ?>
        </div>
    <?php endif; ?>

    <?php if (empty($feeds)): ?>
        <div style="text-align: center; padding: 40px; color: #7f8c8d;">
            <p style="font-size: 16px;">No feeds available at the moment.</p>
        </div>
    <?php else: ?>
        <div class="feeds-grid">
            <?php foreach ($feeds as $feed): ?>
                <div class="feed-card">
                    <!-- Feed Image -->
                    <?php if ($feed['image_url']): ?>
                        <div class="feed-image">
                            <img src="<?php echo htmlspecialchars($feed['image_url']); ?>" alt="<?php echo htmlspecialchars($feed['product_name']); ?>">
                        </div>
                    <?php else: ?>
                        <div class="feed-image-placeholder">Feed</div>
                    <?php endif; ?>
                    
                    <div class="feed-header">
                        <h3 class="feed-name"><?php echo htmlspecialchars($feed['product_name']); ?></h3>
                        <p class="feed-type"><?php echo htmlspecialchars($feed['feed_type']); ?></p>
                    </div>
                    <div class="feed-body">
                        <div class="feed-supplier">
                            <strong>From:</strong> <?php echo htmlspecialchars($feed['supplier_name']); ?>
                        </div>
                        <?php if ($feed['description']): ?>
                            <div class="feed-description">
                                <?php echo htmlspecialchars($feed['description']); ?>
                            </div>
                        <?php endif; ?>
                        <div class="feed-stats">
                            <div class="stat">
                                <div class="stat-label">Price</div>
                                <div class="stat-value">₱<?php echo number_format($feed['unit_price'], 2); ?>/kg</div>
                            </div>
                            <div class="stat">
                                <div class="stat-label">Available</div>
                                <div class="stat-value"><?php echo intval($feed['quantity_available_kg']); ?> kg</div>
                            </div>
                        </div>

                        <form method="POST" action="/LechGo_Final/public/livestock-owner/add-to-cart" 
                              class="order-form" 
                              data-price="<?php echo $feed['unit_price']; ?>"
                              data-max="<?php echo $feed['quantity_available_kg']; ?>">
                            <input type="hidden" name="product_id" value="<?php echo $feed['id']; ?>">
                            <input type="hidden" name="supplier_id" value="<?php echo $feed['supplier_id']; ?>">
                            <input type="hidden" name="unit_price" value="<?php echo $feed['unit_price']; ?>">
                            <input type="hidden" name="product_name" value="<?php echo htmlspecialchars($feed['product_name']); ?>">
                            <input type="hidden" name="feed_type" value="<?php echo htmlspecialchars($feed['feed_type']); ?>">
                            <input type="hidden" name="quantity_kg" class="qty-kg-hidden" value="0">

                            <div class="order-qty-grid">
                                <div class="qty-col">
                                    <label>Sacks <span class="qty-hint">50 kg each</span></label>
                                    <input type="number" class="qty-sacks" placeholder="0" min="0" step="1" value="0">
                                </div>
                                <div class="qty-col">
                                    <label>Half Sacks <span class="qty-hint">25 kg each</span></label>
                                    <input type="number" class="qty-half-sacks" placeholder="0" min="0" step="1" value="0">
                                </div>
                                <div class="qty-col">
                                    <label>Extra kg <span class="qty-hint">direct kg</span></label>
                                    <input type="number" class="qty-extra-kg" placeholder="0" min="0" step="0.5" value="0">
                                </div>
                            </div>

                            <div class="qty-footer">
                                <div class="qty-total-badge">
                                    <div class="total-kg"><span class="qty-total-kg">0</span> kg</div>
                                    <div class="total-price">₱<span class="qty-total-price">0.00</span></div>
                                </div>
                                <button type="submit" class="btn btn-primary btn-order-feed" disabled>Add to Cart</button>
                            </div>
                        </form>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>
</div>

    </main>

    </div>
</body>
</html>

<script>
document.querySelectorAll('.order-form').forEach(function(form) {
    const pricePerKg = parseFloat(form.dataset.price) || 0;
    const maxKg      = parseFloat(form.dataset.max)   || 9999;

    const sacksInput     = form.querySelector('.qty-sacks');
    const halfSacksInput = form.querySelector('.qty-half-sacks');
    const extraKgInput   = form.querySelector('.qty-extra-kg');
    const hiddenKg       = form.querySelector('.qty-kg-hidden');
    const totalKgEl      = form.querySelector('.qty-total-kg');
    const totalPriceEl   = form.querySelector('.qty-total-price');
    const submitBtn      = form.querySelector('.btn-order-feed');

    const KG_PER_SACK      = 50;
    const KG_PER_HALF_SACK = 25;

    function recalc() {
        const sacks     = Math.max(0, parseInt(sacksInput.value)     || 0);
        const halfSacks = Math.max(0, parseInt(halfSacksInput.value) || 0);
        const extraKg   = Math.max(0, parseFloat(extraKgInput.value) || 0);

        const totalKg = (sacks * KG_PER_SACK) + (halfSacks * KG_PER_HALF_SACK) + extraKg;
        const totalPrice = totalKg * pricePerKg;

        totalKgEl.textContent    = totalKg % 1 === 0 ? totalKg : totalKg.toFixed(1);
        totalPriceEl.textContent = totalPrice.toFixed(2);
        hiddenKg.value           = totalKg;

        if (totalKg <= 0) {
            submitBtn.disabled = true;
            submitBtn.textContent = 'Add to Cart';
        } else if (totalKg > maxKg) {
            submitBtn.disabled = true;
            submitBtn.textContent = 'Exceeds available stock';
        } else {
            submitBtn.disabled = false;
            submitBtn.textContent = 'Add to Cart';
        }
    }

    [sacksInput, halfSacksInput, extraKgInput].forEach(function(input) {
        input.addEventListener('input', recalc);
    });

    form.addEventListener('submit', function(e) {
        const kg = parseFloat(hiddenKg.value) || 0;
        if (kg <= 0) {
            e.preventDefault();
            alert('Please enter a quantity (sacks, half sacks, or kg).');
        }
    });
});
</script>

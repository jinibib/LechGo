<?php
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
                     fp.quantity_available_kg, s.id as supplier_id, s.farm_name as supplier_name
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
    <link rel="stylesheet" href="/LechGo_Final/public/styles.css">

</head>
<body>
    <!-- Header/Navigation -->
    <header>
        <div class="header-container">
            <a href="/LechGo_Final/public/" class="no-underline">
                <div class="logo">
                    <img src="/LechGo_Final/public/images/Logo.png" alt="LechGO Logo" class="logo-img">
                    <div class="logo-text">LechGO</div>
                </div>
            </a>
            <nav>
                <div class="user-profile">
                    <div class="user-avatar"><?php echo strtoupper(substr($user['name'] ?? 'U', 0, 1)); ?></div>
                    <div class="user-info">
                        <p class="name"><?php echo htmlspecialchars($user['name'] ?? 'User'); ?></p>
                        <p class="email"><?php echo htmlspecialchars($user['email'] ?? ''); ?></p>
                    </div>
                    <a href="/LechGo_Final/public/logout" class="btn btn-secondary ml-md">Logout</a>
                </div>
            </nav>
        </div>
    </header>
    <main>
    <div class="schedule-container">
            <!-- Back Button -->
            <a href="/LechGo_Final/public/dashboard" class="back-button">← Back to Dashboard</a>

    <main>

<div class="container">
    <div class="header">
        <h1>📦 Order Feed</h1>
        <p style="margin: 10px 0 0 0; opacity: 0.9;">Browse and order quality feeds for your livestock</p>
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

                        <form method="POST" action="/LechGo_Final/public/livestock-owner/add-to-cart" class="order-form">
                            <input type="hidden" name="product_id" value="<?php echo $feed['id']; ?>">
                            <input type="hidden" name="supplier_id" value="<?php echo $feed['supplier_id']; ?>">
                            <input type="hidden" name="unit_price" value="<?php echo $feed['unit_price']; ?>">
                            <input type="hidden" name="product_name" value="<?php echo htmlspecialchars($feed['product_name']); ?>">
                            <input type="hidden" name="feed_type" value="<?php echo htmlspecialchars($feed['feed_type']); ?>">

                            <div class="form-group">
                                <input type="number" name="quantity_kg" placeholder="Qty (kg)" step="0.5" min="1" max="<?php echo $feed['quantity_available_kg']; ?>" value="1" required>
                                <button type="submit" class="btn btn-primary">🛒 Add to Cart</button>
                            </div>
                        </form>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>
</div>

    </main>
    <!-- Footer -->
    <footer>
        <div class="footer-bottom" style="border-top: 1px solid rgba(255,255,255,0.2);">
            <p>&copy; 2026 LechGO. All rights reserved.</p>
        </div>
    </footer>
</body>
</html>

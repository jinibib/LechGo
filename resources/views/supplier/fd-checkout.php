<?php
$currentPage = 'feeds-market';

$sessionMiddleware = new Session();
$user = $sessionMiddleware->getUser();

if (!$sessionMiddleware->isAuthenticated() || $user['role'] !== 'supplier') {
    header('Location: /LechGo_Final/public/login');
    exit;
}

$cart = $_SESSION['fd_cart'] ?? [];

// Get supplier info
$stmt = $GLOBALS['conn']->prepare("SELECT s.id, s.farm_name, l.street, l.barangay, l.municipality, l.city FROM suppliers s LEFT JOIN locations l ON s.location_id = l.location_id WHERE s.user_id = ?");
$stmt->bind_param('i', $user['id']);
$stmt->execute();
$supplier_info = $stmt->get_result()->fetch_assoc();
$stmt->close();

$default_address = implode(', ', array_filter([
    $supplier_info['street'] ?? '',
    $supplier_info['barangay'] ?? '',
    $supplier_info['municipality'] ?? '',
    $supplier_info['city'] ?? ''
]));

// Group by distributor
$by_distributor = [];
$grand_total = 0;
foreach ($cart as $item) {
    $did = $item['distributor_id'];
    $by_distributor[$did]['items'][] = $item;
    $by_distributor[$did]['total']   = ($by_distributor[$did]['total'] ?? 0) + $item['subtotal'];
    $by_distributor[$did]['name']    = $item['distributor_name'] ?? 'Distributor';
    $grand_total += $item['subtotal'];
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Checkout - LechGO</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.8/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="/LechGo_Final/public/styles.css">
    <style>
        .co-grid { display:grid; grid-template-columns:1fr 1fr; gap:20px; }
        .co-box { background:white; border:1px solid #e0e0e0; border-radius:8px; padding:20px; }
        .co-section { background:#f9f9f9; border-left:4px solid #e74c3c; border-radius:4px; padding:12px; margin-bottom:12px; }
        .co-section h3 { margin:0 0 10px 0; font-size:14px; color:#e74c3c; }
        .co-item { display:grid; grid-template-columns:1fr 70px 70px 90px; gap:8px; align-items:center; padding:6px 0; border-bottom:1px solid #eee; font-size:13px; }
        .co-item:last-child { border-bottom:none; }
        .co-total { text-align:right; font-weight:700; color:#e74c3c; margin-top:8px; padding-top:8px; border-top:2px solid #e74c3c; font-size:14px; }
        .grand-total { background:linear-gradient(135deg,#e74c3c,#c0392b); color:white; padding:16px; border-radius:8px; text-align:center; margin-bottom:16px; }
        .grand-total-label { font-size:12px; opacity:.9; }
        .grand-total-value { font-size:28px; font-weight:700; }
        .form-group { margin-bottom:12px; }
        .form-group label { display:block; font-size:12px; font-weight:600; color:#555; margin-bottom:4px; }
        .form-group input, .form-group select, .form-group textarea { width:100%; padding:8px 10px; border:1.5px solid #e0e0e0; border-radius:6px; font-size:13px; }
        .form-group textarea { resize:vertical; min-height:60px; }
        @media(max-width:768px){ .co-grid{ grid-template-columns:1fr; } }
    </style>
</head>
<body>
<div class="dashboard-layout">
    <?php include __DIR__ . '/../layouts/sidebar.php'; ?>
    <main class="dashboard-main">
    <div style="max-width:1100px;margin:0;padding:var(--spacing-md) 0;">

        <?php if (isset($_SESSION['success'])): ?>
            <div class="alert alert-success show">✓ <?php echo htmlspecialchars($_SESSION['success']); unset($_SESSION['success']); ?></div>
        <?php endif; ?>
        <?php if (isset($_SESSION['error'])): ?>
            <div class="alert alert-error show">✗ <?php echo htmlspecialchars($_SESSION['error']); unset($_SESSION['error']); ?></div>
        <?php endif; ?>

        <div style="margin-bottom:16px;">
            <h1 style="margin:0;">Order Summary & Checkout</h1>
            <a href="/LechGo_Final/public/supplier/feeds-market" style="font-size:13px;color:#e74c3c;">&larr; Back to Feeds Market</a>
        </div>

        <?php if (empty($cart)): ?>
            <div class="co-box" style="text-align:center;padding:40px;">
                <p style="color:#999;margin-bottom:16px;">Your cart is empty.</p>
                <a href="/LechGo_Final/public/supplier/feeds-market" class="btn btn-primary">Browse Feeds</a>
            </div>
        <?php else: ?>
        <div class="co-grid">
            <!-- Order summary -->
            <div class="co-box">
                <h2 style="margin:0 0 16px 0;font-size:16px;">Order Details</h2>
                <?php foreach ($by_distributor as $did => $ddata): ?>
                <div class="co-section">
                    <h3><?php echo htmlspecialchars($ddata['name']); ?></h3>
                    <?php $n = 1; foreach ($ddata['items'] as $item): ?>
                    <div class="co-item">
                        <div><strong><?php echo $n++; ?>. <?php echo htmlspecialchars($item['product_name']); ?></strong><br><small><?php echo htmlspecialchars($item['feed_type']); ?></small></div>
                        <div><?php echo number_format($item['quantity_kg'], 1); ?> kg</div>
                        <div>₱<?php echo number_format($item['unit_price'], 2); ?></div>
                        <div style="text-align:right;font-weight:600;">₱<?php echo number_format($item['subtotal'], 2); ?></div>
                    </div>
                    <?php endforeach; ?>
                    <div class="co-total">Subtotal: ₱<?php echo number_format($ddata['total'], 2); ?></div>
                </div>
                <?php endforeach; ?>
                <div style="display:flex;justify-content:space-between;font-size:15px;font-weight:700;color:#e74c3c;padding-top:10px;border-top:2px solid #e74c3c;">
                    <span>Grand Total:</span><span>₱<?php echo number_format($grand_total, 2); ?></span>
                </div>
            </div>

            <!-- Checkout form -->
            <div>
                <form method="POST" action="/LechGo_Final/public/supplier/fd-checkout" class="co-box">
                    <h2 style="margin:0 0 16px 0;font-size:16px;">Delivery & Payment</h2>

                    <div class="form-group">
                        <label>Delivery Address *</label>
                        <textarea name="delivery_address" required><?php echo htmlspecialchars($default_address); ?></textarea>
                    </div>

                    <div class="form-group">
                        <label>Payment Method *</label>
                        <select name="payment_method" required>
                            <option value="">-- Select --</option>
                            <option value="test_payment">Test Payment (Dev Only)</option>
                            <option value="online_payment">Online Payment (PayMongo)</option>
                            <option value="cash_on_delivery">Cash on Delivery</option>
                        </select>
                    </div>

                    <div class="grand-total">
                        <div class="grand-total-label">Total Amount to Pay</div>
                        <div class="grand-total-value">₱<?php echo number_format($grand_total, 2); ?></div>
                    </div>

                    <div style="display:flex;gap:10px;">
                        <a href="/LechGo_Final/public/supplier/feeds-market" class="btn btn-secondary" style="flex:1;text-align:center;text-decoration:none;">Keep Shopping</a>
                        <button type="submit" class="btn btn-primary" style="flex:1;">Place Order</button>
                    </div>
                </form>
            </div>
        </div>
        <?php endif; ?>

    </div>
    </main>

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
const grandTotal = <?php echo $grand_total; ?>;

document.querySelector('form')?.addEventListener('submit', async function(e) {
    const method = this.querySelector('[name="payment_method"]').value;
    if (method === 'online_payment') {
        e.preventDefault();
        const btn = this.querySelector('button[type="submit"]');
        btn.disabled = true; btn.textContent = 'Processing...';
        try {
            // Save order first via AJAX
            const fd = new FormData(this);
            await fetch(this.action, { method:'POST', body: fd, headers:{'X-Requested-With':'XMLHttpRequest'} });

            // Create PayMongo checkout
            const res = await fetch('/LechGo_Final/public/api/create-payment-intent', {
                method: 'POST',
                headers: {'Content-Type':'application/json'},
                body: JSON.stringify({ amount: Math.round(grandTotal * 100), description: 'Feed Purchase - LechGO' })
            });
            const data = await res.json();
            if (!data.success || !data.checkout_url) throw new Error(data.error || 'Payment init failed');
            window.location.href = data.checkout_url;
        } catch(err) {
            alert('Error: ' + err.message);
            btn.disabled = false; btn.textContent = 'Place Order';
        }
    }
});
</script>
</body>
</html>

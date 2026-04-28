<?php
$currentPage = 'fd-product-inventory';

$sessionMiddleware = new Session();
$user = $sessionMiddleware->getUser();

if (!$sessionMiddleware->isAuthenticated() || $user['role'] !== 'feed_distributor') {
    header('Location: /LechGo_Final/public/login');
    exit;
}

// Get distributor record
$stmt = $GLOBALS['conn']->prepare("SELECT id, business_name, contact_number FROM feed_distributors WHERE user_id = ?");
if (!$stmt) die('Database error: ' . $GLOBALS['conn']->error);
$stmt->bind_param('i', $user['id']);
$stmt->execute();
$distributor = $stmt->get_result()->fetch_assoc();
$stmt->close();

if (!$distributor) {
    $_SESSION['error'] = 'Distributor profile not found. Please complete your profile first.';
    header('Location: /LechGo_Final/public/complete-profile');
    exit;
}

$distributor_id = $distributor['id'];

// Get all products
$stmt = $GLOBALS['conn']->prepare(
    "SELECT id, product_name, feed_type, description, unit_price, quantity_available_kg, image_url, is_active, created_at
     FROM feed_distributor_products
     WHERE distributor_id = ?
     ORDER BY created_at DESC"
);
if (!$stmt) die('Database error: ' . $GLOBALS['conn']->error);
$stmt->bind_param('i', $distributor_id);
$stmt->execute();
$products = $stmt->get_result()->fetch_all(MYSQLI_ASSOC) ?? [];
$stmt->close();

$total_products = count($products);
$active_count   = count(array_filter($products, fn($p) => $p['is_active']));
$total_stock    = array_sum(array_column($products, 'quantity_available_kg'));
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Product Inventory - LechGO</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.8/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="/LechGo_Final/public/styles.css">
</head>
<body>
<div class="dashboard-layout">
    <?php include __DIR__ . '/../layouts/sidebar.php'; ?>

    <main class="dashboard-main">
    <div style="max-width:100%;margin:0;padding:var(--spacing-md) 0;">

        <?php if (isset($_SESSION['success'])): ?>
            <div class="alert alert-success show">✓ <?php echo htmlspecialchars($_SESSION['success']); unset($_SESSION['success']); ?></div>
        <?php endif; ?>
        <?php if (isset($_SESSION['error'])): ?>
            <div class="alert alert-error show">✗ <?php echo htmlspecialchars($_SESSION['error']); unset($_SESSION['error']); ?></div>
        <?php endif; ?>

        <!-- Header -->
        <div class="inventory-header">
            <h1>Product Inventory</h1>
            <p><?php echo htmlspecialchars($distributor['business_name']); ?></p>
        </div>

        <!-- Stats -->
        <div class="stats-grid">
            <div class="stat-card">
                <div class="stat-value"><?php echo $total_products; ?></div>
                <div class="stat-label">Total Products</div>
            </div>
            <div class="stat-card">
                <div class="stat-value"><?php echo $active_count; ?></div>
                <div class="stat-label">Active Products</div>
            </div>
            <div class="stat-card">
                <div class="stat-value"><?php echo number_format($total_stock, 1); ?></div>
                <div class="stat-label">Total Stock (kg)</div>
            </div>
        </div>

        <!-- Add Product Form -->
        <div class="product-form">
            <h2 style="margin:0 0 var(--spacing-lg) 0;">Add New Product</h2>
            <form method="POST" action="/LechGo_Final/public/feed-distributor/add-product" enctype="multipart/form-data">
                <div class="form-grid">
                    <div class="form-group">
                        <label for="product_name">Product Name *</label>
                        <input type="text" id="product_name" name="product_name" placeholder="e.g., Premium Corn Feed" required>
                    </div>
                    <div class="form-group">
                        <label for="feed_type">Feed Type *</label>
                        <input type="text" id="feed_type" name="feed_type" placeholder="e.g., Corn, Booster, Supplement" required>
                    </div>
                    <div class="form-group">
                        <label for="unit_price">Price per KG (₱) *</label>
                        <input type="number" id="unit_price" name="unit_price" step="0.01" min="0" placeholder="45.50" required>
                    </div>
                    <div class="form-group">
                        <label for="quantity_available_kg">Quantity Available (kg) *</label>
                        <input type="number" id="quantity_available_kg" name="quantity_available_kg" step="0.5" min="0" placeholder="100" required>
                    </div>
                    <div class="form-group" style="grid-column:1/-1;">
                        <label for="description">Description</label>
                        <textarea id="description" name="description" placeholder="Describe your product..."></textarea>
                    </div>
                    <div class="form-group" style="grid-column:1/-1;">
                        <label for="product_image">Product Image</label>
                        <input type="file" id="product_image" name="product_image" accept="image/jpeg,image/png,image/jpg,image/gif">
                        <small style="color:#666;margin-top:5px;display:block;">Accepted: JPG, PNG, GIF (Max 5MB)</small>
                    </div>
                </div>
                <div class="form-actions">
                    <button type="reset" class="btn btn-secondary">Clear</button>
                    <button type="submit" class="btn btn-primary">+ Add Product</button>
                </div>
            </form>
        </div>

        <!-- Products Table -->
        <div style="margin-top:var(--spacing-lg);">
            <h2>Your Products</h2>
            <?php if (empty($products)): ?>
                <div class="products-table">
                    <div class="empty-state">
                        <div class="empty-state-icon">📭</div>
                        <p>No products yet. Add your first product above!</p>
                    </div>
                </div>
            <?php else: ?>
                <div class="products-table">
                    <table>
                        <thead>
                            <tr>
                                <th>Image</th>
                                <th>Product Name</th>
                                <th>Feed Type</th>
                                <th>Price (₱/kg)</th>
                                <th>Stock (kg)</th>
                                <th>Status</th>
                                <th>Added</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($products as $product): ?>
                            <tr>
                                <td>
                                    <?php if ($product['image_url']): ?>
                                        <img src="<?php echo htmlspecialchars($product['image_url']); ?>"
                                             style="width:50px;height:50px;object-fit:cover;border-radius:4px;">
                                    <?php else: ?>
                                        <div style="width:50px;height:50px;background:#ddd;border-radius:4px;display:flex;align-items:center;justify-content:center;font-size:20px;">📦</div>
                                    <?php endif; ?>
                                </td>
                                <td><strong><?php echo htmlspecialchars($product['product_name']); ?></strong></td>
                                <td><?php echo htmlspecialchars($product['feed_type']); ?></td>
                                <td>₱<?php echo number_format($product['unit_price'], 2); ?></td>
                                <td><?php echo number_format($product['quantity_available_kg'], 1); ?></td>
                                <td>
                                    <span class="<?php echo $product['is_active'] ? 'status-active' : 'status-inactive'; ?>">
                                        <?php echo $product['is_active'] ? '✓ Active' : '✗ Inactive'; ?>
                                    </span>
                                </td>
                                <td><?php echo date('M d, Y', strtotime($product['created_at'])); ?></td>
                                <td>
                                    <div class="action-buttons">
                                        <button type="button" class="btn-small btn-edit"
                                                onclick="editProduct(<?php echo $product['id']; ?>)">Edit</button>
                                        <button type="button" class="btn-small btn-delete"
                                                onclick="deleteProduct(<?php echo $product['id']; ?>)">Delete</button>
                                    </div>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            <?php endif; ?>
        </div>

    </div>
    </main>

    <!-- Edit Modal -->
    <div id="editModal" class="modal" style="display:none;position:fixed;top:0;left:0;width:100%;height:100%;background:rgba(0,0,0,0.5);align-items:center;justify-content:center;z-index:2000;">
        <div class="modal-content">
            <div class="modal-header">
                <h2>Edit Product</h2>
                <button class="modal-close" onclick="closeEditModal()">&times;</button>
            </div>
            <form method="POST" action="/LechGo_Final/public/feed-distributor/edit-product" enctype="multipart/form-data">
                <input type="hidden" id="edit_product_id" name="product_id">
                <div class="form-grid">
                    <div class="form-group">
                        <label>Product Name *</label>
                        <input type="text" id="edit_product_name" name="product_name" required>
                    </div>
                    <div class="form-group">
                        <label>Feed Type *</label>
                        <input type="text" id="edit_feed_type" name="feed_type" required>
                    </div>
                    <div class="form-group">
                        <label>Price per KG (₱) *</label>
                        <input type="number" id="edit_unit_price" name="unit_price" step="0.01" min="0" required>
                    </div>
                    <div class="form-group">
                        <label>Quantity Available (kg) *</label>
                        <input type="number" id="edit_quantity_available_kg" name="quantity_available_kg" step="0.5" min="0" required>
                    </div>
                    <div class="form-group" style="grid-column:1/-1;">
                        <label>Description</label>
                        <textarea id="edit_description" name="description"></textarea>
                    </div>
                    <div class="form-group" style="grid-column:1/-1;">
                        <label>Product Image</label>
                        <div id="current_image_preview"></div>
                        <input type="file" id="edit_product_image" name="product_image" accept="image/jpeg,image/png,image/jpg,image/gif">
                        <small style="color:#666;margin-top:5px;display:block;">Leave blank to keep current image</small>
                    </div>
                </div>
                <div class="form-actions">
                    <button type="button" class="btn btn-secondary" onclick="closeEditModal()">Cancel</button>
                    <button type="submit" class="btn btn-primary">Update Product</button>
                </div>
            </form>
        </div>
    </div>

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
function editProduct(id) {
    fetch('/LechGo_Final/public/feed-distributor/get-product?id=' + id)
        .then(r => r.json())
        .then(data => {
            if (!data.success) { alert('Error: ' + data.message); return; }
            const p = data.product;
            document.getElementById('edit_product_id').value             = p.id;
            document.getElementById('edit_product_name').value           = p.product_name;
            document.getElementById('edit_feed_type').value              = p.feed_type;
            document.getElementById('edit_unit_price').value             = p.unit_price;
            document.getElementById('edit_quantity_available_kg').value  = p.quantity_available_kg;
            document.getElementById('edit_description').value            = p.description || '';
            const preview = document.getElementById('current_image_preview');
            preview.innerHTML = p.image_url
                ? '<div style="margin-bottom:10px;"><strong>Current Image:</strong><br><img src="' + p.image_url + '" style="max-width:150px;max-height:150px;border-radius:4px;margin-top:5px;"></div>'
                : '<div style="margin-bottom:10px;color:#666;">No image uploaded yet</div>';
            document.getElementById('editModal').style.display = 'flex';
        })
        .catch(err => alert('Error: ' + err));
}

function closeEditModal() {
    document.getElementById('editModal').style.display = 'none';
}

document.getElementById('editModal').addEventListener('click', function(e) {
    if (e.target === this) closeEditModal();
});

function deleteProduct(id) {
    if (!confirm('Delete this product?')) return;
    fetch('/LechGo_Final/public/feed-distributor/delete-product', {
        method: 'POST',
        headers: {'Content-Type': 'application/x-www-form-urlencoded'},
        body: 'product_id=' + id
    })
    .then(r => r.json())
    .then(data => {
        if (data.success) { alert('Product deleted!'); location.reload(); }
        else alert('Error: ' + data.message);
    });
}
</script>
</body>
</html>

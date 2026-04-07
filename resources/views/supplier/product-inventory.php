<?php
$sessionMiddleware = new Session();
$user = $sessionMiddleware->getUser();

// Redirect if not authenticated or not supplier
if (!$sessionMiddleware->isAuthenticated() || $user['role'] !== 'supplier') {
    header('Location: /LechGo_Final/public/login');
    exit;
}

// Get supplier ID
$query = "SELECT id FROM suppliers WHERE user_id = ?";
$stmt = $GLOBALS['conn']->prepare($query);
if (!$stmt) {
    die('Database error: ' . $GLOBALS['conn']->error);
}
$stmt->bind_param('i', $user['id']);
$stmt->execute();
$result = $stmt->get_result();
$supplier = $result->fetch_assoc();
$stmt->close();

if (!$supplier) {
    $_SESSION['error'] = 'Supplier profile not found';
    header('Location: /LechGo_Final/public/dashboard');
    exit;
}

$supplier_id = $supplier['id'];

// Get supplier's products
$query = "SELECT id, product_name, feed_type, description, unit_price, quantity_available_kg, image_url, is_active, created_at
          FROM feed_products
          WHERE supplier_id = ?
          ORDER BY created_at DESC";
$stmt = $GLOBALS['conn']->prepare($query);
if (!$stmt) {
    die('Database error: ' . $GLOBALS['conn']->error);
}
$stmt->bind_param('i', $supplier_id);
$stmt->execute();
$result = $stmt->get_result();
$products = $result->fetch_all(MYSQLI_ASSOC) ?? [];
$stmt->close();

// Get supplier info
$query = "SELECT farm_name, contact_number FROM suppliers WHERE id = ?";
$stmt = $GLOBALS['conn']->prepare($query);
if (!$stmt) {
    die('Database error: ' . $GLOBALS['conn']->error);
}
$stmt->bind_param('i', $supplier_id);
$stmt->execute();
$result = $stmt->get_result();
$supplier_info = $result->fetch_assoc();
$stmt->close();

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Product Inventory - LechGO</title>
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
        <div style="max-width: 1200px; margin: 0 auto; padding: var(--spacing-lg);">
            <!-- Back Button -->
            <a href="/LechGo_Final/public/dashboard" class="back-button">← Back to Dashboard</a>

            <!-- Display Messages -->
            <?php if (isset($_SESSION['success'])): ?>
                <div class="alert alert-success show">
                    ✓ <?php echo htmlspecialchars($_SESSION['success']); ?>
                </div>
                <?php unset($_SESSION['success']); ?>
            <?php endif; ?>

            <?php if (isset($_SESSION['error'])): ?>
                <div class="alert alert-error show">
                    ✗ <?php echo htmlspecialchars($_SESSION['error']); ?>
                </div>
                <?php unset($_SESSION['error']); ?>
            <?php endif; ?>

            <!-- Page Header -->
            <div class="inventory-header">
                <h1> Product Inventory Management</h1>
                <p><?php echo htmlspecialchars($supplier_info['farm_name'] ?? 'Your Farm'); ?></p>
            </div>

            <!-- Statistics -->
            <div class="stats-grid">
                <div class="stat-card">
                    <div class="stat-value"><?php echo count($products); ?></div>
                    <div class="stat-label">Total Products</div>
                </div>
                <div class="stat-card">
                    <div class="stat-value"><?php 
                        $active_count = 0;
                        foreach ($products as $p) {
                            if ($p['is_active']) $active_count++;
                        }
                        echo $active_count;
                    ?></div>
                    <div class="stat-label">Active Products</div>
                </div>
                <div class="stat-card">
                    <div class="stat-value"><?php 
                        $total_stock = 0;
                        foreach ($products as $p) {
                            $total_stock += $p['quantity_available_kg'];
                        }
                        echo number_format($total_stock, 1);
                    ?></div>
                    <div class="stat-label">Total Stock (kg)</div>
                </div>
            </div>

            <!-- Add Product Form -->
            <div class="product-form">
                <h2 style="margin: 0 0 var(--spacing-lg) 0;">➕ Add New Product</h2>
                <form method="POST" action="/LechGo_Final/public/supplier/add-product" enctype="multipart/form-data">
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
                        <div class="form-group" style="grid-column: 1 / -1;">
                            <label for="description">Description</label>
                            <textarea id="description" name="description" placeholder="Describe your product..."></textarea>
                        </div>
                        <div class="form-group" style="grid-column: 1 / -1;">
                            <label for="product_image">Product Image</label>
                            <input type="file" id="product_image" name="product_image" accept="image/jpeg,image/png,image/jpg,image/gif" />
                            <small style="color: #666; margin-top: 5px; display: block;">Accepted formats: JPG, PNG, GIF (Max 5MB)</small>
                        </div>
                    </div>
                    <div class="form-actions">
                        <button type="reset" class="btn btn-secondary">Clear</button>
                        <button type="submit" class="btn btn-primary">+ Add Product</button>
                    </div>
                </form>
            </div>

            <!-- Products Table -->
            <div style="margin-top: var(--spacing-lg);">
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
                                                <img src="<?php echo htmlspecialchars($product['image_url']); ?>" alt="<?php echo htmlspecialchars($product['product_name']); ?>" style="width: 50px; height: 50px; object-fit: cover; border-radius: 4px;">
                                            <?php else: ?>
                                                <div style="width: 50px; height: 50px; background: #ddd; border-radius: 4px; display: flex; align-items: center; justify-content: center; font-size: 24px;">📦</div>
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
                                                <button type="button" class="btn-small btn-edit" onclick="editProduct(<?php echo $product['id']; ?>)">Edit</button>
                                                <button type="button" class="btn-small btn-delete" onclick="deleteProduct(<?php echo $product['id']; ?>)">Delete</button>
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

    <!-- Edit Product Modal -->
    <div id="editModal" class="modal" style="display: none; position: fixed; top: 0; left: 0; width: 100%; height: 100%; background-color: rgba(0, 0, 0, 0.5); align-items: center; justify-content: center; z-index: 2000;">
        <div class="modal-content">
            <div class="modal-header">
                <h2>Edit Product</h2>
                <button class="modal-close" onclick="closeEditModal()">&times;</button>
            </div>
            <form method="POST" action="/LechGo_Final/public/supplier/edit-product" enctype="multipart/form-data">
                <input type="hidden" id="edit_product_id" name="product_id">
                <div class="form-grid">
                    <div class="form-group">
                        <label for="edit_product_name">Product Name *</label>
                        <input type="text" id="edit_product_name" name="product_name" required>
                    </div>
                    <div class="form-group">
                        <label for="edit_feed_type">Feed Type *</label>
                        <input type="text" id="edit_feed_type" name="feed_type" required>
                    </div>
                    <div class="form-group">
                        <label for="edit_unit_price">Price per KG (₱) *</label>
                        <input type="number" id="edit_unit_price" name="unit_price" step="0.01" min="0" required>
                    </div>
                    <div class="form-group">
                        <label for="edit_quantity_available_kg">Quantity Available (kg) *</label>
                        <input type="number" id="edit_quantity_available_kg" name="quantity_available_kg" step="0.5" min="0" required>
                    </div>
                    <div class="form-group" style="grid-column: 1 / -1;">
                        <label for="edit_description">Description</label>
                        <textarea id="edit_description" name="description"></textarea>
                    </div>
                    <div class="form-group" style="grid-column: 1 / -1;">
                        <label for="edit_product_image">Product Image</label>
                        <div id="current_image_preview"></div>
                        <input type="file" id="edit_product_image" name="product_image" accept="image/jpeg,image/png,image/jpg,image/gif" />
                        <small style="color: #666; margin-top: 5px; display: block;">Accepted formats: JPG, PNG, GIF (Max 5MB)</small>
                    </div>
                </div>
                <div class="form-actions">
                    <button type="button" class="btn btn-secondary" onclick="closeEditModal()">Cancel</button>
                    <button type="submit" class="btn btn-primary">Update Product</button>
                </div>
            </form>
        </div>
    </div>

    <!-- Footer -->
    <footer>
        <div class="footer-bottom" style="border-top: 1px solid rgba(255,255,255,0.2);">
            <p>&copy; 2026 LechGO. All rights reserved.</p>
        </div>
    </footer>

    <script>
        function editProduct(productId) {
            // Fetch product data via AJAX
            fetch('/LechGo_Final/public/supplier/get-product?id=' + productId)
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        const product = data.product;
                        document.getElementById('edit_product_id').value = product.id;
                        document.getElementById('edit_product_name').value = product.product_name;
                        document.getElementById('edit_feed_type').value = product.feed_type;
                        document.getElementById('edit_unit_price').value = product.unit_price;
                        document.getElementById('edit_quantity_available_kg').value = product.quantity_available_kg;
                        document.getElementById('edit_description').value = product.description || '';
                        
                        // Show current image if exists
                        const previewDiv = document.getElementById('current_image_preview');
                        if (product.image_url) {
                            previewDiv.innerHTML = '<div style="margin-bottom: 10px;"><strong>Current Image:</strong><br><img src="' + product.image_url + '" style="max-width: 150px; max-height: 150px; border-radius: 4px; margin-top: 5px;"></div>';
                        } else {
                            previewDiv.innerHTML = '<div style="margin-bottom: 10px; color: #666;">No image uploaded yet</div>';
                        }
                        
                        // Open modal
                        const modal = document.getElementById('editModal');
                        modal.style.display = 'flex';
                    } else {
                        alert('Error loading product: ' + data.message);
                    }
                })
                .catch(error => alert('Error: ' + error));
        }

        function closeEditModal() {
            document.getElementById('editModal').style.display = 'none';
        }

        // Close modal when clicking outside
        document.addEventListener('DOMContentLoaded', function() {
            const modal = document.getElementById('editModal');
            modal.addEventListener('click', function(event) {
                if (event.target === modal) {
                    closeEditModal();
                }
            });
        });

        function deleteProduct(productId) {
            if (confirm('Are you sure you want to delete this product?')) {
                // Submit delete form
                fetch('/LechGo_Final/public/supplier/delete-product', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/x-www-form-urlencoded',
                    },
                    body: 'product_id=' + productId
                })
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        alert('Product deleted successfully!');
                        location.reload();
                    } else {
                        alert('Error deleting product: ' + data.message);
                    }
                })
                .catch(error => alert('Error: ' + error));
            }
        }
    </script>
</body>
</html>

<?php
/**
 * Supplier - Order Details
 * View detailed information about a submitted order
 */

// Check if user is authenticated as supplier
$user = $_SESSION['user'] ?? null;
if (!$user || $user['role'] !== 'supplier') {
    header('Location: ' . '/LechGo_Final/public/login');
    exit;
}

// Get supplier
$supplier = new FeedSupplier($GLOBALS['conn']);
if (!$supplier->findByUserId($user['id'])) {
    $_SESSION['error'] = 'Supplier profile not found';
    header('Location: ' . '/LechGo_Final/public/supplier/my-orders');
    exit;
}

// Get order details
$query = "SELECT lfo.*, 
                 lo.farm_name AS buyer_farm, lo.location, lo.contact_number as buyer_contact,
                 u.name AS buyer_name, u.email AS buyer_email
          FROM livestock_feed_orders lfo
          LEFT JOIN livestock_owners lo ON lfo.livestock_owner_id = lo.id
          LEFT JOIN users u ON lo.user_id = u.id
          WHERE lfo.id = ? AND lfo.supplier_id = ?";

$stmt = $GLOBALS['conn']->prepare($query);
if (!$stmt) {
    $_SESSION['error'] = 'Database error: ' . $GLOBALS['conn']->error;
    header('Location: ' . '/LechGo_Final/public/supplier/my-orders');
    exit;
}

$stmt->bind_param('ii', $order_id, $supplier->id);
$stmt->execute();
$result = $stmt->get_result();
$order = $result->fetch_assoc();
$stmt->close();

if (!$order) {
    $_SESSION['error'] = 'Order not found or unauthorized';
    header('Location: ' . '/LechGo_Final/public/supplier/my-orders');
    exit;
}

// Get order items
$query_items = "SELECT * FROM livestock_feed_order_items WHERE feed_order_id = ? ORDER BY id ASC";
$stmt_items = $GLOBALS['conn']->prepare($query_items);
if ($stmt_items) {
    $stmt_items->bind_param('i', $order_id);
    $stmt_items->execute();
    $result_items = $stmt_items->get_result();
    $order['items'] = $result_items->fetch_all(MYSQLI_ASSOC);
    $stmt_items->close();
} else {
    $order['items'] = [];
}


?>

<!DOCTYPE html> 
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Order Details - LechGO</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.8/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="/LechGo_Final/public/styles.css">
</head>
<body>
    <div class="dashboard-layout">
        <?php include __DIR__ . '/../layouts/sidebar.php'; ?>
        
        <main class="dashboard-main">
        <div class="container" style="max-width: 1200px; padding: 1rem;">
            <!-- Header Card -->
            <div style="background: white; border-radius: 8px; padding: 1rem; margin-bottom: 1rem; box-shadow: 0 1px 3px rgba(0,0,0,0.1);">
                <div style="display: flex; justify-content: space-between; align-items: center;">
                    <div>
                        <h1 style="font-size: 1.5rem; margin: 0 0 0.25rem 0; font-weight: 600;">Order #<?php echo str_pad($order['id'], 6, "0", STR_PAD_LEFT); ?></h1>
                        <p style="margin: 0; color: #666; font-size: 0.9rem;"><?php echo date('M d, Y g:i A', strtotime($order['created_at'])); ?></p>
                    </div>
                    <span class="status-badge status-<?php echo $order['order_status']; ?>" style="padding: 0.4rem 0.8rem; border-radius: 4px; font-size: 0.9rem; font-weight: 500;">
                        <?php echo str_replace('_', ' ', ucfirst($order['order_status'])); ?>
                    </span>
                </div>
            </div>

            <!-- Status & Payment Grid -->
            <div style="display: grid; grid-template-columns: repeat(3, 1fr); gap: 1rem; margin-bottom: 1rem;">
                <div style="background: white; border-radius: 8px; padding: 1rem; box-shadow: 0 1px 3px rgba(0,0,0,0.1);">
                    <div style="font-size: 0.85rem; color: #666; margin-bottom: 0.25rem;">Order Status</div>
                    <div style="font-size: 1.1rem; font-weight: 600; color: #2c3e50;"><?php echo str_replace('_', ' ', ucfirst($order['order_status'])); ?></div>
                </div>
                <div style="background: white; border-radius: 8px; padding: 1rem; box-shadow: 0 1px 3px rgba(0,0,0,0.1);">
                    <div style="font-size: 0.85rem; color: #666; margin-bottom: 0.25rem;">Payment Status</div>
                    <div style="font-size: 1.1rem; font-weight: 600; color: #2c3e50;"><?php echo str_replace('_', ' ', ucfirst($order['payment_status'])); ?></div>
                </div>
                <div style="background: white; border-radius: 8px; padding: 1rem; box-shadow: 0 1px 3px rgba(0,0,0,0.1);">
                    <div style="font-size: 0.85rem; color: #666; margin-bottom: 0.25rem;">Payment Method</div>
                    <div style="font-size: 1.1rem; font-weight: 600; color: #2c3e50;"><?php echo ucfirst(str_replace('_', ' ', $order['payment_method'] ?? 'Cash on Delivery')); ?></div>
                </div>
            </div>

            <!-- Customer & Delivery Info -->
            <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 1rem; margin-bottom: 1rem;">
                <div style="background: white; border-radius: 8px; padding: 1rem; box-shadow: 0 1px 3px rgba(0,0,0,0.1);">
                    <div style="font-size: 1rem; font-weight: 600; margin-bottom: 0.75rem; color: #2c3e50;">Customer Information</div>
                    <div style="margin-bottom: 0.5rem;">
                        <div style="font-weight: 600; color: #2c3e50;"><?php echo htmlspecialchars($order['buyer_name'] ?? 'N/A'); ?></div>
                        <div style="font-size: 0.9rem; color: #666;"><?php echo htmlspecialchars($order['buyer_farm'] ?? 'N/A'); ?></div>
                    </div>
                    <div style="font-size: 0.9rem; color: #666; line-height: 1.6;">
                        <div><?php echo htmlspecialchars($order['buyer_contact'] ?? 'N/A'); ?></div>
                        <div> <?php echo htmlspecialchars($order['buyer_email'] ?? 'N/A'); ?></div>
                    </div>
                </div>
                <div style="background: white; border-radius: 8px; padding: 1rem; box-shadow: 0 1px 3px rgba(0,0,0,0.1);">
                    <div style="font-size: 1rem; font-weight: 600; margin-bottom: 0.75rem; color: #2c3e50;"> Delivery Information</div>
                    <div style="font-size: 0.9rem; color: #666; line-height: 1.6;">
                        <?php echo nl2br(htmlspecialchars($order['delivery_address'] ?? 'N/A')); ?>
                    </div>
                </div>
                </div>
            </div>

            <!-- Order Items -->
            <div style="background: white; border-radius: 8px; padding: 1rem; box-shadow: 0 1px 3px rgba(0,0,0,0.1); margin-bottom: 1rem;">
                <div style="font-size: 1rem; font-weight: 600; margin-bottom: 0.75rem; color: #2c3e50;"> Order Items</div>
                <table style="width: 100%; border-collapse: collapse;">
                    <thead>
                        <tr style="background: #f8f9fa; border-bottom: 2px solid #dee2e6;">
                            <th style="padding: 0.5rem; text-align: left; font-size: 0.85rem; font-weight: 600; color: #666;">#</th>
                            <th style="padding: 0.5rem; text-align: left; font-size: 0.85rem; font-weight: 600; color: #666;">Product</th>
                            <th style="padding: 0.5rem; text-align: left; font-size: 0.85rem; font-weight: 600; color: #666;">Type</th>
                            <th style="padding: 0.5rem; text-align: right; font-size: 0.85rem; font-weight: 600; color: #666;">Quantity</th>
                            <th style="padding: 0.5rem; text-align: right; font-size: 0.85rem; font-weight: 600; color: #666;">Unit Price</th>
                            <th style="padding: 0.5rem; text-align: right; font-size: 0.85rem; font-weight: 600; color: #666;">Subtotal</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php $itemNum = 1; ?>
                        <?php foreach ($order['items'] as $item): ?>
                        <tr style="border-bottom: 1px solid #eee;">
                            <td style="padding: 0.5rem; font-size: 0.9rem;"><?php echo $itemNum; ?></td>
                            <td style="padding: 0.5rem; font-size: 0.9rem; font-weight: 500;"><?php echo htmlspecialchars($item['product_name'] ?? 'N/A'); ?></td>
                            <td style="padding: 0.5rem; font-size: 0.9rem; color: #666;"><?php echo htmlspecialchars($item['feed_type'] ?? 'N/A'); ?></td>
                            <td style="padding: 0.5rem; text-align: right; font-size: 0.9rem;"><?php echo number_format($item['quantity_kg'] ?? 0, 2); ?> kg</td>
                            <td style="padding: 0.5rem; text-align: right; font-size: 0.9rem;">₱<?php echo number_format($item['unit_price'] ?? 0, 2); ?></td>
                            <td style="padding: 0.5rem; text-align: right; font-size: 0.9rem; font-weight: 500;">₱<?php echo number_format($item['subtotal'] ?? 0, 2); ?></td>
                        </tr>
                        <?php $itemNum++; ?>
                        <?php endforeach; ?>
                        <tr style="background: #f8f9fa; font-weight: 600;">
                            <td colspan="5" style="padding: 0.75rem; text-align: right; font-size: 1rem;">TOTAL</td>
                            <td style="padding: 0.75rem; text-align: right; font-size: 1.1rem; color: #27ae60;">₱<?php echo number_format($order['total_amount'], 2); ?></td>
                        </tr>
                    </tbody>
                </table>
            </div>

            <!-- Action Buttons -->
            <div style="display: flex; gap: 0.75rem; justify-content: space-between; align-items: center;">
                <a href="/LechGo_Final/public/supplier/my-orders" class="btn btn-secondary" style="padding: 0.6rem 1.2rem;">← Back to Orders</a>
                <div style="display: flex; gap: 0.75rem;">
                    <?php if ($order['order_status'] === 'pending'): ?>
                        <button type="button" class="btn btn-primary" id="acceptOrderBtn" data-order-id="<?php echo $order['id']; ?>" style="padding: 0.6rem 1.5rem;">✓ Accept Order</button>
                    <?php elseif ($order['order_status'] === 'confirmed'): ?>
                        <a href="/LechGo_Final/public/supplier/receipt/<?php echo $order['id']; ?>" class="btn btn-primary" target="_blank" style="padding: 0.6rem 1.5rem;">📄 View Receipt</a>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </main>

    </div>

    <!-- Receipt Modal -->
    <div class="modal" id="receiptModal" style="display: none; position: fixed; top: 0; left: 0; width: 100%; height: 100%; background: rgba(0,0,0,0.5); z-index: 9999; align-items: center; justify-content: center;">
        <div class="modal-content" style="max-width: 700px; background: white; border-radius: 8px; max-height: 90vh; overflow: auto;">
            <div class="modal-header" style="padding: 1rem; border-bottom: 1px solid #ddd; display: flex; justify-content: space-between; align-items: center;">
                <h2 style="margin: 0;">✓ Order Confirmed!</h2>
                <button class="modal-close" id="closeReceiptModal" style="background: none; border: none; font-size: 1.5rem; cursor: pointer;">&times;</button>
            </div>
            <div class="modal-body" style="padding: 1.5rem;">
                <p style="text-align: center; color: #27ae60; font-size: 1.1rem; margin-bottom: 1rem;">
                    Order has been confirmed and customer has been notified.
                </p>
                <iframe id="receiptFrame" style="width: 100%; height: 500px; border: 1px solid #ddd; border-radius: 4px;"></iframe>
            </div>
            <div class="modal-footer" style="padding: 1rem; border-top: 1px solid #ddd; display: flex; gap: 0.5rem; justify-content: flex-end;">
                <button class="btn btn-secondary" id="closeReceiptBtn">Close</button>
                <a href="#" id="viewReceiptBtn" class="btn btn-primary" target="_blank"> Open Receipt in New Tab</a>
            </div>
        </div>
    </div>

    <!-- Logout Confirmation Modal -->
    <div class="modal" id="logoutModal">
        <div class="modal-content">
            <div class="modal-header">
                <h2>Confirm Logout</h2>
                <button class="modal-close" id="closeLogoutModal">&times;</button>
            </div>
            <div class="modal-body">
                <p>Are you sure you want to logout?</p>
            </div>
            <div class="modal-footer">
                <button class="btn btn-secondary" id="cancelLogout">Cancel</button>
                <button class="btn btn-primary" id="confirmLogout">Yes, Logout</button>
            </div>
        </div>
    </div>

    <script src="/LechGo_Final/public/script.js"></script>
    <script>
        // Accept Order with Modal
        const acceptOrderBtn = document.getElementById('acceptOrderBtn');
        if (acceptOrderBtn) {
            acceptOrderBtn.addEventListener('click', async function() {
                const orderId = this.getAttribute('data-order-id');
                
                if (!confirm('Are you sure you want to accept this order?')) {
                    return;
                }

                this.disabled = true;
                this.innerHTML = '⏳ Processing...';

                try {
                    const formData = new FormData();
                    formData.append('order_id', orderId);

                    const response = await fetch('/LechGo_Final/public/supplier/accept-order', {
                        method: 'POST',
                        body: formData,
                        headers: {
                            'X-Requested-With': 'XMLHttpRequest'
                        }
                    });

                    const data = await response.json();

                    if (data.success) {
                        // Show receipt modal
                        const modal = document.getElementById('receiptModal');
                        const iframe = document.getElementById('receiptFrame');
                        const viewBtn = document.getElementById('viewReceiptBtn');
                        
                        iframe.src = data.receipt_url;
                        viewBtn.href = data.receipt_url;
                        modal.style.display = 'flex';
                    } else {
                        alert('Error: ' + (data.error || 'Failed to accept order'));
                        this.disabled = false;
                        this.innerHTML = '✓ Accept Order';
                    }
                } catch (error) {
                    console.error('Error:', error);
                    alert('Error accepting order. Please try again.');
                    this.disabled = false;
                    this.innerHTML = '✓ Accept Order';
                }
            });
        }

        // Close receipt modal
        document.getElementById('closeReceiptModal')?.addEventListener('click', function() {
            document.getElementById('receiptModal').style.display = 'none';
            window.location.reload();
        });

        document.getElementById('closeReceiptBtn')?.addEventListener('click', function() {
            document.getElementById('receiptModal').style.display = 'none';
            window.location.reload();
        });
    </script>
</body>
</html>

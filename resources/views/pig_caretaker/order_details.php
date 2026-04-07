<?php
/**
 * Pig Caretaker - Order Details
 * View and respond to order details
 */

// Check if user is authenticated as caretaker
$user = $_SESSION['user'] ?? null;
if (!$user || $user['role'] !== 'pig_caretaker') {
    header('Location: ' . '/LechGo_Final/public/login');
    exit;
}

$feedOrder = new FeedOrder($GLOBALS['conn']);
$order = $feedOrder->getOrderById($order_id);

if (!$order) {
    $_SESSION['error'] = 'Order not found';
    header('Location: ' . '/LechGo_Final/public/pig-caretaker/orders');
    exit;
}

// Verify caretaker owns this order
$caretaker = new PigCaretaker($GLOBALS['conn']);
if (!$caretaker->findByUserId($user['id']) || $caretaker->id !== $order['caretaker_id']) {
    $_SESSION['error'] = 'Unauthorized';
    header('Location: ' . '/LechGo_Final/public/pig-caretaker/orders');
    exit;
}

?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Order Details - LechGO</title>
    <link rel="stylesheet" href="/LechGo_Final/public/styles.css">
    <style>
        .order-detail-container {
            max-width: 900px;
            margin: 0 auto;
        }

        .detail-section {
            background: linear-gradient(135deg, var(--white) 0%, var(--light-red) 100%);
            border: 2px solid var(--primary-red);
            border-radius: var(--radius-lg);
            padding: var(--spacing-lg);
            margin-bottom: var(--spacing-lg);
        }

        .section-title {
            font-size: 1.2rem;
            font-weight: 700;
            color: var(--primary-red);
            margin-bottom: var(--spacing-md);
            padding-bottom: var(--spacing-md);
            border-bottom: 2px solid var(--light-red);
        }

        .detail-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
            gap: var(--spacing-lg);
            margin-bottom: var(--spacing-lg);
        }

        .detail-box {
            background-color: var(--white);
            padding: var(--spacing-md);
            border-radius: var(--radius-md);
            border-left: 4px solid var(--primary-red);
        }

        .detail-label {
            font-size: 0.85rem;
            font-weight: 600;
            color: var(--text-gray);
            text-transform: uppercase;
            letter-spacing: 0.3px;
            margin-bottom: 4px;
        }

        .detail-value {
            font-size: 1rem;
            font-weight: 600;
            color: var(--primary-red);
        }

        .items-table {
            width: 100%;
            border-collapse: collapse;
            background-color: var(--white);
            border-radius: var(--radius-md);
            overflow: hidden;
        }

        .items-table thead {
            background-color: var(--light-red);
            border-bottom: 2px solid var(--primary-red);
        }

        .items-table th {
            padding: var(--spacing-md);
            text-align: left;
            color: var(--primary-red);
            font-weight: 700;
        }

        .items-table td {
            padding: var(--spacing-md);
            border-bottom: 1px solid var(--light-gray);
        }

        .items-table tr:hover {
            background-color: var(--light-red);
        }

        .status-badge {
            display: inline-block;
            padding: 4px 12px;
            border-radius: var(--radius-md);
            font-size: 0.85rem;
            font-weight: 600;
            text-transform: capitalize;
        }

        .status-pending {
            background-color: #FFF3E0;
            color: #F57C00;
        }

        .status-reviewing_payment {
            background-color: #E3F2FD;
            color: #1565C0;
        }

        .status-accepted {
            background-color: #E8F5E9;
            color: #2E7D32;
        }

        .status-rejected {
            background-color: #FFEBEE;
            color: #C62828;
        }

        .total-row {
            background-color: var(--light-red) !important;
            font-weight: 700;
            font-size: 1.1rem;
        }

        .action-buttons {
            display: flex;
            gap: var(--spacing-md);
            justify-content: space-between;
            margin-top: var(--spacing-lg);
        }

        .response-box {
            background-color: #F5F5F5;
            padding: var(--spacing-md);
            border-radius: var(--radius-md);
            border-left: 4px solid var(--primary-red);
        }

        .alert-box {
            background-color: #E3F2FD;
            border-left: 4px solid #1565C0;
            padding: var(--spacing-md);
            border-radius: var(--radius-md);
            margin-bottom: var(--spacing-lg);
        }

        .alert-title {
            font-weight: 700;
            color: #1565C0;
            margin-bottom: 4px;
        }

        .alert-text {
            color: var(--text-gray);
            font-size: 0.95rem;
        }
    </style>
</head>
<body class="body-bg">
    <div class="container mt-lg">
        <div class="order-detail-container">
            <!-- Header -->
            <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: var(--spacing-lg);">
                <div>
                    <h1 class="page-title">Order #<?php echo str_pad($order['id'], 6, "0", STR_PAD_LEFT); ?></h1>
                    <p class="text-gray"><?php echo date('M d, Y g:i A', strtotime($order['created_at'])); ?></p>
                </div>
                <span class="status-badge status-<?php echo $order['order_status']; ?>">
                    <?php echo str_replace('_', ' ', ucfirst($order['order_status'])); ?>
                </span>
            </div>

            <!-- Alert if response needed -->
            <?php if (($order['order_status'] === 'pending' || $order['order_status'] === 'reviewing_payment') && !$order['caretaker_response']): ?>
            <div class="alert-box">
                <div class="alert-title">⚠️ Action Required</div>
                <div class="alert-text">
                    This order needs your response. Please review the items and payment details below, then accept or reject.
                </div>
            </div>
            <?php endif; ?>

            <!-- Order Status & Payment Info -->
            <div class="detail-section">
                <div class="section-title">Order Status</div>
                <div class="detail-grid">
                    <div class="detail-box">
                        <div class="detail-label">Order Status</div>
                        <div class="detail-value"><?php echo str_replace('_', ' ', ucfirst($order['order_status'])); ?></div>
                    </div>
                    <div class="detail-box">
                        <div class="detail-label">Payment Status</div>
                        <div class="detail-value"><?php echo str_replace('_', ' ', ucfirst($order['payment_status'])); ?></div>
                    </div>
                    <div class="detail-box">
                        <div class="detail-label">Payment Method</div>
                        <div class="detail-value"><?php echo ucfirst(str_replace('_', ' ', $order['payment_method'] ?? 'Cash on Delivery')); ?></div>
                    </div>
                </div>
            </div>

            <!-- Supplier Info -->
            <div class="detail-section">
                <div class="section-title">📦 Supplier Information</div>
                <div class="detail-grid">
                    <div class="detail-box">
                        <div class="detail-label">Supplier Name</div>
                        <div class="detail-value"><?php echo htmlspecialchars($order['supplier_name']); ?></div>
                    </div>
                    <div class="detail-box">
                        <div class="detail-label">Farm Name</div>
                        <div class="detail-value"><?php echo htmlspecialchars($order['supplier_farm']); ?></div>
                    </div>
                    <div class="detail-box">
                        <div class="detail-label">Contact Number</div>
                        <div class="detail-value">📞 <?php echo htmlspecialchars($order['supplier_contact']); ?></div>
                    </div>
                    <div class="detail-box">
                        <div class="detail-label">Email</div>
                        <div class="detail-value"><?php echo htmlspecialchars($order['supplier_email']); ?></div>
                    </div>
                </div>
            </div>

            <!-- Order Items -->
            <div class="detail-section">
                <div class="section-title">Order Items</div>
                <table class="items-table">
                    <thead>
                        <tr>
                            <th>#</th>
                            <th>Feed Type</th>
                            <th>Quantity</th>
                            <th>Unit Price</th>
                            <th>Subtotal</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php $itemNum = 1; ?>
                        <?php foreach ($order['items'] as $item): ?>
                        <tr>
                            <td><?php echo $itemNum; ?></td>
                            <td><?php echo htmlspecialchars($item['feed_type']); ?></td>
                            <td><?php echo number_format($item['quantity_ordered_kg'], 2); ?> kg</td>
                            <td>₱<?php echo number_format($item['unit_price'], 2); ?>/kg</td>
                            <td>₱<?php echo number_format($item['subtotal'], 2); ?></td>
                        </tr>
                        <?php $itemNum++; ?>
                        <?php endforeach; ?>
                        <tr class="total-row">
                            <td colspan="4" style="text-align: right;">TOTAL</td>
                            <td>₱<?php echo number_format($order['total_amount'], 2); ?></td>
                        </tr>
                    </tbody>
                </table>
            </div>

            <!-- Notes -->
            <?php if ($order['notes']): ?>
            <div class="detail-section">
                <div class="section-title">Order Notes</div>
                <div class="response-box">
                    <p><?php echo htmlspecialchars($order['notes']); ?></p>
                </div>
            </div>
            <?php endif; ?>

            <!-- Your Response -->
            <?php if ($order['caretaker_response']): ?>
            <div class="detail-section">
                <div class="section-title">Your Response</div>
                <div class="response-box">
                    <div style="font-weight: 600; color: var(--primary-red); margin-bottom: var(--spacing-sm);">
                        ✓ <?php echo str_replace('_', ' ', ucfirst($order['order_status'])); ?> on <?php echo date('M d, Y g:i A', strtotime($order['caretaker_response_date'])); ?>
                    </div>
                    <div style="margin-top: var(--spacing-sm); color: var(--text-gray);">
                        <?php echo htmlspecialchars($order['caretaker_response']) ?: '(No message provided)'; ?>
                    </div>
                </div>
            </div>
            <?php endif; ?>

            <!-- Action Buttons -->
            <div class="action-buttons">
                <?php if ($order['order_status'] === 'accepted' || $order['order_status'] === 'completed'): ?>
                    <a href="/LechGo_Final/public/order-receipt/<?php echo $order['id']; ?>" class="btn btn-primary">📄 View Receipt</a>
                <?php endif; ?>
                
                <?php if (($order['order_status'] === 'pending' || $order['order_status'] === 'reviewing_payment') && !$order['caretaker_response']): ?>
                    <button class="btn btn-primary" onclick="respondToOrder(<?php echo $order['id']; ?>, 'accept')">✓ Accept Order</button>
                    <button class="btn btn-secondary" onclick="respondToOrder(<?php echo $order['id']; ?>, 'reject')">✗ Reject Order</button>
                <?php endif; ?>
                
                <a href="/LechGo_Final/public/pig-caretaker/orders" class="btn btn-secondary">← Back to Orders</a>
            </div>
        </div>
    </div>

    <!-- Respond Modal -->
    <div class="respond-modal" id="respondModal">
        <div class="respond-form">
            <div class="modal-header">Respond to Order</div>
            <form method="POST" action="/LechGo_Final/public/pig-caretaker/respond-order">
                <input type="hidden" name="order_id" id="modal_order_id" value="<?php echo $order['id']; ?>">
                <input type="hidden" name="response" id="modal_response">

                <div class="form-group">
                    <label class="form-label">Message (Optional)</label>
                    <textarea name="response_text" class="form-input" placeholder="Add a message for the supplier..." style="height: 80px;"></textarea>
                </div>

                <div class="modal-actions">
                    <button type="button" class="btn btn-secondary" onclick="closeRespondModal()">Cancel</button>
                    <button type="submit" class="btn btn-primary">Send Response</button>
                </div>
            </form>
        </div>
    </div>

    <style>
        .respond-modal {
            display: none;
            position: fixed;
            top: 0;
            left: 0;
            right: 0;
            bottom: 0;
            background-color: rgba(0, 0, 0, 0.5);
            z-index: 1000;
            align-items: center;
            justify-content: center;
        }

        .respond-modal.active {
            display: flex;
        }

        .respond-form {
            background: white;
            padding: var(--spacing-lg);
            border-radius: var(--radius-lg);
            max-width: 400px;
            width: 90%;
            box-shadow: var(--shadow-lg);
        }

        .modal-header {
            font-size: 1.2rem;
            font-weight: 700;
            color: var(--primary-red);
            margin-bottom: var(--spacing-md);
        }

        .modal-actions {
            display: flex;
            gap: var(--spacing-sm);
            margin-top: var(--spacing-lg);
        }

        .modal-actions .btn {
            flex: 1;
        }
    </style>

    <script>
        function respondToOrder(orderId, response) {
            document.getElementById('modal_order_id').value = orderId;
            document.getElementById('modal_response').value = response;
            document.getElementById('respondModal').classList.add('active');
        }

        function closeRespondModal() {
            document.getElementById('respondModal').classList.remove('active');
        }

        document.getElementById('respondModal').addEventListener('click', function(e) {
            if (e.target === this) {
                closeRespondModal();
            }
        });
    </script>
</body>
</html>

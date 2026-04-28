<?php
/**
 * Pig Caretaker - Received Orders
 * View and manage orders received from suppliers
 */

// Check if user is authenticated as caretaker
$user = $_SESSION['user'] ?? null;
if (!$user || $user['role'] !== 'pig_caretaker') {
    header('Location: ' . '/LechGo_Final/public/login');
    exit;
}

// Get caretaker data
$caretaker = new PigCaretaker($GLOBALS['conn']);
if (!$caretaker->findByUserId($user['id'])) {
    $_SESSION['error'] = 'Caretaker profile not found';
    header('Location: ' . '/LechGo_Final/public/home');
    exit;
}

// Get all orders for this caretaker
$feedOrder = new FeedOrder($GLOBALS['conn']);
$orders = $feedOrder->getCaretakerOrders($caretaker->id);

// Group orders by status
$orders_by_status = [
    'pending' => [],
    'reviewing_payment' => [],
    'accepted' => [],
    'rejected' => [],
    'completed' => [],
    'cancelled' => []
];

foreach ($orders as $order) {
    $orders_by_status[$order['order_status']][] = $order;
}


$currentPage = 'orders';

?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Received Orders - LechGO</title>
    <link rel="stylesheet" href="/LechGo_Final/public/styles.css">
    <style>
        .orders-container {
            margin-bottom: var(--spacing-lg);
        }

        .status-filter {
            display: flex;
            gap: var(--spacing-sm);
            margin-bottom: var(--spacing-lg);
            flex-wrap: wrap;
        }

        .status-btn {
            padding: 8px 16px;
            border: 2px solid var(--gray);
            background-color: var(--white);
            border-radius: var(--radius-md);
            cursor: pointer;
            transition: all 0.3s ease;
            font-weight: 600;
            font-size: 0.9rem;
        }

        .status-btn:hover {
            border-color: var(--primary-red);
            color: var(--primary-red);
        }

        .status-btn.active {
            border-color: var(--primary-red);
            background-color: var(--primary-red);
            color: white;
        }

        .order-card {
            background: linear-gradient(135deg, var(--white) 0%, var(--light-red) 100%);
            border: 2px solid var(--primary-red);
            border-radius: var(--radius-lg);
            padding: var(--spacing-lg);
            margin-bottom: var(--spacing-md);
            transition: all 0.3s ease;
        }

        .order-card:hover {
            box-shadow: var(--shadow-lg);
            transform: translateY(-2px);
        }

        .order-header {
            display: flex;
            justify-content: space-between;
            align-items: start;
            margin-bottom: var(--spacing-md);
            padding-bottom: var(--spacing-md);
            border-bottom: 2px solid var(--light-red);
        }

        .order-id {
            font-size: 1.1rem;
            font-weight: 700;
            color: var(--primary-red);
        }

        .order-date {
            font-size: 0.85rem;
            color: var(--text-gray);
        }

        .order-status-badge {
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

        .order-details {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: var(--spacing-md);
            margin-bottom: var(--spacing-md);
            background-color: var(--white);
            padding: var(--spacing-md);
            border-radius: var(--radius-md);
        }

        .detail-item {
            display: flex;
            flex-direction: column;
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
            font-weight: 600;
            color: var(--primary-red);
            font-size: 1rem;
        }

        .order-actions {
            display: flex;
            gap: var(--spacing-sm);
        }

        .order-actions .btn {
            flex: 1;
            font-size: 0.9rem;
        }

        .empty-state {
            text-align: center;
            padding: var(--spacing-lg);
            background-color: var(--light-red);
            border-radius: var(--radius-lg);
            color: var(--text-gray);
        }

        .empty-state-icon {
            font-size: 3rem;
            margin-bottom: var(--spacing-sm);
        }

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
</head>
<body>
    <div class="dashboard-layout">
        <?php include __DIR__ . '/../layouts/sidebar.php'; ?>
        
        <main class="dashboard-main">
        <div style="max-width: 1000px; margin: 0 auto;">
            <!-- Header -->
            <div style="margin-bottom: var(--spacing-lg);">
                <h1 class="page-title">Received Orders</h1>
                <p class="text-gray">Manage orders received from feed suppliers</p>
            </div>

            <!-- Alerts -->
            <?php if (isset($_SESSION['success'])): ?>
            <div style="background-color: var(--success); color: white; padding: var(--spacing-md); border-radius: var(--radius-md); margin-bottom: var(--spacing-lg);">
                <?php echo htmlspecialchars($_SESSION['success']); unset($_SESSION['success']); ?>
            </div>
            <?php endif; ?>

            <!-- Status Filter -->
            <div class="status-filter">
                <button class="status-btn active" onclick="filterOrders('all')">All Orders</button>
                <button class="status-btn" onclick="filterOrders('pending')">Pending Response</button>
                <button class="status-btn" onclick="filterOrders('reviewing_payment')">Reviewing Payment</button>
                <button class="status-btn" onclick="filterOrders('accepted')">Accepted</button>
                <button class="status-btn" onclick="filterOrders('rejected')">Rejected</button>
            </div>

            <!-- Orders Display -->
            <div class="orders-container">
                <?php
                    $has_orders = false;
                    foreach ($orders_by_status as $status => $status_orders):
                        if (!empty($status_orders)):
                            $has_orders = true;
                ?>
                            <div class="status-section" data-status="<?php echo $status; ?>">
                                <?php foreach ($status_orders as $order): ?>
                                    <div class="order-card" data-status="<?php echo $order['order_status']; ?>">
                                        <div class="order-header">
                                            <div>
                                                <div class="order-id">Order #<?php echo str_pad($order['id'], 6, "0", STR_PAD_LEFT); ?></div>
                                                <div class="order-date"><?php echo date('M d, Y g:i A', strtotime($order['created_at'])); ?></div>
                                            </div>
                                            <span class="order-status-badge status-<?php echo $order['order_status']; ?>">
                                                <?php echo str_replace('_', ' ', ucfirst($order['order_status'])); ?>
                                            </span>
                                        </div>

                                        <div class="order-details">
                                            <div class="detail-item">
                                                <span class="detail-label">From Supplier</span>
                                                <span class="detail-value"><?php echo htmlspecialchars($order['supplier_name']); ?></span>
                                            </div>
                                            <div class="detail-item">
                                                <span class="detail-label">Farm</span>
                                                <span class="detail-value"><?php echo htmlspecialchars($order['supplier_farm']); ?></span>
                                            </div>
                                            <div class="detail-item">
                                                <span class="detail-label">Total Amount</span>
                                                <span class="detail-value">₱<?php echo number_format($order['total_amount'], 2); ?></span>
                                            </div>
                                            <div class="detail-item">
                                                <span class="detail-label">Payment Method</span>
                                                <span class="detail-value"><?php echo ucfirst(str_replace('_', ' ', $order['payment_method'] ?? 'Not Set')); ?></span>
                                            </div>
                                        </div>

                                        <div class="order-actions">
                                            <a href="/LechGo_Final/public/pig-caretaker/order-details/<?php echo $order['id']; ?>" class="btn btn-primary">View Details</a>
                                            <?php if ($order['order_status'] === 'pending' || $order['order_status'] === 'reviewing_payment'): ?>
                                                <button class="btn btn-secondary" onclick="openRespondModal(<?php echo $order['id']; ?>, '<?php echo htmlspecialchars($order['supplier_name']); ?>')">✓ Respond</button>
                                            <?php endif; ?>
                                            <?php if ($order['order_status'] === 'accepted' || $order['order_status'] === 'completed'): ?>
                                                <a href="/LechGo_Final/public/order-receipt/<?php echo $order['id']; ?>" class="btn btn-secondary">📄 Receipt</a>
                                            <?php endif; ?>
                                        </div>
                                    </div>
                                <?php endforeach; ?>
                            </div>
                        <?php endif; endforeach; ?>

                        <?php if (!$has_orders): ?>
                            <div class="empty-state">
                                <div class="empty-state-icon">📬</div>
                                <p>No orders received yet. Suppliers will send orders here once available.</p>
                            </div>
                        <?php endif; ?>
            </div>
        </div>

    <!-- Respond Modal -->
    <div class="respond-modal" id="respondModal">
        <div class="respond-form">
            <div class="modal-header">Respond to Order</div>
            <form method="POST" action="/LechGo_Final/public/pig-caretaker/respond-order">
                <input type="hidden" name="order_id" id="modal_order_id">

                <div class="form-group">
                    <label class="form-label">Response</label>
                    <div style="display: flex; gap: var(--spacing-md);">
                        <label style="display: flex; align-items: center; gap: 8px; cursor: pointer;">
                            <input type="radio" name="response" value="accept" required>
                            <span style="color: var(--success);">✓ Accept Order</span>
                        </label>
                        <label style="display: flex; align-items: center; gap: 8px; cursor: pointer;">
                            <input type="radio" name="response" value="reject" required>
                            <span style="color: var(--error);">✗ Reject Order</span>
                        </label>
                    </div>
                </div>

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

    <script>
        function filterOrders(status) {
            document.querySelectorAll('.status-btn').forEach(btn => btn.classList.remove('active'));
            event.target.classList.add('active');

            document.querySelectorAll('.order-card').forEach(card => {
                if (status === 'all' || card.dataset.status === status) {
                    card.style.display = 'block';
                } else {
                    card.style.display = 'none';
                }
            });
        }

        function openRespondModal(orderId, supplierName) {
            document.getElementById('modal_order_id').value = orderId;
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

    </main>

    </div>
</body>
</html>

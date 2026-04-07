<?php
/**
 * Order Receipt
 * Displays a printable receipt for completed orders
 */

$feedOrder = new FeedOrder($GLOBALS['conn']);
$order = $feedOrder->getOrderById($order_id);
$receipt = $feedOrder->getReceiptByOrderId($order_id);

if (!$order) {
    http_response_code(404);
    echo "Order not found";
    exit;
}

// Verify user has access to this receipt
$user = $_SESSION['user'] ?? null;
if ($user) {
    if ($user['role'] === 'supplier') {
        $supplier = new FeedSupplier($GLOBALS['conn']);
        if (!$supplier->findByUserId($user['id']) || $supplier->id !== $order['supplier_id']) {
            http_response_code(403);
            echo "Unauthorized";
            exit;
        }
    } elseif ($user['role'] === 'pig_caretaker') {
        $caretaker = new PigCaretaker($GLOBALS['conn']);
        if (!$caretaker->findByUserId($user['id']) || $caretaker->id !== $order['caretaker_id']) {
            http_response_code(403);
            echo "Unauthorized";
            exit;
        }
    }
}

?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Order Receipt - LechGO</title>
    <link rel="stylesheet" href="/LechGo_Final/public/styles.css">
    <style>
        .receipt-container {
            max-width: 800px;
            margin: 0 auto;
            padding: var(--spacing-lg);
        }

        .receipt-header {
            text-align: center;
            padding: var(--spacing-lg);
            border-bottom: 3px solid var(--primary-red);
            margin-bottom: var(--spacing-lg);
        }

        .receipt-title {
            font-size: 2rem;
            font-weight: 700;
            color: var(--primary-red);
            margin: 0;
            margin-bottom: 8px;
        }

        .receipt-subtitle {
            font-size: 0.95rem;
            color: var(--text-gray);
            margin: 0;
        }

        .receipt-number {
            font-size: 1.3rem;
            font-weight: 700;
            color: var(--primary-red);
            margin-top: var(--spacing-md);
        }

        .receipt-date {
            color: var(--text-gray);
            font-size: 0.9rem;
        }

        .section {
            margin-bottom: var(--spacing-lg);
            padding: var(--spacing-md);
            border: 1px solid var(--light-red);
            border-radius: var(--radius-md);
            background-color: var(--white);
        }

        .section-title {
            font-weight: 700;
            color: var(--primary-red);
            font-size: 1rem;
            margin-bottom: var(--spacing-sm);
            border-bottom: 2px solid var(--light-red);
            padding-bottom: var(--spacing-sm);
        }

        .info-row {
            display: flex;
            justify-content: space-between;
            padding: 4px 0;
            border-bottom: 1px solid var(--light-gray);
        }

        .info-row:last-child {
            border-bottom: none;
        }

        .info-label {
            font-weight: 600;
            color: var(--text-gray);
        }

        .info-value {
            color: var(--dark-gray);
        }

        .items-table {
            width: 100%;
            border-collapse: collapse;
            margin-bottom: var(--spacing-md);
        }

        .items-table thead {
            background-color: var(--light-red);
            border-bottom: 2px solid var(--primary-red);
        }

        .items-table th {
            padding: 8px;
            text-align: left;
            font-weight: 700;
            color: var(--primary-red);
            font-size: 0.9rem;
        }

        .items-table td {
            padding: 8px;
            border-bottom: 1px solid var(--light-gray);
            font-size: 0.9rem;
        }

        .items-table tr:hover {
            background-color: var(--light-red);
        }

        .total-row {
            background-color: var(--light-red);
            font-weight: 700;
        }

        .total-row td {
            padding: 12px 8px;
            border-top: 2px solid var(--primary-red);
            border-bottom: 2px solid var(--primary-red);
        }

        .receipt-footer {
            text-align: center;
            padding: var(--spacing-lg);
            border-top: 3px solid var(--primary-red);
            margin-top: var(--spacing-lg);
            color: var(--text-gray);
            font-size: 0.9rem;
        }

        .action-buttons {
            display: flex;
            gap: var(--spacing-md);
            justify-content: center;
            margin-top: var(--spacing-lg);
            no-print: true;
        }

        @media print {
            body {
                background-color: white;
                padding: 0;
                margin: 0;
            }

            .action-buttons,
            .back-button {
                display: none !important;
            }

            .receipt-container {
                padding: 0;
                margin: 0;
            }

            .section {
                page-break-inside: avoid;
                border-color: #333;
            }
        }

        .status-badge {
            display: inline-block;
            background-color: var(--light-red);
            color: var(--primary-red);
            padding: 4px 12px;
            border-radius: var(--radius-md);
            font-size: 0.85rem;
            font-weight: 600;
        }
    </style>
</head>
<body class="body-bg">
    <div class="container mt-lg">
        <div class="receipt-container">
            <!-- Receipt Header -->
            <div class="receipt-header">
                <h1 class="receipt-title">LechGO</h1>
                <p class="receipt-subtitle">Feed Supply Order Receipt</p>
                <div class="receipt-number">
                    <?php echo $receipt ? htmlspecialchars($receipt['receipt_data']['receipt_number']) : 'RCP-' . date('Ymd') . '-' . str_pad($order['id'], 5, "0", STR_PAD_LEFT); ?>
                </div>
                <div class="receipt-date">
                    <?php echo date('M d, Y g:i A', strtotime($order['created_at'])); ?>
                </div>
                <span class="status-badge">
                    <?php echo str_replace('_', ' ', ucfirst($order['order_status'])); ?>
                </span>
            </div>

            <!-- Order Information -->
            <div class="section">
                <div class="section-title">Order Information</div>
                <div class="info-row">
                    <span class="info-label">Order Number:</span>
                    <span class="info-value">#<?php echo str_pad($order['id'], 6, "0", STR_PAD_LEFT); ?></span>
                </div>
                <div class="info-row">
                    <span class="info-label">Order Date:</span>
                    <span class="info-value"><?php echo date('M d, Y g:i A', strtotime($order['created_at'])); ?></span>
                </div>
                <div class="info-row">
                    <span class="info-label">Order Status:</span>
                    <span class="info-value"><?php echo str_replace('_', ' ', ucfirst($order['order_status'])); ?></span>
                </div>
                <div class="info-row">
                    <span class="info-label">Payment Method:</span>
                    <span class="info-value"><?php echo ucfirst(str_replace('_', ' ', $order['payment_method'] ?? 'N/A')); ?></span>
                </div>
            </div>

            <!-- Supplier Information -->
            <div class="section">
                <div class="section-title">📦 From Supplier</div>
                <div class="info-row">
                    <span class="info-label">Supplier Name:</span>
                    <span class="info-value"><?php echo htmlspecialchars($order['supplier_name']); ?></span>
                </div>
                <div class="info-row">
                    <span class="info-label">Farm Name:</span>
                    <span class="info-value"><?php echo htmlspecialchars($order['supplier_farm']); ?></span>
                </div>
                <div class="info-row">
                    <span class="info-label">Contact:</span>
                    <span class="info-value"><?php echo htmlspecialchars($order['supplier_contact']); ?></span>
                </div>
                <div class="info-row">
                    <span class="info-label">Email:</span>
                    <span class="info-value"><?php echo htmlspecialchars($order['supplier_email']); ?></span>
                </div>
            </div>

            <!-- Caretaker Information -->
            <div class="section">
                <div class="section-title">➡️ To Caretaker</div>
                <div class="info-row">
                    <span class="info-label">Caretaker Name:</span>
                    <span class="info-value"><?php echo htmlspecialchars($order['caretaker_name']); ?></span>
                </div>
                <div class="info-row">
                    <span class="info-label">Farm Name:</span>
                    <span class="info-value"><?php echo htmlspecialchars($order['caretaker_farm']); ?></span>
                </div>
                <div class="info-row">
                    <span class="info-label">Contact:</span>
                    <span class="info-value"><?php echo htmlspecialchars($order['caretaker_contact']); ?></span>
                </div>
                <div class="info-row">
                    <span class="info-label">Email:</span>
                    <span class="info-value"><?php echo htmlspecialchars($order['caretaker_email']); ?></span>
                </div>
            </div>

            <!-- Order Items -->
            <div class="section">
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
                            <td>₱<?php echo number_format($item['unit_price'], 2); ?></td>
                            <td>₱<?php echo number_format($item['subtotal'], 2); ?></td>
                        </tr>
                        <?php $itemNum++; ?>
                        <?php endforeach; ?>
                        <tr class="total-row">
                            <td colspan="4" style="text-align: right;">TOTAL AMOUNT:</td>
                            <td>₱<?php echo number_format($order['total_amount'], 2); ?></td>
                        </tr>
                    </tbody>
                </table>
            </div>

            <!-- Notes -->
            <?php if ($order['notes']): ?>
            <div class="section">
                <div class="section-title">Order Notes</div>
                <p style="margin: 0; color: var(--dark-gray); font-size: 0.9rem;">
                    <?php echo htmlspecialchars($order['notes']); ?>
                </p>
            </div>
            <?php endif; ?>

            <!-- Footer -->
            <div class="receipt-footer">
                <p style="margin: 0; margin-bottom: 4px;">Thank you for your business!</p>
                <p style="margin: 0; font-size: 0.85rem;">This is an automated receipt. For inquiries, please contact the supplier or caretaker directly.</p>
                <p style="margin: 4px 0 0 0; margin-top: var(--spacing-sm); font-size: 0.85rem; color: #999;">
                    Printed on <?php echo date('M d, Y g:i A'); ?>
                </p>
            </div>

            <!-- Action Buttons -->
            <div class="action-buttons">
                <button class="btn btn-primary" onclick="window.print()">🖨️ Print Receipt</button>
                <button class="btn btn-secondary" onclick="history.back()">← Go Back</button>
            </div>
        </div>
    </div>
</body>
</html>

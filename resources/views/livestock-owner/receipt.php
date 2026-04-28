<?php
$order = $GLOBALS['receipt_order'];
$items = $GLOBALS['receipt_items'];
$user = $_SESSION['user'];
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Order Receipt #<?php echo htmlspecialchars($order['order_number']); ?> - LechGO</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.8/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="/LechGo_Final/public/styles.css">
    <style>
        body {
            background: #f5f5f5;
        }

        .receipt-container {
            max-width: 800px;
            margin: 20px auto;
            background: white;
            border-radius: 8px;
            box-shadow: 0 2px 8px rgba(0,0,0,0.1);
            overflow: hidden;
        }

        .receipt-header {
            background: linear-gradient(135deg, #c5bbbb 0%, #c9a39e 100%);
            color: white;
            padding: 30px;
            text-align: center;
        }

        .receipt-header h1 {
            margin: 0 0 10px 0;
            font-size: 28px;
        }

        .receipt-header p {
            margin: 5px 0;
            opacity: 0.9;
        }

        .receipt-body {
            padding: 30px;
        }

        .receipt-section {
            margin-bottom: 30px;
        }

        .section-title {
            font-size: 14px;
            font-weight: 700;
            text-transform: uppercase;
            color: #666;
            margin-bottom: 12px;
            padding-bottom: 10px;
            border-bottom: 2px solid #ecf0f1;
        }

        .info-row {
            display: flex;
            justify-content: space-between;
            margin-bottom: 8px;
            font-size: 14px;
        }

        .info-label {
            color: #666;
            font-weight: 600;
        }

        .info-value {
            color: #2c3e50;
            text-align: right;
        }

        .items-table {
            width: 100%;
            border-collapse: collapse;
            margin-bottom: 20px;
        }

        .items-table th {
            background: #ecf0f1;
            padding: 12px;
            text-align: left;
            font-weight: 600;
            color: #2c3e50;
            font-size: 13px;
            border-bottom: 2px solid #bdc3c7;
        }

        .items-table td {
            padding: 12px;
            border-bottom: 1px solid #ecf0f1;
            font-size: 13px;
        }

        .items-table tr:last-child td {
            border-bottom: 2px solid #bdc3c7;
        }

        .amount {
            text-align: right;
            color: #2ecc71;
            font-weight: 600;
        }

        .summary-row {
            display: flex;
            justify-content: space-between;
            padding: 12px 0;
            font-size: 14px;
        }

        .summary-row.total {
            border-top: 2px solid #ecf0f1;
            padding-top: 15px;
            margin-top: 15px;
            font-size: 16px;
            font-weight: 700;
            color: #2ecc71;
        }

        .status-info {
            background: #ecf0f1;
            padding: 15px;
            border-radius: 5px;
            margin: 20px 0;
        }

        .status-batch {
            display: inline-block;
            background: #3498db;
            color: white;
            padding: 5px 12px;
            border-radius: 12px;
            font-size: 12px;
            font-weight: 600;
            margin-right: 8px;
            margin-bottom: 8px;
        }

        .print-button {
            text-align: center;
            margin-top: 30px;
            padding-top: 30px;
            border-top: 2px solid #ecf0f1;
        }

        .btn-print {
            background: #3498db;
            color: white;
            border: none;
            padding: 10px 30px;
            border-radius: 5px;
            cursor: pointer;
            font-weight: 600;
            font-size: 14px;
            transition: background 0.3s;
        }

        .btn-print:hover {
            background: #2980b9;
        }

        @media print {
            body {
                background: white;
            }
            .print-button {
                display: none;
            }
            .receipt-container {
                margin: 0;
                box-shadow: none;
                border-radius: 0;
            }
        }
    </style>
</head>
<body>
    <div class="receipt-container">
        <!-- Receipt Header -->
        <div class="receipt-header">
            <h1> Order Receipt</h1>
            <p>Order #<?php echo htmlspecialchars($order['order_number']); ?></p>
            <p><?php echo date('F d, Y \a\t g:i A', strtotime($order['created_at'])); ?></p>
        </div>

        <!-- Receipt Body -->
        <div class="receipt-body">
            <!-- Order Info -->
            <div class="receipt-section">
                <div class="section-title">Order Information</div>
                <div class="info-row">
                    <span class="info-label">Order Number:</span>
                    <span class="info-value"><?php echo htmlspecialchars($order['order_number']); ?></span>
                </div>
                <div class="info-row">
                    <span class="info-label">Order Date:</span>
                    <span class="info-value"><?php echo date('F d, Y', strtotime($order['created_at'])); ?></span>
                </div>
                <div class="info-row">
                    <span class="info-label">Supplier:</span>
                    <span class="info-value"><?php echo htmlspecialchars($order['supplier_name'] ?? 'N/A'); ?></span>
                </div>
            </div>

            <!-- Customer Info -->
            <div class="receipt-section">
                <div class="section-title">Customer Information</div>
                <div class="info-row">
                    <span class="info-label">Farm Name:</span>
                    <span class="info-value"><?php echo htmlspecialchars($order['owner_farm'] ?? 'N/A'); ?></span>
                </div>
                <div class="info-row">
                    <span class="info-label">Location:</span>
                    <span class="info-value"><?php echo htmlspecialchars($order['owner_location'] ?? 'N/A'); ?></span>
                </div>
                <div class="info-row">
                    <span class="info-label">Customer Name:</span>
                    <span class="info-value"><?php echo htmlspecialchars($user['name'] ?? 'N/A'); ?></span>
                </div>
            </div>

            <!-- Order Items -->
            <div class="receipt-section">
                <div class="section-title">Order Items</div>
                <table class="items-table">
                    <thead>
                        <tr>
                            <th>#</th>
                            <th>Product</th>
                            <th>Type</th>
                            <th>Quantity (kg)</th>
                            <th>Unit Price</th>
                            <th class="amount">Subtotal</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php $itemNum = 1; ?>
                        <?php foreach ($items as $item): ?>
                            <tr>
                                <td><?php echo $itemNum; ?></td>
                                <td><?php echo htmlspecialchars($item['product_name']); ?></td>
                                <td><?php echo htmlspecialchars($item['feed_type']); ?></td>
                                <td><?php echo number_format($item['quantity_kg'], 2); ?></td>
                                <td>₱<?php echo number_format($item['unit_price'], 2); ?></td>
                                <td class="amount">₱<?php echo number_format($item['subtotal'], 2); ?></td>
                            </tr>
                            <?php $itemNum++; ?>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>

            <!-- Order Summary -->
            <div class="receipt-section">
                <div class="section-title">Order Summary</div>
                <div class="summary-row">
                    <span>Subtotal:</span>
                    <span>₱<?php echo number_format($order['total_amount'], 2); ?></span>
                </div>
                <div class="summary-row total">
                    <span>Total Amount:</span>
                    <span>₱<?php echo number_format($order['total_amount'], 2); ?></span>
                </div>
            </div>

            <!-- Status Info -->
            <div class="receipt-section">
                <div class="section-title">Status Information</div>
                <div class="status-info">
                    <div style="margin-bottom: 8px;">
                        <span class="status-batch" style="background: #3498db;">
                            Order: <?php echo ucfirst(str_replace('_', ' ', $order['order_status'])); ?>
                        </span>
                        <span class="status-batch" style="background: #2ecc71;">
                            Payment: <?php echo ucfirst($order['payment_status']); ?>
                        </span>
                        <span class="status-batch" style="background: #9b59b6;">
                            Delivery: <?php echo ucfirst(str_replace('_', ' ', $order['delivery_status'])); ?>
                        </span>
                    </div>
                </div>
            </div>

            <!-- Delivery Address -->
            <?php if ($order['delivery_address']): ?>
                <div class="receipt-section">
                    <div class="section-title">Delivery Address</div>
                    <div class="info-row">
                        <span class="info-value" style="text-align: left;">
                            <?php echo nl2br(htmlspecialchars($order['delivery_address'])); ?>
                        </span>
                    </div>
                </div>
            <?php endif; ?>

            <!-- Print Button -->
            <div class="print-button">
                <button class="btn-print" onclick="window.print();">Print Receipt</button>
            </div>
        </div>
    </div>
</body>
</html>

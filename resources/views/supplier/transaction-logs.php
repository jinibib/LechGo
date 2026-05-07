<?php
/**
 * Transaction Logs - Supplier
 */
$currentPage = 'transaction-logs';
$user = $_SESSION['user'] ?? null;
if (!$user || $user['role'] !== 'supplier') {
    header('Location: /LechGo_Final/public/login'); exit;
}

global $conn;

$stmt = $conn->prepare("SELECT id FROM suppliers WHERE user_id = ?");
$stmt->bind_param('i', $user['id']);
$stmt->execute();
$supplier = $stmt->get_result()->fetch_assoc();
$stmt->close();

if (!$supplier) {
    $_SESSION['error'] = 'Supplier profile not found';
    header('Location: /LechGo_Final/public/dashboard'); exit;
}

// Filters
$filter_buyer    = trim($_GET['buyer']     ?? '');
$filter_feed     = trim($_GET['feed_type'] ?? '');
$filter_from     = trim($_GET['from']      ?? '');
$filter_to       = trim($_GET['to']        ?? '');

$where  = ['tl.supplier_id = ?'];
$params = [$supplier['id']];
$types  = 'i';

if ($filter_buyer !== '') {
    $where[]  = 'tl.buyer_name LIKE ?';
    $params[] = '%' . $filter_buyer . '%';
    $types   .= 's';
}
if ($filter_feed !== '') {
    $where[]  = 'tl.feed_type LIKE ?';
    $params[] = '%' . $filter_feed . '%';
    $types   .= 's';
}
if ($filter_from !== '') {
    $where[]  = 'DATE(tl.purchase_date) >= ?';
    $params[] = $filter_from;
    $types   .= 's';
}
if ($filter_to !== '') {
    $where[]  = 'DATE(tl.purchase_date) <= ?';
    $params[] = $filter_to;
    $types   .= 's';
}

$sql = "SELECT tl.* FROM transaction_logs tl
        WHERE " . implode(' AND ', $where) . "
        ORDER BY tl.purchase_date DESC";

$stmt = $conn->prepare($sql);
if ($params) {
    $stmt->bind_param($types, ...$params);
}
$stmt->execute();
$logs = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
$stmt->close();

$totalKg      = array_sum(array_column($logs, 'quantity_kg'));
$totalRevenue = array_sum(array_column($logs, 'subtotal'));
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Transaction Logs - LechGO</title>
    <link rel="stylesheet" href="/LechGo_Final/public/styles.css">
    <style>
        .tl-wrap { max-width: 1100px; margin: 0 auto; padding: 1rem; }
        .tl-header { margin-bottom: 1.25rem; }
        .tl-header h1 { font-size: 1.5rem; margin: 0; color: #333; }
        .tl-header p  { margin: 4px 0 0; color: #888; font-size: .88rem; }

        .tl-summary { display: flex; gap: 1rem; margin-bottom: 1.25rem; flex-wrap: wrap; }
        .tl-sum-card {
            flex: 1; min-width: 140px;
            background: #fff; border-radius: 10px;
            box-shadow: 0 2px 8px rgba(0,0,0,.07);
            padding: 1rem 1.25rem;
        }
        .tl-sum-card .val { font-size: 1.4rem; font-weight: 800; color: var(--primary-color, #c0392b); }
        .tl-sum-card .lbl { font-size: .75rem; color: #888; text-transform: uppercase; letter-spacing: .04em; margin-top: 2px; }

        .tl-filters {
            background: #fff; border-radius: 10px;
            box-shadow: 0 2px 8px rgba(0,0,0,.07);
            padding: 1rem 1.25rem; margin-bottom: 1.25rem;
            display: flex; gap: .75rem; flex-wrap: wrap; align-items: flex-end;
        }
        .tl-filters .fg { display: flex; flex-direction: column; gap: 3px; flex: 1; min-width: 140px; }
        .tl-filters label { font-size: .72rem; font-weight: 700; color: #555; text-transform: uppercase; }
        .tl-filters input {
            padding: 6px 10px; border: 1.5px solid #e0e0e0;
            border-radius: 7px; font-size: .85rem; background: #fafafa;
        }
        .tl-filters input:focus { outline: none; border-color: var(--primary-color, #c0392b); background: #fff; }
        .btn-filter {
            padding: 7px 18px; background: var(--primary-color, #c0392b);
            color: #fff; border: none; border-radius: 7px;
            font-weight: 700; font-size: .85rem; cursor: pointer; align-self: flex-end;
        }
        .btn-clear {
            padding: 7px 14px; background: #f0f0f0;
            color: #555; border: none; border-radius: 7px;
            font-weight: 700; font-size: .85rem; cursor: pointer; align-self: flex-end;
            text-decoration: none;
        }

        .tl-table-wrap {
            background: #fff; border-radius: 10px;
            box-shadow: 0 2px 8px rgba(0,0,0,.07);
            overflow-x: auto;
        }
        .tl-table-wrap table { width: 100%; border-collapse: collapse; font-size: .85rem; }
        .tl-table-wrap th {
            background: #fdf0f0; color: var(--primary-color, #c0392b);
            font-size: .72rem; font-weight: 700; text-transform: uppercase;
            letter-spacing: .04em; padding: 10px 14px; text-align: left;
            border-bottom: 2px solid #f5c6c6; white-space: nowrap;
        }
        .tl-table-wrap td {
            padding: 11px 14px; border-bottom: 1px solid #f5f5f5;
            color: #444; vertical-align: middle;
        }
        .tl-table-wrap tr:last-child td { border-bottom: none; }
        .tl-table-wrap tr:hover td { background: #fdf8f8; }
        .empty-msg { text-align: center; padding: 2.5rem; color: #aaa; font-size: .9rem; }

        .status-pill {
            display: inline-block; padding: 2px 9px; border-radius: 12px;
            font-size: .7rem; font-weight: 700; text-transform: capitalize;
        }
        .pill-delivered  { background: #d4edda; color: #155724; }
        .pill-pending    { background: #fff3cd; color: #856404; }
        .pill-confirmed  { background: #d1ecf1; color: #0c5460; }
        .pill-cancelled  { background: #f8d7da; color: #721c24; }
        .pill-processing { background: #cce5ff; color: #004085; }
    </style>
</head>
<body>
<div class="dashboard-layout">
    <?php include __DIR__ . '/../layouts/sidebar.php'; ?>
    <main class="dashboard-main">
    <div class="tl-wrap">

        <?php if (isset($_SESSION['success'])): ?>
            <div class="alert alert-success show"><?php echo htmlspecialchars($_SESSION['success']); unset($_SESSION['success']); ?></div>
        <?php endif; ?>
        <?php if (isset($_SESSION['error'])): ?>
            <div class="alert alert-error show"><?php echo htmlspecialchars($_SESSION['error']); unset($_SESSION['error']); ?></div>
        <?php endif; ?>

        <div class="tl-header">
            <h1>Transaction Logs</h1>
            <p>All feed sales transactions</p>
        </div>

        <!-- Summary -->
        <div class="tl-summary">
            <div class="tl-sum-card">
                <div class="val"><?php echo count($logs); ?></div>
                <div class="lbl">Total Transactions</div>
            </div>
            <div class="tl-sum-card">
                <div class="val"><?php echo number_format($totalKg, 1); ?> kg</div>
                <div class="lbl">Total Feed Sold</div>
            </div>
            <div class="tl-sum-card">
                <div class="val">₱<?php echo number_format($totalRevenue, 2); ?></div>
                <div class="lbl">Total Revenue</div>
            </div>
        </div>

        <!-- Filters -->
        <form method="GET" action="">
            <div class="tl-filters">
                <div class="fg">
                    <label>Buyer Name</label>
                    <input type="text" name="buyer" placeholder="Search buyer..." value="<?php echo htmlspecialchars($filter_buyer); ?>">
                </div>
                <div class="fg">
                    <label>Feed Type</label>
                    <input type="text" name="feed_type" placeholder="e.g. Corn, Starter..." value="<?php echo htmlspecialchars($filter_feed); ?>">
                </div>
                <div class="fg">
                    <label>From Date</label>
                    <input type="date" name="from" value="<?php echo htmlspecialchars($filter_from); ?>">
                </div>
                <div class="fg">
                    <label>To Date</label>
                    <input type="date" name="to" value="<?php echo htmlspecialchars($filter_to); ?>">
                </div>
                <button type="submit" class="btn-filter"> Filter</button>
                <a href="/LechGo_Final/public/supplier/transaction-logs" class="btn-clear"> Clear</a>
            </div>
        </form>

        <!-- Table -->
        <div class="tl-table-wrap">
            <?php if (empty($logs)): ?>
                <div class="empty-msg">No transactions found.</div>
            <?php else: ?>
            <table>
                <thead>
                    <tr>
                        <th>#</th>
                        <th>Purchase Date</th>
                        <th>Buyer Name</th>
                        <th>Feed Type</th>
                        <th>Product</th>
                        <th>Qty (kg)</th>
                        <th>Price / kg</th>
                        <th>Amount</th>
                        <th>Status</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($logs as $i => $log): ?>
                    <tr>
                        <td style="color:#aaa;font-size:.78rem;"><?php echo $i + 1; ?></td>
                        <td style="white-space:nowrap;"><?php echo date('M d, Y', strtotime($log['purchase_date'])); ?></td>
                        <td><strong><?php echo htmlspecialchars($log['buyer_name']); ?></strong></td>
                        <td><?php echo htmlspecialchars($log['feed_type']); ?></td>
                        <td><?php echo htmlspecialchars($log['product_name']); ?></td>
                        <td><?php echo number_format($log['quantity_kg'], 1); ?></td>
                        <td>₱<?php echo number_format($log['unit_price'], 2); ?></td>
                        <td><strong>₱<?php echo number_format($log['subtotal'], 2); ?></strong></td>
                        <td>
                            <?php
                            $s = $log['order_status'];
                            $cls = match($s) {
                                'delivered'  => 'pill-delivered',
                                'confirmed'  => 'pill-confirmed',
                                'processing' => 'pill-processing',
                                'cancelled'  => 'pill-cancelled',
                                default      => 'pill-pending',
                            };
                            ?>
                            <span class="status-pill <?php echo $cls; ?>"><?php echo ucfirst(str_replace('_',' ',$s)); ?></span>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
                <tfoot>
                    <tr style="background:#fdf0f0;">
                        <td colspan="5" style="padding:10px 14px;font-weight:700;color:#c0392b;font-size:.82rem;">TOTAL</td>
                        <td style="padding:10px 14px;font-weight:800;color:#c0392b;"><?php echo number_format($totalKg, 1); ?> kg</td>
                        <td></td>
                        <td style="padding:10px 14px;font-weight:800;color:#c0392b;">₱<?php echo number_format($totalRevenue, 2); ?></td>
                        <td></td>
                    </tr>
                </tfoot>
            </table>
            <?php endif; ?>
        </div>

    </div>
    </main>
</div>
</body>
</html>

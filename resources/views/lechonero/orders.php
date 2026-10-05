<?php
/**
 * Lechonero Orders - View assigned cooking orders
 * Shows all orders assigned to this lechonero's livestock owner,
 * covering both pig-based orders and marketplace lechon orders.
 */
$currentPage = 'lechonero-orders';
$user = $_SESSION['user'] ?? null;
if (!$user) { header('Location: /login'); exit; }

global $conn;

// Get the livestock owner this lechonero is assigned to
$assignment = null;
$assignStmt = $conn->prepare(
    "SELECT ea.livestock_owner_id, lo.farm_name, lo.location
     FROM employee_assignments ea
     JOIN livestock_owners lo ON lo.id = ea.livestock_owner_id
     WHERE ea.employee_user_id = ? AND ea.status = 'active'
     ORDER BY ea.assigned_at DESC LIMIT 1"
);
$assignStmt->bind_param('i', $user['id']);
$assignStmt->execute();
$assignment = $assignStmt->get_result()->fetch_assoc();
$assignStmt->close();

$orders = [];
$lechon_orders = [];

if ($assignment) {
    $owner_id = $assignment['livestock_owner_id'];

    // Force connection collation to utf8mb4_unicode_ci so queries across mixed-collation tables work
    $conn->query("SET NAMES utf8mb4 COLLATE utf8mb4_unicode_ci");

    // ── Pig-based orders ──────────────────────────────────────────────────────
    $pigSql = "SELECT
                CONVERT(otc.order_number  USING utf8mb4) COLLATE utf8mb4_unicode_ci AS order_number,
                CONVERT(otc.customer_name USING utf8mb4) COLLATE utf8mb4_unicode_ci AS customer_name,
                CONVERT(COALESCE(pd.pig_tag_id, otc.pig_tag_id) USING utf8mb4) COLLATE utf8mb4_unicode_ci AS pig_tag_id,
                CONVERT(COALESCE(pp.cage_number, otc.pin_number) USING utf8mb4) COLLATE utf8mb4_unicode_ci AS pin_number,
                sos.weight_kg,
                CONVERT(sos.order_status  USING utf8mb4) COLLATE utf8mb4_unicode_ci AS order_status,
                CONVERT(sos.payment_status USING utf8mb4) COLLATE utf8mb4_unicode_ci AS payment_status,
                sos.pickup_date AS delivery_date,
                po.created_at   AS order_date,
                otc.total_cost,
                'PIG_ORDER' COLLATE utf8mb4_unicode_ci AS source_type,
                NULL AS lechon_order_id
              FROM order_total_cost otc
              LEFT JOIN swine_order_status sos ON sos.id = otc.swine_order_id
              LEFT JOIN placed_orders po        ON po.swine_order_id = sos.id
              LEFT JOIN pig_details pd           ON pd.id = sos.pig_detail_id
              LEFT JOIN pig_pins pp              ON pp.id = pd.cage_id
              WHERE otc.livestock_owner_id = ?
                AND sos.payment_status = 'paid'
                AND sos.order_status NOT IN ('completed', 'cancelled')
              ORDER BY po.created_at DESC";

    $pigStmt = $conn->prepare($pigSql);
    if ($pigStmt) {
        $pigStmt->bind_param('i', $owner_id);
        $pigStmt->execute();
        $orders = $pigStmt->get_result()->fetch_all(MYSQLI_ASSOC);
        $pigStmt->close();
    }

    // ── Marketplace lechon orders ─────────────────────────────────────────────
    // Only table-check once; fail gracefully if table doesn't exist yet
    $tblCheck = $conn->query("SHOW TABLES LIKE 'lechon_orders'");
    if ($tblCheck && $tblCheck->num_rows > 0) {
        $loSql = "SELECT
                    lo.id            AS lechon_order_id,
                    CONVERT(lo.order_number  USING utf8mb4) COLLATE utf8mb4_unicode_ci AS order_number,
                    CONVERT(u.name           USING utf8mb4) COLLATE utf8mb4_unicode_ci AS customer_name,
                    CONVERT(lo.listing_name  USING utf8mb4) COLLATE utf8mb4_unicode_ci AS pig_tag_id,
                    CONVERT(lo.category      USING utf8mb4) COLLATE utf8mb4_unicode_ci AS pin_number,
                    lo.weight_kg,
                    CONVERT(lo.order_status  USING utf8mb4) COLLATE utf8mb4_unicode_ci AS order_status,
                    CONVERT(lo.payment_status USING utf8mb4) COLLATE utf8mb4_unicode_ci AS payment_status,
                    lo.pickup_date   AS delivery_date,
                    lo.created_at    AS order_date,
                    lo.price         AS total_cost,
                    'LECHON_ORDER' COLLATE utf8mb4_unicode_ci AS source_type
                  FROM lechon_orders lo
                  LEFT JOIN users u ON u.id = lo.customer_id
                  WHERE lo.livestock_owner_id = ?
                    AND lo.payment_status = 'paid'
                    AND lo.order_status NOT IN ('completed', 'cancelled')
                  ORDER BY lo.created_at DESC";

        $loStmt = $conn->prepare($loSql);
        if ($loStmt) {
            $loStmt->bind_param('i', $owner_id);
            $loStmt->execute();
            $lechon_orders = $loStmt->get_result()->fetch_all(MYSQLI_ASSOC);
            $loStmt->close();
        }
    }
}

// Merge and sort by order_date DESC
$all_orders = array_merge($orders, $lechon_orders);
usort($all_orders, fn($a, $b) => strtotime($b['order_date']) - strtotime($a['order_date']));

// Status label helpers
function orderStatusLabel(string $status): array {
    $map = [
        'pending'          => ['Pending',           '#856404', '#fff3cd'],
        'confirmed'        => ['Confirmed',          '#0c5460', '#d1ecf1'],
        'preparing'        => ['Ready to Cook',      '#155724', '#d4edda'],
        'cost_computed'    => ['Cost Computed',      '#383d41', '#e2e3e5'],
        'cooking'          => ['Cooking 🔥',          '#7b2d00', '#ffe0b2'],
        'ready_for_pickup' => ['Ready for Pickup',   '#1b5e20', '#c8e6c9'],
        'delivering'       => ['Delivering 🛵',       '#4a235a', '#f3e5f5'],
        'completed'        => ['Completed ✓',        '#155724', '#d4edda'],
        'cancelled'        => ['Cancelled',          '#721c24', '#f8d7da'],
    ];
    return $map[$status] ?? [ucfirst($status), '#333', '#eee'];
}

function paymentStatusLabel(string $status): array {
    $map = [
        'paid'          => ['Paid ✓',          '#155724', '#d4edda'],
        'unpaid'        => ['Unpaid',           '#856404', '#fff3cd'],
        'partially_paid'=> ['Partial',          '#0c5460', '#d1ecf1'],
        'refunded'      => ['Refunded',         '#721c24', '#f8d7da'],
    ];
    return $map[$status] ?? [ucfirst($status), '#333', '#eee'];
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>My Orders - Lechonero - LechGO</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="/styles.css">
    <style>
        .lo-wrap { max-width: 1300px; margin: 0 auto; padding: 1.5rem; }

        .lo-header { margin-bottom: 1.5rem; }
        .lo-header h1 { font-size: 1.75rem; margin: 0; color: #333; font-weight: 700; }
        .lo-header p  { margin: 4px 0 0; color: #888; font-size: 0.95rem; }

        .farm-badge {
            display: inline-flex; align-items: center; gap: 8px;
            background: #fff5f0; border: 1px solid #f5c6a0;
            padding: 8px 16px; border-radius: 30px;
            font-size: 0.9rem; color: #c0392b; font-weight: 600;
            margin-bottom: 1.25rem;
        }

        /* Summary cards */
        .lo-summary { display: flex; gap: 1rem; margin-bottom: 1.5rem; flex-wrap: wrap; }
        .lo-stat {
            flex: 1; min-width: 140px;
            background: #fff; border-radius: 10px;
            box-shadow: 0 2px 8px rgba(0,0,0,.07);
            padding: 1.25rem 1.5rem;
        }
        .lo-stat .val { font-size: 1.75rem; font-weight: 800; color: #c0392b; }
        .lo-stat .lbl { font-size: .8rem; color: #888; text-transform: uppercase; letter-spacing: .04em; margin-top: 2px; }

        /* Table */
        .lo-card {
            background: #fff; border-radius: 12px;
            box-shadow: 0 2px 10px rgba(0,0,0,.07);
            overflow-x: auto;
        }
        .lo-table { width: 100%; border-collapse: collapse; min-width: 900px; }
        .lo-table th {
            background: #f8f9fa; padding: 14px 12px; text-align: left;
            font-size: .8rem; color: #666; text-transform: uppercase;
            letter-spacing: .03em; border-bottom: 2px solid #eee;
            white-space: nowrap;
        }
        .lo-table td { padding: 13px 12px; border-bottom: 1px solid #f0f0f0; font-size: .9rem; vertical-align: middle; }
        .lo-table tr:last-child td { border-bottom: none; }
        .lo-table tr:hover td { background: #fafafa; }

        .badge-pill {
            display: inline-block; padding: 4px 10px; border-radius: 20px;
            font-size: .75rem; font-weight: 700; white-space: nowrap;
        }
        .type-pig     { background: #e3f2fd; color: #1565c0; }
        .type-lechon  { background: #fce4ec; color: #880e4f; }

        .empty-state {
            text-align: center; padding: 4rem 2rem;
        }
        .empty-state i  { font-size: 3.5rem; color: #ddd; margin-bottom: 1rem; }
        .empty-state h5 { color: #888; font-weight: 600; }
        .empty-state p  { color: #bbb; margin: 0; }

        .action-link {
            display: inline-block; padding: 6px 14px;
            background: linear-gradient(135deg, #c0392b, #e74c3c);
            color: #fff; border-radius: 20px; font-size: .8rem;
            font-weight: 600; text-decoration: none;
            transition: opacity .2s;
        }
        .action-link:hover { opacity: .85; color: #fff; }

        @media (max-width: 600px) {
            .lo-stat { min-width: 120px; }
        }
    </style>
</head>
<body>
<div class="dashboard-container">
    <?php include __DIR__ . '/../layouts/sidebar.php'; ?>

    <main class="dashboard-main">
        <div class="dashboard-topbar">
            <button class="dashboard-mobile-toggle" id="sidebarToggle">&#9776;</button>
            <h1 class="dashboard-topbar-title">My Orders</h1>
            <div class="dashboard-topbar-actions">
                <span class="dashboard-topbar-date"><?php echo date('l, F j, Y'); ?></span>
            </div>
        </div>

        <div class="dashboard-content">
            <div class="lo-wrap">

                <!-- Header -->
                <div class="lo-header">
                    <h1><i class="fas fa-list-alt" style="color:#c0392b;margin-right:8px;"></i>My Assigned Orders</h1>
                    <p>Orders from your assigned farm that are ready for cooking preparation</p>
                </div>

                <!-- Flash messages -->
                <?php if (isset($_SESSION['success'])): ?>
                    <div style="background:#d4edda;border-left:4px solid #28a745;padding:12px 16px;border-radius:6px;margin-bottom:1rem;color:#155724;">
                        <?php echo htmlspecialchars($_SESSION['success']); unset($_SESSION['success']); ?>
                    </div>
                <?php endif; ?>
                <?php if (isset($_SESSION['error'])): ?>
                    <div style="background:#f8d7da;border-left:4px solid #dc3545;padding:12px 16px;border-radius:6px;margin-bottom:1rem;color:#721c24;">
                        <?php echo htmlspecialchars($_SESSION['error']); unset($_SESSION['error']); ?>
                    </div>
                <?php endif; ?>

                <?php if (!$assignment): ?>
                    <!-- Not assigned to any farm -->
                    <div class="lo-card" style="padding:3rem;text-align:center;">
                        <i class="fas fa-exclamation-circle" style="font-size:3rem;color:#f39c12;margin-bottom:1rem;display:block;"></i>
                        <h4 style="color:#555;">Not assigned to a farm yet</h4>
                        <p style="color:#888;">You have not been assigned to a livestock owner. Please contact your employer.</p>
                    </div>
                <?php else: ?>

                    <!-- Farm badge -->
                    <div class="farm-badge">
                        <i class="fas fa-store"></i>
                        <?php echo htmlspecialchars($assignment['farm_name']); ?>
                        <?php if (!empty($assignment['location'])): ?>
                            &nbsp;·&nbsp; <i class="fas fa-map-marker-alt"></i>&nbsp;<?php echo htmlspecialchars($assignment['location']); ?>
                        <?php endif; ?>
                    </div>

                    <!-- Summary cards -->
                    <?php
                    $cnt_ready    = count(array_filter($all_orders, fn($o) => $o['order_status'] === 'preparing'));
                    $cnt_cooking  = count(array_filter($all_orders, fn($o) => $o['order_status'] === 'cooking'));
                    $cnt_deliver  = count(array_filter($all_orders, fn($o) => $o['order_status'] === 'delivering'));
                    ?>
                    <div class="lo-summary">
                        <div class="lo-stat">
                            <div class="val"><?php echo count($all_orders); ?></div>
                            <div class="lbl">Total Active</div>
                        </div>
                        <div class="lo-stat">
                            <div class="val" style="color:#28a745;"><?php echo $cnt_ready; ?></div>
                            <div class="lbl">Ready to Cook</div>
                        </div>
                        <div class="lo-stat">
                            <div class="val" style="color:#e67e22;"><?php echo $cnt_cooking; ?></div>
                            <div class="lbl">Cooking</div>
                        </div>
                        <div class="lo-stat">
                            <div class="val" style="color:#8e44ad;"><?php echo $cnt_deliver; ?></div>
                            <div class="lbl">Delivering</div>
                        </div>
                    </div>

                    <!-- Orders table -->
                    <div class="lo-card">
                        <?php if (empty($all_orders)): ?>
                            <div class="empty-state">
                                <i class="fas fa-inbox"></i>
                                <h5>No active orders</h5>
                                <p>Paid orders ready for cooking will appear here.</p>
                            </div>
                        <?php else: ?>
                            <table class="lo-table">
                                <thead>
                                    <tr>
                                        <th>Order #</th>
                                        <th>Type</th>
                                        <th>Customer</th>
                                        <th>Item / Tag</th>
                                        <th>Weight</th>
                                        <th>Order Date</th>
                                        <th>Delivery Date</th>
                                        <th>Order Status</th>
                                        <th>Payment</th>
                                        <th>Action</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($all_orders as $order):
                                        [$ostLabel, $ostColor, $ostBg] = orderStatusLabel($order['order_status']);
                                        [$pstLabel, $pstColor, $pstBg] = paymentStatusLabel($order['payment_status']);
                                        $isPig    = $order['source_type'] === 'PIG_ORDER';
                                        $itemLabel = $isPig
                                            ? htmlspecialchars($order['pig_tag_id'])
                                            : htmlspecialchars($order['pig_tag_id']); // listing_name aliased as pig_tag_id
                                    ?>
                                    <tr>
                                        <td><strong style="color:#2d3748;"><?php echo htmlspecialchars($order['order_number']); ?></strong></td>
                                        <td>
                                            <span class="badge-pill <?php echo $isPig ? 'type-pig' : 'type-lechon'; ?>">
                                                <?php echo $isPig ? '🐷 Pig Order' : '🍖 Marketplace'; ?>
                                            </span>
                                        </td>
                                        <td><?php echo htmlspecialchars($order['customer_name']); ?></td>
                                        <td>
                                            <strong><?php echo $itemLabel; ?></strong>
                                            <?php if (!$isPig): ?>
                                                <br><small style="color:#888;"><?php echo htmlspecialchars(str_replace('_', ' ', $order['pin_number'])); ?></small>
                                            <?php elseif (!empty($order['pin_number'])): ?>
                                                <br><small style="color:#888;">Pen: <?php echo htmlspecialchars($order['pin_number']); ?></small>
                                            <?php endif; ?>
                                        </td>
                                        <td><?php echo $order['weight_kg'] ? number_format($order['weight_kg'], 1) . ' kg' : '—'; ?></td>
                                        <td><small><?php echo date('M d, Y', strtotime($order['order_date'])); ?></small></td>
                                        <td>
                                            <?php if (!empty($order['delivery_date'])): ?>
                                                <?php
                                                $dDate = strtotime($order['delivery_date']);
                                                $today = strtotime('today');
                                                $diff  = ($dDate - $today) / 86400;
                                                $urgentStyle = $diff <= 1 ? 'color:#c0392b;font-weight:700;' : '';
                                                ?>
                                                <span style="<?php echo $urgentStyle; ?>">
                                                    <?php echo date('M d, Y', $dDate); ?>
                                                    <?php if ($diff <= 0): ?> <span style="font-size:.7rem;background:#f8d7da;color:#721c24;padding:1px 5px;border-radius:3px;">Today</span>
                                                    <?php elseif ($diff <= 1): ?> <span style="font-size:.7rem;background:#fff3cd;color:#856404;padding:1px 5px;border-radius:3px;">Tomorrow</span>
                                                    <?php endif; ?>
                                                </span>
                                            <?php else: ?>
                                                <span style="color:#bbb;">—</span>
                                            <?php endif; ?>
                                        </td>
                                        <td>
                                            <span class="badge-pill" style="background:<?php echo $ostBg; ?>;color:<?php echo $ostColor; ?>;">
                                                <?php echo $ostLabel; ?>
                                            </span>
                                        </td>
                                        <td>
                                            <span class="badge-pill" style="background:<?php echo $pstBg; ?>;color:<?php echo $pstColor; ?>;">
                                                <?php echo $pstLabel; ?>
                                            </span>
                                        </td>
                                        <td>
                                            <?php if (in_array($order['order_status'], ['preparing', 'cooking'])): ?>
                                                <a href="<?php echo $GLOBALS['base_url'] ?? ''; ?>/lechonero/schedule" class="action-link">
                                                    <i class="fas fa-fire"></i> Cook
                                                </a>
                                            <?php else: ?>
                                                <span style="color:#bbb;font-size:.8rem;">—</span>
                                            <?php endif; ?>
                                        </td>
                                    </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        <?php endif; ?>
                    </div>

                <?php endif; // end $assignment check ?>

            </div><!-- /lo-wrap -->
        </div><!-- /dashboard-content -->
    </main>
</div>

<script>
    // Sidebar toggle
    document.getElementById('sidebarToggle')?.addEventListener('click', function() {
        document.getElementById('dashboardSidebar')?.classList.toggle('active');
    });
</script>
</body>
</html>

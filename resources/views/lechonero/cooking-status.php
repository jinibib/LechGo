<?php
/**
 * Lechonero Cooking Status
 * Provides a live summary of orders in each cooking stage and links
 * directly into the schedule page for action items.
 * (No duplicate cooking controls — all actions live in schedule.php)
 */
$currentPage = 'cooking-status';
$user = $_SESSION['user'] ?? null;
if (!$user) { header('Location: /login'); exit; }

global $conn;

// Get assigned livestock owner
$assignment = null;
$aStmt = $conn->prepare(
    "SELECT ea.livestock_owner_id, lo.farm_name
     FROM employee_assignments ea
     JOIN livestock_owners lo ON lo.id = ea.livestock_owner_id
     WHERE ea.employee_user_id = ? AND ea.status = 'active'
     ORDER BY ea.assigned_at DESC LIMIT 1"
);
$aStmt->bind_param('i', $user['id']);
$aStmt->execute();
$assignment = $aStmt->get_result()->fetch_assoc();
$aStmt->close();

// Status counts — pull from both order sources
$counts = [
    'ready_to_cook' => 0,
    'cooking'       => 0,
    'delivering'    => 0,
    'completed'     => 0,
];
$recent = [];

if ($assignment) {
    $oid = $assignment['livestock_owner_id'];

    // Force connection collation to utf8mb4_unicode_ci so queries across mixed-collation tables work
    $conn->query("SET NAMES utf8mb4 COLLATE utf8mb4_unicode_ci");

    // Pig orders
    $pigQ = $conn->prepare(
        "SELECT CONVERT(sos.order_status USING utf8mb4) COLLATE utf8mb4_unicode_ci AS order_status,
                CONVERT(otc.order_number  USING utf8mb4) COLLATE utf8mb4_unicode_ci AS order_number,
                CONVERT(otc.customer_name USING utf8mb4) COLLATE utf8mb4_unicode_ci AS customer_name,
                sos.pickup_date AS delivery_date,
                'PIG_ORDER' COLLATE utf8mb4_unicode_ci AS source_type
         FROM order_total_cost otc
         JOIN swine_order_status sos ON sos.id = otc.swine_order_id
         WHERE otc.livestock_owner_id = ?
           AND sos.payment_status = 'paid'
           AND sos.order_status IN ('preparing','cooking','delivering','completed')
         ORDER BY sos.updated_at DESC LIMIT 50"
    );
    if ($pigQ) {
        $pigQ->bind_param('i', $oid);
        $pigQ->execute();
        $pigRows = $pigQ->get_result()->fetch_all(MYSQLI_ASSOC);
        $pigQ->close();
        foreach ($pigRows as $r) {
            if ($r['order_status'] === 'preparing')  $counts['ready_to_cook']++;
            elseif ($r['order_status'] === 'cooking')   $counts['cooking']++;
            elseif ($r['order_status'] === 'delivering') $counts['delivering']++;
            elseif ($r['order_status'] === 'completed')  $counts['completed']++;
            $recent[] = $r;
        }
    }

    // Marketplace lechon orders
    $loChk = $conn->query("SHOW TABLES LIKE 'lechon_orders'");
    if ($loChk && $loChk->num_rows > 0) {
        $loQ = $conn->prepare(
            "SELECT CONVERT(lo.order_status USING utf8mb4) COLLATE utf8mb4_unicode_ci AS order_status,
                    CONVERT(lo.order_number  USING utf8mb4) COLLATE utf8mb4_unicode_ci AS order_number,
                    CONVERT(u.name           USING utf8mb4) COLLATE utf8mb4_unicode_ci AS customer_name,
                    lo.pickup_date  AS delivery_date,
                    'LECHON_ORDER' COLLATE utf8mb4_unicode_ci AS source_type
             FROM lechon_orders lo
             JOIN users u ON u.id = lo.customer_id
             WHERE lo.livestock_owner_id = ?
               AND lo.payment_status = 'paid'
               AND lo.order_status IN ('preparing','cooking','delivering','completed')
             ORDER BY lo.updated_at DESC LIMIT 50"
        );
        if ($loQ) {
            $loQ->bind_param('i', $oid);
            $loQ->execute();
            $loRows = $loQ->get_result()->fetch_all(MYSQLI_ASSOC);
            $loQ->close();
            foreach ($loRows as $r) {
                if ($r['order_status'] === 'preparing')  $counts['ready_to_cook']++;
                elseif ($r['order_status'] === 'cooking')   $counts['cooking']++;
                elseif ($r['order_status'] === 'delivering') $counts['delivering']++;
                elseif ($r['order_status'] === 'completed')  $counts['completed']++;
                $recent[] = $r;
            }
        }
    }

    // Sort by most recently updated
    usort($recent, fn($a, $b) => strtotime($b['delivery_date'] ?? '1970-01-01') - strtotime($a['delivery_date'] ?? '1970-01-01'));
}

$base = $GLOBALS['base_url'] ?? '';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Cooking Status - LechGO</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="/styles.css">
    <style>
        .cs-wrap { max-width: 1100px; margin: 0 auto; padding: 1.5rem; }
        .cs-header { margin-bottom: 1.5rem; }
        .cs-header h1 { font-size: 1.75rem; margin: 0; color: #333; font-weight: 700; }
        .cs-header p  { margin: 4px 0 0; color: #888; }

        /* Stage pipeline */
        .stage-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(210px, 1fr));
            gap: 1rem;
            margin-bottom: 2rem;
        }
        .stage-card {
            background: #fff;
            border-radius: 14px;
            padding: 1.5rem 1.25rem;
            box-shadow: 0 3px 12px rgba(0,0,0,.08);
            text-align: center;
            position: relative;
            overflow: hidden;
            border-top: 4px solid transparent;
            transition: transform .2s;
        }
        .stage-card:hover { transform: translateY(-3px); }
        .stage-card.ready  { border-top-color: #28a745; }
        .stage-card.cooking { border-top-color: #e67e22; }
        .stage-card.deliver { border-top-color: #8e44ad; }
        .stage-card.done   { border-top-color: #2196f3; }

        .stage-icon { font-size: 2.2rem; margin-bottom: .5rem; }
        .stage-count {
            font-size: 3rem; font-weight: 800; line-height: 1;
            margin-bottom: .35rem;
        }
        .stage-label { font-size: .85rem; color: #777; text-transform: uppercase; letter-spacing: .05em; }

        .stage-card.ready   .stage-count { color: #28a745; }
        .stage-card.cooking .stage-count { color: #e67e22; }
        .stage-card.deliver .stage-count { color: #8e44ad; }
        .stage-card.done    .stage-count { color: #2196f3; }

        /* CTA banner */
        .cta-banner {
            background: linear-gradient(135deg, #c0392b 0%, #e74c3c 100%);
            color: #fff;
            border-radius: 14px;
            padding: 1.5rem 2rem;
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 1rem;
            margin-bottom: 2rem;
            flex-wrap: wrap;
            box-shadow: 0 4px 15px rgba(192,57,43,.3);
        }
        .cta-banner h3 { margin: 0; font-size: 1.2rem; font-weight: 700; }
        .cta-banner p  { margin: 4px 0 0; opacity: .9; font-size: .9rem; }
        .cta-btn {
            background: #fff; color: #c0392b;
            padding: 10px 24px; border-radius: 30px;
            font-weight: 700; text-decoration: none;
            font-size: .95rem; white-space: nowrap;
            transition: box-shadow .2s;
            display: inline-block;
        }
        .cta-btn:hover { box-shadow: 0 4px 14px rgba(0,0,0,.2); color: #c0392b; }

        /* Recent orders mini-table */
        .recent-card {
            background: #fff; border-radius: 12px;
            box-shadow: 0 2px 10px rgba(0,0,0,.07);
            overflow-x: auto;
        }
        .recent-card h4 { padding: 1rem 1.25rem; margin: 0; font-size: 1rem; border-bottom: 1px solid #eee; color: #444; }
        .mini-table { width: 100%; border-collapse: collapse; min-width: 600px; }
        .mini-table th {
            background: #f8f9fa; padding: 10px 12px;
            font-size: .78rem; color: #777;
            text-transform: uppercase; letter-spacing: .03em;
            border-bottom: 1px solid #eee; text-align: left;
        }
        .mini-table td { padding: 11px 12px; border-bottom: 1px solid #f5f5f5; font-size: .88rem; }
        .mini-table tr:last-child td { border-bottom: none; }

        .badge-sm {
            display: inline-block; padding: 3px 9px; border-radius: 20px;
            font-size: .72rem; font-weight: 700;
        }
        .empty-msg { text-align: center; padding: 3rem; color: #aaa; }

        @media (max-width: 500px) { .stage-grid { grid-template-columns: 1fr 1fr; } }
    </style>
</head>
<body>
<div class="dashboard-container">
    <?php include __DIR__ . '/../layouts/sidebar.php'; ?>

    <main class="dashboard-main">
        <div class="dashboard-topbar">
            <button class="dashboard-mobile-toggle" id="sidebarToggle">&#9776;</button>
            <h1 class="dashboard-topbar-title">Cooking Status</h1>
            <div class="dashboard-topbar-actions">
                <span class="dashboard-topbar-date"><?php echo date('l, F j, Y'); ?></span>
            </div>
        </div>

        <div class="dashboard-content">
            <div class="cs-wrap">

                <div class="cs-header">
                    <h1><i class="fas fa-fire" style="color:#c0392b;margin-right:8px;"></i>Cooking Status</h1>
                    <p>Live overview of all orders at each cooking stage</p>
                    <?php if ($assignment): ?>
                        <span style="font-size:.85rem;color:#c0392b;font-weight:600;">
                            <i class="fas fa-store"></i> <?php echo htmlspecialchars($assignment['farm_name']); ?>
                        </span>
                    <?php endif; ?>
                </div>

                <?php if (!$assignment): ?>
                    <div style="background:#fff;border-radius:12px;padding:3rem;text-align:center;box-shadow:0 2px 10px rgba(0,0,0,.07);">
                        <i class="fas fa-exclamation-circle" style="font-size:2.5rem;color:#f39c12;display:block;margin-bottom:1rem;"></i>
                        <h4 style="color:#555;">Not assigned to a farm yet</h4>
                        <p style="color:#888;">Contact your employer to be assigned to a livestock farm.</p>
                    </div>
                <?php else: ?>

                    <!-- Stage pipeline cards -->
                    <div class="stage-grid">
                        <div class="stage-card ready">
                            <div class="stage-icon">🔪</div>
                            <div class="stage-count"><?php echo $counts['ready_to_cook']; ?></div>
                            <div class="stage-label">Ready to Cook</div>
                        </div>
                        <div class="stage-card cooking">
                            <div class="stage-icon">🔥</div>
                            <div class="stage-count"><?php echo $counts['cooking']; ?></div>
                            <div class="stage-label">Cooking</div>
                        </div>
                        <div class="stage-card deliver">
                            <div class="stage-icon">🛵</div>
                            <div class="stage-count"><?php echo $counts['delivering']; ?></div>
                            <div class="stage-label">Delivering</div>
                        </div>
                        <div class="stage-card done">
                            <div class="stage-icon">✅</div>
                            <div class="stage-count"><?php echo $counts['completed']; ?></div>
                            <div class="stage-label">Completed</div>
                        </div>
                    </div>

                    <?php if ($counts['ready_to_cook'] > 0 || $counts['cooking'] > 0): ?>
                    <!-- CTA to schedule page -->
                    <div class="cta-banner">
                        <div>
                            <h3><i class="fas fa-exclamation-circle me-2"></i>
                                <?php
                                if ($counts['cooking'] > 0)
                                    echo $counts['cooking'] . ' order' . ($counts['cooking'] !== 1 ? 's' : '') . ' currently cooking';
                                else
                                    echo $counts['ready_to_cook'] . ' order' . ($counts['ready_to_cook'] !== 1 ? 's' : '') . ' ready to cook';
                                ?>
                            </h3>
                            <p>Go to the Cooking Schedule to start or complete cooking for these orders.</p>
                        </div>
                        <a href="<?php echo $base; ?>/lechonero/schedule" class="cta-btn">
                            <i class="fas fa-fire"></i>&nbsp; Open Schedule
                        </a>
                    </div>
                    <?php endif; ?>

                    <!-- Recent orders mini list -->
                    <div class="recent-card">
                        <h4><i class="fas fa-history" style="margin-right:6px;color:#888;"></i>Recent Orders</h4>
                        <?php if (empty($recent)): ?>
                            <div class="empty-msg">
                                <i class="fas fa-inbox" style="font-size:2rem;display:block;margin-bottom:.5rem;"></i>
                                No active cooking orders.
                            </div>
                        <?php else: ?>
                            <table class="mini-table">
                                <thead>
                                    <tr>
                                        <th>Order #</th>
                                        <th>Customer</th>
                                        <th>Type</th>
                                        <th>Status</th>
                                        <th>Delivery</th>
                                        <th></th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php
                                    $statusStyles = [
                                        'preparing'  => ['Ready to Cook', '#155724', '#d4edda'],
                                        'cooking'    => ['Cooking 🔥',   '#7b2d00', '#ffe0b2'],
                                        'delivering' => ['Delivering',   '#4a235a', '#f3e5f5'],
                                        'completed'  => ['Completed',    '#155724', '#d4edda'],
                                    ];
                                    foreach (array_slice($recent, 0, 20) as $r):
                                        [$sl, $sc, $sb] = $statusStyles[$r['order_status']] ?? [ucfirst($r['order_status']), '#333', '#eee'];
                                    ?>
                                    <tr>
                                        <td><strong><?php echo htmlspecialchars($r['order_number']); ?></strong></td>
                                        <td><?php echo htmlspecialchars($r['customer_name']); ?></td>
                                        <td>
                                            <span class="badge-sm" style="background:<?php echo $r['source_type'] === 'PIG_ORDER' ? '#e3f2fd' : '#fce4ec'; ?>;color:<?php echo $r['source_type'] === 'PIG_ORDER' ? '#1565c0' : '#880e4f'; ?>">
                                                <?php echo $r['source_type'] === 'PIG_ORDER' ? 'Pig' : 'Marketplace'; ?>
                                            </span>
                                        </td>
                                        <td>
                                            <span class="badge-sm" style="background:<?php echo $sb; ?>;color:<?php echo $sc; ?>">
                                                <?php echo $sl; ?>
                                            </span>
                                        </td>
                                        <td>
                                            <small><?php echo !empty($r['delivery_date']) ? date('M d, Y', strtotime($r['delivery_date'])) : '—'; ?></small>
                                        </td>
                                        <td>
                                            <?php if (in_array($r['order_status'], ['preparing', 'cooking'])): ?>
                                                <a href="<?php echo $base; ?>/lechonero/schedule" style="color:#c0392b;font-size:.8rem;font-weight:600;">Cook →</a>
                                            <?php endif; ?>
                                        </td>
                                    </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        <?php endif; ?>
                    </div>

                <?php endif; // end $assignment ?>
            </div>
        </div>
    </main>
</div>

<script>
    document.getElementById('sidebarToggle')?.addEventListener('click', function() {
        document.getElementById('dashboardSidebar')?.classList.toggle('active');
    });
</script>
</body>
</html>

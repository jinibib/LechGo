<?php
/**
 * Live Pigs for Slaughter View
 * Pig Slaughter role - manage live pigs ready for slaughter
 */

// Session and user authentication is handled by index.php routing
// The RBAC middleware already verified access to this route

$sessionMiddleware = new Session();
$user = $sessionMiddleware->getUser();
$currentPage = 'live-pigs';

global $conn;

// Check if cooking_schedule table exists
$cs_exists = $conn->query("SHOW TABLES LIKE 'cooking_schedule'")->num_rows > 0;
$cs_join   = $cs_exists ? "LEFT JOIN cooking_schedule cs ON cs.order_number COLLATE utf8mb4_unicode_ci = otc.order_number COLLATE utf8mb4_unicode_ci" : "";
$cs_fields = $cs_exists
    ? "cs.date_to_butcher, cs.time_to_butcher, cs.start_time, cs.end_time,"
    : "NULL as date_to_butcher, NULL as time_to_butcher, NULL as start_time, NULL as end_time,";

// ── Source 1: hogs_market pig orders (order_total_cost → swine_order_status) ──
$sql = "SELECT DISTINCT
            CONVERT(otc.order_number    USING utf8mb4) COLLATE utf8mb4_unicode_ci as order_number,
            CONVERT(otc.customer_name   USING utf8mb4) COLLATE utf8mb4_unicode_ci as customer_name,
            CONVERT(COALESCE(pd.pig_tag_id, otc.pig_tag_id) USING utf8mb4) COLLATE utf8mb4_unicode_ci as pig_tag_id,
            CONVERT(COALESCE(pp.cage_number, otc.pin_number) USING utf8mb4) COLLATE utf8mb4_unicode_ci as pin_number,
            sos.weight_kg,
            CONVERT(sos.order_status    USING utf8mb4) COLLATE utf8mb4_unicode_ci as order_status,
            CONVERT(sos.payment_status  USING utf8mb4) COLLATE utf8mb4_unicode_ci as payment_status,
            po.created_at as order_date,
            sos.pickup_date as delivery_date,
            {$cs_fields}
            CONVERT(CASE 
                WHEN " . ($cs_exists ? "cs.date_to_butcher IS NOT NULL AND cs.time_to_butcher IS NOT NULL" : "FALSE") . " THEN 'scheduled'
                WHEN sos.payment_status = 'paid' THEN 'ready_to_schedule'
                ELSE 'not_ready'
            END USING utf8mb4) COLLATE utf8mb4_unicode_ci as butcher_status,
            CONVERT('PIG_ORDER' USING utf8mb4) COLLATE utf8mb4_unicode_ci as source_type
        FROM order_total_cost otc
        LEFT JOIN swine_order_status sos ON sos.id = otc.swine_order_id
        LEFT JOIN placed_orders po ON po.swine_order_id = sos.id
        {$cs_join}
        LEFT JOIN pig_details pd ON pd.id = sos.pig_detail_id
        LEFT JOIN pig_pins pp ON pp.id = pd.cage_id
        WHERE sos.payment_status = 'paid'
          AND sos.order_status NOT IN ('preparing', 'cooking', 'delivering', 'completed', 'cancelled')

        UNION ALL

        SELECT
            CONVERT(lo.order_number   USING utf8mb4) COLLATE utf8mb4_unicode_ci as order_number,
            CONVERT(u.name            USING utf8mb4) COLLATE utf8mb4_unicode_ci as customer_name,
            CONVERT(lo.listing_name   USING utf8mb4) COLLATE utf8mb4_unicode_ci as pig_tag_id,
            CONVERT(lo.category       USING utf8mb4) COLLATE utf8mb4_unicode_ci as pin_number,
            lo.weight_kg,
            CONVERT(lo.order_status   USING utf8mb4) COLLATE utf8mb4_unicode_ci as order_status,
            CONVERT(lo.payment_status USING utf8mb4) COLLATE utf8mb4_unicode_ci as payment_status,
            lo.created_at as order_date,
            lo.pickup_date as delivery_date,
            NULL as date_to_butcher,
            NULL as time_to_butcher,
            NULL as start_time,
            NULL as end_time,
            CONVERT(CASE
                WHEN lo.payment_status = 'paid' THEN 'ready_to_schedule'
                ELSE 'not_ready'
            END USING utf8mb4) COLLATE utf8mb4_unicode_ci as butcher_status,
            CONVERT('LECHON_ORDER' USING utf8mb4) COLLATE utf8mb4_unicode_ci as source_type
        FROM lechon_orders lo
        LEFT JOIN users u ON u.id = lo.customer_id
        WHERE lo.payment_status = 'paid'
          AND lo.order_status NOT IN ('preparing', 'cooking', 'delivering', 'completed', 'cancelled')
          AND lo.category = 'WHOLE_LECHON'";

$result = $conn->query($sql);
if (!$result) {
    error_log("live-pigs SQL error: " . $conn->error);
    $pigs = [];
} else {
    $pigs = $result->fetch_all(MYSQLI_ASSOC);
}

// Sort: closest delivery_date first (most urgent at top); nulls go last
usort($pigs, function($a, $b) {
    $aDate = !empty($a['delivery_date']) ? strtotime($a['delivery_date']) : PHP_INT_MAX;
    $bDate = !empty($b['delivery_date']) ? strtotime($b['delivery_date']) : PHP_INT_MAX;
    if ($aDate !== $bDate) return $aDate - $bDate; // soonest first
    // Same date (or both null): newer order first
    return strtotime($b['order_date']) - strtotime($a['order_date']);
});
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Live Pigs for Slaughter - LechGO</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.8/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="/styles.css">
    <style>
        .card-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
        }
        
        .card-actions .form-select {
            width: 200px;
        }
        
        .badge {
            padding: 0.5rem 0.75rem;
            font-size: 0.875rem;
        }
        
        .badge-warning {
            background-color: #ffc107;
            color: #000;
        }
        
        .badge-info {
            background-color: #17a2b8;
            color: #fff;
        }
        
        .badge-success {
            background-color: #28a745;
            color: #fff;
        }
        
        .badge-secondary {
            background-color: #6c757d;
            color: #fff;
        }
        
        .selected-pig-info {
            background-color: #f8f9fa;
            padding: 1rem;
            border-radius: 0.375rem;
            margin-bottom: 1rem;
        }
        
        .selected-pig-info p {
            margin-bottom: 0.5rem;
        }
        
        .selected-pig-info p:last-child {
            margin-bottom: 0;
        }
        
        .stats-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 1rem;
        }
        
        .stat-card {
            background: white;
            border-radius: 0.5rem;
            padding: 1.5rem;
            box-shadow: 0 2px 4px rgba(0,0,0,0.1);
            display: flex;
            align-items: center;
            gap: 1rem;
        }
        
        .stat-icon {
            font-size: 2rem;
        }
        
        .stat-content h4 {
            margin: 0;
            font-size: 1.5rem;
            font-weight: bold;
        }
        
        .stat-content p {
            margin: 0;
            color: #6c757d;
            font-size: 0.875rem;
        }
        
        .form-group {
            margin-bottom: 1rem;
        }
        
        .form-group label {
            display: block;
            margin-bottom: 0.5rem;
            font-weight: 500;
        }
        
        .form-control, .form-select {
            width: 100%;
            padding: 0.5rem;
            border: 1px solid #ddd;
            border-radius: 0.375rem;
        }
    </style>
</head>
<body>
    <div class="dashboard-container">
        <!-- Include Sidebar -->
        <?php include __DIR__ . '/../layouts/sidebar.php'; ?>

        <!-- Main Content -->
        <main class="dashboard-main">
    <!-- Top Bar with Hamburger Menu -->
    <div class="dashboard-topbar">
        <button class="dashboard-mobile-toggle" id="sidebarToggle">â˜°</button>
        <h1 class="dashboard-topbar-title">Live Pigs for Slaughter</h1>
        <div class="dashboard-topbar-actions">
            <span class="dashboard-topbar-date"><?php echo date('l, F j, Y'); ?></span>
        </div>
    </div>
    
    <div class="dashboard-content">
            <!-- Header -->
            <div class="dashboard-header">
                <div class="dashboard-header-content">
                    <h1>Live Pigs for Slaughter</h1>
                    <p>Manage and process live pigs ready for slaughter</p>
                </div>
            </div>

            <!-- Content Area -->
            <div class="dashboard-content">
                <!-- Flash Messages -->
                <?php if (isset($_SESSION['success'])): ?>
                    <div class="alert alert-success show">
                        <?php echo htmlspecialchars($_SESSION['success']); ?>
                    </div>
                    <?php unset($_SESSION['success']); ?>
                <?php endif; ?>

                <?php if (isset($_SESSION['error'])): ?>
                    <div class="alert alert-error show">
                        <?php echo htmlspecialchars($_SESSION['error']); ?>
                    </div>
                    <?php unset($_SESSION['error']); ?>
                <?php endif; ?>

                <!-- Live Pigs Grid -->
                <div class="card">
                    <div class="card-header">
                        <h3>Live Pigs for Slaughter</h3>
                    </div>
                    <div class="card-body">
                        <div class="table-responsive">
                            <table class="table">
                                <thead>
                                    <tr>
                                        <th>Customer Name</th>
                                        <th>Pig Details</th>
                                        <th>Weight (kg)</th>
                                        <th>Butcher Schedule</th>
                                        <th>Status</th>
                                        <th>Actions</th>
                                    </tr>
                                </thead>
                                <tbody id="pigsTableBody">
                                    <?php if (empty($pigs)): ?>
                                        <tr>
                                            <td colspan="6" class="text-center text-muted py-4">
                                                No pigs currently scheduled for slaughter
                                            </td>
                                        </tr>
                                    <?php else: ?>
                                        <?php foreach ($pigs as $index => $pig): ?>
                                            <tr>
                                                <td><strong><?php echo htmlspecialchars($pig['customer_name']); ?></strong></td>
                                                <td>
                                                    <div><strong>Tag:</strong> <?php echo htmlspecialchars($pig['pig_tag_id']); ?></div>
                                                    <?php if (!empty($pig['source_type']) && $pig['source_type'] === 'LECHON_ORDER'): ?>
                                                        <div><small class="text-muted">Type: <?php echo htmlspecialchars(str_replace('_', ' ', $pig['pin_number'])); ?></small></div>
                                                    <?php elseif (!empty($pig['pin_number'])): ?>
                                                        <div><small class="text-muted">Pen: <?php echo htmlspecialchars($pig['pin_number']); ?></small></div>
                                                    <?php endif; ?>
                                                </td>
                                                <td><strong><?php echo number_format($pig['weight_kg'], 1); ?> kg</strong></td>
                                                <td>
                                                    <?php if ($pig['date_to_butcher'] && $pig['time_to_butcher']): ?>
                                                        <div><strong>Date:</strong> <?php echo date('M d, Y', strtotime($pig['date_to_butcher'])); ?></div>
                                                        <div><strong>Time:</strong> <?php echo date('g:i A', strtotime($pig['time_to_butcher'])); ?></div>
                                                    <?php elseif ($pig['butcher_status'] === 'ready_to_schedule'): ?>
                                                        <span style="color: #856404;">Pending Schedule</span>
                                                        <?php if (!empty($pig['delivery_date'])): ?>
                                                            <div style="margin-top:3px;font-size:0.8em;color:#c0392b;font-weight:700;">
                                                                📦 Deliver by: <?php echo date('M d, Y', strtotime($pig['delivery_date'])); ?>
                                                            </div>
                                                        <?php endif; ?>
                                                    <?php else: ?>
                                                        <span style="color: #6c757d;">Not Ready</span>
                                                    <?php endif; ?>
                                                </td>
                                                <td>
                                                    <?php
                                                    $statusClass = '';
                                                    $statusText = '';
                                                    switch ($pig['butcher_status']) {
                                                        case 'scheduled':
                                                            $statusClass = 'badge-info';
                                                            $statusText = 'Scheduled';
                                                            break;
                                                        case 'ready_to_schedule':
                                                            $statusClass = 'badge-warning';
                                                            $statusText = 'Ready to Schedule';
                                                            break;
                                                        default:
                                                            $statusClass = 'badge-secondary';
                                                            $statusText = 'Not Ready';
                                                    }
                                                    ?>
                                                    <span class="badge <?php echo $statusClass; ?>"><?php echo $statusText; ?></span>
                                                    <br><small>Order: <?php echo $pig['order_status']; ?> | Payment: <?php echo $pig['payment_status']; ?></small>
                                                </td>
                                                <td>
                                                    <?php
                                                    $hasButcherSchedule = !empty($pig['date_to_butcher']) && !empty($pig['time_to_butcher']);
                                                    $orderStatus  = $pig['order_status'];
                                                    $orderNum     = htmlspecialchars($pig['order_number']);
                                                    $sourceType   = $pig['source_type'] ?? 'PIG_ORDER';

                                                    if (in_array($orderStatus, ['preparing', 'cooking', 'delivering', 'completed'])) {
                                                        // Already processed — no buttons, just a done label
                                                        echo '<span style="display:block;width:100%;padding:6px 10px;background:#28a745;color:#fff;border-radius:4px;font-weight:700;text-align:center;margin-bottom:3px;">✔ Slaughtered</span>';
                                                    } elseif ($pig['butcher_status'] === 'ready_to_schedule') {
                                                        // Paid, not yet slaughtered — show the action button
                                                        echo '<button class="btn btn-sm btn-success" onclick="markAsSlaughtered(\'' . $orderNum . '\', \'' . $sourceType . '\')" style="display:block;width:100%;margin-bottom:3px;font-weight:700;"> Mark as Slaughtered</button>';
                                                    } elseif ($hasButcherSchedule && $pig['butcher_status'] === 'scheduled') {
                                                        echo '<button class="btn btn-sm btn-secondary" onclick="markAsSlaughtered(\'' . $orderNum . '\', \'' . $sourceType . '\')" style="display:block;width:100%;margin-bottom:3px;"> Mark as Slaughtered</button>';
                                                    } else {
                                                        echo '<span style="display:block;width:100%;padding:6px 10px;background:#ffc107;color:#000;border-radius:4px;font-weight:700;text-align:center;margin-bottom:3px;">Waiting for Schedule</span>';
                                                    }
                                                    ?>
                                                    <button class="btn btn-sm btn-primary" onclick="showOrderDetails('<?php echo $orderNum; ?>')" style="display:block;width:100%;">
                                                        View Details
                                                    </button>
                                                </td>
                                            </tr>
                                        <?php endforeach; ?>
                                    <?php endif; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>



    <script>
        // Load live pigs on page load
        document.addEventListener('DOMContentLoaded', function() {
            // Data is already loaded from PHP, no need to load via JS
            console.log('Live pigs page loaded - <?php echo count($pigs); ?> pigs found');
        });

        /**
         * Show order details modal
         */
        const allPigsData = <?php echo json_encode(array_values($pigs), JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP); ?>;

        function showOrderDetails(orderNumber) {
            const pig = allPigsData.find(p => p.order_number === orderNumber);
            if (!pig) {
                alert('Order details not found for: ' + orderNumber);
                return;
            }

            const isLechon   = pig.source_type === 'LECHON_ORDER';
            const typeLabel  = isLechon ? '🍖 Marketplace Lechon' : '🐷 Pig Order';
            const typeBg     = isLechon ? '#fce4ec' : '#e3f2fd';
            const typeColor  = isLechon ? '#880e4f' : '#1565c0';

            const butcherInfo = pig.date_to_butcher && pig.time_to_butcher
                ? `${new Date(pig.date_to_butcher).toLocaleDateString('en-PH', {year:'numeric',month:'short',day:'numeric'})} at ${pig.time_to_butcher}`
                : '<span style="color:#888;">Not scheduled yet</span>';

            const deliveryInfo = pig.delivery_date
                ? new Date(pig.delivery_date).toLocaleDateString('en-PH', {year:'numeric',month:'short',day:'numeric'})
                : '<span style="color:#888;">—</span>';

            const orderDateInfo = pig.order_date
                ? new Date(pig.order_date).toLocaleDateString('en-PH', {year:'numeric',month:'short',day:'numeric'})
                : '—';

            const payBg    = pig.payment_status === 'paid' ? '#d4edda' : '#fff3cd';
            const payColor = pig.payment_status === 'paid' ? '#155724' : '#856404';
            const payLabel = pig.payment_status === 'paid' ? 'Paid ✓' : 'Unpaid';

            const statusBg    = pig.order_status === 'preparing' ? '#cce5ff' : '#fff3cd';
            const statusColor = pig.order_status === 'preparing' ? '#004085' : '#856404';

            document.getElementById('orderDetailsContent').innerHTML = `
                <div style="display:flex;align-items:center;gap:10px;margin-bottom:1.25rem;">
                    <span style="background:${typeBg};color:${typeColor};padding:4px 12px;border-radius:20px;font-size:.8rem;font-weight:700;">${typeLabel}</span>
                    <span style="background:${statusBg};color:${statusColor};padding:4px 12px;border-radius:20px;font-size:.8rem;font-weight:700;">${pig.order_status}</span>
                    <span style="background:${payBg};color:${payColor};padding:4px 12px;border-radius:20px;font-size:.8rem;font-weight:700;">${payLabel}</span>
                </div>

                <table style="width:100%;border-collapse:collapse;font-size:.9rem;">
                    <tbody>
                        <tr style="border-bottom:1px solid #f0f0f0;">
                            <td style="padding:9px 6px;color:#888;width:42%;font-weight:600;">Order Number</td>
                            <td style="padding:9px 6px;font-weight:700;color:#2d3748;">${pig.order_number}</td>
                        </tr>
                        <tr style="border-bottom:1px solid #f0f0f0;">
                            <td style="padding:9px 6px;color:#888;font-weight:600;">Customer</td>
                            <td style="padding:9px 6px;font-weight:600;">${pig.customer_name}</td>
                        </tr>
                        <tr style="border-bottom:1px solid #f0f0f0;">
                            <td style="padding:9px 6px;color:#888;font-weight:600;">${isLechon ? 'Product' : 'Pig Tag'}</td>
                            <td style="padding:9px 6px;">${pig.pig_tag_id}</td>
                        </tr>
                        ${!isLechon ? `<tr style="border-bottom:1px solid #f0f0f0;">
                            <td style="padding:9px 6px;color:#888;font-weight:600;">Pen/Cage</td>
                            <td style="padding:9px 6px;">${pig.pin_number || '—'}</td>
                        </tr>` : ''}
                        <tr style="border-bottom:1px solid #f0f0f0;">
                            <td style="padding:9px 6px;color:#888;font-weight:600;">Weight</td>
                            <td style="padding:9px 6px;">${pig.weight_kg ? Number(pig.weight_kg).toFixed(1) + ' kg' : '—'}</td>
                        </tr>
                        <tr style="border-bottom:1px solid #f0f0f0;">
                            <td style="padding:9px 6px;color:#888;font-weight:600;">Order Date</td>
                            <td style="padding:9px 6px;">${orderDateInfo}</td>
                        </tr>
                        <tr style="border-bottom:1px solid #f0f0f0;">
                            <td style="padding:9px 6px;color:#888;font-weight:600;">Delivery Date</td>
                            <td style="padding:9px 6px;font-weight:600;color:#c0392b;">${deliveryInfo}</td>
                        </tr>
                        <tr style="border-bottom:1px solid #f0f0f0;">
                            <td style="padding:9px 6px;color:#888;font-weight:600;">Butcher Schedule</td>
                            <td style="padding:9px 6px;">${butcherInfo}</td>
                        </tr>
                    </tbody>
                </table>
            `;
            document.getElementById('orderDetailsTitle').textContent = 'Order #' + orderNumber;
            const modal = new bootstrap.Modal(document.getElementById('orderDetailsModal'));
            modal.show();
        }

        // Simplified pig slaughter workflow

        function markAsSlaughtered(orderNumber, sourceType) {
            if (confirm('Mark this pig as slaughtered?')) {
                updateOrderStatus(orderNumber, 'preparing', sourceType, 'Done! Pig marked as slaughtered.');
            }
        }

        function updateOrderStatus(orderNumber, status, sourceType, successMessage) {
            return fetch('/pig-slaughter/update-order-status', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({
                    order_number: orderNumber,
                    status: status,
                    source_type: sourceType
                })
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    if (successMessage) {
                        alert(successMessage);
                        location.reload();
                    }
                    return data;
                } else {
                    throw new Error(data.message);
                }
            })
            .catch(error => {
                console.error('Error:', error);
                alert('Error updating status: ' + error.message);
            });
        }
    </script>

<script>
// Sidebar toggle for mobile
document.getElementById('sidebarToggle').addEventListener('click', function() {
    document.getElementById('dashboardSidebar').classList.toggle('active');
});
</script>

<!-- Order Details Modal -->
<div class="modal fade" id="orderDetailsModal" tabindex="-1" aria-labelledby="orderDetailsModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content" style="border-radius:14px;border:none;box-shadow:0 10px 40px rgba(0,0,0,.12);">
            <div class="modal-header" style="background:linear-gradient(135deg,#2d3748 0%,#4a5568 100%);color:#fff;border-radius:14px 14px 0 0;padding:1.25rem 1.5rem;border:none;">
                <h5 class="modal-title" id="orderDetailsTitle" style="font-weight:700;font-size:1.1rem;margin:0;">Order Details</h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body" style="padding:1.5rem;" id="orderDetailsContent">
                <!-- Populated by showOrderDetails() -->
            </div>
            <div class="modal-footer" style="border-top:1px solid #f0f0f0;padding:1rem 1.5rem;">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal" style="border-radius:20px;padding:7px 20px;font-weight:600;">Close</button>
            </div>
        </div>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.8/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
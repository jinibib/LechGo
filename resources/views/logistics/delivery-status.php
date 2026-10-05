<?php
/**
 * Logistics Delivery Status View
 * Shows cooked lechons that are ready to be delivered or picked up by customers.
 */
$pageTitle = 'Delivery Status';
$currentPage = 'delivery-status';

$sessionMiddleware = new Session();
$user = $sessionMiddleware->getUser();

// Protect page access
if (!$sessionMiddleware->isAuthenticated() || $user['role'] !== 'logistics') {
    header('Location: /login');
    exit;
}

global $conn;

// Query cooked lechons that are ready for delivery/pickup or completed
$sql = "SELECT DISTINCT
            otc.order_number,
            otc.customer_name,
            otc.delivery_address,
            otc.delivery_method,
            otc.delivery_fee,
            otc.total_cost,
            otc.payment_type,
            otc.down_payment_amount,
            COALESCE(pd.pig_tag_id, sos.pig_tag_id) as pig_tag_id,
            COALESCE(pp.cage_number, sos.pin_number) as pin_number,
            sos.weight_kg,
            sos.order_status,
            sos.pickup_date as delivery_date,
            sos.completed_at as delivery_completed_at,
            ls.cooked_image,
            ls.internal_temperature,
            ls.skin_texture,
            ls.meat_tenderness,
            ls.quality_notes,
            ls.completed_at as cooked_at,
            u.phone as customer_phone,
            po.delivery_notes
        FROM swine_order_status sos
        JOIN order_total_cost otc ON otc.swine_order_id = sos.id
        LEFT JOIN lechon_status ls ON ls.order_number = otc.order_number
        LEFT JOIN users u ON u.id = sos.customer_id
        LEFT JOIN placed_orders po ON po.swine_order_id = sos.id
        LEFT JOIN pig_details pd ON pd.id = sos.pig_detail_id
        LEFT JOIN pig_pins pp ON pp.id = pd.cage_id
        WHERE sos.payment_status = 'paid' 
        AND sos.order_status IN ('delivering', 'completed')
        AND ls.id IS NOT NULL
        ORDER BY 
            CASE WHEN sos.order_status = 'delivering' THEN 1 ELSE 2 END,
            ls.completed_at DESC";

$stmt = $conn->prepare($sql);
$delivery_orders = [];
$pickup_orders = [];
$completed_orders = [];

if ($stmt) {
    $stmt->execute();
    $all_orders = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
    $stmt->close();
    
    foreach ($all_orders as $order) {
        if ($order['order_status'] === 'completed') {
            $completed_orders[] = $order;
        } else {
            if ($order['delivery_method'] === 'delivery') {
                $delivery_orders[] = $order;
            } else {
                $pickup_orders[] = $order;
            }
        }
    }
}
ob_start();
?>
<style>
    /* Premium Logistics Dashboard Styles */
    .logistics-container {
        padding: 1rem 0;
    }

    .logistics-summary-cards {
        display: grid;
        grid-template-columns: repeat(auto-fit, minmax(280px, 1fr));
        gap: 1.5rem;
        margin-bottom: 2.5rem;
    }

    .summary-card {
        background: white;
        border-radius: 16px;
        padding: 1.5rem;
        box-shadow: 0 4px 20px rgba(0, 0, 0, 0.05);
        display: flex;
        align-items: center;
        gap: 1.25rem;
        transition: all 0.3s ease;
        border: 1px solid rgba(226, 232, 240, 0.8);
    }

    .summary-card:hover {
        transform: translateY(-5px);
        box-shadow: 0 8px 30px rgba(0, 0, 0, 0.08);
    }

    .card-icon {
        width: 60px;
        height: 60px;
        border-radius: 12px;
        display: flex;
        align-items: center;
        justify-content: center;
        font-size: 1.75rem;
        color: white;
    }

    .bg-delivery {
        background: linear-gradient(135deg, #ff9233 0%, #ff5233 100%);
        box-shadow: 0 4px 15px rgba(255, 82, 51, 0.3);
    }

    .bg-pickup {
        background: linear-gradient(135deg, #33d1ff 0%, #3370ff 100%);
        box-shadow: 0 4px 15px rgba(51, 112, 255, 0.3);
    }

    .bg-completed {
        background: linear-gradient(135deg, #3df2a5 0%, #05be70 100%);
        box-shadow: 0 4px 15px rgba(5, 190, 112, 0.3);
    }

    .card-info h3 {
        font-size: 0.85rem;
        color: #718096;
        text-transform: uppercase;
        font-weight: 600;
        letter-spacing: 0.5px;
        margin-bottom: 0.25rem;
    }

    .card-info p {
        font-size: 1.85rem;
        font-weight: 700;
        color: #2d3748;
        margin: 0;
    }

    /* Tabs Styling */
    .logistics-tabs-nav {
        display: flex;
        border-bottom: 2px solid #e2e8f0;
        margin-bottom: 2rem;
        gap: 1.5rem;
    }

    .tab-btn {
        background: none;
        border: none;
        padding: 0.75rem 1rem;
        font-size: 1rem;
        font-weight: 600;
        color: #718096;
        position: relative;
        cursor: pointer;
        transition: all 0.3s ease;
    }

    .tab-btn:hover {
        color: #ff5233;
    }

    .tab-btn.active {
        color: #ff5233;
    }

    .tab-btn.active::after {
        content: '';
        position: absolute;
        bottom: -2px;
        left: 0;
        right: 0;
        height: 3px;
        background: #ff5233;
        border-radius: 3px 3px 0 0;
    }

    .badge-count {
        background: #edf2f7;
        color: #4a5568;
        padding: 0.15rem 0.5rem;
        border-radius: 20px;
        font-size: 0.75rem;
        margin-left: 0.5rem;
        font-weight: 700;
    }

    .tab-btn.active .badge-count {
        background: #fff5f5;
        color: #e53e3e;
    }

    .tab-content {
        display: none;
    }

    .tab-content.active {
        display: block;
        animation: fadeIn 0.4s ease;
    }

    @keyframes fadeIn {
        from { opacity: 0; transform: translateY(10px); }
        to { opacity: 1; transform: translateY(0); }
    }

    /* Delivery Cards Layout */
    .orders-grid {
        display: grid;
        grid-template-columns: repeat(auto-fill, minmax(450px, 1fr));
        gap: 2rem;
    }

    @media (max-width: 576px) {
        .orders-grid {
            grid-template-columns: 1fr;
        }
    }

    .delivery-order-card {
        background: white;
        border-radius: 16px;
        box-shadow: 0 4px 20px rgba(0,0,0,0.04);
        border: 1px solid #e2e8f0;
        overflow: hidden;
        transition: all 0.3s ease;
        display: flex;
        flex-direction: column;
    }

    .delivery-order-card:hover {
        transform: translateY(-4px);
        box-shadow: 0 10px 30px rgba(0,0,0,0.07);
    }

    .card-banner-header {
        background: linear-gradient(135deg, #1a202c 0%, #2d3748 100%);
        color: white;
        padding: 1.25rem 1.5rem;
        display: flex;
        justify-content: space-between;
        align-items: center;
    }

    .card-banner-header .order-no {
        font-weight: 700;
        font-size: 1.1rem;
        letter-spacing: 0.5px;
    }

    .card-banner-header .cook-time {
        font-size: 0.8rem;
        opacity: 0.85;
    }

    .card-body-container {
        padding: 1.5rem;
        flex-grow: 1;
        display: flex;
        flex-direction: column;
        gap: 1.25rem;
    }

    /* Customer details box */
    .info-box {
        background: #f7fafc;
        border-radius: 12px;
        padding: 1rem;
        border-left: 4px solid #ff5233;
    }

    .info-box.pickup-box {
        border-left-color: #3370ff;
    }

    .info-box.completed-box {
        border-left-color: #05be70;
    }

    .info-box-title {
        font-size: 0.75rem;
        font-weight: 700;
        text-transform: uppercase;
        color: #718096;
        margin-bottom: 0.5rem;
        display: flex;
        align-items: center;
        gap: 0.5rem;
    }

    .info-box-content {
        color: #2d3748;
        font-size: 0.95rem;
        line-height: 1.5;
    }

    .info-row {
        display: flex;
        gap: 0.5rem;
        margin-bottom: 0.25rem;
    }

    .info-row strong {
        color: #4a5568;
        min-width: 90px;
    }

    /* Pig & Quality assessment styles */
    .pig-badge-grid {
        display: grid;
        grid-template-columns: repeat(3, 1fr);
        gap: 0.75rem;
    }

    .pig-badge-item {
        background: #f8fafc;
        border: 1px solid #e2e8f0;
        border-radius: 8px;
        padding: 0.5rem;
        text-align: center;
    }

    .pig-badge-item .badge-label {
        font-size: 0.7rem;
        text-transform: uppercase;
        color: #a0aec0;
        font-weight: 700;
        margin-bottom: 0.15rem;
    }

    .pig-badge-item .badge-value {
        font-size: 0.9rem;
        font-weight: 700;
        color: #4a5568;
    }

    /* Lechon image and quality description */
    .lechon-preview-container {
        display: flex;
        gap: 1rem;
        align-items: flex-start;
        background: #fffaf0;
        border: 1px solid #feebc8;
        border-radius: 12px;
        padding: 1rem;
    }

    .lechon-thumbnail {
        width: 80px;
        height: 80px;
        border-radius: 8px;
        object-fit: cover;
        cursor: pointer;
        transition: transform 0.2s ease;
        box-shadow: 0 2px 8px rgba(0,0,0,0.1);
        border: 2px solid white;
    }

    .lechon-thumbnail:hover {
        transform: scale(1.05);
    }

    .quality-summary-text {
        font-size: 0.85rem;
        color: #744210;
        line-height: 1.4;
        flex-grow: 1;
    }

    .quality-pills {
        display: flex;
        flex-wrap: wrap;
        gap: 0.4rem;
        margin-top: 0.4rem;
    }

    .q-pill {
        font-size: 0.7rem;
        font-weight: 700;
        padding: 0.15rem 0.4rem;
        border-radius: 4px;
        text-transform: uppercase;
    }

    .q-pill-temp { background: #fee2e2; color: #991b1b; }
    .q-pill-skin { background: #dcfce7; color: #166534; }
    .q-pill-tender { background: #e0f2fe; color: #0369a1; }

    /* Action section styling */
    .card-action-bar {
        padding: 1.25rem 1.5rem;
        border-top: 1px solid #edf2f7;
        background: #fcfcfd;
        display: flex;
        justify-content: space-between;
        align-items: center;
    }

    .price-tag {
        display: flex;
        flex-direction: column;
    }

    .price-tag span {
        font-size: 0.75rem;
        color: #718096;
        text-transform: uppercase;
        font-weight: 600;
    }

    .price-tag strong {
        font-size: 1.3rem;
        font-weight: 800;
        color: #2d3748;
    }

    .btn-logistics-action {
        border: none;
        color: white;
        padding: 0.65rem 1.25rem;
        border-radius: 30px;
        font-weight: 700;
        font-size: 0.9rem;
        cursor: pointer;
        transition: all 0.3s ease;
        display: flex;
        align-items: center;
        gap: 0.5rem;
        box-shadow: 0 4px 12px rgba(255, 82, 51, 0.25);
    }

    .btn-delivery-complete {
        background: linear-gradient(135deg, #ff7b33 0%, #ff4b33 100%);
    }

    .btn-delivery-complete:hover {
        transform: translateY(-2px);
        box-shadow: 0 6px 18px rgba(255, 82, 51, 0.4);
    }

    .btn-pickup-complete {
        background: linear-gradient(135deg, #3385ff 0%, #3359ff 100%);
        box-shadow: 0 4px 12px rgba(51, 89, 255, 0.25);
    }

    .btn-pickup-complete:hover {
        transform: translateY(-2px);
        box-shadow: 0 6px 18px rgba(51, 89, 255, 0.4);
    }

    /* Modal styling for images */
    .lightbox-modal {
        display: none;
        position: fixed;
        top: 0;
        left: 0;
        width: 100%;
        height: 100%;
        background: rgba(0, 0, 0, 0.85);
        z-index: 1050;
        align-items: center;
        justify-content: center;
        padding: 2rem;
    }

    .lightbox-content {
        max-width: 90%;
        max-height: 85%;
        border-radius: 12px;
        box-shadow: 0 10px 40px rgba(0,0,0,0.5);
        border: 4px solid white;
        animation: zoomIn 0.3s ease;
    }

    @keyframes zoomIn {
        from { transform: scale(0.9); opacity: 0; }
        to { transform: scale(1); opacity: 1; }
    }

    .lightbox-close {
        position: absolute;
        top: 20px;
        right: 30px;
        color: white;
        font-size: 2.5rem;
        font-weight: 700;
        cursor: pointer;
        opacity: 0.8;
        transition: opacity 0.2s;
    }

    .lightbox-close:hover {
        opacity: 1;
    }

    /* Empty state styling */
    .empty-placeholder {
        text-align: center;
        padding: 5rem 2rem;
        background: white;
        border-radius: 16px;
        box-shadow: 0 4px 20px rgba(0,0,0,0.03);
        border: 2px dashed #e2e8f0;
        color: #718096;
        grid-column: 1 / -1;
    }

    .empty-placeholder i {
        font-size: 3.5rem;
        color: #cbd5e0;
        margin-bottom: 1rem;
        display: block;
    }

    .empty-placeholder h4 {
        font-weight: 700;
        color: #4a5568;
        margin-bottom: 0.5rem;
    }
</style>
    <!-- Navigation Tabs -->
    <div class="logistics-tabs-nav">
        <button class="tab-btn active" onclick="switchTab(event, 'tab-delivery')">
            For Delivery <span class="badge-count"><?php echo count($delivery_orders); ?></span>
        </button>
        <button class="tab-btn" onclick="switchTab(event, 'tab-pickup')">
            For Pickup <span class="badge-count"><?php echo count($pickup_orders); ?></span>
        </button>
        <button class="tab-btn" onclick="switchTab(event, 'tab-completed')">
            Completed History <span class="badge-count"><?php echo count($completed_orders); ?></span>
        </button>
    </div>

    <!-- Tab 1: For Delivery -->
    <div id="tab-delivery" class="tab-content active">
        <div class="orders-grid">
            <?php if (empty($delivery_orders)): ?>
                <div class="empty-placeholder">
                    <i class="fas fa-box-open"></i>
                    <h4>No Cooked Lechons for Delivery</h4>
                    <p>When the kitchen completes roasting a lechon order with delivery method, it will appear here.</p>
                </div>
            <?php else: ?>
                <?php foreach ($delivery_orders as $order): ?>
                    <div class="delivery-order-card" id="card-<?php echo $order['order_number']; ?>">
                        <div class="card-banner-header">
                            <span class="order-no"><?php echo $order['order_number']; ?></span>
                            <span class="cook-time">
                                Cooked: <?php echo $order['cooked_at'] ? date('M d, g:i A', strtotime($order['cooked_at'])) : 'N/A'; ?>
                            </span>
                        </div>
                        
                        <div class="card-body-container">
                            <!-- Delivery Target details -->
                            <div class="info-box">
                                <div class="info-box-title">
                                    <i class="fas fa-map-marker-alt"></i> Delivery Address & Customer
                                </div>
                                <div class="info-box-content">
                                    <div class="info-row"><strong>Customer:</strong> <?php echo htmlspecialchars($order['customer_name']); ?></div>
                                    <div class="info-row"><strong>Phone:</strong> <?php echo htmlspecialchars($order['customer_phone'] ?? 'No contact info'); ?></div>
                                    <div class="info-row"><strong>Address:</strong> <?php echo htmlspecialchars($order['delivery_address'] ?? 'No address provided'); ?></div>
                                    <?php if (!empty($order['delivery_notes'])): ?>
                                        <div class="info-row" style="margin-top: 0.5rem; border-top: 1px dashed #e2e8f0; padding-top: 0.5rem; font-style: italic; color: #718096;">
                                            <strong>Notes:</strong> <?php echo htmlspecialchars($order['delivery_notes']); ?>
                                        </div>
                                    <?php endif; ?>
                                </div>
                            </div>

                            <!-- Pig Info Grid -->
                            <div class="pig-badge-grid">
                                <div class="pig-badge-item">
                                    <div class="badge-label">Pig Tag</div>
                                    <div class="badge-value"><?php echo htmlspecialchars($order['pig_tag_id']); ?></div>
                                </div>
                                <div class="pig-badge-item">
                                    <div class="badge-label">Pen No</div>
                                    <div class="badge-value"><?php echo htmlspecialchars($order['pin_number'] ?? 'N/A'); ?></div>
                                </div>
                                <div class="pig-badge-item">
                                    <div class="badge-label">Weight</div>
                                    <div class="badge-value"><?php echo number_format($order['weight_kg'], 1); ?> kg</div>
                                </div>
                            </div>

                            <!-- Lechon Roast details & image -->
                            <div class="lechon-preview-container">
                                <?php if ($order['cooked_image']): ?>
                                    <img src="/<?php echo htmlspecialchars($order['cooked_image']); ?>" 
                                         alt="Cooked Lechon" class="lechon-thumbnail" onclick="openLightbox(this.src)">
                                <?php else: ?>
                                    <div class="lechon-thumbnail" style="background: #e2e8f0; display: flex; align-items: center; justify-content: center; font-size: 1.5rem; color: #a0aec0;">
                                        🍖
                                    </div>
                                <?php endif; ?>
                                <div class="quality-summary-text">
                                    <strong>Roasting Quality Assessment:</strong>
                                    <div class="quality-pills">
                                        <span class="q-pill q-pill-temp"><?php echo floatval($order['internal_temperature']); ?> °C</span>
                                        <span class="q-pill q-pill-skin">Skin: <?php echo htmlspecialchars($order['skin_texture']); ?></span>
                                        <span class="q-pill q-pill-tender">Meat: <?php echo htmlspecialchars(str_replace('_', ' ', $order['meat_tenderness'])); ?></span>
                                    </div>
                                    <div style="margin-top: 0.4rem; font-size: 0.8rem; color: #4a5568;">
                                        <?php echo htmlspecialchars($order['quality_notes'] ?? 'No roasting details logged.'); ?>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <div class="card-action-bar">
                            <div class="price-tag">
                                <span>Total + Delivery Fee</span>
                                <strong>₱<?php echo number_format($order['total_cost'] + $order['delivery_fee'], 2); ?></strong>
                            </div>
                            <button class="btn-logistics-action btn-delivery-complete" onclick="completeDelivery('<?php echo $order['order_number']; ?>', '<?php echo $order['payment_type']; ?>', <?php echo floatval($order['down_payment_amount'] ?? 0); ?>, '<?php echo htmlspecialchars($order['delivery_address']); ?>', '<?php echo $order['delivery_date']; ?>', '<?php echo htmlspecialchars($order['customer_name']); ?>')">
                                <i class="fas fa-check"></i> Complete Delivery
                            </button>
                        </div>
                    </div>
                <?php endforeach; ?>
            <?php endif; ?>
        </div>
    </div>

    <!-- Tab 2: For Pickup -->
    <div id="tab-pickup" class="tab-content">
        <div class="orders-grid">
            <?php if (empty($pickup_orders)): ?>
                <div class="empty-placeholder">
                    <i class="fas fa-box-open"></i>
                    <h4>No Cooked Lechons for Pickup</h4>
                    <p>When the kitchen completes roasting a lechon order with pickup method, it will appear here.</p>
                </div>
            <?php else: ?>
                <?php foreach ($pickup_orders as $order): ?>
                    <div class="delivery-order-card" id="card-<?php echo $order['order_number']; ?>">
                        <div class="card-banner-header" style="background: linear-gradient(135deg, #102a43 0%, #243e56 100%);">
                            <span class="order-no"><?php echo $order['order_number']; ?></span>
                            <span class="cook-time">
                                Cooked: <?php echo $order['cooked_at'] ? date('M d, g:i A', strtotime($order['cooked_at'])) : 'N/A'; ?>
                            </span>
                        </div>
                        
                        <div class="card-body-container">
                            <!-- Pickup details -->
                            <div class="info-box pickup-box">
                                <div class="info-box-title" style="color: #3370ff;">
                                    <i class="fas fa-store"></i> Customer Pickup Details
                                </div>
                                <div class="info-box-content">
                                    <div class="info-row"><strong>Customer:</strong> <?php echo htmlspecialchars($order['customer_name']); ?></div>
                                    <div class="info-row"><strong>Phone:</strong> <?php echo htmlspecialchars($order['customer_phone'] ?? 'No contact info'); ?></div>
                                    <div class="info-row"><strong>Delivery:</strong> Customer will pickup at outlet</div>
                                </div>
                            </div>

                            <!-- Pig Info Grid -->
                            <div class="pig-badge-grid">
                                <div class="pig-badge-item">
                                    <div class="badge-label">Pig Tag</div>
                                    <div class="badge-value"><?php echo htmlspecialchars($order['pig_tag_id']); ?></div>
                                </div>
                                <div class="pig-badge-item">
                                    <div class="badge-label">Pen No</div>
                                    <div class="badge-value"><?php echo htmlspecialchars($order['pin_number'] ?? 'N/A'); ?></div>
                                </div>
                                <div class="pig-badge-item">
                                    <div class="badge-label">Weight</div>
                                    <div class="badge-value"><?php echo number_format($order['weight_kg'], 1); ?> kg</div>
                                </div>
                            </div>

                            <!-- Lechon Roast details & image -->
                            <div class="lechon-preview-container">
                                <?php if ($order['cooked_image']): ?>
                                    <img src="/<?php echo htmlspecialchars($order['cooked_image']); ?>" 
                                         alt="Cooked Lechon" class="lechon-thumbnail" onclick="openLightbox(this.src)">
                                <?php else: ?>
                                    <div class="lechon-thumbnail" style="background: #e2e8f0; display: flex; align-items: center; justify-content: center; font-size: 1.5rem; color: #a0aec0;">
                                        🍖
                                    </div>
                                <?php endif; ?>
                                <div class="quality-summary-text">
                                    <strong>Roasting Quality Assessment:</strong>
                                    <div class="quality-pills">
                                        <span class="q-pill q-pill-temp"><?php echo floatval($order['internal_temperature']); ?> °C</span>
                                        <span class="q-pill q-pill-skin">Skin: <?php echo htmlspecialchars($order['skin_texture']); ?></span>
                                        <span class="q-pill q-pill-tender">Meat: <?php echo htmlspecialchars(str_replace('_', ' ', $order['meat_tenderness'])); ?></span>
                                    </div>
                                    <div style="margin-top: 0.4rem; font-size: 0.8rem; color: #4a5568;">
                                        <?php echo htmlspecialchars($order['quality_notes'] ?? 'No roasting details logged.'); ?>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <div class="card-action-bar">
                            <div class="price-tag">
                                <span>Total Amount Paid</span>
                                <strong>₱<?php echo number_format($order['total_cost'], 2); ?></strong>
                            </div>
                            <button class="btn-logistics-action btn-pickup-complete" onclick="completePickup('<?php echo $order['order_number']; ?>', '<?php echo htmlspecialchars($order['customer_name']); ?>')">
                                <i class="fas fa-hand-holding"></i> Mark as Picked Up
                            </button>
                        </div>
                    </div>
                <?php endforeach; ?>
            <?php endif; ?>
        </div>
    </div>

    <!-- Tab 3: Completed History -->
    <div id="tab-completed" class="tab-content">
        <div class="orders-grid">
            <?php if (empty($completed_orders)): ?>
                <div class="empty-placeholder">
                    <i class="fas fa-history"></i>
                    <h4>No Completed Deliveries Yet</h4>
                    <p>Deliveries and pickups you mark as completed will appear here.</p>
                </div>
            <?php else: ?>
                <?php foreach ($completed_orders as $order): ?>
                    <div class="delivery-order-card" style="opacity: 0.9;">
                        <div class="card-banner-header" style="background: linear-gradient(135deg, #1e293b 0%, #0f172a 100%);">
                            <span class="order-no"><?php echo $order['order_number']; ?></span>
                            <span class="cook-time">
                                Completed: <?php echo $order['delivery_completed_at'] ? date('M d, g:i A', strtotime($order['delivery_completed_at'])) : 'N/A'; ?>
                            </span>
                        </div>
                        
                        <div class="card-body-container">
                            <!-- Completed details box -->
                            <div class="info-box completed-box">
                                <div class="info-box-title" style="color: #05be70;">
                                    <i class="fas fa-check-circle"></i> Transaction Summary
                                </div>
                                <div class="info-box-content">
                                    <div class="info-row"><strong>Customer:</strong> <?php echo htmlspecialchars($order['customer_name']); ?></div>
                                    <div class="info-row"><strong>Method:</strong> <?php echo ucfirst($order['delivery_method']); ?></div>
                                    <div class="info-row"><strong>Address:</strong> <?php echo htmlspecialchars($order['delivery_address'] ?? 'Picked up from shop'); ?></div>
                                </div>
                            </div>

                            <!-- Pig Info Grid -->
                            <div class="pig-badge-grid">
                                <div class="pig-badge-item">
                                    <div class="badge-label">Pig Tag</div>
                                    <div class="badge-value"><?php echo htmlspecialchars($order['pig_tag_id']); ?></div>
                                </div>
                                <div class="pig-badge-item">
                                    <div class="badge-label">Pen No</div>
                                    <div class="badge-value"><?php echo htmlspecialchars($order['pin_number'] ?? 'N/A'); ?></div>
                                </div>
                                <div class="pig-badge-item">
                                    <div class="badge-label">Weight</div>
                                    <div class="badge-value"><?php echo number_format($order['weight_kg'], 1); ?> kg</div>
                                </div>
                            </div>
                        </div>

                        <div class="card-action-bar" style="background: #f8fafc;">
                            <div class="price-tag">
                                <span>Total Amount Paid</span>
                                <strong>₱<?php echo number_format($order['total_cost'] + ($order['delivery_method'] === 'delivery' ? $order['delivery_fee'] : 0), 2); ?></strong>
                            </div>
                            <span class="badge bg-success" style="padding: 0.6rem 1.2rem; border-radius: 20px; font-weight: 700; font-size: 0.85rem;">
                                <i class="fas fa-check"></i> COMPLETED
                            </span>
                        </div>
                    </div>
                <?php endforeach; ?>
            <?php endif; ?>
        </div>
    </div>
</div>

<!-- Complete Delivery Modal -->
<div class="modal fade" id="completeDeliveryModal" tabindex="-1" aria-labelledby="completeDeliveryModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-md">
        <div class="modal-content" style="border-radius:16px; border:none; box-shadow:0 10px 40px rgba(0,0,0,0.15);">
            <div class="modal-header" style="background:linear-gradient(135deg,#ff7b33,#ff4b33); color:white; border-radius:16px 16px 0 0; border:none; padding:1.25rem 1.5rem;">
                <h5 class="modal-title fw-bold" id="completeDeliveryModalLabel">
                    <i class="fas fa-check-circle me-2"></i> Complete Delivery
                </h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" style="filter:invert(1);"></button>
            </div>
            <form id="completeDeliveryForm" enctype="multipart/form-data">
                <input type="hidden" id="cdOrderNumber" name="order_number" />
                <input type="hidden" id="cdPaymentType" name="payment_type" />
                <input type="hidden" id="cdDownPayment" name="down_payment" />
                <div class="modal-body" style="padding:1.5rem;">

                    <!-- Order Info -->
                    <div id="cdOrderInfo" class="mb-3"></div>

                    <!-- Delivery Location & Date Info -->
                    <div class="row mb-4">
                        <div class="col-12">
                            <label class="form-label fw-bold"><i class="fas fa-map-marker-alt me-2" style="color:#ff4b33;"></i>Delivery Location</label>
                            <input type="text" id="cdDeliveryLocation" class="form-control" style="border-radius:8px; border:1px solid #e0e0e0; background:#f5f5f5; color:#555;" readonly>
                        </div>
                    </div>

                    <div class="row mb-4">
                        <div class="col-6">
                            <label class="form-label fw-bold"><i class="fas fa-calendar me-2" style="color:#ff4b33;"></i>Scheduled Date</label>
                            <input type="text" id="cdScheduledDate" class="form-control" style="border-radius:8px; border:1px solid #e0e0e0; background:#f5f5f5; color:#555;" readonly>
                        </div>
                        <div class="col-6">
                            <label class="form-label fw-bold"><i class="fas fa-clock me-2" style="color:#ff4b33;"></i>Actual Time Delivered <span class="text-danger"></span></label>
                            <input type="time" id="cdActualTime" name="actual_delivery_time" class="form-control" style="border-radius:8px; border:1px solid #e0e0e0;" required>
                        </div>
                    </div>

                    <!-- Always required: Delivery Photo -->
                    <div class="mb-4">
                        <label class="form-label fw-bold"><i class="fas fa-camera me-2" style="color:#ff4b33;"></i>Proof of Delivery Photo <span class="text-danger">*</span></label>
                        <p class="text-muted small mb-2">Take a photo of the lechon being handed over to the customer.</p>
                        
                        <!-- Action Buttons -->
                        <div class="d-flex gap-2 mb-3">
                            <button type="button" class="btn btn-primary flex-fill" onclick="openDeliveryCamera()">
                                <i class="fas fa-camera me-2"></i>Take Photo
                            </button>
                            <button type="button" class="btn btn-outline-primary flex-fill" onclick="document.getElementById('deliveryPhotoFile').click()">
                                <i class="fas fa-upload me-2"></i>Upload File
                            </button>
                        </div>

                        <!-- Camera Preview (Hidden by default) -->
                        <div id="deliveryCameraContainer" class="d-none mb-3">
                            <div class="position-relative" style="border-radius: 12px; overflow: hidden;">
                                <video id="deliveryCameraPreview" autoplay playsinline style="width: 100%; max-height: 400px; object-fit: cover; background: #000;"></video>
                                <div class="position-absolute bottom-0 start-0 end-0 p-3 d-flex justify-content-center gap-2" style="background: linear-gradient(to top, rgba(0,0,0,0.7), transparent);">
                                    <button type="button" class="btn btn-success btn-lg" onclick="captureDeliveryPhoto()">
                                        <i class="fas fa-camera me-2"></i>Capture
                                    </button>
                                    <button type="button" class="btn btn-danger btn-lg" onclick="closeDeliveryCamera()">
                                        <i class="fas fa-times me-2"></i>Cancel
                                    </button>
                                </div>
                            </div>
                        </div>

                        <div class="upload-zone" id="deliveryPhotoZone" onclick="document.getElementById('deliveryPhotoFile').click()" style="border:2px dashed #ff7b33; border-radius:12px; padding:1.5rem; text-align:center; cursor:pointer; background:#fffaf0; transition:all 0.2s;">
                            <div id="deliveryPhotoContent">
                                <i class="fas fa-cloud-upload-alt fa-2x" style="color:#ff7b33;"></i>
                                <p class="mb-0 mt-2 fw-semibold" style="color:#ff7b33;">Click to upload delivery photo</p>
                                <small class="text-muted">JPG, PNG (max 5MB)</small>
                            </div>
                            <div id="deliveryPhotoPreview" class="d-none">
                                <img id="deliveryPhotoImg" src="" alt="Preview" style="max-height:150px; border-radius:8px; object-fit:cover;">
                                <p class="mt-1 mb-0 small text-success fw-bold"><i class="fas fa-check"></i> Photo selected</p>
                            </div>
                        </div>
                        <input type="file" id="deliveryPhotoFile" name="delivery_photo" accept="image/*" class="d-none">
                    </div>

                    <!-- Conditional: Payment Collection Photo (downpayment only) -->
                    <div id="paymentPhotoSection" class="mb-3" style="display:none;">
                        <div class="alert" style="background:linear-gradient(135deg,#fff3cd,#ffeaa7); border:none; border-radius:10px; padding:0.75rem 1rem;">
                            <i class="fas fa-coins me-2" style="color:#856404;"></i>
                            <strong style="color:#856404;">Remaining Balance Collection</strong>
                            <p class="mb-0 small mt-1" style="color:#856404;">This customer paid a <strong>downpayment</strong>. Take a photo of collecting the remaining balance: <strong id="remainingAmount"></strong></p>
                        </div>
                        <label class="form-label fw-bold mt-2"><i class="fas fa-money-bill-wave me-2" style="color:#856404;"></i>Payment Collection Photo <span class="text-danger">*</span></label>
                        <p class="text-muted small mb-2">Photo proof of receiving the remaining balance from the customer.</p>
                        
                        <!-- Action Buttons -->
                        <div class="d-flex gap-2 mb-3">
                            <button type="button" class="btn btn-warning flex-fill" onclick="openPaymentCamera()">
                                <i class="fas fa-camera me-2"></i>Take Photo
                            </button>
                            <button type="button" class="btn btn-outline-warning flex-fill" onclick="document.getElementById('paymentPhotoFile').click()">
                                <i class="fas fa-upload me-2"></i>Upload File
                            </button>
                        </div>

                        <!-- Camera Preview (Hidden by default) -->
                        <div id="paymentCameraContainer" class="d-none mb-3">
                            <div class="position-relative" style="border-radius: 12px; overflow: hidden;">
                                <video id="paymentCameraPreview" autoplay playsinline style="width: 100%; max-height: 400px; object-fit: cover; background: #000;"></video>
                                <div class="position-absolute bottom-0 start-0 end-0 p-3 d-flex justify-content-center gap-2" style="background: linear-gradient(to top, rgba(0,0,0,0.7), transparent);">
                                    <button type="button" class="btn btn-success btn-lg" onclick="capturePaymentPhoto()">
                                        <i class="fas fa-camera me-2"></i>Capture
                                    </button>
                                    <button type="button" class="btn btn-danger btn-lg" onclick="closePaymentCamera()">
                                        <i class="fas fa-times me-2"></i>Cancel
                                    </button>
                                </div>
                            </div>
                        </div>

                        <div class="upload-zone" id="paymentPhotoZone" onclick="document.getElementById('paymentPhotoFile').click()" style="border:2px dashed #ffc107; border-radius:12px; padding:1.5rem; text-align:center; cursor:pointer; background:#fffdf0; transition:all 0.2s;">
                            <div id="paymentPhotoContent">
                                <i class="fas fa-cloud-upload-alt fa-2x" style="color:#ffc107;"></i>
                                <p class="mb-0 mt-2 fw-semibold" style="color:#856404;">Click to upload payment photo</p>
                                <small class="text-muted">JPG, PNG (max 5MB)</small>
                            </div>
                            <div id="paymentPhotoPreview" class="d-none">
                                <img id="paymentPhotoImg" src="" alt="Preview" style="max-height:150px; border-radius:8px; object-fit:cover;">
                                <p class="mt-1 mb-0 small text-success fw-bold"><i class="fas fa-check"></i> Photo selected</p>
                            </div>
                        </div>
                        <input type="file" id="paymentPhotoFile" name="payment_photo" accept="image/*" class="d-none">
                    </div>

                </div>
                <div class="modal-footer" style="border:none; padding:1rem 1.5rem;">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="button" class="btn fw-bold" id="submitDeliveryBtn" onclick="submitCompleteDelivery()" style="background:linear-gradient(135deg,#ff7b33,#ff4b33); color:white; border:none; border-radius:20px; padding:0.6rem 1.5rem;">
                        <i class="fas fa-check me-1"></i> Confirm Complete
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Lightbox Image Modal -->
<div class="lightbox-modal" id="imageLightbox" onclick="closeLightbox()">
    <span class="lightbox-close">&times;</span>
    <img src="" class="lightbox-content" id="lightboxImage" onclick="event.stopPropagation()">
</div>

<script>
    // Tab Switching Function
    function switchTab(evt, tabId) {
        const tabContents = document.getElementsByClassName("tab-content");
        for (let i = 0; i < tabContents.length; i++) {
            tabContents[i].classList.remove("active");
        }

        const tabBtns = document.getElementsByClassName("tab-btn");
        for (let i = 0; i < tabBtns.length; i++) {
            tabBtns[i].classList.remove("active");
        }

        document.getElementById(tabId).classList.add("active");
        evt.currentTarget.classList.add("active");
    }

    // Lightbox image functions
    function openLightbox(src) {
        const modal = document.getElementById("imageLightbox");
        const img = document.getElementById("lightboxImage");
        img.src = src;
        modal.style.display = "flex";
    }

    function closeLightbox() {
        const modal = document.getElementById("imageLightbox");
        modal.style.display = "none";
    }

    // Open Complete Delivery Modal (for delivery orders only)
    let _currentOrderNumber = null;
    let _isDownPayment = false;

    // Complete Pickup Function (no modal needed)
    function completePickup(orderNumber, customerName) {
        if (!confirm(`Mark order ${orderNumber} as picked up by ${customerName}?`)) {
            return;
        }

        // Create form data for pickup completion
        const formData = new FormData();
        formData.append('order_number', orderNumber);
        formData.append('is_pickup', 'true'); // Flag to indicate this is a pickup

        // Show loading state
        const btn = event.target;
        const originalHTML = btn.innerHTML;
        btn.disabled = true;
        btn.innerHTML = '<i class="fas fa-spinner fa-spin me-1"></i> Processing...';

        fetch('/logistics/complete-delivery', {
            method: 'POST',
            body: formData
        })
        .then(r => r.json())
        .then(data => {
            if (data.success) {
                alert('Order ' + orderNumber + ' marked as picked up successfully!');
                window.location.reload();
            } else {
                alert('Error: ' + (data.message || 'Failed to complete pickup'));
                btn.disabled = false;
                btn.innerHTML = originalHTML;
            }
        })
        .catch(() => {
            alert('Network error. Please try again.');
            btn.disabled = false;
            btn.innerHTML = originalHTML;
        });
    }

    function completeDelivery(orderNumber, paymentType, downPaymentAmount, deliveryAddress, deliveryDate, customerName) {
        _currentOrderNumber = orderNumber;
        _isDownPayment = (paymentType === 'down');

        // Close cameras if open
        closeDeliveryCamera();
        closePaymentCamera();

        // Reset form
        document.getElementById('completeDeliveryForm').reset();
        document.getElementById('cdOrderNumber').value = orderNumber;
        document.getElementById('cdPaymentType').value = paymentType;
        document.getElementById('cdDownPayment').value = downPaymentAmount;
        document.getElementById('deliveryPhotoPreview').classList.add('d-none');
        document.getElementById('deliveryPhotoContent').classList.remove('d-none');
        document.getElementById('paymentPhotoPreview').classList.add('d-none');
        document.getElementById('paymentPhotoContent').classList.remove('d-none');

        // Format delivery date
        const deliveryDateObj = new Date(deliveryDate);
        const formattedDate = deliveryDateObj.toLocaleDateString('en-PH', {year: 'numeric', month: 'short', day: 'numeric'});

        // Order info
        document.getElementById('cdOrderInfo').innerHTML = `
            <div style="background:linear-gradient(135deg,#fff5f0,#ffe8e0); border-radius:10px; padding:0.75rem 1rem; border-left:4px solid #ff4b33;">
                <strong style="color:#cc3300;">Order:</strong> ${orderNumber}<br>
                <small style="color:#cc3300;">Customer: ${customerName}</small><br>
                <small style="color:#cc3300;">Payment: ${_isDownPayment ? '<span style="background:#ffc107;color:#333;padding:2px 8px;border-radius:4px;font-weight:700;">DOWNPAYMENT</span>' : '<span style="background:#28a745;color:white;padding:2px 8px;border-radius:4px;font-weight:700;">FULL PAYMENT</span>'}</small>
            </div>`;

        // Set location and delivery date fields
        document.getElementById('cdDeliveryLocation').value = deliveryAddress;
        document.getElementById('cdScheduledDate').value = formattedDate;
        
        // Auto-fill current time in HH:MM format
        const now = new Date();
        const hours = String(now.getHours()).padStart(2, '0');
        const minutes = String(now.getMinutes()).padStart(2, '0');
        document.getElementById('cdActualTime').value = `${hours}:${minutes}`;

        // Show/hide payment section
        const paySection = document.getElementById('paymentPhotoSection');
        if (_isDownPayment) {
            paySection.style.display = 'block';
            document.getElementById('remainingAmount').textContent = '₱' + parseFloat(downPaymentAmount || 0).toLocaleString('en-PH', {minimumFractionDigits:2});
        } else {
            paySection.style.display = 'none';
        }

        new bootstrap.Modal(document.getElementById('completeDeliveryModal')).show();
    }

    // Image preview handlers
    document.getElementById('deliveryPhotoFile').addEventListener('change', function() {
        if (this.files[0]) {
            const reader = new FileReader();
            reader.onload = e => {
                document.getElementById('deliveryPhotoImg').src = e.target.result;
                document.getElementById('deliveryPhotoPreview').classList.remove('d-none');
                document.getElementById('deliveryPhotoContent').classList.add('d-none');
            };
            reader.readAsDataURL(this.files[0]);
        }
    });

    document.getElementById('paymentPhotoFile').addEventListener('change', function() {
        if (this.files[0]) {
            const reader = new FileReader();
            reader.onload = e => {
                document.getElementById('paymentPhotoImg').src = e.target.result;
                document.getElementById('paymentPhotoPreview').classList.remove('d-none');
                document.getElementById('paymentPhotoContent').classList.add('d-none');
            };
            reader.readAsDataURL(this.files[0]);
        }
    });

    // Camera Functions for Delivery Photo
    let deliveryCameraStream = null;

    async function openDeliveryCamera() {
        try {
            const constraints = {
                video: {
                    facingMode: 'environment', // Use back camera on mobile
                    width: { ideal: 1920 },
                    height: { ideal: 1080 }
                }
            };

            deliveryCameraStream = await navigator.mediaDevices.getUserMedia(constraints);
            const video = document.getElementById('deliveryCameraPreview');
            video.srcObject = deliveryCameraStream;
            
            // Show camera container, hide upload area
            document.getElementById('deliveryCameraContainer').classList.remove('d-none');
            document.getElementById('deliveryPhotoZone').style.display = 'none';
            
        } catch (error) {
            console.error('Error accessing camera:', error);
            alert('Unable to access camera. Please check permissions or use file upload instead.');
        }
    }

    function closeDeliveryCamera() {
        if (deliveryCameraStream) {
            deliveryCameraStream.getTracks().forEach(track => track.stop());
            deliveryCameraStream = null;
        }
        
        // Hide camera container, show upload area
        document.getElementById('deliveryCameraContainer').classList.add('d-none');
        document.getElementById('deliveryPhotoZone').style.display = 'block';
    }

    function captureDeliveryPhoto() {
        const video = document.getElementById('deliveryCameraPreview');
        const canvas = document.createElement('canvas');
        
        canvas.width = video.videoWidth;
        canvas.height = video.videoHeight;
        
        const context = canvas.getContext('2d');
        context.drawImage(video, 0, 0, canvas.width, canvas.height);
        
        canvas.toBlob(function(blob) {
            const file = new File([blob], 'delivery-photo.jpg', { type: 'image/jpeg' });
            
            const dataTransfer = new DataTransfer();
            dataTransfer.items.add(file);
            document.getElementById('deliveryPhotoFile').files = dataTransfer.files;
            
            // Show preview
            const reader = new FileReader();
            reader.onload = function(e) {
                document.getElementById('deliveryPhotoImg').src = e.target.result;
                document.getElementById('deliveryPhotoPreview').classList.remove('d-none');
                document.getElementById('deliveryPhotoContent').classList.add('d-none');
            };
            reader.readAsDataURL(file);
            
            closeDeliveryCamera();
            document.getElementById('deliveryPhotoZone').style.display = 'block';
            
        }, 'image/jpeg', 0.9);
    }

    // Camera Functions for Payment Photo
    let paymentCameraStream = null;

    async function openPaymentCamera() {
        try {
            const constraints = {
                video: {
                    facingMode: 'environment',
                    width: { ideal: 1920 },
                    height: { ideal: 1080 }
                }
            };

            paymentCameraStream = await navigator.mediaDevices.getUserMedia(constraints);
            const video = document.getElementById('paymentCameraPreview');
            video.srcObject = paymentCameraStream;
            
            document.getElementById('paymentCameraContainer').classList.remove('d-none');
            document.getElementById('paymentPhotoZone').style.display = 'none';
            
        } catch (error) {
            console.error('Error accessing camera:', error);
            alert('Unable to access camera. Please check permissions or use file upload instead.');
        }
    }

    function closePaymentCamera() {
        if (paymentCameraStream) {
            paymentCameraStream.getTracks().forEach(track => track.stop());
            paymentCameraStream = null;
        }
        
        document.getElementById('paymentCameraContainer').classList.add('d-none');
        document.getElementById('paymentPhotoZone').style.display = 'block';
    }

    function capturePaymentPhoto() {
        const video = document.getElementById('paymentCameraPreview');
        const canvas = document.createElement('canvas');
        
        canvas.width = video.videoWidth;
        canvas.height = video.videoHeight;
        
        const context = canvas.getContext('2d');
        context.drawImage(video, 0, 0, canvas.width, canvas.height);
        
        canvas.toBlob(function(blob) {
            const file = new File([blob], 'payment-photo.jpg', { type: 'image/jpeg' });
            
            const dataTransfer = new DataTransfer();
            dataTransfer.items.add(file);
            document.getElementById('paymentPhotoFile').files = dataTransfer.files;
            
            const reader = new FileReader();
            reader.onload = function(e) {
                document.getElementById('paymentPhotoImg').src = e.target.result;
                document.getElementById('paymentPhotoPreview').classList.remove('d-none');
                document.getElementById('paymentPhotoContent').classList.add('d-none');
            };
            reader.readAsDataURL(file);
            
            closePaymentCamera();
            document.getElementById('paymentPhotoZone').style.display = 'block';
            
        }, 'image/jpeg', 0.9);
    }

    // Close cameras when modal is closed
    document.getElementById('completeDeliveryModal').addEventListener('hidden.bs.modal', function () {
        closeDeliveryCamera();
        closePaymentCamera();
    });

    function submitCompleteDelivery() {
        const deliveryFile = document.getElementById('deliveryPhotoFile').files[0];
        const actualTime = document.getElementById('cdActualTime').value;
        
        if (!deliveryFile) {
            alert('Please take a photo or upload a proof of delivery photo.');
            return;
        }
        if (!actualTime) {
            alert('Please enter the actual delivery time.');
            return;
        }
        
        const paymentFile = document.getElementById('paymentPhotoFile').files[0];
        if (_isDownPayment && !paymentFile) {
            alert('Please take a photo or upload a payment collection photo for this downpayment order.');
            return;
        }

        const btn = document.getElementById('submitDeliveryBtn');
        btn.disabled = true;
        btn.innerHTML = '<i class="fas fa-spinner fa-spin me-1"></i> Processing...';

        const formData = new FormData(document.getElementById('completeDeliveryForm'));

        fetch('/logistics/complete-delivery', {
            method: 'POST',
            body: formData
        })
        .then(r => r.json())
        .then(data => {
            if (data.success) {
                bootstrap.Modal.getInstance(document.getElementById('completeDeliveryModal')).hide();
                alert('Order ' + _currentOrderNumber + ' completed successfully!');
                window.location.reload();
            } else {
                alert('Error: ' + (data.message || 'Failed to complete delivery'));
                btn.disabled = false;
                btn.innerHTML = '<i class="fas fa-check me-1"></i> Confirm Complete';
            }
        })
        .catch(() => {
            alert('Network error. Please try again.');
            btn.disabled = false;
            btn.innerHTML = '<i class="fas fa-check me-1"></i> Confirm Complete';
        });
    }

    // Close lightbox modal with Escape key
    document.addEventListener('keydown', function(e) {
        if (e.key === 'Escape') {
            closeLightbox();
        }
    });
</script>
<?php
$content = ob_get_clean();
include VIEWS_PATH . '/layouts/dashboard-layout.php';
?>

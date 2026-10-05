<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);

$currentPage = 'delivery-records';

$user = $_SESSION['user'] ?? null;
if (!$user) {
    header('Location:/public/login');
    exit;
}

// Get livestock owner ID
$query = "SELECT id FROM livestock_owners WHERE user_id = ?";
$stmt = $GLOBALS['conn']->prepare($query);
if (!$stmt) {
    $_SESSION['error'] = 'Database error: ' . $GLOBALS['conn']->error;
    header('Location:/public/dashboard');
    exit;
}
$stmt->bind_param('i', $user['id']);
$stmt->execute();
$result = $stmt->get_result();
$owner = $result->fetch_assoc();
$stmt->close();

if (!$owner) {
    $_SESSION['error'] = 'Livestock owner profile not found';
    header('Location:/public/dashboard');
    exit;
}

// Fetch delivery records from orders associated with this livestock owner
$query = "SELECT 
            ds.id,
            ds.order_number,
            ds.driver_user_id,
            ds.delivery_method,
            ds.delivery_address,
            ds.delivery_photo,
            ds.payment_photo,
            ds.delivered_at,
            u.name AS driver_name,
            sos.id as order_id,
            sos.total_price,
            sos.payment_method,
            sos.customer_id,
            sos.pig_tag_id,
            cu.name as customer_name,
            r.id as review_id,
            r.rating,
            r.comment as review_comment,
            r.created_at as review_date
        FROM delivery_status ds
        LEFT JOIN users u ON ds.driver_user_id = u.id
        JOIN swine_order_status sos ON sos.order_number = ds.order_number
        LEFT JOIN users cu ON cu.id = sos.customer_id
        LEFT JOIN reviews r ON r.order_id = sos.id
        WHERE sos.livestock_owner_id = ?
        ORDER BY ds.delivered_at DESC";

$stmt = $GLOBALS['conn']->prepare($query);
if (!$stmt) {
    $_SESSION['error'] = 'Database error: ' . $GLOBALS['conn']->error;
    header('Location:/public/dashboard');
    exit;
}
$stmt->bind_param('i', $owner['id']);
$stmt->execute();
$result = $stmt->get_result();
$deliveries = $result->fetch_all(MYSQLI_ASSOC) ?? [];
$stmt->close();

// Calculate review statistics
$total_reviews = 0;
$total_rating = 0;
foreach ($deliveries as $delivery) {
    if ($delivery['review_id']) {
        $total_reviews++;
        $total_rating += $delivery['rating'];
    }
}
$average_rating = $total_reviews > 0 ? $total_rating / $total_reviews : 0;

ob_start();
?>
<div class="delivery-container">
    <!-- Header -->
    <div class="delivery-header">
        <div class="delivery-header-icon">
            <i class="fas fa-truck"></i>
        </div>
        <div class="delivery-header-text">
            <h1>Delivery Records</h1>
            <p>Track all deliveries sent by logistics</p>
        </div>
    </div>

    <!-- Delivery Records List -->
    <?php if (empty($deliveries)): ?>
        <div class="empty-state">
            <div class="empty-state-icon">
                <i class="fas fa-box-open"></i>
            </div>
            <div class="empty-state-text">
                <h3>No Delivery Records Yet</h3>
                <p>When your lechon orders are delivered, they will appear here with photos and details.</p>
            </div>
        </div>
    <?php else: ?>
        <div class="table-responsive">
            <table class="delivery-table">
                <thead>
                    <tr>
                        <th>Order Number</th>
                        <th>Date</th>
                        <th>Customer</th>
                        <th>Driver</th>
                        <th>Delivery Address</th>
                        <th>Amount</th>
                        <th>Payment</th>
                        <th>Photo</th>
                        <th>Review</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($deliveries as $delivery): ?>
                        <tr>
                            <td class="order-col">
                                <span class="order-badge"><?php echo htmlspecialchars($delivery['order_number']); ?></span>
                            </td>
                            <td class="date-col">
                                <?php echo date('M d, Y', strtotime($delivery['delivered_at'])); ?>
                            </td>
                            <td class="customer-col">
                                <?php echo htmlspecialchars($delivery['customer_name'] ?? 'N/A'); ?>
                            </td>
                            <td class="driver-col">
                                <?php echo !empty($delivery['driver_name']) ? htmlspecialchars($delivery['driver_name']) : '-'; ?>
                            </td>
                            <td class="address-col">
                                <?php echo !empty($delivery['delivery_address']) ? htmlspecialchars($delivery['delivery_address']) : '-'; ?>
                            </td>
                            <td class="amount-col">
                                <span class="amount-badge">₱<?php echo number_format($delivery['total_price'], 2); ?></span>
                            </td>
                            <td class="payment-col">
                                <?php echo !empty($delivery['payment_method']) ? htmlspecialchars(str_replace('_', ' ', ucfirst($delivery['payment_method']))) : '-'; ?>
                            </td>
                            <td class="photo-col">
                                <?php if (!empty($delivery['delivery_photo'])): ?>
                                    <img src="/LechGo_Final/public/<?php echo htmlspecialchars($delivery['delivery_photo']); ?>" 
                                         alt="Delivery Photo" 
                                         class="thumbnail-img" 
                                         onclick="openLightbox('/LechGo_Final/public/<?php echo htmlspecialchars($delivery['delivery_photo']); ?>')"
                                         title="Click to view">
                                <?php else: ?>
                                    <span class="no-photo">No Photo</span>
                                <?php endif; ?>
                            </td>
                            <td class="review-col">
                                <?php if ($delivery['review_id']): ?>
                                    <button class="review-btn has-review" onclick="showReviewModal(<?php echo htmlspecialchars(json_encode($delivery)); ?>)">
                                        <span class="star-rating">
                                            <?php for ($i = 1; $i <= 5; $i++): ?>
                                                <?php echo $i <= $delivery['rating'] ? '⭐' : '☆'; ?>
                                            <?php endfor; ?>
                                        </span>
                                        <span class="rating-number"><?php echo $delivery['rating']; ?>/5</span>
                                    </button>
                                <?php else: ?>
                                    <span class="no-review">No review yet</span>
                                <?php endif; ?>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    <?php endif; ?>
</div>

<!-- Lightbox Modal -->
<div class="lightbox-modal" id="imageLightbox">
    <div class="lightbox-content" onclick="event.stopPropagation();">
        <span class="lightbox-close" onclick="closeLightbox();">&times;</span>
        <img class="lightbox-img" id="lightboxImage" src="" alt="">
    </div>
</div>

<!-- Review Modal -->
<div class="review-modal" id="reviewModal">
    <div class="review-modal-content">
        <span class="review-modal-close" onclick="closeReviewModal()">&times;</span>
        <div class="review-modal-header">
            <h3> Customer Review</h3>
        </div>
        <div class="review-modal-body" id="reviewModalBody">
            <!-- Content will be populated by JavaScript -->
        </div>
    </div>
</div>

<style>
    .delivery-container {
        background: white;
        padding: 15px;
        border-radius: 12px;
        box-shadow: 0 2px 8px rgba(0,0,0,0.08);
    }

    .delivery-header {
        display: flex;
        align-items: center;
        gap: 10px;
        margin-bottom: 15px;
        padding-bottom: 10px;
        border-bottom: 2px solid #f0f0f0;
    }

    .delivery-header-icon {
        width: 40px;
        height: 40px;
        background: linear-gradient(135deg, #ff7b33, #ff4b33);
        border-radius: 50%;
        display: flex;
        align-items: center;
        justify-content: center;
        color: white;
        font-size: 20px;
    }

    .delivery-header-text h1 {
        margin: 0;
        color: #2c3e50;
        font-size: 22px;
        font-weight: 700;
    }

    .delivery-header-text p {
        margin: 2px 0 0;
        color: #666;
        font-size: 12px;
    }

    .table-responsive {
        overflow-x: auto;
        -webkit-overflow-scrolling: touch;
    }

    .delivery-table {
        width: 100%;
        border-collapse: collapse;
        background: white;
        min-width: 600px;
    }

    .delivery-table thead {
        background: linear-gradient(135deg, #ff7b33, #ff4b33);
        color: white;
        position: sticky;
        top: 0;
        z-index: 10;
    }

    .delivery-table th {
        padding: 12px 10px;
        text-align: left;
        font-weight: 600;
        font-size: 11px;
        text-transform: uppercase;
        border-bottom: 2px solid #ff6b35;
        white-space: nowrap;
    }

    .delivery-table tbody tr {
        border-bottom: 1px solid #e0e0e0;
        transition: background 0.2s;
    }

    .delivery-table tbody tr:hover {
        background: #f9f9f9;
    }

    .delivery-table tbody tr:last-child {
        border-bottom: none;
    }

    .delivery-table td {
        padding: 12px 10px;
        font-size: 12px;
    }

    .order-col {
        font-weight: 600;
        color: #2c3e50;
        min-width: 120px;
    }

    .order-badge {
        background: #ffe8dc;
        color: #ff7b33;
        padding: 4px 8px;
        border-radius: 6px;
        font-weight: 700;
        font-size: 11px;
    }

    .date-col {
        color: #666;
        font-size: 11px;
        min-width: 80px;
    }

    .driver-col {
        color: #2c3e50;
        min-width: 100px;
    }

    .address-col {
        color: #666;
        min-width: 150px;
        max-width: 200px;
        overflow: hidden;
        text-overflow: ellipsis;
        white-space: nowrap;
    }

    .amount-col {
        font-weight: 600;
        min-width: 80px;
        text-align: right;
    }

    .amount-badge {
        background: #e8f5e9;
        color: #27ae60;
        padding: 4px 8px;
        border-radius: 6px;
        font-weight: 700;
        font-size: 11px;
    }

    .payment-col {
        color: #2c3e50;
        font-size: 11px;
        min-width: 90px;
    }

    .photo-col {
        text-align: center;
        min-width: 70px;
    }

    .thumbnail-img {
        width: 50px;
        height: 50px;
        object-fit: cover;
        border-radius: 8px;
        cursor: pointer;
        border: 2px solid #e0e0e0;
        transition: all 0.2s;
    }

    .thumbnail-img:hover {
        border-color: #ff7b33;
        box-shadow: 0 2px 8px rgba(255, 123, 51, 0.3);
        transform: scale(1.1);
    }

    .no-photo {
        color: #999;
        font-size: 11px;
        font-weight: 600;
    }

    .empty-state {
        text-align: center;
        padding: 40px 20px;
        color: #999;
    }

    .empty-state-icon {
        font-size: 48px;
        color: #e0e0e0;
        margin-bottom: 10px;
    }

    .empty-state-text h3 {
        color: #2c3e50;
        font-weight: 600;
        margin-bottom: 5px;
    }

    .empty-state-text p {
        color: #999;
        font-size: 13px;
    }

    /* Lightbox Modal */
    .lightbox-modal {
        display: none;
        position: fixed;
        z-index: 9999;
        left: 0;
        top: 0;
        width: 100%;
        height: 100%;
        background: rgba(0,0,0,0.95);
        cursor: pointer;
    }

    .lightbox-modal.active {
        display: flex;
        align-items: center;
        justify-content: center;
    }

    .lightbox-content {
        max-width: 90%;
        max-height: 90%;
        position: relative;
    }

    .lightbox-img {
        max-width: 100%;
        max-height: 100%;
        border-radius: 8px;
    }

    .lightbox-close {
        position: absolute;
        top: 20px;
        right: 30px;
        color: white;
        font-size: 40px;
        font-weight: bold;
        cursor: pointer;
        background: rgba(0,0,0,0.5);
        width: 50px;
        height: 50px;
        display: flex;
        align-items: center;
        justify-content: center;
        border-radius: 50%;
        transition: all 0.3s;
    }

    .lightbox-close:hover {
        background: rgba(0,0,0,0.8);
    }

    /* Review Column */
    .review-col {
        text-align: center;
        min-width: 130px;
    }

    .customer-col {
        color: #2c3e50;
        min-width: 120px;
    }

    .review-btn {
        background: #fff9e6;
        border: 2px solid #ffc107;
        border-radius: 8px;
        padding: 8px 12px;
        cursor: pointer;
        transition: all 0.2s;
        font-size: 11px;
        display: inline-flex;
        flex-direction: column;
        align-items: center;
        gap: 4px;
    }

    .review-btn:hover {
        background: #ffc107;
        transform: translateY(-2px);
        box-shadow: 0 4px 8px rgba(255, 193, 7, 0.3);
    }

    .review-btn.has-review {
        border-color: #27ae60;
        background: #e8f5e9;
    }

    .review-btn.has-review:hover {
        background: #27ae60;
        color: white;
    }

    .star-rating {
        font-size: 14px;
        color: #ffc107;
    }

    .rating-number {
        font-weight: 700;
        color: #333;
        font-size: 10px;
    }

    .review-btn.has-review:hover .rating-number {
        color: white;
    }

    .no-review {
        color: #999;
        font-size: 11px;
        font-style: italic;
    }

    /* Review Modal */
    .review-modal {
        display: none;
        position: fixed;
        z-index: 10000;
        left: 0;
        top: 0;
        width: 100%;
        height: 100%;
        background: rgba(0,0,0,0.6);
        backdrop-filter: blur(5px);
    }

    .review-modal.active {
        display: flex;
        align-items: center;
        justify-content: center;
    }

    .review-modal-content {
        background: white;
        border-radius: 12px;
        max-width: 600px;
        width: 90%;
        max-height: 80vh;
        overflow-y: auto;
        box-shadow: 0 10px 40px rgba(0,0,0,0.3);
        position: relative;
    }

    .review-modal-header {
        background: linear-gradient(135deg, #df0e0eff 0%, #f70000ff 100%);
        color: white;
        padding: 20px;
        border-radius: 12px 12px 0 0;
    }

    .review-modal-header h3 {
        margin: 0;
        font-size: 20px;
    }

    .review-modal-body {
        padding: 20px;
    }

    .review-modal-close {
        position: absolute;
        top: 15px;
        right: 20px;
        color: white;
        font-size: 28px;
        font-weight: bold;
        cursor: pointer;
        z-index: 1;
        width: 35px;
        height: 35px;
        display: flex;
        align-items: center;
        justify-content: center;
        border-radius: 50%;
        background: rgba(0,0,0,0.2);
        transition: all 0.3s;
    }

    .review-modal-close:hover {
        background: rgba(0,0,0,0.4);
        transform: rotate(90deg);
    }

    .review-detail-item {
        margin-bottom: 20px;
        padding-bottom: 15px;
        border-bottom: 1px solid #eee;
    }

    .review-detail-item:last-child {
        border-bottom: none;
    }

    .review-detail-label {
        font-weight: 700;
        color: #666;
        font-size: 12px;
        text-transform: uppercase;
        margin-bottom: 5px;
    }

    .review-detail-value {
        font-size: 14px;
        color: #2c3e50;
    }

    .review-rating-display {
        display: flex;
        align-items: center;
        gap: 10px;
        font-size: 24px;
        color: #ffc107;
    }

    .review-rating-text {
        font-size: 18px;
        font-weight: 700;
        color: #333;
    }

    .review-comment-box {
        background: #f8f9fa;
        padding: 15px;
        border-radius: 8px;
        border-left: 4px solid #27ae60;
        font-style: italic;
        line-height: 1.6;
        color: #333;
    }

    .review-comment-box:before {
        content: '"';
        font-size: 24px;
        color: #27ae60;
        font-weight: bold;
    }

    .review-comment-box:after {
        content: '"';
        font-size: 24px;
        color: #27ae60;
        font-weight: bold;
    }

    @media (max-width: 768px) {
        .delivery-table {
            font-size: 12px;
        }

        .delivery-table th,
        .delivery-table td {
            padding: 8px;
        }

        .address-col {
            max-width: 150px;
        }

        .thumbnail-img {
            width: 50px;
            height: 50px;
        }

        .reviews-summary-stats {
            grid-template-columns: 1fr;
        }

        .stat-box {
            padding: 12px;
        }

        .review-modal-content {
            width: 95%;
        }
    }
</style>

<script>
    function openLightbox(imageSrc) {
        document.getElementById('lightboxImage').src = imageSrc;
        document.getElementById('imageLightbox').classList.add('active');
    }

    function closeLightbox() {
        document.getElementById('imageLightbox').classList.remove('active');
    }

    // Close lightbox when clicking outside image
    document.getElementById('imageLightbox').addEventListener('click', closeLightbox);

    // Close lightbox with Escape key
    document.addEventListener('keydown', function(e) {
        if (e.key === 'Escape') {
            closeLightbox();
            closeReviewModal();
        }
    });

    function showReviewModal(delivery) {
        const modal = document.getElementById('reviewModal');
        const modalBody = document.getElementById('reviewModalBody');
        
        // Generate star rating
        let stars = '';
        for (let i = 1; i <= 5; i++) {
            stars += i <= delivery.rating ? '⭐' : '☆';
        }
        
        // Create modal content
        let content = `
            <div class="review-detail-item">
                <div class="review-detail-label">Order Number</div>
                <div class="review-detail-value">${delivery.order_number}</div>
            </div>
            
            <div class="review-detail-item">
                <div class="review-detail-label">Customer Name</div>
                <div class="review-detail-value">${delivery.customer_name || 'N/A'}</div>
            </div>
            
            <div class="review-detail-item">
                <div class="review-detail-label">Pig Tag</div>
                <div class="review-detail-value">${delivery.pig_tag_id || 'N/A'}</div>
            </div>
            
            <div class="review-detail-item">
                <div class="review-detail-label">Rating</div>
                <div class="review-rating-display">
                    <span>${stars}</span>
                    <span class="review-rating-text">${delivery.rating}/5</span>
                </div>
            </div>
        `;
        
        if (delivery.review_comment) {
            content += `
                <div class="review-detail-item">
                    <div class="review-detail-label">Customer Feedback</div>
                    <div class="review-comment-box">
                        ${delivery.review_comment}
                    </div>
                </div>
            `;
        }
        
        if (delivery.review_date) {
            const reviewDate = new Date(delivery.review_date);
            const formattedDate = reviewDate.toLocaleDateString('en-US', { 
                year: 'numeric', 
                month: 'long', 
                day: 'numeric',
                hour: '2-digit',
                minute: '2-digit'
            });
            
            content += `
                <div class="review-detail-item">
                    <div class="review-detail-label">Review Date</div>
                    <div class="review-detail-value">${formattedDate}</div>
                </div>
            `;
        }
        
        modalBody.innerHTML = content;
        modal.classList.add('active');
    }

    function closeReviewModal() {
        document.getElementById('reviewModal').classList.remove('active');
    }

    // Close review modal when clicking outside
    document.getElementById('reviewModal').addEventListener('click', function(e) {
        if (e.target === this) {
            closeReviewModal();
        }
    });
</script>

<?php
$content = ob_get_clean();
include VIEWS_PATH . '/layouts/dashboard-layout.php';
?>

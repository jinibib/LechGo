<?php
$currentPage = 'browse-lechon';

$user = $_SESSION['user'] ?? null;
if (!$user) {
    header('Location: /login');
    exit;
}

$customer_id = $user['id'];

// Fetch delivered orders from delivery_status table that can be reviewed, 
// fallback to completed orders if no delivery records exist
$query = "SELECT sos.*, 
                 sos.pin_number,
                 COALESCE(otc.pig_tag_id, sos.pig_tag_id) as pig_tag_id,
                 COALESCE(otc.total_cost, sos.total_price) as total_cost,
                 pd.health_status, pd.age_months, pd.photo_url,
                 u.name AS seller_name, lo.farm_name,
                 hm.description as pig_description,
                 r.id as review_id,
                 r.rating,
                 r.comment,
                 r.created_at as review_date,
                 COALESCE(ds.delivered_at, sos.completed_at) as delivered_at,
                 ds.delivery_photo,
                 ds.delivery_address as delivery_final_address,
                 CASE WHEN ds.id IS NOT NULL THEN 'delivered' ELSE 'completed' END as order_type
          FROM swine_order_status sos
          LEFT JOIN delivery_status ds ON ds.order_number = sos.order_number AND ds.delivered_at IS NOT NULL
          LEFT JOIN pig_details pd ON pd.id = sos.pig_detail_id
          LEFT JOIN livestock_owners lo ON lo.id = sos.livestock_owner_id
          LEFT JOIN users u ON u.id = lo.user_id
          LEFT JOIN hogs_market hm ON hm.id = sos.hogs_market_id
          LEFT JOIN order_total_cost otc ON otc.swine_order_id = sos.id
          LEFT JOIN reviews r ON r.order_id = sos.id
          WHERE sos.customer_id = ? 
            AND (ds.delivered_at IS NOT NULL OR sos.order_status = 'completed')
          ORDER BY COALESCE(ds.delivered_at, sos.completed_at) DESC";

$stmt = $GLOBALS['conn']->prepare($query);
if (!$stmt) {
    $orders = [];
} else {
    $stmt->bind_param('i', $customer_id);
    if (!$stmt->execute()) {
        $orders = [];
    } else {
        $result = $stmt->get_result();
        $orders = $result->fetch_all(MYSQLI_ASSOC) ?? [];
        $stmt->close();
    }
}

// Set page title for dashboard layout
$pageTitle = 'Browse Orders for Review';

// Start capturing content
ob_start();
?>
<style>
    .browse-lechon-container {
        padding: 1rem;
        max-width: 1200px;
        margin: 0 auto;
    }

    .page-header {
        margin-bottom: 2rem;
        border-bottom: 2px solid #e74c3c;
        padding-bottom: 1rem;
    }

    .page-header h1 {
        color: #333;
        font-size: 2rem;
        margin: 0 0 0.5rem 0;
    }

    .page-header p {
        color: #666;
        margin: 0;
    }

    .orders-grid {
        display: grid;
        gap: 1.5rem;
        grid-template-columns: 1fr;
    }

    .order-card {
        background: white;
        border: 1px solid #ddd;
        border-radius: 8px;
        padding: 1.5rem;
        box-shadow: 0 2px 4px rgba(0,0,0,0.1);
        transition: transform 0.2s, box-shadow 0.2s;
    }

    .order-card:hover {
        transform: translateY(-2px);
        box-shadow: 0 4px 12px rgba(0,0,0,0.15);
    }

    .order-header {
        display: flex;
        justify-content: space-between;
        align-items: flex-start;
        margin-bottom: 1rem;
    }

    .order-info {
        flex: 1;
    }

    .order-number {
        font-size: 1.1rem;
        font-weight: bold;
        color: #e74c3c;
        margin-bottom: 0.5rem;
    }

    .order-details {
        color: #666;
        margin-bottom: 0.5rem;
    }

    .pig-image {
        width: 100px;
        height: 100px;
        object-fit: cover;
        border-radius: 8px;
        margin-left: 1rem;
    }

    .order-status {
        display: inline-block;
        padding: 0.25rem 0.75rem;
        border-radius: 15px;
        font-size: 0.85rem;
        font-weight: bold;
        background-color: #27ae60;
        color: white;
    }

    .order-status.delivered {
        background-color: #3498db;
        color: white;
    }

    .order-status.completed {
        background-color: #27ae60;
        color: white;
    }

    .review-section {
        margin-top: 1rem;
        padding-top: 1rem;
        border-top: 1px solid #eee;
    }

    .existing-review {
        background: #f8f9fa;
        padding: 1rem;
        border-radius: 8px;
        border-left: 4px solid #28a745;
    }

    .rating-display {
        color: #ffc107;
        font-size: 1.2rem;
        margin-bottom: 0.5rem;
    }

    .review-comment {
        color: #333;
        font-style: italic;
        margin-bottom: 0.5rem;
    }

    .review-date {
        color: #666;
        font-size: 0.85rem;
    }

    .add-review-btn {
        background: #e74c3c;
        color: white;
        border: none;
        padding: 0.75rem 1.5rem;
        border-radius: 5px;
        cursor: pointer;
        font-size: 1rem;
        transition: background-color 0.2s;
    }

    .add-review-btn:hover {
        background: #c0392b;
    }

    .review-form {
        background: #f8f9fa;
        padding: 1rem;
        border-radius: 8px;
        margin-top: 1rem;
        display: none;
    }

    .rating-input {
        margin-bottom: 1rem;
    }

    .rating-input label {
        display: block;
        margin-bottom: 0.5rem;
        font-weight: bold;
        color: #333;
    }

    .rating-help {
        font-size: 0.8rem;
        color: #666;
        margin-bottom: 0.5rem;
        font-style: italic;
    }

    .rating-stars {
        display: flex;
        gap: 0.5rem;
        margin-bottom: 0.5rem;
    }

    .star {
        font-size: 2.5rem;
        color: #ddd;
        cursor: pointer;
        transition: all 0.2s ease;
        user-select: none;
        padding: 0.25rem;
        border-radius: 50%;
        display: flex;
        align-items: center;
        justify-content: center;
        width: 3rem;
        height: 3rem;
        position: relative;
    }

    .star::before {
        content: '';
        position: absolute;
        top: 0;
        left: 0;
        right: 0;
        bottom: 0;
        border-radius: 50%;
        background: rgba(255, 193, 7, 0);
        transition: all 0.2s ease;
    }

    .star:hover::before {
        background: rgba(255, 193, 7, 0.2);
    }

    .star:hover {
        color: #ffc107;
        transform: scale(1.2);
        box-shadow: 0 0 10px rgba(255, 193, 7, 0.5);
    }

    .star.selected {
        color: #ffc107;
        background-color: rgba(255, 193, 7, 0.15);
        transform: scale(1.1);
    }

    .star:active {
        transform: scale(0.95);
    }

    /* Rating animation */
    @keyframes starPulse {
        0% { transform: scale(1); }
        50% { transform: scale(1.2); }
        100% { transform: scale(1); }
    }

    .star.clicked {
        animation: starPulse 0.3s ease;
    }

    .comment-input {
        width: 100%;
        min-height: 100px;
        padding: 0.75rem;
        border: 1px solid #ddd;
        border-radius: 5px;
        resize: vertical;
        font-family: inherit;
    }

    .form-actions {
        display: flex;
        gap: 1rem;
        margin-top: 1rem;
    }

    .btn-submit {
        background: #28a745;
        color: white;
        border: none;
        padding: 0.5rem 1rem;
        border-radius: 5px;
        cursor: pointer;
    }

    .btn-cancel {
        background: #6c757d;
        color: white;
        border: none;
        padding: 0.5rem 1rem;
        border-radius: 5px;
        cursor: pointer;
    }

    .no-orders {
        text-align: center;
        padding: 3rem;
        color: #666;
    }

    .no-orders h3 {
        color: #999;
        margin-bottom: 1rem;
    }

    @media (min-width: 768px) {
        .orders-grid {
            grid-template-columns: repeat(auto-fit, minmax(600px, 1fr));
        }
    }
</style>

<div class="browse-lechon-container">
    <div class="page-header">
        <h1> Browse Orders for Review</h1>
        <p>Rate and review your completed and delivered lechon orders</p>
    </div>

    <?php if (empty($orders)): ?>
        <div class="no-orders">
            <h3>No Orders Available for Review</h3>
            <p>Once your lechon orders are completed or delivered, you'll be able to rate and review them here.</p>
        </div>
    <?php else: ?>
            <div class="orders-grid">
                <?php foreach ($orders as $order): ?>
                    <div class="order-card">
                        <div class="order-header">
                            <div class="order-info">
                                <div class="order-number">#<?php echo htmlspecialchars($order['order_number']); ?></div>
                                <div class="order-details">
                                    <strong>Weight:</strong> <?php echo number_format($order['weight_kg'], 2); ?> kg<br>
                                    <strong>Total Cost:</strong> ₱<?php echo number_format($order['total_cost'], 2); ?><br>
                                    <strong>Seller:</strong> <?php echo htmlspecialchars($order['seller_name']); ?><br>
                                    <strong><?php echo ucfirst($order['order_type']); ?>:</strong> <?php echo date('M d, Y', strtotime($order['delivered_at'])); ?>
                                    <?php if (!empty($order['delivery_final_address'])): ?>
                                        <br><strong>Address:</strong> <?php echo htmlspecialchars($order['delivery_final_address']); ?>
                                    <?php endif; ?>
                                </div>
                                <div class="order-status <?php echo $order['order_type']; ?>"><?php echo ucfirst($order['order_type']); ?></div>
                            </div>
                            <?php if (!empty($order['photo_url'])): ?>
                                <img src="<?php echo htmlspecialchars($order['photo_url']); ?>" alt="Pig Photo" class="pig-image">
                            <?php endif; ?>
                        </div>

                        <div class="review-section">
                            <?php if ($order['review_id']): ?>
                                <!-- Existing Review -->
                                <div class="existing-review">
                                    <h4>Your Review</h4>
                                    <div class="rating-display">
                                        <?php for ($i = 1; $i <= 5; $i++): ?>
                                            <?php echo $i <= $order['rating'] ? '⭐' : '☆'; ?>
                                        <?php endfor; ?>
                                        (<?php echo $order['rating']; ?>/5)
                                    </div>
                                    <?php if ($order['comment']): ?>
                                        <div class="review-comment">"<?php echo htmlspecialchars($order['comment']); ?>"</div>
                                    <?php endif; ?>
                                    <div class="review-date">
                                        Reviewed on <?php echo date('M d, Y', strtotime($order['review_date'])); ?>
                                    </div>
                                </div>
                            <?php else: ?>
                                <!-- Add Review Button -->
                                <button class="add-review-btn" onclick="showReviewForm(<?php echo $order['id']; ?>)">
                                    Add Review & Rating
                                </button>

                                <!-- Review Form -->
                                <div id="review-form-<?php echo $order['id']; ?>" class="review-form">
                                    <h4>Rate Your Experience</h4>
                                    <form onsubmit="submitReview(event, <?php echo $order['id']; ?>)">
                                        <div class="rating-input">
                                            <label>Rating:</label>
                                            <div class="rating-help">Click on the stars to rate your experience (1-5 stars)</div>
                                            <div class="rating-stars" data-order="<?php echo $order['id']; ?>">
                                                <span class="star" data-rating="1">★</span>
                                                <span class="star" data-rating="2">★</span>
                                                <span class="star" data-rating="3">★</span>
                                                <span class="star" data-rating="4">★</span>
                                                <span class="star" data-rating="5">★</span>
                                            </div>
                                            <input type="hidden" name="rating" id="rating-<?php echo $order['id']; ?>" required>
                                        </div>
                                        <div class="comment-input-container">
                                            <label>Comment (Optional):</label>
                                            <textarea name="comment" class="comment-input" placeholder="Share your experience with this lechon order..."></textarea>
                                        </div>
                                        <div class="form-actions">
                                            <button type="submit" class="btn-submit">Submit Review</button>
                                            <button type="button" class="btn-cancel" onclick="hideReviewForm(<?php echo $order['id']; ?>)">Cancel</button>
                                        </div>
                                    </form>
                                </div>
                            <?php endif; ?>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
    <?php endif; ?>
</div>

<script>
    let selectedRatings = {};

    function showReviewForm(orderId) {
        const form = document.getElementById(`review-form-${orderId}`);
        const button = form.previousElementSibling;
        
        form.style.display = 'block';
        button.style.display = 'none';
    }

    function hideReviewForm(orderId) {
        const form = document.getElementById(`review-form-${orderId}`);
        const button = form.previousElementSibling;
        
        form.style.display = 'none';
        button.style.display = 'block';
        
        // Reset form
        selectedRatings[orderId] = 0;
        updateStars(orderId);
        form.querySelector('textarea').value = '';
    }

    // Handle star rating
    document.addEventListener('DOMContentLoaded', function() {
        const starContainers = document.querySelectorAll('.rating-stars');
        
        starContainers.forEach(container => {
            const orderId = container.dataset.order;
            const stars = container.querySelectorAll('.star');
            
            stars.forEach((star, index) => {
                star.addEventListener('click', function() {
                    const rating = parseInt(this.dataset.rating);
                    selectedRatings[orderId] = rating;
                    document.getElementById(`rating-${orderId}`).value = rating;
                    
                    // Add click animation
                    this.classList.add('clicked');
                    setTimeout(() => {
                        this.classList.remove('clicked');
                    }, 300);
                    
                    updateStars(orderId);
                });
                
                star.addEventListener('mouseover', function() {
                    const rating = parseInt(this.dataset.rating);
                    highlightStars(orderId, rating);
                });
            });
            
            container.addEventListener('mouseleave', function() {
                updateStars(orderId);
            });
        });
    });

    function highlightStars(orderId, rating) {
        const container = document.querySelector(`.rating-stars[data-order="${orderId}"]`);
        const stars = container.querySelectorAll('.star');
        
        stars.forEach((star, index) => {
            if (index < rating) {
                star.classList.add('selected');
            } else {
                star.classList.remove('selected');
            }
        });
    }

    function updateStars(orderId) {
        const rating = selectedRatings[orderId] || 0;
        highlightStars(orderId, rating);
    }

    function submitReview(event, orderId) {
        event.preventDefault();
        
        const form = event.target;
        const rating = form.rating.value;
        const comment = form.comment.value;
        
        if (!rating || rating < 1 || rating > 5) {
            alert('Please select a rating between 1 and 5 stars.');
            return;
        }

        // Show loading state
        const submitBtn = form.querySelector('.btn-submit');
        const originalText = submitBtn.textContent;
        submitBtn.textContent = 'Submitting...';
        submitBtn.disabled = true;

        // Submit review
        const formData = new FormData();
        formData.append('order_id', orderId);
        formData.append('rating', rating);
        formData.append('comment', comment);

        console.log('Submitting review:', {
            order_id: orderId,
            rating: rating,
            comment: comment
        });

        fetch('/submit-review', {
            method: 'POST',
            body: formData,
            credentials: 'same-origin' // Include cookies/session
        })
        .then(response => {
            console.log('Response status:', response.status);
            console.log('Response ok:', response.ok);
            
            // Check if response is OK
            if (!response.ok) {
                throw new Error('Network response was not ok: ' + response.status);
            }
            
            return response.text(); // Get as text first
        })
        .then(text => {
            console.log('Raw response:', text);
            
            // Try to parse as JSON
            let data;
            try {
                data = JSON.parse(text);
            } catch (e) {
                console.error('JSON parse error:', e);
                throw new Error('Invalid JSON response: ' + text.substring(0, 100));
            }
            
            console.log('Parsed response:', data);
            
            if (data.success) {
                alert('Review submitted successfully!');
                location.reload(); // Refresh to show the new review
            } else {
                alert('Error submitting review: ' + (data.message || 'Unknown error'));
            }
        })
        .catch(error => {
            console.error('Error details:', error);
            alert('Error submitting review: ' + error.message + '\n\nPlease check the browser console for more details.');
        })
        .finally(() => {
            submitBtn.textContent = originalText;
            submitBtn.disabled = false;
        });
    }
</script>

<?php
// Capture content for dashboard layout
$content = ob_get_clean();

// Include dashboard layout
include VIEWS_PATH . '/layouts/dashboard-layout.php';
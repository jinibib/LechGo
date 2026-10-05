<?php
$currentPage = 'reviews';

// Set page title for dashboard layout
$pageTitle = 'My Reviews';

// Start capturing content
ob_start();
?>

<style>
    .reviews-container {
        padding: 1rem;
        max-width: 1000px;
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

    .review-item {
        background: white;
        border: 1px solid #ddd;
        border-radius: 8px;
        padding: 1.5rem;
        margin-bottom: 1rem;
        box-shadow: 0 2px 4px rgba(0,0,0,0.1);
    }

    .review-header {
        display: flex;
        justify-content: space-between;
        align-items: start;
        margin-bottom: 1rem;
    }

    .order-info {
        flex: 1;
    }

    .order-number {
        font-weight: bold;
        color: #e74c3c;
        margin-bottom: 0.5rem;
    }

    .seller-name {
        color: #666;
        font-size: 0.9rem;
    }

    .rating-display {
        color: #ffc107;
        font-size: 1.2rem;
        margin-bottom: 0.5rem;
    }

    .review-comment {
        background: #f8f9fa;
        padding: 1rem;
        border-radius: 5px;
        border-left: 4px solid #e74c3c;
        margin: 1rem 0;
        font-style: italic;
    }

    .review-date {
        color: #666;
        font-size: 0.85rem;
    }

    .no-reviews {
        text-align: center;
        padding: 3rem;
        color: #666;
    }

    .no-reviews h3 {
        color: #999;
        margin-bottom: 1rem;
    }

    .browse-link {
        color: #e74c3c;
        text-decoration: none;
        font-weight: bold;
    }

    .browse-link:hover {
        text-decoration: underline;
    }
</style>

<div class="reviews-container">
    <div class="page-header">
        <h1>⭐ My Reviews</h1>
        <p>All the reviews you've submitted for your lechon orders</p>
    </div>

        <?php if (empty($reviews)): ?>
            <div class="no-reviews">
                <h3>No Reviews Yet</h3>
                <p>You haven't submitted any reviews yet.</p>
                <p><a href="/customer/browse-lechon" class="browse-link">Browse your completed orders to add reviews</a></p>
            </div>
        <?php else: ?>
            <div class="reviews-list">
                <?php foreach ($reviews as $review): ?>
                    <div class="review-item">
                        <div class="review-header">
                            <div class="order-info">
                                <div class="order-number">Order #<?php echo htmlspecialchars($review['order_number']); ?></div>
                                <div class="seller-name">Seller: <?php echo htmlspecialchars($review['seller_name']); ?></div>
                                <?php if ($review['farm_name']): ?>
                                    <div class="seller-name">Farm: <?php echo htmlspecialchars($review['farm_name']); ?></div>
                                <?php endif; ?>
                            </div>
                            <div class="rating-display">
                                <?php for ($i = 1; $i <= 5; $i++): ?>
                                    <?php echo $i <= $review['rating'] ? '⭐' : '☆'; ?>
                                <?php endfor; ?>
                                (<?php echo $review['rating']; ?>/5)
                            </div>
                        </div>

                        <?php if ($review['comment']): ?>
                            <div class="review-comment">
                                "<?php echo htmlspecialchars($review['comment']); ?>"
                            </div>
                        <?php endif; ?>

                        <div class="review-date">
                            Reviewed on <?php echo date('M d, Y g:i A', strtotime($review['created_at'])); ?>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
    <?php endif; ?>
</div>

<?php
// Capture content for dashboard layout
$content = ob_get_clean();

// Include dashboard layout
include VIEWS_PATH . '/layouts/dashboard-layout.php';
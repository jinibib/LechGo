<?php
$currentPage = 'reviews';

// Set page title for dashboard layout
$pageTitle = 'Customer Reviews & Ratings';

// Start capturing content
ob_start();
?>
<style>
    .reviews-container {
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

    .rating-summary {
        background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
        color: white;
        border-radius: 12px;
        padding: 2rem;
        margin-bottom: 2rem;
        box-shadow: 0 4px 12px rgba(0,0,0,0.15);
        text-align: center;
    }

    .overall-rating {
        font-size: 3.5rem;
        color: #ffc107;
        margin-bottom: 0.5rem;
        text-shadow: 2px 2px 4px rgba(0,0,0,0.2);
    }

    .rating-text {
        font-size: 1.5rem;
        font-weight: bold;
        margin-bottom: 0.5rem;
    }

    .review-count {
        font-size: 0.95rem;
        opacity: 0.9;
    }

    .stats-grid {
        display: grid;
        grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
        gap: 1rem;
        margin-bottom: 2rem;
    }

    .stat-card {
        background: white;
        border: 1px solid #ddd;
        border-radius: 8px;
        padding: 1.5rem;
        box-shadow: 0 2px 4px rgba(0,0,0,0.1);
        text-align: center;
    }

    .stat-icon {
        font-size: 2rem;
        margin-bottom: 0.5rem;
    }

    .stat-value {
        font-size: 1.8rem;
        font-weight: bold;
        color: #333;
        margin-bottom: 0.25rem;
    }

    .stat-label {
        color: #666;
        font-size: 0.9rem;
    }

    .review-item {
        background: white;
        border: 1px solid #ddd;
        border-radius: 8px;
        padding: 1.5rem;
        margin-bottom: 1rem;
        box-shadow: 0 2px 4px rgba(0,0,0,0.1);
        transition: transform 0.2s, box-shadow 0.2s;
    }

    .review-item:hover {
        transform: translateY(-2px);
        box-shadow: 0 4px 12px rgba(0,0,0,0.15);
    }

    .review-header {
        display: flex;
        justify-content: space-between;
        align-items: start;
        margin-bottom: 1rem;
        padding-bottom: 1rem;
        border-bottom: 1px solid #eee;
    }

    .customer-info {
        flex: 1;
    }

    .customer-name {
        font-weight: bold;
        color: #333;
        font-size: 1.1rem;
        margin-bottom: 0.25rem;
    }

    .order-number {
        color: #666;
        font-size: 0.9rem;
        margin-bottom: 0.25rem;
    }

    .pig-tag {
        color: #e74c3c;
        font-weight: bold;
        font-size: 0.9rem;
    }

    .rating-display {
        background: #fff9e6;
        color: #ffc107;
        font-size: 1.2rem;
        padding: 0.5rem 1rem;
        border-radius: 20px;
        white-space: nowrap;
    }

    .rating-number {
        color: #333;
        font-weight: bold;
        font-size: 0.9rem;
        margin-left: 0.25rem;
    }

    .review-comment {
        background: #f8f9fa;
        padding: 1rem;
        border-radius: 8px;
        border-left: 4px solid #28a745;
        margin: 1rem 0;
        font-style: italic;
        color: #333;
        line-height: 1.6;
    }

    .review-comment:before {
        content: '"';
        font-size: 2rem;
        color: #28a745;
        font-weight: bold;
    }

    .review-comment:after {
        content: '"';
        font-size: 2rem;
        color: #28a745;
        font-weight: bold;
    }

    .review-footer {
        display: flex;
        justify-content: space-between;
        align-items: center;
        margin-top: 1rem;
        padding-top: 1rem;
        border-top: 1px solid #eee;
    }

    .review-date {
        color: #666;
        font-size: 0.85rem;
    }

    .review-date strong {
        color: #333;
    }

    .no-reviews {
        text-align: center;
        padding: 4rem 2rem;
        background: white;
        border-radius: 12px;
        box-shadow: 0 2px 4px rgba(0,0,0,0.1);
    }

    .no-reviews-icon {
        font-size: 4rem;
        margin-bottom: 1rem;
        opacity: 0.5;
    }

    .no-reviews h3 {
        color: #999;
        margin-bottom: 1rem;
        font-size: 1.5rem;
    }

    .no-reviews p {
        color: #666;
        margin: 0.5rem 0;
        line-height: 1.6;
    }

    @media (max-width: 768px) {
        .review-header {
            flex-direction: column;
            gap: 1rem;
        }

        .rating-display {
            align-self: flex-start;
        }

        .stats-grid {
            grid-template-columns: 1fr;
        }
    }
</style>

<div class="reviews-container">
    <div class="page-header">
        <h1>⭐ Customer Reviews & Ratings</h1>
        <p>See what customers are saying about your pigs</p>
    </div>

    <?php if ($rating_stats['total_reviews'] > 0): ?>
        <div class="rating-summary">
            <div class="overall-rating">
                <?php
                $avg_rating = $rating_stats['average_rating'];
                for ($i = 1; $i <= 5; $i++) {
                    echo $i <= round($avg_rating) ? '⭐' : '☆';
                }
                ?>
            </div>
            <div class="rating-text"><?php echo number_format($avg_rating, 1); ?> out of 5</div>
            <div class="review-count">Based on <?php echo $rating_stats['total_reviews']; ?> customer review<?php echo $rating_stats['total_reviews'] != 1 ? 's' : ''; ?></div>
        </div>

        <div class="stats-grid">
            <div class="stat-card">
                <div class="stat-icon">📊</div>
                <div class="stat-value"><?php echo $rating_stats['total_reviews']; ?></div>
                <div class="stat-label">Total Reviews</div>
            </div>
            <div class="stat-card">
                <div class="stat-icon">⭐</div>
                <div class="stat-value"><?php echo number_format($avg_rating, 1); ?></div>
                <div class="stat-label">Average Rating</div>
            </div>
            <div class="stat-card">
                <div class="stat-icon">
                    <?php
                    if ($avg_rating >= 4.5) {
                        echo '😄';
                    } elseif ($avg_rating >= 3.5) {
                        echo '😊';
                    } elseif ($avg_rating >= 2.5) {
                        echo '😐';
                    } else {
                        echo '😟';
                    }
                    ?>
                </div>
                <div class="stat-value">
                    <?php
                    if ($avg_rating >= 4.5) {
                        echo 'Excellent';
                    } elseif ($avg_rating >= 3.5) {
                        echo 'Good';
                    } elseif ($avg_rating >= 2.5) {
                        echo 'Average';
                    } else {
                        echo 'Needs Improvement';
                    }
                    ?>
                </div>
                <div class="stat-label">Customer Satisfaction</div>
            </div>
        </div>
    <?php endif; ?>

    <?php if (empty($reviews)): ?>
        <div class="no-reviews">
            <div class="no-reviews-icon">📝</div>
            <h3>No Reviews Yet</h3>
            <p>You haven't received any customer reviews yet.</p>
            <p>Once customers complete their orders and leave feedback, their reviews will appear here.</p>
            <p>Keep providing quality pigs to earn great reviews! 🐷⭐</p>
        </div>
    <?php else: ?>
        <div class="reviews-list">
            <?php foreach ($reviews as $review): ?>
                <div class="review-item">
                    <div class="review-header">
                        <div class="customer-info">
                            <div class="customer-name">👤 <?php echo htmlspecialchars($review['customer_name']); ?></div>
                            <div class="order-number">Order: #<?php echo htmlspecialchars($review['order_number']); ?></div>
                            <div class="pig-tag">🐷 Pig Tag: <?php echo htmlspecialchars($review['pig_tag_id']); ?></div>
                        </div>
                        <div class="rating-display">
                            <?php for ($i = 1; $i <= 5; $i++): ?>
                                <?php echo $i <= $review['rating'] ? '⭐' : '☆'; ?>
                            <?php endfor; ?>
                            <span class="rating-number">(<?php echo $review['rating']; ?>/5)</span>
                        </div>
                    </div>

                    <?php if ($review['comment']): ?>
                        <div class="review-comment">
                            <?php echo htmlspecialchars($review['comment']); ?>
                        </div>
                    <?php else: ?>
                        <div style="color: #999; font-style: italic; padding: 1rem 0;">
                            No comment provided
                        </div>
                    <?php endif; ?>

                    <div class="review-footer">
                        <div class="review-date">
                            <strong>Reviewed on:</strong> <?php echo date('F d, Y', strtotime($review['created_at'])); ?> at <?php echo date('g:i A', strtotime($review['created_at'])); ?>
                        </div>
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
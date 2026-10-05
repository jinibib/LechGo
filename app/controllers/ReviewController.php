<?php

require_once APP_PATH . '/models/Review.php';

class ReviewController {
    private $conn;
    private $reviewModel;

    public function __construct($conn) {
        $this->conn = $conn;
        $this->reviewModel = new Review($conn);
    }

    /**
     * Handle review submission via AJAX
     */
    public function submitReview() {
        header('Content-Type: application/json');

        try {
            // Check if user is logged in
            if (!isset($_SESSION['user'])) {
                echo json_encode(['success' => false, 'message' => 'User not logged in']);
                return;
            }

            $user = $_SESSION['user'];

            // Validate request method
            if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
                echo json_encode(['success' => false, 'message' => 'Invalid request method']);
                return;
            }

            // Get and validate input data
            $order_id = $_POST['order_id'] ?? null;
            $rating = $_POST['rating'] ?? null;
            $comment = trim($_POST['comment'] ?? '');

            if (!$order_id || !$rating) {
                echo json_encode(['success' => false, 'message' => 'Order ID and rating are required']);
                return;
            }

            // Convert rating to integer and validate
            $rating = intval($rating);
            if ($rating < 1 || $rating > 5) {
                echo json_encode(['success' => false, 'message' => 'Rating must be between 1 and 5']);
                return;
            }

            // Verify that the order belongs to the logged-in customer
            $stmt = $this->conn->prepare(
                "SELECT sos.id, sos.customer_id, sos.order_status, sos.completed_at, ds.delivered_at
                 FROM swine_order_status sos 
                 LEFT JOIN delivery_status ds ON ds.order_number = sos.order_number 
                 WHERE sos.id = ?"
            );
            $stmt->bind_param('i', $order_id);
            $stmt->execute();
            $order = $stmt->get_result()->fetch_assoc();
            $stmt->close();

            if (!$order) {
                echo json_encode(['success' => false, 'message' => 'Order not found']);
                return;
            }

            if ($order['customer_id'] != $user['id']) {
                echo json_encode(['success' => false, 'message' => 'Unauthorized: Order does not belong to you']);
                return;
            }

            if (!$order['delivered_at'] && $order['order_status'] !== 'completed') {
                echo json_encode(['success' => false, 'message' => 'Can only review completed or delivered orders']);
                return;
            }

            // Create the review
            $result = $this->reviewModel->createReview($order_id, $rating, $comment);
            
            echo json_encode($result);

        } catch (Exception $e) {
            error_log("Review submission error: " . $e->getMessage());
            echo json_encode(['success' => false, 'message' => 'Server error: ' . $e->getMessage()]);
        }
    }

    /**
     * Display customer reviews page
     */
    public function customerReviews() {
        $user = $_SESSION['user'] ?? null;
        if (!$user) {
            header('Location: /login');
            exit;
        }

        $customer_id = $user['id'];
        $reviews = $this->reviewModel->getReviewsForCustomer($customer_id);

        // Include the view
        include VIEWS_PATH . '/customer/reviews.php';
    }

    /**
     * Display seller reviews page (for livestock owners/lechoneros)
     */
    public function sellerReviews() {
        $user = $_SESSION['user'] ?? null;
        if (!$user || ($user['role'] !== 'livestock_owner' && $user['role'] !== 'lechonero')) {
            header('Location: /login');
            exit;
        }

        // Get livestock owner ID for this user
        $livestock_owner_id = null;
        if ($user['role'] === 'livestock_owner') {
            $stmt = $this->conn->prepare("SELECT id FROM livestock_owners WHERE user_id = ?");
            $stmt->bind_param('i', $user['id']);
            $stmt->execute();
            $result = $stmt->get_result();
            $owner = $result->fetch_assoc();
            $stmt->close();
            
            if ($owner) {
                $livestock_owner_id = $owner['id'];
            }
        }

        if (!$livestock_owner_id) {
            $reviews = [];
            $rating_stats = ['average_rating' => 0, 'total_reviews' => 0];
        } else {
            $reviews = $this->reviewModel->getReviewsForSeller($livestock_owner_id);
            $rating_stats = $this->reviewModel->getSellerAverageRating($livestock_owner_id);
        }

        // Include the appropriate view based on role
        if ($user['role'] === 'livestock_owner') {
            include VIEWS_PATH . '/livestock-owner/reviews.php';
        } else {
            include VIEWS_PATH . '/lechonero/reviews.php';
        }
    }

    /**
     * Get review data for API
     */
    public function getReview() {
        header('Content-Type: application/json');

        if (!isset($_SESSION['user'])) {
            echo json_encode(['success' => false, 'message' => 'User not logged in']);
            return;
        }

        $order_id = $_GET['order_id'] ?? null;
        if (!$order_id) {
            echo json_encode(['success' => false, 'message' => 'Order ID required']);
            return;
        }

        $review = $this->reviewModel->getReviewByOrderId($order_id);
        
        if ($review) {
            echo json_encode(['success' => true, 'review' => $review]);
        } else {
            echo json_encode(['success' => false, 'message' => 'Review not found']);
        }
    }

    /**
     * Update an existing review
     */
    public function updateReview() {
        header('Content-Type: application/json');

        if (!isset($_SESSION['user'])) {
            echo json_encode(['success' => false, 'message' => 'User not logged in']);
            return;
        }

        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            echo json_encode(['success' => false, 'message' => 'Invalid request method']);
            return;
        }

        $review_id = $_POST['review_id'] ?? null;
        $rating = $_POST['rating'] ?? null;
        $comment = trim($_POST['comment'] ?? '');

        if (!$review_id || !$rating) {
            echo json_encode(['success' => false, 'message' => 'Review ID and rating are required']);
            return;
        }

        $rating = intval($rating);
        if ($rating < 1 || $rating > 5) {
            echo json_encode(['success' => false, 'message' => 'Rating must be between 1 and 5']);
            return;
        }

        // Verify ownership of the review
        $user = $_SESSION['user'];
        $stmt = $this->conn->prepare(
            "SELECT r.id FROM reviews r 
             INNER JOIN swine_order_status sos ON sos.id = r.order_id 
             WHERE r.id = ? AND sos.customer_id = ?"
        );
        $stmt->bind_param('ii', $review_id, $user['id']);
        $stmt->execute();
        $exists = $stmt->get_result()->num_rows > 0;
        $stmt->close();

        if (!$exists) {
            echo json_encode(['success' => false, 'message' => 'Review not found or unauthorized']);
            return;
        }

        $result = $this->reviewModel->updateReview($review_id, $rating, $comment);
        echo json_encode($result);
    }

    /**
     * Delete a review
     */
    public function deleteReview() {
        header('Content-Type: application/json');

        if (!isset($_SESSION['user'])) {
            echo json_encode(['success' => false, 'message' => 'User not logged in']);
            return;
        }

        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            echo json_encode(['success' => false, 'message' => 'Invalid request method']);
            return;
        }

        $review_id = $_POST['review_id'] ?? null;
        if (!$review_id) {
            echo json_encode(['success' => false, 'message' => 'Review ID required']);
            return;
        }

        // Verify ownership of the review
        $user = $_SESSION['user'];
        $stmt = $this->conn->prepare(
            "SELECT r.id FROM reviews r 
             INNER JOIN swine_order_status sos ON sos.id = r.order_id 
             WHERE r.id = ? AND sos.customer_id = ?"
        );
        $stmt->bind_param('ii', $review_id, $user['id']);
        $stmt->execute();
        $exists = $stmt->get_result()->num_rows > 0;
        $stmt->close();

        if (!$exists) {
            echo json_encode(['success' => false, 'message' => 'Review not found or unauthorized']);
            return;
        }

        $result = $this->reviewModel->deleteReview($review_id);
        echo json_encode($result);
    }
}
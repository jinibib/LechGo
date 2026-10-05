<?php

class Review {
    private $conn;

    public function __construct($conn) {
        $this->conn = $conn;
    }

    /**
     * Create a new review for an order
     */
    public function createReview($order_id, $rating, $comment = null) {
        // First check if review already exists for this order
        if ($this->reviewExists($order_id)) {
            return ['success' => false, 'message' => 'Review already exists for this order'];
        }

        // Validate rating
        if ($rating < 1 || $rating > 5) {
            return ['success' => false, 'message' => 'Rating must be between 1 and 5'];
        }

        // Verify order exists and is completed or delivered
        $orderCheck = $this->conn->prepare("
            SELECT sos.id, sos.order_status, sos.customer_id, sos.completed_at, ds.delivered_at 
            FROM swine_order_status sos 
            LEFT JOIN delivery_status ds ON ds.order_number = sos.order_number 
            WHERE sos.id = ? 
              AND (ds.delivered_at IS NOT NULL OR sos.order_status = 'completed')
        ");
        $orderCheck->bind_param('i', $order_id);
        $orderCheck->execute();
        $order = $orderCheck->get_result()->fetch_assoc();
        $orderCheck->close();

        if (!$order) {
            return ['success' => false, 'message' => 'Order not found or not completed/delivered yet'];
        }

        // Insert review
        $stmt = $this->conn->prepare(
            "INSERT INTO reviews (order_id, rating, comment, created_at) VALUES (?, ?, ?, NOW())"
        );

        if (!$stmt) {
            return ['success' => false, 'message' => 'Database error: ' . $this->conn->error];
        }

        $stmt->bind_param('iis', $order_id, $rating, $comment);
        
        if ($stmt->execute()) {
            $review_id = $this->conn->insert_id;
            $stmt->close();
            
            return [
                'success' => true, 
                'message' => 'Review submitted successfully',
                'review_id' => $review_id
            ];
        } else {
            $error = $stmt->error;
            $stmt->close();
            return ['success' => false, 'message' => 'Failed to submit review: ' . $error];
        }
    }

    /**
     * Check if a review already exists for an order
     */
    public function reviewExists($order_id) {
        $stmt = $this->conn->prepare("SELECT id FROM reviews WHERE order_id = ?");
        $stmt->bind_param('i', $order_id);
        $stmt->execute();
        $result = $stmt->get_result();
        $exists = $result->num_rows > 0;
        $stmt->close();
        
        return $exists;
    }

    /**
     * Get review for a specific order
     */
    public function getReviewByOrderId($order_id) {
        $stmt = $this->conn->prepare(
            "SELECT * FROM reviews WHERE order_id = ?"
        );
        $stmt->bind_param('i', $order_id);
        $stmt->execute();
        $result = $stmt->get_result();
        $review = $result->fetch_assoc();
        $stmt->close();
        
        return $review;
    }

    /**
     * Get all reviews for a seller (livestock owner)
     */
    public function getReviewsForSeller($livestock_owner_id, $limit = null) {
        $query = "SELECT r.*, sos.order_number, sos.pig_tag_id, sos.completed_at,
                         c.name as customer_name
                  FROM reviews r
                  INNER JOIN swine_order_status sos ON sos.id = r.order_id
                  INNER JOIN users c ON c.id = sos.customer_id
                  WHERE sos.livestock_owner_id = ?
                  ORDER BY r.created_at DESC";

        if ($limit) {
            $query .= " LIMIT " . intval($limit);
        }

        $stmt = $this->conn->prepare($query);
        $stmt->bind_param('i', $livestock_owner_id);
        $stmt->execute();
        $result = $stmt->get_result();
        $reviews = $result->fetch_all(MYSQLI_ASSOC);
        $stmt->close();
        
        return $reviews;
    }

    /**
     * Get average rating for a seller
     */
    public function getSellerAverageRating($livestock_owner_id) {
        $stmt = $this->conn->prepare(
            "SELECT AVG(r.rating) as average_rating, COUNT(r.id) as total_reviews
             FROM reviews r
             INNER JOIN swine_order_status sos ON sos.id = r.order_id
             WHERE sos.livestock_owner_id = ?"
        );
        $stmt->bind_param('i', $livestock_owner_id);
        $stmt->execute();
        $result = $stmt->get_result();
        $data = $result->fetch_assoc();
        $stmt->close();
        
        return [
            'average_rating' => round($data['average_rating'], 1),
            'total_reviews' => $data['total_reviews']
        ];
    }

    /**
     * Get all reviews for a customer
     */
    public function getReviewsForCustomer($customer_id) {
        $stmt = $this->conn->prepare(
            "SELECT r.*, sos.order_number, sos.pig_tag_id, sos.completed_at,
                     u.name as seller_name, lo.farm_name
             FROM reviews r
             INNER JOIN swine_order_status sos ON sos.id = r.order_id
             INNER JOIN livestock_owners lo ON lo.id = sos.livestock_owner_id
             INNER JOIN users u ON u.id = lo.user_id
             WHERE sos.customer_id = ?
             ORDER BY r.created_at DESC"
        );
        $stmt->bind_param('i', $customer_id);
        $stmt->execute();
        $result = $stmt->get_result();
        $reviews = $result->fetch_all(MYSQLI_ASSOC);
        $stmt->close();
        
        return $reviews;
    }

    /**
     * Update an existing review
     */
    public function updateReview($review_id, $rating, $comment = null) {
        // Validate rating
        if ($rating < 1 || $rating > 5) {
            return ['success' => false, 'message' => 'Rating must be between 1 and 5'];
        }

        $stmt = $this->conn->prepare(
            "UPDATE reviews SET rating = ?, comment = ? WHERE id = ?"
        );

        if (!$stmt) {
            return ['success' => false, 'message' => 'Database error: ' . $this->conn->error];
        }

        $stmt->bind_param('isi', $rating, $comment, $review_id);
        
        if ($stmt->execute()) {
            $stmt->close();
            return ['success' => true, 'message' => 'Review updated successfully'];
        } else {
            $error = $stmt->error;
            $stmt->close();
            return ['success' => false, 'message' => 'Failed to update review: ' . $error];
        }
    }

    /**
     * Delete a review
     */
    public function deleteReview($review_id) {
        $stmt = $this->conn->prepare("DELETE FROM reviews WHERE id = ?");
        
        if (!$stmt) {
            return ['success' => false, 'message' => 'Database error: ' . $this->conn->error];
        }

        $stmt->bind_param('i', $review_id);
        
        if ($stmt->execute()) {
            $stmt->close();
            return ['success' => true, 'message' => 'Review deleted successfully'];
        } else {
            $error = $stmt->error;
            $stmt->close();
            return ['success' => false, 'message' => 'Failed to delete review: ' . $error];
        }
    }
}
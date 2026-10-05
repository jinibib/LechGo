<?php

/**
 * MarketTrend Model
 * Handles market trends data and business logic
 */

class MarketTrend {
    private $conn;
    public $id;
    public $title;
    public $description;
    public $summary;
    public $category;
    public $trend_type;
    public $impact_level;
    public $business_insight;
    public $suggested_action;
    public $source;
    public $source_url;
    public $image_url;
    public $published_at;
    public $is_demo;
    public $created_at;
    public $updated_at;

    public function __construct($conn) {
        $this->conn = $conn;
    }

    /**
     * Get all trends with pagination
     */
    public function getAllTrends($limit = 20, $offset = 0) {
        $query = "SELECT * FROM market_trends ORDER BY published_at DESC LIMIT ? OFFSET ?";
        $stmt = $this->conn->prepare($query);
        if (!$stmt) {
            return false;
        }
        $stmt->bind_param('ii', $limit, $offset);
        $stmt->execute();
        return $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
    }

    /**
     * Get trends by category
     */
    public function getTrendsByCategory($category, $limit = 20, $offset = 0) {
        $query = "SELECT * FROM market_trends WHERE category = ? ORDER BY published_at DESC LIMIT ? OFFSET ?";
        $stmt = $this->conn->prepare($query);
        if (!$stmt) {
            return false;
        }
        $stmt->bind_param('sii', $category, $limit, $offset);
        $stmt->execute();
        return $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
    }

    /**
     * Search trends by title or description
     */
    public function searchTrends($query_text, $limit = 20, $offset = 0) {
        $search_term = '%' . $query_text . '%';
        $query = "SELECT * FROM market_trends 
                  WHERE title LIKE ? OR description LIKE ? OR summary LIKE ? 
                  ORDER BY published_at DESC 
                  LIMIT ? OFFSET ?";
        $stmt = $this->conn->prepare($query);
        if (!$stmt) {
            return false;
        }
        $stmt->bind_param('sssii', $search_term, $search_term, $search_term, $limit, $offset);
        $stmt->execute();
        return $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
    }

    /**
     * Get single trend by ID
     */
    public function getTrendById($trend_id) {
        $query = "SELECT * FROM market_trends WHERE id = ?";
        $stmt = $this->conn->prepare($query);
        if (!$stmt) {
            return false;
        }
        $stmt->bind_param('i', $trend_id);
        $stmt->execute();
        $result = $stmt->get_result();
        return $result->fetch_assoc();
    }

    /**
     * Get total trends count
     */
    public function getTrendsCount() {
        $query = "SELECT COUNT(*) as count FROM market_trends";
        $stmt = $this->conn->prepare($query);
        if (!$stmt) {
            return 0;
        }
        $stmt->execute();
        $result = $stmt->get_result();
        $row = $result->fetch_assoc();
        return $row['count'] ?? 0;
    }

    /**
     * Get trends count by category
     */
    public function getTrendsCountByCategory($category) {
        $query = "SELECT COUNT(*) as count FROM market_trends WHERE category = ?";
        $stmt = $this->conn->prepare($query);
        if (!$stmt) {
            return 0;
        }
        $stmt->bind_param('s', $category);
        $stmt->execute();
        $result = $stmt->get_result();
        $row = $result->fetch_assoc();
        return $row['count'] ?? 0;
    }

    /**
     * Create new trend
     */
    public function createTrend($title, $description, $summary, $category, $trend_type, $impact_level, $business_insight, $suggested_action, $source, $source_url, $image_url, $published_at, $is_demo = false) {
        $query = "INSERT INTO market_trends 
                  (title, description, summary, category, trend_type, impact_level, business_insight, suggested_action, source, source_url, image_url, published_at, is_demo) 
                  VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)";
        $stmt = $this->conn->prepare($query);
        if (!$stmt) {
            return false;
        }
        $stmt->bind_param('ssssssssssssi', $title, $description, $summary, $category, $trend_type, $impact_level, $business_insight, $suggested_action, $source, $source_url, $image_url, $published_at, $is_demo);
        if ($stmt->execute()) {
            return $this->conn->insert_id;
        }
        return false;
    }

    /**
     * Get all available categories
     */
    public function getAllCategories() {
        return [
            'Pig Farming',
            'Pork Market',
            'Lechon',
            'Pricing',
            'Agriculture',
            'Food Trends',
            'Industry News'
        ];
    }

    /**
     * Get trends by date range with pagination
     */
    public function getTrendsByDateRange($startDate, $endDate, $limit = 20, $offset = 0) {
        $query = "SELECT * FROM market_trends 
                  WHERE DATE(published_at) BETWEEN ? AND ?
                  ORDER BY published_at DESC 
                  LIMIT ? OFFSET ?";
        $stmt = $this->conn->prepare($query);
        if (!$stmt) {
            return false;
        }
        $stmt->bind_param('ssii', $startDate, $endDate, $limit, $offset);
        $stmt->execute();
        return $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
    }

    /**
     * Get trend count by date range
     */
    public function getTrendCountByDateRange($startDate, $endDate) {
        $query = "SELECT COUNT(*) as count FROM market_trends 
                  WHERE DATE(published_at) BETWEEN ? AND ? AND is_demo = FALSE";
        $stmt = $this->conn->prepare($query);
        if (!$stmt) {
            return 0;
        }
        $stmt->bind_param('ss', $startDate, $endDate);
        $stmt->execute();
        $result = $stmt->get_result();
        $row = $result->fetch_assoc();
        return $row['count'] ?? 0;
    }

    /**
     * Get all impact levels
     */
    public function getAllImpactLevels() {
        return ['High', 'Moderate', 'Low'];
    }
}
?>

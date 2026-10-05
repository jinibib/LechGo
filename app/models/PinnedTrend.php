<?php

/**
 * PinnedTrend Model
 * Handles pinned/saved trends for users
 */

class PinnedTrend {
    private $conn;
    public $id;
    public $user_id;
    public $trend_id;
    public $pinned_at;

    public function __construct($conn) {
        $this->conn = $conn;
    }

    /**
     * Pin a trend for a user
     */
    public function pinTrend($user_id, $trend_id) {
        $query = "INSERT IGNORE INTO pinned_trends (user_id, trend_id) VALUES (?, ?)";
        $stmt = $this->conn->prepare($query);
        if (!$stmt) {
            return false;
        }
        $stmt->bind_param('ii', $user_id, $trend_id);
        return $stmt->execute();
    }

    /**
     * Unpin a trend for a user
     */
    public function unpinTrend($user_id, $trend_id) {
        $query = "DELETE FROM pinned_trends WHERE user_id = ? AND trend_id = ?";
        $stmt = $this->conn->prepare($query);
        if (!$stmt) {
            return false;
        }
        $stmt->bind_param('ii', $user_id, $trend_id);
        return $stmt->execute();
    }

    /**
     * Check if a trend is pinned by user
     */
    public function isTrendPinned($user_id, $trend_id) {
        $query = "SELECT id FROM pinned_trends WHERE user_id = ? AND trend_id = ?";
        $stmt = $this->conn->prepare($query);
        if (!$stmt) {
            return false;
        }
        $stmt->bind_param('ii', $user_id, $trend_id);
        $stmt->execute();
        $result = $stmt->get_result();
        return $result->num_rows > 0;
    }

    /**
     * Get all pinned trends for a user
     */
    public function getUserPinnedTrends($user_id, $limit = 50, $offset = 0) {
        $query = "SELECT mt.* FROM market_trends mt
                  INNER JOIN pinned_trends pt ON mt.id = pt.trend_id
                  WHERE pt.user_id = ?
                  ORDER BY pt.pinned_at DESC
                  LIMIT ? OFFSET ?";
        $stmt = $this->conn->prepare($query);
        if (!$stmt) {
            return false;
        }
        $stmt->bind_param('iii', $user_id, $limit, $offset);
        $stmt->execute();
        return $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
    }

    /**
     * Get count of pinned trends for a user
     */
    public function getUserPinnedTrendsCount($user_id) {
        $query = "SELECT COUNT(*) as count FROM pinned_trends WHERE user_id = ?";
        $stmt = $this->conn->prepare($query);
        if (!$stmt) {
            return 0;
        }
        $stmt->bind_param('i', $user_id);
        $stmt->execute();
        $result = $stmt->get_result();
        $row = $result->fetch_assoc();
        return $row['count'] ?? 0;
    }

    /**
     * Get pinned trend IDs for a user (for quick lookup)
     */
    public function getUserPinnedTrendIds($user_id) {
        $query = "SELECT trend_id FROM pinned_trends WHERE user_id = ?";
        $stmt = $this->conn->prepare($query);
        if (!$stmt) {
            return [];
        }
        $stmt->bind_param('i', $user_id);
        $stmt->execute();
        $result = $stmt->get_result();
        $trend_ids = [];
        while ($row = $result->fetch_assoc()) {
            $trend_ids[] = $row['trend_id'];
        }
        return $trend_ids;
    }

    /**
     * Delete all pinned trends for a user
     */
    public function deleteAllUserPins($user_id) {
        $query = "DELETE FROM pinned_trends WHERE user_id = ?";
        $stmt = $this->conn->prepare($query);
        if (!$stmt) {
            return false;
        }
        $stmt->bind_param('i', $user_id);
        return $stmt->execute();
    }
}
?>

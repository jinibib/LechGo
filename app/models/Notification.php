<?php

class Notification {
    private $conn;
    
    public function __construct($conn = null) {
        // Use provided connection or get from globals
        $this->conn = $conn ?? $GLOBALS['conn'];
    }
    
    /**
     * Create a new notification
     */
    public function create($userId, $type, $title, $message, $link = null) {
        $stmt = $this->conn->prepare("
            INSERT INTO notifications (user_id, type, title, message, link) 
            VALUES (?, ?, ?, ?, ?)
        ");
        $stmt->bind_param('issss', $userId, $type, $title, $message, $link);
        $result = $stmt->execute();
        $stmt->close();
        return $result;
    }
    
    /**
     * Get unread notifications for a user
     */
    public function getUnreadByUser($userId, $limit = 10) {
        $stmt = $this->conn->prepare("
            SELECT * FROM notifications 
            WHERE user_id = ? AND is_read = 0 
            ORDER BY created_at DESC 
            LIMIT ?
        ");
        $stmt->bind_param('ii', $userId, $limit);
        $stmt->execute();
        $result = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
        $stmt->close();
        return $result;
    }
    
    /**
     * Get all notifications for a user
     */
    public function getAllByUser($userId, $limit = 50) {
        $stmt = $this->conn->prepare("
            SELECT * FROM notifications 
            WHERE user_id = ? 
            ORDER BY created_at DESC 
            LIMIT ?
        ");
        $stmt->bind_param('ii', $userId, $limit);
        $stmt->execute();
        $result = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
        $stmt->close();
        return $result;
    }
    
    /**
     * Get unread count for a user
     */
    public function getUnreadCount($userId) {
        $stmt = $this->conn->prepare("
            SELECT COUNT(*) as count 
            FROM notifications 
            WHERE user_id = ? AND is_read = 0
        ");
        $stmt->bind_param('i', $userId);
        $stmt->execute();
        $result = $stmt->get_result()->fetch_assoc();
        $stmt->close();
        return $result['count'] ?? 0;
    }
    
    /**
     * Mark notification as read
     */
    public function markAsRead($notificationId, $userId) {
        $stmt = $this->conn->prepare("
            UPDATE notifications 
            SET is_read = 1, read_at = CURRENT_TIMESTAMP 
            WHERE id = ? AND user_id = ?
        ");
        $stmt->bind_param('ii', $notificationId, $userId);
        $result = $stmt->execute();
        $stmt->close();
        return $result;
    }
    
    /**
     * Mark all notifications as read for a user
     */
    public function markAllAsRead($userId) {
        $stmt = $this->conn->prepare("
            UPDATE notifications 
            SET is_read = 1, read_at = CURRENT_TIMESTAMP 
            WHERE user_id = ? AND is_read = 0
        ");
        $stmt->bind_param('i', $userId);
        $result = $stmt->execute();
        $stmt->close();
        return $result;
    }
    
    /**
     * Delete a notification
     */
    public function delete($notificationId, $userId) {
        $stmt = $this->conn->prepare("
            DELETE FROM notifications 
            WHERE id = ? AND user_id = ?
        ");
        $stmt->bind_param('ii', $notificationId, $userId);
        $result = $stmt->execute();
        $stmt->close();
        return $result;
    }
    
    /**
     * Delete old read notifications (cleanup)
     */
    public function deleteOldReadNotifications($days = 30) {
        $stmt = $this->conn->prepare("
            DELETE FROM notifications 
            WHERE is_read = 1 
            AND read_at < DATE_SUB(NOW(), INTERVAL ? DAY)
        ");
        $stmt->bind_param('i', $days);
        $result = $stmt->execute();
        $stmt->close();
        return $result;
    }
    
    /**
     * Helper: Notify livestock owner when caretaker joins
     */
    public static function notifyLivestockOwnerCaretakerJoined($livestockOwnerId, $caretakerName) {
        $notification = new self();
        return $notification->create(
            $livestockOwnerId,
            'caretaker_request',
            'New Caretaker Request',
            "A new caretaker '{$caretakerName}' has requested to join your farm.",
            '/LechGo_Final/public/livestock-owner/manage-caretakers'
        );
    }
    
    /**
     * Helper: Notify caretaker when approved by livestock owner
     */
    public static function notifyCaretakerApproved($caretakerId, $farmName) {
        $notification = new self();
        return $notification->create(
            $caretakerId,
            'caretaker_approved',
            'Caretaker Request Approved',
            "Your request to join '{$farmName}' has been approved!",
            '/LechGo_Final/public/dashboard'
        );
    }
}

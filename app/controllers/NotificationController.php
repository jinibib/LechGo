<?php

require_once __DIR__ . '/../models/Notification.php';
require_once __DIR__ . '/../middleware/Session.php';

class NotificationController {
    private $notification;
    private $session;
    private $conn;
    
    public function __construct($conn = null) {
        $this->conn = $conn ?? $GLOBALS['conn'];
        $this->notification = new Notification($this->conn);
        $this->session = new Session();
    }
    
    /**
     * Get unread notifications count (AJAX)
     */
    public function getUnreadCount() {
        header('Content-Type: application/json');
        
        if (!$this->session->isAuthenticated()) {
            echo json_encode(['success' => false, 'message' => 'Unauthorized']);
            return;
        }
        
        $user = $this->session->getUser();
        $count = $this->notification->getUnreadCount($user['id']);
        
        echo json_encode(['success' => true, 'count' => $count]);
    }
    
    /**
     * Get notifications list (AJAX)
     */
    public function getNotifications() {
        header('Content-Type: application/json');
        
        if (!$this->session->isAuthenticated()) {
            echo json_encode(['success' => false, 'message' => 'Unauthorized']);
            return;
        }
        
        $user = $this->session->getUser();
        $notifications = $this->notification->getAllByUser($user['id'], 20);
        
        echo json_encode(['success' => true, 'notifications' => $notifications]);
    }
    
    /**
     * Mark notification as read (AJAX)
     */
    public function markAsRead() {
        header('Content-Type: application/json');
        
        if (!$this->session->isAuthenticated()) {
            echo json_encode(['success' => false, 'message' => 'Unauthorized']);
            return;
        }
        
        $data = json_decode(file_get_contents('php://input'), true);
        $notificationId = $data['id'] ?? null;
        
        if (!$notificationId) {
            echo json_encode(['success' => false, 'message' => 'Notification ID required']);
            return;
        }
        
        $user = $this->session->getUser();
        $result = $this->notification->markAsRead($notificationId, $user['id']);
        
        echo json_encode(['success' => $result]);
    }
    
    /**
     * Mark all notifications as read (AJAX)
     */
    public function markAllAsRead() {
        header('Content-Type: application/json');
        
        if (!$this->session->isAuthenticated()) {
            echo json_encode(['success' => false, 'message' => 'Unauthorized']);
            return;
        }
        
        $user = $this->session->getUser();
        $result = $this->notification->markAllAsRead($user['id']);
        
        echo json_encode(['success' => $result]);
    }
    
    /**
     * Delete notification (AJAX)
     */
    public function deleteNotification() {
        header('Content-Type: application/json');
        
        if (!$this->session->isAuthenticated()) {
            echo json_encode(['success' => false, 'message' => 'Unauthorized']);
            return;
        }
        
        $data = json_decode(file_get_contents('php://input'), true);
        $notificationId = $data['id'] ?? null;
        
        if (!$notificationId) {
            echo json_encode(['success' => false, 'message' => 'Notification ID required']);
            return;
        }
        
        $user = $this->session->getUser();
        $result = $this->notification->delete($notificationId, $user['id']);
        
        echo json_encode(['success' => $result]);
    }
}

// Handle AJAX requests
if ($_SERVER['REQUEST_METHOD'] === 'GET' || $_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        $controller = new NotificationController($GLOBALS['conn'] ?? null);
        $action = $_GET['action'] ?? $_POST['action'] ?? '';
        
        switch ($action) {
            case 'count':
                $controller->getUnreadCount();
                break;
            case 'list':
                $controller->getNotifications();
                break;
            case 'mark-read':
                $controller->markAsRead();
                break;
            case 'mark-all-read':
                $controller->markAllAsRead();
                break;
            case 'delete':
                $controller->deleteNotification();
                break;
            default:
                header('Content-Type: application/json');
                echo json_encode(['success' => false, 'message' => 'Invalid action: ' . $action]);
        }
    } catch (Exception $e) {
        header('Content-Type: application/json');
        http_response_code(500);
        echo json_encode(['success' => false, 'message' => 'Error: ' . $e->getMessage()]);
    }
}

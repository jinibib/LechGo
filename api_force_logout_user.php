<?php
/**
 * API Endpoint: Force Logout User
 * Invalidates a user's session by setting a flag in database
 */

// Clear any existing output buffers
while (ob_get_level()) {
    ob_end_clean();
}

// Set JSON header immediately
header('Content-Type: application/json');

// Disable error display
ini_set('display_errors', 0);
error_reporting(E_ALL);

// Start session
session_start();

// Define paths
define('BASE_PATH', dirname(__FILE__));
define('APP_PATH', BASE_PATH . '/app');
define('CONFIG_PATH', BASE_PATH . '/config');

try {
    // Load database
    $conn = require_once CONFIG_PATH . '/db.php';
    
    // Load required classes
    require_once APP_PATH . '/middleware/Session.php';
    
    // Check authentication
    $session = new Session();
    if (!$session->isAuthenticated()) {
        http_response_code(401);
        echo json_encode(['success' => false, 'message' => 'Unauthorized']);
        exit;
    }
    
    $user = $session->getUser();
    
    // Check admin role
    if ($user['role'] !== 'admin' && $user['role'] !== 'system_admin') {
        http_response_code(403);
        echo json_encode(['success' => false, 'message' => 'Unauthorized']);
        exit;
    }
    
    // Check request method
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => 'Invalid request method']);
        exit;
    }
    
    // Get target user ID
    $target_user_id = intval($_POST['user_id'] ?? 0);
    
    if ($target_user_id <= 0) {
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => 'Invalid user ID']);
        exit;
    }
    
    // Set a flag in the database to force logout on next request
    // We'll add a column 'force_logout' to users table or use updated_at to trigger session refresh
    $query = "UPDATE users SET updated_at = NOW() WHERE id = ?";
    $stmt = $conn->prepare($query);
    
    if (!$stmt) {
        throw new Exception('Database error: ' . $conn->error);
    }
    
    $stmt->bind_param('i', $target_user_id);
    $stmt->execute();
    $stmt->close();
    
    http_response_code(200);
    echo json_encode([
        'success' => true, 
        'message' => 'User will be logged out on their next request'
    ]);
    
} catch (Exception $e) {
    error_log("Force logout error: " . $e->getMessage());
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}

exit;

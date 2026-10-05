<?php
/**
 * API Endpoint: Reject Application
 * Direct endpoint to avoid any output buffering issues
 */

// Clear any existing output buffers
while (ob_get_level()) {
    ob_end_clean();
}

// Set JSON header immediately
header('Content-Type: application/json');

// Disable error display (log to file instead)
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
    require_once APP_PATH . '/models/RoleApplication.php';
    
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
    
    // Get parameters
    $application_id = intval($_POST['application_id'] ?? 0);
    $remarks = trim($_POST['remarks'] ?? '');
    
    if ($application_id <= 0) {
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => 'Invalid application ID']);
        exit;
    }
    
    if (empty($remarks)) {
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => 'Please provide rejection reason']);
        exit;
    }
    
    // Process rejection
    $roleApp = new RoleApplication($conn);
    $roleApp->reject($application_id, $user['id'], $remarks);
    
    http_response_code(200);
    echo json_encode(['success' => true, 'message' => 'Application rejected']);
    
} catch (Exception $e) {
    error_log("Rejection error: " . $e->getMessage());
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}

exit;

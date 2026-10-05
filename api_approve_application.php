<?php
/**
 * API Endpoint: Approve Application
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
    require_once APP_PATH . '/models/LivestockOwner.php';
    
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
    
    // Process approval
    $roleApp = new RoleApplication($conn);
    $roleApp->approve($application_id, $user['id'], $remarks);
    
    // Get the approved application to check user_id
    $app_query = "SELECT user_id FROM role_applications WHERE id = ?";
    $app_stmt = $conn->prepare($app_query);
    if ($app_stmt) {
        $app_stmt->bind_param('i', $application_id);
        $app_stmt->execute();
        $app_result = $app_stmt->get_result();
        $approved_app = $app_result->fetch_assoc();
        $app_stmt->close();
        
        $approved_user_id = $approved_app['user_id'] ?? null;
    } else {
        $approved_user_id = null;
    }
    
    http_response_code(200);
    echo json_encode([
        'success' => true, 
        'message' => 'Application approved successfully!',
        'approved_user_id' => $approved_user_id,
        'force_logout' => true
    ]);
    
} catch (Exception $e) {
    error_log("Approval error: " . $e->getMessage());
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}

exit;

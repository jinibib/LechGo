<?php
/**
 * Test Notification Script
 * Run this to create a test notification for the current user
 */

// Start session and load dependencies
session_start();

// Define base paths
define('BASE_PATH', dirname(__FILE__));
define('APP_PATH', BASE_PATH . '/app');
define('CONFIG_PATH', BASE_PATH . '/config');

// Load configuration and database
$conn = require_once CONFIG_PATH . '/db.php';
require_once APP_PATH . '/models/Notification.php';
require_once APP_PATH . '/middleware/Session.php';

// Check if user is logged in
$sessionMiddleware = new Session();
if (!$sessionMiddleware->isAuthenticated()) {
    echo "Please login first before running this test.\n";
    echo "Go to: http://localhost/LechGo_Final/public/login\n";
    exit;
}

$user = $sessionMiddleware->getUser();

// Create test notification
$notification = new Notification($conn);
$result = $notification->create(
    $user['id'],
    'test',
    'Test Notification',
    'This is a test notification to verify the system is working!',
    '/LechGo_Final/public/dashboard'
);

if ($result) {
    echo "✅ Test notification created successfully!\n";
    echo "User ID: " . $user['id'] . "\n";
    echo "User Name: " . $user['name'] . "\n";
    echo "User Role: " . $user['role'] . "\n";
    echo "\nGo to your dashboard to see the notification bell icon with a badge!\n";
    echo "Dashboard URL: http://localhost/LechGo_Final/public/dashboard\n";
} else {
    echo "❌ Failed to create test notification.\n";
    echo "Please check if the notifications table exists in your database.\n";
}
?>
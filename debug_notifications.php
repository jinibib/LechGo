<!DOCTYPE html>
<html>
<head>
    <title>Debug Notifications</title>
    <style>
        body { font-family: Arial, sans-serif; margin: 20px; }
        .test { margin: 10px 0; padding: 10px; border: 1px solid #ccc; }
        .success { background: #d4edda; }
        .error { background: #f8d7da; }
        .info { background: #d1ecf1; }
    </style>
</head>
<body>
    <h1>🔍 Notification System Debug</h1>
    
    <?php
    session_start();
    
    // Define paths
    define('BASE_PATH', dirname(__FILE__));
    define('APP_PATH', BASE_PATH . '/app');
    define('CONFIG_PATH', BASE_PATH . '/config');
    define('VIEWS_PATH', BASE_PATH . '/resources/views');
    
    echo '<div class="test info"><strong>1. Checking Session...</strong><br>';
    if (isset($_SESSION['user'])) {
        echo '✅ User logged in: ' . htmlspecialchars($_SESSION['user']['name']) . ' (' . $_SESSION['user']['role'] . ')';
    } else {
        echo '❌ No user session found. Please login first.';
        echo '<br><a href="/LechGo_Final/public/login">Login here</a>';
        echo '</div></body></html>';
        exit;
    }
    echo '</div>';
    
    echo '<div class="test info"><strong>2. Checking Database Connection...</strong><br>';
    try {
        $conn = require_once CONFIG_PATH . '/db.php';
        echo '✅ Database connected successfully';
    } catch (Exception $e) {
        echo '❌ Database connection failed: ' . $e->getMessage();
        echo '</div></body></html>';
        exit;
    }
    echo '</div>';
    
    echo '<div class="test info"><strong>3. Checking Notifications Table...</strong><br>';
    $result = $conn->query("SHOW TABLES LIKE 'notifications'");
    if ($result && $result->num_rows > 0) {
        echo '✅ Notifications table exists<br>';
        
        // Check table structure
        $structure = $conn->query("DESCRIBE notifications");
        echo 'Table columns: ';
        while ($row = $structure->fetch_assoc()) {
            echo $row['Field'] . ' ';
        }
    } else {
        echo '❌ Notifications table does not exist!<br>';
        echo 'Please run the SQL migration first.';
    }
    echo '</div>';
    
    echo '<div class="test info"><strong>4. Testing Notification Model...</strong><br>';
    try {
        require_once APP_PATH . '/models/Notification.php';
        $notification = new Notification($conn);
        echo '✅ Notification model loaded successfully<br>';
        
        // Test creating a notification
        $user_id = $_SESSION['user']['id'];
        $result = $notification->create($user_id, 'debug', 'Debug Test', 'This is a debug test notification');
        if ($result) {
            echo '✅ Test notification created successfully<br>';
        } else {
            echo '❌ Failed to create test notification<br>';
        }
        
        // Test getting count
        $count = $notification->getUnreadCount($user_id);
        echo '✅ Unread notifications count: ' . $count;
        
    } catch (Exception $e) {
        echo '❌ Notification model error: ' . $e->getMessage();
    }
    echo '</div>';
    
    echo '<div class="test info"><strong>5. Testing Notification API...</strong><br>';
    echo 'Try these URLs manually:<br>';
    echo '<a href="/LechGo_Final/public/notifications?action=count" target="_blank">Test Count API</a><br>';
    echo '<a href="/LechGo_Final/public/notifications?action=list" target="_blank">Test List API</a><br>';
    echo '</div>';
    
    echo '<div class="test info"><strong>6. Checking Files...</strong><br>';
    $files_to_check = [
        'public/notifications.js',
        'app/controllers/NotificationController.php',
        'resources/views/layouts/dashboard-layout.php'
    ];
    
    foreach ($files_to_check as $file) {
        if (file_exists($file)) {
            echo '✅ ' . $file . ' exists<br>';
        } else {
            echo '❌ ' . $file . ' missing<br>';
        }
    }
    echo '</div>';
    
    echo '<div class="test success"><strong>Next Steps:</strong><br>';
    echo '1. Check browser console for JavaScript errors (F12)<br>';
    echo '2. Test the API URLs above<br>';
    echo '3. Go to <a href="/LechGo_Final/public/dashboard">Dashboard</a> and check for notification bell<br>';
    echo '</div>';
    ?>
</body>
</html>
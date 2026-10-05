<?php

/**
 * Database Configuration
 * 
 * LechGO Database Connection Settings
 * Adjust credentials as needed for your environment
 */

// Database credentials - InfinityFree Configuration
define('DB_HOST', 'sql211.infinityfree.com');
define('DB_USER', 'if0_42218125');
define('DB_PASS', 'yxckmXHgjlDlVfw');
define('DB_NAME', 'if0_42218125_lechgo');
define('DB_PORT', 3306);

// Enable mysqli error reporting
mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);

// Create MySQLi connection with error handling
try {
    $conn = new mysqli(DB_HOST, DB_USER, DB_PASS, DB_NAME, DB_PORT);
    
    // Set charset to utf8mb4
    $conn->set_charset("utf8mb4");
    
} catch (mysqli_sql_exception $e) {
    // Log the error
    error_log("Database Connection Error: " . $e->getMessage());
    
    // Show user-friendly error page
    http_response_code(503);
    die("
    <!DOCTYPE html>
    <html>
    <head>
        <title>Database Connection Error</title>
        <style>
            body { font-family: Arial; text-align: center; padding: 50px; background: #f5f5f5; }
            .error-box { background: white; border-radius: 10px; padding: 30px; 
                         box-shadow: 0 2px 10px rgba(0,0,0,0.1); max-width: 500px; margin: 0 auto; }
            h1 { color: #c0392b; }
            p { color: #666; line-height: 1.6; }
            .btn { display: inline-block; margin-top: 20px; padding: 10px 20px; 
                   background: #c0392b; color: white; text-decoration: none; border-radius: 5px; }
        </style>
    </head>
    <body>
        <div class='error-box'>
            <h1>⚠️ Database Connection Error</h1>
            <p><strong>The database server is currently unavailable.</strong></p>
            <p>This is usually temporary with free hosting services. Please try again in a few minutes.</p>
            <p style='font-size: 12px; color: #999; margin-top: 20px;'>
                Error: Connection refused to sql211.infinityfree.com<br>
                If this persists, contact support.
            </p>
            <a href='javascript:location.reload()' class='btn'>🔄 Retry</a>
        </div>
    </body>
    </html>
    ");
}

// Return connection for use in other files
return $conn;

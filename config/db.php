<?php

/**
 * Database Configuration
 * 
 * LechGO Database Connection Settings
 * Adjust credentials as needed for your environment
 */

// Database credentials
define('DB_HOST', 'localhost');
define('DB_USER', 'root');
define('DB_PASS', '');
define('DB_NAME', 'lechgo_db');
define('DB_PORT', 3306);

// Create MySQLi connection
$conn = new mysqli(DB_HOST, DB_USER, DB_PASS, DB_NAME, DB_PORT);

// Check connection
if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}

// Set charset to utf8mb4
$conn->set_charset("utf8mb4");

// Return connection for use in other files
return $conn;

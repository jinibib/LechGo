<?php
/**
 * SECURITY WARNING: DELETE THIS FILE AFTER DEBUGGING!
 * This file should NOT be in production
 */

// Add basic security - change this password
$DEBUG_PASSWORD = 'debug123';

if (!isset($_GET['pass']) || $_GET['pass'] !== $DEBUG_PASSWORD) {
    die('Access denied');
}

require_once __DIR__ . '/../config/db.php';

header('Content-Type: text/plain');

echo "=== Database Debug Info ===\n\n";

// Get email from query parameter
$email = $_GET['email'] ?? null;

if ($email) {
    echo "Checking email: $email\n\n";
    
    // Check users table
    echo "1. Users table:\n";
    $query = "SELECT id, name, email, role, email_verified, created_at FROM users WHERE LOWER(email) = LOWER(?)";
    $stmt = $conn->prepare($query);
    $stmt->bind_param("s", $email);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($result->num_rows > 0) {
        echo "Found " . $result->num_rows . " user(s):\n";
        while ($row = $result->fetch_assoc()) {
            echo "  ID: {$row['id']}\n";
            echo "  Name: {$row['name']}\n";
            echo "  Email: {$row['email']}\n";
            echo "  Role: {$row['role']}\n";
            echo "  Verified: " . ($row['email_verified'] ? 'Yes' : 'No') . "\n";
            echo "  Created: {$row['created_at']}\n";
            echo "  ---\n";
        }
    } else {
        echo "  No user found with this email\n";
    }
    $stmt->close();
    
    // Check email_verification_tokens
    echo "\n2. Email verification tokens:\n";
    $query = "SELECT id, user_id, email, verified_at, expires_at, created_at FROM email_verification_tokens WHERE LOWER(email) = LOWER(?) ORDER BY created_at DESC LIMIT 5";
    $stmt = $conn->prepare($query);
    $stmt->bind_param("s", $email);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($result->num_rows > 0) {
        echo "Found " . $result->num_rows . " token(s):\n";
        while ($row = $result->fetch_assoc()) {
            echo "  Token ID: {$row['id']}\n";
            echo "  User ID: {$row['user_id']}\n";
            echo "  Email: {$row['email']}\n";
            echo "  Verified: " . ($row['verified_at'] ? $row['verified_at'] : 'Not yet') . "\n";
            echo "  Expires: {$row['expires_at']}\n";
            echo "  Created: {$row['created_at']}\n";
            echo "  ---\n";
        }
    } else {
        echo "  No tokens found\n";
    }
    $stmt->close();
    
    // Check OTPs
    echo "\n3. OTPs:\n";
    $query = "SELECT id, email, verified_at, expires_at, created_at FROM otps WHERE LOWER(email) = LOWER(?) ORDER BY created_at DESC LIMIT 5";
    $stmt = $conn->prepare($query);
    $stmt->bind_param("s", $email);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($result->num_rows > 0) {
        echo "Found " . $result->num_rows . " OTP(s):\n";
        while ($row = $result->fetch_assoc()) {
            echo "  OTP ID: {$row['id']}\n";
            echo "  Email: {$row['email']}\n";
            echo "  Verified: " . ($row['verified_at'] ? $row['verified_at'] : 'Not yet') . "\n";
            echo "  Expires: {$row['expires_at']}\n";
            echo "  Created: {$row['created_at']}\n";
            echo "  ---\n";
        }
    } else {
        echo "  No OTPs found\n";
    }
    $stmt->close();
    
} else {
    // Just show basic database info
    echo "Database Connection: " . ($conn->ping() ? "OK" : "FAILED") . "\n\n";
    
    echo "Total users: ";
    $result = $conn->query("SELECT COUNT(*) as count FROM users");
    $row = $result->fetch_assoc();
    echo $row['count'] . "\n\n";
    
    echo "Total email_verification_tokens: ";
    $result = $conn->query("SELECT COUNT(*) as count FROM email_verification_tokens");
    $row = $result->fetch_assoc();
    echo $row['count'] . "\n\n";
    
    echo "Total otps: ";
    $result = $conn->query("SELECT COUNT(*) as count FROM otps");
    $row = $result->fetch_assoc();
    echo $row['count'] . "\n\n";
    
    echo "Usage: debug_db.php?pass=$DEBUG_PASSWORD&email=test@example.com\n";
}

echo "\n=== SECURITY REMINDER ===\n";
echo "DELETE THIS FILE AFTER DEBUGGING!\n";

$conn->close();

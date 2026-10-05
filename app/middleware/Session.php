<?php

/**
 * Session Middleware
 * Handles session initialization and authentication checks
 */

class Session
{
    public function __construct()
    {
        // Session already started in index.php
        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }

        // Generate CSRF token if not exists
        if (!isset($_SESSION['csrf_token'])) {
            $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
        }
    }

    /**
     * Check if user is authenticated
     */
    public function isAuthenticated()
    {
        return isset($_SESSION['user_id']) && !empty($_SESSION['user_id']);
    }

    /**
     * Get current user ID
     */
    public function getUserId()
    {
        return $_SESSION['user_id'] ?? null;
    }

    /**
     * Get current user data
     * Automatically syncs with database if role might have changed
     */
    public function getUser()
    {
        // If user is in session, check if we need to sync with database
        if (isset($_SESSION['user']) && isset($_SESSION['user_id'])) {
            // Check last sync time
            $last_sync = $_SESSION['user_last_sync'] ?? 0;
            $current_time = time();
            
            // Sync immediately if never synced, or every 2 seconds
            // This ensures role changes are picked up quickly
            if ($last_sync === 0 || $current_time - $last_sync >= 2) {
                $this->syncUserFromDatabase();
            }
        }
        
        return $_SESSION['user'] ?? null;
    }
    
    /**
     * Sync user data from database
     * Updates session if role or other data has changed
     */
    private function syncUserFromDatabase()
    {
        if (!isset($_SESSION['user_id']) || !isset($GLOBALS['conn'])) {
            return;
        }
        
        $conn = $GLOBALS['conn'];
        $user_id = $_SESSION['user_id'];
        
        // Query fresh data from database
        $query = "SELECT id, email, name, role, phone FROM users WHERE id = ?";
        $stmt = $conn->prepare($query);
        
        if (!$stmt) {
            return;
        }
        
        $stmt->bind_param('i', $user_id);
        $stmt->execute();
        $result = $stmt->get_result();
        $db_user = $result->fetch_assoc();
        $stmt->close();
        
        if ($db_user) {
            // Check if role changed
            $role_changed = ($_SESSION['user']['role'] ?? '') !== $db_user['role'];
            
            // Update session with fresh data
            $_SESSION['user'] = [
                'id' => $db_user['id'],
                'email' => $db_user['email'],
                'name' => $db_user['name'],
                'role' => $db_user['role'],
                'phone' => $db_user['phone'],
            ];
            
            $_SESSION['user_last_sync'] = time();
            
            // Log role change for debugging
            if ($role_changed) {
                error_log("User {$user_id} role synced from database: {$db_user['role']}");
            }
        }
    }

    /**
     * Set user session
     */
    public function setUser($user_id, $email, $name, $role, $phone = null)
    {
        $_SESSION['user_id'] = $user_id;
        $_SESSION['user'] = [
            'id' => $user_id,
            'email' => $email,
            'name' => $name,
            'role' => $role,
            'phone' => $phone,
        ];
    }

    /**
     * Destroy user session
     */
    public function logout()
    {
        // Clear all session data first
        $_SESSION = array();
        
        // Destroy the session cookie if it exists
        if (isset($_COOKIE[session_name()])) {
            setcookie(session_name(), '', time()-3600, '/');
        }
        
        // Destroy the session
        session_destroy();
        
        // Start fresh session for flash messages
        session_start();
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
        $_SESSION['success'] = 'You have been logged out successfully.';
    }

    /**
     * Get CSRF token
     */
    public function getCsrfToken()
    {
        return $_SESSION['csrf_token'] ?? '';
    }

    /**
     * Verify CSRF token
     */
    public function verifyCsrfToken($token)
    {
        return isset($_SESSION['csrf_token']) && hash_equals($_SESSION['csrf_token'], $token);
    }

    /**
     * Get or create email verification session
     */
    public function setVerificationEmail($email, $user_id = null)
    {
        $_SESSION['verification_email'] = $email;
        if ($user_id) {
            $_SESSION['verification_user_id'] = $user_id;
        }
    }

    /**
     * Get verification email from session
     */
    public function getVerificationEmail()
    {
        return $_SESSION['verification_email'] ?? null;
    }

    /**
     * Get verification user ID from session
     */
    public function getVerificationUserId()
    {
        return $_SESSION['verification_user_id'] ?? null;
    }

    /**
     * Clear verification session
     */
    public function clearVerification()
    {
        unset($_SESSION['verification_email']);
        unset($_SESSION['verification_user_id']);
    }

    /**
     * Set OTP verification session
     */
    public function setOTPEmail($email)
    {
        $_SESSION['otp_email'] = $email;
    }

    /**
     * Get OTP email from session
     */
    public function getOTPEmail()
    {
        return $_SESSION['otp_email'] ?? null;
    }

    /**
     * Clear OTP session
     */
    public function clearOTP()
    {
        unset($_SESSION['otp_email']);
    }
}

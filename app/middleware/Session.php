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
     */
    public function getUser()
    {
        return $_SESSION['user'] ?? null;
    }

    /**
     * Set user session
     */
    public function setUser($user_id, $email, $name, $role)
    {
        $_SESSION['user_id'] = $user_id;
        $_SESSION['user'] = [
            'id' => $user_id,
            'email' => $email,
            'name' => $name,
            'role' => $role,
        ];
    }

    /**
     * Destroy user session
     */
    public function logout()
    {
        session_destroy();
        session_start();
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
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

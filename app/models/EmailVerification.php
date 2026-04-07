<?php

/**
 * EmailVerification Model
 * Handles email verification token operations
 */

class EmailVerification
{
    private $conn;
    private $table = 'email_verification_tokens';

    public $id;
    public $user_id;
    public $email;
    public $token;
    public $verified_at;
    public $expires_at;

    public function __construct($db)
    {
        $this->conn = $db;
    }

    /**
     * Generate and store verification token
     */
    public function generateToken($user_id, $email)
    {
        // Generate unique token
        $token = bin2hex(random_bytes(32));

        // Set expiration time (24 hours)
        $expires_at = date('Y-m-d H:i:s', time() + EMAIL_TOKEN_EXPIRY);

        // Delete any existing tokens for this user
        $deleteQuery = "DELETE FROM " . $this->table . " WHERE user_id = ?";
        $deleteStmt = $this->conn->prepare($deleteQuery);

        if ($deleteStmt) {
            $deleteStmt->bind_param("i", $user_id);
            $deleteStmt->execute();
            $deleteStmt->close();
        }

        // Insert new token
        $query = "INSERT INTO " . $this->table . " (user_id, email, token, expires_at) 
                 VALUES (?, ?, ?, ?)";
        $stmt = $this->conn->prepare($query);

        if (!$stmt) {
            throw new Exception("Prepare error: " . $this->conn->error);
        }

        $stmt->bind_param("isss", $user_id, $email, $token, $expires_at);

        if ($stmt->execute()) {
            $this->id = $this->conn->insert_id;
            $this->user_id = $user_id;
            $this->email = $email;
            $this->token = $token;
            $this->expires_at = $expires_at;

            $stmt->close();
            return $token;
        } else {
            $stmt->close();
            throw new Exception("Error generating token: " . $this->conn->error);
        }
    }

    /**
     * Verify token
     */
    public function verifyToken($token)
    {
        $query = "SELECT * FROM " . $this->table . " 
                 WHERE token = ? AND verified_at IS NULL 
                 AND expires_at > NOW()";
        $stmt = $this->conn->prepare($query);

        if (!$stmt) {
            throw new Exception("Prepare error: " . $this->conn->error);
        }

        $stmt->bind_param("s", $token);
        $stmt->execute();
        $result = $stmt->get_result();

        if ($result->num_rows > 0) {
            $row = $result->fetch_assoc();
            $this->id = $row['id'];
            $this->user_id = $row['user_id'];
            $this->email = $row['email'];
            $this->token = $row['token'];
            $this->verified_at = $row['verified_at'];
            $this->expires_at = $row['expires_at'];

            $stmt->close();
            return true;
        }

        $stmt->close();
        return false;
    }

    /**
     * Mark token as verified
     */
    public function markAsVerified()
    {
        $query = "UPDATE " . $this->table . " SET verified_at = NOW() WHERE id = ?";
        $stmt = $this->conn->prepare($query);

        if (!$stmt) {
            throw new Exception("Prepare error: " . $this->conn->error);
        }

        $stmt->bind_param("i", $this->id);
        $result = $stmt->execute();
        $stmt->close();

        return $result;
    }

    /**
     * Get token by user ID
     */
    public function getByUserId($user_id)
    {
        $query = "SELECT * FROM " . $this->table . " 
                 WHERE user_id = ? AND verified_at IS NULL 
                 ORDER BY created_at DESC LIMIT 1";
        $stmt = $this->conn->prepare($query);

        if (!$stmt) {
            throw new Exception("Prepare error: " . $this->conn->error);
        }

        $stmt->bind_param("i", $user_id);
        $stmt->execute();
        $result = $stmt->get_result();

        if ($result->num_rows > 0) {
            $row = $result->fetch_assoc();
            $this->id = $row['id'];
            $this->user_id = $row['user_id'];
            $this->email = $row['email'];
            $this->token = $row['token'];
            $this->verified_at = $row['verified_at'];
            $this->expires_at = $row['expires_at'];

            $stmt->close();
            return true;
        }

        $stmt->close();
        return false;
    }

    /**
     * Check if token is expired
     */
    public function isExpired()
    {
        return strtotime($this->expires_at) < time();
    }
}

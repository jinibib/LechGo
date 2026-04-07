<?php

/**
 * OTP Model
 * Handles OTP generation and verification
 */

class OTP
{
    private $conn;
    private $table = 'otp_verification';

    public $id;
    public $email;
    public $otp_code;
    public $attempts;
    public $verified_at;
    public $expires_at;
    public $locked_until;
    public $lastError = null;

    public function __construct($db)
    {
        $this->conn = $db;
    }

    /**
     * Generate and store OTP
     */
    public function generateOTP($email)
    {
        // Generate random 6-digit OTP
        $otp_code = str_pad(random_int(0, 999999), 6, '0', STR_PAD_LEFT);

        // Set expiration time (5 minutes)
        $expires_at = date('Y-m-d H:i:s', time() + OTP_EXPIRY);

        // Delete any existing OTP for this email
        $deleteQuery = "DELETE FROM " . $this->table . " WHERE email = ?";
        $deleteStmt = $this->conn->prepare($deleteQuery);

        if ($deleteStmt) {
            $deleteStmt->bind_param("s", $email);
            $deleteStmt->execute();
            $deleteStmt->close();
        }

        // Insert new OTP
        $query = "INSERT INTO " . $this->table . " (email, otp_code, expires_at, attempts) 
                 VALUES (?, ?, ?, 0)";
        $stmt = $this->conn->prepare($query);

        if (!$stmt) {
            throw new Exception("Prepare error: " . $this->conn->error);
        }

        $stmt->bind_param("sss", $email, $otp_code, $expires_at);

        if ($stmt->execute()) {
            $this->id = $this->conn->insert_id;
            $this->email = $email;
            $this->otp_code = $otp_code;
            $this->expires_at = $expires_at;
            $this->attempts = 0;

            $stmt->close();
            return $otp_code;
        } else {
            $stmt->close();
            throw new Exception("Error generating OTP: " . $this->conn->error);
        }
    }

    /**
     * Verify OTP
     */
    public function verifyOTP($email, $otp_code)
    {
        // Check if account is locked
        $lockQuery = "SELECT locked_until FROM " . $this->table . " 
                    WHERE email = ? AND locked_until IS NOT NULL 
                    AND locked_until > NOW() LIMIT 1";
        $lockStmt = $this->conn->prepare($lockQuery);

        if ($lockStmt) {
            $lockStmt->bind_param("s", $email);
            $lockStmt->execute();
            $lockResult = $lockStmt->get_result();

            if ($lockResult->num_rows > 0) {
                $lockStmt->close();
                throw new Exception("Account temporarily locked. Please try again later.");
            }
            $lockStmt->close();
        }

        // Verify OTP
        $query = "SELECT * FROM " . $this->table . " 
                 WHERE email = ? AND otp_code = ? 
                 AND verified_at IS NULL 
                 ORDER BY created_at DESC LIMIT 1";
        $stmt = $this->conn->prepare($query);

        if (!$stmt) {
            throw new Exception("Prepare error: " . $this->conn->error);
        }

        $stmt->bind_param("ss", $email, $otp_code);
        $stmt->execute();
        $result = $stmt->get_result();

        if ($result->num_rows > 0) {
            $row = $result->fetch_assoc();
            $this->id = $row['id'];
            $this->email = $row['email'];
            $this->otp_code = $row['otp_code'];
            $this->attempts = $row['attempts'];
            $this->verified_at = $row['verified_at'];
            $this->expires_at = $row['expires_at'];
            $this->locked_until = $row['locked_until'];

            $stmt->close();

            // Check expiration using PHP time to avoid DB timezone mismatch
            $expiresTimestamp = strtotime($this->expires_at);
            if ($expiresTimestamp === false || $expiresTimestamp < time()) {
                $this->lastError = 'expired';
                $this->incrementAttempts($email);
                return false;
            }

            // Mark as verified
            $this->markAsVerified();
            return true;
        }

        // If no matching code found, check if there is an active OTP for this email to determine invalid vs expired
        $stmt->close();

        $activeQuery = "SELECT * FROM " . $this->table . " 
                        WHERE email = ? AND verified_at IS NULL
                        ORDER BY created_at DESC LIMIT 1";
        $activeStmt = $this->conn->prepare($activeQuery);
        if ($activeStmt) {
            $activeStmt->bind_param("s", $email);
            $activeStmt->execute();
            $activeResult = $activeStmt->get_result();

            if ($activeResult->num_rows > 0) {
                $activeRow = $activeResult->fetch_assoc();
                $this->expires_at = $activeRow['expires_at'];
                $expiresTimestamp = strtotime($activeRow['expires_at']);
                if ($expiresTimestamp !== false && $expiresTimestamp < time()) {
                    $this->lastError = 'expired';
                } else {
                    $this->lastError = 'invalid';
                }
            } else {
                $this->lastError = 'invalid';
            }
            $activeStmt->close();
        } else {
            $this->lastError = 'invalid';
        }

        // Increment failed attempts only once
        $this->incrementAttempts($email);
        return false;
    }

    /**
     * Mark OTP as verified
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
     * Increment failed attempts and lock account if necessary
     */
    public function incrementAttempts($email)
    {
        $query = "UPDATE " . $this->table . " SET attempts = attempts + 1 
                 WHERE email = ? ORDER BY created_at DESC LIMIT 1";
        $stmt = $this->conn->prepare($query);

        if (!$stmt) {
            throw new Exception("Prepare error: " . $this->conn->error);
        }

        $stmt->bind_param("s", $email);
        $stmt->execute();
        $stmt->close();

        // Check if attempts exceeded
        $checkQuery = "SELECT attempts FROM " . $this->table . " 
                     WHERE email = ? ORDER BY created_at DESC LIMIT 1";
        $checkStmt = $this->conn->prepare($checkQuery);

        if ($checkStmt) {
            $checkStmt->bind_param("s", $email);
            $checkStmt->execute();
            $checkResult = $checkStmt->get_result();

            if ($checkResult->num_rows > 0) {
                $row = $checkResult->fetch_assoc();

                if ($row['attempts'] >= MAX_OTP_ATTEMPTS) {
                    // Lock account for OTP_LOCKOUT_TIME
                    $locked_until = date('Y-m-d H:i:s', time() + OTP_LOCKOUT_TIME);
                    $lockQuery = "UPDATE " . $this->table . " SET locked_until = ? 
                                WHERE email = ? ORDER BY created_at DESC LIMIT 1";
                    $lockStmt = $this->conn->prepare($lockQuery);

                    if ($lockStmt) {
                        $lockStmt->bind_param("ss", $locked_until, $email);
                        $lockStmt->execute();
                        $lockStmt->close();
                    }
                }
            }

            $checkStmt->close();
        }
    }

    /**
     * Get OTP by email
     */
    public function getByEmail($email)
    {
        $query = "SELECT * FROM " . $this->table . " 
                 WHERE email = ? ORDER BY created_at DESC LIMIT 1";
        $stmt = $this->conn->prepare($query);

        if (!$stmt) {
            throw new Exception("Prepare error: " . $this->conn->error);
        }

        $stmt->bind_param("s", $email);
        $stmt->execute();
        $result = $stmt->get_result();

        if ($result->num_rows > 0) {
            $row = $result->fetch_assoc();
            $this->id = $row['id'];
            $this->email = $row['email'];
            $this->otp_code = $row['otp_code'];
            $this->attempts = $row['attempts'];
            $this->verified_at = $row['verified_at'];
            $this->expires_at = $row['expires_at'];
            $this->locked_until = $row['locked_until'];

            $stmt->close();
            return true;
        }

        $stmt->close();
        return false;
    }

    /**
     * Check if OTP is expired
     */
    public function isExpired()
    {
        return strtotime($this->expires_at) < time();
    }

    /**
     * Get last OTP verification error state (invalid or expired)
     */
    public function getLastError()
    {
        return $this->lastError;
    }

    /**
     * Check if account is locked
     */
    public function isLocked()
    {
        if ($this->locked_until === null) {
            return false;
        }

        return strtotime($this->locked_until) > time();
    }
}

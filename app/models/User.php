<?php

/**
 * User Model
 * Handles user database operations
 */

class User
{
    private $conn;
    private $table = 'users';

    public $id;
    public $name;
    public $email;
    public $password;
    public $phone;
    public $role;
    public $email_verified;
    public $created_at;

    public function __construct($db)
    {
        $this->conn = $db;
    }

    /**
     * Find user by email
     */
    public function findByEmail($email)
    {
        $query = "SELECT * FROM " . $this->table . " WHERE email = ?";
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
            $this->name = $row['name'];
            $this->email = $row['email'];
            $this->password = $row['password'];
            $this->phone = $row['phone'];
            $this->role = $row['role'];
            $this->email_verified = $row['email_verified'];
            $this->created_at = $row['created_at'];

            $stmt->close();
            return true;
        }

        $stmt->close();
        return false;
    }

    /**
     * Find user by ID
     */
    public function findById($id)
    {
        $query = "SELECT * FROM " . $this->table . " WHERE id = ?";
        $stmt = $this->conn->prepare($query);

        if (!$stmt) {
            throw new Exception("Prepare error: " . $this->conn->error);
        }

        $stmt->bind_param("i", $id);
        $stmt->execute();
        $result = $stmt->get_result();

        if ($result->num_rows > 0) {
            $row = $result->fetch_assoc();
            $this->id = $row['id'];
            $this->name = $row['name'];
            $this->email = $row['email'];
            $this->password = $row['password'];
            $this->phone = $row['phone'];
            $this->role = $row['role'];
            $this->email_verified = $row['email_verified'];
            $this->created_at = $row['created_at'];

            $stmt->close();
            return true;
        }

        $stmt->close();
        return false;
    }

    /**
     * Create new user
     */
    public function create($name, $email, $password, $phone, $role = 'customer')
    {
        // Check if email already exists
        if ($this->findByEmail($email)) {
            throw new Exception("Email already registered");
        }

        // Hash password
        $hashedPassword = password_hash($password, PASSWORD_BCRYPT);

        $query = "INSERT INTO " . $this->table . " (name, email, password, phone, role, email_verified) 
                 VALUES (?, ?, ?, ?, ?, 0)";
        $stmt = $this->conn->prepare($query);

        if (!$stmt) {
            throw new Exception("Prepare error: " . $this->conn->error);
        }

        $stmt->bind_param("sssss", $name, $email, $hashedPassword, $phone, $role);
        
        if ($stmt->execute()) {
            $this->id = $this->conn->insert_id;
            $this->name = $name;
            $this->email = $email;
            $this->password = $hashedPassword;
            $this->phone = $phone;
            $this->role = $role;
            $this->email_verified = 0;

            $stmt->close();
            return $this->id;
        } else {
            $stmt->close();
            throw new Exception("Error creating user: " . $this->conn->error);
        }
    }

    /**
     * Verify password
     */
    public function verifyPassword($password)
    {
        return password_verify($password, $this->password);
    }

    /**
     * Mark email as verified
     */
    public function markEmailVerified()
    {
        $query = "UPDATE " . $this->table . " SET email_verified = 1, email_verified_at = NOW() 
                 WHERE id = ?";
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
     * Check if email is verified
     */
    public function isEmailVerified()
    {
        return $this->email_verified == 1;
    }

    /**
     * Update last login
     */
    public function updateLastLogin()
    {
        $query = "UPDATE " . $this->table . " SET last_login = NOW() WHERE id = ?";
        $stmt = $this->conn->prepare($query);

        if (!$stmt) {
            throw new Exception("Prepare error: " . $this->conn->error);
        }

        $stmt->bind_param("i", $this->id);
        $result = $stmt->execute();
        $stmt->close();

        return $result;
    }
}

<?php

/**
 * Lechonero Model
 * Handles lechonero database operations
 */

class Lechonero
{
    private $conn;
    private $table = 'lechoneros';

    public $id;
    public $user_id;
    public $business_name;
    public $specialty;
    public $rating;

    public function __construct($db)
    {
        $this->conn = $db;
    }

    /**
     * Find lechonero by user ID
     */
    public function findByUserId($user_id)
    {
        $query = "SELECT * FROM " . $this->table . " WHERE user_id = ?";
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
            $this->business_name = $row['business_name'];
            $this->specialty = $row['specialty'];
            $this->rating = $row['rating'];

            $stmt->close();
            return true;
        }

        $stmt->close();
        return false;
    }

    /**
     * Create new lechonero
     */
    public function create($user_id, $business_name, $specialty)
    {
        $query = "INSERT INTO " . $this->table . " (user_id, business_name, specialty, rating) 
                 VALUES (?, ?, ?, 0)";
        $stmt = $this->conn->prepare($query);

        if (!$stmt) {
            throw new Exception("Prepare error: " . $this->conn->error);
        }

        $stmt->bind_param("iss", $user_id, $business_name, $specialty);
        
        if ($stmt->execute()) {
            $this->id = $this->conn->insert_id;
            $this->user_id = $user_id;
            $this->business_name = $business_name;
            $this->specialty = $specialty;
            $this->rating = 0;

            $stmt->close();
            return $this->id;
        } else {
            $stmt->close();
            throw new Exception("Error creating lechonero: " . $this->conn->error);
        }
    }
}
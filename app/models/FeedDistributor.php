<?php

/**
 * FeedDistributor Model
 * Handles feed distributor database operations
 */

class FeedDistributor
{
    private $conn;
    private $table = 'feed_distributors';

    public $id;
    public $user_id;
    public $business_name;
    public $location_id;
    public $street;
    public $barangay;
    public $municipality;
    public $city;
    public $contact_number;

    public function __construct($db)
    {
        $this->conn = $db;
    }

    public function findByUserId($user_id)
    {
        $query = "SELECT fd.*, l.street, l.barangay, l.municipality, l.city
                  FROM {$this->table} fd
                  LEFT JOIN locations l ON fd.location_id = l.location_id
                  WHERE fd.user_id = ?";
        $stmt = $this->conn->prepare($query);
        if (!$stmt) throw new Exception("Prepare error: " . $this->conn->error);

        $stmt->bind_param("i", $user_id);
        $stmt->execute();
        $result = $stmt->get_result();

        if ($result->num_rows > 0) {
            $row = $result->fetch_assoc();
            $this->id              = $row['id'];
            $this->user_id         = $row['user_id'];
            $this->business_name   = $row['business_name'];
            $this->location_id     = $row['location_id'];
            $this->street          = $row['street'] ?? null;
            $this->barangay        = $row['barangay'] ?? null;
            $this->municipality    = $row['municipality'] ?? null;
            $this->city            = $row['city'] ?? 'Davao City';
            $this->contact_number  = $row['contact_number'];
            $stmt->close();
            return true;
        }

        $stmt->close();
        return false;
    }

    public function create($user_id, $business_name, $street, $barangay, $municipality, $city, $contact_number)
    {
        // Create location record
        $loc_query = "INSERT INTO locations (street, barangay, municipality, city) VALUES (?, ?, ?, ?)";
        $loc_stmt = $this->conn->prepare($loc_query);
        if (!$loc_stmt) throw new Exception("Prepare error: " . $this->conn->error);
        $loc_stmt->bind_param("ssss", $street, $barangay, $municipality, $city);
        if (!$loc_stmt->execute()) throw new Exception("Error creating location: " . $this->conn->error);
        $location_id = $this->conn->insert_id;
        $loc_stmt->close();

        // Create distributor record
        $query = "INSERT INTO {$this->table} (user_id, business_name, location_id, contact_number) VALUES (?, ?, ?, ?)";
        $stmt = $this->conn->prepare($query);
        if (!$stmt) throw new Exception("Prepare error: " . $this->conn->error);
        $stmt->bind_param("isii", $user_id, $business_name, $location_id, $contact_number);
        if (!$stmt->execute()) throw new Exception("Error creating distributor: " . $this->conn->error);

        $this->id             = $this->conn->insert_id;
        $this->user_id        = $user_id;
        $this->business_name  = $business_name;
        $this->location_id    = $location_id;
        $this->street         = $street;
        $this->barangay       = $barangay;
        $this->municipality   = $municipality;
        $this->city           = $city;
        $this->contact_number = $contact_number;

        $stmt->close();
        return $this->id;
    }
}

<?php

/**
 * PigCaretaker Model
 * Handles pig caretaker database operations
 */

class PigCaretaker
{
    private $conn;
    private $table = 'pig_caretakers';

    public $id;
    public $user_id;
    public $farm_name;
    public $location;
    public $contact_number;
    public $created_at;

    public function __construct($db)
    {
        $this->conn = $db;
    }

    /**
     * Find pig caretaker by user ID
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
            $this->farm_name = $row['farm_name'];
            $this->location = $row['location'];
            $this->contact_number = $row['contact_number'];
            $this->created_at = $row['created_at'];

            $stmt->close();
            return true;
        }

        $stmt->close();
        return false;
    }

    /**
     * Create new pig caretaker
     */
    public function create($user_id, $farm_name, $location, $contact_number)
    {
        $query = "INSERT INTO " . $this->table . " (user_id, farm_name, location, contact_number) 
                 VALUES (?, ?, ?, ?)";
        $stmt = $this->conn->prepare($query);

        if (!$stmt) {
            throw new Exception("Prepare error: " . $this->conn->error);
        }

        $stmt->bind_param("isss", $user_id, $farm_name, $location, $contact_number);
        
        if ($stmt->execute()) {
            $this->id = $this->conn->insert_id;
            $this->user_id = $user_id;
            $this->farm_name = $farm_name;
            $this->location = $location;
            $this->contact_number = $contact_number;

            // Create default cages (A-E)
            $this->createDefaultCages($this->id);

            $stmt->close();
            return $this->id;
        } else {
            $stmt->close();
            throw new Exception("Error creating pig caretaker: " . $this->conn->error);
        }
    }

    /**
     * Create default pig cages A-E for new caretaker
     */
    private function createDefaultCages($caretaker_id)
    {
        $cages = ['A', 'B', 'C', 'D', 'E'];
        $query = "INSERT INTO pig_cages (caretaker_id, cage_number, current_pig_count, max_capacity, status) 
                 VALUES (?, ?, 0, 3, 'active')";
        $stmt = $this->conn->prepare($query);

        if (!$stmt) {
            throw new Exception("Prepare error: " . $this->conn->error);
        }

        foreach ($cages as $cage) {
            $stmt->bind_param("is", $caretaker_id, $cage);
            $stmt->execute();
        }

        $stmt->close();
    }

    /**
     * Get all pig cages for this caretaker
     */
    public function getPigCages()
    {
        $query = "SELECT * FROM pig_cages WHERE caretaker_id = ? ORDER BY cage_number ASC";
        $stmt = $this->conn->prepare($query);

        if (!$stmt) {
            throw new Exception("Prepare error: " . $this->conn->error);
        }

        $stmt->bind_param("i", $this->id);
        $stmt->execute();
        $result = $stmt->get_result();

        $cages = [];
        while ($row = $result->fetch_assoc()) {
            $cages[] = $row;
        }

        $stmt->close();
        return $cages;
    }

    /**
     * Get all pigs in a specific cage
     */
    public function getPigsInCage($cage_id)
    {
        $query = "SELECT * FROM pig_details WHERE cage_id = ? AND status = 'active' ORDER BY created_at DESC";
        $stmt = $this->conn->prepare($query);

        if (!$stmt) {
            throw new Exception("Prepare error: " . $this->conn->error);
        }

        $stmt->bind_param("i", $cage_id);
        $stmt->execute();
        $result = $stmt->get_result();

        $pigs = [];
        while ($row = $result->fetch_assoc()) {
            $pigs[] = $row;
        }

        $stmt->close();
        return $pigs;
    }

    /**
     * Add pig to cage
     */
    public function addPigToCage($cage_id, $breed, $age_months, $weight_kg, $health_status = 'healthy')
    {
        // Check cage capacity
        $cageQuery = "SELECT current_pig_count, max_capacity FROM pig_cages WHERE id = ? AND caretaker_id = ?";
        $cageStmt = $this->conn->prepare($cageQuery);
        $cageStmt->bind_param("ii", $cage_id, $this->id);
        $cageStmt->execute();
        $cageResult = $cageStmt->get_result();

        if ($cageResult->num_rows === 0) {
            throw new Exception("Cage not found");
        }

        $cage = $cageResult->fetch_assoc();
        if ($cage['current_pig_count'] >= $cage['max_capacity']) {
            throw new Exception("Cage is at maximum capacity (3 pigs)");
        }

        $cageStmt->close();

        // Add pig
        $query = "INSERT INTO pig_details (cage_id, breed, age_months, weight_kg, health_status, date_added, status) 
                 VALUES (?, ?, ?, ?, ?, CURDATE(), 'active')";
        $stmt = $this->conn->prepare($query);

        if (!$stmt) {
            throw new Exception("Prepare error: " . $this->conn->error);
        }

        $stmt->bind_param("isids", $cage_id, $breed, $age_months, $weight_kg, $health_status);
        
        if ($stmt->execute()) {
            // Update cage pig count
            $updateQuery = "UPDATE pig_cages SET current_pig_count = current_pig_count + 1 WHERE id = ?";
            $updateStmt = $this->conn->prepare($updateQuery);
            $updateStmt->bind_param("i", $cage_id);
            $updateStmt->execute();
            $updateStmt->close();

            $stmt->close();
            return $this->conn->insert_id;
        } else {
            $stmt->close();
            throw new Exception("Error adding pig: " . $this->conn->error);
        }
    }

    /**
     * Get feed inventory
     */
    public function getFeedInventory()
    {
        $query = "SELECT * FROM feed_inventory WHERE caretaker_id = ? AND status IN ('in_stock', 'low_stock') ORDER BY created_at DESC";
        $stmt = $this->conn->prepare($query);

        if (!$stmt) {
            throw new Exception("Prepare error: " . $this->conn->error);
        }

        $stmt->bind_param("i", $this->id);
        $stmt->execute();
        $result = $stmt->get_result();

        $feeds = [];
        while ($row = $result->fetch_assoc()) {
            $feeds[] = $row;
        }

        $stmt->close();
        return $feeds;
    }

    /**
     * Add feed to inventory
     */
    public function addFeedToInventory($feed_type, $quantity_kg, $unit_price = null, $supplier_name = null, $purchase_date = null, $expiry_date = null)
    {
        $query = "INSERT INTO feed_inventory (caretaker_id, feed_type, quantity_kg, unit_price, supplier_name, purchase_date, expiry_date, status) 
                 VALUES (?, ?, ?, ?, ?, ?, ?, 'in_stock')";
        $stmt = $this->conn->prepare($query);

        if (!$stmt) {
            throw new Exception("Prepare error: " . $this->conn->error);
        }

        $stmt->bind_param("isddsss", $this->id, $feed_type, $quantity_kg, $unit_price, $supplier_name, $purchase_date, $expiry_date);
        
        if ($stmt->execute()) {
            $stmt->close();
            return $this->conn->insert_id;
        } else {
            $stmt->close();
            throw new Exception("Error adding feed inventory: " . $this->conn->error);
        }
    }

    /**
     * Get total pig count
     */
    public function getTotalPigCount()
    {
        $query = "SELECT SUM(current_pig_count) as total FROM pig_cages WHERE caretaker_id = ?";
        $stmt = $this->conn->prepare($query);

        if (!$stmt) {
            throw new Exception("Prepare error: " . $this->conn->error);
        }

        $stmt->bind_param("i", $this->id);
        $stmt->execute();
        $result = $stmt->get_result();
        $row = $result->fetch_assoc();

        $stmt->close();
        return $row['total'] ?? 0;
    }

    /**
     * Get total feed in stock
     */
    public function getTotalFeedInStock()
    {
        $query = "SELECT SUM(quantity_kg) as total FROM feed_inventory WHERE caretaker_id = ? AND status IN ('in_stock', 'low_stock')";
        $stmt = $this->conn->prepare($query);

        if (!$stmt) {
            throw new Exception("Prepare error: " . $this->conn->error);
        }

        $stmt->bind_param("i", $this->id);
        $stmt->execute();
        $result = $stmt->get_result();
        $row = $result->fetch_assoc();

        $stmt->close();
        return $row['total'] ?? 0;
    }
}

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
    public $livestock_owner_id;
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
            $this->livestock_owner_id = $row['livestock_owner_id'];
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
     * Create default pig pins 1-7 for new caretaker
     */
    private function createDefaultCages($caretaker_id)
    {
        $pins = ['1', '2', '3', '4', '5', '6', '7'];
        $query = "INSERT INTO pig_pins (caretaker_id, cage_number, current_pig_count, max_capacity, status) 
                 VALUES (?, ?, 0, 3, 'inactive')";
        $stmt = $this->conn->prepare($query);

        if (!$stmt) {
            throw new Exception("Prepare error: " . $this->conn->error);
        }

        foreach ($pins as $pin) {
            $stmt->bind_param("is", $caretaker_id, $pin);
            $stmt->execute();
        }

        $stmt->close();
    }

    /**
     * Get all pig cages for this caretaker.
     * current_pig_count is computed live from pig_details so it is always
     * accurate even if the stored counter drifted (e.g. direct DB deletes).
     */
    public function getPigCages()
    {
        $query = "SELECT pp.*,
                         COUNT(pd.id) AS current_pig_count
                  FROM pig_pins pp
                  LEFT JOIN pig_details pd ON pd.cage_id = pp.id AND pd.status = 'active'
                  WHERE pp.caretaker_id = ?
                  GROUP BY pp.id
                  ORDER BY pp.cage_number + 0 ASC";
        $stmt = $this->conn->prepare($query);

        if (!$stmt) {
            throw new Exception("Prepare error: " . $this->conn->error);
        }

        $stmt->bind_param("i", $this->id);
        $stmt->execute();
        $result = $stmt->get_result();

        $cages = [];
        while ($row = $result->fetch_assoc()) {
            // Keep the stored counter in sync while we're here
            $this->conn->query(
                "UPDATE pig_pins SET current_pig_count = " . (int)$row['current_pig_count'] .
                ", status = '" . ($row['current_pig_count'] > 0 ? 'active' : 'inactive') . "'" .
                " WHERE id = " . (int)$row['id']
            );
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
        $cageQuery = "SELECT current_pig_count, max_capacity FROM pig_pins WHERE id = ? AND caretaker_id = ?";
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
            // Update pin pig count and set active
            $updateQuery = "UPDATE pig_pins SET current_pig_count = current_pig_count + 1, status = 'active' WHERE id = ?";
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
     * Add feed to inventory (upsert: adds to existing row if same feed_type exists)
     */
    public function addFeedToInventory($feed_type, $quantity_kg, $unit_price = null, $supplier_name = null, $purchase_date = null, $expiry_date = null, $product_name = null)
    {
        // Check if a row already exists for this caretaker + feed_type
        $check = $this->conn->prepare(
            "SELECT id, quantity_kg FROM feed_inventory WHERE caretaker_id = ? AND feed_type = ? LIMIT 1"
        );
        if (!$check) {
            throw new Exception("Prepare error: " . $this->conn->error);
        }
        $check->bind_param("is", $this->id, $feed_type);
        $check->execute();
        $existing = $check->get_result()->fetch_assoc();
        $check->close();

        if ($existing) {
            // Update: add quantity, update status, optionally update price/supplier/date/name
            $new_qty = $existing['quantity_kg'] + $quantity_kg;
            $status  = $new_qty <= 5 ? 'low_stock' : 'in_stock';

            $upd = $this->conn->prepare(
                "UPDATE feed_inventory 
                 SET quantity_kg = ?,
                     status = ?,
                     feed_name     = COALESCE(?, feed_name),
                     unit_price    = COALESCE(?, unit_price),
                     supplier_name = COALESCE(?, supplier_name),
                     purchase_date = COALESCE(?, purchase_date),
                     updated_at    = NOW()
                 WHERE id = ?"
            );
            if (!$upd) {
                throw new Exception("Prepare error: " . $this->conn->error);
            }
            $upd->bind_param("dsssssi", $new_qty, $status, $product_name, $unit_price, $supplier_name, $purchase_date, $existing['id']);
            if (!$upd->execute()) {
                throw new Exception("Error updating feed inventory: " . $this->conn->error);
            }
            $upd->close();
            return $existing['id'];
        }

        // Insert new row
        $feed_name = $product_name ?? 'Feed';
        $ins = $this->conn->prepare(
            "INSERT INTO feed_inventory (caretaker_id, feed_type, feed_name, quantity_kg, unit_price, supplier_name, purchase_date, expiry_date, status) 
             VALUES (?, ?, ?, ?, ?, ?, ?, ?, 'in_stock')"
        );
        if (!$ins) {
            throw new Exception("Prepare error: " . $this->conn->error);
        }
        $ins->bind_param("issddsss", $this->id, $feed_type, $feed_name, $quantity_kg, $unit_price, $supplier_name, $purchase_date, $expiry_date);
        if (!$ins->execute()) {
            throw new Exception("Error adding feed inventory: " . $this->conn->error);
        }
        $ins->close();
        return $this->conn->insert_id;
    }

    /**
     * Get total pig count (live from pig_details, not the stored counter)
     */
    public function getTotalPigCount()
    {
        $query = "SELECT COUNT(pd.id) as total
                  FROM pig_details pd
                  INNER JOIN pig_pins pp ON pd.cage_id = pp.id
                  WHERE pp.caretaker_id = ? AND pd.status = 'active'";
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

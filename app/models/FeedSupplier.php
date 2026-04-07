<?php

/**
 * FeedSupplier Model
 * Handles supplier database operations
 */

class FeedSupplier
{
    private $conn;
    private $table = 'suppliers';
    private $locationTable = 'locations';

    public $id;
    public $user_id;
    public $farm_name;
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

    /**
     * Find supplier by user ID
     */
    public function findByUserId($user_id)
    {
        $query = "SELECT s.*, l.street, l.barangay, l.municipality, l.city 
                  FROM " . $this->table . " s
                  LEFT JOIN " . $this->locationTable . " l ON s.location_id = l.location_id
                  WHERE s.user_id = ?";
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
            $this->location_id = $row['location_id'];
            $this->street = $row['street'] ?? null;
            $this->barangay = $row['barangay'] ?? null;
            $this->municipality = $row['municipality'] ?? null;
            $this->city = $row['city'] ?? 'Davao City';
            $this->contact_number = $row['contact_number'];

            $stmt->close();
            return true;
        }

        $stmt->close();
        return false;
    }

    /**
     * Create location record and return location_id
     */
    private function createLocation($street, $barangay, $municipality, $city)
    {
        $query = "INSERT INTO " . $this->locationTable . " (street, barangay, municipality, city) 
                 VALUES (?, ?, ?, ?)";
        $stmt = $this->conn->prepare($query);

        if (!$stmt) {
            throw new Exception("Prepare error: " . $this->conn->error);
        }

        $stmt->bind_param("ssss", $street, $barangay, $municipality, $city);
        
        if ($stmt->execute()) {
            $location_id = $this->conn->insert_id;
            $stmt->close();
            return $location_id;
        } else {
            $stmt->close();
            throw new Exception("Error creating location: " . $this->conn->error);
        }
    }

    /**
     * Create new supplier with address
     */
    public function create($user_id, $farm_name, $street, $barangay, $municipality, $city, $contact_number)
    {
        // First, create the location record
        $location_id = $this->createLocation($street, $barangay, $municipality, $city);

        // Then create the supplier record with location_id
        $query = "INSERT INTO " . $this->table . " (user_id, farm_name, location_id, contact_number) 
                 VALUES (?, ?, ?, ?)";
        $stmt = $this->conn->prepare($query);

        if (!$stmt) {
            throw new Exception("Prepare error: " . $this->conn->error);
        }

        $stmt->bind_param("isii", $user_id, $farm_name, $location_id, $contact_number);
        
        if ($stmt->execute()) {
            $this->id = $this->conn->insert_id;
            $this->user_id = $user_id;
            $this->farm_name = $farm_name;
            $this->location_id = $location_id;
            $this->street = $street;
            $this->barangay = $barangay;
            $this->municipality = $municipality;
            $this->city = $city;
            $this->contact_number = $contact_number;

            $stmt->close();
            return $this->id;
        } else {
            $stmt->close();
            throw new Exception("Error creating supplier: " . $this->conn->error);
        }
    }

    /**
     * Get all available feeds from pig caretakers
     */
    public function getAvailableFeeds()
    {
        $query = "SELECT fi.id, fi.feed_type, fi.quantity_kg, fi.unit_price, 
                         fi.supplier_name, fi.purchase_date, fi.expiry_date, 
                         fi.status, fi.created_at,
                         fi.caretaker_id,
                         pc.farm_name as caretaker_farm, pc.contact_number as caretaker_contact,
                         u.name as caretaker_name
                  FROM feed_inventory fi
                  JOIN pig_caretakers pc ON fi.caretaker_id = pc.id
                  JOIN users u ON pc.user_id = u.id
                  WHERE fi.status IN ('in_stock', 'low_stock')
                  ORDER BY fi.created_at DESC";
        
        $stmt = $this->conn->prepare($query);
        if (!$stmt) {
            throw new Exception("Prepare error: " . $this->conn->error);
        }

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
     * Get all available livestock (pigs) from caretakers
     */
    public function getAvailableLivestock()
    {
        $query = "SELECT pd.id, pd.breed, pd.age_months, pd.weight_kg, 
                         pd.health_status, pd.date_added, pd.notes,
                         pc.cage_number, 
                         pc_user.farm_name, pc_user.contact_number,
                         users.name as caretaker_name
                  FROM pig_details pd
                  JOIN pig_cages pc ON pd.cage_id = pc.id
                  JOIN pig_caretakers pc_user ON pc.caretaker_id = pc_user.id
                  JOIN users ON pc_user.user_id = users.id
                  WHERE pd.status = 'active' AND pd.health_status IN ('healthy', 'recovering')
                  ORDER BY pd.created_at DESC";
        
        $stmt = $this->conn->prepare($query);
        if (!$stmt) {
            throw new Exception("Prepare error: " . $this->conn->error);
        }

        $stmt->execute();
        $result = $stmt->get_result();
        $livestock = [];
        
        while ($row = $result->fetch_assoc()) {
            $livestock[] = $row;
        }
        
        $stmt->close();
        return $livestock;
    }

    /**
     * Get available feeds in same city as supplier
     */
    public function getFeedsInCity($city = null)
    {
        if (!$city && $this->city) {
            $city = $this->city;
        }

        $query = "SELECT fi.id, fi.feed_type, fi.quantity_kg, fi.unit_price, 
                         fi.supplier_name, fi.purchase_date, fi.expiry_date, 
                         fi.status, fi.created_at,
                         fi.caretaker_id,
                         pc.farm_name as caretaker_farm, pc.contact_number as caretaker_contact,
                         u.name as caretaker_name, pc.location
                  FROM feed_inventory fi
                  JOIN pig_caretakers pc ON fi.caretaker_id = pc.id
                  JOIN users u ON pc.user_id = u.id
                  WHERE fi.status IN ('in_stock', 'low_stock')
                  AND (pc.location = ? OR pc.location LIKE CONCAT('%', ?, '%'))
                  ORDER BY fi.created_at DESC";
        
        $stmt = $this->conn->prepare($query);
        if (!$stmt) {
            throw new Exception("Prepare error: " . $this->conn->error);
        }

        $stmt->bind_param("ss", $city, $city);
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
     * Get available livestock in same city as supplier
     */
    public function getLivestockInCity($city = null)
    {
        if (!$city && $this->city) {
            $city = $this->city;
        }

        $query = "SELECT pd.id, pd.breed, pd.age_months, pd.weight_kg, 
                         pd.health_status, pd.date_added, pd.notes,
                         pc.cage_number, 
                         pc_user.farm_name, pc_user.contact_number, pc_user.location,
                         u.name as caretaker_name
                  FROM pig_details pd
                  JOIN pig_cages pc ON pd.cage_id = pc.id
                  JOIN pig_caretakers pc_user ON pc.caretaker_id = pc_user.id
                  JOIN users u ON pc_user.user_id = u.id
                  WHERE pd.status = 'active' AND pd.health_status IN ('healthy', 'recovering')
                  AND pc_user.location LIKE CONCAT('%', ?, '%')
                  ORDER BY pd.date_added DESC";
        
        $stmt = $this->conn->prepare($query);
        if (!$stmt) {
            throw new Exception("Prepare error: " . $this->conn->error);
        }

        $stmt->bind_param("s", $city);
        $stmt->execute();
        $result = $stmt->get_result();
        $livestock = [];
        
        while ($row = $result->fetch_assoc()) {
            $livestock[] = $row;
        }
        
        $stmt->close();
        return $livestock;
    }

    /**
     * Get specific feed details with caretaker info
     */
    public function getFeedDetails($feed_id)
    {
        $query = "SELECT fi.*, pc.farm_name, pc.contact_number, u.name as caretaker_name,
                         u.email as caretaker_email
                  FROM feed_inventory fi
                  JOIN pig_caretakers pc ON fi.caretaker_id = pc.id
                  JOIN users u ON pc.user_id = u.id
                  WHERE fi.id = ?";
        
        $stmt = $this->conn->prepare($query);
        if (!$stmt) {
            throw new Exception("Prepare error: " . $this->conn->error);
        }

        $stmt->bind_param("i", $feed_id);
        $stmt->execute();
        $result = $stmt->get_result();
        
        if ($result->num_rows > 0) {
            $feed = $result->fetch_assoc();
            $stmt->close();
            return $feed;
        }
        
        $stmt->close();
        return null;
    }

    /**
     * Get specific livestock details with caretaker info
     */
    public function getLivestockDetails($livestock_id)
    {
        $query = "SELECT pd.*, pc.cage_number, pc_user.farm_name, pc_user.contact_number,
                         u.name as caretaker_name, u.email as caretaker_email
                  FROM pig_details pd
                  JOIN pig_cages pc ON pd.cage_id = pc.id
                  JOIN pig_caretakers pc_user ON pc.caretaker_id = pc_user.id
                  JOIN users u ON pc_user.user_id = u.id
                  WHERE pd.id = ?";
        
        $stmt = $this->conn->prepare($query);
        if (!$stmt) {
            throw new Exception("Prepare error: " . $this->conn->error);
        }

        $stmt->bind_param("i", $livestock_id);
        $stmt->execute();
        $result = $stmt->get_result();
        
        if ($result->num_rows > 0) {
            $livestock = $result->fetch_assoc();
            $stmt->close();
            return $livestock;
        }
        
        $stmt->close();
        return null;
    }
}
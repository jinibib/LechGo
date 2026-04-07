<?php

class LivestockOwner {
    private $conn;
    public $id;
    public $user_id;
    public $farm_name;
    public $location;
    public $contact_number;
    public $created_at;
    public $updated_at;

    public function __construct($conn) {
        $this->conn = $conn;
    }

    /**
     * Find livestock owner by user ID
     */
    public function findByUserId($user_id) {
        $query = "SELECT * FROM livestock_owners WHERE user_id = ?";
        $stmt = $this->conn->prepare($query);
        
        if (!$stmt) {
            return false; // Table may not exist yet
        }
        
        $stmt->bind_param('i', $user_id);
        $stmt->execute();
        $result = $stmt->get_result();
        
        if ($result->num_rows > 0) {
            $data = $result->fetch_assoc();
            $this->id = $data['id'];
            $this->user_id = $data['user_id'];
            $this->farm_name = $data['farm_name'];
            $this->location = $data['location'];
            $this->contact_number = $data['contact_number'];
            $this->created_at = $data['created_at'];
            $this->updated_at = $data['updated_at'];
            $stmt->close();
            return true;
        }
        
        $stmt->close();
        return false;
    }

    /**
     * Find livestock owner by ID
     */
    public function findById($id) {
        $query = "SELECT * FROM livestock_owners WHERE id = ?";
        $stmt = $this->conn->prepare($query);
        
        if (!$stmt) {
            return false; // Table may not exist yet
        }
        
        $stmt->bind_param('i', $id);
        $stmt->execute();
        $result = $stmt->get_result();
        
        if ($result->num_rows > 0) {
            $data = $result->fetch_assoc();
            $this->id = $data['id'];
            $this->user_id = $data['user_id'];
            $this->farm_name = $data['farm_name'];
            $this->location = $data['location'];
            $this->contact_number = $data['contact_number'];
            $this->created_at = $data['created_at'];
            $this->updated_at = $data['updated_at'];
            $stmt->close();
            return true;
        }
        
        $stmt->close();
        return false;
    }

    /**
     * Get all assigned pig caretakers
     */
    public function getCaretakers() {
        $query = "SELECT pc.*, u.name, u.email 
                  FROM pig_caretakers pc
                  JOIN users u ON pc.user_id = u.id
                  WHERE pc.livestock_owner_id = ?
                  ORDER BY pc.full_name";
        
        $stmt = $this->conn->prepare($query);
        if (!$stmt) {
            return [];
        }
        
        $stmt->bind_param('i', $this->id);
        $stmt->execute();
        $result = $stmt->get_result();
        
        $caretakers = [];
        while ($row = $result->fetch_assoc()) {
            $caretakers[] = $row;
        }
        
        $stmt->close();
        return $caretakers;
    }

    /**
     * Get all orders placed by this livestock owner
     */
    public function getOrders() {
        $query = "SELECT fo.*, fs.name AS supplier_name, 
                         COUNT(foi.id) AS item_count
                  FROM feed_orders fo
                  LEFT JOIN feed_suppliers fs ON fo.supplier_id = fs.id
                  LEFT JOIN feed_order_items foi ON fo.id = foi.order_id
                  WHERE fo.livestock_owner_id = ?
                  GROUP BY fo.id
                  ORDER BY fo.created_at DESC";
        
        $stmt = $this->conn->prepare($query);
        if (!$stmt) {
            return [];
        }
        
        $stmt->bind_param('i', $this->id);
        $stmt->execute();
        $result = $stmt->get_result();
        
        $orders = [];
        while ($row = $result->fetch_assoc()) {
            $orders[] = $row;
        }
        
        $stmt->close();
        return $orders;
    }

    /**
     * Get reports from assigned caretakers
     */
    public function getCaretakerReports() {
        $query = "SELECT cr.*, pc.full_name AS caretaker_name, pc.location,
                         u.email AS caretaker_email
                  FROM caretaker_reports cr
                  JOIN pig_caretakers pc ON cr.caretaker_id = pc.id
                  JOIN users u ON pc.user_id = u.id
                  WHERE pc.livestock_owner_id = ?
                  ORDER BY cr.created_at DESC
                  LIMIT 50";
        
        $stmt = $this->conn->prepare($query);
        if (!$stmt) {
            return [];
        }
        
        $stmt->bind_param('i', $this->id);
        $stmt->execute();
        $result = $stmt->get_result();
        
        $reports = [];
        while ($row = $result->fetch_assoc()) {
            $reports[] = $row;
        }
        
        $stmt->close();
        return $reports;
    }

    /**
     * Create a new livestock owner record
     */
    public function create($user_id, $farm_name, $location, $contact_number) {
        $query = "INSERT INTO livestock_owners (user_id, farm_name, location, contact_number, created_at, updated_at)
                  VALUES (?, ?, ?, ?, NOW(), NOW())";
        
        $stmt = $this->conn->prepare($query);
        if (!$stmt) {
            throw new Exception('Database error: Cannot create livestock owner');
        }
        
        $stmt->bind_param('isss', $user_id, $farm_name, $location, $contact_number);
        
        if ($stmt->execute()) {
            $this->id = $this->conn->insert_id;
            $this->user_id = $user_id;
            $this->farm_name = $farm_name;
            $this->location = $location;
            $this->contact_number = $contact_number;
            $stmt->close();
            return true;
        }
        
        $stmt->close();
        return false;
    }

    /**
     * Update livestock owner information
     */
    public function update($farm_name, $location, $contact_number) {
        $query = "UPDATE livestock_owners 
                  SET farm_name = ?, location = ?, contact_number = ?, updated_at = NOW()
                  WHERE id = ?";
        
        $stmt = $this->conn->prepare($query);
        if (!$stmt) {
            throw new Exception('Database error: Cannot update livestock owner');
        }
        
        $stmt->bind_param('sssi', $farm_name, $location, $contact_number, $this->id);
        
        if ($stmt->execute()) {
            $this->farm_name = $farm_name;
            $this->location = $location;
            $this->contact_number = $contact_number;
            $stmt->close();
            return true;
        }
        
        $stmt->close();
        return false;
    }
}

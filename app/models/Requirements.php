<?php

/**
 * Requirements Model
 * Handles piggery application document requirements
 */

class Requirements
{
    private $conn;
    private $table = 'requirements';

    public $id;
    public $user_id;
    public $application_id;
    public $farm_name;
    public $farm_location;
    public $business_registration;
    public $barangay_clearance;
    public $cpdo_clearance;
    public $denr_ecc;
    public $cvo_sanitary_permit;
    public $business_permit;
    public $status;
    public $remarks;
    public $reviewed_by;
    public $reviewed_at;
    public $created_at;
    public $updated_at;

    public function __construct($db)
    {
        $this->conn = $db;
    }

    /**
     * Create a new requirement record with uploaded documents
     * NOTE: Some document fields may be NULL during testing
     */
    public function create($user_id, $application_id, $farm_name, $farm_location, $documents)
    {
        $query = "INSERT INTO " . $this->table . " 
                  (user_id, application_id, farm_name, farm_location, 
                   business_registration, barangay_clearance, cpdo_clearance, 
                   denr_ecc, cvo_sanitary_permit, business_permit, status) 
                  VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 'pending')";
        
        $stmt = $this->conn->prepare($query);
        if (!$stmt) {
            throw new Exception("Database error: " . $this->conn->error);
        }

        // FIX: Extract array values to variables FIRST (required for bind_param by reference)
        // Allow NULL values for documents not uploaded yet
        $business_reg = $documents['business_registration'] ?? null;
        $barangay_clear = $documents['barangay_clearance'] ?? null;
        $cpdo_clear = $documents['cpdo_clearance'] ?? null;
        $denr = $documents['denr_ecc'] ?? null;
        $cvo_permit = $documents['cvo_sanitary_permit'] ?? null;
        $business_perm = $documents['business_permit'] ?? null;

        $stmt->bind_param("iissssssss", 
            $user_id,
            $application_id,
            $farm_name,
            $farm_location,
            $business_reg,
            $barangay_clear,
            $cpdo_clear,
            $denr,
            $cvo_permit,
            $business_perm
        );
        
        if ($stmt->execute()) {
            $this->id = $this->conn->insert_id;
            $stmt->close();
            return $this->id;
        } else {
            $error = $stmt->error;
            $stmt->close();
            throw new Exception("Failed to create requirements record: " . $error);
        }
    }

    /**
     * Get requirements by user ID
     */
    public function findByUserId($user_id)
    {
        $query = "SELECT * FROM " . $this->table . " 
                  WHERE user_id = ? 
                  ORDER BY created_at DESC 
                  LIMIT 1";
        
        $stmt = $this->conn->prepare($query);
        if (!$stmt) {
            return false;
        }

        $stmt->bind_param("i", $user_id);
        $stmt->execute();
        $result = $stmt->get_result();

        if ($row = $result->fetch_assoc()) {
            $this->setProperties($row);
            $stmt->close();
            return true;
        }

        $stmt->close();
        return false;
    }

    /**
     * Get requirements by application ID
     */
    public function findByApplicationId($application_id)
    {
        $query = "SELECT * FROM " . $this->table . " 
                  WHERE application_id = ?";
        
        $stmt = $this->conn->prepare($query);
        if (!$stmt) {
            return false;
        }

        $stmt->bind_param("i", $application_id);
        $stmt->execute();
        $result = $stmt->get_result();

        if ($row = $result->fetch_assoc()) {
            $this->setProperties($row);
            $stmt->close();
            return true;
        }

        $stmt->close();
        return false;
    }

    /**
     * Get all pending requirements
     */
    public function getAllPending()
    {
        $query = "SELECT r.*, u.name as user_name, u.email as user_email
                  FROM " . $this->table . " r
                  JOIN users u ON r.user_id = u.id
                  WHERE r.status = 'pending'
                  ORDER BY r.created_at DESC";
        
        $result = $this->conn->query($query);
        $requirements = [];

        if ($result && $result->num_rows > 0) {
            while ($row = $result->fetch_assoc()) {
                $requirements[] = $row;
            }
        }

        return $requirements;
    }

    /**
     * Approve requirements
     */
    public function approve($requirement_id, $reviewed_by, $remarks = null)
    {
        $query = "UPDATE " . $this->table . " 
                  SET status = 'approved', 
                      reviewed_by = ?, 
                      reviewed_at = NOW(),
                      remarks = ?
                  WHERE id = ?";
        
        $stmt = $this->conn->prepare($query);
        if (!$stmt) {
            throw new Exception("Database error: " . $this->conn->error);
        }

        $stmt->bind_param("isi", $reviewed_by, $remarks, $requirement_id);
        
        if ($stmt->execute()) {
            $stmt->close();
            return true;
        } else {
            $error = $stmt->error;
            $stmt->close();
            throw new Exception("Failed to approve requirements: " . $error);
        }
    }

    /**
     * Reject requirements
     */
    public function reject($requirement_id, $reviewed_by, $remarks)
    {
        $query = "UPDATE " . $this->table . " 
                  SET status = 'rejected', 
                      reviewed_by = ?, 
                      reviewed_at = NOW(),
                      remarks = ?
                  WHERE id = ?";
        
        $stmt = $this->conn->prepare($query);
        if (!$stmt) {
            throw new Exception("Database error: " . $this->conn->error);
        }

        $stmt->bind_param("isi", $reviewed_by, $remarks, $requirement_id);
        
        if ($stmt->execute()) {
            $stmt->close();
            return true;
        } else {
            $error = $stmt->error;
            $stmt->close();
            throw new Exception("Failed to reject requirements: " . $error);
        }
    }

    /**
     * Set object properties from database row
     */
    private function setProperties($row)
    {
        $this->id = $row['id'];
        $this->user_id = $row['user_id'];
        $this->application_id = $row['application_id'];
        $this->farm_name = $row['farm_name'];
        $this->farm_location = $row['farm_location'];
        $this->business_registration = $row['business_registration'];
        $this->barangay_clearance = $row['barangay_clearance'];
        $this->cpdo_clearance = $row['cpdo_clearance'];
        $this->denr_ecc = $row['denr_ecc'];
        $this->cvo_sanitary_permit = $row['cvo_sanitary_permit'];
        $this->business_permit = $row['business_permit'];
        $this->status = $row['status'];
        $this->remarks = $row['remarks'];
        $this->reviewed_by = $row['reviewed_by'];
        $this->reviewed_at = $row['reviewed_at'];
        $this->created_at = $row['created_at'];
        $this->updated_at = $row['updated_at'];
    }

    /**
     * Get all document paths as array
     */
    public function getDocuments()
    {
        return [
            'business_registration' => $this->business_registration,
            'barangay_clearance' => $this->barangay_clearance,
            'cpdo_clearance' => $this->cpdo_clearance,
            'denr_ecc' => $this->denr_ecc,
            'cvo_sanitary_permit' => $this->cvo_sanitary_permit,
            'business_permit' => $this->business_permit
        ];
    }
}

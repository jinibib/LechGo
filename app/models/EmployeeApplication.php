<?php

/**
 * EmployeeApplication Model
 * Handles employee applications to piggery farms
 */

class EmployeeApplication
{
    private $conn;
    private $table = 'employee_applications';

    public $id;
    public $applicant_user_id;
    public $livestock_owner_id;
    public $position;
    public $cover_letter;
    public $status;
    public $reviewed_at;
    public $applied_at;

    public function __construct($db)
    {
        $this->conn = $db;
    }

    /**
     * Create a new employee application
     */
    public function create($applicant_user_id, $livestock_owner_id, $position, $cover_letter = null)
    {
        // Check if user already has a pending application to this owner
        if ($this->hasPendingApplicationToOwner($applicant_user_id, $livestock_owner_id)) {
            throw new Exception("You already have a pending application to this piggery");
        }

        // Check if user is already employed
        if ($this->isAlreadyEmployed($applicant_user_id)) {
            throw new Exception("You are already employed. Cannot apply to multiple piggeries.");
        }

        $query = "INSERT INTO " . $this->table . " 
                  (applicant_user_id, livestock_owner_id, position, cover_letter, status) 
                  VALUES (?, ?, ?, ?, 'pending')";
        
        $stmt = $this->conn->prepare($query);
        if (!$stmt) {
            throw new Exception("Database error: " . $this->conn->error);
        }

        $stmt->bind_param("iiss", $applicant_user_id, $livestock_owner_id, $position, $cover_letter);
        
        if ($stmt->execute()) {
            $this->id = $this->conn->insert_id;
            $stmt->close();
            return $this->id;
        } else {
            $error = $stmt->error;
            $stmt->close();
            throw new Exception("Failed to create application: " . $error);
        }
    }

    /**
     * Check if user has pending application to specific owner
     */
    public function hasPendingApplicationToOwner($applicant_user_id, $livestock_owner_id)
    {
        $query = "SELECT COUNT(*) as count FROM " . $this->table . " 
                  WHERE applicant_user_id = ? AND livestock_owner_id = ? AND status = 'pending'";
        
        $stmt = $this->conn->prepare($query);
        if (!$stmt) {
            return false;
        }

        $stmt->bind_param("ii", $applicant_user_id, $livestock_owner_id);
        $stmt->execute();
        $result = $stmt->get_result();
        $row = $result->fetch_assoc();
        $stmt->close();

        return $row['count'] > 0;
    }

    /**
     * Check if user is already employed (has active assignment)
     */
    public function isAlreadyEmployed($applicant_user_id)
    {
        $query = "SELECT COUNT(*) as count FROM employee_assignments 
                  WHERE employee_user_id = ? AND status = 'active'";
        
        $stmt = $this->conn->prepare($query);
        if (!$stmt) {
            return false;
        }

        $stmt->bind_param("i", $applicant_user_id);
        $stmt->execute();
        $result = $stmt->get_result();
        $row = $result->fetch_assoc();
        $stmt->close();

        return $row['count'] > 0;
    }

    /**
     * Get all applications for a livestock owner
     */
    public function getApplicationsForOwner($livestock_owner_id)
    {
        $query = "SELECT ea.*, u.name as applicant_name, u.email as applicant_email, u.phone as applicant_phone
                  FROM " . $this->table . " ea
                  JOIN users u ON ea.applicant_user_id = u.id
                  WHERE ea.livestock_owner_id = ?
                  ORDER BY 
                    CASE ea.status 
                      WHEN 'pending' THEN 1 
                      WHEN 'approved' THEN 2 
                      WHEN 'rejected' THEN 3 
                    END,
                    ea.applied_at DESC";
        
        $stmt = $this->conn->prepare($query);
        if (!$stmt) {
            return [];
        }

        $stmt->bind_param("i", $livestock_owner_id);
        $stmt->execute();
        $result = $stmt->get_result();
        
        $applications = [];
        while ($row = $result->fetch_assoc()) {
            $applications[] = $row;
        }
        
        $stmt->close();
        return $applications;
    }

    /**
     * Get all applications by a user
     */
    public function getApplicationsByUser($applicant_user_id)
    {
        $query = "SELECT ea.*, lo.farm_name, lo.location, u.name as owner_name
                  FROM " . $this->table . " ea
                  JOIN livestock_owners lo ON ea.livestock_owner_id = lo.id
                  JOIN users u ON lo.user_id = u.id
                  WHERE ea.applicant_user_id = ?
                  ORDER BY ea.applied_at DESC";
        
        $stmt = $this->conn->prepare($query);
        if (!$stmt) {
            return [];
        }

        $stmt->bind_param("i", $applicant_user_id);
        $stmt->execute();
        $result = $stmt->get_result();
        
        $applications = [];
        while ($row = $result->fetch_assoc()) {
            $applications[] = $row;
        }
        
        $stmt->close();
        return $applications;
    }

    /**
     * Get pending owner IDs that user has applied to
     */
    public function getPendingOwnerIdsByUser($applicant_user_id)
    {
        $query = "SELECT livestock_owner_id FROM " . $this->table . " 
                  WHERE applicant_user_id = ? AND status = 'pending'";
        
        $stmt = $this->conn->prepare($query);
        if (!$stmt) {
            return [];
        }

        $stmt->bind_param("i", $applicant_user_id);
        $stmt->execute();
        $result = $stmt->get_result();
        
        $owner_ids = [];
        while ($row = $result->fetch_assoc()) {
            $owner_ids[] = $row['livestock_owner_id'];
        }
        
        $stmt->close();
        return $owner_ids;
    }

    /**
     * Approve application and create employee assignment
     */
    public function approve($application_id, $livestock_owner_id)
    {
        // Get application details
        $query = "SELECT * FROM " . $this->table . " 
                  WHERE id = ? AND livestock_owner_id = ? AND status = 'pending'";
        
        $stmt = $this->conn->prepare($query);
        $stmt->bind_param("ii", $application_id, $livestock_owner_id);
        $stmt->execute();
        $result = $stmt->get_result();
        
        if ($result->num_rows == 0) {
            $stmt->close();
            throw new Exception("Application not found or already processed");
        }

        $application = $result->fetch_assoc();
        $stmt->close();

        // Check if applicant is already employed elsewhere
        if ($this->isAlreadyEmployed($application['applicant_user_id'])) {
            throw new Exception("This applicant is already employed elsewhere");
        }

        // Start transaction
        $this->conn->begin_transaction();

        try {
            // Update application status
            $query = "UPDATE " . $this->table . " 
                      SET status = 'approved', reviewed_at = NOW() 
                      WHERE id = ?";
            
            $stmt = $this->conn->prepare($query);
            $stmt->bind_param("i", $application_id);
            if (!$stmt->execute()) {
                throw new Exception("Failed to update application status: " . $stmt->error);
            }
            $stmt->close();

            // Create employee assignment - NOTE: This will start a nested transaction
            // We need to handle this carefully
            require_once __DIR__ . '/EmployeeAssignment.php';
            $employeeAssignment = new EmployeeAssignment($this->conn);
            
            try {
                $employeeAssignment->assign(
                    $livestock_owner_id,
                    $application['applicant_user_id'],
                    $application['position']
                );
            } catch (Exception $e) {
                // Assignment failed, rollback and re-throw
                throw new Exception("Failed to create employee assignment: " . $e->getMessage());
            }

            // Reject all other pending applications from this user
            $query = "UPDATE " . $this->table . " 
                      SET status = 'rejected', reviewed_at = NOW() 
                      WHERE applicant_user_id = ? AND status = 'pending' AND id != ?";
            
            $stmt = $this->conn->prepare($query);
            $stmt->bind_param("ii", $application['applicant_user_id'], $application_id);
            if (!$stmt->execute()) {
                throw new Exception("Failed to reject other applications: " . $stmt->error);
            }
            $stmt->close();

            $this->conn->commit();
            return true;

        } catch (Exception $e) {
            $this->conn->rollback();
            error_log("Application approval failed: " . $e->getMessage());
            throw $e;
        }
    }

    /**
     * Reject application
     */
    public function reject($application_id, $livestock_owner_id, $reason = null)
    {
        // Verify ownership
        $query = "SELECT * FROM " . $this->table . " 
                  WHERE id = ? AND livestock_owner_id = ? AND status = 'pending'";
        
        $stmt = $this->conn->prepare($query);
        $stmt->bind_param("ii", $application_id, $livestock_owner_id);
        $stmt->execute();
        $result = $stmt->get_result();
        
        if ($result->num_rows == 0) {
            $stmt->close();
            throw new Exception("Application not found or already processed");
        }
        $stmt->close();

        // Update application status
        $query = "UPDATE " . $this->table . " 
                  SET status = 'rejected', reviewed_at = NOW(), rejection_reason = ? 
                  WHERE id = ?";
        
        $stmt = $this->conn->prepare($query);
        $stmt->bind_param("si", $reason, $application_id);
        
        if ($stmt->execute()) {
            $stmt->close();
            return true;
        } else {
            $error = $stmt->error;
            $stmt->close();
            throw new Exception("Failed to reject application: " . $error);
        }
    }

    /**
     * Get all livestock owners (for application selection)
     */
    public function getAllLivestockOwners()
    {
        $query = "SELECT lo.id as owner_id, lo.farm_name, lo.location, lo.contact_number, 
                         u.id as user_id, u.name as owner_name
                  FROM livestock_owners lo
                  JOIN users u ON lo.user_id = u.id
                  WHERE u.role = 'livestock_owner'
                  ORDER BY lo.farm_name ASC";
        
        $result = $this->conn->query($query);
        $owners = [];

        if ($result && $result->num_rows > 0) {
            while ($row = $result->fetch_assoc()) {
                $owners[] = $row;
            }
        }

        return $owners;
    }
}

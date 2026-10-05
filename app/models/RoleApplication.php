<?php

/**
 * RoleApplication Model
 * Handles user role application operations
 */

class RoleApplication
{
    private $conn;
    private $table = 'role_applications';
    public $id;
    public $user_id;
    public $application_type;
    public $status;
    public $farm_name;
    public $farm_location;
    public $proof_documents;
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
     * Create a new role application
     */
    public function create($user_id, $application_type, $farm_name = null, $farm_location = null, $proof_documents = null)
    {
        // Check if user already has a pending application
        if ($this->hasPendingApplication($user_id)) {
            throw new Exception("You already have a pending application. Please wait for review.");
        }

        $query = "INSERT INTO " . $this->table . " 
                  (user_id, application_type, farm_name, farm_location, proof_documents, status) 
                  VALUES (?, ?, ?, ?, ?, 'pending')";
        
        $stmt = $this->conn->prepare($query);
        if (!$stmt) {
            throw new Exception("Database error: " . $this->conn->error);
        }

        $documents_json = json_encode($proof_documents);
        $stmt->bind_param("issss", $user_id, $application_type, $farm_name, $farm_location, $documents_json);
        
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
     * Check if user has a pending application
     */
    public function hasPendingApplication($user_id)
    {
        $query = "SELECT COUNT(*) as count FROM " . $this->table . " 
                  WHERE user_id = ? AND status = 'pending'";
        $stmt = $this->conn->prepare($query);
        
        if (!$stmt) {
            return false;
        }

        $stmt->bind_param("i", $user_id);
        $stmt->execute();
        $result = $stmt->get_result();
        $row = $result->fetch_assoc();
        $stmt->close();

        return $row['count'] > 0;
    }

    /**
     * Get all pending applications (for admin)
     */
    public function getAllPending()
    {
        $query = "SELECT ra.*, u.name as user_name, u.email as user_email, u.phone as user_phone,
                         r.business_registration, r.barangay_clearance, r.cpdo_clearance,
                         r.denr_ecc, r.cvo_sanitary_permit, r.business_permit
                  FROM " . $this->table . " ra
                  JOIN users u ON ra.user_id = u.id
                  LEFT JOIN requirements r ON ra.id = r.application_id
                  WHERE ra.status = 'pending'
                  ORDER BY ra.created_at DESC";
        
        $result = $this->conn->query($query);
        $applications = [];

        if ($result && $result->num_rows > 0) {
            while ($row = $result->fetch_assoc()) {
                // Build proof_documents array from requirements table
                $proof_documents = [];
                if (!empty($row['business_registration'])) {
                    $proof_documents[] = $row['business_registration'];
                }
                if (!empty($row['barangay_clearance'])) {
                    $proof_documents[] = $row['barangay_clearance'];
                }
                if (!empty($row['cpdo_clearance'])) {
                    $proof_documents[] = $row['cpdo_clearance'];
                }
                if (!empty($row['denr_ecc'])) {
                    $proof_documents[] = $row['denr_ecc'];
                }
                if (!empty($row['cvo_sanitary_permit'])) {
                    $proof_documents[] = $row['cvo_sanitary_permit'];
                }
                if (!empty($row['business_permit'])) {
                    $proof_documents[] = $row['business_permit'];
                }
                
                $row['proof_documents'] = $proof_documents;
                
                // Remove requirement fields from main array to avoid confusion
                unset($row['business_registration'], $row['barangay_clearance'], $row['cpdo_clearance']);
                unset($row['denr_ecc'], $row['cvo_sanitary_permit'], $row['business_permit']);
                
                $applications[] = $row;
            }
        }

        return $applications;
    }

    /**
     * Get application by ID
     */
    public function findById($id)
    {
        $query = "SELECT ra.*, u.name as user_name, u.email as user_email, u.phone as user_phone
                  FROM " . $this->table . " ra
                  JOIN users u ON ra.user_id = u.id
                  WHERE ra.id = ?";
        
        $stmt = $this->conn->prepare($query);
        if (!$stmt) {
            return false;
        }

        $stmt->bind_param("i", $id);
        $stmt->execute();
        $result = $stmt->get_result();

        if ($result->num_rows > 0) {
            $row = $result->fetch_assoc();
            $row['proof_documents'] = json_decode($row['proof_documents'], true);
            
            $this->id = $row['id'];
            $this->user_id = $row['user_id'];
            $this->application_type = $row['application_type'];
            $this->status = $row['status'];
            $this->farm_name = $row['farm_name'];
            $this->farm_location = $row['farm_location'];
            $this->proof_documents = $row['proof_documents'];
            $this->remarks = $row['remarks'];
            $this->reviewed_by = $row['reviewed_by'];
            $this->reviewed_at = $row['reviewed_at'];
            $this->created_at = $row['created_at'];
            $this->updated_at = $row['updated_at'];
            
            $stmt->close();
            return $row;
        }

        $stmt->close();
        return false;
    }

    /**
     * Approve application
     */
    public function approve($application_id, $admin_user_id, $remarks = null)
    {
        // Get application details
        $app = $this->findById($application_id);
        if (!$app) {
            throw new Exception("Application not found");
        }

        error_log("Approving application ID: $application_id for user ID: {$app['user_id']}");

        // Start transaction
        $this->conn->begin_transaction();

        try {
            // Update application status
            $query = "UPDATE " . $this->table . " 
                      SET status = 'approved', reviewed_by = ?, reviewed_at = NOW(), remarks = ?
                      WHERE id = ?";
            
            $stmt = $this->conn->prepare($query);
            if (!$stmt) {
                throw new Exception("Database error: " . $this->conn->error);
            }

            $stmt->bind_param("isi", $admin_user_id, $remarks, $application_id);
            if (!$stmt->execute()) {
                throw new Exception("Failed to update application: " . $stmt->error);
            }
            error_log("Application status updated to approved");
            $stmt->close();

            // Update user role based on application type
            $new_role = ($app['application_type'] === 'piggery_owner') ? 'livestock_owner' : 'customer';
            
            error_log("Updating user {$app['user_id']} role to: $new_role");
            
            $query = "UPDATE users SET role = ? WHERE id = ?";
            $stmt = $this->conn->prepare($query);
            if (!$stmt) {
                throw new Exception("Database error: " . $this->conn->error);
            }

            $stmt->bind_param("si", $new_role, $app['user_id']);
            if (!$stmt->execute()) {
                throw new Exception("Failed to update user role: " . $stmt->error);
            }
            
            $affected_rows = $stmt->affected_rows;
            error_log("User role update affected rows: $affected_rows");
            $stmt->close();
            
            if ($affected_rows === 0) {
                error_log("WARNING: No rows affected when updating user role. User might not exist.");
            }

            // Verify the update
            $verify_query = "SELECT role FROM users WHERE id = ?";
            $verify_stmt = $this->conn->prepare($verify_query);
            $verify_stmt->bind_param("i", $app['user_id']);
            $verify_stmt->execute();
            $verify_result = $verify_stmt->get_result();
            $verify_data = $verify_result->fetch_assoc();
            $verify_stmt->close();
            
            error_log("User role after update: " . ($verify_data['role'] ?? 'NOT FOUND'));

            // If piggery owner, create livestock_owner record
            if ($app['application_type'] === 'piggery_owner') {
                error_log("Creating livestock_owner record");
                
                // Check if livestock_owners table exists
                $check_table = "SHOW TABLES LIKE 'livestock_owners'";
                $result = $this->conn->query($check_table);
                
                if ($result && $result->num_rows > 0) {
                    // Load LivestockOwner model
                    $owner_file = __DIR__ . '/LivestockOwner.php';
                    if (file_exists($owner_file)) {
                        require_once $owner_file;
                        $owner = new LivestockOwner($this->conn);
                        
                        // Extract phone from user
                        $user_query = "SELECT phone FROM users WHERE id = ?";
                        $user_stmt = $this->conn->prepare($user_query);
                        $user_stmt->bind_param("i", $app['user_id']);
                        $user_stmt->execute();
                        $user_result = $user_stmt->get_result();
                        $user_data = $user_result->fetch_assoc();
                        $user_stmt->close();

                        $owner->create(
                            $app['user_id'],
                            $app['farm_name'],
                            $app['farm_location'],
                            $user_data['phone'] ?? ''
                        );
                        error_log("Livestock owner record created");
                    } else {
                        error_log("LivestockOwner model file not found: $owner_file");
                    }
                } else {
                    error_log("livestock_owners table does not exist");
                }
            }

            $this->conn->commit();
            error_log("Transaction committed successfully");
            return true;

        } catch (Exception $e) {
            $this->conn->rollback();
            error_log("Approval error (rolled back): " . $e->getMessage());
            throw $e;
        }
    }

    /**
     * Reject application
     */
    public function reject($application_id, $admin_user_id, $remarks)
    {
        $query = "UPDATE " . $this->table . " 
                  SET status = 'rejected', reviewed_by = ?, reviewed_at = NOW(), remarks = ?
                  WHERE id = ?";
        
        $stmt = $this->conn->prepare($query);
        if (!$stmt) {
            throw new Exception("Database error: " . $this->conn->error);
        }

        $stmt->bind_param("isi", $admin_user_id, $remarks, $application_id);
        
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
     * Get user's application history
     */
    public function getUserApplications($user_id)
    {
        $query = "SELECT * FROM " . $this->table . " 
                  WHERE user_id = ? 
                  ORDER BY created_at DESC";
        
        $stmt = $this->conn->prepare($query);
        if (!$stmt) {
            return [];
        }

        $stmt->bind_param("i", $user_id);
        $stmt->execute();
        $result = $stmt->get_result();
        
        $applications = [];
        while ($row = $result->fetch_assoc()) {
            $row['proof_documents'] = json_decode($row['proof_documents'], true);
            $applications[] = $row;
        }
        
        $stmt->close();
        return $applications;
    }
}

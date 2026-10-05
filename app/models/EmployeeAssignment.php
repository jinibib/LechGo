<?php

/**
 * EmployeeAssignment Model
 * Handles employee assignments for livestock owners
 */

class EmployeeAssignment
{
    private $conn;
    private $table = 'employee_assignments';

    public $id;
    public $livestock_owner_id;
    public $employee_user_id;
    public $assigned_role;
    public $status;
    public $assigned_at;
    public $updated_at;

    public function __construct($db)
    {
        $this->conn = $db;
    }

    /**
     * Assign an employee to a livestock owner
     * Note: Does NOT start its own transaction - caller must handle transactions
     */
    public function assign($livestock_owner_id, $employee_user_id, $assigned_role)
    {
        // Validate role
        $valid_roles = ['pig_caretaker', 'lechonero', 'pig_slaughter', 'logistics'];
        if (!in_array($assigned_role, $valid_roles)) {
            throw new Exception("Invalid role: " . $assigned_role);
        }

        // Check if employee already assigned to this owner
        if ($this->isAlreadyAssigned($livestock_owner_id, $employee_user_id)) {
            throw new Exception("This employee is already assigned to you");
        }

        // Insert assignment record
        $query = "INSERT INTO " . $this->table . " 
                  (livestock_owner_id, employee_user_id, assigned_role, status) 
                  VALUES (?, ?, ?, 'active')";
        
        $stmt = $this->conn->prepare($query);
        if (!$stmt) {
            throw new Exception("Database error preparing assignment: " . $this->conn->error);
        }

        $stmt->bind_param("iis", $livestock_owner_id, $employee_user_id, $assigned_role);
        if (!$stmt->execute()) {
            $error = $stmt->error;
            $stmt->close();
            throw new Exception("Failed to create assignment: " . $error);
        }
        $this->id = $this->conn->insert_id;
        $stmt->close();

        // Update user's role
        $query = "UPDATE users SET role = ? WHERE id = ?";
        $stmt = $this->conn->prepare($query);
        if (!$stmt) {
            throw new Exception("Database error preparing user update: " . $this->conn->error);
        }

        $stmt->bind_param("si", $assigned_role, $employee_user_id);
        if (!$stmt->execute()) {
            $error = $stmt->error;
            $stmt->close();
            throw new Exception("Failed to update user role: " . $error);
        }
        $affected = $stmt->affected_rows;
        $stmt->close();
        
        if ($affected === 0) {
            throw new Exception("User not found or role not updated (user_id: " . $employee_user_id . ")");
        }

        // Create role-specific record
        $this->createRoleSpecificRecord($employee_user_id, $assigned_role, $livestock_owner_id);

        return $this->id;
    }

    /**
     * Create role-specific record (pig_caretaker, etc.)
     */
    private function createRoleSpecificRecord($user_id, $role, $livestock_owner_id)
    {
        // Get user info
        $query = "SELECT name, phone FROM users WHERE id = ?";
        $stmt = $this->conn->prepare($query);
        $stmt->bind_param("i", $user_id);
        $stmt->execute();
        $result = $stmt->get_result();
        $user = $result->fetch_assoc();
        $stmt->close();

        // Get owner info (farm name and location) using livestock_owner_id from livestock_owners table
        $query = "SELECT farm_name, location, user_id FROM livestock_owners WHERE id = ?";
        $stmt = $this->conn->prepare($query);
        $stmt->bind_param("i", $livestock_owner_id);
        $stmt->execute();
        $result = $stmt->get_result();
        $owner = $result->fetch_assoc();
        $stmt->close();

        if (!$owner) {
            throw new Exception("Livestock owner not found (id: " . $livestock_owner_id . ")");
        }

        switch ($role) {
            case 'pig_caretaker':
                // Check if record already exists
                $check_query = "SELECT id FROM pig_caretakers WHERE user_id = ?";
                $check_stmt = $this->conn->prepare($check_query);
                $check_stmt->bind_param("i", $user_id);
                $check_stmt->execute();
                $check_result = $check_stmt->get_result();
                
                if ($check_result->num_rows == 0) {
                    // Try to insert into pig_caretakers table
                    // Note: This table has foreign key constraint on livestock_owner_id
                    try {
                        $query = "INSERT INTO pig_caretakers (user_id, livestock_owner_id, farm_name, full_name, location, contact_number) 
                                  VALUES (?, ?, ?, ?, ?, ?)";
                        $stmt = $this->conn->prepare($query);
                        if (!$stmt) {
                            error_log("Failed to prepare pig_caretaker insert: " . $this->conn->error);
                        } else {
                            $stmt->bind_param("iissss", 
                                $user_id, 
                                $livestock_owner_id,
                                $owner['farm_name'], 
                                $user['name'], 
                                $owner['location'], 
                                $user['phone']
                            );
                            if (!$stmt->execute()) {
                                error_log("Failed to insert pig_caretaker (FK constraint): " . $stmt->error);
                                // Don't throw - the employee_assignment is more important
                            }
                            $stmt->close();
                        }
                    } catch (Exception $e) {
                        error_log("Exception inserting pig_caretaker: " . $e->getMessage());
                        // Don't throw - continue with assignment
                    }
                }
                $check_stmt->close();
                break;

            // Lechonero, pig_slaughter, logistics don't need special tables for now
            case 'lechonero':
            case 'pig_slaughter':
            case 'logistics':
                // These roles don't require additional profile tables
                break;
        }
    }

    /**
     * Check if employee is already assigned
     */
    public function isAlreadyAssigned($livestock_owner_id, $employee_user_id)
    {
        $query = "SELECT COUNT(*) as count FROM " . $this->table . " 
                  WHERE livestock_owner_id = ? AND employee_user_id = ? AND status = 'active'";
        
        $stmt = $this->conn->prepare($query);
        if (!$stmt) {
            return false;
        }

        $stmt->bind_param("ii", $livestock_owner_id, $employee_user_id);
        $stmt->execute();
        $result = $stmt->get_result();
        $row = $result->fetch_assoc();
        $stmt->close();

        return $row['count'] > 0;
    }

    /**
     * Get all employees assigned to a livestock owner
     */
    public function getOwnerEmployees($livestock_owner_id)
    {
        $query = "SELECT ea.*, u.name, u.email, u.phone 
                  FROM " . $this->table . " ea
                  JOIN users u ON ea.employee_user_id = u.id
                  WHERE ea.livestock_owner_id = ? AND ea.status = 'active'
                  ORDER BY ea.assigned_at DESC";
        
        $stmt = $this->conn->prepare($query);
        if (!$stmt) {
            return [];
        }

        $stmt->bind_param("i", $livestock_owner_id);
        $stmt->execute();
        $result = $stmt->get_result();
        
        $employees = [];
        while ($row = $result->fetch_assoc()) {
            $employees[] = $row;
        }
        
        $stmt->close();
        return $employees;
    }

    /**
     * Get all users without specific roles (available for assignment)
     */
    public function getAvailableUsers()
    {
        // Get users who are just 'customer' and have no pending applications
        $query = "SELECT u.id, u.name, u.email, u.phone, u.role
                  FROM users u
                  LEFT JOIN role_applications ra ON u.id = ra.user_id AND ra.status = 'pending'
                  WHERE u.role = 'customer' 
                  AND ra.id IS NULL
                  ORDER BY u.name ASC";
        
        $result = $this->conn->query($query);
        $users = [];

        if ($result && $result->num_rows > 0) {
            while ($row = $result->fetch_assoc()) {
                $users[] = $row;
            }
        }

        return $users;
    }

    /**
     * Remove/deactivate an employee assignment
     */
    public function remove($assignment_id, $livestock_owner_id)
    {
        // Verify ownership
        $query = "SELECT * FROM " . $this->table . " 
                  WHERE id = ? AND livestock_owner_id = ?";
        
        $stmt = $this->conn->prepare($query);
        $stmt->bind_param("ii", $assignment_id, $livestock_owner_id);
        $stmt->execute();
        $result = $stmt->get_result();
        
        if ($result->num_rows == 0) {
            $stmt->close();
            throw new Exception("Assignment not found or unauthorized");
        }

        $assignment = $result->fetch_assoc();
        $stmt->close();

        // Start transaction
        $this->conn->begin_transaction();

        try {
            // Deactivate assignment
            $query = "UPDATE " . $this->table . " SET status = 'inactive' WHERE id = ?";
            $stmt = $this->conn->prepare($query);
            $stmt->bind_param("i", $assignment_id);
            $stmt->execute();
            $stmt->close();

            // Revert user role to 'customer'
            $query = "UPDATE users SET role = 'customer' WHERE id = ?";
            $stmt = $this->conn->prepare($query);
            $stmt->bind_param("i", $assignment['employee_user_id']);
            $stmt->execute();
            $stmt->close();

            $this->conn->commit();
            return true;

        } catch (Exception $e) {
            $this->conn->rollback();
            throw $e;
        }
    }
}

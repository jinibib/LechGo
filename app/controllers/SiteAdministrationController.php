<?php

/**
 * SiteAdministrationController
 * Handles employee management for livestock owners
 */

class SiteAdministrationController
{
    private $conn;

    public function __construct($db)
    {
        $this->conn = $db;
    }

    /**
     * Show site administration panel (for livestock owners)
     */
    public function index()
    {
        $session = new Session();
        if (!$session->isAuthenticated()) {
            header('Location: /login');
            exit;
        }

        $user = $session->getUser();

        // Only livestock owners can access
        if ($user['role'] !== 'livestock_owner') {
            $_SESSION['error'] = 'Unauthorized access';
            header('Location: /dashboard');
            exit;
        }

        // Get livestock owner ID from livestock_owners table
        $query = "SELECT id FROM livestock_owners WHERE user_id = ?";
        $stmt = $this->conn->prepare($query);
        $stmt->bind_param("i", $user['id']);
        $stmt->execute();
        $result = $stmt->get_result();
        $livestock_owner_data = $result->fetch_assoc();
        $stmt->close();
        
        $livestock_owner_id = $livestock_owner_data['id'] ?? 0;

        $employeeAssignment = new EmployeeAssignment($this->conn);
        $employees = $employeeAssignment->getOwnerEmployees($livestock_owner_id);
        $available_users = $employeeAssignment->getAvailableUsers();

        // Get employee applications
        require_once __DIR__ . '/../models/EmployeeApplication.php';
        $employeeApplication = new EmployeeApplication($this->conn);
        
        $applications = [];
        
        if ($livestock_owner_id > 0) {
            $applications = $employeeApplication->getApplicationsForOwner($livestock_owner_id);
        }

        include __DIR__ . '/../../resources/views/livestock-owner/site-administration.php';
    }

    /**
     * Assign employee
     */
    public function assignEmployee()
    {
        try {
            $session = new Session();
            if (!$session->isAuthenticated()) {
                echo json_encode(['success' => false, 'message' => 'Unauthorized']);
                exit;
            }

            $user = $session->getUser();

            if ($user['role'] !== 'livestock_owner') {
                echo json_encode(['success' => false, 'message' => 'Unauthorized']);
                exit;
            }

            if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
                echo json_encode(['success' => false, 'message' => 'Invalid request']);
                exit;
            }

            $employee_user_id = intval($_POST['employee_user_id'] ?? 0);
            $assigned_role = trim($_POST['assigned_role'] ?? '');

            if ($employee_user_id <= 0) {
                echo json_encode(['success' => false, 'message' => 'Invalid user']);
                exit;
            }

            if (empty($assigned_role)) {
                echo json_encode(['success' => false, 'message' => 'Please select a role']);
                exit;
            }

            // Get livestock owner ID from livestock_owners table
            $query = "SELECT id FROM livestock_owners WHERE user_id = ?";
            $stmt = $this->conn->prepare($query);
            $stmt->bind_param("i", $user['id']);
            $stmt->execute();
            $result = $stmt->get_result();
            $livestock_owner_data = $result->fetch_assoc();
            $stmt->close();

            if (!$livestock_owner_data) {
                echo json_encode(['success' => false, 'message' => 'Livestock owner profile not found']);
                exit;
            }

            $employeeAssignment = new EmployeeAssignment($this->conn);
            $employeeAssignment->assign($livestock_owner_data['id'], $employee_user_id, $assigned_role);

            echo json_encode(['success' => true, 'message' => 'Employee assigned successfully']);
            exit;

        } catch (Exception $e) {
            echo json_encode(['success' => false, 'message' => $e->getMessage()]);
            exit;
        }
    }

    /**
     * Remove employee assignment
     */
    public function removeEmployee()
    {
        try {
            $session = new Session();
            if (!$session->isAuthenticated()) {
                echo json_encode(['success' => false, 'message' => 'Unauthorized']);
                exit;
            }

            $user = $session->getUser();

            if ($user['role'] !== 'livestock_owner') {
                echo json_encode(['success' => false, 'message' => 'Unauthorized']);
                exit;
            }

            if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
                echo json_encode(['success' => false, 'message' => 'Invalid request']);
                exit;
            }

            $assignment_id = intval($_POST['assignment_id'] ?? 0);

            if ($assignment_id <= 0) {
                echo json_encode(['success' => false, 'message' => 'Invalid assignment']);
                exit;
            }

            // Get livestock owner ID from livestock_owners table
            $query = "SELECT id FROM livestock_owners WHERE user_id = ?";
            $stmt = $this->conn->prepare($query);
            $stmt->bind_param("i", $user['id']);
            $stmt->execute();
            $result = $stmt->get_result();
            $livestock_owner_data = $result->fetch_assoc();
            $stmt->close();

            if (!$livestock_owner_data) {
                echo json_encode(['success' => false, 'message' => 'Livestock owner profile not found']);
                exit;
            }

            $employeeAssignment = new EmployeeAssignment($this->conn);
            $employeeAssignment->remove($assignment_id, $livestock_owner_data['id']);

            echo json_encode(['success' => true, 'message' => 'Employee removed successfully']);
            exit;

        } catch (Exception $e) {
            echo json_encode(['success' => false, 'message' => $e->getMessage()]);
            exit;
        }
    }

    /**
     * Approve employee application
     */
    public function approveEmployeeApplication()
    {
        try {
            $session = new Session();
            if (!$session->isAuthenticated()) {
                echo json_encode(['success' => false, 'message' => 'Unauthorized']);
                exit;
            }

            $user = $session->getUser();

            if ($user['role'] !== 'livestock_owner') {
                echo json_encode(['success' => false, 'message' => 'Unauthorized']);
                exit;
            }

            if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
                echo json_encode(['success' => false, 'message' => 'Invalid request']);
                exit;
            }

            $application_id = intval($_POST['application_id'] ?? 0);

            if ($application_id <= 0) {
                echo json_encode(['success' => false, 'message' => 'Invalid application']);
                exit;
            }

            // Get livestock owner ID
            $query = "SELECT id FROM livestock_owners WHERE user_id = ?";
            $stmt = $this->conn->prepare($query);
            $stmt->bind_param("i", $user['id']);
            $stmt->execute();
            $result = $stmt->get_result();
            $livestock_owner_data = $result->fetch_assoc();
            $stmt->close();

            if (!$livestock_owner_data) {
                echo json_encode(['success' => false, 'message' => 'Livestock owner profile not found']);
                exit;
            }

            require_once __DIR__ . '/../models/EmployeeApplication.php';
            $employeeApplication = new EmployeeApplication($this->conn);
            $employeeApplication->approve($application_id, $livestock_owner_data['id']);

            echo json_encode(['success' => true, 'message' => 'Application approved and employee assigned successfully']);
            exit;

        } catch (Exception $e) {
            echo json_encode(['success' => false, 'message' => $e->getMessage()]);
            exit;
        }
    }

    /**
     * Reject employee application
     */
    public function rejectEmployeeApplication()
    {
        try {
            $session = new Session();
            if (!$session->isAuthenticated()) {
                echo json_encode(['success' => false, 'message' => 'Unauthorized']);
                exit;
            }

            $user = $session->getUser();

            if ($user['role'] !== 'livestock_owner') {
                echo json_encode(['success' => false, 'message' => 'Unauthorized']);
                exit;
            }

            if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
                echo json_encode(['success' => false, 'message' => 'Invalid request']);
                exit;
            }

            $application_id = intval($_POST['application_id'] ?? 0);
            $reason = trim($_POST['reason'] ?? '');

            if ($application_id <= 0) {
                echo json_encode(['success' => false, 'message' => 'Invalid application']);
                exit;
            }

            // Get livestock owner ID
            $query = "SELECT id FROM livestock_owners WHERE user_id = ?";
            $stmt = $this->conn->prepare($query);
            $stmt->bind_param("i", $user['id']);
            $stmt->execute();
            $result = $stmt->get_result();
            $livestock_owner_data = $result->fetch_assoc();
            $stmt->close();

            if (!$livestock_owner_data) {
                echo json_encode(['success' => false, 'message' => 'Livestock owner profile not found']);
                exit;
            }

            require_once __DIR__ . '/../models/EmployeeApplication.php';
            $employeeApplication = new EmployeeApplication($this->conn);
            $employeeApplication->reject($application_id, $livestock_owner_data['id'], $reason);

            echo json_encode(['success' => true, 'message' => 'Application rejected successfully']);
            exit;

        } catch (Exception $e) {
            echo json_encode(['success' => false, 'message' => $e->getMessage()]);
            exit;
        }
    }
}

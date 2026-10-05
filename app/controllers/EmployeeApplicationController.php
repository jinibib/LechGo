<?php

/**
 * EmployeeApplicationController
 * Handles employee application flows
 */

class EmployeeApplicationController
{
    private $conn;

    public function __construct($db)
    {
        $this->conn = $db;
    }

    /**
     * Show apply as employee page
     */
    public function showApplicationForm()
    {
        $session = new Session();
        if (!$session->isAuthenticated()) {
            header('Location: /login');
            exit;
        }

        $user = $session->getUser();

        // Only customers can apply as employees
        if ($user['role'] !== 'customer') {
            $_SESSION['error'] = 'Only customers can apply as piggery employees';
            header('Location: /dashboard');
            exit;
        }

        require_once APP_PATH . '/models/EmployeeApplication.php';
        $employeeApp = new EmployeeApplication($this->conn);

        // Check if already employed
        if ($employeeApp->isAlreadyEmployed($user['id'])) {
            $_SESSION['error'] = 'You are already employed at a piggery';
            header('Location: /dashboard');
            exit;
        }

        // Get available livestock owners
        $livestock_owners = $employeeApp->getAllLivestockOwners();

        // Get user's applications
        $my_applications = $employeeApp->getApplicationsByUser($user['id']);

        // Get pending owner IDs
        $applied_owner_ids = $employeeApp->getPendingOwnerIdsByUser($user['id']);

        include __DIR__ . '/../../resources/views/employee/apply-as-employee.php';
    }

    /**
     * Submit employee application
     */
    public function submitApplication()
    {
        try {
            $session = new Session();
            if (!$session->isAuthenticated()) {
                echo json_encode(['success' => false, 'message' => 'Unauthorized']);
                exit;
            }

            $user = $session->getUser();

            if ($user['role'] !== 'customer') {
                echo json_encode(['success' => false, 'message' => 'Only customers can apply as employees']);
                exit;
            }

            if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
                echo json_encode(['success' => false, 'message' => 'Invalid request']);
                exit;
            }

            $owner_id = intval($_POST['owner_id'] ?? 0);
            $position = trim($_POST['position'] ?? '');
            $cover_letter = trim($_POST['cover_letter'] ?? '');

            if ($owner_id <= 0) {
                echo json_encode(['success' => false, 'message' => 'Invalid piggery farm']);
                exit;
            }

            if (empty($position)) {
                echo json_encode(['success' => false, 'message' => 'Please select a position']);
                exit;
            }

            // Validate position
            $valid_positions = ['pig_caretaker', 'lechonero', 'pig_slaughter', 'logistics'];
            if (!in_array($position, $valid_positions)) {
                echo json_encode(['success' => false, 'message' => 'Invalid position']);
                exit;
            }

            require_once APP_PATH . '/models/EmployeeApplication.php';
            $employeeApp = new EmployeeApplication($this->conn);
            
            $employeeApp->create($user['id'], $owner_id, $position, $cover_letter);

            echo json_encode(['success' => true, 'message' => 'Your application has been submitted successfully!']);
            exit;

        } catch (Exception $e) {
            echo json_encode(['success' => false, 'message' => $e->getMessage()]);
            exit;
        }
    }

    /**
     * Livestock owner - View employee applications
     */
    public function viewApplications()
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

        // Get livestock owner ID
        require_once APP_PATH . '/models/LivestockOwner.php';
        $livestockOwner = new LivestockOwner($this->conn);
        if (!$livestockOwner->findByUserId($user['id'])) {
            $_SESSION['error'] = 'Livestock owner profile not found';
            header('Location: /dashboard');
            exit;
        }

        require_once APP_PATH . '/models/EmployeeApplication.php';
        $employeeApp = new EmployeeApplication($this->conn);
        
        $applications = $employeeApp->getApplicationsForOwner($livestockOwner->id);

        include __DIR__ . '/../../resources/views/livestock-owner/employee-applications.php';
    }

    /**
     * Livestock owner - Approve employee application
     */
    public function approveApplication()
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
            require_once APP_PATH . '/models/LivestockOwner.php';
            $livestockOwner = new LivestockOwner($this->conn);
            if (!$livestockOwner->findByUserId($user['id'])) {
                echo json_encode(['success' => false, 'message' => 'Livestock owner profile not found']);
                exit;
            }

            require_once APP_PATH . '/models/EmployeeApplication.php';
            $employeeApp = new EmployeeApplication($this->conn);
            
            $employeeApp->approve($application_id, $livestockOwner->id);

            echo json_encode(['success' => true, 'message' => 'Application approved! Employee has been assigned to your piggery.']);
            exit;

        } catch (Exception $e) {
            echo json_encode(['success' => false, 'message' => $e->getMessage()]);
            exit;
        }
    }

    /**
     * Livestock owner - Reject employee application
     */
    public function rejectApplication()
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

            if (empty($reason)) {
                echo json_encode(['success' => false, 'message' => 'Please provide rejection reason']);
                exit;
            }

            // Get livestock owner ID
            require_once APP_PATH . '/models/LivestockOwner.php';
            $livestockOwner = new LivestockOwner($this->conn);
            if (!$livestockOwner->findByUserId($user['id'])) {
                echo json_encode(['success' => false, 'message' => 'Livestock owner profile not found']);
                exit;
            }

            require_once APP_PATH . '/models/EmployeeApplication.php';
            $employeeApp = new EmployeeApplication($this->conn);
            
            $employeeApp->reject($application_id, $livestockOwner->id, $reason);

            echo json_encode(['success' => true, 'message' => 'Application rejected']);
            exit;

        } catch (Exception $e) {
            echo json_encode(['success' => false, 'message' => $e->getMessage()]);
            exit;
        }
    }
}

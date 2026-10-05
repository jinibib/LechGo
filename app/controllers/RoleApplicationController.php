<?php

/**
 * RoleApplicationController
 * Handles role application flows
 */

class RoleApplicationController
{
    private $conn;

    public function __construct($db)
    {
        $this->conn = $db;
    }

    /**
     * Apply as Customer (simple, no documents needed)
     */
    public function applyAsCustomer()
    {
        try {
            $session = new Session();
            if (!$session->isAuthenticated()) {
                header('Location: /login');
                exit;
            }

            $user = $session->getUser();

            // Check if already has a role other than default
            if ($user['role'] !== 'customer') {
                $_SESSION['error'] = 'You already have a role assigned';
                header('Location: /dashboard');
                exit;
            }

            if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
                header('Location: /dashboard');
                exit;
            }

            $roleApp = new RoleApplication($this->conn);
            $roleApp->create($user['id'], 'customer');

            // Auto-approve customer applications
            $app_id = $roleApp->id;
            $roleApp->approve($app_id, $user['id'], 'Auto-approved customer application');

            $_SESSION['success'] = 'Your customer account has been activated!';
            
            // Update session
            $_SESSION['user']['role'] = 'customer';
            
            header('Location: /dashboard');
            exit;

        } catch (Exception $e) {
            $_SESSION['error'] = $e->getMessage();
            header('Location: /dashboard');
            exit;
        }
    }

    /**
     * Show piggery application form
     */
    public function showPiggeryApplicationForm()
    {
        $session = new Session();
        if (!$session->isAuthenticated()) {
            header('Location: /login');
            exit;
        }

        $user = $session->getUser();

        // Check if already has a role other than default
        if ($user['role'] !== 'customer') {
            $_SESSION['error'] = 'You already have a role assigned';
            header('Location: /dashboard');
            exit;
        }

        // Check for pending applications
        $roleApp = new RoleApplication($this->conn);
        if ($roleApp->hasPendingApplication($user['id'])) {
            $_SESSION['warning'] = 'You have a pending application. Please wait for admin approval.';
            header('Location: /dashboard');
            exit;
        }

        include __DIR__ . '/../../resources/views/role-application/piggery-application.php';
    }

    /**
     * Submit piggery application
     */
    public function submitPiggeryApplication()
    {
        // IMPORTANT: Clear any output buffers to prevent HTML from interfering with JSON
        if (ob_get_level()) {
            ob_clean();
        }
        
        // Force JSON response header at the start
        header('Content-Type: application/json');
        
        // Check if AJAX request
        $isAjax = !empty($_SERVER['HTTP_X_REQUESTED_WITH']) && 
                  strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) == 'xmlhttprequest';
        
        try {
            $session = new Session();
            if (!$session->isAuthenticated()) {
                if ($isAjax) {
                    http_response_code(401);
                    echo json_encode(['success' => false, 'message' => 'You must be logged in']);
                    exit();
                }
                $_SESSION['error'] = 'You must be logged in';
                header('Location: /login');
                exit();
            }

            if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
                if ($isAjax) {
                    http_response_code(400);
                    echo json_encode(['success' => false, 'message' => 'Invalid request method']);
                    exit();
                }
                $_SESSION['error'] = 'Invalid request method';
                header('Location: /customer/profile');
                exit();
            }

            $user = $session->getUser();

            // Validate inputs
            $farm_name = trim($_POST['farm_name'] ?? '');
            $farm_location = trim($_POST['farm_location'] ?? '');
            $street = trim($_POST['street'] ?? '');
            $municipality = trim($_POST['municipality'] ?? '');
            $barangay = trim($_POST['barangay'] ?? '');

            error_log("Piggery Application - Form data: farm_name=$farm_name, municipality=$municipality, barangay=$barangay, street=$street");

            if (empty($farm_name)) {
                if ($isAjax) {
                    http_response_code(400);
                    echo json_encode(['success' => false, 'message' => 'Farm name is required']);
                    exit();
                }
                $_SESSION['error'] = 'Farm name is required';
                header('Location: /customer/profile');
                exit();
            }

            if (empty($street) || empty($municipality) || empty($barangay)) {
                if ($isAjax) {
                    http_response_code(400);
                    echo json_encode(['success' => false, 'message' => 'Complete address (district, barangay, street) is required']);
                    exit();
                }
                $_SESSION['error'] = 'Complete address (district, barangay, street) is required';
                header('Location: /customer/profile');
                exit();
            }

            // Build complete location if not provided
            if (empty($farm_location)) {
                $farm_location = $street . ', ' . $barangay . ', ' . $municipality . ', Davao City';
            }

            error_log("Complete farm location: $farm_location");

            // Handle file uploads - 2 required documents (TESTING MODE)
            $upload_dir = __DIR__ . '/../../uploads/piggery_applications/';
            
            // Create directory if it doesn't exist
            if (!is_dir($upload_dir)) {
                if (!mkdir($upload_dir, 0777, true)) {
                    throw new Exception('Failed to create upload directory');
                }
            }

            $required_docs = 2;
            $uploaded_files = [
                'business_registration' => null,
                'barangay_clearance' => null,
                'cpdo_clearance' => null,
                'denr_ecc' => null,
                'cvo_sanitary_permit' => null,
                'business_permit' => null
            ];

            // Check if files are uploaded
            if (!isset($_FILES['documents']) || !is_array($_FILES['documents']['name'])) {
                if ($isAjax) {
                    http_response_code(400);
                    echo json_encode(['success' => false, 'message' => 'No documents uploaded']);
                    exit();
                }
                $_SESSION['error'] = 'No documents uploaded';
                header('Location: /customer/profile');
                exit();
            }

            $file_count = count($_FILES['documents']['name']);
            error_log("Number of files uploaded: $file_count");

            if ($file_count < $required_docs) {
                if ($isAjax) {
                    http_response_code(400);
                    echo json_encode(['success' => false, 'message' => "All 2 documents are required. You uploaded $file_count file(s)."]);
                    exit();
                }
                $_SESSION['error'] = 'All 2 documents are required. You uploaded ' . $file_count . ' file(s).';
                header('Location: /customer/profile');
                exit();
            }

            $doc_keys = array_keys($uploaded_files);
            
            // OPTIMIZED FOR INFINITYFREE: Minimal validation, faster processing
            for ($i = 0; $i < $file_count && $i < $required_docs; $i++) {
                error_log("Processing file $i: error=" . $_FILES['documents']['error'][$i]);
                
                if ($_FILES['documents']['error'][$i] === UPLOAD_ERR_OK) {
                    $file_name = $_FILES['documents']['name'][$i];
                    $file_tmp = $_FILES['documents']['tmp_name'][$i];
                    
                    error_log("File $i: name=$file_name, tmp_name=$file_tmp");
                    
                    // ONLY check extension (fast, no file I/O operations)
                    $file_ext = strtolower(pathinfo($file_name, PATHINFO_EXTENSION));
                    $allowed_extensions = ['jpg', 'jpeg', 'png', 'pdf'];
                    
                    if (!in_array($file_ext, $allowed_extensions)) {
                        error_log("File $i REJECTED: Invalid extension $file_ext");
                        if ($isAjax) {
                            http_response_code(400);
                            echo json_encode(['success' => false, 'message' => "File '$file_name' must be JPG, PNG, or PDF"]);
                            exit();
                        }
                        $_SESSION['error'] = "File '$file_name' must be JPG, PNG, or PDF";
                        header('Location: /customer/profile');
                        exit();
                    }
                    
                    error_log("File $i ACCEPTED (ext: $file_ext)");

                    // Generate unique filename (simplified)
                    $doc_type = $doc_keys[$i];
                    $unique_name = 'piggery_' . $user['id'] . '_' . $doc_type . '_' . time() . rand(100, 999) . '.' . $file_ext;
                    $destination = $upload_dir . $unique_name;

                    if (move_uploaded_file($file_tmp, $destination)) {
                        $uploaded_files[$doc_type] = 'uploads/piggery_applications/' . $unique_name;
                        error_log("File $i uploaded: " . $uploaded_files[$doc_type]);
                    } else {
                        throw new Exception("Failed to upload file: $file_name");
                    }
                } else {
                    $error_code = $_FILES['documents']['error'][$i];
                    $error_msg = "File upload error";
                    if ($error_code == UPLOAD_ERR_INI_SIZE || $error_code == UPLOAD_ERR_FORM_SIZE) {
                        $error_msg = "File too large (max 2MB)";
                    }
                    throw new Exception($error_msg . " (code: $error_code)");
                }
            }

            // NOTE: Not verifying all documents since we're only requiring 2 for testing

            // Create role application first
            $roleApp = new RoleApplication($this->conn);
            $app_id = $roleApp->create($user['id'], 'piggery_owner', $farm_name, $farm_location, null);

            error_log("RoleApplication created with ID: " . $app_id);

            // Save requirements to database
            require_once APP_PATH . '/models/Requirements.php';
            $requirements = new Requirements($this->conn);
            
            error_log("Creating Requirements record...");
            error_log("Documents: " . print_r($uploaded_files, true));
            
            $req_id = $requirements->create(
                $user['id'],
                $app_id,
                $farm_name,
                $farm_location,
                $uploaded_files
            );

            error_log("Requirements created with ID: " . $req_id);

            // Return success response
            if ($isAjax) {
                http_response_code(200);
                echo json_encode([
                    'success' => true, 
                    'message' => 'Your piggery application has been submitted successfully! Please wait for admin approval.',
                    'redirect' => '/customer/profile'
                ]);
                exit();
            }
            
            // For regular form submission, show simple success page
            echo '<!DOCTYPE html><html><head><meta charset="UTF-8"><title>Application Submitted</title></head><body style="font-family: Arial; padding: 50px; text-align: center;">';
            echo '<div style="background: #d4edda; border: 2px solid #28a745; padding: 30px; border-radius: 10px; max-width: 600px; margin: 0 auto;">';
            echo '<h1 style="color: #155724; margin: 0 0 20px 0;">✅ Success!</h1>';
            echo '<p style="color: #155724; font-size: 18px; margin: 0 0 20px 0;">Your piggery application has been submitted successfully!</p>';
            echo '<p style="color: #155724; margin: 0 0 30px 0;">Farm: <strong>' . htmlspecialchars($farm_name) . '</strong><br>';
            echo 'Location: ' . htmlspecialchars($farm_location) . '<br>';
            echo 'Application ID: <strong>' . $app_id . '</strong><br>';
            echo 'Requirements ID: <strong>' . $req_id . '</strong></p>';
            echo '<p style="color: #856404; background: #fff3cd; padding: 15px; border-radius: 5px; margin: 0 0 20px 0;">Please wait for admin approval. You will be notified once your application is processed.</p>';
            echo '<a href="/customer/profile" style="display: inline-block; background: #28a745; color: white; padding: 15px 30px; text-decoration: none; border-radius: 5px; font-weight: bold;">Go to Profile</a>';
            echo '</div></body></html>';
            exit();

        } catch (Exception $e) {
            error_log("Application submission error: " . $e->getMessage());
            error_log("Stack trace: " . $e->getTraceAsString());
            
            if ($isAjax) {
                http_response_code(500);
                echo json_encode(['success' => false, 'message' => 'Application submission failed: ' . $e->getMessage()]);
                exit();
            }
            
            $_SESSION['error'] = 'Application submission failed: ' . $e->getMessage();
            header('Location: /customer/profile');
            exit();
        }
    }

    /**
     * System Admin - View all pending applications
     */
    public function viewPendingApplications()
    {
        $session = new Session();
        if (!$session->isAuthenticated()) {
            header('Location: /login');
            exit;
        }

        $user = $session->getUser();

        // Only system admin can access
        if ($user['role'] !== 'admin' && $user['role'] !== 'system_admin') {
            $_SESSION['error'] = 'Unauthorized access';
            header('Location: /dashboard');
            exit;
        }

        $roleApp = new RoleApplication($this->conn);
        $pending_applications = $roleApp->getAllPending();

        include __DIR__ . '/../../resources/views/admin/pending-applications.php';
    }

    /**
     * System Admin - Approve application
     */
    public function approveApplication()
    {
        // Clear any output buffers
        while (ob_get_level()) {
            ob_end_clean();
        }
        
        // Set JSON header
        header('Content-Type: application/json');
        
        try {
            $session = new Session();
            if (!$session->isAuthenticated()) {
                http_response_code(401);
                echo json_encode(['success' => false, 'message' => 'Unauthorized']);
                exit;
            }

            $user = $session->getUser();

            if ($user['role'] !== 'admin' && $user['role'] !== 'system_admin') {
                http_response_code(403);
                echo json_encode(['success' => false, 'message' => 'Unauthorized']);
                exit;
            }

            if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
                http_response_code(400);
                echo json_encode(['success' => false, 'message' => 'Invalid request']);
                exit;
            }

            $application_id = intval($_POST['application_id'] ?? 0);
            $remarks = trim($_POST['remarks'] ?? '');

            if ($application_id <= 0) {
                http_response_code(400);
                echo json_encode(['success' => false, 'message' => 'Invalid application ID']);
                exit;
            }

            $roleApp = new RoleApplication($this->conn);
            $roleApp->approve($application_id, $user['id'], $remarks);

            http_response_code(200);
            echo json_encode(['success' => true, 'message' => 'Application approved successfully']);
            exit;

        } catch (Exception $e) {
            error_log("Approve application error: " . $e->getMessage());
            http_response_code(500);
            echo json_encode(['success' => false, 'message' => $e->getMessage()]);
            exit;
        }
    }

    /**
     * System Admin - Reject application
     */
    public function rejectApplication()
    {
        // Clear any output buffers
        while (ob_get_level()) {
            ob_end_clean();
        }
        
        // Set JSON header
        header('Content-Type: application/json');
        
        try {
            $session = new Session();
            if (!$session->isAuthenticated()) {
                http_response_code(401);
                echo json_encode(['success' => false, 'message' => 'Unauthorized']);
                exit;
            }

            $user = $session->getUser();

            if ($user['role'] !== 'admin' && $user['role'] !== 'system_admin') {
                http_response_code(403);
                echo json_encode(['success' => false, 'message' => 'Unauthorized']);
                exit;
            }

            if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
                http_response_code(400);
                echo json_encode(['success' => false, 'message' => 'Invalid request']);
                exit;
            }

            $application_id = intval($_POST['application_id'] ?? 0);
            $remarks = trim($_POST['remarks'] ?? '');

            if ($application_id <= 0) {
                http_response_code(400);
                echo json_encode(['success' => false, 'message' => 'Invalid application ID']);
                exit;
            }

            if (empty($remarks)) {
                http_response_code(400);
                echo json_encode(['success' => false, 'message' => 'Please provide rejection reason']);
                exit;
            }

            $roleApp = new RoleApplication($this->conn);
            $roleApp->reject($application_id, $user['id'], $remarks);

            http_response_code(200);
            echo json_encode(['success' => true, 'message' => 'Application rejected']);
            exit;

        } catch (Exception $e) {
            error_log("Reject application error: " . $e->getMessage());
            http_response_code(500);
            echo json_encode(['success' => false, 'message' => $e->getMessage()]);
            exit;
        }
    }
}

<?php

/**
 * Authentication Controller
 * Handles user authentication flows
 */

class AuthController
{
    private $conn;
    private $baseUrl = 'http://localhost/LechGo_Final/public';

    public function __construct($db)
    {
        $this->conn = $db;
    }

    /**
     * Handle user registration
     */
    public function register()
    {
        try {
            // Validate request method
            if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
                $this->redirect('/register');
                return;
            }

            // Get form data
            $name = trim($_POST['name'] ?? '');
            $email = trim($_POST['email'] ?? '');
            $password = $_POST['password'] ?? '';
            $phone = trim($_POST['phone'] ?? '');
            // Always register new users as 'customer' - admins can assign other roles later
            $role = 'customer';

            // Validate inputs
            if (empty($name) || empty($email) || empty($password) || empty($phone)) {
                $_SESSION['error'] = 'All fields are required';
                $this->redirect('/register');
                return;
            }

            if (strlen($name) < 3) {
                $_SESSION['error'] = 'Name must be at least 3 characters';
                $this->redirect('/register');
                return;
            }

            if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
                $_SESSION['error'] = 'Invalid email format';
                $this->redirect('/register');
                return;
            }

            if (strlen($password) < 8) {
                $_SESSION['error'] = 'Password must be at least 8 characters';
                $this->redirect('/register');
                return;
            }

            if (!preg_match('/[A-Z]/', $password)) {
                $_SESSION['error'] = 'Password must contain at least 1 uppercase letter';
                $this->redirect('/register');
                return;
            }

            if (!preg_match('/[0-9]/', $password)) {
                $_SESSION['error'] = 'Password must contain at least 1 number';
                $this->redirect('/register');
                return;
            }

            if (strlen($phone) < 10) {
                $_SESSION['error'] = 'Invalid phone number';
                $this->redirect('/register');
                return;
            }

            // Create user
            $user = new User($this->conn);
            $user_id = $user->create($name, $email, $password, $phone, $role);

            // Generate verification token
            $emailVerification = new EmailVerification($this->conn);
            $token = $emailVerification->generateToken($user_id, $email);

            // Send verification email
            $emailService = new EmailService();
            $emailService->sendVerificationEmail($email, $name, $token);

            // Store verification email in session
            $session = new Session();
            $session->setVerificationEmail($email, $user_id);

            $_SESSION['success'] = 'Registration successful! Please check your email to verify your account.';
            $this->redirect('/verify-email');
        } catch (Exception $e) {
            $_SESSION['error'] = $e->getMessage();
            $this->redirect('/register');
        }
    }

    /**
     * Handle email verification
     */
    public function verifyEmail($token = null)
    {
        try {
            if ($token === null) {
                $_SESSION['error'] = 'Verification token not found';
                $this->redirect('/login');
                return;
            }

            // Verify token
            $emailVerification = new EmailVerification($this->conn);
            if (!$emailVerification->verifyToken($token)) {
                $_SESSION['error'] = 'Invalid or expired verification token';
                $this->redirect('/register');
                return;
            }

            // Mark user email as verified
            $user = new User($this->conn);
            if (!$user->findById($emailVerification->user_id)) {
                $_SESSION['error'] = 'User not found';
                $this->redirect('/login');
                return;
            }

            $user->markEmailVerified();
            $emailVerification->markAsVerified();

            // Generate and send OTP
            $otp = new OTP($this->conn);
            $otp_code = $otp->generateOTP($user->email);

            $emailService = new EmailService();
            $emailService->sendOTPEmail($user->email, $user->name, $otp_code);

            // Store OTP email in session
            $session = new Session();
            $session->setOTPEmail($user->email);

            $_SESSION['success'] = 'Email verified successfully! Check your email for the login code.';
            $this->redirect('/verify-otp');
        } catch (Exception $e) {
            $_SESSION['error'] = 'Verification failed: ' . $e->getMessage();
            $this->redirect('/register');
        }
    }

    /**
     * Handle login
     */
    public function login()
    {
        try {
            if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
                $this->redirect('/login');
                return;
            }

            $email = trim($_POST['email'] ?? '');
            $password = $_POST['password'] ?? '';

            if (empty($email) || empty($password)) {
                $_SESSION['error'] = 'Email and password are required';
                $this->redirect('/login');
                return;
            }

            // Find user
            $user = new User($this->conn);
            if (!$user->findByEmail($email)) {
                $_SESSION['error'] = 'Invalid email or password';
                $this->redirect('/login');
                return;
            }

            // Verify password
            if (!$user->verifyPassword($password)) {
                $_SESSION['error'] = 'Invalid email or password';
                $this->redirect('/login');
                return;
            }

            // Check email verification
            if (!$user->isEmailVerified()) {
                // Regenerate verification token
                $emailVerification = new EmailVerification($this->conn);
                $token = $emailVerification->generateToken($user->id, $user->email);

                $emailService = new EmailService();
                $emailService->sendVerificationEmail($user->email, $user->name, $token);

                $session = new Session();
                $session->setVerificationEmail($user->email, $user->id);

                $_SESSION['warning'] = 'Your email is not verified. Check your email for the verification link.';
                $this->redirect('/verify-email');
                return;
            }

            // Generate and send OTP
            $otp = new OTP($this->conn);
            $otp_code = $otp->generateOTP($user->email);

            $emailService = new EmailService();
            $emailService->sendOTPEmail($user->email, $user->name, $otp_code);

            // Store OTP email in session
            $session = new Session();
            $session->setOTPEmail($user->email);

            $_SESSION['success'] = 'Check your email for the login code.';
            $this->redirect('/verify-otp');
        } catch (Exception $e) {
            $_SESSION['error'] = 'Login failed: ' . $e->getMessage();
            $this->redirect('/login');
        }
    }

    /**
     * Handle OTP verification
     */
    public function verifyOTP()
    {
        try {
            if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
                $this->redirect('/verify-otp');
                return;
            }

            // Get OTP from form. Support both individual digits and combined OTP field.
            if (!empty($_POST['otp'])) {
                $otp_code_raw = preg_replace('/\D+/', '', $_POST['otp']);
                $otp_code = substr($otp_code_raw, 0, 6);
            } else {
                $otp_code = '';
                for ($i = 1; $i <= 6; $i++) {
                    $digit = trim($_POST['otp_' . $i] ?? '');
                    $otp_code .= $digit;
                }
            }

            if (strlen($otp_code) !== 6 || !ctype_digit($otp_code)) {
                $_SESSION['error'] = 'Invalid OTP format. Please enter a 6-digit code.';
                $this->redirect('/verify-otp');
                return;
            }

            // Get email from session
            $session = new Session();
            $email = $session->getOTPEmail();

            if (empty($email)) {
                $_SESSION['error'] = 'Invalid session. Please log in again.';
                $this->redirect('/login');
                return;
            }

            // Verify OTP
            $otp = new OTP($this->conn);
            if (!$otp->verifyOTP($email, $otp_code)) {
                $errorState = $otp->getLastError();
                if ($errorState === 'expired') {
                    $_SESSION['error'] = 'OTP has expired. Please request a new code.';
                } else {
                    $_SESSION['error'] = 'Invalid OTP. Please check the code and try again.';
                }
                $this->redirect('/verify-otp');
                return;
            }

            // Get user and create session
            $user = new User($this->conn);
            if (!$user->findByEmail($email)) {
                $_SESSION['error'] = 'User not found';
                $this->redirect('/login');
                return;
            }

            // Create user session
            $session->setUser($user->id, $user->email, $user->name, $user->role, $user->phone);
            $session->clearOTP();

            // Check if this is a new registration (coming from email verification)
            $isNewRegistration = isset($_SESSION['verification_email']) || isset($_SESSION['verification_user_id']);
            
            if ($isNewRegistration && $user->role === 'customer') {
                // New registration - redirect to role selection
                $_SESSION['temp_user_id'] = $user->id;
                $_SESSION['temp_user_email'] = $user->email;
                $_SESSION['temp_user_name'] = $user->name;
                
                $_SESSION['success'] = 'Email verified! Now select your role to continue.';
                $this->redirect('/select-role');
            } else {
                // Existing user login - check if profile is complete
                if ($this->isProfileComplete($user->id, $user->role)) {
                    // Profile complete - redirect to dashboard
                    $_SESSION['success'] = 'Welcome back! You are logged in.';
                    $dashboard = getRoleDashboard($user->role);
                    $this->redirect($dashboard);
                } else {
                    // Profile incomplete - redirect to complete-profile
                    $_SESSION['success'] = 'Please complete your profile to continue.';
                    $this->redirect('/complete-profile');
                }
            }
        } catch (Exception $e) {
            $_SESSION['error'] = 'OTP verification failed: ' . $e->getMessage();
            $this->redirect('/verify-otp');
        }
    }

    /**
     * Handle logout
     */
    public function logout()
    {
        $session = new Session();
        $session->logout();

        $_SESSION['success'] = 'You have been logged out successfully.';
        $this->redirect('/');
    }

    /**
     * Resend verification email
     */
    public function resendVerificationEmail()
    {
        try {
            if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
                http_response_code(405);
                echo json_encode(['success' => false, 'message' => 'Method not allowed']);
                return;
            }

            $session = new Session();
            $email = $session->getVerificationEmail();

            if (empty($email)) {
                echo json_encode(['success' => false, 'message' => 'Invalid session']);
                return;
            }

            // Find user
            $user = new User($this->conn);
            if (!$user->findByEmail($email)) {
                echo json_encode(['success' => false, 'message' => 'User not found']);
                return;
            }

            // Generate new token
            $emailVerification = new EmailVerification($this->conn);
            $token = $emailVerification->generateToken($user->id, $user->email);

            // Send email
            $emailService = new EmailService();
            $emailService->sendVerificationEmail($user->email, $user->name, $token);

            echo json_encode(['success' => true, 'message' => 'Verification email sent']);
        } catch (Exception $e) {
            echo json_encode(['success' => false, 'message' => $e->getMessage()]);
        }
    }

    /**
     * Resend OTP
     */
    public function resendOTP()
    {
        try {
            if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
                http_response_code(405);
                echo json_encode(['success' => false, 'message' => 'Method not allowed']);
                return;
            }

            $session = new Session();
            $email = $session->getOTPEmail();

            if (empty($email)) {
                echo json_encode(['success' => false, 'message' => 'Invalid session']);
                return;
            }

            // Find user
            $user = new User($this->conn);
            if (!$user->findByEmail($email)) {
                echo json_encode(['success' => false, 'message' => 'User not found']);
                return;
            }

            // Generate new OTP
            $otp = new OTP($this->conn);
            $otp_code = $otp->generateOTP($user->email);

            // Send email
            $emailService = new EmailService();
            $emailService->sendOTPEmail($user->email, $user->name, $otp_code);

            echo json_encode(['success' => true, 'message' => 'OTP resent successfully']);
        } catch (Exception $e) {
            echo json_encode(['success' => false, 'message' => $e->getMessage()]);
        }
    }

    /**
     * Check if user profile is complete
     */
    private function isProfileComplete($user_id, $role)
    {
        switch ($role) {
            case 'customer':
                return true; // Customers don't need additional profile data
            case 'lechonero':
                $lechonero = new Lechonero($this->conn);
                return $lechonero->findByUserId($user_id);
            case 'supplier':
                $supplier = new FeedSupplier($this->conn);
                return $supplier->findByUserId($user_id);
            case 'livestock_owner':
                $owner = new LivestockOwner($this->conn);
                return $owner->findByUserId($user_id);
            case 'pig_caretaker':
                $caretaker = new PigCaretaker($this->conn);
                return $caretaker->findByUserId($user_id);
            case 'feed_distributor':
                $distributor = new FeedDistributor($this->conn);
                return $distributor->findByUserId($user_id);
            case 'admin':
            case 'logistics':
                return true; // Admins and logistics don't need additional data for now
            default:
                return true;
        }
    }

    /**
     * Handle profile completion
     */
    public function completeProfile()
    {
        try {
            // Check authentication
            $session = new Session();
            if (!$session->isAuthenticated()) {
                $this->redirect('/login');
                return;
            }

            $user = $session->getUser();
            $role = $user['role'];

            if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
                $this->redirect('/complete-profile');
                return;
            }

            // Process based on role
            switch ($role) {
                case 'lechonero':
                    $business_name = trim($_POST['business_name'] ?? '');
                    $specialty = trim($_POST['specialty'] ?? '');

                    if (empty($business_name) || empty($specialty)) {
                        $_SESSION['error'] = 'All fields are required';
                        $this->redirect('/complete-profile');
                        return;
                    }

                    $lechonero = new Lechonero($this->conn);
                    $lechonero->create($user['id'], $business_name, $specialty);
                    break;

                case 'supplier':
                    $farm_name = trim($_POST['farm_name'] ?? '');
                    $city = trim($_POST['city'] ?? '');
                    $municipality = trim($_POST['municipality'] ?? '');
                    $barangay = trim($_POST['barangay'] ?? '');
                    $street = trim($_POST['street'] ?? '');
                    $contact_number = trim($_POST['contact_number'] ?? '');

                    $allowedMunicipalities = [
                        'Tugbok', 'Cabantian', 'Toril', 'Bajada', 'Agdao', 'Poblacion',
                        'Matina', 'Lanang', 'Mintal', 'Talomo'
                    ];

                    if (empty($farm_name) || empty($city) || empty($municipality) || empty($barangay) || empty($street) || empty($contact_number)) {
                        $_SESSION['error'] = 'All fields are required';
                        $this->redirect('/complete-profile');
                        return;
                    }

                    if ($city !== 'Davao City') {
                        $_SESSION['error'] = 'City must be Davao City.';
                        $this->redirect('/complete-profile');
                        return;
                    }

                    if (!in_array($municipality, $allowedMunicipalities, true)) {
                        $_SESSION['error'] = 'Invalid municipality/district selected. Please choose a Davao City district.';
                        $this->redirect('/complete-profile');
                        return;
                    }

                    $supplier = new FeedSupplier($this->conn);
                    $supplier->create($user['id'], $farm_name, $street, $barangay, $municipality, $city, $contact_number);
                    break;

                case 'pig_caretaker':
                    $farm_name = trim($_POST['farm_name'] ?? '');
                    $city = trim($_POST['city'] ?? '');
                    $municipality = trim($_POST['municipality'] ?? '');
                    $barangay = trim($_POST['barangay'] ?? '');
                    $street = trim($_POST['street'] ?? '');
                    $contact_number = trim($_POST['contact_number'] ?? '');

                    $allowedMunicipalities = [
                        'Tugbok', 'Cabantian', 'Toril', 'Bajada', 'Agdao', 'Poblacion',
                        'Matina', 'Lanang', 'Mintal', 'Talomo'
                    ];

                    if (empty($farm_name) || empty($city) || empty($municipality) || empty($barangay) || empty($street) || empty($contact_number)) {
                        $_SESSION['error'] = 'All fields are required';
                        $this->redirect('/complete-profile');
                        return;
                    }

                    if ($city !== 'Davao City') {
                        $_SESSION['error'] = 'City must be Davao City.';
                        $this->redirect('/complete-profile');
                        return;
                    }

                    if (!in_array($municipality, $allowedMunicipalities, true)) {
                        $_SESSION['error'] = 'Invalid municipality/district selected. Please choose a Davao City district.';
                        $this->redirect('/complete-profile');
                        return;
                    }

                    // Construct full location string
                    $location = $street . ', ' . $barangay . ', ' . $municipality . ', ' . $city;

                    $pigCaretaker = new PigCaretaker($this->conn);
                    $pigCaretaker->create($user['id'], $farm_name, $location, $contact_number);
                    
                    // Notify all livestock owners about new caretaker
                    try {
                        require_once __DIR__ . '/../models/Notification.php';
                        // Get all livestock owners (don't filter by is_active since they might not be fully set up yet)
                        $query = "SELECT lo.user_id FROM livestock_owners lo";
                        $stmt = $this->conn->prepare($query);
                        if ($stmt) {
                            $stmt->execute();
                            $result = $stmt->get_result();
                            $notified_count = 0;
                            while ($row = $result->fetch_assoc()) {
                                $notification = new Notification($this->conn);
                                $created = $notification->create(
                                    $row['user_id'],
                                    'caretaker_request',
                                    'New Caretaker Request',
                                    "A new caretaker '" . htmlspecialchars($user['name']) . "' has requested to join your farm.",
                                    '/LechGo_Final/public/livestock-owner/manage-caretakers'
                                );
                                if ($created) {
                                    $notified_count++;
                                }
                            }
                            $stmt->close();
                            // Log for debugging
                            error_log("Notified $notified_count livestock owners about new caretaker: " . $user['name']);
                        }
                    } catch (Exception $e) {
                        error_log("Error notifying livestock owners: " . $e->getMessage());
                    }
                    break;

                case 'livestock_owner':
                    $farm_name = trim($_POST['farm_name'] ?? '');
                    $city = trim($_POST['city'] ?? '');
                    $municipality = trim($_POST['municipality'] ?? '');
                    $barangay = trim($_POST['barangay'] ?? '');
                    $street = trim($_POST['street'] ?? '');
                    $contact_number = trim($_POST['contact_number'] ?? '');

                    $allowedMunicipalities = [
                        'Tugbok', 'Cabantian', 'Toril', 'Bajada', 'Agdao', 'Poblacion',
                        'Matina', 'Lanang', 'Mintal', 'Talomo'
                    ];

                    if (empty($farm_name) || empty($city) || empty($municipality) || empty($barangay) || empty($street) || empty($contact_number)) {
                        $_SESSION['error'] = 'All fields are required';
                        $this->redirect('/complete-profile');
                        return;
                    }

                    if ($city !== 'Davao City') {
                        $_SESSION['error'] = 'City must be Davao City.';
                        $this->redirect('/complete-profile');
                        return;
                    }

                    if (!in_array($municipality, $allowedMunicipalities, true)) {
                        $_SESSION['error'] = 'Invalid municipality/district selected. Please choose a Davao City district.';
                        $this->redirect('/complete-profile');
                        return;
                    }

                    // Construct full location string
                    $location = $street . ', ' . $barangay . ', ' . $municipality . ', ' . $city;

                    $owner = new LivestockOwner($this->conn);
                    $owner->create($user['id'], $farm_name, $location, $contact_number);
                    break;

                case 'feed_distributor':
                    $business_name = trim($_POST['business_name'] ?? '');
                    $city = trim($_POST['city'] ?? '');
                    $municipality = trim($_POST['municipality'] ?? '');
                    $barangay = trim($_POST['barangay'] ?? '');
                    $street = trim($_POST['street'] ?? '');
                    $contact_number = trim($_POST['contact_number'] ?? '');

                    $allowedMunicipalities = [
                        'Tugbok', 'Cabantian', 'Toril', 'Bajada', 'Agdao', 'Poblacion',
                        'Matina', 'Lanang', 'Mintal', 'Talomo'
                    ];

                    if (empty($business_name) || empty($city) || empty($municipality) || empty($barangay) || empty($street) || empty($contact_number)) {
                        $_SESSION['error'] = 'All fields are required';
                        $this->redirect('/complete-profile');
                        return;
                    }

                    if ($city !== 'Davao City') {
                        $_SESSION['error'] = 'City must be Davao City.';
                        $this->redirect('/complete-profile');
                        return;
                    }

                    if (!in_array($municipality, $allowedMunicipalities, true)) {
                        $_SESSION['error'] = 'Invalid municipality/district selected. Please choose a Davao City district.';
                        $this->redirect('/complete-profile');
                        return;
                    }

                    $distributor = new FeedDistributor($this->conn);
                    $distributor->create($user['id'], $business_name, $street, $barangay, $municipality, $city, $contact_number);
                    break;

                default:
                    // No additional data needed
                    break;
            }

            $_SESSION['success'] = 'Profile completed successfully!';
            $this->redirect('/dashboard');
        } catch (Exception $e) {
            $_SESSION['error'] = $e->getMessage();
            $this->redirect('/complete-profile');
        }
    }

    /**
     * Handle role selection after OTP verification
     */
    public function selectRole()
    {
        try {
            if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
                $this->redirect('/select-role');
                return;
            }

            // Get temp user info from session
            $user_id = $_SESSION['temp_user_id'] ?? null;
            $email = $_SESSION['temp_user_email'] ?? null;
            $name = $_SESSION['temp_user_name'] ?? null;

            if (!$user_id || !$email) {
                $_SESSION['error'] = 'Invalid session. Please register again.';
                $this->redirect('/register');
                return;
            }

            // Get selected role
            $role = trim($_POST['role'] ?? '');

            if (empty($role)) {
                $_SESSION['error'] = 'Please select a role';
                $this->redirect('/select-role');
                return;
            }

            // Validate role
            if (!isValidRole($role)) {
                $_SESSION['error'] = 'Invalid role selected';
                $this->redirect('/select-role');
                return;
            }

            // Update user role in database
            $query = "UPDATE users SET role = ? WHERE id = ?";
            $stmt = $this->conn->prepare($query);
            if (!$stmt) {
                throw new Exception('Database error: ' . $this->conn->error);
            }
            $stmt->bind_param('si', $role, $user_id);
            if (!$stmt->execute()) {
                throw new Exception('Failed to update role: ' . $stmt->error);
            }
            $stmt->close();

            // Get phone from database
            $query = "SELECT phone FROM users WHERE id = ?";
            $stmt = $this->conn->prepare($query);
            if (!$stmt) {
                throw new Exception('Database error: ' . $this->conn->error);
            }
            $stmt->bind_param('i', $user_id);
            $stmt->execute();
            $result = $stmt->get_result();
            $user_data = $result->fetch_assoc();
            $phone = $user_data['phone'] ?? null;
            $stmt->close();

            // Create user session
            $session = new Session();
            $session->setUser($user_id, $email, $name, $role, $phone);
            $session->clearOTP();

            // Clear temporary session data
            unset($_SESSION['temp_user_id']);
            unset($_SESSION['temp_user_email']);
            unset($_SESSION['temp_user_name']);
            unset($_SESSION['verification_email']);
            unset($_SESSION['verification_user_id']);

            $_SESSION['success'] = 'Welcome! You are now logged in as ' . ucfirst(str_replace('_', ' ', $role)) . '.';
            
            // Check if profile is complete for this role
            if ($this->isProfileComplete($user_id, $role)) {
                // Profile complete, go to dashboard
                $dashboard = getRoleDashboard($role);
                $this->redirect($dashboard);
            } else {
                // Profile incomplete, redirect to complete profile
                $_SESSION['success'] = 'Welcome! Now complete your profile to finish setup.';
                $this->redirect('/complete-profile');
            }
        } catch (Exception $e) {
            $_SESSION['error'] = 'Role selection failed: ' . $e->getMessage();
            $this->redirect('/select-role');
        }
    }

    /**
     * Redirect helper
     */
    private function redirect($path)
    {
        header('Location: ' . $this->baseUrl . $path);
        exit;
    }
}

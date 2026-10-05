    <?php

    /**
     * LechGO - Front Controller
     * 
     * Main entry point for the application
     * Routes all requests to appropriate controllers
     */

    // Define base paths FIRST - Adjusted for InfinityFree deployment
    // Since index.php is in htdocs root, BASE_PATH should be current directory
    define('BASE_PATH', dirname(__FILE__));
    define('APP_PATH', BASE_PATH . '/app');
    define('CONFIG_PATH', BASE_PATH . '/config');
    define('RESOURCES_PATH', BASE_PATH . '/resources');
    define('VIEWS_PATH', RESOURCES_PATH . '/views');

    // Enable error display for debugging
    ini_set('display_errors', 1);
    ini_set('display_startup_errors', 1);
    ini_set('log_errors', 1);
    ini_set('error_log', BASE_PATH . '/error.log');
    error_reporting(E_ALL);

    // Configure session settings for better persistence across redirects
    ini_set('session.cookie_lifetime', 86400); // 24 hours
    ini_set('session.gc_maxlifetime', 86400);
    ini_set('session.cookie_secure', isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on');
    ini_set('session.cookie_httponly', true);
    ini_set('session.cookie_samesite', 'Lax'); // Allow cross-site requests for payment redirects

    // Start session and initialize application
    session_start();

    // Load configuration and database
    $conn = require_once CONFIG_PATH . '/db.php';
    require_once CONFIG_PATH . '/email.php';
    require_once CONFIG_PATH . '/roles.php';

    // Make connection available globally to views
    $GLOBALS['conn'] = $conn;

    // Load core classes
    require_once APP_PATH . '/models/User.php';
    require_once APP_PATH . '/models/EmailVerification.php';
    require_once APP_PATH . '/models/OTP.php';
    require_once APP_PATH . '/models/Lechonero.php';
    require_once APP_PATH . '/models/FeedSupplier.php';
    require_once APP_PATH . '/models/PigCaretaker.php';
    require_once APP_PATH . '/models/LivestockOwner.php';
    require_once APP_PATH . '/models/FeedOrder.php';
    require_once APP_PATH . '/models/FeedOrderStatus.php';
    require_once APP_PATH . '/models/FeedReceipt.php';
    require_once APP_PATH . '/models/Notification.php';
    require_once APP_PATH . '/models/FeedDistributor.php';
    require_once APP_PATH . '/models/Review.php';
    require_once APP_PATH . '/controllers/AuthController.php';
    require_once APP_PATH . '/controllers/LocationController.php';
    require_once APP_PATH . '/services/EmailService.php';
    require_once APP_PATH . '/services/PayMongoService.php';
    require_once APP_PATH . '/middleware/Session.php';
    require_once APP_PATH . '/middleware/RBACMiddleware.php';

    // Parse request URI
    $request_uri = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);
    $base_url = '';

    // Make base_url available globally for views
    $GLOBALS['base_url'] = $base_url;

    // Initialize middleware
    $sessionMiddleware = new Session();
    $rbacMiddleware = new RBACMiddleware($sessionMiddleware, $base_url);
    
    // Get route from either clean URL or query parameter (fallback for InfinityFree)
    if (isset($_GET['__route']) && !empty($_GET['__route'])) {
        // Query string routing (fallback when .htaccess doesn't work)
        $route = trim($_GET['__route'], '/');
    } else {
        // Clean URL routing
        $route = str_replace($base_url, '', $request_uri);
        $route = trim($route, '/');
    }

    // DEBUG: Log what route we're processing
    error_log("LechGO Route: '{$route}' from URI: '{$request_uri}'");

    // Handle empty route
    if (empty($route)) {
        $route = 'landing';
    }

    // ── Transaction Log Helper ───────────────────────────────────────────────────
    function insertTransactionLog($conn, $order_id, $order_number, $livestock_owner_id, $supplier_id,
                                $supplier_name, $buyer_name, $feed_type, $product_name,
                                $quantity_kg, $unit_price, $subtotal, $purchase_date,
                                $payment_status = 'pending', $order_status = 'pending') {
        $stmt = $conn->prepare(
            "INSERT IGNORE INTO transaction_logs
            (order_id, order_number, livestock_owner_id, supplier_id, supplier_name, buyer_name,
            feed_type, product_name, quantity_kg, unit_price, subtotal, purchase_date, payment_status, order_status)
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)"
        );
        if (!$stmt) return;
        // Types: i=order_id, s=order_number, i=livestock_owner_id, i=supplier_id,
        //        s=supplier_name, s=buyer_name, s=feed_type, s=product_name,
        //        d=quantity_kg, d=unit_price, d=subtotal,
        //        s=purchase_date, s=payment_status, s=order_status  (14 total)
        $stmt->bind_param('isiissssdddsss',
            $order_id, $order_number, $livestock_owner_id, $supplier_id,
            $supplier_name, $buyer_name, $feed_type, $product_name,
            $quantity_kg, $unit_price, $subtotal, $purchase_date,
            $payment_status, $order_status
        );
        $stmt->execute();
        $stmt->close();
    }

    // Handle API routes BEFORE RBAC check
    if ($route === 'notifications') {
        require_once APP_PATH . '/controllers/NotificationController.php';
        exit;
    }

    // Handle API routes BEFORE RBAC check
    if ($route === 'notifications') {
        require_once APP_PATH . '/controllers/NotificationController.php';
        exit;
    }

    // Handle AJAX API endpoints that require authentication but not full RBAC
    if ($route === 'submit-review') {
        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            // Check authentication for API endpoints
            if (!$sessionMiddleware->isAuthenticated()) {
                header('Content-Type: application/json');
                echo json_encode(['success' => false, 'message' => 'Please log in to submit a review']);
                exit;
            }
            
            require_once APP_PATH . '/controllers/ReviewController.php';
            $reviewController = new ReviewController($conn);
            $reviewController->submitReview();
            exit;
        }
    }

    // Handle logout BEFORE RBAC check (logout should always work)
    if ($route === 'logout') {
        $authController = new AuthController($conn);
        $authController->logout();
        exit;
    }

    // Handle Budget Planner API routes BEFORE RBAC check
    if (strpos($route, 'api/budget-planner/') === 0) {
        // Suppress PHP errors for API routes — they would corrupt JSON output
        ini_set('display_errors', 0);
        ob_start(); // buffer any accidental output

        // Check authentication for API endpoints
        if (!$sessionMiddleware->isAuthenticated()) {
            ob_clean();
            header('Content-Type: application/json');
            http_response_code(401);
            echo json_encode(['success' => false, 'message' => 'Please log in to continue']);
            exit;
        }
        
        require_once APP_PATH . '/controllers/BudgetPlannerController.php';
        $controller = new BudgetPlannerController($conn);
        
        if ($route === 'api/budget-planner/save') {
            $controller->save();
            exit;
        } elseif ($route === 'api/budget-planner/records') {
            $controller->getRecords();
            exit;
        } elseif ($route === 'api/budget-planner/stats') {
            $controller->getStats();
            exit;
        } elseif ($route === 'api/budget-planner/lechon-spending') {
            $controller->getLechonSpending();
            exit;
        }
    }

    // Handle Market Intelligence API routes BEFORE RBAC check
    if (strpos($route, 'api/market-intelligence/') === 0) {
        // Check authentication for API endpoints
        if (!$sessionMiddleware->isAuthenticated()) {
            header('Content-Type: application/json');
            http_response_code(401);
            echo json_encode(['success' => false, 'message' => 'Please log in to continue']);
            exit;
        }
        
        require_once APP_PATH . '/controllers/MarketIntelligenceController.php';
        $controller = new MarketIntelligenceController($conn, $sessionMiddleware, $base_url);
        
        if ($route === 'api/market-intelligence/trends') {
            $controller->getTrends();
            exit;
        } elseif ($route === 'api/market-intelligence/trend') {
            $controller->getTrendDetails();
            exit;
        } elseif ($route === 'api/market-intelligence/pin') {
            $controller->pinTrend();
            exit;
        } elseif ($route === 'api/market-intelligence/unpin') {
            $controller->unpinTrend();
            exit;
        } elseif ($route === 'api/market-intelligence/pinned') {
            $controller->getPinnedTrends();
            exit;
        } elseif ($route === 'api/market-intelligence/calendar-save') {
            $controller->saveCalendarItem();
            exit;
        } elseif ($route === 'api/market-intelligence/calendar') {
            $controller->getCalendarMonth();
            exit;
        } elseif ($route === 'api/market-intelligence/calendar-delete') {
            $controller->deleteCalendarItem();
            exit;
        } elseif ($route === 'api/market-intelligence/note-save') {
            $controller->createNote();
            exit;
        } elseif ($route === 'api/market-intelligence/notes') {
            $controller->getNotes();
            exit;
        } elseif ($route === 'api/market-intelligence/note-update') {
            $controller->updateNote();
            exit;
        } elseif ($route === 'api/market-intelligence/note-delete') {
            $controller->deleteNote();
            exit;
        } elseif ($route === 'api/market-intelligence/refresh') {
            $controller->refreshTrends();
            exit;
        } elseif ($route === 'api/market-intelligence/last-update') {
            $controller->getLastUpdate();
            exit;
        } elseif ($route === 'api/market-intelligence/filter-date') {
            $controller->getTrendsByDateFilter();
            exit;
        }
    }

    // Check RBAC for protected routes before routing
    if (RBACMiddleware::isProtectedRoute($route)) {
        if (!$sessionMiddleware->isAuthenticated()) {
            $_SESSION['error'] = 'Please log in to continue';
            header('Location: ' . $base_url . '/login');
            exit;
        }
        // Verify the user has permission to access this route
        if (!$rbacMiddleware->canAccess($route)) {
            $_SESSION['error'] = 'You do not have permission to access this page';
            $rbacMiddleware->redirectToDashboard();
        }
    }

    // Route handler
    switch ($route) {
        // Database Setup (unprotected route)
        case 'setup-database':
            require 'setup-database.php';
            break;

        // Landing page
        case 'landing':
        case '':
            require VIEWS_PATH . '/landing.php';
            break;

        // About page (public - shows site legitimacy)
        case 'about':
            require VIEWS_PATH . '/about.php';
            break;

        // Authentication routes
        case 'login':
            if ($_SERVER['REQUEST_METHOD'] === 'GET') {
                require VIEWS_PATH . '/auth/login.php';
            }
            break;

        case 'register':
            if ($_SERVER['REQUEST_METHOD'] === 'GET') {
                require VIEWS_PATH . '/auth/register.php';
            }
            break;

        case 'auth/register':
            if ($_SERVER['REQUEST_METHOD'] === 'POST') {
                $authController = new AuthController($conn);
                $authController->register();
            } else {
                // Ensure GET on auth/register redirects to user-facing register page
                header('Location: ' . $base_url . '/register');
                exit;
            }
            break;

        case 'verify-email':
            if ($_SERVER['REQUEST_METHOD'] === 'GET' && isset($_GET['token'])) {
                $authController = new AuthController($conn);
                $authController->verifyEmail($_GET['token']);
            } else {
                require VIEWS_PATH . '/auth/verify-email.php';
            }
            break;

        case 'auth/verify-otp':
            if ($_SERVER['REQUEST_METHOD'] === 'POST') {
                $authController = new AuthController($conn);
                $authController->verifyOTP();
            }
            break;

        case 'verify-otp':
            if ($_SERVER['REQUEST_METHOD'] === 'GET') {
                require VIEWS_PATH . '/auth/verify-otp.php';
            }
            break;

        // DEPRECATED: Role selection page - no longer used
        /*
        case 'select-role':
            if ($_SERVER['REQUEST_METHOD'] === 'GET') {
                require VIEWS_PATH . '/auth/select-role.php';
            }
            break;

        case 'auth/select-role':
            if ($_SERVER['REQUEST_METHOD'] === 'POST') {
                $authController = new AuthController($conn);
                $authController->selectRole();
            }
            break;
        */

        case 'auth/login':
            if ($_SERVER['REQUEST_METHOD'] === 'POST') {
                $authController = new AuthController($conn);
                $authController->login();
            }
            break;

        case 'dashboard':
        case 'home':
            // RBAC check already done above
            require VIEWS_PATH . '/home.php';
            break;

        case 'market-intelligence':
            if ($_SERVER['REQUEST_METHOD'] === 'GET') {
                // RBAC check already done above
                require_once APP_PATH . '/controllers/MarketIntelligenceController.php';
                $marketIntelligenceController = new MarketIntelligenceController($conn, $sessionMiddleware, $base_url);
                $marketIntelligenceController->showPage();
            }
            break;

        case 'complete-profile':
            if ($_SERVER['REQUEST_METHOD'] === 'GET') {
                // RBAC check already done above
                require VIEWS_PATH . '/auth/complete-profile.php';
            }
            break;

        case 'auth/complete-profile':
            if ($_SERVER['REQUEST_METHOD'] === 'POST') {
                $authController = new AuthController($conn);
                $authController->completeProfile();
            }
            break;

        case 'auth/resend-verification':
            if ($_SERVER['REQUEST_METHOD'] === 'POST') {
                $authController = new AuthController($conn);
                $authController->resendVerificationEmail();
            }
            break;

        case 'auth/resend-otp':
            if ($_SERVER['REQUEST_METHOD'] === 'POST') {
                $authController = new AuthController($conn);
                $authController->resendOTP();
            }
            break;

        case 'api/locations':
            // API endpoint for location data
            $locationController = new LocationController($conn);
            $locationController->handleRequest();
            break;

        case 'debug':
            require 'debug.php';
            break;

        case 'locations':
            // RBAC check already done above
            require VIEWS_PATH . '/locations.php';
            break;

        case 'pig-caretaker/feed-inventory':
            if ($_SERVER['REQUEST_METHOD'] === 'GET') {
                // RBAC check already done above
                require VIEWS_PATH . '/pig_caretaker/feed_inventory.php';
            }
            break;

        case 'pig-caretaker/pigs':
            if ($_SERVER['REQUEST_METHOD'] === 'GET') {
                // RBAC check already done above
                require VIEWS_PATH . '/pig_caretaker/pig_inventory.php';
            }
            break;

        case 'pig-caretaker/view-pigs':
            if ($_SERVER['REQUEST_METHOD'] === 'GET') {
                require VIEWS_PATH . '/pig_caretaker/view_pig.php';
            }
            break;

        case 'pig-caretaker/feeding-schedule':
            if ($_SERVER['REQUEST_METHOD'] === 'GET') {
                // RBAC check already done above
                require VIEWS_PATH . '/pig_caretaker/feeding_schedule.php';
            }
            break;

        case 'pig-caretaker/farm-profile':
            if ($_SERVER['REQUEST_METHOD'] === 'GET') {
                // RBAC check already done above
                require VIEWS_PATH . '/pig_caretaker/farm_profile.php';
            }
            break;

        case 'pig-caretaker/add-feed':
            if ($_SERVER['REQUEST_METHOD'] === 'POST') {
                // RBAC check already done above
                
                $user = $sessionMiddleware->getUser();
                $pigCaretaker = new PigCaretaker($conn);
                
                if (!$pigCaretaker->findByUserId($user['id'])) {
                    $_SESSION['error'] = 'Farm data not found';
                    header('Location: ' . $base_url . '/pig-caretaker/feed-inventory');
                    exit;
                }

                try {
                    $feed_type = trim($_POST['feed_type'] ?? '');
                    $quantity_kg = floatval($_POST['quantity_kg'] ?? 0);
                    $unit_price = !empty($_POST['unit_price']) ? floatval($_POST['unit_price']) : null;
                    $supplier_name = trim($_POST['supplier_name'] ?? '');
                    $purchase_date = !empty($_POST['purchase_date']) ? $_POST['purchase_date'] : null;
                    $expiry_date = !empty($_POST['expiry_date']) ? $_POST['expiry_date'] : null;

                    if (empty($feed_type) || $quantity_kg <= 0) {
                        $_SESSION['error'] = 'Feed type and quantity are required';
                    } else {
                        $pigCaretaker->addFeedToInventory($feed_type, $quantity_kg, $unit_price, $supplier_name, $purchase_date, $expiry_date);
                        $_SESSION['success'] = 'Feed inventory added successfully!';
                    }
                } catch (Exception $e) {
                    $_SESSION['error'] = $e->getMessage();
                }

                header('Location: ' . $base_url . '/pig-caretaker/feed-inventory');
                exit;
            }
            break;

        case 'pig-caretaker/import-from-order':
            if ($_SERVER['REQUEST_METHOD'] === 'POST') {
                // RBAC check already done above
                header('Content-Type: application/json');

                try {
                    $user = $sessionMiddleware->getUser();
                    $order_id = intval($_POST['order_id'] ?? 0);

                    if (!$order_id) {
                        throw new Exception('Order ID is required');
                    }

                    $pigCaretaker = new PigCaretaker($conn);
                    if (!$pigCaretaker->findByUserId($user['id'])) {
                        throw new Exception('Farm profile not found');
                    }

                    // Get all items from this order
                    $query = "SELECT lfo.id, lfo.livestock_owner_id, lfo.supplier_id, lfo.created_at,
                                    s.farm_name as supplier_name,
                                    lfoi.product_name, lfoi.feed_type, lfoi.quantity_kg, lfoi.unit_price
                            FROM livestock_feed_orders lfo
                            LEFT JOIN livestock_feed_order_items lfoi ON lfo.id = lfoi.feed_order_id
                            LEFT JOIN suppliers s ON lfo.supplier_id = s.id
                            LEFT JOIN users u ON s.user_id = u.id
                            WHERE lfo.id = ?";
                    
                    $stmt = $conn->prepare($query);
                    if (!$stmt) {
                        throw new Exception('Database error: ' . $conn->error);
                    }

                    $stmt->bind_param('i', $order_id);
                    $stmt->execute();
                    $result = $stmt->get_result();
                    $order_items = $result->fetch_all(MYSQLI_ASSOC);
                    $stmt->close();

                    if (empty($order_items)) {
                        throw new Exception('Order not found');
                    }

                    $supplier_name = $order_items[0]['supplier_name'] ?? 'Unknown';
                    $purchase_date = $order_items[0]['created_at'] ? date('Y-m-d', strtotime($order_items[0]['created_at'])) : date('Y-m-d');
                    $imported_count = 0;

                    // Add each item to inventory
                    foreach ($order_items as $item) {
                        // Skip only if quantity_kg is missing/invalid
                        if (!isset($item['quantity_kg']) || $item['quantity_kg'] <= 0) {
                            continue;
                        }

                        // Use product_name as feed_type fallback if feed_type is empty or '0'
                        $feed_type = (!empty($item['feed_type']) && $item['feed_type'] !== '0')
                            ? $item['feed_type']
                            : ($item['product_name'] ?? 'Feed');

                        try {
                            $pigCaretaker->addFeedToInventory(
                                $feed_type,
                                $item['quantity_kg'],
                                $item['unit_price'] ?? null,
                                $supplier_name,
                                $purchase_date,
                                null,
                                $item['product_name'] ?? null
                            );
                            $imported_count++;
                        } catch (Exception $e) {
                            error_log('Feed import error: ' . $e->getMessage());
                        }
                    }

                    // Mark order as delivered so it no longer appears in the import list
                    $upd = $conn->prepare("UPDATE livestock_feed_orders SET order_status = 'delivered' WHERE id = ?");
                    if ($upd) {
                        $upd->bind_param('i', $order_id);
                        $upd->execute();
                        $upd->close();
                    }

                    echo json_encode([
                        'success' => true,
                        'message' => $imported_count . ' feed item(s) imported successfully!',
                        'count' => $imported_count
                    ]);
                    exit;

                } catch (Exception $e) {
                    http_response_code(400);
                    echo json_encode(['error' => $e->getMessage()]);
                    exit;
                }
            }
            break;

        case 'pig-caretaker/record-feeding':
            if ($_SERVER['REQUEST_METHOD'] === 'POST') {
                // RBAC check already done above
                
                try {
                    $user = $sessionMiddleware->getUser();
                    $cage_id = $_POST['cage_id'] ?? null;
                    $feed_inventory_id = $_POST['feed_inventory_id'] ?? null;
                    $feeding_date = $_POST['feeding_date'] ?? date('Y-m-d');
                    $feeding_time = $_POST['feeding_time'] ?? date('H:i');
                    $amount_kg = !empty($_POST['amount_kg']) ? (float)$_POST['amount_kg'] : 0;
                    $notes = $_POST['notes'] ?? '';
                    
                    if (!$cage_id) {
                        throw new Exception('Pin is required');
                    }
                    
                    // Get caretaker
                    $caretaker = new PigCaretaker($conn);
                    if (!$caretaker->findByUserId($user['id'])) {
                        throw new Exception('Caretaker profile not found');
                    }
                    
                    // Verify cage belongs to this caretaker
                    $query = "SELECT id FROM pig_pins WHERE id = ? AND caretaker_id = ?";
                    $stmt = $conn->prepare($query);
                    if (!$stmt) {
                        throw new Exception('Database error: ' . $conn->error);
                    }
                    $stmt->bind_param('ii', $cage_id, $caretaker->id);
                    $stmt->execute();
                    $cage_result = $stmt->get_result();
                    if (!$cage_result->fetch_assoc()) {
                        throw new Exception('Cage not found');
                    }
                    $stmt->close();
                    
                    // Insert feeding record
                    $query = "INSERT INTO feeding_schedule (caretaker_id, cage_id, feed_inventory_id, feeding_date, feeding_time, amount_kg, notes)
                            VALUES (?, ?, ?, ?, ?, ?, ?)";
                    $stmt = $conn->prepare($query);
                    if (!$stmt) {
                        throw new Exception('Database error: ' . $conn->error);
                    }
                    
                    $stmt->bind_param('iiiisds', $caretaker->id, $cage_id, $feed_inventory_id, $feeding_date, $feeding_time, $amount_kg, $notes);
                    if (!$stmt->execute()) {
                        throw new Exception('Error recording feeding: ' . $stmt->error);
                    }
                    $stmt->close();
                    
                    // Deduct from feed inventory and update status if low
                    if ($feed_inventory_id && $amount_kg > 0) {
                        $query = "UPDATE feed_inventory 
                                SET quantity_kg = quantity_kg - ?, 
                                    status = CASE WHEN (quantity_kg - ?) <= 5 THEN 'low_stock' ELSE status END
                                WHERE id = ? AND caretaker_id = ?";
                        $stmt = $conn->prepare($query);
                        if (!$stmt) {
                            throw new Exception('Database error updating inventory: ' . $conn->error);
                        }
                        $stmt->bind_param('ddii', $amount_kg, $amount_kg, $feed_inventory_id, $caretaker->id);
                        if (!$stmt->execute()) {
                            throw new Exception('Error updating feed inventory: ' . $stmt->error);
                        }
                        $stmt->close();
                    }
                    
                    $_SESSION['success'] = 'Feeding record saved and inventory updated!';
                    header('Location: ' . $base_url . '/pig-caretaker/feeding-schedule');
                    exit;
                    
                } catch (Exception $e) {
                    $_SESSION['error'] = 'Error: ' . $e->getMessage();
                    header('Location: ' . $base_url . '/pig-caretaker/feeding-schedule');
                    exit;
                }
            }
            break;



        // Livestock Owner - Order Feed from Supplier
        case 'livestock-owner/order-feed':
            if ($_SERVER['REQUEST_METHOD'] === 'POST') {
                // RBAC check already done above
                
                try {
                    $user = $sessionMiddleware->getUser();
                    $feed_id = intval($_POST['feed_id'] ?? 0);
                    $supplier_id = intval($_POST['supplier_id'] ?? 0);
                    $quantity = floatval($_POST['quantity'] ?? 0);
                    $unit_price = floatval($_POST['unit_price'] ?? 0);
                    $feed_type = $_POST['feed_type'] ?? '';
                    
                    if (!$feed_id || !$supplier_id || $quantity <= 0 || $unit_price <= 0) {
                        throw new Exception('Invalid order data');
                    }
                    
                    // Get or create livestock owner
                    $query = "SELECT * FROM livestock_owners WHERE user_id = ?";
                    $stmt = $conn->prepare($query);
                    if (!$stmt) {
                        throw new Exception('Database error: ' . $conn->error);
                    }
                    $stmt->bind_param('i', $user['id']);
                    $stmt->execute();
                    $result = $stmt->get_result();
                    $owner = $result->fetch_assoc();
                    $stmt->close();
                    
                    if (!$owner) {
                        throw new Exception('Livestock owner profile not found');
                    }
                    
                    // Create order
                    $total = $quantity * $unit_price;
                    $query = "INSERT INTO feed_orders (supplier_id, livestock_owner_id, total_amount, order_status, payment_status, payment_method, created_at)
                            VALUES (?, ?, ?, 'pending', 'unpaid', 'cash_on_delivery', NOW())";
                    $stmt = $conn->prepare($query);
                    if (!$stmt) {
                        throw new Exception('Database error');
                    }
                    
                    $stmt->bind_param('iid', $supplier_id, $owner['id'], $total);
                    if (!$stmt->execute()) {
                        throw new Exception('Error creating order');
                    }
                    
                    $order_id = $conn->insert_id;
                    $stmt->close();
                    
                    // Create order item
                    $item_query = "INSERT INTO feed_order_items (order_id, feed_id, quantity_kg, unit_price, subtotal)
                                VALUES (?, ?, ?, ?, ?)";
                    $item_stmt = $conn->prepare($item_query);
                    if (!$item_stmt) {
                        throw new Exception('Database error');
                    }
                    
                    $item_stmt->bind_param('iiddd', $order_id, $feed_id, $quantity, $unit_price, $total);
                    if (!$item_stmt->execute()) {
                        throw new Exception('Error creating order item');
                    }
                    $item_stmt->close();
                    
                    $_SESSION['success'] = "Order for {$feed_type} created successfully!";
                    header('Location: ' . $base_url . '/livestock-owner/my-orders');
                    exit;
                    
                } catch (Exception $e) {
                    $_SESSION['error'] = 'Error: ' . $e->getMessage();
                    header('Location: ' . $base_url . '/livestock-owner/available-feeds');
                    exit;
                }
            }
            break;

        // Livestock Owner - Browse Available Feeds
        case 'livestock-owner/available-feeds':
            if ($_SERVER['REQUEST_METHOD'] === 'GET') {
                // RBAC check already done above
                require VIEWS_PATH . '/livestock-owner/available_feeds.php';
            }
            break;

        // Livestock Owner - Add Product to Cart
        case 'livestock-owner/add-to-cart':
            if ($_SERVER['REQUEST_METHOD'] === 'POST') {
                // RBAC check already done above
                try {
                    $product_id = (int)($_POST['product_id'] ?? 0);
                    $supplier_id = (int)($_POST['supplier_id'] ?? 0);
                    $quantity_kg = (float)($_POST['quantity_kg'] ?? 0);
                    $unit_price = (float)($_POST['unit_price'] ?? 0);
                    $product_name = $_POST['product_name'] ?? '';
                    $feed_type = $_POST['feed_type'] ?? '';

                    if (!$product_id || $quantity_kg <= 0 || $unit_price <= 0) {
                        throw new Exception('Invalid product or quantity');
                    }

                    // Initialize cart in session if not exists
                    if (!isset($_SESSION['feed_cart'])) {
                        $_SESSION['feed_cart'] = [];
                    }

                    // Create cart key (product_id-supplier_id to group by supplier and product)
                    $cart_key = $product_id . '-' . $supplier_id;

                    // Add or update item in cart
                    if (isset($_SESSION['feed_cart'][$cart_key])) {
                        $_SESSION['feed_cart'][$cart_key]['quantity_kg'] += $quantity_kg;
                        $_SESSION['feed_cart'][$cart_key]['subtotal'] = $_SESSION['feed_cart'][$cart_key]['quantity_kg'] * $unit_price;
                    } else {
                        $_SESSION['feed_cart'][$cart_key] = [
                            'product_id' => $product_id,
                            'supplier_id' => $supplier_id,
                            'product_name' => $product_name,
                            'feed_type' => $feed_type,
                            'unit_price' => $unit_price,
                            'quantity_kg' => $quantity_kg,
                            'subtotal' => $quantity_kg * $unit_price
                        ];
                    }

                    $_SESSION['success'] = htmlspecialchars($product_name) . ' added to cart!';
                    header('Location: ' . $base_url . '/livestock-owner/checkout');
                    exit;

                } catch (Exception $e) {
                    $_SESSION['error'] = 'Error adding to cart: ' . $e->getMessage();
                    header('Location: ' . $base_url . '/livestock-owner/available-feeds');
                    exit;
                }
            }
            break;

        // Livestock Owner - Checkout
        case 'livestock-owner/checkout':
            if ($_SERVER['REQUEST_METHOD'] === 'GET') {
                // RBAC check already done above
                require VIEWS_PATH . '/livestock-owner/checkout.php';
            } elseif ($_SERVER['REQUEST_METHOD'] === 'POST') {
                // RBAC check already done above
                try {
                    $user = $sessionMiddleware->getUser();
                    $cart = $_SESSION['feed_cart'] ?? [];
                    $payment_method = $_POST['payment_method'] ?? '';

                    if (empty($cart)) {
                        throw new Exception('Cart is empty');
                    }

                    if (empty($payment_method)) {
                        throw new Exception('Please select a payment method');
                    }

                    // Get livestock owner ID and details
                    $query = "SELECT lo.id, lo.farm_name, u.name, u.email 
                            FROM livestock_owners lo
                            LEFT JOIN users u ON lo.user_id = u.id
                            WHERE lo.user_id = ?";
                    $stmt = $conn->prepare($query);
                    if (!$stmt) {
                        throw new Exception('Database error: ' . $conn->error);
                    }
                    $stmt->bind_param('i', $user['id']);
                    $stmt->execute();
                    $result = $stmt->get_result();
                    $owner = $result->fetch_assoc();
                    $stmt->close();

                    if (!$owner) {
                        throw new Exception('Livestock owner profile not found');
                    }

                    // Group items by supplier (so one order per supplier)
                    $orders_by_supplier = [];
                    foreach ($cart as $item) {
                        $supplier_id = $item['supplier_id'];
                        if (!isset($orders_by_supplier[$supplier_id])) {
                            $orders_by_supplier[$supplier_id] = [];
                        }
                        $orders_by_supplier[$supplier_id][] = $item;
                    }

                    $delivery_address = $_POST['delivery_address'] ?? '';

                    // Handle test payment (simulate successful payment)
                    if ($payment_method === 'test_payment') {
                        // Initialize FeedOrderStatus model
                        $feedOrderStatus = new FeedOrderStatus($conn);
                        
                        foreach ($orders_by_supplier as $supplier_id => $items) {
                            $total_amount = 0;
                            foreach ($items as $item) {
                                $total_amount += $item['subtotal'];
                            }

                            $order_number = 'LO-' . $owner['id'] . '-' . time() . '-' . $supplier_id;

                            // Insert order with paid payment status
                            $query = "INSERT INTO livestock_feed_orders (livestock_owner_id, supplier_id, order_number, order_status, payment_status, delivery_status, total_amount, delivery_address, payment_method, payment_reference)
                                    VALUES (?, ?, ?, 'pending', 'paid', 'pending', ?, ?, 'test_payment', ?)";
                            $stmt = $conn->prepare($query);
                            if (!$stmt) {
                                throw new Exception('Database error: ' . $conn->error);
                            }

                            $test_reference = 'TEST-' . time();
                            $stmt->bind_param('iisdss', $owner['id'], $supplier_id, $order_number, $total_amount, $delivery_address, $test_reference);
                            if (!$stmt->execute()) {
                                throw new Exception('Error creating order: ' . $stmt->error);
                            }
                            $order_id = $conn->insert_id;
                            $stmt->close();

                            // Log initial statuses to feed_order_status table
                            $feedOrderStatus->addStatus($order_id, 'order', 'pending', 'Order created via test payment', $user['id']);
                            $feedOrderStatus->addStatus($order_id, 'payment', 'paid', 'Test payment completed successfully', $user['id']);
                            $feedOrderStatus->addStatus($order_id, 'delivery', 'pending', 'Awaiting supplier confirmation', $user['id']);

                            // Send notification to supplier
                            $query_supplier = "SELECT user_id FROM suppliers WHERE id = ?";
                            $stmt_supplier = $conn->prepare($query_supplier);
                            if ($stmt_supplier) {
                                $stmt_supplier->bind_param('i', $supplier_id);
                                $stmt_supplier->execute();
                                $result_supplier = $stmt_supplier->get_result();
                                $supplier_data = $result_supplier->fetch_assoc();
                                $stmt_supplier->close();

                                if ($supplier_data) {
                                    $notification = new Notification($conn);
                                    $notification->create(
                                        $supplier_data['user_id'],
                                        'new_feed_order',
                                        'New Feed Order Received',
                                        'You have received a new feed order #' . $order_number . ' from ' . $owner['farm_name'] . '. Total: ₱' . number_format($total_amount, 2),
                                        '/supplier/order-details/' . $order_id
                                    );
                                }
                            }

                            // Insert order items + deduct supplier inventory
                            foreach ($items as $item) {
                                $query = "INSERT INTO livestock_feed_order_items (feed_order_id, feed_product_id, product_name, feed_type, quantity_kg, unit_price, subtotal)
                                        VALUES (?, ?, ?, ?, ?, ?, ?)";
                                $stmt = $conn->prepare($query);
                                if (!$stmt) {
                                    throw new Exception('Database error: ' . $conn->error);
                                }
                                $stmt->bind_param('iissddd', $order_id, $item['product_id'], $item['product_name'], $item['feed_type'], $item['quantity_kg'], $item['unit_price'], $item['subtotal']);
                                if (!$stmt->execute()) {
                                    throw new Exception('Error adding order item: ' . $stmt->error);
                                }
                                $stmt->close();

                                // Deduct from supplier's feed_products inventory
                                $deduct_stmt = $conn->prepare("UPDATE feed_products SET quantity_available_kg = GREATEST(0, quantity_available_kg - ?) WHERE id = ?");
                                if ($deduct_stmt) {
                                    $deduct_stmt->bind_param('di', $item['quantity_kg'], $item['product_id']);
                                    $deduct_stmt->execute();
                                    $deduct_stmt->close();
                                }

            // Log transaction
                                $sup_name_q = $conn->prepare("SELECT COALESCE(s.farm_name, u.name) as biz_name FROM suppliers s JOIN users u ON s.user_id = u.id WHERE s.id = ?");
                                $sup_name = 'Unknown Supplier';
                                if ($sup_name_q) {
                                    $sup_name_q->bind_param('i', $supplier_id);
                                    $sup_name_q->execute();
                                    $sup_row = $sup_name_q->get_result()->fetch_assoc();
                                    $sup_name_q->close();
                                    if ($sup_row) $sup_name = $sup_row['biz_name'];
                                }
                                insertTransactionLog(
                                    $conn, $order_id, $order_number,
                                    $owner['id'], $supplier_id,
                                    $sup_name, $owner['name'],
                                    $item['feed_type'], $item['product_name'],
                                    $item['quantity_kg'], $item['unit_price'], $item['subtotal'],
                                    date('Y-m-d H:i:s'), 'paid', 'pending'
                                );
                            }
                        }

                        // Clear cart
                        unset($_SESSION['feed_cart']);
                        $_SESSION['success'] = '✅ Test payment successful! Orders placed successfully!';
                        header('Location: ' . $base_url . '/livestock-owner/my-orders');
                        exit;
                    }

                    // If online payment, create payment intent immediately
                    if ($payment_method === 'online_payment') {
                        // Store pending orders for later (after payment)
                        $_SESSION['pending_orders'] = [
                            'orders_by_supplier' => $orders_by_supplier,
                            'owner' => $owner,
                            'delivery_address' => $delivery_address,
                            'payment_method' => $payment_method
                        ];

                        // Check if this is an AJAX request
                        $isAjax = !empty($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) === 'xmlhttprequest';
                        
                        if ($isAjax) {
                            // Return JSON for AJAX requests
                            header('Content-Type: application/json');
                            // For AJAX, just acknowledge the session was stored
                            echo json_encode([
                                'success' => true,
                                'message' => 'Orders saved. Ready to process payment.'
                            ]);
                            exit;
                        } else {
                            // For regular form submission, redirect to payment page
                            header('Location: ' . $base_url . '/livestock-owner/payment');
                            exit;
                        }
                    }

                    // For non-online payment, create orders immediately
                    foreach ($orders_by_supplier as $supplier_id => $items) {
                        $total_amount = 0;
                        foreach ($items as $item) {
                            $total_amount += $item['subtotal'];
                        }

                        // Generate order number
                        $order_number = 'LO-' . $owner['id'] . '-' . time();

                        // Insert order
                        $query = "INSERT INTO livestock_feed_orders (livestock_owner_id, supplier_id, order_number, order_status, payment_status, delivery_status, total_amount, delivery_address, payment_method)
                                VALUES (?, ?, ?, 'pending', 'unpaid', 'pending', ?, ?, ?)";
                        $stmt = $conn->prepare($query);
                        if (!$stmt) {
                            throw new Exception('Database error: ' . $conn->error);
                        }

                        $stmt->bind_param('iisdss', $owner['id'], $supplier_id, $order_number, $total_amount, $delivery_address, $payment_method);
                        if (!$stmt->execute()) {
                            throw new Exception('Error creating order: ' . $stmt->error);
                        }
                        $order_id = $conn->insert_id;
                        $stmt->close();

                        // Insert order items
                        foreach ($items as $item) {
                            $query = "INSERT INTO livestock_feed_order_items (feed_order_id, feed_product_id, product_name, feed_type, quantity_kg, unit_price, subtotal)
                                    VALUES (?, ?, ?, ?, ?, ?, ?)";
                            $stmt = $conn->prepare($query);
                            if (!$stmt) {
                                throw new Exception('Database error: ' . $conn->error);
                            }
                            $stmt->bind_param('iissddd', $order_id, $item['product_id'], $item['product_name'], $item['feed_type'], $item['quantity_kg'], $item['unit_price'], $item['subtotal']);
                            if (!$stmt->execute()) {
                                throw new Exception('Error adding order item: ' . $stmt->error);
                            }
                            $stmt->close();

                            // Deduct from supplier's feed_products inventory
                            $deduct_stmt = $conn->prepare("UPDATE feed_products SET quantity_available_kg = GREATEST(0, quantity_available_kg - ?) WHERE id = ?");
                            if ($deduct_stmt) {
                                $deduct_stmt->bind_param('di', $item['quantity_kg'], $item['product_id']);
                                $deduct_stmt->execute();
                                $deduct_stmt->close();
                            }

                            // Log transaction (COD / bank transfer)
                            $sup_name_q2 = $conn->prepare("SELECT COALESCE(s.farm_name, u.name) as biz_name FROM suppliers s JOIN users u ON s.user_id = u.id WHERE s.id = ?");
                            $sup_name2 = 'Unknown Supplier';
                            if ($sup_name_q2) {
                                $sup_name_q2->bind_param('i', $supplier_id);
                                $sup_name_q2->execute();
                                $sup_row2 = $sup_name_q2->get_result()->fetch_assoc();
                                $sup_name_q2->close();
                                if ($sup_row2) $sup_name2 = $sup_row2['biz_name'];
                            }
                            insertTransactionLog(
                                $conn, $order_id, $order_number,
                                $owner['id'], $supplier_id,
                                $sup_name2, $owner['name'],
                                $item['feed_type'], $item['product_name'],
                                $item['quantity_kg'], $item['unit_price'], $item['subtotal'],
                                date('Y-m-d H:i:s'), 'unpaid', 'pending'
                            );
                        }

                        // Send notification to supplier
                        $query_supplier = "SELECT user_id FROM suppliers WHERE id = ?";
                        $stmt_supplier = $conn->prepare($query_supplier);
                        if ($stmt_supplier) {
                            $stmt_supplier->bind_param('i', $supplier_id);
                            $stmt_supplier->execute();
                            $result_supplier = $stmt_supplier->get_result();
                            $supplier_data = $result_supplier->fetch_assoc();
                            $stmt_supplier->close();

                            if ($supplier_data) {
                                $notification = new Notification($conn);
                                $payment_method_label = $payment_method === 'cash_on_delivery' ? 'Cash on Delivery' : 'Bank Transfer';
                                $notification->create(
                                    $supplier_data['user_id'],
                                    'new_feed_order',
                                    'New Feed Order Received',
                                    'You have received a new feed order #' . $order_number . ' from ' . $owner['farm_name'] . '. Payment: ' . $payment_method_label . '. Total: ₱' . number_format($total_amount, 2),
                                    '/supplier/order-details/' . $order_id
                                );
                            }
                        }
                    }

                    // Clear cart
                    unset($_SESSION['feed_cart']);
                    $_SESSION['success'] = 'Orders placed successfully! Suppliers will review your order.';
                    header('Location: ' . $base_url . '/livestock-owner/my-orders');
                    exit;

                } catch (Exception $e) {
                    $_SESSION['error'] = 'Error placing order: ' . $e->getMessage();
                    header('Location: ' . $base_url . '/livestock-owner/checkout');
                    exit;
                }
            }
            break;

        // Livestock Owner - Payment Processing
        case 'livestock-owner/payment':
            if ($_SERVER['REQUEST_METHOD'] === 'GET') {
                // RBAC check already done above
                require VIEWS_PATH . '/livestock-owner/payment.php';
            } elseif ($_SERVER['REQUEST_METHOD'] === 'POST') {
                // RBAC check already done above
                try {
                    $pending_orders = $_SESSION['pending_orders'] ?? null;
                    if (!$pending_orders) {
                        throw new Exception('No pending orders found');
                    }

                    $orders_by_supplier = $pending_orders['orders_by_supplier'];
                    $owner = $pending_orders['owner'];
                    $delivery_address = $pending_orders['delivery_address'];
                    $payment_method = $pending_orders['payment_method'];

                    $payment_reference = $_POST['payment_reference'] ?? null;

                    // Create orders for each supplier
                    foreach ($orders_by_supplier as $supplier_id => $items) {
                        $total_amount = 0;
                        foreach ($items as $item) {
                            $total_amount += $item['subtotal'];
                        }

                        // Generate order number
                        $order_number = 'LO-' . $owner['id'] . '-' . time();

                        // Insert order with payment info
                        $query = "INSERT INTO livestock_feed_orders (livestock_owner_id, supplier_id, order_number, order_status, payment_status, delivery_status, total_amount, delivery_address, payment_method, payment_reference)
                                VALUES (?, ?, ?, 'pending', 'pending', 'pending', ?, ?, ?, ?)";
                        $stmt = $conn->prepare($query);
                        if (!$stmt) {
                            throw new Exception('Database error: ' . $conn->error);
                        }

                        $stmt->bind_param('iisdsss', $owner['id'], $supplier_id, $order_number, $total_amount, $delivery_address, $payment_method, $payment_reference);
                        if (!$stmt->execute()) {
                            throw new Exception('Error creating order: ' . $stmt->error);
                        }
                        $order_id = $conn->insert_id;
                        $stmt->close();

                        // Insert order items
                        foreach ($items as $item) {
                            $query = "INSERT INTO livestock_feed_order_items (feed_order_id, feed_product_id, product_name, feed_type, quantity_kg, unit_price, subtotal)
                                    VALUES (?, ?, ?, ?, ?, ?, ?)";
                            $stmt = $conn->prepare($query);
                            if (!$stmt) {
                                throw new Exception('Database error: ' . $conn->error);
                            }
                            $stmt->bind_param('iissddd', $order_id, $item['product_id'], $item['product_name'], $item['feed_type'], $item['quantity_kg'], $item['unit_price'], $item['subtotal']);
                            if (!$stmt->execute()) {
                                throw new Exception('Error adding order item: ' . $stmt->error);
                            }
                            $stmt->close();

                            // Deduct from supplier's feed_products inventory
                            $deduct_stmt = $conn->prepare("UPDATE feed_products SET quantity_available_kg = GREATEST(0, quantity_available_kg - ?) WHERE id = ?");
                            if ($deduct_stmt) {
                                $deduct_stmt->bind_param('di', $item['quantity_kg'], $item['product_id']);
                                $deduct_stmt->execute();
                                $deduct_stmt->close();
                            }
                        }
                    }

                    // Clear session and cart
                    unset($_SESSION['feed_cart']);
                    unset($_SESSION['pending_orders']);
                    $_SESSION['success'] = 'Payment processed! Order has been placed successfully.';
                    header('Location: ' . $base_url . '/livestock-owner/my-orders');
                    exit;

                } catch (Exception $e) {
                    $_SESSION['error'] = 'Error processing payment: ' . $e->getMessage();
                    header('Location: ' . $base_url . '/livestock-owner/payment');
                    exit;
                }
            }
            break;

        // Livestock Owner - Payment Success Page
        case 'livestock-owner/payment-success':
            if ($_SERVER['REQUEST_METHOD'] === 'GET') {
                // Just show the payment success page - the actual processing happens via AJAX
                require VIEWS_PATH . '/livestock-owner/payment-success.php';
            }
            break;

        // Livestock Owner - View My Orders
        case 'livestock-owner/my-orders':
            if ($_SERVER['REQUEST_METHOD'] === 'GET') {
                // RBAC check already done above
                require VIEWS_PATH . '/livestock-owner/my-orders.php';
            }
            break;

        case 'livestock-owner/transaction-logs':
            if ($_SERVER['REQUEST_METHOD'] === 'GET') {
                require VIEWS_PATH . '/livestock-owner/transaction-logs.php';
            }
            break;

        case 'livestock-owner/lechon-logs':
            if ($_SERVER['REQUEST_METHOD'] === 'GET') {
                // RBAC check already done above
                require VIEWS_PATH . '/livestock-owner/lechon-logs.php';
            }
            break;

        case 'livestock-owner/delivery-records':
            if ($_SERVER['REQUEST_METHOD'] === 'GET') {
                // RBAC check already done above
                require VIEWS_PATH . '/livestock-owner/delivery-records.php';
            }
            break;

        case 'livestock-owner/reviews':
            if ($_SERVER['REQUEST_METHOD'] === 'GET') {
                // RBAC check already done above
                require_once APP_PATH . '/controllers/ReviewController.php';
                $reviewController = new ReviewController($conn);
                $reviewController->sellerReviews();
            }
            break;

        case 'livestock-owner/inventory-logs':
            if ($_SERVER['REQUEST_METHOD'] === 'GET') {
                // RBAC check already done above
                require VIEWS_PATH . '/livestock-owner/inventory-logs.php';
            } elseif ($_SERVER['REQUEST_METHOD'] === 'POST') {
                // RBAC check already done above
                // Handle POST requests directly in the view file for simple updates
                require VIEWS_PATH . '/livestock-owner/inventory-logs.php';
            }
            break;

        case 'livestock-owner/save-inventory-log':
            if ($_SERVER['REQUEST_METHOD'] === 'POST') {
                // RBAC check already done above
                try {
                    $user = $sessionMiddleware->getUser();
                    
                    // Get livestock owner ID
                    $query = "SELECT id FROM livestock_owners WHERE user_id = ?";
                    $stmt = $conn->prepare($query);
                    $stmt->bind_param('i', $user['id']);
                    $stmt->execute();
                    $result = $stmt->get_result();
                    $owner = $result->fetch_assoc();
                    $stmt->close();
                    
                    if (!$owner) {
                        throw new Exception('Livestock owner profile not found');
                    }
                    
                    // Get form data
                    $log_id = !empty($_POST['log_id']) ? intval($_POST['log_id']) : null;
                    $feed_cost = floatval($_POST['feed_cost'] ?? 0);
                    $labor_cost = floatval($_POST['labor_cost'] ?? 0);
                    $veterinary_vaccines_cost = floatval($_POST['veterinary_vaccines_cost'] ?? 0);
                    $housing_utilities_labor_cost = floatval($_POST['housing_utilities_labor_cost'] ?? 0);
                    $other_cost = floatval($_POST['other_cost'] ?? 0);
                    $water_cost = floatval($_POST['water_cost'] ?? 0);
                    $electricity_cost = floatval($_POST['electricity_cost'] ?? 0);
                    $heart_girth_cm = !empty($_POST['heart_girth_cm']) ? floatval($_POST['heart_girth_cm']) : null;
                    $pig_length_cm = !empty($_POST['pig_length_cm']) ? floatval($_POST['pig_length_cm']) : null;
                    $total_variable_cost = floatval($_POST['total_variable_cost'] ?? 0);
                    $total_fixed_cost = floatval($_POST['total_fixed_cost'] ?? 0);
                    $total_opportunity_cost = floatval($_POST['total_opportunity_cost'] ?? 0);
                    $current_cost = !empty($_POST['current_cost']) ? floatval($_POST['current_cost']) : null;
                    $previous_cost = !empty($_POST['previous_cost']) ? floatval($_POST['previous_cost']) : null;
                    $batch_name = !empty($_POST['batch_name']) ? trim($_POST['batch_name']) : null;
                    $log_date = $_POST['log_date'] ?? date('Y-m-d');
                    $notes = !empty($_POST['notes']) ? trim($_POST['notes']) : null;
                    
                    // NEW: OPEX (Operating Expenses) fields
                    $biologics_cost = floatval($_POST['biologics_cost'] ?? 0);
                    $fuel_cost = floatval($_POST['fuel_cost'] ?? 0);
                    // labor_cost is already captured above from the form
                    
                    // NEW: CAPEX (Capital Expenditures) fields
                    $building_depreciation = floatval($_POST['building_depreciation'] ?? 0);
                    $equipment_depreciation = floatval($_POST['equipment_depreciation'] ?? 0);
                    $permits_taxes = floatval($_POST['permits_taxes'] ?? 0);
                    
                    // NEW: Opportunity Costs fields
                    $capital_opportunity_cost = floatval($_POST['capital_opportunity_cost'] ?? 0);
                    $land_rent_opportunity = floatval($_POST['land_rent_opportunity'] ?? 0);
                    
                    // NEW: Cost Comparison fields
                    $previous_cost_per_kg = floatval($_POST['previous_cost_per_kg'] ?? 0);
                    
                    // Get total live weight from pig_details
                    $total_live_weight = 0.0;
                    $pig_weight_query = "SELECT COALESCE(SUM(weight_kg), 0) as total_weight
                                        FROM pig_details
                                        WHERE weight_kg > 0";
                    $stmt = $conn->prepare($pig_weight_query);
                    if ($stmt) {
                        $stmt->execute();
                        $result = $stmt->get_result();
                        $weight_data = $result->fetch_assoc();
                        $total_live_weight = floatval($weight_data['total_weight'] ?? 0);
                        $stmt->close();
                    }
                    
                    // Calculate production costs
                    $total_variable_costs_tvc = $feed_cost + $biologics_cost + $fuel_cost + 
                                                $labor_cost + $water_cost + $electricity_cost;
                    $total_fixed_costs_tfc = $building_depreciation + $equipment_depreciation + $permits_taxes;
                    $total_opportunity_costs_toc = $capital_opportunity_cost + $land_rent_opportunity;
                    $total_production_cost = $total_variable_costs_tvc + $total_fixed_costs_tfc + $total_opportunity_costs_toc;
                    
                    // Calculate unit economics
                    $average_production_cost_apc = 0;
                    $current_cost_per_kg = 0;
                    if ($total_live_weight > 0) {
                        $average_production_cost_apc = $total_production_cost / $total_live_weight;
                        $current_cost_per_kg = $average_production_cost_apc;
                    }
                    
                    // Calculate cost variance
                    $cost_variance = $current_cost_per_kg - $previous_cost_per_kg;
                    $cost_variance_percent = 0;
                    if ($previous_cost_per_kg > 0) {
                        $cost_variance_percent = ($cost_variance / $previous_cost_per_kg) * 100;
                    }
                    
                    // Calculate weight if measurements provided
                    $calculated_weight = null;
                    if ($heart_girth_cm && $pig_length_cm) {
                        $calculated_weight = ($heart_girth_cm * $heart_girth_cm * $pig_length_cm) / 69.3;
                    }
                    
                    // Calculate total cost (no need to calculate manually - database handles it)
                    // But we keep this for validation purposes
                    $total_cost = $feed_cost + $labor_cost + $veterinary_vaccines_cost + $housing_utilities_labor_cost + $other_cost + $water_cost + $electricity_cost;
                    
                    // Calculate cost per kg
                    $cost_per_kg = null;
                    if ($calculated_weight && $calculated_weight > 0) {
                        $cost_per_kg = $total_cost / $calculated_weight;
                    }
                    
                    // Calculate APC (Average Production Cost)
                    $apc = null;
                    if ($calculated_weight && $calculated_weight > 0) {
                        $total_apc_cost = $total_variable_cost + $total_fixed_cost + $total_opportunity_cost;
                        if ($total_apc_cost > 0) {
                            $apc = $total_apc_cost / $calculated_weight;
                        }
                    }
                    
                    // Calculate percent change
                    $percent_change = null;
                    if ($current_cost !== null && $previous_cost !== null && $previous_cost > 0) {
                        $percent_change = (($current_cost - $previous_cost) / $previous_cost) * 100;
                    }
                    
                    // Check if UPDATE or INSERT
                    // First, check if there's already a log for this month and batch
                    if (!$log_id && $batch_name) {
                        $check_query = "SELECT id FROM inventory_logs 
                                       WHERE livestock_owner_id = ? 
                                       AND batch_name = ? 
                                       AND MONTH(log_date) = MONTH(?) 
                                       AND YEAR(log_date) = YEAR(?)
                                       LIMIT 1";
                        $check_stmt = $conn->prepare($check_query);
                        $check_stmt->bind_param('isss', $owner['id'], $batch_name, $log_date, $log_date);
                        $check_stmt->execute();
                        $check_result = $check_stmt->get_result();
                        if ($existing = $check_result->fetch_assoc()) {
                            $log_id = $existing['id']; // Found existing - will update instead
                        }
                        $check_stmt->close();
                    }
                    
                    if ($log_id) {
                        // UPDATE existing log - only update non-zero fields to preserve existing data
                        $updateFields = [];
                        $updateValues = [];
                        $updateTypes = '';
                        
                        // Always update these fields
                        $updateFields[] = "log_date = ?";
                        $updateValues[] = $log_date;
                        $updateTypes .= 's';
                        
                        $updateFields[] = "batch_name = ?";
                        $updateValues[] = $batch_name;
                        $updateTypes .= 's';
                        
                        // Only update if value is provided (non-zero)
                        if ($feed_cost > 0) {
                            $updateFields[] = "feed_cost = ?";
                            $updateValues[] = $feed_cost;
                            $updateTypes .= 'd';
                        }
                        if ($labor_cost > 0) {
                            $updateFields[] = "labor_cost = ?";
                            $updateValues[] = $labor_cost;
                            $updateTypes .= 'd';
                        }
                        if ($veterinary_vaccines_cost > 0) {
                            $updateFields[] = "veterinary_vaccines_cost = ?";
                            $updateValues[] = $veterinary_vaccines_cost;
                            $updateTypes .= 'd';
                        }
                        if ($housing_utilities_labor_cost > 0) {
                            $updateFields[] = "housing_utilities_labor_cost = ?";
                            $updateValues[] = $housing_utilities_labor_cost;
                            $updateTypes .= 'd';
                        }
                        if ($other_cost > 0) {
                            $updateFields[] = "other_cost = ?";
                            $updateValues[] = $other_cost;
                            $updateTypes .= 'd';
                        }
                        if ($water_cost > 0) {
                            $updateFields[] = "water_cost = ?";
                            $updateValues[] = $water_cost;
                            $updateTypes .= 'd';
                        }
                        if ($electricity_cost > 0) {
                            $updateFields[] = "electricity_cost = ?";
                            $updateValues[] = $electricity_cost;
                            $updateTypes .= 'd';
                        }
                        
                        // OPEX fields
                        if ($biologics_cost > 0) {
                            $updateFields[] = "biologics_cost = ?";
                            $updateValues[] = $biologics_cost;
                            $updateTypes .= 'd';
                        }
                        if ($fuel_cost > 0) {
                            $updateFields[] = "fuel_cost = ?";
                            $updateValues[] = $fuel_cost;
                            $updateTypes .= 'd';
                        }
                        // labor_cost is handled above with the basic cost fields
                        
                        // CAPEX fields
                        if ($building_depreciation > 0) {
                            $updateFields[] = "building_depreciation = ?";
                            $updateValues[] = $building_depreciation;
                            $updateTypes .= 'd';
                        }
                        if ($equipment_depreciation > 0) {
                            $updateFields[] = "equipment_depreciation = ?";
                            $updateValues[] = $equipment_depreciation;
                            $updateTypes .= 'd';
                        }
                        if ($permits_taxes > 0) {
                            $updateFields[] = "permits_taxes = ?";
                            $updateValues[] = $permits_taxes;
                            $updateTypes .= 'd';
                        }
                        
                        // TOC fields
                        if ($capital_opportunity_cost > 0) {
                            $updateFields[] = "capital_opportunity_cost = ?";
                            $updateValues[] = $capital_opportunity_cost;
                            $updateTypes .= 'd';
                        }
                        if ($land_rent_opportunity > 0) {
                            $updateFields[] = "land_rent_opportunity = ?";
                            $updateValues[] = $land_rent_opportunity;
                            $updateTypes .= 'd';
                        }
                        
                        // Fetch current values and recalculate totals
                        $fetch_query = "SELECT * FROM inventory_logs WHERE id = ?";
                        $fetch_stmt = $conn->prepare($fetch_query);
                        $fetch_stmt->bind_param('i', $log_id);
                        $fetch_stmt->execute();
                        $current = $fetch_stmt->get_result()->fetch_assoc();
                        $fetch_stmt->close();
                        
                        // Merge current with new values
                        $merged_feed = $feed_cost > 0 ? $feed_cost : ($current['feed_cost'] ?? 0);
                        $merged_biologics = $biologics_cost > 0 ? $biologics_cost : ($current['biologics_cost'] ?? 0);
                        $merged_fuel = $fuel_cost > 0 ? $fuel_cost : ($current['fuel_cost'] ?? 0);
                        $merged_labor = $labor_cost > 0 ? $labor_cost : ($current['labor_cost'] ?? 0);
                        $merged_water = $water_cost > 0 ? $water_cost : ($current['water_cost'] ?? 0);
                        $merged_electricity = $electricity_cost > 0 ? $electricity_cost : ($current['electricity_cost'] ?? 0);
                        
                        $merged_building = $building_depreciation > 0 ? $building_depreciation : ($current['building_depreciation'] ?? 0);
                        $merged_equipment = $equipment_depreciation > 0 ? $equipment_depreciation : ($current['equipment_depreciation'] ?? 0);
                        $merged_permits = $permits_taxes > 0 ? $permits_taxes : ($current['permits_taxes'] ?? 0);
                        
                        $merged_capital_opp = $capital_opportunity_cost > 0 ? $capital_opportunity_cost : ($current['capital_opportunity_cost'] ?? 0);
                        $merged_land_rent = $land_rent_opportunity > 0 ? $land_rent_opportunity : ($current['land_rent_opportunity'] ?? 0);
                        
                        // Recalculate totals
                        $recalc_tvc = $merged_feed + $merged_biologics + $merged_fuel + $merged_labor + $merged_water + $merged_electricity;
                        $recalc_tfc = $merged_building + $merged_equipment + $merged_permits;
                        $recalc_toc = $merged_capital_opp + $merged_land_rent;
                        $recalc_total = $recalc_tvc + $recalc_tfc + $recalc_toc;
                        
                        $updateFields[] = "total_variable_costs_tvc = ?";
                        $updateValues[] = $recalc_tvc;
                        $updateTypes .= 'd';
                        
                        $updateFields[] = "total_fixed_costs_tfc = ?";
                        $updateValues[] = $recalc_tfc;
                        $updateTypes .= 'd';
                        
                        $updateFields[] = "total_opportunity_costs_toc = ?";
                        $updateValues[] = $recalc_toc;
                        $updateTypes .= 'd';
                        
                        $updateFields[] = "total_production_cost = ?";
                        $updateValues[] = $recalc_total;
                        $updateTypes .= 'd';
                        
                        $updateFields[] = "final_weight_kg = ?";
                        $updateValues[] = $total_live_weight;
                        $updateTypes .= 'd';
                        
                        $updateFields[] = "total_live_weight_tw = ?";
                        $updateValues[] = $total_live_weight;
                        $updateTypes .= 'd';
                        
                        // Always update notes if provided
                        if ($notes) {
                            $updateFields[] = "notes = ?";
                            $updateValues[] = $notes;
                            $updateTypes .= 's';
                        }
                        
                        $query = "UPDATE inventory_logs SET " . implode(', ', $updateFields) . " WHERE id = ? AND livestock_owner_id = ?";
                        $updateValues[] = $log_id;
                        $updateValues[] = $owner['id'];
                        $updateTypes .= 'ii';
                        
                        $stmt = $conn->prepare($query);
                        if (!$stmt) {
                            throw new Exception('Database error: ' . $conn->error);
                        }
                        
                        $stmt->bind_param($updateTypes, ...$updateValues);
                        
                        if (!$stmt->execute()) {
                            throw new Exception('Failed to update inventory log: ' . $stmt->error);
                        }
                        
                        $_SESSION['success'] = 'Inventory log updated successfully!';
                    } else {
                        // INSERT new log
                        $query = "INSERT INTO inventory_logs (
                            livestock_owner_id, log_date, batch_name,
                            feed_cost, labor_cost, veterinary_vaccines_cost, housing_utilities_labor_cost, other_cost, water_cost, electricity_cost,
                            heart_girth_cm, pig_length_cm, calculated_live_weight_kg, manual_weight_kg, final_weight_kg,
                            cost_per_kg, total_variable_cost, total_fixed_cost, total_opportunity_cost, apc,
                            current_cost, previous_cost, percent_change, notes,
                            biologics_cost, fuel_cost, caretaker_labor_cost,
                            building_depreciation, equipment_depreciation, permits_taxes,
                            capital_opportunity_cost, land_rent_opportunity,
                            total_variable_costs_tvc, total_fixed_costs_tfc, total_opportunity_costs_toc,
                            total_production_cost, total_live_weight_tw, average_production_cost_apc,
                            current_cost_per_kg, previous_cost_per_kg, cost_variance, cost_variance_percent
                        ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)";
                        
                        $stmt = $conn->prepare($query);
                        if (!$stmt) {
                            throw new Exception('Database error: ' . $conn->error);
                        }
                        
                        // Force all nullable fields to proper types
                        $heart_girth_cm = $heart_girth_cm ?? 0.0;
                        $pig_length_cm = $pig_length_cm ?? 0.0;
                        $calculated_weight = $calculated_weight ?? 0.0;
                        $cost_per_kg = $cost_per_kg ?? 0.0;
                        $apc = $apc ?? 0.0;
                        $current_cost = $current_cost ?? 0.0;
                        $previous_cost = $previous_cost ?? 0.0;
                        $percent_change = $percent_change ?? 0.0;
                        $notes = $notes ?? '';
                        
                        // Type string: i=int, s=string, d=double/decimal
                        // 42 params: 1 int (owner_id) + 3 strings (log_date, batch_name, notes) + 38 decimals
                        $stmt->bind_param(
                            'issddddddddddddddddddddsdddddddddddddddddd',
                            $owner['id'],              // 1: i - livestock_owner_id
                            $log_date,                 // 2: s - log_date
                            $batch_name,               // 3: s - batch_name
                            $feed_cost,                // 4: d - feed_cost
                            $labor_cost,               // 5: d - labor_cost
                            $veterinary_vaccines_cost, // 6: d - veterinary_vaccines_cost
                            $housing_utilities_labor_cost, // 7: d - housing_utilities_labor_cost
                            $other_cost,               // 8: d - other_cost
                            $water_cost,               // 9: d - water_cost
                            $electricity_cost,         // 10: d - electricity_cost
                            $heart_girth_cm,           // 11: d - heart_girth_cm
                            $pig_length_cm,            // 12: d - pig_length_cm
                            $calculated_weight,        // 13: d - calculated_live_weight_kg
                            $calculated_weight,        // 14: d - manual_weight_kg
                            $total_live_weight,        // 15: d - final_weight_kg
                            $cost_per_kg,              // 16: d - cost_per_kg
                            $total_variable_cost,      // 17: d - total_variable_cost
                            $total_fixed_cost,         // 18: d - total_fixed_cost
                            $total_opportunity_cost,   // 19: d - total_opportunity_cost
                            $apc,                      // 20: d - apc
                            $current_cost,             // 21: d - current_cost
                            $previous_cost,            // 22: d - previous_cost
                            $percent_change,           // 23: d - percent_change
                            $notes,                    // 24: s - notes
                            $biologics_cost,           // 25: d - biologics_cost
                            $fuel_cost,                // 26: d - fuel_cost
                            $caretaker_labor_cost,     // 27: d - caretaker_labor_cost
                            $building_depreciation,    // 28: d - building_depreciation
                            $equipment_depreciation,   // 29: d - equipment_depreciation
                            $permits_taxes,            // 30: d - permits_taxes
                            $capital_opportunity_cost, // 31: d - capital_opportunity_cost
                            $land_rent_opportunity,    // 32: d - land_rent_opportunity
                            $total_variable_costs_tvc, // 33: d - total_variable_costs_tvc
                            $total_fixed_costs_tfc,    // 34: d - total_fixed_costs_tfc
                            $total_opportunity_costs_toc, // 35: d - total_opportunity_costs_toc
                            $total_production_cost,    // 36: d - total_production_cost
                            $total_live_weight,        // 37: d - total_live_weight_tw
                            $average_production_cost_apc, // 38: d - average_production_cost_apc
                            $current_cost_per_kg,      // 39: d - current_cost_per_kg
                            $previous_cost_per_kg,     // 40: d - previous_cost_per_kg
                            $cost_variance,            // 41: d - cost_variance
                            $cost_variance_percent     // 42: d - cost_variance_percent
                        );
                        
                        if (!$stmt->execute()) {
                            throw new Exception('Failed to save inventory log: ' . $stmt->error);
                        }
                        
                        $_SESSION['success'] = 'Inventory log saved successfully!';
                    }
                    
                    $stmt->close();
                    header('Location: ' . $base_url . '/livestock-owner/inventory-logs');
                    exit;
                    
                } catch (Exception $e) {
                    $_SESSION['error'] = 'Error: ' . $e->getMessage();
                    header('Location: ' . $base_url . '/livestock-owner/inventory-logs');
                    exit;
                }
            }
            break;

        case 'livestock-owner/delete-inventory-log':
            if ($_SERVER['REQUEST_METHOD'] === 'POST') {
                header('Content-Type: application/json');
                
                try {
                    $user = $sessionMiddleware->getUser();
                    
                    // Get livestock owner ID
                    $query = "SELECT id FROM livestock_owners WHERE user_id = ?";
                    $stmt = $conn->prepare($query);
                    $stmt->bind_param('i', $user['id']);
                    $stmt->execute();
                    $result = $stmt->get_result();
                    $owner = $result->fetch_assoc();
                    $stmt->close();
                    
                    if (!$owner) {
                        throw new Exception('Livestock owner profile not found');
                    }
                    
                    // Get log_id from POST
                    $log_id = intval($_POST['log_id'] ?? 0);
                    
                    if (!$log_id) {
                        throw new Exception('Invalid log ID');
                    }
                    
                    // Delete the inventory log (verify ownership)
                    $query = "DELETE FROM inventory_logs WHERE id = ? AND livestock_owner_id = ?";
                    $stmt = $conn->prepare($query);
                    
                    if (!$stmt) {
                        throw new Exception('Database error: ' . $conn->error);
                    }
                    
                    $stmt->bind_param('ii', $log_id, $owner['id']);
                    
                    if (!$stmt->execute()) {
                        throw new Exception('Failed to delete inventory log: ' . $stmt->error);
                    }
                    
                    $affected_rows = $stmt->affected_rows;
                    $stmt->close();
                    
                    if ($affected_rows === 0) {
                        throw new Exception('Inventory log not found or you do not have permission to delete it');
                    }
                    
                    echo json_encode([
                        'success' => true,
                        'message' => 'Inventory log deleted successfully'
                    ]);
                    exit;
                    
                } catch (Exception $e) {
                    echo json_encode([
                        'success' => false,
                        'message' => $e->getMessage()
                    ]);
                    exit;
                }
            }
            break;

        case 'livestock-owner/update-cooking-time':
            if ($_SERVER['REQUEST_METHOD'] === 'POST') {
                // RBAC check already done above
                header('Content-Type: application/json');
                
                try {
                    $input = json_decode(file_get_contents('php://input'), true);
                    $order_number = $input['order_number'] ?? '';
                    $type = $input['type'] ?? ''; // 'start' or 'end'
                    $time = $input['time'] ?? '';
                    
                    if (!$order_number || !$type) {
                        throw new Exception('Missing required parameters');
                    }
                    
                    // Allow empty time for optional fields (start/end cooking times)
                    if (empty($time)) {
                        if ($type === 'butcher_date' || $type === 'butcher_time') {
                            throw new Exception('Date and time to butcher are required');
                        }
                        // For optional fields (start/end), we'll allow empty values and skip saving
                        echo json_encode(['success' => true, 'message' => 'Empty time value - skipped']);
                        exit;
                    }
                    
                    // Validate format based on type
                    if ($type === 'butcher_date') {
                        // Validate date format (YYYY-MM-DD)
                        if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $time)) {
                            throw new Exception('Invalid date format: ' . $time);
                        }
                        // Additional validation: check if it's a valid date
                        $date_parts = explode('-', $time);
                        if (!checkdate($date_parts[1], $date_parts[2], $date_parts[0])) {
                            throw new Exception('Invalid date: ' . $time);
                        }
                    } else {
                        // Validate time format (HH:MM or HH:MM:SS)
                        if (!preg_match('/^([01]?[0-9]|2[0-3]):[0-5][0-9](:[0-5][0-9])?$/', $time)) {
                            throw new Exception('Invalid time format: ' . $time . ' (expected HH:MM or HH:MM:SS)');
                        }
                    }
                    
                    // Check if cooking_schedule table exists, if not create it
                    $check_table = $conn->query("SHOW TABLES LIKE 'cooking_schedule'");
                    if (!$check_table || $check_table->num_rows == 0) {
                        $create_table = "CREATE TABLE IF NOT EXISTS `cooking_schedule` (
                            `id` int(11) NOT NULL AUTO_INCREMENT,
                            `order_number` varchar(50) NOT NULL,
                            `start_time` time DEFAULT NULL,
                            `end_time` time DEFAULT NULL,
                            `date_to_butcher` date DEFAULT NULL,
                            `time_to_butcher` time DEFAULT NULL,
                            `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
                            `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
                            PRIMARY KEY (`id`),
                            UNIQUE KEY `unique_order` (`order_number`),
                            KEY `idx_order_number` (`order_number`),
                            KEY `idx_date_to_butcher` (`date_to_butcher`),
                            KEY `idx_butcher_time` (`time_to_butcher`)
                        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci";
                        
                        if (!$conn->query($create_table)) {
                            throw new Exception('Error creating cooking_schedule table: ' . $conn->error);
                        }
                    }
                    
                    // Update or insert cooking time
                    if ($type === 'start') {
                        $column = 'start_time';
                    } elseif ($type === 'end') {
                        $column = 'end_time';
                    } elseif ($type === 'butcher_date') {
                        $column = 'date_to_butcher';
                    } elseif ($type === 'butcher_time') {
                        $column = 'time_to_butcher';
                    } else {
                        throw new Exception('Invalid time type');
                    }
                    
                    $sql = "INSERT INTO cooking_schedule (order_number, {$column}) 
                            VALUES (?, ?) 
                            ON DUPLICATE KEY UPDATE {$column} = VALUES({$column})";
                    
                    $stmt = $conn->prepare($sql);
                    if (!$stmt) {
                        throw new Exception('Database prepare error: ' . $conn->error);
                    }
                    
                    $stmt->bind_param('ss', $order_number, $time);
                    
                    if ($stmt->execute()) {
                        echo json_encode(['success' => true, 'message' => 'Cooking time updated successfully']);
                    } else {
                        throw new Exception('Database error: ' . $stmt->error);
                    }
                    $stmt->close();
                    
                } catch (Exception $e) {
                    error_log("Cooking time update error: " . $e->getMessage());
                    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
                }
            }
            break;



        // Livestock Owner - View Order Receipt
        case preg_match('/^livestock-owner\/receipt\/\d+$/', $route) ? $route : null:
            if ($_SERVER['REQUEST_METHOD'] === 'GET') {
                // RBAC check already done above

                $route_parts = explode('/', $route);
                $order_id = intval(array_pop($route_parts));
                
                try {
                    // Get order details
                    $query = "SELECT lfo.*, 
                                    u.name AS supplier_name, u.email AS supplier_email,
                                    lo.farm_name AS owner_farm, lo.location AS owner_location
                            FROM livestock_feed_orders lfo
                            LEFT JOIN suppliers s ON lfo.supplier_id = s.id
                            LEFT JOIN users u ON s.user_id = u.id
                            LEFT JOIN livestock_owners lo ON lfo.livestock_owner_id = lo.id
                            WHERE lfo.id = ? AND lfo.livestock_owner_id = (SELECT id FROM livestock_owners WHERE user_id = ?)";
                    
                    $stmt = $conn->prepare($query);
                    if (!$stmt) {
                        throw new Exception('Database error: ' . $conn->error);
                    }

                    $user_id = $_SESSION['user']['id'];
                    $stmt->bind_param('ii', $order_id, $user_id);
                    $stmt->execute();
                    $result = $stmt->get_result();
                    $order = $result->fetch_assoc();
                    $stmt->close();

                    if (!$order) {
                        throw new Exception('Order not found');
                    }

                    // Get order items
                    $query = "SELECT * FROM livestock_feed_order_items WHERE feed_order_id = ?";
                    $stmt = $conn->prepare($query);
                    if (!$stmt) {
                        throw new Exception('Database error: ' . $conn->error);
                    }

                    $stmt->bind_param('i', $order_id);
                    $stmt->execute();
                    $result = $stmt->get_result();
                    $order_items = $result->fetch_all(MYSQLI_ASSOC);
                    $stmt->close();

                    // Store in globals for the view
                    $GLOBALS['receipt_order'] = $order;
                    $GLOBALS['receipt_items'] = $order_items;

                    require VIEWS_PATH . '/livestock-owner/receipt.php';
                } catch (Exception $e) {
                    $_SESSION['error'] = 'Error loading receipt: ' . $e->getMessage();
                    header('Location: ' . $base_url . '/livestock-owner/my-orders');
                    exit;
                }
            }
            break;

        // Livestock Owner - View Caretaker Reports
        case 'livestock-owner/caretaker-reports':
            if ($_SERVER['REQUEST_METHOD'] === 'GET') {
                // RBAC check already done above
                require VIEWS_PATH . '/livestock-owner/caretaker-reports.php';
            }
            break;

        // Livestock Owner - View Caretaker Feed Inventory
        case 'livestock-owner/caretaker-feed-inventory':
            if ($_SERVER['REQUEST_METHOD'] === 'GET') {
                // RBAC check already done above
                require VIEWS_PATH . '/livestock-owner/caretaker-feed-inventory.php';
            }
            break;

        // Livestock Owner - View Caretaker Pig Inventory
        case 'livestock-owner/caretaker-pig-inventory':
            if ($_SERVER['REQUEST_METHOD'] === 'GET') {
                // RBAC check already done above
                require VIEWS_PATH . '/livestock-owner/caretaker-pig-inventory.php';
            }
            break;

        // Livestock Owner - Manage Caretakers
        case 'livestock-owner/manage-caretakers':
            if ($_SERVER['REQUEST_METHOD'] === 'GET') {
                // RBAC check already done above
                require VIEWS_PATH . '/livestock-owner/manage-caretakers.php';
            }
            break;

        // Livestock Owner - Assign Caretaker
        case 'livestock-owner/assign-caretaker':
            if ($_SERVER['REQUEST_METHOD'] === 'POST') {
                // RBAC check already done above
                
                try {
                    $user = $sessionMiddleware->getUser();
                    $caretaker_id = isset($_POST['caretaker_id']) ? (int)$_POST['caretaker_id'] : 0;
                    
                    if (!$caretaker_id) {
                        throw new Exception('Invalid caretaker ID');
                    }
                    
                    // Load livestock owner
                    require_once APP_PATH . '/models/LivestockOwner.php';
                    $owner = new LivestockOwner($conn);
                    
                    if (!$owner->findByUserId($user['id'])) {
                        throw new Exception('Livestock owner profile not found');
                    }
                    
                    // Verify caretaker exists and is unassigned
                    $query = "SELECT id FROM pig_caretakers WHERE id = ? AND livestock_owner_id IS NULL";
                    $stmt = $conn->prepare($query);
                    if (!$stmt) {
                        throw new Exception('Database error: ' . $conn->error);
                    }
                    
                    $stmt->bind_param('i', $caretaker_id);
                    $stmt->execute();
                    $result = $stmt->get_result();
                    
                    if ($result->num_rows === 0) {
                        throw new Exception('Caretaker not found or already assigned');
                    }
                    
                    $stmt->close();
                    
                    // Assign caretaker
                    if ($owner->assignCaretaker($caretaker_id)) {
                        // Get caretaker user_id for notification
                        $query = "SELECT user_id, farm_name FROM pig_caretakers WHERE id = ?";
                        $stmt = $conn->prepare($query);
                        $stmt->bind_param('i', $caretaker_id);
                        $stmt->execute();
                        $result = $stmt->get_result();
                        $caretaker_data = $result->fetch_assoc();
                        $stmt->close();
                        
                        // Send notification to caretaker
                        if ($caretaker_data) {
                            require_once APP_PATH . '/models/Notification.php';
                            $notification = new Notification($conn);
                            $notification->create(
                                $caretaker_data['user_id'],
                                'caretaker_approved',
                                'Caretaker Request Approved',
                                "Your request to join '" . htmlspecialchars($owner->farm_name) . "' has been approved!",
                                '/dashboard'
                            );
                        }
                        
                        $_SESSION['success'] = 'Caretaker assigned successfully!';
                        header('Location: ' . $base_url . '/livestock-owner/manage-caretakers?success=' . urlencode('Caretaker assigned successfully!'));
                        exit;
                    } else {
                        throw new Exception('Failed to assign caretaker');
                    }
                } catch (Exception $e) {
                    $_SESSION['error'] = 'Error: ' . $e->getMessage();
                    header('Location: ' . $base_url . '/livestock-owner/manage-caretakers?error=' . urlencode($e->getMessage()));
                    exit;
                }
            }
            break;

        // Livestock Owner - Remove Caretaker
        case 'livestock-owner/remove-caretaker':
            if ($_SERVER['REQUEST_METHOD'] === 'POST') {
                // RBAC check already done above
                
                try {
                    $user = $sessionMiddleware->getUser();
                    $caretaker_id = isset($_POST['caretaker_id']) ? (int)$_POST['caretaker_id'] : 0;
                    
                    if (!$caretaker_id) {
                        throw new Exception('Invalid caretaker ID');
                    }
                    
                    // Load livestock owner
                    require_once APP_PATH . '/models/LivestockOwner.php';
                    $owner = new LivestockOwner($conn);
                    
                    if (!$owner->findByUserId($user['id'])) {
                        throw new Exception('Livestock owner profile not found');
                    }
                    
                    // Verify caretaker is assigned to this owner
                    $query = "SELECT id FROM pig_caretakers WHERE id = ? AND livestock_owner_id = ?";
                    $stmt = $conn->prepare($query);
                    if (!$stmt) {
                        throw new Exception('Database error: ' . $conn->error);
                    }
                    
                    $stmt->bind_param('ii', $caretaker_id, $owner->id);
                    $stmt->execute();
                    $result = $stmt->get_result();
                    
                    if ($result->num_rows === 0) {
                        throw new Exception('Caretaker not found or not assigned to your farm');
                    }
                    
                    $stmt->close();
                    
                    // Remove assignment
                    if ($owner->removeCaretaker($caretaker_id)) {
                        $_SESSION['success'] = 'Caretaker removed successfully!';
                        header('Location: ' . $base_url . '/livestock-owner/manage-caretakers?success=' . urlencode('Caretaker removed successfully!'));
                        exit;
                    } else {
                        throw new Exception('Failed to remove caretaker assignment');
                    }
                } catch (Exception $e) {
                    $_SESSION['error'] = 'Error: ' . $e->getMessage();
                    header('Location: ' . $base_url . '/livestock-owner/manage-caretakers?error=' . urlencode($e->getMessage()));
                    exit;
                }
            }
            break;

        // Livestock Owner - Reject Caretaker
        case 'livestock-owner/reject-caretaker':
            if ($_SERVER['REQUEST_METHOD'] === 'POST') {
                // RBAC check already done above
                
                try {
                    $user = $sessionMiddleware->getUser();
                    $caretaker_id = isset($_POST['caretaker_id']) ? (int)$_POST['caretaker_id'] : 0;
                    
                    if (!$caretaker_id) {
                        throw new Exception('Invalid caretaker ID');
                    }
                    
                    // Load livestock owner
                    require_once APP_PATH . '/models/LivestockOwner.php';
                    $owner = new LivestockOwner($conn);
                    
                    if (!$owner->findByUserId($user['id'])) {
                        throw new Exception('Livestock owner profile not found');
                    }
                    
                    // Verify caretaker exists and is unassigned
                    $query = "SELECT user_id FROM pig_caretakers WHERE id = ? AND livestock_owner_id IS NULL";
                    $stmt = $conn->prepare($query);
                    if (!$stmt) {
                        throw new Exception('Database error: ' . $conn->error);
                    }
                    
                    $stmt->bind_param('i', $caretaker_id);
                    $stmt->execute();
                    $result = $stmt->get_result();
                    
                    if ($result->num_rows === 0) {
                        throw new Exception('Caretaker not found or already assigned');
                    }
                    
                    $caretaker_data = $result->fetch_assoc();
                    $stmt->close();
                    
                    // Send rejection notification to caretaker
                    require_once APP_PATH . '/models/Notification.php';
                    $notification = new Notification($conn);
                    $notification->create(
                        $caretaker_data['user_id'],
                        'caretaker_rejected',
                        'Caretaker Request Rejected',
                        "Your request to join '" . htmlspecialchars($owner->farm_name) . "' has been rejected.",
                        '/dashboard'
                    );
                    
                    $_SESSION['success'] = 'Caretaker request rejected!';
                    header('Location: ' . $base_url . '/livestock-owner/manage-caretakers?success=' . urlencode('Caretaker request rejected!'));
                    exit;
                } catch (Exception $e) {
                    $_SESSION['error'] = 'Error: ' . $e->getMessage();
                    header('Location: ' . $base_url . '/livestock-owner/manage-caretakers?error=' . urlencode($e->getMessage()));
                    exit;
                }
            }
            break;

        // Pig Caretaker - Add Pig to Cage
        case 'pig-caretaker/add-pig':
            if ($_SERVER['REQUEST_METHOD'] === 'POST') {
                // RBAC check already done above
                
                try {
                    $user = $sessionMiddleware->getUser();
                    $cage_id = $_POST['cage_id'] ?? null;
                    $pig_tag_id = !empty($_POST['pig_tag_id']) ? $_POST['pig_tag_id'] : null;
                    $breed = $_POST['breed'] ?? 'Unknown';
                    $age_days = !empty($_POST['age_days']) ? (int)$_POST['age_days'] : 0;
                    $age_months = (int)round($age_days / 30); // keep age_months in sync for legacy display
                    $weight_kg = !empty($_POST['weight_kg']) ? (float)$_POST['weight_kg'] : 0.00;
                    $heart_girth = !empty($_POST['heart_girth']) ? (float)$_POST['heart_girth'] : null;
                    $body_length = !empty($_POST['body_length']) ? (float)$_POST['body_length'] : null;
                    $calculated_weight = !empty($_POST['calculated_weight']) ? (float)$_POST['calculated_weight'] : null;
                    $weight_source = $_POST['weight_source'] ?? 'manual';
                    $health_status = $_POST['health_status'] ?? 'healthy';
                    $date_added = $_POST['date_added'] ?? date('Y-m-d');
                    $notes = $_POST['notes'] ?? '';
                    
                    if (!$cage_id || empty($breed)) {
                        throw new Exception('Pin ID and breed are required');
                    }
                    
                    // Verify cage belongs to this caretaker and has space
                    $query = "SELECT pg.* FROM pig_pins pg
                            JOIN pig_caretakers pc ON pg.caretaker_id = pc.id
                            WHERE pg.id = ? AND pc.user_id = ?";
                    $stmt = $conn->prepare($query);
                    if (!$stmt) {
                        throw new Exception('Database error: ' . $conn->error);
                    }
                    $stmt->bind_param('ii', $cage_id, $user['id']);
                    $stmt->execute();
                    $cage_result = $stmt->get_result();
                    $cage = $cage_result->fetch_assoc();
                    $stmt->close();
                    
                    if (!$cage) {
                        throw new Exception('Cage not found');
                    }
                    
                    if ($cage['current_pig_count'] >= $cage['max_capacity']) {
                        throw new Exception('Cage is full');
                    }
                    
                    // Handle photo upload
                    $pig_photo = null;
                    if (!empty($_FILES['pig_photo']['tmp_name'])) {
                        $upload_dir = BASE_PATH . '/uploads/pigs/';
                        if (!is_dir($upload_dir)) mkdir($upload_dir, 0755, true);
                        $ext = strtolower(pathinfo($_FILES['pig_photo']['name'], PATHINFO_EXTENSION));
                        $allowed = ['jpg','jpeg','png','gif','webp'];
                        if (in_array($ext, $allowed)) {
                            $filename = 'pig_' . time() . '_' . rand(100,999) . '.' . $ext;
                            if (move_uploaded_file($_FILES['pig_photo']['tmp_name'], $upload_dir . $filename)) {
                                $pig_photo = '/uploads/pigs/' . $filename;
                            }
                        }
                    }

                    // Handle AIC upload
                    $aic_file = null;
                    if (!empty($_FILES['aic_file']['tmp_name'])) {
                        $upload_dir = BASE_PATH . '/uploads/pigs/docs/';
                        if (!is_dir($upload_dir)) mkdir($upload_dir, 0755, true);
                        $ext = strtolower(pathinfo($_FILES['aic_file']['name'], PATHINFO_EXTENSION));
                        $allowed_docs = ['jpg','jpeg','png','pdf'];
                        if (in_array($ext, $allowed_docs)) {
                            $filename = 'aic_' . time() . '_' . rand(100,999) . '.' . $ext;
                            if (move_uploaded_file($_FILES['aic_file']['tmp_name'], $upload_dir . $filename)) {
                                $aic_file = '/uploads/pigs/docs/' . $filename;
                            }
                        }
                    }

                    // Handle Barangay Cert upload
                    $brgy_cert_file = null;
                    if (!empty($_FILES['brgy_cert_file']['tmp_name'])) {
                        $upload_dir = BASE_PATH . '/uploads/pigs/docs/';
                        if (!is_dir($upload_dir)) mkdir($upload_dir, 0755, true);
                        $ext = strtolower(pathinfo($_FILES['brgy_cert_file']['name'], PATHINFO_EXTENSION));
                        $allowed_docs = ['jpg','jpeg','png','pdf'];
                        if (in_array($ext, $allowed_docs)) {
                            $filename = 'brgy_' . time() . '_' . rand(100,999) . '.' . $ext;
                            if (move_uploaded_file($_FILES['brgy_cert_file']['tmp_name'], $upload_dir . $filename)) {
                                $brgy_cert_file = '/uploads/pigs/docs/' . $filename;
                            }
                        }
                    }

                    // Insert pig
                    $query = "INSERT INTO pig_details (cage_id, pig_tag_id, age_months, age_days, weight_kg, heart_girth_cm, body_length_cm, calculated_weight_kg, weight_source, health_status, date_added, status, photo_url, aic_file, brgy_cert_file)
                            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 'active', ?, ?, ?)";
                    $stmt = $conn->prepare($query);
                    if (!$stmt) {
                        throw new Exception('Database error: ' . $conn->error);
                    }
                    
                    $stmt->bind_param('isiiddddssssss', $cage_id, $pig_tag_id, $age_months, $age_days, $weight_kg, $heart_girth, $body_length, $calculated_weight, $weight_source, $health_status, $date_added, $pig_photo, $aic_file, $brgy_cert_file);
                    if (!$stmt->execute()) {
                        throw new Exception('Error adding pig: ' . $stmt->error);
                    }
                    $stmt->close();
                    
                    // Update pin pig count and set active
                    $new_count = $cage['current_pig_count'] + 1;
                    $query = "UPDATE pig_pins SET current_pig_count = ?, status = 'active' WHERE id = ?";
                    $stmt = $conn->prepare($query);
                    if (!$stmt) {
                        throw new Exception('Database error: ' . $conn->error);
                    }
                    $stmt->bind_param('ii', $new_count, $cage_id);
                    $stmt->execute();
                    $stmt->close();
                    
                    $_SESSION['success'] = 'Pig added successfully!';
                    header('Location: ' . $base_url . '/pig-caretaker/pigs');
                    exit;
                    
                } catch (Exception $e) {
                    $_SESSION['error'] = 'Error: ' . $e->getMessage();
                    header('Location: ' . $base_url . '/pig-caretaker/pigs');
                    exit;
                }
            }
            break;

        // Pig Caretaker - Add Pin
        case 'pig-caretaker/add-pin':
            if ($_SERVER['REQUEST_METHOD'] === 'POST') {
                try {
                    $user = $sessionMiddleware->getUser();
                    
                    // Get pig caretaker ID
                    $query = "SELECT id FROM pig_caretakers WHERE user_id = ?";
                    $stmt = $conn->prepare($query);
                    $stmt->bind_param('i', $user['id']);
                    $stmt->execute();
                    $result = $stmt->get_result();
                    $caretaker = $result->fetch_assoc();
                    $stmt->close();
                    
                    if (!$caretaker) {
                        throw new Exception('Pig caretaker profile not found');
                    }
                    
                    $pin_number = strtoupper(trim($_POST['pin_number'] ?? ''));
                    $max_capacity = (int)($_POST['max_capacity'] ?? 3);
                    $status = $_POST['status'] ?? 'inactive';
                    
                    if (empty($pin_number)) {
                        throw new Exception('Pin number is required');
                    }
                    
                    // Check if pin number already exists for this caretaker
                    $query = "SELECT id FROM pig_pins WHERE caretaker_id = ? AND cage_number = ?";
                    $stmt = $conn->prepare($query);
                    $stmt->bind_param('is', $caretaker['id'], $pin_number);
                    $stmt->execute();
                    $existing = $stmt->get_result()->fetch_assoc();
                    $stmt->close();
                    
                    if ($existing) {
                        throw new Exception('Pin number already exists. Please use a different number.');
                    }
                    
                    // Insert new pin
                    $query = "INSERT INTO pig_pins (caretaker_id, cage_number, max_capacity, current_pig_count, status, created_at)
                            VALUES (?, ?, ?, 0, ?, NOW())";
                    $stmt = $conn->prepare($query);
                    if (!$stmt) {
                        throw new Exception('Database error: ' . $conn->error);
                    }
                    
                    $stmt->bind_param('isis', $caretaker['id'], $pin_number, $max_capacity, $status);
                    if (!$stmt->execute()) {
                        throw new Exception('Error creating pin: ' . $stmt->error);
                    }
                    $stmt->close();
                    
                    $_SESSION['success'] = 'Pin "' . $pin_number . '" added successfully!';
                    header('Location: ' . $base_url . '/pig-caretaker/pigs');
                    exit;
                    
                } catch (Exception $e) {
                    $_SESSION['error'] = 'Error: ' . $e->getMessage();
                    header('Location: ' . $base_url . '/pig-caretaker/pigs');
                    exit;
                }
            }
            break;

        // Pig Caretaker - Edit Pig
        case 'pig-caretaker/edit-pig':
            if ($_SERVER['REQUEST_METHOD'] === 'POST') {
                try {
                    $user     = $sessionMiddleware->getUser();
                    $pig_id   = (int)($_POST['pig_id'] ?? 0);
                    $age_days = (int)($_POST['age_days'] ?? 0);
                    $age_months = (int)round($age_days / 30);
                    $weight_kg     = !empty($_POST['weight_kg'])     ? (float)$_POST['weight_kg']     : 0;
                    $health_status = $_POST['health_status'] ?? 'healthy';
                    $date_added    = $_POST['date_added']    ?? date('Y-m-d');

                    if (!$pig_id) throw new Exception('Pig not found');

                    // Verify pig belongs to this caretaker
                    $stmt = $conn->prepare(
                        "SELECT pd.id, pd.photo_url FROM pig_details pd
                        INNER JOIN pig_pins pp ON pd.cage_id = pp.id
                        INNER JOIN pig_caretakers pc ON pp.caretaker_id = pc.id
                        WHERE pd.id = ? AND pc.user_id = ? AND pd.status = 'active'"
                    );
                    if (!$stmt) throw new Exception('DB error: ' . $conn->error);
                    $stmt->bind_param('ii', $pig_id, $user['id']);
                    $stmt->execute();
                    $existing = $stmt->get_result()->fetch_assoc();
                    $stmt->close();
                    if (!$existing) throw new Exception('Pig not found or access denied');

                    // Handle new photo upload
                    $photo_url = $existing['photo_url']; // keep existing by default
                    if (!empty($_FILES['pig_photo']['tmp_name'])) {
                        $upload_dir = BASE_PATH . '/uploads/pigs/';
                        if (!is_dir($upload_dir)) mkdir($upload_dir, 0755, true);
                        $ext = strtolower(pathinfo($_FILES['pig_photo']['name'], PATHINFO_EXTENSION));
                        $allowed = ['jpg','jpeg','png','gif','webp'];
                        if (!in_array($ext, $allowed)) throw new Exception('Invalid image type');
                        $filename = 'pig_' . time() . '_' . rand(100,999) . '.' . $ext;
                        if (move_uploaded_file($_FILES['pig_photo']['tmp_name'], $upload_dir . $filename)) {
                            // Delete old photo file if exists
                            if ($existing['photo_url']) {
                                $old = BASE_PATH . str_replace('/LechGo_Final', '', $existing['photo_url']);
                                if (file_exists($old)) @unlink($old);
                            }
                            $photo_url = '/uploads/pigs/' . $filename;
                        }
                    }

                    // Update
                    $stmt = $conn->prepare(
                        "UPDATE pig_details
                        SET age_days = ?, age_months = ?, weight_kg = ?, health_status = ?, date_added = ?, photo_url = ?
                        WHERE id = ?"
                    );
                    if (!$stmt) throw new Exception('DB error: ' . $conn->error);
                    $stmt->bind_param('iidsssi', $age_days, $age_months, $weight_kg, $health_status, $date_added, $photo_url, $pig_id);
                    if (!$stmt->execute()) throw new Exception('Update failed: ' . $stmt->error);
                    $stmt->close();

                    $_SESSION['success'] = 'Pig updated successfully!';
                } catch (Exception $e) {
                    $_SESSION['error'] = 'Error: ' . $e->getMessage();
                }
                header('Location: ' . $base_url . '/pig-caretaker/view-pigs');
                exit;
            }
            break;

        // Pig Caretaker - Delete Pig
        case 'pig-caretaker/delete-pig':
            if ($_SERVER['REQUEST_METHOD'] === 'POST') {
                try {
                    $user   = $sessionMiddleware->getUser();
                    $pig_id = (int)($_POST['pig_id'] ?? 0);

                    if (!$pig_id) throw new Exception('Pig not found');

                    // Verify pig belongs to this caretaker and get cage_id
                    $stmt = $conn->prepare(
                        "SELECT pd.id, pd.cage_id, pd.photo_url, pd.aic_file, pd.brgy_cert_file
                        FROM pig_details pd
                        INNER JOIN pig_pins pp ON pd.cage_id = pp.id
                        INNER JOIN pig_caretakers pc ON pp.caretaker_id = pc.id
                        WHERE pd.id = ? AND pc.user_id = ? AND pd.status = 'active'"
                    );
                    if (!$stmt) throw new Exception('DB error: ' . $conn->error);
                    $stmt->bind_param('ii', $pig_id, $user['id']);
                    $stmt->execute();
                    $pig = $stmt->get_result()->fetch_assoc();
                    $stmt->close();

                    if (!$pig) throw new Exception('Pig not found or access denied');

                    $cage_id = $pig['cage_id'];

                    // Soft-delete: mark pig as removed
                    $stmt = $conn->prepare("UPDATE pig_details SET status = 'removed' WHERE id = ?");
                    if (!$stmt) throw new Exception('DB error: ' . $conn->error);
                    $stmt->bind_param('i', $pig_id);
                    if (!$stmt->execute()) throw new Exception('Delete failed: ' . $stmt->error);
                    $stmt->close();

                    // Decrement pin count and set inactive if now empty
                    $stmt = $conn->prepare(
                        "UPDATE pig_pins
                        SET current_pig_count = GREATEST(current_pig_count - 1, 0),
                            status = CASE WHEN (current_pig_count - 1) <= 0 THEN 'inactive' ELSE 'active' END
                        WHERE id = ?"
                    );
                    if (!$stmt) throw new Exception('DB error: ' . $conn->error);
                    $stmt->bind_param('i', $cage_id);
                    $stmt->execute();
                    $stmt->close();

                    $_SESSION['success'] = 'Pig removed successfully.';
                } catch (Exception $e) {
                    $_SESSION['error'] = 'Error: ' . $e->getMessage();
                }
                header('Location: ' . $base_url . '/pig-caretaker/view-pigs');
                exit;
            }
            break;

        // Pig Caretaker Submit Report
        case 'pig-caretaker/submit-report':
            if ($_SERVER['REQUEST_METHOD'] === 'POST') {
                try {
                    $user = $sessionMiddleware->getUser();
                    $title          = trim($_POST['title'] ?? '');
                    $report_date    = $_POST['report_date'] ?? date('Y-m-d');
                    $overall_status = $_POST['overall_status'] ?? 'good';

                    if (empty($title)) {
                        throw new Exception('Report title is required');
                    }

                    $caretaker = new PigCaretaker($conn);
                    if (!$caretaker->findByUserId($user['id'])) {
                        throw new Exception('Caretaker profile not found');
                    }

                    // Save report header
                    $stmt = $conn->prepare(
                        "INSERT INTO swine_inventory (caretaker_id, title, overall_status, report_date, created_at)
                        VALUES (?, ?, ?, ?, NOW())"
                    );
                    if (!$stmt) throw new Exception('Database error: ' . $conn->error);
                    $stmt->bind_param('isss', $caretaker->id, $title, $overall_status, $report_date);
                    if (!$stmt->execute()) throw new Exception('Error saving report: ' . $stmt->error);
                    $report_id = $conn->insert_id;
                    $stmt->close();

                    // Save pig snapshots for this report
                    $pig_stmt = $conn->prepare(
                        "SELECT pd.id as pig_detail_id, pd.pig_tag_id, pp.cage_number as pin_number,
                                pd.age_months, pd.weight_kg, pd.health_status, pd.date_added,
                                pd.photo_url, pd.aic_file, pd.brgy_cert_file
                        FROM pig_details pd
                        JOIN pig_pins pp ON pd.cage_id = pp.id
                        WHERE pp.caretaker_id = ? AND pd.status = 'active'"
                    );
                    if ($pig_stmt) {
                        $pig_stmt->bind_param('i', $caretaker->id);
                        $pig_stmt->execute();
                        $pigs = $pig_stmt->get_result()->fetch_all(MYSQLI_ASSOC);
                        $pig_stmt->close();

                        // Insert each pig snapshot
                        $snapshot_stmt = $conn->prepare(
                            "INSERT INTO swine_inventory_snapshots 
                            (swine_inventory_id, pig_detail_id, pig_tag_id, pin_number, age_months, 
                            weight_kg, health_status, date_added, photo_url, aic_file, brgy_cert_file)
                            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)"
                        );
                        if ($snapshot_stmt) {
                            foreach ($pigs as $pig) {
                                $snapshot_stmt->bind_param(
                                    'iissidsssss',
                                    $report_id,
                                    $pig['pig_detail_id'],
                                    $pig['pig_tag_id'],
                                    $pig['pin_number'],
                                    $pig['age_months'],
                                    $pig['weight_kg'],
                                    $pig['health_status'],
                                    $pig['date_added'],
                                    $pig['photo_url'],
                                    $pig['aic_file'],
                                    $pig['brgy_cert_file']
                                );
                                $snapshot_stmt->execute();
                            }
                            $snapshot_stmt->close();
                        }
                    }

                    // Notify livestock owner
                    if ($caretaker->livestock_owner_id) {
                        $owner_stmt = $conn->prepare(
                            "SELECT lo.user_id, u.name as caretaker_name
                            FROM livestock_owners lo
                            JOIN pig_caretakers pc ON pc.livestock_owner_id = lo.id
                            JOIN users u ON pc.user_id = u.id
                            WHERE lo.id = ? LIMIT 1"
                        );
                        if ($owner_stmt) {
                            $owner_stmt->bind_param('i', $caretaker->livestock_owner_id);
                            $owner_stmt->execute();
                            $owner_data = $owner_stmt->get_result()->fetch_assoc();
                            $owner_stmt->close();

                            if ($owner_data) {
                                $status_label = ['good' => '✅ Good', 'concern' => '⚠️ Concern', 'critical' => '🚨 Critical'][$overall_status] ?? $overall_status;
                                $notification = new Notification($conn);
                                $notification->create(
                                    $owner_data['user_id'],
                                    'piggery_report',
                                    'New Piggery Status Report',
                                    $owner_data['caretaker_name'] . ' submitted a report: "' . $title . '" — Status: ' . $status_label,
                                    '/livestock-owner/caretaker-pig-inventory'
                                );
                            }
                        }
                    }

                    $_SESSION['success'] = 'Report submitted successfully!';
                    header('Location: ' . $base_url . '/pig-caretaker/view-pigs');
                    exit;

                } catch (Exception $e) {
                    $_SESSION['error'] = 'Error: ' . $e->getMessage();
                    header('Location: ' . $base_url . '/pig-caretaker/view-pigs');
                    exit;
                }
            }
            break;

        // Pig Caretaker Reports Page
        case 'pig-caretaker/reports':
            if ($_SERVER['REQUEST_METHOD'] === 'GET') {
                // RBAC check already done above
                require VIEWS_PATH . '/pig-caretaker/reports.php';
            }
            break;





        // ========== SUPPLIER - PRODUCT INVENTORY ==========
        
        // Supplier - Product Inventory
        case 'supplier/product-inventory':
            if ($_SERVER['REQUEST_METHOD'] === 'GET') {
                // RBAC check already done above
                require VIEWS_PATH . '/supplier/product-inventory.php';
            }
            break;

        case 'supplier/transaction-logs':
            if ($_SERVER['REQUEST_METHOD'] === 'GET') {
                require VIEWS_PATH . '/supplier/transaction-logs.php';
            }
            break;

        // Supplier - Feeds Market (browse Feed Distributor products)
        case 'supplier/feeds-market':
            if ($_SERVER['REQUEST_METHOD'] === 'GET') {
                require VIEWS_PATH . '/supplier/feeds-market.php';
            }
            break;

        // Supplier - Add Product
        case 'supplier/add-product':
            if ($_SERVER['REQUEST_METHOD'] === 'POST') {
                // RBAC check already done above
                try {
                    $user = $sessionMiddleware->getUser();
                    $product_name = $_POST['product_name'] ?? '';
                    $feed_type = $_POST['feed_type'] ?? '';
                    $unit_price = (float)($_POST['unit_price'] ?? 0);
                    $quantity_available_kg = (float)($_POST['quantity_available_kg'] ?? 0);
                    $description = $_POST['description'] ?? '';

                    if (!$product_name || !$feed_type || $unit_price <= 0 || $quantity_available_kg < 0) {
                        throw new Exception('Please fill in all required fields with valid values');
                    }

                    // Get supplier ID
                    $query = "SELECT id FROM suppliers WHERE user_id = ?";
                    $stmt = $conn->prepare($query);
                    if (!$stmt) {
                        throw new Exception('Database error: ' . $conn->error);
                    }
                    $stmt->bind_param('i', $user['id']);
                    $stmt->execute();
                    $result = $stmt->get_result();
                    $supplier = $result->fetch_assoc();
                    $stmt->close();

                    if (!$supplier) {
                        throw new Exception('Supplier profile not found');
                    }

                    $supplier_id = $supplier['id'];
                    $image_url = null;

                    // Handle image upload
                    if (isset($_FILES['product_image']) && $_FILES['product_image']['error'] === UPLOAD_ERR_OK) {
                        $upload_dir = __DIR__ . '/uploads/products/';
                        if (!is_dir($upload_dir)) {
                            mkdir($upload_dir, 0755, true);
                        }

                        $file_tmp = $_FILES['product_image']['tmp_name'];
                        $file_name = $_FILES['product_image']['name'];
                        $file_size = $_FILES['product_image']['size'];
                        
                        // Validate file
                        $max_size = 5 * 1024 * 1024; // 5MB
                        if ($file_size > $max_size) {
                            throw new Exception('Image file is too large. Maximum size is 5MB.');
                        }

                        $allowed_types = ['image/jpeg', 'image/png', 'image/gif', 'image/jpg'];
                        $file_type = mime_content_type($file_tmp);
                        if (!in_array($file_type, $allowed_types)) {
                            throw new Exception('Invalid image format. Allowed: JPG, PNG, GIF');
                        }

                        // Generate unique filename
                        $file_ext = pathinfo($file_name, PATHINFO_EXTENSION);
                        $unique_name = 'product_' . $supplier_id . '_' . time() . '.' . $file_ext;
                        $file_path = $upload_dir . $unique_name;

                        if (!move_uploaded_file($file_tmp, $file_path)) {
                            throw new Exception('Failed to upload image file');
                        }

                        $image_url = '/uploads/products/' . $unique_name;
                    }

                    // Insert product
                    $query = "INSERT INTO feed_products (supplier_id, product_name, feed_type, description, unit_price, quantity_available_kg, image_url, is_active)
                            VALUES (?, ?, ?, ?, ?, ?, ?, 1)";
                    $stmt = $conn->prepare($query);
                    if (!$stmt) {
                        throw new Exception('Database error: ' . $conn->error);
                    }
                    $stmt->bind_param('isssdds', $supplier_id, $product_name, $feed_type, $description, $unit_price, $quantity_available_kg, $image_url);
                    if (!$stmt->execute()) {
                        throw new Exception('Error adding product: ' . $stmt->error);
                    }
                    $stmt->close();

                    $_SESSION['success'] = 'Product added successfully!';
                    header('Location: ' . $base_url . '/supplier/product-inventory');
                    exit;

                } catch (Exception $e) {
                    $_SESSION['error'] = 'Error: ' . $e->getMessage();
                    header('Location: ' . $base_url . '/supplier/product-inventory');
                    exit;
                }
            }
            break;

        // Supplier - Delete Product
        case 'supplier/delete-product':
            if ($_SERVER['REQUEST_METHOD'] === 'POST') {
                // RBAC check already done above
                header('Content-Type: application/json');
                try {
                    $user = $sessionMiddleware->getUser();
                    $product_id = (int)($_POST['product_id'] ?? 0);

                    if ($product_id <= 0) {
                        throw new Exception('Invalid product ID');
                    }

                    // Verify the product belongs to this supplier
                    $query = "SELECT supplier_id FROM feed_products WHERE id = ?";
                    $stmt = $conn->prepare($query);
                    if (!$stmt) {
                        throw new Exception('Database error: ' . $conn->error);
                    }
                    $stmt->bind_param('i', $product_id);
                    $stmt->execute();
                    $result = $stmt->get_result();
                    $product = $result->fetch_assoc();
                    $stmt->close();

                    if (!$product) {
                        throw new Exception('Product not found');
                    }

                    // Get supplier ID
                    $query = "SELECT id FROM suppliers WHERE user_id = ?";
                    $stmt = $conn->prepare($query);
                    if (!$stmt) {
                        throw new Exception('Database error: ' . $conn->error);
                    }
                    $stmt->bind_param('i', $user['id']);
                    $stmt->execute();
                    $result = $stmt->get_result();
                    $supplier = $result->fetch_assoc();
                    $stmt->close();

                    if ($product['supplier_id'] != $supplier['id']) {
                        throw new Exception('Unauthorized: This product does not belong to your store');
                    }

                    // Delete product
                    $query = "DELETE FROM feed_products WHERE id = ? AND supplier_id = ?";
                    $stmt = $conn->prepare($query);
                    if (!$stmt) {
                        throw new Exception('Database error: ' . $conn->error);
                    }
                    $stmt->bind_param('ii', $product_id, $supplier['id']);
                    if (!$stmt->execute()) {
                        throw new Exception('Error deleting product: ' . $stmt->error);
                    }
                    $stmt->close();

                    header('Content-Type: application/json');
                    echo json_encode(['success' => true, 'message' => 'Product deleted successfully']);
                    exit;

                } catch (Exception $e) {
                    header('Content-Type: application/json');
                    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
                    exit;
                }
            }
            break;

        case 'supplier/get-product':
            if ($_SERVER['REQUEST_METHOD'] === 'GET') {
                // RBAC check already done above
                header('Content-Type: application/json');
                try {
                    $user = $sessionMiddleware->getUser();
                    $product_id = (int)($_GET['id'] ?? 0);

                    if ($product_id <= 0) {
                        throw new Exception('Invalid product ID');
                    }

                    // Get supplier ID
                    $query = "SELECT id FROM suppliers WHERE user_id = ?";
                    $stmt = $conn->prepare($query);
                    if (!$stmt) {
                        throw new Exception('Database error: ' . $conn->error);
                    }
                    $stmt->bind_param('i', $user['id']);
                    $stmt->execute();
                    $result = $stmt->get_result();
                    $supplier = $result->fetch_assoc();
                    $stmt->close();

                    if (!$supplier) {
                        throw new Exception('Supplier profile not found');
                    }

                    // Get product
                    $query = "SELECT id, product_name, feed_type, description, unit_price, quantity_available_kg, image_url FROM feed_products WHERE id = ? AND supplier_id = ?";
                    $stmt = $conn->prepare($query);
                    if (!$stmt) {
                        throw new Exception('Database error: ' . $conn->error);
                    }
                    $stmt->bind_param('ii', $product_id, $supplier['id']);
                    $stmt->execute();
                    $result = $stmt->get_result();
                    $product = $result->fetch_assoc();
                    $stmt->close();

                    if (!$product) {
                        throw new Exception('Product not found or you do not have access');
                    }

                    header('Content-Type: application/json');
                    echo json_encode(['success' => true, 'product' => $product]);
                    exit;

                } catch (Exception $e) {
                    header('Content-Type: application/json');
                    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
                    exit;
                }
            }
            break;

        case 'supplier/edit-product':
            if ($_SERVER['REQUEST_METHOD'] === 'POST') {
                // RBAC check already done above
                try {
                    $user = $sessionMiddleware->getUser();
                    $product_id = (int)($_POST['product_id'] ?? 0);
                    $product_name = $_POST['product_name'] ?? '';
                    $feed_type = $_POST['feed_type'] ?? '';
                    $unit_price = (float)($_POST['unit_price'] ?? 0);
                    $quantity_available_kg = (float)($_POST['quantity_available_kg'] ?? 0);
                    $description = $_POST['description'] ?? '';

                    if ($product_id <= 0 || !$product_name || !$feed_type || $unit_price <= 0 || $quantity_available_kg < 0) {
                        throw new Exception('Please fill in all required fields with valid values');
                    }

                    // Get supplier ID
                    $query = "SELECT id FROM suppliers WHERE user_id = ?";
                    $stmt = $conn->prepare($query);
                    if (!$stmt) {
                        throw new Exception('Database error: ' . $conn->error);
                    }
                    $stmt->bind_param('i', $user['id']);
                    $stmt->execute();
                    $result = $stmt->get_result();
                    $supplier = $result->fetch_assoc();
                    $stmt->close();

                    if (!$supplier) {
                        throw new Exception('Supplier profile not found');
                    }

                    // Verify product belongs to this supplier
                    $query = "SELECT image_url, unit_price FROM feed_products WHERE id = ? AND supplier_id = ?";
                    $stmt = $conn->prepare($query);
                    if (!$stmt) {
                        throw new Exception('Database error: ' . $conn->error);
                    }
                    $stmt->bind_param('ii', $product_id, $supplier['id']);
                    $stmt->execute();
                    $result = $stmt->get_result();
                    $existing_product = $result->fetch_assoc();
                    $stmt->close();

                    if (!$existing_product) {
                        throw new Exception('Product not found or unauthorized');
                    }

                    $image_url = $existing_product['image_url'];
                    // Track price change
                    $old_price = (float)$existing_product['unit_price'];
                    $price_changed = abs($old_price - $unit_price) > 0.001;

                    // Handle image upload
                    if (isset($_FILES['product_image']) && $_FILES['product_image']['error'] === UPLOAD_ERR_OK) {
                        $upload_dir = __DIR__ . '/uploads/products/';
                        if (!is_dir($upload_dir)) {
                            mkdir($upload_dir, 0755, true);
                        }

                        $file_tmp = $_FILES['product_image']['tmp_name'];
                        $file_name = $_FILES['product_image']['name'];
                        $file_size = $_FILES['product_image']['size'];
                        
                        // Validate file
                        $max_size = 5 * 1024 * 1024;
                        if ($file_size > $max_size) {
                            throw new Exception('Image file is too large. Maximum size is 5MB.');
                        }

                        $allowed_types = ['image/jpeg', 'image/png', 'image/gif', 'image/jpg'];
                        $file_type = mime_content_type($file_tmp);
                        if (!in_array($file_type, $allowed_types)) {
                            throw new Exception('Invalid image format. Allowed: JPG, PNG, GIF');
                        }

                        // Delete old image if exists
                        if ($image_url) {
                            $old_file = __DIR__ . str_replace('/LechGo_Final/public', '', $image_url);
                            if (file_exists($old_file)) {
                                unlink($old_file);
                            }
                        }

                        // Generate unique filename
                        $file_ext = pathinfo($file_name, PATHINFO_EXTENSION);
                        $unique_name = 'product_' . $supplier['id'] . '_' . time() . '.' . $file_ext;
                        $file_path = $upload_dir . $unique_name;

                        if (!move_uploaded_file($file_tmp, $file_path)) {
                            throw new Exception('Failed to upload image file');
                        }

                        $image_url = '/uploads/products/' . $unique_name;
                    }

                    // Update product — save previous price if it changed
                    if ($price_changed) {
                        $query = "UPDATE feed_products SET product_name = ?, feed_type = ?, description = ?, unit_price = ?, quantity_available_kg = ?, image_url = ?, previous_price = ?, price_updated_at = NOW() WHERE id = ? AND supplier_id = ?";
                        $stmt = $conn->prepare($query);
                        if (!$stmt) throw new Exception('Database error: ' . $conn->error);
                        $stmt->bind_param('sssddsdii', $product_name, $feed_type, $description, $unit_price, $quantity_available_kg, $image_url, $old_price, $product_id, $supplier['id']);
                    } else {
                        $query = "UPDATE feed_products SET product_name = ?, feed_type = ?, description = ?, unit_price = ?, quantity_available_kg = ?, image_url = ? WHERE id = ? AND supplier_id = ?";
                        $stmt = $conn->prepare($query);
                        if (!$stmt) throw new Exception('Database error: ' . $conn->error);
                        $stmt->bind_param('sssddsii', $product_name, $feed_type, $description, $unit_price, $quantity_available_kg, $image_url, $product_id, $supplier['id']);
                    }
                    if (!$stmt->execute()) {
                        throw new Exception('Error updating product: ' . $stmt->error);
                    }
                    $stmt->close();

                    $_SESSION['success'] = 'Product updated successfully!';
                    header('Location: ' . $base_url . '/supplier/product-inventory');
                    exit;

                } catch (Exception $e) {
                    $_SESSION['error'] = 'Error: ' . $e->getMessage();
                    header('Location: ' . $base_url . '/supplier/product-inventory');
                    exit;
                }
            }
            break;

        // ========== SUPPLIER - MY ORDERS ==========

        // Supplier - View Received Orders
        case 'supplier/my-orders':
        case 'supplier/orders':
            if ($_SERVER['REQUEST_METHOD'] === 'GET') {
                if (!$sessionMiddleware->isAuthenticated() || $sessionMiddleware->getUser()['role'] !== 'supplier') {
                    header('Location: ' . $base_url . '/login');
                    exit;
                }
                require VIEWS_PATH . '/supplier/orders.php';
            }
            break;

        // Supplier - View Order Details
        case (preg_match('/^supplier\/order-details\/(\d+)$/', $route, $matches) ? true : false):
            if ($_SERVER['REQUEST_METHOD'] === 'GET') {
                if (!$sessionMiddleware->isAuthenticated() || $sessionMiddleware->getUser()['role'] !== 'supplier') {
                    header('Location: ' . $base_url . '/login');
                    exit;
                }
                $order_id = $matches[1];
                require VIEWS_PATH . '/supplier/order_details.php';
            }
            break;

        case 'supplier/accept-order':
            if ($_SERVER['REQUEST_METHOD'] === 'POST') {
                if (!$sessionMiddleware->isAuthenticated() || $sessionMiddleware->getUser()['role'] !== 'supplier') {
                    header('Location: ' . $base_url . '/login');
                    exit;
                }

                try {
                    $order_id = intval($_POST['order_id'] ?? 0);
                    if (!$order_id) {
                        throw new Exception('Order ID is required');
                    }

                    // Get order details for notification
                    $query = "SELECT lfo.*, lo.user_id as owner_user_id, lo.farm_name, u.name as owner_name
                            FROM livestock_feed_orders lfo
                            LEFT JOIN livestock_owners lo ON lfo.livestock_owner_id = lo.id
                            LEFT JOIN users u ON lo.user_id = u.id
                            WHERE lfo.id = ?";
                    $stmt = $conn->prepare($query);
                    if (!$stmt) {
                        throw new Exception('Database error: ' . $conn->error);
                    }
                    $stmt->bind_param('i', $order_id);
                    $stmt->execute();
                    $result = $stmt->get_result();
                    $order = $result->fetch_assoc();
                    $stmt->close();

                    if (!$order) {
                        throw new Exception('Order not found');
                    }

                    // Update order status to confirmed
                    $query = "UPDATE livestock_feed_orders SET order_status = 'confirmed', updated_at = NOW() WHERE id = ?";
                    $stmt = $conn->prepare($query);
                    if (!$stmt) {
                        throw new Exception('Database error: ' . $conn->error);
                    }
                    $stmt->bind_param('i', $order_id);
                    if (!$stmt->execute()) {
                        throw new Exception('Error updating order: ' . $stmt->error);
                    }
                    $stmt->close();

                    // Log status change
                    $feedOrderStatus = new FeedOrderStatus($conn);
                    $feedOrderStatus->updateOrderStatus($order_id, 'confirmed', 'Order confirmed by supplier', $sessionMiddleware->getUser()['id']);

                    // Create receipt record in database
                    $feedReceipt = new FeedReceipt($conn);
                    $receiptResult = $feedReceipt->createFromOrder($order_id, $sessionMiddleware->getUser()['id']);
                    
                    if (!$receiptResult['success']) {
                        error_log('Failed to create receipt: ' . $receiptResult['error']);
                        // Don't fail the whole operation, just log the error
                    }

                    // Send notification to livestock owner
                    $notification = new Notification($conn);
                    $notification->create(
                        $order['owner_user_id'],
                        'order_confirmed',
                        'Order Confirmed!',
                        'Your feed order #' . $order['order_number'] . ' has been confirmed by the supplier. Total: ₱' . number_format($order['total_amount'], 2),
                        '/livestock-owner/receipt/' . $order_id
                    );

                    // Notify all pig caretakers under this livestock owner
                    $caretaker_query = $conn->prepare(
                        "SELECT pc.id, u.id as user_id, u.name 
                        FROM pig_caretakers pc 
                        JOIN users u ON pc.user_id = u.id 
                        WHERE pc.livestock_owner_id = ?"
                    );
                    if ($caretaker_query) {
                        $caretaker_query->bind_param('i', $order['livestock_owner_id']);
                        $caretaker_query->execute();
                        $caretakers = $caretaker_query->get_result()->fetch_all(MYSQLI_ASSOC);
                        $caretaker_query->close();

                        foreach ($caretakers as $caretaker) {
                            $notification->create(
                                $caretaker['user_id'],
                                'new_feed_available',
                                'New Feed Order Ready to Import',
                                'Your livestock owner\'s feed order #' . $order['order_number'] . ' has been confirmed by the supplier. Go to Feed Inventory → Import from Orders to add the feeds.',
                                '/pig-caretaker/feed-inventory'
                            );
                        }
                    }

                    // Check if AJAX request
                    $isAjax = !empty($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) === 'xmlhttprequest';
                    
                    if ($isAjax) {
                        header('Content-Type: application/json');
                        echo json_encode([
                            'success' => true,
                            'message' => 'Order confirmed successfully!',
                            'order_id' => $order_id,
                            'receipt_url' => '/supplier/receipt/' . $order_id,
                            'receipt_number' => $receiptResult['receipt_number'] ?? null
                        ]);
                        exit;
                    }

                    $_SESSION['success'] = '✓ Order accepted! Receipt generated and customer notified.';
                    header('Location: ' . $base_url . '/supplier/order-details/' . $order_id);
                    exit;

                } catch (Exception $e) {
                    $isAjax = !empty($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) === 'xmlhttprequest';
                    
                    if ($isAjax) {
                        header('Content-Type: application/json');
                        http_response_code(400);
                        echo json_encode(['success' => false, 'error' => $e->getMessage()]);
                        exit;
                    }

                    $_SESSION['error'] = 'Error accepting order: ' . $e->getMessage();
                    header('Location: ' . $base_url . '/supplier/orders');
                    exit;
                }
            }
            break;

        // ========== FEED DISTRIBUTOR ROUTES ==========

        case 'feed-distributor/product-inventory':
            if ($_SERVER['REQUEST_METHOD'] === 'GET') {
                require VIEWS_PATH . '/feed-distributor/product-inventory.php';
            }
            break;

        case 'feed-distributor/market':
            if ($_SERVER['REQUEST_METHOD'] === 'GET') {
                require VIEWS_PATH . '/feed-distributor/market.php';
            }
            break;

        case 'feed-distributor/orders':
            if ($_SERVER['REQUEST_METHOD'] === 'GET') {
                require VIEWS_PATH . '/feed-distributor/orders.php';
            }
            break;

        case 'feed-distributor/add-product':
            if ($_SERVER['REQUEST_METHOD'] === 'POST') {
                try {
                    $user = $sessionMiddleware->getUser();
                    $product_name = trim($_POST['product_name'] ?? '');
                    $feed_type = trim($_POST['feed_type'] ?? '');
                    $unit_price = (float)($_POST['unit_price'] ?? 0);
                    $quantity_available_kg = (float)($_POST['quantity_available_kg'] ?? 0);
                    $description = trim($_POST['description'] ?? '');

                    if (!$product_name || !$feed_type || $unit_price <= 0 || $quantity_available_kg < 0) {
                        throw new Exception('Please fill in all required fields with valid values');
                    }

                    $stmt = $conn->prepare("SELECT id FROM feed_distributors WHERE user_id = ?");
                    if (!$stmt) throw new Exception('Database error: ' . $conn->error);
                    $stmt->bind_param('i', $user['id']);
                    $stmt->execute();
                    $distributor = $stmt->get_result()->fetch_assoc();
                    $stmt->close();
                    if (!$distributor) throw new Exception('Distributor profile not found');

                    $distributor_id = $distributor['id'];
                    $image_url = null;

                    if (isset($_FILES['product_image']) && $_FILES['product_image']['error'] === UPLOAD_ERR_OK) {
                        $upload_dir = __DIR__ . '/uploads/products/';
                        if (!is_dir($upload_dir)) mkdir($upload_dir, 0755, true);
                        $file_tmp = $_FILES['product_image']['tmp_name'];
                        $file_size = $_FILES['product_image']['size'];
                        if ($file_size > 5 * 1024 * 1024) throw new Exception('Image too large. Max 5MB.');
                        $file_type = mime_content_type($file_tmp);
                        if (!in_array($file_type, ['image/jpeg','image/png','image/gif','image/jpg'])) throw new Exception('Invalid image format.');
                        $file_ext = pathinfo($_FILES['product_image']['name'], PATHINFO_EXTENSION);
                        $unique_name = 'fdprod_' . $distributor_id . '_' . time() . '.' . $file_ext;
                        if (!move_uploaded_file($file_tmp, $upload_dir . $unique_name)) throw new Exception('Failed to upload image.');
                        $image_url = '/uploads/products/' . $unique_name;
                    }

                    $stmt = $conn->prepare("INSERT INTO feed_distributor_products (distributor_id, product_name, feed_type, description, unit_price, quantity_available_kg, image_url, is_active) VALUES (?, ?, ?, ?, ?, ?, ?, 1)");
                    if (!$stmt) throw new Exception('Database error: ' . $conn->error);
                    $stmt->bind_param('isssdds', $distributor_id, $product_name, $feed_type, $description, $unit_price, $quantity_available_kg, $image_url);
                    if (!$stmt->execute()) throw new Exception('Error adding product: ' . $stmt->error);
                    $stmt->close();

                    $_SESSION['success'] = 'Product added successfully!';
                    header('Location: ' . $base_url . '/feed-distributor/product-inventory');
                    exit;
                } catch (Exception $e) {
                    $_SESSION['error'] = 'Error: ' . $e->getMessage();
                    header('Location: ' . $base_url . '/feed-distributor/product-inventory');
                    exit;
                }
            }
            break;

        case 'feed-distributor/get-product':
            if ($_SERVER['REQUEST_METHOD'] === 'GET') {
                header('Content-Type: application/json');
                try {
                    $user = $sessionMiddleware->getUser();
                    $product_id = (int)($_GET['id'] ?? 0);
                    if ($product_id <= 0) throw new Exception('Invalid product ID');

                    $stmt = $conn->prepare("SELECT id FROM feed_distributors WHERE user_id = ?");
                    if (!$stmt) throw new Exception('Database error');
                    $stmt->bind_param('i', $user['id']);
                    $stmt->execute();
                    $distributor = $stmt->get_result()->fetch_assoc();
                    $stmt->close();
                    if (!$distributor) throw new Exception('Distributor profile not found');

                    $stmt = $conn->prepare("SELECT id, product_name, feed_type, description, unit_price, quantity_available_kg, image_url FROM feed_distributor_products WHERE id = ? AND distributor_id = ?");
                    if (!$stmt) throw new Exception('Database error');
                    $stmt->bind_param('ii', $product_id, $distributor['id']);
                    $stmt->execute();
                    $product = $stmt->get_result()->fetch_assoc();
                    $stmt->close();
                    if (!$product) throw new Exception('Product not found');

                    echo json_encode(['success' => true, 'product' => $product]);
                    exit;
                } catch (Exception $e) {
                    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
                    exit;
                }
            }
            break;

        case 'feed-distributor/edit-product':
            if ($_SERVER['REQUEST_METHOD'] === 'POST') {
                try {
                    $user = $sessionMiddleware->getUser();
                    $product_id = (int)($_POST['product_id'] ?? 0);
                    $product_name = trim($_POST['product_name'] ?? '');
                    $feed_type = trim($_POST['feed_type'] ?? '');
                    $unit_price = (float)($_POST['unit_price'] ?? 0);
                    $quantity_available_kg = (float)($_POST['quantity_available_kg'] ?? 0);
                    $description = trim($_POST['description'] ?? '');

                    if ($product_id <= 0 || !$product_name || !$feed_type || $unit_price <= 0 || $quantity_available_kg < 0) {
                        throw new Exception('Please fill in all required fields');
                    }

                    $stmt = $conn->prepare("SELECT id FROM feed_distributors WHERE user_id = ?");
                    if (!$stmt) throw new Exception('Database error');
                    $stmt->bind_param('i', $user['id']);
                    $stmt->execute();
                    $distributor = $stmt->get_result()->fetch_assoc();
                    $stmt->close();
                    if (!$distributor) throw new Exception('Distributor profile not found');

                    $stmt = $conn->prepare("SELECT image_url FROM feed_distributor_products WHERE id = ? AND distributor_id = ?");
                    if (!$stmt) throw new Exception('Database error');
                    $stmt->bind_param('ii', $product_id, $distributor['id']);
                    $stmt->execute();
                    $existing = $stmt->get_result()->fetch_assoc();
                    $stmt->close();
                    if (!$existing) throw new Exception('Product not found or unauthorized');

                    $image_url = $existing['image_url'];

                    if (isset($_FILES['product_image']) && $_FILES['product_image']['error'] === UPLOAD_ERR_OK) {
                        $upload_dir = __DIR__ . '/uploads/products/';
                        if (!is_dir($upload_dir)) mkdir($upload_dir, 0755, true);
                        $file_tmp = $_FILES['product_image']['tmp_name'];
                        if ($_FILES['product_image']['size'] > 5 * 1024 * 1024) throw new Exception('Image too large. Max 5MB.');
                        $file_type = mime_content_type($file_tmp);
                        if (!in_array($file_type, ['image/jpeg','image/png','image/gif','image/jpg'])) throw new Exception('Invalid image format.');
                        if ($image_url) { $old = __DIR__ . str_replace('/LechGo_Final/public', '', $image_url); if (file_exists($old)) unlink($old); }
                        $file_ext = pathinfo($_FILES['product_image']['name'], PATHINFO_EXTENSION);
                        $unique_name = 'fdprod_' . $distributor['id'] . '_' . time() . '.' . $file_ext;
                        if (!move_uploaded_file($file_tmp, $upload_dir . $unique_name)) throw new Exception('Failed to upload image.');
                        $image_url = '/uploads/products/' . $unique_name;
                    }

                    $stmt = $conn->prepare("UPDATE feed_distributor_products SET product_name=?, feed_type=?, description=?, unit_price=?, quantity_available_kg=?, image_url=? WHERE id=? AND distributor_id=?");
                    if (!$stmt) throw new Exception('Database error');
                    $stmt->bind_param('sssddsii', $product_name, $feed_type, $description, $unit_price, $quantity_available_kg, $image_url, $product_id, $distributor['id']);
                    if (!$stmt->execute()) throw new Exception('Error updating product: ' . $stmt->error);
                    $stmt->close();

                    $_SESSION['success'] = 'Product updated successfully!';
                    header('Location: ' . $base_url . '/feed-distributor/product-inventory');
                    exit;
                } catch (Exception $e) {
                    $_SESSION['error'] = 'Error: ' . $e->getMessage();
                    header('Location: ' . $base_url . '/feed-distributor/product-inventory');
                    exit;
                }
            }
            break;

        case 'feed-distributor/delete-product':
            if ($_SERVER['REQUEST_METHOD'] === 'POST') {
                header('Content-Type: application/json');
                try {
                    $user = $sessionMiddleware->getUser();
                    $product_id = (int)($_POST['product_id'] ?? 0);
                    if ($product_id <= 0) throw new Exception('Invalid product ID');

                    $stmt = $conn->prepare("SELECT id FROM feed_distributors WHERE user_id = ?");
                    if (!$stmt) throw new Exception('Database error');
                    $stmt->bind_param('i', $user['id']);
                    $stmt->execute();
                    $distributor = $stmt->get_result()->fetch_assoc();
                    $stmt->close();
                    if (!$distributor) throw new Exception('Distributor profile not found');

                    $stmt = $conn->prepare("DELETE FROM feed_distributor_products WHERE id = ? AND distributor_id = ?");
                    if (!$stmt) throw new Exception('Database error');
                    $stmt->bind_param('ii', $product_id, $distributor['id']);
                    if (!$stmt->execute()) throw new Exception('Error deleting product');
                    $stmt->close();

                    echo json_encode(['success' => true, 'message' => 'Product deleted successfully']);
                    exit;
                } catch (Exception $e) {
                    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
                    exit;
                }
            }
            break;

        case 'feed-distributor/update-price':
            if ($_SERVER['REQUEST_METHOD'] === 'POST') {
                header('Content-Type: application/json');
                try {
                    $user = $sessionMiddleware->getUser();
                    $product_id = (int)($_POST['product_id'] ?? 0);
                    $unit_price = (float)($_POST['unit_price'] ?? 0);
                    if ($product_id <= 0 || $unit_price <= 0) throw new Exception('Invalid product or price');

                    $stmt = $conn->prepare("SELECT id FROM feed_distributors WHERE user_id = ?");
                    if (!$stmt) throw new Exception('Database error');
                    $stmt->bind_param('i', $user['id']);
                    $stmt->execute();
                    $distributor = $stmt->get_result()->fetch_assoc();
                    $stmt->close();
                    if (!$distributor) throw new Exception('Distributor profile not found');

                    $stmt = $conn->prepare("UPDATE feed_distributor_products SET unit_price = ? WHERE id = ? AND distributor_id = ?");
                    if (!$stmt) throw new Exception('Database error');
                    $stmt->bind_param('dii', $unit_price, $product_id, $distributor['id']);
                    if (!$stmt->execute()) throw new Exception('Error updating price');
                    $stmt->close();

                    echo json_encode(['success' => true]);
                    exit;
                } catch (Exception $e) {
                    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
                    exit;
                }
            }
            break;

        case 'feed-distributor/toggle-listing':
            if ($_SERVER['REQUEST_METHOD'] === 'POST') {
                header('Content-Type: application/json');
                try {
                    $user = $sessionMiddleware->getUser();
                    $product_id = (int)($_POST['product_id'] ?? 0);
                    $is_active = (int)($_POST['is_active'] ?? 0);
                    if ($product_id <= 0) throw new Exception('Invalid product ID');

                    $stmt = $conn->prepare("SELECT id FROM feed_distributors WHERE user_id = ?");
                    if (!$stmt) throw new Exception('Database error');
                    $stmt->bind_param('i', $user['id']);
                    $stmt->execute();
                    $distributor = $stmt->get_result()->fetch_assoc();
                    $stmt->close();
                    if (!$distributor) throw new Exception('Distributor profile not found');

                    $stmt = $conn->prepare("UPDATE feed_distributor_products SET is_active = ? WHERE id = ? AND distributor_id = ?");
                    if (!$stmt) throw new Exception('Database error');
                    $stmt->bind_param('iii', $is_active, $product_id, $distributor['id']);
                    if (!$stmt->execute()) throw new Exception('Error updating listing');
                    $stmt->close();

                    echo json_encode(['success' => true]);
                    exit;
                } catch (Exception $e) {
                    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
                    exit;
                }
            }
            break;

        case 'feed-distributor/accept-order':
            if ($_SERVER['REQUEST_METHOD'] === 'POST') {
                try {
                    $user = $sessionMiddleware->getUser();
                    $order_id = (int)($_POST['order_id'] ?? 0);
                    if (!$order_id) throw new Exception('Order ID is required');

                    $stmt = $conn->prepare("SELECT id FROM feed_distributors WHERE user_id = ?");
                    if (!$stmt) throw new Exception('Database error');
                    $stmt->bind_param('i', $user['id']);
                    $stmt->execute();
                    $distributor = $stmt->get_result()->fetch_assoc();
                    $stmt->close();
                    if (!$distributor) throw new Exception('Distributor profile not found');

                    $stmt = $conn->prepare("UPDATE feed_distributor_orders SET order_status = 'confirmed', updated_at = NOW() WHERE id = ? AND distributor_id = ?");
                    if (!$stmt) throw new Exception('Database error');
                    $stmt->bind_param('ii', $order_id, $distributor['id']);
                    if (!$stmt->execute()) throw new Exception('Error updating order');
                    $stmt->close();

                    // Notify the buyer (supplier) that their order was accepted
                    $stmt = $conn->prepare(
                        "SELECT fdo.buyer_user_id, fdo.order_number, fdo.total_amount, fd.business_name
                        FROM feed_distributor_orders fdo
                        JOIN feed_distributors fd ON fdo.distributor_id = fd.id
                        WHERE fdo.id = ?"
                    );
                    if ($stmt) {
                        $stmt->bind_param('i', $order_id);
                        $stmt->execute();
                        $order_info = $stmt->get_result()->fetch_assoc();
                        $stmt->close();

                        if ($order_info) {
                            $notification = new Notification($conn);
                            $notification->create(
                                $order_info['buyer_user_id'],
                                'order_confirmed',
                                'Feed Order Accepted',
                                'Your feed order #' . ($order_info['order_number'] ?: $order_id) . ' from ' . $order_info['business_name'] . ' has been accepted. Total: ₱' . number_format($order_info['total_amount'], 2),
                                '/supplier/fd-orders'
                            );
                        }
                    }

                    $_SESSION['success'] = 'Order accepted successfully!';
                    header('Location: ' . $base_url . '/feed-distributor/orders');
                    exit;
                } catch (Exception $e) {
                    $_SESSION['error'] = 'Error: ' . $e->getMessage();
                    header('Location: ' . $base_url . '/feed-distributor/orders');
                    exit;
                }
            }
            break;

        // Feed Distributor - Order Details
        case (preg_match('/^feed-distributor\/order-details\/(\d+)$/', $route, $matches) ? true : false):
            if ($_SERVER['REQUEST_METHOD'] === 'GET') {
                $order_id = (int)$matches[1];
                require VIEWS_PATH . '/feed-distributor/order-details.php';
            }
            break;

        case 'feed-distributor/update-order-status':
            if ($_SERVER['REQUEST_METHOD'] === 'POST') {
                try {
                    $user     = $sessionMiddleware->getUser();
                    $order_id = (int)($_POST['order_id'] ?? 0);
                    $new_status = $_POST['new_status'] ?? '';
                    $allowed = ['confirmed','processing','ready_for_delivery','delivered','cancelled'];
                    if (!$order_id || !in_array($new_status, $allowed)) throw new Exception('Invalid data');

                    $stmt = $conn->prepare("SELECT id FROM feed_distributors WHERE user_id = ?");
                    if (!$stmt) throw new Exception('Database error');
                    $stmt->bind_param('i', $user['id']);
                    $stmt->execute();
                    $dist = $stmt->get_result()->fetch_assoc();
                    $stmt->close();
                    if (!$dist) throw new Exception('Distributor profile not found');

                    $stmt = $conn->prepare("UPDATE feed_distributor_orders SET order_status = ?, updated_at = NOW() WHERE id = ? AND distributor_id = ?");
                    if (!$stmt) throw new Exception('Database error');
                    $stmt->bind_param('sii', $new_status, $order_id, $dist['id']);
                    if (!$stmt->execute()) throw new Exception('Error updating status');
                    $stmt->close();

                    // Notify buyer of status change
                    $stmt = $conn->prepare(
                        "SELECT fdo.buyer_user_id, fdo.order_number, fdo.total_amount, fd.business_name
                        FROM feed_distributor_orders fdo
                        JOIN feed_distributors fd ON fdo.distributor_id = fd.id
                        WHERE fdo.id = ?"
                    );
                    if ($stmt) {
                        $stmt->bind_param('i', $order_id);
                        $stmt->execute();
                        $order_info = $stmt->get_result()->fetch_assoc();
                        $stmt->close();

                        if ($order_info) {
                            $status_labels = [
                                'processing'         => 'is now being processed',
                                'ready_for_delivery' => 'is ready for delivery',
                                'delivered'          => 'has been delivered',
                                'cancelled'          => 'has been cancelled',
                            ];
                            $label = $status_labels[$new_status] ?? ('status updated to ' . str_replace('_', ' ', $new_status));
                            $notification = new Notification($conn);
                            $notification->create(
                                $order_info['buyer_user_id'],
                                'order_status_update',
                                'Feed Order Update',
                                'Your order #' . ($order_info['order_number'] ?: $order_id) . ' from ' . $order_info['business_name'] . ' ' . $label . '.',
                                '/supplier/fd-orders'
                            );
                        }
                    }

                    $_SESSION['success'] = 'Order status updated to ' . ucfirst(str_replace('_', ' ', $new_status)) . '.';
                    header('Location: ' . $base_url . '/feed-distributor/order-details/' . $order_id);
                    exit;
                } catch (Exception $e) {
                    $_SESSION['error'] = 'Error: ' . $e->getMessage();
                    header('Location: ' . $base_url . '/feed-distributor/orders');
                    exit;
                }
            }
            break;

        // ========== PIG SLAUGHTER ROUTES ==========
        
        case 'pig-slaughter/live-pigs':
            if ($_SERVER['REQUEST_METHOD'] === 'GET') {
                // RBAC check already done above
                require VIEWS_PATH . '/pig-slaughter/live-pigs.php';
            }
            break;

        case 'pig-slaughter/profile':
            if ($_SERVER['REQUEST_METHOD'] === 'GET') {
                // RBAC check already done above
                require VIEWS_PATH . '/pig-slaughter/profile.php';
            }
            break;

        case 'pig-slaughter/update-order-status':
            if ($_SERVER['REQUEST_METHOD'] === 'POST') {
                // RBAC check already done above
                header('Content-Type: application/json');
                
                try {
                    $input = json_decode(file_get_contents('php://input'), true);
                    $order_number = $input['order_number'] ?? '';
                    $status = $input['status'] ?? '';
                    
                    if (!$order_number || !$status) {
                        throw new Exception('Missing required parameters');
                    }
                    
                    // Validate status
                    $valid_statuses = ['pending', 'confirmed', 'preparing', 'cooking', 'delivering', 'completed', 'cancelled'];
                    if (!in_array($status, $valid_statuses)) {
                        throw new Exception('Invalid status');
                    }
                    
                    $source_type = $input['source_type'] ?? 'PIG_ORDER';

                    if ($source_type === 'LECHON_ORDER') {
                        // lechon_orders ENUM: pending/confirmed/preparing/cost_computed/ready_for_pickup/completed/cancelled
                        // Map 'cooking' → 'preparing' to fit the ENUM
                        $db_status = ($status === 'cooking') ? 'preparing' : $status;
                        $sql = "UPDATE lechon_orders SET order_status = ?, updated_at = NOW() WHERE order_number = ?";
                        $stmt = $conn->prepare($sql);
                        if (!$stmt) throw new Exception('DB prepare error: ' . $conn->error);
                        $stmt->bind_param('ss', $db_status, $order_number);
                        if (!$stmt->execute()) throw new Exception('DB error: ' . $stmt->error);
                        $affected = $stmt->affected_rows;
                        $stmt->close();
                    } else {
                        // Try via order_total_cost join first
                        $sql = "UPDATE swine_order_status sos
                                JOIN order_total_cost otc ON otc.swine_order_id = sos.id
                                SET sos.order_status = ?
                                WHERE otc.order_number = ?";
                        $stmt = $conn->prepare($sql);
                        if (!$stmt) throw new Exception('DB prepare error: ' . $conn->error);
                        $stmt->bind_param('ss', $status, $order_number);
                        if (!$stmt->execute()) throw new Exception('DB error: ' . $stmt->error);
                        $affected = $stmt->affected_rows;
                        $stmt->close();

                        // Fallback: update directly by order_number on swine_order_status
                        if ($affected === 0) {
                            $sql2 = "UPDATE swine_order_status SET order_status = ? WHERE order_number = ?";
                            $stmt2 = $conn->prepare($sql2);
                            if ($stmt2) {
                                $stmt2->bind_param('ss', $status, $order_number);
                                $stmt2->execute();
                                $affected = $stmt2->affected_rows;
                                $stmt2->close();
                            }
                        }
                    }

                    if ($affected > 0) {
                        echo json_encode(['success' => true, 'message' => 'Status updated']);
                    } else {
                        throw new Exception('No matching order found for: ' . $order_number);
                    }
                    
                } catch (Exception $e) {
                    error_log("Order status update error: " . $e->getMessage());
                    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
                }
            }
            break;

        case 'supplier/add-to-fd-cart':
            if ($_SERVER['REQUEST_METHOD'] === 'POST') {
                try {
                    $product_id     = (int)($_POST['product_id'] ?? 0);
                    $distributor_id = (int)($_POST['distributor_id'] ?? 0);
                    $quantity_kg    = (float)($_POST['quantity_kg'] ?? 0);
                    $unit_price     = (float)($_POST['unit_price'] ?? 0);
                    $product_name   = $_POST['product_name'] ?? '';
                    $feed_type      = $_POST['feed_type'] ?? '';

                    if (!$product_id || $quantity_kg <= 0 || $unit_price <= 0) {
                        throw new Exception('Invalid product or quantity');
                    }

                    // Get distributor name
                    $stmt = $conn->prepare("SELECT business_name FROM feed_distributors WHERE id = ?");
                    if (!$stmt) throw new Exception('Database error');
                    $stmt->bind_param('i', $distributor_id);
                    $stmt->execute();
                    $dist = $stmt->get_result()->fetch_assoc();
                    $stmt->close();

                    if (!isset($_SESSION['fd_cart'])) $_SESSION['fd_cart'] = [];

                    $cart_key = $product_id . '-' . $distributor_id;
                    if (isset($_SESSION['fd_cart'][$cart_key])) {
                        $_SESSION['fd_cart'][$cart_key]['quantity_kg'] += $quantity_kg;
                        $_SESSION['fd_cart'][$cart_key]['subtotal'] = $_SESSION['fd_cart'][$cart_key]['quantity_kg'] * $unit_price;
                    } else {
                        $_SESSION['fd_cart'][$cart_key] = [
                            'product_id'       => $product_id,
                            'distributor_id'   => $distributor_id,
                            'distributor_name' => $dist['business_name'] ?? 'Distributor',
                            'product_name'     => $product_name,
                            'feed_type'        => $feed_type,
                            'unit_price'       => $unit_price,
                            'quantity_kg'      => $quantity_kg,
                            'subtotal'         => $quantity_kg * $unit_price,
                        ];
                    }

                    $_SESSION['success'] = htmlspecialchars($product_name) . ' added to cart!';
                    header('Location: ' . $base_url . '/supplier/feeds-market');
                    exit;
                } catch (Exception $e) {
                    $_SESSION['error'] = 'Error: ' . $e->getMessage();
                    header('Location: ' . $base_url . '/supplier/feeds-market');
                    exit;
                }
            }
            break;

        case 'supplier/fd-checkout':
            if ($_SERVER['REQUEST_METHOD'] === 'GET') {
                require VIEWS_PATH . '/supplier/fd-checkout.php';
            } elseif ($_SERVER['REQUEST_METHOD'] === 'POST') {
                $isAjax = !empty($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) === 'xmlhttprequest';
                try {
                    $user           = $sessionMiddleware->getUser();
                    $cart           = $_SESSION['fd_cart'] ?? [];
                    $payment_method = $_POST['payment_method'] ?? '';
                    $delivery_addr  = $_POST['delivery_address'] ?? '';

                    if (empty($cart))           throw new Exception('Cart is empty');
                    if (empty($payment_method)) throw new Exception('Please select a payment method');

                    // Get supplier record
                    $stmt = $conn->prepare("SELECT id, farm_name FROM suppliers WHERE user_id = ?");
                    if (!$stmt) throw new Exception('Database error');
                    $stmt->bind_param('i', $user['id']);
                    $stmt->execute();
                    $supplier_rec = $stmt->get_result()->fetch_assoc();
                    $stmt->close();
                    if (!$supplier_rec) throw new Exception('Supplier profile not found');

                    // Group cart by distributor
                    $by_dist = [];
                    foreach ($cart as $item) {
                        $by_dist[$item['distributor_id']][] = $item;
                    }

                    if ($payment_method === 'test_payment') {
                        foreach ($by_dist as $dist_id => $items) {
                            $total = array_sum(array_column($items, 'subtotal'));
                            $order_number = 'SUP-' . $supplier_rec['id'] . '-' . time() . '-' . $dist_id;
                            $ref = 'TEST-' . time();

                            $stmt = $conn->prepare(
                                "INSERT INTO feed_distributor_orders (distributor_id, buyer_user_id, buyer_name, order_number, order_status, payment_status, total_amount, delivery_address)
                                VALUES (?, ?, ?, ?, 'pending', 'paid', ?, ?)"
                            );
                            if (!$stmt) throw new Exception('Database error: ' . $conn->error);
                            $stmt->bind_param('iissds', $dist_id, $user['id'], $supplier_rec['farm_name'], $order_number, $total, $delivery_addr);
                            if (!$stmt->execute()) throw new Exception('Error creating order');
                            $order_id = $conn->insert_id;
                            $stmt->close();

                            // Insert items + deduct stock
                            foreach ($items as $item) {
                                $stmt = $conn->prepare(
                                    "INSERT INTO feed_distributor_order_items (order_id, product_id, product_name, feed_type, quantity_kg, unit_price, subtotal)
                                    VALUES (?, ?, ?, ?, ?, ?, ?)"
                                );
                                if (!$stmt) throw new Exception('Database error');
                                $stmt->bind_param('iissddd', $order_id, $item['product_id'], $item['product_name'], $item['feed_type'], $item['quantity_kg'], $item['unit_price'], $item['subtotal']);
                                $stmt->execute();
                                $stmt->close();

                                // Deduct distributor stock
                                $stmt = $conn->prepare(
                                    "UPDATE feed_distributor_products SET quantity_available_kg = quantity_available_kg - ? WHERE id = ? AND quantity_available_kg >= ?"
                                );
                                if ($stmt) {
                                    $stmt->bind_param('did', $item['quantity_kg'], $item['product_id'], $item['quantity_kg']);
                                    $stmt->execute();
                                    $stmt->close();
                                }
                            }

                            // Notify distributor
                            $stmt = $conn->prepare("SELECT user_id FROM feed_distributors WHERE id = ?");
                            if ($stmt) {
                                $stmt->bind_param('i', $dist_id);
                                $stmt->execute();
                                $dist_user = $stmt->get_result()->fetch_assoc();
                                $stmt->close();
                                if ($dist_user) {
                                    $notification = new Notification($conn);
                                    $notification->create(
                                        $dist_user['user_id'],
                                        'new_feed_order',
                                        'New Order Received',
                                        'Supplier ' . $supplier_rec['farm_name'] . ' placed order #' . $order_number . ' — ₱' . number_format($total, 2),
                                        '/feed-distributor/orders'
                                    );
                                }
                            }
                        }

                        unset($_SESSION['fd_cart']);
                        if ($isAjax) { echo json_encode(['success' => true]); exit; }
                        $_SESSION['success'] = 'Order placed successfully via test payment!';
                        header('Location: ' . $base_url . '/supplier/fd-orders');
                        exit;

                    } elseif ($payment_method === 'cash_on_delivery') {
                        foreach ($by_dist as $dist_id => $items) {
                            $total = array_sum(array_column($items, 'subtotal'));
                            $order_number = 'SUP-' . $supplier_rec['id'] . '-' . time() . '-' . $dist_id;

                            $stmt = $conn->prepare(
                                "INSERT INTO feed_distributor_orders (distributor_id, buyer_user_id, buyer_name, order_number, order_status, payment_status, total_amount, delivery_address)
                                VALUES (?, ?, ?, ?, 'pending', 'unpaid', ?, ?)"
                            );
                            if (!$stmt) throw new Exception('Database error');
                            $stmt->bind_param('iissds', $dist_id, $user['id'], $supplier_rec['farm_name'], $order_number, $total, $delivery_addr);
                            if (!$stmt->execute()) throw new Exception('Error creating order');
                            $order_id = $conn->insert_id;
                            $stmt->close();

                            foreach ($items as $item) {
                                $stmt = $conn->prepare(
                                    "INSERT INTO feed_distributor_order_items (order_id, product_id, product_name, feed_type, quantity_kg, unit_price, subtotal)
                                    VALUES (?, ?, ?, ?, ?, ?, ?)"
                                );
                                if (!$stmt) throw new Exception('Database error');
                                $stmt->bind_param('iissddd', $order_id, $item['product_id'], $item['product_name'], $item['feed_type'], $item['quantity_kg'], $item['unit_price'], $item['subtotal']);
                                $stmt->execute();
                                $stmt->close();

                                $stmt = $conn->prepare(
                                    "UPDATE feed_distributor_products SET quantity_available_kg = quantity_available_kg - ? WHERE id = ? AND quantity_available_kg >= ?"
                                );
                                if ($stmt) {
                                    $stmt->bind_param('did', $item['quantity_kg'], $item['product_id'], $item['quantity_kg']);
                                    $stmt->execute();
                                    $stmt->close();
                                }
                            }
                        }

                        unset($_SESSION['fd_cart']);
                        if ($isAjax) { echo json_encode(['success' => true]); exit; }
                        $_SESSION['success'] = 'Order placed! Pay on delivery.';
                        header('Location: ' . $base_url . '/supplier/fd-orders');
                        exit;

                    } elseif ($payment_method === 'online_payment') {
                        // Save pending cart data for after PayMongo redirect
                        $_SESSION['fd_pending_checkout'] = [
                            'cart'           => $cart,
                            'delivery_addr'  => $delivery_addr,
                            'supplier_id'    => $supplier_rec['id'],
                            'supplier_name'  => $supplier_rec['farm_name'],
                            'buyer_user_id'  => $user['id'],
                        ];
                        if ($isAjax) { echo json_encode(['success' => true]); exit; }
                        // JS will handle PayMongo redirect
                        exit;
                    }

                    throw new Exception('Invalid payment method');
                } catch (Exception $e) {
                    if ($isAjax) { http_response_code(400); echo json_encode(['error' => $e->getMessage()]); exit; }
                    $_SESSION['error'] = 'Error: ' . $e->getMessage();
                    header('Location: ' . $base_url . '/supplier/fd-checkout');
                    exit;
                }
            }
            break;

        case 'supplier/fd-orders':
            if ($_SERVER['REQUEST_METHOD'] === 'GET') {
                require VIEWS_PATH . '/supplier/fd-orders.php';
            }
            break;

        case 'supplier/import-fd-order':
            if ($_SERVER['REQUEST_METHOD'] === 'POST') {
                header('Content-Type: application/json');
                try {
                    $user     = $sessionMiddleware->getUser();
                    $order_id = (int)($_POST['order_id'] ?? 0);
                    if (!$order_id) throw new Exception('Order ID is required');

                    // Verify order belongs to this supplier (any non-cancelled status)
                    $stmt = $conn->prepare(
                        "SELECT fdo.id, fdo.imported_to_inventory, fd.business_name
                        FROM feed_distributor_orders fdo
                        JOIN feed_distributors fd ON fdo.distributor_id = fd.id
                        WHERE fdo.id = ? AND fdo.buyer_user_id = ? AND fdo.order_status != 'cancelled'"
                    );
                    if (!$stmt) throw new Exception('Database error');
                    $stmt->bind_param('ii', $order_id, $user['id']);
                    $stmt->execute();
                    $order = $stmt->get_result()->fetch_assoc();
                    $stmt->close();

                    if (!$order) throw new Exception('Order not found or was cancelled');
                    if (!empty($order['imported_to_inventory'])) throw new Exception('This order has already been imported');

                    // Get supplier record
                    $stmt = $conn->prepare("SELECT id FROM suppliers WHERE user_id = ?");
                    if (!$stmt) throw new Exception('Database error');
                    $stmt->bind_param('i', $user['id']);
                    $stmt->execute();
                    $supplier = $stmt->get_result()->fetch_assoc();
                    $stmt->close();
                    if (!$supplier) throw new Exception('Supplier profile not found');

                    // Get order items
                    $stmt = $conn->prepare(
                        "SELECT product_name, feed_type, unit_price, quantity_kg
                        FROM feed_distributor_order_items WHERE order_id = ?"
                    );
                    if (!$stmt) throw new Exception('Database error');
                    $stmt->bind_param('i', $order_id);
                    $stmt->execute();
                    $items = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
                    $stmt->close();

                    if (empty($items)) throw new Exception('No items found in this order');

                    $imported = 0;
                    foreach ($items as $item) {
                        // Check if product with same name+type already exists for this supplier
                        $stmt = $conn->prepare(
                            "SELECT id, quantity_available_kg FROM feed_products
                            WHERE supplier_id = ? AND product_name = ? AND feed_type = ?"
                        );
                        if (!$stmt) throw new Exception('Database error');
                        $stmt->bind_param('iss', $supplier['id'], $item['product_name'], $item['feed_type']);
                        $stmt->execute();
                        $existing = $stmt->get_result()->fetch_assoc();
                        $stmt->close();

                        if ($existing) {
                            // Add stock to existing product
                            $stmt = $conn->prepare(
                                "UPDATE feed_products SET quantity_available_kg = quantity_available_kg + ?, updated_at = NOW()
                                WHERE id = ?"
                            );
                            if (!$stmt) throw new Exception('Database error');
                            $stmt->bind_param('di', $item['quantity_kg'], $existing['id']);
                            $stmt->execute();
                            $stmt->close();
                        } else {
                            // Insert as new product
                            $desc = 'Imported from ' . $order['business_name'];
                            $stmt = $conn->prepare(
                                "INSERT INTO feed_products (supplier_id, product_name, feed_type, description, unit_price, quantity_available_kg, is_active)
                                VALUES (?, ?, ?, ?, ?, ?, 1)"
                            );
                            if (!$stmt) throw new Exception('Database error');
                            $stmt->bind_param('isssdd', $supplier['id'], $item['product_name'], $item['feed_type'], $desc, $item['unit_price'], $item['quantity_kg']);
                            $stmt->execute();
                            $stmt->close();
                        }
                        $imported++;
                    }

                    // Mark order as imported
                    $stmt = $conn->prepare("UPDATE feed_distributor_orders SET imported_to_inventory = 1 WHERE id = ?");
                    if ($stmt) { $stmt->bind_param('i', $order_id); $stmt->execute(); $stmt->close(); }

                    echo json_encode(['success' => true, 'count' => $imported]);
                    exit;
                } catch (Exception $e) {
                    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
                    exit;
                }
            }
            break;

        case preg_match('/^supplier\/receipt\/\d+$/', $route) ? $route : null:
            if ($_SERVER['REQUEST_METHOD'] === 'GET') {
                if (!$sessionMiddleware->isAuthenticated() || $sessionMiddleware->getUser()['role'] !== 'supplier') {
                    header('Location: ' . $base_url . '/login');
                    exit;
                }

                $route_parts = explode('/', $route);
                $order_id = intval(array_pop($route_parts));

                // Get order details
                $query = "SELECT lfo.*, u.name as owner_name, u.email,
                                lo.farm_name, lo.location, lo.contact_number as owner_contact,
                                su.name as supplier_name, s.farm_name as supplier_farm
                        FROM livestock_feed_orders lfo
                        LEFT JOIN livestock_owners lo ON lfo.livestock_owner_id = lo.id
                        LEFT JOIN users u ON lo.user_id = u.id
                        LEFT JOIN suppliers s ON lfo.supplier_id = s.id
                        LEFT JOIN users su ON s.user_id = su.id
                        WHERE lfo.id = ?";
                $stmt = $conn->prepare($query);
                if (!$stmt) {
                    $_SESSION['error'] = 'Database error';
                    header('Location: ' . $base_url . '/supplier/my-orders');
                    exit;
                }

                $stmt->bind_param('i', $order_id);
                $stmt->execute();
                $result = $stmt->get_result();
                $order = $result->fetch_assoc();
                $stmt->close();

                if (!$order) {
                    $_SESSION['error'] = 'Order not found';
                    header('Location: ' . $base_url . '/supplier/my-orders');
                    exit;
                }

                // Get order items
                $query_items = "SELECT * FROM livestock_feed_order_items WHERE feed_order_id = ?";
                $stmt_items = $conn->prepare($query_items);
                $stmt_items->bind_param('i', $order_id);
                $stmt_items->execute();
                $items_result = $stmt_items->get_result();
                $items = $items_result->fetch_all(MYSQLI_ASSOC);
                $stmt_items->close();

                // Display receipt
                ?>
    <!DOCTYPE html>
    <html>
    <head>
        <title>Order Receipt #<?php echo str_pad($order_id, 6, '0', STR_PAD_LEFT); ?></title>
        <link rel="stylesheet" href="/styles.css">
        <style>
            .receipt { max-width: 600px; margin: 0 auto; padding: 40px 20px; font-family: Arial, sans-serif; }
            .receipt-header { text-align: center; margin-bottom: 30px; border-bottom: 2px solid #333; padding-bottom: 20px; }
            .receipt-title { font-size: 24px; font-weight: bold; margin-bottom: 5px; }
            .receipt-number { color: #666; font-size: 14px; }
            .receipt-section { margin-bottom: 25px; }
            .receipt-label { font-weight: bold; color: #333; margin-bottom: 5px; }
            .receipt-value { color: #666; margin-bottom: 8px; }
            .receipt-items { margin: 20px 0; }
            .item-row { display: flex; justify-content: space-between; padding: 8px 0; border-bottom: 1px solid #eee; }
            .item-qty { width: 60px; text-align: center; }
            .item-name { flex: 1; }
            .item-price { width: 100px; text-align: right; }
            .item-total { width: 100px; text-align: right; font-weight: bold; }
            .receipt-total { font-size: 18px; font-weight: bold; text-align: right; padding-top: 15px; border-top: 2px solid #333; margin-top: 15px; }
            .print-btn { text-align: center; margin-top: 30px; }
            .print-btn button { padding: 10px 30px; background: #2ecc71; color: white; border: none; border-radius: 5px; cursor: pointer; font-size: 16px; }
            @media print { .print-btn { display: none; } }
        </style>
    </head>
    <body>
        <div class="receipt">
            <div class="receipt-header">
                <div class="receipt-title">LechGO - RECEIPT</div>
                <div class="receipt-number">Order #<?php echo str_pad($order_id, 6, '0', STR_PAD_LEFT); ?></div>
            </div>

            <div class="receipt-section">
                <div class="receipt-label">FROM:</div>
                <div class="receipt-value"><?php echo htmlspecialchars($order['supplier_name'] ?? 'LechGO'); ?></div>
            </div>

            <div class="receipt-section">
                <div class="receipt-label">TO:</div>
                <div class="receipt-value"><?php echo htmlspecialchars($order['owner_name']); ?></div>
                <div class="receipt-value">Farm: <?php echo htmlspecialchars($order['farm_name'] ?? 'N/A'); ?></div>
                <div class="receipt-value">Location: <?php echo htmlspecialchars($order['location']); ?></div>
                <div class="receipt-value">Email: <?php echo htmlspecialchars($order['email']); ?></div>
            </div>

            <div class="receipt-section">
                <div class="receipt-label">Order Date:</div>
                <div class="receipt-value"><?php echo date('F d, Y H:i A', strtotime($order['created_at'])); ?></div>
            </div>

            <div class="receipt-label">Items Ordered:</div>
            <div class="receipt-items">
                <div class="item-row" style="font-weight: bold; border-bottom: 2px solid #333;">
                    <div class="item-name">Product</div>
                    <div class="item-qty">Qty</div>
                    <div class="item-price">Unit Price</div>
                    <div class="item-total">Total</div>
                </div>
                <?php foreach ($items as $item): ?>
                    <div class="item-row">
                        <div class="item-name"><?php echo htmlspecialchars($item['product_name']); ?></div>
                        <div class="item-qty"><?php echo number_format($item['quantity_kg'], 1); ?> kg</div>
                        <div class="item-price">₱<?php echo number_format($item['unit_price'], 2); ?></div>
                        <div class="item-total">₱<?php echo number_format($item['subtotal'], 2); ?></div>
                    </div>
                <?php endforeach; ?>
            </div>

            <div class="receipt-total">
                Total Amount: ₱<?php echo number_format($order['total_amount'], 2); ?>
            </div>

            <div class="receipt-section" style="margin-top: 30px;">
                <div class="receipt-label">Payment Status:</div>
                <div class="receipt-value"><?php echo str_replace('_', ' ', ucfirst($order['payment_status'])); ?></div>
            </div>

            <div class="receipt-section">
                <div class="receipt-label">Delivery Address:</div>
                <div class="receipt-value"><?php echo htmlspecialchars($order['delivery_address']); ?></div>
            </div>

            <div class="print-btn">
                <button onclick="window.print()"> Print Receipt</button>
            </div>
        </div>
    </body>
    </html>
                <?php
                exit;
            }
            break;

        case preg_match('/^supplier\/order-details\/\d+$/', $route) ? $route : null:
            if ($_SERVER['REQUEST_METHOD'] === 'GET') {
                if (!$sessionMiddleware->isAuthenticated() || $sessionMiddleware->getUser()['role'] !== 'supplier') {
                    header('Location: ' . $base_url . '/login');
                    exit;
                }
                $route_parts = explode('/', $route);
                $order_id = intval(array_pop($route_parts));
                require VIEWS_PATH . '/supplier/order_details.php';
            }
            break;

        // Feed Order Routes - Caretaker
        case 'pig-caretaker/orders':
            if ($_SERVER['REQUEST_METHOD'] === 'GET') {
                if (!$sessionMiddleware->isAuthenticated() || $sessionMiddleware->getUser()['role'] !== 'pig_caretaker') {
                    header('Location: ' . $base_url . '/login');
                    exit;
                }
                require VIEWS_PATH . '/pig_caretaker/received_orders.php';
            }
            break;

        case 'pig-caretaker/respond-order':
            if ($_SERVER['REQUEST_METHOD'] === 'POST') {
                if (!$sessionMiddleware->isAuthenticated() || $sessionMiddleware->getUser()['role'] !== 'pig_caretaker') {
                    header('Location: ' . $base_url . '/login');
                    exit;
                }
                
                try {
                    $feedOrder = new FeedOrder($conn);
                    $order_id = $_POST['order_id'] ?? null;
                    $response = $_POST['response'] ?? null;
                    $response_text = $_POST['response_text'] ?? null;
                    
                    if (!$order_id || !$response) {
                        throw new Exception("Invalid order response data");
                    }
                    
                    $feedOrder->respondToOrder($order_id, $response, $response_text);
                    
                    // If accepted, generate receipt
                    if ($response === 'accept') {
                        $feedOrder->generateReceipt($order_id);
                        $_SESSION['success'] = 'Order accepted! Receipt generated.';
                    } else {
                        $_SESSION['success'] = 'Order rejected.';
                    }
                    
                    header('Location: ' . $base_url . '/pig-caretaker/orders');
                    exit;
                    
                } catch (Exception $e) {
                    $_SESSION['error'] = 'Error responding to order: ' . $e->getMessage();
                    header('Location: ' . $base_url . '/pig-caretaker/orders');
                    exit;
                }
            }
            break;

        case preg_match('/^pig-caretaker\/order-details\/\d+$/', $route) ? $route : null:
            if ($_SERVER['REQUEST_METHOD'] === 'GET') {
                if (!$sessionMiddleware->isAuthenticated() || $sessionMiddleware->getUser()['role'] !== 'pig_caretaker') {
                    header('Location: ' . $base_url . '/login');
                    exit;
                }
                $route_parts = explode('/', $route);
                $order_id = intval(array_pop($route_parts));
                require VIEWS_PATH . '/pig_caretaker/order_details.php';
            }
            break;

        // Livestock Purchase Order Routes REMOVED - will be reimplemented

        // Removed: case 'supplier/confirm-livestock-payment' - will be reimplemented

        // PayMongo Payment API Routes
        case 'api/create-payment-intent':
            if ($_SERVER['REQUEST_METHOD'] === 'POST') {
                if (!$sessionMiddleware->isAuthenticated()) {
                    http_response_code(403);
                    header('Content-Type: application/json');
                    echo json_encode(['error' => 'Unauthorized']);
                    exit;
                }

                header('Content-Type: application/json');
                
                try {
                    // Handle both JSON and form data
                    $data = [];
                    $contentType = $_SERVER['CONTENT_TYPE'] ?? '';
                    
                    if (strpos($contentType, 'application/json') !== false) {
                        $input = file_get_contents('php://input');
                        $data = json_decode($input, true) ?? [];
                    } else {
                        $data = $_POST;
                    }

                    $amount = floatval($data['amount'] ?? 0);
                    $description = $data['description'] ?? 'Feed Order Payment';
                    
                    if ($amount <= 0) {
                        throw new Exception("Invalid amount: " . $amount);
                    }

                    // Amount is already in centavos, convert to pesos for PayMongoService
                    $amountInPesos = $amount / 100;

                    // Create checkout session (PayMongo Link)
                    $payMongo = new PayMongoService(null, $conn);
                    $checkoutSession = $payMongo->createCheckoutSession(
                        $amountInPesos,
                        $description
                    );

                    if (!isset($checkoutSession['id'])) {
                        throw new Exception("Failed to create checkout session: " . json_encode($checkoutSession));
                    }

                    // Extract checkout URL from PayMongo Link response
                    $checkoutUrl = $checkoutSession['attributes']['checkout_url'] ?? null;
                    $referenceNumber = $checkoutSession['attributes']['reference_number'] ?? null;
                    
                    if (!$checkoutUrl) {
                        throw new Exception("No checkout URL in response");
                    }

                    echo json_encode([
                        'success' => true,
                        'intentId' => $checkoutSession['id'],
                        'reference_number' => $referenceNumber,
                        'checkout_url' => $checkoutUrl,
                        'status' => $checkoutSession['attributes']['status'] ?? 'unpaid',
                        'amount' => $amount
                    ]);
                    exit;

                } catch (Exception $e) {
                    http_response_code(400);
                    echo json_encode(['error' => $e->getMessage()]);
                    exit;
                }
            }
            break;

        case 'api/attach-payment-method':
            if ($_SERVER['REQUEST_METHOD'] === 'POST') {
                if (!$sessionMiddleware->isAuthenticated()) {
                    http_response_code(403);
                    echo json_encode(['error' => 'Unauthorized']);
                    exit;
                }

                try {
                    $payment_intent_id = $_POST['payment_intent_id'] ?? null;
                    $payment_method_id = $_POST['payment_method_id'] ?? null;

                    if (!$payment_intent_id || !$payment_method_id) {
                        throw new Exception("Missing payment data");
                    }

                    // Attach payment method
                    $payMongo = new PayMongoService();
                    $result = $payMongo->attachPaymentMethod($payment_intent_id, $payment_method_id);

                    echo json_encode([
                        'success' => true,
                        'status' => $result['attributes']['status'] ?? 'awaiting_action',
                        'payment_intent_id' => $result['id']
                    ]);
                    exit;

                } catch (Exception $e) {
                    http_response_code(400);
                    echo json_encode(['error' => $e->getMessage()]);
                    exit;
                }
            }
            break;

        case 'api/confirm-payment':
            if ($_SERVER['REQUEST_METHOD'] === 'POST') {
                if (!$sessionMiddleware->isAuthenticated() || $_SESSION['user']['role'] !== 'supplier') {
                    http_response_code(403);
                    echo json_encode(['error' => 'Unauthorized']);
                    exit;
                }

                try {
                    $user = $sessionMiddleware->getUser();
                    $payment_intent_id = $_POST['payment_intent_id'] ?? null;
                    $amount = floatval($_POST['amount'] ?? 0);
                    $caretaker_id = $_POST['caretaker_id'] ?? null;
                    $items = json_decode($_POST['items'] ?? '[]', true);
                    $notes = $_POST['notes'] ?? null;

                    if (!$payment_intent_id || !$caretaker_id || empty($items)) {
                        throw new Exception("Invalid payment data");
                    }

                    // Get supplier
                    $supplier = new FeedSupplier($conn);
                    if (!$supplier->findByUserId($user['id'])) {
                        throw new Exception("Supplier not found");
                    }

                    // Create feed order with verified payment
                    $feedOrder = new FeedOrder($conn);
                    $order_id = $feedOrder->create($supplier->id, $caretaker_id, $items, $notes);

                    // Update payment status
                    $feedOrder->updatePaymentStatus($order_id, 'verified', 'online', $payment_intent_id);
                    $feedOrder->updateOrderStatus($order_id, 'reviewing_payment');

                    $_SESSION['success'] = 'Order placed successfully!';

                    echo json_encode([
                        'success' => true,
                        'order_id' => $order_id,
                        'status' => 'confirmed'
                    ]);
                    exit;

                } catch (Exception $e) {
                    http_response_code(400);
                    echo json_encode(['error' => $e->getMessage()]);
                    exit;
                }
            }
            break;

        // PayMongo Webhook Handler
        case 'api/paymongo-webhook':
            if ($_SERVER['REQUEST_METHOD'] === 'POST') {
                header('Content-Type: application/json');
                
                try {
                    // Get raw payload for signature verification
                    $payload = file_get_contents('php://input');
                    $signature = $_SERVER['HTTP_X_PAYMONGO_SIGNATURE'] ?? null;

                    if (!$signature) {
                        error_log("PayMongo Webhook: Missing signature");
                        http_response_code(401);
                        echo json_encode(['error' => 'Missing signature']);
                        exit;
                    }

                    // Verify webhook signature
                    $payMongo = new PayMongoService(null, $conn);
                    if (!$payMongo->verifyWebhookSignature($payload, $signature)) {
                        error_log("PayMongo Webhook: Invalid signature");
                        http_response_code(401);
                        echo json_encode(['error' => 'Invalid signature']);
                        exit;
                    }

                    // Decode and handle webhook data
                    $data = json_decode($payload, true);
                    if (!$data) {
                        throw new Exception("Invalid JSON payload");
                    }

                    error_log("PayMongo Webhook received: " . json_encode($data));

                    // Handle the webhook
                    $result = $payMongo->handlePaymentWebhook($data);

                    if ($result) {
                        echo json_encode(['success' => true, 'message' => 'Webhook processed']);
                    } else {
                        echo json_encode(['success' => false, 'message' => 'Event not processed']);
                    }
                    exit;

                } catch (Exception $e) {
                    error_log("PayMongo Webhook Error: " . $e->getMessage());
                    http_response_code(400);
                    echo json_encode(['error' => $e->getMessage()]);
                    exit;
                }
            }
            break;

        case 'api/process-payment-success':
            if ($_SERVER['REQUEST_METHOD'] === 'POST') {
                if (!$sessionMiddleware->isAuthenticated()) {
                    http_response_code(403);
                    header('Content-Type: application/json');
                    echo json_encode(['error' => 'Unauthorized']);
                    exit;
                }

                header('Content-Type: application/json');
                
                try {
                    // Get payment intent ID from request
                    $input = file_get_contents('php://input');
                    $data = json_decode($input, true) ?? [];
                    $payment_intent_id = $data['payment_intent_id'] ?? null;

                    if (!$payment_intent_id) {
                        throw new Exception("Payment intent ID is required");
                    }

                    // Get pending orders from session
                    $pending_orders = $_SESSION['pending_orders'] ?? null;
                    if (!$pending_orders) {
                        throw new Exception('No pending orders found');
                    }

                    $orders_by_supplier = $pending_orders['orders_by_supplier'];
                    $owner = $pending_orders['owner'];
                    $delivery_address = $pending_orders['delivery_address'];

                    // Create orders for each supplier
                    foreach ($orders_by_supplier as $supplier_id => $items) {
                        $total_amount = 0;
                        foreach ($items as $item) {
                            $total_amount += $item['subtotal'];
                        }

                        // Generate order number
                        $order_number = 'LO-' . $owner['id'] . '-' . time();

                        // Insert order with payment info
                        $query = "INSERT INTO livestock_feed_orders (livestock_owner_id, supplier_id, order_number, order_status, payment_status, delivery_status, total_amount, delivery_address, payment_method, payment_reference)
                                VALUES (?, ?, ?, 'pending', 'paid', 'pending', ?, ?, 'online_payment', ?)";
                        $stmt = $conn->prepare($query);
                        if (!$stmt) {
                            throw new Exception('Database error: ' . $conn->error);
                        }

                        $stmt->bind_param('iisdss', $owner['id'], $supplier_id, $order_number, $total_amount, $delivery_address, $payment_intent_id);
                        if (!$stmt->execute()) {
                            throw new Exception('Error creating order: ' . $stmt->error);
                        }
                        $order_id = $conn->insert_id;
                        $stmt->close();

                        // Insert order items
                        foreach ($items as $item) {
                            $query = "INSERT INTO livestock_feed_order_items (feed_order_id, feed_product_id, product_name, feed_type, quantity_kg, unit_price, subtotal)
                                    VALUES (?, ?, ?, ?, ?, ?, ?)";
                            $stmt = $conn->prepare($query);
                            if (!$stmt) {
                                throw new Exception('Database error: ' . $conn->error);
                            }
                            $stmt->bind_param('iissddd', $order_id, $item['product_id'], $item['product_name'], $item['feed_type'], $item['quantity_kg'], $item['unit_price'], $item['subtotal']);
                            if (!$stmt->execute()) {
                                throw new Exception('Error adding order item: ' . $stmt->error);
                            }
                            $stmt->close();

                            // Deduct from supplier's feed_products inventory
                            $deduct_stmt = $conn->prepare("UPDATE feed_products SET quantity_available_kg = GREATEST(0, quantity_available_kg - ?) WHERE id = ?");
                            if ($deduct_stmt) {
                                $deduct_stmt->bind_param('di', $item['quantity_kg'], $item['product_id']);
                                $deduct_stmt->execute();
                                $deduct_stmt->close();
                            }
                        }

                        // Send notification email to supplier
                        try {
                            $supplier_query = "SELECT u.email, u.name FROM suppliers s 
                                            LEFT JOIN users u ON s.user_id = u.id 
                                            WHERE s.id = ?";
                            $supplier_stmt = $conn->prepare($supplier_query);
                            if ($supplier_stmt) {
                                $supplier_stmt->bind_param('i', $supplier_id);
                                $supplier_stmt->execute();
                                $supplier_result = $supplier_stmt->get_result();
                                $supplier_data = $supplier_result->fetch_assoc();
                                $supplier_stmt->close();

                                if ($supplier_data && !empty($supplier_data['email'])) {
                                    $emailService = new EmailService();
                                    $email_body = "
                                        <h2>New Feed Order Received!</h2>
                                        <p>You have received a new order from <strong>" . htmlspecialchars($owner['name']) . "</strong></p>
                                        <p><strong>Order Number:</strong> " . $order_number . "</p>
                                        <p><strong>Total Amount:</strong> ₱" . number_format($total_amount, 2) . "</p>
                                        <p><strong>Delivery Address:</strong><br>" . nl2br(htmlspecialchars($delivery_address)) . "</p>
                                        <p><strong>Payment Status:</strong> PAID</p>
                                        <hr>
                                        <p><a href='/supplier/orders' style='background: #2ecc71; color: white; padding: 10px 20px; text-decoration: none; border-radius: 5px;'>View Order Details</a></p>
                                        <p>Log in to your supplier account to see the complete order details and confirm the order.</p>
                                    ";
                                    
                                    $emailService->sendEmail(
                                        $supplier_data['email'],
                                        'New Feed Order - ' . $order_number,
                                        $email_body
                                    );
                                }
                            }
                        } catch (Exception $e) {
                            // Log email error but don't fail the order creation
                            error_log('Failed to send supplier notification: ' . $e->getMessage());
                        }
                    }

                    // Clear session and cart
                    unset($_SESSION['feed_cart']);
                    unset($_SESSION['pending_orders']);

                    echo json_encode([
                        'success' => true,
                        'message' => 'Orders created successfully'
                    ]);
                    exit;

                } catch (Exception $e) {
                    http_response_code(400);
                    echo json_encode(['error' => $e->getMessage()]);
                    exit;
                }
            }
            break;

        // Receipt routes
        case preg_match('/^order-receipt\/\d+$/', $route) ? $route : null:
            if ($_SERVER['REQUEST_METHOD'] === 'GET') {
                $route_parts = explode('/', $route);
                $order_id = intval(array_pop($route_parts));
                require VIEWS_PATH . '/receipt.php';
            }
            break;

        // ========== CUSTOMER ROUTES ==========
        case 'customer/browse-lechon':
            if ($_SERVER['REQUEST_METHOD'] === 'GET') {
                // RBAC check already done above
                require VIEWS_PATH . '/customer/browse-lechon.php';
            }
            break;

        case 'customer/my-orders':
            if ($_SERVER['REQUEST_METHOD'] === 'GET') {
                // RBAC check already done above
                require VIEWS_PATH . '/customer/my-orders.php';
            }
            break;

        case 'customer/budget-planner':
            if ($_SERVER['REQUEST_METHOD'] === 'GET') {
                // RBAC check already done above
                require VIEWS_PATH . '/customer/budget-planner.php';
            }
            break;

        // ========== NEW: GET MARKETPLACE PURCHASES FOR BUDGET PLANNER ==========
        case 'customer/get-marketplace-purchases':
            if ($_SERVER['REQUEST_METHOD'] === 'GET') {
                header('Content-Type: application/json');
                
                try {
                    $user = $sessionMiddleware->getUser();
                    
                    if (!$user) {
                        echo json_encode(['success' => false, 'error' => 'Not authenticated']);
                        exit;
                    }
                    
                    $purchases = [];
                    
                    // Fetch placed lechon orders from marketplace (all non-cancelled, regardless of payment status)
                    // Orders are included as soon as they are placed so the customer can budget accordingly.
                    // Payment is handled separately by the existing payment system.
                    $lechonStmt = $conn->prepare(
                        "SELECT 
                            'lechon' as source,
                            lo.id,
                            lo.order_number,
                            lo.listing_name as name,
                            lo.category,
                            lo.price,
                            1 as qty,
                            lo.created_at,
                            lo.payment_status,
                            lo.order_status
                        FROM lechon_orders lo
                        WHERE lo.customer_id = ? 
                          AND lo.order_status NOT IN ('cancelled')
                        ORDER BY lo.created_at DESC"
                    );
                    
                    if ($lechonStmt) {
                        $lechonStmt->bind_param('i', $user['id']);
                        $lechonStmt->execute();
                        $lechonResult = $lechonStmt->get_result();
                        
                        while ($row = $lechonResult->fetch_assoc()) {
                            // Map category to Budget Planner categories
                            $category = 'Lechon'; // Default
                            if (stripos($row['category'], 'LECHON') !== false) {
                                $category = 'Lechon';
                            } elseif (stripos($row['category'], 'MEAT') !== false) {
                                $category = 'Meat';
                            } elseif (stripos($row['category'], 'OTHER') !== false) {
                                $category = 'Other';
                            }
                            
                            $purchases[] = [
                                'source' => $row['source'],
                                'order_number' => $row['order_number'],
                                'name' => $row['name'],
                                'category' => $category,
                                'price' => floatval($row['price']),
                                'qty' => intval($row['qty']),
                                'order_date' => $row['created_at'],
                                'payment_status' => $row['payment_status'],
                                'order_status' => $row['order_status']
                            ];
                        }
                        
                        $lechonStmt->close();
                    }
                    
                    // Optionally: Fetch paid pig orders if customer ordered pig for lechon
                    // (Uncomment if needed)
                    /*
                    $pigStmt = $conn->prepare(
                        "SELECT 
                            'pig' as source,
                            otc.order_number,
                            CONCAT('Lechon from Pig ', COALESCE(pd.pig_tag_id, 'Custom')) as name,
                            'Lechon' as category,
                            otc.pig_base_amount as price,
                            1 as qty,
                            po.created_at,
                            sos.payment_status,
                            sos.order_status
                        FROM order_total_cost otc
                        LEFT JOIN swine_order_status sos ON sos.id = otc.swine_order_id
                        LEFT JOIN placed_orders po ON po.swine_order_id = sos.id
                        LEFT JOIN pig_details pd ON pd.id = sos.pig_detail_id
                        WHERE sos.customer_id = ? 
                          AND sos.payment_status = 'paid'
                          AND sos.order_status NOT IN ('cancelled')
                        ORDER BY po.created_at DESC"
                    );
                    
                    if ($pigStmt) {
                        $pigStmt->bind_param('i', $user['id']);
                        $pigStmt->execute();
                        $pigResult = $pigStmt->get_result();
                        
                        while ($row = $pigResult->fetch_assoc()) {
                            $purchases[] = [
                                'source' => $row['source'],
                                'order_number' => $row['order_number'],
                                'name' => $row['name'],
                                'category' => $row['category'],
                                'price' => floatval($row['price']),
                                'qty' => intval($row['qty']),
                                'order_date' => $row['created_at']
                            ];
                        }
                        
                        $pigStmt->close();
                    }
                    */
                    
                    echo json_encode([
                        'success' => true,
                        'purchases' => $purchases,
                        'count' => count($purchases)
                    ]);
                    
                } catch (Exception $e) {
                    error_log("Error fetching marketplace purchases: " . $e->getMessage());
                    echo json_encode([
                        'success' => false,
                        'error' => 'Failed to fetch marketplace purchases'
                    ]);
                }
                exit;
            }
            break;
        // ========== END NEW CODE ==========

        case 'customer/place-order':
            if ($_SERVER['REQUEST_METHOD'] === 'POST') {
                try {
                    $user = $sessionMiddleware->getUser();
                    $swine_order_id = (int)($_POST['swine_order_id'] ?? 0);
                    $order_number = trim($_POST['order_number'] ?? '');
                    $delivery_address = trim($_POST['delivery_address'] ?? '');
                    $delivery_notes = trim($_POST['delivery_notes'] ?? '');
                    $pickup_date = trim($_POST['pickup_date'] ?? '');
                    $delivery_method = trim($_POST['delivery_method'] ?? 'pickup');
                    
                    // Convert radio button value to individual flags
                    $additional_item = trim($_POST['additional_item'] ?? 'none');
                    $include_laman_loob = ($additional_item === 'laman_loob') ? 1 : 0;
                    $include_boopes = ($additional_item === 'boopes') ? 1 : 0;
                    $include_dinuguan = ($additional_item === 'dinuguan') ? 1 : 0;

                    // Validate required fields
                    if (!$swine_order_id || empty($order_number) || empty($pickup_date)) {
                        throw new Exception('Please provide all required information including pickup/delivery date');
                    }
                    
                    // Delivery address is required only for delivery method
                    if ($delivery_method === 'delivery' && empty($delivery_address)) {
                        throw new Exception('Delivery address is required when delivery method is selected');
                    }

                    // Validate pickup date is not in the past
                    $pickup_timestamp = strtotime($pickup_date);
                    $today_timestamp = strtotime('today');
                    if ($pickup_timestamp < $today_timestamp) {
                        throw new Exception('Pickup/delivery date cannot be in the past');
                    }

                    // Get order details
                    $stmt = $conn->prepare(
                        "SELECT customer_id, livestock_owner_id FROM swine_order_status WHERE id = ? AND customer_id = ?"
                    );
                    $stmt->bind_param('ii', $swine_order_id, $user['id']);
                    $stmt->execute();
                    $order = $stmt->get_result()->fetch_assoc();
                    $stmt->close();

                    if (!$order) {
                        throw new Exception('Order not found');
                    }

                    // Insert into placed_orders
                    $insertPlaced = $conn->prepare(
                        "INSERT INTO placed_orders 
                        (swine_order_id, order_number, customer_id, livestock_owner_id, delivery_address, delivery_notes, delivery_method, include_laman_loob, include_boopes, include_dinuguan)
                        VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)"
                    );
                    
                    if (!$insertPlaced) {
                        throw new Exception('Database error: ' . $conn->error . '. Please make sure the database migration has been run.');
                    }
                    
                    $insertPlaced->bind_param('isiisssiii', 
                        $swine_order_id,
                        $order_number,
                        $user['id'],
                        $order['livestock_owner_id'],
                        $delivery_address,
                        $delivery_notes,
                        $delivery_method,
                        $include_laman_loob,
                        $include_boopes,
                        $include_dinuguan
                    );
                    $insertPlaced->execute();
                    $insertPlaced->close();

                    // Update swine_order_status - set to preparing status and save pickup_date
                    $updateStatus = $conn->prepare(
                        "UPDATE swine_order_status 
                        SET order_status = 'preparing',
                            pickup_date = ?,
                            updated_at = NOW()
                        WHERE id = ?"
                    );
                    
                    if (!$updateStatus) {
                        throw new Exception('Database error: ' . $conn->error);
                    }
                    
                    $updateStatus->bind_param('si', $pickup_date, $swine_order_id);
                    $updateStatus->execute();
                    $updateStatus->close();

                    // Update hogs_market status to 'sold' if not already
                    $updateMarket = $conn->prepare(
                        "UPDATE hogs_market hm
                        JOIN swine_order_status sos ON sos.hogs_market_id = hm.id
                        SET hm.status = 'sold'
                        WHERE sos.id = ?"
                    );
                    $updateMarket->bind_param('i', $swine_order_id);
                    $updateMarket->execute();
                    $updateMarket->close();

                    $_SESSION['success'] = 'Order placed successfully! The seller will prepare your pig.';
                    header('Location: ' . $base_url . '/customer/my-orders');
                    exit;
                } catch (Exception $e) {
                    $_SESSION['error'] = $e->getMessage();
                    header('Location: ' . $base_url . '/customer/my-orders');
                    exit;
                }
            }
            break;

        case 'customer/reviews':
            if ($_SERVER['REQUEST_METHOD'] === 'GET') {
                // RBAC check already done above
                require_once APP_PATH . '/controllers/ReviewController.php';
                $reviewController = new ReviewController($conn);
                $reviewController->customerReviews();
            }
            break;

        case 'customer/profile':
            if ($_SERVER['REQUEST_METHOD'] === 'GET') {
                // RBAC check already done above
                require VIEWS_PATH . '/customer/profile-simple.php';
            }
            break;

        case 'customer/reserve-order':
            if ($_SERVER['REQUEST_METHOD'] === 'GET') {
                // RBAC check already done above
                require VIEWS_PATH . '/customer/reserve-order.php';
            }
            break;

        case 'customer/payment':
            if ($_SERVER['REQUEST_METHOD'] === 'GET') {
                // RBAC check already done above
                require VIEWS_PATH . '/customer/payment.php';
            }
            break;

        case 'customer/pay-order':
            if ($_SERVER['REQUEST_METHOD'] === 'GET') {
                // RBAC check already done above
                require VIEWS_PATH . '/customer/pay-order.php';
            }
            break;

        case 'customer/pay-remaining-balance':
            if ($_SERVER['REQUEST_METHOD'] === 'GET') {
                // RBAC check already done above
                require VIEWS_PATH . '/customer/pay-remaining-balance.php';
            }
            break;

        case 'customer/process-payment':
            if ($_SERVER['REQUEST_METHOD'] === 'POST') {
                try {
                    $user = $sessionMiddleware->getUser();
                    $order_id = (int)($_POST['order_id'] ?? 0);
                    $payment_method = $_POST['payment_method'] ?? '';
                    $payment_type = $_POST['payment_type'] ?? 'full'; // full or down
                    $order_type = $_POST['order_type'] ?? 'pig'; // pig or lechon

                    if (!$order_id || empty($payment_method)) {
                        throw new Exception('Invalid payment information');
                    }

                    // Fetch order details based on order type
                    if ($order_type === 'lechon') {
                        // Fetch lechon order
                        $stmt = $conn->prepare(
                            "SELECT lo.*, u.name AS seller_name, u.email AS seller_email,
                                    lo.price as total_cost,
                                    ll.name as listing_name
                            FROM lechon_orders lo
                            LEFT JOIN livestock_owners owner ON owner.id = lo.livestock_owner_id
                            LEFT JOIN users u ON u.id = owner.user_id
                            LEFT JOIN lechon_listings ll ON ll.id = lo.lechon_listing_id
                            WHERE lo.id = ? AND lo.customer_id = ?"
                        );
                        $stmt->bind_param('ii', $order_id, $user['id']);
                        $stmt->execute();
                        $order = $stmt->get_result()->fetch_assoc();
                        $stmt->close();

                        if (!$order) {
                            throw new Exception('Lechon order not found');
                        }

                        // Lechon orders are always full payment
                        $payment_type = 'full';
                        $total_amount = $order['total_cost'];
                        $payment_amount = $total_amount;
                        $remaining_balance = 0;

                    } else {
                        // Fetch pig order with computed total cost
                        $stmt = $conn->prepare(
                            "SELECT sos.*, u.name AS seller_name, u.email AS seller_email,
                                    otc.total_cost as computed_total_cost,
                                    otc.id as has_total_cost
                            FROM swine_order_status sos
                            LEFT JOIN livestock_owners lo ON lo.id = sos.livestock_owner_id
                            LEFT JOIN users u ON u.id = lo.user_id
                            LEFT JOIN order_total_cost otc ON otc.swine_order_id = sos.id
                            WHERE sos.id = ? AND sos.customer_id = ?"
                        );
                        $stmt->bind_param('ii', $order_id, $user['id']);
                        $stmt->execute();
                        $order = $stmt->get_result()->fetch_assoc();
                        $stmt->close();

                        if (!$order) {
                            throw new Exception('Order not found');
                        }

                        // Check if total cost has been computed
                        if (empty($order['has_total_cost'])) {
                            throw new Exception('Please wait for the seller to compute the total cost before making payment');
                        }

                        // Use computed total cost if available, otherwise use base price
                        $total_amount = !empty($order['computed_total_cost']) 
                            ? $order['computed_total_cost'] 
                            : $order['total_price'];

                        // Calculate payment amount based on payment type
                        if ($payment_type === 'down') {
                            $payment_amount = $total_amount * 0.5; // 50% down payment
                            $remaining_balance = $total_amount * 0.5;
                        } else {
                            $payment_amount = $total_amount;
                            $remaining_balance = 0;
                        }
                    }

                    // Check if already paid
                    if ($order['payment_status'] === 'paid') {
                        throw new Exception('This order has already been paid');
                    }

                    // Handle different payment methods
                    if ($payment_method === 'paymongo') {
                        // Initialize PayMongo service
                        $payMongo = new PayMongoService(null, $conn);
                        
                        // Create checkout session with success/failed URLs
                        if ($order_type === 'lechon') {
                            $description = "LechGO Lechon Order #{$order['order_number']} - {$order['listing_name']}";
                        } else {
                            $description = "LechGO Pig Order #{$order['order_number']} - {$order['pig_tag_id']}";
                        }
                        
                        // Build success and failed URLs with payment reference
                        $baseUrl = (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? "https" : "http") . "://" . $_SERVER['HTTP_HOST'];
                        $successUrl = $baseUrl . '/customer/payment-success?order_id=' . $order_id . '&user_id=' . $user['id'] . '&order_type=' . $order_type;
                        $failedUrl = $baseUrl . '/customer/payment-failed?order_id=' . $order_id . '&order_type=' . $order_type;
                        
                        try {
                            $checkoutSession = $payMongo->createCheckoutSession(
                                $payment_amount,
                                $description,
                                $successUrl,
                                $failedUrl
                            );

                            // Store payment reference and payment type
                            $payment_reference = $checkoutSession['id'];
                            
                            if ($order_type === 'lechon') {
                                // Update lechon_orders table
                                $updateStmt = $conn->prepare(
                                    "UPDATE lechon_orders 
                                    SET payment_method = 'paymongo', payment_reference = ?, updated_at = NOW()
                                    WHERE id = ?"
                                );
                                $updateStmt->bind_param('si', $payment_reference, $order_id);
                                $updateStmt->execute();
                                $updateStmt->close();
                            } else {
                                // Update swine_order_status table
                                $updateStmt = $conn->prepare(
                                    "UPDATE swine_order_status 
                                    SET payment_method = 'paymongo', payment_reference = ?, updated_at = NOW()
                                    WHERE id = ?"
                                );
                                $updateStmt->bind_param('si', $payment_reference, $order_id);
                                $updateStmt->execute();
                                $updateStmt->close();

                                // Update order_total_cost with payment type and remaining balance
                                $updateOtc = $conn->prepare(
                                    "UPDATE order_total_cost 
                                    SET payment_method = 'paymongo', 
                                        payment_reference = ?,
                                        payment_type = ?,
                                        amount_paid = ?,
                                        remaining_balance = ?
                                    WHERE swine_order_id = ?"
                                );
                                $updateOtc->bind_param('ssddi', $payment_reference, $payment_type, $payment_amount, $remaining_balance, $order_id);
                                $updateOtc->execute();
                                $updateOtc->close();
                            }

                            // Redirect to PayMongo checkout
                            $checkout_url = $checkoutSession['attributes']['checkout_url'];
                            header('Location: ' . $checkout_url);
                            exit;

                        } catch (Exception $e) {
                            throw new Exception('Payment processing error: ' . $e->getMessage());
                        }

                    } elseif ($payment_method === 'cod') {
                        // Cash on Delivery - mark as pending payment
                        if ($order_type === 'lechon') {
                            $updateStmt = $conn->prepare(
                                "UPDATE lechon_orders 
                                SET payment_method = 'cash_on_delivery', payment_status = 'unpaid', updated_at = NOW()
                                WHERE id = ?"
                            );
                            $updateStmt->bind_param('i', $order_id);
                            $updateStmt->execute();
                            $updateStmt->close();
                        } else {
                            $updateStmt = $conn->prepare(
                                "UPDATE swine_order_status 
                                SET payment_method = 'cash_on_delivery', payment_status = 'unpaid', updated_at = NOW()
                                WHERE id = ?"
                            );
                            $updateStmt->bind_param('i', $order_id);
                            $updateStmt->execute();
                            $updateStmt->close();

                            // Update order_total_cost with payment type and remaining balance
                            $updateOtc = $conn->prepare(
                                "UPDATE order_total_cost 
                                SET payment_method = 'cash_on_delivery', 
                                    payment_status = 'unpaid',
                                    payment_type = ?,
                                    amount_paid = 0,
                                    remaining_balance = ?
                                WHERE swine_order_id = ?"
                            );
                            $updateOtc->bind_param('sdi', $payment_type, $total_amount, $order_id);
                            $updateOtc->execute();
                            $updateOtc->close();
                        }

                        $message = $payment_type === 'down' 
                            ? ' Cash on Delivery confirmed! Pay 50% down payment (₱' . number_format($payment_amount, 2) . ') when you receive your order.'
                            : ' Cash on Delivery confirmed! Pay when you receive your order.';
                        $_SESSION['success'] = $message;
                        
                        $redirect_url = $order_type === 'lechon' 
                            ? $base_url . '/customer/my-orders?orders_tab=lechon'
                            : $base_url . '/customer/my-orders';
                        header('Location: ' . $redirect_url);
                        exit;

                    } elseif ($payment_method === 'bank') {
                        // Bank Transfer - mark as pending verification
                        if ($order_type === 'lechon') {
                            $updateStmt = $conn->prepare(
                                "UPDATE lechon_orders 
                                SET payment_method = 'bank_transfer', payment_status = 'unpaid', updated_at = NOW()
                                WHERE id = ?"
                            );
                            $updateStmt->bind_param('i', $order_id);
                            $updateStmt->execute();
                            $updateStmt->close();
                        } else {
                            $updateStmt = $conn->prepare(
                                "UPDATE swine_order_status 
                                SET payment_method = 'bank_transfer', payment_status = 'unpaid', updated_at = NOW()
                                WHERE id = ?"
                            );
                            $updateStmt->bind_param('i', $order_id);
                            $updateStmt->execute();
                            $updateStmt->close();

                            // Update order_total_cost with payment type and remaining balance
                            $updateOtc = $conn->prepare(
                                "UPDATE order_total_cost 
                                SET payment_method = 'bank_transfer', 
                                    payment_status = 'unpaid',
                                    payment_type = ?,
                                    amount_paid = 0,
                                    remaining_balance = ?
                                WHERE swine_order_id = ?"
                            );
                            $updateOtc->bind_param('sdi', $payment_type, $total_amount, $order_id);
                            $updateOtc->execute();
                            $updateOtc->close();
                        }

                        $_SESSION['success'] = ' Bank Transfer selected! Please transfer to the seller\'s account and provide proof of payment.';
                        
                        $redirect_url = $order_type === 'lechon' 
                            ? $base_url . '/customer/my-orders?orders_tab=lechon'
                            : $base_url . '/customer/my-orders';
                        header('Location: ' . $redirect_url);
                        exit;

                    } elseif ($payment_method === 'test') {
                        // Test Payment (Development Only) - simulate successful payment
                        $test_payment_reference = 'TEST-' . $order_id . '-' . time() . '-' . rand(1000, 9999);
                        
                        // Determine payment status (lechon is always full payment)
                        $payment_status = ($payment_type === 'down') ? 'partially_paid' : 'paid';
                        
                        if ($order_type === 'lechon') {
                            // Update lechon_orders
                            $updateStmt = $conn->prepare(
                                "UPDATE lechon_orders 
                                SET payment_method = 'test_payment', 
                                    payment_status = 'paid', 
                                    payment_reference = ?,
                                    updated_at = NOW()
                                WHERE id = ?"
                            );
                            $updateStmt->bind_param('si', $test_payment_reference, $order_id);
                            $updateStmt->execute();
                            $updateStmt->close();
                        } else {
                            // Update swine_order_status
                            $updateStmt = $conn->prepare(
                                "UPDATE swine_order_status 
                                SET payment_method = 'test_payment', 
                                    payment_status = ?, 
                                    payment_reference = ?,
                                    updated_at = NOW()
                                WHERE id = ?"
                            );
                            $updateStmt->bind_param('ssi', $payment_status, $test_payment_reference, $order_id);
                            $updateStmt->execute();
                            $updateStmt->close();

                            // Update order_total_cost
                            $otc_payment_status = ($payment_type === 'down') ? 'partially_paid' : 'paid';
                            $updateOtc = $conn->prepare(
                                "UPDATE order_total_cost 
                                SET payment_method = 'test_payment', 
                                    payment_status = ?,
                                    payment_reference = ?,
                                    payment_type = ?,
                                    amount_paid = ?,
                                    remaining_balance = ?,
                                    paid_at = CASE WHEN ? = 'paid' THEN NOW() ELSE NULL END
                                WHERE swine_order_id = ?"
                            );
                            $updateOtc->bind_param('sssddsi', $otc_payment_status, $test_payment_reference, $payment_type, $payment_amount, $remaining_balance, $otc_payment_status, $order_id);
                            $updateOtc->execute();
                            $updateOtc->close();
                        }

                        // Log test payment to customer_payments table if it exists
                        $tableCheck = $conn->query("SHOW TABLES LIKE 'customer_payments'");
                        if ($tableCheck && $tableCheck->num_rows > 0) {
                            $customer_name = trim(($user['first_name'] ?? '') . ' ' . ($user['last_name'] ?? ''));
                            if (empty($customer_name)) {
                                $customer_name = $user['name'] ?? 'Customer';
                            }
                            
                            $payment_notes = ($payment_type === 'down') 
                                ? 'Test down payment completed - Development mode. Remaining balance: ₱' . number_format($remaining_balance, 2)
                                : 'Test payment completed successfully - Development mode';
                            
                            // Insert payment record with proper order type handling
                            if ($order_type === 'lechon') {
                                // Lechon order: use lechon_order_id column
                                $insertPayment = $conn->prepare(
                                    "INSERT INTO customer_payments (
                                        payment_reference, lechon_order_id, order_type, order_number, customer_id, customer_name,
                                        payment_method, payment_provider, amount, currency, payment_status,
                                        payment_date, notes, created_at, updated_at
                                    ) VALUES (?, ?, 'lechon', ?, ?, ?, 'test', 'Test System', ?, 'PHP', 'completed', NOW(), 
                                            ?, NOW(), NOW())"
                                );
                                    
                                $insertPayment->bind_param('sisisds', 
                                    $test_payment_reference, 
                                    $order_id,  // This goes to lechon_order_id
                                    $order['order_number'], 
                                    $user['id'], 
                                    $customer_name, 
                                    $payment_amount,
                                    $payment_notes
                                );
                            } else {
                                // Pig order: use order_id column (references order_total_cost)
                                $payment_order_id = $order['has_total_cost'] ?? $order_id;
                                $insertPayment = $conn->prepare(
                                    "INSERT INTO customer_payments (
                                        payment_reference, order_id, order_type, order_number, customer_id, customer_name,
                                        payment_method, payment_provider, amount, currency, payment_status,
                                        payment_date, notes, created_at, updated_at
                                    ) VALUES (?, ?, 'pig', ?, ?, ?, 'test', 'Test System', ?, 'PHP', 'completed', NOW(), 
                                            ?, NOW(), NOW())"
                                );
                                    
                                $insertPayment->bind_param('sisisds', 
                                    $test_payment_reference, 
                                    $payment_order_id,  // This goes to order_id
                                    $order['order_number'], 
                                    $user['id'], 
                                    $customer_name, 
                                    $payment_amount,
                                    $payment_notes
                                );
                            }
                            
                            $insertPayment->execute();
                            $insertPayment->close();
                        }

                        if ($payment_type === 'down') {
                            $message = '🧪 Test down payment completed! Paid ₱' . number_format($payment_amount, 2) . '. Remaining balance: ₱' . number_format($remaining_balance, 2);
                        } else {
                            $message = '🧪 Test payment completed! Full payment of ₱' . number_format($payment_amount, 2) . ' processed successfully.';
                        }
                        
                        $_SESSION['success'] = $message;
                        
                        // Redirect to payment success page with reference
                        $redirect_url = $base_url . '/customer/payment-success?ref=' . $test_payment_reference . '&order_id=' . $order_id . '&order_type=' . $order_type;
                        header('Location: ' . $redirect_url);
                        exit;

                    } else {
                        throw new Exception('Invalid payment method');
                    }

                } catch (Exception $e) {
                    $_SESSION['error'] = $e->getMessage();
                    $order_type = $_POST['order_type'] ?? 'pig';
                    $redirect_url = $order_type === 'lechon' 
                        ? $base_url . '/customer/my-orders?orders_tab=lechon'
                        : $base_url . '/customer/my-orders';
                    header('Location: ' . $redirect_url);
                    exit;
                }
            }
            break;

        case 'customer/process-remaining-payment':
            if ($_SERVER['REQUEST_METHOD'] === 'POST') {
                try {
                    $user = $sessionMiddleware->getUser();
                    $order_id = (int)($_POST['order_id'] ?? 0);
                    $payment_method = $_POST['payment_method'] ?? '';
                    $payment_amount = (float)($_POST['payment_amount'] ?? 0);

                    if (!$order_id || empty($payment_method) || $payment_amount <= 0) {
                        throw new Exception('Invalid payment information');
                    }

                    // Get order details with remaining balance
                    $stmt = $conn->prepare(
                        "SELECT sos.*, u.name AS seller_name, u.email AS seller_email,
                                otc.total_cost, otc.amount_paid, otc.remaining_balance,
                                otc.id as has_total_cost
                        FROM swine_order_status sos
                        LEFT JOIN livestock_owners lo ON lo.id = sos.livestock_owner_id
                        LEFT JOIN users u ON u.id = lo.user_id
                        LEFT JOIN order_total_cost otc ON otc.swine_order_id = sos.id
                        WHERE sos.id = ? AND sos.customer_id = ?"
                    );
                    $stmt->bind_param('ii', $order_id, $user['id']);
                    $stmt->execute();
                    $order = $stmt->get_result()->fetch_assoc();
                    $stmt->close();

                    if (!$order) {
                        throw new Exception('Order not found');
                    }

                    // Verify remaining balance
                    if ($order['remaining_balance'] <= 0) {
                        throw new Exception('No remaining balance to pay');
                    }

                    // Handle different payment methods for remaining balance
                    if ($payment_method === 'test') {
                        $test_payment_reference = 'TEST-BALANCE-' . $order_id . '-' . time();
                        $new_amount_paid = $order['amount_paid'] + $payment_amount;
                        
                        // Update both tables
                        $conn->query("UPDATE swine_order_status SET payment_status = 'paid', payment_reference = '$test_payment_reference' WHERE id = $order_id");
                        $conn->query("UPDATE order_total_cost SET payment_status = 'paid', amount_paid = $new_amount_paid, remaining_balance = 0 WHERE swine_order_id = $order_id");
                        
                        $_SESSION['success'] = '🧪 Remaining balance paid! Order fully completed.';
                        header('Location: ' . $base_url . '/customer/my-orders');
                        exit;

                        // Log test payment to customer_payments table if it exists
                        $tableCheck = $conn->query("SHOW TABLES LIKE 'customer_payments'");
                        if ($tableCheck && $tableCheck->num_rows > 0) {
                            $insertPayment = $conn->prepare(
                                "INSERT INTO customer_payments (
                                    payment_reference, order_id, order_number, customer_id, customer_name,
                                    payment_method, payment_provider, amount, currency, payment_status,
                                    payment_date, notes, created_at, updated_at
                                ) VALUES (?, ?, ?, ?, ?, 'test', 'Test System', ?, 'PHP', 'completed', NOW(), 
                                        'Test remaining balance payment completed - Development mode', NOW(), NOW())"
                            );
                            
                            $customer_name = trim($user['first_name'] . ' ' . $user['last_name']);
                            $insertPayment->bind_param('sisiss', 
                                $test_payment_reference, 
                                $order['has_total_cost'], 
                                $order['order_number'], 
                                $user['id'], 
                                $customer_name, 
                                $payment_amount
                            );
                            $insertPayment->execute();
                            $insertPayment->close();
                        }

                        $_SESSION['success'] = '🧪 Test remaining balance payment completed! Order is now fully paid (₱' . number_format($new_amount_paid, 2) . ').';
                        
                        // Redirect to payment success page with reference
                        header('Location: ' . $base_url . '/customer/payment-success?ref=' . $test_payment_reference . '&order_id=' . $order_id);
                        exit;

                    } else {
                        // Handle other payment methods (PayMongo, COD, Bank Transfer)
                        $_SESSION['info'] = 'Remaining balance payment method selected. Please complete the payment process.';
                        header('Location: ' . $base_url . '/customer/my-orders');
                        exit;
                    }

                } catch (Exception $e) {
                    $_SESSION['error'] = $e->getMessage();
                    header('Location: ' . $base_url . '/customer/my-orders');
                    exit;
                }
            }
            break;

        case 'customer/payment-success':
            if ($_SERVER['REQUEST_METHOD'] === 'GET') {
                // Log the callback for debugging
                error_log("Payment Success Callback Triggered: " . json_encode($_GET));
                error_log("Session data: " . json_encode($_SESSION));
                
                $order_id = (int)($_GET['order_id'] ?? 0);
                $user_id_param = (int)($_GET['user_id'] ?? 0);
                $payment_reference = $_GET['reference'] ?? $_GET['link_id'] ?? $_GET['checkout_id'] ?? '';
                $order_type = $_GET['order_type'] ?? 'pig';
                
                // Handle PayMongo payment success callback
                $user = $sessionMiddleware->getUser();
                
                // If no user session, try to find user by order_id or user_id parameter (more robust approach)
                if (!$user && ($order_id || $user_id_param)) {
                    error_log("No user session found, trying to find user by order_id: $order_id or user_id: $user_id_param");
                    
                    if ($order_id) {
                        if ($order_type === 'lechon') {
                            $stmt = $conn->prepare(
                                "SELECT lo.customer_id, u.id, u.email, u.name, u.role, u.phone 
                                FROM lechon_orders lo 
                                JOIN users u ON u.id = lo.customer_id 
                                WHERE lo.id = ?"
                            );
                        } else {
                            $stmt = $conn->prepare(
                                "SELECT sos.customer_id, u.id, u.email, u.name, u.role, u.phone 
                                FROM swine_order_status sos 
                                JOIN users u ON u.id = sos.customer_id 
                                WHERE sos.id = ?"
                            );
                        }
                        $stmt->bind_param('i', $order_id);
                    } else {
                        $stmt = $conn->prepare(
                            "SELECT u.id, u.email, u.name, u.role, u.phone 
                            FROM users u 
                            WHERE u.id = ?"
                        );
                        $stmt->bind_param('i', $user_id_param);
                    }
                    
                    $stmt->execute();
                    $result = $stmt->get_result();
                    $user_data = $result->fetch_assoc();
                    $stmt->close();
                    
                    if ($user_data) {
                        // Restore user session
                        $sessionMiddleware->setUser(
                            $user_data['id'],
                            $user_data['email'],
                            $user_data['name'],
                            $user_data['role'],
                            $user_data['phone']
                        );
                        $user = $sessionMiddleware->getUser();
                        error_log("User session restored for user_id: " . $user_data['id']);
                    } else {
                        error_log("Could not find user for order_id: $order_id or user_id: $user_id_param");
                    }
                }
                
                if (!$user) {
                    error_log("No user found, redirecting to login");
                    $_SESSION['error'] = 'Session expired. Please login and check your orders.';
                    header('Location: ' . $base_url . '/login');
                    exit;
                }

                if ($order_id) {
                    error_log("Updating payment status for order_id: $order_id, user_id: " . $user['id'] . ", order_type: $order_type");
                    
                    if ($order_type === 'lechon') {
                        // Update lechon order payment status to paid
                        $stmt = $conn->prepare(
                            "UPDATE lechon_orders 
                            SET payment_status = 'paid', updated_at = NOW()
                            WHERE id = ? AND customer_id = ?"
                        );
                        $stmt->bind_param('ii', $order_id, $user['id']);
                        $stmt->execute();
                        $affected = $stmt->affected_rows;
                        $stmt->close();
                        
                        error_log("lechon_orders updated: $affected rows");
                        
                        $_SESSION['success'] = ' Payment successful! Your lechon order is confirmed.';
                        header('Location: ' . $base_url . '/customer/my-orders?orders_tab=lechon');
                        exit;
                        
                    } else {
                        // Update pig order payment status to paid
                        $stmt = $conn->prepare(
                            "UPDATE swine_order_status 
                            SET payment_status = 'paid', updated_at = NOW()
                            WHERE id = ? AND customer_id = ?"
                        );
                        $stmt->bind_param('ii', $order_id, $user['id']);
                        $stmt->execute();
                        $affected = $stmt->affected_rows;
                        $stmt->close();
                        
                        error_log("swine_order_status updated: $affected rows");

                        // Check if payment_type column exists in order_total_cost
                        $check_column = $conn->query("SHOW COLUMNS FROM order_total_cost LIKE 'payment_type'");
                        $has_payment_type_column = $check_column->num_rows > 0;
                        
                        if ($has_payment_type_column) {
                            // New version with payment_type support
                            error_log("Using new payment_type column");
                            
                            // Get payment details from order_total_cost to check payment type
                            $stmt = $conn->prepare(
                                "SELECT payment_type, amount_paid, remaining_balance, total_cost 
                                FROM order_total_cost 
                                WHERE swine_order_id = ?"
                            );
                            $stmt->bind_param('i', $order_id);
                            $stmt->execute();
                            $payment_info = $stmt->get_result()->fetch_assoc();
                            $stmt->close();

                            if ($payment_info) {
                                $payment_type = $payment_info['payment_type'] ?? 'full';
                                $total_cost = $payment_info['total_cost'];
                                
                                if ($payment_type === 'down') {
                                    // Down payment - update amount_paid to 50%
                                    $amount_paid = $total_cost * 0.5;
                                    $remaining_balance = $total_cost * 0.5;
                                    
                                    $stmt = $conn->prepare(
                                        "UPDATE order_total_cost 
                                        SET payment_status = 'paid', 
                                            amount_paid = ?,
                                            remaining_balance = ?,
                                            paid_at = NOW()
                                        WHERE swine_order_id = ?"
                                    );
                                    $stmt->bind_param('ddi', $amount_paid, $remaining_balance, $order_id);
                                    $stmt->execute();
                                    $stmt->close();
                                    
                                    error_log("Down payment updated: amount_paid=$amount_paid, remaining=$remaining_balance");
                                    $_SESSION['success'] = 'Down payment successful! ₱' . number_format($amount_paid, 2) . ' paid. Remaining balance: ₱' . number_format($remaining_balance, 2);
                                } else {
                                    // Full payment
                                    $amount_paid = $total_cost;
                                    $stmt = $conn->prepare(
                                        "UPDATE order_total_cost 
                                        SET payment_status = 'paid', 
                                            amount_paid = ?,
                                            remaining_balance = 0,
                                            paid_at = NOW()
                                        WHERE swine_order_id = ?"
                                    );
                                    $stmt->bind_param('di', $amount_paid, $order_id);
                                    $stmt->execute();
                                    $stmt->close();
                                    
                                    error_log("Full payment updated: amount_paid=$amount_paid");
                                    $_SESSION['success'] = 'Payment successful! Your order has been confirmed.';
                                }
                            } else {
                                // Fallback if no order_total_cost record
                                error_log("No payment_info found for order_id: $order_id");
                                $stmt = $conn->prepare(
                                    "UPDATE order_total_cost 
                                    SET payment_status = 'paid', paid_at = NOW()
                                    WHERE swine_order_id = ?"
                                );
                                $stmt->bind_param('i', $order_id);
                                $stmt->execute();
                                $stmt->close();
                                
                                $_SESSION['success'] = 'Payment successful! Your order has been confirmed.';
                            }
                        } else {
                            // Old version without payment_type column - just update payment_status
                            error_log("payment_type column not found, using simple update");
                            $stmt = $conn->prepare(
                                "UPDATE order_total_cost 
                                SET payment_status = 'paid', paid_at = NOW()
                                WHERE swine_order_id = ?"
                            );
                            $stmt->bind_param('i', $order_id);
                            $stmt->execute();
                            $stmt->close();
                            
                            $_SESSION['success'] = 'Payment successful! Your order has been confirmed.';
                        }
                        
                        error_log("order_total_cost payment status updated");
                        
                        // Force session save before redirect
                        session_write_close();
                        session_start();
                        
                        $_SESSION['success'] = ' Payment successful! Your order has been confirmed.';
                        header('Location: ' . $base_url . '/customer/my-orders');
                        exit;
                    }
                    
                } elseif ($payment_reference) {
                    error_log("Updating payment status by reference: $payment_reference");
                    
                    // Fallback: try to find order by payment reference
                    $stmt = $conn->prepare(
                        "UPDATE swine_order_status 
                        SET payment_status = 'paid', updated_at = NOW()
                        WHERE payment_reference = ? AND customer_id = ?"
                    );
                    $stmt->bind_param('si', $payment_reference, $user['id']);
                    $stmt->execute();
                    $affected = $stmt->affected_rows;
                    $stmt->close();

                    // Also update order_total_cost if exists
                    $stmt = $conn->prepare(
                        "UPDATE order_total_cost otc
                        JOIN swine_order_status sos ON sos.id = otc.swine_order_id
                        SET otc.payment_status = 'paid', otc.paid_at = NOW()
                        WHERE sos.payment_reference = ? AND sos.customer_id = ?"
                    );
                    $stmt->bind_param('si', $payment_reference, $user['id']);
                    $stmt->execute();
                    $affected2 = $stmt->affected_rows;
                    $stmt->close();

                    if ($affected > 0 || $affected2 > 0) {
                        $_SESSION['success'] = 'Payment successful! Your order has been confirmed.';
                    } else {
                        $_SESSION['error'] = 'Payment confirmation failed. Please contact support.';
                    }
                    
                    // Force session save before redirect
                    session_write_close();
                    session_start();
                    
                } else {
                    error_log("Payment success callback: No order_id or reference provided");
                    $_SESSION['error'] = 'Payment confirmation failed. Please contact support.';
                }

                error_log("Redirecting to my-orders page");
                header('Location: ' . $base_url . '/customer/my-orders');
                exit;
            }
            break;

        case 'customer/payment-failed':
            if ($_SERVER['REQUEST_METHOD'] === 'GET') {
                $_SESSION['error'] = 'Payment failed or was cancelled. Please try again.';
                $order_type = $_GET['order_type'] ?? 'pig';
                $redirect_url = $order_type === 'lechon' 
                    ? $base_url . '/customer/my-orders?orders_tab=lechon'
                    : $base_url . '/customer/my-orders';
                header('Location: ' . $redirect_url);
                exit;
            }
            break;

        // ========== PAYMONGO WEBHOOK ==========
        case 'webhook/paymongo':
            if ($_SERVER['REQUEST_METHOD'] === 'POST') {
                try {
                    // Get raw POST data
                    $payload = file_get_contents('php://input');
                    $signature = $_SERVER['HTTP_PAYMONGO_SIGNATURE'] ?? '';
                    
                    error_log("PayMongo Webhook Received: " . $payload);
                    error_log("Signature: " . $signature);
                    
                    // Initialize PayMongo service
                    $payMongo = new PayMongoService(null, $conn);
                    
                    // Verify webhook signature (optional for testing)
                    if (!empty($signature)) {
                        if (!$payMongo->verifyWebhookSignature($payload, $signature)) {
                            http_response_code(401);
                            echo json_encode(['error' => 'Invalid signature']);
                            exit;
                        }
                    }
                    
                    // Parse webhook data
                    $data = json_decode($payload, true);
                    if (!$data) {
                        http_response_code(400);
                        echo json_encode(['error' => 'Invalid JSON']);
                        exit;
                    }
                    
                    // Handle webhook
                    $handled = $payMongo->handlePaymentWebhook($data);
                    
                    if ($handled) {
                        http_response_code(200);
                        echo json_encode(['status' => 'success']);
                    } else {
                        http_response_code(400);
                        echo json_encode(['error' => 'Webhook not handled']);
                    }
                    
                } catch (Exception $e) {
                    error_log("PayMongo Webhook Error: " . $e->getMessage());
                    http_response_code(500);
                    echo json_encode(['error' => 'Internal server error']);
                }
                exit;
            }
            break;

        // ========== VERIFY PAYMENT STATUS API ==========
        case 'api/verify-payment-status':
            if ($_SERVER['REQUEST_METHOD'] === 'POST') {
                // Set headers FIRST before any output
                header('Content-Type: application/json');
                
                // Turn off error display to prevent HTML output
                ini_set('display_errors', 0);
                
                if (!$sessionMiddleware->isAuthenticated()) {
                    http_response_code(403);
                    echo json_encode(['success' => false, 'error' => 'Unauthorized']);
                    exit;
                }
                
                try {
                    $input = file_get_contents('php://input');
                    $data = json_decode($input, true);
                    $order_id = (int)($data['order_id'] ?? 0);
                    $payment_reference = $data['payment_reference'] ?? '';
                    
                    error_log("Verify Payment Request - Order ID: $order_id, Payment Ref: $payment_reference");
                    
                    if (!$order_id || !$payment_reference) {
                        throw new Exception('Order ID and payment reference are required');
                    }
                    
                    $user = $sessionMiddleware->getUser();
                    
                    // Verify order belongs to current user
                    $stmt = $conn->prepare("SELECT id FROM swine_order_status WHERE id = ? AND customer_id = ?");
                    if (!$stmt) {
                        throw new Exception('Database prepare error: ' . $conn->error);
                    }
                    $stmt->bind_param('ii', $order_id, $user['id']);
                    $stmt->execute();
                    $result = $stmt->get_result();
                    if ($result->num_rows === 0) {
                        $stmt->close();
                        throw new Exception('Order not found or unauthorized');
                    }
                    $stmt->close();
                    
                    // Initialize PayMongo service
                    if (!class_exists('PayMongoService')) {
                        throw new Exception('PayMongoService class not found');
                    }
                    $payMongo = new PayMongoService(null, $conn);
                    
                    // Try to get the link/checkout session from PayMongo
                    try {
                        error_log("Fetching PayMongo link data for: $payment_reference");
                        $linkData = $payMongo->getCheckoutSession($payment_reference);
                        $linkStatus = $linkData['attributes']['status'] ?? 'unpaid';
                        
                        error_log("PayMongo Link Status for $payment_reference: $linkStatus");
                        
                        // If paid on PayMongo, update our database
                        if ($linkStatus === 'paid') {
                            // Update swine_order_status
                            $stmt = $conn->prepare("UPDATE swine_order_status SET payment_status = 'paid', updated_at = NOW() WHERE id = ?");
                            if (!$stmt) {
                                throw new Exception('Database prepare error: ' . $conn->error);
                            }
                            $stmt->bind_param('i', $order_id);
                            $stmt->execute();
                            $affected1 = $stmt->affected_rows;
                            $stmt->close();
                            
                            // Update order_total_cost (using amount_paid not paid_amount)
                            $stmt = $conn->prepare(
                                "UPDATE order_total_cost 
                                SET payment_status = 'paid', 
                                    paid_at = NOW(),
                                    amount_paid = total_cost,
                                    updated_at = NOW() 
                                WHERE payment_reference = ?"
                            );
                            if (!$stmt) {
                                throw new Exception('Database prepare error: ' . $conn->error);
                            }
                            $stmt->bind_param('s', $payment_reference);
                            $stmt->execute();
                            $affected2 = $stmt->affected_rows;
                            $stmt->close();
                            
                            error_log("Manually verified and updated payment for order $order_id (affected rows: $affected1, $affected2)");
                            
                            echo json_encode([
                                'success' => true,
                                'payment_status' => 'paid',
                                'message' => 'Payment verified and order updated successfully!'
                            ]);
                        } else {
                            echo json_encode([
                                'success' => true,
                                'payment_status' => $linkStatus,
                                'message' => 'Payment status from PayMongo: ' . $linkStatus . '. Please complete your payment if not yet done.'
                            ]);
                        }
                    } catch (Exception $e) {
                        error_log("Error fetching PayMongo link: " . $e->getMessage());
                        
                        // If PayMongo API fails, return a helpful message
                        echo json_encode([
                            'success' => false,
                            'message' => 'Unable to verify payment with PayMongo. This could mean: (1) Payment reference is invalid, (2) Network issue, or (3) Payment link has expired. Please try using "Pay Now" button again or contact support.',
                            'error' => $e->getMessage()
                        ]);
                    }
                    
                } catch (Exception $e) {
                    error_log("Verify payment error: " . $e->getMessage());
                    error_log("Stack trace: " . $e->getTraceAsString());
                    http_response_code(400);
                    echo json_encode([
                        'success' => false,
                        'message' => $e->getMessage(),
                        'error' => $e->getMessage()
                    ]);
                }
                exit;
            }
            break;

        // ========== LECHONERO ROUTES ==========
        case 'lechonero/orders':
            if ($_SERVER['REQUEST_METHOD'] === 'GET') {
                // RBAC check already done above
                require VIEWS_PATH . '/lechonero/orders.php';
            }
            break;

        case 'lechonero/schedule':
            if ($_SERVER['REQUEST_METHOD'] === 'GET') {
                // RBAC check already done above
                require VIEWS_PATH . '/lechonero/schedule.php';
            }
            break;

        case 'lechonero/cooking-status':
            if ($_SERVER['REQUEST_METHOD'] === 'GET') {
                // RBAC check already done above
                require VIEWS_PATH . '/lechonero/cooking-status.php';
            }
            break;

        case 'lechonero/profile':
            if ($_SERVER['REQUEST_METHOD'] === 'GET') {
                // RBAC check already done above
                require VIEWS_PATH . '/lechonero/profile.php';
            }
            break;

        case 'lechonero/reviews':
            if ($_SERVER['REQUEST_METHOD'] === 'GET') {
                // RBAC check already done above
                require_once APP_PATH . '/controllers/ReviewController.php';
                $reviewController = new ReviewController($conn);
                $reviewController->sellerReviews();
            }
            break;

        case 'lechonero/start-cooking':
            if ($_SERVER['REQUEST_METHOD'] === 'POST') {
                // RBAC check already done above
                header('Content-Type: application/json');
                
                try {
                    $order_number        = trim($_POST['order_number'] ?? '');
                    $initial_weight      = floatval($_POST['initial_weight'] ?? 0);
                    $estimated_cook_time = floatval($_POST['estimated_cook_time'] ?? 0);
                    $cooking_method      = trim($_POST['cooking_method'] ?? '');
                    $initial_notes       = trim($_POST['initial_notes'] ?? '');
                    $source_type         = trim($_POST['source_type'] ?? 'PIG_ORDER');
                    $lechon_order_id     = intval($_POST['lechon_order_id'] ?? 0);
                    
                    if (empty($order_number)) {
                        throw new Exception('Order number is required');
                    }
                    
                    // Validate cooking method if provided
                    $valid_cooking_methods = ['charcoal', 'wood', 'gas', 'electric', 'combination'];
                    if (!empty($cooking_method) && !in_array($cooking_method, $valid_cooking_methods)) {
                        throw new Exception('Invalid cooking method');
                    }
                    
                    // Update cooking_schedule with start time and cooking details (PIG_ORDER only;
                    // LECHON_ORDER does not use cooking_schedule — silently skip if no row exists)
                    $schedule_stmt = $conn->prepare(
                        "UPDATE cooking_schedule 
                         SET start_time = CURTIME(), 
                             initial_weight = ?, 
                             estimated_cook_time = ?, 
                             cooking_method = ?, 
                             initial_notes = ?, 
                             updated_at = CURRENT_TIMESTAMP
                         WHERE order_number = ?"
                    );
                    if ($schedule_stmt) {
                        $schedule_stmt->bind_param('ddsss', $initial_weight, $estimated_cook_time, $cooking_method, $initial_notes, $order_number);
                        $schedule_stmt->execute();
                        $schedule_stmt->close();
                    }
                    
                    // Update order status to 'cooking' on the correct table
                    if ($source_type === 'LECHON_ORDER' && $lechon_order_id > 0) {
                        // Marketplace lechon order — lechon_orders ENUM supports 'preparing' but not 'cooking'.
                        // We keep it as 'preparing' (= in-progress) since ENUM does not have 'cooking'.
                        // The cooking_status logic in schedule.php maps 'preparing' → ready_to_cook, so we
                        // insert a cooking_schedule row to signal cooking has started instead.
                        $cs_upsert = $conn->prepare(
                            "INSERT INTO cooking_schedule (order_number, start_time, initial_weight, estimated_cook_time, cooking_method, initial_notes)
                             VALUES (?, CURTIME(), ?, ?, ?, ?)
                             ON DUPLICATE KEY UPDATE
                                start_time = VALUES(start_time),
                                initial_weight = VALUES(initial_weight),
                                estimated_cook_time = VALUES(estimated_cook_time),
                                cooking_method = VALUES(cooking_method),
                                initial_notes = VALUES(initial_notes),
                                updated_at = CURRENT_TIMESTAMP"
                        );
                        if (!$cs_upsert) {
                            throw new Exception('Database prepare error: ' . $conn->error);
                        }
                        $cs_upsert->bind_param('sddss', $order_number, $initial_weight, $estimated_cook_time, $cooking_method, $initial_notes);
                        if (!$cs_upsert->execute()) {
                            throw new Exception('Database error saving cooking schedule: ' . $cs_upsert->error);
                        }
                        $cs_upsert->close();

                        // Also flip lechon_orders.order_status to 'preparing' if it is still 'confirmed'
                        // so the schedule view knows cooking has started (confirmed → preparing maps to cooking)
                        $lo_stmt = $conn->prepare(
                            "UPDATE lechon_orders SET order_status = 'preparing', updated_at = NOW()
                             WHERE id = ? AND order_status IN ('confirmed', 'preparing')"
                        );
                        if ($lo_stmt) {
                            $lo_stmt->bind_param('i', $lechon_order_id);
                            $lo_stmt->execute();
                            $lo_stmt->close();
                        }
                    } else {
                        // Pig order — update swine_order_status
                        $update_status_sql = "UPDATE swine_order_status sos 
                                             JOIN order_total_cost otc ON otc.swine_order_id = sos.id
                                             SET sos.order_status = 'cooking'
                                             WHERE otc.order_number = ?";
                        $stmt = $conn->prepare($update_status_sql);
                        if ($stmt) {
                            $stmt->bind_param('s', $order_number);
                            $stmt->execute();
                            $stmt->close();
                        }
                    }
                    
                    echo json_encode([
                        'success' => true, 
                        'message' => 'Cooking started successfully',
                        'data' => [
                            'start_time'          => date('H:i:s'),
                            'initial_weight'      => $initial_weight,
                            'estimated_cook_time' => $estimated_cook_time,
                            'cooking_method'      => $cooking_method
                        ]
                    ]);
                    
                } catch (Exception $e) {
                    error_log("Start cooking error: " . $e->getMessage());
                    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
                }
            }
            break;

        case 'lechonero/complete-cooking':
            if ($_SERVER['REQUEST_METHOD'] === 'POST') {
                // RBAC check already done above
                header('Content-Type: application/json');
                
                try {
                    // Get current logged-in user
                    $sessionMiddleware = new Session();
                    $currentUser = $sessionMiddleware->getUser();
                    $lechonero_user_id = intval($currentUser['id'] ?? 0);
                    
                    if (!$lechonero_user_id) {
                        throw new Exception('User session not found. Please log in again.');
                    }
                    
                    $order_number       = trim($_POST['order_number'] ?? '');
                    $internal_temperature = floatval($_POST['internal_temperature'] ?? 0);
                    $skin_texture       = trim($_POST['skin_texture'] ?? '');
                    $meat_tenderness    = trim($_POST['meat_tenderness'] ?? '');
                    $quality_notes      = trim($_POST['quality_notes'] ?? '');
                    $source_type        = trim($_POST['source_type'] ?? 'PIG_ORDER');
                    $lechon_order_id    = intval($_POST['lechon_order_id'] ?? 0);
                    
                    if (empty($order_number) || empty($skin_texture) || empty($meat_tenderness) || $internal_temperature <= 0) {
                        throw new Exception('All required fields must be filled');
                    }
                    
                    // Validate enum values
                    $valid_skin_textures = ['crispy', 'soft', 'burnt', 'undercooked'];
                    $valid_meat_tenderness = ['very_tender', 'tender', 'tough', 'very_tough'];
                    
                    if (!in_array($skin_texture, $valid_skin_textures)) {
                        throw new Exception('Invalid skin texture');
                    }
                    if (!in_array($meat_tenderness, $valid_meat_tenderness)) {
                        throw new Exception('Invalid meat tenderness');
                    }
                    
                    // Handle image upload
                    $image_path = null;
                    $skip_image = !empty($_POST['skip_image']) && $_POST['skip_image'] === '1';

                    if (!$skip_image) {
                        if (isset($_FILES['cooked_image']) && $_FILES['cooked_image']['error'] === UPLOAD_ERR_OK) {
                            $upload_dir = 'uploads/lechon_images/';
                            if (!is_dir($upload_dir)) {
                                mkdir($upload_dir, 0755, true);
                            }
                            
                            $file_extension = strtolower(pathinfo($_FILES['cooked_image']['name'], PATHINFO_EXTENSION));
                            $allowed_extensions = ['jpg', 'jpeg', 'png', 'gif'];
                            
                            if (!in_array($file_extension, $allowed_extensions)) {
                                throw new Exception('Invalid image format. Only JPG, PNG, and GIF allowed.');
                            }
                            if ($_FILES['cooked_image']['size'] > 5 * 1024 * 1024) {
                                throw new Exception('Image too large. Maximum size is 5MB.');
                            }
                            
                            $filename   = 'lechon_' . $order_number . '_' . time() . '.' . $file_extension;
                            $image_path = $upload_dir . $filename;
                            
                            if (!move_uploaded_file($_FILES['cooked_image']['tmp_name'], $image_path)) {
                                throw new Exception('Failed to upload image');
                            }
                        } else {
                            throw new Exception('Image upload is required');
                        }
                    }
                    
                    // Ensure lechon_status table exists
                    $create_table_sql = "CREATE TABLE IF NOT EXISTS `lechon_status` (
                        `id` int(11) NOT NULL AUTO_INCREMENT,
                        `order_number` varchar(50) NOT NULL,
                        `lechonero_user_id` int(11) NOT NULL,
                        `cooked_image` varchar(255) DEFAULT NULL,
                        `internal_temperature` decimal(5,2) DEFAULT NULL,
                        `skin_texture` enum('crispy', 'soft', 'burnt', 'undercooked') DEFAULT NULL,
                        `meat_tenderness` enum('very_tender', 'tender', 'tough', 'very_tough') DEFAULT NULL,
                        `quality_notes` text DEFAULT NULL,
                        `completed_at` timestamp NOT NULL DEFAULT current_timestamp(),
                        `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
                        `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
                        PRIMARY KEY (`id`),
                        UNIQUE KEY `unique_order` (`order_number`),
                        KEY `idx_order_number` (`order_number`),
                        KEY `idx_lechonero` (`lechonero_user_id`)
                    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci";
                    
                    if (!$conn->query($create_table_sql)) {
                        error_log("Failed to create lechon_status table: " . $conn->error);
                    }
                    
                    // Insert / update quality record in lechon_status (same for both source types)
                    $sql = "INSERT INTO lechon_status 
                            (order_number, lechonero_user_id, cooked_image, internal_temperature, skin_texture, meat_tenderness, quality_notes) 
                            VALUES (?, ?, ?, ?, ?, ?, ?)
                            ON DUPLICATE KEY UPDATE
                            cooked_image = VALUES(cooked_image),
                            internal_temperature = VALUES(internal_temperature),
                            skin_texture = VALUES(skin_texture),
                            meat_tenderness = VALUES(meat_tenderness),
                            quality_notes = VALUES(quality_notes),
                            completed_at = current_timestamp()";
                    
                    $stmt = $conn->prepare($sql);
                    if (!$stmt) {
                        throw new Exception('Database prepare error: ' . $conn->error);
                    }
                    $stmt->bind_param('sisdsss', $order_number, $lechonero_user_id, $image_path, $internal_temperature, $skin_texture, $meat_tenderness, $quality_notes);
                    if (!$stmt->execute()) {
                        throw new Exception('Database error: ' . $stmt->error);
                    }
                    $stmt->close();
                    
                    // Update order status to 'delivering' on the correct table
                    if ($source_type === 'LECHON_ORDER' && $lechon_order_id > 0) {
                        // Marketplace lechon order — update lechon_orders directly
                        $update_sql = "UPDATE lechon_orders
                                       SET order_status = 'delivering', updated_at = NOW()
                                       WHERE id = ?
                                       AND order_status IN ('confirmed', 'preparing', 'cooking')";
                        $stmt = $conn->prepare($update_sql);
                        if (!$stmt) {
                            throw new Exception('Database prepare error updating lechon order: ' . $conn->error);
                        }
                        $stmt->bind_param('i', $lechon_order_id);
                        if (!$stmt->execute()) {
                            throw new Exception('Database error updating lechon order status: ' . $stmt->error);
                        }
                        $stmt->close();
                    } else {
                        // Pig order — update swine_order_status via order_total_cost join
                        $update_sql = "UPDATE swine_order_status sos 
                                       JOIN order_total_cost otc ON otc.swine_order_id = sos.id
                                       SET sos.order_status = 'delivering'
                                       WHERE otc.order_number = ?";
                        $stmt = $conn->prepare($update_sql);
                        if ($stmt) {
                            $stmt->bind_param('s', $order_number);
                            $stmt->execute();
                            $stmt->close();
                        }
                    }
                    
                    // Update cooking_schedule with end time and final details (applies to both)
                    $final_weight    = floatval($_POST['final_weight'] ?? 0);
                    $actual_cook_time = floatval($_POST['actual_cook_time'] ?? 0);
                    
                    $cooking_end_sql = "UPDATE cooking_schedule 
                                        SET end_time = CURTIME(), 
                                            final_weight = ?, 
                                            actual_cook_time = ?, 
                                            updated_at = CURRENT_TIMESTAMP
                                        WHERE order_number = ?";
                    $stmt = $conn->prepare($cooking_end_sql);
                    if ($stmt) {
                        $stmt->bind_param('dds', $final_weight, $actual_cook_time, $order_number);
                        $stmt->execute();
                        $stmt->close();
                    }
                    
                    echo json_encode(['success' => true, 'message' => 'Lechon cooking completed successfully']);
                    
                } catch (Exception $e) {
                    error_log("Lechon completion error: " . $e->getMessage());
                    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
                }
            }
            break;

        // ========== LOGISTICS ROUTES ==========
        case 'logistics/delivery-status':
            if ($_SERVER['REQUEST_METHOD'] === 'GET') {
                // RBAC check already done above
                require VIEWS_PATH . '/logistics/delivery-status.php';
            }
            break;

        case 'logistics/complete-delivery':
            if ($_SERVER['REQUEST_METHOD'] === 'POST') {
                header('Content-Type: application/json');
                try {
                    // Accept FormData (multipart) instead of JSON
                    $order_number = trim($_POST['order_number'] ?? '');
                    $is_pickup = isset($_POST['is_pickup']) && $_POST['is_pickup'] === 'true';
                    
                    if (empty($order_number)) {
                        throw new Exception('Order number is required');
                    }
                    
                    // For pickup, we don't need delivery time or photos
                    if (!$is_pickup) {
                        $actual_delivery_time = trim($_POST['actual_delivery_time'] ?? '');
                        
                        if (empty($actual_delivery_time)) {
                            throw new Exception('Actual delivery time is required');
                        }
                        
                        // Validate and parse the time (HH:MM format)
                        if (!preg_match('/^\d{2}:\d{2}$/', $actual_delivery_time)) {
                            throw new Exception('Invalid time format. Please use HH:MM format.');
                        }
                        
                        // Create datetime with today's date and provided time
                        $delivered_at = date('Y-m-d') . ' ' . $actual_delivery_time . ':00';
                    } else {
                        // For pickup, use current time
                        $delivered_at = date('Y-m-d H:i:s');
                    }
                    
                    // Handle delivery photo upload (required only for delivery, not pickup)
                    $delivery_photo_path = null;
                    if (!$is_pickup) {
                        if (isset($_FILES['delivery_photo']) && $_FILES['delivery_photo']['error'] === UPLOAD_ERR_OK) {
                            $upload_dir = 'uploads/delivery_photos/';
                            if (!is_dir($upload_dir)) mkdir($upload_dir, 0755, true);
                            $ext = strtolower(pathinfo($_FILES['delivery_photo']['name'], PATHINFO_EXTENSION));
                            if (!in_array($ext, ['jpg','jpeg','png','gif'])) throw new Exception('Invalid delivery photo format.');
                            if ($_FILES['delivery_photo']['size'] > 5 * 1024 * 1024) throw new Exception('Delivery photo too large (max 5MB).');
                            $fname = 'delivery_' . $order_number . '_' . time() . '.' . $ext;
                            if (!move_uploaded_file($_FILES['delivery_photo']['tmp_name'], $upload_dir . $fname)) throw new Exception('Failed to save delivery photo.');
                            $delivery_photo_path = $upload_dir . $fname;
                        } else {
                            throw new Exception('Delivery photo is required.');
                        }
                    }
                    
                    // Handle payment collection photo (only required for downpayment orders, not for pickup)
                    $payment_photo_path = null;
                    if (!$is_pickup && isset($_FILES['payment_photo']) && $_FILES['payment_photo']['error'] === UPLOAD_ERR_OK) {
                        $upload_dir = 'uploads/delivery_photos/';
                        $ext = strtolower(pathinfo($_FILES['payment_photo']['name'], PATHINFO_EXTENSION));
                        if (!in_array($ext, ['jpg','jpeg','png','gif'])) throw new Exception('Invalid payment photo format.');
                        if ($_FILES['payment_photo']['size'] > 5 * 1024 * 1024) throw new Exception('Payment photo too large (max 5MB).');
                        $fname = 'payment_' . $order_number . '_' . time() . '.' . $ext;
                        if (!move_uploaded_file($_FILES['payment_photo']['tmp_name'], $upload_dir . $fname)) throw new Exception('Failed to save payment photo.');
                        $payment_photo_path = $upload_dir . $fname;
                    }
                    
                    // Get customer and owner user IDs
                    $stmt = $conn->prepare("
                        SELECT sos.customer_id, lo.user_id as owner_user_id 
                        FROM swine_order_status sos
                        LEFT JOIN livestock_owners lo ON lo.id = sos.livestock_owner_id
                        WHERE sos.order_number = ?
                    ");
                    if (!$stmt) {
                        throw new Exception('Prepare error: ' . $conn->error);
                    }
                    $stmt->bind_param('s', $order_number);
                    $stmt->execute();
                    $orderData = $stmt->get_result()->fetch_assoc();
                    $stmt->close();
                    
                    if (!$orderData) {
                        throw new Exception('Order not found');
                    }
                    
                    $customer_id = $orderData['customer_id'];
                    $owner_user_id = $orderData['owner_user_id'];
                    
                    // Update order status in swine_order_status
                    $stmt = $conn->prepare("
                        UPDATE swine_order_status 
                        SET order_status = 'completed', payment_status = 'paid', completed_at = NOW(), updated_at = NOW() 
                        WHERE order_number = ?
                    ");
                    if (!$stmt) {
                        throw new Exception('Prepare error: ' . $conn->error);
                    }
                    $stmt->bind_param('s', $order_number);
                    $stmt->execute();
                    $stmt->close();
                    
                    // Update order status in order_total_cost
                    $stmt = $conn->prepare("
                        UPDATE order_total_cost 
                        SET order_status = 'completed', payment_status = 'paid', amount_paid = total_cost, remaining_balance = 0.00, updated_at = NOW() 
                        WHERE order_number = ?
                    ");
                    if ($stmt) {
                        $stmt->bind_param('s', $order_number);
                        $stmt->execute();
                        $stmt->close();
                    }
                    
                    // Get current logistics user from session
                    $sessionMiddleware = new Session();
                    $logisticsUser = $sessionMiddleware->getUser();
                    $driver_user_id = intval($logisticsUser['id'] ?? 0);
                    
                    // Get delivery details for the record
                    $detailStmt = $conn->prepare("
                        SELECT otc.delivery_address, sos.payment_method, 
                            sos.total_price, otc.delivery_method, otc.delivery_fee,
                            sos.completed_at
                        FROM swine_order_status sos
                        JOIN order_total_cost otc ON otc.swine_order_id = sos.id
                        WHERE otc.order_number = ?
                    ");
                    $deliveryDetails = [];
                    if ($detailStmt) {
                        $detailStmt->bind_param('s', $order_number);
                        $detailStmt->execute();
                        $deliveryDetails = $detailStmt->get_result()->fetch_assoc() ?? [];
                        $detailStmt->close();
                    }
                    
                    // Create delivery_status table if it doesn't exist
                    $conn->query("CREATE TABLE IF NOT EXISTS `delivery_status` (
                        `id` int(11) NOT NULL AUTO_INCREMENT,
                        `order_number` varchar(50) NOT NULL,
                        `driver_user_id` int(11) DEFAULT NULL,
                        `delivery_method` enum('delivery','pickup') DEFAULT 'delivery',
                        `delivery_address` text DEFAULT NULL,
                        `delivery_photo` varchar(255) DEFAULT NULL,
                        `payment_photo` varchar(255) DEFAULT NULL,
                        `delivered_at` timestamp NOT NULL DEFAULT current_timestamp(),
                        `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
                        PRIMARY KEY (`id`),
                        UNIQUE KEY `unique_order` (`order_number`),
                        KEY `idx_order_number` (`order_number`),
                        KEY `idx_driver` (`driver_user_id`)
                    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci");
                    
                    // Insert into delivery_status
                    $deliveryMethod = $deliveryDetails['delivery_method'] ?? 'delivery';
                    $deliveryAddress = $deliveryDetails['delivery_address'] ?? null;
                    
                    $insertStmt = $conn->prepare("
                        INSERT INTO delivery_status 
                            (order_number, driver_user_id, delivery_method, delivery_address, delivery_photo, payment_photo, delivered_at)
                        VALUES (?, ?, ?, ?, ?, ?, ?)
                        ON DUPLICATE KEY UPDATE
                            driver_user_id = VALUES(driver_user_id),
                            delivery_photo = VALUES(delivery_photo),
                            payment_photo = VALUES(payment_photo),
                            delivered_at = VALUES(delivered_at)
                    ");
                    if ($insertStmt) {
                        $insertStmt->bind_param('ssissss', $order_number, $driver_user_id, $deliveryMethod, $deliveryAddress, $delivery_photo_path, $payment_photo_path, $delivered_at);
                        $insertStmt->execute();
                        $insertStmt->close();
                    }
                    
                    // Create notifications
                    if ($customer_id) {
                        $notification = new Notification($conn);
                        $notification->create(
                            $customer_id,
                            'delivery_completed',
                            'Lechon Delivered! 🍖',
                            "Your lechon order {$order_number} has been successfully delivered and completed. Enjoy!",
                            '/customer/my-orders'
                        );
                    }
                    
                    if ($owner_user_id) {
                        $notification = new Notification($conn);
                        $notification->create(
                            $owner_user_id,
                            'delivery_completed',
                            'Order Delivery Completed',
                            "Order {$order_number} has been delivered and marked as completed.",
                            '/livestock-owner/lechon-logs'
                        );
                    }
                    
                    echo json_encode(['success' => true, 'message' => 'Delivery completed successfully']);
                    
                } catch (Exception $e) {
                    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
                }
                exit;
            }
            break;

        case 'logistics/track-orders':
            if ($_SERVER['REQUEST_METHOD'] === 'GET') {
                // RBAC check already done above
                // Placeholder for track orders
                echo '<div style="padding: 20px; text-align: center;"><h2>Track Orders</h2><p>Order tracking feature coming soon!</p></div>';
            }
            break;

        case 'logistics/schedule':
            if ($_SERVER['REQUEST_METHOD'] === 'GET') {
                // RBAC check already done above
                // Placeholder for schedule
                echo '<div style="padding: 20px; text-align: center;"><h2>Delivery Schedule</h2><p>Schedule management coming soon!</p></div>';
            }
            break;

        case 'logistics/profile':
            if ($_SERVER['REQUEST_METHOD'] === 'GET') {
                // RBAC check already done above
                // Placeholder for driver profile
                echo '<div style="padding: 20px; text-align: center;"><h2>Driver Profile</h2><p>Profile management coming soon!</p></div>';
            }
            break;

        // ========== CUSTOMER - BUY A PIG ==========
        case 'customer/buy-pig':
            if ($_SERVER['REQUEST_METHOD'] === 'GET') {
                require VIEWS_PATH . '/customer/buy-pig.php';
            }
            break;

        case 'customer/pig-inquiry':
            if ($_SERVER['REQUEST_METHOD'] === 'POST') {
                try {
                    $user        = $sessionMiddleware->getUser();
                    $listing_id  = (int)($_POST['listing_id'] ?? 0);
                    $message     = trim($_POST['message'] ?? '');

                    if (!$listing_id || empty($message)) {
                        throw new Exception('Invalid inquiry data');
                    }

                    // Get listing + owner info — only allow if still active
                    $stmt = $conn->prepare(
                        "SELECT hm.pig_tag_id, hm.pig_detail_id, hm.pin_number, hm.weight_kg, 
                                hm.price_per_kg, hm.total_price, hm.status, 
                                lo.id as livestock_owner_id, lo.user_id as owner_user_id
                        FROM hogs_market hm
                        JOIN livestock_owners lo ON lo.id = hm.livestock_owner_id
                        WHERE hm.id = ? AND hm.status = 'active'"
                    );
                    $stmt->bind_param('i', $listing_id);
                    $stmt->execute();
                    $listing = $stmt->get_result()->fetch_assoc();
                    $stmt->close();

                    if (!$listing) throw new Exception('This pig is no longer available.');

                    // Generate order number
                    $order_number = 'PIG-' . $user['id'] . '-' . time();

                    // Create swine order status entry
                    $orderStmt = $conn->prepare(
                        "INSERT INTO swine_order_status 
                        (order_number, customer_id, livestock_owner_id, hogs_market_id, pig_detail_id, 
                        pig_tag_id, pin_number, weight_kg, price_per_kg, total_price, inquiry_message, order_status)
                        VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 'pending')"
                    );
                    $orderStmt->bind_param('siiiiissdds', 
                        $order_number, 
                        $user['id'], 
                        $listing['livestock_owner_id'],
                        $listing_id,
                        $listing['pig_detail_id'],
                        $listing['pig_tag_id'],
                        $listing['pin_number'],
                        $listing['weight_kg'],
                        $listing['price_per_kg'],
                        $listing['total_price'],
                        $message
                    );
                    $orderStmt->execute();
                    $swine_order_id = $conn->insert_id;
                    $orderStmt->close();

                    // Reserve the listing in hogs_market
                    $upd = $conn->prepare(
                        "UPDATE hogs_market SET status='reserved', reserved_by_user_id=?, reserved_by_name=?, inquiry_message=?, reserved_at=NOW()
                        WHERE id = ? AND status='active'"
                    );
                    $upd->bind_param('issi', $user['id'], $user['name'], $message, $listing_id);
                    $upd->execute();
                    $upd->close();

                    // Notify the livestock owner
                    $notification = new Notification($conn);
                    $notification->create(
                        $listing['owner_user_id'],
                        'pig_inquiry',
                        ' Pig Reserved — Action Required',
                        $user['name'] . ' wants to buy ' . $listing['pig_tag_id'] .
                            ' (₱' . number_format($listing['total_price'], 2) . '). Message: "' . $message . '". Go to My Pig Market to confirm.',
                        '/livestock-owner/my-pig-market'
                    );

                    $_SESSION['success'] = ' Your reservation has been sent! The seller will confirm shortly.';
                    header('Location: ' . $base_url . '/customer/buy-pig');
                    exit;
                } catch (Exception $e) {
                    $_SESSION['error'] = $e->getMessage();
                    header('Location: ' . $base_url . '/customer/buy-pig');
                    exit;
                }
            }
            break;

        // ========== CUSTOMER - LECHON INQUIRY (reserve a lechon listing) ==========
        case 'customer/lechon-inquiry':
            if ($_SERVER['REQUEST_METHOD'] === 'POST') {
                try {
                    $user        = $sessionMiddleware->getUser();
                    $listing_id  = (int)($_POST['listing_id'] ?? 0);
                    $message     = trim($_POST['message'] ?? '');

                    if (!$listing_id || empty($message)) {
                        throw new Exception('Invalid inquiry data');
                    }

                    // ── Ensure lechon_orders table exists (auto-create if needed) ──
                    $conn->query("CREATE TABLE IF NOT EXISTS `lechon_orders` (
                        `id`                  INT AUTO_INCREMENT PRIMARY KEY,
                        `order_number`        VARCHAR(100) NOT NULL UNIQUE,
                        `customer_id`         INT NOT NULL,
                        `livestock_owner_id`  INT NOT NULL,
                        `lechon_listing_id`   INT NOT NULL,
                        `listing_name`        VARCHAR(255) NOT NULL,
                        `category`            ENUM('WHOLE_LECHON','WHOLE_PACKAGES','BELLY_BUNDLES','WEEKDAY_COMBOS','EVENT_CATERING','OTHER') NOT NULL DEFAULT 'WHOLE_LECHON',
                        `weight_kg`           DECIMAL(8,2) NULL,
                        `serving_capacity`    VARCHAR(100) NULL,
                        `price`               DECIMAL(10,2) NOT NULL,
                        `inquiry_message`     TEXT NULL,
                        `order_status`        ENUM('pending','confirmed','preparing','cost_computed','ready_for_pickup','completed','cancelled') NOT NULL DEFAULT 'pending',
                        `payment_status`      ENUM('unpaid','paid','partially_paid','refunded') NOT NULL DEFAULT 'unpaid',
                        `payment_method`      VARCHAR(50) NULL,
                        `payment_reference`   VARCHAR(255) NULL,
                        `seller_feedback`     TEXT NULL,
                        `pickup_date`         DATE NULL,
                        `delivery_method`     ENUM('pickup','delivery') NULL DEFAULT 'pickup',
                        `delivery_address`    TEXT NULL,
                        `delivery_notes`      TEXT NULL,
                        `reserved_by_name`    VARCHAR(200) NULL,
                        `reserved_at`         DATETIME NULL,
                        `cancelled_at`        DATETIME NULL,
                        `cancellation_reason` TEXT NULL,
                        `completed_at`        DATETIME NULL,
                        `created_at`          TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                        `updated_at`          TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                        INDEX `idx_customer`   (`customer_id`),
                        INDEX `idx_owner`      (`livestock_owner_id`),
                        INDEX `idx_listing`    (`lechon_listing_id`),
                        INDEX `idx_status`     (`order_status`),
                        INDEX `idx_pay_status` (`payment_status`)
                    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");

                    // ── Fetch the lechon listing — only allow active listings ──
                    $stmt = $conn->prepare(
                        "SELECT ll.id, ll.name, ll.category, ll.weight_kg, ll.serving_capacity,
                                ll.price, ll.status, ll.livestock_owner_id,
                                lo.user_id as owner_user_id
                         FROM lechon_listings ll
                         JOIN livestock_owners lo ON lo.id = ll.livestock_owner_id
                         WHERE ll.id = ? AND ll.status = 'active'"
                    );
                    $stmt->bind_param('i', $listing_id);
                    $stmt->execute();
                    $listing = $stmt->get_result()->fetch_assoc();
                    $stmt->close();

                    if (!$listing) {
                        throw new Exception('This lechon listing is no longer available.');
                    }

                    // ── Generate order number (mirrors pig format) ──
                    $order_number = 'LCH-' . $user['id'] . '-' . time();

                    // ── Insert into lechon_orders ──
                    $orderStmt = $conn->prepare(
                        "INSERT INTO lechon_orders
                         (order_number, customer_id, livestock_owner_id, lechon_listing_id,
                          listing_name, category, weight_kg, serving_capacity, price,
                          inquiry_message, order_status, reserved_by_name, reserved_at)
                         VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 'pending', ?, NOW())"
                    );
                    $orderStmt->bind_param(
                        'siiissdsdss',
                        $order_number,
                        $user['id'],
                        $listing['livestock_owner_id'],
                        $listing_id,
                        $listing['name'],
                        $listing['category'],
                        $listing['weight_kg'],
                        $listing['serving_capacity'],
                        $listing['price'],
                        $message,
                        $user['name']
                    );
                    $orderStmt->execute();
                    $orderStmt->close();

                    // ── Notify the livestock owner ──
                    $notification = new Notification($conn);
                    $notification->create(
                        $listing['owner_user_id'],
                        'lechon_inquiry',
                        'Lechon Reserved — Action Required',
                        $user['name'] . ' wants to reserve ' . $listing['name'] .
                            ' (₱' . number_format($listing['price'], 2) . '). Message: "' . $message .
                            '". Go to My Pig Market -> Lechon tab to confirm.',
                        '/livestock-owner/my-pig-market?tab=lechon'
                    );

                    $_SESSION['success'] = 'Your lechon reservation has been sent! The seller will confirm shortly.';
                    header('Location: ' . $base_url . '/customer/buy-pig?tab=lechon');
                    exit;
                } catch (Exception $e) {
                    $_SESSION['error'] = $e->getMessage();
                    header('Location: ' . $base_url . '/customer/buy-pig?tab=lechon');
                    exit;
                }
            }
            break;

        // ── Customer: cancel a pending lechon order ──────────────────────
        case 'customer/cancel-lechon-order':
            if ($_SERVER['REQUEST_METHOD'] === 'POST') {
                try {
                    $user     = $sessionMiddleware->getUser();
                    $order_id = (int)($_POST['order_id'] ?? 0);
                    if (!$order_id) throw new Exception('Invalid order');

                    $stmt = $conn->prepare(
                        "UPDATE lechon_orders
                         SET order_status = 'cancelled', cancelled_at = NOW(),
                             cancellation_reason = 'Cancelled by customer'
                         WHERE id = ? AND customer_id = ? AND order_status = 'pending'"
                    );
                    $stmt->bind_param('ii', $order_id, $user['id']);
                    $stmt->execute();
                    if ($stmt->affected_rows === 0) {
                        throw new Exception('Order cannot be cancelled (it may already be confirmed or does not belong to you).');
                    }
                    $stmt->close();

                    $_SESSION['success'] = 'Lechon order cancelled.';
                    header('Location: ' . $base_url . '/customer/my-orders?orders_tab=lechon');
                    exit;
                } catch (Exception $e) {
                    $_SESSION['error'] = $e->getMessage();
                    header('Location: ' . $base_url . '/customer/my-orders?orders_tab=lechon');
                    exit;
                }
            }
            break;

        // ── Customer: place a confirmed lechon order (delivery details) ──
        case 'customer/place-lechon-order':
            if ($_SERVER['REQUEST_METHOD'] === 'POST') {
                try {
                    $user              = $sessionMiddleware->getUser();
                    $lechon_order_id   = (int)($_POST['lechon_order_id'] ?? 0);
                    $order_number      = trim($_POST['order_number'] ?? '');
                    $delivery_method   = in_array($_POST['delivery_method'] ?? '', ['pickup','delivery'])
                                         ? $_POST['delivery_method'] : 'pickup';
                    $delivery_address  = trim($_POST['delivery_address'] ?? '');
                    $delivery_notes    = trim($_POST['delivery_notes'] ?? '');
                    $pickup_date       = trim($_POST['pickup_date'] ?? '');

                    if (!$lechon_order_id) throw new Exception('Invalid order');
                    if ($delivery_method === 'delivery' && empty($delivery_address)) {
                        throw new Exception('Please enter a delivery address');
                    }
                    if (empty($pickup_date)) throw new Exception('Please select a pickup / delivery date');

                    $pickup_timestamp = strtotime($pickup_date);
                    if ($pickup_timestamp < strtotime('today')) {
                        throw new Exception('Pickup/delivery date cannot be in the past');
                    }

                    // Verify the order belongs to this customer and is confirmed
                    $chkStmt = $conn->prepare(
                        "SELECT id, livestock_owner_id FROM lechon_orders
                         WHERE id = ? AND customer_id = ? AND order_status = 'confirmed'"
                    );
                    $chkStmt->bind_param('ii', $lechon_order_id, $user['id']);
                    $chkStmt->execute();
                    $ordRow = $chkStmt->get_result()->fetch_assoc();
                    $chkStmt->close();
                    if (!$ordRow) throw new Exception('Order not found or cannot be placed at this time.');

                    // Store delivery details on the lechon_orders row itself
                    $updStmt = $conn->prepare(
                        "UPDATE lechon_orders
                         SET order_status      = 'preparing',
                             pickup_date       = ?,
                             delivery_method   = ?,
                             delivery_address  = ?,
                             delivery_notes    = ?,
                             updated_at        = NOW()
                         WHERE id = ? AND customer_id = ?"
                    );
                    $updStmt->bind_param('ssssii', $pickup_date, $delivery_method, $delivery_address, $delivery_notes, $lechon_order_id, $user['id']);
                    $updStmt->execute();
                    $updStmt->close();

                    // Also persist delivery address + notes in the session so seller can see them
                    // (stored as a JSON note appended to seller_feedback; or we add columns later)
                    // For now store in session and show a summary
                    if ($delivery_method === 'delivery') {
                        $note = 'Delivery to: ' . $delivery_address;
                        if ($delivery_notes) $note .= '. Notes: ' . $delivery_notes;
                    } else {
                        $note = 'Pickup';
                        if ($delivery_notes) $note .= '. Notes: ' . $delivery_notes;
                    }

                    // Save delivery details to lechon_orders via seller_feedback column as a quick store
                    $noteStmt = $conn->prepare(
                        "UPDATE lechon_orders SET seller_feedback = ? WHERE id = ?"
                    );
                    $noteStmt->bind_param('si', $note, $lechon_order_id);
                    $noteStmt->execute();
                    $noteStmt->close();

                    // Notify the seller
                    $getOwnerUser = $conn->prepare(
                        "SELECT lo.user_id, lor.listing_name
                         FROM lechon_orders lor
                         JOIN livestock_owners lo ON lo.id = lor.livestock_owner_id
                         WHERE lor.id = ?"
                    );
                    $getOwnerUser->bind_param('i', $lechon_order_id);
                    $getOwnerUser->execute();
                    $ownerInfo = $getOwnerUser->get_result()->fetch_assoc();
                    $getOwnerUser->close();

                    if ($ownerInfo) {
                        $notification = new Notification($conn);
                        $notification->create(
                            $ownerInfo['user_id'],
                            'lechon_order_placed',
                            'Lechon Order Details Received',
                            $user['name'] . ' has placed their order for ' . $ownerInfo['listing_name'] .
                                '. ' . $note . '. Pickup date: ' . date('M d, Y', strtotime($pickup_date)),
                            '/livestock-owner/my-pig-market?tab=lechon'
                        );
                    }

                    $_SESSION['success'] = 'Order placed! The seller has been notified and will prepare your lechon.';
                    header('Location: ' . $base_url . '/customer/my-orders?orders_tab=lechon');
                    exit;
                } catch (Exception $e) {
                    $_SESSION['error'] = $e->getMessage();
                    header('Location: ' . $base_url . '/customer/my-orders?orders_tab=lechon');
                    exit;
                }
            }
            break;

        // ── Livestock owner: confirm a lechon order ───────────────────────
        case 'livestock-owner/confirm-lechon-order':
            if ($_SERVER['REQUEST_METHOD'] === 'POST') {
                try {
                    $user     = $sessionMiddleware->getUser();
                    $order_id = (int)($_POST['order_id'] ?? 0);
                    if (!$order_id) throw new Exception('Invalid order');

                    // Verify ownership
                    $ownerStmt = $conn->prepare("SELECT id FROM livestock_owners WHERE user_id = ?");
                    $ownerStmt->bind_param('i', $user['id']);
                    $ownerStmt->execute();
                    $owner = $ownerStmt->get_result()->fetch_assoc();
                    $ownerStmt->close();
                    if (!$owner) throw new Exception('Owner not found');

                    $stmt = $conn->prepare(
                        "UPDATE lechon_orders
                         SET order_status = 'confirmed', updated_at = NOW()
                         WHERE id = ? AND livestock_owner_id = ? AND order_status = 'pending'"
                    );
                    $stmt->bind_param('ii', $order_id, $owner['id']);
                    $stmt->execute();
                    if ($stmt->affected_rows === 0) throw new Exception('Order not found or already processed.');
                    $stmt->close();

                    // Get customer_id for notification
                    $getOrd = $conn->prepare("SELECT customer_id, listing_name FROM lechon_orders WHERE id = ?");
                    $getOrd->bind_param('i', $order_id);
                    $getOrd->execute();
                    $ordRow = $getOrd->get_result()->fetch_assoc();
                    $getOrd->close();

                    if ($ordRow) {
                        $notification = new Notification($conn);
                        $notification->create(
                            $ordRow['customer_id'],
                            'lechon_confirmed',
                            'Lechon Order Confirmed',
                            'Your order for ' . $ordRow['listing_name'] . ' has been confirmed by the seller. They are now preparing your order.',
                            '/customer/my-orders?orders_tab=lechon'
                        );
                    }

                    $_SESSION['success'] = 'Lechon order confirmed. Customer has been notified.';
                    header('Location: ' . $base_url . '/livestock-owner/my-pig-market?tab=lechon');
                    exit;
                } catch (Exception $e) {
                    $_SESSION['error'] = $e->getMessage();
                    header('Location: ' . $base_url . '/livestock-owner/my-pig-market?tab=lechon');
                    exit;
                }
            }
            break;

        // ── Livestock owner: decline a lechon order ───────────────────────
        case 'livestock-owner/decline-lechon-order':
            if ($_SERVER['REQUEST_METHOD'] === 'POST') {
                try {
                    $user     = $sessionMiddleware->getUser();
                    $order_id = (int)($_POST['order_id'] ?? 0);
                    if (!$order_id) throw new Exception('Invalid order');

                    $ownerStmt = $conn->prepare("SELECT id FROM livestock_owners WHERE user_id = ?");
                    $ownerStmt->bind_param('i', $user['id']);
                    $ownerStmt->execute();
                    $owner = $ownerStmt->get_result()->fetch_assoc();
                    $ownerStmt->close();
                    if (!$owner) throw new Exception('Owner not found');

                    $stmt = $conn->prepare(
                        "UPDATE lechon_orders
                         SET order_status = 'cancelled', cancelled_at = NOW(),
                             cancellation_reason = 'Declined by seller'
                         WHERE id = ? AND livestock_owner_id = ? AND order_status = 'pending'"
                    );
                    $stmt->bind_param('ii', $order_id, $owner['id']);
                    $stmt->execute();
                    if ($stmt->affected_rows === 0) throw new Exception('Order not found or already processed.');
                    $stmt->close();

                    // Notify customer
                    $getOrd = $conn->prepare("SELECT customer_id, listing_name FROM lechon_orders WHERE id = ?");
                    $getOrd->bind_param('i', $order_id);
                    $getOrd->execute();
                    $ordRow = $getOrd->get_result()->fetch_assoc();
                    $getOrd->close();

                    if ($ordRow) {
                        $notification = new Notification($conn);
                        $notification->create(
                            $ordRow['customer_id'],
                            'lechon_declined',
                            'Lechon Order Declined',
                            'Unfortunately your order for ' . $ordRow['listing_name'] . ' was declined by the seller. You may order from another listing.',
                            '/customer/buy-pig?tab=lechon'
                        );
                    }

                    $_SESSION['success'] = 'Lechon order declined. Customer has been notified.';
                    header('Location: ' . $base_url . '/livestock-owner/my-pig-market?tab=lechon');
                    exit;
                } catch (Exception $e) {
                    $_SESSION['error'] = $e->getMessage();
                    header('Location: ' . $base_url . '/livestock-owner/my-pig-market?tab=lechon');
                    exit;
                }
            }
            break;

        // ── Livestock owner: send order to logistics (mark as delivering) ──
        case 'livestock-owner/send-to-logistics':
            if ($_SERVER['REQUEST_METHOD'] === 'POST') {
                header('Content-Type: application/json');
                try {
                    $input          = json_decode(file_get_contents('php://input'), true);
                    $order_number   = trim($input['order_number']    ?? '');
                    $lechon_order_id = (int)($input['lechon_order_id'] ?? 0);
                    $source_type    = trim($input['source_type']      ?? '');

                    if (empty($order_number)) throw new Exception('Order number is required');

                    $user = $sessionMiddleware->getUser();
                    $ownerStmt = $conn->prepare("SELECT id FROM livestock_owners WHERE user_id = ?");
                    $ownerStmt->bind_param('i', $user['id']);
                    $ownerStmt->execute();
                    $owner = $ownerStmt->get_result()->fetch_assoc();
                    $ownerStmt->close();
                    if (!$owner) throw new Exception('Owner profile not found');

                    if ($source_type === 'LECHON_ORDER' && $lechon_order_id > 0) {
                        // Marketplace lechon order — update lechon_orders table
                        $stmt = $conn->prepare(
                            "UPDATE lechon_orders
                             SET order_status = 'delivering', updated_at = NOW()
                             WHERE id = ? AND livestock_owner_id = ?
                             AND order_status IN ('confirmed','preparing','cooking')"
                        );
                        $stmt->bind_param('ii', $lechon_order_id, $owner['id']);
                        $stmt->execute();
                        if ($stmt->affected_rows === 0) throw new Exception('Order not found or already sent to logistics.');
                        $stmt->close();

                        // Notify customer
                        $getOrd = $conn->prepare("SELECT customer_id, listing_name FROM lechon_orders WHERE id = ?");
                        $getOrd->bind_param('i', $lechon_order_id);
                        $getOrd->execute();
                        $ordRow = $getOrd->get_result()->fetch_assoc();
                        $getOrd->close();
                        if ($ordRow) {
                            $notification = new Notification($conn);
                            $notification->create(
                                $ordRow['customer_id'],
                                'lechon_delivering',
                                'Your Order is On Its Way!',
                                'Your order for ' . $ordRow['listing_name'] . ' (#' . $order_number . ') has been handed to logistics and is now on its way to you.',
                                '/customer/my-orders?orders_tab=lechon'
                            );
                        }
                    } else {
                        // Pig-based swine order — update swine_order_status table
                        $stmt = $conn->prepare(
                            "UPDATE swine_order_status sos
                             JOIN order_total_cost otc ON otc.swine_order_id = sos.id
                             SET sos.order_status = 'delivering', sos.updated_at = NOW()
                             WHERE otc.order_number = ? AND otc.livestock_owner_id = ?
                             AND sos.order_status IN ('confirmed','preparing','cooking')"
                        );
                        $stmt->bind_param('si', $order_number, $owner['id']);
                        $stmt->execute();
                        if ($stmt->affected_rows === 0) throw new Exception('Order not found or already sent to logistics.');
                        $stmt->close();

                        // Notify customer
                        $getOrd = $conn->prepare(
                            "SELECT sos.customer_id FROM swine_order_status sos
                             JOIN order_total_cost otc ON otc.swine_order_id = sos.id
                             WHERE otc.order_number = ? LIMIT 1"
                        );
                        $getOrd->bind_param('s', $order_number);
                        $getOrd->execute();
                        $ordRow = $getOrd->get_result()->fetch_assoc();
                        $getOrd->close();
                        if ($ordRow) {
                            $notification = new Notification($conn);
                            $notification->create(
                                $ordRow['customer_id'],
                                'lechon_delivering',
                                'Your Order is On Its Way!',
                                'Your lechon order (#' . $order_number . ') has been handed to logistics and is now on its way to you.',
                                '/customer/my-orders'
                            );
                        }
                    }

                    echo json_encode(['success' => true, 'message' => 'Order sent to logistics successfully.']);
                    exit;
                } catch (Exception $e) {
                    http_response_code(400);
                    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
                    exit;
                }
            }
            break;

        // ========== LIVESTOCK OWNER - PIG MARKET ==========
        // Ensure lechon_listings table exists
        if ($conn) {
            $check_lechon_table = $conn->query("SHOW TABLES LIKE 'lechon_listings'");
            if (!$check_lechon_table || $check_lechon_table->num_rows == 0) {
                $create_lechon_table = "CREATE TABLE IF NOT EXISTS `lechon_listings` (
                    `id` INT AUTO_INCREMENT PRIMARY KEY,
                    `livestock_owner_id` INT NOT NULL,
                    `listing_type` ENUM('WHOLE_LECHON', 'PROMO_PACKAGE') NOT NULL DEFAULT 'WHOLE_LECHON',
                    `category` ENUM('WHOLE_LECHON', 'WHOLE_PACKAGES', 'BELLY_BUNDLES', 'WEEKDAY_COMBOS', 'EVENT_CATERING', 'OTHER') NOT NULL DEFAULT 'WHOLE_LECHON',
                    `name` VARCHAR(255) NOT NULL,
                    `weight_kg` DECIMAL(8, 2) NULL COMMENT 'Weight in kg for whole lechon',
                    `portion_size` VARCHAR(100) NULL COMMENT 'Portion size for portions/packages (e.g., 1/4 Lechon)',
                    `serving_capacity` VARCHAR(100) NULL COMMENT 'Good for how many people (e.g., 50-60 pax)',
                    `price` DECIMAL(10, 2) NOT NULL,
                    `available_date` DATE NOT NULL,
                    `available_quantity` INT NOT NULL DEFAULT 1,
                    `description` LONGTEXT NULL,
                    `photo_url` VARCHAR(500) NULL,
                    `status` ENUM('active', 'inactive', 'removed') NOT NULL DEFAULT 'active',
                    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                    `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                    FOREIGN KEY (`livestock_owner_id`) REFERENCES `livestock_owners`(`id`) ON DELETE CASCADE,
                    INDEX `idx_owner` (`livestock_owner_id`),
                    INDEX `idx_status` (`status`),
                    INDEX `idx_category` (`category`),
                    INDEX `idx_listing_type` (`listing_type`),
                    INDEX `idx_available_date` (`available_date`)
                ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci";
                
                if (!$conn->query($create_lechon_table)) {
                    error_log("Error creating lechon_listings table: " . $conn->error);
                }
            }
            // Migrate category ENUM if table already exists (update live column values)
            $conn->query("ALTER TABLE lechon_listings MODIFY COLUMN `category` ENUM('WHOLE_LECHON','WHOLE_PACKAGES','BELLY_BUNDLES','WEEKDAY_COMBOS','EVENT_CATERING','OTHER') NOT NULL DEFAULT 'WHOLE_LECHON'");
        }

        case 'livestock-owner/my-pig-market':
            if ($_SERVER['REQUEST_METHOD'] === 'GET') {
                require VIEWS_PATH . '/livestock-owner/my-pig-market.php';
            }
            break;

        case 'livestock-owner/post-pig-to-market':
            error_log("Route matched: livestock-owner/post-pig-to-market, METHOD=" . $_SERVER['REQUEST_METHOD']);
            if ($_SERVER['REQUEST_METHOD'] === 'POST') {
                try {
                    $user = $sessionMiddleware->getUser();
                    $pig_detail_id = (int)($_POST['pig_detail_id'] ?? 0);
                    $pig_tag_id    = trim($_POST['pig_tag_id'] ?? '');
                    $pin_number    = trim($_POST['pin_number'] ?? '');
                    $weight_kg     = (float)($_POST['weight_kg'] ?? 0);
                    $price_per_kg  = (float)($_POST['price_per_kg'] ?? 0);
                    $description   = trim($_POST['description'] ?? '');

                    error_log("POST pig to market: pig_id={$pig_detail_id}, tag={$pig_tag_id}, weight={$weight_kg}, price={$price_per_kg}");

                    if (!$pig_detail_id) {
                        throw new Exception('Invalid pig ID: ' . var_export($pig_detail_id, true));
                    }
                    if ($weight_kg <= 0) {
                        throw new Exception('Invalid weight: ' . var_export($weight_kg, true));
                    }
                    if ($price_per_kg <= 0) {
                        throw new Exception('Invalid price: ' . var_export($price_per_kg, true));
                    }

                    // Check if user is a livestock owner
                    $stmt = $conn->prepare("SELECT id FROM livestock_owners WHERE user_id = ?");
                    $stmt->bind_param('i', $user['id']);
                    $stmt->execute();
                    $owner = $stmt->get_result()->fetch_assoc();
                    $stmt->close();
                    
                    // If not a livestock owner, check if user is a caretaker
                    if (!$owner) {
                        $stmt = $conn->prepare("SELECT livestock_owner_id as id FROM pig_caretakers WHERE user_id = ?");
                        $stmt->bind_param('i', $user['id']);
                        $stmt->execute();
                        $owner = $stmt->get_result()->fetch_assoc();
                        $stmt->close();
                    }
                    
                    error_log("Owner lookup result: " . ($owner ? 'Found owner id=' . $owner['id'] : 'No owner found'));
                    
                    if (!$owner) throw new Exception('Livestock owner profile not found');

                    // Prevent duplicate active listing for same pig
                    $chk = $conn->prepare("SELECT id FROM hogs_market WHERE pig_detail_id = ? AND status = 'active'");
                    $chk->bind_param('i', $pig_detail_id);
                    $chk->execute();
                    if ($chk->get_result()->fetch_assoc()) {
                        throw new Exception('This pig already has an active market listing');
                    }
                    $chk->close();

                    $ins = $conn->prepare(
                        "INSERT INTO hogs_market (livestock_owner_id, pig_detail_id, pig_tag_id, pin_number, weight_kg, price_per_kg, description)
                        VALUES (?, ?, ?, ?, ?, ?, ?)"
                    );
                    $ins->bind_param('iissdds', $owner['id'], $pig_detail_id, $pig_tag_id, $pin_number, $weight_kg, $price_per_kg, $description);
                    error_log("Attempting INSERT with owner_id={$owner['id']}, pig_id={$pig_detail_id}, price={$price_per_kg}");
                    if (!$ins->execute()) {
                        error_log("INSERT failed: " . $ins->error);
                        throw new Exception('Error creating listing: ' . $ins->error);
                    }
                    error_log("INSERT successful, last insert id=" . $conn->insert_id);
                    $ins->close();

                    $_SESSION['success'] = "🛒 {$pig_tag_id} posted to your market!";
                    header('Location: ' . $base_url . '/livestock-owner/caretaker-pig-inventory');
                    exit;
                } catch (Exception $e) {
                    $_SESSION['error'] = $e->getMessage();
                    header('Location: ' . $base_url . '/livestock-owner/caretaker-pig-inventory');
                    exit;
                }
            }
            break;

        case 'livestock-owner/update-pig-listing':
            if ($_SERVER['REQUEST_METHOD'] === 'POST') {
                try {
                    $user       = $sessionMiddleware->getUser();
                    $listing_id = (int)($_POST['listing_id'] ?? 0);
                    $action     = $_POST['action'] ?? '';

                    if (!$listing_id || !in_array($action, ['sold', 'removed', 'active'])) {
                        throw new Exception('Invalid request');
                    }

                    $stmt = $conn->prepare("SELECT id FROM livestock_owners WHERE user_id = ?");
                    $stmt->bind_param('i', $user['id']);
                    $stmt->execute();
                    $owner = $stmt->get_result()->fetch_assoc();
                    $stmt->close();
                    if (!$owner) throw new Exception('Owner not found');

                    // Get full listing info before updating
                    $lstmt = $conn->prepare(
                        "SELECT hm.pig_tag_id, hm.pig_detail_id, hm.total_price,
                                hm.reserved_by_user_id, hm.reserved_by_name
                        FROM hogs_market hm
                        WHERE hm.id = ? AND hm.livestock_owner_id = ?"
                    );
                    $lstmt->bind_param('ii', $listing_id, $owner['id']);
                    $lstmt->execute();
                    $listing = $lstmt->get_result()->fetch_assoc();
                    $lstmt->close();
                    if (!$listing) throw new Exception('Listing not found');

                    // Update listing status
                    $upd = $conn->prepare("UPDATE hogs_market SET status=? WHERE id=? AND livestock_owner_id=?");
                    $upd->bind_param('sii', $action, $listing_id, $owner['id']);
                    $upd->execute();
                    $upd->close();

                    if ($action === 'sold') {
                        // Mark pig_details as sold
                        if ($listing['pig_detail_id']) {
                            $pd = $conn->prepare("UPDATE pig_details SET status='sold' WHERE id=?");
                            $pd->bind_param('i', $listing['pig_detail_id']);
                            $pd->execute();
                            $pd->close();
                        }

                        // Get seller feedback before updating
                        $seller_feedback = trim($_POST['seller_feedback'] ?? '');

                        // Update swine_order_status to confirmed and save seller_feedback
                        $updateOrder = $conn->prepare(
                            "UPDATE swine_order_status 
                            SET order_status = 'confirmed', seller_feedback = ?, updated_at = NOW()
                            WHERE hogs_market_id = ? AND customer_id = ? AND order_status = 'pending'"
                        );
                        $updateOrder->bind_param('sii', $seller_feedback, $listing_id, $listing['reserved_by_user_id']);
                        $updateOrder->execute();
                        $updateOrder->close();

                        // Get the swine_order_id and order_number
                        $getOrder = $conn->prepare(
                            "SELECT id, order_number FROM swine_order_status 
                            WHERE hogs_market_id = ? AND customer_id = ? 
                            ORDER BY created_at DESC LIMIT 1"
                        );
                        $getOrder->bind_param('ii', $listing_id, $listing['reserved_by_user_id']);
                        $getOrder->execute();
                        $orderResult = $getOrder->get_result()->fetch_assoc();
                        $getOrder->close();

                        // Create entry in reserved_orders table
                        if ($orderResult) {
                            $reservedOrder = $conn->prepare(
                                "INSERT INTO reserved_orders 
                                (swine_order_id, order_number, customer_id, livestock_owner_id, pig_tag_id, total_price, status, confirmed_by_owner_at, notes)
                                VALUES (?, ?, ?, ?, ?, ?, 'confirmed', NOW(), ?)"
                            );
                            $reservedOrder->bind_param('isiisds', 
                                $orderResult['id'],
                                $orderResult['order_number'],
                                $listing['reserved_by_user_id'],
                                $owner['id'],
                                $listing['pig_tag_id'],
                                $listing['total_price'],
                                $seller_feedback
                            );
                            $reservedOrder->execute();
                            $reservedOrder->close();
                        }

                        // Notify the customer who reserved it
                        if ($listing['reserved_by_user_id']) {
                            $notif_message = 'Great news! The livestock owner has confirmed your reservation for ' .
                                $listing['pig_tag_id'] . ' (₱' . number_format($listing['total_price'], 2) .
                                '). Please coordinate with the seller for pickup/delivery.';
                            if ($seller_feedback !== '') {
                                $notif_message .= ' Message from seller: "' . $seller_feedback . '"';
                            }
                            $notification = new Notification($conn);
                            $notification->create(
                                $listing['reserved_by_user_id'],
                                'pig_sold',
                                ' Your Pig Order is Confirmed!',
                                $notif_message,
                                '/customer/my-orders'
                            );
                        }
                        $_SESSION['success'] = ' Pig reserved for customer! They have been notified.';
                    } elseif ($action === 'active') {
                        // Cancel reservation — reset reserved fields
                        $rst = $conn->prepare(
                            "UPDATE hogs_market SET reserved_by_user_id=NULL, reserved_by_name=NULL, inquiry_message=NULL, reserved_at=NULL WHERE id=? AND livestock_owner_id=?"
                        );
                        $rst->bind_param('ii', $listing_id, $owner['id']);
                        $rst->execute();
                        $rst->close();

                        // Update swine_order_status to cancelled
                        $cancelOrder = $conn->prepare(
                            "UPDATE swine_order_status 
                            SET order_status = 'cancelled', cancelled_at = NOW(), cancellation_reason = 'Cancelled by seller'
                            WHERE hogs_market_id = ? AND order_status = 'pending'"
                        );
                        $cancelOrder->bind_param('i', $listing_id);
                        $cancelOrder->execute();
                        $cancelOrder->close();

                        $_SESSION['success'] = 'Reservation cancelled. Pig is active again.';
                    } else {
                        $_SESSION['success'] = 'Listing removed.';
                    }

                    header('Location: ' . $base_url . '/livestock-owner/my-pig-market');
                    exit;
                } catch (Exception $e) {
                    $_SESSION['error'] = $e->getMessage();
                    header('Location: ' . $base_url . '/livestock-owner/my-pig-market');
                    exit;
                }
            }
            break;

        case 'livestock-owner/compute-total-cost':
            if ($_SERVER['REQUEST_METHOD'] === 'POST') {
                try {
                    $user = $sessionMiddleware->getUser();
                    $listing_id = (int)($_POST['listing_id'] ?? 0);
                    $customer_id = (int)($_POST['customer_id'] ?? 0);
                    $delivery_method = $_POST['delivery_method'] ?? 'pickup';
                    $delivery_fee = (float)($_POST['delivery_fee'] ?? 0);
                    $labor_cost = (float)($_POST['labor_cost'] ?? 0);
                    
                    // Additional items (now free)
                    $additional_item = $_POST['additional_item'] ?? '';
                    $include_laman_loob = ($additional_item === 'laman_loob') ? 1 : 0;
                    $include_boopes = ($additional_item === 'boopes') ? 1 : 0;
                    $include_dinuguan = ($additional_item === 'dinuguan') ? 1 : 0;
                    
                    // Payment options
                    $payment_type_raw = $_POST['payment_type'] ?? 'full_payment';
                    // Convert to match database ENUM values
                    $payment_type = ($payment_type_raw === 'down_payment') ? 'down' : 'full';
                    $payment_deadline = $_POST['payment_deadline'] ?? null;
                    $payment_instructions = $_POST['payment_instructions'] ?? '';

                    if (!$listing_id || !$customer_id) {
                        throw new Exception('Invalid request data');
                    }

                    // Verify livestock owner
                    $stmt = $conn->prepare("SELECT id FROM livestock_owners WHERE user_id = ?");
                    $stmt->bind_param('i', $user['id']);
                    $stmt->execute();
                    $owner = $stmt->get_result()->fetch_assoc();
                    $stmt->close();
                    if (!$owner) throw new Exception('Owner not found');

                    // Get order details from swine_order_status and placed_orders
                    $orderQuery = $conn->prepare(
                        "SELECT sos.id as swine_order_id, sos.order_number, sos.pig_tag_id, sos.pin_number, 
                                sos.total_price as pig_base_amount, po.delivery_address,
                                u.name as customer_name
                        FROM swine_order_status sos
                        LEFT JOIN placed_orders po ON po.swine_order_id = sos.id
                        LEFT JOIN users u ON u.id = sos.customer_id
                        WHERE sos.hogs_market_id = ? AND sos.customer_id = ? AND sos.livestock_owner_id = ?"
                    );
                    $orderQuery->bind_param('iii', $listing_id, $customer_id, $owner['id']);
                    $orderQuery->execute();
                    $orderData = $orderQuery->get_result()->fetch_assoc();
                    $orderQuery->close();

                    if (!$orderData) {
                        throw new Exception('Order not found');
                    }

                    // Calculate total cost
                    $pig_base_amount = $orderData['pig_base_amount'];
                    $subtotal = $pig_base_amount;
                    
                    if ($delivery_method === 'delivery') {
                        $subtotal += $delivery_fee;
                    }
                    
                    // Add labor cost
                    if ($labor_cost > 0) {
                        $subtotal += $labor_cost;
                    }
                    
                    // Additional items are now free, so no price added
                    
                    $total_cost = $subtotal;
                    
                    // Calculate payment amounts
                    $down_payment_amount = 0;
                    $remaining_balance = 0;
                    
                    if ($payment_type === 'down_payment') {
                        $down_payment_amount = $total_cost * 0.5; // 50% down payment
                        $remaining_balance = $total_cost - $down_payment_amount;
                    }

                    // Insert or update order_total_cost
                    $insertCost = $conn->prepare(
                        "INSERT INTO order_total_cost 
                        (swine_order_id, order_number, customer_id, livestock_owner_id, customer_name, 
                        delivery_address, delivery_method, delivery_fee, labor_cost, pig_tag_id, pin_number, 
                        pig_base_amount, include_laman_loob, laman_loob_price, include_boopes, 
                        boopes_price, include_dinuguan, dinuguan_price, subtotal, total_cost,
                        payment_type, down_payment_amount, remaining_balance, payment_deadline, payment_instructions)
                        VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
                        ON DUPLICATE KEY UPDATE
                        delivery_method = VALUES(delivery_method),
                        delivery_fee = VALUES(delivery_fee),
                        labor_cost = VALUES(labor_cost),
                        include_laman_loob = VALUES(include_laman_loob),
                        laman_loob_price = VALUES(laman_loob_price),
                        include_boopes = VALUES(include_boopes),
                        boopes_price = VALUES(boopes_price),
                        include_dinuguan = VALUES(include_dinuguan),
                        dinuguan_price = VALUES(dinuguan_price),
                        subtotal = VALUES(subtotal),
                        total_cost = VALUES(total_cost),
                        payment_type = VALUES(payment_type),
                        down_payment_amount = VALUES(down_payment_amount),
                        remaining_balance = VALUES(remaining_balance),
                        payment_deadline = VALUES(payment_deadline),
                        payment_instructions = VALUES(payment_instructions),
                        computed_at = NOW(),
                        updated_at = NOW()"
                    );
                    
                    if (!$insertCost) {
                        throw new Exception('Failed to prepare statement: ' . $conn->error);
                    }
                    
                    // Create variables for bind_param (all parameters must be variables, not literals)
                    $laman_loob_price = 0.00; // now free
                    $boopes_price = 0.00; // now free  
                    $dinuguan_price = 0.00; // now free
                    
                    // WORKAROUND: Create type string dynamically to bypass cache
                    $type_chars = [];
                    $type_chars[] = 'i'; // swine_order_id
                    $type_chars[] = 's'; // order_number
                    $type_chars[] = 'i'; // customer_id
                    $type_chars[] = 'i'; // owner_id
                    $type_chars[] = 's'; // customer_name
                    $type_chars[] = 's'; // delivery_address
                    $type_chars[] = 's'; // delivery_method
                    $type_chars[] = 'd'; // delivery_fee
                    $type_chars[] = 'd'; // labor_cost
                    $type_chars[] = 's'; // pig_tag_id
                    $type_chars[] = 's'; // pin_number
                    $type_chars[] = 'd'; // pig_base_amount
                    $type_chars[] = 'i'; // include_laman_loob
                    $type_chars[] = 'd'; // laman_loob_price
                    $type_chars[] = 'i'; // include_boopes
                    $type_chars[] = 'd'; // boopes_price
                    $type_chars[] = 'i'; // include_dinuguan
                    $type_chars[] = 'd'; // dinuguan_price
                    $type_chars[] = 'd'; // subtotal
                    $type_chars[] = 'd'; // total_cost
                    $type_chars[] = 's'; // payment_type
                    $type_chars[] = 'd'; // down_payment_amount
                    $type_chars[] = 'd'; // remaining_balance
                    $type_chars[] = 's'; // payment_deadline
                    $type_chars[] = 's'; // payment_instructions
                    
                    $type_string = implode('', $type_chars); // This creates the string dynamically
                    
                    // Debug: Count actual parameters
                    $params = [
                        $orderData['swine_order_id'],      // 1: i (int)
                        $orderData['order_number'],        // 2: s (string)
                        $customer_id,                      // 3: i (int)
                        $owner['id'],                      // 4: i (int)
                        $orderData['customer_name'],       // 5: s (string)
                        $orderData['delivery_address'],    // 6: s (string)
                        $delivery_method,                  // 7: s (string)
                        $delivery_fee,                     // 8: d (decimal)
                        $labor_cost,                       // 9: d (decimal)
                        $orderData['pig_tag_id'],          // 10: s (string)
                        $orderData['pin_number'],          // 11: s (string)
                        $pig_base_amount,                  // 12: d (decimal)
                        $include_laman_loob,               // 13: i (int/boolean)
                        $laman_loob_price,                 // 14: d (decimal)
                        $include_boopes,                   // 15: i (int/boolean)
                        $boopes_price,                     // 16: d (decimal)
                        $include_dinuguan,                 // 17: i (int/boolean)
                        $dinuguan_price,                   // 18: d (decimal)
                        $subtotal,                         // 19: d (decimal)
                        $total_cost,                       // 20: d (decimal)
                        $payment_type,                     // 21: s (string)
                        $down_payment_amount,              // 22: d (decimal)
                        $remaining_balance,                // 23: d (decimal)
                        $payment_deadline,                 // 24: s (string/date)
                        $payment_instructions              // 25: s (string)
                    ];
                    
                    error_log("DYNAMIC Type string: '$type_string' (length: " . strlen($type_string) . ")");
                    error_log("DYNAMIC Number of parameters: " . count($params));
                    
                    $insertCost->bind_param($type_string, ...$params);
                    
                    $insertCost->execute();
                    $insertCost->close();

                    // Store payment information temporarily in session for receipt display
                    $_SESSION['payment_info'] = [
                        'order_id' => $orderData['swine_order_id'],
                        'payment_type' => $payment_type,
                        'down_payment_amount' => $down_payment_amount,
                        'remaining_balance' => $remaining_balance,
                        'payment_instructions' => $payment_instructions,
                        'payment_deadline' => $payment_deadline,
                        'total_cost' => $total_cost
                    ];

                    // Update swine_order_status to show cost computed
                    $updateStatus = $conn->prepare(
                        "UPDATE swine_order_status 
                        SET order_status = 'cost_computed', updated_at = NOW()
                        WHERE id = ?"
                    );
                    $updateStatus->bind_param('i', $orderData['swine_order_id']);
                    $updateStatus->execute();
                    $updateStatus->close();

                    // Notify customer about computed cost with payment instructions
                    $notification = new Notification($conn);
                    $paymentMessage = ($payment_type === 'down_payment') 
                        ? 'Down Payment: ₱' . number_format($down_payment_amount, 2) . ' (Balance: ₱' . number_format($remaining_balance, 2) . ')'
                        : 'Full Payment: ₱' . number_format($total_cost, 2);
                    
                    $deadlineText = $payment_deadline ? date('M d, Y', strtotime($payment_deadline)) : 'Not set';
                    $instructionsText = $payment_instructions ? ' Instructions: ' . substr($payment_instructions, 0, 100) . '...' : '';
                    
                    $notification->create(
                        $customer_id,
                        'cost_computed',
                        ' Receipt & Payment Instructions Ready',
                        'Your order receipt for ' . $orderData['pig_tag_id'] . ' is ready! ' . $paymentMessage . 
                        '. Payment deadline: ' . $deadlineText . $instructionsText . 
                        ' Please check your orders for complete payment instructions.',
                        '/customer/my-orders'
                    );

                    $_SESSION['success'] = ' Receipt generated and payment instructions sent to customer!';
                    header('Location: ' . $base_url . '/livestock-owner/my-pig-market');
                    exit;
                } catch (Exception $e) {
                    $_SESSION['error'] = $e->getMessage();
                    header('Location: ' . $base_url . '/livestock-owner/my-pig-market');
                    exit;
                }
            }
            break;

        // ========== LIVESTOCK OWNER - LECHON MARKET ==========
        case 'livestock-owner/add-lechon':
            if ($_SERVER['REQUEST_METHOD'] === 'POST') {
                try {
                    $user = $sessionMiddleware->getUser();
                    
                    // Get owner ID
                    $stmt = $conn->prepare("SELECT id FROM livestock_owners WHERE user_id = ?");
                    $stmt->bind_param('i', $user['id']);
                    $stmt->execute();
                    $owner = $stmt->get_result()->fetch_assoc();
                    $stmt->close();
                    
                    if (!$owner) {
                        throw new Exception('Livestock owner profile not found');
                    }
                    
                    // Get form data
                    $listing_type = $_POST['listing_type'] ?? '';
                    $category = $_POST['category'] ?? '';
                    $name = trim($_POST['name'] ?? '');
                    $weight_kg = isset($_POST['weight_kg']) ? (float)$_POST['weight_kg'] : NULL;
                    $portion_size = trim($_POST['portion_size'] ?? '') ?: NULL;
                    $serving_capacity = trim($_POST['serving_capacity'] ?? '') ?: NULL;
                    $price = (float)($_POST['price'] ?? 0);
                    $available_date = $_POST['available_date'] ?? '';
                    $available_quantity = (int)($_POST['available_quantity'] ?? 1);
                    $description = trim($_POST['description'] ?? '') ?: NULL;
                    $status = $_POST['status'] ?? 'active';
                    
                    // Handle image upload
                    $photo_url = NULL;
                    if (isset($_FILES['photo']) && $_FILES['photo']['error'] === UPLOAD_ERR_OK) {
                        $upload_dir = __DIR__ . '/uploads/lechon/';
                        if (!is_dir($upload_dir)) {
                            mkdir($upload_dir, 0755, true);
                        }
                        
                        $file_ext = strtolower(pathinfo($_FILES['photo']['name'], PATHINFO_EXTENSION));
                        $allowed = ['jpg', 'jpeg', 'png', 'gif', 'webp'];
                        
                        if (!in_array($file_ext, $allowed)) {
                            throw new Exception('Invalid image format');
                        }
                        
                        $file_name = 'lechon_' . time() . '_' . bin2hex(random_bytes(4)) . '.' . $file_ext;
                        $file_path = $upload_dir . $file_name;
                        
                        if (move_uploaded_file($_FILES['photo']['tmp_name'], $file_path)) {
                            $photo_url = '/uploads/lechon/' . $file_name;
                        }
                    }
                    
                    // Validate required fields
                    if (!$listing_type || !$category || !$name || $price <= 0 || !$available_date) {
                        throw new Exception('Please fill in all required fields');
                    }
                    
                    // Insert into lechon_listings
                    $insert = $conn->prepare(
                        "INSERT INTO lechon_listings 
                        (livestock_owner_id, listing_type, category, name, weight_kg, portion_size, 
                         serving_capacity, price, available_date, available_quantity, description, photo_url, status)
                        VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)"
                    );
                    
                    if (!$insert) {
                        throw new Exception('Database error: ' . $conn->error);
                    }
                    
                    // 13 vars: owner(i) type(s) category(s) name(s) weight(d) portion(s) serving(s) price(d) date(s) qty(i) desc(s) photo(s) status(s)
                    $insert->bind_param(
                        'isssdssdsisss',
                        $owner['id'],
                        $listing_type,
                        $category,
                        $name,
                        $weight_kg,
                        $portion_size,
                        $serving_capacity,
                        $price,
                        $available_date,
                        $available_quantity,
                        $description,
                        $photo_url,
                        $status
                    );
                    
                    if (!$insert->execute()) {
                        throw new Exception('Failed to create listing: ' . $insert->error);
                    }
                    
                    $insert->close();
                    
                    $_SESSION['success'] = 'Lechon listing created successfully!';
                    header('Location: ' . $base_url . '/livestock-owner/my-pig-market?tab=lechon');
                    exit;
                    
                } catch (Exception $e) {
                    $_SESSION['error'] = $e->getMessage();
                    header('Location: ' . $base_url . '/livestock-owner/my-pig-market?tab=lechon');
                    exit;
                }
            }
            break;

        case 'livestock-owner/edit-lechon':
            if ($_SERVER['REQUEST_METHOD'] === 'POST') {
                try {
                    $user = $sessionMiddleware->getUser();
                    $listing_id = (int)($_POST['listing_id'] ?? 0);
                    
                    // Get owner ID
                    $stmt = $conn->prepare("SELECT id FROM livestock_owners WHERE user_id = ?");
                    $stmt->bind_param('i', $user['id']);
                    $stmt->execute();
                    $owner = $stmt->get_result()->fetch_assoc();
                    $stmt->close();
                    
                    if (!$owner || !$listing_id) {
                        throw new Exception('Invalid request');
                    }
                    
                    // Get form data
                    $name = trim($_POST['name'] ?? '');
                    $price = (float)($_POST['price'] ?? 0);
                    $available_date = $_POST['available_date'] ?? '';
                    $available_quantity = (int)($_POST['available_quantity'] ?? 1);
                    $description = trim($_POST['description'] ?? '') ?: NULL;
                    $status = $_POST['status'] ?? 'active';
                    $weight_kg = isset($_POST['weight_kg']) ? (float)$_POST['weight_kg'] : NULL;
                    $portion_size = trim($_POST['portion_size'] ?? '') ?: NULL;
                    $serving_capacity = trim($_POST['serving_capacity'] ?? '') ?: NULL;
                    
                    // Handle image upload
                    $photo_url = NULL;
                    if (isset($_FILES['photo']) && $_FILES['photo']['error'] === UPLOAD_ERR_OK) {
                        $upload_dir = __DIR__ . '/uploads/lechon/';
                        if (!is_dir($upload_dir)) {
                            mkdir($upload_dir, 0755, true);
                        }
                        
                        $file_ext = strtolower(pathinfo($_FILES['photo']['name'], PATHINFO_EXTENSION));
                        $allowed = ['jpg', 'jpeg', 'png', 'gif', 'webp'];
                        
                        if (!in_array($file_ext, $allowed)) {
                            throw new Exception('Invalid image format');
                        }
                        
                        $file_name = 'lechon_' . time() . '_' . bin2hex(random_bytes(4)) . '.' . $file_ext;
                        $file_path = $upload_dir . $file_name;
                        
                        if (move_uploaded_file($_FILES['photo']['tmp_name'], $file_path)) {
                            $photo_url = '/uploads/lechon/' . $file_name;
                        }
                    }
                    
                    // Update lechon_listings
                    $update = $conn->prepare(
                        "UPDATE lechon_listings SET name=?, price=?, available_date=?, 
                         available_quantity=?, description=?, weight_kg=?, portion_size=?,
                         serving_capacity=?, status=?" . ($photo_url ? ", photo_url=?" : "") . 
                         " WHERE id=? AND livestock_owner_id=?"
                    );
                    
                    if (!$update) {
                        throw new Exception('Database error: ' . $conn->error);
                    }
                    
                    if ($photo_url) {
                        // 12 vars: name(s) price(d) date(s) qty(i) desc(s) weight(d) portion(s) serving(s) status(s) photo(s) id(i) owner(i)
                        $update->bind_param(
                            'sdsidsssssii',
                            $name,
                            $price,
                            $available_date,
                            $available_quantity,
                            $description,
                            $weight_kg,
                            $portion_size,
                            $serving_capacity,
                            $status,
                            $photo_url,
                            $listing_id,
                            $owner['id']
                        );
                    } else {
                        // 11 vars: name(s) price(d) date(s) qty(i) desc(s) weight(d) portion(s) serving(s) status(s) id(i) owner(i)
                        $update->bind_param(
                            'sdsidssssii',
                            $name,
                            $price,
                            $available_date,
                            $available_quantity,
                            $description,
                            $weight_kg,
                            $portion_size,
                            $serving_capacity,
                            $status,
                            $listing_id,
                            $owner['id']
                        );
                    }
                    
                    if (!$update->execute()) {
                        throw new Exception('Failed to update listing: ' . $update->error);
                    }
                    
                    $update->close();
                    
                    $_SESSION['success'] = 'Lechon listing updated successfully!';
                    header('Location: ' . $base_url . '/livestock-owner/my-pig-market?tab=lechon');
                    exit;
                    
                } catch (Exception $e) {
                    $_SESSION['error'] = $e->getMessage();
                    header('Location: ' . $base_url . '/livestock-owner/my-pig-market?tab=lechon');
                    exit;
                }
            }
            break;

        case 'livestock-owner/delete-lechon':
            if ($_SERVER['REQUEST_METHOD'] === 'POST') {
                try {
                    $user = $sessionMiddleware->getUser();
                    $listing_id = (int)($_POST['listing_id'] ?? 0);
                    
                    // Get owner ID
                    $stmt = $conn->prepare("SELECT id FROM livestock_owners WHERE user_id = ?");
                    $stmt->bind_param('i', $user['id']);
                    $stmt->execute();
                    $owner = $stmt->get_result()->fetch_assoc();
                    $stmt->close();
                    
                    if (!$owner || !$listing_id) {
                        throw new Exception('Invalid request');
                    }
                    
                    // Delete lechon listing
                    $delete = $conn->prepare("DELETE FROM lechon_listings WHERE id=? AND livestock_owner_id=?");
                    if (!$delete) {
                        throw new Exception('Database error: ' . $conn->error);
                    }
                    
                    $delete->bind_param('ii', $listing_id, $owner['id']);
                    if (!$delete->execute()) {
                        throw new Exception('Failed to delete listing: ' . $delete->error);
                    }
                    
                    $delete->close();
                    
                    $_SESSION['success'] = 'Lechon listing deleted successfully!';
                    header('Location: ' . $base_url . '/livestock-owner/my-pig-market?tab=lechon');
                    exit;
                    
                } catch (Exception $e) {
                    $_SESSION['error'] = $e->getMessage();
                    header('Location: ' . $base_url . '/livestock-owner/my-pig-market?tab=lechon');
                    exit;
                }
            }
            break;

        case 'livestock-owner/update-lechon-status':
            if ($_SERVER['REQUEST_METHOD'] === 'POST') {
                try {
                    $user = $sessionMiddleware->getUser();
                    $listing_id = (int)($_POST['listing_id'] ?? 0);
                    $status = $_POST['status'] ?? '';
                    
                    // Get owner ID
                    $stmt = $conn->prepare("SELECT id FROM livestock_owners WHERE user_id = ?");
                    $stmt->bind_param('i', $user['id']);
                    $stmt->execute();
                    $owner = $stmt->get_result()->fetch_assoc();
                    $stmt->close();
                    
                    if (!$owner || !$listing_id || !in_array($status, ['active', 'inactive', 'removed'])) {
                        throw new Exception('Invalid request');
                    }
                    
                    // Update status
                    $update = $conn->prepare("UPDATE lechon_listings SET status=? WHERE id=? AND livestock_owner_id=?");
                    if (!$update) {
                        throw new Exception('Database error: ' . $conn->error);
                    }
                    
                    $update->bind_param('sii', $status, $listing_id, $owner['id']);
                    if (!$update->execute()) {
                        throw new Exception('Failed to update status: ' . $update->error);
                    }
                    
                    $update->close();
                    
                    $_SESSION['success'] = 'Lechon status updated successfully!';
                    header('Location: ' . $base_url . '/livestock-owner/my-pig-market?tab=lechon');
                    exit;
                    
                } catch (Exception $e) {
                    $_SESSION['error'] = $e->getMessage();
                    header('Location: ' . $base_url . '/livestock-owner/my-pig-market?tab=lechon');
                    exit;
                }
            }
            break;

        // ========== ROLE APPLICATION ROUTES ==========
        case 'apply-customer':
            if ($_SERVER['REQUEST_METHOD'] === 'POST') {
                require_once APP_PATH . '/models/RoleApplication.php';
                require_once APP_PATH . '/controllers/RoleApplicationController.php';
                $roleAppController = new RoleApplicationController($conn);
                $roleAppController->applyAsCustomer();
            }
            break;

        case 'apply-piggery':
            if ($_SERVER['REQUEST_METHOD'] === 'GET') {
                require_once APP_PATH . '/models/RoleApplication.php';
                require_once APP_PATH . '/controllers/RoleApplicationController.php';
                $roleAppController = new RoleApplicationController($conn);
                $roleAppController->showPiggeryApplicationForm();
            }
            break;

        case 'role-application/piggery/submit':
            if ($_SERVER['REQUEST_METHOD'] === 'POST') {
                require_once APP_PATH . '/models/RoleApplication.php';
                require_once APP_PATH . '/controllers/RoleApplicationController.php';
                $roleAppController = new RoleApplicationController($conn);
                $roleAppController->submitPiggeryApplication();
            }
            break;

        case 'admin/pending-applications':
            if ($_SERVER['REQUEST_METHOD'] === 'GET') {
                require_once APP_PATH . '/models/RoleApplication.php';
                require_once APP_PATH . '/controllers/RoleApplicationController.php';
                $roleAppController = new RoleApplicationController($conn);
                $roleAppController->viewPendingApplications();
            }
            break;

        case 'admin/all-users':
            if ($_SERVER['REQUEST_METHOD'] === 'GET') {
                require VIEWS_PATH . '/admin/all-users.php';
            }
            break;

        case 'admin/system-logs':
            if ($_SERVER['REQUEST_METHOD'] === 'GET') {
                require VIEWS_PATH . '/admin/system-logs.php';
            }
            break;

        case 'role-application/approve':
            if ($_SERVER['REQUEST_METHOD'] === 'POST') {
                // Clear ALL output buffers before JSON response
                while (ob_get_level()) {
                    ob_end_clean();
                }
                
                require_once APP_PATH . '/models/RoleApplication.php';
                require_once APP_PATH . '/controllers/RoleApplicationController.php';
                $roleAppController = new RoleApplicationController($conn);
                $roleAppController->approveApplication();
            }
            break;

        case 'role-application/reject':
            if ($_SERVER['REQUEST_METHOD'] === 'POST') {
                // Clear ALL output buffers before JSON response
                while (ob_get_level()) {
                    ob_end_clean();
                }
                
                require_once APP_PATH . '/models/RoleApplication.php';
                require_once APP_PATH . '/controllers/RoleApplicationController.php';
                $roleAppController = new RoleApplicationController($conn);
                $roleAppController->rejectApplication();
            }
            break;

        // ========== SITE ADMINISTRATION ROUTES (Livestock Owner Employee Management) ==========
        case 'site-administration':
        case 'livestock-owner/site-administration':
            if ($_SERVER['REQUEST_METHOD'] === 'GET') {
                require_once APP_PATH . '/models/EmployeeAssignment.php';
                require_once APP_PATH . '/controllers/SiteAdministrationController.php';
                $siteAdminController = new SiteAdministrationController($conn);
                $siteAdminController->index();
            }
            break;

        case 'site-administration/assign':
            if ($_SERVER['REQUEST_METHOD'] === 'POST') {
                require_once APP_PATH . '/models/EmployeeAssignment.php';
                require_once APP_PATH . '/controllers/SiteAdministrationController.php';
                $siteAdminController = new SiteAdministrationController($conn);
                $siteAdminController->assignEmployee();
            }
            break;

        case 'site-administration/remove':
            if ($_SERVER['REQUEST_METHOD'] === 'POST') {
                require_once APP_PATH . '/models/EmployeeAssignment.php';
                require_once APP_PATH . '/controllers/SiteAdministrationController.php';
                $siteAdminController = new SiteAdministrationController($conn);
                $siteAdminController->removeEmployee();
            }
            break;

        case 'site-administration/approve-employee-application':
            if ($_SERVER['REQUEST_METHOD'] === 'POST') {
                require_once APP_PATH . '/models/EmployeeApplication.php';
                require_once APP_PATH . '/models/EmployeeAssignment.php';
                require_once APP_PATH . '/controllers/SiteAdministrationController.php';
                $siteAdminController = new SiteAdministrationController($conn);
                $siteAdminController->approveEmployeeApplication();
            }
            break;

        case 'site-administration/reject-employee-application':
            if ($_SERVER['REQUEST_METHOD'] === 'POST') {
                require_once APP_PATH . '/models/EmployeeApplication.php';
                require_once APP_PATH . '/controllers/SiteAdministrationController.php';
                $siteAdminController = new SiteAdministrationController($conn);
                $siteAdminController->rejectEmployeeApplication();
            }
            break;

        // ========== EMPLOYEE APPLICATION ROUTES ==========
        case 'employee/apply':
            if ($_SERVER['REQUEST_METHOD'] === 'GET') {
                require_once APP_PATH . '/models/EmployeeApplication.php';
                require_once APP_PATH . '/controllers/EmployeeApplicationController.php';
                $employeeAppController = new EmployeeApplicationController($conn);
                $employeeAppController->showApplicationForm();
            }
            break;

        case 'employee/submit-application':
            if ($_SERVER['REQUEST_METHOD'] === 'POST') {
                require_once APP_PATH . '/models/EmployeeApplication.php';
                require_once APP_PATH . '/controllers/EmployeeApplicationController.php';
                $employeeAppController = new EmployeeApplicationController($conn);
                $employeeAppController->submitApplication();
            }
            break;

        case 'livestock-owner/employee-applications':
            if ($_SERVER['REQUEST_METHOD'] === 'GET') {
                require_once APP_PATH . '/models/EmployeeApplication.php';
                require_once APP_PATH . '/models/LivestockOwner.php';
                require_once APP_PATH . '/controllers/EmployeeApplicationController.php';
                $employeeAppController = new EmployeeApplicationController($conn);
                $employeeAppController->viewApplications();
            }
            break;

        case 'livestock-owner/approve-employee-application':
            if ($_SERVER['REQUEST_METHOD'] === 'POST') {
                require_once APP_PATH . '/models/EmployeeApplication.php';
                require_once APP_PATH . '/models/EmployeeAssignment.php';
                require_once APP_PATH . '/models/LivestockOwner.php';
                require_once APP_PATH . '/controllers/EmployeeApplicationController.php';
                $employeeAppController = new EmployeeApplicationController($conn);
                $employeeAppController->approveApplication();
            }
            break;

        case 'livestock-owner/reject-employee-application':
            if ($_SERVER['REQUEST_METHOD'] === 'POST') {
                require_once APP_PATH . '/models/EmployeeApplication.php';
                require_once APP_PATH . '/models/LivestockOwner.php';
                require_once APP_PATH . '/controllers/EmployeeApplicationController.php';
                $employeeAppController = new EmployeeApplicationController($conn);
                $employeeAppController->rejectApplication();
            }
            break;

        default:
            http_response_code(404);
            echo "404 - Page not found";
            break;
    }

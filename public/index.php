<?php

/**
 * LechGO - Front Controller
 * 
 * Main entry point for the application
 * Routes all requests to appropriate controllers
 */

// Start session and initialize application
session_start();

// Define base paths
define('BASE_PATH', dirname(dirname(__FILE__)));
define('APP_PATH', BASE_PATH . '/app');
define('CONFIG_PATH', BASE_PATH . '/config');
define('RESOURCES_PATH', BASE_PATH . '/resources');
define('VIEWS_PATH', RESOURCES_PATH . '/views');

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
require_once APP_PATH . '/controllers/AuthController.php';
require_once APP_PATH . '/controllers/LocationController.php';
require_once APP_PATH . '/services/EmailService.php';
require_once APP_PATH . '/services/PayMongoService.php';
require_once APP_PATH . '/middleware/Session.php';
require_once APP_PATH . '/middleware/RBACMiddleware.php';

// Parse request URI
$request_uri = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);
$base_url = '/LechGo_Final/public';

// Initialize middleware
$sessionMiddleware = new Session();
$rbacMiddleware = new RBACMiddleware($sessionMiddleware, $base_url);
$route = str_replace($base_url, '', $request_uri);
$route = trim($route, '/');

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

    case 'logout':
        $authController = new AuthController($conn);
        $authController->logout();
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
                                    '/LechGo_Final/public/supplier/order-details/' . $order_id
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
                                '/LechGo_Final/public/supplier/order-details/' . $order_id
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
                            '/LechGo_Final/public/dashboard'
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
                    '/LechGo_Final/public/dashboard'
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
                    $upload_dir = BASE_PATH . '/public/uploads/pigs/';
                    if (!is_dir($upload_dir)) mkdir($upload_dir, 0755, true);
                    $ext = strtolower(pathinfo($_FILES['pig_photo']['name'], PATHINFO_EXTENSION));
                    $allowed = ['jpg','jpeg','png','gif','webp'];
                    if (in_array($ext, $allowed)) {
                        $filename = 'pig_' . time() . '_' . rand(100,999) . '.' . $ext;
                        if (move_uploaded_file($_FILES['pig_photo']['tmp_name'], $upload_dir . $filename)) {
                            $pig_photo = '/LechGo_Final/public/uploads/pigs/' . $filename;
                        }
                    }
                }

                // Handle AIC upload
                $aic_file = null;
                if (!empty($_FILES['aic_file']['tmp_name'])) {
                    $upload_dir = BASE_PATH . '/public/uploads/pigs/docs/';
                    if (!is_dir($upload_dir)) mkdir($upload_dir, 0755, true);
                    $ext = strtolower(pathinfo($_FILES['aic_file']['name'], PATHINFO_EXTENSION));
                    $allowed_docs = ['jpg','jpeg','png','pdf'];
                    if (in_array($ext, $allowed_docs)) {
                        $filename = 'aic_' . time() . '_' . rand(100,999) . '.' . $ext;
                        if (move_uploaded_file($_FILES['aic_file']['tmp_name'], $upload_dir . $filename)) {
                            $aic_file = '/LechGo_Final/public/uploads/pigs/docs/' . $filename;
                        }
                    }
                }

                // Handle Barangay Cert upload
                $brgy_cert_file = null;
                if (!empty($_FILES['brgy_cert_file']['tmp_name'])) {
                    $upload_dir = BASE_PATH . '/public/uploads/pigs/docs/';
                    if (!is_dir($upload_dir)) mkdir($upload_dir, 0755, true);
                    $ext = strtolower(pathinfo($_FILES['brgy_cert_file']['name'], PATHINFO_EXTENSION));
                    $allowed_docs = ['jpg','jpeg','png','pdf'];
                    if (in_array($ext, $allowed_docs)) {
                        $filename = 'brgy_' . time() . '_' . rand(100,999) . '.' . $ext;
                        if (move_uploaded_file($_FILES['brgy_cert_file']['tmp_name'], $upload_dir . $filename)) {
                            $brgy_cert_file = '/LechGo_Final/public/uploads/pigs/docs/' . $filename;
                        }
                    }
                }

                // Insert pig
                $query = "INSERT INTO pig_details (cage_id, pig_tag_id, age_months, age_days, weight_kg, health_status, date_added, status, photo_url, aic_file, brgy_cert_file)
                          VALUES (?, ?, ?, ?, ?, ?, ?, 'active', ?, ?, ?)";
                $stmt = $conn->prepare($query);
                if (!$stmt) {
                    throw new Exception('Database error: ' . $conn->error);
                }
                
                $stmt->bind_param('isiidsssss', $cage_id, $pig_tag_id, $age_months, $age_days, $weight_kg, $health_status, $date_added, $pig_photo, $aic_file, $brgy_cert_file);
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
                    $upload_dir = BASE_PATH . '/public/uploads/pigs/';
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
                        $photo_url = '/LechGo_Final/public/uploads/pigs/' . $filename;
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

                // Save report — pig data is fetched via FK (caretaker_id → pig_pins → pig_details)
                $stmt = $conn->prepare(
                    "INSERT INTO swine_inventory (caretaker_id, title, overall_status, report_date, created_at)
                     VALUES (?, ?, ?, ?, NOW())"
                );
                if (!$stmt) throw new Exception('Database error: ' . $conn->error);
                $stmt->bind_param('isss', $caretaker->id, $title, $overall_status, $report_date);
                if (!$stmt->execute()) throw new Exception('Error saving report: ' . $stmt->error);
                $stmt->close();

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
                                '/LechGo_Final/public/livestock-owner/caretaker-pig-inventory'
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

                    $image_url = '/LechGo_Final/public/uploads/products/' . $unique_name;
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

                    $image_url = '/LechGo_Final/public/uploads/products/' . $unique_name;
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
                    '/LechGo_Final/public/livestock-owner/receipt/' . $order_id
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
                            '/LechGo_Final/public/pig-caretaker/feed-inventory'
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
                        'receipt_url' => '/LechGo_Final/public/supplier/receipt/' . $order_id,
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
                    $image_url = '/LechGo_Final/public/uploads/products/' . $unique_name;
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
                    $image_url = '/LechGo_Final/public/uploads/products/' . $unique_name;
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
                            '/LechGo_Final/public/supplier/fd-orders'
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
                            '/LechGo_Final/public/supplier/fd-orders'
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
                                    '/LechGo_Final/public/feed-distributor/orders'
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
    <link rel="stylesheet" href="/LechGo_Final/public/styles.css">
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
            <button onclick="window.print()">🖨️ Print Receipt</button>
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
                                    <p><a href='/LechGo_Final/public/supplier/orders' style='background: #2ecc71; color: white; padding: 10px 20px; text-decoration: none; border-radius: 5px;'>View Order Details</a></p>
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

    case 'customer/reviews':
        if ($_SERVER['REQUEST_METHOD'] === 'GET') {
            // RBAC check already done above
            // Placeholder for reviews page
            echo '<div style="padding: 20px; text-align: center;"><h2>Customer Reviews</h2><p>Reviews feature coming soon!</p></div>';
        }
        break;

    case 'customer/profile':
        if ($_SERVER['REQUEST_METHOD'] === 'GET') {
            // RBAC check already done above
            // Placeholder for profile page
            echo '<div style="padding: 20px; text-align: center;"><h2>Customer Profile</h2><p>Profile management coming soon!</p></div>';
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

    case 'lechonero/reviews':
        if ($_SERVER['REQUEST_METHOD'] === 'GET') {
            // RBAC check already done above
            // Placeholder for reviews page
            echo '<div style="padding: 20px; text-align: center;"><h2>Lechonero Reviews</h2><p>Reviews feature coming soon!</p></div>';
        }
        break;

    // ========== LOGISTICS ROUTES ==========
    case 'logistics/delivery-status':
        if ($_SERVER['REQUEST_METHOD'] === 'GET') {
            // RBAC check already done above
            require VIEWS_PATH . '/logistics/delivery-status.php';
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
                    "SELECT hm.pig_tag_id, hm.total_price, hm.status, lo.user_id as owner_user_id
                     FROM hogs_market hm
                     JOIN livestock_owners lo ON lo.id = hm.livestock_owner_id
                     WHERE hm.id = ? AND hm.status = 'active'"
                );
                $stmt->bind_param('i', $listing_id);
                $stmt->execute();
                $listing = $stmt->get_result()->fetch_assoc();
                $stmt->close();

                if (!$listing) throw new Exception('This pig is no longer available.');

                // Reserve the listing
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
                    '/LechGo_Final/public/livestock-owner/my-pig-market'
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

    // ========== LIVESTOCK OWNER - PIG MARKET ==========
    case 'livestock-owner/my-pig-market':
        if ($_SERVER['REQUEST_METHOD'] === 'GET') {
            require VIEWS_PATH . '/livestock-owner/my-pig-market.php';
        }
        break;

    case 'livestock-owner/post-pig-to-market':
        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            try {
                $user = $sessionMiddleware->getUser();
                $pig_detail_id = (int)($_POST['pig_detail_id'] ?? 0);
                $pig_tag_id    = trim($_POST['pig_tag_id'] ?? '');
                $pin_number    = trim($_POST['pin_number'] ?? '');
                $weight_kg     = (float)($_POST['weight_kg'] ?? 0);
                $price_per_kg  = (float)($_POST['price_per_kg'] ?? 0);
                $description   = trim($_POST['description'] ?? '');

                if (!$pig_detail_id || $weight_kg <= 0 || $price_per_kg <= 0) {
                    throw new Exception('Invalid pig or price data');
                }

                $stmt = $conn->prepare("SELECT id FROM livestock_owners WHERE user_id = ?");
                $stmt->bind_param('i', $user['id']);
                $stmt->execute();
                $owner = $stmt->get_result()->fetch_assoc();
                $stmt->close();
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
                if (!$ins->execute()) throw new Exception('Error creating listing: ' . $ins->error);
                $ins->close();

                $_SESSION['success'] = " {$pig_tag_id} posted to your market!";
                header('Location: ' . $base_url . '/livestock-owner/my-pig-market');
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

                    // Notify the customer who reserved it
                    if ($listing['reserved_by_user_id']) {
                        $seller_feedback = trim($_POST['seller_feedback'] ?? '');
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
                            '/LechGo_Final/public/customer/buy-pig'
                        );
                    }
                    $_SESSION['success'] = ' Marked as sold! The customer has been notified.';
                } elseif ($action === 'active') {
                    // Cancel reservation — reset reserved fields
                    $rst = $conn->prepare(
                        "UPDATE hogs_market SET reserved_by_user_id=NULL, reserved_by_name=NULL, inquiry_message=NULL, reserved_at=NULL WHERE id=? AND livestock_owner_id=?"
                    );
                    $rst->bind_param('ii', $listing_id, $owner['id']);
                    $rst->execute();
                    $rst->close();
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

    default:
        http_response_code(404);
        echo "404 - Page not found";
        break;
}

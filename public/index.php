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
require_once APP_PATH . '/controllers/AuthController.php';
require_once APP_PATH . '/controllers/LocationController.php';
require_once APP_PATH . '/services/EmailService.php';
require_once APP_PATH . '/services/PayMongoService.php';
require_once APP_PATH . '/middleware/Session.php';

// Initialize middleware
$sessionMiddleware = new Session();

// Parse request URI
$request_uri = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);
$base_url = '/LechGo_Final/public';
$route = str_replace($base_url, '', $request_uri);
$route = trim($route, '/');

// Handle empty route
if (empty($route)) {
    $route = 'landing';
}

// Route handler
switch ($route) {
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

    case 'auth/login':
        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            $authController = new AuthController($conn);
            $authController->login();
        }
        break;

    case 'dashboard':
    case 'home':
        // Protected route - check session
        if (!$sessionMiddleware->isAuthenticated()) {
            header('Location: ' . $base_url . '/login');
            exit;
        }
        require VIEWS_PATH . '/home.php';
        break;

    case 'logout':
        $authController = new AuthController($conn);
        $authController->logout();
        break;

    case 'complete-profile':
        if ($_SERVER['REQUEST_METHOD'] === 'GET') {
            // Protected route - check session
            if (!$sessionMiddleware->isAuthenticated()) {
                header('Location: ' . $base_url . '/login');
                exit;
            }
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
        // Protected route - check session
        if (!$sessionMiddleware->isAuthenticated()) {
            header('Location: ' . $base_url . '/login');
            exit;
        }
        require VIEWS_PATH . '/locations.php';
        break;

    case 'pig-caretaker/feed-inventory':
        if ($_SERVER['REQUEST_METHOD'] === 'GET') {
            if (!$sessionMiddleware->isAuthenticated() || $sessionMiddleware->getUser()['role'] !== 'pig_caretaker') {
                header('Location: ' . $base_url . '/login');
                exit;
            }
            require VIEWS_PATH . '/pig_caretaker/feed_inventory.php';
        }
        break;

    case 'pig-caretaker/pigs':
        if ($_SERVER['REQUEST_METHOD'] === 'GET') {
            if (!$sessionMiddleware->isAuthenticated() || $sessionMiddleware->getUser()['role'] !== 'pig_caretaker') {
                header('Location: ' . $base_url . '/login');
                exit;
            }
            require VIEWS_PATH . '/pig_caretaker/pig_inventory.php';
        }
        break;

    case 'pig-caretaker/feeding-schedule':
        if ($_SERVER['REQUEST_METHOD'] === 'GET') {
            if (!$sessionMiddleware->isAuthenticated() || $sessionMiddleware->getUser()['role'] !== 'pig_caretaker') {
                header('Location: ' . $base_url . '/login');
                exit;
            }
            require VIEWS_PATH . '/pig_caretaker/feeding_schedule.php';
        }
        break;

    case 'pig-caretaker/farm-profile':
        if ($_SERVER['REQUEST_METHOD'] === 'GET') {
            if (!$sessionMiddleware->isAuthenticated() || $sessionMiddleware->getUser()['role'] !== 'pig_caretaker') {
                header('Location: ' . $base_url . '/login');
                exit;
            }
            require VIEWS_PATH . '/pig_caretaker/farm_profile.php';
        }
        break;

    case 'pig-caretaker/add-feed':
        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            if (!$sessionMiddleware->isAuthenticated() || $sessionMiddleware->getUser()['role'] !== 'pig_caretaker') {
                header('Location: ' . $base_url . '/login');
                exit;
            }
            
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
            if (!$sessionMiddleware->isAuthenticated() || $sessionMiddleware->getUser()['role'] !== 'pig_caretaker') {
                http_response_code(403);
                header('Content-Type: application/json');
                echo json_encode(['error' => 'Unauthorized']);
                exit;
            }

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
                $query = "SELECT lfo.id, lfo.supplier_id, lfo.created_at,
                                 u.name as supplier_name,
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
                    if (empty($item['feed_type']) || empty($item['quantity_kg'])) {
                        continue;
                    }

                    try {
                        $pigCaretaker->addFeedToInventory(
                            $item['feed_type'],
                            $item['quantity_kg'],
                            $item['unit_price'] ?? null,
                            $supplier_name,
                            $purchase_date,
                            null  // no expiry date for now
                        );
                        $imported_count++;
                    } catch (Exception $e) {
                        // Log but continue with other items
                        error_log('Feed import error: ' . $e->getMessage());
                    }
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
            if (!$sessionMiddleware->isAuthenticated() || $sessionMiddleware->getUser()['role'] !== 'pig_caretaker') {
                header('Location: ' . $base_url . '/login');
                exit;
            }
            
            try {
                $user = $sessionMiddleware->getUser();
                $cage_id = $_POST['cage_id'] ?? null;
                $feed_inventory_id = $_POST['feed_inventory_id'] ?? null;
                $feeding_date = $_POST['feeding_date'] ?? date('Y-m-d');
                $feeding_time = $_POST['feeding_time'] ?? date('H:i');
                $amount_kg = !empty($_POST['amount_kg']) ? (float)$_POST['amount_kg'] : 0;
                $notes = $_POST['notes'] ?? '';
                
                if (!$cage_id) {
                    throw new Exception('Cage is required');
                }
                
                // Get caretaker
                $caretaker = new PigCaretaker($conn);
                if (!$caretaker->findByUserId($user['id'])) {
                    throw new Exception('Caretaker profile not found');
                }
                
                // Verify cage belongs to this caretaker
                $query = "SELECT id FROM pig_cages WHERE id = ? AND caretaker_id = ?";
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
            if (!$sessionMiddleware->isAuthenticated() || $sessionMiddleware->getUser()['role'] !== 'livestock_owner') {
                $_SESSION['error'] = 'Please login as livestock owner';
                header('Location: ' . $base_url . '/login');
                exit;
            }
            
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
            if (!$sessionMiddleware->isAuthenticated() || $sessionMiddleware->getUser()['role'] !== 'livestock_owner') {
                header('Location: ' . $base_url . '/login');
                exit;
            }
            require VIEWS_PATH . '/livestock-owner/available_feeds.php';
        }
        break;

    // Livestock Owner - Add Product to Cart
    case 'livestock-owner/add-to-cart':
        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            if (!$sessionMiddleware->isAuthenticated() || $sessionMiddleware->getUser()['role'] !== 'livestock_owner') {
                header('Location: ' . $base_url . '/login');
                exit;
            }

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
            if (!$sessionMiddleware->isAuthenticated() || $sessionMiddleware->getUser()['role'] !== 'livestock_owner') {
                header('Location: ' . $base_url . '/login');
                exit;
            }
            require VIEWS_PATH . '/livestock-owner/checkout.php';
        } elseif ($_SERVER['REQUEST_METHOD'] === 'POST') {
            if (!$sessionMiddleware->isAuthenticated() || $sessionMiddleware->getUser()['role'] !== 'livestock_owner') {
                header('Location: ' . $base_url . '/login');
                exit;
            }

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
                $delivery_notes = $_POST['delivery_notes'] ?? '';

                // If online payment, store details in session and redirect to payment page
                if ($payment_method === 'online_payment') {
                    $_SESSION['pending_orders'] = [
                        'orders_by_supplier' => $orders_by_supplier,
                        'owner' => $owner,
                        'delivery_address' => $delivery_address,
                        'delivery_notes' => $delivery_notes,
                        'payment_method' => $payment_method
                    ];
                    header('Location: ' . $base_url . '/livestock-owner/payment');
                    exit;
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
                    $query = "INSERT INTO livestock_feed_orders (livestock_owner_id, supplier_id, order_number, order_status, payment_status, delivery_status, total_amount, delivery_address, delivery_notes, payment_method)
                              VALUES (?, ?, ?, 'pending', 'unpaid', 'pending', ?, ?, ?, ?)";
                    $stmt = $conn->prepare($query);
                    if (!$stmt) {
                        throw new Exception('Database error: ' . $conn->error);
                    }

                    $stmt->bind_param('iisdsss', $owner['id'], $supplier_id, $order_number, $total_amount, $delivery_address, $delivery_notes, $payment_method);
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
                        $stmt->bind_param('iisidds', $order_id, $item['product_id'], $item['product_name'], $item['feed_type'], $item['quantity_kg'], $item['unit_price'], $item['subtotal']);
                        if (!$stmt->execute()) {
                            throw new Exception('Error adding order item: ' . $stmt->error);
                        }
                        $stmt->close();
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
            if (!$sessionMiddleware->isAuthenticated() || $sessionMiddleware->getUser()['role'] !== 'livestock_owner') {
                header('Location: ' . $base_url . '/login');
                exit;
            }
            require VIEWS_PATH . '/livestock-owner/payment.php';
        } elseif ($_SERVER['REQUEST_METHOD'] === 'POST') {
            if (!$sessionMiddleware->isAuthenticated() || $sessionMiddleware->getUser()['role'] !== 'livestock_owner') {
                header('Location: ' . $base_url . '/login');
                exit;
            }

            try {
                $pending_orders = $_SESSION['pending_orders'] ?? null;
                if (!$pending_orders) {
                    throw new Exception('No pending orders found');
                }

                $orders_by_supplier = $pending_orders['orders_by_supplier'];
                $owner = $pending_orders['owner'];
                $delivery_address = $pending_orders['delivery_address'];
                $delivery_notes = $pending_orders['delivery_notes'];
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
                    $query = "INSERT INTO livestock_feed_orders (livestock_owner_id, supplier_id, order_number, order_status, payment_status, delivery_status, total_amount, delivery_address, delivery_notes, payment_method, payment_reference)
                              VALUES (?, ?, ?, 'pending', 'pending', 'pending', ?, ?, ?, ?, ?)";
                    $stmt = $conn->prepare($query);
                    if (!$stmt) {
                        throw new Exception('Database error: ' . $conn->error);
                    }

                    $stmt->bind_param('iisdssss', $owner['id'], $supplier_id, $order_number, $total_amount, $delivery_address, $delivery_notes, $payment_method, $payment_reference);
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
                        $stmt->bind_param('iisidds', $order_id, $item['product_id'], $item['product_name'], $item['feed_type'], $item['quantity_kg'], $item['unit_price'], $item['subtotal']);
                        if (!$stmt->execute()) {
                            throw new Exception('Error adding order item: ' . $stmt->error);
                        }
                        $stmt->close();
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

    // Livestock Owner - Payment Success Handler
    case 'livestock-owner/payment-success':
        if ($_SERVER['REQUEST_METHOD'] === 'GET') {
            if (!$sessionMiddleware->isAuthenticated() || $sessionMiddleware->getUser()['role'] !== 'livestock_owner') {
                header('Location: ' . $base_url . '/login');
                exit;
            }

            try {
                $pending_orders = $_SESSION['pending_orders'] ?? null;
                if (!$pending_orders) {
                    throw new Exception('No pending orders found');
                }

                $orders_by_supplier = $pending_orders['orders_by_supplier'];
                $owner = $pending_orders['owner'];
                $delivery_address = $pending_orders['delivery_address'];
                $delivery_notes = $pending_orders['delivery_notes'];
                $payment_method = $pending_orders['payment_method'];

                // Check if this is COD or PayMongo
                $is_cod = isset($_GET['method']) && $_GET['method'] === 'cod';
                $payment_intent_id = $_GET['payment_intent_id'] ?? null;

                if (!$is_cod && !$payment_intent_id) {
                    throw new Exception('Payment reference not found');
                }

                // Determine payment status based on method
                $payment_status = $is_cod ? 'unpaid' : 'paid';

                // Create orders for each supplier with the payment reference
                foreach ($orders_by_supplier as $supplier_id => $items) {
                    $total_amount = 0;
                    foreach ($items as $item) {
                        $total_amount += $item['subtotal'];
                    }

                    // Generate order number
                    $order_number = 'LO-' . $owner['id'] . '-' . time();

                    // Insert order with payment info
                    $query = "INSERT INTO livestock_feed_orders (livestock_owner_id, supplier_id, order_number, order_status, payment_status, delivery_status, total_amount, delivery_address, delivery_notes, payment_method, payment_reference)
                              VALUES (?, ?, ?, 'pending', ?, 'pending', ?, ?, ?, ?, ?)";
                    $stmt = $conn->prepare($query);
                    if (!$stmt) {
                        throw new Exception('Database error: ' . $conn->error);
                    }

                    $ref_id = $is_cod ? null : $payment_intent_id;
                    $stmt->bind_param('iissdssss', $owner['id'], $supplier_id, $order_number, $payment_status, $total_amount, $delivery_address, $delivery_notes, $payment_method, $ref_id);
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
                        $stmt->bind_param('iisidds', $order_id, $item['product_id'], $item['product_name'], $item['feed_type'], $item['quantity_kg'], $item['unit_price'], $item['subtotal']);
                        if (!$stmt->execute()) {
                            throw new Exception('Error adding order item: ' . $stmt->error);
                        }
                        $stmt->close();
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
                                $payment_note = $is_cod ? '<p><strong style="color: red;">⚠️ Payment Status: CASH ON DELIVERY</strong> - Customer will pay upon delivery</p>' : '';
                                $email_body = "
                                    <h2>New Feed Order Received!</h2>
                                    <p>You have received a new order from <strong>" . htmlspecialchars($owner['name']) . "</strong></p>
                                    <p><strong>Order Number:</strong> " . $order_number . "</p>
                                    <p><strong>Total Amount:</strong> ₱" . number_format($total_amount, 2) . "</p>
                                    <p><strong>Delivery Address:</strong><br>" . nl2br(htmlspecialchars($delivery_address)) . "</p>
                                    " . $payment_note . "
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
                
                if ($is_cod) {
                    $_SESSION['success'] = '✓ Order placed successfully! Payment will be collected upon delivery.';
                } else {
                    $_SESSION['success'] = '✓ Payment successful! Your orders have been placed.';
                }
                
                header('Location: ' . $base_url . '/livestock-owner/my-orders');
                exit;

            } catch (Exception $e) {
                $_SESSION['error'] = 'Error processing payment: ' . $e->getMessage();
                header('Location: ' . $base_url . '/livestock-owner/payment');
                exit;
            }
        }
        break;

    // Livestock Owner - View My Orders
    case 'livestock-owner/my-orders':
        if ($_SERVER['REQUEST_METHOD'] === 'GET') {
            if (!$sessionMiddleware->isAuthenticated() || $sessionMiddleware->getUser()['role'] !== 'livestock_owner') {
                header('Location: ' . $base_url . '/login');
                exit;
            }
            require VIEWS_PATH . '/livestock-owner/my-orders.php';
        }
        break;

    // Livestock Owner - View Order Receipt
    case preg_match('/^livestock-owner\/receipt\/\d+$/', $route) ? $route : null:
        if ($_SERVER['REQUEST_METHOD'] === 'GET') {
            if (!$sessionMiddleware->isAuthenticated() || $sessionMiddleware->getUser()['role'] !== 'livestock_owner') {
                header('Location: ' . $base_url . '/login');
                exit;
            }

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
            if (!$sessionMiddleware->isAuthenticated() || $sessionMiddleware->getUser()['role'] !== 'livestock_owner') {
                header('Location: ' . $base_url . '/login');
                exit;
            }
            require VIEWS_PATH . '/livestock-owner/caretaker-reports.php';
        }
        break;

    // Livestock Owner - View Caretaker Feed Inventory
    case 'livestock-owner/caretaker-feed-inventory':
        if ($_SERVER['REQUEST_METHOD'] === 'GET') {
            if (!$sessionMiddleware->isAuthenticated() || $sessionMiddleware->getUser()['role'] !== 'livestock_owner') {
                header('Location: ' . $base_url . '/login');
                exit;
            }
            require VIEWS_PATH . '/livestock-owner/caretaker-feed-inventory.php';
        }
        break;

    // Livestock Owner - View Caretaker Pig Inventory
    case 'livestock-owner/caretaker-pig-inventory':
        if ($_SERVER['REQUEST_METHOD'] === 'GET') {
            if (!$sessionMiddleware->isAuthenticated() || $sessionMiddleware->getUser()['role'] !== 'livestock_owner') {
                header('Location: ' . $base_url . '/login');
                exit;
            }
            require VIEWS_PATH . '/livestock-owner/caretaker-pig-inventory.php';
        }
        break;

    // Pig Caretaker - Add Pig to Cage
    case 'pig-caretaker/add-pig':
        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            if (!$sessionMiddleware->isAuthenticated() || $sessionMiddleware->getUser()['role'] !== 'pig_caretaker') {
                $_SESSION['error'] = 'Please login as pig caretaker';
                header('Location: ' . $base_url . '/login');
                exit;
            }
            
            try {
                $user = $sessionMiddleware->getUser();
                $cage_id = $_POST['cage_id'] ?? null;
                $pig_tag_id = !empty($_POST['pig_tag_id']) ? $_POST['pig_tag_id'] : null;
                $breed = $_POST['breed'] ?? '';
                $age_months = !empty($_POST['age_months']) ? (int)$_POST['age_months'] : 0;
                $weight_kg = !empty($_POST['weight_kg']) ? (float)$_POST['weight_kg'] : 0.00;
                $health_status = $_POST['health_status'] ?? 'healthy';
                $date_added = $_POST['date_added'] ?? date('Y-m-d');
                $notes = $_POST['notes'] ?? '';
                
                if (!$cage_id || empty($breed)) {
                    throw new Exception('Cage ID and breed are required');
                }
                
                // Verify cage belongs to this caretaker and has space
                $query = "SELECT pg.* FROM pig_cages pg
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
                
                // Insert pig
                $query = "INSERT INTO pig_details (cage_id, pig_tag_id, breed, age_months, weight_kg, health_status, date_added, status, notes)
                          VALUES (?, ?, ?, ?, ?, ?, ?, 'active', ?)";
                $stmt = $conn->prepare($query);
                if (!$stmt) {
                    throw new Exception('Database error');
                }
                
                $stmt->bind_param('issidsss', $cage_id, $pig_tag_id, $breed, $age_months, $weight_kg, $health_status, $date_added, $notes);
                if (!$stmt->execute()) {
                    throw new Exception('Error adding pig: ' . $stmt->error);
                }
                $stmt->close();
                
                // Update cage pig count
                $new_count = $cage['current_pig_count'] + 1;
                $query = "UPDATE pig_cages SET current_pig_count = ? WHERE id = ?";
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

    // Pig Caretaker Submit Report
    case 'pig-caretaker/submit-report':
        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            if (!$sessionMiddleware->isAuthenticated() || $sessionMiddleware->getUser()['role'] !== 'pig_caretaker') {
                $_SESSION['error'] = 'Please login as pig caretaker';
                header('Location: ' . $base_url . '/login');
                exit;
            }
            
            try {
                $user = $sessionMiddleware->getUser();
                $title = $_POST['title'] ?? '';
                $content = $_POST['content'] ?? '';
                $report_date = $_POST['report_date'] ?? date('Y-m-d');
                
                if (empty($title) || empty($content)) {
                    throw new Exception('Report title and content required');
                }
                
                // Get pig caretaker
                $caretaker = new PigCaretaker($conn);
                if (!$caretaker->findByUserId($user['id'])) {
                    throw new Exception('Caretaker profile not found');
                }
                
                // Create report
                $query = "INSERT INTO caretaker_reports (caretaker_id, title, content, report_date, created_at)
                          VALUES (?, ?, ?, ?, NOW())";
                $stmt = $conn->prepare($query);
                if (!$stmt) {
                    throw new Exception('Database error');
                }
                
                $stmt->bind_param('isss', $caretaker->id, $title, $content, $report_date);
                if (!$stmt->execute()) {
                    throw new Exception('Error creating report');
                }
                $stmt->close();
                
                $_SESSION['success'] = 'Report submitted successfully!';
                header('Location: ' . $base_url . '/pig-caretaker/dashboard');
                exit;
                
            } catch (Exception $e) {
                $_SESSION['error'] = 'Error: ' . $e->getMessage();
                header('Location: ' . $base_url . '/pig-caretaker/reports');
                exit;
            }
        }
        break;

    // Pig Caretaker Reports Page
    case 'pig-caretaker/reports':
        if ($_SERVER['REQUEST_METHOD'] === 'GET') {
            if (!$sessionMiddleware->isAuthenticated() || $sessionMiddleware->getUser()['role'] !== 'pig_caretaker') {
                header('Location: ' . $base_url . '/login');
                exit;
            }
            require VIEWS_PATH . '/pig-caretaker/reports.php';
        }
        break;





    // ========== SUPPLIER - PRODUCT INVENTORY ==========
    
    // Supplier - Product Inventory
    case 'supplier/product-inventory':
        if ($_SERVER['REQUEST_METHOD'] === 'GET') {
            if (!$sessionMiddleware->isAuthenticated() || $sessionMiddleware->getUser()['role'] !== 'supplier') {
                header('Location: ' . $base_url . '/login');
                exit;
            }
            require VIEWS_PATH . '/supplier/product-inventory.php';
        }
        break;

    // Supplier - Add Product
    case 'supplier/add-product':
        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            if (!$sessionMiddleware->isAuthenticated() || $sessionMiddleware->getUser()['role'] !== 'supplier') {
                header('Location: ' . $base_url . '/login');
                exit;
            }

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
            if (!$sessionMiddleware->isAuthenticated() || $sessionMiddleware->getUser()['role'] !== 'supplier') {
                header('Content-Type: application/json');
                die(json_encode(['success' => false, 'message' => 'Unauthorized']));
            }

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
            if (!$sessionMiddleware->isAuthenticated() || $sessionMiddleware->getUser()['role'] !== 'supplier') {
                header('Content-Type: application/json');
                die(json_encode(['success' => false, 'message' => 'Unauthorized']));
            }

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
            if (!$sessionMiddleware->isAuthenticated() || $sessionMiddleware->getUser()['role'] !== 'supplier') {
                header('Location: ' . $base_url . '/login');
                exit;
            }

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
                $query = "SELECT image_url FROM feed_products WHERE id = ? AND supplier_id = ?";
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

                // Update product
                $query = "UPDATE feed_products SET product_name = ?, feed_type = ?, description = ?, unit_price = ?, quantity_available_kg = ?, image_url = ? WHERE id = ? AND supplier_id = ?";
                $stmt = $conn->prepare($query);
                if (!$stmt) {
                    throw new Exception('Database error: ' . $conn->error);
                }
                $stmt->bind_param('sssddsii', $product_name, $feed_type, $description, $unit_price, $quantity_available_kg, $image_url, $product_id, $supplier['id']);
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

                // Update order status to confirmed
                $query = "UPDATE livestock_feed_orders SET order_status = 'confirmed' WHERE id = ?";
                $stmt = $conn->prepare($query);
                if (!$stmt) {
                    throw new Exception('Database error: ' . $conn->error);
                }

                $stmt->bind_param('i', $order_id);
                if (!$stmt->execute()) {
                    throw new Exception('Error updating order: ' . $stmt->error);
                }
                $stmt->close();

                $_SESSION['success'] = '✓ Order accepted! Receipt generated.';
                header('Location: ' . $base_url . '/supplier/orders');
                exit;

            } catch (Exception $e) {
                $_SESSION['error'] = 'Error accepting order: ' . $e->getMessage();
                header('Location: ' . $base_url . '/supplier/orders');
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
                             lo.farm_name, lo.location,
                             s.name as supplier_name
                      FROM livestock_feed_orders lfo
                      LEFT JOIN livestock_owners lo ON lfo.livestock_owner_id = lo.id
                      LEFT JOIN users u ON lo.user_id = u.id
                      LEFT JOIN suppliers s ON lfo.supplier_id = s.id
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

                // Create payment intent
                $payMongo = new PayMongoService();
                $paymentIntent = $payMongo->createPaymentIntent(
                    $amountInPesos,
                    'order-' . time(),
                    $description,
                    $_SESSION['user']['email'] ?? null
                );

                if (!isset($paymentIntent['id'])) {
                    throw new Exception("Failed to create payment intent: " . json_encode($paymentIntent));
                }

                // Extract client key from attributes
                $clientKey = $paymentIntent['attributes']['client_key'] ?? null;
                
                if (!$clientKey) {
                    throw new Exception("No client key in payment intent response");
                }

                echo json_encode([
                    'success' => true,
                    'intentId' => $paymentIntent['id'],
                    'client_key' => $clientKey,
                    'status' => $paymentIntent['attributes']['status'] ?? 'awaiting_payment_method',
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

    // Receipt routes
    case preg_match('/^order-receipt\/\d+$/', $route) ? $route : null:
        if ($_SERVER['REQUEST_METHOD'] === 'GET') {
            $route_parts = explode('/', $route);
            $order_id = intval(array_pop($route_parts));
            require VIEWS_PATH . '/receipt.php';
        }
        break;

    default:
        http_response_code(404);
        echo "404 - Page not found";
        break;
}

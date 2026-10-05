<?php

/**
 * PayMongo Payment Service
 * Handles payment processing through PayMongo API
 */

class PayMongoService {
    private $apiKey;
    private $apiUrl = 'https://api.paymongo.com/v1';
    private $conn;

    public function __construct($apiKey = null, $conn = null) {
        // Load .env file if not already loaded
        if (!isset($_ENV['PAYMONGO_SECRET'])) {
            $envFile = __DIR__ . '/../../.env';
            if (file_exists($envFile)) {
                $lines = file($envFile, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
                foreach ($lines as $line) {
                    if (strpos(trim($line), '#') === 0) continue;
                    list($key, $value) = explode('=', $line, 2);
                    $_ENV[trim($key)] = trim($value);
                }
            }
        }
        
        // Use provided key, or environment variable, or test key
        // IMPORTANT: Must use SECRET key (sk_test_*) for server-side API calls, NOT public key
        $this->apiKey = $apiKey ?? ($_ENV['PAYMONGO_SECRET'] ?? 'sk_test_GjTkuDZcy9mquPGvvSm9g4Uq');
        $this->conn = $conn;
    }

    /**
     * Create a checkout session (PayMongo Link)
     */
    public function createCheckoutSession($amount, $description, $successUrl = null, $cancelUrl = null, $metadata = []) {
        try {
            // Set default URLs if not provided
            $baseUrl = (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? "https" : "http") . "://" . $_SERVER['HTTP_HOST'];
            
            // PayMongo will show their success page first, then redirect here when customer clicks "Continue"
            // Add query parameter to show success message
            $defaultSuccessUrl = $baseUrl . '/customer/my-orders?payment_success=1';
            $defaultCancelUrl = $baseUrl . '/customer/my-orders?payment_cancelled=1';
            
            $successUrl = $successUrl ?? $defaultSuccessUrl;
            $cancelUrl = $cancelUrl ?? $defaultCancelUrl;
            
            $payload = [
                'data' => [
                    'attributes' => [
                        'amount' => intval($amount * 100), // Convert to centavos
                        'currency' => 'PHP',
                        'description' => $description,
                        'remarks' => 'LechGO Pig Order Payment',
                        'success_url' => $successUrl,
                        'failed_url' => $cancelUrl,
                        'payment_method_types' => [
                            'card',
                            'gcash',
                            'paymaya',
                            'grab_pay',
                            'qrph'
                        ],
                        'metadata' => array_merge([
                            'source' => 'lechgo_system',
                            'timestamp' => time()
                        ], $metadata)
                    ]
                ]
            ];

            $response = $this->makeRequest('POST', '/links', $payload);

            if (isset($response['data']['id'])) {
                return $response['data'];
            } else {
                throw new Exception("Failed to create checkout session: " . json_encode($response));
            }
        } catch (Exception $e) {
            throw new Exception("PayMongo Error: " . $e->getMessage());
        }
    }

    /**
     * Get checkout session (PayMongo Link) details
     */
    public function getCheckoutSession($linkId) {
        try {
            $response = $this->makeRequest('GET', '/links/' . $linkId);

            if (isset($response['data']['id'])) {
                return $response['data'];
            } else {
                throw new Exception("Failed to retrieve checkout session");
            }
        } catch (Exception $e) {
            throw new Exception("PayMongo Error: " . $e->getMessage());
        }
    }

    /**
     * Create a payment intent for the order
     */
    public function createPaymentIntent($amount, $orderId, $description, $customerEmail = null) {
        try {
            $payload = [
                'data' => [
                    'attributes' => [
                        'amount' => intval($amount * 100), // Convert to centavos
                        'currency' => 'PHP', // Required by PayMongo API
                        'payment_method_allowed' => ['card', 'gcash', 'grab_pay', 'paymaya'],
                        'payment_method_options' => [
                            'card' => [
                                'request_three_d_secure' => 'automatic'
                            ]
                        ],
                        'description' => $description,
                        'statement_descriptor' => 'LechGO Feed Order',
                        'metadata' => [
                            'order_id' => $orderId,
                            'customer_email' => $customerEmail ?? ''
                        ]
                    ]
                ]
            ];

            $response = $this->makeRequest('POST', '/payment_intents', $payload);

            if (isset($response['data']['id'])) {
                // PayMongo payment intents include checkout_url in the response
                return $response['data'];
            } else {
                throw new Exception("Failed to create payment intent: " . json_encode($response));
            }
        } catch (Exception $e) {
            throw new Exception("PayMongo Error: " . $e->getMessage());
        }
    }

    /**
     * Retrieve payment intent status
     */
    public function getPaymentIntent($intentId) {
        try {
            $response = $this->makeRequest('GET', '/payment_intents/' . $intentId);

            if (isset($response['data']['id'])) {
                return $response['data'];
            } else {
                throw new Exception("Failed to retrieve payment intent");
            }
        } catch (Exception $e) {
            throw new Exception("PayMongo Error: " . $e->getMessage());
        }
    }

    /**
     * Attach payment method to payment intent
     */
    public function attachPaymentMethod($intentId, $paymentMethodId) {
        try {
            $payload = [
                'data' => [
                    'attributes' => [
                        'payment_method' => $paymentMethodId
                    ]
                ]
            ];

            $response = $this->makeRequest('POST', '/payment_intents/' . $intentId . '/attach', $payload);

            if (isset($response['data']['id'])) {
                return $response['data'];
            } else {
                throw new Exception("Failed to attach payment method");
            }
        } catch (Exception $e) {
            throw new Exception("PayMongo Error: " . $e->getMessage());
        }
    }

    /**
     * Verify webhook signature from PayMongo
     */
    public function verifyWebhookSignature($payload, $signature) {
        try {
            // Get the secret key for webhook verification
            $webhookSecret = $_ENV['PAYMONGO_WEBHOOK_SECRET'] ?? 'whsk_zSep7iBnhj9m6swVKfcase2N';

            if (empty($webhookSecret)) {
                // For testing/development only - log warning but don't fail
                error_log("WARNING: Webhook secret not configured. Webhook verification skipped.");
                return true; // Allow webhook for testing
            }

            // PayMongo uses HMAC-SHA256
            $hmac = hash_hmac('sha256', $payload, $webhookSecret, true);
            $computedSignature = base64_encode($hmac);

            return hash_equals($computedSignature, $signature);
        } catch (Exception $e) {
            throw new Exception("Webhook Verification Error: " . $e->getMessage());
        }
    }

    /**
     * Handle payment webhook
     */
    public function handlePaymentWebhook($data) {
        try {
            $eventType = $data['data']['type'] ?? null;
            $attributes = $data['data']['attributes'] ?? [];
            
            error_log("PayMongo Webhook Event: " . $eventType);
            error_log("PayMongo Webhook Data: " . json_encode($data));

            // Handle different webhook events
            switch ($eventType) {
                case 'link.payment.paid':
                    return $this->handleLinkPaymentPaid($data);
                    
                case 'link.payment.failed':
                    return $this->handleLinkPaymentFailed($data);
                    
                case 'payment.paid':
                    return $this->handlePaymentPaid($data);
                    
                case 'payment.failed':
                    return $this->handlePaymentFailed($data);
                    
                default:
                    error_log("Unhandled webhook event type: " . $eventType);
                    return false;
            }
            
        } catch (Exception $e) {
            throw new Exception("Webhook Handler Error: " . $e->getMessage());
        }
    }
    
    /**
     * Handle link payment paid event
     */
    private function handleLinkPaymentPaid($data) {
        try {
            $linkId = $data['data']['id'] ?? null;
            $attributes = $data['data']['attributes'] ?? [];
            
            if (!$linkId) {
                error_log("No link ID in webhook data");
                return false;
            }
            
            // Find payment record by link ID
            $paymentReference = $this->findPaymentByLinkId($linkId);
            if (!$paymentReference) {
                error_log("No payment found for link ID: " . $linkId);
                return false;
            }
            
            // Update payment status
            $this->updatePaymentStatus($paymentReference, 'completed', $attributes);
            
            // Update order status
            $this->updateOrderStatusByPaymentReference($paymentReference, 'paid');
            
            error_log("Link payment completed: " . $paymentReference);
            return true;
            
        } catch (Exception $e) {
            error_log("Error handling link payment paid: " . $e->getMessage());
            return false;
        }
    }
    
    /**
     * Handle link payment failed event
     */
    private function handleLinkPaymentFailed($data) {
        try {
            $linkId = $data['data']['id'] ?? null;
            $attributes = $data['data']['attributes'] ?? [];
            
            if (!$linkId) {
                return false;
            }
            
            $paymentReference = $this->findPaymentByLinkId($linkId);
            if (!$paymentReference) {
                return false;
            }
            
            $failureReason = $attributes['failure_reason'] ?? 'Payment failed';
            
            // Update payment status
            $this->updatePaymentStatus($paymentReference, 'failed', $attributes, $failureReason);
            
            error_log("Link payment failed: " . $paymentReference . " - " . $failureReason);
            return true;
            
        } catch (Exception $e) {
            error_log("Error handling link payment failed: " . $e->getMessage());
            return false;
        }
    }
    
    /**
     * Handle regular payment paid event
     */
    private function handlePaymentPaid($data) {
        try {
            $intentId = $data['data']['attributes']['payment_intent_id'] ?? null;
            $metadata = $data['data']['attributes']['metadata'] ?? [];
            $orderId = $metadata['order_id'] ?? null;

            if ($orderId) {
                // Update order payment status
                $this->updateOrderPaymentStatus($orderId, 'verified', 'online', $intentId);
                return true;
            }
            
            return false;
        } catch (Exception $e) {
            error_log("Error handling payment paid: " . $e->getMessage());
            return false;
        }
    }
    
    /**
     * Handle regular payment failed event
     */
    private function handlePaymentFailed($data) {
        try {
            $intentId = $data['data']['attributes']['payment_intent_id'] ?? null;
            $metadata = $data['data']['attributes']['metadata'] ?? [];
            $orderId = $metadata['order_id'] ?? null;

            if ($orderId) {
                // Update order payment status to failed
                $this->updateOrderPaymentStatus($orderId, 'failed', 'online', $intentId);
                return true;
            }
            
            return false;
        } catch (Exception $e) {
            error_log("Error handling payment failed: " . $e->getMessage());
            return false;
        }
    }
    
    /**
     * Find payment by PayMongo link ID
     */
    private function findPaymentByLinkId($linkId) {
        try {
            // Check if we have customer_payments table
            $query = "SHOW TABLES LIKE 'customer_payments'";
            $result = $this->conn->query($query);
            
            if ($result && $result->num_rows > 0) {
                // Use new customer_payments table
                $query = "SELECT payment_reference FROM customer_payments 
                          WHERE JSON_EXTRACT(gateway_response, '$.paymongo_link_id') = ? 
                          OR payment_gateway_id = ?
                          LIMIT 1";
                
                $stmt = $this->conn->prepare($query);
                if ($stmt) {
                    $stmt->bind_param("ss", $linkId, $linkId);
                    $stmt->execute();
                    $result = $stmt->get_result();
                    
                    if ($row = $result->fetch_assoc()) {
                        return $row['payment_reference'];
                    }
                }
            }
            
            // Fallback: try to find in order_total_cost table
            $query = "SELECT payment_reference FROM order_total_cost 
                      WHERE payment_reference = ? 
                      LIMIT 1";
            
            $stmt = $this->conn->prepare($query);
            if ($stmt) {
                $stmt->bind_param("s", $linkId);
                $stmt->execute();
                $result = $stmt->get_result();
                
                if ($row = $result->fetch_assoc()) {
                    return $row['payment_reference'];
                }
            }
            
            return null;
            
        } catch (Exception $e) {
            error_log("Error finding payment by link ID: " . $e->getMessage());
            return null;
        }
    }
    
    /**
     * Update payment status in customer_payments table
     */
    private function updatePaymentStatus($paymentReference, $status, $gatewayResponse = null, $failureReason = null) {
        try {
            // Check if customer_payments table exists
            $query = "SHOW TABLES LIKE 'customer_payments'";
            $result = $this->conn->query($query);
            
            if ($result && $result->num_rows > 0) {
                $query = "UPDATE customer_payments SET 
                          payment_status = ?, 
                          payment_date = CASE WHEN ? = 'completed' THEN NOW() ELSE payment_date END,
                          gateway_response = COALESCE(?, gateway_response),
                          failure_reason = ?,
                          updated_at = NOW()
                          WHERE payment_reference = ?";
                
                $stmt = $this->conn->prepare($query);
                if ($stmt) {
                    $gatewayJson = $gatewayResponse ? json_encode($gatewayResponse) : null;
                    $stmt->bind_param("sssss", $status, $status, $gatewayJson, $failureReason, $paymentReference);
                    $stmt->execute();
                    
                    error_log("Updated payment status: " . $paymentReference . " -> " . $status);
                }
            }
            
        } catch (Exception $e) {
            error_log("Error updating payment status: " . $e->getMessage());
        }
    }
    
    /**
     * Update order status by payment reference
     */
    private function updateOrderStatusByPaymentReference($paymentReference, $status) {
        try {
            // Update order_total_cost table (using amount_paid not paid_amount)
            $query = "UPDATE order_total_cost SET 
                      payment_status = ?, 
                      paid_at = NOW(),
                      amount_paid = total_cost,
                      updated_at = NOW() 
                      WHERE payment_reference = ?";
            
            $stmt = $this->conn->prepare($query);
            if ($stmt) {
                $stmt->bind_param("ss", $status, $paymentReference);
                $stmt->execute();
                
                if ($stmt->affected_rows > 0) {
                    error_log("Updated order_total_cost payment status: " . $paymentReference . " -> " . $status);
                    
                    // Also update swine_order_status table
                    $query2 = "UPDATE swine_order_status sos
                               INNER JOIN order_total_cost otc ON otc.swine_order_id = sos.id
                               SET sos.payment_status = ?, 
                                   sos.updated_at = NOW()
                               WHERE otc.payment_reference = ?";
                    
                    $stmt2 = $this->conn->prepare($query2);
                    if ($stmt2) {
                        $stmt2->bind_param("ss", $status, $paymentReference);
                        $stmt2->execute();
                        
                        if ($stmt2->affected_rows > 0) {
                            error_log("Updated swine_order_status payment status: " . $paymentReference . " -> " . $status);
                        } else {
                            error_log("No swine_order_status record found for payment reference: " . $paymentReference);
                        }
                        $stmt2->close();
                    }
                } else {
                    error_log("No order_total_cost record found for payment reference: " . $paymentReference);
                }
                $stmt->close();
            }
            
        } catch (Exception $e) {
            error_log("Error updating order status: " . $e->getMessage());
        }
    }

    /**
     * Update order payment status in database
     */
    private function updateOrderPaymentStatus($orderId, $status, $method, $reference) {
        try {
            // Try feed_orders first
            $query = "UPDATE feed_orders 
                      SET payment_status = ?, payment_method = ?, payment_reference = ?, updated_at = NOW()
                      WHERE id = ?";

            $stmt = $this->conn->prepare($query);
            if (!$stmt) {
                throw new Exception("Prepare error: " . $this->conn->error);
            }

            $stmt->bind_param("sssi", $status, $method, $reference, $orderId);
            $stmt->execute();
            $affected = $stmt->affected_rows;
            $stmt->close();

            // If no feed order found, try swine_order_status
            if ($affected === 0) {
                $query = "UPDATE swine_order_status 
                          SET payment_status = ?, payment_method = ?, payment_reference = ?, updated_at = NOW()
                          WHERE id = ?";

                $stmt = $this->conn->prepare($query);
                if (!$stmt) {
                    throw new Exception("Prepare error: " . $this->conn->error);
                }

                $stmt->bind_param("sssi", $status, $method, $reference, $orderId);
                $stmt->execute();
                $stmt->close();

                // Also update order_total_cost if exists
                if ($status === 'paid' || $status === 'verified') {
                    $query = "UPDATE order_total_cost 
                              SET payment_status = 'paid', 
                                  payment_method = ?,
                                  payment_reference = ?,
                                  amount_paid = total_cost,
                                  paid_at = NOW()
                              WHERE swine_order_id = ?";
                    
                    $stmt = $this->conn->prepare($query);
                    if ($stmt) {
                        $stmt->bind_param("ssi", $method, $reference, $orderId);
                        $stmt->execute();
                        $stmt->close();
                    }
                }
            }

            // If verified, update order status
            if ($status === 'verified' || $status === 'paid') {
                // Try feed_orders
                $query = "UPDATE feed_orders SET order_status = 'reviewing_payment' WHERE id = ? AND order_status = 'pending'";
                $stmt = $this->conn->prepare($query);
                if ($stmt) {
                    $stmt->bind_param("i", $orderId);
                    $stmt->execute();
                    $stmt->close();
                }

                // Try swine_order_status - move to ready_for_pickup if in preparing status
                $query = "UPDATE swine_order_status 
                          SET order_status = CASE 
                              WHEN order_status = 'preparing' THEN 'ready_for_pickup'
                              WHEN order_status = 'cost_computed' THEN 'ready_for_pickup'
                              ELSE order_status 
                          END
                          WHERE id = ? AND payment_status = 'paid'";
                $stmt = $this->conn->prepare($query);
                if ($stmt) {
                    $stmt->bind_param("i", $orderId);
                    $stmt->execute();
                    $stmt->close();
                }
            }

            return true;
        } catch (Exception $e) {
            throw new Exception("Update Payment Status Error: " . $e->getMessage());
        }
    }

    /**
     * Make API request to PayMongo
     */
    private function makeRequest($method, $endpoint, $data = null) {
        try {
            $url = $this->apiUrl . $endpoint;
            
            // Use cURL for more reliable API requests
            $ch = curl_init($url);
            
            if (!$ch) {
                throw new Exception("Failed to initialize cURL");
            }

            // PayMongo uses HTTP Basic Authentication with secret key
            // Use CURLOPT_USERPWD for proper basic auth handling
            $headers = [
                'Content-Type: application/json',
                'Accept: application/json'
            ];

            curl_setopt($ch, CURLOPT_CUSTOMREQUEST, $method);
            curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
            curl_setopt($ch, CURLOPT_TIMEOUT, 30);
            curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, true);
            curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, 2);
            curl_setopt($ch, CURLOPT_USERPWD, $this->apiKey . ':');
            curl_setopt($ch, CURLOPT_HTTPAUTH, CURLAUTH_BASIC);

            if ($data !== null) {
                $jsonData = json_encode($data);
                curl_setopt($ch, CURLOPT_POSTFIELDS, $jsonData);
                error_log("PayMongo API Request: " . $method . " " . $url);
                error_log("Payload: " . $jsonData);
            }

            $response = curl_exec($ch);
            $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
            $curlError = curl_error($ch);

            curl_close($ch);

            if ($response === false) {
                error_log("PayMongo cURL Error: " . $curlError);
                throw new Exception("Request failed: " . $curlError);
            }

            error_log("PayMongo API Response (HTTP {$httpCode}): " . $response);
            
            $decoded = json_decode($response, true);
            if ($decoded === null && $response !== '') {
                error_log("PayMongo JSON decode error: " . json_last_error_msg());
                throw new Exception("Invalid JSON response: " . substr($response, 0, 200));
            }

            return $decoded;
        } catch (Exception $e) {
            error_log("PayMongo API Error: " . $e->getMessage());
            throw new Exception("API Request Error: " . $e->getMessage());
        }
    }

    /**
     * Format amount to PHP currency
     */
    public static function formatAmount($amount) {
        return '₱' . number_format($amount, 2);
    }

    /**
     * Get payment status display text
     */
    public static function getStatusDisplay($status) {
        $statuses = [
            'awaiting_payment_method' => 'Awaiting Payment',
            'succeeded' => 'Payment Successful',
            'failed' => 'Payment Failed',
            'processing' => 'Processing',
            'verified' => 'Verified'
        ];

        return $statuses[$status] ?? ucfirst(str_replace('_', ' ', $status));
    }
}

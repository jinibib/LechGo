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
    public function createCheckoutSession($amount, $description, $successUrl = null, $cancelUrl = null) {
        try {
            $payload = [
                'data' => [
                    'attributes' => [
                        'amount' => intval($amount * 100), // Convert to centavos
                        'currency' => 'PHP',
                        'description' => $description,
                        'remarks' => 'LechGO Feed Order Payment',
                        'payment_method_types' => [
                            'card',
                            'gcash',
                            'paymaya',
                            'grab_pay',
                            'qrph'
                        ]
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

            if ($eventType === 'payment.paid') {
                $intentId = $attributes['payment_intent_id'] ?? null;
                $metadata = $attributes['metadata'] ?? [];
                $orderId = $metadata['order_id'] ?? null;

                if ($orderId) {
                    // Update order payment status
                    $this->updateOrderPaymentStatus($orderId, 'verified', 'online', $intentId);
                    return true;
                }
            } elseif ($eventType === 'payment.failed') {
                $intentId = $attributes['payment_intent_id'] ?? null;
                $metadata = $attributes['metadata'] ?? [];
                $orderId = $metadata['order_id'] ?? null;

                if ($orderId) {
                    // Update order payment status to failed
                    $this->updateOrderPaymentStatus($orderId, 'failed', 'online', $intentId);
                    return true;
                }
            }

            return false;
        } catch (Exception $e) {
            throw new Exception("Webhook Handler Error: " . $e->getMessage());
        }
    }

    /**
     * Update order payment status in database
     */
    private function updateOrderPaymentStatus($orderId, $status, $method, $reference) {
        try {
            $query = "UPDATE feed_orders 
                      SET payment_status = ?, payment_method = ?, payment_reference = ?, updated_at = NOW()
                      WHERE id = ?";

            $stmt = $this->conn->prepare($query);
            if (!$stmt) {
                throw new Exception("Prepare error: " . $this->conn->error);
            }

            $stmt->bind_param("sssi", $status, $method, $reference, $orderId);
            $stmt->execute();
            $stmt->close();

            // If verified, update order status
            if ($status === 'verified') {
                $query = "UPDATE feed_orders SET order_status = 'reviewing_payment' WHERE id = ?";
                $stmt = $this->conn->prepare($query);
                if (!$stmt) {
                    throw new Exception("Prepare error: " . $this->conn->error);
                }
                $stmt->bind_param("i", $orderId);
                $stmt->execute();
                $stmt->close();
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

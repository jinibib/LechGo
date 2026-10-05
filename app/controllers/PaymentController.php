<?php

require_once __DIR__ . '/../models/CustomerPayment.php';
require_once __DIR__ . '/../services/PayMongoService.php';

class PaymentController {
    private $db;
    private $customerPayment;
    private $payMongo;
    
    public function __construct($database) {
        $this->db = $database;
        $this->customerPayment = new CustomerPayment($database);
        $this->payMongo = new PayMongoService(null, $database);
    }
    
    /**
     * Process customer payment for pig orders
     */
    public function processCustomerPayment($orderData) {
        try {
            // Generate unique payment reference
            $paymentReference = $this->generatePaymentReference($orderData['order_id']);
            
            // Log payment attempt
            $paymentData = [
                'payment_reference' => $paymentReference,
                'order_id' => $orderData['order_id'],
                'order_number' => $orderData['order_number'],
                'customer_id' => $orderData['customer_id'],
                'customer_name' => $orderData['customer_name'],
                'payment_method' => $orderData['payment_method'],
                'amount' => $orderData['amount'],
                'payment_status' => 'pending',
                'notes' => 'Payment initiated by customer'
            ];
            
            // Save payment record
            if (!$this->customerPayment->createPayment($paymentData)) {
                throw new Exception('Failed to create payment record');
            }
            
            // Process based on payment method
            switch ($orderData['payment_method']) {
                case 'paymongo':
                    return $this->processPayMongoPayment($paymentReference, $orderData);
                    
                case 'gcash':
                    return $this->processGCashPayment($paymentReference, $orderData);
                    
                case 'cash_on_delivery':
                    return $this->processCODPayment($paymentReference, $orderData);
                    
                default:
                    throw new Exception('Unsupported payment method');
            }
            
        } catch (Exception $e) {
            // Log failed payment attempt
            if (isset($paymentReference)) {
                $this->customerPayment->updatePaymentStatus(
                    $paymentReference, 
                    'failed', 
                    null, 
                    $e->getMessage()
                );
            }
            throw $e;
        }
    }
    
    /**
     * Process PayMongo payment
     */
    private function processPayMongoPayment($paymentReference, $orderData) {
        try {
            // Create PayMongo checkout session
            $description = "Payment for Order " . $orderData['order_number'];
            $successUrl = $this->getSuccessUrl($paymentReference);
            $cancelUrl = $this->getCancelUrl($paymentReference);
            
            $checkoutSession = $this->payMongo->createCheckoutSession(
                $orderData['amount'],
                $description,
                $successUrl,
                $cancelUrl
            );
            
            // Update payment record with PayMongo details
            $this->customerPayment->updatePaymentStatus(
                $paymentReference,
                'processing',
                [
                    'paymongo_link_id' => $checkoutSession['id'],
                    'checkout_url' => $checkoutSession['attributes']['checkout_url'],
                    'reference_number' => $checkoutSession['attributes']['reference_number']
                ]
            );
            
            return [
                'success' => true,
                'payment_reference' => $paymentReference,
                'checkout_url' => $checkoutSession['attributes']['checkout_url'],
                'paymongo_link_id' => $checkoutSession['id']
            ];
            
        } catch (Exception $e) {
            $this->customerPayment->updatePaymentStatus(
                $paymentReference,
                'failed',
                null,
                'PayMongo error: ' . $e->getMessage()
            );
            throw $e;
        }
    }
    
    /**
     * Process GCash payment via PayMongo
     */
    private function processGCashPayment($paymentReference, $orderData) {
        // Similar to PayMongo but specifically for GCash
        return $this->processPayMongoPayment($paymentReference, $orderData);
    }
    
    /**
     * Process Cash on Delivery
     */
    private function processCODPayment($paymentReference, $orderData) {
        // Update payment status to pending (will be completed on delivery)
        $this->customerPayment->updatePaymentStatus(
            $paymentReference,
            'pending',
            ['cod_notes' => 'Cash on Delivery - Payment due on pickup/delivery']
        );
        
        return [
            'success' => true,
            'payment_reference' => $paymentReference,
            'message' => 'Cash on Delivery order confirmed. Payment due on pickup/delivery.'
        ];
    }
    
    /**
     * Handle payment success callback
     */
    public function handlePaymentSuccess($paymentReference, $gatewayData = null) {
        try {
            // Get payment record
            $payment = $this->customerPayment->getPaymentByReference($paymentReference);
            if (!$payment) {
                throw new Exception('Payment record not found');
            }
            
            // Update payment status to completed
            $this->customerPayment->updatePaymentStatus(
                $paymentReference,
                'completed',
                $gatewayData,
                null
            );
            
            // Update order status
            $this->updateOrderStatus($payment['order_id'], 'paid');
            
            // Send confirmation email/SMS (if implemented)
            $this->sendPaymentConfirmation($payment);
            
            return [
                'success' => true,
                'message' => 'Payment completed successfully',
                'payment' => $payment
            ];
            
        } catch (Exception $e) {
            error_log("Payment success handler error: " . $e->getMessage());
            throw $e;
        }
    }
    
    /**
     * Handle payment failure
     */
    public function handlePaymentFailure($paymentReference, $failureReason = null) {
        try {
            $this->customerPayment->updatePaymentStatus(
                $paymentReference,
                'failed',
                null,
                $failureReason
            );
            
            return [
                'success' => false,
                'message' => 'Payment failed: ' . ($failureReason ?? 'Unknown error')
            ];
            
        } catch (Exception $e) {
            error_log("Payment failure handler error: " . $e->getMessage());
            throw $e;
        }
    }
    
    /**
     * Get customer payment history
     */
    public function getCustomerPaymentHistory($customerId, $limit = 20) {
        return $this->customerPayment->getCustomerPaymentHistory($customerId, $limit);
    }
    
    /**
     * Get payment details by reference
     */
    public function getPaymentDetails($paymentReference) {
        return $this->customerPayment->getPaymentByReference($paymentReference);
    }
    
    /**
     * Process refund
     */
    public function processRefund($paymentReference, $refundAmount, $reason) {
        try {
            $payment = $this->customerPayment->getPaymentByReference($paymentReference);
            if (!$payment) {
                throw new Exception('Payment not found');
            }
            
            if ($payment['payment_status'] !== 'completed') {
                throw new Exception('Can only refund completed payments');
            }
            
            // Process refund with payment gateway if needed
            if ($payment['payment_method'] === 'paymongo') {
                // Implement PayMongo refund API call here
                // For now, just mark as refunded in our system
            }
            
            // Update payment record
            $this->customerPayment->processRefund($paymentReference, $refundAmount, $reason);
            
            // Update order status
            $this->updateOrderStatus($payment['order_id'], 'refunded');
            
            return [
                'success' => true,
                'message' => 'Refund processed successfully',
                'refund_amount' => $refundAmount
            ];
            
        } catch (Exception $e) {
            throw $e;
        }
    }
    
    /**
     * Get payment statistics for admin dashboard
     */
    public function getPaymentStatistics($dateFrom = null, $dateTo = null) {
        return $this->customerPayment->getPaymentStats($dateFrom, $dateTo);
    }
    
    /**
     * Get daily payment summary
     */
    public function getDailyPaymentSummary($date = null) {
        return $this->customerPayment->getDailyPaymentSummary($date);
    }
    
    /**
     * Generate unique payment reference
     */
    private function generatePaymentReference($orderId) {
        return 'PAY-' . $orderId . '-' . time() . '-' . rand(1000, 9999);
    }
    
    /**
     * Get payment success URL
     */
    private function getSuccessUrl($paymentReference) {
        $baseUrl = (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? "https" : "http") . "://" . $_SERVER['HTTP_HOST'];
        return $baseUrl . '/customer/payment-success?ref=' . $paymentReference;
    }
    
    /**
     * Get payment cancel URL
     */
    private function getCancelUrl($paymentReference) {
        $baseUrl = (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? "https" : "http") . "://" . $_SERVER['HTTP_HOST'];
        return $baseUrl . '/customer/payment-failed?ref=' . $paymentReference;
    }
    
    /**
     * Update order status in order_total_cost table
     */
    private function updateOrderStatus($orderId, $status) {
        try {
            $sql = "UPDATE order_total_cost SET 
                    payment_status = ?, 
                    updated_at = NOW() 
                    WHERE id = ?";
            
            $stmt = $this->db->prepare($sql);
            $stmt->execute([$status, $orderId]);
            
        } catch (Exception $e) {
            error_log("Error updating order status: " . $e->getMessage());
        }
    }
    
    /**
     * Send payment confirmation (placeholder for email/SMS service)
     */
    private function sendPaymentConfirmation($payment) {
        // Implement email/SMS confirmation here
        // For now, just log the confirmation
        error_log("Payment confirmation sent for payment: " . $payment['payment_reference']);
    }
    
    /**
     * Webhook handler for PayMongo callbacks
     */
    public function handleWebhook($webhookData) {
        try {
            $eventType = $webhookData['data']['type'] ?? null;
            $attributes = $webhookData['data']['attributes'] ?? [];
            
            if ($eventType === 'link.payment.paid') {
                // Extract payment reference from webhook data
                $linkId = $webhookData['data']['id'] ?? null;
                $paymentReference = $this->findPaymentReferenceByLinkId($linkId);
                
                if ($paymentReference) {
                    return $this->handlePaymentSuccess($paymentReference, $attributes);
                }
            } elseif ($eventType === 'link.payment.failed') {
                $linkId = $webhookData['data']['id'] ?? null;
                $paymentReference = $this->findPaymentReferenceByLinkId($linkId);
                
                if ($paymentReference) {
                    $failureReason = $attributes['failure_reason'] ?? 'Payment failed';
                    return $this->handlePaymentFailure($paymentReference, $failureReason);
                }
            }
            
            return ['success' => false, 'message' => 'Unhandled webhook event'];
            
        } catch (Exception $e) {
            error_log("Webhook handler error: " . $e->getMessage());
            throw $e;
        }
    }
    
    /**
     * Find payment reference by PayMongo link ID
     */
    private function findPaymentReferenceByLinkId($linkId) {
        try {
            $sql = "SELECT payment_reference FROM customer_payments 
                    WHERE JSON_EXTRACT(gateway_response, '$.paymongo_link_id') = ?";
            
            $stmt = $this->db->prepare($sql);
            $stmt->execute([$linkId]);
            $result = $stmt->fetch(PDO::FETCH_ASSOC);
            
            return $result ? $result['payment_reference'] : null;
            
        } catch (Exception $e) {
            error_log("Error finding payment by link ID: " . $e->getMessage());
            return null;
        }
    }
}
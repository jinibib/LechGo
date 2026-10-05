<?php

class CustomerPayment {
    private $db;
    
    public function __construct($database) {
        $this->db = $database;
    }
    
    /**
     * Create a new payment record
     */
    public function createPayment($data) {
        $sql = "INSERT INTO customer_payments (
            payment_reference, order_id, order_number, customer_id, customer_name,
            payment_method, payment_provider, payment_gateway_id, amount, currency,
            payment_status, gateway_response, notes
        ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)";
        
        $stmt = $this->db->prepare($sql);
        return $stmt->execute([
            $data['payment_reference'],
            $data['order_id'],
            $data['order_number'],
            $data['customer_id'],
            $data['customer_name'],
            $data['payment_method'],
            $data['payment_provider'] ?? null,
            $data['payment_gateway_id'] ?? null,
            $data['amount'],
            $data['currency'] ?? 'PHP',
            $data['payment_status'] ?? 'pending',
            isset($data['gateway_response']) ? json_encode($data['gateway_response']) : null,
            $data['notes'] ?? null
        ]);
    }
    
    /**
     * Update payment status
     */
    public function updatePaymentStatus($paymentReference, $status, $gatewayResponse = null, $failureReason = null) {
        $sql = "UPDATE customer_payments SET 
                payment_status = ?, 
                payment_date = CASE WHEN ? = 'completed' THEN NOW() ELSE payment_date END,
                gateway_response = COALESCE(?, gateway_response),
                failure_reason = ?,
                updated_at = NOW()
                WHERE payment_reference = ?";
        
        $stmt = $this->db->prepare($sql);
        return $stmt->execute([
            $status,
            $status,
            $gatewayResponse ? json_encode($gatewayResponse) : null,
            $failureReason,
            $paymentReference
        ]);
    }
    
    /**
     * Get payment by reference
     */
    public function getPaymentByReference($paymentReference) {
        $sql = "SELECT * FROM customer_payments WHERE payment_reference = ?";
        $stmt = $this->db->prepare($sql);
        $stmt->execute([$paymentReference]);
        return $stmt->fetch(PDO::FETCH_ASSOC);
    }
    
    /**
     * Get payment by order ID
     */
    public function getPaymentsByOrderId($orderId) {
        $sql = "SELECT * FROM customer_payments WHERE order_id = ? ORDER BY created_at DESC";
        $stmt = $this->db->prepare($sql);
        $stmt->execute([$orderId]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
    
    /**
     * Get customer payment history
     */
    public function getCustomerPaymentHistory($customerId, $limit = 50) {
        $sql = "SELECT cp.*, otc.delivery_address, otc.delivery_method
                FROM customer_payments cp
                LEFT JOIN order_total_cost otc ON cp.order_id = otc.id
                WHERE cp.customer_id = ?
                ORDER BY cp.created_at DESC
                LIMIT ?";
        
        $stmt = $this->db->prepare($sql);
        $stmt->execute([$customerId, $limit]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
    
    /**
     * Get payment statistics
     */
    public function getPaymentStats($dateFrom = null, $dateTo = null) {
        $whereClause = "";
        $params = [];
        
        if ($dateFrom && $dateTo) {
            $whereClause = "WHERE DATE(created_at) BETWEEN ? AND ?";
            $params = [$dateFrom, $dateTo];
        }
        
        $sql = "SELECT 
                    payment_method,
                    payment_status,
                    COUNT(*) as transaction_count,
                    SUM(amount) as total_amount,
                    AVG(amount) as average_amount
                FROM customer_payments 
                $whereClause
                GROUP BY payment_method, payment_status
                ORDER BY payment_method, payment_status";
        
        $stmt = $this->db->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
    
    /**
     * Process refund
     */
    public function processRefund($paymentReference, $refundAmount, $refundReason) {
        $sql = "UPDATE customer_payments SET 
                payment_status = 'refunded',
                refund_amount = ?,
                refund_date = NOW(),
                refund_reason = ?,
                updated_at = NOW()
                WHERE payment_reference = ?";
        
        $stmt = $this->db->prepare($sql);
        return $stmt->execute([$refundAmount, $refundReason, $paymentReference]);
    }
    
    /**
     * Get failed payments for retry
     */
    public function getFailedPayments($customerId = null) {
        $sql = "SELECT cp.*, otc.order_number, otc.customer_name
                FROM customer_payments cp
                LEFT JOIN order_total_cost otc ON cp.order_id = otc.id
                WHERE cp.payment_status = 'failed'";
        
        $params = [];
        if ($customerId) {
            $sql .= " AND cp.customer_id = ?";
            $params[] = $customerId;
        }
        
        $sql .= " ORDER BY cp.created_at DESC";
        
        $stmt = $this->db->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
    
    /**
     * Get pending payments
     */
    public function getPendingPayments($customerId = null) {
        $sql = "SELECT cp.*, otc.order_number, otc.customer_name
                FROM customer_payments cp
                LEFT JOIN order_total_cost otc ON cp.order_id = otc.id
                WHERE cp.payment_status IN ('pending', 'processing')";
        
        $params = [];
        if ($customerId) {
            $sql .= " AND cp.customer_id = ?";
            $params[] = $customerId;
        }
        
        $sql .= " ORDER BY cp.created_at DESC";
        
        $stmt = $this->db->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
    
    /**
     * Get daily payment summary
     */
    public function getDailyPaymentSummary($date = null) {
        $date = $date ?? date('Y-m-d');
        
        $sql = "SELECT 
                    payment_method,
                    COUNT(*) as transaction_count,
                    SUM(CASE WHEN payment_status = 'completed' THEN amount ELSE 0 END) as completed_amount,
                    SUM(CASE WHEN payment_status = 'pending' THEN amount ELSE 0 END) as pending_amount,
                    SUM(CASE WHEN payment_status = 'failed' THEN amount ELSE 0 END) as failed_amount
                FROM customer_payments 
                WHERE DATE(created_at) = ?
                GROUP BY payment_method
                ORDER BY completed_amount DESC";
        
        $stmt = $this->db->prepare($sql);
        $stmt->execute([$date]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
}
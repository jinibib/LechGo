<?php

class FeedReceipt {
    private $conn;
    public $id;
    public $receipt_number;
    public $feed_order_id;
    public $buyer_id;
    public $supplier_id;
    public $total_amount;
    public $created_at;

    public function __construct($db) {
        $this->conn = $db;
    }

    /**
     * Generate receipt number
     * Format: RCP-YYYYMMDD-XXXXX
     */
    private function generateReceiptNumber() {
        $date = date('Ymd');
        $random = str_pad(rand(1, 99999), 5, '0', STR_PAD_LEFT);
        return "RCP-{$date}-{$random}";
    }

    /**
     * Create receipt from order when supplier accepts
     */
    public function createFromOrder($order_id, $accepted_by_user_id) {
        // Get order details
        $query = "SELECT 
                    lfo.id, lfo.order_number, lfo.total_amount, lfo.payment_method, lfo.created_at,
                    lfo.supplier_id, lfo.livestock_owner_id,
                    us.name as supplier_name,
                    ub.name as buyer_name
                  FROM livestock_feed_orders lfo
                  LEFT JOIN suppliers s ON lfo.supplier_id = s.id
                  LEFT JOIN users us ON s.user_id = us.id
                  LEFT JOIN livestock_owners lo ON lfo.livestock_owner_id = lo.id
                  LEFT JOIN users ub ON lo.user_id = ub.id
                  WHERE lfo.id = ?";
        
        $stmt = $this->conn->prepare($query);
        if (!$stmt) {
            return ['success' => false, 'error' => 'Database error: ' . $this->conn->error];
        }

        $stmt->bind_param('i', $order_id);
        $stmt->execute();
        $result = $stmt->get_result();
        $order = $result->fetch_assoc();
        $stmt->close();

        if (!$order) {
            return ['success' => false, 'error' => 'Order not found'];
        }

        // Check if receipt already exists
        $check_query = "SELECT id, receipt_number FROM receipts_record WHERE feed_order_id = ?";
        $check_stmt = $this->conn->prepare($check_query);
        if ($check_stmt) {
            $check_stmt->bind_param('i', $order_id);
            $check_stmt->execute();
            $check_result = $check_stmt->get_result();
            if ($existing = $check_result->fetch_assoc()) {
                $check_stmt->close();
                return [
                    'success' => true, 
                    'receipt_id' => $existing['id'],
                    'receipt_number' => $existing['receipt_number'],
                    'message' => 'Receipt already exists'
                ];
            }
            $check_stmt->close();
        }

        // Generate unique receipt number
        do {
            $receipt_number = $this->generateReceiptNumber();
            $check = $this->conn->prepare("SELECT id FROM receipts_record WHERE receipt_number = ?");
            $check->bind_param('s', $receipt_number);
            $check->execute();
            $exists = $check->get_result()->num_rows > 0;
            $check->close();
        } while ($exists);

        // Insert receipt record
        $insert_query = "INSERT INTO receipts_record (
            receipt_number, feed_order_id,
            buyer_id, buyer_name,
            supplier_id, supplier_name,
            total_amount, payment_method,
            order_date, confirmed_date, accepted_by
        ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, NOW(), ?)";

        $stmt_insert = $this->conn->prepare($insert_query);
        if (!$stmt_insert) {
            return ['success' => false, 'error' => 'Database error: ' . $this->conn->error];
        }

        $stmt_insert->bind_param(
            'siisisdssi',
            $receipt_number,
            $order_id,
            $order['livestock_owner_id'],
            $order['buyer_name'],
            $order['supplier_id'],
            $order['supplier_name'],
            $order['total_amount'],
            $order['payment_method'],
            $order['created_at'],
            $accepted_by_user_id
        );

        if ($stmt_insert->execute()) {
            $receipt_id = $stmt_insert->insert_id;
            $stmt_insert->close();
            
            return [
                'success' => true,
                'receipt_id' => $receipt_id,
                'receipt_number' => $receipt_number,
                'message' => 'Receipt created successfully'
            ];
        } else {
            $error = $stmt_insert->error;
            $stmt_insert->close();
            return ['success' => false, 'error' => 'Failed to create receipt: ' . $error];
        }
    }

    /**
     * Get receipt by ID
     */
    public function getById($receipt_id) {
        $query = "SELECT * FROM receipts_record WHERE id = ?";
        $stmt = $this->conn->prepare($query);
        
        if (!$stmt) {
            return null;
        }

        $stmt->bind_param('i', $receipt_id);
        $stmt->execute();
        $result = $stmt->get_result();
        $receipt = $result->fetch_assoc();
        $stmt->close();

        return $receipt;
    }

    /**
     * Get receipt by order ID
     */
    public function getByOrderId($order_id) {
        $query = "SELECT * FROM receipts_record WHERE feed_order_id = ?";
        $stmt = $this->conn->prepare($query);
        
        if (!$stmt) {
            return null;
        }

        $stmt->bind_param('i', $order_id);
        $stmt->execute();
        $result = $stmt->get_result();
        $receipt = $result->fetch_assoc();
        $stmt->close();

        return $receipt;
    }

    /**
     * Get receipt by receipt number
     */
    public function getByReceiptNumber($receipt_number) {
        $query = "SELECT * FROM receipts_record WHERE receipt_number = ?";
        $stmt = $this->conn->prepare($query);
        
        if (!$stmt) {
            return null;
        }

        $stmt->bind_param('s', $receipt_number);
        $stmt->execute();
        $result = $stmt->get_result();
        $receipt = $result->fetch_assoc();
        $stmt->close();

        return $receipt;
    }

    /**
     * Get all receipts for a supplier
     */
    public function getBySupplier($supplier_id, $limit = 50, $offset = 0) {
        $query = "SELECT * FROM receipts_record 
                  WHERE supplier_id = ? 
                  ORDER BY created_at DESC 
                  LIMIT ? OFFSET ?";
        
        $stmt = $this->conn->prepare($query);
        if (!$stmt) {
            return [];
        }

        $stmt->bind_param('iii', $supplier_id, $limit, $offset);
        $stmt->execute();
        $result = $stmt->get_result();
        $receipts = $result->fetch_all(MYSQLI_ASSOC);
        $stmt->close();

        return $receipts;
    }

    /**
     * Get all receipts for a buyer (livestock owner)
     */
    public function getByBuyer($buyer_id, $limit = 50, $offset = 0) {
        $query = "SELECT * FROM receipts_record 
                  WHERE buyer_id = ? 
                  ORDER BY created_at DESC 
                  LIMIT ? OFFSET ?";
        
        $stmt = $this->conn->prepare($query);
        if (!$stmt) {
            return [];
        }

        $stmt->bind_param('iii', $buyer_id, $limit, $offset);
        $stmt->execute();
        $result = $stmt->get_result();
        $receipts = $result->fetch_all(MYSQLI_ASSOC);
        $stmt->close();

        return $receipts;
    }
}

<?php

/**
 * FeedOrder Model
 * Handles feed order management for supplier-to-caretaker transactions
 */

class FeedOrder
{
    private $conn;
    private $table = 'feed_orders';
    private $itemsTable = 'feed_order_items';
    private $receiptsTable = 'feed_order_receipts';

    public $id;
    public $supplier_id;
    public $caretaker_id;
    public $order_status;
    public $payment_status;
    public $total_amount;
    public $notes;
    public $payment_method;
    public $payment_reference;
    public $caretaker_response;
    public $created_at;

    public function __construct($db)
    {
        $this->conn = $db;
    }

    /**
     * Create a new feed order
     */
    public function create($supplier_id, $caretaker_id, $items = [], $notes = null)
    {
        // Calculate total from items
        $total = 0;
        foreach ($items as $item) {
            $total += $item['subtotal'];
        }

        $query = "INSERT INTO " . $this->table . " 
                  (supplier_id, caretaker_id, total_amount, notes, order_status, payment_status)
                  VALUES (?, ?, ?, ?, 'pending', 'pending')";
        
        $stmt = $this->conn->prepare($query);
        if (!$stmt) {
            throw new Exception("Prepare error: " . $this->conn->error);
        }

        $stmt->bind_param("iids", $supplier_id, $caretaker_id, $total, $notes);
        
        if ($stmt->execute()) {
            $order_id = $this->conn->insert_id;
            $stmt->close();

            // Add order items
            foreach ($items as $item) {
                $this->addOrderItem($order_id, $item);
            }

            return $order_id;
        } else {
            $stmt->close();
            throw new Exception("Error creating order: " . $this->conn->error);
        }
    }

    /**
     * Add item to order
     */
    private function addOrderItem($order_id, $item)
    {
        $query = "INSERT INTO " . $this->itemsTable . "
                  (feed_order_id, feed_inventory_id, feed_type, quantity_ordered_kg, unit_price, subtotal)
                  VALUES (?, ?, ?, ?, ?, ?)";
        
        $stmt = $this->conn->prepare($query);
        if (!$stmt) {
            throw new Exception("Prepare error: " . $this->conn->error);
        }

        $stmt->bind_param("iisdd", 
            $order_id, 
            $item['feed_inventory_id'], 
            $item['feed_type'], 
            $item['quantity_kg'],
            $item['unit_price']
        );
        
        // Calculate subtotal
        $subtotal = $item['quantity_kg'] * $item['unit_price'];
        $stmt->bind_param("iisdd", 
            $order_id, 
            $item['feed_inventory_id'], 
            $item['feed_type'], 
            $item['quantity_kg'],
            $item['unit_price'],
            $subtotal
        );

        if (!$stmt->execute()) {
            throw new Exception("Error adding order item: " . $this->conn->error);
        }
        $stmt->close();
    }

    /**
     * Get order by ID with all details
     */
    public function getOrderById($order_id)
    {
        $query = "SELECT fo.*, 
                         s.farm_name as supplier_farm, s.contact_number as supplier_contact,
                         su.name as supplier_name, su.email as supplier_email,
                         pc.farm_name as caretaker_farm, pc.contact_number as caretaker_contact,
                         pu.name as caretaker_name, pu.email as caretaker_email
                  FROM " . $this->table . " fo
                  JOIN suppliers s ON fo.supplier_id = s.id
                  JOIN users su ON s.user_id = su.id
                  JOIN pig_caretakers pc ON fo.caretaker_id = pc.id
                  JOIN users pu ON pc.user_id = pu.id
                  WHERE fo.id = ?";
        
        $stmt = $this->conn->prepare($query);
        if (!$stmt) {
            throw new Exception("Prepare error: " . $this->conn->error);
        }

        $stmt->bind_param("i", $order_id);
        $stmt->execute();
        $result = $stmt->get_result();
        
        if ($result->num_rows > 0) {
            $order = $result->fetch_assoc();
            $stmt->close();
            
            // Get order items
            $order['items'] = $this->getOrderItems($order_id);
            return $order;
        }
        
        $stmt->close();
        return null;
    }

    /**
     * Get all items in an order
     */
    public function getOrderItems($order_id)
    {
        $query = "SELECT * FROM " . $this->itemsTable . " WHERE feed_order_id = ? ORDER BY created_at ASC";
        
        $stmt = $this->conn->prepare($query);
        if (!$stmt) {
            throw new Exception("Prepare error: " . $this->conn->error);
        }

        $stmt->bind_param("i", $order_id);
        $stmt->execute();
        $result = $stmt->get_result();
        
        $items = [];
        while ($row = $result->fetch_assoc()) {
            $items[] = $row;
        }
        
        $stmt->close();
        return $items;
    }

    /**
     * Get orders for supplier
     */
    public function getSupplierOrders($supplier_id, $status = null)
    {
        $query = "SELECT fo.*, 
                         pc.farm_name as caretaker_farm, pc.contact_number,
                         pu.name as caretaker_name
                  FROM " . $this->table . " fo
                  JOIN pig_caretakers pc ON fo.caretaker_id = pc.id
                  JOIN users pu ON pc.user_id = pu.id
                  WHERE fo.supplier_id = ?";
        
        if ($status) {
            $query .= " AND fo.order_status = ?";
        }
        
        $query .= " ORDER BY fo.created_at DESC";
        
        $stmt = $this->conn->prepare($query);
        if (!$stmt) {
            throw new Exception("Prepare error: " . $this->conn->error);
        }

        if ($status) {
            $stmt->bind_param("is", $supplier_id, $status);
        } else {
            $stmt->bind_param("i", $supplier_id);
        }
        
        $stmt->execute();
        $result = $stmt->get_result();
        
        $orders = [];
        while ($row = $result->fetch_assoc()) {
            $orders[] = $row;
        }
        
        $stmt->close();
        return $orders;
    }

    /**
     * Get orders for caretaker (orders received from suppliers)
     */
    public function getCaretakerOrders($caretaker_id, $status = null)
    {
        $query = "SELECT fo.*, 
                         s.farm_name as supplier_farm, s.contact_number,
                         su.name as supplier_name
                  FROM " . $this->table . " fo
                  JOIN suppliers s ON fo.supplier_id = s.id
                  JOIN users su ON s.user_id = su.id
                  WHERE fo.caretaker_id = ?";
        
        if ($status) {
            $query .= " AND fo.order_status = ?";
        }
        
        $query .= " ORDER BY fo.created_at DESC";
        
        $stmt = $this->conn->prepare($query);
        if (!$stmt) {
            throw new Exception("Prepare error: " . $this->conn->error);
        }

        if ($status) {
            $stmt->bind_param("is", $caretaker_id, $status);
        } else {
            $stmt->bind_param("i", $caretaker_id);
        }
        
        $stmt->execute();
        $result = $stmt->get_result();
        
        $orders = [];
        while ($row = $result->fetch_assoc()) {
            $orders[] = $row;
        }
        
        $stmt->close();
        return $orders;
    }

    /**
     * Update order status
     */
    public function updateOrderStatus($order_id, $status)
    {
        $query = "UPDATE " . $this->table . " SET order_status = ?, updated_at = NOW() WHERE id = ?";
        
        $stmt = $this->conn->prepare($query);
        if (!$stmt) {
            throw new Exception("Prepare error: " . $this->conn->error);
        }

        $stmt->bind_param("si", $status, $order_id);
        
        if ($stmt->execute()) {
            $stmt->close();
            return true;
        } else {
            $stmt->close();
            throw new Exception("Error updating order status: " . $this->conn->error);
        }
    }

    /**
     * Update payment status
     */
    public function updatePaymentStatus($order_id, $status, $payment_method = null, $reference = null)
    {
        $query = "UPDATE " . $this->table . " 
                  SET payment_status = ?, payment_method = ?, payment_reference = ?, updated_at = NOW() 
                  WHERE id = ?";
        
        $stmt = $this->conn->prepare($query);
        if (!$stmt) {
            throw new Exception("Prepare error: " . $this->conn->error);
        }

        $stmt->bind_param("sssi", $status, $payment_method, $reference, $order_id);
        
        if ($stmt->execute()) {
            $stmt->close();
            return true;
        } else {
            $stmt->close();
            throw new Exception("Error updating payment status: " . $this->conn->error);
        }
    }

    /**
     * Accept/Reject order from caretaker side
     */
    public function respondToOrder($order_id, $response, $caretaker_response_text = null)
    {
        $status = ($response === 'accept') ? 'accepted' : 'rejected';
        
        $query = "UPDATE " . $this->table . " 
                  SET order_status = ?, caretaker_response = ?, caretaker_response_date = NOW(), updated_at = NOW()
                  WHERE id = ?";
        
        $stmt = $this->conn->prepare($query);
        if (!$stmt) {
            throw new Exception("Prepare error: " . $this->conn->error);
        }

        $stmt->bind_param("ssi", $status, $caretaker_response_text, $order_id);
        
        if ($stmt->execute()) {
            $stmt->close();
            return true;
        } else {
            $stmt->close();
            throw new Exception("Error responding to order: " . $this->conn->error);
        }
    }

    /**
     * Generate receipt for completed order
     */
    public function generateReceipt($order_id)
    {
        $order = $this->getOrderById($order_id);
        if (!$order) {
            throw new Exception("Order not found");
        }

        // Generate receipt number
        $receipt_number = "RCP-" . date('Ymd') . "-" . str_pad($order_id, 5, "0", STR_PAD_LEFT);
        
        // Get receipt data
        $receipt_data = [
            'receipt_number' => $receipt_number,
            'order_id' => $order_id,
            'order_date' => $order['created_at'],
            'supplier' => [
                'name' => $order['supplier_name'],
                'farm' => $order['supplier_farm'],
                'email' => $order['supplier_email'],
                'contact' => $order['supplier_contact']
            ],
            'caretaker' => [
                'name' => $order['caretaker_name'],
                'farm' => $order['caretaker_farm'],
                'email' => $order['caretaker_email'],
                'contact' => $order['caretaker_contact']
            ],
            'items' => $order['items'],
            'total_amount' => $order['total_amount'],
            'payment_method' => $order['payment_method'],
            'status' => $order['order_status']
        ];

        $query = "INSERT INTO " . $this->receiptsTable . " 
                  (feed_order_id, receipt_number, receipt_data)
                  VALUES (?, ?, ?)";
        
        $stmt = $this->conn->prepare($query);
        if (!$stmt) {
            throw new Exception("Prepare error: " . $this->conn->error);
        }

        $receipt_data_json = json_encode($receipt_data);
        $stmt->bind_param("iss", $order_id, $receipt_number, $receipt_data_json);
        
        if ($stmt->execute()) {
            $stmt->close();
            return $receipt_number;
        } else {
            $stmt->close();
            throw new Exception("Error generating receipt: " . $this->conn->error);
        }
    }

    /**
     * Get receipt by order ID
     */
    public function getReceiptByOrderId($order_id)
    {
        $query = "SELECT * FROM " . $this->receiptsTable . " WHERE feed_order_id = ?";
        
        $stmt = $this->conn->prepare($query);
        if (!$stmt) {
            throw new Exception("Prepare error: " . $this->conn->error);
        }

        $stmt->bind_param("i", $order_id);
        $stmt->execute();
        $result = $stmt->get_result();
        
        if ($result->num_rows > 0) {
            $receipt = $result->fetch_assoc();
            $receipt['receipt_data'] = json_decode($receipt['receipt_data'], true);
            $stmt->close();
            return $receipt;
        }
        
        $stmt->close();
        return null;
    }

    /**
     * Delete order (only if pending)
     */
    public function deleteOrder($order_id)
    {
        // Check if order is still pending
        $query = "SELECT order_status FROM " . $this->table . " WHERE id = ?";
        $stmt = $this->conn->prepare($query);
        $stmt->bind_param("i", $order_id);
        $stmt->execute();
        $result = $stmt->get_result();
        
        if ($result->num_rows > 0) {
            $row = $result->fetch_assoc();
            if ($row['order_status'] !== 'pending') {
                $stmt->close();
                throw new Exception("Cannot delete non-pending order");
            }
        }
        $stmt->close();

        // Delete order items first
        $delete_items_query = "DELETE FROM " . $this->itemsTable . " WHERE feed_order_id = ?";
        $stmt = $this->conn->prepare($delete_items_query);
        $stmt->bind_param("i", $order_id);
        $stmt->execute();
        $stmt->close();

        // Delete order
        $query = "DELETE FROM " . $this->table . " WHERE id = ? AND order_status = 'pending'";
        $stmt = $this->conn->prepare($query);
        if (!$stmt) {
            throw new Exception("Prepare error: " . $this->conn->error);
        }

        $stmt->bind_param("i", $order_id);
        
        if ($stmt->execute()) {
            $stmt->close();
            return true;
        } else {
            $stmt->close();
            throw new Exception("Error deleting order: " . $this->conn->error);
        }
    }
}

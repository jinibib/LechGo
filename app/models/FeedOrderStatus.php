<?php

/**
 * FeedOrderStatus Model
 * Manages feed order status tracking and history
 */

class FeedOrderStatus {
    private $conn;

    // Status types
    const TYPE_ORDER = 'order';
    const TYPE_PAYMENT = 'payment';
    const TYPE_DELIVERY = 'delivery';

    // Order status values
    const ORDER_PENDING = 'pending';
    const ORDER_CONFIRMED = 'confirmed';
    const ORDER_PROCESSING = 'processing';
    const ORDER_READY_FOR_DELIVERY = 'ready_for_delivery';
    const ORDER_DELIVERED = 'delivered';
    const ORDER_CANCELLED = 'cancelled';

    // Payment status values
    const PAYMENT_UNPAID = 'unpaid';
    const PAYMENT_PAID = 'paid';
    const PAYMENT_FAILED = 'failed';

    // Delivery status values
    const DELIVERY_PENDING = 'pending';
    const DELIVERY_IN_TRANSIT = 'in_transit';
    const DELIVERY_DELIVERED = 'delivered';
    const DELIVERY_FAILED = 'failed';

    public function __construct($conn) {
        $this->conn = $conn;
    }

    /**
     * Add a new status entry
     */
    public function addStatus($feedOrderId, $statusType, $statusValue, $notes = null, $changedBy = null) {
        try {
            $query = "INSERT INTO feed_order_status (feed_order_id, status_type, status_value, notes, changed_by)
                      VALUES (?, ?, ?, ?, ?)";
            
            $stmt = $this->conn->prepare($query);
            if (!$stmt) {
                throw new Exception('Database error: ' . $this->conn->error);
            }

            $stmt->bind_param('isssi', $feedOrderId, $statusType, $statusValue, $notes, $changedBy);
            
            if (!$stmt->execute()) {
                throw new Exception('Error adding status: ' . $stmt->error);
            }

            $statusId = $this->conn->insert_id;
            $stmt->close();

            return $statusId;
        } catch (Exception $e) {
            error_log("FeedOrderStatus::addStatus Error: " . $e->getMessage());
            throw $e;
        }
    }

    /**
     * Get status history for an order
     */
    public function getOrderStatusHistory($feedOrderId, $statusType = null) {
        try {
            if ($statusType) {
                $query = "SELECT fos.*, u.name as changed_by_name
                          FROM feed_order_status fos
                          LEFT JOIN users u ON fos.changed_by = u.id
                          WHERE fos.feed_order_id = ? AND fos.status_type = ?
                          ORDER BY fos.created_at DESC";
                $stmt = $this->conn->prepare($query);
                $stmt->bind_param('is', $feedOrderId, $statusType);
            } else {
                $query = "SELECT fos.*, u.name as changed_by_name
                          FROM feed_order_status fos
                          LEFT JOIN users u ON fos.changed_by = u.id
                          WHERE fos.feed_order_id = ?
                          ORDER BY fos.created_at DESC";
                $stmt = $this->conn->prepare($query);
                $stmt->bind_param('i', $feedOrderId);
            }

            if (!$stmt) {
                throw new Exception('Database error: ' . $this->conn->error);
            }

            $stmt->execute();
            $result = $stmt->get_result();
            $history = $result->fetch_all(MYSQLI_ASSOC);
            $stmt->close();

            return $history;
        } catch (Exception $e) {
            error_log("FeedOrderStatus::getOrderStatusHistory Error: " . $e->getMessage());
            return [];
        }
    }

    /**
     * Get latest status for an order
     */
    public function getLatestStatus($feedOrderId, $statusType) {
        try {
            $query = "SELECT fos.*, u.name as changed_by_name
                      FROM feed_order_status fos
                      LEFT JOIN users u ON fos.changed_by = u.id
                      WHERE fos.feed_order_id = ? AND fos.status_type = ?
                      ORDER BY fos.created_at DESC
                      LIMIT 1";
            
            $stmt = $this->conn->prepare($query);
            if (!$stmt) {
                throw new Exception('Database error: ' . $this->conn->error);
            }

            $stmt->bind_param('is', $feedOrderId, $statusType);
            $stmt->execute();
            $result = $stmt->get_result();
            $status = $result->fetch_assoc();
            $stmt->close();

            return $status;
        } catch (Exception $e) {
            error_log("FeedOrderStatus::getLatestStatus Error: " . $e->getMessage());
            return null;
        }
    }

    /**
     * Update order status and log to history
     */
    public function updateOrderStatus($feedOrderId, $newStatus, $notes = null, $changedBy = null) {
        try {
            // Update main order table
            $query = "UPDATE livestock_feed_orders SET order_status = ?, updated_at = NOW() WHERE id = ?";
            $stmt = $this->conn->prepare($query);
            if (!$stmt) {
                throw new Exception('Database error: ' . $this->conn->error);
            }
            $stmt->bind_param('si', $newStatus, $feedOrderId);
            $stmt->execute();
            $stmt->close();

            // Log to status history
            return $this->addStatus($feedOrderId, self::TYPE_ORDER, $newStatus, $notes, $changedBy);
        } catch (Exception $e) {
            error_log("FeedOrderStatus::updateOrderStatus Error: " . $e->getMessage());
            throw $e;
        }
    }

    /**
     * Update payment status and log to history
     */
    public function updatePaymentStatus($feedOrderId, $newStatus, $notes = null, $changedBy = null) {
        try {
            // Update main order table
            $query = "UPDATE livestock_feed_orders SET payment_status = ?, updated_at = NOW() WHERE id = ?";
            $stmt = $this->conn->prepare($query);
            if (!$stmt) {
                throw new Exception('Database error: ' . $this->conn->error);
            }
            $stmt->bind_param('si', $newStatus, $feedOrderId);
            $stmt->execute();
            $stmt->close();

            // Log to status history
            return $this->addStatus($feedOrderId, self::TYPE_PAYMENT, $newStatus, $notes, $changedBy);
        } catch (Exception $e) {
            error_log("FeedOrderStatus::updatePaymentStatus Error: " . $e->getMessage());
            throw $e;
        }
    }

    /**
     * Update delivery status and log to history
     */
    public function updateDeliveryStatus($feedOrderId, $newStatus, $notes = null, $changedBy = null) {
        try {
            // Update main order table
            $query = "UPDATE livestock_feed_orders SET delivery_status = ?, updated_at = NOW() WHERE id = ?";
            $stmt = $this->conn->prepare($query);
            if (!$stmt) {
                throw new Exception('Database error: ' . $this->conn->error);
            }
            $stmt->bind_param('si', $newStatus, $feedOrderId);
            $stmt->execute();
            $stmt->close();

            // Log to status history
            return $this->addStatus($feedOrderId, self::TYPE_DELIVERY, $newStatus, $notes, $changedBy);
        } catch (Exception $e) {
            error_log("FeedOrderStatus::updateDeliveryStatus Error: " . $e->getMessage());
            throw $e;
        }
    }

    /**
     * Get all status changes for an order (formatted for timeline display)
     */
    public function getOrderTimeline($feedOrderId) {
        try {
            $query = "SELECT fos.*, u.name as changed_by_name
                      FROM feed_order_status fos
                      LEFT JOIN users u ON fos.changed_by = u.id
                      WHERE fos.feed_order_id = ?
                      ORDER BY fos.created_at ASC";
            
            $stmt = $this->conn->prepare($query);
            if (!$stmt) {
                throw new Exception('Database error: ' . $this->conn->error);
            }

            $stmt->bind_param('i', $feedOrderId);
            $stmt->execute();
            $result = $stmt->get_result();
            $timeline = $result->fetch_all(MYSQLI_ASSOC);
            $stmt->close();

            return $timeline;
        } catch (Exception $e) {
            error_log("FeedOrderStatus::getOrderTimeline Error: " . $e->getMessage());
            return [];
        }
    }
}

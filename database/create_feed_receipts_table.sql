-- Run this SQL to create the receipts_record table
-- Simplified table to store essential receipt information from accepted orders

CREATE TABLE IF NOT EXISTS receipts_record (
    id INT AUTO_INCREMENT PRIMARY KEY,
    receipt_number VARCHAR(50) UNIQUE NOT NULL,
    feed_order_id INT NOT NULL,
    
    -- Who bought (Livestock Owner)
    buyer_id INT NOT NULL,
    buyer_name VARCHAR(255) NOT NULL,
    
    -- Who sold (Supplier)
    supplier_id INT NOT NULL,
    supplier_name VARCHAR(255) NOT NULL,
    
    -- Order info
    total_amount DECIMAL(10, 2) NOT NULL,
    payment_method VARCHAR(50),
    
    -- When
    order_date DATETIME NOT NULL,
    confirmed_date DATETIME NOT NULL,
    
    -- Who accepted the order
    accepted_by INT NOT NULL,
    
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    
    -- Indexes
    INDEX idx_receipt_number (receipt_number),
    INDEX idx_feed_order (feed_order_id),
    INDEX idx_buyer (buyer_id),
    INDEX idx_supplier (supplier_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- Add foreign keys
ALTER TABLE receipts_record
    ADD CONSTRAINT fk_receipt_order FOREIGN KEY (feed_order_id) REFERENCES livestock_feed_orders(id) ON DELETE CASCADE,
    ADD CONSTRAINT fk_receipt_buyer FOREIGN KEY (buyer_id) REFERENCES livestock_owners(id) ON DELETE CASCADE,
    ADD CONSTRAINT fk_receipt_supplier FOREIGN KEY (supplier_id) REFERENCES suppliers(id) ON DELETE CASCADE,
    ADD CONSTRAINT fk_receipt_accepted_by FOREIGN KEY (accepted_by) REFERENCES users(id) ON DELETE CASCADE;

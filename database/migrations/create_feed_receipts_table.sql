-- Feed Receipts Table
-- Stores receipt records for all feed orders

CREATE TABLE IF NOT EXISTS feed_receipts (
    id INT AUTO_INCREMENT PRIMARY KEY,
    receipt_number VARCHAR(50) UNIQUE NOT NULL,
    feed_order_id INT NOT NULL,
    
    -- Supplier Information
    supplier_id INT NOT NULL,
    supplier_name VARCHAR(255) NOT NULL,
    supplier_farm VARCHAR(255),
    supplier_contact VARCHAR(20),
    supplier_email VARCHAR(255),
    supplier_address TEXT,
    
    -- Buyer Information
    buyer_id INT NOT NULL,
    buyer_name VARCHAR(255) NOT NULL,
    buyer_farm VARCHAR(255),
    buyer_contact VARCHAR(20),
    buyer_email VARCHAR(255),
    buyer_address TEXT,
    
    -- Order Details
    order_date DATETIME NOT NULL,
    confirmed_date DATETIME NOT NULL,
    payment_method VARCHAR(50),
    payment_status VARCHAR(50),
    
    -- Items (stored as JSON for flexibility)
    items JSON NOT NULL,
    
    -- Amounts
    subtotal DECIMAL(10, 2) NOT NULL,
    tax DECIMAL(10, 2) DEFAULT 0.00,
    delivery_fee DECIMAL(10, 2) DEFAULT 0.00,
    total_amount DECIMAL(10, 2) NOT NULL,
    
    -- Delivery Information
    delivery_address TEXT,
    delivery_notes TEXT,
    
    -- Metadata
    generated_by INT NOT NULL, -- user_id who generated (supplier)
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    
    -- Foreign Keys
    FOREIGN KEY (feed_order_id) REFERENCES livestock_feed_orders(id) ON DELETE CASCADE,
    FOREIGN KEY (supplier_id) REFERENCES feed_suppliers(id) ON DELETE CASCADE,
    FOREIGN KEY (buyer_id) REFERENCES livestock_owners(id) ON DELETE CASCADE,
    FOREIGN KEY (generated_by) REFERENCES users(id) ON DELETE CASCADE,
    
    -- Indexes
    INDEX idx_receipt_number (receipt_number),
    INDEX idx_feed_order (feed_order_id),
    INDEX idx_supplier (supplier_id),
    INDEX idx_buyer (buyer_id),
    INDEX idx_created_at (created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

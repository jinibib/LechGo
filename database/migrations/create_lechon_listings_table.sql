-- Create lechon_listings table for Lechon marketplace
-- Similar structure to hogs_market but tailored for lechon products

CREATE TABLE IF NOT EXISTS lechon_listings (
    id INT AUTO_INCREMENT PRIMARY KEY,
    livestock_owner_id INT NOT NULL,
    listing_type ENUM('WHOLE_LECHON', 'PROMO_PACKAGE') NOT NULL DEFAULT 'WHOLE_LECHON',
    category ENUM('WHOLE_LECHON', 'BOOPIE', 'LAMAN_LOOB', 'PORTION', 'COMBO', 'OTHER') NOT NULL DEFAULT 'WHOLE_LECHON',
    name VARCHAR(255) NOT NULL,
    weight_kg DECIMAL(8, 2) NULL COMMENT 'Weight in kg for whole lechon',
    portion_size VARCHAR(100) NULL COMMENT 'Portion size for portions/packages (e.g., 1/4 Lechon)',
    serving_capacity VARCHAR(100) NULL COMMENT 'Good for how many people (e.g., 50-60 pax)',
    price DECIMAL(10, 2) NOT NULL,
    available_date DATE NOT NULL,
    available_quantity INT NOT NULL DEFAULT 1,
    description LONGTEXT NULL,
    photo_url VARCHAR(500) NULL,
    status ENUM('active', 'inactive', 'removed') NOT NULL DEFAULT 'active',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (livestock_owner_id) REFERENCES livestock_owners(id) ON DELETE CASCADE,
    INDEX idx_owner (livestock_owner_id),
    INDEX idx_status (status),
    INDEX idx_category (category),
    INDEX idx_listing_type (listing_type),
    INDEX idx_available_date (available_date)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================================
-- LECHON ORDERS TABLE
-- Mirrors swine_order_status for the Lechon marketplace tab.
-- The pig order flow remains untouched.
-- ============================================================

CREATE TABLE IF NOT EXISTS `lechon_orders` (
    `id`                  INT AUTO_INCREMENT PRIMARY KEY,
    `order_number`        VARCHAR(100) NOT NULL UNIQUE,
    `customer_id`         INT NOT NULL,
    `livestock_owner_id`  INT NOT NULL,
    `lechon_listing_id`   INT NOT NULL,

    -- Snapshot of listing at time of order (denormalised for history safety)
    `listing_name`        VARCHAR(255) NOT NULL,
    `category`            ENUM('WHOLE_LECHON','WHOLE_PACKAGES','BELLY_BUNDLES','WEEKDAY_COMBOS','EVENT_CATERING','OTHER') NOT NULL DEFAULT 'WHOLE_LECHON',
    `weight_kg`           DECIMAL(8,2) NULL,
    `serving_capacity`    VARCHAR(100) NULL,
    `price`               DECIMAL(10,2) NOT NULL,

    -- Customer inquiry / message
    `inquiry_message`     TEXT NULL,

    -- Order lifecycle (same statuses as swine_order_status)
    `order_status`        ENUM('pending','confirmed','preparing','cost_computed','ready_for_pickup','completed','cancelled') NOT NULL DEFAULT 'pending',
    `payment_status`      ENUM('unpaid','paid','partially_paid','refunded') NOT NULL DEFAULT 'unpaid',
    `payment_method`      VARCHAR(50) NULL,
    `payment_reference`   VARCHAR(255) NULL,
    `seller_feedback`     TEXT NULL,
    `pickup_date`         DATE NULL,

    -- Reservation tracking
    `reserved_by_name`    VARCHAR(200) NULL,
    `reserved_at`         DATETIME NULL,

    -- Cancellation
    `cancelled_at`        DATETIME NULL,
    `cancellation_reason` TEXT NULL,
    `completed_at`        DATETIME NULL,

    `created_at`          TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    `updated_at`          TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,

    INDEX `idx_customer`    (`customer_id`),
    INDEX `idx_owner`       (`livestock_owner_id`),
    INDEX `idx_listing`     (`lechon_listing_id`),
    INDEX `idx_status`      (`order_status`),
    INDEX `idx_pay_status`  (`payment_status`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

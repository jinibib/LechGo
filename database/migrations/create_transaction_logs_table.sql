-- Migration: Create transaction_logs table
-- Run in phpMyAdmin → lechgo_db → SQL tab

CREATE TABLE IF NOT EXISTS `transaction_logs` (
  `id`                 int(11)       NOT NULL AUTO_INCREMENT,
  `order_id`           int(11)       NOT NULL,
  `order_number`       varchar(100)  DEFAULT NULL,
  `livestock_owner_id` int(11)       NOT NULL,
  `supplier_id`        int(11)       NOT NULL,
  `supplier_name`      varchar(150)  NOT NULL,
  `buyer_name`         varchar(150)  NOT NULL,
  `feed_type`          varchar(100)  NOT NULL,
  `product_name`       varchar(150)  NOT NULL,
  `quantity_kg`        decimal(8,2)  NOT NULL DEFAULT 0.00,
  `unit_price`         decimal(10,2) NOT NULL DEFAULT 0.00,
  `subtotal`           decimal(12,2) NOT NULL DEFAULT 0.00,
  `purchase_date`      timestamp     NOT NULL DEFAULT current_timestamp(),
  `payment_status`     varchar(50)   DEFAULT 'pending',
  `order_status`       varchar(50)   DEFAULT 'pending',
  `created_at`         timestamp     NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `idx_tl_order`    (`order_id`),
  KEY `idx_tl_supplier` (`supplier_id`),
  KEY `idx_tl_owner`    (`livestock_owner_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- Backfill existing orders
INSERT IGNORE INTO `transaction_logs`
  (order_id, order_number, livestock_owner_id, supplier_id, supplier_name, buyer_name,
   feed_type, product_name, quantity_kg, unit_price, subtotal, purchase_date, payment_status, order_status)
SELECT
  lfo.id,
  lfo.order_number,
  lfo.livestock_owner_id,
  lfo.supplier_id,
  COALESCE(s.farm_name, su.name, 'Unknown Supplier'),
  COALESCE(ou.name, 'Unknown Buyer'),
  COALESCE(lfoi.feed_type, 'N/A'),
  COALESCE(lfoi.product_name, 'N/A'),
  COALESCE(lfoi.quantity_kg, 0),
  COALESCE(lfoi.unit_price, 0),
  COALESCE(lfoi.subtotal, 0),
  lfo.created_at,
  lfo.payment_status,
  lfo.order_status
FROM livestock_feed_orders lfo
LEFT JOIN livestock_feed_order_items lfoi ON lfoi.feed_order_id = lfo.id
LEFT JOIN suppliers s ON lfo.supplier_id = s.id
LEFT JOIN users su ON s.user_id = su.id
LEFT JOIN livestock_owners lo ON lfo.livestock_owner_id = lo.id
LEFT JOIN users ou ON lo.user_id = ou.id;

-- Fix existing rows that stored personal name instead of business name
UPDATE transaction_logs tl
JOIN suppliers s ON tl.supplier_id = s.id
SET tl.supplier_name = COALESCE(s.farm_name, tl.supplier_name)
WHERE s.farm_name IS NOT NULL AND s.farm_name != '';

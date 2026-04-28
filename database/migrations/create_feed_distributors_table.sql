-- Migration: Add Feed Distributor role support
-- Run this against lechgo_db

-- 1. Add feed_distributor to users role enum
ALTER TABLE `users`
  MODIFY COLUMN `role` ENUM(
    'customer','lechonero','livestock_owner','supplier',
    'pig_caretaker','admin','logistics','feed_distributor'
  ) NOT NULL;

-- 2. Create feed_distributors table
CREATE TABLE IF NOT EXISTS `feed_distributors` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `user_id` int(11) NOT NULL,
  `business_name` varchar(150) NOT NULL,
  `location_id` int(11) DEFAULT NULL,
  `contact_number` varchar(20) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_fd_user` (`user_id`),
  KEY `idx_fd_location` (`location_id`),
  CONSTRAINT `fk_fd_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE ON UPDATE CASCADE,
  CONSTRAINT `fk_fd_location` FOREIGN KEY (`location_id`) REFERENCES `locations` (`location_id`) ON DELETE SET NULL ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- 3. Create feed_distributor_products table (separate from supplier feed_products)
CREATE TABLE IF NOT EXISTS `feed_distributor_products` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `distributor_id` int(11) NOT NULL,
  `product_name` varchar(150) NOT NULL,
  `feed_type` varchar(100) NOT NULL,
  `description` text DEFAULT NULL,
  `unit_price` decimal(10,2) NOT NULL DEFAULT 0.00,
  `quantity_available_kg` decimal(10,2) NOT NULL DEFAULT 0.00,
  `image_url` varchar(255) DEFAULT NULL,
  `is_active` tinyint(1) NOT NULL DEFAULT 1,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `idx_fdp_distributor` (`distributor_id`),
  CONSTRAINT `fk_fdp_distributor` FOREIGN KEY (`distributor_id`) REFERENCES `feed_distributors` (`id`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- 4. Create feed_distributor_orders table
CREATE TABLE IF NOT EXISTS `feed_distributor_orders` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `distributor_id` int(11) NOT NULL,
  `buyer_user_id` int(11) NOT NULL,
  `buyer_name` varchar(150) DEFAULT NULL,
  `order_number` varchar(50) DEFAULT NULL,
  `order_status` enum('pending','confirmed','processing','ready_for_delivery','delivered','cancelled') NOT NULL DEFAULT 'pending',
  `payment_status` enum('unpaid','paid','refunded') NOT NULL DEFAULT 'unpaid',
  `total_amount` decimal(12,2) NOT NULL DEFAULT 0.00,
  `delivery_address` text DEFAULT NULL,
  `notes` text DEFAULT NULL,
  `imported_to_inventory` tinyint(1) NOT NULL DEFAULT 0,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `idx_fdo_distributor` (`distributor_id`),
  KEY `idx_fdo_buyer` (`buyer_user_id`),
  CONSTRAINT `fk_fdo_distributor` FOREIGN KEY (`distributor_id`) REFERENCES `feed_distributors` (`id`) ON DELETE CASCADE ON UPDATE CASCADE,
  CONSTRAINT `fk_fdo_buyer` FOREIGN KEY (`buyer_user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- 5. Create feed_distributor_order_items table
CREATE TABLE IF NOT EXISTS `feed_distributor_order_items` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `order_id` int(11) NOT NULL,
  `product_id` int(11) NOT NULL,
  `product_name` varchar(150) NOT NULL,
  `feed_type` varchar(100) DEFAULT NULL,
  `quantity_kg` decimal(10,2) NOT NULL,
  `unit_price` decimal(10,2) NOT NULL,
  `subtotal` decimal(12,2) NOT NULL,
  PRIMARY KEY (`id`),
  KEY `idx_fdoi_order` (`order_id`),
  KEY `idx_fdoi_product` (`product_id`),
  CONSTRAINT `fk_fdoi_order` FOREIGN KEY (`order_id`) REFERENCES `feed_distributor_orders` (`id`) ON DELETE CASCADE ON UPDATE CASCADE,
  CONSTRAINT `fk_fdoi_product` FOREIGN KEY (`product_id`) REFERENCES `feed_distributor_products` (`id`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- 6. Add imported_to_inventory column if upgrading existing DB
ALTER TABLE `feed_distributor_orders`
  ADD COLUMN IF NOT EXISTS `imported_to_inventory` tinyint(1) NOT NULL DEFAULT 0;

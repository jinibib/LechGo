-- Migration: pig_market_listings
-- Livestock owners can post individual pigs for sale

CREATE TABLE IF NOT EXISTS `pig_market_listings` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `livestock_owner_id` int(11) NOT NULL,
  `pig_detail_id` int(11) NOT NULL,
  `pig_tag_id` varchar(100) NOT NULL,
  `pin_number` varchar(50) NOT NULL,
  `weight_kg` decimal(8,2) NOT NULL,
  `price_per_kg` decimal(10,2) NOT NULL,
  `total_price` decimal(10,2) GENERATED ALWAYS AS (`weight_kg` * `price_per_kg`) STORED,
  `description` text DEFAULT NULL,
  `status` enum('active','sold','removed') NOT NULL DEFAULT 'active',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `livestock_owner_id` (`livestock_owner_id`),
  KEY `pig_detail_id` (`pig_detail_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

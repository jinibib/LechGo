-- Add reservation fields to hogs_market
ALTER TABLE `hogs_market`
  MODIFY COLUMN `status` enum('active','reserved','sold','removed') NOT NULL DEFAULT 'active',
  ADD COLUMN `reserved_by_user_id` int(11) DEFAULT NULL AFTER `status`,
  ADD COLUMN `reserved_by_name` varchar(255) DEFAULT NULL AFTER `reserved_by_user_id`,
  ADD COLUMN `inquiry_message` text DEFAULT NULL AFTER `reserved_by_name`,
  ADD COLUMN `reserved_at` timestamp NULL DEFAULT NULL AFTER `inquiry_message`;

-- Add farm_name and farm_location columns to role_applications table
ALTER TABLE `role_applications` 
ADD COLUMN `farm_name` VARCHAR(255) DEFAULT NULL AFTER `application_type`,
ADD COLUMN `farm_location` TEXT DEFAULT NULL AFTER `farm_name`;

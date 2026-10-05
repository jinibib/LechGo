-- Fix customer_payments table to support both pig and lechon orders
-- Currently it only supports pig orders via order_total_cost foreign key

-- Step 1: Drop the existing foreign key constraint
ALTER TABLE `customer_payments`
DROP FOREIGN KEY `fk_customer_payments_order`;

-- Step 2: Make order_id nullable (for lechon orders that don't have order_total_cost)
ALTER TABLE `customer_payments`
MODIFY COLUMN `order_id` INT NULL;

-- Step 3: Add lechon_order_id column
ALTER TABLE `customer_payments`
ADD COLUMN `lechon_order_id` INT NULL AFTER `order_id`,
ADD COLUMN `order_type` ENUM('pig', 'lechon') NOT NULL DEFAULT 'pig' AFTER `lechon_order_id`;

-- Step 4: Re-add the foreign key for pig orders (but now it's optional)
ALTER TABLE `customer_payments`
ADD CONSTRAINT `fk_customer_payments_pig_order` 
    FOREIGN KEY (`order_id`) 
    REFERENCES `order_total_cost`(`id`) 
    ON DELETE CASCADE;

-- Step 5: Add foreign key for lechon orders
ALTER TABLE `customer_payments`
ADD CONSTRAINT `fk_customer_payments_lechon_order` 
    FOREIGN KEY (`lechon_order_id`) 
    REFERENCES `lechon_orders`(`id`) 
    ON DELETE CASCADE;

-- Step 6: Add indexes for better performance
ALTER TABLE `customer_payments`
ADD INDEX `idx_lechon_order` (`lechon_order_id`),
ADD INDEX `idx_order_type` (`order_type`);

-- Step 7: Update existing records to set order_type
UPDATE `customer_payments`
SET `order_type` = 'pig'
WHERE `order_id` IS NOT NULL AND `order_type` = 'pig';

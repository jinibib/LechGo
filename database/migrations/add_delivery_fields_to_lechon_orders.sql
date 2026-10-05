-- Add delivery method and address columns to lechon_orders table
-- This allows proper tracking of delivery vs pickup orders

ALTER TABLE `lechon_orders`
ADD COLUMN `delivery_method` ENUM('pickup', 'delivery') NULL DEFAULT 'pickup' AFTER `pickup_date`,
ADD COLUMN `delivery_address` TEXT NULL AFTER `delivery_method`,
ADD COLUMN `delivery_notes` TEXT NULL AFTER `delivery_address`;

-- Update existing records to extract delivery info from seller_feedback
UPDATE `lechon_orders`
SET 
    `delivery_method` = CASE
        WHEN `seller_feedback` LIKE 'Delivery to:%' THEN 'delivery'
        WHEN `seller_feedback` LIKE 'Pickup%' THEN 'pickup'
        ELSE 'pickup'
    END,
    `delivery_address` = CASE
        WHEN `seller_feedback` LIKE 'Delivery to:%' THEN 
            TRIM(SUBSTRING_INDEX(SUBSTRING_INDEX(`seller_feedback`, 'Delivery to: ', -1), '.', 1))
        ELSE NULL
    END
WHERE `seller_feedback` IS NOT NULL;

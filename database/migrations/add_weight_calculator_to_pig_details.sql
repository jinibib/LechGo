-- Add weight calculator fields to pig_details table
-- This allows pig caretakers to calculate pig weight using Heart Girth and Body Length

ALTER TABLE pig_details 
ADD COLUMN heart_girth_cm DECIMAL(10,2) NULL COMMENT 'Heart girth measurement in centimeters' AFTER weight_kg,
ADD COLUMN body_length_cm DECIMAL(10,2) NULL COMMENT 'Body length measurement in centimeters' AFTER heart_girth_cm,
ADD COLUMN calculated_weight_kg DECIMAL(10,2) NULL COMMENT 'Auto-calculated weight using formula: (Heart Girth² × Body Length) ÷ 69.3' AFTER body_length_cm,
ADD COLUMN weight_source ENUM('manual', 'calculated') DEFAULT 'manual' COMMENT 'Source of weight: manual entry or calculated' AFTER calculated_weight_kg;

-- Note: Run this migration on your database to enable the weight calculator feature

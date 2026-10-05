-- Ensure inventory_logs table has all required columns
-- Run this to fix missing columns that cause white screen errors

-- First, check if table exists, if not create basic structure
CREATE TABLE IF NOT EXISTS inventory_logs (
    id INT AUTO_INCREMENT PRIMARY KEY,
    livestock_owner_id INT NOT NULL,
    log_date DATE NOT NULL DEFAULT (CURRENT_DATE()),
    batch_name VARCHAR(255) DEFAULT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
);

-- Add OPEX columns if they don't exist
ALTER TABLE inventory_logs 
ADD COLUMN IF NOT EXISTS feed_cost DECIMAL(10,2) DEFAULT 0.00,
ADD COLUMN IF NOT EXISTS biologics_cost DECIMAL(10,2) DEFAULT 0.00,
ADD COLUMN IF NOT EXISTS fuel_cost DECIMAL(10,2) DEFAULT 0.00,
ADD COLUMN IF NOT EXISTS labor_cost DECIMAL(10,2) DEFAULT 0.00,
ADD COLUMN IF NOT EXISTS water_cost DECIMAL(10,2) DEFAULT 0.00,
ADD COLUMN IF NOT EXISTS electricity_cost DECIMAL(10,2) DEFAULT 0.00;

-- Add CAPEX columns if they don't exist
ALTER TABLE inventory_logs 
ADD COLUMN IF NOT EXISTS building_depreciation DECIMAL(10,2) DEFAULT 0.00,
ADD COLUMN IF NOT EXISTS equipment_depreciation DECIMAL(10,2) DEFAULT 0.00,
ADD COLUMN IF NOT EXISTS permits_taxes DECIMAL(10,2) DEFAULT 0.00;

-- Add TOC (Total Opportunity Cost) columns if they don't exist
ALTER TABLE inventory_logs 
ADD COLUMN IF NOT EXISTS capital_opportunity_cost DECIMAL(10,2) DEFAULT 0.00,
ADD COLUMN IF NOT EXISTS land_rent_opportunity DECIMAL(10,2) DEFAULT 0.00;

-- Show final table structure
DESCRIBE inventory_logs;
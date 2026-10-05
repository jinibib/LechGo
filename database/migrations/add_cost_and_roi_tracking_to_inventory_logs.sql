-- Migration: Add Cost & ROI Tracking Columns to inventory_logs
-- This enables livestock cost comparison, variance analysis, and ROI calculations
-- Created: 2026-09-02

-- Add Cost Tracking Columns
ALTER TABLE `inventory_logs`
ADD COLUMN IF NOT EXISTS `current_cost_per_kg` DECIMAL(10, 2) DEFAULT 0.00 COMMENT 'Current batch cost per kg',
ADD COLUMN IF NOT EXISTS `previous_cost_per_kg` DECIMAL(10, 2) DEFAULT 0.00 COMMENT 'Historical baseline cost per kg for comparison',
ADD COLUMN IF NOT EXISTS `cost_variance` DECIMAL(10, 2) DEFAULT 0.00 COMMENT 'Difference: Current Cost - Previous Cost (PHP)',
ADD COLUMN IF NOT EXISTS `cost_variance_percent` DECIMAL(8, 4) DEFAULT 0.00 COMMENT 'Percentage change in cost ((Current - Previous) / Previous * 100)',

-- Add Livestock Metrics
ADD COLUMN IF NOT EXISTS `batch_mortality_rate` DECIMAL(5, 2) DEFAULT 0.00 COMMENT 'Mortality rate percentage for this batch (0-100)',
ADD COLUMN IF NOT EXISTS `finished_pigs_count` INT DEFAULT 0 COMMENT 'Number of pigs that reached market weight',
ADD COLUMN IF NOT EXISTS `average_pig_weight_kg` DECIMAL(10, 2) DEFAULT 0.00 COMMENT 'Average weight per pig at finish (kg)',

-- Add ROI Tracking
ADD COLUMN IF NOT EXISTS `total_revenue` DECIMAL(10, 2) DEFAULT 0.00 COMMENT 'Total revenue from batch (pigs sold × selling price)',
ADD COLUMN IF NOT EXISTS `total_production_cost` DECIMAL(10, 2) DEFAULT 0.00 COMMENT 'Total production cost (TVC + TFC + TOC)',
ADD COLUMN IF NOT EXISTS `gross_profit` DECIMAL(10, 2) DEFAULT 0.00 COMMENT 'Gross Profit = Revenue - Production Cost',
ADD COLUMN IF NOT EXISTS `roi_percent` DECIMAL(8, 4) DEFAULT 0.00 COMMENT 'Return on Investment % = (Gross Profit / Production Cost) × 100';

-- Verify table structure
DESCRIBE inventory_logs;

-- Status check
SELECT 'Migration completed successfully!' as Status,
       COUNT(*) as Total_Inventory_Logs
FROM inventory_logs;

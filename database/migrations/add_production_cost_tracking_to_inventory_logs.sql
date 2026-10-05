-- Migration: Add Production Cost Tracking Columns to inventory_logs
-- This enables CAPEX/OPEX breakdown and production cost analysis
-- Created: 2026-08-14

ALTER TABLE `inventory_logs`
-- OPEX (Operating Expenses) - Variable Costs per Production Cycle
ADD COLUMN `feed_cost_detail` DECIMAL(10, 2) DEFAULT 0.00 COMMENT 'Detailed feed cost breakdown',
ADD COLUMN `biologics_cost` DECIMAL(10, 2) DEFAULT 0.00 COMMENT 'Vaccines, medicines, vitamins',
ADD COLUMN `fuel_cost` DECIMAL(10, 2) DEFAULT 0.00 COMMENT 'Transportation and fuel expenses',
ADD COLUMN `caretaker_labor_cost` DECIMAL(10, 2) DEFAULT 0.00 COMMENT 'Caretaker/farm worker wages',

-- CAPEX (Capital Expenditures) - Fixed Costs
ADD COLUMN `building_depreciation` DECIMAL(10, 2) DEFAULT 0.00 COMMENT 'Depreciation of buildings/structures',
ADD COLUMN `equipment_depreciation` DECIMAL(10, 2) DEFAULT 0.00 COMMENT 'Depreciation of equipment/tools',
ADD COLUMN `permits_taxes` DECIMAL(10, 2) DEFAULT 0.00 COMMENT 'Business permits and taxes',

-- Opportunity Costs
ADD COLUMN `capital_opportunity_cost` DECIMAL(10, 2) DEFAULT 0.00 COMMENT 'Foregone interest on capital invested',
ADD COLUMN `land_rent_opportunity` DECIMAL(10, 2) DEFAULT 0.00 COMMENT 'Foregone land rent value',

-- Production Cost Calculations
ADD COLUMN `total_variable_costs_tvc` DECIMAL(10, 2) DEFAULT 0.00 COMMENT 'TVC = Feed + Biologics + Fuel + Labor',
ADD COLUMN `total_fixed_costs_tfc` DECIMAL(10, 2) DEFAULT 0.00 COMMENT 'TFC = Building + Equipment depreciation + Permits/Taxes',
ADD COLUMN `total_opportunity_costs_toc` DECIMAL(10, 2) DEFAULT 0.00 COMMENT 'TOC = Capital + Land opportunity costs',
ADD COLUMN `total_production_cost` DECIMAL(10, 2) DEFAULT 0.00 COMMENT 'Total = TVC + TFC + TOC',

-- Unit Economics
ADD COLUMN `total_live_weight_tw` DECIMAL(10, 2) DEFAULT 0.00 COMMENT 'Total weight of pigs produced (kg)',
ADD COLUMN `average_production_cost_apc` DECIMAL(10, 2) DEFAULT 0.00 COMMENT 'APC = Total Production Cost / Total Weight (PHP/kg)',

-- Cost Comparison
ADD COLUMN `current_cost_per_kg` DECIMAL(10, 2) DEFAULT 0.00 COMMENT 'Current batch cost per kg',
ADD COLUMN `previous_cost_per_kg` DECIMAL(10, 2) DEFAULT 0.00 COMMENT 'Historical baseline cost per kg',
ADD COLUMN `cost_variance` DECIMAL(10, 2) DEFAULT 0.00 COMMENT 'Difference: Current - Previous cost',
ADD COLUMN `cost_variance_percent` DECIMAL(5, 2) DEFAULT 0.00 COMMENT 'Percentage change in cost',

-- Metadata
ADD COLUMN `batch_mortality_rate` DECIMAL(5, 2) DEFAULT 0.00 COMMENT 'Mortality rate percentage for this batch',
ADD COLUMN `finished_pigs_count` INT DEFAULT 0 COMMENT 'Number of pigs that reached market weight',
ADD COLUMN `average_pig_weight_kg` DECIMAL(10, 2) DEFAULT 0.00 COMMENT 'Average weight per pig at finish';

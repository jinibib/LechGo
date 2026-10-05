-- =====================================================
-- LechGO Budget Planner - Database Installation
-- =====================================================
-- Run this file in phpMyAdmin or MySQL CLI
-- This will create the budget_planner table
-- =====================================================

-- Step 1: Create the budget_planner table
CREATE TABLE IF NOT EXISTS budget_planner (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    
    -- Basic Info
    holiday_event VARCHAR(255) NOT NULL COMMENT 'Event name (e.g., Noche Buena, New Year)',
    planned_budget DECIMAL(10,2) NOT NULL COMMENT 'Total planned budget in PHP',
    family_members INT NOT NULL COMMENT 'Number of family members',
    
    -- Calculated Totals
    total_planned_cost DECIMAL(10,2) DEFAULT 0 COMMENT 'Sum of all planned items',
    holiday_afc DECIMAL(10,2) DEFAULT 0 COMMENT 'Holiday Actual Food Consumption - total actual spending',
    overspending_percent DECIMAL(10,2) DEFAULT 0 COMMENT 'Percentage over/under budget',
    
    -- Status
    status ENUM('planning', 'completed') DEFAULT 'planning' COMMENT 'planning = in progress, completed = finished',
    
    -- Items stored as JSON
    planned_items JSON COMMENT 'Array of planned food items with price and qty',
    actual_items JSON COMMENT 'Array of actual expenses with amounts',
    
    -- Timestamps
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    
    -- Foreign Key
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    
    -- Indexes for performance
    INDEX idx_user_id (user_id),
    INDEX idx_status (status),
    INDEX idx_created_at (created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Step 2: Verify table created successfully
SELECT 'Table created successfully!' as Status;

-- Step 3: Show table structure
DESCRIBE budget_planner;

-- Step 4: Sample data for testing (OPTIONAL - Remove if not needed)
-- This creates a sample budget entry for user_id = 1
-- Make sure to change user_id to an actual user in your system
/*
INSERT INTO budget_planner 
(user_id, holiday_event, planned_budget, family_members, 
 total_planned_cost, holiday_afc, overspending_percent, 
 status, planned_items, actual_items)
VALUES
(1, 'Noche Buena 2024', 12000.00, 5,
 8750.00, 7000.00, -41.67,
 'completed',
 '[
    {"name": "Lechon", "category": "Lechon", "price": 5000, "qty": 1},
    {"name": "Fruit Salad", "category": "Dessert", "price": 500, "qty": 1},
    {"name": "Spaghetti", "category": "Other", "price": 800, "qty": 2},
    {"name": "Drinks", "category": "Drinks", "price": 1250, "qty": 1}
 ]',
 '[
    {"name": "Lechon", "category": "Lechon", "amount": 5000},
    {"name": "Fruit Salad", "category": "Dessert", "amount": 450},
    {"name": "Spaghetti", "category": "Other", "amount": 1200},
    {"name": "Drinks", "category": "Drinks", "amount": 350}
 ]'
);
*/

-- Step 5: Test query - Get all budget records
-- This should return empty if no data, or show sample data if inserted
SELECT 
    id,
    user_id,
    holiday_event,
    planned_budget,
    holiday_afc,
    overspending_percent,
    status,
    created_at
FROM budget_planner
ORDER BY created_at DESC;

-- =====================================================
-- Installation Complete!
-- =====================================================
-- Next Steps:
-- 1. Upload the modified files to your server
-- 2. Test the Budget Planner in the app
-- 3. Create a budget and verify it saves to database
-- =====================================================

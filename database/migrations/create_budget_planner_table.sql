-- Budget Planner Table
-- Stores holiday food budget planning records for customers

CREATE TABLE IF NOT EXISTS budget_planner (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    
    -- Basic Info
    holiday_event VARCHAR(255) NOT NULL,
    planned_budget DECIMAL(10,2) NOT NULL,
    family_members INT NOT NULL,
    
    -- Totals
    total_planned_cost DECIMAL(10,2) DEFAULT 0,
    holiday_afc DECIMAL(10,2) DEFAULT 0,
    overspending_percent DECIMAL(10,2) DEFAULT 0,
    
    -- Status
    status ENUM('planning', 'completed') DEFAULT 'planning',
    
    -- Items stored as JSON
    planned_items JSON,
    actual_items JSON,
    
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    INDEX idx_user_id (user_id),
    INDEX idx_status (status),
    INDEX idx_created_at (created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

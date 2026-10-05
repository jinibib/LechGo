-- =====================================================
-- LechGO Market Intelligence - Database Tables
-- =====================================================
-- Stores market trends, pinned trends, calendar items, and notes
-- Run this file in phpMyAdmin or MySQL CLI
-- =====================================================

-- Step 1: Create the market_trends table
-- Stores fetched market information about pigs, pork, lechon, etc.
CREATE TABLE IF NOT EXISTS market_trends (
    id INT AUTO_INCREMENT PRIMARY KEY,
    
    -- Trend Information
    title VARCHAR(500) NOT NULL COMMENT 'Trend title/headline',
    description TEXT COMMENT 'Short description of the trend',
    summary TEXT COMMENT 'AI-generated summary (2-3 sentences)',
    
    -- Categorization
    category ENUM(
        'Pig Farming',
        'Pork Market',
        'Lechon',
        'Pricing',
        'Agriculture',
        'Food Trends',
        'Industry News'
    ) DEFAULT 'Industry News' COMMENT 'Trend category',
    
    trend_type VARCHAR(100) COMMENT 'Price Increase, Price Decrease, Supply Change, etc.',
    impact_level ENUM('High', 'Moderate', 'Low') DEFAULT 'Low' COMMENT 'Business impact level',
    
    -- Business Insight
    business_insight TEXT COMMENT 'How this affects lechon/pork business',
    suggested_action TEXT COMMENT 'Business-oriented suggestion',
    
    -- Source Information
    source VARCHAR(255) COMMENT 'News source name',
    source_url VARCHAR(500) COMMENT 'URL to original article',
    image_url VARCHAR(500) COMMENT 'Thumbnail image URL',
    
    -- Timestamp
    published_at DATETIME COMMENT 'Publication date of the trend',
    is_demo BOOLEAN DEFAULT FALSE COMMENT 'TRUE if this is demo data',
    
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    
    -- Indexes for performance
    INDEX idx_category (category),
    INDEX idx_impact_level (impact_level),
    INDEX idx_published_at (published_at),
    INDEX idx_created_at (created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Step 2: Create the pinned_trends table
-- Stores trends saved/pinned by users
CREATE TABLE IF NOT EXISTS pinned_trends (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL COMMENT 'User who pinned the trend',
    trend_id INT NOT NULL COMMENT 'Trend being pinned',
    
    pinned_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    FOREIGN KEY (trend_id) REFERENCES market_trends(id) ON DELETE CASCADE,
    
    -- Prevent duplicate pins
    UNIQUE KEY unique_pin (user_id, trend_id),
    INDEX idx_user_id (user_id),
    INDEX idx_pinned_at (pinned_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Step 3: Create the calendar_items table
-- Stores calendar events linked to pinned trends
CREATE TABLE IF NOT EXISTS calendar_items (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL COMMENT 'User who created the calendar item',
    trend_id INT COMMENT 'Associated trend (optional)',
    
    -- Calendar Information
    calendar_date DATE NOT NULL COMMENT 'Date of the calendar item',
    note TEXT COMMENT 'Business note for this date',
    
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    FOREIGN KEY (trend_id) REFERENCES market_trends(id) ON DELETE SET NULL,
    
    INDEX idx_user_id (user_id),
    INDEX idx_calendar_date (calendar_date),
    INDEX idx_created_at (created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Step 4: Create the market_notes table
-- Stores standalone notes for market intelligence tracking
CREATE TABLE IF NOT EXISTS market_notes (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL COMMENT 'User who created the note',
    
    -- Note Information
    title VARCHAR(255) NOT NULL COMMENT 'Note title',
    content TEXT NOT NULL COMMENT 'Note content',
    note_date DATE NOT NULL COMMENT 'Date associated with the note',
    
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    
    INDEX idx_user_id (user_id),
    INDEX idx_note_date (note_date),
    INDEX idx_created_at (created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- =====================================================
-- Verification and Sample Data
-- =====================================================

-- Verify tables created successfully
SELECT 'Tables created successfully!' as Status;

-- Show table structures
DESCRIBE market_trends;
DESCRIBE pinned_trends;
DESCRIBE calendar_items;
DESCRIBE market_notes;

-- =====================================================
-- Sample Demo Data for Market Intelligence
-- =====================================================
-- These are sample trends to display when API is unavailable

INSERT IGNORE INTO market_trends 
(title, description, summary, category, trend_type, impact_level, business_insight, suggested_action, source, source_url, image_url, published_at, is_demo)
VALUES
(
    'Pork Prices Rise Amid Supply Concerns',
    'Global pork prices show upward movement due to reduced supply in major markets',
    'Pork prices are increasing across major markets due to supply chain disruptions. This could impact lechon production costs and pricing strategy.',
    'Pork Market',
    'Price Increase',
    'High',
    'Rising pork prices will directly increase production costs for lechon businesses. Consider reviewing supplier contracts and potentially adjusting menu pricing.',
    'Monitor supplier prices weekly. Review current lechon pricing to maintain margin.',
    'DEMO DATA - Market Intelligence Feed',
    'https://lechgo.local',
    'https://via.placeholder.com/400x200?text=Pork+Market',
    NOW(),
    TRUE
),
(
    'New Lechon Flavor Trend in Filipino Food Culture',
    'Filipino consumers show increased preference for flavored lechon variants',
    'Market research indicates growing consumer interest in specialty lechon flavors. This presents a business opportunity for differentiation.',
    'Lechon',
    'Consumer Preference',
    'Moderate',
    'Offering unique flavored variants could increase customer demand and justify premium pricing. Early movers may capture market share.',
    'Survey customers about preferred flavors. Test 2-3 variants and measure sales response.',
    'DEMO DATA - Market Intelligence Feed',
    'https://lechgo.local',
    'https://via.placeholder.com/400x200?text=Lechon+Trends',
    NOW() - INTERVAL 2 DAY,
    TRUE
),
(
    'Feed Price Volatility Expected This Quarter',
    'Agricultural experts predict feed price fluctuations due to commodity market changes',
    'Feed costs are expected to experience volatility. Timing feed purchases strategically could optimize costs.',
    'Pig Farming',
    'Price Change',
    'High',
    'Feed costs directly impact pig production expenses. Strategic purchasing and supply chain optimization are critical.',
    'Establish relationships with multiple feed suppliers. Consider bulk purchasing during price dips.',
    'DEMO DATA - Market Intelligence Feed',
    'https://lechgo.local',
    'https://via.placeholder.com/400x200?text=Feed+Prices',
    NOW() - INTERVAL 5 DAY,
    TRUE
),
(
    'Growing Demand for Sustainable Farming Practices',
    'Consumers increasingly prefer products from farms with eco-friendly practices',
    'Sustainable farming is becoming a market differentiator. Implementing green practices could improve brand value.',
    'Agriculture',
    'Market Opportunity',
    'Moderate',
    'Marketing sustainable practices could attract premium-price customers and improve brand reputation.',
    'Document current sustainable practices. Consider certifications or eco-friendly marketing.',
    'DEMO DATA - Market Intelligence Feed',
    'https://lechgo.local',
    'https://via.placeholder.com/400x200?text=Sustainable+Farming',
    NOW() - INTERVAL 7 DAY,
    TRUE
),
(
    'Food Safety Regulations Update for Livestock Producers',
    'New compliance requirements announced for pig farming and meat processing',
    'Regulatory changes require updated practices. Ensure compliance to avoid penalties.',
    'Industry News',
    'Regulatory Change',
    'High',
    'Regulatory compliance is mandatory. Non-compliance could result in fines or operational restrictions.',
    'Review new regulations in detail. Audit current practices for compliance gaps.',
    'DEMO DATA - Market Intelligence Feed',
    'https://lechgo.local',
    'https://via.placeholder.com/400x200?text=Food+Safety',
    NOW() - INTERVAL 10 DAY,
    TRUE
);

-- =====================================================
-- Installation Complete!
-- =====================================================
-- Next Steps:
-- 1. Run this migration in phpMyAdmin or MySQL CLI
-- 2. Verify all tables are created successfully
-- 3. Test Market Intelligence feature in the LechGO app
-- =====================================================

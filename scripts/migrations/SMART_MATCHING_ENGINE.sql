-- ============================================================
-- SMART MATCHING ENGINE - Database Migration
-- Multiverse-Class Matching Algorithm Support
-- ============================================================

-- ============================================================
-- 1. MATCH PREFERENCES TABLE
-- Stores user preferences for matching (distance, categories, notifications)
-- ============================================================
CREATE TABLE IF NOT EXISTS match_preferences (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    tenant_id INT NOT NULL,

    -- Distance Settings
    max_distance_km INT DEFAULT 25 COMMENT 'Maximum distance for matches in km',

    -- Score Settings
    min_match_score INT DEFAULT 50 COMMENT 'Minimum score (0-100) to show as match',

    -- Notification Settings
    notification_frequency ENUM('instant', 'daily', 'weekly', 'off') DEFAULT 'daily',
    notify_hot_matches TINYINT(1) DEFAULT 1 COMMENT 'Instant notify for hot matches (>80%)',
    notify_mutual_matches TINYINT(1) DEFAULT 1 COMMENT 'Instant notify for mutual matches',

    -- Category Filters
    categories JSON DEFAULT NULL COMMENT 'Array of category IDs to match, null = all',

    -- Availability (for future use)
    availability JSON DEFAULT NULL COMMENT 'Available days/times for exchanges',

    -- Metadata
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,

    UNIQUE KEY user_tenant (user_id, tenant_id),
    INDEX idx_tenant (tenant_id),

    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    FOREIGN KEY (tenant_id) REFERENCES tenants(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================================
-- 2. MATCH CACHE TABLE
-- Caches calculated matches for performance and notification tracking
-- ============================================================
CREATE TABLE IF NOT EXISTS match_cache (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    listing_id INT NOT NULL,
    tenant_id INT NOT NULL,

    -- Match Data
    match_score DECIMAL(5,2) NOT NULL COMMENT 'Score 0-100',
    distance_km DECIMAL(8,2) DEFAULT NULL,
    match_type ENUM('one_way', 'potential', 'mutual', 'cold_start') DEFAULT 'one_way',
    match_reasons JSON DEFAULT NULL COMMENT 'Array of reason strings',

    -- Status Tracking
    status ENUM('new', 'viewed', 'contacted', 'saved', 'dismissed') DEFAULT 'new',

    -- Notification Tracking
    notified_at TIMESTAMP NULL DEFAULT NULL,
    notification_type VARCHAR(20) DEFAULT NULL COMMENT 'instant, daily, weekly',

    -- Metadata
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    expires_at TIMESTAMP NULL DEFAULT NULL,

    -- Indexes for fast lookup
    UNIQUE KEY unique_user_listing (user_id, listing_id, tenant_id),
    INDEX idx_user_score (user_id, match_score DESC),
    INDEX idx_tenant_status (tenant_id, status),
    INDEX idx_new_matches (tenant_id, status, created_at DESC),
    INDEX idx_hot_matches (tenant_id, match_score DESC, distance_km ASC),

    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    FOREIGN KEY (listing_id) REFERENCES listings(id) ON DELETE CASCADE,
    FOREIGN KEY (tenant_id) REFERENCES tenants(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================================
-- 3. MATCH HISTORY TABLE
-- Tracks match interactions for ML improvements
-- ============================================================
CREATE TABLE IF NOT EXISTS match_history (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    listing_id INT NOT NULL,
    tenant_id INT NOT NULL,

    -- Match at time of interaction
    match_score DECIMAL(5,2) NOT NULL,
    distance_km DECIMAL(8,2) DEFAULT NULL,

    -- User Action
    action ENUM('viewed', 'contacted', 'saved', 'dismissed', 'completed') NOT NULL,

    -- Outcome (for learning)
    resulted_in_transaction TINYINT(1) DEFAULT 0,
    transaction_id INT DEFAULT NULL,

    -- Metadata
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,

    INDEX idx_user (user_id),
    INDEX idx_listing (listing_id),
    INDEX idx_tenant_action (tenant_id, action),
    INDEX idx_outcomes (tenant_id, resulted_in_transaction),

    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    FOREIGN KEY (listing_id) REFERENCES listings(id) ON DELETE CASCADE,
    FOREIGN KEY (tenant_id) REFERENCES tenants(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================================
-- 4. ADD COORDINATES TO USERS TABLE (if not exists)
-- ============================================================
-- Check and add latitude column
SET @sql = (SELECT IF(
    (SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS
     WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'users' AND COLUMN_NAME = 'latitude') = 0,
    'ALTER TABLE users ADD COLUMN latitude DECIMAL(10, 8) DEFAULT NULL AFTER location',
    'SELECT 1'
));
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

-- Check and add longitude column
SET @sql = (SELECT IF(
    (SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS
     WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'users' AND COLUMN_NAME = 'longitude') = 0,
    'ALTER TABLE users ADD COLUMN longitude DECIMAL(11, 8) DEFAULT NULL AFTER latitude',
    'SELECT 1'
));
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

-- Add index for geo queries on users
SET @sql = (SELECT IF(
    (SELECT COUNT(*) FROM INFORMATION_SCHEMA.STATISTICS
     WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'users' AND INDEX_NAME = 'idx_user_coords') = 0,
    'ALTER TABLE users ADD INDEX idx_user_coords (latitude, longitude)',
    'SELECT 1'
));
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

-- ============================================================
-- 5. ADD COORDINATES TO LISTINGS TABLE (if not exists)
-- ============================================================
-- Check and add latitude column
SET @sql = (SELECT IF(
    (SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS
     WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'listings' AND COLUMN_NAME = 'latitude') = 0,
    'ALTER TABLE listings ADD COLUMN latitude DECIMAL(10, 8) DEFAULT NULL',
    'SELECT 1'
));
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

-- Check and add longitude column
SET @sql = (SELECT IF(
    (SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS
     WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'listings' AND COLUMN_NAME = 'longitude') = 0,
    'ALTER TABLE listings ADD COLUMN longitude DECIMAL(11, 8) DEFAULT NULL',
    'SELECT 1'
));
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

-- Add index for geo queries on listings
SET @sql = (SELECT IF(
    (SELECT COUNT(*) FROM INFORMATION_SCHEMA.STATISTICS
     WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'listings' AND INDEX_NAME = 'idx_listing_coords') = 0,
    'ALTER TABLE listings ADD INDEX idx_listing_coords (latitude, longitude)',
    'SELECT 1'
));
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

-- ============================================================
-- 6. ADD SKILLS COLUMN TO USERS (if not exists)
-- For skill-based matching
-- ============================================================
SET @sql = (SELECT IF(
    (SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS
     WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'users' AND COLUMN_NAME = 'skills') = 0,
    'ALTER TABLE users ADD COLUMN skills TEXT DEFAULT NULL COMMENT "Comma-separated skills"',
    'SELECT 1'
));
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

-- ============================================================
-- 7. ADD SMART MATCHING CONFIG TO TENANTS
-- Store algorithm configuration per tenant
-- ============================================================
-- The configuration is stored in the existing tenants.configuration JSON column
-- Example structure:
-- {
--   "algorithms": {
--     "smart_matching": {
--       "enabled": true,
--       "max_distance_km": 50,
--       "min_match_score": 40,
--       "hot_match_threshold": 80,
--       "weights": {
--         "category": 0.25,
--         "skill": 0.20,
--         "proximity": 0.25,
--         "freshness": 0.10,
--         "reciprocity": 0.15,
--         "quality": 0.05
--       }
--     }
--   }
-- }

-- ============================================================
-- 8. CREATE VIEW FOR EASY MATCH QUERIES
-- ============================================================
CREATE OR REPLACE VIEW v_active_listings_with_coords AS
SELECT
    l.id,
    l.user_id,
    l.tenant_id,
    l.title,
    l.description,
    l.type,
    l.category_id,
    l.image_url,
    l.status,
    l.created_at,
    COALESCE(l.latitude, u.latitude) as latitude,
    COALESCE(l.longitude, u.longitude) as longitude,
    u.first_name,
    u.last_name,
    u.avatar_url,
    u.location as author_location,
    c.name as category_name,
    c.color as category_color
FROM listings l
JOIN users u ON l.user_id = u.id
LEFT JOIN categories c ON l.category_id = c.id
WHERE l.status = 'active';

-- ============================================================
-- DONE
-- ============================================================
-- Run this migration with: mysql -u username -p database < SMART_MATCHING_ENGINE.sql

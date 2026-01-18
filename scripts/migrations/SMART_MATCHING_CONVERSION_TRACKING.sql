-- ============================================================
-- SMART MATCHING ENGINE - Conversion Tracking Migration
-- Adds match attribution to transactions for conversion analytics
-- ============================================================

-- ============================================================
-- 1. ADD SOURCE_MATCH_ID TO TRANSACTIONS TABLE
-- Links transactions back to the match that initiated them
-- ============================================================
SET @sql = (SELECT IF(
    (SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS
     WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'transactions' AND COLUMN_NAME = 'source_match_id') = 0,
    'ALTER TABLE transactions ADD COLUMN source_match_id INT DEFAULT NULL COMMENT "Match history ID that led to this transaction"',
    'SELECT 1'
));
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

-- Add index for match attribution lookups
SET @sql = (SELECT IF(
    (SELECT COUNT(*) FROM INFORMATION_SCHEMA.STATISTICS
     WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'transactions' AND INDEX_NAME = 'idx_source_match') = 0,
    'ALTER TABLE transactions ADD INDEX idx_source_match (source_match_id)',
    'SELECT 1'
));
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

-- ============================================================
-- 2. ADD CONVERSION_TIME TO MATCH_HISTORY
-- Tracks when a match converted to a transaction
-- ============================================================
SET @sql = (SELECT IF(
    (SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS
     WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'match_history' AND COLUMN_NAME = 'conversion_time') = 0,
    'ALTER TABLE match_history ADD COLUMN conversion_time DATETIME DEFAULT NULL COMMENT "When match resulted in transaction"',
    'SELECT 1'
));
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

-- ============================================================
-- 3. CREATE USER_CATEGORY_AFFINITY TABLE
-- Tracks learned category preferences for ML feedback loop
-- ============================================================
CREATE TABLE IF NOT EXISTS user_category_affinity (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    tenant_id INT NOT NULL,
    category_id INT NOT NULL,

    -- Affinity metrics
    view_count INT DEFAULT 0 COMMENT 'Times viewed listings in this category',
    save_count INT DEFAULT 0 COMMENT 'Times saved listings in this category',
    contact_count INT DEFAULT 0 COMMENT 'Times contacted in this category',
    transaction_count INT DEFAULT 0 COMMENT 'Transactions in this category',
    dismiss_count INT DEFAULT 0 COMMENT 'Times dismissed listings in this category',

    -- Calculated affinity score (0-100)
    affinity_score DECIMAL(5,2) DEFAULT 50.00,

    -- Metadata
    last_interaction TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,

    UNIQUE KEY unique_user_category (user_id, tenant_id, category_id),
    INDEX idx_user_affinity (user_id, affinity_score DESC),
    INDEX idx_tenant (tenant_id),

    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    FOREIGN KEY (tenant_id) REFERENCES tenants(id) ON DELETE CASCADE,
    FOREIGN KEY (category_id) REFERENCES categories(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================================
-- 4. CREATE USER_DISTANCE_PREFERENCE TABLE
-- Tracks learned distance preferences for ML feedback loop
-- ============================================================
CREATE TABLE IF NOT EXISTS user_distance_preference (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    tenant_id INT NOT NULL,

    -- Distance buckets interaction counts
    interactions_0_2km INT DEFAULT 0,
    interactions_2_5km INT DEFAULT 0,
    interactions_5_15km INT DEFAULT 0,
    interactions_15_50km INT DEFAULT 0,
    interactions_50plus_km INT DEFAULT 0,

    -- Learned preference
    learned_max_distance_km DECIMAL(8,2) DEFAULT NULL COMMENT 'Calculated from actual behavior',
    stated_max_distance_km INT DEFAULT 25 COMMENT 'User stated preference',

    -- Metadata
    last_updated TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,

    UNIQUE KEY unique_user_tenant (user_id, tenant_id),

    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    FOREIGN KEY (tenant_id) REFERENCES tenants(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================================
-- 5. CREATE USER_BLOCKS TABLE
-- For blocking users from matches
-- ============================================================
CREATE TABLE IF NOT EXISTS user_blocks (
    id INT AUTO_INCREMENT PRIMARY KEY,
    blocker_user_id INT NOT NULL,
    blocked_user_id INT NOT NULL,
    tenant_id INT NOT NULL,
    reason VARCHAR(255) DEFAULT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,

    UNIQUE KEY unique_block (blocker_user_id, blocked_user_id, tenant_id),
    INDEX idx_blocker (blocker_user_id, tenant_id),
    INDEX idx_blocked (blocked_user_id, tenant_id),

    FOREIGN KEY (blocker_user_id) REFERENCES users(id) ON DELETE CASCADE,
    FOREIGN KEY (blocked_user_id) REFERENCES users(id) ON DELETE CASCADE,
    FOREIGN KEY (tenant_id) REFERENCES tenants(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================================
-- DONE
-- ============================================================
-- Run this migration with: mysql -u username -p database < SMART_MATCHING_CONVERSION_TRACKING.sql

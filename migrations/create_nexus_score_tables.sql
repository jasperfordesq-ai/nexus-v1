-- ============================================================================
-- NEXUS SCORE SYSTEM - Database Migration
-- ============================================================================
-- Created: January 12, 2026
-- Purpose: Set up database tables and columns for the Nexus Score System
--
-- This migration ensures all required tables exist for the scoring system:
-- 1. Adds transaction_type column to transactions (optional, for better tracking)
-- 2. Creates nexus_score_cache table (for performance optimization)
-- 3. Ensures all required tables exist (post_likes, user_badges, etc.)
-- ============================================================================

SET SQL_MODE='ALLOW_INVALID_DATES';

-- ============================================================================
-- 1. ADD TRANSACTION_TYPE TO TRANSACTIONS TABLE (OPTIONAL)
-- ============================================================================
-- This allows filtering volunteer hours from regular time credit exchanges
-- If you don't add this, the system will count all transactions as volunteer hours

DROP PROCEDURE IF EXISTS add_transaction_type;
DELIMITER //
CREATE PROCEDURE add_transaction_type()
BEGIN
    -- Check if transaction_type column exists
    IF NOT EXISTS (
        SELECT 1 FROM INFORMATION_SCHEMA.COLUMNS
        WHERE TABLE_SCHEMA = DATABASE()
        AND TABLE_NAME = 'transactions'
        AND COLUMN_NAME = 'transaction_type'
    ) THEN
        -- Add transaction_type column
        ALTER TABLE transactions
        ADD COLUMN transaction_type ENUM('exchange', 'volunteer', 'donation', 'other')
        DEFAULT 'exchange'
        COMMENT 'Type of transaction for scoring purposes'
        AFTER status;

        -- Add index for performance
        ALTER TABLE transactions ADD INDEX idx_transaction_type (transaction_type);
    END IF;
END //
DELIMITER ;
CALL add_transaction_type();
DROP PROCEDURE IF EXISTS add_transaction_type;

-- ============================================================================
-- 2. CREATE NEXUS_SCORE_CACHE TABLE (PERFORMANCE OPTIMIZATION)
-- ============================================================================
-- Caches calculated scores to avoid recalculating on every page load

CREATE TABLE IF NOT EXISTS nexus_score_cache (
    id INT AUTO_INCREMENT PRIMARY KEY,
    tenant_id INT NOT NULL,
    user_id INT NOT NULL,
    total_score DECIMAL(6,2) NOT NULL COMMENT 'Total score out of 1000',
    engagement_score DECIMAL(6,2) NOT NULL COMMENT 'Community Engagement score (250 max)',
    quality_score DECIMAL(6,2) NOT NULL COMMENT 'Contribution Quality score (200 max)',
    volunteer_score DECIMAL(6,2) NOT NULL COMMENT 'Volunteer Hours score (200 max)',
    activity_score DECIMAL(6,2) NOT NULL COMMENT 'Platform Activity score (150 max)',
    badge_score DECIMAL(6,2) NOT NULL COMMENT 'Badges & Achievements score (100 max)',
    impact_score DECIMAL(6,2) NOT NULL COMMENT 'Social Impact score (100 max)',
    percentile INT NOT NULL COMMENT 'User percentile rank (0-100)',
    tier VARCHAR(50) NOT NULL COMMENT 'User tier (Novice, Beginner, etc.)',
    calculated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,

    UNIQUE KEY unique_user_score (tenant_id, user_id),
    INDEX idx_total_score (total_score DESC),
    INDEX idx_tenant (tenant_id),
    INDEX idx_calculated (calculated_at),

    FOREIGN KEY (tenant_id) REFERENCES tenants(id) ON DELETE CASCADE,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
COMMENT='Cached Nexus Score calculations for performance';

-- ============================================================================
-- 3. ENSURE POST_LIKES TABLE EXISTS (Already in fix_database_errors migration)
-- ============================================================================
-- Required for Social Impact score calculation

CREATE TABLE IF NOT EXISTS post_likes (
    id INT AUTO_INCREMENT PRIMARY KEY,
    tenant_id INT NOT NULL,
    post_id INT NOT NULL COMMENT 'ID of the feed_posts entry',
    user_id INT NOT NULL COMMENT 'User who liked the post',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY unique_like (tenant_id, post_id, user_id),
    INDEX idx_post (post_id),
    INDEX idx_user (user_id),
    INDEX idx_tenant (tenant_id),
    INDEX idx_created (created_at),
    FOREIGN KEY (tenant_id) REFERENCES tenants(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
COMMENT='Track likes on feed posts for gamification';

-- ============================================================================
-- 4. ADD IS_SHOWCASED TO USER_BADGES (if not exists)
-- ============================================================================
-- Allows users to feature up to 3 badges on their profile

DROP PROCEDURE IF EXISTS add_badge_showcase;
DELIMITER //
CREATE PROCEDURE add_badge_showcase()
BEGIN
    -- Check if is_showcased column exists
    IF NOT EXISTS (
        SELECT 1 FROM INFORMATION_SCHEMA.COLUMNS
        WHERE TABLE_SCHEMA = DATABASE()
        AND TABLE_NAME = 'user_badges'
        AND COLUMN_NAME = 'is_showcased'
    ) THEN
        -- Add is_showcased column
        ALTER TABLE user_badges
        ADD COLUMN is_showcased TINYINT(1) DEFAULT 0
        COMMENT 'Whether badge is featured on profile'
        AFTER icon;

        -- Add showcase_order column
        ALTER TABLE user_badges
        ADD COLUMN showcase_order INT NULL
        COMMENT 'Order of showcased badges (1-3)'
        AFTER is_showcased;

        -- Add index
        ALTER TABLE user_badges ADD INDEX idx_showcased (user_id, is_showcased, showcase_order);
    END IF;
END //
DELIMITER ;
CALL add_badge_showcase();
DROP PROCEDURE IF EXISTS add_badge_showcase;

-- ============================================================================
-- 5. CREATE NEXUS_SCORE_HISTORY TABLE (Optional - Track Changes Over Time)
-- ============================================================================
-- Tracks score changes for trend analysis

CREATE TABLE IF NOT EXISTS nexus_score_history (
    id INT AUTO_INCREMENT PRIMARY KEY,
    tenant_id INT NOT NULL,
    user_id INT NOT NULL,
    total_score DECIMAL(6,2) NOT NULL,
    tier VARCHAR(50) NOT NULL,
    snapshot_date DATE NOT NULL,

    UNIQUE KEY unique_user_snapshot (tenant_id, user_id, snapshot_date),
    INDEX idx_user (user_id, snapshot_date),
    INDEX idx_tenant (tenant_id),

    FOREIGN KEY (tenant_id) REFERENCES tenants(id) ON DELETE CASCADE,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
COMMENT='Historical Nexus Score snapshots for trend analysis';

-- ============================================================================
-- 6. CREATE NEXUS_SCORE_MILESTONES TABLE (Optional - Track Achievements)
-- ============================================================================
-- Records when users reach score milestones

CREATE TABLE IF NOT EXISTS nexus_score_milestones (
    id INT AUTO_INCREMENT PRIMARY KEY,
    tenant_id INT NOT NULL,
    user_id INT NOT NULL,
    milestone_type ENUM('score_100', 'score_200', 'score_300', 'score_400', 'score_500',
                        'score_600', 'score_700', 'score_800', 'score_900',
                        'tier_beginner', 'tier_intermediate', 'tier_advanced',
                        'tier_expert', 'tier_elite', 'tier_legendary') NOT NULL,
    milestone_name VARCHAR(255) NOT NULL,
    score_at_milestone DECIMAL(6,2) NOT NULL,
    achieved_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,

    UNIQUE KEY unique_user_milestone (tenant_id, user_id, milestone_type),
    INDEX idx_user (user_id, achieved_at),
    INDEX idx_tenant (tenant_id),

    FOREIGN KEY (tenant_id) REFERENCES tenants(id) ON DELETE CASCADE,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
COMMENT='User milestone achievements for Nexus Score';

-- ============================================================================
-- 7. VERIFY REQUIRED TABLES EXIST (MySQL/MariaDB Compatible)
-- ============================================================================

-- Check transactions table exists
DROP PROCEDURE IF EXISTS check_transactions_table;
DELIMITER //
CREATE PROCEDURE check_transactions_table()
BEGIN
    DECLARE table_count INT;
    SELECT COUNT(*) INTO table_count
    FROM INFORMATION_SCHEMA.TABLES
    WHERE TABLE_SCHEMA = DATABASE()
    AND TABLE_NAME = 'transactions';

    IF table_count = 0 THEN
        SIGNAL SQLSTATE '45000'
        SET MESSAGE_TEXT = 'ERROR: transactions table does not exist. Please run main schema migration first.';
    END IF;
END //
DELIMITER ;
CALL check_transactions_table();
DROP PROCEDURE IF EXISTS check_transactions_table;

-- Check reviews table exists
DROP PROCEDURE IF EXISTS check_reviews_table;
DELIMITER //
CREATE PROCEDURE check_reviews_table()
BEGIN
    DECLARE table_count INT;
    SELECT COUNT(*) INTO table_count
    FROM INFORMATION_SCHEMA.TABLES
    WHERE TABLE_SCHEMA = DATABASE()
    AND TABLE_NAME = 'reviews';

    IF table_count = 0 THEN
        SIGNAL SQLSTATE '45000'
        SET MESSAGE_TEXT = 'ERROR: reviews table does not exist. Please run main schema migration first.';
    END IF;
END //
DELIMITER ;
CALL check_reviews_table();
DROP PROCEDURE IF EXISTS check_reviews_table;

-- Check user_badges table exists
DROP PROCEDURE IF EXISTS check_user_badges_table;
DELIMITER //
CREATE PROCEDURE check_user_badges_table()
BEGIN
    DECLARE table_count INT;
    SELECT COUNT(*) INTO table_count
    FROM INFORMATION_SCHEMA.TABLES
    WHERE TABLE_SCHEMA = DATABASE()
    AND TABLE_NAME = 'user_badges';

    IF table_count = 0 THEN
        SIGNAL SQLSTATE '45000'
        SET MESSAGE_TEXT = 'ERROR: user_badges table does not exist. Please run main schema migration first.';
    END IF;
END //
DELIMITER ;
CALL check_user_badges_table();
DROP PROCEDURE IF EXISTS check_user_badges_table;

-- ============================================================================
-- SUCCESS MESSAGE
-- ============================================================================
SELECT 'Nexus Score System migration completed successfully!' AS Status;
SELECT 'Tables created/verified:' AS Info;
SELECT '  - nexus_score_cache (performance)' AS Table1;
SELECT '  - nexus_score_history (trends)' AS Table2;
SELECT '  - nexus_score_milestones (achievements)' AS Table3;
SELECT '  - post_likes (verified/created)' AS Table4;
SELECT '  - user_badges (is_showcased added)' AS Table5;
SELECT '  - transactions (transaction_type added - OPTIONAL)' AS Table6;
SELECT '' AS Blank;
SELECT 'IMPORTANT: The transaction_type column is OPTIONAL.' AS Note1;
SELECT 'If you skip it, all transactions count as volunteer hours.' AS Note2;
SELECT 'To use it, update your transaction creation code to set the type.' AS Note3;

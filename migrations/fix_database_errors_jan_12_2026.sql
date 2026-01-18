-- ============================================================================
-- FIX DATABASE ERRORS - January 12, 2026
-- ============================================================================
-- This migration fixes critical database issues found in error logs:
-- 1. Missing giver_id column in transactions table
-- 2. Missing org_alert_settings table
-- 3. Missing post_likes table
-- 4. Missing user_blocks table (causing SmartMatchingEngine errors)
-- ============================================================================

SET SQL_MODE='ALLOW_INVALID_DATES';

-- ============================================================================
-- 1. FIX TRANSACTIONS TABLE - Add giver_id column
-- ============================================================================
-- The SmartMatchingEngine queries reference giver_id but it doesn't exist
-- giver_id appears to be an alias for sender_id based on the query context

DROP PROCEDURE IF EXISTS add_transaction_giver_id;
DELIMITER //
CREATE PROCEDURE add_transaction_giver_id()
BEGIN
    -- Check if giver_id column exists
    IF NOT EXISTS (
        SELECT 1 FROM INFORMATION_SCHEMA.COLUMNS
        WHERE TABLE_SCHEMA = DATABASE()
        AND TABLE_NAME = 'transactions'
        AND COLUMN_NAME = 'giver_id'
    ) THEN
        -- Add giver_id as an alias/copy of sender_id for backwards compatibility
        ALTER TABLE transactions ADD COLUMN giver_id INT NULL AFTER sender_id;

        -- Copy existing sender_id values to giver_id
        UPDATE transactions SET giver_id = sender_id WHERE giver_id IS NULL;

        -- Add index for performance
        ALTER TABLE transactions ADD INDEX idx_giver_id (giver_id);

        -- Add foreign key if users table exists
        ALTER TABLE transactions
        ADD CONSTRAINT fk_transactions_giver
        FOREIGN KEY (giver_id) REFERENCES users(id) ON DELETE CASCADE;
    END IF;
END //
DELIMITER ;
CALL add_transaction_giver_id();
DROP PROCEDURE IF EXISTS add_transaction_giver_id;

-- ============================================================================
-- 2. CREATE ORG_ALERT_SETTINGS TABLE
-- ============================================================================
-- Required by BalanceAlertService for organization wallet balance alerts

CREATE TABLE IF NOT EXISTS org_alert_settings (
    id INT AUTO_INCREMENT PRIMARY KEY,
    tenant_id INT NOT NULL,
    organization_id INT NOT NULL,
    low_balance_threshold DECIMAL(10,2) DEFAULT 50.00 COMMENT 'Balance threshold to trigger low balance alert',
    critical_balance_threshold DECIMAL(10,2) DEFAULT 10.00 COMMENT 'Balance threshold to trigger critical alert',
    alerts_enabled TINYINT(1) DEFAULT 1 COMMENT 'Enable/disable alerts for this organization',
    notification_emails TEXT NULL COMMENT 'Comma-separated list of additional emails to notify',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NULL ON UPDATE CURRENT_TIMESTAMP,
    UNIQUE KEY unique_org_alerts (tenant_id, organization_id),
    INDEX idx_tenant (tenant_id),
    INDEX idx_organization (organization_id),
    FOREIGN KEY (tenant_id) REFERENCES tenants(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
COMMENT='Organization wallet balance alert settings';

-- ============================================================================
-- 3. CREATE POST_LIKES TABLE
-- ============================================================================
-- Required by GamificationService for tracking likes received

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
    FOREIGN KEY (tenant_id) REFERENCES tenants(id) ON DELETE CASCADE,
    FOREIGN KEY (post_id) REFERENCES feed_posts(id) ON DELETE CASCADE,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
COMMENT='Track likes on feed posts for gamification';

-- ============================================================================
-- 4. CREATE ORG_BALANCE_ALERTS TABLE (companion table)
-- ============================================================================
-- Tracks when balance alerts were sent to prevent spam

CREATE TABLE IF NOT EXISTS org_balance_alerts (
    id INT AUTO_INCREMENT PRIMARY KEY,
    tenant_id INT NOT NULL,
    organization_id INT NOT NULL,
    alert_type ENUM('low', 'critical') NOT NULL,
    balance_at_alert DECIMAL(10,2) NULL COMMENT 'Balance when alert was triggered',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_org_alerts (tenant_id, organization_id, created_at),
    INDEX idx_alert_type (alert_type),
    FOREIGN KEY (tenant_id) REFERENCES tenants(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
COMMENT='Log of balance alerts sent to organizations';

-- ============================================================================
-- 5. CREATE USER_BLOCKS TABLE
-- ============================================================================
-- Required by SmartMatchingEngine to filter blocked users from recommendations
-- Error: "Unknown column 'blocker_user_id' in 'where clause'"

CREATE TABLE IF NOT EXISTS user_blocks (
    id INT AUTO_INCREMENT PRIMARY KEY,
    blocker_user_id INT NOT NULL COMMENT 'User who is blocking',
    blocked_user_id INT NOT NULL COMMENT 'User who is being blocked',
    tenant_id INT NOT NULL,
    reason VARCHAR(255) DEFAULT NULL COMMENT 'Optional reason for blocking',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,

    UNIQUE KEY unique_block (blocker_user_id, blocked_user_id, tenant_id),
    INDEX idx_blocker (blocker_user_id, tenant_id),
    INDEX idx_blocked (blocked_user_id, tenant_id),
    INDEX idx_tenant (tenant_id),

    FOREIGN KEY (blocker_user_id) REFERENCES users(id) ON DELETE CASCADE,
    FOREIGN KEY (blocked_user_id) REFERENCES users(id) ON DELETE CASCADE,
    FOREIGN KEY (tenant_id) REFERENCES tenants(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
COMMENT='User blocking for SmartMatchingEngine filtering';

-- ============================================================================
-- SUCCESS MESSAGE
-- ============================================================================
SELECT 'Migration completed successfully!' AS Status;
SELECT 'Fixed: transactions.giver_id column' AS Fix1;
SELECT 'Created: org_alert_settings table' AS Fix2;
SELECT 'Created: post_likes table' AS Fix3;
SELECT 'Created: org_balance_alerts table' AS Fix4;
SELECT 'Created: user_blocks table' AS Fix5;

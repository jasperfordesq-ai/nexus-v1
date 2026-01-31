-- ============================================================================
-- CONSOLIDATED SCHEMA SYNC MIGRATION
-- ============================================================================
-- Date: January 31, 2026
-- Purpose: Sync production database with local development schema
--
-- Missing tables:
--   - nexus_scores (alias for nexus_score_cache)
--   - nexus_score_components (not used, skip)
--   - community_ranks (CommunityRank system)
--   - deliverability_events (email tracking)
--   - group_recommendation_scores (not needed - using cache table)
--   - federation_reviews (not a table - reviews table extended)
--
-- Missing columns:
--   - federation_external_partners.last_message_at
--
-- This migration is IDEMPOTENT - safe to run multiple times
-- ============================================================================

SET SQL_MODE='ALLOW_INVALID_DATES';

-- ============================================================================
-- 1. NEXUS SCORE TABLES
-- ============================================================================
-- The nexus_score_cache table already exists, but nexus_scores may be expected
-- as an alias or separate table. Creating it if missing.

CREATE TABLE IF NOT EXISTS nexus_scores (
    id INT AUTO_INCREMENT PRIMARY KEY,
    tenant_id INT NOT NULL,
    user_id INT NOT NULL,
    total_score DECIMAL(6,2) NOT NULL DEFAULT 0 COMMENT 'Total score out of 1000',
    engagement_score DECIMAL(6,2) NOT NULL DEFAULT 0 COMMENT 'Community Engagement score (250 max)',
    quality_score DECIMAL(6,2) NOT NULL DEFAULT 0 COMMENT 'Contribution Quality score (200 max)',
    volunteer_score DECIMAL(6,2) NOT NULL DEFAULT 0 COMMENT 'Volunteer Hours score (200 max)',
    activity_score DECIMAL(6,2) NOT NULL DEFAULT 0 COMMENT 'Platform Activity score (150 max)',
    badge_score DECIMAL(6,2) NOT NULL DEFAULT 0 COMMENT 'Badges & Achievements score (100 max)',
    impact_score DECIMAL(6,2) NOT NULL DEFAULT 0 COMMENT 'Social Impact score (100 max)',
    percentile INT NOT NULL DEFAULT 0 COMMENT 'User percentile rank (0-100)',
    tier VARCHAR(50) NOT NULL DEFAULT 'Novice' COMMENT 'User tier (Novice, Beginner, etc.)',
    calculated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,

    UNIQUE KEY unique_user_score (tenant_id, user_id),
    INDEX idx_total_score (total_score DESC),
    INDEX idx_tenant (tenant_id),
    INDEX idx_calculated (calculated_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
COMMENT='Main Nexus Score table for user scoring';

-- ============================================================================
-- 2. COMMUNITY RANKS TABLE
-- ============================================================================
-- Stores the configuration for the CommunityRank algorithm per tenant

CREATE TABLE IF NOT EXISTS community_ranks (
    id INT AUTO_INCREMENT PRIMARY KEY,
    tenant_id INT NOT NULL,
    user_id INT NOT NULL,
    rank_score DECIMAL(10,4) NOT NULL DEFAULT 0,
    activity_score DECIMAL(10,4) NOT NULL DEFAULT 0,
    contribution_score DECIMAL(10,4) NOT NULL DEFAULT 0,
    reputation_score DECIMAL(10,4) NOT NULL DEFAULT 0,
    connectivity_score DECIMAL(10,4) NOT NULL DEFAULT 0,
    proximity_score DECIMAL(10,4) NOT NULL DEFAULT 0,
    rank_position INT NULL,
    tier VARCHAR(50) DEFAULT 'Bronze',
    calculated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,

    UNIQUE KEY unique_user_rank (tenant_id, user_id),
    INDEX idx_tenant (tenant_id),
    INDEX idx_rank_score (rank_score DESC),
    INDEX idx_position (tenant_id, rank_position)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
COMMENT='Community rank scores for users';

-- ============================================================================
-- 3. DELIVERABILITY EVENTS TABLE
-- ============================================================================
-- Tracks email delivery events (bounces, opens, clicks, etc.)

CREATE TABLE IF NOT EXISTS deliverability_events (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    tenant_id INT NOT NULL,
    email_type VARCHAR(100) NOT NULL COMMENT 'newsletter, transactional, digest, etc.',
    email_id INT NULL COMMENT 'Reference to newsletter or email record',
    recipient_email VARCHAR(255) NOT NULL,
    recipient_user_id INT NULL,
    event_type ENUM('sent', 'delivered', 'bounced', 'complained', 'opened', 'clicked', 'unsubscribed') NOT NULL,
    event_data JSON NULL COMMENT 'Additional event metadata',
    bounce_type VARCHAR(50) NULL COMMENT 'hard, soft, etc.',
    bounce_reason TEXT NULL,
    ip_address VARCHAR(45) NULL,
    user_agent TEXT NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,

    INDEX idx_tenant (tenant_id),
    INDEX idx_recipient (recipient_email),
    INDEX idx_event_type (event_type),
    INDEX idx_email (email_type, email_id),
    INDEX idx_created (created_at),
    INDEX idx_tenant_event (tenant_id, event_type, created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
COMMENT='Email deliverability tracking events';

-- ============================================================================
-- 4. ADD MISSING COLUMN TO FEDERATION_EXTERNAL_PARTNERS
-- ============================================================================
-- Add last_message_at column for tracking messaging activity

-- Check if column exists and add if not
SET @column_exists = (
    SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS
    WHERE TABLE_SCHEMA = DATABASE()
    AND TABLE_NAME = 'federation_external_partners'
    AND COLUMN_NAME = 'last_message_at'
);

SET @sql = IF(@column_exists = 0,
    'ALTER TABLE federation_external_partners ADD COLUMN last_message_at DATETIME NULL AFTER last_sync_at',
    'SELECT "Column last_message_at already exists"'
);
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

-- Add index for last_message_at if column was added
SET @index_exists = (
    SELECT COUNT(*) FROM INFORMATION_SCHEMA.STATISTICS
    WHERE TABLE_SCHEMA = DATABASE()
    AND TABLE_NAME = 'federation_external_partners'
    AND INDEX_NAME = 'idx_last_message'
);

SET @sql = IF(@index_exists = 0 AND @column_exists = 0,
    'ALTER TABLE federation_external_partners ADD INDEX idx_last_message (last_message_at)',
    'SELECT "Index idx_last_message already exists or column not added"'
);
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

-- ============================================================================
-- 5. ENSURE COMMUNITYRANK_SETTINGS HAS DEFAULT DATA
-- ============================================================================
-- Insert default settings for existing tenants if not present

INSERT INTO communityrank_settings (tenant_id, is_enabled, activity_weight, contribution_weight, reputation_weight, connectivity_weight, proximity_weight)
SELECT t.id, 1, 0.25, 0.25, 0.20, 0.20, 0.10
FROM tenants t
WHERE NOT EXISTS (
    SELECT 1 FROM communityrank_settings cs WHERE cs.tenant_id = t.id
)
ON DUPLICATE KEY UPDATE tenant_id = tenant_id;

-- ============================================================================
-- VERIFICATION QUERIES (commented out - run manually to verify)
-- ============================================================================
-- SHOW TABLES LIKE 'nexus_scores';
-- SHOW TABLES LIKE 'community_ranks';
-- SHOW TABLES LIKE 'deliverability_events';
-- SHOW COLUMNS FROM federation_external_partners LIKE 'last_message_at';

-- ============================================================================
-- MIGRATION COMPLETE
-- ============================================================================
SELECT 'Consolidated schema sync migration completed successfully!' AS Status;
SELECT 'Created/verified: nexus_scores, community_ranks, deliverability_events' AS Tables;
SELECT 'Added column: federation_external_partners.last_message_at' AS Columns;

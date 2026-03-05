-- ============================================================================
-- PRODUCTION ERROR FIXES - JANUARY 13, 2026 (DIRECT EXECUTION VERSION)
-- ============================================================================
-- This comprehensive migration fixes all database errors identified from
-- production logs dated 2026-01-11
--
-- IMPORTANT: This version contains all SQL directly (no SOURCE commands)
-- Execute this file through phpMyAdmin or mysql command line
--
-- ERRORS FIXED:
-- 1. Missing table: volunteering_organizations
-- 2. Missing table: cron_jobs
-- 3. Missing table: tenant_settings
-- 4. Missing table: group_audit_log
-- 5. Missing table: group_recommendation_interactions
-- 6. Missing table: layout_ab_tests (and related tables)
-- 7. Missing column: users.is_verified
-- 8. Missing column: group_members.created_at
-- ============================================================================

-- Set SQL mode for safety
SET SQL_MODE = 'NO_AUTO_VALUE_ON_ZERO';
SET time_zone = '+00:00';

-- ============================================================================
-- SECTION 1: TENANT SETTINGS TABLE
-- ============================================================================

CREATE TABLE IF NOT EXISTS `tenant_settings` (
    `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    `tenant_id` INT UNSIGNED NOT NULL DEFAULT 1,
    `setting_key` VARCHAR(255) NOT NULL,
    `setting_value` TEXT NULL,
    `setting_type` ENUM('string', 'boolean', 'integer', 'float', 'json', 'array') DEFAULT 'string',
    `description` TEXT NULL,
    `is_encrypted` TINYINT(1) NOT NULL DEFAULT 0,
    `created_at` TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at` TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    `created_by` INT UNSIGNED NULL,
    `updated_by` INT UNSIGNED NULL,
    PRIMARY KEY (`id`),
    UNIQUE KEY `unique_tenant_setting` (`tenant_id`, `setting_key`),
    KEY `idx_tenant_id` (`tenant_id`),
    KEY `idx_setting_key` (`setting_key`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Insert default feature flags
INSERT IGNORE INTO `tenant_settings` (`tenant_id`, `setting_key`, `setting_value`, `setting_type`, `description`) VALUES
(1, 'feature.timebanking', '1', 'boolean', 'Enable timebanking functionality'),
(1, 'feature.listings', '1', 'boolean', 'Enable listings/marketplace functionality'),
(1, 'feature.messaging', '1', 'boolean', 'Enable direct messaging between users'),
(1, 'feature.groups', '1', 'boolean', 'Enable groups/communities'),
(1, 'feature.gamification', '1', 'boolean', 'Enable gamification'),
(1, 'feature.ai_chat', '1', 'boolean', 'Enable AI chat assistant');

-- ============================================================================
-- SECTION 2: VOLUNTEERING ORGANIZATIONS TABLE
-- ============================================================================

CREATE TABLE IF NOT EXISTS `volunteering_organizations` (
    `id` int(11) NOT NULL AUTO_INCREMENT,
    `tenant_id` int(11) NOT NULL,
    `name` varchar(255) NOT NULL,
    `description` text DEFAULT NULL,
    `contact_email` varchar(255) DEFAULT NULL,
    `contact_phone` varchar(50) DEFAULT NULL,
    `website` varchar(255) DEFAULT NULL,
    `address` text DEFAULT NULL,
    `status` enum('pending', 'approved', 'rejected') NOT NULL DEFAULT 'pending',
    `submitted_by` int(11) DEFAULT NULL,
    `reviewed_by` int(11) DEFAULT NULL,
    `reviewed_at` timestamp NULL DEFAULT NULL,
    `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    KEY `idx_tenant_status` (`tenant_id`, `status`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================================================
-- SECTION 3: CRON JOBS TABLE
-- ============================================================================

CREATE TABLE IF NOT EXISTS `cron_jobs` (
    `id` int(11) NOT NULL AUTO_INCREMENT,
    `tenant_id` int(11) NOT NULL,
    `job_name` varchar(100) NOT NULL,
    `job_type` varchar(50) DEFAULT NULL,
    `last_run` timestamp NULL DEFAULT NULL,
    `last_status` enum('success', 'failed', 'running') DEFAULT NULL,
    `last_duration` int(11) DEFAULT NULL,
    `last_error` text DEFAULT NULL,
    `next_run` timestamp NULL DEFAULT NULL,
    `run_count` int(11) DEFAULT 0,
    `failure_count` int(11) DEFAULT 0,
    `enabled` tinyint(1) DEFAULT 1,
    `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    UNIQUE KEY `unique_tenant_job` (`tenant_id`, `job_name`),
    KEY `idx_tenant_last_run` (`tenant_id`, `last_run`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================================================
-- SECTION 4: GROUP AUDIT LOG TABLE
-- ============================================================================

CREATE TABLE IF NOT EXISTS `group_audit_log` (
    `id` BIGINT(20) UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    `tenant_id` INT(11) NOT NULL,
    `group_id` INT(11) NULL,
    `user_id` INT(11) NULL,
    `target_user_id` INT(11) NULL,
    `action` VARCHAR(100) NOT NULL,
    `details` JSON NULL,
    `ip_address` VARCHAR(45) NULL,
    `user_agent` TEXT NULL,
    `created_at` DATETIME DEFAULT CURRENT_TIMESTAMP,
    INDEX `idx_tenant_group_date` (`tenant_id`, `group_id`, `created_at`),
    INDEX `idx_tenant_action_date` (`tenant_id`, `action`, `created_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================================================
-- SECTION 5: GROUP RECOMMENDATION INTERACTIONS TABLE
-- ============================================================================

CREATE TABLE IF NOT EXISTS `group_recommendation_interactions` (
    `id` BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    `tenant_id` INT UNSIGNED NOT NULL,
    `user_id` INT UNSIGNED NOT NULL,
    `group_id` INT UNSIGNED NOT NULL,
    `action` ENUM('viewed', 'clicked', 'joined', 'dismissed') NOT NULL,
    `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    INDEX `idx_tenant_user` (`tenant_id`, `user_id`),
    INDEX `idx_tenant_group` (`tenant_id`, `group_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================================================
-- SECTION 6: LAYOUT A/B TESTING TABLES
-- ============================================================================

CREATE TABLE IF NOT EXISTS `layout_ab_tests` (
    `id` BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    `name` VARCHAR(100) NOT NULL,
    `layouts` JSON NOT NULL,
    `start_date` DATETIME NOT NULL,
    `end_date` DATETIME NOT NULL,
    `metrics` JSON NULL,
    `status` ENUM('draft', 'active', 'paused', 'completed') DEFAULT 'draft',
    `winner_layout` VARCHAR(50) NULL,
    `winner_score` DECIMAL(10,2) NULL,
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    `completed_at` DATETIME NULL,
    INDEX `idx_status` (`status`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `layout_ab_assignments` (
    `id` BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    `user_id` INT NOT NULL,
    `campaign_id` BIGINT UNSIGNED NOT NULL,
    `assigned_layout` VARCHAR(50) NOT NULL,
    `assigned_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY `unique_assignment` (`user_id`, `campaign_id`),
    INDEX `idx_campaign` (`campaign_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================================================
-- SECTION 7: ADD MISSING COLUMNS
-- ============================================================================

-- Add is_verified to users table
SET @column_exists = (
    SELECT COUNT(*)
    FROM information_schema.COLUMNS
    WHERE TABLE_SCHEMA = DATABASE()
    AND TABLE_NAME = 'users'
    AND COLUMN_NAME = 'is_verified'
);

SET @sql_add_column = IF(@column_exists = 0,
    'ALTER TABLE `users` ADD COLUMN `is_verified` TINYINT(1) NOT NULL DEFAULT 0',
    'SELECT ''Column is_verified already exists'' AS result'
);

PREPARE stmt FROM @sql_add_column;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

-- Add created_at to group_members table
SET @column_exists = (
    SELECT COUNT(*)
    FROM information_schema.COLUMNS
    WHERE TABLE_SCHEMA = DATABASE()
    AND TABLE_NAME = 'group_members'
    AND COLUMN_NAME = 'created_at'
);

SET @sql_add_column = IF(@column_exists = 0,
    'ALTER TABLE `group_members` ADD COLUMN `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP',
    'SELECT ''Column created_at already exists in group_members'' AS result'
);

PREPARE stmt FROM @sql_add_column;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

-- ============================================================================
-- SECTION 8: ADD PERFORMANCE INDEXES
-- ============================================================================

-- Groups table indexes
CREATE INDEX IF NOT EXISTS `idx_tenant_featured_created` ON `groups` (`tenant_id`, `is_featured`, `created_at`);
CREATE INDEX IF NOT EXISTS `idx_tenant_parent` ON `groups` (`tenant_id`, `parent_id`);
CREATE INDEX IF NOT EXISTS `idx_owner_id` ON `groups` (`owner_id`);

-- Group_members table indexes
CREATE INDEX IF NOT EXISTS `idx_group_status` ON `group_members` (`group_id`, `status`);
CREATE INDEX IF NOT EXISTS `idx_group_status_id` ON `group_members` (`group_id`, `status`, `id`);

-- Users table indexes
CREATE INDEX IF NOT EXISTS `idx_is_verified` ON `users` (`is_verified`);
CREATE INDEX IF NOT EXISTS `idx_names` ON `users` (`first_name`, `last_name`);

-- ============================================================================
-- VERIFICATION QUERIES
-- ============================================================================

SELECT 'Verifying tables exist...' AS status;

SELECT
    CASE WHEN EXISTS (SELECT 1 FROM information_schema.TABLES WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'volunteering_organizations') THEN 'OK' ELSE 'MISSING' END AS volunteering_organizations,
    CASE WHEN EXISTS (SELECT 1 FROM information_schema.TABLES WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'cron_jobs') THEN 'OK' ELSE 'MISSING' END AS cron_jobs,
    CASE WHEN EXISTS (SELECT 1 FROM information_schema.TABLES WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'tenant_settings') THEN 'OK' ELSE 'MISSING' END AS tenant_settings,
    CASE WHEN EXISTS (SELECT 1 FROM information_schema.TABLES WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'group_audit_log') THEN 'OK' ELSE 'MISSING' END AS group_audit_log,
    CASE WHEN EXISTS (SELECT 1 FROM information_schema.TABLES WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'group_recommendation_interactions') THEN 'OK' ELSE 'MISSING' END AS group_recommendation_interactions,
    CASE WHEN EXISTS (SELECT 1 FROM information_schema.TABLES WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'layout_ab_tests') THEN 'OK' ELSE 'MISSING' END AS layout_ab_tests;

SELECT 'Verifying columns exist...' AS status;

SELECT
    CASE WHEN EXISTS (SELECT 1 FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'users' AND COLUMN_NAME = 'is_verified') THEN 'OK' ELSE 'MISSING' END AS users_is_verified,
    CASE WHEN EXISTS (SELECT 1 FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'group_members' AND COLUMN_NAME = 'created_at') THEN 'OK' ELSE 'MISSING' END AS group_members_created_at;

-- ============================================================================
-- MIGRATION COMPLETE
-- ============================================================================

SELECT CONCAT(
    '\n',
    '============================================================================\n',
    'PRODUCTION ERROR FIXES - MIGRATION COMPLETE\n',
    '============================================================================\n',
    '\n',
    'TABLES CREATED:\n',
    '✓ volunteering_organizations\n',
    '✓ cron_jobs\n',
    '✓ tenant_settings\n',
    '✓ group_audit_log\n',
    '✓ group_recommendation_interactions\n',
    '✓ layout_ab_tests + layout_ab_assignments\n',
    '\n',
    'COLUMNS ADDED:\n',
    '✓ users.is_verified\n',
    '✓ group_members.created_at\n',
    '\n',
    'INDEXES ADDED:\n',
    '✓ Performance indexes for groups queries\n',
    '\n',
    'Check the verification results above for "OK" status.\n',
    '============================================================================\n'
) AS MIGRATION_COMPLETE;

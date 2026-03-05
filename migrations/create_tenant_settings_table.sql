-- ============================================================================
-- CREATE tenant_settings TABLE
-- ============================================================================
-- Migration: Create tenant_settings table for storing tenant configuration
-- Purpose: Fix missing table error in EnterpriseController feature flags
-- Date: 2026-01-11
--
-- Error Fixed:
--   SQLSTATE[42S02]: Base table or view not found: 1146 Table
--   'project-nexus_.tenant_settings' doesn't exist
--
-- References:
--   - src/Controllers/Admin/EnterpriseController.php:1597 (getFeatureFlags)
--   - src/Controllers/Admin/EnterpriseController.php:1249 (configSettingUpdate)
--   - src/Controllers/Admin/EnterpriseController.php:1362 (featureFlagToggle)
--   - src/Controllers/Admin/EnterpriseController.php:1388 (featureFlagsReset)
-- ============================================================================

-- Create tenant_settings table (if not exists)
-- This table stores tenant-specific configuration settings and feature flags
CREATE TABLE IF NOT EXISTS `tenant_settings` (
    `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    `tenant_id` INT UNSIGNED NOT NULL DEFAULT 1 COMMENT 'Tenant identifier (multi-tenancy support)',
    `setting_key` VARCHAR(255) NOT NULL COMMENT 'Setting key in dot notation (e.g., feature.gamification, security.2fa_required)',
    `setting_value` TEXT NULL COMMENT 'Setting value (can be JSON, boolean string, or scalar value)',
    `setting_type` ENUM('string', 'boolean', 'integer', 'float', 'json', 'array') DEFAULT 'string' COMMENT 'Data type of the setting value',
    `description` TEXT NULL COMMENT 'Optional description of what this setting controls',
    `is_encrypted` TINYINT(1) NOT NULL DEFAULT 0 COMMENT 'Whether the value is encrypted (1) or not (0)',
    `created_at` TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at` TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    `created_by` INT UNSIGNED NULL COMMENT 'User ID who created this setting',
    `updated_by` INT UNSIGNED NULL COMMENT 'User ID who last updated this setting',
    PRIMARY KEY (`id`),
    UNIQUE KEY `unique_tenant_setting` (`tenant_id`, `setting_key`),
    KEY `idx_tenant_id` (`tenant_id`),
    KEY `idx_setting_key` (`setting_key`),
    KEY `idx_setting_type` (`setting_type`),
    KEY `idx_created_at` (`created_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
COMMENT='Tenant-specific configuration settings and feature flags';

-- Insert default feature flags if they don't exist
-- These are the default features that are enabled by default
INSERT IGNORE INTO `tenant_settings` (`tenant_id`, `setting_key`, `setting_value`, `setting_type`, `description`) VALUES
-- Core Modules
(1, 'feature.timebanking', '1', 'boolean', 'Enable timebanking functionality'),
(1, 'feature.listings', '1', 'boolean', 'Enable listings/marketplace functionality'),
(1, 'feature.messaging', '1', 'boolean', 'Enable direct messaging between users'),
(1, 'feature.connections', '1', 'boolean', 'Enable user connections/following'),
(1, 'feature.profiles', '1', 'boolean', 'Enable user profiles'),

-- Community Features
(1, 'feature.groups', '1', 'boolean', 'Enable groups/communities'),
(1, 'feature.events', '1', 'boolean', 'Enable events functionality'),
(1, 'feature.volunteering', '1', 'boolean', 'Enable volunteering opportunities'),
(1, 'feature.organizations', '1', 'boolean', 'Enable organizations/business profiles'),

-- Engagement Features
(1, 'feature.gamification', '1', 'boolean', 'Enable gamification (points, levels, etc.)'),
(1, 'feature.leaderboard', '1', 'boolean', 'Enable leaderboards'),
(1, 'feature.badges', '1', 'boolean', 'Enable badges/achievements'),
(1, 'feature.streaks', '1', 'boolean', 'Enable activity streaks'),

-- AI & Smart Features
(1, 'feature.ai_chat', '1', 'boolean', 'Enable AI chat assistant'),
(1, 'feature.smart_matching', '1', 'boolean', 'Enable smart matching engine'),
(1, 'feature.ai_moderation', '0', 'boolean', 'Enable AI content moderation'),

-- Notifications
(1, 'feature.push_notifications', '1', 'boolean', 'Enable push notifications'),
(1, 'feature.email_notifications', '1', 'boolean', 'Enable email notifications'),

-- Enterprise Features
(1, 'feature.gdpr_compliance', '1', 'boolean', 'Enable GDPR compliance features'),
(1, 'feature.analytics', '1', 'boolean', 'Enable analytics tracking'),
(1, 'feature.audit_logging', '1', 'boolean', 'Enable audit logging'),

-- Map & Location Features
(1, 'feature.map_view', '1', 'boolean', 'Enable map view for listings/events'),
(1, 'feature.geolocation', '1', 'boolean', 'Enable geolocation features');

-- ============================================================================
-- VERIFICATION QUERY (Optional - run separately after migration)
-- ============================================================================
-- Run these queries in a separate query window AFTER running the migration above

/*
-- Verify the table exists and check structure
SHOW CREATE TABLE `tenant_settings`;

-- Check all feature flags for tenant 1
SELECT
    setting_key,
    setting_value,
    setting_type,
    description
FROM `tenant_settings`
WHERE `tenant_id` = 1
AND `setting_key` LIKE 'feature.%'
ORDER BY `setting_key`;

-- Count settings by type
SELECT
    setting_type,
    COUNT(*) as count
FROM `tenant_settings`
GROUP BY setting_type;

-- Check for any encrypted settings
SELECT
    setting_key,
    is_encrypted
FROM `tenant_settings`
WHERE is_encrypted = 1;
*/

-- Output success message
SELECT 'TENANT_SETTINGS TABLE: Created successfully with default feature flags' AS result;

-- ============================================================================
-- MIGRATION COMPLETE
-- ============================================================================
-- Summary:
-- ✓ Created tenant_settings table with proper indexes
-- ✓ Added unique constraint on (tenant_id, setting_key) to prevent duplicates
-- ✓ Inserted default feature flags for tenant 1
-- ✓ Fixed error in EnterpriseController.php
--
-- Next Steps:
-- 1. Run this migration on your database
-- 2. Verify feature flags are visible in /admin/enterprise/config
-- 3. Configure feature flags via admin interface as needed
-- 4. Consider adding more configuration settings for your use case
--
-- Usage Examples:
-- - Feature flags: feature.* (e.g., feature.gamification, feature.ai_chat)
-- - Security settings: security.* (e.g., security.2fa_required, security.password_min_length)
-- - Performance settings: performance.* (e.g., performance.cache_enabled, performance.cdn_url)
-- - Notification settings: notifications.* (e.g., notifications.email_from, notifications.smtp_host)
-- ============================================================================

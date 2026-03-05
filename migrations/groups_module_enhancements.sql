-- ============================================================================
-- GROUPS MODULE ENHANCEMENTS - Database Migration
-- ============================================================================
-- This migration creates all tables needed for the comprehensive groups
-- module enhancements including configuration, permissions, audit logging,
-- moderation, approval workflows, and feature toggles.
--
-- Created: 2026-01-10
-- Reference: GROUPS_MODULE_ENHANCEMENTS.md
-- ============================================================================

-- ============================================================================
-- 1. GROUP_CONFIGURATION TABLE
-- ============================================================================
-- Stores tenant-specific configuration settings for the groups module
CREATE TABLE IF NOT EXISTS `group_configuration` (
    `id` INT(11) UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    `tenant_id` INT(11) NOT NULL COMMENT 'Tenant this configuration belongs to',
    `config_key` VARCHAR(100) NOT NULL COMMENT 'Configuration key (e.g., allow_user_group_creation)',
    `config_value` TEXT NOT NULL COMMENT 'Configuration value (stored as JSON for complex types)',
    `created_at` DATETIME DEFAULT CURRENT_TIMESTAMP,
    `updated_at` DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    UNIQUE KEY `unique_tenant_config` (`tenant_id`, `config_key`),
    INDEX `idx_tenant` (`tenant_id`),
    FOREIGN KEY (`tenant_id`) REFERENCES `tenants`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
COMMENT='Tenant-specific configuration settings for groups module';

-- ============================================================================
-- 2. GROUP_POLICIES TABLE
-- ============================================================================
-- Stores flexible tenant-specific policies with categories and typed values
CREATE TABLE IF NOT EXISTS `group_policies` (
    `id` INT(11) UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    `tenant_id` INT(11) NOT NULL COMMENT 'Tenant this policy belongs to',
    `policy_key` VARCHAR(100) NOT NULL COMMENT 'Policy identifier (e.g., banned_words)',
    `policy_value` TEXT NOT NULL COMMENT 'Policy value (stored as JSON for complex types)',
    `category` ENUM('creation', 'membership', 'content', 'moderation', 'notifications', 'features') NOT NULL COMMENT 'Policy category',
    `value_type` ENUM('boolean', 'number', 'string', 'json', 'list') NOT NULL COMMENT 'Type of value stored',
    `description` TEXT NULL COMMENT 'Description of what this policy does',
    `created_at` DATETIME DEFAULT CURRENT_TIMESTAMP,
    `updated_at` DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    UNIQUE KEY `unique_tenant_policy` (`tenant_id`, `policy_key`),
    INDEX `idx_tenant_category` (`tenant_id`, `category`),
    INDEX `idx_tenant` (`tenant_id`),
    FOREIGN KEY (`tenant_id`) REFERENCES `tenants`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
COMMENT='Flexible tenant-specific policies for groups module';

-- ============================================================================
-- 3. GROUP_USER_PERMISSIONS TABLE
-- ============================================================================
-- Stores custom global permissions granted to specific users
CREATE TABLE IF NOT EXISTS `group_user_permissions` (
    `id` INT(11) UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    `tenant_id` INT(11) NOT NULL COMMENT 'Tenant context',
    `user_id` INT(11) NOT NULL COMMENT 'User who has this permission',
    `permission` VARCHAR(100) NOT NULL COMMENT 'Permission identifier (e.g., create_hub)',
    `granted_by` INT(11) NULL COMMENT 'User ID who granted this permission',
    `granted_at` DATETIME DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY `unique_user_permission` (`tenant_id`, `user_id`, `permission`),
    INDEX `idx_tenant_user` (`tenant_id`, `user_id`),
    INDEX `idx_user` (`user_id`),
    FOREIGN KEY (`tenant_id`) REFERENCES `tenants`(`id`) ON DELETE CASCADE,
    FOREIGN KEY (`user_id`) REFERENCES `users`(`id`) ON DELETE CASCADE,
    FOREIGN KEY (`granted_by`) REFERENCES `users`(`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
COMMENT='Custom global permissions for specific users in groups module';

-- ============================================================================
-- 4. GROUP_MEMBER_PERMISSIONS TABLE
-- ============================================================================
-- Stores custom permissions for specific users within specific groups
CREATE TABLE IF NOT EXISTS `group_member_permissions` (
    `id` INT(11) UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    `group_id` INT(11) NOT NULL COMMENT 'Group this permission applies to',
    `user_id` INT(11) NOT NULL COMMENT 'User who has this permission',
    `permission` VARCHAR(100) NOT NULL COMMENT 'Permission identifier (e.g., edit_group)',
    `granted_by` INT(11) NULL COMMENT 'User ID who granted this permission',
    `granted_at` DATETIME DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY `unique_group_user_permission` (`group_id`, `user_id`, `permission`),
    INDEX `idx_group` (`group_id`),
    INDEX `idx_user` (`user_id`),
    FOREIGN KEY (`group_id`) REFERENCES `groups`(`id`) ON DELETE CASCADE,
    FOREIGN KEY (`user_id`) REFERENCES `users`(`id`) ON DELETE CASCADE,
    FOREIGN KEY (`granted_by`) REFERENCES `users`(`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
COMMENT='Custom group-level permissions for specific users';

-- ============================================================================
-- 5. GROUP_AUDIT_LOG TABLE
-- ============================================================================
-- Comprehensive audit trail for all group-related actions
CREATE TABLE IF NOT EXISTS `group_audit_log` (
    `id` BIGINT(20) UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    `tenant_id` INT(11) NOT NULL COMMENT 'Tenant context',
    `group_id` INT(11) NULL COMMENT 'Group this action relates to',
    `user_id` INT(11) NULL COMMENT 'User who performed the action',
    `target_user_id` INT(11) NULL COMMENT 'User who was affected by the action',
    `action` VARCHAR(100) NOT NULL COMMENT 'Action type (e.g., group_created, member_joined)',
    `details` JSON NULL COMMENT 'Additional context about the action',
    `ip_address` VARCHAR(45) NULL COMMENT 'IP address of the user',
    `user_agent` TEXT NULL COMMENT 'Browser/client user agent',
    `created_at` DATETIME DEFAULT CURRENT_TIMESTAMP,
    INDEX `idx_tenant_group_date` (`tenant_id`, `group_id`, `created_at`),
    INDEX `idx_tenant_user_date` (`tenant_id`, `user_id`, `created_at`),
    INDEX `idx_tenant_action_date` (`tenant_id`, `action`, `created_at`),
    INDEX `idx_group` (`group_id`),
    INDEX `idx_user` (`user_id`),
    INDEX `idx_created` (`created_at`),
    FOREIGN KEY (`tenant_id`) REFERENCES `tenants`(`id`) ON DELETE CASCADE,
    FOREIGN KEY (`group_id`) REFERENCES `groups`(`id`) ON DELETE CASCADE,
    FOREIGN KEY (`user_id`) REFERENCES `users`(`id`) ON DELETE SET NULL,
    FOREIGN KEY (`target_user_id`) REFERENCES `users`(`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
COMMENT='Comprehensive audit trail for groups module';

-- ============================================================================
-- 6. GROUP_CONTENT_FLAGS TABLE
-- ============================================================================
-- Stores flagged content for moderation review
CREATE TABLE IF NOT EXISTS `group_content_flags` (
    `id` INT(11) UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    `tenant_id` INT(11) NOT NULL COMMENT 'Tenant context',
    `content_type` ENUM('discussion', 'post', 'comment', 'group') NOT NULL COMMENT 'Type of content flagged',
    `content_id` INT(11) NOT NULL COMMENT 'ID of the flagged content',
    `reported_by` INT(11) NOT NULL COMMENT 'User who reported this content',
    `reason` ENUM('spam', 'harassment', 'inappropriate', 'offensive', 'misinformation', 'other', 'violence') NOT NULL COMMENT 'Reason for flagging',
    `description` TEXT NULL COMMENT 'Additional details from reporter',
    `status` ENUM('pending', 'reviewed', 'actioned', 'dismissed') DEFAULT 'pending' COMMENT 'Moderation status',
    `moderated_by` INT(11) NULL COMMENT 'Moderator who reviewed this flag',
    `moderation_action` ENUM('flag', 'hide', 'delete', 'approve', 'warn', 'ban') NULL COMMENT 'Action taken by moderator',
    `moderator_notes` TEXT NULL COMMENT 'Notes from moderator',
    `created_at` DATETIME DEFAULT CURRENT_TIMESTAMP,
    `resolved_at` DATETIME NULL COMMENT 'When this flag was resolved',
    INDEX `idx_tenant_status` (`tenant_id`, `status`),
    INDEX `idx_content` (`content_type`, `content_id`),
    INDEX `idx_tenant_reporter` (`tenant_id`, `reported_by`),
    INDEX `idx_status` (`status`),
    INDEX `idx_created` (`created_at`),
    FOREIGN KEY (`tenant_id`) REFERENCES `tenants`(`id`) ON DELETE CASCADE,
    FOREIGN KEY (`reported_by`) REFERENCES `users`(`id`) ON DELETE CASCADE,
    FOREIGN KEY (`moderated_by`) REFERENCES `users`(`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
COMMENT='Flagged content for moderation review';

-- ============================================================================
-- 7. GROUP_USER_BANS TABLE
-- ============================================================================
-- Stores users banned from the groups module
CREATE TABLE IF NOT EXISTS `group_user_bans` (
    `id` INT(11) UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    `tenant_id` INT(11) NOT NULL COMMENT 'Tenant context',
    `user_id` INT(11) NOT NULL COMMENT 'Banned user',
    `banned_by` INT(11) NOT NULL COMMENT 'Admin who issued the ban',
    `reason` TEXT NOT NULL COMMENT 'Reason for the ban',
    `banned_at` DATETIME DEFAULT CURRENT_TIMESTAMP,
    `banned_until` DATETIME NULL COMMENT 'NULL = permanent, otherwise temporary ban expiry',
    UNIQUE KEY `unique_tenant_user_ban` (`tenant_id`, `user_id`),
    INDEX `idx_tenant` (`tenant_id`),
    INDEX `idx_user` (`user_id`),
    INDEX `idx_expiry` (`banned_until`),
    FOREIGN KEY (`tenant_id`) REFERENCES `tenants`(`id`) ON DELETE CASCADE,
    FOREIGN KEY (`user_id`) REFERENCES `users`(`id`) ON DELETE CASCADE,
    FOREIGN KEY (`banned_by`) REFERENCES `users`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
COMMENT='Users banned from groups module';

-- ============================================================================
-- 8. GROUP_USER_WARNINGS TABLE
-- ============================================================================
-- Stores warnings issued to users (auto-ban after 3 warnings)
CREATE TABLE IF NOT EXISTS `group_user_warnings` (
    `id` INT(11) UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    `tenant_id` INT(11) NOT NULL COMMENT 'Tenant context',
    `user_id` INT(11) NOT NULL COMMENT 'Warned user',
    `warned_by` INT(11) NOT NULL COMMENT 'Admin who issued the warning',
    `reason` TEXT NOT NULL COMMENT 'Reason for the warning',
    `created_at` DATETIME DEFAULT CURRENT_TIMESTAMP,
    INDEX `idx_tenant_user_date` (`tenant_id`, `user_id`, `created_at`),
    INDEX `idx_tenant` (`tenant_id`),
    INDEX `idx_user` (`user_id`),
    INDEX `idx_created` (`created_at`),
    FOREIGN KEY (`tenant_id`) REFERENCES `tenants`(`id`) ON DELETE CASCADE,
    FOREIGN KEY (`user_id`) REFERENCES `users`(`id`) ON DELETE CASCADE,
    FOREIGN KEY (`warned_by`) REFERENCES `users`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
COMMENT='Warnings issued to users in groups module';

-- ============================================================================
-- 9. GROUP_APPROVAL_REQUESTS TABLE
-- ============================================================================
-- Stores group creation approval workflow requests
CREATE TABLE IF NOT EXISTS `group_approval_requests` (
    `id` INT(11) UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    `tenant_id` INT(11) NOT NULL COMMENT 'Tenant context',
    `group_id` INT(11) NOT NULL COMMENT 'Group awaiting approval',
    `submitted_by` INT(11) NOT NULL COMMENT 'User who submitted for approval',
    `submission_notes` TEXT NULL COMMENT 'Notes from submitter',
    `reviewed_by` INT(11) NULL COMMENT 'Admin who reviewed this request',
    `review_notes` TEXT NULL COMMENT 'Notes from reviewer',
    `status` ENUM('pending', 'approved', 'rejected', 'changes_requested') DEFAULT 'pending' COMMENT 'Approval status',
    `created_at` DATETIME DEFAULT CURRENT_TIMESTAMP,
    `reviewed_at` DATETIME NULL COMMENT 'When this request was reviewed',
    INDEX `idx_tenant_status` (`tenant_id`, `status`),
    INDEX `idx_group` (`group_id`),
    INDEX `idx_tenant_submitter` (`tenant_id`, `submitted_by`),
    INDEX `idx_status` (`status`),
    INDEX `idx_created` (`created_at`),
    FOREIGN KEY (`tenant_id`) REFERENCES `tenants`(`id`) ON DELETE CASCADE,
    FOREIGN KEY (`group_id`) REFERENCES `groups`(`id`) ON DELETE CASCADE,
    FOREIGN KEY (`submitted_by`) REFERENCES `users`(`id`) ON DELETE CASCADE,
    FOREIGN KEY (`reviewed_by`) REFERENCES `users`(`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
COMMENT='Group approval workflow requests';

-- ============================================================================
-- 10. GROUP_FEATURE_TOGGLES TABLE
-- ============================================================================
-- Stores feature toggles to enable/disable features per tenant
CREATE TABLE IF NOT EXISTS `group_feature_toggles` (
    `id` INT(11) UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    `tenant_id` INT(11) NOT NULL COMMENT 'Tenant this toggle belongs to',
    `feature_key` VARCHAR(100) NOT NULL COMMENT 'Feature identifier (e.g., discussions, feedback)',
    `is_enabled` TINYINT(1) DEFAULT 1 COMMENT 'Whether this feature is enabled',
    `category` ENUM('core', 'content', 'moderation', 'gamification', 'advanced') NOT NULL COMMENT 'Feature category',
    `description` TEXT NULL COMMENT 'Description of what this feature does',
    `created_at` DATETIME DEFAULT CURRENT_TIMESTAMP,
    `updated_at` DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    UNIQUE KEY `unique_tenant_feature` (`tenant_id`, `feature_key`),
    INDEX `idx_tenant` (`tenant_id`),
    INDEX `idx_tenant_category` (`tenant_id`, `category`),
    INDEX `idx_enabled` (`is_enabled`),
    FOREIGN KEY (`tenant_id`) REFERENCES `tenants`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
COMMENT='Feature toggles for groups module per tenant';

-- ============================================================================
-- INSERT DEFAULT FEATURE TOGGLES
-- ============================================================================
-- Create default feature toggles for all tenants
INSERT INTO `group_feature_toggles` (`tenant_id`, `feature_key`, `is_enabled`, `category`, `description`)
SELECT
    t.id,
    'module_enabled',
    1,
    'core',
    'Enable/disable entire groups module'
FROM `tenants` t
WHERE NOT EXISTS (
    SELECT 1 FROM `group_feature_toggles`
    WHERE `tenant_id` = t.id AND `feature_key` = 'module_enabled'
);

INSERT INTO `group_feature_toggles` (`tenant_id`, `feature_key`, `is_enabled`, `category`, `description`)
SELECT
    t.id,
    'user_group_creation',
    1,
    'core',
    'Allow users to create their own groups'
FROM `tenants` t
WHERE NOT EXISTS (
    SELECT 1 FROM `group_feature_toggles`
    WHERE `tenant_id` = t.id AND `feature_key` = 'user_group_creation'
);

INSERT INTO `group_feature_toggles` (`tenant_id`, `feature_key`, `is_enabled`, `category`, `description`)
SELECT
    t.id,
    'discussions',
    1,
    'content',
    'Enable group discussions'
FROM `tenants` t
WHERE NOT EXISTS (
    SELECT 1 FROM `group_feature_toggles`
    WHERE `tenant_id` = t.id AND `feature_key` = 'discussions'
);

INSERT INTO `group_feature_toggles` (`tenant_id`, `feature_key`, `is_enabled`, `category`, `description`)
SELECT
    t.id,
    'feedback',
    1,
    'content',
    'Enable group feedback/testimonials'
FROM `tenants` t
WHERE NOT EXISTS (
    SELECT 1 FROM `group_feature_toggles`
    WHERE `tenant_id` = t.id AND `feature_key` = 'feedback'
);

INSERT INTO `group_feature_toggles` (`tenant_id`, `feature_key`, `is_enabled`, `category`, `description`)
SELECT
    t.id,
    'analytics',
    1,
    'advanced',
    'Enable group analytics and statistics'
FROM `tenants` t
WHERE NOT EXISTS (
    SELECT 1 FROM `group_feature_toggles`
    WHERE `tenant_id` = t.id AND `feature_key` = 'analytics'
);

INSERT INTO `group_feature_toggles` (`tenant_id`, `feature_key`, `is_enabled`, `category`, `description`)
SELECT
    t.id,
    'moderation',
    1,
    'moderation',
    'Enable content moderation and flagging'
FROM `tenants` t
WHERE NOT EXISTS (
    SELECT 1 FROM `group_feature_toggles`
    WHERE `tenant_id` = t.id AND `feature_key` = 'moderation'
);

INSERT INTO `group_feature_toggles` (`tenant_id`, `feature_key`, `is_enabled`, `category`, `description`)
SELECT
    t.id,
    'approval_workflow',
    0,
    'moderation',
    'Require admin approval for new groups'
FROM `tenants` t
WHERE NOT EXISTS (
    SELECT 1 FROM `group_feature_toggles`
    WHERE `tenant_id` = t.id AND `feature_key` = 'approval_workflow'
);

INSERT INTO `group_feature_toggles` (`tenant_id`, `feature_key`, `is_enabled`, `category`, `description`)
SELECT
    t.id,
    'achievements',
    1,
    'gamification',
    'Enable group achievements and badges'
FROM `tenants` t
WHERE NOT EXISTS (
    SELECT 1 FROM `group_feature_toggles`
    WHERE `tenant_id` = t.id AND `feature_key` = 'achievements'
);

-- ============================================================================
-- MIGRATION COMPLETE
-- ============================================================================
-- Summary:
-- ✓ Created 10 new tables for groups module enhancements
-- ✓ Added proper indexes for performance
-- ✓ Added foreign keys for data integrity
-- ✓ Inserted default feature toggles for all tenants
--
-- Tables Created:
-- 1. group_configuration - Configuration settings
-- 2. group_policies - Tenant-specific policies
-- 3. group_user_permissions - Custom user permissions
-- 4. group_member_permissions - Custom group-level permissions
-- 5. group_audit_log - Comprehensive audit trail
-- 6. group_content_flags - Flagged content for review
-- 7. group_user_bans - Banned users
-- 8. group_user_warnings - User warnings
-- 9. group_approval_requests - Approval workflow
-- 10. group_feature_toggles - Feature toggles per tenant
--
-- Next Steps:
-- 1. Run this migration on your database
-- 2. The service classes will auto-populate configuration and policies
-- 3. Access admin dashboard at /admin/groups
-- 4. Configure settings at /admin/groups/settings
-- 5. Manage policies at /admin/groups/policies
-- 6. View analytics at /admin/groups/analytics
-- ============================================================================

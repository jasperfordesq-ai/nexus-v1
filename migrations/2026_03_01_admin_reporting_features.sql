-- =============================================================================
-- Migration: Admin & Reporting Features (A1, A4, A7)
-- Date: 2026-03-01
-- Description: Creates tables for social value config, member activity flags,
--              and content moderation queue.
-- Idempotent: All statements use IF NOT EXISTS / IF EXISTS guards.
-- =============================================================================

-- =============================================================================
-- A1: Social Value Configuration (per-tenant SROI settings)
-- =============================================================================
CREATE TABLE IF NOT EXISTS `social_value_config` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `tenant_id` INT NOT NULL,
    `hour_value_currency` VARCHAR(3) NOT NULL DEFAULT 'GBP' COMMENT 'ISO 4217 currency code',
    `hour_value_amount` DECIMAL(10,2) NOT NULL DEFAULT 15.00 COMMENT 'Monetary value per hour',
    `social_multiplier` DECIMAL(5,2) NOT NULL DEFAULT 3.50 COMMENT 'SROI multiplier factor',
    `reporting_period` ENUM('monthly','quarterly','annually') NOT NULL DEFAULT 'annually',
    `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at` TIMESTAMP NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
    UNIQUE KEY `uk_tenant` (`tenant_id`),
    INDEX `idx_tenant` (`tenant_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- =============================================================================
-- A4: Member Activity Flags (inactive member detection)
-- =============================================================================
CREATE TABLE IF NOT EXISTS `member_activity_flags` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `user_id` INT NOT NULL,
    `tenant_id` INT NOT NULL,
    `last_activity_at` TIMESTAMP NULL DEFAULT NULL COMMENT 'Most recent activity across all types',
    `last_login_at` TIMESTAMP NULL DEFAULT NULL,
    `last_transaction_at` TIMESTAMP NULL DEFAULT NULL,
    `last_post_at` TIMESTAMP NULL DEFAULT NULL,
    `last_event_at` TIMESTAMP NULL DEFAULT NULL,
    `flag_type` ENUM('inactive','dormant','at_risk') NOT NULL DEFAULT 'inactive',
    `flagged_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `notified_at` TIMESTAMP NULL DEFAULT NULL COMMENT 'When admin was last notified about this flag',
    `resolved_at` TIMESTAMP NULL DEFAULT NULL COMMENT 'When flag was resolved (user became active again)',
    UNIQUE KEY `uk_user_tenant` (`user_id`, `tenant_id`),
    INDEX `idx_tenant_flag` (`tenant_id`, `flag_type`),
    INDEX `idx_tenant_last_activity` (`tenant_id`, `last_activity_at`),
    INDEX `idx_flagged_at` (`tenant_id`, `flagged_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- =============================================================================
-- A7: Content Moderation Queue
-- =============================================================================
CREATE TABLE IF NOT EXISTS `content_moderation_queue` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `tenant_id` INT NOT NULL,
    `content_type` ENUM('post','listing','event','comment','group') NOT NULL,
    `content_id` INT NOT NULL,
    `author_id` INT NOT NULL COMMENT 'User who created the content',
    `title` VARCHAR(255) NULL COMMENT 'Content title/summary for quick review',
    `status` ENUM('pending','approved','rejected','flagged') NOT NULL DEFAULT 'pending',
    `reviewer_id` INT NULL COMMENT 'Admin who reviewed',
    `reviewed_at` TIMESTAMP NULL DEFAULT NULL,
    `rejection_reason` TEXT NULL,
    `auto_flagged` TINYINT(1) NOT NULL DEFAULT 0 COMMENT 'Was this auto-flagged by profanity/spam filter',
    `flag_reason` VARCHAR(255) NULL COMMENT 'Reason for auto-flagging',
    `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at` TIMESTAMP NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
    INDEX `idx_tenant_status` (`tenant_id`, `status`),
    INDEX `idx_tenant_type_status` (`tenant_id`, `content_type`, `status`),
    INDEX `idx_content` (`content_type`, `content_id`),
    INDEX `idx_author` (`tenant_id`, `author_id`),
    INDEX `idx_reviewer` (`reviewer_id`),
    INDEX `idx_created` (`tenant_id`, `created_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- =============================================================================
-- A7: Content Moderation Settings (per-tenant, which content types need moderation)
-- These are stored in tenant_settings with prefix 'moderation.'
-- No separate table needed — uses existing tenant_settings infrastructure.
-- =============================================================================
-- Settings keys:
--   moderation.enabled          = 1/0  (global toggle)
--   moderation.require_post     = 1/0  (posts require approval)
--   moderation.require_listing  = 1/0  (listings require approval)
--   moderation.require_event    = 1/0  (events require approval)
--   moderation.require_comment  = 1/0  (comments require approval)
--   moderation.auto_filter      = 1/0  (enable profanity/spam auto-filter)

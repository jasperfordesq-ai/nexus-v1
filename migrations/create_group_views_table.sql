-- Migration: Create Group Views Tracking Table
-- Purpose: Track group profile views for analytics
-- Date: 2026-01-10

CREATE TABLE IF NOT EXISTS `group_views` (
    `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    `tenant_id` INT UNSIGNED NOT NULL,
    `group_id` INT UNSIGNED NOT NULL,
    `user_id` INT UNSIGNED NULL COMMENT 'NULL if anonymous visitor',
    `viewed_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `referrer` VARCHAR(255) NULL COMMENT 'Where they came from',
    `session_id` VARCHAR(64) NULL COMMENT 'Anonymous session tracking',

    INDEX `idx_tenant` (`tenant_id`),
    INDEX `idx_group_date` (`group_id`, `viewed_at`),
    INDEX `idx_user` (`user_id`),

    FOREIGN KEY (`group_id`) REFERENCES `groups`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Add view tracking insert logic
-- This table is OPTIONAL - analytics will work without it,
-- but provides richer discovery metrics when implemented.

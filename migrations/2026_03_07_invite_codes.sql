-- Invite Code System — Schema Migration
-- Date: 2026-03-07
-- Author: Jasper Ford / Claude
-- Description: Adds invite code tables for invite-only registration mode.
-- Idempotent: Uses IF NOT EXISTS throughout.

-- 1. Tenant Invite Codes
CREATE TABLE IF NOT EXISTS `tenant_invite_codes` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `tenant_id` INT NOT NULL,
    `code` VARCHAR(20) NOT NULL,
    `created_by` INT NOT NULL COMMENT 'Admin user who generated the code',
    `max_uses` INT NOT NULL DEFAULT 1,
    `uses_count` INT NOT NULL DEFAULT 0,
    `note` VARCHAR(255) DEFAULT NULL,
    `is_active` TINYINT(1) NOT NULL DEFAULT 1,
    `expires_at` DATETIME DEFAULT NULL,
    `last_used_at` DATETIME DEFAULT NULL,
    `last_used_by` INT DEFAULT NULL,
    `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY `uk_tic_tenant_code` (`tenant_id`, `code`),
    INDEX `idx_tic_tenant_active` (`tenant_id`, `is_active`),
    CONSTRAINT `fk_tic_tenant` FOREIGN KEY (`tenant_id`) REFERENCES `tenants`(`id`) ON DELETE CASCADE,
    CONSTRAINT `fk_tic_creator` FOREIGN KEY (`created_by`) REFERENCES `users`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 2. Invite Code Usage Log
CREATE TABLE IF NOT EXISTS `tenant_invite_code_uses` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `invite_code_id` INT NOT NULL,
    `user_id` INT NOT NULL COMMENT 'User who used the code to register',
    `used_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    INDEX `idx_ticu_code` (`invite_code_id`),
    INDEX `idx_ticu_user` (`user_id`),
    CONSTRAINT `fk_ticu_code` FOREIGN KEY (`invite_code_id`) REFERENCES `tenant_invite_codes`(`id`) ON DELETE CASCADE,
    CONSTRAINT `fk_ticu_user` FOREIGN KEY (`user_id`) REFERENCES `users`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

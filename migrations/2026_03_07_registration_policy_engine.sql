-- Registration Policy Engine — Schema Migration
-- Date: 2026-03-07
-- Author: Jasper Ford / Claude
-- Description: Adds tenant registration policies, identity verification sessions,
--              verification events audit log, and user verification status columns.
-- Idempotent: Uses IF NOT EXISTS throughout.

-- 1. Tenant Registration Policies
CREATE TABLE IF NOT EXISTS `tenant_registration_policies` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `tenant_id` INT NOT NULL,
    `registration_mode` ENUM('open','open_with_approval','verified_identity','government_id','invite_only') NOT NULL DEFAULT 'open',
    `verification_provider` VARCHAR(50) DEFAULT NULL COMMENT 'Provider slug: mock, stripe_identity, veriff, etc.',
    `verification_level` ENUM('none','document_only','document_selfie','reusable_digital_id','manual_review') NOT NULL DEFAULT 'none',
    `post_verification` ENUM('activate','admin_approval','limited_access','reject_on_fail') NOT NULL DEFAULT 'activate',
    `fallback_mode` ENUM('none','admin_review','native_registration') NOT NULL DEFAULT 'none',
    `require_email_verify` TINYINT(1) NOT NULL DEFAULT 1,
    `provider_config` TEXT DEFAULT NULL COMMENT 'Encrypted JSON — provider API keys and settings',
    `is_active` TINYINT(1) NOT NULL DEFAULT 1,
    `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at` DATETIME DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
    UNIQUE KEY `uk_trp_tenant` (`tenant_id`),
    INDEX `idx_trp_tenant` (`tenant_id`),
    CONSTRAINT `fk_trp_tenant` FOREIGN KEY (`tenant_id`) REFERENCES `tenants`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;


-- 2. Identity Verification Sessions
CREATE TABLE IF NOT EXISTS `identity_verification_sessions` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `tenant_id` INT NOT NULL,
    `user_id` INT NOT NULL,
    `provider_slug` VARCHAR(50) NOT NULL COMMENT 'Provider slug: mock, stripe_identity, veriff, etc.',
    `provider_session_id` VARCHAR(255) DEFAULT NULL COMMENT 'External session ID from provider',
    `verification_level` ENUM('document_only','document_selfie','reusable_digital_id','manual_review') NOT NULL,
    `status` ENUM('created','started','processing','passed','failed','expired','cancelled') NOT NULL DEFAULT 'created',
    `redirect_url` TEXT DEFAULT NULL COMMENT 'URL for hosted/redirect verification flows',
    `client_token` VARCHAR(500) DEFAULT NULL COMMENT 'Token for embedded SDK flows',
    `result_summary` TEXT DEFAULT NULL COMMENT 'Encrypted JSON: decision, risk_score, checks_passed',
    `provider_reference` VARCHAR(255) DEFAULT NULL COMMENT 'Provider reference for the completed check',
    `failure_reason` VARCHAR(500) DEFAULT NULL COMMENT 'Human-readable failure reason',
    `metadata` TEXT DEFAULT NULL COMMENT 'Encrypted JSON: provider-specific extra data',
    `expires_at` DATETIME DEFAULT NULL,
    `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at` DATETIME DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
    `completed_at` DATETIME DEFAULT NULL,
    INDEX `idx_ivs_tenant_user` (`tenant_id`, `user_id`),
    INDEX `idx_ivs_provider_session` (`provider_slug`, `provider_session_id`),
    INDEX `idx_ivs_status` (`status`),
    CONSTRAINT `fk_ivs_tenant` FOREIGN KEY (`tenant_id`) REFERENCES `tenants`(`id`) ON DELETE CASCADE,
    CONSTRAINT `fk_ivs_user` FOREIGN KEY (`user_id`) REFERENCES `users`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;


-- 3. Identity Verification Events (Audit Log)
CREATE TABLE IF NOT EXISTS `identity_verification_events` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `tenant_id` INT NOT NULL,
    `user_id` INT NOT NULL,
    `session_id` INT DEFAULT NULL COMMENT 'FK to identity_verification_sessions if applicable',
    `event_type` ENUM(
        'registration_started','verification_created','verification_started',
        'verification_processing','verification_passed','verification_failed',
        'verification_expired','verification_cancelled',
        'admin_review_started','admin_approved','admin_rejected',
        'account_activated','fallback_triggered'
    ) NOT NULL,
    `actor_id` INT DEFAULT NULL COMMENT 'Admin user ID if admin action, NULL for system/webhook',
    `actor_type` ENUM('system','user','admin','webhook') NOT NULL DEFAULT 'system',
    `details` TEXT DEFAULT NULL COMMENT 'JSON context for the event',
    `ip_address` VARCHAR(45) DEFAULT NULL,
    `user_agent` VARCHAR(500) DEFAULT NULL,
    `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    INDEX `idx_ive_tenant_user` (`tenant_id`, `user_id`),
    INDEX `idx_ive_session` (`session_id`),
    INDEX `idx_ive_type` (`event_type`),
    INDEX `idx_ive_created` (`created_at`),
    CONSTRAINT `fk_ive_session` FOREIGN KEY (`session_id`) REFERENCES `identity_verification_sessions`(`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;


-- 4. Add verification columns to users table
-- Using stored procedure for idempotent ALTER TABLE
DELIMITER //
CREATE PROCEDURE IF NOT EXISTS add_verification_columns()
BEGIN
    -- verification_status column
    IF NOT EXISTS (
        SELECT 1 FROM INFORMATION_SCHEMA.COLUMNS
        WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'users' AND COLUMN_NAME = 'verification_status'
    ) THEN
        ALTER TABLE `users` ADD COLUMN `verification_status` ENUM('none','pending','passed','failed','expired') NOT NULL DEFAULT 'none' AFTER `is_approved`;
    END IF;

    -- verification_provider column
    IF NOT EXISTS (
        SELECT 1 FROM INFORMATION_SCHEMA.COLUMNS
        WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'users' AND COLUMN_NAME = 'verification_provider'
    ) THEN
        ALTER TABLE `users` ADD COLUMN `verification_provider` VARCHAR(50) DEFAULT NULL AFTER `verification_status`;
    END IF;

    -- verification_completed_at column
    IF NOT EXISTS (
        SELECT 1 FROM INFORMATION_SCHEMA.COLUMNS
        WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'users' AND COLUMN_NAME = 'verification_completed_at'
    ) THEN
        ALTER TABLE `users` ADD COLUMN `verification_completed_at` DATETIME DEFAULT NULL AFTER `verification_provider`;
    END IF;

    -- Add index on verification_status
    IF NOT EXISTS (
        SELECT 1 FROM INFORMATION_SCHEMA.STATISTICS
        WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'users' AND INDEX_NAME = 'idx_users_verification'
    ) THEN
        ALTER TABLE `users` ADD INDEX `idx_users_verification` (`tenant_id`, `verification_status`);
    END IF;
END //
DELIMITER ;

CALL add_verification_columns();
DROP PROCEDURE IF EXISTS add_verification_columns;


-- 5. Backfill existing tenants with default policy matching current behavior
-- Only inserts for tenants that don't already have a policy row.
INSERT IGNORE INTO `tenant_registration_policies`
    (`tenant_id`, `registration_mode`, `verification_level`, `post_verification`, `fallback_mode`, `require_email_verify`, `is_active`)
SELECT
    t.id,
    CASE
        WHEN ts_mode.setting_value = 'closed' THEN 'invite_only'
        WHEN ts_mode.setting_value = 'invite' THEN 'invite_only'
        WHEN ts_approval.setting_value IN ('true', '1') THEN 'open_with_approval'
        ELSE 'open'
    END,
    'none',
    CASE
        WHEN ts_approval.setting_value IN ('true', '1') THEN 'admin_approval'
        ELSE 'activate'
    END,
    'none',
    CASE
        WHEN ts_email.setting_value IN ('true', '1') THEN 1
        ELSE 0
    END,
    1
FROM tenants t
LEFT JOIN tenant_settings ts_mode ON ts_mode.tenant_id = t.id AND ts_mode.setting_key = 'general.registration_mode'
LEFT JOIN tenant_settings ts_approval ON ts_approval.tenant_id = t.id AND ts_approval.setting_key = 'general.admin_approval'
LEFT JOIN tenant_settings ts_email ON ts_email.tenant_id = t.id AND ts_email.setting_key = 'general.email_verification'
WHERE t.is_active = 1;

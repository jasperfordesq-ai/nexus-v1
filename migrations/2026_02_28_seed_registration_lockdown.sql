-- Migration: Seed secure registration defaults for ALL tenants
-- Date: 2026-02-28
-- Purpose: Lock down all tenants to require email verification and admin approval.
-- This is a security fix — previously these settings were stored in tenant_settings
-- but NEVER enforced by the registration or login controllers.
--
-- IDEMPOTENT: Uses INSERT IGNORE so it can be safely re-run.

-- Ensure tenant_settings table exists (should already exist from AdminConfigApiController)
CREATE TABLE IF NOT EXISTS `tenant_settings` (
    `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    `tenant_id` INT UNSIGNED NOT NULL DEFAULT 1,
    `setting_key` VARCHAR(255) NOT NULL,
    `setting_value` TEXT NULL,
    `setting_type` ENUM('string','boolean','integer','float','json','array') DEFAULT 'string',
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

-- For EVERY active tenant, seed the registration policy settings.
-- INSERT IGNORE skips if the setting already exists (admin previously saved it).
-- All tenants get:
--   registration_mode = 'open'   (allow signups, but enforce verification + approval)
--   email_verification = 'true'  (MUST verify email before login)
--   admin_approval = 'true'      (MUST be approved by admin before login)
--   maintenance_mode = 'false'   (site is live)

INSERT IGNORE INTO tenant_settings (tenant_id, setting_key, setting_value, setting_type, description)
SELECT t.id, 'general.registration_mode', 'open', 'string', 'Registration mode: open, closed, or invite'
FROM tenants t;

INSERT IGNORE INTO tenant_settings (tenant_id, setting_key, setting_value, setting_type, description)
SELECT t.id, 'general.email_verification', 'true', 'boolean', 'Require email verification before login'
FROM tenants t;

INSERT IGNORE INTO tenant_settings (tenant_id, setting_key, setting_value, setting_type, description)
SELECT t.id, 'general.admin_approval', 'true', 'boolean', 'Require admin approval before login'
FROM tenants t;

INSERT IGNORE INTO tenant_settings (tenant_id, setting_key, setting_value, setting_type, description)
SELECT t.id, 'general.maintenance_mode', 'false', 'boolean', 'Maintenance mode (admin-only access)'
FROM tenants t;

-- FORCE UPDATE: For tenants that already had these settings but had them set to insecure values,
-- force them to the secure defaults. This is the "lock down ALL tenants" mandate.
UPDATE tenant_settings
SET setting_value = 'true', updated_at = CURRENT_TIMESTAMP
WHERE setting_key = 'general.email_verification';

UPDATE tenant_settings
SET setting_value = 'true', updated_at = CURRENT_TIMESTAMP
WHERE setting_key = 'general.admin_approval';

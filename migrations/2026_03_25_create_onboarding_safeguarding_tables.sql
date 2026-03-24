-- Migration: Onboarding module + safeguarding configuration tables
-- Date: 2026-03-25
-- Purpose: Admin-configurable onboarding module with tenant-level safeguarding options
--          and member safeguarding preference storage (access-controlled)
-- Idempotent: uses IF NOT EXISTS throughout

-- =============================================================================
-- 1. tenant_safeguarding_options
--    Admin-configured safeguarding checkboxes/options shown during onboarding.
--    Each tenant defines their own options via country presets or custom config.
-- =============================================================================

CREATE TABLE IF NOT EXISTS `tenant_safeguarding_options` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `tenant_id` int(11) NOT NULL,
  `option_key` varchar(100) NOT NULL COMMENT 'Machine-readable key e.g. works_with_children',
  `option_type` enum('checkbox','info','select') NOT NULL DEFAULT 'checkbox',
  `label` varchar(500) NOT NULL COMMENT 'Display label shown to member',
  `description` text DEFAULT NULL COMMENT 'Help text shown under the option',
  `help_url` varchar(500) DEFAULT NULL COMMENT 'Optional link to more info',
  `sort_order` int(10) unsigned NOT NULL DEFAULT 0,
  `is_active` tinyint(1) NOT NULL DEFAULT 1,
  `is_required` tinyint(1) NOT NULL DEFAULT 0 COMMENT 'Must answer before completing onboarding',
  `select_options` json DEFAULT NULL COMMENT 'For type=select: [{value, label}] array',
  `triggers` json DEFAULT NULL COMMENT 'Behavioral triggers: {requires_vetted_interaction, requires_broker_approval, restricts_messaging, restricts_matching, notify_admin_on_selection, vetting_type_required}',
  `preset_source` varchar(50) DEFAULT NULL COMMENT 'Country preset that created this: ireland, england_wales, scotland, northern_ireland, or NULL for custom',
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  `updated_at` datetime DEFAULT NULL ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `uk_tenant_option` (`tenant_id`, `option_key`),
  KEY `idx_tenant_active` (`tenant_id`, `is_active`, `sort_order`),
  CONSTRAINT `fk_tso_tenant` FOREIGN KEY (`tenant_id`) REFERENCES `tenants` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
  COMMENT='Admin-configured safeguarding options shown during onboarding per tenant';


-- =============================================================================
-- 2. user_safeguarding_preferences
--    Member selections from tenant safeguarding options. Access-controlled:
--    never exposed in public profile API, only visible to admins/brokers.
-- =============================================================================

CREATE TABLE IF NOT EXISTS `user_safeguarding_preferences` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `tenant_id` int(11) NOT NULL,
  `user_id` int(11) NOT NULL,
  `option_id` int(10) unsigned NOT NULL COMMENT 'FK to tenant_safeguarding_options',
  `selected_value` varchar(255) NOT NULL DEFAULT '1' COMMENT 'Checkbox: 1/0. Select: chosen value',
  `notes` text DEFAULT NULL COMMENT 'Free-text notes from member',
  `consent_given_at` datetime NOT NULL COMMENT 'GDPR consent timestamp',
  `consent_ip` varchar(45) DEFAULT NULL COMMENT 'IP at time of consent',
  `revoked_at` datetime DEFAULT NULL COMMENT 'Set when member withdraws consent',
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  `updated_at` datetime DEFAULT NULL ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `uk_user_option` (`tenant_id`, `user_id`, `option_id`),
  KEY `idx_tenant_user` (`tenant_id`, `user_id`),
  KEY `idx_option` (`option_id`),
  CONSTRAINT `fk_usp_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_usp_tenant` FOREIGN KEY (`tenant_id`) REFERENCES `tenants` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_usp_option` FOREIGN KEY (`option_id`) REFERENCES `tenant_safeguarding_options` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
  COMMENT='Member safeguarding preference selections (access-controlled, never in public API)';


-- =============================================================================
-- 3. Seed default onboarding.* settings for all active tenants
--    These defaults match the current hardcoded behavior exactly so that
--    existing tenants see zero change until an admin configures new settings.
-- =============================================================================

INSERT IGNORE INTO `tenant_settings` (`tenant_id`, `setting_key`, `setting_value`, `setting_type`, `description`)
SELECT t.id, 'onboarding.enabled', '1', 'boolean', 'Whether onboarding wizard is enabled'
FROM tenants t WHERE t.is_active = 1;

INSERT IGNORE INTO `tenant_settings` (`tenant_id`, `setting_key`, `setting_value`, `setting_type`, `description`)
SELECT t.id, 'onboarding.mandatory', '1', 'boolean', 'Whether onboarding is required before platform access'
FROM tenants t WHERE t.is_active = 1;

INSERT IGNORE INTO `tenant_settings` (`tenant_id`, `setting_key`, `setting_value`, `setting_type`, `description`)
SELECT t.id, 'onboarding.step_welcome_enabled', '1', 'boolean', 'Show welcome step'
FROM tenants t WHERE t.is_active = 1;

INSERT IGNORE INTO `tenant_settings` (`tenant_id`, `setting_key`, `setting_value`, `setting_type`, `description`)
SELECT t.id, 'onboarding.step_profile_enabled', '1', 'boolean', 'Show profile step'
FROM tenants t WHERE t.is_active = 1;

INSERT IGNORE INTO `tenant_settings` (`tenant_id`, `setting_key`, `setting_value`, `setting_type`, `description`)
SELECT t.id, 'onboarding.step_profile_required', '1', 'boolean', 'Profile step is mandatory'
FROM tenants t WHERE t.is_active = 1;

INSERT IGNORE INTO `tenant_settings` (`tenant_id`, `setting_key`, `setting_value`, `setting_type`, `description`)
SELECT t.id, 'onboarding.step_interests_enabled', '1', 'boolean', 'Show interests step'
FROM tenants t WHERE t.is_active = 1;

INSERT IGNORE INTO `tenant_settings` (`tenant_id`, `setting_key`, `setting_value`, `setting_type`, `description`)
SELECT t.id, 'onboarding.step_interests_required', '0', 'boolean', 'Interests step is mandatory'
FROM tenants t WHERE t.is_active = 1;

INSERT IGNORE INTO `tenant_settings` (`tenant_id`, `setting_key`, `setting_value`, `setting_type`, `description`)
SELECT t.id, 'onboarding.step_skills_enabled', '1', 'boolean', 'Show skills step'
FROM tenants t WHERE t.is_active = 1;

INSERT IGNORE INTO `tenant_settings` (`tenant_id`, `setting_key`, `setting_value`, `setting_type`, `description`)
SELECT t.id, 'onboarding.step_skills_required', '0', 'boolean', 'Skills step is mandatory'
FROM tenants t WHERE t.is_active = 1;

INSERT IGNORE INTO `tenant_settings` (`tenant_id`, `setting_key`, `setting_value`, `setting_type`, `description`)
SELECT t.id, 'onboarding.step_safeguarding_enabled', '0', 'boolean', 'Show safeguarding step (off by default — admin must enable)'
FROM tenants t WHERE t.is_active = 1;

INSERT IGNORE INTO `tenant_settings` (`tenant_id`, `setting_key`, `setting_value`, `setting_type`, `description`)
SELECT t.id, 'onboarding.step_safeguarding_required', '0', 'boolean', 'Safeguarding step is mandatory'
FROM tenants t WHERE t.is_active = 1;

INSERT IGNORE INTO `tenant_settings` (`tenant_id`, `setting_key`, `setting_value`, `setting_type`, `description`)
SELECT t.id, 'onboarding.step_confirm_enabled', '1', 'boolean', 'Show confirm/review step'
FROM tenants t WHERE t.is_active = 1;

INSERT IGNORE INTO `tenant_settings` (`tenant_id`, `setting_key`, `setting_value`, `setting_type`, `description`)
SELECT t.id, 'onboarding.avatar_required', '1', 'boolean', 'Require avatar upload during onboarding'
FROM tenants t WHERE t.is_active = 1;

INSERT IGNORE INTO `tenant_settings` (`tenant_id`, `setting_key`, `setting_value`, `setting_type`, `description`)
SELECT t.id, 'onboarding.bio_required', '1', 'boolean', 'Require bio text during onboarding'
FROM tenants t WHERE t.is_active = 1;

INSERT IGNORE INTO `tenant_settings` (`tenant_id`, `setting_key`, `setting_value`, `setting_type`, `description`)
SELECT t.id, 'onboarding.bio_min_length', '10', 'integer', 'Minimum bio character count'
FROM tenants t WHERE t.is_active = 1;

INSERT IGNORE INTO `tenant_settings` (`tenant_id`, `setting_key`, `setting_value`, `setting_type`, `description`)
SELECT t.id, 'onboarding.listing_creation_mode', 'disabled', 'string', 'Listing creation mode: disabled, suggestions_only, draft, pending_review, active'
FROM tenants t WHERE t.is_active = 1;

INSERT IGNORE INTO `tenant_settings` (`tenant_id`, `setting_key`, `setting_value`, `setting_type`, `description`)
SELECT t.id, 'onboarding.listing_max_auto', '3', 'integer', 'Max auto-generated listings (0-10)'
FROM tenants t WHERE t.is_active = 1;

INSERT IGNORE INTO `tenant_settings` (`tenant_id`, `setting_key`, `setting_value`, `setting_type`, `description`)
SELECT t.id, 'onboarding.require_completion_for_visibility', '0', 'boolean', 'Profile hidden until onboarding complete'
FROM tenants t WHERE t.is_active = 1;

INSERT IGNORE INTO `tenant_settings` (`tenant_id`, `setting_key`, `setting_value`, `setting_type`, `description`)
SELECT t.id, 'onboarding.require_avatar_for_visibility', '0', 'boolean', 'Profile hidden until avatar set'
FROM tenants t WHERE t.is_active = 1;

INSERT IGNORE INTO `tenant_settings` (`tenant_id`, `setting_key`, `setting_value`, `setting_type`, `description`)
SELECT t.id, 'onboarding.require_bio_for_visibility', '0', 'boolean', 'Profile hidden until bio set'
FROM tenants t WHERE t.is_active = 1;

INSERT IGNORE INTO `tenant_settings` (`tenant_id`, `setting_key`, `setting_value`, `setting_type`, `description`)
SELECT t.id, 'onboarding.country_preset', 'custom', 'string', 'Country preset: ireland, england_wales, scotland, northern_ireland, custom'
FROM tenants t WHERE t.is_active = 1;

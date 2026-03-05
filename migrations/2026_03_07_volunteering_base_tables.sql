-- Migration: Volunteering Base Tables
-- Purpose: Track the core vol_* tables that were created outside of migrations
-- Date: 2026-03-07
-- These tables already exist in production; this migration is idempotent (IF NOT EXISTS).

-- =========================================================================
-- vol_organizations — volunteer organizations registered per tenant
-- =========================================================================
CREATE TABLE IF NOT EXISTS `vol_organizations` (
    `id`              INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
    `tenant_id`       INT UNSIGNED NOT NULL,
    `user_id`         INT UNSIGNED NOT NULL COMMENT 'Owner/registrant',
    `name`            VARCHAR(255) NOT NULL,
    `slug`            VARCHAR(255) DEFAULT NULL,
    `description`     TEXT DEFAULT NULL,
    `contact_email`   VARCHAR(255) DEFAULT NULL,
    `website`         VARCHAR(500) DEFAULT NULL,
    `logo_url`        VARCHAR(500) DEFAULT NULL,
    `status`          ENUM('pending','approved','declined','suspended') NOT NULL DEFAULT 'pending',
    `auto_pay_enabled` TINYINT(1) NOT NULL DEFAULT 0,
    `created_at`      DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at`      DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX `idx_vol_org_tenant`  (`tenant_id`),
    INDEX `idx_vol_org_user`    (`user_id`),
    INDEX `idx_vol_org_status`  (`tenant_id`, `status`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- =========================================================================
-- vol_opportunities — volunteer opportunity listings
-- =========================================================================
CREATE TABLE IF NOT EXISTS `vol_opportunities` (
    `id`              INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
    `tenant_id`       INT UNSIGNED NOT NULL,
    `organization_id` INT UNSIGNED NOT NULL,
    `created_by`      INT UNSIGNED NOT NULL COMMENT 'User who created this listing',
    `category_id`     INT UNSIGNED DEFAULT NULL,
    `title`           VARCHAR(255) NOT NULL,
    `description`     TEXT DEFAULT NULL,
    `location`        VARCHAR(500) DEFAULT NULL,
    `latitude`        DECIMAL(10,7) DEFAULT NULL,
    `longitude`       DECIMAL(10,7) DEFAULT NULL,
    `skills_needed`   TEXT DEFAULT NULL,
    `start_date`      DATE DEFAULT NULL,
    `end_date`        DATE DEFAULT NULL,
    `status`          ENUM('open','closed','cancelled','filled') NOT NULL DEFAULT 'open',
    `is_active`       TINYINT(1) NOT NULL DEFAULT 1,
    `created_at`      DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at`      DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX `idx_vol_opp_tenant`  (`tenant_id`),
    INDEX `idx_vol_opp_org`     (`organization_id`),
    INDEX `idx_vol_opp_active`  (`tenant_id`, `is_active`, `status`),
    INDEX `idx_vol_geo`         (`latitude`, `longitude`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- =========================================================================
-- vol_shifts — time slots for opportunities
-- =========================================================================
CREATE TABLE IF NOT EXISTS `vol_shifts` (
    `id`              INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
    `tenant_id`       INT UNSIGNED NOT NULL,
    `opportunity_id`  INT UNSIGNED NOT NULL,
    `start_time`      DATETIME NOT NULL,
    `end_time`        DATETIME NOT NULL,
    `capacity`        INT NOT NULL DEFAULT 1,
    `filled_count`    INT NOT NULL DEFAULT 0,
    `required_skills` JSON DEFAULT NULL COMMENT 'JSON array of required skill keywords',
    `created_at`      DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at`      DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX `idx_vol_shift_tenant` (`tenant_id`),
    INDEX `idx_vol_shift_opp`    (`opportunity_id`),
    INDEX `idx_vol_shift_time`   (`tenant_id`, `start_time`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- =========================================================================
-- vol_applications — user applications to volunteer opportunities
-- =========================================================================
CREATE TABLE IF NOT EXISTS `vol_applications` (
    `id`              INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
    `tenant_id`       INT UNSIGNED NOT NULL,
    `opportunity_id`  INT UNSIGNED NOT NULL,
    `user_id`         INT UNSIGNED NOT NULL,
    `shift_id`        INT UNSIGNED DEFAULT NULL,
    `message`         TEXT DEFAULT NULL,
    `status`          ENUM('pending','approved','declined','withdrawn') NOT NULL DEFAULT 'pending',
    `reviewed_by`     INT UNSIGNED DEFAULT NULL,
    `reviewed_at`     DATETIME DEFAULT NULL,
    `created_at`      DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at`      DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    UNIQUE KEY `uq_vol_app_user_opp` (`tenant_id`, `opportunity_id`, `user_id`),
    INDEX `idx_vol_app_tenant`  (`tenant_id`),
    INDEX `idx_vol_app_user`    (`user_id`),
    INDEX `idx_vol_app_opp`     (`opportunity_id`),
    INDEX `idx_vol_app_status`  (`tenant_id`, `status`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- =========================================================================
-- vol_logs — volunteer hour logs (a.k.a. vol_hours in some references)
-- =========================================================================
CREATE TABLE IF NOT EXISTS `vol_logs` (
    `id`              INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
    `tenant_id`       INT UNSIGNED NOT NULL,
    `user_id`         INT UNSIGNED NOT NULL,
    `organization_id` INT UNSIGNED DEFAULT NULL,
    `opportunity_id`  INT UNSIGNED DEFAULT NULL,
    `date_logged`     DATE NOT NULL,
    `hours`           DECIMAL(5,2) NOT NULL,
    `description`     TEXT DEFAULT NULL,
    `status`          ENUM('pending','approved','rejected') NOT NULL DEFAULT 'pending',
    `verified_by`     INT UNSIGNED DEFAULT NULL,
    `verified_at`     DATETIME DEFAULT NULL,
    `created_at`      DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at`      DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX `idx_vol_log_tenant`  (`tenant_id`),
    INDEX `idx_vol_log_user`    (`user_id`),
    INDEX `idx_vol_log_org`     (`organization_id`),
    INDEX `idx_vol_log_status`  (`tenant_id`, `status`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- =========================================================================
-- vol_shift_signups — direct shift signups (separate from applications)
-- =========================================================================
CREATE TABLE IF NOT EXISTS `vol_shift_signups` (
    `id`              INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
    `tenant_id`       INT UNSIGNED NOT NULL,
    `shift_id`        INT UNSIGNED NOT NULL,
    `user_id`         INT UNSIGNED NOT NULL,
    `status`          ENUM('confirmed','cancelled','attended','no_show') NOT NULL DEFAULT 'confirmed',
    `created_at`      DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at`      DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    UNIQUE KEY `uq_vol_signup_user_shift` (`tenant_id`, `shift_id`, `user_id`),
    INDEX `idx_vol_signup_tenant` (`tenant_id`),
    INDEX `idx_vol_signup_shift`  (`shift_id`),
    INDEX `idx_vol_signup_user`   (`user_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

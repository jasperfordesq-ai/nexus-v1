-- Migration: Fix schema mismatches discovered during full test suite run
-- Date: 2026-03-22
-- Fixes: missing updated_at on 7 tables, missing category on tenant_settings,
--        missing tables (job_interview_slots, broker_control_config),
--        missing columns on various tables

-- ============================================================
-- 1. Add updated_at to 7 legacy tables
-- ============================================================

ALTER TABLE `groups`
    ADD COLUMN IF NOT EXISTS `updated_at` DATETIME NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP AFTER `created_at`;

ALTER TABLE `goals`
    ADD COLUMN IF NOT EXISTS `updated_at` TIMESTAMP NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP AFTER `created_at`;

ALTER TABLE `events`
    ADD COLUMN IF NOT EXISTS `updated_at` DATETIME NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP AFTER `created_at`;

ALTER TABLE `reviews`
    ADD COLUMN IF NOT EXISTS `updated_at` DATETIME NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP AFTER `created_at`;

ALTER TABLE `connections`
    ADD COLUMN IF NOT EXISTS `updated_at` DATETIME NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP AFTER `created_at`;

ALTER TABLE `transactions`
    ADD COLUMN IF NOT EXISTS `updated_at` DATETIME NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP AFTER `created_at`;

ALTER TABLE `skills`
    ADD COLUMN IF NOT EXISTS `updated_at` TIMESTAMP NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP AFTER `created_at`;

-- ============================================================
-- 2. Add category column to tenant_settings
-- ============================================================

ALTER TABLE `tenant_settings`
    ADD COLUMN IF NOT EXISTS `category` VARCHAR(100) NULL DEFAULT 'general' AFTER `setting_type`;

-- ============================================================
-- 3. Add start_date alias column to events (actual column is start_time)
-- ============================================================

ALTER TABLE `events`
    ADD COLUMN IF NOT EXISTS `start_date` DATETIME NULL DEFAULT NULL AFTER `start_time`;

-- ============================================================
-- 4. Add password column to users (actual column is password_hash)
-- ============================================================

ALTER TABLE `users`
    ADD COLUMN IF NOT EXISTS `password` VARCHAR(255) NULL DEFAULT NULL AFTER `password_hash`;

-- ============================================================
-- 5. Add slug to seo_metadata
-- ============================================================

ALTER TABLE `seo_metadata`
    ADD COLUMN IF NOT EXISTS `slug` VARCHAR(255) NULL DEFAULT NULL AFTER `entity_id`;

-- ============================================================
-- 6. Add from_path to seo_redirects (source_url exists but code uses from_path)
-- ============================================================

ALTER TABLE `seo_redirects`
    ADD COLUMN IF NOT EXISTS `from_path` VARCHAR(500) NULL DEFAULT NULL AFTER `source_url`,
    ADD COLUMN IF NOT EXISTS `to_path` VARCHAR(500) NULL DEFAULT NULL AFTER `from_path`,
    ADD COLUMN IF NOT EXISTS `status_code` INT DEFAULT 301 AFTER `to_path`,
    ADD COLUMN IF NOT EXISTS `updated_at` TIMESTAMP NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP AFTER `created_at`;

-- ============================================================
-- 7. Add user_id to deliverables (owner_id exists but code also uses user_id)
-- ============================================================

ALTER TABLE `deliverables`
    ADD COLUMN IF NOT EXISTS `user_id` INT NULL DEFAULT NULL AFTER `owner_id`;

-- ============================================================
-- 8. Add tenant_id to group_members
-- ============================================================

ALTER TABLE `group_members`
    ADD COLUMN IF NOT EXISTS `tenant_id` INT NOT NULL DEFAULT 1 AFTER `id`,
    ADD COLUMN IF NOT EXISTS `updated_at` TIMESTAMP NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP AFTER `created_at`;

-- ============================================================
-- 9. Create job_interview_slots table
-- ============================================================

CREATE TABLE IF NOT EXISTS `job_interview_slots` (
    `id` INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
    `tenant_id` INT NOT NULL,
    `job_id` INT UNSIGNED NOT NULL,
    `employer_user_id` INT UNSIGNED NOT NULL,
    `slot_start` DATETIME NOT NULL,
    `slot_end` DATETIME NOT NULL,
    `is_booked` TINYINT(1) NOT NULL DEFAULT 0,
    `booked_by_user_id` INT UNSIGNED NULL DEFAULT NULL,
    `booked_at` DATETIME NULL DEFAULT NULL,
    `interview_type` VARCHAR(50) NULL DEFAULT 'in_person',
    `meeting_link` VARCHAR(500) NULL DEFAULT NULL,
    `location` VARCHAR(255) NULL DEFAULT NULL,
    `notes` TEXT NULL DEFAULT NULL,
    `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at` TIMESTAMP NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
    INDEX `idx_job_slots_tenant` (`tenant_id`),
    INDEX `idx_job_slots_job` (`job_id`),
    INDEX `idx_job_slots_employer` (`employer_user_id`),
    INDEX `idx_job_slots_booked` (`is_booked`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================================
-- 10. Create broker_control_config table
-- ============================================================

CREATE TABLE IF NOT EXISTS `broker_control_config` (
    `id` INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
    `tenant_id` INT NOT NULL,
    `config_key` VARCHAR(100) NOT NULL,
    `config_value` TEXT NULL DEFAULT NULL,
    `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at` TIMESTAMP NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
    UNIQUE KEY `uq_broker_config_tenant_key` (`tenant_id`, `config_key`),
    INDEX `idx_broker_config_tenant` (`tenant_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

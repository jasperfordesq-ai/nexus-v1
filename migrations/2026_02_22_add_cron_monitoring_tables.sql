-- Migration: Add cron monitoring tables
-- Date: 2026-02-22
-- Purpose: Create cron_logs, cron_settings, cron_job_settings tables.
--          These tables back the React admin cron monitoring UI.
-- Note: These tables already exist on production. This migration documents
--        the actual production schema for local dev environments.

-- ============================================================================
-- TABLE 1: cron_logs
-- Per-execution log entries for cron jobs (audit trail)
-- Note: job_id is VARCHAR (matches job_name in cron_jobs), status uses 'error' not 'failed'
-- ============================================================================

CREATE TABLE IF NOT EXISTS `cron_logs` (
    `id` INT(11) NOT NULL AUTO_INCREMENT,
    `job_id` VARCHAR(100) NOT NULL COMMENT 'Job identifier (matches cron_jobs.job_name)',
    `status` ENUM('success', 'error', 'running') DEFAULT 'running',
    `output` TEXT DEFAULT NULL COMMENT 'stdout / error message',
    `duration_seconds` DECIMAL(10,2) DEFAULT NULL,
    `executed_at` DATETIME DEFAULT CURRENT_TIMESTAMP,
    `executed_by` INT(11) DEFAULT NULL,
    `tenant_id` INT(11) DEFAULT NULL,
    `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    KEY `idx_status` (`status`),
    KEY `idx_executed_at` (`executed_at`),
    KEY `idx_job_id` (`job_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
COMMENT='Execution log for cron jobs — one row per run';

-- ============================================================================
-- TABLE 2: cron_settings
-- Global cron configuration (key-value store, NOT tenant-scoped)
-- ============================================================================

CREATE TABLE IF NOT EXISTS `cron_settings` (
    `id` INT(11) NOT NULL AUTO_INCREMENT,
    `setting_key` VARCHAR(100) NOT NULL,
    `setting_value` TEXT DEFAULT NULL,
    `updated_at` DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    UNIQUE KEY `unique_key` (`setting_key`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
COMMENT='Global cron settings (key-value pairs)';

-- ============================================================================
-- TABLE 3: cron_job_settings
-- Per-job overrides (enable/disable, schedule, retry, notifications)
-- Note: NOT tenant-scoped, job_id is VARCHAR
-- ============================================================================

CREATE TABLE IF NOT EXISTS `cron_job_settings` (
    `id` INT(11) NOT NULL AUTO_INCREMENT,
    `job_id` VARCHAR(100) NOT NULL COMMENT 'Job identifier',
    `is_enabled` TINYINT(1) DEFAULT 1,
    `custom_schedule` VARCHAR(50) DEFAULT NULL COMMENT 'Cron expression override',
    `notify_on_failure` TINYINT(1) DEFAULT 0,
    `notify_emails` TEXT DEFAULT NULL COMMENT 'Comma-separated emails',
    `max_retries` INT(11) DEFAULT 0,
    `timeout_seconds` INT(11) DEFAULT 300,
    `created_at` DATETIME DEFAULT CURRENT_TIMESTAMP,
    `updated_at` DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    UNIQUE KEY `unique_job` (`job_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
COMMENT='Per-job cron settings overrides';

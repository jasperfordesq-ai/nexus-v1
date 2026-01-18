-- Migration: Create Optional Feature Tables
-- Purpose: Add tables for volunteering organizations and cron job tracking
-- Date: 2026-01-11
-- These tables are queried by AdminController but were missing from the schema

-- --------------------------------------------------------
-- Table: volunteering_organizations
-- Purpose: Track volunteering organizations pending approval
-- --------------------------------------------------------
CREATE TABLE IF NOT EXISTS `volunteering_organizations` (
    `id` int(11) NOT NULL AUTO_INCREMENT,
    `tenant_id` int(11) NOT NULL,
    `name` varchar(255) NOT NULL,
    `description` text DEFAULT NULL,
    `contact_email` varchar(255) DEFAULT NULL,
    `contact_phone` varchar(50) DEFAULT NULL,
    `website` varchar(255) DEFAULT NULL,
    `address` text DEFAULT NULL,
    `status` enum('pending', 'approved', 'rejected') NOT NULL DEFAULT 'pending',
    `submitted_by` int(11) DEFAULT NULL COMMENT 'User ID who submitted the organization',
    `reviewed_by` int(11) DEFAULT NULL COMMENT 'Admin user ID who reviewed',
    `reviewed_at` timestamp NULL DEFAULT NULL,
    `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    KEY `idx_tenant_status` (`tenant_id`, `status`),
    KEY `idx_submitted_by` (`submitted_by`),
    KEY `idx_reviewed_by` (`reviewed_by`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
COMMENT='Volunteering organizations with approval workflow';

-- --------------------------------------------------------
-- Table: cron_jobs
-- Purpose: Track cron job execution for monitoring and health checks
-- --------------------------------------------------------
CREATE TABLE IF NOT EXISTS `cron_jobs` (
    `id` int(11) NOT NULL AUTO_INCREMENT,
    `tenant_id` int(11) NOT NULL,
    `job_name` varchar(100) NOT NULL COMMENT 'Name/identifier of the cron job',
    `job_type` varchar(50) DEFAULT NULL COMMENT 'Category of job (email, cleanup, analytics, etc.)',
    `last_run` timestamp NULL DEFAULT NULL COMMENT 'When this job last executed',
    `last_status` enum('success', 'failed', 'running') DEFAULT NULL,
    `last_duration` int(11) DEFAULT NULL COMMENT 'Execution time in seconds',
    `last_error` text DEFAULT NULL COMMENT 'Error message if failed',
    `next_run` timestamp NULL DEFAULT NULL COMMENT 'Scheduled next execution',
    `run_count` int(11) DEFAULT 0 COMMENT 'Total number of executions',
    `failure_count` int(11) DEFAULT 0 COMMENT 'Total number of failures',
    `enabled` tinyint(1) DEFAULT 1 COMMENT 'Whether this job is active',
    `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    UNIQUE KEY `unique_tenant_job` (`tenant_id`, `job_name`),
    KEY `idx_tenant_last_run` (`tenant_id`, `last_run`),
    KEY `idx_next_run` (`next_run`),
    KEY `idx_enabled` (`enabled`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
COMMENT='Tracks cron job execution for system health monitoring';

-- --------------------------------------------------------
-- Add foreign key constraints (if not exists)
-- --------------------------------------------------------

-- Check and add foreign key for volunteering_organizations
SET @fk_vol_exists = (
    SELECT COUNT(*)
    FROM information_schema.TABLE_CONSTRAINTS
    WHERE CONSTRAINT_SCHEMA = DATABASE()
    AND TABLE_NAME = 'volunteering_organizations'
    AND CONSTRAINT_NAME = 'fk_vol_org_tenant'
    AND CONSTRAINT_TYPE = 'FOREIGN KEY'
);

SET @sql_fk_vol = IF(@fk_vol_exists = 0,
    'ALTER TABLE `volunteering_organizations` ADD CONSTRAINT `fk_vol_org_tenant` FOREIGN KEY (`tenant_id`) REFERENCES `tenants` (`id`) ON DELETE CASCADE',
    'SELECT ''Foreign key fk_vol_org_tenant already exists'' AS result'
);

PREPARE stmt FROM @sql_fk_vol;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

-- Check and add foreign key for cron_jobs
SET @fk_cron_exists = (
    SELECT COUNT(*)
    FROM information_schema.TABLE_CONSTRAINTS
    WHERE CONSTRAINT_SCHEMA = DATABASE()
    AND TABLE_NAME = 'cron_jobs'
    AND CONSTRAINT_NAME = 'fk_cron_tenant'
    AND CONSTRAINT_TYPE = 'FOREIGN KEY'
);

SET @sql_fk_cron = IF(@fk_cron_exists = 0,
    'ALTER TABLE `cron_jobs` ADD CONSTRAINT `fk_cron_tenant` FOREIGN KEY (`tenant_id`) REFERENCES `tenants` (`id`) ON DELETE CASCADE',
    'SELECT ''Foreign key fk_cron_tenant already exists'' AS result'
);

PREPARE stmt FROM @sql_fk_cron;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

-- Migration: Enhance job vacancies module
-- Date: 2026-03-01
-- Description: Adds saved jobs, application history, job alerts, featured jobs,
--              salary/compensation fields, pipeline stages, expiry/renewal,
--              and analytics tracking tables.

-- =============================================================================
-- J1: Saved jobs (bookmarks)
-- =============================================================================
CREATE TABLE IF NOT EXISTS `saved_jobs` (
    `id` int(11) UNSIGNED NOT NULL AUTO_INCREMENT,
    `user_id` int(11) NOT NULL,
    `job_id` int(11) UNSIGNED NOT NULL,
    `tenant_id` int(11) UNSIGNED NOT NULL,
    `saved_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    UNIQUE KEY `uq_saved_job_user` (`user_id`, `job_id`),
    INDEX `idx_saved_jobs_tenant_user` (`tenant_id`, `user_id`),
    INDEX `idx_saved_jobs_job` (`job_id`),
    CONSTRAINT `fk_saved_jobs_vacancy` FOREIGN KEY (`job_id`) REFERENCES `job_vacancies` (`id`) ON DELETE CASCADE,
    CONSTRAINT `fk_saved_jobs_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- =============================================================================
-- J3: Application pipeline stages - extend status enum
-- =============================================================================
ALTER TABLE `job_vacancy_applications`
    MODIFY COLUMN `status` varchar(30) NOT NULL DEFAULT 'applied';

-- Add stage column for pipeline tracking
-- Using a procedure to check column existence for idempotency
DROP PROCEDURE IF EXISTS add_application_stage_column;
DELIMITER //
CREATE PROCEDURE add_application_stage_column()
BEGIN
    IF NOT EXISTS (
        SELECT 1 FROM information_schema.COLUMNS
        WHERE TABLE_SCHEMA = DATABASE()
        AND TABLE_NAME = 'job_vacancy_applications'
        AND COLUMN_NAME = 'stage'
    ) THEN
        ALTER TABLE `job_vacancy_applications` ADD COLUMN `stage` varchar(30) NOT NULL DEFAULT 'applied';
    END IF;
END //
DELIMITER ;
CALL add_application_stage_column();
DROP PROCEDURE IF EXISTS add_application_stage_column;

-- =============================================================================
-- J4: Application status history
-- =============================================================================
CREATE TABLE IF NOT EXISTS `job_application_history` (
    `id` int(11) UNSIGNED NOT NULL AUTO_INCREMENT,
    `application_id` int(11) UNSIGNED NOT NULL,
    `from_status` varchar(30) DEFAULT NULL,
    `to_status` varchar(30) NOT NULL,
    `changed_by` int(11) DEFAULT NULL,
    `changed_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `notes` text DEFAULT NULL,
    PRIMARY KEY (`id`),
    INDEX `idx_app_history_application` (`application_id`),
    INDEX `idx_app_history_changed_at` (`changed_at`),
    CONSTRAINT `fk_app_history_application` FOREIGN KEY (`application_id`) REFERENCES `job_vacancy_applications` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- =============================================================================
-- J6: Job alerts/notifications
-- =============================================================================
CREATE TABLE IF NOT EXISTS `job_alerts` (
    `id` int(11) UNSIGNED NOT NULL AUTO_INCREMENT,
    `user_id` int(11) NOT NULL,
    `tenant_id` int(11) UNSIGNED NOT NULL,
    `keywords` varchar(500) DEFAULT NULL,
    `categories` varchar(500) DEFAULT NULL,
    `type` varchar(30) DEFAULT NULL,
    `commitment` varchar(30) DEFAULT NULL,
    `location` varchar(255) DEFAULT NULL,
    `is_remote_only` tinyint(1) NOT NULL DEFAULT 0,
    `is_active` tinyint(1) NOT NULL DEFAULT 1,
    `last_notified_at` datetime DEFAULT NULL,
    `created_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at` datetime DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    INDEX `idx_job_alerts_tenant_user` (`tenant_id`, `user_id`),
    INDEX `idx_job_alerts_active` (`tenant_id`, `is_active`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- =============================================================================
-- J7: Job expiry + renewal columns
-- =============================================================================
DROP PROCEDURE IF EXISTS add_job_expiry_columns;
DELIMITER //
CREATE PROCEDURE add_job_expiry_columns()
BEGIN
    IF NOT EXISTS (
        SELECT 1 FROM information_schema.COLUMNS
        WHERE TABLE_SCHEMA = DATABASE()
        AND TABLE_NAME = 'job_vacancies'
        AND COLUMN_NAME = 'expired_at'
    ) THEN
        ALTER TABLE `job_vacancies` ADD COLUMN `expired_at` datetime DEFAULT NULL;
    END IF;

    IF NOT EXISTS (
        SELECT 1 FROM information_schema.COLUMNS
        WHERE TABLE_SCHEMA = DATABASE()
        AND TABLE_NAME = 'job_vacancies'
        AND COLUMN_NAME = 'renewed_at'
    ) THEN
        ALTER TABLE `job_vacancies` ADD COLUMN `renewed_at` datetime DEFAULT NULL;
    END IF;

    IF NOT EXISTS (
        SELECT 1 FROM information_schema.COLUMNS
        WHERE TABLE_SCHEMA = DATABASE()
        AND TABLE_NAME = 'job_vacancies'
        AND COLUMN_NAME = 'renewal_count'
    ) THEN
        ALTER TABLE `job_vacancies` ADD COLUMN `renewal_count` int(11) NOT NULL DEFAULT 0;
    END IF;
END //
DELIMITER ;
CALL add_job_expiry_columns();
DROP PROCEDURE IF EXISTS add_job_expiry_columns;

-- =============================================================================
-- J8: Job analytics - view tracking over time
-- =============================================================================
CREATE TABLE IF NOT EXISTS `job_vacancy_views` (
    `id` int(11) UNSIGNED NOT NULL AUTO_INCREMENT,
    `vacancy_id` int(11) UNSIGNED NOT NULL,
    `user_id` int(11) DEFAULT NULL,
    `tenant_id` int(11) UNSIGNED NOT NULL,
    `viewed_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `ip_hash` varchar(64) DEFAULT NULL,
    PRIMARY KEY (`id`),
    INDEX `idx_job_views_vacancy` (`vacancy_id`),
    INDEX `idx_job_views_tenant` (`tenant_id`),
    INDEX `idx_job_views_date` (`viewed_at`),
    CONSTRAINT `fk_job_views_vacancy` FOREIGN KEY (`vacancy_id`) REFERENCES `job_vacancies` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- =============================================================================
-- J9: Salary/compensation fields
-- =============================================================================
DROP PROCEDURE IF EXISTS add_salary_columns;
DELIMITER //
CREATE PROCEDURE add_salary_columns()
BEGIN
    IF NOT EXISTS (
        SELECT 1 FROM information_schema.COLUMNS
        WHERE TABLE_SCHEMA = DATABASE()
        AND TABLE_NAME = 'job_vacancies'
        AND COLUMN_NAME = 'salary_min'
    ) THEN
        ALTER TABLE `job_vacancies` ADD COLUMN `salary_min` decimal(12,2) DEFAULT NULL;
    END IF;

    IF NOT EXISTS (
        SELECT 1 FROM information_schema.COLUMNS
        WHERE TABLE_SCHEMA = DATABASE()
        AND TABLE_NAME = 'job_vacancies'
        AND COLUMN_NAME = 'salary_max'
    ) THEN
        ALTER TABLE `job_vacancies` ADD COLUMN `salary_max` decimal(12,2) DEFAULT NULL;
    END IF;

    IF NOT EXISTS (
        SELECT 1 FROM information_schema.COLUMNS
        WHERE TABLE_SCHEMA = DATABASE()
        AND TABLE_NAME = 'job_vacancies'
        AND COLUMN_NAME = 'salary_type'
    ) THEN
        ALTER TABLE `job_vacancies` ADD COLUMN `salary_type` varchar(30) DEFAULT NULL;
    END IF;

    IF NOT EXISTS (
        SELECT 1 FROM information_schema.COLUMNS
        WHERE TABLE_SCHEMA = DATABASE()
        AND TABLE_NAME = 'job_vacancies'
        AND COLUMN_NAME = 'salary_currency'
    ) THEN
        ALTER TABLE `job_vacancies` ADD COLUMN `salary_currency` varchar(10) DEFAULT NULL;
    END IF;

    IF NOT EXISTS (
        SELECT 1 FROM information_schema.COLUMNS
        WHERE TABLE_SCHEMA = DATABASE()
        AND TABLE_NAME = 'job_vacancies'
        AND COLUMN_NAME = 'salary_negotiable'
    ) THEN
        ALTER TABLE `job_vacancies` ADD COLUMN `salary_negotiable` tinyint(1) NOT NULL DEFAULT 0;
    END IF;
END //
DELIMITER ;
CALL add_salary_columns();
DROP PROCEDURE IF EXISTS add_salary_columns;

-- =============================================================================
-- J10: Featured jobs
-- =============================================================================
DROP PROCEDURE IF EXISTS add_featured_columns;
DELIMITER //
CREATE PROCEDURE add_featured_columns()
BEGIN
    IF NOT EXISTS (
        SELECT 1 FROM information_schema.COLUMNS
        WHERE TABLE_SCHEMA = DATABASE()
        AND TABLE_NAME = 'job_vacancies'
        AND COLUMN_NAME = 'is_featured'
    ) THEN
        ALTER TABLE `job_vacancies` ADD COLUMN `is_featured` tinyint(1) NOT NULL DEFAULT 0;
    END IF;

    IF NOT EXISTS (
        SELECT 1 FROM information_schema.COLUMNS
        WHERE TABLE_SCHEMA = DATABASE()
        AND TABLE_NAME = 'job_vacancies'
        AND COLUMN_NAME = 'featured_until'
    ) THEN
        ALTER TABLE `job_vacancies` ADD COLUMN `featured_until` datetime DEFAULT NULL;
    END IF;
END //
DELIMITER ;
CALL add_featured_columns();
DROP PROCEDURE IF EXISTS add_featured_columns;

-- Add index for featured jobs queries
-- Using a procedure for idempotency
DROP PROCEDURE IF EXISTS add_featured_index;
DELIMITER //
CREATE PROCEDURE add_featured_index()
BEGIN
    IF NOT EXISTS (
        SELECT 1 FROM information_schema.STATISTICS
        WHERE TABLE_SCHEMA = DATABASE()
        AND TABLE_NAME = 'job_vacancies'
        AND INDEX_NAME = 'idx_job_vacancies_featured'
    ) THEN
        ALTER TABLE `job_vacancies` ADD INDEX `idx_job_vacancies_featured` (`tenant_id`, `is_featured`, `featured_until`);
    END IF;

    IF NOT EXISTS (
        SELECT 1 FROM information_schema.STATISTICS
        WHERE TABLE_SCHEMA = DATABASE()
        AND TABLE_NAME = 'job_vacancies'
        AND INDEX_NAME = 'idx_job_vacancies_deadline'
    ) THEN
        ALTER TABLE `job_vacancies` ADD INDEX `idx_job_vacancies_deadline` (`tenant_id`, `deadline`, `status`);
    END IF;
END //
DELIMITER ;
CALL add_featured_index();
DROP PROCEDURE IF EXISTS add_featured_index;

-- =============================================================================
-- Additional indexes for analytics and common queries
-- =============================================================================
DROP PROCEDURE IF EXISTS add_analytics_indexes;
DELIMITER //
CREATE PROCEDURE add_analytics_indexes()
BEGIN
    IF NOT EXISTS (
        SELECT 1 FROM information_schema.STATISTICS
        WHERE TABLE_SCHEMA = DATABASE()
        AND TABLE_NAME = 'job_vacancy_applications'
        AND INDEX_NAME = 'idx_app_vacancy_status'
    ) THEN
        ALTER TABLE `job_vacancy_applications` ADD INDEX `idx_app_vacancy_status` (`vacancy_id`, `status`);
    END IF;

    IF NOT EXISTS (
        SELECT 1 FROM information_schema.STATISTICS
        WHERE TABLE_SCHEMA = DATABASE()
        AND TABLE_NAME = 'job_alerts'
        AND INDEX_NAME = 'idx_job_alerts_is_active'
    ) THEN
        ALTER TABLE `job_alerts` ADD INDEX `idx_job_alerts_is_active` (`is_active`);
    END IF;
END //
DELIMITER ;
CALL add_analytics_indexes();
DROP PROCEDURE IF EXISTS add_analytics_indexes;

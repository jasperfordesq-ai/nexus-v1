-- Migration: Add job vacancies tables
-- Date: 2026-03-01
-- Description: Creates job_vacancies and job_vacancy_applications tables for the Jobs module

CREATE TABLE IF NOT EXISTS `job_vacancies` (
    `id` int(11) UNSIGNED NOT NULL AUTO_INCREMENT,
    `tenant_id` int(11) UNSIGNED NOT NULL,
    `user_id` int(11) NOT NULL,
    `organization_id` int(11) UNSIGNED DEFAULT NULL,
    `title` varchar(255) NOT NULL,
    `description` text NOT NULL,
    `location` varchar(255) DEFAULT NULL,
    `is_remote` tinyint(1) NOT NULL DEFAULT 0,
    `type` enum('paid','volunteer','timebank') NOT NULL DEFAULT 'paid',
    `commitment` enum('full_time','part_time','flexible','one_off') NOT NULL DEFAULT 'flexible',
    `category` varchar(100) DEFAULT NULL,
    `skills_required` text DEFAULT NULL,
    `hours_per_week` decimal(5,1) DEFAULT NULL,
    `time_credits` decimal(10,2) DEFAULT NULL,
    `contact_email` varchar(255) DEFAULT NULL,
    `contact_phone` varchar(50) DEFAULT NULL,
    `deadline` datetime DEFAULT NULL,
    `status` enum('open','closed','filled','draft') NOT NULL DEFAULT 'open',
    `views_count` int(11) NOT NULL DEFAULT 0,
    `applications_count` int(11) NOT NULL DEFAULT 0,
    `created_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at` datetime DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    INDEX `idx_job_vacancies_tenant_status` (`tenant_id`, `status`),
    INDEX `idx_job_vacancies_tenant_user` (`tenant_id`, `user_id`),
    INDEX `idx_job_vacancies_tenant_type` (`tenant_id`, `type`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `job_vacancy_applications` (
    `id` int(11) UNSIGNED NOT NULL AUTO_INCREMENT,
    `vacancy_id` int(11) UNSIGNED NOT NULL,
    `user_id` int(11) NOT NULL,
    `message` text DEFAULT NULL,
    `status` enum('pending','reviewed','accepted','rejected','withdrawn') NOT NULL DEFAULT 'pending',
    `reviewer_notes` text DEFAULT NULL,
    `reviewed_by` int(11) DEFAULT NULL,
    `reviewed_at` datetime DEFAULT NULL,
    `created_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at` datetime DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    UNIQUE KEY `uq_vacancy_user` (`vacancy_id`, `user_id`),
    INDEX `idx_applications_vacancy` (`vacancy_id`),
    INDEX `idx_applications_user` (`user_id`),
    CONSTRAINT `fk_app_vacancy` FOREIGN KEY (`vacancy_id`) REFERENCES `job_vacancies` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

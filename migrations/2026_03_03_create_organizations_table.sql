-- Migration: Create organizations table
-- Date: 2026-03-03
-- Description: Creates the organizations table referenced by job_vacancies and org_wallets
-- Root cause: Table was referenced in job_vacancies schema and JobVacancyService but never created,
--             causing SQLSTATE[42S02] "Table 'nexus.organizations' doesn't exist" on the vacancies endpoint.

CREATE TABLE IF NOT EXISTS `organizations` (
    `id` int(11) UNSIGNED NOT NULL AUTO_INCREMENT,
    `tenant_id` int(11) UNSIGNED NOT NULL,
    `user_id` int(11) DEFAULT NULL COMMENT 'Owner/creator user',
    `name` varchar(255) NOT NULL,
    `description` text DEFAULT NULL,
    `website` varchar(500) DEFAULT NULL,
    `contact_email` varchar(255) DEFAULT NULL,
    `logo_url` varchar(500) DEFAULT NULL,
    `status` enum('active','inactive','pending') NOT NULL DEFAULT 'active',
    `created_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at` datetime DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    INDEX `idx_organizations_tenant` (`tenant_id`),
    INDEX `idx_organizations_tenant_status` (`tenant_id`, `status`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
COMMENT='Organizations/businesses that can post job vacancies';

-- Verify
SELECT
    CASE WHEN EXISTS (
        SELECT 1 FROM information_schema.TABLES
        WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'organizations'
    ) THEN '✓ organizations table created OK' ELSE '✗ FAILED' END AS result;

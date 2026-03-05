-- ===================================================================
-- CREATE GROUP RANKING LOGS TABLE
-- ===================================================================
-- Stores history of automatic ranking updates for auditing and debugging
-- This table is OPTIONAL but recommended for tracking changes over time
-- ===================================================================

CREATE TABLE IF NOT EXISTS `group_ranking_logs` (
    `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    `tenant_id` INT UNSIGNED NOT NULL,
    `ranking_type` VARCHAR(50) NOT NULL COMMENT 'local_hubs or community_groups',
    `stats_json` JSON NULL COMMENT 'Full ranking statistics and results',
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,

    INDEX `idx_tenant_type` (`tenant_id`, `ranking_type`),
    INDEX `idx_created_at` (`created_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ===================================================================
-- VERIFICATION
-- ===================================================================
SHOW CREATE TABLE `group_ranking_logs`;

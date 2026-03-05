-- Migration: Group Recommendation System Tables
-- Purpose: Support ML-powered group discovery and recommendation tracking
-- Date: 2026-01-10

-- Table: Track user interactions with recommendations (for learning)
CREATE TABLE IF NOT EXISTS `group_recommendation_interactions` (
    `id` BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    `tenant_id` INT UNSIGNED NOT NULL,
    `user_id` INT UNSIGNED NOT NULL,
    `group_id` INT UNSIGNED NOT NULL,
    `action` ENUM('viewed', 'clicked', 'joined', 'dismissed') NOT NULL,
    `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,

    INDEX `idx_tenant_user` (`tenant_id`, `user_id`),
    INDEX `idx_tenant_group` (`tenant_id`, `group_id`),
    INDEX `idx_created` (`created_at`),
    INDEX `idx_action` (`action`)

) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Table: Cache computed recommendations (optional performance optimization)
CREATE TABLE IF NOT EXISTS `group_recommendation_cache` (
    `id` BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    `tenant_id` INT UNSIGNED NOT NULL,
    `user_id` INT UNSIGNED NOT NULL,
    `group_id` INT UNSIGNED NOT NULL,
    `score` DECIMAL(5,4) NOT NULL COMMENT 'Recommendation score 0.0000-1.0000',
    `reason` VARCHAR(255) NULL,
    `algorithm` VARCHAR(50) NOT NULL COMMENT 'collaborative|content|location|activity',
    `computed_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `expires_at` DATETIME NOT NULL,

    INDEX `idx_tenant_user` (`tenant_id`, `user_id`, `expires_at`),
    INDEX `idx_tenant_group` (`tenant_id`, `group_id`),
    INDEX `idx_expires` (`expires_at`),

    UNIQUE KEY `unique_recommendation` (`tenant_id`, `user_id`, `group_id`, `algorithm`)

) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Optional: Add indexes to existing tables for recommendation performance
-- Only run if these columns exist and aren't already indexed

-- User location index (for location-based recommendations)
-- ALTER TABLE `users` ADD INDEX `idx_location` (`tenant_id`, `latitude`, `longitude`);

-- Group location index
-- ALTER TABLE `groups` ADD INDEX `idx_location` (`tenant_id`, `latitude`, `longitude`);

-- User bio/interests index (for content-based matching)
-- ALTER TABLE `users` ADD FULLTEXT INDEX `idx_bio_search` (`bio`, `interests`);

-- Group description index (for content-based matching)
-- ALTER TABLE `groups` ADD FULLTEXT INDEX `idx_description_search` (`name`, `description`);

-- NOTES:
-- 1. The recommendation_cache table is optional - use if you want to pre-compute
--    recommendations and cache them for 24 hours to reduce load
-- 2. The interactions table is essential for learning and improving recommendations
-- 3. Consider partitioning these tables by created_at for large deployments

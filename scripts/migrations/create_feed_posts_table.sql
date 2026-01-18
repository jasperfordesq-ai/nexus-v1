-- ============================================================
-- FEED POSTS TABLE MIGRATION
-- Creates the feed_posts table for social feed functionality
-- ============================================================
-- Run this SQL on your database to create the feed_posts table

CREATE TABLE IF NOT EXISTS `feed_posts` (
    `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
    `tenant_id` INT UNSIGNED NOT NULL DEFAULT 1,
    `user_id` INT UNSIGNED NOT NULL,
    `group_id` INT UNSIGNED NULL DEFAULT NULL COMMENT 'For group posts',
    `content` TEXT NULL,
    `emoji` VARCHAR(50) NULL DEFAULT NULL,
    `image_url` VARCHAR(500) NULL DEFAULT NULL,
    `video_url` VARCHAR(500) NULL DEFAULT NULL,
    `parent_id` INT UNSIGNED NULL DEFAULT NULL COMMENT 'For shared content',
    `parent_type` VARCHAR(50) NULL DEFAULT NULL COMMENT 'Type of shared content: post, listing, event, poll, etc.',
    `visibility` ENUM('public', 'friends', 'private') NOT NULL DEFAULT 'public',
    `likes_count` INT UNSIGNED NOT NULL DEFAULT 0,
    `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at` TIMESTAMP NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    INDEX `idx_tenant` (`tenant_id`),
    INDEX `idx_user` (`user_id`),
    INDEX `idx_group` (`group_id`),
    INDEX `idx_created` (`created_at`),
    INDEX `idx_tenant_created` (`tenant_id`, `created_at`),
    INDEX `idx_group_tenant` (`group_id`, `tenant_id`),
    INDEX `idx_user_tenant` (`user_id`, `tenant_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Add foreign keys if users and groups tables exist
-- ALTER TABLE `feed_posts` ADD CONSTRAINT `fk_feed_posts_user` FOREIGN KEY (`user_id`) REFERENCES `users`(`id`) ON DELETE CASCADE;
-- ALTER TABLE `feed_posts` ADD CONSTRAINT `fk_feed_posts_group` FOREIGN KEY (`group_id`) REFERENCES `groups`(`id`) ON DELETE SET NULL;

-- ============================================================
-- VERIFY TABLE CREATED
-- ============================================================
SELECT 'feed_posts table created successfully!' AS status;
SHOW COLUMNS FROM feed_posts;

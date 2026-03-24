-- Story archive and audience controls migration
-- Adds: story_archive table (auto-save expired stories), audience column on stories
-- 2026-03-24
-- Idempotent: uses IF NOT EXISTS / stored procedure checks

-- ============================================================================
-- 1. story_archive — Auto-save expired stories for later highlight curation
-- ============================================================================
CREATE TABLE IF NOT EXISTS `story_archive` (
  `id` BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  `tenant_id` INT NOT NULL,
  `user_id` INT UNSIGNED NOT NULL,
  `original_story_id` BIGINT UNSIGNED NOT NULL,
  `media_type` ENUM('image','text','poll','video') NOT NULL DEFAULT 'image',
  `media_url` TEXT DEFAULT NULL,
  `thumbnail_url` TEXT DEFAULT NULL,
  `text_content` VARCHAR(500) DEFAULT NULL,
  `text_style` JSON DEFAULT NULL,
  `background_color` VARCHAR(20) DEFAULT NULL,
  `background_gradient` VARCHAR(100) DEFAULT NULL,
  `duration` INT UNSIGNED NOT NULL DEFAULT 5,
  `video_duration` FLOAT DEFAULT NULL,
  `poll_question` VARCHAR(255) DEFAULT NULL,
  `poll_options` JSON DEFAULT NULL,
  `view_count` INT UNSIGNED NOT NULL DEFAULT 0,
  `original_created_at` TIMESTAMP NOT NULL,
  `archived_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  KEY `idx_archive_tenant_user` (`tenant_id`, `user_id`),
  KEY `idx_archive_original` (`original_story_id`),
  FOREIGN KEY (`tenant_id`) REFERENCES `tenants`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================================================
-- 2. Add audience column to stories — controls who can see the story
-- ============================================================================
DROP PROCEDURE IF EXISTS `add_stories_audience_column`;
DELIMITER //
CREATE PROCEDURE `add_stories_audience_column`()
BEGIN
    IF NOT EXISTS (
        SELECT 1 FROM INFORMATION_SCHEMA.COLUMNS
        WHERE TABLE_NAME = 'stories' AND COLUMN_NAME = 'audience'
    ) THEN
        ALTER TABLE `stories` ADD COLUMN `audience` ENUM('everyone','connections','close_friends') NOT NULL DEFAULT 'everyone' AFTER `poll_options`;
        ALTER TABLE `stories` ADD KEY `idx_audience` (`tenant_id`, `audience`);
    END IF;
END //
DELIMITER ;
CALL `add_stories_audience_column`();
DROP PROCEDURE IF EXISTS `add_stories_audience_column`;

-- ============================================================================
-- 3. close_friends — User-curated list of close friends for audience targeting
-- ============================================================================
CREATE TABLE IF NOT EXISTS `close_friends` (
  `user_id` INT UNSIGNED NOT NULL,
  `friend_id` INT UNSIGNED NOT NULL,
  `tenant_id` INT NOT NULL,
  `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`user_id`, `friend_id`),
  KEY `idx_close_friends_tenant` (`tenant_id`, `user_id`),
  FOREIGN KEY (`tenant_id`) REFERENCES `tenants`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

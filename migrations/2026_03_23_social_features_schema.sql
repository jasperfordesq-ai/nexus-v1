-- Social features schema migration
-- Adds missing tables: user_presence, stories, story_views, story_reactions,
-- story_poll_votes, story_highlights, story_highlight_items
-- Adds missing updated_at columns on feed_posts and categories
-- Idempotent: uses IF NOT EXISTS / IF NOT EXISTS checks throughout
-- 2026-03-23

-- ============================================================================
-- 1. user_presence — real-time user presence tracking
-- ============================================================================
CREATE TABLE IF NOT EXISTS `user_presence` (
  `user_id` int(10) unsigned NOT NULL,
  `tenant_id` int(11) NOT NULL,
  `status` enum('online','away','dnd','offline') NOT NULL DEFAULT 'offline',
  `custom_status` varchar(80) DEFAULT NULL,
  `status_emoji` varchar(10) DEFAULT NULL,
  `last_seen_at` timestamp NULL DEFAULT NULL,
  `last_activity_at` timestamp NULL DEFAULT NULL,
  `hide_presence` tinyint(1) NOT NULL DEFAULT 0,
  `created_at` timestamp NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`user_id`),
  KEY `idx_tenant_status` (`tenant_id`,`status`),
  KEY `idx_last_seen` (`tenant_id`,`last_seen_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================================================
-- 2. stories — 24-hour disappearing stories
-- ============================================================================
CREATE TABLE IF NOT EXISTS `stories` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `tenant_id` int(11) NOT NULL,
  `user_id` int(10) unsigned NOT NULL,
  `media_type` enum('image','text','poll') NOT NULL DEFAULT 'image',
  `media_url` text DEFAULT NULL,
  `thumbnail_url` text DEFAULT NULL,
  `text_content` varchar(500) DEFAULT NULL,
  `text_style` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL,
  `background_color` varchar(20) DEFAULT NULL,
  `background_gradient` varchar(100) DEFAULT NULL,
  `duration` int(10) unsigned NOT NULL DEFAULT 5,
  `poll_question` varchar(255) DEFAULT NULL,
  `poll_options` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL,
  `view_count` int(10) unsigned NOT NULL DEFAULT 0,
  `is_active` tinyint(1) NOT NULL DEFAULT 1,
  `expires_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `created_at` timestamp NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `idx_tenant_user` (`tenant_id`,`user_id`),
  KEY `idx_active_expires` (`tenant_id`,`is_active`,`expires_at`),
  KEY `idx_user_active` (`user_id`,`is_active`,`expires_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================================================
-- 3. story_views — tracks who viewed each story
-- ============================================================================
CREATE TABLE IF NOT EXISTS `story_views` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `story_id` bigint(20) unsigned NOT NULL,
  `viewer_id` int(10) unsigned NOT NULL,
  `viewed_at` timestamp NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `uk_story_viewer` (`story_id`,`viewer_id`),
  KEY `idx_story_views` (`story_id`),
  KEY `idx_viewer` (`viewer_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================================================
-- 4. story_reactions — emoji reactions on stories
-- ============================================================================
CREATE TABLE IF NOT EXISTS `story_reactions` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `story_id` bigint(20) unsigned NOT NULL,
  `user_id` int(10) unsigned NOT NULL,
  `reaction_type` varchar(20) NOT NULL,
  `created_at` timestamp NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `idx_story_reactions` (`story_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================================================
-- 5. story_poll_votes — votes on poll-type stories
-- ============================================================================
CREATE TABLE IF NOT EXISTS `story_poll_votes` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `story_id` bigint(20) unsigned NOT NULL,
  `user_id` int(10) unsigned NOT NULL,
  `option_index` tinyint(3) unsigned NOT NULL,
  `created_at` timestamp NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `uk_story_poll_vote` (`story_id`,`user_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================================================
-- 6. story_highlights — saved story collections
-- ============================================================================
CREATE TABLE IF NOT EXISTS `story_highlights` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `tenant_id` int(11) NOT NULL,
  `user_id` int(10) unsigned NOT NULL,
  `title` varchar(100) NOT NULL,
  `cover_url` text DEFAULT NULL,
  `display_order` tinyint(3) unsigned NOT NULL DEFAULT 0,
  `created_at` timestamp NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `idx_user_highlights` (`tenant_id`,`user_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================================================
-- 7. story_highlight_items — stories in a highlight
-- ============================================================================
CREATE TABLE IF NOT EXISTS `story_highlight_items` (
  `highlight_id` bigint(20) unsigned NOT NULL,
  `story_id` bigint(20) unsigned NOT NULL,
  `display_order` tinyint(3) unsigned NOT NULL DEFAULT 0,
  PRIMARY KEY (`highlight_id`,`story_id`),
  KEY `story_id` (`story_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================================================
-- 8. Add updated_at to feed_posts (if missing)
-- ============================================================================
SET @dbname = DATABASE();
SET @tablename = 'feed_posts';
SET @columnname = 'updated_at';
SET @preparedStatement = (SELECT IF(
  (SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS
   WHERE TABLE_SCHEMA = @dbname AND TABLE_NAME = @tablename AND COLUMN_NAME = @columnname) > 0,
  'SELECT 1',
  CONCAT('ALTER TABLE `', @tablename, '` ADD COLUMN `', @columnname, '` timestamp NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()')
));
PREPARE stmt FROM @preparedStatement;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

-- ============================================================================
-- 9. Add updated_at to categories (if missing)
-- ============================================================================
SET @tablename = 'categories';
SET @preparedStatement = (SELECT IF(
  (SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS
   WHERE TABLE_SCHEMA = @dbname AND TABLE_NAME = @tablename AND COLUMN_NAME = @columnname) > 0,
  'SELECT 1',
  CONCAT('ALTER TABLE `', @tablename, '` ADD COLUMN `', @columnname, '` timestamp NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()')
));
PREPARE stmt FROM @preparedStatement;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

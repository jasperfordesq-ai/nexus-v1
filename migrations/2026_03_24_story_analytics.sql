-- Story analytics migration
-- Tracks navigation taps, completion, and engagement per story
-- 2026-03-24

CREATE TABLE IF NOT EXISTS `story_analytics` (
  `id` BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  `story_id` BIGINT UNSIGNED NOT NULL,
  `viewer_id` INT UNSIGNED NOT NULL,
  `event_type` ENUM('view_start','view_complete','tap_forward','tap_back','tap_exit','swipe_next','swipe_prev') NOT NULL,
  `watch_duration_ms` INT UNSIGNED DEFAULT NULL,
  `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  KEY `idx_analytics_story` (`story_id`),
  KEY `idx_analytics_viewer` (`viewer_id`),
  KEY `idx_analytics_type` (`story_id`, `event_type`),
  FOREIGN KEY (`story_id`) REFERENCES `stories`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

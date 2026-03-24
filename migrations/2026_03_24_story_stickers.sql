-- Story stickers — draggable overlays on photo/video stories
-- Stores sticker positions and data per story
CREATE TABLE IF NOT EXISTS `story_stickers` (
  `id` BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  `story_id` BIGINT UNSIGNED NOT NULL,
  `sticker_type` ENUM('mention','location','link','emoji','text_tag') NOT NULL,
  `content` VARCHAR(500) NOT NULL,
  `metadata` JSON DEFAULT NULL,
  `position_x` FLOAT NOT NULL DEFAULT 50,
  `position_y` FLOAT NOT NULL DEFAULT 50,
  `rotation` FLOAT NOT NULL DEFAULT 0,
  `scale` FLOAT NOT NULL DEFAULT 1,
  `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  KEY `idx_sticker_story` (`story_id`),
  FOREIGN KEY (`story_id`) REFERENCES `stories`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

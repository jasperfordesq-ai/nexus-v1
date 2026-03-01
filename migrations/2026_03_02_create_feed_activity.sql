-- Migration: Create feed_activity table for unified feed aggregation
-- Date: 2026-03-02
-- Description: Replaces the N-query feed pattern with a single denormalized table.
--              Each content creation (post, listing, event, poll, goal, review, job,
--              challenge, volunteer) inserts a row here. Feed queries hit this single
--              table instead of querying 9 separate source tables.

CREATE TABLE IF NOT EXISTS `feed_activity` (
  `id` BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  `tenant_id` INT UNSIGNED NOT NULL,
  `user_id` INT UNSIGNED NOT NULL,
  `source_type` VARCHAR(20) NOT NULL COMMENT 'post, listing, event, poll, goal, review, job, challenge, volunteer',
  `source_id` INT UNSIGNED NOT NULL,
  `group_id` INT UNSIGNED DEFAULT NULL,
  `title` VARCHAR(500) DEFAULT NULL,
  `content` TEXT DEFAULT NULL,
  `image_url` VARCHAR(500) DEFAULT NULL,
  `metadata` JSON DEFAULT NULL COMMENT 'Type-specific fields: rating, receiver, job_type, location, etc.',
  `is_visible` TINYINT(1) NOT NULL DEFAULT 1,
  `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,

  -- Idempotent upserts: one activity row per content item per tenant
  UNIQUE KEY `uq_tenant_source` (`tenant_id`, `source_type`, `source_id`),

  -- Main feed: all visible items ordered by newest first
  INDEX `idx_main_feed` (`tenant_id`, `is_visible`, `created_at` DESC, `id` DESC),

  -- User profile feed: items by a specific user
  INDEX `idx_user_feed` (`tenant_id`, `user_id`, `is_visible`, `created_at` DESC, `id` DESC),

  -- Group feed: items in a specific group
  INDEX `idx_group_feed` (`tenant_id`, `group_id`, `is_visible`, `created_at` DESC, `id` DESC),

  -- Type-filtered feed: items of a specific type
  INDEX `idx_type_feed` (`tenant_id`, `source_type`, `is_visible`, `created_at` DESC, `id` DESC),

  -- Reverse lookup: find activity row for a source item (for hide/show/remove)
  INDEX `idx_source_lookup` (`source_type`, `source_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

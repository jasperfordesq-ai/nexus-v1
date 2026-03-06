-- CRM Module: member notes, coordinator tasks, onboarding funnel tracking
-- 2026-03-06

-- Admin notes on members (private coordinator annotations)
CREATE TABLE IF NOT EXISTS `member_notes` (
  `id` INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
  `tenant_id` INT UNSIGNED NOT NULL,
  `user_id` INT UNSIGNED NOT NULL COMMENT 'The member this note is about',
  `author_id` INT UNSIGNED NOT NULL COMMENT 'The admin who wrote the note',
  `content` TEXT NOT NULL,
  `category` ENUM('general','outreach','support','onboarding','concern','follow_up') NOT NULL DEFAULT 'general',
  `is_pinned` TINYINT(1) NOT NULL DEFAULT 0,
  `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  INDEX `idx_member_notes_tenant_user` (`tenant_id`, `user_id`),
  INDEX `idx_member_notes_author` (`tenant_id`, `author_id`),
  INDEX `idx_member_notes_category` (`tenant_id`, `category`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Coordinator follow-up tasks
CREATE TABLE IF NOT EXISTS `coordinator_tasks` (
  `id` INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
  `tenant_id` INT UNSIGNED NOT NULL,
  `assigned_to` INT UNSIGNED NOT NULL COMMENT 'Admin user this task is for',
  `user_id` INT UNSIGNED DEFAULT NULL COMMENT 'Optional: member this task relates to',
  `title` VARCHAR(255) NOT NULL,
  `description` TEXT DEFAULT NULL,
  `priority` ENUM('low','medium','high','urgent') NOT NULL DEFAULT 'medium',
  `status` ENUM('pending','in_progress','completed','cancelled') NOT NULL DEFAULT 'pending',
  `due_date` DATE DEFAULT NULL,
  `completed_at` DATETIME DEFAULT NULL,
  `created_by` INT UNSIGNED NOT NULL,
  `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  INDEX `idx_coordinator_tasks_tenant_assigned` (`tenant_id`, `assigned_to`, `status`),
  INDEX `idx_coordinator_tasks_tenant_user` (`tenant_id`, `user_id`),
  INDEX `idx_coordinator_tasks_due` (`tenant_id`, `due_date`),
  INDEX `idx_coordinator_tasks_status` (`tenant_id`, `status`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Member tags for CRM segmentation
CREATE TABLE IF NOT EXISTS `member_tags` (
  `id` INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
  `tenant_id` INT UNSIGNED NOT NULL,
  `user_id` INT UNSIGNED NOT NULL,
  `tag` VARCHAR(50) NOT NULL,
  `created_by` INT UNSIGNED NOT NULL,
  `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  UNIQUE KEY `uk_member_tags` (`tenant_id`, `user_id`, `tag`),
  INDEX `idx_member_tags_tag` (`tenant_id`, `tag`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

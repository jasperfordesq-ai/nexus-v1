-- ============================================================================
-- GOALS, POLLS, AND RESOURCES ENHANCEMENTS
-- ============================================================================
-- Migration: Add tables and columns for enhanced Goals, Polls, and Resources
-- Date: 2026-03-01
--
-- Features:
-- G1 - Goal templates
-- G3 - Goal check-ins
-- G4 - Goal reminders
-- G5 - Goal progress history
-- P1 - Ranked-choice voting
-- P2 - Poll categories/tags
-- P3 - Anonymous voting
-- R1 - Resource categories (hierarchical)
-- R2 - Rich content (content_type field)
-- R3 - Resource ordering (sort_order)
-- R4 - Knowledge base articles
-- ============================================================================

-- ============================================================================
-- G1: GOAL TEMPLATES
-- ============================================================================
CREATE TABLE IF NOT EXISTS `goal_templates` (
    `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    `tenant_id` INT UNSIGNED NOT NULL,
    `title` VARCHAR(255) NOT NULL,
    `description` TEXT NULL,
    `default_milestones` JSON NULL COMMENT 'JSON array of milestone objects: [{title, target_value}]',
    `category` VARCHAR(100) NULL,
    `default_target_value` DECIMAL(10,2) NOT NULL DEFAULT 0,
    `is_public` TINYINT(1) NOT NULL DEFAULT 1 COMMENT 'Whether this template is visible to all users',
    `created_by` INT UNSIGNED NULL,
    `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at` DATETIME NULL ON UPDATE CURRENT_TIMESTAMP,
    INDEX `idx_goal_templates_tenant` (`tenant_id`),
    INDEX `idx_goal_templates_category` (`tenant_id`, `category`),
    INDEX `idx_goal_templates_created_by` (`created_by`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

SELECT 'CREATED: goal_templates table' AS result;

-- ============================================================================
-- G3: GOAL CHECK-INS
-- ============================================================================
CREATE TABLE IF NOT EXISTS `goal_checkins` (
    `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    `goal_id` INT UNSIGNED NOT NULL,
    `user_id` INT UNSIGNED NOT NULL,
    `tenant_id` INT UNSIGNED NOT NULL,
    `progress_percent` DECIMAL(5,2) NULL COMMENT 'Progress percentage at time of check-in',
    `note` TEXT NULL COMMENT 'Free-text check-in note',
    `mood` ENUM('great', 'good', 'neutral', 'struggling', 'stuck') NULL COMMENT 'Mood indicator',
    `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    INDEX `idx_goal_checkins_goal` (`goal_id`),
    INDEX `idx_goal_checkins_user` (`user_id`),
    INDEX `idx_goal_checkins_tenant` (`tenant_id`),
    INDEX `idx_goal_checkins_created` (`goal_id`, `created_at` DESC)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

SELECT 'CREATED: goal_checkins table' AS result;

-- ============================================================================
-- G4: GOAL REMINDERS
-- ============================================================================
CREATE TABLE IF NOT EXISTS `goal_reminders` (
    `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    `goal_id` INT UNSIGNED NOT NULL,
    `user_id` INT UNSIGNED NOT NULL,
    `tenant_id` INT UNSIGNED NOT NULL,
    `frequency` ENUM('daily', 'weekly', 'biweekly', 'monthly') NOT NULL DEFAULT 'weekly',
    `next_reminder_at` DATETIME NULL,
    `last_sent_at` DATETIME NULL,
    `enabled` TINYINT(1) NOT NULL DEFAULT 1,
    `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at` DATETIME NULL ON UPDATE CURRENT_TIMESTAMP,
    UNIQUE INDEX `idx_goal_reminders_unique` (`goal_id`, `user_id`),
    INDEX `idx_goal_reminders_next` (`enabled`, `next_reminder_at`),
    INDEX `idx_goal_reminders_tenant` (`tenant_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

SELECT 'CREATED: goal_reminders table' AS result;

-- ============================================================================
-- G5: GOAL PROGRESS LOG
-- ============================================================================
CREATE TABLE IF NOT EXISTS `goal_progress_log` (
    `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    `goal_id` INT UNSIGNED NOT NULL,
    `tenant_id` INT UNSIGNED NOT NULL,
    `event_type` ENUM('progress_update', 'milestone_reached', 'checkin', 'status_change', 'buddy_joined', 'created', 'completed') NOT NULL,
    `old_value` VARCHAR(255) NULL,
    `new_value` VARCHAR(255) NULL,
    `metadata` JSON NULL COMMENT 'Additional data about the event',
    `created_by` INT UNSIGNED NULL,
    `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    INDEX `idx_goal_progress_log_goal` (`goal_id`, `created_at` DESC),
    INDEX `idx_goal_progress_log_tenant` (`tenant_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

SELECT 'CREATED: goal_progress_log table' AS result;

-- Add checkin_frequency to goals table for configurable check-in reminders
ALTER TABLE `goals`
ADD COLUMN IF NOT EXISTS `checkin_frequency` ENUM('none', 'weekly', 'biweekly') NOT NULL DEFAULT 'none'
COMMENT 'How often to prompt for check-ins';

ALTER TABLE `goals`
ADD COLUMN IF NOT EXISTS `last_checkin_at` DATETIME NULL
COMMENT 'Timestamp of last check-in';

ALTER TABLE `goals`
ADD COLUMN IF NOT EXISTS `template_id` INT UNSIGNED NULL
COMMENT 'Goal template this was created from';

SELECT 'GOALS TABLE: Added checkin_frequency, last_checkin_at, template_id columns' AS result;

-- ============================================================================
-- P1: RANKED-CHOICE VOTING
-- ============================================================================
CREATE TABLE IF NOT EXISTS `poll_rankings` (
    `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    `poll_id` INT UNSIGNED NOT NULL,
    `user_id` INT UNSIGNED NOT NULL,
    `option_id` INT UNSIGNED NOT NULL,
    `rank` INT UNSIGNED NOT NULL COMMENT 'Rank position (1 = first choice)',
    `tenant_id` INT UNSIGNED NOT NULL,
    `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    UNIQUE INDEX `idx_poll_rankings_unique` (`poll_id`, `user_id`, `option_id`),
    INDEX `idx_poll_rankings_poll` (`poll_id`),
    INDEX `idx_poll_rankings_user` (`poll_id`, `user_id`),
    INDEX `idx_poll_rankings_tenant` (`tenant_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

SELECT 'CREATED: poll_rankings table' AS result;

-- Add poll_type to polls table to distinguish standard vs ranked
ALTER TABLE `polls`
ADD COLUMN IF NOT EXISTS `poll_type` ENUM('standard', 'ranked') NOT NULL DEFAULT 'standard'
COMMENT 'Type of poll voting mechanism';

-- Ensure poll_votes has created_at for export
ALTER TABLE `poll_votes`
ADD COLUMN IF NOT EXISTS `created_at` DATETIME NULL DEFAULT CURRENT_TIMESTAMP;

-- ============================================================================
-- P2: POLL CATEGORIES AND TAGS
-- ============================================================================
ALTER TABLE `polls`
ADD COLUMN IF NOT EXISTS `category` VARCHAR(100) NULL
COMMENT 'Poll category (e.g., governance, feedback, social)';

ALTER TABLE `polls`
ADD COLUMN IF NOT EXISTS `tags` JSON NULL
COMMENT 'JSON array of tag strings';

SELECT 'POLLS TABLE: Added poll_type, category, tags columns' AS result;

-- ============================================================================
-- P3: ANONYMOUS VOTING
-- ============================================================================
ALTER TABLE `polls`
ADD COLUMN IF NOT EXISTS `is_anonymous` TINYINT(1) NOT NULL DEFAULT 0
COMMENT 'When true, voter identities are hidden in results';

SELECT 'POLLS TABLE: Added is_anonymous column' AS result;

-- ============================================================================
-- R1: RESOURCE CATEGORIES (HIERARCHICAL)
-- ============================================================================
CREATE TABLE IF NOT EXISTS `resource_categories` (
    `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    `tenant_id` INT UNSIGNED NOT NULL,
    `name` VARCHAR(255) NOT NULL,
    `slug` VARCHAR(255) NOT NULL,
    `parent_id` INT UNSIGNED NULL COMMENT 'Parent category for hierarchy',
    `sort_order` INT NOT NULL DEFAULT 0,
    `icon` VARCHAR(100) NULL COMMENT 'Icon identifier (e.g., lucide icon name)',
    `description` TEXT NULL,
    `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at` DATETIME NULL ON UPDATE CURRENT_TIMESTAMP,
    INDEX `idx_resource_categories_tenant` (`tenant_id`),
    INDEX `idx_resource_categories_parent` (`parent_id`),
    INDEX `idx_resource_categories_slug` (`tenant_id`, `slug`),
    INDEX `idx_resource_categories_sort` (`tenant_id`, `sort_order`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

SELECT 'CREATED: resource_categories table' AS result;

-- ============================================================================
-- R2: RICH CONTENT (content_type field)
-- ============================================================================
ALTER TABLE `resources`
ADD COLUMN IF NOT EXISTS `content_type` ENUM('plain', 'html', 'markdown') NOT NULL DEFAULT 'plain'
COMMENT 'Content format type for rich content rendering';

ALTER TABLE `resources`
ADD COLUMN IF NOT EXISTS `content_body` TEXT NULL
COMMENT 'Rich content body (HTML/Markdown) for text-based resources';

SELECT 'RESOURCES TABLE: Added content_type and content_body columns' AS result;

-- ============================================================================
-- R3: RESOURCE ORDERING
-- ============================================================================
ALTER TABLE `resources`
ADD COLUMN IF NOT EXISTS `sort_order` INT NOT NULL DEFAULT 0
COMMENT 'Sort order for manual ordering (lower = first)';

SELECT 'RESOURCES TABLE: Added sort_order column' AS result;

-- ============================================================================
-- R4: KNOWLEDGE BASE ARTICLES
-- ============================================================================
CREATE TABLE IF NOT EXISTS `knowledge_base_articles` (
    `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    `tenant_id` INT UNSIGNED NOT NULL,
    `title` VARCHAR(255) NOT NULL,
    `slug` VARCHAR(255) NOT NULL,
    `content` LONGTEXT NULL,
    `content_type` ENUM('plain', 'html', 'markdown') NOT NULL DEFAULT 'html',
    `category_id` INT UNSIGNED NULL COMMENT 'FK to resource_categories',
    `parent_article_id` INT UNSIGNED NULL COMMENT 'Parent article for nested structure',
    `sort_order` INT NOT NULL DEFAULT 0,
    `is_published` TINYINT(1) NOT NULL DEFAULT 0,
    `views_count` INT UNSIGNED NOT NULL DEFAULT 0,
    `helpful_yes` INT UNSIGNED NOT NULL DEFAULT 0 COMMENT 'Was this helpful? Yes count',
    `helpful_no` INT UNSIGNED NOT NULL DEFAULT 0 COMMENT 'Was this helpful? No count',
    `created_by` INT UNSIGNED NULL,
    `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at` DATETIME NULL ON UPDATE CURRENT_TIMESTAMP,
    INDEX `idx_kb_articles_tenant` (`tenant_id`),
    INDEX `idx_kb_articles_slug` (`tenant_id`, `slug`),
    INDEX `idx_kb_articles_category` (`category_id`),
    INDEX `idx_kb_articles_parent` (`parent_article_id`),
    INDEX `idx_kb_articles_published` (`tenant_id`, `is_published`, `sort_order`),
    INDEX `idx_kb_articles_created_by` (`created_by`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Track "Was this helpful?" feedback per user
CREATE TABLE IF NOT EXISTS `knowledge_base_feedback` (
    `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    `article_id` INT UNSIGNED NOT NULL,
    `user_id` INT UNSIGNED NULL COMMENT 'Null for anonymous feedback',
    `tenant_id` INT UNSIGNED NOT NULL,
    `is_helpful` TINYINT(1) NOT NULL,
    `comment` TEXT NULL,
    `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    UNIQUE INDEX `idx_kb_feedback_unique` (`article_id`, `user_id`),
    INDEX `idx_kb_feedback_tenant` (`tenant_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

SELECT 'CREATED: knowledge_base_articles and knowledge_base_feedback tables' AS result;

-- ============================================================================
-- MIGRATION COMPLETE
-- ============================================================================
-- Summary:
-- + goal_templates table (G1)
-- + goal_checkins table (G3)
-- + goal_reminders table (G4)
-- + goal_progress_log table (G5)
-- + goals.checkin_frequency, last_checkin_at, template_id columns
-- + poll_rankings table (P1)
-- + polls.poll_type column (P1)
-- + polls.category, tags columns (P2)
-- + polls.is_anonymous column (P3)
-- + resource_categories table (R1)
-- + resources.content_type, content_body columns (R2)
-- + resources.sort_order column (R3)
-- + knowledge_base_articles table (R4)
-- + knowledge_base_feedback table (R4)
-- ============================================================================

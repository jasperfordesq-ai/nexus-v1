-- ============================================================================
-- IDEATION CHALLENGES: FULL INNOVATION PIPELINE MIGRATION
-- ============================================================================
-- Date: 2026-03-01
-- Description: Complete schema for the ideation innovation pipeline:
--   I1  - Challenge categories & tagging (normalized tables)
--   I2  - Rich media idea submissions
--   I3  - Idea-to-Team conversion tracking
--   I4  - Team chatrooms (group discussion channels)
--   I5  - Team task management
--   I6  - Team document sharing
--   I7  - Campaign integration
--   I8  - (already done: challenge_favorites)
--   I9  - Challenge templates
--   I10 - Challenge impact tracking
--   I11 - Status lifecycle enhancements (evaluating, archived)
--   I12 - (already done: duplicate logic in PHP)
-- ============================================================================

-- ============================================================================
-- I1: CHALLENGE CATEGORIES & TAGS (normalized)
-- ============================================================================

-- Category taxonomy per tenant
CREATE TABLE IF NOT EXISTS `challenge_categories` (
    `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    `tenant_id` INT UNSIGNED NOT NULL,
    `name` VARCHAR(100) NOT NULL,
    `slug` VARCHAR(100) NOT NULL,
    `icon` VARCHAR(50) DEFAULT NULL COMMENT 'Lucide icon name e.g. Leaf, Cpu, Heart',
    `color` VARCHAR(20) DEFAULT NULL COMMENT 'Tailwind color e.g. blue, green, amber',
    `sort_order` INT NOT NULL DEFAULT 0,
    `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY `uk_tenant_slug` (`tenant_id`, `slug`),
    INDEX `idx_tenant_sort` (`tenant_id`, `sort_order`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Tag pool per tenant
CREATE TABLE IF NOT EXISTS `challenge_tags` (
    `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    `tenant_id` INT UNSIGNED NOT NULL,
    `name` VARCHAR(100) NOT NULL,
    `slug` VARCHAR(100) NOT NULL,
    `tag_type` ENUM('interest', 'skill', 'general') NOT NULL DEFAULT 'general',
    `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY `uk_tenant_slug` (`tenant_id`, `slug`),
    INDEX `idx_tenant_type` (`tenant_id`, `tag_type`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Link challenges to categories (many-to-one via category_id column)
ALTER TABLE `ideation_challenges`
ADD COLUMN IF NOT EXISTS `category_id` INT UNSIGNED DEFAULT NULL COMMENT 'FK to challenge_categories';

-- Link challenges to tags (many-to-many)
CREATE TABLE IF NOT EXISTS `challenge_tag_links` (
    `challenge_id` INT UNSIGNED NOT NULL,
    `tag_id` INT UNSIGNED NOT NULL,
    PRIMARY KEY (`challenge_id`, `tag_id`),
    INDEX `idx_tag` (`tag_id`),
    CONSTRAINT `fk_ctlink_challenge` FOREIGN KEY (`challenge_id`) REFERENCES `ideation_challenges` (`id`) ON DELETE CASCADE,
    CONSTRAINT `fk_ctlink_tag` FOREIGN KEY (`tag_id`) REFERENCES `challenge_tags` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================================================
-- I2: RICH MEDIA IDEA SUBMISSIONS
-- ============================================================================

CREATE TABLE IF NOT EXISTS `idea_media` (
    `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    `idea_id` INT UNSIGNED NOT NULL,
    `tenant_id` INT UNSIGNED NOT NULL,
    `media_type` ENUM('image', 'video', 'document', 'link') NOT NULL DEFAULT 'image',
    `url` VARCHAR(1000) NOT NULL,
    `caption` VARCHAR(500) DEFAULT NULL,
    `sort_order` INT NOT NULL DEFAULT 0,
    `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    INDEX `idx_idea` (`idea_id`),
    INDEX `idx_tenant` (`tenant_id`),
    CONSTRAINT `fk_media_idea` FOREIGN KEY (`idea_id`) REFERENCES `challenge_ideas` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================================================
-- I3: IDEA → TEAM CONVERSION TRACKING
-- ============================================================================

CREATE TABLE IF NOT EXISTS `idea_team_links` (
    `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    `idea_id` INT UNSIGNED NOT NULL,
    `group_id` INT UNSIGNED NOT NULL,
    `challenge_id` INT UNSIGNED NOT NULL,
    `tenant_id` INT UNSIGNED NOT NULL,
    `converted_by` INT NOT NULL COMMENT 'User who initiated conversion',
    `converted_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY `uk_idea_group` (`idea_id`, `group_id`),
    INDEX `idx_group` (`group_id`),
    INDEX `idx_challenge` (`challenge_id`),
    INDEX `idx_tenant` (`tenant_id`),
    CONSTRAINT `fk_itl_idea` FOREIGN KEY (`idea_id`) REFERENCES `challenge_ideas` (`id`) ON DELETE CASCADE,
    CONSTRAINT `fk_itl_challenge` FOREIGN KEY (`challenge_id`) REFERENCES `ideation_challenges` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================================================
-- I4: TEAM CHATROOMS (group discussion channels)
-- ============================================================================

CREATE TABLE IF NOT EXISTS `group_chatrooms` (
    `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    `group_id` INT UNSIGNED NOT NULL,
    `tenant_id` INT UNSIGNED NOT NULL,
    `name` VARCHAR(100) NOT NULL,
    `description` VARCHAR(500) DEFAULT NULL,
    `created_by` INT NOT NULL,
    `is_default` TINYINT(1) NOT NULL DEFAULT 0 COMMENT 'Default "General" channel',
    `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    INDEX `idx_group` (`group_id`, `tenant_id`),
    INDEX `idx_tenant` (`tenant_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Messages in chatrooms
CREATE TABLE IF NOT EXISTS `group_chatroom_messages` (
    `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    `chatroom_id` INT UNSIGNED NOT NULL,
    `user_id` INT NOT NULL,
    `body` TEXT NOT NULL,
    `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at` DATETIME DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
    INDEX `idx_chatroom` (`chatroom_id`, `created_at`),
    CONSTRAINT `fk_msg_chatroom` FOREIGN KEY (`chatroom_id`) REFERENCES `group_chatrooms` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================================================
-- I5: TEAM TASK MANAGEMENT
-- ============================================================================

CREATE TABLE IF NOT EXISTS `team_tasks` (
    `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    `group_id` INT UNSIGNED NOT NULL,
    `tenant_id` INT UNSIGNED NOT NULL,
    `title` VARCHAR(255) NOT NULL,
    `description` TEXT DEFAULT NULL,
    `assigned_to` INT DEFAULT NULL COMMENT 'User ID of assignee',
    `status` ENUM('todo', 'in_progress', 'done') NOT NULL DEFAULT 'todo',
    `priority` ENUM('low', 'medium', 'high', 'urgent') NOT NULL DEFAULT 'medium',
    `due_date` DATE DEFAULT NULL,
    `created_by` INT NOT NULL,
    `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `completed_at` DATETIME DEFAULT NULL,
    `updated_at` DATETIME DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
    INDEX `idx_group` (`group_id`, `tenant_id`),
    INDEX `idx_assigned` (`assigned_to`),
    INDEX `idx_status` (`group_id`, `status`),
    INDEX `idx_due` (`group_id`, `due_date`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================================================
-- I6: TEAM DOCUMENT SHARING
-- ============================================================================

CREATE TABLE IF NOT EXISTS `team_documents` (
    `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    `group_id` INT UNSIGNED NOT NULL,
    `tenant_id` INT UNSIGNED NOT NULL,
    `title` VARCHAR(255) NOT NULL,
    `file_path` VARCHAR(500) NOT NULL,
    `file_type` VARCHAR(100) DEFAULT NULL,
    `file_size` INT UNSIGNED DEFAULT NULL COMMENT 'Bytes',
    `uploaded_by` INT NOT NULL,
    `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    INDEX `idx_group` (`group_id`, `tenant_id`),
    INDEX `idx_uploader` (`uploaded_by`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================================================
-- I7: CAMPAIGN INTEGRATION
-- ============================================================================

CREATE TABLE IF NOT EXISTS `campaigns` (
    `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    `tenant_id` INT UNSIGNED NOT NULL,
    `title` VARCHAR(255) NOT NULL,
    `description` TEXT DEFAULT NULL,
    `cover_image` VARCHAR(500) DEFAULT NULL,
    `status` ENUM('draft', 'active', 'completed', 'archived') NOT NULL DEFAULT 'draft',
    `start_date` DATE DEFAULT NULL,
    `end_date` DATE DEFAULT NULL,
    `created_by` INT NOT NULL,
    `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at` DATETIME DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
    INDEX `idx_tenant_status` (`tenant_id`, `status`),
    INDEX `idx_dates` (`tenant_id`, `start_date`, `end_date`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Link challenges to campaigns (many-to-many)
CREATE TABLE IF NOT EXISTS `campaign_challenges` (
    `campaign_id` INT UNSIGNED NOT NULL,
    `challenge_id` INT UNSIGNED NOT NULL,
    `sort_order` INT NOT NULL DEFAULT 0,
    PRIMARY KEY (`campaign_id`, `challenge_id`),
    INDEX `idx_challenge` (`challenge_id`),
    CONSTRAINT `fk_cc_campaign` FOREIGN KEY (`campaign_id`) REFERENCES `campaigns` (`id`) ON DELETE CASCADE,
    CONSTRAINT `fk_cc_challenge` FOREIGN KEY (`challenge_id`) REFERENCES `ideation_challenges` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================================================
-- I9: CHALLENGE TEMPLATES
-- ============================================================================

CREATE TABLE IF NOT EXISTS `challenge_templates` (
    `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    `tenant_id` INT UNSIGNED NOT NULL,
    `title` VARCHAR(255) NOT NULL,
    `description` TEXT DEFAULT NULL,
    `default_tags` JSON DEFAULT NULL COMMENT 'Array of tag names',
    `default_category_id` INT UNSIGNED DEFAULT NULL,
    `evaluation_criteria` JSON DEFAULT NULL COMMENT 'Array of criteria strings',
    `prize_description` TEXT DEFAULT NULL,
    `max_ideas_per_user` INT DEFAULT NULL,
    `created_by` INT NOT NULL,
    `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at` DATETIME DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
    INDEX `idx_tenant` (`tenant_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================================================
-- I10: CHALLENGE IMPACT / OUTCOME TRACKING
-- ============================================================================

CREATE TABLE IF NOT EXISTS `challenge_outcomes` (
    `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    `challenge_id` INT UNSIGNED NOT NULL,
    `winning_idea_id` INT UNSIGNED DEFAULT NULL,
    `tenant_id` INT UNSIGNED NOT NULL,
    `status` ENUM('not_started', 'in_progress', 'implemented', 'abandoned') NOT NULL DEFAULT 'not_started',
    `impact_description` TEXT DEFAULT NULL,
    `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at` DATETIME DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
    UNIQUE KEY `uk_challenge` (`challenge_id`),
    INDEX `idx_tenant` (`tenant_id`),
    CONSTRAINT `fk_outcome_challenge` FOREIGN KEY (`challenge_id`) REFERENCES `ideation_challenges` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================================================
-- I11: STATUS LIFECYCLE ENHANCEMENTS
-- ============================================================================
-- Add 'evaluating' and 'archived' to the challenge status enum
-- MariaDB requires ALTER TABLE MODIFY to change ENUM values
ALTER TABLE `ideation_challenges`
MODIFY COLUMN `status` ENUM('draft', 'open', 'voting', 'evaluating', 'closed', 'archived') NOT NULL DEFAULT 'draft';

-- Add evaluation_criteria column to challenges
ALTER TABLE `ideation_challenges`
ADD COLUMN IF NOT EXISTS `evaluation_criteria` JSON DEFAULT NULL COMMENT 'Array of evaluation criteria';

-- ============================================================================
-- VERIFICATION
-- ============================================================================
-- SHOW TABLES LIKE 'challenge_%';
-- SHOW TABLES LIKE 'idea_%';
-- SHOW TABLES LIKE 'team_%';
-- SHOW TABLES LIKE 'group_chatroom%';
-- SHOW TABLES LIKE 'campaign%';
-- DESCRIBE ideation_challenges;

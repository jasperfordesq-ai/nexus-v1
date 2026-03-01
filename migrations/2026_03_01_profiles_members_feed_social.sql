-- ============================================================================
-- Migration: Profiles/Members + Feed/Social Features
-- Date: 2026-03-01
-- Features: M1 (Skill Taxonomy), M2 (Availability), M3 (Endorsements),
--           M4 (Activity Dashboard), M5 (Verification Badges), M6 (Sub-accounts),
--           F2 (Post Sharing), F4 (Hashtags)
-- All statements are idempotent (IF NOT EXISTS / IF EXISTS)
-- ============================================================================

-- ============================================================================
-- M1: SKILL TAXONOMY — Hierarchical skill categories
-- ============================================================================

CREATE TABLE IF NOT EXISTS `skill_categories` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `tenant_id` int(11) NOT NULL,
  `name` varchar(100) NOT NULL,
  `slug` varchar(120) NOT NULL,
  `parent_id` int(11) DEFAULT NULL,
  `description` text DEFAULT NULL,
  `icon` varchar(50) DEFAULT NULL,
  `display_order` int(11) NOT NULL DEFAULT 0,
  `is_active` tinyint(1) NOT NULL DEFAULT 1,
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_tenant` (`tenant_id`),
  KEY `idx_parent` (`parent_id`),
  KEY `idx_tenant_slug` (`tenant_id`, `slug`),
  KEY `idx_tenant_parent` (`tenant_id`, `parent_id`),
  CONSTRAINT `fk_skill_cat_parent` FOREIGN KEY (`parent_id`) REFERENCES `skill_categories` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `user_skills` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `user_id` int(11) NOT NULL,
  `tenant_id` int(11) NOT NULL,
  `category_id` int(11) DEFAULT NULL,
  `skill_name` varchar(100) NOT NULL,
  `proficiency` enum('beginner','intermediate','advanced','expert') DEFAULT 'intermediate',
  `is_offering` tinyint(1) NOT NULL DEFAULT 1,
  `is_requesting` tinyint(1) NOT NULL DEFAULT 0,
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_user_tenant` (`user_id`, `tenant_id`),
  KEY `idx_category` (`category_id`),
  KEY `idx_skill_name` (`skill_name`),
  KEY `idx_tenant_offering` (`tenant_id`, `is_offering`),
  KEY `idx_tenant_requesting` (`tenant_id`, `is_requesting`),
  CONSTRAINT `fk_user_skills_cat` FOREIGN KEY (`category_id`) REFERENCES `skill_categories` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================================================
-- M2: AVAILABILITY CALENDAR
-- ============================================================================

CREATE TABLE IF NOT EXISTS `member_availability` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `user_id` int(11) NOT NULL,
  `tenant_id` int(11) NOT NULL,
  `day_of_week` tinyint(1) NOT NULL COMMENT '0=Sunday, 6=Saturday',
  `start_time` time NOT NULL,
  `end_time` time NOT NULL,
  `is_recurring` tinyint(1) NOT NULL DEFAULT 1,
  `specific_date` date DEFAULT NULL COMMENT 'For one-off availability',
  `note` varchar(255) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_user_tenant` (`user_id`, `tenant_id`),
  KEY `idx_day` (`tenant_id`, `day_of_week`),
  KEY `idx_specific_date` (`tenant_id`, `specific_date`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================================================
-- M3: SKILL ENDORSEMENTS
-- ============================================================================

CREATE TABLE IF NOT EXISTS `skill_endorsements` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `endorser_id` int(11) NOT NULL,
  `endorsed_id` int(11) NOT NULL,
  `skill_id` int(11) DEFAULT NULL COMMENT 'References user_skills.id',
  `skill_name` varchar(100) NOT NULL COMMENT 'Denormalized for display',
  `tenant_id` int(11) NOT NULL,
  `comment` varchar(500) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `unique_endorsement` (`endorser_id`, `endorsed_id`, `skill_name`, `tenant_id`),
  KEY `idx_endorsed_tenant` (`endorsed_id`, `tenant_id`),
  KEY `idx_endorser_tenant` (`endorser_id`, `tenant_id`),
  KEY `idx_skill` (`skill_id`),
  KEY `idx_tenant_skill` (`tenant_id`, `skill_name`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================================================
-- M5: MEMBER VERIFICATION BADGES
-- ============================================================================

CREATE TABLE IF NOT EXISTS `member_verification_badges` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `user_id` int(11) NOT NULL,
  `tenant_id` int(11) NOT NULL,
  `badge_type` varchar(50) NOT NULL COMMENT 'email_verified, phone_verified, id_verified, dbs_checked, admin_verified',
  `verified_by` int(11) DEFAULT NULL COMMENT 'Admin user_id who granted',
  `verification_note` varchar(500) DEFAULT NULL,
  `granted_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `expires_at` timestamp NULL DEFAULT NULL,
  `revoked_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `unique_user_badge` (`user_id`, `tenant_id`, `badge_type`),
  KEY `idx_user_tenant` (`user_id`, `tenant_id`),
  KEY `idx_badge_type` (`tenant_id`, `badge_type`),
  KEY `idx_verified_by` (`verified_by`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================================================
-- M6: SUB-ACCOUNTS (Family/Care home accounts)
-- ============================================================================

CREATE TABLE IF NOT EXISTS `account_relationships` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `parent_user_id` int(11) NOT NULL,
  `child_user_id` int(11) NOT NULL,
  `tenant_id` int(11) NOT NULL,
  `relationship_type` varchar(50) NOT NULL DEFAULT 'family' COMMENT 'family, guardian, carer, organization',
  `permissions` text DEFAULT NULL COMMENT 'JSON: {can_view_activity, can_manage_listings, can_transact}',
  `status` enum('active','pending','revoked') NOT NULL DEFAULT 'pending',
  `approved_at` timestamp NULL DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `unique_relationship` (`parent_user_id`, `child_user_id`, `tenant_id`),
  KEY `idx_parent` (`parent_user_id`, `tenant_id`),
  KEY `idx_child` (`child_user_id`, `tenant_id`),
  KEY `idx_tenant_status` (`tenant_id`, `status`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================================================
-- F2: POST SHARING / REPOSTING
-- ============================================================================

CREATE TABLE IF NOT EXISTS `post_shares` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `user_id` int(11) NOT NULL COMMENT 'User who shared',
  `tenant_id` int(11) NOT NULL,
  `original_post_id` int(11) NOT NULL COMMENT 'Original feed_posts.id',
  `original_type` varchar(50) NOT NULL DEFAULT 'post' COMMENT 'post, listing, event',
  `shared_post_id` int(11) DEFAULT NULL COMMENT 'The new feed_posts.id created by sharing',
  `comment` text DEFAULT NULL COMMENT 'Optional comment when sharing',
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_original` (`original_post_id`, `tenant_id`),
  KEY `idx_user` (`user_id`, `tenant_id`),
  KEY `idx_created` (`tenant_id`, `created_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Add share_count column to feed_posts if it doesn't exist
ALTER TABLE `feed_posts` ADD COLUMN IF NOT EXISTS `share_count` int(11) NOT NULL DEFAULT 0;
ALTER TABLE `feed_posts` ADD COLUMN IF NOT EXISTS `original_post_id` int(11) DEFAULT NULL COMMENT 'For reposts: ID of original post';
ALTER TABLE `feed_posts` ADD COLUMN IF NOT EXISTS `is_repost` tinyint(1) NOT NULL DEFAULT 0;

-- ============================================================================
-- F4: HASHTAGS
-- ============================================================================

CREATE TABLE IF NOT EXISTS `hashtags` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `tenant_id` int(11) NOT NULL,
  `tag` varchar(100) NOT NULL,
  `post_count` int(11) NOT NULL DEFAULT 0,
  `last_used_at` timestamp NULL DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `unique_tag_tenant` (`tenant_id`, `tag`),
  KEY `idx_trending` (`tenant_id`, `post_count` DESC),
  KEY `idx_last_used` (`tenant_id`, `last_used_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `post_hashtags` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `post_id` int(11) NOT NULL,
  `hashtag_id` int(11) NOT NULL,
  `tenant_id` int(11) NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `unique_post_tag` (`post_id`, `hashtag_id`),
  KEY `idx_hashtag` (`hashtag_id`, `tenant_id`),
  KEY `idx_post` (`post_id`),
  CONSTRAINT `fk_post_hashtags_post` FOREIGN KEY (`post_id`) REFERENCES `feed_posts` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_post_hashtags_tag` FOREIGN KEY (`hashtag_id`) REFERENCES `hashtags` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================================================
-- M4: ACTIVITY DASHBOARD — Additional indexes for performance
-- ============================================================================

-- No new tables needed — the dashboard aggregates from existing tables:
-- transactions, feed_posts, listings, connections, event_rsvps, comments, etc.
-- Add indexes to help the dashboard queries:

ALTER TABLE `feed_posts` ADD KEY IF NOT EXISTS `idx_user_created` (`user_id`, `created_at`);

-- ============================================================================
-- DONE
-- ============================================================================

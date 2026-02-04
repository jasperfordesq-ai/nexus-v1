-- ============================================================================
-- SCHEMA DRIFT FIX MIGRATION
-- ============================================================================
-- Generated: 2026-02-01
-- Purpose: Sync production with local development schema
-- This migration is IDEMPOTENT - safe to run multiple times
-- ============================================================================

SET SQL_MODE='ALLOW_INVALID_DATES';

-- ============================================================================
-- 1. MISSING TABLES (6 tables)
-- ============================================================================

-- Table: api_logs
CREATE TABLE IF NOT EXISTS `api_logs` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `tenant_id` int(11) DEFAULT NULL,
  `user_id` int(11) DEFAULT NULL,
  `endpoint` varchar(255) NOT NULL,
  `method` varchar(10) NOT NULL,
  `ip_address` varchar(45) NOT NULL,
  `user_agent` varchar(500) DEFAULT NULL,
  `request_body` text DEFAULT NULL,
  `response_code` smallint(5) unsigned DEFAULT NULL,
  `response_time_ms` int(10) unsigned DEFAULT NULL,
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `idx_tenant` (`tenant_id`),
  KEY `idx_user` (`user_id`),
  KEY `idx_created` (`created_at`),
  KEY `idx_endpoint` (`endpoint`(100))
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='API request logging for debugging and analytics';

-- Table: blog_posts
CREATE TABLE IF NOT EXISTS `blog_posts` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `tenant_id` int(11) NOT NULL,
  `author_id` int(11) NOT NULL,
  `title` varchar(255) NOT NULL,
  `slug` varchar(255) NOT NULL,
  `excerpt` text DEFAULT NULL,
  `content` longtext NOT NULL,
  `featured_image` varchar(500) DEFAULT NULL,
  `status` enum('draft','published','archived') NOT NULL DEFAULT 'draft',
  `published_at` datetime DEFAULT NULL,
  `views` int(10) unsigned DEFAULT 0,
  `meta_title` varchar(255) DEFAULT NULL,
  `meta_description` text DEFAULT NULL,
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  `updated_at` datetime DEFAULT NULL ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `idx_tenant_slug` (`tenant_id`,`slug`),
  KEY `idx_tenant_status` (`tenant_id`,`status`),
  KEY `idx_published` (`published_at`),
  KEY `idx_author` (`author_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Blog posts for tenant websites';

-- Table: email_verification_tokens
CREATE TABLE IF NOT EXISTS `email_verification_tokens` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `user_id` int(11) NOT NULL,
  `token` varchar(255) NOT NULL COMMENT 'Hashed verification token',
  `created_at` datetime DEFAULT current_timestamp(),
  `expires_at` datetime NOT NULL COMMENT 'Token expiry time',
  PRIMARY KEY (`id`),
  KEY `idx_user_id` (`user_id`),
  KEY `idx_expires_at` (`expires_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Table: federation_tenant_settings
CREATE TABLE IF NOT EXISTS `federation_tenant_settings` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `tenant_id` int(11) NOT NULL,
  `federation_enabled` tinyint(1) NOT NULL DEFAULT 0 COMMENT 'Whether federation is enabled for this tenant',
  `allow_incoming_messages` tinyint(1) NOT NULL DEFAULT 1,
  `allow_outgoing_messages` tinyint(1) NOT NULL DEFAULT 1,
  `allow_transactions` tinyint(1) NOT NULL DEFAULT 1,
  `require_approval` tinyint(1) NOT NULL DEFAULT 0 COMMENT 'Require admin approval for new partnerships',
  `max_partners` int(10) unsigned DEFAULT 100 COMMENT 'Maximum number of partnerships',
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  `updated_at` datetime DEFAULT NULL ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `idx_tenant` (`tenant_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Per-tenant federation settings';

-- Table: fraud_alerts
CREATE TABLE IF NOT EXISTS `fraud_alerts` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `tenant_id` int(11) NOT NULL,
  `user_id` int(11) DEFAULT NULL COMMENT 'User who triggered the alert',
  `alert_type` varchar(50) NOT NULL COMMENT 'duplicate_account, suspicious_activity, etc.',
  `severity` enum('low','medium','high','critical') NOT NULL DEFAULT 'medium',
  `description` text NOT NULL,
  `metadata` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL COMMENT 'Additional context data' CHECK (json_valid(`metadata`)),
  `status` enum('open','investigating','resolved','dismissed') NOT NULL DEFAULT 'open',
  `resolved_by` int(11) DEFAULT NULL,
  `resolved_at` datetime DEFAULT NULL,
  `resolution_notes` text DEFAULT NULL,
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  `updated_at` datetime DEFAULT NULL ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `idx_tenant_status` (`tenant_id`,`status`),
  KEY `idx_severity` (`severity`),
  KEY `idx_created` (`created_at`),
  KEY `idx_user` (`user_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Fraud detection alerts for admin review';

-- Table: newsletter_link_clicks
CREATE TABLE IF NOT EXISTS `newsletter_link_clicks` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `tenant_id` int(11) NOT NULL,
  `newsletter_id` int(11) NOT NULL,
  `subscriber_id` int(11) DEFAULT NULL,
  `user_id` int(11) DEFAULT NULL,
  `url` varchar(2000) NOT NULL,
  `ip_address` varchar(45) DEFAULT NULL,
  `user_agent` varchar(500) DEFAULT NULL,
  `clicked_at` datetime NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `idx_tenant` (`tenant_id`),
  KEY `idx_newsletter` (`newsletter_id`),
  KEY `idx_clicked` (`clicked_at`),
  KEY `idx_url` (`url`(255))
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Newsletter link click tracking';


-- ============================================================================
-- 2. MISSING COLUMNS (7 tables)
-- ============================================================================

-- Table: contact_submissions
ALTER TABLE `contact_submissions` ADD COLUMN IF NOT EXISTS `ip_address` varchar(45) DEFAULT NULL;
ALTER TABLE `contact_submissions` ADD COLUMN IF NOT EXISTS `replied_at` datetime DEFAULT NULL;
ALTER TABLE `contact_submissions` ADD COLUMN IF NOT EXISTS `replied_by` int(11) DEFAULT NULL;
ALTER TABLE `contact_submissions` ADD COLUMN IF NOT EXISTS `status` enum('new','read','replied','archived') NOT NULL DEFAULT 'new';
ALTER TABLE `contact_submissions` ADD COLUMN IF NOT EXISTS `user_agent` varchar(500) DEFAULT NULL;

-- Table: events
ALTER TABLE `events` ADD COLUMN IF NOT EXISTS `status` enum('active','cancelled','completed','draft') DEFAULT 'active';

-- Table: feed_posts
ALTER TABLE `feed_posts` ADD COLUMN IF NOT EXISTS `type` varchar(50) DEFAULT 'post';

-- Table: goals
ALTER TABLE `goals` ADD COLUMN IF NOT EXISTS `current_value` decimal(10,2) NOT NULL DEFAULT 0.00 COMMENT 'Current progress value';
ALTER TABLE `goals` ADD COLUMN IF NOT EXISTS `target_value` decimal(10,2) NOT NULL DEFAULT 0.00 COMMENT 'Target value for goal completion';

-- Table: group_feature_toggles
ALTER TABLE `group_feature_toggles` ADD COLUMN IF NOT EXISTS `category` enum('core','content','moderation','gamification','advanced') DEFAULT NULL COMMENT 'Feature category';
ALTER TABLE `group_feature_toggles` ADD COLUMN IF NOT EXISTS `description` text DEFAULT NULL COMMENT 'Description of what this feature does';
ALTER TABLE `group_feature_toggles` ADD COLUMN IF NOT EXISTS `tenant_id` int(11) DEFAULT NULL COMMENT 'Tenant this toggle belongs to';
ALTER TABLE `group_feature_toggles` ADD COLUMN IF NOT EXISTS `updated_at` datetime DEFAULT current_timestamp() ON UPDATE current_timestamp();

-- Table: notifications
ALTER TABLE `notifications` ADD COLUMN IF NOT EXISTS `title` varchar(255) DEFAULT NULL;

-- Table: poll_options
ALTER TABLE `poll_options` ADD COLUMN IF NOT EXISTS `votes` int(11) NOT NULL DEFAULT 0 COMMENT 'Cached vote count for this option';

-- ============================================================================
-- END OF MIGRATION
-- ============================================================================

/*M!999999\- enable the sandbox mode */ 
/*!40103 SET @OLD_TIME_ZONE=@@TIME_ZONE */;
/*!40103 SET TIME_ZONE='+00:00' */;
/*!40014 SET @OLD_UNIQUE_CHECKS=@@UNIQUE_CHECKS, UNIQUE_CHECKS=0 */;
/*!40014 SET @OLD_FOREIGN_KEY_CHECKS=@@FOREIGN_KEY_CHECKS, FOREIGN_KEY_CHECKS=0 */;
/*!40101 SET @OLD_SQL_MODE=@@SQL_MODE, SQL_MODE='NO_AUTO_VALUE_ON_ZERO' */;
/*!40111 SET @OLD_SQL_NOTES=@@SQL_NOTES, SQL_NOTES=0 */;
DROP TABLE IF EXISTS `abuse_alerts`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `abuse_alerts` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `tenant_id` int(11) NOT NULL,
  `alert_type` varchar(50) NOT NULL,
  `severity` enum('low','medium','high','critical') DEFAULT 'medium',
  `user_id` int(11) DEFAULT NULL,
  `transaction_id` int(11) DEFAULT NULL,
  `details` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`details`)),
  `status` enum('new','reviewing','resolved','dismissed') DEFAULT 'new',
  `resolved_by` int(11) DEFAULT NULL,
  `resolved_at` timestamp NULL DEFAULT NULL,
  `resolution_notes` text DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `last_transaction` varchar(255) DEFAULT NULL,
  `works_with_children` tinyint(1) NOT NULL DEFAULT 0,
  `works_with_vulnerable_adults` tinyint(1) NOT NULL DEFAULT 0,
  `home_visits` tinyint(1) NOT NULL DEFAULT 0,
  `requires_vetting` tinyint(1) NOT NULL DEFAULT 0,
  `safeguarding_category` enum('general','children','vulnerable_adults','home_visit','financial_abuse','neglect','exploitation') DEFAULT 'general',
  `risk_assessment_score` int(11) DEFAULT NULL COMMENT 'Risk score 0-100',
  PRIMARY KEY (`id`),
  KEY `idx_abuse_tenant` (`tenant_id`),
  KEY `idx_abuse_status` (`status`),
  KEY `idx_abuse_severity` (`severity`),
  KEY `idx_abuse_user` (`user_id`),
  KEY `idx_abuse_type` (`alert_type`),
  KEY `idx_abuse_date` (`created_at`),
  KEY `idx_abuse_alerts_tenant_status` (`tenant_id`,`status`)
) ENGINE=InnoDB AUTO_INCREMENT=428 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `account_relationships`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `account_relationships` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `parent_user_id` int(11) NOT NULL,
  `child_user_id` int(11) NOT NULL,
  `tenant_id` int(11) NOT NULL,
  `relationship_type` varchar(50) NOT NULL DEFAULT 'family' COMMENT 'family, guardian, carer, organization',
  `permissions` text DEFAULT NULL COMMENT 'JSON: {can_view_activity, can_manage_listings, can_transact}',
  `status` enum('active','pending','revoked') NOT NULL DEFAULT 'pending',
  `approved_at` timestamp NULL DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NULL DEFAULT NULL ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `unique_relationship` (`parent_user_id`,`child_user_id`,`tenant_id`),
  KEY `idx_parent` (`parent_user_id`,`tenant_id`),
  KEY `idx_child` (`child_user_id`,`tenant_id`),
  KEY `idx_tenant_status` (`tenant_id`,`status`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `achievement_analytics`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `achievement_analytics` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `tenant_id` int(11) NOT NULL,
  `date` date NOT NULL,
  `metric_type` varchar(50) NOT NULL,
  `metric_value` int(11) DEFAULT 0,
  `details` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`details`)),
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `unique_metric` (`tenant_id`,`date`,`metric_type`),
  KEY `idx_tenant_date` (`tenant_id`,`date`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb3 COLLATE=utf8mb3_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `achievement_campaigns`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `achievement_campaigns` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `tenant_id` int(11) NOT NULL,
  `name` varchar(100) NOT NULL,
  `description` text DEFAULT NULL,
  `campaign_type` enum('badge_award','xp_bonus','challenge') DEFAULT 'badge_award',
  `badge_key` varchar(50) DEFAULT NULL,
  `xp_amount` int(11) DEFAULT 0,
  `target_criteria` text DEFAULT NULL,
  `schedule_type` enum('immediate','scheduled','recurring') DEFAULT 'immediate',
  `scheduled_at` timestamp NULL DEFAULT NULL,
  `recurrence_pattern` varchar(50) DEFAULT NULL,
  `status` enum('draft','scheduled','running','completed','cancelled') DEFAULT 'draft',
  `created_by` int(11) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `executed_at` timestamp NULL DEFAULT NULL,
  `target_audience` varchar(50) DEFAULT 'all_users',
  `audience_config` text DEFAULT NULL,
  `schedule` varchar(50) DEFAULT NULL,
  `activated_at` timestamp NULL DEFAULT NULL,
  `last_run_at` timestamp NULL DEFAULT NULL,
  `total_awards` int(11) DEFAULT 0,
  PRIMARY KEY (`id`),
  KEY `idx_tenant_status` (`tenant_id`,`status`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb3 COLLATE=utf8mb3_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `achievement_celebrations`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `achievement_celebrations` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `user_id` int(11) NOT NULL,
  `achievement_user_id` int(11) NOT NULL,
  `achievement_type` varchar(50) NOT NULL,
  `achievement_id` int(11) NOT NULL,
  `celebrated_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `unique_celebration` (`user_id`,`achievement_type`,`achievement_id`),
  KEY `idx_achievement_user` (`achievement_user_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb3 COLLATE=utf8mb3_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `activity_log`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `activity_log` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `tenant_id` int(11) DEFAULT NULL,
  `user_id` int(11) DEFAULT NULL,
  `action` varchar(255) NOT NULL,
  `action_type` varchar(50) DEFAULT NULL,
  `entity_type` varchar(50) DEFAULT NULL,
  `entity_id` int(11) DEFAULT NULL,
  `details` text DEFAULT NULL,
  `ip_address` varchar(45) DEFAULT NULL,
  `created_at` datetime DEFAULT current_timestamp(),
  `is_public` tinyint(1) NOT NULL DEFAULT 0,
  `link_url` varchar(255) DEFAULT NULL,
  `click_rate` timestamp NULL DEFAULT NULL,
  `distance` varchar(255) DEFAULT NULL,
  `open_rate` timestamp NULL DEFAULT NULL,
  `user_agent` varchar(255) DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `idx_activity_log_user_id` (`user_id`),
  KEY `idx_activity_log_created_at` (`created_at`),
  KEY `idx_activity_log_user_created` (`user_id`,`created_at`),
  KEY `idx_activity_log_tenant` (`tenant_id`)
) ENGINE=InnoDB AUTO_INCREMENT=1513 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `activity_logs`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `activity_logs` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `admin_actions`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `admin_actions` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `admin_id` int(11) NOT NULL,
  `admin_name` varchar(255) NOT NULL,
  `admin_email` varchar(255) NOT NULL,
  `action_type` varchar(100) NOT NULL,
  `target_user_id` int(11) DEFAULT NULL,
  `target_user_name` varchar(255) DEFAULT NULL,
  `target_user_email` varchar(255) DEFAULT NULL,
  `details` text DEFAULT NULL,
  `ip_address` varchar(45) DEFAULT NULL,
  `user_agent` text DEFAULT NULL,
  `tenant_id` int(11) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `idx_admin_id` (`admin_id`),
  KEY `idx_target_user_id` (`target_user_id`),
  KEY `idx_action_type` (`action_type`),
  KEY `idx_created_at` (`created_at`),
  KEY `idx_tenant_id` (`tenant_id`),
  CONSTRAINT `admin_actions_ibfk_1` FOREIGN KEY (`admin_id`) REFERENCES `users` (`id`) ON DELETE CASCADE,
  CONSTRAINT `admin_actions_ibfk_2` FOREIGN KEY (`target_user_id`) REFERENCES `users` (`id`) ON DELETE SET NULL,
  CONSTRAINT `admin_actions_ibfk_3` FOREIGN KEY (`tenant_id`) REFERENCES `tenants` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=3 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `ai_content_cache`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `ai_content_cache` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `tenant_id` int(11) NOT NULL,
  `cache_key` varchar(255) NOT NULL,
  `content` text DEFAULT NULL,
  `expires_at` timestamp NULL DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `idx_ai_cache_key` (`tenant_id`,`cache_key`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `ai_conversations`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `ai_conversations` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `tenant_id` int(11) NOT NULL,
  `user_id` int(11) NOT NULL,
  `title` varchar(255) DEFAULT NULL,
  `provider` varchar(50) DEFAULT NULL,
  `model` varchar(100) DEFAULT NULL,
  `context_type` varchar(50) DEFAULT 'general',
  `context_id` int(11) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NULL DEFAULT NULL ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `idx_ai_conv_user` (`tenant_id`,`user_id`),
  KEY `idx_ai_conv_created` (`created_at`)
) ENGINE=InnoDB AUTO_INCREMENT=31 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `ai_messages`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `ai_messages` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `conversation_id` int(11) NOT NULL,
  `role` enum('user','assistant','system') NOT NULL,
  `content` text NOT NULL,
  `tokens_used` int(11) DEFAULT 0,
  `model` varchar(100) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `idx_ai_msg_conv` (`conversation_id`),
  CONSTRAINT `ai_messages_ibfk_1` FOREIGN KEY (`conversation_id`) REFERENCES `ai_conversations` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=59 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `ai_settings`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `ai_settings` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `tenant_id` int(11) NOT NULL,
  `setting_key` varchar(100) NOT NULL,
  `setting_value` text DEFAULT NULL,
  `is_encrypted` tinyint(1) DEFAULT 0,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NULL DEFAULT NULL ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `idx_ai_settings_key` (`tenant_id`,`setting_key`)
) ENGINE=InnoDB AUTO_INCREMENT=224 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `ai_usage`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `ai_usage` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `tenant_id` int(11) NOT NULL,
  `user_id` int(11) NOT NULL,
  `provider` varchar(50) NOT NULL,
  `feature` varchar(50) NOT NULL,
  `tokens_input` int(11) DEFAULT 0,
  `tokens_output` int(11) DEFAULT 0,
  `cost_usd` decimal(10,6) DEFAULT 0.000000,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `bill` varchar(255) DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `idx_ai_usage_tenant` (`tenant_id`,`created_at`),
  KEY `idx_ai_usage_user` (`user_id`,`created_at`)
) ENGINE=InnoDB AUTO_INCREMENT=21 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `ai_usages`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `ai_usages` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `ai_user_limits`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `ai_user_limits` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `tenant_id` int(11) NOT NULL,
  `user_id` int(11) NOT NULL,
  `daily_limit` int(11) DEFAULT 50,
  `monthly_limit` int(11) DEFAULT 1000,
  `daily_used` int(11) DEFAULT 0,
  `monthly_used` int(11) DEFAULT 0,
  `last_reset_daily` date DEFAULT NULL,
  `last_reset_monthly` date DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NULL DEFAULT NULL ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `idx_ai_limits_user` (`tenant_id`,`user_id`)
) ENGINE=InnoDB AUTO_INCREMENT=36 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `api_logs`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `api_logs` (
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
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `attributes`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `attributes` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `tenant_id` int(11) NOT NULL,
  `category_id` int(11) DEFAULT NULL,
  `name` varchar(255) NOT NULL,
  `slug` varchar(255) DEFAULT NULL,
  `target_type` enum('any','offer','request') DEFAULT 'any',
  `input_type` enum('checkbox','text','select') DEFAULT 'checkbox',
  `is_active` tinyint(1) DEFAULT 1,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  `distance_km` varchar(255) DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_attributes_tenant_name_type` (`tenant_id`,`name`,`target_type`),
  KEY `idx_tenant` (`tenant_id`),
  KEY `idx_category` (`category_id`)
) ENGINE=InnoDB AUTO_INCREMENT=101 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `badge_collection_items`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `badge_collection_items` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `collection_id` int(11) NOT NULL,
  `badge_key` varchar(50) NOT NULL,
  `display_order` int(11) DEFAULT 0,
  PRIMARY KEY (`id`),
  UNIQUE KEY `unique_collection_badge` (`collection_id`,`badge_key`),
  CONSTRAINT `badge_collection_items_ibfk_1` FOREIGN KEY (`collection_id`) REFERENCES `badge_collections` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb3 COLLATE=utf8mb3_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `badge_collections`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `badge_collections` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `tenant_id` int(11) NOT NULL,
  `collection_key` varchar(50) NOT NULL,
  `collection_type` enum('journey','collection') NOT NULL DEFAULT 'collection' COMMENT 'journey=ordered step-by-step path, collection=unordered badge group',
  `is_ordered` tinyint(1) NOT NULL DEFAULT 0 COMMENT 'Whether steps must be completed in sequence',
  `estimated_duration` varchar(50) DEFAULT NULL COMMENT 'Human-readable estimate, e.g. "2 weeks", "1 month"',
  `name` varchar(100) NOT NULL,
  `description` text DEFAULT NULL,
  `icon` varchar(50) DEFAULT NULL,
  `bonus_xp` int(11) DEFAULT 100,
  `bonus_badge_key` varchar(50) DEFAULT NULL,
  `display_order` int(11) DEFAULT 0,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `unique_collection` (`tenant_id`,`collection_key`)
) ENGINE=InnoDB AUTO_INCREMENT=7 DEFAULT CHARSET=utf8mb3 COLLATE=utf8mb3_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `badges`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `badges` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `tenant_id` int(11) NOT NULL,
  `badge_key` varchar(100) NOT NULL,
  `name` varchar(200) NOT NULL,
  `description` text DEFAULT NULL,
  `icon` varchar(100) DEFAULT 'fa-award',
  `color` varchar(20) DEFAULT '#6366f1',
  `image_url` varchar(500) DEFAULT NULL,
  `xp_value` int(11) DEFAULT 0,
  `rarity` enum('common','uncommon','rare','epic','legendary') DEFAULT 'common',
  `category` varchar(50) DEFAULT 'general',
  `sort_order` int(11) DEFAULT 0,
  `points_required` int(11) DEFAULT 0,
  `is_hidden` tinyint(1) DEFAULT 0,
  `is_active` tinyint(1) DEFAULT 1,
  `badge_tier` enum('core','template','custom') NOT NULL DEFAULT 'template' COMMENT 'core=always enabled, template=tenant-configurable, custom=tenant-created',
  `badge_class` enum('quantity','quality','special','verification') NOT NULL DEFAULT 'quantity' COMMENT 'quantity=threshold counter, quality=behavioral, special=one-off, verification=trust',
  `threshold` int(10) unsigned NOT NULL DEFAULT 0,
  `threshold_type` varchar(50) DEFAULT NULL COMMENT 'count, rate, duration_months, ratio',
  `evaluation_method` varchar(100) DEFAULT NULL COMMENT 'PHP method name for badge check logic',
  `is_enabled` tinyint(1) NOT NULL DEFAULT 1 COMMENT 'Global default enabled state (tenant overrides in tenant_badge_overrides)',
  `config_json` text DEFAULT NULL COMMENT 'Flexible config for quality badges (thresholds, time windows, etc.)',
  `created_at` datetime DEFAULT current_timestamp(),
  `updated_at` datetime DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `unique_badge_key` (`tenant_id`,`badge_key`),
  KEY `idx_tenant` (`tenant_id`),
  KEY `idx_category` (`category`),
  KEY `idx_rarity` (`rarity`),
  KEY `idx_active` (`is_active`),
  KEY `idx_badge_tier` (`badge_tier`),
  KEY `idx_badge_class` (`badge_class`)
) ENGINE=InnoDB AUTO_INCREMENT=892 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `blog_posts`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `blog_posts` (
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
) ENGINE=InnoDB AUTO_INCREMENT=90100 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Blog posts for tenant websites';
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `bookmark_collections`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `bookmark_collections` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `tenant_id` bigint(20) unsigned NOT NULL,
  `user_id` bigint(20) unsigned NOT NULL,
  `name` varchar(100) NOT NULL,
  `description` text DEFAULT NULL,
  `is_default` tinyint(1) NOT NULL DEFAULT 0,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `bookmark_collections_tenant_id_user_id_name_unique` (`tenant_id`,`user_id`,`name`),
  KEY `bookmark_collections_tenant_id_index` (`tenant_id`),
  KEY `bookmark_collections_user_id_index` (`user_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `bookmarks`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `bookmarks` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `tenant_id` bigint(20) unsigned NOT NULL,
  `user_id` bigint(20) unsigned NOT NULL,
  `bookmarkable_type` varchar(50) NOT NULL,
  `bookmarkable_id` bigint(20) unsigned NOT NULL,
  `collection_id` bigint(20) unsigned DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `bookmarks_unique` (`tenant_id`,`user_id`,`bookmarkable_type`,`bookmarkable_id`),
  KEY `bookmarks_bookmarkable_type_bookmarkable_id_index` (`bookmarkable_type`,`bookmarkable_id`),
  KEY `bookmarks_collection_id_foreign` (`collection_id`),
  KEY `bookmarks_tenant_id_index` (`tenant_id`),
  KEY `bookmarks_user_id_index` (`user_id`),
  CONSTRAINT `bookmarks_collection_id_foreign` FOREIGN KEY (`collection_id`) REFERENCES `bookmark_collections` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `broker_control_config`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `broker_control_config` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `tenant_id` int(11) NOT NULL,
  `config_key` varchar(100) NOT NULL,
  `config_value` text DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NULL DEFAULT NULL ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_broker_config_tenant_key` (`tenant_id`,`config_key`),
  KEY `idx_broker_config_tenant` (`tenant_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `broker_message_copies`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `broker_message_copies` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `tenant_id` int(11) NOT NULL,
  `original_message_id` int(11) NOT NULL,
  `conversation_key` varchar(100) NOT NULL COMMENT 'Hash of sorted sender+receiver IDs',
  `sender_id` int(11) NOT NULL,
  `receiver_id` int(11) NOT NULL,
  `message_body` text DEFAULT NULL,
  `sent_at` timestamp NOT NULL,
  `copy_reason` enum('first_contact','high_risk_listing','new_member','flagged_user','manual_monitoring','random_sample') NOT NULL,
  `related_listing_id` int(11) DEFAULT NULL,
  `related_exchange_id` int(11) DEFAULT NULL,
  `reviewed_by` int(11) DEFAULT NULL,
  `reviewed_at` timestamp NULL DEFAULT NULL,
  `review_notes` text DEFAULT NULL,
  `flagged` tinyint(1) DEFAULT 0,
  `flag_reason` varchar(255) DEFAULT NULL,
  `flag_severity` enum('info','warning','concern','urgent') DEFAULT NULL,
  `action_taken` varchar(100) DEFAULT NULL COMMENT 'e.g., no_action, warning_sent, monitoring_added',
  `action_notes` text DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT current_timestamp(),
  `archived_at` timestamp NULL DEFAULT NULL COMMENT 'When this copy was archived via approve/flag decision',
  `archive_id` int(11) DEFAULT NULL COMMENT 'Link to broker_review_archives.id',
  PRIMARY KEY (`id`),
  KEY `idx_tenant_unreviewed` (`tenant_id`,`reviewed_at`),
  KEY `idx_conversation` (`conversation_key`),
  KEY `idx_flagged` (`tenant_id`,`flagged`),
  KEY `idx_copy_reason` (`tenant_id`,`copy_reason`),
  KEY `idx_sender` (`sender_id`),
  KEY `idx_receiver` (`receiver_id`),
  KEY `reviewed_by` (`reviewed_by`),
  KEY `related_listing_id` (`related_listing_id`),
  KEY `related_exchange_id` (`related_exchange_id`),
  KEY `idx_bmc_archived` (`tenant_id`,`archived_at`),
  CONSTRAINT `broker_message_copies_ibfk_1` FOREIGN KEY (`tenant_id`) REFERENCES `tenants` (`id`) ON DELETE CASCADE,
  CONSTRAINT `broker_message_copies_ibfk_2` FOREIGN KEY (`sender_id`) REFERENCES `users` (`id`) ON DELETE CASCADE,
  CONSTRAINT `broker_message_copies_ibfk_3` FOREIGN KEY (`receiver_id`) REFERENCES `users` (`id`) ON DELETE CASCADE,
  CONSTRAINT `broker_message_copies_ibfk_4` FOREIGN KEY (`reviewed_by`) REFERENCES `users` (`id`) ON DELETE SET NULL,
  CONSTRAINT `broker_message_copies_ibfk_5` FOREIGN KEY (`related_listing_id`) REFERENCES `listings` (`id`) ON DELETE SET NULL,
  CONSTRAINT `broker_message_copies_ibfk_6` FOREIGN KEY (`related_exchange_id`) REFERENCES `exchange_requests` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB AUTO_INCREMENT=53 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `broker_review_archives`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `broker_review_archives` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `tenant_id` int(11) NOT NULL,
  `broker_copy_id` int(11) NOT NULL,
  `sender_id` int(11) NOT NULL,
  `sender_name` varchar(200) NOT NULL,
  `receiver_id` int(11) NOT NULL,
  `receiver_name` varchar(200) NOT NULL,
  `related_listing_id` int(11) DEFAULT NULL,
  `listing_title` varchar(255) DEFAULT NULL,
  `copy_reason` enum('first_contact','high_risk_listing','new_member','flagged_user','manual_monitoring','random_sample') NOT NULL,
  `target_message_body` text NOT NULL,
  `target_message_sent_at` timestamp NOT NULL,
  `conversation_snapshot` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin NOT NULL COMMENT 'Array of {id, sender_id, sender_name, body, created_at} — frozen at approval time' CHECK (json_valid(`conversation_snapshot`)),
  `decision` enum('approved','flagged') NOT NULL,
  `decision_notes` text DEFAULT NULL,
  `decided_by` int(11) NOT NULL,
  `decided_by_name` varchar(200) NOT NULL,
  `decided_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `flag_reason` varchar(255) DEFAULT NULL,
  `flag_severity` enum('info','warning','concern','urgent') DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `idx_tenant_decision` (`tenant_id`,`decision`),
  KEY `idx_tenant_date` (`tenant_id`,`decided_at`),
  KEY `idx_sender` (`tenant_id`,`sender_id`),
  KEY `idx_receiver` (`tenant_id`,`receiver_id`),
  KEY `idx_listing` (`tenant_id`,`related_listing_id`),
  KEY `idx_broker_copy` (`broker_copy_id`),
  KEY `idx_decided_by` (`decided_by`),
  CONSTRAINT `broker_review_archives_ibfk_1` FOREIGN KEY (`tenant_id`) REFERENCES `tenants` (`id`) ON DELETE CASCADE,
  CONSTRAINT `broker_review_archives_ibfk_2` FOREIGN KEY (`decided_by`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `campaign_awards`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `campaign_awards` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `tenant_id` int(11) NOT NULL DEFAULT 1,
  `campaign_id` int(11) NOT NULL,
  `user_id` int(11) NOT NULL,
  `awarded_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `last_login` varchar(255) DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `unique_campaign_award` (`campaign_id`,`user_id`),
  KEY `idx_campaign` (`campaign_id`),
  KEY `idx_user` (`user_id`),
  KEY `idx_tenant` (`tenant_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb3 COLLATE=utf8mb3_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `campaign_challenges`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `campaign_challenges` (
  `campaign_id` int(10) unsigned NOT NULL,
  `challenge_id` int(10) unsigned NOT NULL,
  `sort_order` int(11) NOT NULL DEFAULT 0,
  PRIMARY KEY (`campaign_id`,`challenge_id`),
  KEY `idx_challenge` (`challenge_id`),
  CONSTRAINT `fk_cc_campaign` FOREIGN KEY (`campaign_id`) REFERENCES `campaigns` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_cc_challenge` FOREIGN KEY (`challenge_id`) REFERENCES `ideation_challenges` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `campaign_executions`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `campaign_executions` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `campaign_id` int(11) NOT NULL,
  `users_affected` int(11) DEFAULT 0,
  `execution_details` text DEFAULT NULL,
  `executed_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `campaign_id` (`campaign_id`),
  CONSTRAINT `campaign_executions_ibfk_1` FOREIGN KEY (`campaign_id`) REFERENCES `achievement_campaigns` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb3 COLLATE=utf8mb3_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `campaigns`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `campaigns` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `tenant_id` int(10) unsigned NOT NULL,
  `title` varchar(255) NOT NULL,
  `description` text DEFAULT NULL,
  `cover_image` varchar(500) DEFAULT NULL,
  `status` enum('draft','active','completed','archived') NOT NULL DEFAULT 'draft',
  `start_date` date DEFAULT NULL,
  `end_date` date DEFAULT NULL,
  `created_by` int(11) NOT NULL,
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  `updated_at` datetime DEFAULT NULL ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `idx_tenant_status` (`tenant_id`,`status`),
  KEY `idx_dates` (`tenant_id`,`start_date`,`end_date`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `categories`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `categories` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `tenant_id` int(11) NOT NULL,
  `name` varchar(50) NOT NULL,
  `slug` varchar(255) NOT NULL,
  `sort_order` int(11) NOT NULL DEFAULT 0,
  `is_active` tinyint(1) NOT NULL DEFAULT 1,
  `color` varchar(50) DEFAULT 'blue',
  `created_at` datetime DEFAULT current_timestamp(),
  `type` varchar(50) NOT NULL DEFAULT 'listing',
  `blocker_user_id` int(11) DEFAULT NULL,
  `clicked_at` timestamp NULL DEFAULT NULL,
  `distance_km` varchar(255) DEFAULT NULL,
  `match` timestamp NULL DEFAULT NULL,
  `reset_token` varchar(255) DEFAULT NULL,
  `parent_id` int(10) unsigned DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `unique_name_tenant` (`name`,`tenant_id`),
  KEY `tenant_id` (`tenant_id`),
  KEY `idx_cat_parent` (`parent_id`),
  CONSTRAINT `categories_ibfk_1` FOREIGN KEY (`tenant_id`) REFERENCES `tenants` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=431 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `challenge_categories`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `challenge_categories` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `tenant_id` int(10) unsigned NOT NULL,
  `name` varchar(100) NOT NULL,
  `slug` varchar(100) NOT NULL,
  `icon` varchar(50) DEFAULT NULL COMMENT 'Lucide icon name e.g. Leaf, Cpu, Heart',
  `color` varchar(20) DEFAULT NULL COMMENT 'Tailwind color e.g. blue, green, amber',
  `sort_order` int(11) NOT NULL DEFAULT 0,
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `uk_tenant_slug` (`tenant_id`,`slug`),
  KEY `idx_tenant_sort` (`tenant_id`,`sort_order`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `challenge_favorites`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `challenge_favorites` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `challenge_id` int(10) unsigned NOT NULL,
  `user_id` int(11) NOT NULL,
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `uk_challenge_user` (`challenge_id`,`user_id`),
  KEY `idx_user` (`user_id`),
  CONSTRAINT `fk_fav_challenge` FOREIGN KEY (`challenge_id`) REFERENCES `ideation_challenges` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=4 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `challenge_idea_comments`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `challenge_idea_comments` (
  `id` int(11) unsigned NOT NULL AUTO_INCREMENT,
  `idea_id` int(11) unsigned NOT NULL,
  `user_id` int(11) NOT NULL,
  `body` text NOT NULL,
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  `updated_at` datetime DEFAULT NULL ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `idx_comments_idea` (`idea_id`),
  CONSTRAINT `fk_comment_idea` FOREIGN KEY (`idea_id`) REFERENCES `challenge_ideas` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=2 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `challenge_idea_votes`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `challenge_idea_votes` (
  `id` int(11) unsigned NOT NULL AUTO_INCREMENT,
  `idea_id` int(11) unsigned NOT NULL,
  `user_id` int(11) NOT NULL,
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_idea_user_vote` (`idea_id`,`user_id`),
  CONSTRAINT `fk_vote_idea` FOREIGN KEY (`idea_id`) REFERENCES `challenge_ideas` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=2 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `challenge_ideas`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `challenge_ideas` (
  `id` int(11) unsigned NOT NULL AUTO_INCREMENT,
  `challenge_id` int(11) unsigned NOT NULL,
  `user_id` int(11) NOT NULL,
  `title` varchar(255) NOT NULL,
  `description` text NOT NULL,
  `votes_count` int(11) NOT NULL DEFAULT 0,
  `comments_count` int(11) NOT NULL DEFAULT 0,
  `status` enum('draft','submitted','shortlisted','winner','withdrawn') NOT NULL DEFAULT 'submitted',
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  `updated_at` datetime DEFAULT NULL ON UPDATE current_timestamp(),
  `image_url` varchar(500) DEFAULT NULL COMMENT 'Optional image attachment for the idea',
  PRIMARY KEY (`id`),
  KEY `idx_ideas_challenge` (`challenge_id`),
  KEY `idx_ideas_user` (`user_id`),
  KEY `idx_ideas_votes` (`challenge_id`,`votes_count` DESC),
  KEY `idx_status` (`status`),
  KEY `idx_user_status` (`user_id`,`status`,`challenge_id`),
  CONSTRAINT `fk_idea_challenge` FOREIGN KEY (`challenge_id`) REFERENCES `ideation_challenges` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=2 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `challenge_outcomes`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `challenge_outcomes` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `challenge_id` int(10) unsigned NOT NULL,
  `winning_idea_id` int(10) unsigned DEFAULT NULL,
  `tenant_id` int(10) unsigned NOT NULL,
  `status` enum('not_started','in_progress','implemented','abandoned') NOT NULL DEFAULT 'not_started',
  `impact_description` text DEFAULT NULL,
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  `updated_at` datetime DEFAULT NULL ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `uk_challenge` (`challenge_id`),
  KEY `idx_tenant` (`tenant_id`),
  CONSTRAINT `fk_outcome_challenge` FOREIGN KEY (`challenge_id`) REFERENCES `ideation_challenges` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `challenge_progress`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `challenge_progress` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `user_id` int(11) NOT NULL,
  `tenant_id` int(11) NOT NULL,
  `challenge_id` int(11) NOT NULL,
  `status` enum('in_progress','completed','claimed') NOT NULL DEFAULT 'in_progress',
  `claimed_at` timestamp NULL DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `unique_user_challenge` (`user_id`,`challenge_id`),
  KEY `idx_user_tenant` (`user_id`,`tenant_id`),
  KEY `idx_challenge` (`challenge_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `challenge_tag_links`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `challenge_tag_links` (
  `challenge_id` int(10) unsigned NOT NULL,
  `tag_id` int(10) unsigned NOT NULL,
  PRIMARY KEY (`challenge_id`,`tag_id`),
  KEY `idx_tag` (`tag_id`),
  CONSTRAINT `fk_ctlink_challenge` FOREIGN KEY (`challenge_id`) REFERENCES `ideation_challenges` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_ctlink_tag` FOREIGN KEY (`tag_id`) REFERENCES `challenge_tags` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `challenge_tags`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `challenge_tags` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `tenant_id` int(10) unsigned NOT NULL,
  `name` varchar(100) NOT NULL,
  `slug` varchar(100) NOT NULL,
  `tag_type` enum('interest','skill','general') NOT NULL DEFAULT 'general',
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `uk_tenant_slug` (`tenant_id`,`slug`),
  KEY `idx_tenant_type` (`tenant_id`,`tag_type`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `challenge_templates`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `challenge_templates` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `tenant_id` int(10) unsigned NOT NULL,
  `title` varchar(255) NOT NULL,
  `description` text DEFAULT NULL,
  `default_tags` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL COMMENT 'Array of tag names' CHECK (json_valid(`default_tags`)),
  `default_category_id` int(10) unsigned DEFAULT NULL,
  `evaluation_criteria` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL COMMENT 'Array of criteria strings' CHECK (json_valid(`evaluation_criteria`)),
  `prize_description` text DEFAULT NULL,
  `max_ideas_per_user` int(11) DEFAULT NULL,
  `created_by` int(11) NOT NULL,
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  `updated_at` datetime DEFAULT NULL ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `idx_tenant` (`tenant_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `challenges`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `challenges` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `tenant_id` int(11) NOT NULL,
  `title` varchar(255) NOT NULL,
  `description` text DEFAULT NULL,
  `challenge_type` enum('daily','weekly','monthly','special') DEFAULT 'weekly',
  `action_type` varchar(50) NOT NULL,
  `target_count` int(11) DEFAULT 1,
  `xp_reward` int(11) DEFAULT 50,
  `badge_reward` varchar(50) DEFAULT NULL,
  `start_date` date NOT NULL,
  `end_date` date NOT NULL,
  `is_active` tinyint(1) DEFAULT 1,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `idx_tenant_active` (`tenant_id`,`is_active`),
  KEY `idx_dates` (`start_date`,`end_date`)
) ENGINE=InnoDB AUTO_INCREMENT=4 DEFAULT CHARSET=utf8mb3 COLLATE=utf8mb3_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `close_friends`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `close_friends` (
  `user_id` int(10) unsigned NOT NULL,
  `friend_id` int(10) unsigned NOT NULL,
  `tenant_id` int(11) NOT NULL,
  `created_at` timestamp NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`user_id`,`friend_id`),
  KEY `idx_close_friends_tenant` (`tenant_id`,`user_id`),
  CONSTRAINT `close_friends_ibfk_1` FOREIGN KEY (`tenant_id`) REFERENCES `tenants` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `comment_reactions`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `comment_reactions` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `tenant_id` int(10) unsigned NOT NULL,
  `comment_id` bigint(20) unsigned NOT NULL,
  `user_id` int(10) unsigned NOT NULL,
  `reaction_type` enum('love','like','laugh','wow','sad','celebrate','clap','time_credit') NOT NULL,
  `created_at` timestamp NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `uk_user_comment_reaction` (`tenant_id`,`comment_id`,`user_id`),
  KEY `idx_comment_reactions` (`tenant_id`,`comment_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `comments`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `comments` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `tenant_id` int(11) NOT NULL DEFAULT 1,
  `user_id` int(11) NOT NULL,
  `target_type` varchar(50) NOT NULL,
  `target_id` int(11) NOT NULL,
  `parent_id` int(11) DEFAULT NULL COMMENT 'Parent comment ID for nested replies',
  `content` text NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NULL DEFAULT current_timestamp(),
  `event` varchar(255) DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `user_id` (`user_id`),
  KEY `target` (`target_type`,`target_id`),
  KEY `tenant_id` (`tenant_id`),
  KEY `idx_parent` (`parent_id`),
  KEY `idx_comments_tenant_target` (`tenant_id`,`target_type`,`target_id`),
  CONSTRAINT `comments_tenant_id_foreign` FOREIGN KEY (`tenant_id`) REFERENCES `tenants` (`id`) ON DELETE CASCADE,
  CONSTRAINT `comments_user_id_foreign` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=43 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `community_fund_accounts`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `community_fund_accounts` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `tenant_id` int(11) NOT NULL,
  `balance` decimal(10,2) NOT NULL DEFAULT 0.00,
  `total_deposited` decimal(10,2) NOT NULL DEFAULT 0.00,
  `total_withdrawn` decimal(10,2) NOT NULL DEFAULT 0.00,
  `total_donated` decimal(10,2) NOT NULL DEFAULT 0.00,
  `description` varchar(500) DEFAULT NULL COMMENT 'Description of the community fund',
  `created_at` timestamp NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `uk_tenant` (`tenant_id`),
  CONSTRAINT `community_fund_accounts_ibfk_1` FOREIGN KEY (`tenant_id`) REFERENCES `tenants` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=5 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `community_fund_transactions`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `community_fund_transactions` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `tenant_id` int(11) NOT NULL,
  `fund_id` int(11) NOT NULL,
  `user_id` int(11) DEFAULT NULL COMMENT 'User involved (admin for deposits/withdrawals, donor for donations)',
  `type` enum('deposit','withdrawal','donation','starting_balance_grant') NOT NULL,
  `amount` decimal(10,2) NOT NULL,
  `balance_after` decimal(10,2) NOT NULL,
  `description` varchar(500) DEFAULT NULL,
  `admin_id` int(11) DEFAULT NULL COMMENT 'Admin who performed the action',
  `created_at` timestamp NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `idx_tenant` (`tenant_id`),
  KEY `idx_fund` (`fund_id`),
  KEY `idx_user` (`user_id`),
  KEY `idx_type` (`type`),
  KEY `admin_id` (`admin_id`),
  CONSTRAINT `community_fund_transactions_ibfk_1` FOREIGN KEY (`tenant_id`) REFERENCES `tenants` (`id`) ON DELETE CASCADE,
  CONSTRAINT `community_fund_transactions_ibfk_2` FOREIGN KEY (`fund_id`) REFERENCES `community_fund_accounts` (`id`) ON DELETE CASCADE,
  CONSTRAINT `community_fund_transactions_ibfk_3` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE SET NULL,
  CONSTRAINT `community_fund_transactions_ibfk_4` FOREIGN KEY (`admin_id`) REFERENCES `users` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `community_ranks`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `community_ranks` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `tenant_id` int(11) NOT NULL,
  `user_id` int(11) NOT NULL,
  `rank_score` decimal(10,4) NOT NULL DEFAULT 0.0000,
  `activity_score` decimal(10,4) NOT NULL DEFAULT 0.0000,
  `contribution_score` decimal(10,4) NOT NULL DEFAULT 0.0000,
  `reputation_score` decimal(10,4) NOT NULL DEFAULT 0.0000,
  `connectivity_score` decimal(10,4) NOT NULL DEFAULT 0.0000,
  `proximity_score` decimal(10,4) NOT NULL DEFAULT 0.0000,
  `rank_position` int(11) DEFAULT NULL,
  `tier` varchar(50) DEFAULT 'Bronze',
  `calculated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `unique_user_rank` (`tenant_id`,`user_id`),
  KEY `idx_tenant` (`tenant_id`),
  KEY `idx_rank_score` (`rank_score`),
  KEY `idx_position` (`tenant_id`,`rank_position`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Community rank scores for users';
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `communityrank_settings`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `communityrank_settings` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `tenant_id` int(11) NOT NULL,
  `is_enabled` tinyint(1) NOT NULL DEFAULT 1,
  `activity_weight` decimal(3,2) NOT NULL DEFAULT 0.25,
  `contribution_weight` decimal(3,2) NOT NULL DEFAULT 0.25,
  `reputation_weight` decimal(3,2) NOT NULL DEFAULT 0.20,
  `connectivity_weight` decimal(3,2) NOT NULL DEFAULT 0.20,
  `proximity_weight` decimal(3,2) NOT NULL DEFAULT 0.10,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  `verify` varchar(255) DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `tenant_id` (`tenant_id`)
) ENGINE=InnoDB AUTO_INCREMENT=6 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `connections`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `connections` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `tenant_id` int(10) unsigned NOT NULL DEFAULT 1,
  `requester_id` int(11) NOT NULL,
  `receiver_id` int(11) NOT NULL,
  `status` varchar(20) DEFAULT 'pending',
  `created_at` datetime DEFAULT current_timestamp(),
  `updated_at` datetime DEFAULT NULL ON UPDATE current_timestamp(),
  `award` varchar(255) DEFAULT NULL,
  `decl` varchar(255) DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uk_connections_pair` (`tenant_id`,`requester_id`,`receiver_id`),
  KEY `requester_id` (`requester_id`),
  KEY `receiver_id` (`receiver_id`),
  KEY `idx_tenant_id` (`tenant_id`),
  CONSTRAINT `connections_ibfk_1` FOREIGN KEY (`requester_id`) REFERENCES `users` (`id`) ON DELETE CASCADE,
  CONSTRAINT `connections_ibfk_2` FOREIGN KEY (`receiver_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=20 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `consent_types`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `consent_types` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `slug` varchar(100) NOT NULL,
  `name` varchar(200) NOT NULL,
  `description` text DEFAULT NULL,
  `category` varchar(50) DEFAULT 'general',
  `is_required` tinyint(1) DEFAULT 0,
  `current_version` varchar(20) NOT NULL DEFAULT '1.0',
  `current_text` text NOT NULL,
  `legal_basis` enum('consent','contract','legal_obligation','vital_interests','public_task','legitimate_interests') DEFAULT 'consent',
  `retention_days` int(11) DEFAULT NULL,
  `is_active` tinyint(1) DEFAULT 1,
  `display_order` int(11) DEFAULT 0,
  `created_at` datetime DEFAULT current_timestamp(),
  `updated_at` datetime DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  `erasure` varchar(255) DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `slug` (`slug`)
) ENGINE=InnoDB AUTO_INCREMENT=9 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `consent_version_history`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `consent_version_history` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `consent_type_slug` varchar(100) NOT NULL,
  `version` varchar(20) NOT NULL,
  `text_content` text NOT NULL,
  `text_hash` varchar(64) NOT NULL COMMENT 'SHA-256 hash for comparison',
  `created_by` int(11) DEFAULT NULL COMMENT 'Admin user who made the change',
  `effective_from` datetime DEFAULT current_timestamp() COMMENT 'When this version became active',
  `created_at` datetime DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `idx_consent_type` (`consent_type_slug`),
  KEY `idx_version` (`version`),
  KEY `idx_effective_from` (`effective_from`),
  KEY `idx_text_hash` (`text_hash`),
  CONSTRAINT `consent_version_history_ibfk_1` FOREIGN KEY (`consent_type_slug`) REFERENCES `consent_types` (`slug`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=16 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `contact_submissions`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `contact_submissions` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `tenant_id` int(11) NOT NULL,
  `name` varchar(255) NOT NULL,
  `email` varchar(255) NOT NULL,
  `subject` varchar(255) DEFAULT NULL,
  `message` text NOT NULL,
  `email_sent` tinyint(1) DEFAULT 0,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `ip_address` varchar(45) DEFAULT NULL,
  `replied_at` datetime DEFAULT NULL,
  `replied_by` int(11) DEFAULT NULL,
  `status` enum('new','read','replied','archived') NOT NULL DEFAULT 'new',
  `user_agent` varchar(500) DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `idx_tenant` (`tenant_id`),
  KEY `idx_created` (`created_at`)
) ENGINE=InnoDB AUTO_INCREMENT=39 DEFAULT CHARSET=utf8mb3 COLLATE=utf8mb3_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `content_embeddings`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `content_embeddings` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `tenant_id` int(10) unsigned NOT NULL,
  `content_type` enum('listing','user','event','group') NOT NULL,
  `content_id` int(10) unsigned NOT NULL,
  `model` varchar(100) NOT NULL DEFAULT 'text-embedding-3-small',
  `embedding` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin NOT NULL CHECK (json_valid(`embedding`)),
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `uk_content` (`tenant_id`,`content_type`,`content_id`),
  KEY `idx_tenant_type` (`tenant_id`,`content_type`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `content_moderation_queue`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `content_moderation_queue` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `tenant_id` int(11) NOT NULL,
  `content_type` enum('post','listing','event','comment','group') NOT NULL,
  `content_id` int(11) NOT NULL,
  `author_id` int(11) NOT NULL COMMENT 'User who created the content',
  `title` varchar(255) DEFAULT NULL COMMENT 'Content title/summary for quick review',
  `status` enum('pending','approved','rejected','flagged') NOT NULL DEFAULT 'pending',
  `reviewer_id` int(11) DEFAULT NULL COMMENT 'Admin who reviewed',
  `reviewed_at` timestamp NULL DEFAULT NULL,
  `rejection_reason` text DEFAULT NULL,
  `auto_flagged` tinyint(1) NOT NULL DEFAULT 0 COMMENT 'Was this auto-flagged by profanity/spam filter',
  `flag_reason` varchar(255) DEFAULT NULL COMMENT 'Reason for auto-flagging',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NULL DEFAULT NULL ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `idx_tenant_status` (`tenant_id`,`status`),
  KEY `idx_tenant_type_status` (`tenant_id`,`content_type`,`status`),
  KEY `idx_content` (`content_type`,`content_id`),
  KEY `idx_author` (`tenant_id`,`author_id`),
  KEY `idx_reviewer` (`reviewer_id`),
  KEY `idx_created` (`tenant_id`,`created_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `conversation_participants`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `conversation_participants` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `tenant_id` bigint(20) unsigned NOT NULL,
  `conversation_id` bigint(20) unsigned NOT NULL,
  `user_id` bigint(20) unsigned NOT NULL,
  `role` enum('admin','member') NOT NULL DEFAULT 'member',
  `joined_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `left_at` timestamp NULL DEFAULT NULL,
  `muted_until` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `unique_participant` (`tenant_id`,`conversation_id`,`user_id`),
  KEY `idx_cp_conv_user` (`conversation_id`,`user_id`),
  KEY `idx_cp_user` (`user_id`),
  KEY `conversation_participants_tenant_id_index` (`tenant_id`),
  CONSTRAINT `conversation_participants_conversation_id_foreign` FOREIGN KEY (`conversation_id`) REFERENCES `conversations` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `conversations`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `conversations` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `tenant_id` bigint(20) unsigned NOT NULL,
  `is_group` tinyint(1) NOT NULL DEFAULT 0,
  `group_name` varchar(100) DEFAULT NULL,
  `group_avatar_url` varchar(500) DEFAULT NULL,
  `created_by` bigint(20) unsigned DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `conversations_tenant_id_is_group_index` (`tenant_id`,`is_group`),
  KEY `conversations_tenant_id_index` (`tenant_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `cookie_consent_audit`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `cookie_consent_audit` (
  `id` bigint(20) NOT NULL AUTO_INCREMENT,
  `consent_id` bigint(20) NOT NULL COMMENT 'Reference to cookie_consents.id',
  `action` enum('created','updated','withdrawn','expired') NOT NULL COMMENT 'What happened',
  `old_values` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL COMMENT 'Previous consent state' CHECK (json_valid(`old_values`)),
  `new_values` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL COMMENT 'New consent state' CHECK (json_valid(`new_values`)),
  `ip_address` varchar(45) DEFAULT NULL COMMENT 'IP address of requester',
  `user_agent` text DEFAULT NULL COMMENT 'Browser user agent',
  `created_at` datetime DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `idx_consent_id` (`consent_id`),
  KEY `idx_action` (`action`),
  KEY `idx_created_at` (`created_at`)
) ENGINE=InnoDB AUTO_INCREMENT=121 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Audit trail of all consent changes for compliance';
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `cookie_consent_stats`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `cookie_consent_stats` (
  `id` bigint(20) NOT NULL AUTO_INCREMENT,
  `tenant_id` int(11) NOT NULL,
  `stat_date` date NOT NULL COMMENT 'Date of statistics',
  `total_consents` int(11) DEFAULT 0 COMMENT 'Total consent records created',
  `accept_all_count` int(11) DEFAULT 0 COMMENT 'Users who accepted all',
  `reject_all_count` int(11) DEFAULT 0 COMMENT 'Users who rejected all',
  `custom_count` int(11) DEFAULT 0 COMMENT 'Users who customized',
  `functional_accepted` int(11) DEFAULT 0 COMMENT 'Users who accepted functional',
  `analytics_accepted` int(11) DEFAULT 0 COMMENT 'Users who accepted analytics',
  `marketing_accepted` int(11) DEFAULT 0 COMMENT 'Users who accepted marketing',
  `withdrawals_count` int(11) DEFAULT 0 COMMENT 'Consent withdrawals',
  `created_at` datetime DEFAULT current_timestamp(),
  `updated_at` datetime DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `unique_tenant_date` (`tenant_id`,`stat_date`),
  KEY `idx_tenant_id` (`tenant_id`),
  KEY `idx_stat_date` (`stat_date`)
) ENGINE=InnoDB AUTO_INCREMENT=121 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Daily aggregated consent statistics for analytics';
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `cookie_consents`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `cookie_consents` (
  `id` bigint(20) NOT NULL AUTO_INCREMENT,
  `session_id` varchar(255) NOT NULL,
  `user_id` int(11) DEFAULT NULL,
  `tenant_id` int(11) NOT NULL,
  `essential` tinyint(1) DEFAULT 1,
  `analytics` tinyint(1) DEFAULT 0,
  `marketing` tinyint(1) DEFAULT 0,
  `functional` tinyint(1) DEFAULT 0,
  `ip_address` varchar(45) DEFAULT NULL,
  `user_agent` text DEFAULT NULL,
  `consent_string` text DEFAULT NULL,
  `created_at` datetime DEFAULT current_timestamp(),
  `updated_at` datetime DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  `expires_at` datetime DEFAULT NULL COMMENT 'When consent expires (typically 12 months)',
  `consent_version` varchar(20) DEFAULT '1.0' COMMENT 'Version of consent terms',
  `last_updated_by_user` datetime DEFAULT NULL COMMENT 'When user last changed preferences',
  `withdrawal_date` datetime DEFAULT NULL COMMENT 'When consent was withdrawn',
  `source` varchar(50) DEFAULT 'web' COMMENT 'Source: web, mobile, api',
  PRIMARY KEY (`id`),
  KEY `idx_session_id` (`session_id`),
  KEY `idx_user_id` (`user_id`),
  KEY `idx_tenant_id` (`tenant_id`),
  KEY `idx_expires_at` (`expires_at`),
  KEY `idx_consent_version` (`consent_version`),
  KEY `idx_valid_consent` (`user_id`,`tenant_id`,`expires_at`,`withdrawal_date`),
  KEY `idx_session_tenant` (`session_id`,`tenant_id`)
) ENGINE=InnoDB AUTO_INCREMENT=122 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `cookie_inventory`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `cookie_inventory` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `cookie_name` varchar(255) NOT NULL COMMENT 'Actual cookie name',
  `category` enum('essential','functional','analytics','marketing') NOT NULL COMMENT 'Cookie category',
  `purpose` text NOT NULL COMMENT 'Plain language purpose',
  `duration` varchar(100) NOT NULL COMMENT 'How long it lasts (e.g., Session, 1 year)',
  `third_party` varchar(255) DEFAULT NULL COMMENT 'First-party or provider name (e.g., Google)',
  `tenant_id` int(11) DEFAULT NULL COMMENT 'NULL = global cookie, or specific tenant ID',
  `is_active` tinyint(1) DEFAULT 1 COMMENT 'Whether cookie is currently in use',
  `created_at` datetime DEFAULT current_timestamp(),
  `updated_at` datetime DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `unique_cookie_tenant` (`cookie_name`,`tenant_id`),
  KEY `idx_category` (`category`),
  KEY `idx_tenant_id` (`tenant_id`),
  KEY `idx_is_active` (`is_active`)
) ENGINE=InnoDB AUTO_INCREMENT=5 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Inventory of all cookies used by the platform';
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `coordinator_tasks`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `coordinator_tasks` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `tenant_id` int(10) unsigned NOT NULL,
  `assigned_to` int(10) unsigned NOT NULL COMMENT 'Admin user this task is for',
  `user_id` int(10) unsigned DEFAULT NULL COMMENT 'Optional: member this task relates to',
  `title` varchar(255) NOT NULL,
  `description` text DEFAULT NULL,
  `priority` enum('low','medium','high','urgent') NOT NULL DEFAULT 'medium',
  `status` enum('pending','in_progress','completed','cancelled') NOT NULL DEFAULT 'pending',
  `due_date` date DEFAULT NULL,
  `completed_at` datetime DEFAULT NULL,
  `created_by` int(10) unsigned NOT NULL,
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  `updated_at` datetime NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `idx_coordinator_tasks_tenant_assigned` (`tenant_id`,`assigned_to`,`status`),
  KEY `idx_coordinator_tasks_tenant_user` (`tenant_id`,`user_id`),
  KEY `idx_coordinator_tasks_due` (`tenant_id`,`due_date`),
  KEY `idx_coordinator_tasks_status` (`tenant_id`,`status`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `credit_donations`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `credit_donations` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `tenant_id` int(11) NOT NULL,
  `donor_id` int(11) NOT NULL,
  `recipient_type` enum('user','community_fund') NOT NULL DEFAULT 'community_fund',
  `recipient_id` int(11) DEFAULT NULL COMMENT 'User ID if donating to a user, NULL for community fund',
  `amount` decimal(10,2) NOT NULL,
  `message` varchar(500) DEFAULT NULL,
  `transaction_id` int(11) DEFAULT NULL COMMENT 'Link to wallet transaction',
  `created_at` timestamp NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `idx_tenant` (`tenant_id`),
  KEY `idx_donor` (`donor_id`),
  KEY `idx_recipient` (`recipient_type`,`recipient_id`),
  CONSTRAINT `credit_donations_ibfk_1` FOREIGN KEY (`tenant_id`) REFERENCES `tenants` (`id`) ON DELETE CASCADE,
  CONSTRAINT `credit_donations_ibfk_2` FOREIGN KEY (`donor_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `cron_job_settings`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `cron_job_settings` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `job_id` varchar(100) NOT NULL,
  `is_enabled` tinyint(1) DEFAULT 1,
  `custom_schedule` varchar(50) DEFAULT NULL,
  `notify_on_failure` tinyint(1) DEFAULT 0,
  `notify_emails` text DEFAULT NULL,
  `max_retries` int(11) DEFAULT 0,
  `timeout_seconds` int(11) DEFAULT 300,
  `created_at` datetime DEFAULT current_timestamp(),
  `updated_at` datetime DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `job_id` (`job_id`),
  KEY `idx_job_id` (`job_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb3 COLLATE=utf8mb3_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `cron_jobs`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `cron_jobs` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `tenant_id` int(11) NOT NULL,
  `job_name` varchar(100) NOT NULL COMMENT 'Name/identifier of the cron job',
  `job_type` varchar(50) DEFAULT NULL COMMENT 'Category of job (email, cleanup, analytics, etc.)',
  `last_run` timestamp NULL DEFAULT NULL COMMENT 'When this job last executed',
  `last_status` enum('success','failed','running') DEFAULT NULL,
  `last_duration` int(11) DEFAULT NULL COMMENT 'Execution time in seconds',
  `last_error` text DEFAULT NULL COMMENT 'Error message if failed',
  `next_run` timestamp NULL DEFAULT NULL COMMENT 'Scheduled next execution',
  `run_count` int(11) DEFAULT 0 COMMENT 'Total number of executions',
  `failure_count` int(11) DEFAULT 0 COMMENT 'Total number of failures',
  `enabled` tinyint(1) DEFAULT 1 COMMENT 'Whether this job is active',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  `content` text DEFAULT NULL,
  `errors` varchar(255) DEFAULT NULL,
  `pages` varchar(255) DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `unique_tenant_job` (`tenant_id`,`job_name`),
  KEY `idx_tenant_last_run` (`tenant_id`,`last_run`),
  KEY `idx_next_run` (`next_run`),
  KEY `idx_enabled` (`enabled`),
  CONSTRAINT `fk_cron_tenant` FOREIGN KEY (`tenant_id`) REFERENCES `tenants` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Tracks cron job execution for system health monitoring';
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `cron_logs`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `cron_logs` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `job_id` varchar(100) NOT NULL,
  `status` enum('success','error','running') DEFAULT 'running',
  `output` text DEFAULT NULL,
  `duration_seconds` decimal(10,2) DEFAULT NULL,
  `executed_at` datetime DEFAULT current_timestamp(),
  `executed_by` int(11) DEFAULT NULL,
  `tenant_id` int(11) DEFAULT NULL,
  `clicked_at` timestamp NULL DEFAULT NULL,
  `reset_token` varchar(255) DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `idx_job_id` (`job_id`),
  KEY `idx_executed_at` (`executed_at`),
  KEY `idx_status` (`status`)
) ENGINE=InnoDB AUTO_INCREMENT=191092 DEFAULT CHARSET=utf8mb3 COLLATE=utf8mb3_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `cron_settings`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `cron_settings` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `setting_key` varchar(100) NOT NULL,
  `setting_value` text DEFAULT NULL,
  `updated_at` datetime DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `setting_key` (`setting_key`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb3 COLLATE=utf8mb3_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `custom_badges`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `custom_badges` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `tenant_id` int(11) NOT NULL,
  `badge_key` varchar(50) NOT NULL,
  `name` varchar(100) NOT NULL,
  `description` text DEFAULT NULL,
  `icon` varchar(50) DEFAULT NULL,
  `icon_url` varchar(255) DEFAULT NULL,
  `badge_type` varchar(50) DEFAULT 'custom',
  `trigger_type` enum('manual','automatic','event') DEFAULT 'manual',
  `trigger_condition` text DEFAULT NULL,
  `xp_reward` int(11) DEFAULT 25,
  `is_active` tinyint(1) DEFAULT 1,
  `created_by` int(11) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `category` varchar(100) DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `unique_custom_badge` (`tenant_id`,`badge_key`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb3 COLLATE=utf8mb3_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `daily_rewards`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `daily_rewards` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `tenant_id` int(11) NOT NULL,
  `user_id` int(11) NOT NULL,
  `reward_date` date NOT NULL,
  `xp_earned` int(11) DEFAULT 0,
  `streak_day` int(11) DEFAULT 1,
  `milestone_bonus` int(11) DEFAULT 0,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `claimed_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `unique_daily_reward` (`tenant_id`,`user_id`,`reward_date`),
  KEY `idx_user_date` (`user_id`,`reward_date`),
  KEY `idx_tenant_date` (`tenant_id`,`reward_date`)
) ENGINE=InnoDB AUTO_INCREMENT=17 DEFAULT CHARSET=utf8mb3 COLLATE=utf8mb3_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `data_breach_log`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `data_breach_log` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `tenant_id` int(11) NOT NULL,
  `breach_id` varchar(50) NOT NULL,
  `breach_type` varchar(100) NOT NULL,
  `severity` enum('low','medium','high','critical') DEFAULT 'medium',
  `description` text NOT NULL,
  `data_categories_affected` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin NOT NULL CHECK (json_valid(`data_categories_affected`)),
  `number_of_records_affected` int(11) DEFAULT NULL,
  `number_of_users_affected` int(11) DEFAULT NULL,
  `detected_at` datetime NOT NULL,
  `occurred_at` datetime DEFAULT NULL,
  `contained_at` datetime DEFAULT NULL,
  `resolved_at` datetime DEFAULT NULL,
  `reported_to_authority` tinyint(1) DEFAULT 0,
  `reported_to_authority_at` datetime DEFAULT NULL,
  `dpa_notified_at` datetime DEFAULT NULL,
  `authority_reference` varchar(200) DEFAULT NULL,
  `authority_response` text DEFAULT NULL,
  `users_notified` tinyint(1) DEFAULT 0,
  `users_notified_at` datetime DEFAULT NULL,
  `notification_method` varchar(100) DEFAULT NULL,
  `remediation_actions` text DEFAULT NULL,
  `root_cause` text DEFAULT NULL,
  `lessons_learned` text DEFAULT NULL,
  `prevention_measures` text DEFAULT NULL,
  `escalated_at` datetime DEFAULT NULL,
  `escalated_by` int(11) DEFAULT NULL,
  `created_by` int(11) NOT NULL,
  `updated_by` int(11) DEFAULT NULL,
  `status` enum('detected','investigating','contained','resolved','closed') DEFAULT 'detected',
  `created_at` datetime DEFAULT current_timestamp(),
  `updated_at` datetime DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `breach_id` (`breach_id`),
  KEY `idx_tenant_id` (`tenant_id`),
  KEY `idx_detected_at` (`detected_at`),
  KEY `idx_severity` (`severity`),
  KEY `idx_status` (`status`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `data_processing_log`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `data_processing_log` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `tenant_id` int(11) NOT NULL,
  `processing_activity` varchar(200) NOT NULL,
  `purpose` varchar(500) NOT NULL,
  `legal_basis` enum('consent','contract','legal_obligation','vital_interests','public_task','legitimate_interests') NOT NULL,
  `data_categories` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin NOT NULL CHECK (json_valid(`data_categories`)),
  `data_subjects` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin NOT NULL CHECK (json_valid(`data_subjects`)),
  `recipients` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`recipients`)),
  `third_country_transfers` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`third_country_transfers`)),
  `retention_period` varchar(100) NOT NULL,
  `security_measures` text DEFAULT NULL,
  `dpia_required` tinyint(1) DEFAULT 0,
  `dpia_conducted` tinyint(1) DEFAULT 0,
  `dpia_date` date DEFAULT NULL,
  `is_active` tinyint(1) DEFAULT 1,
  `created_at` datetime DEFAULT current_timestamp(),
  `updated_at` datetime DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `idx_tenant_id` (`tenant_id`),
  KEY `idx_processing_activity` (`processing_activity`)
) ENGINE=InnoDB AUTO_INCREMENT=7 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `data_retention_policies`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `data_retention_policies` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `tenant_id` int(11) NOT NULL,
  `data_category` varchar(100) NOT NULL,
  `table_name` varchar(100) NOT NULL,
  `retention_days` int(11) NOT NULL,
  `deletion_method` enum('hard_delete','soft_delete','anonymize') DEFAULT 'soft_delete',
  `legal_basis` text DEFAULT NULL,
  `exception_criteria` text DEFAULT NULL,
  `last_cleanup_at` datetime DEFAULT NULL,
  `records_deleted_last` int(11) DEFAULT 0,
  `is_active` tinyint(1) DEFAULT 1,
  `created_at` datetime DEFAULT current_timestamp(),
  `updated_at` datetime DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `unique_tenant_category` (`tenant_id`,`data_category`),
  KEY `idx_tenant_id` (`tenant_id`)
) ENGINE=InnoDB AUTO_INCREMENT=8 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `deliverability_events`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `deliverability_events` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `tenant_id` int(11) NOT NULL,
  `email_type` varchar(100) NOT NULL COMMENT 'newsletter, transactional, digest, etc.',
  `email_id` int(11) DEFAULT NULL COMMENT 'Reference to newsletter or email record',
  `recipient_email` varchar(255) NOT NULL,
  `recipient_user_id` int(11) DEFAULT NULL,
  `event_type` enum('sent','delivered','bounced','complained','opened','clicked','unsubscribed') NOT NULL,
  `event_data` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL COMMENT 'Additional event metadata' CHECK (json_valid(`event_data`)),
  `bounce_type` varchar(50) DEFAULT NULL COMMENT 'hard, soft, etc.',
  `bounce_reason` text DEFAULT NULL,
  `ip_address` varchar(45) DEFAULT NULL,
  `user_agent` text DEFAULT NULL,
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `idx_tenant` (`tenant_id`),
  KEY `idx_recipient` (`recipient_email`),
  KEY `idx_event_type` (`event_type`),
  KEY `idx_email` (`email_type`,`email_id`),
  KEY `idx_created` (`created_at`),
  KEY `idx_tenant_event` (`tenant_id`,`event_type`,`created_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Email deliverability tracking events';
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `deliverable_comments`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `deliverable_comments` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `tenant_id` int(11) NOT NULL,
  `deliverable_id` int(11) NOT NULL,
  `user_id` int(11) NOT NULL,
  `comment_text` text NOT NULL,
  `comment_type` enum('general','blocker','question','update','resolution') DEFAULT 'general',
  `parent_comment_id` int(11) DEFAULT NULL,
  `reactions` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`reactions`)),
  `is_pinned` tinyint(1) DEFAULT 0,
  `is_edited` tinyint(1) DEFAULT 0,
  `edited_at` datetime DEFAULT NULL,
  `is_deleted` tinyint(1) DEFAULT 0,
  `deleted_at` datetime DEFAULT NULL,
  `mentioned_user_ids` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`mentioned_user_ids`)),
  `created_at` datetime DEFAULT current_timestamp(),
  `updated_at` datetime DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `idx_tenant_id` (`tenant_id`),
  KEY `idx_deliverable_id` (`deliverable_id`),
  KEY `idx_user_id` (`user_id`),
  KEY `idx_parent_comment` (`parent_comment_id`),
  KEY `idx_created_at` (`created_at`),
  KEY `idx_deliverable_created` (`deliverable_id`,`created_at`),
  CONSTRAINT `deliverable_comments_ibfk_1` FOREIGN KEY (`tenant_id`) REFERENCES `tenants` (`id`) ON DELETE CASCADE,
  CONSTRAINT `deliverable_comments_ibfk_2` FOREIGN KEY (`deliverable_id`) REFERENCES `deliverables` (`id`) ON DELETE CASCADE,
  CONSTRAINT `deliverable_comments_ibfk_3` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE,
  CONSTRAINT `deliverable_comments_ibfk_4` FOREIGN KEY (`parent_comment_id`) REFERENCES `deliverable_comments` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `deliverable_history`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `deliverable_history` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `tenant_id` int(11) NOT NULL,
  `deliverable_id` int(11) NOT NULL,
  `action_type` enum('created','status_changed','assigned','reassigned','progress_updated','deadline_changed','priority_changed','milestone_completed','commented','attachment_added','completed','cancelled','reopened','metadata_updated') NOT NULL,
  `user_id` int(11) NOT NULL,
  `action_timestamp` datetime DEFAULT current_timestamp(),
  `old_value` text DEFAULT NULL,
  `new_value` text DEFAULT NULL,
  `field_name` varchar(100) DEFAULT NULL,
  `change_description` text DEFAULT NULL,
  `ip_address` varchar(45) DEFAULT NULL,
  `user_agent` text DEFAULT NULL,
  `created_at` datetime DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `idx_tenant_id` (`tenant_id`),
  KEY `idx_deliverable_id` (`deliverable_id`),
  KEY `idx_user_id` (`user_id`),
  KEY `idx_action_type` (`action_type`),
  KEY `idx_timestamp` (`action_timestamp`),
  KEY `idx_deliverable_timestamp` (`deliverable_id`,`action_timestamp`),
  CONSTRAINT `deliverable_history_ibfk_1` FOREIGN KEY (`tenant_id`) REFERENCES `tenants` (`id`) ON DELETE CASCADE,
  CONSTRAINT `deliverable_history_ibfk_2` FOREIGN KEY (`deliverable_id`) REFERENCES `deliverables` (`id`) ON DELETE CASCADE,
  CONSTRAINT `deliverable_history_ibfk_3` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `deliverable_milestones`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `deliverable_milestones` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `tenant_id` int(11) NOT NULL,
  `deliverable_id` int(11) NOT NULL,
  `title` varchar(255) NOT NULL,
  `description` text DEFAULT NULL,
  `order_position` int(11) DEFAULT 0,
  `status` enum('pending','in_progress','completed','skipped') DEFAULT 'pending',
  `completed_at` datetime DEFAULT NULL,
  `completed_by` int(11) DEFAULT NULL,
  `due_date` datetime DEFAULT NULL,
  `estimated_hours` decimal(8,2) DEFAULT NULL,
  `depends_on_milestone_ids` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`depends_on_milestone_ids`)),
  `created_at` datetime DEFAULT current_timestamp(),
  `updated_at` datetime DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `idx_tenant_id` (`tenant_id`),
  KEY `idx_deliverable_id` (`deliverable_id`),
  KEY `idx_status` (`status`),
  KEY `idx_order` (`deliverable_id`,`order_position`),
  KEY `completed_by` (`completed_by`),
  CONSTRAINT `deliverable_milestones_ibfk_1` FOREIGN KEY (`tenant_id`) REFERENCES `tenants` (`id`) ON DELETE CASCADE,
  CONSTRAINT `deliverable_milestones_ibfk_2` FOREIGN KEY (`deliverable_id`) REFERENCES `deliverables` (`id`) ON DELETE CASCADE,
  CONSTRAINT `deliverable_milestones_ibfk_3` FOREIGN KEY (`completed_by`) REFERENCES `users` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `deliverables`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `deliverables` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `tenant_id` int(11) NOT NULL,
  `title` varchar(255) NOT NULL,
  `description` text DEFAULT NULL,
  `category` varchar(100) DEFAULT 'general',
  `priority` enum('low','medium','high','urgent') DEFAULT 'medium',
  `owner_id` int(11) NOT NULL,
  `user_id` int(11) DEFAULT NULL,
  `assigned_to` int(11) DEFAULT NULL,
  `assigned_group_id` int(11) DEFAULT NULL,
  `start_date` datetime DEFAULT NULL,
  `due_date` datetime DEFAULT NULL,
  `completed_at` datetime DEFAULT NULL,
  `status` enum('draft','ready','in_progress','blocked','review','completed','cancelled','on_hold') DEFAULT 'draft',
  `progress_percentage` decimal(5,2) DEFAULT 0.00,
  `estimated_hours` decimal(8,2) DEFAULT NULL,
  `actual_hours` decimal(8,2) DEFAULT NULL,
  `parent_deliverable_id` int(11) DEFAULT NULL,
  `blocking_deliverable_ids` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`blocking_deliverable_ids`)),
  `depends_on_deliverable_ids` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`depends_on_deliverable_ids`)),
  `tags` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`tags`)),
  `custom_fields` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`custom_fields`)),
  `delivery_confidence` enum('low','medium','high') DEFAULT 'medium',
  `risk_level` enum('low','medium','high','critical') DEFAULT 'low',
  `risk_notes` text DEFAULT NULL,
  `watchers` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`watchers`)),
  `collaborators` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`collaborators`)),
  `attachment_urls` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`attachment_urls`)),
  `external_links` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`external_links`)),
  `created_at` datetime DEFAULT current_timestamp(),
  `updated_at` datetime DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `idx_tenant_id` (`tenant_id`),
  KEY `idx_owner_id` (`owner_id`),
  KEY `idx_assigned_to` (`assigned_to`),
  KEY `idx_assigned_group` (`assigned_group_id`),
  KEY `idx_status` (`status`),
  KEY `idx_priority` (`priority`),
  KEY `idx_due_date` (`due_date`),
  KEY `idx_parent` (`parent_deliverable_id`),
  KEY `idx_tenant_status` (`tenant_id`,`status`),
  KEY `idx_tenant_assigned` (`tenant_id`,`assigned_to`),
  CONSTRAINT `deliverables_ibfk_1` FOREIGN KEY (`tenant_id`) REFERENCES `tenants` (`id`) ON DELETE CASCADE,
  CONSTRAINT `deliverables_ibfk_2` FOREIGN KEY (`owner_id`) REFERENCES `users` (`id`) ON DELETE CASCADE,
  CONSTRAINT `deliverables_ibfk_3` FOREIGN KEY (`assigned_to`) REFERENCES `users` (`id`) ON DELETE SET NULL,
  CONSTRAINT `deliverables_ibfk_4` FOREIGN KEY (`assigned_group_id`) REFERENCES `groups` (`id`) ON DELETE SET NULL,
  CONSTRAINT `deliverables_ibfk_5` FOREIGN KEY (`parent_deliverable_id`) REFERENCES `deliverables` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `email_settings`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `email_settings` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `tenant_id` int(11) NOT NULL,
  `setting_key` varchar(100) NOT NULL,
  `setting_value` text DEFAULT NULL,
  `is_encrypted` tinyint(1) DEFAULT 0,
  `created_at` timestamp NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NULL DEFAULT NULL ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `idx_email_settings_tenant_key` (`tenant_id`,`setting_key`),
  KEY `idx_email_settings_tenant` (`tenant_id`)
) ENGINE=InnoDB AUTO_INCREMENT=5 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `email_verification_tokens`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `email_verification_tokens` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `user_id` int(11) NOT NULL,
  `tenant_id` int(11) NOT NULL,
  `token` varchar(255) NOT NULL COMMENT 'Hashed verification token',
  `created_at` datetime DEFAULT current_timestamp(),
  `expires_at` datetime NOT NULL COMMENT 'Token expiry time',
  PRIMARY KEY (`id`),
  KEY `idx_user_id` (`user_id`),
  KEY `idx_expires_at` (`expires_at`),
  KEY `idx_tenant_id` (`tenant_id`),
  KEY `idx_tenant_user` (`tenant_id`,`user_id`)
) ENGINE=InnoDB AUTO_INCREMENT=87 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `error404_logs`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `error404_logs` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `error_404_log`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `error_404_log` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `url` varchar(1000) NOT NULL,
  `referer` varchar(1000) DEFAULT NULL,
  `user_agent` varchar(500) DEFAULT NULL,
  `ip_address` varchar(45) DEFAULT NULL,
  `user_id` int(11) DEFAULT NULL,
  `hit_count` int(11) DEFAULT 1,
  `first_seen_at` datetime NOT NULL,
  `last_seen_at` datetime NOT NULL,
  `resolved` tinyint(1) DEFAULT 0,
  `redirect_id` int(11) DEFAULT NULL,
  `notes` text DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `idx_url` (`url`(255)),
  KEY `idx_resolved` (`resolved`),
  KEY `idx_last_seen` (`last_seen_at`),
  KEY `idx_hit_count` (`hit_count`),
  KEY `user_id` (`user_id`),
  CONSTRAINT `error_404_log_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB AUTO_INCREMENT=10961 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `event_attendance`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `event_attendance` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `event_id` int(11) NOT NULL,
  `user_id` int(11) NOT NULL,
  `tenant_id` int(11) NOT NULL,
  `checked_in_at` timestamp NULL DEFAULT NULL COMMENT 'When the attendee was checked in',
  `checked_in_by` int(11) DEFAULT NULL COMMENT 'User who checked them in (organizer/admin)',
  `checked_out_at` timestamp NULL DEFAULT NULL COMMENT 'Optional: when they left',
  `hours_credited` decimal(6,2) DEFAULT NULL COMMENT 'Time credits awarded',
  `notes` text DEFAULT NULL COMMENT 'Organizer notes about attendance',
  `created_at` timestamp NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `uk_attendance_event_user` (`event_id`,`user_id`),
  KEY `idx_attendance_event` (`event_id`),
  KEY `idx_attendance_user` (`user_id`),
  KEY `idx_attendance_tenant` (`tenant_id`),
  CONSTRAINT `fk_attendance_event` FOREIGN KEY (`event_id`) REFERENCES `events` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_attendance_tenant` FOREIGN KEY (`tenant_id`) REFERENCES `tenants` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_attendance_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `event_recurrence_rules`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `event_recurrence_rules` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `event_id` int(11) NOT NULL COMMENT 'The parent/template event',
  `tenant_id` int(11) NOT NULL,
  `frequency` enum('daily','weekly','monthly','yearly','custom') NOT NULL DEFAULT 'weekly',
  `interval_value` int(10) unsigned NOT NULL DEFAULT 1 COMMENT 'Every N frequency units',
  `days_of_week` varchar(50) DEFAULT NULL COMMENT 'Comma-separated: 0=Sun,1=Mon,...,6=Sat',
  `day_of_month` int(10) unsigned DEFAULT NULL COMMENT 'For monthly: 1-31',
  `month_of_year` int(10) unsigned DEFAULT NULL COMMENT 'For yearly: 1-12',
  `rrule` text DEFAULT NULL COMMENT 'iCal RRULE string for custom patterns',
  `ends_type` enum('never','after_count','on_date') NOT NULL DEFAULT 'never',
  `ends_after_count` int(10) unsigned DEFAULT NULL COMMENT 'Stop after N occurrences',
  `ends_on_date` date DEFAULT NULL COMMENT 'Stop on this date',
  `created_at` timestamp NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `idx_recurrence_event` (`event_id`),
  KEY `idx_recurrence_tenant` (`tenant_id`),
  CONSTRAINT `fk_recurrence_event` FOREIGN KEY (`event_id`) REFERENCES `events` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_recurrence_tenant` FOREIGN KEY (`tenant_id`) REFERENCES `tenants` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `event_reminder_sent`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `event_reminder_sent` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `tenant_id` int(10) unsigned NOT NULL,
  `event_id` int(10) unsigned NOT NULL,
  `user_id` int(10) unsigned NOT NULL,
  `reminder_type` enum('24h','1h') NOT NULL DEFAULT '24h',
  `sent_at` datetime NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_event_reminder` (`tenant_id`,`event_id`,`user_id`,`reminder_type`),
  KEY `idx_event_reminder_lookup` (`tenant_id`,`event_id`,`user_id`,`reminder_type`),
  KEY `idx_event_reminder_cleanup` (`sent_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `event_reminders`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `event_reminders` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `event_id` int(11) NOT NULL,
  `user_id` int(11) NOT NULL,
  `tenant_id` int(11) NOT NULL,
  `remind_before_minutes` int(10) unsigned NOT NULL COMMENT '60=1hr, 1440=1day, 10080=1week',
  `reminder_type` enum('platform','email','both') NOT NULL DEFAULT 'both',
  `sent_at` timestamp NULL DEFAULT NULL COMMENT 'When the reminder was actually sent',
  `scheduled_for` timestamp NOT NULL COMMENT 'When the reminder should fire',
  `status` enum('pending','sent','failed','cancelled') NOT NULL DEFAULT 'pending',
  `created_at` timestamp NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `uk_reminder_event_user_time` (`event_id`,`user_id`,`remind_before_minutes`),
  KEY `idx_reminder_event` (`event_id`),
  KEY `idx_reminder_user` (`user_id`),
  KEY `idx_reminder_tenant` (`tenant_id`),
  KEY `idx_reminder_scheduled` (`scheduled_for`,`status`),
  KEY `idx_reminder_pending` (`status`,`scheduled_for`),
  CONSTRAINT `fk_reminder_event` FOREIGN KEY (`event_id`) REFERENCES `events` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_reminder_tenant` FOREIGN KEY (`tenant_id`) REFERENCES `tenants` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_reminder_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `event_rsvps`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `event_rsvps` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `tenant_id` int(10) unsigned NOT NULL DEFAULT 1,
  `event_id` int(11) NOT NULL,
  `user_id` int(11) NOT NULL,
  `status` enum('going','interested','maybe','not_going','declined','invited','attended','cancelled','waitlisted') NOT NULL DEFAULT 'going',
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  `updated_at` datetime DEFAULT NULL,
  `checked_in_at` datetime DEFAULT NULL,
  `checked_out_at` datetime DEFAULT NULL,
  `award` varchar(255) DEFAULT NULL,
  `event` varchar(255) DEFAULT NULL,
  `is_federated` tinyint(1) NOT NULL DEFAULT 0,
  `source_tenant_id` int(11) DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `idx_rsvp_unique` (`event_id`,`user_id`),
  KEY `idx_rsvp_user` (`user_id`),
  KEY `idx_tenant_id` (`tenant_id`),
  KEY `idx_federated_events` (`is_federated`,`source_tenant_id`),
  CONSTRAINT `event_rsvps_event_id_foreign` FOREIGN KEY (`event_id`) REFERENCES `events` (`id`) ON DELETE CASCADE,
  CONSTRAINT `event_rsvps_user_id_foreign` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=17 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `event_series`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `event_series` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `tenant_id` int(11) NOT NULL,
  `title` varchar(255) NOT NULL COMMENT 'Series title e.g. "Weekly Book Club"',
  `description` text DEFAULT NULL,
  `created_by` int(11) NOT NULL,
  `created_at` timestamp NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `idx_series_tenant` (`tenant_id`),
  KEY `idx_series_creator` (`created_by`),
  CONSTRAINT `fk_series_creator` FOREIGN KEY (`created_by`) REFERENCES `users` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_series_tenant` FOREIGN KEY (`tenant_id`) REFERENCES `tenants` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `event_waitlist`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `event_waitlist` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `event_id` int(11) NOT NULL,
  `user_id` int(11) NOT NULL,
  `tenant_id` int(11) NOT NULL,
  `position` int(10) unsigned NOT NULL COMMENT 'Position in waitlist queue',
  `status` enum('waiting','promoted','cancelled','expired') NOT NULL DEFAULT 'waiting',
  `promoted_at` timestamp NULL DEFAULT NULL,
  `cancelled_at` timestamp NULL DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `uk_waitlist_event_user` (`event_id`,`user_id`),
  KEY `idx_waitlist_event` (`event_id`),
  KEY `idx_waitlist_user` (`user_id`),
  KEY `idx_waitlist_tenant` (`tenant_id`),
  KEY `idx_waitlist_status` (`event_id`,`status`),
  KEY `idx_waitlist_position` (`event_id`,`position`),
  CONSTRAINT `fk_waitlist_event` FOREIGN KEY (`event_id`) REFERENCES `events` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_waitlist_tenant` FOREIGN KEY (`tenant_id`) REFERENCES `tenants` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_waitlist_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `events`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `events` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `tenant_id` int(11) NOT NULL,
  `user_id` int(11) NOT NULL COMMENT 'Organizer',
  `group_id` int(11) DEFAULT NULL,
  `title` varchar(255) NOT NULL,
  `description` text NOT NULL,
  `location` varchar(255) DEFAULT NULL,
  `start_time` datetime NOT NULL,
  `start_date` datetime DEFAULT NULL,
  `end_time` datetime DEFAULT NULL,
  `max_attendees` int(11) DEFAULT NULL,
  `is_online` tinyint(1) NOT NULL DEFAULT 0,
  `online_link` varchar(512) DEFAULT NULL,
  `image_url` varchar(512) DEFAULT NULL,
  `video_url` varchar(512) DEFAULT NULL,
  `cover_image` varchar(255) DEFAULT NULL,
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  `updated_at` datetime DEFAULT NULL ON UPDATE current_timestamp(),
  `sdg_goals` longtext DEFAULT NULL CHECK (json_valid(`sdg_goals`)),
  `category_id` int(11) DEFAULT NULL,
  `volunteer_opportunity_id` int(11) DEFAULT NULL COMMENT 'Linked vol opp (Signed)',
  `auto_log_hours` tinyint(1) DEFAULT 0 COMMENT 'Auto-log hours upon attendance',
  `latitude` decimal(10,8) DEFAULT NULL,
  `longitude` decimal(11,8) DEFAULT NULL,
  `award` varchar(255) DEFAULT NULL,
  `event` varchar(255) DEFAULT NULL,
  `federated_visibility` enum('none','listed','joinable') NOT NULL DEFAULT 'none',
  `allow_remote_attendance` tinyint(1) NOT NULL DEFAULT 0,
  `status` enum('active','cancelled','completed','draft') DEFAULT 'active',
  `parent_event_id` int(11) DEFAULT NULL COMMENT 'Links to parent recurring event template',
  `occurrence_date` date DEFAULT NULL COMMENT 'Specific date for this occurrence',
  `is_recurring_template` tinyint(1) NOT NULL DEFAULT 0 COMMENT 'True if this event is a recurrence template',
  `cancellation_reason` text DEFAULT NULL,
  `cancelled_at` timestamp NULL DEFAULT NULL,
  `cancelled_by` int(11) DEFAULT NULL,
  `series_id` int(11) DEFAULT NULL COMMENT 'Links event to a series',
  PRIMARY KEY (`id`),
  KEY `idx_event_tenant` (`tenant_id`),
  KEY `idx_event_start` (`start_time`),
  KEY `fk_event_group` (`group_id`),
  KEY `fk_events_category` (`category_id`),
  KEY `fk_event_opportunity` (`volunteer_opportunity_id`),
  KEY `idx_events_user_id` (`user_id`),
  KEY `idx_events_parent` (`parent_event_id`),
  KEY `idx_events_occurrence` (`occurrence_date`),
  KEY `idx_events_status` (`status`),
  KEY `idx_events_series` (`series_id`),
  KEY `idx_events_group` (`group_id`),
  KEY `idx_events_tenant_status_start` (`tenant_id`,`status`,`start_time`),
  CONSTRAINT `fk_event_group` FOREIGN KEY (`group_id`) REFERENCES `groups` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_event_opportunity` FOREIGN KEY (`volunteer_opportunity_id`) REFERENCES `vol_opportunities` (`id`) ON DELETE SET NULL,
  CONSTRAINT `fk_events_category` FOREIGN KEY (`category_id`) REFERENCES `categories` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB AUTO_INCREMENT=15 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `exchange_history`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `exchange_history` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `exchange_id` int(11) NOT NULL,
  `action` varchar(100) NOT NULL COMMENT 'e.g., created, accepted, broker_approved, confirmed',
  `actor_id` int(11) DEFAULT NULL COMMENT 'User who performed action',
  `actor_role` enum('requester','provider','broker','system') NOT NULL,
  `old_status` varchar(50) DEFAULT NULL,
  `new_status` varchar(50) DEFAULT NULL,
  `notes` text DEFAULT NULL,
  `metadata` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL COMMENT 'Additional action-specific data' CHECK (json_valid(`metadata`)),
  `created_at` timestamp NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `idx_exchange` (`exchange_id`),
  KEY `idx_actor` (`actor_id`),
  KEY `idx_action` (`action`),
  CONSTRAINT `exchange_history_ibfk_1` FOREIGN KEY (`exchange_id`) REFERENCES `exchange_requests` (`id`) ON DELETE CASCADE,
  CONSTRAINT `exchange_history_ibfk_2` FOREIGN KEY (`actor_id`) REFERENCES `users` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB AUTO_INCREMENT=29 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `exchange_ratings`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `exchange_ratings` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `tenant_id` int(11) NOT NULL,
  `exchange_id` int(11) NOT NULL,
  `rater_id` int(11) NOT NULL COMMENT 'User giving the rating',
  `rated_id` int(11) NOT NULL COMMENT 'User being rated',
  `rating` tinyint(4) NOT NULL COMMENT '1-5 star rating',
  `comment` text DEFAULT NULL,
  `role` enum('requester','provider') NOT NULL COMMENT 'Role of the rater in the exchange',
  `created_at` timestamp NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `uk_exchange_rater` (`exchange_id`,`rater_id`),
  KEY `idx_tenant` (`tenant_id`),
  KEY `idx_rated` (`rated_id`),
  KEY `idx_exchange` (`exchange_id`),
  KEY `rater_id` (`rater_id`),
  CONSTRAINT `exchange_ratings_ibfk_1` FOREIGN KEY (`tenant_id`) REFERENCES `tenants` (`id`) ON DELETE CASCADE,
  CONSTRAINT `exchange_ratings_ibfk_2` FOREIGN KEY (`exchange_id`) REFERENCES `exchange_requests` (`id`) ON DELETE CASCADE,
  CONSTRAINT `exchange_ratings_ibfk_3` FOREIGN KEY (`rater_id`) REFERENCES `users` (`id`) ON DELETE CASCADE,
  CONSTRAINT `exchange_ratings_ibfk_4` FOREIGN KEY (`rated_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `exchange_requests`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `exchange_requests` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `tenant_id` int(11) NOT NULL,
  `listing_id` int(11) NOT NULL,
  `requester_id` int(11) NOT NULL COMMENT 'User requesting the exchange',
  `provider_id` int(11) NOT NULL COMMENT 'Listing owner providing service',
  `proposed_hours` decimal(5,2) NOT NULL,
  `proposed_date` date DEFAULT NULL,
  `proposed_time` time DEFAULT NULL,
  `proposed_location` varchar(255) DEFAULT NULL,
  `requester_notes` text DEFAULT NULL,
  `status` enum('pending_provider','pending_broker','accepted','scheduled','in_progress','pending_confirmation','completed','disputed','cancelled','expired') DEFAULT 'pending_provider',
  `broker_id` int(11) DEFAULT NULL COMMENT 'Assigned broker for this exchange',
  `broker_notes` text DEFAULT NULL,
  `broker_approved_at` timestamp NULL DEFAULT NULL,
  `broker_conditions` text DEFAULT NULL COMMENT 'Conditions set by broker',
  `requester_confirmed_at` timestamp NULL DEFAULT NULL,
  `requester_confirmed_hours` decimal(5,2) DEFAULT NULL,
  `requester_feedback` text DEFAULT NULL,
  `requester_rating` tinyint(4) DEFAULT NULL COMMENT '1-5 rating',
  `provider_confirmed_at` timestamp NULL DEFAULT NULL,
  `provider_confirmed_hours` decimal(5,2) DEFAULT NULL,
  `provider_feedback` text DEFAULT NULL,
  `provider_rating` tinyint(4) DEFAULT NULL COMMENT '1-5 rating',
  `final_hours` decimal(5,2) DEFAULT NULL COMMENT 'Agreed hours after confirmation',
  `transaction_id` int(11) DEFAULT NULL COMMENT 'Link to wallet transaction',
  `completed_at` timestamp NULL DEFAULT NULL,
  `risk_tag_id` int(11) DEFAULT NULL COMMENT 'Link to listing risk tag if exists',
  `risk_acknowledged_at` timestamp NULL DEFAULT NULL COMMENT 'When requester acknowledged risk',
  `cancelled_by` int(11) DEFAULT NULL,
  `cancelled_at` timestamp NULL DEFAULT NULL,
  `cancellation_reason` text DEFAULT NULL,
  `decline_reason` text DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  `expires_at` timestamp NULL DEFAULT NULL COMMENT 'Request expires if not actioned',
  `prep_time` decimal(5,2) DEFAULT NULL COMMENT 'Preparation time in hours',
  PRIMARY KEY (`id`),
  KEY `idx_tenant_status` (`tenant_id`,`status`),
  KEY `idx_requester` (`tenant_id`,`requester_id`),
  KEY `idx_provider` (`tenant_id`,`provider_id`),
  KEY `idx_broker` (`tenant_id`,`broker_id`),
  KEY `idx_listing` (`listing_id`),
  KEY `idx_pending_provider` (`tenant_id`,`status`,`created_at`),
  KEY `idx_pending_broker` (`tenant_id`,`status`,`broker_id`),
  KEY `requester_id` (`requester_id`),
  KEY `provider_id` (`provider_id`),
  KEY `broker_id` (`broker_id`),
  KEY `cancelled_by` (`cancelled_by`),
  KEY `risk_tag_id` (`risk_tag_id`),
  CONSTRAINT `exchange_requests_ibfk_1` FOREIGN KEY (`tenant_id`) REFERENCES `tenants` (`id`) ON DELETE CASCADE,
  CONSTRAINT `exchange_requests_ibfk_2` FOREIGN KEY (`listing_id`) REFERENCES `listings` (`id`) ON DELETE CASCADE,
  CONSTRAINT `exchange_requests_ibfk_3` FOREIGN KEY (`requester_id`) REFERENCES `users` (`id`) ON DELETE CASCADE,
  CONSTRAINT `exchange_requests_ibfk_4` FOREIGN KEY (`provider_id`) REFERENCES `users` (`id`) ON DELETE CASCADE,
  CONSTRAINT `exchange_requests_ibfk_5` FOREIGN KEY (`broker_id`) REFERENCES `users` (`id`) ON DELETE SET NULL,
  CONSTRAINT `exchange_requests_ibfk_6` FOREIGN KEY (`cancelled_by`) REFERENCES `users` (`id`) ON DELETE SET NULL,
  CONSTRAINT `exchange_requests_ibfk_7` FOREIGN KEY (`risk_tag_id`) REFERENCES `listing_risk_tags` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB AUTO_INCREMENT=14 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `fcm_device_tokens`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `fcm_device_tokens` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `user_id` int(11) NOT NULL,
  `tenant_id` int(11) NOT NULL,
  `token` varchar(255) NOT NULL,
  `platform` varchar(20) DEFAULT 'android',
  `created_at` datetime DEFAULT current_timestamp(),
  `updated_at` datetime DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  `content` text DEFAULT NULL,
  `errors` varchar(255) DEFAULT NULL,
  `pages` varchar(255) DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `unique_token` (`token`),
  KEY `idx_user_tenant` (`user_id`,`tenant_id`),
  KEY `idx_tenant` (`tenant_id`)
) ENGINE=InnoDB AUTO_INCREMENT=23 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `federated_identities`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `federated_identities` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `local_user_id` bigint(20) unsigned NOT NULL,
  `partner_id` bigint(20) unsigned NOT NULL,
  `external_user_id` varchar(255) NOT NULL,
  `external_handle` varchar(255) DEFAULT NULL,
  `attestation_signature` text DEFAULT NULL,
  `verified_at` timestamp NULL DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uniq_fed_identity_partner_ext` (`partner_id`,`external_user_id`),
  KEY `idx_fed_identity_local_user` (`local_user_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `federation_api_keys`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `federation_api_keys` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `tenant_id` int(11) NOT NULL,
  `name` varchar(100) NOT NULL COMMENT 'Human-readable key name',
  `key_hash` varchar(64) NOT NULL COMMENT 'SHA-256 hash of API key',
  `key_prefix` varchar(8) NOT NULL COMMENT 'First 8 chars for identification',
  `signing_secret` varchar(64) DEFAULT NULL COMMENT 'HMAC-SHA256 signing secret (hex encoded)',
  `signing_enabled` tinyint(1) DEFAULT 0 COMMENT 'Whether HMAC signing is required for this key',
  `platform_id` varchar(100) DEFAULT NULL COMMENT 'External platform identifier',
  `permissions` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin NOT NULL DEFAULT '[]' COMMENT 'Array of permission strings' CHECK (json_valid(`permissions`)),
  `rate_limit` int(10) unsigned DEFAULT 1000 COMMENT 'Requests per hour',
  `request_count` int(10) unsigned DEFAULT 0 COMMENT 'Current hour request count',
  `status` enum('active','suspended','revoked') DEFAULT 'active',
  `expires_at` datetime DEFAULT NULL COMMENT 'NULL = never expires',
  `last_used_at` datetime DEFAULT NULL,
  `created_by` int(11) NOT NULL COMMENT 'Admin who created the key',
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  `updated_at` datetime DEFAULT NULL ON UPDATE current_timestamp(),
  `rate_limit_hour` datetime DEFAULT NULL,
  `hourly_request_count` int(10) unsigned DEFAULT 0,
  PRIMARY KEY (`id`),
  UNIQUE KEY `idx_key_hash` (`key_hash`),
  KEY `idx_tenant` (`tenant_id`),
  KEY `idx_status` (`status`),
  KEY `idx_prefix` (`key_prefix`),
  KEY `idx_platform` (`platform_id`),
  KEY `idx_rate_limit_hour` (`rate_limit_hour`),
  CONSTRAINT `federation_api_keys_ibfk_1` FOREIGN KEY (`tenant_id`) REFERENCES `tenants` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=6 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `federation_api_logs`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `federation_api_logs` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `api_key_id` int(10) unsigned NOT NULL,
  `endpoint` varchar(255) NOT NULL,
  `method` varchar(10) NOT NULL,
  `ip_address` varchar(45) NOT NULL,
  `user_agent` varchar(255) DEFAULT NULL,
  `signature_valid` tinyint(1) DEFAULT NULL COMMENT 'NULL=not signed, 0=invalid, 1=valid',
  `auth_method` enum('api_key','hmac','jwt') DEFAULT 'api_key',
  `response_code` smallint(5) unsigned DEFAULT NULL,
  `response_time_ms` int(10) unsigned DEFAULT NULL,
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `idx_api_key` (`api_key_id`),
  KEY `idx_created` (`created_at`),
  KEY `idx_endpoint` (`endpoint`(100)),
  CONSTRAINT `federation_api_logs_ibfk_1` FOREIGN KEY (`api_key_id`) REFERENCES `federation_api_keys` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=25 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `federation_audit_log`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `federation_audit_log` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `action_type` varchar(100) NOT NULL,
  `category` varchar(50) NOT NULL,
  `level` enum('debug','info','warning','critical') NOT NULL DEFAULT 'info',
  `source_tenant_id` int(10) unsigned DEFAULT NULL,
  `target_tenant_id` int(10) unsigned DEFAULT NULL,
  `actor_user_id` int(10) unsigned DEFAULT NULL,
  `actor_name` varchar(200) DEFAULT NULL,
  `actor_email` varchar(255) DEFAULT NULL,
  `data` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`data`)),
  `ip_address` varchar(45) DEFAULT NULL,
  `user_agent` varchar(500) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `idx_action_type` (`action_type`),
  KEY `idx_category` (`category`),
  KEY `idx_level` (`level`),
  KEY `idx_source_tenant` (`source_tenant_id`),
  KEY `idx_target_tenant` (`target_tenant_id`),
  KEY `idx_actor` (`actor_user_id`),
  KEY `idx_created_at` (`created_at`),
  KEY `idx_level_created` (`level`,`created_at`)
) ENGINE=InnoDB AUTO_INCREMENT=419 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `federation_cc_entries`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `federation_cc_entries` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `tenant_id` bigint(20) unsigned NOT NULL,
  `transaction_uuid` char(36) NOT NULL,
  `federation_transaction_id` bigint(20) unsigned DEFAULT NULL COMMENT 'FK to federation_transactions.id (nullable for relay entries)',
  `payer` varchar(100) NOT NULL COMMENT 'CC account path (e.g., node-slug/username)',
  `payee` varchar(100) NOT NULL COMMENT 'CC account path',
  `quant` decimal(12,4) NOT NULL COMMENT 'Amount in CC units',
  `description` text DEFAULT NULL,
  `state` char(1) NOT NULL DEFAULT 'P' COMMENT 'CC state: P/V/C/E/X',
  `workflow` varchar(50) DEFAULT NULL COMMENT 'CC workflow code',
  `author` varchar(100) DEFAULT NULL COMMENT 'CC account path of entry creator',
  `metadata` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL COMMENT 'CC metadata (arbitrary key-value)' CHECK (json_valid(`metadata`)),
  `written_at` timestamp NULL DEFAULT NULL COMMENT 'When entry was permanently written',
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `federation_cc_entries_tenant_id_state_index` (`tenant_id`,`state`),
  KEY `federation_cc_entries_payer_tenant_id_index` (`payer`,`tenant_id`),
  KEY `federation_cc_entries_payee_tenant_id_index` (`payee`,`tenant_id`),
  KEY `federation_cc_entries_tenant_id_index` (`tenant_id`),
  KEY `federation_cc_entries_transaction_uuid_index` (`transaction_uuid`),
  KEY `federation_cc_entries_federation_transaction_id_index` (`federation_transaction_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `federation_cc_node_config`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `federation_cc_node_config` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `tenant_id` bigint(20) unsigned NOT NULL,
  `node_slug` varchar(50) NOT NULL COMMENT 'CC node identifier (3-15 chars, lowercase)',
  `display_name` varchar(100) DEFAULT NULL,
  `currency_format` varchar(100) NOT NULL DEFAULT '<quantity> hours' COMMENT 'CC currency display format',
  `exchange_rate` decimal(10,6) NOT NULL DEFAULT 1.000000 COMMENT 'Exchange rate relative to parent node (1.0 = same unit)',
  `validated_window` int(10) unsigned NOT NULL DEFAULT 300 COMMENT 'Seconds a validated transaction remains valid before timeout',
  `parent_node_url` varchar(500) DEFAULT NULL COMMENT 'URL of parent CC node (null = root/standalone)',
  `parent_node_slug` varchar(50) DEFAULT NULL,
  `last_hash` text DEFAULT NULL COMMENT 'Last hashchain hash for this node',
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `federation_cc_node_config_tenant_id_unique` (`tenant_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `federation_connections`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `federation_connections` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `requester_user_id` int(10) unsigned NOT NULL,
  `requester_tenant_id` int(10) unsigned NOT NULL,
  `receiver_user_id` int(10) unsigned NOT NULL,
  `receiver_tenant_id` int(10) unsigned NOT NULL,
  `status` enum('pending','accepted','rejected') NOT NULL DEFAULT 'pending',
  `message` varchar(500) DEFAULT NULL COMMENT 'Optional message with connection request',
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  `updated_at` datetime DEFAULT NULL ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `uk_connection` (`requester_user_id`,`requester_tenant_id`,`receiver_user_id`,`receiver_tenant_id`),
  KEY `idx_requester` (`requester_user_id`,`requester_tenant_id`),
  KEY `idx_receiver` (`receiver_user_id`,`receiver_tenant_id`),
  KEY `idx_status` (`status`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `federation_credit_agreements`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `federation_credit_agreements` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `from_tenant_id` int(11) NOT NULL,
  `to_tenant_id` int(11) NOT NULL,
  `exchange_rate` decimal(10,4) NOT NULL DEFAULT 1.0000,
  `status` enum('pending','active','suspended','terminated') NOT NULL DEFAULT 'pending',
  `max_monthly_credits` decimal(10,2) DEFAULT NULL,
  `approved_by_from` int(11) DEFAULT NULL,
  `approved_by_to` int(11) DEFAULT NULL,
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  `updated_at` datetime DEFAULT NULL ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `idx_credit_from` (`from_tenant_id`),
  KEY `idx_credit_to` (`to_tenant_id`),
  KEY `idx_credit_status` (`status`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `federation_credit_balances`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `federation_credit_balances` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `tenant_id_a` int(11) NOT NULL,
  `tenant_id_b` int(11) NOT NULL,
  `net_balance` decimal(10,2) NOT NULL DEFAULT 0.00,
  `last_settlement_at` datetime DEFAULT NULL,
  `updated_at` datetime NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `uk_balance_pair` (`tenant_id_a`,`tenant_id_b`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `federation_credit_transfers`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `federation_credit_transfers` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `agreement_id` int(11) NOT NULL,
  `from_tenant_id` int(11) NOT NULL,
  `to_tenant_id` int(11) NOT NULL,
  `user_id` int(11) NOT NULL,
  `amount` decimal(10,2) NOT NULL,
  `converted_amount` decimal(10,2) NOT NULL,
  `exchange_rate` decimal(10,4) NOT NULL,
  `description` varchar(500) DEFAULT NULL,
  `status` enum('pending','completed','reversed') NOT NULL DEFAULT 'pending',
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  `completed_at` datetime DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `idx_transfer_agreement` (`agreement_id`),
  KEY `idx_transfer_user` (`user_id`),
  KEY `idx_transfer_from` (`from_tenant_id`),
  KEY `idx_transfer_to` (`to_tenant_id`),
  KEY `idx_transfer_status` (`status`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `federation_directory_profiles`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `federation_directory_profiles` (
  `tenant_id` int(10) unsigned NOT NULL,
  `display_name` varchar(200) DEFAULT NULL,
  `tagline` varchar(300) DEFAULT NULL,
  `description` text DEFAULT NULL,
  `logo_url` varchar(500) DEFAULT NULL,
  `cover_image_url` varchar(500) DEFAULT NULL,
  `website_url` varchar(500) DEFAULT NULL,
  `country_code` char(2) DEFAULT NULL,
  `region` varchar(100) DEFAULT NULL,
  `city` varchar(100) DEFAULT NULL,
  `latitude` decimal(10,8) DEFAULT NULL,
  `longitude` decimal(11,8) DEFAULT NULL,
  `member_count` int(10) unsigned NOT NULL DEFAULT 0,
  `active_listings_count` int(10) unsigned NOT NULL DEFAULT 0,
  `total_hours_exchanged` decimal(12,2) NOT NULL DEFAULT 0.00,
  `show_member_count` tinyint(1) NOT NULL DEFAULT 1,
  `show_activity_stats` tinyint(1) NOT NULL DEFAULT 0,
  `show_location` tinyint(1) NOT NULL DEFAULT 1,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NULL DEFAULT NULL ON UPDATE current_timestamp(),
  PRIMARY KEY (`tenant_id`),
  KEY `idx_country` (`country_code`),
  KEY `idx_location` (`latitude`,`longitude`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `federation_events`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `federation_events` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `tenant_id` bigint(20) unsigned NOT NULL,
  `external_partner_id` bigint(20) unsigned NOT NULL,
  `external_id` varchar(128) NOT NULL,
  `title` varchar(500) NOT NULL,
  `description` text DEFAULT NULL,
  `starts_at` datetime DEFAULT NULL,
  `ends_at` datetime DEFAULT NULL,
  `location` varchar(500) DEFAULT NULL,
  `metadata` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`metadata`)),
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uk_fed_events_partner_ext` (`external_partner_id`,`external_id`),
  KEY `federation_events_tenant_id_index` (`tenant_id`),
  KEY `federation_events_external_partner_id_index` (`external_partner_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `federation_exports`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `federation_exports` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `tenant_id` int(11) NOT NULL,
  `export_type` enum('users','partnerships','transactions','audit','all') NOT NULL,
  `filename` varchar(255) NOT NULL,
  `file_size` int(10) unsigned DEFAULT NULL COMMENT 'Size in bytes',
  `record_count` int(10) unsigned DEFAULT 0 COMMENT 'Number of records exported',
  `status` enum('pending','processing','completed','failed') DEFAULT 'pending',
  `error_message` text DEFAULT NULL,
  `filters` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL COMMENT 'Applied filters (date range, etc.)' CHECK (json_valid(`filters`)),
  `exported_by` int(11) NOT NULL COMMENT 'Admin who initiated the export',
  `downloaded_at` datetime DEFAULT NULL COMMENT 'When the file was downloaded',
  `expires_at` datetime DEFAULT NULL COMMENT 'When the file should be deleted',
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  `completed_at` datetime DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `idx_tenant` (`tenant_id`),
  KEY `idx_type` (`export_type`),
  KEY `idx_status` (`status`),
  KEY `idx_created` (`created_at`),
  KEY `idx_exported_by` (`exported_by`),
  KEY `idx_expires` (`expires_at`),
  CONSTRAINT `federation_exports_ibfk_1` FOREIGN KEY (`tenant_id`) REFERENCES `tenants` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=5 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `federation_external_partner_logs`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `federation_external_partner_logs` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `partner_id` int(10) unsigned NOT NULL,
  `endpoint` varchar(500) NOT NULL,
  `method` varchar(10) NOT NULL,
  `request_body` text DEFAULT NULL,
  `response_code` int(11) DEFAULT NULL,
  `response_body` text DEFAULT NULL,
  `response_time_ms` int(10) unsigned DEFAULT NULL,
  `success` tinyint(1) DEFAULT 0,
  `error_message` text DEFAULT NULL,
  `created_at` datetime DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `idx_partner` (`partner_id`),
  KEY `idx_created` (`created_at`),
  KEY `idx_success` (`success`)
) ENGINE=InnoDB AUTO_INCREMENT=315 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `federation_external_partners`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `federation_external_partners` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `tenant_id` int(10) unsigned NOT NULL,
  `name` varchar(255) NOT NULL,
  `description` text DEFAULT NULL,
  `base_url` varchar(500) NOT NULL,
  `api_path` varchar(255) DEFAULT '/api/v1/federation',
  `api_key` varchar(500) DEFAULT NULL,
  `auth_method` enum('api_key','hmac','oauth2') DEFAULT 'api_key',
  `protocol_type` varchar(30) NOT NULL DEFAULT 'nexus' COMMENT 'Federation protocol: nexus, timeoverflow, komunitin, credit_commons',
  `signing_secret` varchar(500) DEFAULT NULL,
  `oauth_client_id` varchar(255) DEFAULT NULL,
  `oauth_client_secret` varchar(500) DEFAULT NULL,
  `oauth_token_url` varchar(500) DEFAULT NULL,
  `status` enum('pending','active','suspended','failed') DEFAULT 'pending',
  `verified_at` datetime DEFAULT NULL,
  `last_sync_at` datetime DEFAULT NULL,
  `last_message_at` datetime DEFAULT NULL,
  `last_error` text DEFAULT NULL,
  `error_count` int(10) unsigned DEFAULT 0,
  `partner_name` varchar(255) DEFAULT NULL,
  `partner_version` varchar(50) DEFAULT NULL,
  `partner_member_count` int(10) unsigned DEFAULT NULL,
  `partner_metadata` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`partner_metadata`)),
  `allow_member_search` tinyint(1) DEFAULT 1,
  `allow_listing_search` tinyint(1) DEFAULT 1,
  `allow_messaging` tinyint(1) DEFAULT 1,
  `allow_transactions` tinyint(1) DEFAULT 1,
  `allow_events` tinyint(1) DEFAULT 0,
  `allow_groups` tinyint(1) DEFAULT 0,
  `allow_connections` tinyint(1) NOT NULL DEFAULT 0,
  `allow_volunteering` tinyint(1) NOT NULL DEFAULT 0,
  `allow_member_sync` tinyint(1) NOT NULL DEFAULT 0,
  `created_by` int(10) unsigned DEFAULT NULL,
  `created_at` datetime DEFAULT current_timestamp(),
  `updated_at` datetime DEFAULT NULL ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `uk_tenant_url` (`tenant_id`,`base_url`(191)),
  KEY `idx_tenant` (`tenant_id`),
  KEY `idx_status` (`status`),
  KEY `idx_base_url` (`base_url`(191)),
  KEY `idx_last_message` (`last_message_at`)
) ENGINE=InnoDB AUTO_INCREMENT=3 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `federation_groups`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `federation_groups` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `tenant_id` bigint(20) unsigned NOT NULL,
  `external_partner_id` bigint(20) unsigned NOT NULL,
  `external_id` varchar(128) NOT NULL,
  `name` varchar(500) NOT NULL,
  `description` text DEFAULT NULL,
  `privacy` varchar(32) NOT NULL DEFAULT 'public',
  `member_count` int(10) unsigned NOT NULL DEFAULT 0,
  `metadata` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`metadata`)),
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uk_fed_groups_partner_ext` (`external_partner_id`,`external_id`),
  KEY `federation_groups_tenant_id_index` (`tenant_id`),
  KEY `federation_groups_external_partner_id_index` (`external_partner_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `federation_inbound_connections`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `federation_inbound_connections` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `tenant_id` bigint(20) unsigned NOT NULL,
  `external_partner_id` bigint(20) unsigned NOT NULL,
  `local_user_id` bigint(20) unsigned NOT NULL,
  `external_user_id` varchar(128) NOT NULL,
  `status` varchar(32) NOT NULL DEFAULT 'pending',
  `message` varchar(1000) DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uk_fed_inbound_conn` (`external_partner_id`,`local_user_id`,`external_user_id`),
  KEY `idx_fed_inbound_conn_local_user` (`local_user_id`),
  KEY `federation_inbound_connections_tenant_id_index` (`tenant_id`),
  KEY `federation_inbound_connections_external_partner_id_index` (`external_partner_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `federation_listings`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `federation_listings` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `tenant_id` bigint(20) unsigned NOT NULL,
  `external_partner_id` bigint(20) unsigned NOT NULL,
  `external_id` varchar(128) NOT NULL,
  `title` varchar(500) NOT NULL,
  `description` text DEFAULT NULL,
  `type` varchar(32) DEFAULT NULL,
  `category` varchar(128) DEFAULT NULL,
  `external_user_id` varchar(128) DEFAULT NULL,
  `external_user_name` varchar(255) DEFAULT NULL,
  `metadata` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`metadata`)),
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uk_fed_listings_partner_ext` (`external_partner_id`,`external_id`),
  KEY `federation_listings_tenant_id_index` (`tenant_id`),
  KEY `federation_listings_external_partner_id_index` (`external_partner_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `federation_members`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `federation_members` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `tenant_id` bigint(20) unsigned NOT NULL,
  `external_partner_id` bigint(20) unsigned NOT NULL,
  `external_id` varchar(128) NOT NULL,
  `username` varchar(255) DEFAULT NULL,
  `display_name` varchar(255) DEFAULT NULL,
  `bio` text DEFAULT NULL,
  `location` varchar(255) DEFAULT NULL,
  `avatar_url` varchar(1000) DEFAULT NULL,
  `metadata` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`metadata`)),
  `profile_updated_at` timestamp NULL DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uk_fed_members_partner_ext` (`external_partner_id`,`external_id`),
  KEY `federation_members_tenant_id_index` (`tenant_id`),
  KEY `federation_members_external_partner_id_index` (`external_partner_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `federation_messages`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `federation_messages` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `sender_tenant_id` int(11) NOT NULL,
  `sender_user_id` int(11) NOT NULL,
  `receiver_tenant_id` int(11) NOT NULL,
  `receiver_user_id` int(11) NOT NULL,
  `subject` varchar(255) DEFAULT '',
  `body` text NOT NULL,
  `direction` enum('outbound','inbound') NOT NULL DEFAULT 'outbound',
  `status` enum('pending','delivered','unread','read','failed') NOT NULL DEFAULT 'pending',
  `reference_message_id` int(11) DEFAULT NULL,
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  `read_at` datetime DEFAULT NULL,
  `external_partner_id` int(11) DEFAULT NULL,
  `external_receiver_name` varchar(255) DEFAULT NULL,
  `external_message_id` varchar(255) DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `idx_sender` (`sender_tenant_id`,`sender_user_id`),
  KEY `idx_receiver` (`receiver_tenant_id`,`receiver_user_id`),
  KEY `idx_direction` (`direction`),
  KEY `idx_status` (`status`),
  KEY `idx_created` (`created_at`),
  KEY `idx_thread` (`sender_tenant_id`,`sender_user_id`,`receiver_tenant_id`,`receiver_user_id`),
  KEY `idx_ref_message` (`reference_message_id`),
  KEY `idx_external_partner` (`external_partner_id`)
) ENGINE=InnoDB AUTO_INCREMENT=10 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Cross-tenant messages between federated timebank members';
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `federation_neighborhood_members`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `federation_neighborhood_members` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `neighborhood_id` int(11) NOT NULL,
  `tenant_id` int(11) NOT NULL,
  `joined_at` datetime NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `uk_neighborhood_tenant` (`neighborhood_id`,`tenant_id`),
  KEY `idx_neighborhood_member_tenant` (`tenant_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `federation_neighborhoods`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `federation_neighborhoods` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `name` varchar(255) NOT NULL,
  `description` text DEFAULT NULL,
  `region` varchar(255) DEFAULT NULL,
  `created_by` int(11) DEFAULT NULL,
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  `updated_at` datetime DEFAULT NULL ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `idx_neighborhood_region` (`region`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `federation_notifications`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `federation_notifications` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `tenant_id` int(10) unsigned NOT NULL,
  `user_id` int(10) unsigned DEFAULT NULL,
  `type` varchar(50) NOT NULL,
  `title` varchar(200) NOT NULL,
  `message` text DEFAULT NULL,
  `data` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`data`)),
  `related_tenant_id` int(10) unsigned DEFAULT NULL,
  `related_partnership_id` int(10) unsigned DEFAULT NULL,
  `is_read` tinyint(1) NOT NULL DEFAULT 0,
  `read_at` timestamp NULL DEFAULT NULL,
  `read_by` int(10) unsigned DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `idx_tenant` (`tenant_id`),
  KEY `idx_user` (`user_id`),
  KEY `idx_unread` (`tenant_id`,`is_read`),
  KEY `idx_type` (`type`),
  KEY `idx_created` (`created_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `federation_partnerships`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `federation_partnerships` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `tenant_id` int(10) unsigned NOT NULL,
  `partner_tenant_id` int(10) unsigned NOT NULL,
  `status` enum('pending','active','suspended','terminated','rejected') NOT NULL DEFAULT 'pending',
  `federation_level` tinyint(3) unsigned NOT NULL DEFAULT 1,
  `profiles_enabled` tinyint(1) NOT NULL DEFAULT 0,
  `messaging_enabled` tinyint(1) NOT NULL DEFAULT 0,
  `transactions_enabled` tinyint(1) NOT NULL DEFAULT 0,
  `listings_enabled` tinyint(1) NOT NULL DEFAULT 0,
  `events_enabled` tinyint(1) NOT NULL DEFAULT 0,
  `groups_enabled` tinyint(1) NOT NULL DEFAULT 0,
  `requested_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `requested_by` int(10) unsigned DEFAULT NULL,
  `approved_at` timestamp NULL DEFAULT NULL,
  `approved_by` int(10) unsigned DEFAULT NULL,
  `terminated_at` timestamp NULL DEFAULT NULL,
  `terminated_by` int(10) unsigned DEFAULT NULL,
  `termination_reason` varchar(500) DEFAULT NULL,
  `notes` text DEFAULT NULL,
  `counter_proposed_at` timestamp NULL DEFAULT NULL,
  `counter_proposed_by` int(10) unsigned DEFAULT NULL,
  `counter_proposal_message` text DEFAULT NULL,
  `counter_proposed_level` int(10) unsigned DEFAULT NULL,
  `counter_proposed_permissions` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`counter_proposed_permissions`)),
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NULL DEFAULT NULL ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `unique_partnership` (`tenant_id`,`partner_tenant_id`),
  KEY `idx_status` (`status`),
  KEY `idx_tenant` (`tenant_id`),
  KEY `idx_partner` (`partner_tenant_id`),
  KEY `idx_level` (`federation_level`),
  KEY `idx_counter_proposed` (`counter_proposed_at`)
) ENGINE=InnoDB AUTO_INCREMENT=12 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `federation_rate_limits`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `federation_rate_limits` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `tenant_id` int(10) unsigned DEFAULT NULL,
  `user_id` int(10) unsigned DEFAULT NULL,
  `ip_address` varchar(45) DEFAULT NULL,
  `operation` varchar(50) NOT NULL,
  `window_start` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  `request_count` int(10) unsigned NOT NULL DEFAULT 1,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NULL DEFAULT NULL ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `idx_window` (`window_start`),
  KEY `idx_operation` (`operation`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `federation_realtime_queue`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `federation_realtime_queue` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `tenant_id` int(11) NOT NULL,
  `user_id` int(11) DEFAULT NULL COMMENT 'NULL for tenant-wide events',
  `event_type` varchar(50) NOT NULL,
  `event_data` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin NOT NULL CHECK (json_valid(`event_data`)),
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  `delivered_at` datetime DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `idx_tenant_user` (`tenant_id`,`user_id`),
  KEY `idx_pending_events` (`tenant_id`,`user_id`,`delivered_at`),
  KEY `idx_cleanup` (`delivered_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `federation_reputation`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `federation_reputation` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `user_id` int(10) unsigned NOT NULL,
  `home_tenant_id` int(10) unsigned NOT NULL,
  `trust_score` decimal(5,2) NOT NULL DEFAULT 0.00,
  `reliability_score` decimal(5,2) NOT NULL DEFAULT 0.00,
  `responsiveness_score` decimal(5,2) NOT NULL DEFAULT 0.00,
  `review_score` decimal(5,2) NOT NULL DEFAULT 0.00,
  `total_transactions` int(10) unsigned NOT NULL DEFAULT 0,
  `successful_transactions` int(10) unsigned NOT NULL DEFAULT 0,
  `reviews_received` int(10) unsigned NOT NULL DEFAULT 0,
  `reviews_given` int(10) unsigned NOT NULL DEFAULT 0,
  `hours_given` decimal(10,2) NOT NULL DEFAULT 0.00,
  `hours_received` decimal(10,2) NOT NULL DEFAULT 0.00,
  `is_verified` tinyint(1) NOT NULL DEFAULT 0,
  `verified_at` timestamp NULL DEFAULT NULL,
  `verified_by` int(10) unsigned DEFAULT NULL,
  `share_reputation` tinyint(1) NOT NULL DEFAULT 0,
  `last_calculated_at` timestamp NULL DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NULL DEFAULT NULL ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `unique_user_tenant` (`user_id`,`home_tenant_id`),
  KEY `idx_trust_score` (`trust_score`),
  KEY `idx_verified` (`is_verified`),
  KEY `idx_share` (`share_reputation`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `federation_system_control`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `federation_system_control` (
  `id` int(10) unsigned NOT NULL DEFAULT 1,
  `federation_enabled` tinyint(1) NOT NULL DEFAULT 0,
  `whitelist_mode_enabled` tinyint(1) NOT NULL DEFAULT 1,
  `max_federation_level` tinyint(3) unsigned NOT NULL DEFAULT 0,
  `cross_tenant_profiles_enabled` tinyint(1) NOT NULL DEFAULT 0,
  `cross_tenant_messaging_enabled` tinyint(1) NOT NULL DEFAULT 0,
  `cross_tenant_transactions_enabled` tinyint(1) NOT NULL DEFAULT 0,
  `cross_tenant_listings_enabled` tinyint(1) NOT NULL DEFAULT 0,
  `cross_tenant_events_enabled` tinyint(1) NOT NULL DEFAULT 0,
  `cross_tenant_groups_enabled` tinyint(1) NOT NULL DEFAULT 0,
  `emergency_lockdown_active` tinyint(1) NOT NULL DEFAULT 0,
  `emergency_lockdown_reason` text DEFAULT NULL,
  `emergency_lockdown_at` timestamp NULL DEFAULT NULL,
  `emergency_lockdown_by` int(10) unsigned DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NULL DEFAULT NULL ON UPDATE current_timestamp(),
  `updated_by` int(10) unsigned DEFAULT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `federation_tenant_features`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `federation_tenant_features` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `tenant_id` int(10) unsigned NOT NULL,
  `feature_key` varchar(100) NOT NULL,
  `is_enabled` tinyint(1) NOT NULL DEFAULT 0,
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  `updated_by` int(10) unsigned DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `unique_tenant_feature` (`tenant_id`,`feature_key`),
  KEY `idx_tenant` (`tenant_id`),
  KEY `idx_feature` (`feature_key`)
) ENGINE=InnoDB AUTO_INCREMENT=151 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `federation_tenant_settings`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `federation_tenant_settings` (
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
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `federation_tenant_topics`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `federation_tenant_topics` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `tenant_id` bigint(20) unsigned NOT NULL,
  `topic_id` bigint(20) unsigned NOT NULL,
  `is_primary` tinyint(1) NOT NULL DEFAULT 0,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `federation_tenant_topics_tenant_id_topic_id_unique` (`tenant_id`,`topic_id`),
  KEY `federation_tenant_topics_topic_id_index` (`topic_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `federation_tenant_whitelist`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `federation_tenant_whitelist` (
  `tenant_id` int(10) unsigned NOT NULL,
  `approved_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `approved_by` int(10) unsigned NOT NULL,
  `notes` varchar(500) DEFAULT NULL,
  PRIMARY KEY (`tenant_id`),
  KEY `idx_approved_at` (`approved_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `federation_topics`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `federation_topics` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `name` varchar(100) NOT NULL,
  `slug` varchar(100) NOT NULL,
  `icon` varchar(50) DEFAULT NULL,
  `category` varchar(50) DEFAULT NULL,
  `sort_order` smallint(5) unsigned NOT NULL DEFAULT 0,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `federation_topics_slug_unique` (`slug`)
) ENGINE=InnoDB AUTO_INCREMENT=25 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `federation_transactions`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `federation_transactions` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `sender_tenant_id` int(11) NOT NULL,
  `sender_user_id` int(11) NOT NULL,
  `receiver_tenant_id` int(11) NOT NULL,
  `receiver_user_id` int(11) NOT NULL,
  `amount` decimal(10,2) NOT NULL,
  `description` text DEFAULT NULL,
  `status` enum('pending','completed','cancelled','disputed') NOT NULL DEFAULT 'pending',
  `listing_id` int(11) DEFAULT NULL,
  `listing_tenant_id` int(11) DEFAULT NULL,
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  `completed_at` datetime DEFAULT NULL,
  `cancelled_at` datetime DEFAULT NULL,
  `cancelled_by` int(11) DEFAULT NULL,
  `cancellation_reason` varchar(500) DEFAULT NULL,
  `sender_reviewed` tinyint(1) NOT NULL DEFAULT 0,
  `receiver_reviewed` tinyint(1) NOT NULL DEFAULT 0,
  `external_partner_id` int(11) DEFAULT NULL,
  `external_receiver_name` varchar(255) DEFAULT NULL,
  `external_transaction_id` varchar(100) DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `idx_sender` (`sender_tenant_id`,`sender_user_id`),
  KEY `idx_receiver` (`receiver_tenant_id`,`receiver_user_id`),
  KEY `idx_status` (`status`),
  KEY `idx_created` (`created_at`),
  KEY `idx_listing` (`listing_tenant_id`,`listing_id`),
  KEY `idx_federation_transactions_external` (`external_partner_id`)
) ENGINE=InnoDB AUTO_INCREMENT=3 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `federation_user_settings`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `federation_user_settings` (
  `user_id` int(10) unsigned NOT NULL,
  `federation_optin` tinyint(1) NOT NULL DEFAULT 0,
  `profile_visible_federated` tinyint(1) NOT NULL DEFAULT 0,
  `messaging_enabled_federated` tinyint(1) NOT NULL DEFAULT 0,
  `transactions_enabled_federated` tinyint(1) NOT NULL DEFAULT 0,
  `appear_in_federated_search` tinyint(1) NOT NULL DEFAULT 0,
  `show_skills_federated` tinyint(1) NOT NULL DEFAULT 0,
  `show_location_federated` tinyint(1) NOT NULL DEFAULT 0,
  `service_reach` enum('local_only','remote_ok','travel_ok') NOT NULL DEFAULT 'local_only',
  `travel_radius_km` int(10) unsigned DEFAULT NULL,
  `opted_in_at` timestamp NULL DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NULL DEFAULT NULL ON UPDATE current_timestamp(),
  `email_notifications` tinyint(1) NOT NULL DEFAULT 1,
  `show_reviews_federated` tinyint(1) NOT NULL DEFAULT 1,
  PRIMARY KEY (`user_id`),
  KEY `idx_optin` (`federation_optin`),
  KEY `idx_searchable` (`appear_in_federated_search`),
  KEY `idx_service_reach` (`service_reach`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `federation_volunteering`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `federation_volunteering` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `tenant_id` bigint(20) unsigned NOT NULL,
  `external_partner_id` bigint(20) unsigned NOT NULL,
  `external_id` varchar(128) NOT NULL,
  `title` varchar(500) NOT NULL,
  `description` text DEFAULT NULL,
  `hours_requested` decimal(8,2) DEFAULT NULL,
  `location` varchar(500) DEFAULT NULL,
  `starts_at` datetime DEFAULT NULL,
  `metadata` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`metadata`)),
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uk_fed_vol_partner_ext` (`external_partner_id`,`external_id`),
  KEY `federation_volunteering_tenant_id_index` (`tenant_id`),
  KEY `federation_volunteering_external_partner_id_index` (`external_partner_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `federation_webhook_logs`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `federation_webhook_logs` (
  `id` bigint(20) NOT NULL AUTO_INCREMENT,
  `webhook_id` int(11) NOT NULL,
  `tenant_id` int(11) NOT NULL,
  `event_type` varchar(100) NOT NULL,
  `payload` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin NOT NULL CHECK (json_valid(`payload`)),
  `response_code` int(11) DEFAULT NULL,
  `response_body` text DEFAULT NULL,
  `response_time_ms` int(11) DEFAULT NULL,
  `success` tinyint(1) DEFAULT 0,
  `error_message` varchar(500) DEFAULT NULL,
  `attempt_number` int(11) DEFAULT 1,
  `created_at` timestamp NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `idx_webhook` (`webhook_id`),
  KEY `idx_tenant` (`tenant_id`),
  KEY `idx_created` (`created_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `federation_webhooks`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `federation_webhooks` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `tenant_id` int(11) NOT NULL,
  `url` varchar(500) NOT NULL,
  `secret` varchar(255) NOT NULL,
  `events` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin NOT NULL COMMENT 'Array of event types to subscribe to' CHECK (json_valid(`events`)),
  `status` enum('active','inactive','failing') DEFAULT 'active',
  `description` varchar(255) DEFAULT NULL,
  `consecutive_failures` int(11) DEFAULT 0,
  `last_triggered_at` timestamp NULL DEFAULT NULL,
  `last_success_at` timestamp NULL DEFAULT NULL,
  `last_failure_at` timestamp NULL DEFAULT NULL,
  `last_failure_reason` varchar(500) DEFAULT NULL,
  `created_by` int(11) NOT NULL,
  `created_at` timestamp NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `idx_tenant` (`tenant_id`),
  KEY `idx_status` (`status`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `feed_activities`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `feed_activities` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `tenant_id` int(10) unsigned NOT NULL,
  `user_id` int(10) unsigned NOT NULL,
  `activity_type` varchar(50) NOT NULL,
  `entity_type` varchar(50) DEFAULT NULL,
  `entity_id` int(10) unsigned DEFAULT NULL,
  `metadata` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`metadata`)),
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `feed_activities_tenant_id_user_id_index` (`tenant_id`,`user_id`),
  KEY `feed_activities_tenant_id_activity_type_index` (`tenant_id`,`activity_type`),
  KEY `feed_activities_tenant_id_created_at_index` (`tenant_id`,`created_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `feed_activity`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `feed_activity` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `tenant_id` int(10) unsigned NOT NULL,
  `user_id` int(10) unsigned NOT NULL,
  `source_type` varchar(20) NOT NULL COMMENT 'post, listing, event, poll, goal, review, job, challenge, volunteer',
  `source_id` int(10) unsigned NOT NULL,
  `group_id` int(10) unsigned DEFAULT NULL,
  `title` varchar(500) DEFAULT NULL,
  `content` text DEFAULT NULL,
  `image_url` varchar(500) DEFAULT NULL,
  `metadata` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL COMMENT 'Type-specific fields: rating, receiver, job_type, location, etc.' CHECK (json_valid(`metadata`)),
  `is_visible` tinyint(1) NOT NULL DEFAULT 1,
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  `is_hidden` tinyint(1) NOT NULL DEFAULT 0,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_tenant_source` (`tenant_id`,`source_type`,`source_id`),
  KEY `idx_main_feed` (`tenant_id`,`is_visible`,`created_at` DESC,`id` DESC),
  KEY `idx_user_feed` (`tenant_id`,`user_id`,`is_visible`,`created_at` DESC,`id` DESC),
  KEY `idx_group_feed` (`tenant_id`,`group_id`,`is_visible`,`created_at` DESC,`id` DESC),
  KEY `idx_type_feed` (`tenant_id`,`source_type`,`is_visible`,`created_at` DESC,`id` DESC),
  KEY `idx_source_lookup` (`source_type`,`source_id`),
  FULLTEXT KEY `ft_feed_search` (`title`,`content`)
) ENGINE=InnoDB AUTO_INCREMENT=572 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `feed_clicks`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `feed_clicks` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `post_id` int(10) unsigned NOT NULL,
  `user_id` int(10) unsigned NOT NULL,
  `tenant_id` int(10) unsigned NOT NULL,
  `click_count` int(10) unsigned NOT NULL DEFAULT 1,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_click` (`post_id`,`user_id`,`tenant_id`),
  KEY `idx_post_tenant` (`post_id`,`tenant_id`),
  KEY `idx_user_tenant` (`user_id`,`tenant_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `feed_comments`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `feed_comments` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `tenant_id` int(10) unsigned NOT NULL,
  `post_id` int(10) unsigned NOT NULL,
  `user_id` int(10) unsigned NOT NULL,
  `content` text NOT NULL,
  `parent_id` int(10) unsigned DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NULL DEFAULT NULL ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `feed_comments_tenant_id_post_id_index` (`tenant_id`,`post_id`),
  KEY `feed_comments_parent_id_index` (`parent_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `feed_hidden`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `feed_hidden` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `user_id` int(11) NOT NULL,
  `tenant_id` int(11) NOT NULL,
  `target_type` varchar(50) NOT NULL DEFAULT 'post',
  `target_id` int(11) NOT NULL,
  `created_at` timestamp NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `unique_hidden` (`user_id`,`tenant_id`,`target_type`,`target_id`),
  KEY `idx_user_tenant` (`user_id`,`tenant_id`)
) ENGINE=InnoDB AUTO_INCREMENT=10 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `feed_impressions`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `feed_impressions` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `post_id` int(10) unsigned NOT NULL,
  `user_id` int(10) unsigned NOT NULL,
  `tenant_id` int(10) unsigned NOT NULL,
  `view_count` int(10) unsigned NOT NULL DEFAULT 1,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_impression` (`post_id`,`user_id`,`tenant_id`),
  KEY `idx_post_tenant` (`post_id`,`tenant_id`),
  KEY `idx_user_tenant` (`user_id`,`tenant_id`)
) ENGINE=InnoDB AUTO_INCREMENT=450 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `feed_likes`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `feed_likes` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `tenant_id` int(10) unsigned NOT NULL,
  `post_id` int(10) unsigned NOT NULL,
  `user_id` int(10) unsigned NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `feed_likes_tenant_id_post_id_user_id_unique` (`tenant_id`,`post_id`,`user_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `feed_muted_users`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `feed_muted_users` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `user_id` int(11) NOT NULL,
  `tenant_id` int(11) NOT NULL,
  `muted_user_id` int(11) NOT NULL,
  `created_at` timestamp NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `unique_muted` (`user_id`,`tenant_id`,`muted_user_id`),
  KEY `idx_user_tenant` (`user_id`,`tenant_id`)
) ENGINE=InnoDB AUTO_INCREMENT=5 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `feed_posts`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `feed_posts` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `tenant_id` int(11) NOT NULL,
  `user_id` int(11) NOT NULL,
  `group_id` int(11) DEFAULT NULL,
  `content` text NOT NULL,
  `emoji` varchar(10) DEFAULT NULL,
  `image_url` varchar(255) DEFAULT NULL,
  `video_url` varchar(500) DEFAULT NULL,
  `likes_count` int(11) DEFAULT 0,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `visibility` varchar(50) DEFAULT 'public',
  `scheduled_at` timestamp NULL DEFAULT NULL,
  `publish_status` enum('published','scheduled','draft') NOT NULL DEFAULT 'published',
  `parent_id` int(11) DEFAULT 0,
  `parent_type` varchar(50) DEFAULT 'post',
  `award` varchar(255) DEFAULT NULL,
  `event` varchar(255) DEFAULT NULL,
  `lov` varchar(255) DEFAULT NULL,
  `type` varchar(50) DEFAULT 'post',
  `share_count` int(11) NOT NULL DEFAULT 0,
  `original_post_id` int(11) DEFAULT NULL COMMENT 'For reposts: ID of original post',
  `quoted_post_id` int(10) unsigned DEFAULT NULL COMMENT 'ID of the post being quoted (quote repost)',
  `is_repost` tinyint(1) NOT NULL DEFAULT 0,
  `is_hidden` tinyint(1) NOT NULL DEFAULT 0,
  `updated_at` timestamp NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  `views_count` int(10) unsigned NOT NULL DEFAULT 0,
  PRIMARY KEY (`id`),
  KEY `idx_tenant_user` (`tenant_id`,`user_id`),
  KEY `idx_created` (`created_at`),
  KEY `idx_group` (`group_id`),
  KEY `idx_feed_posts_tenant_created` (`tenant_id`,`created_at` DESC,`id` DESC),
  KEY `idx_user_created` (`user_id`,`created_at`),
  KEY `idx_is_hidden` (`tenant_id`,`is_hidden`),
  KEY `idx_feed_posts_publish_schedule` (`publish_status`,`scheduled_at`),
  KEY `idx_feed_posts_quoted_post_id` (`quoted_post_id`),
  CONSTRAINT `feed_posts_group_id_foreign` FOREIGN KEY (`group_id`) REFERENCES `groups` (`id`) ON DELETE SET NULL,
  CONSTRAINT `feed_posts_tenant_id_foreign` FOREIGN KEY (`tenant_id`) REFERENCES `tenants` (`id`) ON DELETE CASCADE,
  CONSTRAINT `feed_posts_user_id_foreign` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=36 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `fraud_alerts`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `fraud_alerts` (
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
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `friend_challenges`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `friend_challenges` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `tenant_id` int(11) NOT NULL,
  `challenger_id` int(11) NOT NULL,
  `challenged_id` int(11) NOT NULL,
  `challenge_type` varchar(50) NOT NULL,
  `target_value` int(11) NOT NULL,
  `xp_stake` int(11) DEFAULT 50,
  `challenger_progress` int(11) DEFAULT 0,
  `challenged_progress` int(11) DEFAULT 0,
  `start_date` date NOT NULL,
  `end_date` date NOT NULL,
  `status` enum('pending','active','completed','declined','expired') DEFAULT 'pending',
  `winner_id` int(11) DEFAULT NULL,
  `accepted_at` timestamp NULL DEFAULT NULL,
  `declined_at` timestamp NULL DEFAULT NULL,
  `completed_at` timestamp NULL DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `idx_challenger` (`challenger_id`),
  KEY `idx_challenged` (`challenged_id`),
  KEY `idx_status` (`status`),
  KEY `idx_tenant` (`tenant_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb3 COLLATE=utf8mb3_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `gamification_challenges`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `gamification_challenges` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `tenant_id` int(10) unsigned NOT NULL,
  `name` varchar(255) NOT NULL,
  `description` text DEFAULT NULL,
  `type` varchar(50) NOT NULL DEFAULT 'one_time',
  `badge_key` varchar(100) DEFAULT NULL,
  `xp_reward` int(11) NOT NULL DEFAULT 0,
  `criteria` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`criteria`)),
  `status` varchar(20) NOT NULL DEFAULT 'active',
  `starts_at` datetime DEFAULT NULL,
  `ends_at` datetime DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `gamification_challenges_tenant_id_index` (`tenant_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `gamification_cron_log`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `gamification_cron_log` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `tenant_id` int(11) NOT NULL,
  `job_name` varchar(50) NOT NULL,
  `run_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `run_by` int(11) DEFAULT NULL,
  `status` varchar(20) DEFAULT 'completed',
  `details` text DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `idx_tenant_job` (`tenant_id`,`job_name`),
  KEY `idx_run_at` (`run_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb3 COLLATE=utf8mb3_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `gamification_tour_completions`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `gamification_tour_completions` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `user_id` int(11) NOT NULL,
  `completed_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `skipped` tinyint(1) DEFAULT 0,
  PRIMARY KEY (`id`),
  UNIQUE KEY `unique_user` (`user_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb3 COLLATE=utf8mb3_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `gamifications`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `gamifications` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `gdpr_audit_log`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `gdpr_audit_log` (
  `id` bigint(20) NOT NULL AUTO_INCREMENT,
  `user_id` int(11) DEFAULT NULL,
  `admin_id` int(11) DEFAULT NULL,
  `tenant_id` int(11) NOT NULL,
  `action` varchar(100) NOT NULL,
  `entity_type` varchar(100) DEFAULT NULL,
  `entity_id` int(11) DEFAULT NULL,
  `old_value` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`old_value`)),
  `new_value` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`new_value`)),
  `ip_address` varchar(45) DEFAULT NULL,
  `user_agent` text DEFAULT NULL,
  `request_id` varchar(100) DEFAULT NULL,
  `additional_data` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`additional_data`)),
  `created_at` datetime DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `idx_user_id` (`user_id`),
  KEY `idx_admin_id` (`admin_id`),
  KEY `idx_action` (`action`),
  KEY `idx_created_at` (`created_at`),
  KEY `idx_tenant_id` (`tenant_id`),
  KEY `idx_entity` (`entity_type`,`entity_id`)
) ENGINE=InnoDB AUTO_INCREMENT=273 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `gdpr_requests`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `gdpr_requests` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `user_id` int(11) NOT NULL,
  `tenant_id` int(11) NOT NULL,
  `request_type` enum('access','erasure','rectification','restriction','portability','objection') NOT NULL,
  `status` enum('pending','processing','completed','rejected','cancelled') DEFAULT 'pending',
  `priority` enum('normal','high','urgent') DEFAULT 'normal',
  `requested_at` datetime DEFAULT current_timestamp(),
  `acknowledged_at` datetime DEFAULT NULL,
  `processed_at` datetime DEFAULT NULL,
  `processed_by` int(11) DEFAULT NULL,
  `assigned_to` bigint(20) unsigned DEFAULT NULL,
  `rejection_reason` text DEFAULT NULL,
  `export_file_path` varchar(500) DEFAULT NULL,
  `export_expires_at` datetime DEFAULT NULL,
  `verification_token` varchar(255) DEFAULT NULL,
  `verified_at` datetime DEFAULT NULL,
  `notes` text DEFAULT NULL,
  `metadata` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`metadata`)),
  `created_at` datetime DEFAULT current_timestamp(),
  `updated_at` datetime DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `idx_user_id` (`user_id`),
  KEY `idx_status` (`status`),
  KEY `idx_tenant_id` (`tenant_id`),
  KEY `idx_request_type` (`request_type`),
  KEY `idx_requested_at` (`requested_at`),
  KEY `gdpr_requests_assigned_to_index` (`assigned_to`)
) ENGINE=InnoDB AUTO_INCREMENT=6 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `geocode_cache`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `geocode_cache` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `address_hash` varchar(32) DEFAULT NULL,
  `address` varchar(255) DEFAULT NULL,
  `latitude` decimal(10,8) DEFAULT NULL,
  `longitude` decimal(11,8) DEFAULT NULL,
  `created_at` datetime DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `address_hash` (`address_hash`),
  KEY `idx_hash` (`address_hash`),
  KEY `idx_created` (`created_at`)
) ENGINE=InnoDB AUTO_INCREMENT=257 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `goal_checkins`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `goal_checkins` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `goal_id` int(10) unsigned NOT NULL,
  `user_id` int(10) unsigned NOT NULL,
  `tenant_id` int(10) unsigned NOT NULL,
  `progress_percent` decimal(5,2) DEFAULT NULL COMMENT 'Progress percentage at time of check-in',
  `note` text DEFAULT NULL COMMENT 'Free-text check-in note',
  `mood` enum('great','good','neutral','okay','struggling','stuck','motivated','grateful') DEFAULT NULL,
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `idx_goal_checkins_goal` (`goal_id`),
  KEY `idx_goal_checkins_user` (`user_id`),
  KEY `idx_goal_checkins_tenant` (`tenant_id`),
  KEY `idx_goal_checkins_created` (`goal_id`,`created_at` DESC)
) ENGINE=InnoDB AUTO_INCREMENT=2 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `goal_progress_log`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `goal_progress_log` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `goal_id` int(10) unsigned NOT NULL,
  `tenant_id` int(10) unsigned NOT NULL,
  `event_type` enum('progress_update','milestone_reached','checkin','status_change','buddy_joined','created','completed') NOT NULL,
  `old_value` varchar(255) DEFAULT NULL,
  `new_value` varchar(255) DEFAULT NULL,
  `metadata` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL COMMENT 'Additional data about the event' CHECK (json_valid(`metadata`)),
  `created_by` int(10) unsigned DEFAULT NULL,
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `idx_goal_progress_log_goal` (`goal_id`,`created_at` DESC),
  KEY `idx_goal_progress_log_tenant` (`tenant_id`)
) ENGINE=InnoDB AUTO_INCREMENT=2 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `goal_reminders`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `goal_reminders` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `goal_id` int(10) unsigned NOT NULL,
  `user_id` int(10) unsigned NOT NULL,
  `tenant_id` int(10) unsigned NOT NULL,
  `frequency` enum('daily','weekly','biweekly','monthly') NOT NULL DEFAULT 'weekly',
  `next_reminder_at` datetime DEFAULT NULL,
  `last_sent_at` datetime DEFAULT NULL,
  `enabled` tinyint(1) NOT NULL DEFAULT 1,
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  `updated_at` datetime DEFAULT NULL ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `idx_goal_reminders_unique` (`goal_id`,`user_id`),
  KEY `idx_goal_reminders_next` (`enabled`,`next_reminder_at`),
  KEY `idx_goal_reminders_tenant` (`tenant_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `goal_templates`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `goal_templates` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `tenant_id` int(10) unsigned NOT NULL,
  `title` varchar(255) NOT NULL,
  `description` text DEFAULT NULL,
  `default_milestones` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL COMMENT 'JSON array of milestone objects: [{title, target_value}]' CHECK (json_valid(`default_milestones`)),
  `category` varchar(100) DEFAULT NULL,
  `default_target_value` decimal(10,2) NOT NULL DEFAULT 0.00,
  `is_public` tinyint(1) NOT NULL DEFAULT 1 COMMENT 'Whether this template is visible to all users',
  `created_by` int(10) unsigned DEFAULT NULL,
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  `updated_at` datetime DEFAULT NULL ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `idx_goal_templates_tenant` (`tenant_id`),
  KEY `idx_goal_templates_category` (`tenant_id`,`category`),
  KEY `idx_goal_templates_created_by` (`created_by`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `goals`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `goals` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `tenant_id` int(11) NOT NULL,
  `user_id` int(11) NOT NULL,
  `mentor_id` int(11) DEFAULT NULL,
  `title` varchar(255) NOT NULL,
  `description` text DEFAULT NULL,
  `deadline` date DEFAULT NULL,
  `is_public` tinyint(1) DEFAULT 0,
  `status` enum('active','completed','achieved','abandoned') DEFAULT 'active',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NULL DEFAULT NULL ON UPDATE current_timestamp(),
  `buddy_id` int(11) DEFAULT NULL,
  `completed_at` timestamp NULL DEFAULT NULL,
  `current_value` decimal(10,2) NOT NULL DEFAULT 0.00 COMMENT 'Current progress value',
  `target_value` decimal(10,2) NOT NULL DEFAULT 0.00 COMMENT 'Target value for goal completion',
  `checkin_frequency` enum('none','weekly','biweekly') NOT NULL DEFAULT 'none' COMMENT 'How often to prompt for check-ins',
  `last_checkin_at` datetime DEFAULT NULL COMMENT 'Timestamp of last check-in',
  `template_id` int(10) unsigned DEFAULT NULL COMMENT 'Goal template this was created from',
  PRIMARY KEY (`id`),
  KEY `tenant_id` (`tenant_id`),
  KEY `user_id` (`user_id`),
  KEY `mentor_id` (`mentor_id`)
) ENGINE=InnoDB AUTO_INCREMENT=9 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `group_achievement_progress`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `group_achievement_progress` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `group_id` int(11) NOT NULL,
  `achievement_id` int(11) NOT NULL,
  `current_count` int(11) DEFAULT 0,
  `completed_at` timestamp NULL DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `unique_group_progress` (`group_id`,`achievement_id`),
  KEY `achievement_id` (`achievement_id`),
  CONSTRAINT `group_achievement_progress_ibfk_1` FOREIGN KEY (`achievement_id`) REFERENCES `group_achievements` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb3 COLLATE=utf8mb3_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `group_achievements`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `group_achievements` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `tenant_id` int(11) NOT NULL,
  `achievement_key` varchar(50) NOT NULL,
  `name` varchar(100) NOT NULL,
  `description` text DEFAULT NULL,
  `icon` varchar(50) DEFAULT NULL,
  `action_type` varchar(50) NOT NULL,
  `target_count` int(11) DEFAULT 1,
  `xp_reward_per_member` int(11) DEFAULT 25,
  `is_active` tinyint(1) DEFAULT 1,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `unique_group_achievement` (`tenant_id`,`achievement_key`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb3 COLLATE=utf8mb3_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `group_announcements`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `group_announcements` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `group_id` int(11) NOT NULL,
  `tenant_id` int(11) NOT NULL,
  `title` varchar(255) NOT NULL,
  `content` text NOT NULL,
  `is_pinned` tinyint(1) NOT NULL DEFAULT 0,
  `priority` int(11) NOT NULL DEFAULT 0,
  `created_by` int(11) NOT NULL,
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  `updated_at` datetime DEFAULT NULL ON UPDATE current_timestamp(),
  `expires_at` datetime DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `idx_group_announcements_group` (`group_id`),
  KEY `idx_group_announcements_tenant` (`tenant_id`),
  KEY `idx_group_announcements_pinned` (`is_pinned`,`priority` DESC),
  KEY `idx_group_announcements_expires` (`expires_at`)
) ENGINE=InnoDB AUTO_INCREMENT=2 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `group_answers`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `group_answers` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `tenant_id` int(11) NOT NULL,
  `question_id` int(11) NOT NULL,
  `user_id` int(11) NOT NULL,
  `body` text NOT NULL,
  `is_accepted` tinyint(1) NOT NULL DEFAULT 0,
  `vote_count` int(11) NOT NULL DEFAULT 0,
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  `updated_at` datetime NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `idx_ga_question` (`question_id`),
  KEY `idx_ga_votes` (`question_id`,`vote_count` DESC)
) ENGINE=InnoDB AUTO_INCREMENT=2 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `group_approval_requests`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `group_approval_requests` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `tenant_id` int(11) NOT NULL,
  `group_id` int(11) NOT NULL,
  `submitted_by` int(11) NOT NULL,
  `submission_notes` text DEFAULT NULL,
  `reviewed_by` int(11) DEFAULT NULL,
  `review_notes` text DEFAULT NULL,
  `status` varchar(50) DEFAULT 'pending',
  `created_at` timestamp NULL DEFAULT current_timestamp(),
  `reviewed_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `idx_tenant_status` (`tenant_id`,`status`),
  KEY `idx_group` (`group_id`),
  KEY `idx_submitter` (`tenant_id`,`submitted_by`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `group_audit_log`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `group_audit_log` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `tenant_id` int(11) NOT NULL,
  `group_id` int(11) DEFAULT NULL,
  `user_id` int(11) DEFAULT NULL,
  `target_user_id` int(11) DEFAULT NULL,
  `action` varchar(100) NOT NULL,
  `details` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`details`)),
  `ip_address` varchar(45) DEFAULT NULL,
  `user_agent` text DEFAULT NULL,
  `created_at` datetime DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `idx_tenant_group_date` (`tenant_id`,`group_id`,`created_at`),
  KEY `idx_tenant_action_date` (`tenant_id`,`action`,`created_at`)
) ENGINE=InnoDB AUTO_INCREMENT=6 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `group_auto_assign_rules`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `group_auto_assign_rules` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `tenant_id` int(11) NOT NULL,
  `group_id` int(11) NOT NULL,
  `rule_type` enum('location','interest','role','attribute') NOT NULL,
  `rule_value` varchar(255) NOT NULL COMMENT 'e.g. location name, interest tag, user role',
  `is_active` tinyint(1) NOT NULL DEFAULT 1,
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `idx_gaar_tenant` (`tenant_id`,`is_active`),
  KEY `idx_gaar_group` (`group_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `group_challenges`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `group_challenges` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `tenant_id` int(11) NOT NULL,
  `group_id` int(11) NOT NULL,
  `created_by` int(11) NOT NULL,
  `title` varchar(255) NOT NULL,
  `description` text DEFAULT NULL,
  `metric` varchar(50) NOT NULL COMMENT 'e.g. posts, discussions, events, members, files',
  `target_value` int(11) NOT NULL,
  `current_value` int(11) NOT NULL DEFAULT 0,
  `reward_xp` int(11) NOT NULL DEFAULT 100,
  `reward_badge` varchar(100) DEFAULT NULL,
  `status` enum('active','completed','expired','cancelled') NOT NULL DEFAULT 'active',
  `starts_at` datetime NOT NULL DEFAULT current_timestamp(),
  `ends_at` datetime NOT NULL,
  `completed_at` datetime DEFAULT NULL,
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  `updated_at` datetime NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `idx_gc_group` (`group_id`,`status`),
  KEY `idx_gc_tenant` (`tenant_id`),
  KEY `idx_gc_active` (`status`,`ends_at`)
) ENGINE=InnoDB AUTO_INCREMENT=6 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `group_chatroom_messages`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `group_chatroom_messages` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `chatroom_id` int(10) unsigned NOT NULL,
  `user_id` int(11) NOT NULL,
  `body` text NOT NULL,
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  `updated_at` datetime DEFAULT NULL ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `idx_chatroom` (`chatroom_id`,`created_at`),
  CONSTRAINT `fk_msg_chatroom` FOREIGN KEY (`chatroom_id`) REFERENCES `group_chatrooms` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=4 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `group_chatroom_pinned_messages`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `group_chatroom_pinned_messages` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `chatroom_id` int(10) unsigned NOT NULL,
  `message_id` int(10) unsigned NOT NULL,
  `pinned_by` int(11) NOT NULL,
  `tenant_id` int(10) unsigned NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_chatroom_pinned_msg` (`chatroom_id`,`message_id`),
  KEY `group_chatroom_pinned_messages_message_id_foreign` (`message_id`),
  KEY `group_chatroom_pinned_messages_pinned_by_foreign` (`pinned_by`),
  CONSTRAINT `group_chatroom_pinned_messages_chatroom_id_foreign` FOREIGN KEY (`chatroom_id`) REFERENCES `group_chatrooms` (`id`) ON DELETE CASCADE,
  CONSTRAINT `group_chatroom_pinned_messages_message_id_foreign` FOREIGN KEY (`message_id`) REFERENCES `group_chatroom_messages` (`id`) ON DELETE CASCADE,
  CONSTRAINT `group_chatroom_pinned_messages_pinned_by_foreign` FOREIGN KEY (`pinned_by`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=2 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `group_chatrooms`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `group_chatrooms` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `group_id` int(10) unsigned NOT NULL,
  `tenant_id` int(10) unsigned NOT NULL,
  `name` varchar(100) NOT NULL,
  `description` varchar(500) DEFAULT NULL,
  `category` varchar(100) DEFAULT NULL,
  `is_private` tinyint(1) NOT NULL DEFAULT 0,
  `permissions` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`permissions`)),
  `created_by` int(11) NOT NULL,
  `is_default` tinyint(1) NOT NULL DEFAULT 0 COMMENT 'Default "General" channel',
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `idx_group` (`group_id`,`tenant_id`),
  KEY `idx_tenant` (`tenant_id`)
) ENGINE=InnoDB AUTO_INCREMENT=5 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `group_collection_items`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `group_collection_items` (
  `collection_id` int(11) NOT NULL,
  `group_id` int(11) NOT NULL,
  `sort_order` int(11) NOT NULL DEFAULT 0,
  PRIMARY KEY (`collection_id`,`group_id`),
  KEY `idx_gci_group` (`group_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `group_collections`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `group_collections` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `tenant_id` int(11) NOT NULL,
  `name` varchar(255) NOT NULL,
  `description` text DEFAULT NULL,
  `image_url` varchar(500) DEFAULT NULL,
  `sort_order` int(11) NOT NULL DEFAULT 0,
  `is_active` tinyint(1) NOT NULL DEFAULT 1,
  `created_by` int(11) NOT NULL,
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  `updated_at` datetime NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `idx_gc_tenant` (`tenant_id`,`is_active`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `group_configuration`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `group_configuration` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `tenant_id` int(11) NOT NULL,
  `config_key` varchar(100) NOT NULL,
  `config_value` text DEFAULT NULL,
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `unique_tenant_config` (`tenant_id`,`config_key`),
  KEY `idx_tenant` (`tenant_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `group_content_flags`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `group_content_flags` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `tenant_id` int(11) NOT NULL,
  `content_type` varchar(50) NOT NULL,
  `content_id` int(11) NOT NULL,
  `reported_by` int(11) NOT NULL,
  `reason` varchar(50) NOT NULL,
  `description` text DEFAULT NULL,
  `status` varchar(20) DEFAULT 'pending',
  `moderated_by` int(11) DEFAULT NULL,
  `moderation_action` varchar(50) DEFAULT NULL,
  `moderator_notes` text DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT current_timestamp(),
  `resolved_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `idx_tenant_status` (`tenant_id`,`status`),
  KEY `idx_content` (`content_type`,`content_id`),
  KEY `idx_reporter` (`tenant_id`,`reported_by`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `group_custom_field_values`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `group_custom_field_values` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `group_id` int(11) NOT NULL,
  `field_id` int(11) NOT NULL,
  `field_value` text DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uk_gcfv` (`group_id`,`field_id`),
  KEY `idx_gcfv_field` (`field_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `group_custom_fields`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `group_custom_fields` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `tenant_id` int(11) NOT NULL,
  `field_name` varchar(100) NOT NULL,
  `field_key` varchar(100) NOT NULL,
  `field_type` enum('text','number','date','select','multi_select','boolean','url') NOT NULL DEFAULT 'text',
  `options` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL COMMENT 'Options for select/multi_select types' CHECK (json_valid(`options`)),
  `is_required` tinyint(1) NOT NULL DEFAULT 0,
  `is_searchable` tinyint(1) NOT NULL DEFAULT 0,
  `sort_order` int(11) NOT NULL DEFAULT 0,
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `uk_gcf_key` (`tenant_id`,`field_key`),
  KEY `idx_gcf_tenant` (`tenant_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `group_discussion_messages`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `group_discussion_messages` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `group_discussion_subscribers`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `group_discussion_subscribers` (
  `id` int(11) NOT NULL,
  `discussion_id` int(11) NOT NULL,
  `user_id` int(11) NOT NULL,
  `created_at` datetime DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `group_discussions`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `group_discussions` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `tenant_id` int(10) unsigned NOT NULL DEFAULT 1,
  `group_id` int(11) NOT NULL,
  `user_id` int(11) NOT NULL,
  `title` varchar(255) NOT NULL,
  `is_pinned` tinyint(1) DEFAULT 0,
  `is_locked` tinyint(1) DEFAULT 0,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  `banned_until` varchar(255) DEFAULT NULL,
  `resolved_at` timestamp NULL DEFAULT NULL,
  `simplicity` varchar(255) DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `group_id` (`group_id`),
  KEY `user_id` (`user_id`),
  KEY `idx_tenant_id` (`tenant_id`),
  CONSTRAINT `group_discussions_ibfk_1` FOREIGN KEY (`group_id`) REFERENCES `groups` (`id`) ON DELETE CASCADE,
  CONSTRAINT `group_discussions_ibfk_2` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=14 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `group_exchange_participants`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `group_exchange_participants` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `group_exchange_id` int(11) NOT NULL,
  `user_id` int(11) NOT NULL,
  `role` enum('provider','receiver') NOT NULL,
  `hours` decimal(10,2) NOT NULL DEFAULT 0.00,
  `weight` decimal(5,2) DEFAULT 1.00,
  `confirmed` tinyint(1) NOT NULL DEFAULT 0,
  `confirmed_at` timestamp NULL DEFAULT NULL,
  `notes` text DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `uk_exchange_user_role` (`group_exchange_id`,`user_id`,`role`),
  KEY `idx_exchange` (`group_exchange_id`),
  KEY `idx_user` (`user_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `group_exchanges`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `group_exchanges` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `tenant_id` int(11) NOT NULL,
  `title` varchar(255) NOT NULL,
  `description` text DEFAULT NULL,
  `organizer_id` int(11) NOT NULL,
  `listing_id` int(11) DEFAULT NULL,
  `status` enum('draft','pending_participants','pending_broker','active','pending_confirmation','completed','cancelled','disputed') NOT NULL DEFAULT 'draft',
  `split_type` enum('equal','custom','weighted') NOT NULL DEFAULT 'equal',
  `total_hours` decimal(10,2) NOT NULL DEFAULT 0.00,
  `broker_id` int(11) DEFAULT NULL,
  `broker_notes` text DEFAULT NULL,
  `completed_at` timestamp NULL DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `idx_tenant` (`tenant_id`),
  KEY `idx_organizer` (`organizer_id`),
  KEY `idx_status` (`status`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `group_feature_toggles`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `group_feature_toggles` (
  `id` int(11) unsigned NOT NULL AUTO_INCREMENT,
  `tenant_id` int(11) NOT NULL COMMENT 'Tenant this toggle belongs to',
  `feature_key` varchar(100) NOT NULL COMMENT 'Feature identifier',
  `is_enabled` tinyint(1) DEFAULT 1 COMMENT 'Whether this feature is enabled',
  `category` enum('core','content','moderation','gamification','advanced') NOT NULL COMMENT 'Feature category',
  `description` text DEFAULT NULL COMMENT 'Description of what this feature does',
  `created_at` datetime DEFAULT current_timestamp(),
  `updated_at` datetime DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `unique_tenant_feature` (`tenant_id`,`feature_key`),
  KEY `idx_tenant` (`tenant_id`),
  KEY `idx_tenant_category` (`tenant_id`,`category`),
  KEY `idx_enabled` (`is_enabled`),
  CONSTRAINT `group_feature_toggles_ibfk_1` FOREIGN KEY (`tenant_id`) REFERENCES `tenants` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Feature toggles for groups module per tenant';
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `group_feedbacks`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `group_feedbacks` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `group_files`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `group_files` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `group_id` int(11) NOT NULL,
  `tenant_id` int(11) NOT NULL,
  `file_name` varchar(255) NOT NULL,
  `file_path` varchar(500) NOT NULL,
  `file_type` varchar(50) NOT NULL,
  `file_size` bigint(20) NOT NULL DEFAULT 0,
  `folder` varchar(255) DEFAULT NULL,
  `description` text DEFAULT NULL,
  `download_count` int(11) NOT NULL DEFAULT 0,
  `uploaded_by` int(11) NOT NULL,
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `idx_group_files_group` (`group_id`),
  KEY `idx_group_files_tenant` (`tenant_id`),
  KEY `idx_group_files_uploader` (`uploaded_by`),
  KEY `idx_group_files_folder` (`group_id`,`folder`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `group_invites`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `group_invites` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `tenant_id` int(11) NOT NULL,
  `group_id` int(11) NOT NULL,
  `invited_by` int(11) NOT NULL,
  `invite_type` enum('email','link') NOT NULL DEFAULT 'email',
  `email` varchar(255) DEFAULT NULL,
  `token` varchar(80) NOT NULL,
  `message` text DEFAULT NULL,
  `status` enum('pending','accepted','expired','revoked') NOT NULL DEFAULT 'pending',
  `accepted_by` int(11) DEFAULT NULL,
  `accepted_at` datetime DEFAULT NULL,
  `expires_at` datetime DEFAULT NULL,
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  `updated_at` datetime NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `uk_group_invites_token` (`token`),
  KEY `idx_group_invites_group` (`group_id`,`status`),
  KEY `idx_group_invites_email` (`email`,`status`),
  KEY `idx_group_invites_tenant` (`tenant_id`)
) ENGINE=InnoDB AUTO_INCREMENT=2 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `group_media`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `group_media` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `tenant_id` int(11) NOT NULL,
  `group_id` int(11) NOT NULL,
  `uploaded_by` int(11) NOT NULL,
  `media_type` enum('image','video') NOT NULL DEFAULT 'image',
  `file_path` varchar(500) DEFAULT NULL,
  `url` varchar(500) DEFAULT NULL COMMENT 'External URL for video embeds',
  `thumbnail_path` varchar(500) DEFAULT NULL,
  `caption` text DEFAULT NULL,
  `file_size` bigint(20) NOT NULL DEFAULT 0,
  `width` int(11) DEFAULT NULL,
  `height` int(11) DEFAULT NULL,
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `idx_gm_group` (`group_id`,`tenant_id`),
  KEY `idx_gm_type` (`group_id`,`media_type`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `group_members`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `group_members` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `tenant_id` int(11) NOT NULL DEFAULT 1,
  `group_id` int(11) NOT NULL,
  `user_id` int(11) NOT NULL,
  `joined_at` datetime DEFAULT current_timestamp(),
  `status` enum('active','pending','invited','banned') NOT NULL DEFAULT 'active',
  `role` enum('member','admin','owner') NOT NULL DEFAULT 'member',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp() COMMENT 'When the user joined this group',
  `updated_at` timestamp NULL DEFAULT NULL ON UPDATE current_timestamp(),
  `apply` varchar(255) DEFAULT NULL,
  `award` varchar(255) DEFAULT NULL,
  `banned_until` varchar(255) DEFAULT NULL,
  `certa` varchar(255) DEFAULT NULL,
  `click_rate` timestamp NULL DEFAULT NULL,
  `content` text DEFAULT NULL,
  `distance` varchar(255) DEFAULT NULL,
  `errors` varchar(255) DEFAULT NULL,
  `key_name` varchar(255) DEFAULT NULL,
  `open_rate` timestamp NULL DEFAULT NULL,
  `pages` varchar(255) DEFAULT NULL,
  `resolved_at` timestamp NULL DEFAULT NULL,
  `is_federated` tinyint(1) NOT NULL DEFAULT 0,
  `source_tenant_id` int(11) DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `group_id` (`group_id`),
  KEY `user_id` (`user_id`),
  KEY `idx_group_members_group_status` (`group_id`,`status`),
  KEY `idx_group_members_group_id` (`group_id`),
  KEY `idx_created_at` (`created_at`),
  KEY `idx_group_status` (`group_id`,`status`),
  KEY `idx_group_status_id` (`group_id`,`status`,`id`),
  KEY `idx_user_status` (`user_id`,`status`),
  KEY `idx_federated_groups` (`is_federated`,`source_tenant_id`),
  KEY `idx_group_members_role` (`role`),
  CONSTRAINT `group_members_ibfk_1` FOREIGN KEY (`group_id`) REFERENCES `groups` (`id`) ON DELETE CASCADE,
  CONSTRAINT `group_members_ibfk_2` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=3847 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `group_notification_preferences`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `group_notification_preferences` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `tenant_id` int(11) NOT NULL,
  `user_id` int(11) NOT NULL,
  `group_id` int(11) NOT NULL,
  `frequency` enum('instant','digest','muted') NOT NULL DEFAULT 'instant',
  `email_enabled` tinyint(1) NOT NULL DEFAULT 1,
  `push_enabled` tinyint(1) NOT NULL DEFAULT 1,
  `updated_at` datetime NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `uk_gnp` (`user_id`,`group_id`),
  KEY `idx_gnp_group` (`group_id`)
) ENGINE=InnoDB AUTO_INCREMENT=4 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `group_policies`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `group_policies` (
  `id` int(11) unsigned NOT NULL AUTO_INCREMENT,
  `tenant_id` int(11) NOT NULL COMMENT 'Tenant this policy belongs to',
  `policy_key` varchar(100) NOT NULL COMMENT 'Policy identifier',
  `policy_value` text NOT NULL COMMENT 'Policy value (JSON-encoded)',
  `category` enum('creation','membership','content','moderation','notifications','features') NOT NULL COMMENT 'Policy category',
  `value_type` enum('boolean','number','string','json','list') NOT NULL COMMENT 'Type of value stored',
  `description` text DEFAULT NULL COMMENT 'Description of what this policy does',
  `created_at` datetime DEFAULT current_timestamp(),
  `updated_at` datetime DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `unique_tenant_policy` (`tenant_id`,`policy_key`),
  KEY `idx_tenant_category` (`tenant_id`,`category`),
  KEY `idx_tenant` (`tenant_id`),
  CONSTRAINT `group_policies_ibfk_1` FOREIGN KEY (`tenant_id`) REFERENCES `tenants` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=5 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Flexible tenant-specific policies for groups module';
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `group_posts`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `group_posts` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `tenant_id` int(10) unsigned NOT NULL DEFAULT 1,
  `discussion_id` int(11) NOT NULL,
  `user_id` int(11) NOT NULL,
  `content` text NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `banned_until` varchar(255) DEFAULT NULL,
  `resolved_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `discussion_id` (`discussion_id`),
  KEY `user_id` (`user_id`),
  KEY `idx_tenant_id` (`tenant_id`),
  CONSTRAINT `group_posts_ibfk_1` FOREIGN KEY (`discussion_id`) REFERENCES `group_discussions` (`id`) ON DELETE CASCADE,
  CONSTRAINT `group_posts_ibfk_2` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=14 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `group_qa_votes`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `group_qa_votes` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `tenant_id` int(11) NOT NULL,
  `user_id` int(11) NOT NULL,
  `votable_type` enum('question','answer') NOT NULL,
  `votable_id` int(11) NOT NULL,
  `vote` tinyint(1) NOT NULL COMMENT '1 = upvote, -1 = downvote',
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `uk_gqv` (`user_id`,`votable_type`,`votable_id`),
  KEY `idx_gqv_votable` (`votable_type`,`votable_id`)
) ENGINE=InnoDB AUTO_INCREMENT=3 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `group_questions`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `group_questions` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `tenant_id` int(11) NOT NULL,
  `group_id` int(11) NOT NULL,
  `user_id` int(11) NOT NULL,
  `title` varchar(500) NOT NULL,
  `body` text DEFAULT NULL,
  `accepted_answer_id` int(11) DEFAULT NULL,
  `is_closed` tinyint(1) NOT NULL DEFAULT 0,
  `view_count` int(11) NOT NULL DEFAULT 0,
  `vote_count` int(11) NOT NULL DEFAULT 0,
  `answer_count` int(11) NOT NULL DEFAULT 0,
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  `updated_at` datetime NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `idx_gq_group` (`group_id`,`tenant_id`),
  KEY `idx_gq_user` (`user_id`),
  KEY `idx_gq_votes` (`group_id`,`vote_count` DESC)
) ENGINE=InnoDB AUTO_INCREMENT=4 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `group_ranking_logs`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `group_ranking_logs` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `tenant_id` int(10) unsigned NOT NULL,
  `ranking_type` varchar(50) NOT NULL,
  `stats_json` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`stats_json`)),
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `idx_tenant_type` (`tenant_id`,`ranking_type`),
  KEY `idx_created_at` (`created_at`)
) ENGINE=InnoDB AUTO_INCREMENT=29 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `group_recommendation_cache`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `group_recommendation_cache` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `tenant_id` int(10) unsigned NOT NULL,
  `user_id` int(10) unsigned NOT NULL,
  `group_id` int(10) unsigned NOT NULL,
  `score` decimal(5,4) NOT NULL COMMENT 'Recommendation score 0.0000-1.0000',
  `reason` varchar(255) DEFAULT NULL,
  `algorithm` varchar(50) NOT NULL COMMENT 'collaborative|content|location|activity',
  `computed_at` datetime NOT NULL DEFAULT current_timestamp(),
  `expires_at` datetime NOT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `unique_recommendation` (`tenant_id`,`user_id`,`group_id`,`algorithm`),
  KEY `idx_tenant_user` (`tenant_id`,`user_id`,`expires_at`),
  KEY `idx_tenant_group` (`tenant_id`,`group_id`),
  KEY `idx_expires` (`expires_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `group_recommendation_interactions`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `group_recommendation_interactions` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `tenant_id` int(10) unsigned NOT NULL,
  `user_id` int(10) unsigned NOT NULL,
  `group_id` int(10) unsigned NOT NULL,
  `action` enum('viewed','clicked','joined','dismissed') NOT NULL,
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `idx_tenant_user` (`tenant_id`,`user_id`),
  KEY `idx_tenant_group` (`tenant_id`,`group_id`),
  KEY `idx_created` (`created_at`),
  KEY `idx_action` (`action`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `group_scheduled_posts`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `group_scheduled_posts` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `tenant_id` int(11) NOT NULL,
  `group_id` int(11) NOT NULL,
  `user_id` int(11) NOT NULL,
  `post_type` enum('discussion','announcement','post') NOT NULL DEFAULT 'discussion',
  `title` varchar(500) DEFAULT NULL,
  `content` text NOT NULL,
  `is_recurring` tinyint(1) NOT NULL DEFAULT 0,
  `recurrence_pattern` enum('daily','weekly','monthly') DEFAULT NULL,
  `scheduled_at` datetime NOT NULL,
  `published_at` datetime DEFAULT NULL,
  `status` enum('scheduled','published','cancelled') NOT NULL DEFAULT 'scheduled',
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  `updated_at` datetime NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `idx_gsp_group` (`group_id`,`status`),
  KEY `idx_gsp_scheduled` (`status`,`scheduled_at`),
  KEY `idx_gsp_tenant` (`tenant_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `group_sso_mappings`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `group_sso_mappings` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `tenant_id` int(11) NOT NULL,
  `saml_group_name` varchar(255) NOT NULL,
  `group_id` int(11) NOT NULL,
  `auto_assign` tinyint(1) NOT NULL DEFAULT 1,
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `uk_gsm` (`tenant_id`,`saml_group_name`,`group_id`),
  KEY `idx_gsm_tenant` (`tenant_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `group_tag_assignments`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `group_tag_assignments` (
  `group_id` int(11) NOT NULL,
  `tag_id` int(11) NOT NULL,
  PRIMARY KEY (`group_id`,`tag_id`),
  KEY `idx_gta_tag` (`tag_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `group_tags`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `group_tags` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `tenant_id` int(11) NOT NULL,
  `name` varchar(100) NOT NULL,
  `slug` varchar(120) NOT NULL,
  `color` varchar(7) DEFAULT NULL COMMENT 'Hex color code',
  `usage_count` int(11) NOT NULL DEFAULT 0,
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `uk_group_tags_slug` (`tenant_id`,`slug`),
  KEY `idx_group_tags_tenant` (`tenant_id`),
  KEY `idx_group_tags_usage` (`tenant_id`,`usage_count` DESC)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `group_templates`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `group_templates` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `tenant_id` int(11) NOT NULL,
  `name` varchar(255) NOT NULL,
  `description` text DEFAULT NULL,
  `icon` varchar(50) DEFAULT NULL,
  `default_visibility` enum('public','private','secret') NOT NULL DEFAULT 'public',
  `default_type_id` int(11) DEFAULT NULL,
  `default_tags` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL COMMENT 'Array of tag IDs to auto-assign' CHECK (json_valid(`default_tags`)),
  `features` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL COMMENT 'Feature toggles to apply' CHECK (json_valid(`features`)),
  `welcome_message` text DEFAULT NULL,
  `is_active` tinyint(1) NOT NULL DEFAULT 1,
  `sort_order` int(11) NOT NULL DEFAULT 0,
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  `updated_at` datetime NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `idx_group_templates_tenant` (`tenant_id`,`is_active`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `group_types`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `group_types` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `tenant_id` int(11) NOT NULL,
  `name` varchar(100) NOT NULL,
  `slug` varchar(100) NOT NULL,
  `description` text DEFAULT NULL,
  `icon` varchar(100) DEFAULT 'fa-layer-group',
  `color` varchar(20) DEFAULT '#6366f1',
  `image_url` varchar(500) DEFAULT NULL,
  `sort_order` int(11) DEFAULT 0,
  `is_active` tinyint(1) DEFAULT 1,
  `is_hub` tinyint(1) DEFAULT 0,
  `created_at` datetime DEFAULT current_timestamp(),
  `updated_at` datetime DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  `content` text DEFAULT NULL,
  `errors` varchar(255) DEFAULT NULL,
  `pages` varchar(255) DEFAULT NULL,
  `reviewed_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `unique_type_slug` (`tenant_id`,`slug`),
  KEY `idx_tenant` (`tenant_id`),
  KEY `idx_active` (`is_active`),
  KEY `idx_sort` (`sort_order`),
  KEY `idx_is_hub` (`is_hub`),
  CONSTRAINT `group_types_ibfk_1` FOREIGN KEY (`tenant_id`) REFERENCES `tenants` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=31 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `group_user_bans`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `group_user_bans` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `banned_by` varchar(255) DEFAULT NULL,
  `banned_until` varchar(255) DEFAULT NULL,
  `resolved_at` timestamp NULL DEFAULT NULL,
  `tenant_id` int(11) DEFAULT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `group_user_warnings`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `group_user_warnings` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `banned_until` varchar(255) DEFAULT NULL,
  `resolved_at` timestamp NULL DEFAULT NULL,
  `tenant_id` int(11) DEFAULT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `group_views`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `group_views` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `group_id` int(11) NOT NULL,
  `tenant_id` int(11) NOT NULL,
  `user_id` int(11) DEFAULT NULL,
  `viewed_at` timestamp NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `idx_group` (`group_id`),
  KEY `idx_group_viewed` (`group_id`,`viewed_at`),
  KEY `idx_tenant` (`tenant_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `group_webhooks`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `group_webhooks` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `tenant_id` int(11) NOT NULL,
  `group_id` int(11) NOT NULL,
  `url` varchar(500) NOT NULL,
  `events` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin NOT NULL COMMENT 'Array of event types to trigger on' CHECK (json_valid(`events`)),
  `secret` varchar(255) DEFAULT NULL COMMENT 'HMAC secret for signature verification',
  `is_active` tinyint(1) NOT NULL DEFAULT 1,
  `last_fired_at` datetime DEFAULT NULL,
  `failure_count` int(11) NOT NULL DEFAULT 0,
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  `updated_at` datetime NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `idx_group_webhooks_group` (`group_id`,`is_active`),
  KEY `idx_group_webhooks_tenant` (`tenant_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `group_wiki_pages`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `group_wiki_pages` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `tenant_id` int(11) NOT NULL,
  `group_id` int(11) NOT NULL,
  `parent_id` int(11) DEFAULT NULL,
  `title` varchar(500) NOT NULL,
  `slug` varchar(500) NOT NULL,
  `content` longtext DEFAULT NULL,
  `created_by` int(11) NOT NULL,
  `last_edited_by` int(11) DEFAULT NULL,
  `sort_order` int(11) NOT NULL DEFAULT 0,
  `is_published` tinyint(1) NOT NULL DEFAULT 1,
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  `updated_at` datetime NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `uk_gwp_slug` (`group_id`,`slug`),
  KEY `idx_gwp_group` (`group_id`,`tenant_id`),
  KEY `idx_gwp_parent` (`parent_id`)
) ENGINE=InnoDB AUTO_INCREMENT=10 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `group_wiki_revisions`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `group_wiki_revisions` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `page_id` int(11) NOT NULL,
  `content` longtext NOT NULL,
  `edited_by` int(11) NOT NULL,
  `change_summary` varchar(255) DEFAULT NULL,
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `idx_gwr_page` (`page_id`)
) ENGINE=InnoDB AUTO_INCREMENT=11 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `groups`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `groups` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `parent_id` int(11) DEFAULT NULL,
  `type_id` int(11) DEFAULT NULL,
  `tenant_id` int(11) NOT NULL,
  `owner_id` int(11) NOT NULL,
  `name` varchar(255) NOT NULL,
  `slug` varchar(255) DEFAULT NULL,
  `description` text DEFAULT NULL,
  `visibility` enum('public','private') NOT NULL DEFAULT 'public',
  `is_featured` tinyint(1) DEFAULT 0,
  `image_url` varchar(255) DEFAULT NULL,
  `cover_image_url` varchar(255) DEFAULT NULL,
  `primary_color` varchar(7) DEFAULT NULL,
  `accent_color` varchar(7) DEFAULT NULL,
  `created_at` datetime DEFAULT current_timestamp(),
  `updated_at` datetime DEFAULT NULL ON UPDATE current_timestamp(),
  `location` varchar(255) DEFAULT NULL,
  `latitude` decimal(10,8) DEFAULT NULL,
  `longitude` decimal(11,8) DEFAULT NULL,
  `is_active` tinyint(1) NOT NULL DEFAULT 1 COMMENT 'Whether this group is active (1) or inactive (0)',
  `status` varchar(30) NOT NULL DEFAULT 'active',
  `cached_member_count` int(10) unsigned DEFAULT 0 COMMENT 'Cached count of active members',
  `has_children` tinyint(1) DEFAULT 0 COMMENT 'Whether this group has sub-groups',
  `ancestor` varchar(255) DEFAULT NULL,
  `apply` varchar(255) DEFAULT NULL,
  `banned_until` varchar(255) DEFAULT NULL,
  `certa` varchar(255) DEFAULT NULL,
  `click_rate` timestamp NULL DEFAULT NULL,
  `content` text DEFAULT NULL,
  `creator_id` int(11) DEFAULT NULL,
  `descendant` varchar(255) DEFAULT NULL,
  `distance` varchar(255) DEFAULT NULL,
  `errors` varchar(255) DEFAULT NULL,
  `feature_key` timestamp NULL DEFAULT NULL,
  `key_name` varchar(255) DEFAULT NULL,
  `open_rate` timestamp NULL DEFAULT NULL,
  `pages` varchar(255) DEFAULT NULL,
  `resolved_at` timestamp NULL DEFAULT NULL,
  `reviewed_at` timestamp NULL DEFAULT NULL,
  `allow_federated_members` tinyint(1) NOT NULL DEFAULT 0,
  `federated_visibility` enum('none','listed','joinable') NOT NULL DEFAULT 'none',
  `source_idea_id` int(10) unsigned DEFAULT NULL COMMENT 'Links to challenge_ideas.id if group was created from an ideation idea',
  `source_challenge_id` int(10) unsigned DEFAULT NULL COMMENT 'Links to ideation_challenges.id for context',
  PRIMARY KEY (`id`),
  UNIQUE KEY `uk_groups_slug` (`tenant_id`,`slug`),
  KEY `tenant_id` (`tenant_id`),
  KEY `owner_id` (`owner_id`),
  KEY `idx_type` (`type_id`),
  KEY `idx_tenant_active` (`tenant_id`,`is_active`),
  KEY `idx_parent_id` (`parent_id`),
  KEY `idx_tenant_parent` (`tenant_id`,`parent_id`),
  KEY `idx_tenant_name` (`tenant_id`,`name`),
  KEY `idx_cached_member_count` (`cached_member_count`),
  KEY `idx_tenant_membercount_name` (`tenant_id`,`cached_member_count`,`name`),
  KEY `idx_has_children` (`has_children`),
  KEY `idx_tenant_leaf_nodes` (`tenant_id`,`has_children`),
  KEY `idx_tenant_featured_created` (`tenant_id`,`is_featured`,`created_at`),
  KEY `idx_owner_id` (`owner_id`),
  KEY `idx_groups_status` (`tenant_id`,`status`),
  CONSTRAINT `groups_ibfk_1` FOREIGN KEY (`tenant_id`) REFERENCES `tenants` (`id`) ON DELETE CASCADE,
  CONSTRAINT `groups_ibfk_2` FOREIGN KEY (`owner_id`) REFERENCES `users` (`id`) ON DELETE CASCADE,
  CONSTRAINT `groups_ibfk_3` FOREIGN KEY (`type_id`) REFERENCES `group_types` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB AUTO_INCREMENT=90003 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `hashtags`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `hashtags` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `tenant_id` int(11) NOT NULL,
  `tag` varchar(100) NOT NULL,
  `post_count` int(11) NOT NULL DEFAULT 0,
  `last_used_at` timestamp NULL DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `unique_tag_tenant` (`tenant_id`,`tag`),
  KEY `idx_trending` (`tenant_id`,`post_count` DESC),
  KEY `idx_last_used` (`tenant_id`,`last_used_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `health_check_history`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `health_check_history` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `tenant_id` bigint(20) unsigned NOT NULL,
  `status` enum('healthy','degraded','unhealthy') NOT NULL,
  `checks` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin NOT NULL CHECK (json_valid(`checks`)),
  `latency_ms` int(10) unsigned DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `health_check_history_tenant_id_index` (`tenant_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `help_article_feedback`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `help_article_feedback` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `tenant_id` int(11) NOT NULL DEFAULT 1,
  `article_id` int(10) unsigned NOT NULL,
  `helpful` tinyint(1) NOT NULL DEFAULT 1 COMMENT '1 = helpful, 0 = not helpful',
  `user_id` int(10) unsigned DEFAULT NULL COMMENT 'NULL for anonymous feedback',
  `ip_address` varchar(45) DEFAULT NULL COMMENT 'For anonymous rate limiting',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `unique_user_feedback` (`article_id`,`user_id`),
  KEY `idx_article_id` (`article_id`),
  KEY `idx_user_id` (`user_id`),
  KEY `idx_ip_address` (`ip_address`),
  KEY `idx_anonymous_feedback` (`article_id`,`ip_address`),
  KEY `idx_tenant` (`tenant_id`)
) ENGINE=InnoDB AUTO_INCREMENT=2 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `help_articles`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `help_articles` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `tenant_id` int(11) NOT NULL DEFAULT 1,
  `title` varchar(255) NOT NULL,
  `slug` varchar(255) NOT NULL,
  `content` text NOT NULL,
  `module_tag` varchar(50) NOT NULL COMMENT 'core, wallet, listings, groups, events, volunteering, blog',
  `icon` varchar(50) DEFAULT 'dashicons-editor-help',
  `is_public` tinyint(1) DEFAULT 1,
  `view_count` int(10) unsigned NOT NULL DEFAULT 0,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `slug` (`slug`),
  KEY `idx_view_count` (`view_count`),
  KEY `idx_module_views` (`module_tag`,`view_count`),
  KEY `idx_tenant` (`tenant_id`)
) ENGINE=InnoDB AUTO_INCREMENT=113 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `help_faqs`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `help_faqs` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `tenant_id` int(10) unsigned NOT NULL,
  `category` varchar(100) NOT NULL DEFAULT 'General',
  `question` text NOT NULL,
  `answer` text NOT NULL,
  `sort_order` int(11) NOT NULL DEFAULT 0,
  `is_published` tinyint(1) NOT NULL DEFAULT 1,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `idx_tenant_published` (`tenant_id`,`is_published`),
  KEY `idx_tenant_category` (`tenant_id`,`category`)
) ENGINE=InnoDB AUTO_INCREMENT=19 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `idea_media`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `idea_media` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `idea_id` int(10) unsigned NOT NULL,
  `tenant_id` int(10) unsigned NOT NULL,
  `media_type` enum('image','video','document','link') NOT NULL DEFAULT 'image',
  `url` varchar(1000) NOT NULL,
  `caption` varchar(500) DEFAULT NULL,
  `sort_order` int(11) NOT NULL DEFAULT 0,
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `idx_idea` (`idea_id`),
  KEY `idx_tenant` (`tenant_id`),
  CONSTRAINT `fk_media_idea` FOREIGN KEY (`idea_id`) REFERENCES `challenge_ideas` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `idea_team_links`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `idea_team_links` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `idea_id` int(10) unsigned NOT NULL,
  `group_id` int(10) unsigned NOT NULL,
  `challenge_id` int(10) unsigned NOT NULL,
  `tenant_id` int(10) unsigned NOT NULL,
  `converted_by` int(11) NOT NULL COMMENT 'User who initiated conversion',
  `converted_at` datetime NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `uk_idea_group` (`idea_id`,`group_id`),
  KEY `idx_group` (`group_id`),
  KEY `idx_challenge` (`challenge_id`),
  KEY `idx_tenant` (`tenant_id`),
  CONSTRAINT `fk_itl_challenge` FOREIGN KEY (`challenge_id`) REFERENCES `ideation_challenges` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_itl_idea` FOREIGN KEY (`idea_id`) REFERENCES `challenge_ideas` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `ideation_challenges`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `ideation_challenges` (
  `id` int(11) unsigned NOT NULL AUTO_INCREMENT,
  `tenant_id` int(11) unsigned NOT NULL,
  `user_id` int(11) NOT NULL,
  `title` varchar(255) NOT NULL,
  `description` text NOT NULL,
  `category` varchar(100) DEFAULT NULL,
  `status` enum('draft','open','voting','evaluating','closed','archived') NOT NULL DEFAULT 'draft',
  `ideas_count` int(11) NOT NULL DEFAULT 0,
  `submission_deadline` datetime DEFAULT NULL,
  `voting_deadline` datetime DEFAULT NULL,
  `cover_image` varchar(500) DEFAULT NULL,
  `prize_description` text DEFAULT NULL,
  `max_ideas_per_user` int(11) DEFAULT NULL,
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  `updated_at` datetime DEFAULT NULL ON UPDATE current_timestamp(),
  `tags` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL COMMENT 'Array of tags/skills e.g. ["design","technology","sustainability"]' CHECK (json_valid(`tags`)),
  `views_count` int(10) unsigned NOT NULL DEFAULT 0,
  `is_featured` tinyint(1) NOT NULL DEFAULT 0,
  `favorites_count` int(10) unsigned NOT NULL DEFAULT 0,
  `category_id` int(10) unsigned DEFAULT NULL COMMENT 'FK to challenge_categories',
  `evaluation_criteria` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL COMMENT 'Array of evaluation criteria' CHECK (json_valid(`evaluation_criteria`)),
  PRIMARY KEY (`id`),
  KEY `idx_ideation_tenant_status` (`tenant_id`,`status`),
  KEY `idx_ideation_tenant_user` (`tenant_id`,`user_id`)
) ENGINE=InnoDB AUTO_INCREMENT=3 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `identity_provider_mappings`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `identity_provider_mappings` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `provider_id` bigint(20) unsigned NOT NULL,
  `user_id` int(10) unsigned NOT NULL,
  `external_id` varchar(255) NOT NULL,
  `profile_data` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`profile_data`)),
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `identity_provider_mappings_provider_id_index` (`provider_id`),
  KEY `identity_provider_mappings_user_id_index` (`user_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `identity_providers`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `identity_providers` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `tenant_id` int(10) unsigned NOT NULL,
  `provider` varchar(50) NOT NULL,
  `name` varchar(255) NOT NULL,
  `config` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`config`)),
  `is_active` tinyint(1) NOT NULL DEFAULT 1,
  `status` varchar(20) NOT NULL DEFAULT 'active',
  `last_health_check` datetime DEFAULT NULL,
  `health_data` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`health_data`)),
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `identity_providers_tenant_id_provider_index` (`tenant_id`,`provider`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `identity_verification_events`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `identity_verification_events` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `tenant_id` int(11) NOT NULL,
  `user_id` int(11) NOT NULL,
  `session_id` int(11) DEFAULT NULL COMMENT 'FK to identity_verification_sessions if applicable',
  `event_type` enum('registration_started','verification_created','verification_started','verification_processing','verification_passed','verification_failed','verification_expired','verification_cancelled','admin_review_started','admin_approved','admin_rejected','account_activated','fallback_triggered') NOT NULL,
  `actor_id` int(11) DEFAULT NULL COMMENT 'Admin user ID if admin action, NULL for system/webhook',
  `actor_type` enum('system','user','admin','webhook') NOT NULL DEFAULT 'system',
  `details` text DEFAULT NULL COMMENT 'JSON context for the event',
  `ip_address` varchar(45) DEFAULT NULL,
  `user_agent` varchar(500) DEFAULT NULL,
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `idx_ive_tenant_user` (`tenant_id`,`user_id`),
  KEY `idx_ive_session` (`session_id`),
  KEY `idx_ive_type` (`event_type`),
  KEY `idx_ive_created` (`created_at`),
  CONSTRAINT `fk_ive_session` FOREIGN KEY (`session_id`) REFERENCES `identity_verification_sessions` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB AUTO_INCREMENT=10 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `identity_verification_sessions`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `identity_verification_sessions` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `tenant_id` int(11) NOT NULL,
  `user_id` int(11) NOT NULL,
  `provider_slug` varchar(50) NOT NULL COMMENT 'Provider slug: mock, stripe_identity, veriff, etc.',
  `provider_session_id` varchar(255) DEFAULT NULL COMMENT 'External session ID from provider',
  `verification_level` enum('document_only','document_selfie','reusable_digital_id','manual_review') NOT NULL,
  `status` enum('created','started','processing','passed','failed','expired','cancelled') NOT NULL DEFAULT 'created',
  `redirect_url` text DEFAULT NULL COMMENT 'URL for hosted/redirect verification flows',
  `client_token` varchar(500) DEFAULT NULL COMMENT 'Token for embedded SDK flows',
  `result_summary` text DEFAULT NULL COMMENT 'Encrypted JSON: decision, risk_score, checks_passed',
  `provider_reference` varchar(255) DEFAULT NULL COMMENT 'Provider reference for the completed check',
  `failure_reason` varchar(500) DEFAULT NULL COMMENT 'Human-readable failure reason',
  `reminder_sent_at` datetime DEFAULT NULL,
  `metadata` text DEFAULT NULL COMMENT 'Encrypted JSON: provider-specific extra data',
  `stripe_payment_intent_id` varchar(255) DEFAULT NULL,
  `verification_fee_amount` int(11) DEFAULT NULL,
  `payment_status` enum('none','pending','completed','failed') NOT NULL DEFAULT 'none',
  `expires_at` datetime DEFAULT NULL,
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  `updated_at` datetime DEFAULT NULL ON UPDATE current_timestamp(),
  `completed_at` datetime DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `idx_ivs_tenant_user` (`tenant_id`,`user_id`),
  KEY `idx_ivs_provider_session` (`provider_slug`,`provider_session_id`),
  KEY `idx_ivs_status` (`status`),
  KEY `fk_ivs_user` (`user_id`),
  CONSTRAINT `fk_ivs_tenant` FOREIGN KEY (`tenant_id`) REFERENCES `tenants` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_ivs_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `insurance_certificates`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `insurance_certificates` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `tenant_id` int(11) NOT NULL,
  `user_id` int(11) NOT NULL,
  `insurance_type` enum('public_liability','professional_indemnity','employers_liability','product_liability','personal_accident','other') NOT NULL DEFAULT 'public_liability',
  `provider_name` varchar(255) DEFAULT NULL COMMENT 'Insurance company name',
  `policy_number` varchar(100) DEFAULT NULL,
  `coverage_amount` decimal(12,2) DEFAULT NULL COMMENT 'Coverage amount in local currency',
  `start_date` date DEFAULT NULL,
  `expiry_date` date DEFAULT NULL,
  `certificate_file_path` varchar(500) DEFAULT NULL COMMENT 'Uploaded certificate file',
  `status` enum('pending','submitted','verified','expired','rejected','revoked') NOT NULL DEFAULT 'pending',
  `verified_by` int(11) DEFAULT NULL,
  `verified_at` datetime DEFAULT NULL,
  `notes` text DEFAULT NULL COMMENT 'Internal broker notes',
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  `updated_at` datetime DEFAULT NULL ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `idx_ins_tenant` (`tenant_id`),
  KEY `idx_ins_user` (`user_id`),
  KEY `idx_ins_status` (`status`),
  KEY `idx_ins_expiry` (`expiry_date`),
  CONSTRAINT `insurance_certificates_ibfk_1` FOREIGN KEY (`tenant_id`) REFERENCES `tenants` (`id`) ON DELETE CASCADE,
  CONSTRAINT `insurance_certificates_ibfk_2` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=2 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `job_alert_subscriptions`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `job_alert_subscriptions` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `tenant_id` int(10) unsigned NOT NULL,
  `user_id` int(10) unsigned NOT NULL,
  `criteria` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`criteria`)),
  `frequency` varchar(20) NOT NULL DEFAULT 'weekly',
  `is_active` tinyint(1) NOT NULL DEFAULT 1,
  `last_sent_at` datetime DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `job_alert_subscriptions_tenant_id_user_id_index` (`tenant_id`,`user_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `job_alerts`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `job_alerts` (
  `id` int(11) unsigned NOT NULL AUTO_INCREMENT,
  `user_id` int(11) NOT NULL,
  `tenant_id` int(11) unsigned NOT NULL,
  `keywords` varchar(500) DEFAULT NULL,
  `categories` varchar(500) DEFAULT NULL,
  `type` varchar(30) DEFAULT NULL,
  `commitment` varchar(30) DEFAULT NULL,
  `location` varchar(255) DEFAULT NULL,
  `is_remote_only` tinyint(1) NOT NULL DEFAULT 0,
  `is_active` tinyint(1) NOT NULL DEFAULT 1,
  `last_notified_at` datetime DEFAULT NULL,
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  `updated_at` datetime DEFAULT NULL ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `idx_job_alerts_tenant_user` (`tenant_id`,`user_id`),
  KEY `idx_job_alerts_active` (`tenant_id`,`is_active`),
  KEY `idx_job_alerts_is_active` (`is_active`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `job_application_history`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `job_application_history` (
  `id` int(11) unsigned NOT NULL AUTO_INCREMENT,
  `application_id` int(11) unsigned NOT NULL,
  `from_status` varchar(30) DEFAULT NULL,
  `to_status` varchar(30) NOT NULL,
  `changed_by` int(11) DEFAULT NULL,
  `changed_at` datetime NOT NULL DEFAULT current_timestamp(),
  `notes` text DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `idx_app_history_application` (`application_id`),
  KEY `idx_app_history_changed_at` (`changed_at`),
  CONSTRAINT `fk_app_history_application` FOREIGN KEY (`application_id`) REFERENCES `job_vacancy_applications` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=3 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `job_applications`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `job_applications` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `tenant_id` int(10) unsigned NOT NULL,
  `vacancy_id` int(10) unsigned NOT NULL,
  `user_id` int(10) unsigned NOT NULL,
  `status` varchar(30) NOT NULL DEFAULT 'pending',
  `cover_letter` text DEFAULT NULL,
  `resume_path` varchar(500) DEFAULT NULL,
  `answers` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`answers`)),
  `notes` text DEFAULT NULL,
  `pipeline_stage` varchar(50) DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `job_applications_tenant_id_vacancy_id_index` (`tenant_id`,`vacancy_id`),
  KEY `job_applications_tenant_id_user_id_index` (`tenant_id`,`user_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `job_bias_audits`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `job_bias_audits` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `tenant_id` int(10) unsigned NOT NULL,
  `vacancy_id` int(10) unsigned DEFAULT NULL,
  `audit_type` varchar(50) NOT NULL DEFAULT 'posting',
  `findings` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`findings`)),
  `bias_score` decimal(5,2) DEFAULT NULL,
  `recommendations` text DEFAULT NULL,
  `audited_by` int(10) unsigned DEFAULT NULL,
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `job_bias_audits_tenant_id_vacancy_id_index` (`tenant_id`,`vacancy_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `job_expiry_notifications`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `job_expiry_notifications` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `tenant_id` int(10) unsigned NOT NULL,
  `vacancy_id` int(10) unsigned NOT NULL,
  `notification_type` varchar(50) NOT NULL,
  `sent_at` datetime DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `job_expiry_notifications_tenant_id_vacancy_id_index` (`tenant_id`,`vacancy_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `job_feeds`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `job_feeds` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `tenant_id` int(10) unsigned NOT NULL,
  `name` varchar(255) NOT NULL,
  `feed_type` varchar(50) NOT NULL DEFAULT 'rss',
  `feed_url` varchar(500) DEFAULT NULL,
  `config` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`config`)),
  `is_active` tinyint(1) NOT NULL DEFAULT 1,
  `last_synced_at` datetime DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `job_feeds_tenant_id_index` (`tenant_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `job_gdpr_consents`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `job_gdpr_consents` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `tenant_id` int(10) unsigned NOT NULL,
  `user_id` int(10) unsigned NOT NULL,
  `vacancy_id` int(10) unsigned DEFAULT NULL,
  `consent_type` varchar(50) NOT NULL,
  `consented` tinyint(1) NOT NULL DEFAULT 0,
  `consented_at` datetime DEFAULT NULL,
  `withdrawn_at` datetime DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `job_gdpr_consents_tenant_id_user_id_index` (`tenant_id`,`user_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `job_interview_scheduling`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `job_interview_scheduling` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `tenant_id` int(10) unsigned NOT NULL,
  `application_id` int(10) unsigned NOT NULL,
  `interviewer_id` int(10) unsigned NOT NULL,
  `interview_type` varchar(50) NOT NULL DEFAULT 'video',
  `scheduled_at` datetime DEFAULT NULL,
  `duration_minutes` int(11) NOT NULL DEFAULT 30,
  `location` varchar(500) DEFAULT NULL,
  `meeting_link` varchar(500) DEFAULT NULL,
  `status` varchar(30) NOT NULL DEFAULT 'scheduled',
  `notes` text DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `job_interview_scheduling_tenant_id_application_id_index` (`tenant_id`,`application_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `job_interview_slots`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `job_interview_slots` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `tenant_id` int(11) NOT NULL,
  `job_id` int(11) NOT NULL,
  `employer_user_id` int(11) NOT NULL,
  `slot_start` datetime NOT NULL,
  `slot_end` datetime NOT NULL,
  `is_booked` tinyint(1) DEFAULT 0,
  `booked_by_user_id` int(11) DEFAULT NULL,
  `booked_at` datetime DEFAULT NULL,
  `interview_type` enum('video','phone','in_person') DEFAULT 'video',
  `meeting_link` varchar(500) DEFAULT NULL,
  `location` varchar(500) DEFAULT NULL,
  `notes` text DEFAULT NULL,
  `created_at` datetime DEFAULT current_timestamp(),
  `updated_at` datetime DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `idx_tenant_job` (`tenant_id`,`job_id`),
  KEY `idx_available` (`tenant_id`,`job_id`,`is_booked`,`slot_start`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `job_interviews`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `job_interviews` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `tenant_id` int(10) unsigned NOT NULL,
  `vacancy_id` bigint(20) unsigned NOT NULL,
  `application_id` bigint(20) unsigned NOT NULL,
  `proposed_by` bigint(20) unsigned NOT NULL,
  `interview_type` varchar(30) NOT NULL DEFAULT 'video',
  `scheduled_at` datetime NOT NULL,
  `duration_mins` smallint(6) NOT NULL DEFAULT 60,
  `location_notes` text DEFAULT NULL,
  `status` varchar(30) NOT NULL DEFAULT 'proposed',
  `reminder_sent_at` timestamp NULL DEFAULT NULL,
  `candidate_notes` text DEFAULT NULL,
  `interviewer_notes` text DEFAULT NULL,
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  `updated_at` datetime NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `idx_ji_tenant_vacancy` (`tenant_id`,`vacancy_id`),
  KEY `idx_ji_application` (`application_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `job_moderation_logs`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `job_moderation_logs` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `tenant_id` int(10) unsigned NOT NULL,
  `vacancy_id` int(10) unsigned NOT NULL,
  `admin_id` int(10) unsigned NOT NULL,
  `action` varchar(50) NOT NULL,
  `previous_status` varchar(30) DEFAULT NULL,
  `new_status` varchar(30) DEFAULT NULL,
  `notes` text DEFAULT NULL,
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `job_moderation_logs_tenant_id_vacancy_id_index` (`tenant_id`,`vacancy_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `job_offers`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `job_offers` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `tenant_id` int(10) unsigned NOT NULL,
  `vacancy_id` bigint(20) unsigned NOT NULL,
  `application_id` bigint(20) unsigned NOT NULL,
  `salary_offered` decimal(12,2) DEFAULT NULL,
  `salary_currency` varchar(3) DEFAULT 'EUR',
  `salary_type` varchar(20) DEFAULT NULL,
  `start_date` date DEFAULT NULL,
  `message` text DEFAULT NULL,
  `status` varchar(30) NOT NULL DEFAULT 'pending',
  `responded_at` datetime DEFAULT NULL,
  `expires_at` datetime DEFAULT NULL,
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  `updated_at` datetime NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `application_id` (`application_id`),
  KEY `idx_jo_tenant_vacancy` (`tenant_id`,`vacancy_id`),
  KEY `idx_jo_application` (`application_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `job_pipeline_rules`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `job_pipeline_rules` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `tenant_id` int(10) unsigned NOT NULL,
  `vacancy_id` bigint(20) unsigned NOT NULL,
  `name` varchar(160) NOT NULL,
  `trigger_stage` varchar(50) NOT NULL COMMENT 'stage that triggers evaluation: applied, screening, etc.',
  `condition_days` smallint(5) unsigned NOT NULL DEFAULT 7 COMMENT 'days in stage before rule fires',
  `action` enum('move_stage','reject','notify_reviewer') NOT NULL DEFAULT 'move_stage',
  `action_target` varchar(50) DEFAULT NULL COMMENT 'target stage for move_stage action',
  `is_active` tinyint(1) NOT NULL DEFAULT 1,
  `last_run_at` datetime DEFAULT NULL,
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  `updated_at` datetime NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `idx_pipeline_vacancy` (`tenant_id`,`vacancy_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `job_referrals`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `job_referrals` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `tenant_id` int(10) unsigned NOT NULL,
  `vacancy_id` bigint(20) unsigned NOT NULL,
  `referrer_user_id` int(10) unsigned DEFAULT NULL COMMENT 'NULL = anonymous share',
  `referred_user_id` int(10) unsigned DEFAULT NULL COMMENT 'set when referred user applies',
  `ref_token` varchar(64) NOT NULL,
  `applied` tinyint(1) NOT NULL DEFAULT 0,
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  `applied_at` datetime DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_ref_token` (`ref_token`),
  KEY `idx_referrals_vacancy` (`tenant_id`,`vacancy_id`),
  KEY `idx_referrals_referrer` (`tenant_id`,`referrer_user_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `job_saved_profiles`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `job_saved_profiles` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `tenant_id` int(10) unsigned NOT NULL,
  `user_id` int(10) unsigned NOT NULL,
  `cv_path` varchar(500) NOT NULL,
  `cv_filename` varchar(255) NOT NULL,
  `cv_size` int(10) unsigned NOT NULL,
  `headline` varchar(160) DEFAULT NULL COMMENT 'saved cover letter headline',
  `cover_text` text DEFAULT NULL COMMENT 'saved cover letter body',
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  `updated_at` datetime NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_saved_profile` (`tenant_id`,`user_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `job_scorecards`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `job_scorecards` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `tenant_id` int(10) unsigned NOT NULL,
  `vacancy_id` bigint(20) unsigned NOT NULL,
  `application_id` bigint(20) unsigned NOT NULL,
  `reviewer_id` int(10) unsigned NOT NULL,
  `criteria` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin NOT NULL COMMENT 'array of {label, score, max_score} objects' CHECK (json_valid(`criteria`)),
  `total_score` decimal(5,2) NOT NULL DEFAULT 0.00,
  `max_score` decimal(5,2) NOT NULL DEFAULT 100.00,
  `notes` text DEFAULT NULL,
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  `updated_at` datetime NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_scorecard` (`application_id`,`reviewer_id`),
  KEY `idx_scorecard_vacancy` (`tenant_id`,`vacancy_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `job_spam_patterns`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `job_spam_patterns` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `tenant_id` int(10) unsigned NOT NULL,
  `pattern_type` varchar(50) NOT NULL,
  `pattern_value` text NOT NULL,
  `weight` int(11) NOT NULL DEFAULT 1,
  `is_active` tinyint(1) NOT NULL DEFAULT 1,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `job_spam_patterns_tenant_id_index` (`tenant_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `job_team_members`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `job_team_members` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `tenant_id` int(10) unsigned NOT NULL,
  `vacancy_id` int(10) unsigned NOT NULL,
  `user_id` int(10) unsigned NOT NULL,
  `role` varchar(50) NOT NULL DEFAULT 'viewer',
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `job_team_members_vacancy_id_user_id_unique` (`vacancy_id`,`user_id`),
  KEY `job_team_members_tenant_id_index` (`tenant_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `job_templates`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `job_templates` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `tenant_id` int(10) unsigned NOT NULL,
  `user_id` int(10) unsigned NOT NULL COMMENT 'creator',
  `template_type` varchar(20) NOT NULL DEFAULT 'job_posting',
  `name` varchar(160) NOT NULL,
  `description` text DEFAULT NULL,
  `type` enum('paid','volunteer','timebank') NOT NULL DEFAULT 'paid',
  `commitment` enum('full_time','part_time','flexible','one_off') NOT NULL DEFAULT 'flexible',
  `category` varchar(100) DEFAULT NULL,
  `skills_required` varchar(500) DEFAULT NULL,
  `is_remote` tinyint(1) NOT NULL DEFAULT 0,
  `salary_type` enum('hourly','monthly','annual') DEFAULT NULL,
  `salary_currency` varchar(10) DEFAULT 'EUR',
  `salary_min` decimal(10,2) DEFAULT NULL,
  `salary_max` decimal(10,2) DEFAULT NULL,
  `hours_per_week` decimal(5,2) DEFAULT NULL,
  `time_credits` decimal(8,2) DEFAULT NULL,
  `benefits` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`benefits`)),
  `tagline` varchar(160) DEFAULT NULL,
  `is_public` tinyint(1) NOT NULL DEFAULT 0 COMMENT 'shared with all tenant employers',
  `use_count` int(10) unsigned NOT NULL DEFAULT 0,
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  `updated_at` datetime NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `idx_templates_tenant` (`tenant_id`,`user_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `job_vacancies`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `job_vacancies` (
  `id` int(11) unsigned NOT NULL AUTO_INCREMENT,
  `tenant_id` int(11) unsigned NOT NULL,
  `user_id` int(11) NOT NULL,
  `organization_id` int(11) unsigned DEFAULT NULL,
  `title` varchar(255) NOT NULL,
  `description` text NOT NULL,
  `tagline` varchar(160) DEFAULT NULL,
  `video_url` varchar(500) DEFAULT NULL,
  `culture_photos` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`culture_photos`)),
  `company_size` varchar(50) DEFAULT NULL,
  `benefits` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`benefits`)),
  `location` varchar(255) DEFAULT NULL,
  `is_remote` tinyint(1) NOT NULL DEFAULT 0,
  `type` enum('paid','volunteer','timebank') NOT NULL DEFAULT 'paid',
  `commitment` enum('full_time','part_time','flexible','one_off') NOT NULL DEFAULT 'flexible',
  `category` varchar(100) DEFAULT NULL,
  `skills_required` text DEFAULT NULL,
  `hours_per_week` decimal(5,1) DEFAULT NULL,
  `time_credits` decimal(10,2) DEFAULT NULL,
  `contact_email` varchar(255) DEFAULT NULL,
  `contact_phone` varchar(50) DEFAULT NULL,
  `deadline` datetime DEFAULT NULL,
  `status` enum('open','closed','filled','draft') NOT NULL DEFAULT 'open',
  `views_count` int(11) NOT NULL DEFAULT 0,
  `applications_count` int(11) NOT NULL DEFAULT 0,
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  `updated_at` datetime DEFAULT NULL ON UPDATE current_timestamp(),
  `expired_at` datetime DEFAULT NULL,
  `renewed_at` datetime DEFAULT NULL,
  `renewal_count` int(11) NOT NULL DEFAULT 0,
  `salary_min` decimal(12,2) DEFAULT NULL,
  `salary_max` decimal(12,2) DEFAULT NULL,
  `salary_type` varchar(30) DEFAULT NULL,
  `salary_currency` varchar(10) DEFAULT NULL,
  `salary_negotiable` tinyint(1) NOT NULL DEFAULT 0,
  `is_featured` tinyint(1) NOT NULL DEFAULT 0,
  `featured_until` datetime DEFAULT NULL,
  `latitude` decimal(10,8) DEFAULT NULL,
  `longitude` decimal(11,8) DEFAULT NULL,
  `blind_hiring` tinyint(1) DEFAULT 0,
  `moderation_status` enum('pending_review','approved','rejected','flagged') DEFAULT NULL,
  `moderation_notes` text DEFAULT NULL,
  `moderated_by` int(11) DEFAULT NULL,
  `moderated_at` datetime DEFAULT NULL,
  `spam_score` int(11) DEFAULT NULL,
  `spam_flags` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`spam_flags`)),
  PRIMARY KEY (`id`),
  KEY `idx_job_vacancies_tenant_status` (`tenant_id`,`status`),
  KEY `idx_job_vacancies_tenant_user` (`tenant_id`,`user_id`),
  KEY `idx_job_vacancies_tenant_type` (`tenant_id`,`type`),
  KEY `idx_job_vacancies_featured` (`tenant_id`,`is_featured`,`featured_until`),
  KEY `idx_job_vacancies_deadline` (`tenant_id`,`deadline`,`status`),
  KEY `idx_jv_geo` (`latitude`,`longitude`),
  KEY `idx_moderation_status` (`tenant_id`,`moderation_status`),
  KEY `idx_moderated_at` (`tenant_id`,`moderated_at`)
) ENGINE=InnoDB AUTO_INCREMENT=90003 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `job_vacancy_applications`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `job_vacancy_applications` (
  `id` int(11) unsigned NOT NULL AUTO_INCREMENT,
  `tenant_id` int(10) unsigned DEFAULT NULL,
  `vacancy_id` int(11) unsigned NOT NULL,
  `user_id` int(11) NOT NULL,
  `message` text DEFAULT NULL,
  `cv_path` varchar(500) DEFAULT NULL,
  `cv_filename` varchar(255) DEFAULT NULL,
  `cv_size` int(11) DEFAULT NULL,
  `status` varchar(30) NOT NULL DEFAULT 'applied',
  `reviewer_notes` text DEFAULT NULL,
  `reviewed_by` int(11) DEFAULT NULL,
  `reviewed_at` datetime DEFAULT NULL,
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  `updated_at` datetime DEFAULT NULL ON UPDATE current_timestamp(),
  `stage` varchar(30) NOT NULL DEFAULT 'applied',
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_vacancy_user` (`vacancy_id`,`user_id`),
  KEY `idx_applications_vacancy` (`vacancy_id`),
  KEY `idx_applications_user` (`user_id`),
  KEY `idx_app_vacancy_status` (`vacancy_id`,`status`),
  KEY `idx_jva_tenant_vacancy` (`tenant_id`,`vacancy_id`),
  KEY `idx_jva_tenant_user` (`tenant_id`,`user_id`),
  KEY `idx_jva_tenant_status_created` (`tenant_id`,`status`,`created_at`),
  CONSTRAINT `fk_app_vacancy` FOREIGN KEY (`vacancy_id`) REFERENCES `job_vacancies` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=2 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `job_vacancy_team`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `job_vacancy_team` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `tenant_id` int(10) unsigned NOT NULL,
  `vacancy_id` bigint(20) unsigned NOT NULL,
  `user_id` int(10) unsigned NOT NULL,
  `role` enum('reviewer','manager') NOT NULL DEFAULT 'reviewer',
  `added_by` int(10) unsigned NOT NULL,
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_team_member` (`vacancy_id`,`user_id`),
  KEY `idx_team_vacancy` (`tenant_id`,`vacancy_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `job_vacancy_views`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `job_vacancy_views` (
  `id` int(11) unsigned NOT NULL AUTO_INCREMENT,
  `vacancy_id` int(11) unsigned NOT NULL,
  `user_id` int(11) DEFAULT NULL,
  `tenant_id` int(11) unsigned NOT NULL,
  `viewed_at` datetime NOT NULL DEFAULT current_timestamp(),
  `ip_hash` varchar(64) DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `idx_job_views_vacancy` (`vacancy_id`),
  KEY `idx_job_views_tenant` (`tenant_id`),
  KEY `idx_job_views_date` (`viewed_at`),
  CONSTRAINT `fk_job_views_vacancy` FOREIGN KEY (`vacancy_id`) REFERENCES `job_vacancies` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=34 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `knowledge_base_articles`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `knowledge_base_articles` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `tenant_id` int(10) unsigned NOT NULL,
  `title` varchar(255) NOT NULL,
  `slug` varchar(255) NOT NULL,
  `content` longtext DEFAULT NULL,
  `content_type` enum('plain','html','markdown') NOT NULL DEFAULT 'html',
  `category_id` int(10) unsigned DEFAULT NULL COMMENT 'FK to resource_categories',
  `parent_article_id` int(10) unsigned DEFAULT NULL COMMENT 'Parent article for nested structure',
  `sort_order` int(11) NOT NULL DEFAULT 0,
  `is_published` tinyint(1) NOT NULL DEFAULT 0,
  `views_count` int(10) unsigned NOT NULL DEFAULT 0,
  `helpful_yes` int(10) unsigned NOT NULL DEFAULT 0 COMMENT 'Was this helpful? Yes count',
  `helpful_no` int(10) unsigned NOT NULL DEFAULT 0 COMMENT 'Was this helpful? No count',
  `created_by` int(10) unsigned DEFAULT NULL,
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  `updated_at` datetime DEFAULT NULL ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `idx_kb_articles_tenant` (`tenant_id`),
  KEY `idx_kb_articles_slug` (`tenant_id`,`slug`),
  KEY `idx_kb_articles_category` (`category_id`),
  KEY `idx_kb_articles_parent` (`parent_article_id`),
  KEY `idx_kb_articles_published` (`tenant_id`,`is_published`,`sort_order`),
  KEY `idx_kb_articles_created_by` (`created_by`)
) ENGINE=InnoDB AUTO_INCREMENT=90002 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `knowledge_base_attachments`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `knowledge_base_attachments` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `article_id` int(10) unsigned NOT NULL,
  `tenant_id` int(10) unsigned NOT NULL,
  `file_name` varchar(255) NOT NULL,
  `file_path` varchar(500) NOT NULL,
  `file_url` varchar(500) NOT NULL,
  `mime_type` varchar(100) NOT NULL,
  `file_size` int(10) unsigned NOT NULL DEFAULT 0,
  `sort_order` int(11) NOT NULL DEFAULT 0,
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `idx_kb_attach_article_tenant` (`article_id`,`tenant_id`),
  KEY `idx_kb_attach_tenant` (`tenant_id`),
  CONSTRAINT `knowledge_base_attachments_article_id_foreign` FOREIGN KEY (`article_id`) REFERENCES `knowledge_base_articles` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `knowledge_base_feedback`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `knowledge_base_feedback` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `article_id` int(10) unsigned NOT NULL,
  `user_id` int(10) unsigned DEFAULT NULL COMMENT 'Null for anonymous feedback',
  `tenant_id` int(10) unsigned NOT NULL,
  `is_helpful` tinyint(1) NOT NULL,
  `comment` text DEFAULT NULL,
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `idx_kb_feedback_unique` (`article_id`,`user_id`),
  KEY `idx_kb_feedback_tenant` (`tenant_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `laravel_migration_registry`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `laravel_migration_registry` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `filename` varchar(255) NOT NULL COMMENT 'Legacy SQL migration filename',
  `applied_at` timestamp NOT NULL DEFAULT current_timestamp() COMMENT 'When this migration was applied',
  PRIMARY KEY (`id`),
  UNIQUE KEY `laravel_migration_registry_filename_unique` (`filename`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `laravel_migrations`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `laravel_migrations` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `migration` varchar(255) NOT NULL,
  `batch` int(11) NOT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=82 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `leaderboard_cache`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `leaderboard_cache` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `tenant_id` int(11) NOT NULL,
  `user_id` int(11) NOT NULL,
  `leaderboard_type` varchar(30) NOT NULL,
  `period` enum('all_time','monthly','weekly') NOT NULL,
  `score` decimal(10,2) DEFAULT 0.00,
  `rank_position` int(11) DEFAULT 0,
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `unique_entry` (`tenant_id`,`user_id`,`leaderboard_type`,`period`),
  KEY `idx_rank` (`tenant_id`,`leaderboard_type`,`period`,`rank_position`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb3 COLLATE=utf8mb3_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `leaderboard_seasons`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `leaderboard_seasons` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `tenant_id` int(11) NOT NULL,
  `name` varchar(100) NOT NULL,
  `season_type` enum('weekly','monthly','quarterly','yearly') DEFAULT 'monthly',
  `start_date` date NOT NULL,
  `end_date` date NOT NULL,
  `is_active` tinyint(1) DEFAULT 1,
  `is_finalized` tinyint(1) DEFAULT 0,
  `status` varchar(20) NOT NULL DEFAULT 'active',
  `rewards` text DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `idx_tenant_active` (`tenant_id`,`is_active`),
  KEY `idx_dates` (`start_date`,`end_date`),
  KEY `idx_tenant_status` (`tenant_id`,`status`,`start_date`,`end_date`)
) ENGINE=InnoDB AUTO_INCREMENT=3 DEFAULT CHARSET=utf8mb3 COLLATE=utf8mb3_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `legal_document_versions`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `legal_document_versions` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `document_id` int(10) unsigned NOT NULL,
  `version_number` varchar(20) NOT NULL,
  `version_label` varchar(100) DEFAULT NULL,
  `content` longtext NOT NULL,
  `content_plain` longtext DEFAULT NULL,
  `summary_of_changes` text DEFAULT NULL,
  `effective_date` date NOT NULL,
  `published_at` timestamp NULL DEFAULT NULL,
  `is_draft` tinyint(1) NOT NULL DEFAULT 1,
  `is_current` tinyint(1) NOT NULL DEFAULT 0,
  `notification_sent` tinyint(1) NOT NULL DEFAULT 0,
  `notification_sent_at` timestamp NULL DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  `created_by` int(10) unsigned NOT NULL,
  `published_by` int(10) unsigned DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `unique_document_version` (`document_id`,`version_number`),
  KEY `idx_document_id` (`document_id`),
  KEY `idx_is_current` (`is_current`),
  KEY `idx_is_draft` (`is_draft`),
  CONSTRAINT `fk_legal_version_document` FOREIGN KEY (`document_id`) REFERENCES `legal_documents` (`id`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=5 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `legal_documents`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `legal_documents` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `tenant_id` int(10) unsigned NOT NULL DEFAULT 1,
  `document_type` enum('terms','privacy','cookies','accessibility','community_guidelines','acceptable_use') NOT NULL,
  `title` varchar(255) NOT NULL,
  `slug` varchar(100) NOT NULL,
  `current_version_id` int(10) unsigned DEFAULT NULL,
  `requires_acceptance` tinyint(1) NOT NULL DEFAULT 1,
  `acceptance_required_for` enum('registration','login','first_use','none') DEFAULT 'registration',
  `notify_on_update` tinyint(1) NOT NULL DEFAULT 1,
  `is_active` tinyint(1) NOT NULL DEFAULT 1,
  `created_at` timestamp NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  `created_by` int(10) unsigned DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `unique_tenant_document` (`tenant_id`,`document_type`),
  KEY `idx_tenant_id` (`tenant_id`),
  KEY `idx_document_type` (`document_type`),
  KEY `idx_slug` (`slug`),
  KEY `idx_is_active` (`is_active`)
) ENGINE=InnoDB AUTO_INCREMENT=10 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `likes`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `likes` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `user_id` int(11) NOT NULL,
  `target_type` varchar(50) NOT NULL,
  `target_id` int(11) NOT NULL,
  `tenant_id` int(11) NOT NULL DEFAULT 1,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `event` varchar(255) DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uk_likes_user_target` (`user_id`,`tenant_id`,`target_type`,`target_id`),
  KEY `user_id` (`user_id`),
  KEY `target` (`target_type`,`target_id`),
  KEY `tenant_id` (`tenant_id`),
  KEY `idx_likes_user_tenant_target` (`user_id`,`tenant_id`,`target_type`,`target_id`),
  KEY `idx_likes_tenant_target` (`tenant_id`,`target_type`,`target_id`),
  CONSTRAINT `likes_tenant_id_foreign` FOREIGN KEY (`tenant_id`) REFERENCES `tenants` (`id`) ON DELETE CASCADE,
  CONSTRAINT `likes_user_id_foreign` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=238 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `link_previews`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `link_previews` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `url_hash` varchar(64) NOT NULL,
  `url` text NOT NULL,
  `title` varchar(500) DEFAULT NULL,
  `description` text DEFAULT NULL,
  `image_url` text DEFAULT NULL,
  `site_name` varchar(255) DEFAULT NULL,
  `favicon_url` text DEFAULT NULL,
  `domain` varchar(255) NOT NULL,
  `content_type` varchar(50) DEFAULT 'website',
  `embed_html` text DEFAULT NULL,
  `fetched_at` timestamp NULL DEFAULT current_timestamp(),
  `expires_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uk_url_hash` (`url_hash`),
  KEY `idx_domain` (`domain`),
  KEY `idx_expires` (`expires_at`)
) ENGINE=InnoDB AUTO_INCREMENT=2 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `listing_attributes`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `listing_attributes` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `listing_id` int(11) NOT NULL,
  `attribute_id` int(11) NOT NULL,
  `value` text DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `distance_km` varchar(255) DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `idx_listing` (`listing_id`),
  KEY `idx_attribute` (`attribute_id`),
  CONSTRAINT `fk_la_attribute` FOREIGN KEY (`attribute_id`) REFERENCES `attributes` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_la_listing` FOREIGN KEY (`listing_id`) REFERENCES `listings` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=48 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `listing_contacts`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `listing_contacts` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `tenant_id` int(10) unsigned NOT NULL,
  `listing_id` int(11) NOT NULL,
  `user_id` int(10) unsigned NOT NULL COMMENT 'User who contacted the listing owner',
  `contact_type` enum('message','phone','email','exchange_request') NOT NULL DEFAULT 'message',
  `contacted_at` datetime NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `idx_listing_contacts_tenant` (`tenant_id`),
  KEY `idx_listing_contacts_listing` (`listing_id`),
  KEY `idx_listing_contacts_date` (`contacted_at`),
  KEY `idx_listing_contacts_lookup` (`tenant_id`,`listing_id`),
  CONSTRAINT `fk_listing_contacts_listing_id` FOREIGN KEY (`listing_id`) REFERENCES `listings` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `listing_expiry_reminders_sent`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `listing_expiry_reminders_sent` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `tenant_id` int(10) unsigned NOT NULL,
  `listing_id` int(10) unsigned NOT NULL,
  `user_id` int(10) unsigned NOT NULL,
  `days_before_expiry` int(11) NOT NULL DEFAULT 3,
  `sent_at` datetime NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_listing_expiry_reminder` (`tenant_id`,`listing_id`,`user_id`,`days_before_expiry`),
  KEY `idx_listing_expiry_lookup` (`tenant_id`,`listing_id`,`user_id`),
  KEY `idx_listing_expiry_cleanup` (`sent_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `listing_favorites`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `listing_favorites` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `user_id` int(10) unsigned NOT NULL,
  `listing_id` int(10) unsigned NOT NULL,
  `tenant_id` int(10) unsigned NOT NULL,
  `created_at` timestamp NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `uk_user_listing` (`user_id`,`listing_id`),
  KEY `idx_listing` (`listing_id`),
  KEY `idx_tenant` (`tenant_id`),
  KEY `idx_user` (`user_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `listing_images`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `listing_images` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `tenant_id` bigint(20) unsigned NOT NULL,
  `listing_id` bigint(20) unsigned NOT NULL,
  `image_url` varchar(255) NOT NULL,
  `sort_order` smallint(5) unsigned NOT NULL DEFAULT 0,
  `alt_text` varchar(255) DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `listing_reports`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `listing_reports` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `tenant_id` bigint(20) unsigned NOT NULL,
  `listing_id` bigint(20) unsigned NOT NULL,
  `reporter_id` bigint(20) unsigned NOT NULL,
  `reason` enum('inappropriate','safety_concern','misleading','spam','not_timebank_service','other') NOT NULL,
  `details` text DEFAULT NULL,
  `status` enum('pending','reviewed','dismissed','action_taken') NOT NULL DEFAULT 'pending',
  `admin_notes` text DEFAULT NULL,
  `reviewed_by` bigint(20) unsigned DEFAULT NULL,
  `reviewed_at` timestamp NULL DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `listing_risk_tags`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `listing_risk_tags` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `tenant_id` int(11) NOT NULL,
  `listing_id` int(11) NOT NULL,
  `risk_level` enum('low','medium','high','critical') NOT NULL DEFAULT 'low',
  `risk_category` varchar(100) DEFAULT NULL COMMENT 'e.g., safeguarding, insurance, mobility, heights',
  `risk_notes` text DEFAULT NULL COMMENT 'Internal broker notes',
  `member_visible_notes` text DEFAULT NULL COMMENT 'Notes visible to listing owner',
  `requires_approval` tinyint(1) DEFAULT 0 COMMENT 'Requires broker pre-approval before match',
  `insurance_required` tinyint(1) DEFAULT 0,
  `dbs_required` tinyint(1) DEFAULT 0 COMMENT 'DBS/background check required',
  `tagged_by` int(11) DEFAULT NULL COMMENT 'Broker user ID who tagged this',
  `created_at` timestamp NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `unique_listing_tag` (`listing_id`),
  KEY `idx_tenant_listing` (`tenant_id`,`listing_id`),
  KEY `idx_risk_level` (`tenant_id`,`risk_level`),
  KEY `idx_requires_approval` (`tenant_id`,`requires_approval`),
  KEY `tagged_by` (`tagged_by`),
  CONSTRAINT `listing_risk_tags_ibfk_1` FOREIGN KEY (`tenant_id`) REFERENCES `tenants` (`id`) ON DELETE CASCADE,
  CONSTRAINT `listing_risk_tags_ibfk_2` FOREIGN KEY (`listing_id`) REFERENCES `listings` (`id`) ON DELETE CASCADE,
  CONSTRAINT `listing_risk_tags_ibfk_3` FOREIGN KEY (`tagged_by`) REFERENCES `users` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB AUTO_INCREMENT=2 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `listing_skill_tags`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `listing_skill_tags` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `tenant_id` int(10) unsigned NOT NULL,
  `listing_id` int(10) unsigned NOT NULL,
  `tag` varchar(100) NOT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_listing_skill_tag` (`listing_id`,`tag`),
  KEY `idx_skill_tags_tenant` (`tenant_id`),
  KEY `idx_skill_tags_listing` (`listing_id`),
  KEY `idx_skill_tags_tag` (`tag`),
  KEY `idx_skill_tags_lookup` (`tenant_id`,`tag`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `listing_views`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `listing_views` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `tenant_id` int(10) unsigned NOT NULL,
  `listing_id` int(11) NOT NULL,
  `user_id` int(10) unsigned DEFAULT NULL COMMENT 'NULL for anonymous views',
  `ip_hash` varchar(64) DEFAULT NULL COMMENT 'Hashed IP for anonymous dedup',
  `viewed_at` datetime NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `idx_listing_views_tenant` (`tenant_id`),
  KEY `idx_listing_views_listing` (`listing_id`),
  KEY `idx_listing_views_date` (`viewed_at`),
  KEY `idx_listing_views_lookup` (`tenant_id`,`listing_id`,`user_id`),
  CONSTRAINT `fk_listing_views_listing_id` FOREIGN KEY (`listing_id`) REFERENCES `listings` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=152 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `listings`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `listings` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `tenant_id` int(11) NOT NULL,
  `user_id` int(11) NOT NULL,
  `category_id` int(11) DEFAULT NULL,
  `title` varchar(255) NOT NULL,
  `description` text DEFAULT NULL,
  `location` varchar(255) DEFAULT NULL,
  `latitude` decimal(10,8) DEFAULT NULL,
  `longitude` decimal(11,8) DEFAULT NULL,
  `type` varchar(50) NOT NULL,
  `status` enum('active','inactive','paused','completed','expired','closed','pending','rejected','deleted') DEFAULT 'active',
  `created_at` datetime DEFAULT current_timestamp(),
  `image_url` varchar(255) DEFAULT NULL,
  `sdg_goals` longtext DEFAULT NULL CHECK (json_valid(`sdg_goals`)),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  `expires_at` datetime DEFAULT NULL,
  `price` decimal(10,2) DEFAULT 0.00,
  `category` timestamp NULL DEFAULT NULL,
  `deleted_at` timestamp NULL DEFAULT NULL,
  `distance` varchar(255) DEFAULT NULL,
  `cached_distance_km` decimal(10,2) DEFAULT NULL,
  `match` timestamp NULL DEFAULT NULL,
  `subcategory_id` int(11) DEFAULT NULL,
  `federated_visibility` enum('none','listed','bookable') NOT NULL DEFAULT 'none',
  `service_type` enum('physical_only','remote_only','hybrid','location_dependent') NOT NULL DEFAULT 'physical_only',
  `availability` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`availability`)),
  `direct_messaging_disabled` tinyint(1) DEFAULT 0 COMMENT 'Disable direct contact for this listing, require exchange request',
  `exchange_workflow_required` tinyint(1) DEFAULT 0 COMMENT 'This listing requires formal exchange workflow',
  `hours_estimate` decimal(5,2) DEFAULT NULL,
  `renewed_at` datetime DEFAULT NULL,
  `renewal_count` int(10) unsigned NOT NULL DEFAULT 0,
  `view_count` int(10) unsigned NOT NULL DEFAULT 0,
  `contact_count` int(10) unsigned NOT NULL DEFAULT 0,
  `save_count` int(10) unsigned NOT NULL DEFAULT 0,
  `is_featured` tinyint(1) NOT NULL DEFAULT 0,
  `featured_until` datetime DEFAULT NULL,
  `moderation_status` enum('pending_review','approved','rejected') DEFAULT NULL,
  `reviewed_by` int(10) unsigned DEFAULT NULL,
  `reviewed_at` datetime DEFAULT NULL,
  `rejection_reason` text DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `tenant_id` (`tenant_id`),
  KEY `user_id` (`user_id`),
  KEY `idx_listings_search` (`title`,`description`(100)),
  KEY `idx_listings_type` (`type`),
  KEY `idx_listings_category` (`category_id`),
  KEY `idx_listings_user_status` (`user_id`,`status`),
  KEY `idx_listing_coords` (`latitude`,`longitude`),
  KEY `idx_listings_messaging_disabled` (`tenant_id`,`direct_messaging_disabled`),
  KEY `idx_listings_expires_at` (`expires_at`),
  KEY `idx_listings_featured` (`is_featured`,`featured_until`),
  KEY `idx_listings_moderation` (`tenant_id`,`moderation_status`),
  KEY `idx_listings_tenant_status` (`tenant_id`,`status`),
  KEY `idx_listings_tenant_status_id` (`tenant_id`,`status`,`id`),
  FULLTEXT KEY `ft_listings_search` (`title`,`description`),
  CONSTRAINT `listings_ibfk_1` FOREIGN KEY (`tenant_id`) REFERENCES `tenants` (`id`) ON DELETE CASCADE,
  CONSTRAINT `listings_ibfk_2` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE,
  CONSTRAINT `listings_ibfk_3` FOREIGN KEY (`category_id`) REFERENCES `categories` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB AUTO_INCREMENT=90004 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `login_attempts`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `login_attempts` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `identifier` varchar(255) NOT NULL,
  `type` varchar(50) NOT NULL DEFAULT 'email',
  `ip_address` varchar(45) NOT NULL,
  `success` tinyint(1) NOT NULL DEFAULT 0,
  `attempted_at` datetime NOT NULL,
  PRIMARY KEY (`id`),
  KEY `idx_identifier_type` (`identifier`,`type`),
  KEY `idx_attempted_at` (`attempted_at`)
) ENGINE=InnoDB AUTO_INCREMENT=35386 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `marketplace_categories`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `marketplace_categories` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `tenant_id` bigint(20) unsigned DEFAULT NULL,
  `name` varchar(100) NOT NULL,
  `slug` varchar(100) NOT NULL,
  `description` text DEFAULT NULL,
  `icon` varchar(50) DEFAULT NULL,
  `parent_id` bigint(20) unsigned DEFAULT NULL,
  `sort_order` int(11) NOT NULL DEFAULT 0,
  `is_active` tinyint(1) NOT NULL DEFAULT 1,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `mpc_tenant_slug_unique` (`tenant_id`,`slug`),
  KEY `marketplace_categories_parent_id_foreign` (`parent_id`),
  KEY `marketplace_categories_tenant_id_index` (`tenant_id`),
  CONSTRAINT `marketplace_categories_parent_id_foreign` FOREIGN KEY (`parent_id`) REFERENCES `marketplace_categories` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB AUTO_INCREMENT=19 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `marketplace_category_templates`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `marketplace_category_templates` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `tenant_id` bigint(20) unsigned DEFAULT NULL,
  `category_id` bigint(20) unsigned DEFAULT NULL,
  `name` varchar(100) NOT NULL,
  `fields` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin NOT NULL CHECK (json_valid(`fields`)),
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `marketplace_category_templates_category_id_foreign` (`category_id`),
  KEY `marketplace_category_templates_tenant_id_index` (`tenant_id`),
  CONSTRAINT `marketplace_category_templates_category_id_foreign` FOREIGN KEY (`category_id`) REFERENCES `marketplace_categories` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `marketplace_collection_items`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `marketplace_collection_items` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `tenant_id` bigint(20) unsigned NOT NULL,
  `collection_id` bigint(20) unsigned NOT NULL,
  `marketplace_listing_id` bigint(20) unsigned NOT NULL,
  `note` text DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `mci_collection_listing_unique` (`collection_id`,`marketplace_listing_id`),
  KEY `marketplace_collection_items_marketplace_listing_id_foreign` (`marketplace_listing_id`),
  KEY `marketplace_collection_items_tenant_id_index` (`tenant_id`),
  CONSTRAINT `marketplace_collection_items_collection_id_foreign` FOREIGN KEY (`collection_id`) REFERENCES `marketplace_collections` (`id`) ON DELETE CASCADE,
  CONSTRAINT `marketplace_collection_items_marketplace_listing_id_foreign` FOREIGN KEY (`marketplace_listing_id`) REFERENCES `marketplace_listings` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `marketplace_collections`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `marketplace_collections` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `tenant_id` bigint(20) unsigned NOT NULL,
  `user_id` bigint(20) unsigned NOT NULL,
  `name` varchar(100) NOT NULL,
  `description` text DEFAULT NULL,
  `is_public` tinyint(1) NOT NULL DEFAULT 0,
  `item_count` int(10) unsigned NOT NULL DEFAULT 0,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `mc_user_id_idx` (`user_id`),
  KEY `marketplace_collections_tenant_id_user_id_index` (`tenant_id`,`user_id`),
  KEY `marketplace_collections_tenant_id_index` (`tenant_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `marketplace_delivery_offers`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `marketplace_delivery_offers` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `tenant_id` bigint(20) unsigned NOT NULL DEFAULT 1,
  `order_id` bigint(20) unsigned NOT NULL,
  `deliverer_id` int(10) unsigned NOT NULL,
  `time_credits` decimal(8,2) NOT NULL COMMENT 'Time credits offered for delivery',
  `estimated_minutes` smallint(5) unsigned DEFAULT NULL COMMENT 'Estimated delivery time in minutes',
  `notes` text DEFAULT NULL COMMENT 'Deliverer notes about the delivery',
  `status` enum('pending','accepted','declined','completed','cancelled') NOT NULL DEFAULT 'pending',
  `accepted_at` timestamp NULL DEFAULT NULL,
  `completed_at` timestamp NULL DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `idx_mdo_order_status` (`order_id`,`status`),
  KEY `idx_mdo_deliverer_status` (`deliverer_id`,`status`),
  KEY `idx_mdo_tenant` (`tenant_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `marketplace_disputes`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `marketplace_disputes` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `tenant_id` bigint(20) unsigned NOT NULL,
  `order_id` bigint(20) unsigned NOT NULL,
  `opened_by` bigint(20) unsigned NOT NULL,
  `reason` enum('not_received','not_as_described','damaged','wrong_item','other') NOT NULL,
  `description` text NOT NULL,
  `evidence_urls` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`evidence_urls`)),
  `status` enum('open','under_review','resolved_buyer','resolved_seller','escalated','closed') NOT NULL DEFAULT 'open',
  `resolution_notes` text DEFAULT NULL,
  `resolved_by` bigint(20) unsigned DEFAULT NULL,
  `resolved_at` timestamp NULL DEFAULT NULL,
  `refund_amount` decimal(10,2) DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `md_tenant_status_idx` (`tenant_id`,`status`),
  KEY `marketplace_disputes_order_id_foreign` (`order_id`),
  KEY `md_opened_by_idx` (`opened_by`),
  KEY `md_resolved_by_idx` (`resolved_by`),
  KEY `marketplace_disputes_tenant_id_index` (`tenant_id`),
  CONSTRAINT `marketplace_disputes_order_id_foreign` FOREIGN KEY (`order_id`) REFERENCES `marketplace_orders` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `marketplace_escrow`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `marketplace_escrow` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `tenant_id` bigint(20) unsigned NOT NULL,
  `order_id` bigint(20) unsigned NOT NULL,
  `payment_id` bigint(20) unsigned NOT NULL,
  `amount` decimal(10,2) NOT NULL,
  `currency` varchar(3) NOT NULL DEFAULT 'EUR',
  `status` enum('held','released','refunded','disputed') NOT NULL DEFAULT 'held',
  `held_at` timestamp NULL DEFAULT NULL,
  `release_after` timestamp NULL DEFAULT NULL,
  `released_at` timestamp NULL DEFAULT NULL,
  `release_trigger` enum('buyer_confirmed','auto_timeout','admin_override','dispute_resolved') DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `marketplace_escrow_order_id_foreign` (`order_id`),
  KEY `marketplace_escrow_payment_id_foreign` (`payment_id`),
  KEY `marketplace_escrow_tenant_id_status_index` (`tenant_id`,`status`),
  KEY `marketplace_escrow_tenant_id_index` (`tenant_id`),
  CONSTRAINT `marketplace_escrow_order_id_foreign` FOREIGN KEY (`order_id`) REFERENCES `marketplace_orders` (`id`) ON DELETE CASCADE,
  CONSTRAINT `marketplace_escrow_payment_id_foreign` FOREIGN KEY (`payment_id`) REFERENCES `marketplace_payments` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `marketplace_images`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `marketplace_images` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `tenant_id` bigint(20) unsigned NOT NULL,
  `marketplace_listing_id` bigint(20) unsigned NOT NULL,
  `image_url` varchar(500) NOT NULL,
  `thumbnail_url` varchar(500) DEFAULT NULL,
  `alt_text` varchar(255) DEFAULT NULL,
  `sort_order` int(11) NOT NULL DEFAULT 0,
  `is_primary` tinyint(1) NOT NULL DEFAULT 0,
  `created_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `marketplace_images_marketplace_listing_id_foreign` (`marketplace_listing_id`),
  KEY `marketplace_images_tenant_id_index` (`tenant_id`),
  CONSTRAINT `marketplace_images_marketplace_listing_id_foreign` FOREIGN KEY (`marketplace_listing_id`) REFERENCES `marketplace_listings` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=5 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `marketplace_listings`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `marketplace_listings` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `tenant_id` bigint(20) unsigned NOT NULL,
  `user_id` bigint(20) unsigned NOT NULL,
  `title` varchar(200) NOT NULL,
  `description` text NOT NULL,
  `tagline` varchar(300) DEFAULT NULL,
  `price` decimal(10,2) DEFAULT NULL,
  `price_currency` varchar(3) NOT NULL DEFAULT 'EUR',
  `price_type` enum('fixed','negotiable','free','auction','contact') NOT NULL DEFAULT 'fixed',
  `time_credit_price` decimal(8,2) DEFAULT NULL,
  `category_id` bigint(20) unsigned DEFAULT NULL,
  `condition` enum('new','like_new','good','fair','poor') DEFAULT NULL,
  `quantity` int(11) NOT NULL DEFAULT 1,
  `location` varchar(255) DEFAULT NULL,
  `latitude` decimal(10,8) DEFAULT NULL,
  `longitude` decimal(11,8) DEFAULT NULL,
  `shipping_available` tinyint(1) NOT NULL DEFAULT 0,
  `local_pickup` tinyint(1) NOT NULL DEFAULT 1,
  `delivery_method` enum('pickup','shipping','both','community_delivery') NOT NULL DEFAULT 'pickup',
  `seller_type` enum('private','business') NOT NULL DEFAULT 'private',
  `status` enum('draft','active','sold','reserved','expired','removed') NOT NULL DEFAULT 'draft',
  `moderation_status` enum('pending','approved','rejected','flagged') NOT NULL DEFAULT 'pending',
  `moderation_notes` text DEFAULT NULL,
  `moderated_by` bigint(20) unsigned DEFAULT NULL,
  `moderated_at` timestamp NULL DEFAULT NULL,
  `views_count` int(11) NOT NULL DEFAULT 0,
  `saves_count` int(11) NOT NULL DEFAULT 0,
  `contacts_count` int(11) NOT NULL DEFAULT 0,
  `promoted_until` timestamp NULL DEFAULT NULL,
  `promotion_type` varchar(30) DEFAULT NULL,
  `expires_at` timestamp NULL DEFAULT NULL,
  `renewed_at` timestamp NULL DEFAULT NULL,
  `renewal_count` int(11) NOT NULL DEFAULT 0,
  `template_data` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`template_data`)),
  `video_url` varchar(500) DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `mpl_tenant_status_idx` (`tenant_id`,`status`),
  KEY `mpl_tenant_category_idx` (`tenant_id`,`category_id`),
  KEY `mpl_tenant_user_idx` (`tenant_id`,`user_id`),
  KEY `mpl_tenant_geo_idx` (`tenant_id`,`latitude`,`longitude`),
  KEY `marketplace_listings_category_id_foreign` (`category_id`),
  FULLTEXT KEY `mpl_title_description_ft` (`title`,`description`),
  CONSTRAINT `marketplace_listings_category_id_foreign` FOREIGN KEY (`category_id`) REFERENCES `marketplace_categories` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB AUTO_INCREMENT=9 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `marketplace_offers`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `marketplace_offers` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `tenant_id` bigint(20) unsigned NOT NULL,
  `marketplace_listing_id` bigint(20) unsigned NOT NULL,
  `buyer_id` bigint(20) unsigned NOT NULL,
  `seller_id` bigint(20) unsigned NOT NULL,
  `amount` decimal(10,2) NOT NULL,
  `currency` varchar(3) NOT NULL DEFAULT 'EUR',
  `message` text DEFAULT NULL,
  `status` enum('pending','accepted','declined','countered','expired','withdrawn') NOT NULL DEFAULT 'pending',
  `counter_amount` decimal(10,2) DEFAULT NULL,
  `counter_message` text DEFAULT NULL,
  `expires_at` timestamp NULL DEFAULT NULL,
  `accepted_at` timestamp NULL DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `mpo_tenant_listing_buyer_idx` (`tenant_id`,`marketplace_listing_id`,`buyer_id`),
  KEY `mpo_tenant_status_idx` (`tenant_id`,`status`),
  KEY `marketplace_offers_marketplace_listing_id_foreign` (`marketplace_listing_id`),
  KEY `mpo_buyer_id_idx` (`buyer_id`),
  KEY `mpo_seller_id_idx` (`seller_id`),
  KEY `marketplace_offers_tenant_id_index` (`tenant_id`),
  CONSTRAINT `marketplace_offers_marketplace_listing_id_foreign` FOREIGN KEY (`marketplace_listing_id`) REFERENCES `marketplace_listings` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `marketplace_orders`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `marketplace_orders` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `tenant_id` bigint(20) unsigned NOT NULL,
  `order_number` varchar(50) NOT NULL,
  `buyer_id` bigint(20) unsigned NOT NULL,
  `seller_id` bigint(20) unsigned NOT NULL,
  `marketplace_listing_id` bigint(20) unsigned NOT NULL,
  `marketplace_offer_id` bigint(20) unsigned DEFAULT NULL,
  `quantity` int(11) NOT NULL DEFAULT 1,
  `unit_price` decimal(10,2) NOT NULL,
  `total_price` decimal(10,2) NOT NULL,
  `currency` varchar(3) NOT NULL DEFAULT 'EUR',
  `time_credits_used` decimal(8,2) DEFAULT NULL,
  `status` enum('pending_payment','paid','shipped','delivered','completed','disputed','refunded','cancelled') NOT NULL DEFAULT 'pending_payment',
  `payment_intent_id` varchar(255) DEFAULT NULL,
  `escrow_released_at` timestamp NULL DEFAULT NULL,
  `shipping_method` varchar(100) DEFAULT NULL,
  `shipping_cost` decimal(8,2) DEFAULT NULL,
  `tracking_number` varchar(255) DEFAULT NULL,
  `tracking_url` varchar(500) DEFAULT NULL,
  `delivery_address` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`delivery_address`)),
  `delivery_notes` text DEFAULT NULL,
  `buyer_confirmed_at` timestamp NULL DEFAULT NULL,
  `seller_confirmed_at` timestamp NULL DEFAULT NULL,
  `auto_complete_at` timestamp NULL DEFAULT NULL,
  `cancelled_at` timestamp NULL DEFAULT NULL,
  `cancellation_reason` text DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `mo_tenant_order_number_unique` (`tenant_id`,`order_number`),
  KEY `mo_tenant_buyer_idx` (`tenant_id`,`buyer_id`),
  KEY `mo_tenant_seller_idx` (`tenant_id`,`seller_id`),
  KEY `mo_tenant_status_idx` (`tenant_id`,`status`),
  KEY `marketplace_orders_marketplace_listing_id_foreign` (`marketplace_listing_id`),
  KEY `marketplace_orders_marketplace_offer_id_foreign` (`marketplace_offer_id`),
  KEY `mo_buyer_id_idx` (`buyer_id`),
  KEY `mo_seller_id_idx` (`seller_id`),
  KEY `marketplace_orders_tenant_id_index` (`tenant_id`),
  CONSTRAINT `marketplace_orders_marketplace_listing_id_foreign` FOREIGN KEY (`marketplace_listing_id`) REFERENCES `marketplace_listings` (`id`) ON DELETE CASCADE,
  CONSTRAINT `marketplace_orders_marketplace_offer_id_foreign` FOREIGN KEY (`marketplace_offer_id`) REFERENCES `marketplace_offers` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB AUTO_INCREMENT=3 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `marketplace_payments`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `marketplace_payments` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `tenant_id` bigint(20) unsigned NOT NULL,
  `order_id` bigint(20) unsigned NOT NULL,
  `stripe_payment_intent_id` varchar(255) DEFAULT NULL,
  `stripe_charge_id` varchar(255) DEFAULT NULL,
  `amount` decimal(10,2) NOT NULL,
  `currency` varchar(3) NOT NULL DEFAULT 'EUR',
  `platform_fee` decimal(10,2) NOT NULL DEFAULT 0.00,
  `seller_payout` decimal(10,2) NOT NULL DEFAULT 0.00,
  `payment_method` varchar(50) DEFAULT NULL,
  `status` enum('pending','succeeded','failed','refunded','partially_refunded') NOT NULL DEFAULT 'pending',
  `refund_amount` decimal(10,2) DEFAULT NULL,
  `refund_reason` text DEFAULT NULL,
  `refunded_at` timestamp NULL DEFAULT NULL,
  `payout_status` enum('pending','scheduled','paid','failed') NOT NULL DEFAULT 'pending',
  `payout_id` varchar(255) DEFAULT NULL,
  `paid_out_at` timestamp NULL DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `marketplace_payments_order_id_foreign` (`order_id`),
  KEY `marketplace_payments_tenant_id_order_id_index` (`tenant_id`,`order_id`),
  KEY `marketplace_payments_tenant_id_status_index` (`tenant_id`,`status`),
  KEY `marketplace_payments_tenant_id_index` (`tenant_id`),
  KEY `marketplace_payments_stripe_payment_intent_id_index` (`stripe_payment_intent_id`),
  CONSTRAINT `marketplace_payments_order_id_foreign` FOREIGN KEY (`order_id`) REFERENCES `marketplace_orders` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `marketplace_promotions`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `marketplace_promotions` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `tenant_id` bigint(20) unsigned NOT NULL,
  `marketplace_listing_id` bigint(20) unsigned NOT NULL,
  `user_id` bigint(20) unsigned NOT NULL,
  `promotion_type` enum('bump','featured','top_of_category','homepage_carousel') NOT NULL,
  `stripe_payment_intent_id` varchar(255) DEFAULT NULL,
  `amount_paid` decimal(10,2) NOT NULL DEFAULT 0.00,
  `currency` varchar(3) NOT NULL DEFAULT 'EUR',
  `started_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `expires_at` timestamp NOT NULL,
  `is_active` tinyint(1) NOT NULL DEFAULT 1,
  `impressions` int(10) unsigned NOT NULL DEFAULT 0,
  `clicks` int(10) unsigned NOT NULL DEFAULT 0,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `marketplace_promotions_marketplace_listing_id_foreign` (`marketplace_listing_id`),
  KEY `mpr_user_id_idx` (`user_id`),
  KEY `mp_listing_active_idx` (`tenant_id`,`marketplace_listing_id`,`is_active`),
  KEY `mp_user_idx` (`tenant_id`,`user_id`),
  KEY `mp_active_expires_idx` (`is_active`,`expires_at`),
  KEY `marketplace_promotions_tenant_id_index` (`tenant_id`),
  CONSTRAINT `marketplace_promotions_marketplace_listing_id_foreign` FOREIGN KEY (`marketplace_listing_id`) REFERENCES `marketplace_listings` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `marketplace_reports`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `marketplace_reports` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `tenant_id` bigint(20) unsigned NOT NULL,
  `marketplace_listing_id` bigint(20) unsigned NOT NULL,
  `reporter_id` bigint(20) unsigned NOT NULL,
  `reason` enum('counterfeit','illegal','unsafe','misleading','discrimination','ip_violation','other') NOT NULL,
  `description` text NOT NULL,
  `evidence_urls` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`evidence_urls`)),
  `status` enum('received','acknowledged','under_review','action_taken','no_action','appealed','appeal_resolved') NOT NULL DEFAULT 'received',
  `acknowledged_at` timestamp NULL DEFAULT NULL,
  `resolved_at` timestamp NULL DEFAULT NULL,
  `resolution_reason` text DEFAULT NULL,
  `action_taken` enum('none','warning','listing_removed','seller_suspended') DEFAULT NULL,
  `appeal_text` text DEFAULT NULL,
  `appeal_resolved_at` timestamp NULL DEFAULT NULL,
  `handled_by` bigint(20) unsigned DEFAULT NULL,
  `transparency_report_included` tinyint(1) NOT NULL DEFAULT 0,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `marketplace_reports_marketplace_listing_id_foreign` (`marketplace_listing_id`),
  KEY `mr_reporter_id_idx` (`reporter_id`),
  KEY `mr_handled_by_idx` (`handled_by`),
  KEY `marketplace_reports_tenant_id_status_index` (`tenant_id`,`status`),
  KEY `marketplace_reports_tenant_id_marketplace_listing_id_index` (`tenant_id`,`marketplace_listing_id`),
  KEY `marketplace_reports_tenant_id_index` (`tenant_id`),
  CONSTRAINT `marketplace_reports_marketplace_listing_id_foreign` FOREIGN KEY (`marketplace_listing_id`) REFERENCES `marketplace_listings` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `marketplace_saved_listings`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `marketplace_saved_listings` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `tenant_id` bigint(20) unsigned NOT NULL,
  `user_id` bigint(20) unsigned NOT NULL,
  `marketplace_listing_id` bigint(20) unsigned NOT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `mps_tenant_user_listing_unique` (`tenant_id`,`user_id`,`marketplace_listing_id`),
  KEY `marketplace_saved_listings_marketplace_listing_id_foreign` (`marketplace_listing_id`),
  KEY `marketplace_saved_listings_tenant_id_index` (`tenant_id`),
  CONSTRAINT `marketplace_saved_listings_marketplace_listing_id_foreign` FOREIGN KEY (`marketplace_listing_id`) REFERENCES `marketplace_listings` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `marketplace_saved_searches`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `marketplace_saved_searches` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `tenant_id` bigint(20) unsigned NOT NULL,
  `user_id` bigint(20) unsigned NOT NULL,
  `name` varchar(100) NOT NULL,
  `search_query` varchar(255) DEFAULT NULL,
  `filters` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`filters`)),
  `alert_frequency` enum('instant','daily','weekly') NOT NULL DEFAULT 'daily',
  `alert_channel` enum('email','push','both') NOT NULL DEFAULT 'email',
  `last_alerted_at` timestamp NULL DEFAULT NULL,
  `is_active` tinyint(1) NOT NULL DEFAULT 1,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `mss_user_id_idx` (`user_id`),
  KEY `marketplace_saved_searches_tenant_id_user_id_index` (`tenant_id`,`user_id`),
  KEY `marketplace_saved_searches_tenant_id_index` (`tenant_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `marketplace_seller_profiles`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `marketplace_seller_profiles` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `tenant_id` bigint(20) unsigned NOT NULL,
  `user_id` bigint(20) unsigned NOT NULL,
  `display_name` varchar(100) DEFAULT NULL,
  `bio` text DEFAULT NULL,
  `cover_image_url` varchar(500) DEFAULT NULL,
  `avatar_url` varchar(500) DEFAULT NULL,
  `seller_type` enum('private','business') NOT NULL DEFAULT 'private',
  `business_name` varchar(200) DEFAULT NULL,
  `business_registration` varchar(100) DEFAULT NULL,
  `vat_number` varchar(50) DEFAULT NULL,
  `business_address` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`business_address`)),
  `business_verified` tinyint(1) NOT NULL DEFAULT 0,
  `stripe_account_id` varchar(100) DEFAULT NULL,
  `stripe_onboarding_complete` tinyint(1) NOT NULL DEFAULT 0,
  `response_time_avg` int(11) DEFAULT NULL COMMENT 'Average response time in minutes',
  `response_rate` decimal(5,2) DEFAULT NULL COMMENT 'Response rate percentage',
  `total_sales` int(11) NOT NULL DEFAULT 0,
  `total_revenue` decimal(12,2) NOT NULL DEFAULT 0.00,
  `avg_rating` decimal(3,2) DEFAULT NULL,
  `total_ratings` int(11) NOT NULL DEFAULT 0,
  `community_trust_score` decimal(5,2) DEFAULT NULL,
  `is_community_endorsed` tinyint(1) NOT NULL DEFAULT 0,
  `joined_marketplace_at` timestamp NULL DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `mpsp_tenant_user_unique` (`tenant_id`,`user_id`),
  KEY `marketplace_seller_profiles_tenant_id_index` (`tenant_id`)
) ENGINE=InnoDB AUTO_INCREMENT=3 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `marketplace_seller_ratings`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `marketplace_seller_ratings` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `tenant_id` bigint(20) unsigned NOT NULL,
  `order_id` bigint(20) unsigned NOT NULL,
  `rater_id` bigint(20) unsigned NOT NULL,
  `ratee_id` bigint(20) unsigned NOT NULL,
  `rater_role` enum('buyer','seller') NOT NULL,
  `rating` tinyint(4) NOT NULL,
  `comment` text DEFAULT NULL,
  `is_anonymous` tinyint(1) NOT NULL DEFAULT 0,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `msr_tenant_order_role_unique` (`tenant_id`,`order_id`,`rater_role`),
  KEY `marketplace_seller_ratings_order_id_foreign` (`order_id`),
  KEY `msr_rater_id_idx` (`rater_id`),
  KEY `msr_ratee_id_idx` (`ratee_id`),
  KEY `marketplace_seller_ratings_tenant_id_index` (`tenant_id`),
  CONSTRAINT `marketplace_seller_ratings_order_id_foreign` FOREIGN KEY (`order_id`) REFERENCES `marketplace_orders` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `marketplace_shipping_options`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `marketplace_shipping_options` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `tenant_id` bigint(20) unsigned NOT NULL,
  `seller_id` bigint(20) unsigned NOT NULL,
  `courier_name` varchar(100) NOT NULL,
  `courier_code` varchar(50) DEFAULT NULL,
  `price` decimal(8,2) NOT NULL,
  `currency` varchar(3) NOT NULL DEFAULT 'EUR',
  `estimated_days` int(10) unsigned DEFAULT NULL,
  `is_default` tinyint(1) NOT NULL DEFAULT 0,
  `is_active` tinyint(1) NOT NULL DEFAULT 1,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `marketplace_shipping_options_seller_id_foreign` (`seller_id`),
  KEY `marketplace_shipping_options_tenant_id_seller_id_index` (`tenant_id`,`seller_id`),
  KEY `marketplace_shipping_options_tenant_id_index` (`tenant_id`),
  CONSTRAINT `marketplace_shipping_options_seller_id_foreign` FOREIGN KEY (`seller_id`) REFERENCES `marketplace_seller_profiles` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `match_approvals`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `match_approvals` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `tenant_id` int(11) NOT NULL,
  `user_id` int(11) NOT NULL,
  `listing_id` int(11) NOT NULL,
  `listing_owner_id` int(11) NOT NULL,
  `match_score` decimal(5,2) NOT NULL,
  `match_type` varchar(50) DEFAULT 'one_way',
  `match_reasons` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`match_reasons`)),
  `distance_km` decimal(8,2) DEFAULT NULL,
  `status` enum('pending','approved','rejected') DEFAULT 'pending',
  `submitted_at` timestamp NULL DEFAULT current_timestamp(),
  `reviewed_by` int(11) DEFAULT NULL,
  `reviewed_at` timestamp NULL DEFAULT NULL,
  `review_notes` text DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `unique_pending_match` (`tenant_id`,`user_id`,`listing_id`,`status`),
  KEY `fk_match_approvals_owner` (`listing_owner_id`),
  KEY `idx_tenant_status` (`tenant_id`,`status`),
  KEY `idx_pending` (`tenant_id`,`status`,`submitted_at`),
  KEY `idx_user` (`user_id`),
  KEY `idx_listing` (`listing_id`),
  KEY `idx_reviewer` (`reviewed_by`),
  CONSTRAINT `fk_match_approvals_listing` FOREIGN KEY (`listing_id`) REFERENCES `listings` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_match_approvals_owner` FOREIGN KEY (`listing_owner_id`) REFERENCES `users` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_match_approvals_reviewer` FOREIGN KEY (`reviewed_by`) REFERENCES `users` (`id`) ON DELETE SET NULL,
  CONSTRAINT `fk_match_approvals_tenant` FOREIGN KEY (`tenant_id`) REFERENCES `tenants` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_match_approvals_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=11 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `match_cache`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `match_cache` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `user_id` int(11) NOT NULL,
  `listing_id` int(11) NOT NULL,
  `tenant_id` int(11) NOT NULL,
  `match_score` decimal(5,2) NOT NULL COMMENT 'Score 0-100',
  `distance_km` decimal(8,2) DEFAULT NULL,
  `match_type` enum('one_way','potential','mutual','cold_start') DEFAULT 'one_way',
  `match_reasons` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL COMMENT 'Array of reason strings' CHECK (json_valid(`match_reasons`)),
  `status` enum('new','viewed','contacted','saved','dismissed') DEFAULT 'new',
  `notified_at` timestamp NULL DEFAULT NULL,
  `notification_type` varchar(20) DEFAULT NULL COMMENT 'instant, daily, weekly',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `expires_at` timestamp NULL DEFAULT NULL,
  `blocker_user_id` int(11) DEFAULT NULL,
  `clicked_at` timestamp NULL DEFAULT NULL,
  `match` timestamp NULL DEFAULT NULL,
  `reset_token` varchar(255) DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `unique_user_listing` (`user_id`,`listing_id`,`tenant_id`),
  KEY `idx_user_score` (`user_id`,`match_score`),
  KEY `idx_tenant_status` (`tenant_id`,`status`),
  KEY `idx_new_matches` (`tenant_id`,`status`,`created_at`),
  KEY `idx_hot_matches` (`tenant_id`,`match_score`,`distance_km`),
  KEY `listing_id` (`listing_id`),
  CONSTRAINT `match_cache_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE,
  CONSTRAINT `match_cache_ibfk_2` FOREIGN KEY (`listing_id`) REFERENCES `listings` (`id`) ON DELETE CASCADE,
  CONSTRAINT `match_cache_ibfk_3` FOREIGN KEY (`tenant_id`) REFERENCES `tenants` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=284 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `match_digest_log`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `match_digest_log` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `tenant_id` int(11) NOT NULL,
  `user_id` int(11) NOT NULL,
  `matches_count` int(11) NOT NULL DEFAULT 0,
  `sent_at` datetime NOT NULL DEFAULT current_timestamp(),
  `digest_data` text DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `idx_digest_tenant_user` (`tenant_id`,`user_id`),
  KEY `idx_digest_sent` (`sent_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `match_dismissals`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `match_dismissals` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `tenant_id` int(10) unsigned NOT NULL,
  `user_id` int(10) unsigned NOT NULL,
  `listing_id` int(10) unsigned NOT NULL,
  `reason` varchar(50) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `uk_user_listing` (`tenant_id`,`user_id`,`listing_id`),
  KEY `idx_user` (`tenant_id`,`user_id`),
  KEY `idx_listing` (`tenant_id`,`listing_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `match_history`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `match_history` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `user_id` int(11) NOT NULL,
  `listing_id` int(11) NOT NULL,
  `tenant_id` int(11) NOT NULL,
  `match_score` decimal(5,2) NOT NULL,
  `distance_km` decimal(8,2) DEFAULT NULL,
  `action` enum('impression','view','save','contact','dismiss','accept','decline','viewed','contacted','saved','dismissed','completed') NOT NULL,
  `resulted_in_transaction` tinyint(1) DEFAULT 0,
  `transaction_id` int(11) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `conversion_time` datetime DEFAULT NULL COMMENT 'When match resulted in transaction',
  `match_reasons` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL COMMENT 'Array of human-readable match reason strings' CHECK (json_valid(`match_reasons`)),
  `clicked_at` timestamp NULL DEFAULT NULL,
  `match` timestamp NULL DEFAULT NULL,
  `reset_token` varchar(255) DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `idx_user` (`user_id`),
  KEY `idx_listing` (`listing_id`),
  KEY `idx_tenant_action` (`tenant_id`,`action`),
  KEY `idx_outcomes` (`tenant_id`,`resulted_in_transaction`),
  KEY `idx_mh_conversion` (`tenant_id`,`resulted_in_transaction`),
  KEY `idx_mh_user_listing` (`user_id`,`listing_id`,`tenant_id`),
  CONSTRAINT `match_history_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE,
  CONSTRAINT `match_history_ibfk_2` FOREIGN KEY (`listing_id`) REFERENCES `listings` (`id`) ON DELETE CASCADE,
  CONSTRAINT `match_history_ibfk_3` FOREIGN KEY (`tenant_id`) REFERENCES `tenants` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=4 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `match_notification_sent`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `match_notification_sent` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `tenant_id` int(10) unsigned NOT NULL,
  `listing_id` int(10) unsigned NOT NULL COMMENT 'The newly created listing that triggered the match',
  `matched_user_id` int(10) unsigned NOT NULL COMMENT 'The user who was notified about the match',
  `match_score` int(10) unsigned NOT NULL DEFAULT 0,
  `sent_at` datetime NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_match_notification` (`tenant_id`,`listing_id`,`matched_user_id`),
  KEY `idx_match_notif_lookup` (`tenant_id`,`listing_id`,`matched_user_id`),
  KEY `idx_match_notif_cleanup` (`sent_at`)
) ENGINE=InnoDB AUTO_INCREMENT=10 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `match_preferences`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `match_preferences` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `user_id` int(11) NOT NULL,
  `tenant_id` int(11) NOT NULL,
  `max_distance_km` int(11) DEFAULT 25 COMMENT 'Maximum distance for matches in km',
  `min_match_score` int(11) DEFAULT 50 COMMENT 'Minimum score (0-100) to show as match',
  `notification_frequency` enum('daily','weekly','fortnightly','never') DEFAULT 'fortnightly',
  `notify_hot_matches` tinyint(1) DEFAULT 1 COMMENT 'Instant notify for hot matches (>80%)',
  `notify_mutual_matches` tinyint(1) DEFAULT 1 COMMENT 'Instant notify for mutual matches',
  `categories` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL COMMENT 'Array of category IDs to match, null = all' CHECK (json_valid(`categories`)),
  `availability` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL COMMENT 'Available days/times for exchanges' CHECK (json_valid(`availability`)),
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  `blocker_user_id` int(11) DEFAULT NULL,
  `clicked_at` timestamp NULL DEFAULT NULL,
  `match` timestamp NULL DEFAULT NULL,
  `reset_token` varchar(255) DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `user_tenant` (`user_id`,`tenant_id`),
  KEY `idx_tenant` (`tenant_id`),
  CONSTRAINT `match_preferences_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE,
  CONSTRAINT `match_preferences_ibfk_2` FOREIGN KEY (`tenant_id`) REFERENCES `tenants` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=228 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `matches`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `matches` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `tenant_id` int(10) unsigned NOT NULL,
  `user_id` int(10) unsigned NOT NULL,
  `matched_user_id` int(10) unsigned NOT NULL,
  `listing_id` int(10) unsigned DEFAULT NULL,
  `score` decimal(5,2) NOT NULL DEFAULT 0.00,
  `match_type` varchar(50) DEFAULT NULL,
  `status` enum('pending','accepted','rejected','expired') NOT NULL DEFAULT 'pending',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NULL DEFAULT NULL ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `matches_tenant_id_user_id_index` (`tenant_id`,`user_id`),
  KEY `matches_tenant_id_status_index` (`tenant_id`,`status`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `member_activity_flags`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `member_activity_flags` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `user_id` int(11) NOT NULL,
  `tenant_id` int(11) NOT NULL,
  `last_activity_at` timestamp NULL DEFAULT NULL COMMENT 'Most recent activity across all types',
  `last_login_at` timestamp NULL DEFAULT NULL,
  `last_transaction_at` timestamp NULL DEFAULT NULL,
  `last_post_at` timestamp NULL DEFAULT NULL,
  `last_event_at` timestamp NULL DEFAULT NULL,
  `flag_type` enum('inactive','dormant','at_risk') NOT NULL DEFAULT 'inactive',
  `flagged_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `notified_at` timestamp NULL DEFAULT NULL COMMENT 'When admin was last notified about this flag',
  `resolved_at` timestamp NULL DEFAULT NULL COMMENT 'When flag was resolved (user became active again)',
  PRIMARY KEY (`id`),
  UNIQUE KEY `uk_user_tenant` (`user_id`,`tenant_id`),
  KEY `idx_tenant_flag` (`tenant_id`,`flag_type`),
  KEY `idx_tenant_last_activity` (`tenant_id`,`last_activity_at`),
  KEY `idx_flagged_at` (`tenant_id`,`flagged_at`)
) ENGINE=InnoDB AUTO_INCREMENT=3604 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `member_availability`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `member_availability` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `user_id` int(11) NOT NULL,
  `tenant_id` int(11) NOT NULL,
  `day_of_week` tinyint(1) NOT NULL COMMENT '0=Sunday, 6=Saturday',
  `start_time` time NOT NULL,
  `end_time` time NOT NULL,
  `is_recurring` tinyint(1) NOT NULL DEFAULT 1,
  `specific_date` date DEFAULT NULL COMMENT 'For one-off availability',
  `note` varchar(255) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NULL DEFAULT NULL ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `idx_user_tenant` (`user_id`,`tenant_id`),
  KEY `idx_day` (`tenant_id`,`day_of_week`),
  KEY `idx_specific_date` (`tenant_id`,`specific_date`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `member_notes`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `member_notes` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `tenant_id` int(10) unsigned NOT NULL,
  `user_id` int(10) unsigned NOT NULL COMMENT 'The member this note is about',
  `author_id` int(10) unsigned NOT NULL COMMENT 'The admin who wrote the note',
  `content` text NOT NULL,
  `category` enum('general','outreach','support','onboarding','concern','follow_up') NOT NULL DEFAULT 'general',
  `is_pinned` tinyint(1) NOT NULL DEFAULT 0,
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  `updated_at` datetime NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `idx_member_notes_tenant_user` (`tenant_id`,`user_id`),
  KEY `idx_member_notes_author` (`tenant_id`,`author_id`),
  KEY `idx_member_notes_category` (`tenant_id`,`category`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `member_tags`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `member_tags` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `tenant_id` int(10) unsigned NOT NULL,
  `user_id` int(10) unsigned NOT NULL,
  `tag` varchar(50) NOT NULL,
  `created_by` int(10) unsigned NOT NULL,
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `uk_member_tags` (`tenant_id`,`user_id`,`tag`),
  KEY `idx_member_tags_tag` (`tenant_id`,`tag`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `member_verification_badges`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `member_verification_badges` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `user_id` int(11) NOT NULL,
  `tenant_id` int(11) NOT NULL,
  `badge_type` varchar(50) NOT NULL COMMENT 'email_verified, phone_verified, id_verified, dbs_checked, admin_verified',
  `verified_by` int(11) DEFAULT NULL COMMENT 'Admin user_id who granted',
  `organization_id` int(10) unsigned DEFAULT NULL,
  `verification_note` varchar(500) DEFAULT NULL,
  `granted_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `expires_at` timestamp NULL DEFAULT NULL,
  `revoked_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `unique_user_badge` (`user_id`,`tenant_id`,`badge_type`),
  KEY `idx_user_tenant` (`user_id`,`tenant_id`),
  KEY `idx_badge_type` (`tenant_id`,`badge_type`),
  KEY `idx_verified_by` (`verified_by`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `mentions`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `mentions` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `comment_id` int(11) DEFAULT NULL,
  `mentioned_user_id` int(11) NOT NULL COMMENT 'The user being mentioned',
  `mentioning_user_id` int(11) NOT NULL COMMENT 'The user who made the mention',
  `tenant_id` int(11) NOT NULL,
  `entity_type` varchar(30) DEFAULT 'comment',
  `entity_id` int(11) DEFAULT NULL,
  `seen_at` timestamp NULL DEFAULT NULL COMMENT 'When the mentioned user saw the notification',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `unique_mention` (`comment_id`,`mentioned_user_id`),
  KEY `mentioning_user_id` (`mentioning_user_id`),
  KEY `idx_mentioned_user` (`mentioned_user_id`),
  KEY `idx_comment` (`comment_id`),
  KEY `idx_tenant` (`tenant_id`),
  KEY `idx_unseen` (`mentioned_user_id`,`seen_at`),
  KEY `idx_tenant_mentioned` (`tenant_id`,`mentioned_user_id`),
  KEY `idx_tenant_entity` (`tenant_id`,`entity_type`,`entity_id`),
  CONSTRAINT `mentions_ibfk_1` FOREIGN KEY (`comment_id`) REFERENCES `comments` (`id`) ON DELETE CASCADE,
  CONSTRAINT `mentions_ibfk_2` FOREIGN KEY (`mentioned_user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE,
  CONSTRAINT `mentions_ibfk_3` FOREIGN KEY (`mentioning_user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE,
  CONSTRAINT `mentions_ibfk_4` FOREIGN KEY (`tenant_id`) REFERENCES `tenants` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `menu_cache`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `menu_cache` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `tenant_id` int(11) NOT NULL,
  `layout` varchar(50) DEFAULT NULL,
  `location` varchar(50) NOT NULL,
  `user_role` varchar(50) DEFAULT 'guest' COMMENT 'guest, user, admin, super_admin',
  `cache_key` varchar(255) NOT NULL,
  `cached_data` longtext NOT NULL COMMENT 'Serialized menu structure',
  `expires_at` timestamp NULL DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `plan` varchar(255) DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `idx_menu_cache_key` (`cache_key`),
  KEY `idx_menu_cache_tenant` (`tenant_id`,`location`,`layout`),
  KEY `idx_menu_cache_expires` (`expires_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `menu_items`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `menu_items` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `menu_id` int(11) NOT NULL,
  `parent_id` int(11) DEFAULT NULL COMMENT 'For nested/dropdown menus',
  `type` enum('link','dropdown','page','route','external','divider') DEFAULT 'link',
  `label` varchar(255) NOT NULL,
  `url` varchar(500) DEFAULT NULL,
  `route_name` varchar(100) DEFAULT NULL COMMENT 'Internal route name for route type',
  `page_id` int(11) DEFAULT NULL COMMENT 'CMS page ID for page type',
  `icon` varchar(100) DEFAULT NULL COMMENT 'Icon class or name',
  `css_class` varchar(255) DEFAULT NULL,
  `target` varchar(20) DEFAULT '_self' COMMENT '_self, _blank, etc.',
  `sort_order` int(11) DEFAULT 0,
  `visibility_rules` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL COMMENT 'Auth, role, feature conditions' CHECK (json_valid(`visibility_rules`)),
  `is_active` tinyint(1) DEFAULT 1,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NULL DEFAULT NULL ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `idx_menu_items_menu` (`menu_id`),
  KEY `idx_menu_items_parent` (`parent_id`),
  KEY `idx_menu_items_sort` (`sort_order`),
  KEY `idx_menu_items_active` (`is_active`),
  KEY `idx_menu_items_page` (`page_id`),
  CONSTRAINT `menu_items_ibfk_1` FOREIGN KEY (`menu_id`) REFERENCES `menus` (`id`) ON DELETE CASCADE,
  CONSTRAINT `menu_items_ibfk_2` FOREIGN KEY (`parent_id`) REFERENCES `menu_items` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=19 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `menus`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `menus` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `tenant_id` int(11) NOT NULL,
  `name` varchar(100) NOT NULL,
  `slug` varchar(50) NOT NULL,
  `description` text DEFAULT NULL,
  `location` varchar(50) NOT NULL COMMENT 'header-main, header-secondary, footer, sidebar, mobile',
  `layout` varchar(50) DEFAULT NULL COMMENT 'modern, civicone, nexus-social, or NULL for all layouts',
  `min_plan_tier` int(11) DEFAULT 0 COMMENT 'Minimum plan tier required to see this menu',
  `is_active` tinyint(1) DEFAULT 1,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NULL DEFAULT NULL ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `idx_menu_unique` (`tenant_id`,`slug`),
  KEY `idx_menu_tenant` (`tenant_id`),
  KEY `idx_menu_location` (`location`),
  KEY `idx_menu_layout` (`layout`),
  KEY `idx_menu_active` (`is_active`)
) ENGINE=InnoDB AUTO_INCREMENT=5 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `message_attachments`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `message_attachments` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `message_id` int(10) unsigned NOT NULL,
  `tenant_id` int(10) unsigned NOT NULL,
  `file_name` varchar(255) NOT NULL COMMENT 'Original file name',
  `file_path` varchar(500) NOT NULL COMMENT 'Storage path',
  `file_url` varchar(500) NOT NULL COMMENT 'Public URL',
  `file_type` varchar(20) NOT NULL DEFAULT 'file' COMMENT 'image or file',
  `mime_type` varchar(100) DEFAULT NULL COMMENT 'MIME type',
  `file_size` int(10) unsigned DEFAULT 0 COMMENT 'Size in bytes',
  `created_at` timestamp NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `idx_message_id` (`message_id`),
  KEY `idx_tenant_id` (`tenant_id`)
) ENGINE=InnoDB AUTO_INCREMENT=3 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `message_link_previews`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `message_link_previews` (
  `message_id` bigint(20) unsigned NOT NULL,
  `link_preview_id` bigint(20) unsigned NOT NULL,
  PRIMARY KEY (`message_id`,`link_preview_id`),
  KEY `idx_preview` (`link_preview_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `message_reactions`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `message_reactions` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `tenant_id` bigint(20) unsigned NOT NULL DEFAULT 0,
  `message_id` int(11) NOT NULL,
  `user_id` int(11) NOT NULL,
  `emoji` varchar(10) NOT NULL,
  `created_at` datetime DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `unique_reaction` (`message_id`,`user_id`,`emoji`),
  KEY `idx_message_id` (`message_id`),
  KEY `idx_user_id` (`user_id`),
  KEY `idx_mr_tenant_id` (`tenant_id`),
  CONSTRAINT `message_reactions_ibfk_1` FOREIGN KEY (`message_id`) REFERENCES `messages` (`id`) ON DELETE CASCADE,
  CONSTRAINT `message_reactions_ibfk_2` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=8 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `messages`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `messages` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `tenant_id` int(11) NOT NULL,
  `conversation_id` bigint(20) unsigned DEFAULT NULL,
  `sender_id` int(11) NOT NULL,
  `receiver_id` int(11) NOT NULL,
  `listing_id` int(11) DEFAULT NULL,
  `subject` varchar(255) DEFAULT NULL,
  `body` text DEFAULT NULL,
  `audio_url` varchar(500) DEFAULT NULL,
  `audio_duration` int(10) unsigned DEFAULT NULL COMMENT 'Duration in seconds',
  `transcript` text DEFAULT NULL,
  `transcript_language` varchar(10) DEFAULT NULL,
  `is_read` tinyint(1) DEFAULT 0,
  `read_at` datetime DEFAULT NULL,
  `is_federated` tinyint(1) DEFAULT 0,
  `created_at` datetime DEFAULT current_timestamp(),
  `award` varchar(255) DEFAULT NULL,
  `archived_by_sender` datetime DEFAULT NULL COMMENT 'When sender archived this conversation',
  `archived_by_receiver` datetime DEFAULT NULL COMMENT 'When receiver archived this conversation',
  `reactions` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL COMMENT 'JSON object of emoji reactions' CHECK (json_valid(`reactions`)),
  `is_edited` tinyint(1) DEFAULT 0 COMMENT 'Whether message was edited',
  `edited_at` timestamp NULL DEFAULT NULL COMMENT 'When message was edited',
  `is_deleted` tinyint(1) DEFAULT 0 COMMENT 'Whether message was deleted',
  `is_deleted_sender` tinyint(1) NOT NULL DEFAULT 0,
  `is_deleted_receiver` tinyint(1) NOT NULL DEFAULT 0,
  `deleted_at` timestamp NULL DEFAULT NULL COMMENT 'When message was deleted',
  `context_type` varchar(50) DEFAULT NULL,
  `context_id` int(11) DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `tenant_id` (`tenant_id`),
  KEY `receiver_id` (`receiver_id`),
  KEY `idx_messages_thread` (`sender_id`,`receiver_id`),
  KEY `idx_messages_unread` (`is_read`,`receiver_id`),
  KEY `idx_messages_audio` (`audio_url`(100)),
  KEY `idx_msg_is_federated` (`is_federated`),
  KEY `idx_messages_sender_archived` (`sender_id`,`archived_by_sender`),
  KEY `idx_messages_receiver_archived` (`receiver_id`,`archived_by_receiver`),
  KEY `idx_messages_deleted` (`is_deleted`),
  KEY `idx_messages_listing` (`listing_id`),
  KEY `idx_messages_context` (`context_type`,`context_id`),
  KEY `idx_messages_is_deleted_sender` (`tenant_id`,`sender_id`,`is_deleted_sender`),
  KEY `idx_messages_is_deleted_receiver` (`tenant_id`,`receiver_id`,`is_deleted_receiver`),
  KEY `idx_msg_tenant_sender_receiver` (`tenant_id`,`sender_id`,`receiver_id`),
  KEY `idx_msg_tenant_receiver_unread` (`tenant_id`,`receiver_id`,`is_read`),
  KEY `idx_msg_conversation` (`conversation_id`),
  CONSTRAINT `fk_messages_listing` FOREIGN KEY (`listing_id`) REFERENCES `listings` (`id`) ON DELETE SET NULL,
  CONSTRAINT `messages_ibfk_1` FOREIGN KEY (`tenant_id`) REFERENCES `tenants` (`id`) ON DELETE CASCADE,
  CONSTRAINT `messages_ibfk_3` FOREIGN KEY (`receiver_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=376 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `migrations`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `migrations` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `migration_name` varchar(255) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `backups` varchar(255) DEFAULT NULL,
  `executed_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `idx_migration_name` (`migration_name`)
) ENGINE=InnoDB AUTO_INCREMENT=266 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `monthly_engagement`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `monthly_engagement` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `tenant_id` int(10) unsigned NOT NULL,
  `user_id` int(10) unsigned NOT NULL,
  `year_month` varchar(7) NOT NULL,
  `was_active` tinyint(1) NOT NULL DEFAULT 0,
  `activity_count` int(10) unsigned NOT NULL DEFAULT 0,
  `recognized_at` timestamp NULL DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uniq_monthly_engagement` (`tenant_id`,`user_id`,`year_month`),
  KEY `idx_me_tenant` (`tenant_id`),
  KEY `idx_me_user_month` (`user_id`,`year_month`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `news`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `news` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `tenant_id` int(11) NOT NULL,
  `user_id` int(11) DEFAULT NULL,
  `title` varchar(255) NOT NULL,
  `slug` varchar(255) DEFAULT NULL,
  `excerpt` text DEFAULT NULL,
  `content` longtext DEFAULT NULL,
  `image_url` varchar(500) DEFAULT NULL,
  `is_published` tinyint(1) DEFAULT 0,
  `is_featured` tinyint(1) DEFAULT 0,
  `published_at` datetime DEFAULT NULL,
  `views` int(11) DEFAULT 0,
  `created_at` datetime DEFAULT current_timestamp(),
  `updated_at` datetime DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `idx_tenant` (`tenant_id`),
  KEY `idx_published` (`tenant_id`,`is_published`,`published_at`),
  KEY `idx_featured` (`tenant_id`,`is_featured`,`published_at`),
  KEY `idx_user` (`user_id`),
  KEY `idx_slug` (`tenant_id`,`slug`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `newsletter_ab_stats`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `newsletter_ab_stats` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `tenant_id` int(11) NOT NULL,
  `newsletter_id` int(11) NOT NULL,
  `variant` char(1) NOT NULL,
  `total_sent` int(11) DEFAULT 0,
  `total_opens` int(11) DEFAULT 0,
  `unique_opens` int(11) DEFAULT 0,
  `total_clicks` int(11) DEFAULT 0,
  `unique_clicks` int(11) DEFAULT 0,
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `unique_newsletter_variant` (`newsletter_id`,`variant`),
  KEY `idx_tenant_newsletter` (`tenant_id`,`newsletter_id`),
  CONSTRAINT `newsletter_ab_stats_ibfk_1` FOREIGN KEY (`tenant_id`) REFERENCES `tenants` (`id`) ON DELETE CASCADE,
  CONSTRAINT `newsletter_ab_stats_ibfk_2` FOREIGN KEY (`newsletter_id`) REFERENCES `newsletters` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `newsletter_bounces`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `newsletter_bounces` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `tenant_id` int(11) NOT NULL,
  `email` varchar(255) NOT NULL,
  `newsletter_id` int(11) DEFAULT NULL,
  `queue_id` int(11) DEFAULT NULL,
  `bounce_type` enum('hard','soft','complaint') NOT NULL,
  `bounce_reason` text DEFAULT NULL,
  `bounce_code` varchar(50) DEFAULT NULL,
  `bounced_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `newsletter_id` (`newsletter_id`),
  KEY `idx_email` (`email`),
  KEY `idx_tenant_email` (`tenant_id`,`email`),
  KEY `idx_bounce_type` (`bounce_type`),
  CONSTRAINT `newsletter_bounces_ibfk_1` FOREIGN KEY (`tenant_id`) REFERENCES `tenants` (`id`) ON DELETE CASCADE,
  CONSTRAINT `newsletter_bounces_ibfk_2` FOREIGN KEY (`newsletter_id`) REFERENCES `newsletters` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB AUTO_INCREMENT=6 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `newsletter_clicks`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `newsletter_clicks` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `tenant_id` int(11) NOT NULL,
  `newsletter_id` int(11) NOT NULL,
  `queue_id` int(11) DEFAULT NULL,
  `email` varchar(255) NOT NULL,
  `url` text NOT NULL,
  `link_id` varchar(64) NOT NULL,
  `user_agent` varchar(500) DEFAULT NULL,
  `ip_address` varchar(45) DEFAULT NULL,
  `clicked_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `click_rate` timestamp NULL DEFAULT NULL,
  `distance` varchar(255) DEFAULT NULL,
  `open_rate` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `idx_newsletter_clicks` (`newsletter_id`),
  KEY `idx_link` (`link_id`),
  KEY `idx_tenant_newsletter` (`tenant_id`,`newsletter_id`),
  KEY `idx_newsletter_clicks_email_newsletter` (`email`,`newsletter_id`),
  CONSTRAINT `newsletter_clicks_ibfk_1` FOREIGN KEY (`tenant_id`) REFERENCES `tenants` (`id`) ON DELETE CASCADE,
  CONSTRAINT `newsletter_clicks_ibfk_2` FOREIGN KEY (`newsletter_id`) REFERENCES `newsletters` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=44 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `newsletter_engagement_patterns`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `newsletter_engagement_patterns` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `tenant_id` int(11) NOT NULL,
  `email` varchar(255) NOT NULL,
  `opens_by_hour` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`opens_by_hour`)),
  `clicks_by_hour` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`clicks_by_hour`)),
  `opens_by_day` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`opens_by_day`)),
  `clicks_by_day` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`clicks_by_day`)),
  `best_hour` tinyint(2) DEFAULT NULL,
  `best_day` tinyint(1) DEFAULT NULL,
  `total_opens` int(11) DEFAULT 0,
  `total_clicks` int(11) DEFAULT 0,
  `last_updated` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `unique_tenant_email` (`tenant_id`,`email`),
  CONSTRAINT `newsletter_engagement_patterns_ibfk_1` FOREIGN KEY (`tenant_id`) REFERENCES `tenants` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `newsletter_link_clicks`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `newsletter_link_clicks` (
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
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `newsletter_opens`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `newsletter_opens` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `tenant_id` int(11) NOT NULL,
  `newsletter_id` int(11) NOT NULL,
  `queue_id` int(11) DEFAULT NULL,
  `email` varchar(255) NOT NULL,
  `user_agent` varchar(500) DEFAULT NULL,
  `ip_address` varchar(45) DEFAULT NULL,
  `opened_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `click_rate` timestamp NULL DEFAULT NULL,
  `distance` varchar(255) DEFAULT NULL,
  `open_rate` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `idx_newsletter_opens` (`newsletter_id`),
  KEY `idx_tenant_newsletter` (`tenant_id`,`newsletter_id`),
  KEY `idx_email` (`email`),
  KEY `idx_newsletter_opens_email_newsletter` (`email`,`newsletter_id`),
  CONSTRAINT `newsletter_opens_ibfk_1` FOREIGN KEY (`tenant_id`) REFERENCES `tenants` (`id`) ON DELETE CASCADE,
  CONSTRAINT `newsletter_opens_ibfk_2` FOREIGN KEY (`newsletter_id`) REFERENCES `newsletters` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=470 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `newsletter_queue`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `newsletter_queue` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `newsletter_id` int(11) NOT NULL,
  `user_id` int(11) DEFAULT NULL,
  `email` varchar(255) NOT NULL,
  `name` varchar(255) NOT NULL DEFAULT '',
  `first_name` varchar(100) NOT NULL DEFAULT '',
  `last_name` varchar(100) NOT NULL DEFAULT '',
  `status` enum('pending','sent','failed') DEFAULT 'pending',
  `error_message` text DEFAULT NULL,
  `attempts` tinyint(3) unsigned NOT NULL DEFAULT 0,
  `last_attempted_at` timestamp NULL DEFAULT NULL,
  `unsubscribe_token` varchar(64) DEFAULT NULL,
  `tracking_token` varchar(64) DEFAULT NULL,
  `ab_variant` char(1) DEFAULT NULL,
  `sent_at` datetime DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `click_rate` timestamp NULL DEFAULT NULL,
  `clicked_at` timestamp NULL DEFAULT NULL,
  `distance` varchar(255) DEFAULT NULL,
  `open_rate` timestamp NULL DEFAULT NULL,
  `reset_token` varchar(255) DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `unique_newsletter_email` (`newsletter_id`,`email`),
  KEY `idx_newsletter_status` (`newsletter_id`,`status`),
  KEY `idx_tracking_token` (`tracking_token`),
  KEY `idx_ab_variant` (`newsletter_id`,`ab_variant`),
  KEY `idx_newsletter_queue_user_status` (`user_id`,`status`),
  KEY `idx_newsletter_queue_email_newsletter` (`email`,`newsletter_id`),
  KEY `idx_newsletter_queue_sent_at` (`sent_at`),
  KEY `idx_newsletter_queue_retry` (`newsletter_id`,`status`,`last_attempted_at`),
  CONSTRAINT `newsletter_queue_ibfk_1` FOREIGN KEY (`newsletter_id`) REFERENCES `newsletters` (`id`) ON DELETE CASCADE,
  CONSTRAINT `newsletter_queue_ibfk_2` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB AUTO_INCREMENT=1124 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `newsletter_segments`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `newsletter_segments` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `tenant_id` int(11) NOT NULL,
  `name` varchar(100) NOT NULL,
  `description` text DEFAULT NULL,
  `rules` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin NOT NULL CHECK (json_valid(`rules`)),
  `is_active` tinyint(1) DEFAULT 1,
  `created_by` int(11) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  `click_rate` timestamp NULL DEFAULT NULL,
  `distance` varchar(255) DEFAULT NULL,
  `open_rate` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `idx_tenant_active` (`tenant_id`,`is_active`),
  CONSTRAINT `newsletter_segments_ibfk_1` FOREIGN KEY (`tenant_id`) REFERENCES `tenants` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=4 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `newsletter_subscribers`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `newsletter_subscribers` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `tenant_id` int(11) NOT NULL,
  `email` varchar(255) NOT NULL,
  `user_id` int(11) DEFAULT NULL,
  `first_name` varchar(100) DEFAULT NULL,
  `last_name` varchar(100) DEFAULT NULL,
  `status` enum('pending','active','unsubscribed') DEFAULT 'pending',
  `confirmation_token` varchar(64) DEFAULT NULL,
  `confirmed_at` datetime DEFAULT NULL,
  `unsubscribe_token` varchar(64) NOT NULL,
  `unsubscribed_at` datetime DEFAULT NULL,
  `unsubscribe_reason` varchar(255) DEFAULT NULL,
  `source` enum('signup','import','manual','member_sync') DEFAULT 'signup',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  `is_active` tinyint(1) DEFAULT 0,
  PRIMARY KEY (`id`),
  UNIQUE KEY `unique_tenant_email` (`tenant_id`,`email`),
  KEY `user_id` (`user_id`),
  KEY `idx_tenant_status` (`tenant_id`,`status`),
  KEY `idx_confirmation_token` (`confirmation_token`),
  KEY `idx_unsubscribe_token` (`unsubscribe_token`),
  CONSTRAINT `newsletter_subscribers_ibfk_1` FOREIGN KEY (`tenant_id`) REFERENCES `tenants` (`id`) ON DELETE CASCADE,
  CONSTRAINT `newsletter_subscribers_ibfk_2` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB AUTO_INCREMENT=252 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `newsletter_suppression_list`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `newsletter_suppression_list` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `tenant_id` int(11) NOT NULL,
  `email` varchar(255) NOT NULL,
  `reason` enum('hard_bounce','repeated_soft_bounce','complaint','manual','unsubscribe') NOT NULL,
  `bounce_count` int(11) DEFAULT 1,
  `suppressed_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `expires_at` timestamp NULL DEFAULT NULL,
  `clicked_at` timestamp NULL DEFAULT NULL,
  `reset_token` varchar(255) DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `unique_tenant_email` (`tenant_id`,`email`),
  KEY `idx_email` (`email`),
  CONSTRAINT `newsletter_suppression_list_ibfk_1` FOREIGN KEY (`tenant_id`) REFERENCES `tenants` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=5 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `newsletter_templates`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `newsletter_templates` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `tenant_id` int(11) NOT NULL,
  `name` varchar(255) NOT NULL,
  `description` text DEFAULT NULL,
  `category` enum('starter','custom','saved') DEFAULT 'custom',
  `subject` varchar(255) DEFAULT NULL,
  `preview_text` varchar(255) DEFAULT NULL,
  `content` longtext DEFAULT NULL,
  `thumbnail` text DEFAULT NULL,
  `is_active` tinyint(1) DEFAULT 1,
  `use_count` int(11) DEFAULT 0,
  `created_by` int(11) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `created_by` (`created_by`),
  KEY `idx_tenant_active` (`tenant_id`,`is_active`),
  KEY `idx_category` (`category`),
  CONSTRAINT `newsletter_templates_ibfk_1` FOREIGN KEY (`tenant_id`) REFERENCES `tenants` (`id`) ON DELETE CASCADE,
  CONSTRAINT `newsletter_templates_ibfk_2` FOREIGN KEY (`created_by`) REFERENCES `users` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB AUTO_INCREMENT=2 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `newsletters`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `newsletters` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `tenant_id` int(11) NOT NULL,
  `name` varchar(255) DEFAULT NULL,
  `subject` varchar(255) NOT NULL,
  `preview_text` varchar(255) DEFAULT NULL,
  `content` longtext NOT NULL,
  `status` enum('draft','scheduled','sending','sent','failed') DEFAULT 'draft',
  `scheduled_at` datetime DEFAULT NULL,
  `sent_at` datetime DEFAULT NULL,
  `total_recipients` int(11) DEFAULT 0,
  `total_sent` int(11) DEFAULT 0,
  `total_failed` int(11) DEFAULT 0,
  `total_opens` int(11) DEFAULT 0,
  `unique_opens` int(11) DEFAULT 0,
  `total_clicks` int(11) DEFAULT 0,
  `unique_clicks` int(11) DEFAULT 0,
  `target_audience` enum('all_members','subscribers_only','both','segment') DEFAULT 'all_members',
  `segment_id` int(11) DEFAULT NULL,
  `target_counties` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`target_counties`)),
  `target_towns` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`target_towns`)),
  `target_groups` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`target_groups`)),
  `ab_test_enabled` tinyint(1) DEFAULT 0,
  `subject_b` varchar(255) DEFAULT NULL,
  `ab_split_percentage` int(11) DEFAULT 50,
  `ab_winner` varchar(1) DEFAULT NULL,
  `ab_winner_metric` varchar(20) DEFAULT 'opens',
  `ab_auto_select_winner` tinyint(1) DEFAULT 0,
  `ab_auto_select_after_hours` int(11) DEFAULT 24,
  `is_recurring` tinyint(1) DEFAULT 0,
  `recurring_frequency` enum('daily','weekly','biweekly','monthly') DEFAULT NULL,
  `recurring_day_of_week` tinyint(1) DEFAULT NULL,
  `recurring_day_of_month` tinyint(2) DEFAULT NULL,
  `recurring_time` time DEFAULT NULL,
  `recurring_timezone` varchar(50) DEFAULT 'Europe/Dublin',
  `recurring_next_send` datetime DEFAULT NULL,
  `recurring_last_sent` datetime DEFAULT NULL,
  `recurring_end_date` date DEFAULT NULL,
  `parent_newsletter_id` int(11) DEFAULT NULL,
  `created_by` int(11) NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  `recurring_day` varchar(10) DEFAULT NULL COMMENT 'Day of week: mon, tue, wed, thu, fri, sat, sun',
  `last_recurring_sent` datetime DEFAULT NULL,
  `template_id` int(11) DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `created_by` (`created_by`),
  KEY `idx_tenant_status` (`tenant_id`,`status`),
  KEY `idx_scheduled` (`scheduled_at`,`status`),
  KEY `segment_id` (`segment_id`),
  KEY `idx_recurring` (`is_recurring`,`recurring_next_send`),
  CONSTRAINT `newsletters_ibfk_1` FOREIGN KEY (`tenant_id`) REFERENCES `tenants` (`id`) ON DELETE CASCADE,
  CONSTRAINT `newsletters_ibfk_2` FOREIGN KEY (`created_by`) REFERENCES `users` (`id`) ON DELETE CASCADE,
  CONSTRAINT `newsletters_ibfk_3` FOREIGN KEY (`segment_id`) REFERENCES `newsletter_segments` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB AUTO_INCREMENT=21 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `nexus_score_cache`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `nexus_score_cache` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `tenant_id` int(11) NOT NULL,
  `user_id` int(11) NOT NULL,
  `total_score` decimal(6,2) NOT NULL COMMENT 'Total score out of 1000',
  `engagement_score` decimal(6,2) NOT NULL COMMENT 'Community Engagement score (250 max)',
  `quality_score` decimal(6,2) NOT NULL COMMENT 'Contribution Quality score (200 max)',
  `volunteer_score` decimal(6,2) NOT NULL COMMENT 'Volunteer Hours score (200 max)',
  `activity_score` decimal(6,2) NOT NULL COMMENT 'Platform Activity score (150 max)',
  `badge_score` decimal(6,2) NOT NULL COMMENT 'Badges & Achievements score (100 max)',
  `impact_score` decimal(6,2) NOT NULL COMMENT 'Social Impact score (100 max)',
  `percentile` int(11) NOT NULL COMMENT 'User percentile rank (0-100)',
  `tier` varchar(50) NOT NULL COMMENT 'User tier (Novice, Beginner, etc.)',
  `calculated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `unique_user_score` (`tenant_id`,`user_id`),
  KEY `idx_total_score` (`total_score`),
  KEY `idx_tenant` (`tenant_id`),
  KEY `idx_calculated` (`calculated_at`),
  KEY `user_id` (`user_id`),
  CONSTRAINT `nexus_score_cache_ibfk_1` FOREIGN KEY (`tenant_id`) REFERENCES `tenants` (`id`) ON DELETE CASCADE,
  CONSTRAINT `nexus_score_cache_ibfk_2` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=94 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Cached Nexus Score calculations for performance';
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `nexus_score_history`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `nexus_score_history` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `tenant_id` int(11) NOT NULL,
  `user_id` int(11) NOT NULL,
  `total_score` decimal(6,2) NOT NULL,
  `tier` varchar(50) NOT NULL,
  `snapshot_date` date NOT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `unique_user_snapshot` (`tenant_id`,`user_id`,`snapshot_date`),
  KEY `idx_user` (`user_id`,`snapshot_date`),
  KEY `idx_tenant` (`tenant_id`),
  CONSTRAINT `nexus_score_history_ibfk_1` FOREIGN KEY (`tenant_id`) REFERENCES `tenants` (`id`) ON DELETE CASCADE,
  CONSTRAINT `nexus_score_history_ibfk_2` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Historical Nexus Score snapshots for trend analysis';
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `nexus_score_milestones`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `nexus_score_milestones` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `tenant_id` int(11) NOT NULL,
  `user_id` int(11) NOT NULL,
  `milestone_type` enum('score_100','score_200','score_300','score_400','score_500','score_600','score_700','score_800','score_900','tier_beginner','tier_intermediate','tier_advanced','tier_expert','tier_elite','tier_legendary') NOT NULL,
  `milestone_name` varchar(255) NOT NULL,
  `score_at_milestone` decimal(6,2) NOT NULL,
  `achieved_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `unique_user_milestone` (`tenant_id`,`user_id`,`milestone_type`),
  KEY `idx_user` (`user_id`,`achieved_at`),
  KEY `idx_tenant` (`tenant_id`),
  CONSTRAINT `nexus_score_milestones_ibfk_1` FOREIGN KEY (`tenant_id`) REFERENCES `tenants` (`id`) ON DELETE CASCADE,
  CONSTRAINT `nexus_score_milestones_ibfk_2` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='User milestone achievements for Nexus Score';
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `nexus_scores`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `nexus_scores` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `tenant_id` int(11) NOT NULL,
  `user_id` int(11) NOT NULL,
  `total_score` decimal(6,2) NOT NULL DEFAULT 0.00 COMMENT 'Total score out of 1000',
  `engagement_score` decimal(6,2) NOT NULL DEFAULT 0.00 COMMENT 'Community Engagement score (250 max)',
  `quality_score` decimal(6,2) NOT NULL DEFAULT 0.00 COMMENT 'Contribution Quality score (200 max)',
  `volunteer_score` decimal(6,2) NOT NULL DEFAULT 0.00 COMMENT 'Volunteer Hours score (200 max)',
  `activity_score` decimal(6,2) NOT NULL DEFAULT 0.00 COMMENT 'Platform Activity score (150 max)',
  `badge_score` decimal(6,2) NOT NULL DEFAULT 0.00 COMMENT 'Badges & Achievements score (100 max)',
  `impact_score` decimal(6,2) NOT NULL DEFAULT 0.00 COMMENT 'Social Impact score (100 max)',
  `percentile` int(11) NOT NULL DEFAULT 0 COMMENT 'User percentile rank (0-100)',
  `tier` varchar(50) NOT NULL DEFAULT 'Novice' COMMENT 'User tier (Novice, Beginner, etc.)',
  `calculated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `unique_user_score` (`tenant_id`,`user_id`),
  KEY `idx_total_score` (`total_score`),
  KEY `idx_tenant` (`tenant_id`),
  KEY `idx_calculated` (`calculated_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Main Nexus Score table for user scoring';
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `notification_queue`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `notification_queue` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `user_id` int(11) NOT NULL,
  `activity_type` varchar(50) NOT NULL,
  `content_snippet` text DEFAULT NULL,
  `link` text DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `status` enum('pending','sent','failed') NOT NULL DEFAULT 'pending',
  `sent_at` datetime DEFAULT NULL,
  `frequency` enum('instant','daily','weekly') DEFAULT 'daily',
  `email_body` longtext DEFAULT NULL,
  `clicked_at` timestamp NULL DEFAULT NULL,
  `match` timestamp NULL DEFAULT NULL,
  `reset_token` varchar(255) DEFAULT NULL,
  `simplicity` varchar(255) DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `user_id` (`user_id`),
  CONSTRAINT `notification_queue_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=173 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `notification_settings`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `notification_settings` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `user_id` int(11) NOT NULL,
  `context_type` enum('global','group','thread') NOT NULL,
  `context_id` int(11) DEFAULT NULL,
  `frequency` enum('instant','daily','weekly','off') NOT NULL DEFAULT 'instant',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  `simplicity` varchar(255) DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `unique_user_context` (`user_id`,`context_type`,`context_id`),
  CONSTRAINT `notification_settings_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=3 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `notifications`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `notifications` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `user_id` int(11) NOT NULL,
  `message` text NOT NULL,
  `is_read` tinyint(1) DEFAULT 0,
  `deleted_at` timestamp NULL DEFAULT NULL,
  `created_at` datetime DEFAULT current_timestamp(),
  `link` varchar(255) DEFAULT NULL,
  `type` varchar(50) DEFAULT 'system',
  `actor_id` int(11) DEFAULT NULL,
  `read_at` timestamp NULL DEFAULT NULL,
  `tenant_id` int(11) DEFAULT NULL,
  `title` varchar(255) DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `user_id` (`user_id`),
  KEY `idx_deleted_at` (`deleted_at`),
  KEY `idx_notifications_tenant_id` (`tenant_id`),
  KEY `idx_notifications_is_read` (`is_read`),
  KEY `idx_notifications_created_at` (`created_at`),
  KEY `idx_notifications_user_read` (`user_id`,`is_read`),
  KEY `idx_notif_tenant_user_read_deleted` (`tenant_id`,`user_id`,`is_read`,`deleted_at`),
  CONSTRAINT `notifications_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=3521 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `org_alert_settings`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `org_alert_settings` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `tenant_id` int(11) NOT NULL,
  `organization_id` int(11) NOT NULL,
  `low_balance_threshold` decimal(10,2) DEFAULT 50.00 COMMENT 'Balance threshold to trigger low balance alert',
  `critical_balance_threshold` decimal(10,2) DEFAULT 10.00 COMMENT 'Balance threshold to trigger critical alert',
  `alerts_enabled` tinyint(1) DEFAULT 1 COMMENT 'Enable/disable alerts for this organization',
  `notification_emails` text DEFAULT NULL COMMENT 'Comma-separated list of additional emails to notify',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NULL DEFAULT NULL ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `unique_org_alerts` (`tenant_id`,`organization_id`),
  KEY `idx_tenant` (`tenant_id`),
  KEY `idx_organization` (`organization_id`),
  CONSTRAINT `org_alert_settings_ibfk_1` FOREIGN KEY (`tenant_id`) REFERENCES `tenants` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Organization wallet balance alert settings';
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `org_audit_log`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `org_audit_log` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `tenant_id` int(11) NOT NULL,
  `organization_id` int(11) DEFAULT NULL,
  `user_id` int(11) DEFAULT NULL,
  `target_user_id` int(11) DEFAULT NULL,
  `action` varchar(50) NOT NULL,
  `details` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`details`)),
  `ip_address` varchar(45) DEFAULT NULL,
  `user_agent` varchar(500) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `idx_org_audit` (`tenant_id`,`organization_id`,`created_at`),
  KEY `idx_user_audit` (`tenant_id`,`user_id`,`created_at`),
  KEY `idx_action` (`tenant_id`,`action`,`created_at`)
) ENGINE=InnoDB AUTO_INCREMENT=129 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `org_balance_alerts`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `org_balance_alerts` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `tenant_id` int(11) NOT NULL,
  `organization_id` int(11) NOT NULL,
  `alert_type` enum('low','critical') NOT NULL,
  `balance_at_alert` decimal(10,2) DEFAULT NULL COMMENT 'Balance when alert was triggered',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `idx_org_alerts` (`tenant_id`,`organization_id`,`created_at`),
  KEY `idx_alert_type` (`alert_type`),
  CONSTRAINT `org_balance_alerts_ibfk_1` FOREIGN KEY (`tenant_id`) REFERENCES `tenants` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=13 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Log of balance alerts sent to organizations';
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `org_members`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `org_members` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `tenant_id` int(11) NOT NULL,
  `organization_id` int(11) NOT NULL,
  `user_id` int(11) NOT NULL,
  `role` enum('owner','admin','member') DEFAULT 'member',
  `status` enum('active','pending','invited','removed') DEFAULT 'pending',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NULL DEFAULT NULL ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `unique_org_member` (`organization_id`,`user_id`),
  KEY `idx_org_members_tenant` (`tenant_id`),
  KEY `idx_org_members_user` (`user_id`),
  KEY `idx_org_members_org` (`organization_id`),
  KEY `idx_org_members_status` (`status`),
  KEY `idx_org_members_org_status` (`organization_id`,`status`)
) ENGINE=InnoDB AUTO_INCREMENT=8 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `org_transactions`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `org_transactions` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `tenant_id` int(11) NOT NULL,
  `organization_id` int(11) NOT NULL,
  `transfer_request_id` int(11) DEFAULT NULL,
  `sender_type` enum('organization','user') NOT NULL,
  `sender_id` int(11) NOT NULL,
  `receiver_type` enum('organization','user') NOT NULL,
  `receiver_id` int(11) NOT NULL,
  `amount` decimal(10,2) NOT NULL,
  `description` text DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `idx_org_trx_tenant` (`tenant_id`),
  KEY `idx_org_trx_org` (`organization_id`),
  KEY `idx_org_trx_date` (`created_at`),
  KEY `idx_org_trx_sender` (`sender_type`,`sender_id`),
  KEY `idx_org_trx_receiver` (`receiver_type`,`receiver_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `org_transfer_requests`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `org_transfer_requests` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `tenant_id` int(11) NOT NULL,
  `organization_id` int(11) NOT NULL,
  `requester_id` int(11) NOT NULL,
  `recipient_id` int(11) NOT NULL,
  `amount` decimal(10,2) NOT NULL,
  `description` text DEFAULT NULL,
  `status` enum('pending','approved','rejected','cancelled') DEFAULT 'pending',
  `approved_by` int(11) DEFAULT NULL,
  `approved_at` timestamp NULL DEFAULT NULL,
  `rejection_reason` text DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NULL DEFAULT NULL ON UPDATE current_timestamp(),
  `last_transaction` varchar(255) DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `idx_transfer_tenant` (`tenant_id`),
  KEY `idx_transfer_org` (`organization_id`),
  KEY `idx_transfer_status` (`status`),
  KEY `idx_transfer_requester` (`requester_id`),
  KEY `idx_transfer_recipient` (`recipient_id`),
  KEY `idx_org_transfer_requests_tenant_status` (`tenant_id`,`status`),
  KEY `idx_org_transfer_requests_org_status` (`organization_id`,`status`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `org_wallet_limits`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `org_wallet_limits` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `daily_limit` varchar(255) DEFAULT NULL,
  `monthly_limit` varchar(255) DEFAULT NULL,
  `org_daily_limit` varchar(255) DEFAULT NULL,
  `single_transaction_max` varchar(255) DEFAULT NULL,
  `tenant_id` int(11) DEFAULT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `org_wallets`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `org_wallets` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `tenant_id` int(11) NOT NULL,
  `organization_id` int(11) NOT NULL,
  `balance` decimal(10,2) DEFAULT 0.00,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NULL DEFAULT NULL ON UPDATE current_timestamp(),
  `last_transaction` varchar(255) DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `unique_org_wallet` (`tenant_id`,`organization_id`),
  KEY `idx_org_wallet_tenant` (`tenant_id`),
  KEY `idx_org_wallet_org` (`organization_id`),
  KEY `idx_org_wallets_tenant_balance` (`tenant_id`,`balance`)
) ENGINE=InnoDB AUTO_INCREMENT=5 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `organizations`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `organizations` (
  `id` int(11) unsigned NOT NULL AUTO_INCREMENT,
  `tenant_id` int(11) unsigned NOT NULL,
  `user_id` int(11) DEFAULT NULL,
  `name` varchar(255) NOT NULL,
  `description` text DEFAULT NULL,
  `website` varchar(500) DEFAULT NULL,
  `contact_email` varchar(255) DEFAULT NULL,
  `logo_url` varchar(500) DEFAULT NULL,
  `status` enum('active','inactive','pending') NOT NULL DEFAULT 'active',
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  `updated_at` datetime DEFAULT NULL ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `idx_organizations_tenant` (`tenant_id`),
  KEY `idx_organizations_tenant_status` (`tenant_id`,`status`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `outbound_webhook_logs`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `outbound_webhook_logs` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `tenant_id` int(10) unsigned NOT NULL,
  `created_at` datetime NOT NULL DEFAULT current_timestamp() COMMENT 'Record creation timestamp (distinct from attempted_at which updates on each retry)',
  `webhook_id` int(10) unsigned NOT NULL,
  `event_type` varchar(100) NOT NULL,
  `payload` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin NOT NULL CHECK (json_valid(`payload`)),
  `response_code` smallint(5) unsigned DEFAULT NULL,
  `response_body` text DEFAULT NULL,
  `response_time_ms` int(10) unsigned DEFAULT NULL,
  `status` enum('success','failed','pending','retrying') NOT NULL DEFAULT 'pending',
  `attempt_count` tinyint(3) unsigned NOT NULL DEFAULT 1,
  `next_retry_at` datetime DEFAULT NULL,
  `attempted_at` datetime NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `idx_webhook_logs_webhook` (`webhook_id`,`status`),
  KEY `idx_webhook_logs_tenant_date` (`tenant_id`,`attempted_at`),
  KEY `idx_webhook_logs_retry` (`status`,`next_retry_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `outbound_webhooks`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `outbound_webhooks` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `tenant_id` int(10) unsigned NOT NULL,
  `name` varchar(255) NOT NULL,
  `url` varchar(2048) NOT NULL,
  `secret` varchar(255) DEFAULT NULL,
  `events` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin NOT NULL CHECK (json_valid(`events`)),
  `headers` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`headers`)),
  `is_active` tinyint(1) NOT NULL DEFAULT 1,
  `last_triggered_at` datetime DEFAULT NULL,
  `failure_count` int(10) unsigned NOT NULL DEFAULT 0,
  `max_retries` tinyint(3) unsigned NOT NULL DEFAULT 3,
  `created_by` int(10) unsigned NOT NULL,
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  `updated_at` datetime NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `idx_outbound_webhooks_tenant` (`tenant_id`,`is_active`)
) ENGINE=InnoDB AUTO_INCREMENT=2 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `page_blocks`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `page_blocks` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `page_id` int(11) NOT NULL,
  `block_type` varchar(50) NOT NULL,
  `block_data` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin NOT NULL CHECK (json_valid(`block_data`)),
  `sort_order` int(11) NOT NULL DEFAULT 0,
  `created_at` datetime DEFAULT current_timestamp(),
  `updated_at` datetime DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `idx_page_sort` (`page_id`,`sort_order`),
  KEY `idx_block_type` (`block_type`),
  CONSTRAINT `page_blocks_ibfk_1` FOREIGN KEY (`page_id`) REFERENCES `pages` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `page_versions`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `page_versions` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `page_id` int(11) NOT NULL,
  `tenant_id` int(11) NOT NULL,
  `version_number` int(11) NOT NULL DEFAULT 1,
  `title` varchar(255) NOT NULL,
  `slug` varchar(255) NOT NULL,
  `content` longtext DEFAULT NULL,
  `created_by` int(11) DEFAULT NULL,
  `created_at` datetime DEFAULT current_timestamp(),
  `restore_note` varchar(255) DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `idx_page_versions_page` (`page_id`),
  KEY `idx_page_versions_tenant` (`tenant_id`),
  CONSTRAINT `page_versions_ibfk_1` FOREIGN KEY (`page_id`) REFERENCES `pages` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `pages`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `pages` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `tenant_id` int(11) NOT NULL,
  `title` varchar(255) NOT NULL,
  `slug` varchar(255) NOT NULL,
  `content` longtext DEFAULT NULL,
  `builder_version` varchar(10) DEFAULT 'v2',
  `is_published` tinyint(1) DEFAULT 0,
  `sort_order` int(11) DEFAULT 0,
  `show_in_menu` tinyint(1) DEFAULT 0,
  `menu_location` varchar(20) DEFAULT 'about',
  `publish_at` datetime DEFAULT NULL,
  `menu_order` int(11) DEFAULT 0,
  `created_at` datetime DEFAULT current_timestamp(),
  `updated_at` datetime DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  `meta_description` varchar(500) DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `unique_slug_tenant` (`slug`,`tenant_id`),
  KEY `tenant_id` (`tenant_id`),
  KEY `idx_pages_publish_at` (`publish_at`),
  KEY `idx_pages_sort_order` (`tenant_id`,`sort_order`),
  KEY `idx_pages_menu` (`tenant_id`,`is_published`,`show_in_menu`,`menu_location`)
) ENGINE=InnoDB AUTO_INCREMENT=81 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `password_resets`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `password_resets` (
  `email` varchar(255) NOT NULL,
  `token` varchar(255) NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `pay_plans`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `pay_plans` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `name` varchar(100) NOT NULL,
  `slug` varchar(50) NOT NULL,
  `description` text DEFAULT NULL,
  `tier_level` int(11) NOT NULL DEFAULT 0,
  `features` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL COMMENT 'Feature flags available to this plan' CHECK (json_valid(`features`)),
  `allowed_layouts` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL COMMENT 'Array of layout names allowed for this plan' CHECK (json_valid(`allowed_layouts`)),
  `max_menus` int(11) DEFAULT 5 COMMENT 'Maximum number of custom menus allowed',
  `max_menu_items` int(11) DEFAULT 50 COMMENT 'Maximum menu items per menu',
  `price_monthly` decimal(10,2) DEFAULT 0.00,
  `price_yearly` decimal(10,2) DEFAULT 0.00,
  `is_active` tinyint(1) DEFAULT 1,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NULL DEFAULT NULL ON UPDATE current_timestamp(),
  `stripe_product_id` varchar(255) DEFAULT NULL,
  `stripe_price_id_monthly` varchar(255) DEFAULT NULL,
  `stripe_price_id_yearly` varchar(255) DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `slug` (`slug`),
  KEY `idx_pay_plans_tier` (`tier_level`),
  KEY `idx_pay_plans_active` (`is_active`)
) ENGINE=InnoDB AUTO_INCREMENT=5 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `peer_endorsements`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `peer_endorsements` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `tenant_id` int(10) unsigned NOT NULL,
  `endorser_id` int(10) unsigned NOT NULL,
  `endorsed_id` int(10) unsigned NOT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uniq_peer_endorsement` (`tenant_id`,`endorser_id`,`endorsed_id`),
  KEY `idx_pe_tenant` (`tenant_id`),
  KEY `idx_pe_endorsed` (`endorsed_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `permission_audit_log`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `permission_audit_log` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `event_type` enum('role_assigned','role_revoked','permission_granted','permission_revoked','permission_checked','access_denied') NOT NULL,
  `user_id` int(11) NOT NULL COMMENT 'User affected by this event',
  `role_id` int(10) unsigned DEFAULT NULL COMMENT 'Role involved (if applicable)',
  `permission_id` int(10) unsigned DEFAULT NULL COMMENT 'Permission involved (if applicable)',
  `actor_id` int(11) DEFAULT NULL COMMENT 'User who performed this action',
  `ip_address` varchar(45) DEFAULT NULL COMMENT 'IP address of requester',
  `user_agent` text DEFAULT NULL COMMENT 'Browser/client user agent',
  `reason` text DEFAULT NULL COMMENT 'Why this action was taken',
  `metadata` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL COMMENT 'Additional context' CHECK (json_valid(`metadata`)),
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `permission_name` varchar(100) DEFAULT NULL COMMENT 'Permission name at time of check',
  `resource_type` varchar(50) DEFAULT NULL COMMENT 'Type of resource accessed',
  `resource_id` int(10) unsigned DEFAULT NULL COMMENT 'ID of resource accessed',
  `result` enum('granted','denied') DEFAULT NULL COMMENT 'Result of permission check',
  PRIMARY KEY (`id`),
  KEY `idx_event_type` (`event_type`),
  KEY `idx_user` (`user_id`),
  KEY `idx_actor` (`actor_id`),
  KEY `idx_created` (`created_at`)
) ENGINE=InnoDB AUTO_INCREMENT=67 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `permissions`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `permissions` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `name` varchar(100) NOT NULL COMMENT 'Permission identifier (e.g., users.delete)',
  `display_name` varchar(150) NOT NULL COMMENT 'Human-readable name',
  `description` text DEFAULT NULL COMMENT 'Detailed description of what this permission allows',
  `category` varchar(50) NOT NULL COMMENT 'Permission category (users, gdpr, config, etc.)',
  `is_dangerous` tinyint(1) DEFAULT 0 COMMENT 'Requires extra confirmation (delete, ban, etc.)',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  `tenant_id` int(10) unsigned DEFAULT NULL COMMENT 'NULL = global, otherwise tenant-specific',
  `direct_grant` varchar(255) DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `name` (`name`),
  KEY `idx_category` (`category`),
  KEY `idx_tenant` (`tenant_id`)
) ENGINE=InnoDB AUTO_INCREMENT=75 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `personal_access_tokens`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `personal_access_tokens` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `tokenable_type` varchar(255) NOT NULL,
  `tokenable_id` bigint(20) unsigned NOT NULL,
  `name` varchar(255) NOT NULL,
  `token` varchar(64) NOT NULL,
  `abilities` text DEFAULT NULL,
  `last_used_at` timestamp NULL DEFAULT NULL,
  `expires_at` timestamp NULL DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `personal_access_tokens_token_unique` (`token`),
  KEY `personal_access_tokens_tokenable_type_tokenable_id_index` (`tokenable_type`,`tokenable_id`)
) ENGINE=InnoDB AUTO_INCREMENT=115 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `poll_options`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `poll_options` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `poll_id` int(11) NOT NULL,
  `tenant_id` int(11) NOT NULL DEFAULT 0,
  `label` varchar(255) NOT NULL,
  `expires_at` timestamp NULL DEFAULT NULL,
  `votes` int(11) NOT NULL DEFAULT 0 COMMENT 'Cached vote count for this option',
  PRIMARY KEY (`id`),
  KEY `idx_opt_poll` (`poll_id`),
  KEY `idx_poll_options_tenant` (`tenant_id`,`poll_id`)
) ENGINE=InnoDB AUTO_INCREMENT=13 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `poll_rankings`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `poll_rankings` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `poll_id` int(10) unsigned NOT NULL,
  `user_id` int(10) unsigned NOT NULL,
  `option_id` int(10) unsigned NOT NULL,
  `rank` int(10) unsigned NOT NULL COMMENT 'Rank position (1 = first choice)',
  `tenant_id` int(10) unsigned NOT NULL,
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `idx_poll_rankings_unique` (`poll_id`,`user_id`,`option_id`),
  KEY `idx_poll_rankings_poll` (`poll_id`),
  KEY `idx_poll_rankings_user` (`poll_id`,`user_id`),
  KEY `idx_poll_rankings_tenant` (`tenant_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `poll_votes`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `poll_votes` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `poll_id` int(11) NOT NULL,
  `tenant_id` int(11) NOT NULL DEFAULT 0,
  `option_id` int(11) NOT NULL,
  `user_id` int(11) NOT NULL,
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  `expires_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `idx_vote_unique` (`poll_id`,`user_id`),
  KEY `idx_poll_votes_tenant` (`tenant_id`,`poll_id`)
) ENGINE=InnoDB AUTO_INCREMENT=20 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `polls`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `polls` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `tenant_id` int(11) NOT NULL,
  `user_id` int(11) NOT NULL,
  `event_id` int(10) unsigned DEFAULT NULL,
  `question` varchar(255) NOT NULL,
  `description` text DEFAULT NULL,
  `end_date` datetime DEFAULT NULL,
  `is_active` tinyint(1) DEFAULT 1,
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  `expires_at` timestamp NULL DEFAULT NULL,
  `poll_type` enum('standard','ranked') NOT NULL DEFAULT 'standard' COMMENT 'Type of poll voting mechanism',
  `category` varchar(100) DEFAULT NULL COMMENT 'Poll category (e.g., governance, feedback, social)',
  `tags` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL COMMENT 'JSON array of tag strings' CHECK (json_valid(`tags`)),
  `is_anonymous` tinyint(1) NOT NULL DEFAULT 0 COMMENT 'When true, voter identities are hidden in results',
  PRIMARY KEY (`id`),
  KEY `idx_polls_tenant` (`tenant_id`),
  KEY `polls_event_id_index` (`event_id`)
) ENGINE=InnoDB AUTO_INCREMENT=7 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `post_hashtags`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `post_hashtags` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `post_id` int(11) NOT NULL,
  `hashtag_id` int(11) NOT NULL,
  `tenant_id` int(11) NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `unique_post_tag` (`post_id`,`hashtag_id`),
  KEY `idx_hashtag` (`hashtag_id`,`tenant_id`),
  KEY `idx_post` (`post_id`),
  CONSTRAINT `fk_post_hashtags_post` FOREIGN KEY (`post_id`) REFERENCES `feed_posts` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_post_hashtags_tag` FOREIGN KEY (`hashtag_id`) REFERENCES `hashtags` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `post_likes`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `post_likes` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `tenant_id` int(11) NOT NULL,
  `post_id` int(11) NOT NULL COMMENT 'ID of the feed_posts entry',
  `user_id` int(11) NOT NULL COMMENT 'User who liked the post',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `award` varchar(255) DEFAULT NULL,
  `lov` varchar(255) DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `unique_like` (`tenant_id`,`post_id`,`user_id`),
  KEY `idx_post` (`post_id`),
  KEY `idx_user` (`user_id`),
  KEY `idx_tenant` (`tenant_id`),
  KEY `idx_created` (`created_at`),
  CONSTRAINT `post_likes_ibfk_1` FOREIGN KEY (`tenant_id`) REFERENCES `tenants` (`id`) ON DELETE CASCADE,
  CONSTRAINT `post_likes_ibfk_2` FOREIGN KEY (`post_id`) REFERENCES `feed_posts` (`id`) ON DELETE CASCADE,
  CONSTRAINT `post_likes_ibfk_3` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Track likes on feed posts for gamification';
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `post_link_previews`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `post_link_previews` (
  `post_id` bigint(20) unsigned NOT NULL,
  `link_preview_id` bigint(20) unsigned NOT NULL,
  `display_order` tinyint(3) unsigned NOT NULL DEFAULT 0,
  PRIMARY KEY (`post_id`,`link_preview_id`),
  KEY `idx_preview` (`link_preview_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `post_media`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `post_media` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `tenant_id` int(10) unsigned NOT NULL,
  `post_id` bigint(20) unsigned NOT NULL,
  `media_type` enum('image','video') NOT NULL DEFAULT 'image',
  `file_url` text NOT NULL,
  `thumbnail_url` text DEFAULT NULL,
  `alt_text` varchar(500) DEFAULT NULL,
  `width` int(10) unsigned DEFAULT NULL,
  `height` int(10) unsigned DEFAULT NULL,
  `file_size` int(10) unsigned DEFAULT NULL,
  `display_order` tinyint(3) unsigned NOT NULL DEFAULT 0,
  `created_at` timestamp NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NULL DEFAULT NULL ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `idx_post_media` (`tenant_id`,`post_id`),
  KEY `idx_display_order` (`post_id`,`display_order`)
) ENGINE=InnoDB AUTO_INCREMENT=55 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `post_reactions`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `post_reactions` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `tenant_id` int(10) unsigned NOT NULL,
  `post_id` bigint(20) unsigned NOT NULL,
  `user_id` int(10) unsigned NOT NULL,
  `reaction_type` enum('love','like','laugh','wow','sad','celebrate','clap','time_credit') NOT NULL,
  `created_at` timestamp NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `uk_user_post_reaction` (`tenant_id`,`post_id`,`user_id`),
  KEY `idx_post_reactions` (`tenant_id`,`post_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `post_shares`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `post_shares` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `user_id` int(10) unsigned NOT NULL COMMENT 'User who shared',
  `post_id` int(10) unsigned NOT NULL COMMENT 'Post that was shared',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `tenant_id` int(11) NOT NULL DEFAULT 1,
  `original_post_id` int(11) NOT NULL DEFAULT 0 COMMENT 'Original feed_posts.id',
  `original_type` varchar(50) NOT NULL DEFAULT 'post' COMMENT 'post, listing, event',
  `shared_post_id` int(11) DEFAULT NULL COMMENT 'The new feed_posts.id created by sharing',
  `comment` text DEFAULT NULL COMMENT 'Optional comment when sharing',
  PRIMARY KEY (`id`),
  KEY `idx_post` (`post_id`),
  KEY `idx_user` (`user_id`),
  KEY `idx_original` (`original_post_id`,`tenant_id`),
  KEY `idx_user_tenant` (`user_id`,`tenant_id`),
  KEY `idx_created_tenant` (`tenant_id`,`created_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `post_views`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `post_views` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `tenant_id` bigint(20) unsigned NOT NULL,
  `post_id` bigint(20) unsigned NOT NULL,
  `user_id` bigint(20) unsigned DEFAULT NULL,
  `ip_hash` varchar(64) DEFAULT NULL,
  `viewed_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `post_views_user_unique` (`tenant_id`,`post_id`,`user_id`),
  UNIQUE KEY `post_views_ip_unique` (`tenant_id`,`post_id`,`ip_hash`),
  KEY `post_views_tenant_id_index` (`tenant_id`),
  KEY `post_views_post_id_index` (`post_id`)
) ENGINE=InnoDB AUTO_INCREMENT=56 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `posts`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `posts` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `tenant_id` int(11) NOT NULL,
  `author_id` int(11) NOT NULL,
  `title` varchar(255) NOT NULL,
  `slug` varchar(255) NOT NULL,
  `excerpt` text DEFAULT NULL,
  `content` longtext DEFAULT NULL,
  `featured_image` varchar(255) DEFAULT NULL,
  `status` enum('draft','published') DEFAULT 'draft',
  `created_at` datetime DEFAULT current_timestamp(),
  `updated_at` datetime DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  `category_id` int(11) DEFAULT NULL,
  `content_json` longtext DEFAULT NULL,
  `html_render` longtext DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `unique_tenant_slug` (`tenant_id`,`slug`),
  KEY `idx_tenant_status` (`tenant_id`,`status`),
  KEY `fk_posts_author` (`author_id`),
  KEY `fk_posts_category` (`category_id`),
  CONSTRAINT `fk_posts_author` FOREIGN KEY (`author_id`) REFERENCES `users` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_posts_category` FOREIGN KEY (`category_id`) REFERENCES `categories` (`id`) ON DELETE SET NULL,
  CONSTRAINT `fk_posts_tenant` FOREIGN KEY (`tenant_id`) REFERENCES `tenants` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=90100 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `progress_notifications`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `progress_notifications` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `tenant_id` int(11) NOT NULL,
  `user_id` int(11) NOT NULL,
  `badge_key` varchar(50) NOT NULL,
  `threshold` int(11) NOT NULL,
  `sent_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `last_login` varchar(255) DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `unique_progress_notif` (`user_id`,`badge_key`,`threshold`),
  KEY `idx_user` (`user_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb3 COLLATE=utf8mb3_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `proposal_votes`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `proposal_votes` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `proposal_id` int(11) NOT NULL,
  `user_id` int(11) NOT NULL,
  `choice` varchar(50) NOT NULL COMMENT 'yes, no, abstain',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `unique_vote` (`proposal_id`,`user_id`),
  KEY `user_id` (`user_id`),
  CONSTRAINT `proposal_votes_ibfk_1` FOREIGN KEY (`proposal_id`) REFERENCES `proposals` (`id`) ON DELETE CASCADE,
  CONSTRAINT `proposal_votes_ibfk_2` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `proposals`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `proposals` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `group_id` int(11) NOT NULL,
  `user_id` int(11) NOT NULL,
  `title` varchar(255) NOT NULL,
  `description` text DEFAULT NULL,
  `deadline` datetime DEFAULT NULL,
  `status` varchar(20) DEFAULT 'active',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `group_id` (`group_id`),
  KEY `user_id` (`user_id`),
  CONSTRAINT `proposals_ibfk_1` FOREIGN KEY (`group_id`) REFERENCES `groups` (`id`) ON DELETE CASCADE,
  CONSTRAINT `proposals_ibfk_2` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=2 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `push_subscriptions`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `push_subscriptions` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `user_id` int(11) NOT NULL,
  `tenant_id` int(11) DEFAULT NULL,
  `endpoint` text NOT NULL,
  `p256dh_key` text DEFAULT NULL,
  `auth_key` varchar(255) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  `content` text DEFAULT NULL,
  `errors` varchar(255) DEFAULT NULL,
  `pages` varchar(255) DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `user_id` (`user_id`),
  CONSTRAINT `push_subscriptions_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=160 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `rating`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `rating` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `reactions`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `reactions` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `user_id` int(11) NOT NULL,
  `tenant_id` int(11) NOT NULL,
  `target_type` varchar(50) NOT NULL COMMENT 'Type of content: post, listing, event, poll, volunteering, comment',
  `target_id` int(11) NOT NULL COMMENT 'ID of the reacted content',
  `emoji` varchar(50) NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `reactions_unique` (`tenant_id`,`user_id`,`target_type`,`target_id`),
  KEY `idx_target` (`target_type`,`target_id`),
  KEY `idx_user_target` (`user_id`,`target_type`,`target_id`),
  KEY `idx_tenant` (`tenant_id`),
  KEY `idx_emoji` (`emoji`),
  CONSTRAINT `reactions_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE,
  CONSTRAINT `reactions_ibfk_2` FOREIGN KEY (`tenant_id`) REFERENCES `tenants` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=22 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `recurring_shift_patterns`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `recurring_shift_patterns` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `tenant_id` int(11) NOT NULL,
  `opportunity_id` int(11) NOT NULL,
  `created_by` int(11) NOT NULL,
  `title` varchar(255) DEFAULT NULL,
  `frequency` enum('daily','weekly','biweekly','monthly') NOT NULL DEFAULT 'weekly',
  `days_of_week` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`days_of_week`)),
  `start_time` time NOT NULL,
  `end_time` time NOT NULL,
  `spots_per_shift` int(10) unsigned NOT NULL DEFAULT 1,
  `capacity` int(10) unsigned NOT NULL DEFAULT 1,
  `start_date` date NOT NULL,
  `end_date` date DEFAULT NULL,
  `max_occurrences` int(10) unsigned DEFAULT NULL,
  `occurrences_generated` int(10) unsigned NOT NULL DEFAULT 0,
  `is_active` tinyint(1) NOT NULL DEFAULT 1,
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  `updated_at` datetime NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `idx_rsp_tenant` (`tenant_id`),
  KEY `idx_rsp_opportunity` (`opportunity_id`),
  KEY `idx_rsp_active` (`is_active`,`tenant_id`),
  KEY `fk_rsp_creator` (`created_by`),
  CONSTRAINT `fk_rsp_creator` FOREIGN KEY (`created_by`) REFERENCES `users` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_rsp_opportunity` FOREIGN KEY (`opportunity_id`) REFERENCES `vol_opportunities` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_rsp_tenant` FOREIGN KEY (`tenant_id`) REFERENCES `tenants` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `referral_tracking`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `referral_tracking` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `tenant_id` int(11) NOT NULL,
  `referrer_id` int(11) NOT NULL,
  `referred_id` int(11) NOT NULL,
  `referral_code` varchar(50) DEFAULT NULL,
  `status` enum('pending','active','qualified') DEFAULT 'pending',
  `qualified_at` timestamp NULL DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `activated_at` timestamp NULL DEFAULT NULL,
  `claimed_at` timestamp NULL DEFAULT NULL,
  `engaged_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `unique_referral` (`referrer_id`,`referred_id`),
  KEY `idx_referrer` (`referrer_id`),
  KEY `idx_tenant` (`tenant_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb3 COLLATE=utf8mb3_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `reports`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `reports` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `tenant_id` int(11) NOT NULL,
  `reporter_id` int(11) NOT NULL,
  `target_type` enum('listing','user','message') NOT NULL,
  `target_id` int(11) NOT NULL,
  `reason` text NOT NULL,
  `status` enum('open','resolved','dismissed') NOT NULL DEFAULT 'open',
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  `updated_at` datetime NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `tenant_status` (`tenant_id`,`status`),
  KEY `idx_reports_status` (`status`,`tenant_id`)
) ENGINE=InnoDB AUTO_INCREMENT=8 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `resource_categories`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `resource_categories` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `tenant_id` int(10) unsigned NOT NULL,
  `name` varchar(255) NOT NULL,
  `slug` varchar(255) NOT NULL,
  `parent_id` int(10) unsigned DEFAULT NULL COMMENT 'Parent category for hierarchy',
  `sort_order` int(11) NOT NULL DEFAULT 0,
  `icon` varchar(100) DEFAULT NULL COMMENT 'Icon identifier (e.g., lucide icon name)',
  `description` text DEFAULT NULL,
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  `updated_at` datetime DEFAULT NULL ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `idx_resource_categories_tenant` (`tenant_id`),
  KEY `idx_resource_categories_parent` (`parent_id`),
  KEY `idx_resource_categories_slug` (`tenant_id`,`slug`),
  KEY `idx_resource_categories_sort` (`tenant_id`,`sort_order`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `resource_items`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `resource_items` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `resources`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `resources` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `tenant_id` int(11) NOT NULL,
  `user_id` int(11) NOT NULL,
  `category_id` int(11) DEFAULT NULL,
  `title` varchar(255) NOT NULL,
  `description` text DEFAULT NULL,
  `file_path` varchar(255) NOT NULL,
  `file_type` varchar(50) DEFAULT NULL,
  `file_size` int(11) DEFAULT 0,
  `downloads` int(11) DEFAULT 0,
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  `content_type` enum('plain','html','markdown') NOT NULL DEFAULT 'plain' COMMENT 'Content format type for rich content rendering',
  `content_body` text DEFAULT NULL COMMENT 'Rich content body (HTML/Markdown) for text-based resources',
  `sort_order` int(11) NOT NULL DEFAULT 0 COMMENT 'Sort order for manual ordering (lower = first)',
  PRIMARY KEY (`id`),
  KEY `idx_res_tenant` (`tenant_id`)
) ENGINE=InnoDB AUTO_INCREMENT=11 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `review_responses`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `review_responses` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `review_id` int(11) NOT NULL,
  `responder_id` int(11) NOT NULL,
  `response` text NOT NULL,
  `status` enum('visible','hidden') NOT NULL DEFAULT 'visible',
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `idx_review` (`review_id`),
  CONSTRAINT `review_responses_ibfk_1` FOREIGN KEY (`review_id`) REFERENCES `reviews` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `review_votes`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `review_votes` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `review_id` int(11) NOT NULL,
  `user_id` int(11) NOT NULL,
  `vote` tinyint(4) NOT NULL DEFAULT 1,
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `unique_vote` (`review_id`,`user_id`),
  CONSTRAINT `review_votes_ibfk_1` FOREIGN KEY (`review_id`) REFERENCES `reviews` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `reviews`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `reviews` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `tenant_id` int(10) unsigned NOT NULL DEFAULT 1,
  `reviewer_id` int(11) NOT NULL,
  `reviewer_tenant_id` int(10) unsigned DEFAULT NULL,
  `receiver_id` int(11) NOT NULL,
  `receiver_tenant_id` int(10) unsigned DEFAULT NULL,
  `transaction_id` int(11) DEFAULT NULL,
  `federation_transaction_id` int(10) unsigned DEFAULT NULL,
  `group_id` int(11) DEFAULT NULL,
  `rating` int(11) NOT NULL,
  `comment` text DEFAULT NULL,
  `review_type` enum('local','federated') NOT NULL DEFAULT 'local',
  `dimensions` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`dimensions`)),
  `status` enum('pending','approved','rejected') DEFAULT 'approved',
  `is_anonymous` tinyint(1) NOT NULL DEFAULT 0,
  `show_cross_tenant` tinyint(1) NOT NULL DEFAULT 1,
  `created_at` datetime DEFAULT current_timestamp(),
  `updated_at` datetime DEFAULT NULL ON UPDATE current_timestamp(),
  `award` varchar(255) DEFAULT NULL,
  `blocker_user_id` int(11) DEFAULT NULL,
  `match` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `reviewer_id` (`reviewer_id`),
  KEY `receiver_id` (`receiver_id`),
  KEY `idx_reviews_group` (`group_id`),
  KEY `idx_tenant_id` (`tenant_id`),
  KEY `idx_review_status` (`status`),
  KEY `idx_federation_transaction` (`federation_transaction_id`),
  KEY `idx_tenant_receiver` (`receiver_tenant_id`,`receiver_id`),
  CONSTRAINT `reviews_ibfk_1` FOREIGN KEY (`reviewer_id`) REFERENCES `users` (`id`) ON DELETE CASCADE,
  CONSTRAINT `reviews_ibfk_2` FOREIGN KEY (`receiver_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=21 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `revoked_tokens`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `revoked_tokens` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `user_id` int(11) NOT NULL,
  `jti` varchar(64) NOT NULL COMMENT 'Token unique identifier (from JWT jti claim)',
  `revoked_at` datetime DEFAULT current_timestamp(),
  `expires_at` datetime NOT NULL COMMENT 'When this revocation record can be cleaned up (token expiry time)',
  PRIMARY KEY (`id`),
  UNIQUE KEY `idx_jti` (`jti`),
  KEY `idx_user_id` (`user_id`),
  KEY `idx_expires_at` (`expires_at`),
  CONSTRAINT `revoked_tokens_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=143 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `role_permissions`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `role_permissions` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `role_id` int(10) unsigned NOT NULL,
  `permission_id` int(10) unsigned NOT NULL,
  `granted_by` int(10) unsigned DEFAULT NULL COMMENT 'User ID who granted this permission',
  `granted_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `tenant_id` int(10) unsigned DEFAULT NULL COMMENT 'NULL = global, otherwise tenant-specific',
  PRIMARY KEY (`id`),
  UNIQUE KEY `unique_role_permission` (`role_id`,`permission_id`),
  KEY `idx_role` (`role_id`),
  KEY `idx_permission` (`permission_id`),
  CONSTRAINT `role_permissions_ibfk_1` FOREIGN KEY (`role_id`) REFERENCES `roles` (`id`) ON DELETE CASCADE,
  CONSTRAINT `role_permissions_ibfk_2` FOREIGN KEY (`permission_id`) REFERENCES `permissions` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=97 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `roles`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `roles` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `name` varchar(100) NOT NULL COMMENT 'Role identifier (e.g., gdpr_officer)',
  `display_name` varchar(150) NOT NULL COMMENT 'Human-readable role name',
  `description` text DEFAULT NULL COMMENT 'Role purpose and responsibilities',
  `level` int(10) unsigned DEFAULT 0 COMMENT 'Role hierarchy level (higher = more privileges)',
  `is_system` tinyint(1) DEFAULT 0 COMMENT 'System roles cannot be deleted',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  `tenant_id` int(10) unsigned DEFAULT NULL COMMENT 'NULL = global, otherwise tenant-specific',
  PRIMARY KEY (`id`),
  UNIQUE KEY `name` (`name`),
  KEY `idx_level` (`level`),
  KEY `idx_tenant` (`tenant_id`)
) ENGINE=InnoDB AUTO_INCREMENT=11 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `safeguarding_assignments`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `safeguarding_assignments` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `guardian_user_id` int(11) NOT NULL,
  `ward_user_id` int(11) NOT NULL,
  `tenant_id` int(11) NOT NULL,
  `assigned_by` int(11) NOT NULL,
  `assigned_at` datetime NOT NULL DEFAULT current_timestamp(),
  `consent_given_at` datetime DEFAULT NULL,
  `revoked_at` datetime DEFAULT NULL,
  `notes` text DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uk_safeguard_pair` (`guardian_user_id`,`ward_user_id`,`tenant_id`),
  KEY `idx_safeguard_guardian` (`guardian_user_id`),
  KEY `idx_safeguard_ward` (`ward_user_id`),
  KEY `idx_safeguard_tenant` (`tenant_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `safeguarding_flagged_messages`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `safeguarding_flagged_messages` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `message_id` int(11) NOT NULL,
  `tenant_id` int(11) NOT NULL,
  `flagged_reason` varchar(255) NOT NULL DEFAULT 'keyword_match',
  `matched_keyword` varchar(100) DEFAULT NULL,
  `reviewed_by` int(11) DEFAULT NULL,
  `reviewed_at` datetime DEFAULT NULL,
  `review_notes` text DEFAULT NULL,
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `idx_flagged_tenant` (`tenant_id`),
  KEY `idx_flagged_message` (`message_id`),
  KEY `idx_flagged_reviewed` (`reviewed_by`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `salary_benchmarks`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `salary_benchmarks` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `tenant_id` int(10) unsigned DEFAULT NULL COMMENT 'NULL = global benchmark',
  `role_keyword` varchar(100) NOT NULL COMMENT 'matched against job titles',
  `industry` varchar(100) DEFAULT NULL,
  `location` varchar(100) DEFAULT NULL COMMENT 'country or region',
  `salary_min` decimal(10,2) NOT NULL,
  `salary_max` decimal(10,2) NOT NULL,
  `salary_median` decimal(10,2) NOT NULL,
  `salary_type` enum('hourly','monthly','annual') NOT NULL DEFAULT 'annual',
  `currency` varchar(10) NOT NULL DEFAULT 'EUR',
  `year` smallint(6) NOT NULL DEFAULT 2026,
  `source` varchar(200) DEFAULT NULL,
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `idx_benchmark_role` (`role_keyword`)
) ENGINE=InnoDB AUTO_INCREMENT=21 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `saved_jobs`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `saved_jobs` (
  `id` int(11) unsigned NOT NULL AUTO_INCREMENT,
  `user_id` int(11) NOT NULL,
  `job_id` int(11) unsigned NOT NULL,
  `tenant_id` int(11) unsigned NOT NULL,
  `saved_at` datetime NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_saved_job_user` (`user_id`,`job_id`),
  KEY `idx_saved_jobs_tenant_user` (`tenant_id`,`user_id`),
  KEY `idx_saved_jobs_job` (`job_id`),
  CONSTRAINT `fk_saved_jobs_vacancy` FOREIGN KEY (`job_id`) REFERENCES `job_vacancies` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `saved_searches`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `saved_searches` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `tenant_id` int(10) unsigned NOT NULL,
  `user_id` int(10) unsigned NOT NULL,
  `name` varchar(255) NOT NULL,
  `query_params` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin NOT NULL COMMENT 'Search query and filters as JSON' CHECK (json_valid(`query_params`)),
  `notify_on_new` tinyint(1) NOT NULL DEFAULT 0,
  `last_run_at` datetime DEFAULT NULL,
  `last_notified_at` timestamp NULL DEFAULT NULL,
  `last_result_count` int(10) unsigned DEFAULT NULL,
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  `updated_at` datetime NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `idx_saved_searches_user` (`tenant_id`,`user_id`),
  KEY `idx_saved_searches_notify` (`tenant_id`,`notify_on_new`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `score`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `score` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `search_feedback`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `search_feedback` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `tenant_id` int(11) NOT NULL,
  `user_id` int(11) NOT NULL,
  `query` varchar(500) NOT NULL,
  `result_id` int(11) NOT NULL,
  `result_type` enum('listing','user','event','group') NOT NULL,
  `action` enum('click','skip','helpful','not_helpful') NOT NULL,
  `position` tinyint(3) unsigned DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `idx_tenant_user` (`tenant_id`,`user_id`),
  KEY `idx_query` (`tenant_id`,`query`(255)),
  KEY `idx_result` (`result_type`,`result_id`),
  KEY `idx_created` (`created_at`),
  KEY `fk_search_feedback_user` (`user_id`),
  CONSTRAINT `fk_search_feedback_tenant` FOREIGN KEY (`tenant_id`) REFERENCES `tenants` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_search_feedback_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `search_logs`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `search_logs` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `tenant_id` int(11) NOT NULL,
  `user_id` int(11) DEFAULT NULL,
  `query` varchar(500) NOT NULL,
  `search_type` varchar(50) DEFAULT 'general',
  `result_count` int(11) DEFAULT 0,
  `filters` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`filters`)),
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `idx_tenant_user` (`tenant_id`,`user_id`),
  KEY `idx_tenant_created` (`tenant_id`,`created_at`),
  KEY `idx_query_trending` (`tenant_id`,`query`(255),`created_at`),
  KEY `fk_search_logs_user` (`user_id`),
  CONSTRAINT `fk_search_logs_tenant` FOREIGN KEY (`tenant_id`) REFERENCES `tenants` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_search_logs_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=9 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `season_rankings`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `season_rankings` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `season_id` int(11) NOT NULL,
  `user_id` int(11) NOT NULL,
  `rank_position` int(11) NOT NULL,
  `leaderboard_type` varchar(50) NOT NULL,
  `score` int(11) DEFAULT 0,
  `reward_xp` int(11) DEFAULT 0,
  `reward_badge` varchar(50) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `rewards_claimed` varchar(255) DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `unique_season_user_type` (`season_id`,`user_id`,`leaderboard_type`),
  CONSTRAINT `season_rankings_ibfk_1` FOREIGN KEY (`season_id`) REFERENCES `leaderboard_seasons` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb3 COLLATE=utf8mb3_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `seasonal_recognition`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `seasonal_recognition` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `tenant_id` int(10) unsigned NOT NULL,
  `user_id` int(10) unsigned NOT NULL,
  `season` varchar(20) NOT NULL,
  `months_active` smallint(5) unsigned NOT NULL DEFAULT 0,
  `recognized_at` timestamp NULL DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uniq_seasonal_recognition` (`tenant_id`,`user_id`,`season`),
  KEY `idx_sr_tenant` (`tenant_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `seo_audits`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `seo_audits` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `tenant_id` int(11) NOT NULL,
  `url` varchar(2048) NOT NULL DEFAULT '',
  `results` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`results`)),
  `score` tinyint(3) unsigned DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `idx_tenant` (`tenant_id`),
  KEY `idx_created` (`created_at`)
) ENGINE=InnoDB AUTO_INCREMENT=91 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `seo_metadata`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `seo_metadata` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `tenant_id` int(11) NOT NULL,
  `entity_type` varchar(50) NOT NULL COMMENT 'global, page, post, etc',
  `entity_id` int(11) DEFAULT NULL COMMENT 'Null for global, ID for others',
  `slug` varchar(255) DEFAULT NULL,
  `meta_title` varchar(255) DEFAULT NULL,
  `meta_description` text DEFAULT NULL,
  `meta_keywords` text DEFAULT NULL,
  `canonical_url` varchar(255) DEFAULT NULL,
  `og_image_url` varchar(255) DEFAULT NULL,
  `noindex` tinyint(1) DEFAULT 0,
  `unlisted` tinyint(1) DEFAULT 0,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `idx_seo_lookup` (`tenant_id`,`entity_type`,`entity_id`)
) ENGINE=InnoDB AUTO_INCREMENT=49 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `seo_redirects`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `seo_redirects` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `tenant_id` int(11) NOT NULL,
  `source_url` varchar(500) NOT NULL,
  `from_path` varchar(500) DEFAULT NULL,
  `to_path` varchar(500) DEFAULT NULL,
  `status_code` int(11) DEFAULT 301,
  `destination_url` varchar(500) NOT NULL,
  `hits` int(11) DEFAULT 0,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NULL DEFAULT NULL ON UPDATE current_timestamp(),
  `dest` varchar(255) DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `unique_redirect` (`tenant_id`,`source_url`(191)),
  KEY `idx_source` (`source_url`(191)),
  KEY `idx_tenant` (`tenant_id`),
  CONSTRAINT `seo_redirects_ibfk_1` FOREIGN KEY (`tenant_id`) REFERENCES `tenants` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=1530 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `sessions`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `sessions` (
  `id` varchar(255) NOT NULL COMMENT 'Session ID (hash)',
  `user_id` int(11) DEFAULT NULL COMMENT 'User ID if authenticated',
  `tenant_id` int(11) DEFAULT NULL COMMENT 'Tenant ID for multi-tenancy',
  `session_data` text DEFAULT NULL COMMENT 'Serialized session data',
  `ip_address` varchar(45) DEFAULT NULL COMMENT 'IPv4 or IPv6 address',
  `user_agent` text DEFAULT NULL COMMENT 'Browser user agent string',
  `last_activity` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp() COMMENT 'Last activity time',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp() COMMENT 'Session start time',
  `expires_at` timestamp NOT NULL DEFAULT '0000-00-00 00:00:00' COMMENT 'Session expiry time',
  `is_authenticated` tinyint(1) DEFAULT 0 COMMENT 'Is user logged in',
  `device_type` enum('desktop','mobile','tablet','unknown') DEFAULT 'unknown',
  PRIMARY KEY (`id`),
  KEY `idx_user_id` (`user_id`),
  KEY `idx_tenant_id` (`tenant_id`),
  KEY `idx_last_activity` (`last_activity`),
  KEY `idx_expires_at` (`expires_at`),
  KEY `idx_user_tenant` (`user_id`,`tenant_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='User sessions for tracking active users and session management';
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `skill_categories`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `skill_categories` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `tenant_id` int(11) NOT NULL,
  `name` varchar(100) NOT NULL,
  `slug` varchar(120) NOT NULL,
  `parent_id` int(11) DEFAULT NULL,
  `description` text DEFAULT NULL,
  `icon` varchar(50) DEFAULT NULL,
  `display_order` int(11) NOT NULL DEFAULT 0,
  `is_active` tinyint(1) NOT NULL DEFAULT 1,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NULL DEFAULT NULL ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `idx_tenant` (`tenant_id`),
  KEY `idx_parent` (`parent_id`),
  KEY `idx_tenant_slug` (`tenant_id`,`slug`),
  KEY `idx_tenant_parent` (`tenant_id`,`parent_id`),
  CONSTRAINT `fk_skill_cat_parent` FOREIGN KEY (`parent_id`) REFERENCES `skill_categories` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB AUTO_INCREMENT=25 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `skill_endorsements`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `skill_endorsements` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `endorser_id` int(11) NOT NULL,
  `endorsed_id` int(11) NOT NULL,
  `skill_id` int(11) DEFAULT NULL COMMENT 'References user_skills.id',
  `skill_name` varchar(100) NOT NULL COMMENT 'Denormalized for display',
  `tenant_id` int(11) NOT NULL,
  `comment` varchar(500) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `unique_endorsement` (`endorser_id`,`endorsed_id`,`skill_name`,`tenant_id`),
  KEY `idx_endorsed_tenant` (`endorsed_id`,`tenant_id`),
  KEY `idx_endorser_tenant` (`endorser_id`,`tenant_id`),
  KEY `idx_skill` (`skill_id`),
  KEY `idx_tenant_skill` (`tenant_id`,`skill_name`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `skills`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `skills` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `tenant_id` int(10) unsigned NOT NULL,
  `name` varchar(255) NOT NULL,
  `slug` varchar(255) DEFAULT NULL,
  `category_id` int(10) unsigned DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NULL DEFAULT NULL ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `skills_tenant_id_name_unique` (`tenant_id`,`name`),
  KEY `skills_tenant_id_index` (`tenant_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `social_identities`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `social_identities` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `user_id` int(11) NOT NULL,
  `provider` varchar(50) NOT NULL COMMENT 'google, facebook, github',
  `provider_id` varchar(255) NOT NULL COMMENT 'External User ID',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `unique_social` (`provider`,`provider_id`),
  KEY `user_id` (`user_id`),
  CONSTRAINT `fk_social_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=9 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `social_value_config`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `social_value_config` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `tenant_id` int(11) NOT NULL,
  `hour_value_currency` varchar(3) NOT NULL DEFAULT 'GBP' COMMENT 'ISO 4217 currency code',
  `hour_value_amount` decimal(10,2) NOT NULL DEFAULT 15.00 COMMENT 'Monetary value per hour',
  `social_multiplier` decimal(5,2) NOT NULL DEFAULT 3.50 COMMENT 'SROI multiplier factor',
  `reporting_period` enum('monthly','quarterly','annually') NOT NULL DEFAULT 'annually',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NULL DEFAULT NULL ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `uk_tenant` (`tenant_id`),
  KEY `idx_tenant` (`tenant_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `staffing_predictions`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `staffing_predictions` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `tenant_id` int(11) NOT NULL,
  `shift_id` int(11) DEFAULT NULL,
  `event_id` int(11) DEFAULT NULL,
  `predicted_date` date NOT NULL,
  `predicted_shortfall` int(11) NOT NULL DEFAULT 0,
  `confidence` decimal(5,2) NOT NULL DEFAULT 0.00,
  `risk_level` enum('low','medium','high','critical') NOT NULL DEFAULT 'low',
  `factors_json` text DEFAULT NULL,
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  `updated_at` datetime NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  `resolved_at` datetime DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `idx_staffing_tenant` (`tenant_id`),
  KEY `idx_staffing_date` (`predicted_date`),
  KEY `idx_staffing_risk` (`risk_level`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `stories`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `stories` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `tenant_id` int(10) unsigned NOT NULL,
  `user_id` int(10) unsigned NOT NULL,
  `media_type` enum('image','text','poll','video') NOT NULL DEFAULT 'image',
  `media_url` text DEFAULT NULL,
  `thumbnail_url` text DEFAULT NULL,
  `text_content` varchar(500) DEFAULT NULL,
  `text_style` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`text_style`)),
  `background_color` varchar(20) DEFAULT NULL,
  `background_gradient` varchar(100) DEFAULT NULL,
  `duration` int(10) unsigned NOT NULL DEFAULT 5,
  `video_duration` float DEFAULT NULL,
  `poll_question` varchar(255) DEFAULT NULL,
  `poll_options` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`poll_options`)),
  `audience` enum('everyone','connections','close_friends') NOT NULL DEFAULT 'everyone',
  `view_count` int(10) unsigned NOT NULL DEFAULT 0,
  `is_active` tinyint(1) NOT NULL DEFAULT 1,
  `expires_at` timestamp NOT NULL,
  `created_at` timestamp NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `idx_tenant_user` (`tenant_id`,`user_id`),
  KEY `idx_active_expires` (`tenant_id`,`is_active`,`expires_at`),
  KEY `idx_user_active` (`user_id`,`is_active`,`expires_at`),
  KEY `idx_audience` (`tenant_id`,`audience`)
) ENGINE=InnoDB AUTO_INCREMENT=8 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `story_analytics`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `story_analytics` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `story_id` bigint(20) unsigned NOT NULL,
  `viewer_id` int(10) unsigned NOT NULL,
  `event_type` enum('view_start','view_complete','tap_forward','tap_back','tap_exit','swipe_next','swipe_prev') NOT NULL,
  `watch_duration_ms` int(10) unsigned DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `idx_analytics_story` (`story_id`),
  KEY `idx_analytics_viewer` (`viewer_id`),
  KEY `idx_analytics_type` (`story_id`,`event_type`),
  CONSTRAINT `story_analytics_ibfk_1` FOREIGN KEY (`story_id`) REFERENCES `stories` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=8 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `story_archive`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `story_archive` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `tenant_id` int(11) NOT NULL,
  `user_id` int(10) unsigned NOT NULL,
  `original_story_id` bigint(20) unsigned NOT NULL,
  `media_type` enum('image','text','poll','video') NOT NULL DEFAULT 'image',
  `media_url` text DEFAULT NULL,
  `thumbnail_url` text DEFAULT NULL,
  `text_content` varchar(500) DEFAULT NULL,
  `text_style` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`text_style`)),
  `background_color` varchar(20) DEFAULT NULL,
  `background_gradient` varchar(100) DEFAULT NULL,
  `duration` int(10) unsigned NOT NULL DEFAULT 5,
  `video_duration` float DEFAULT NULL,
  `poll_question` varchar(255) DEFAULT NULL,
  `poll_options` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`poll_options`)),
  `view_count` int(10) unsigned NOT NULL DEFAULT 0,
  `original_created_at` timestamp NOT NULL,
  `archived_at` timestamp NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `idx_archive_tenant_user` (`tenant_id`,`user_id`),
  KEY `idx_archive_original` (`original_story_id`),
  CONSTRAINT `story_archive_ibfk_1` FOREIGN KEY (`tenant_id`) REFERENCES `tenants` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=8 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `story_highlight_items`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `story_highlight_items` (
  `highlight_id` bigint(20) unsigned NOT NULL,
  `story_id` bigint(20) unsigned NOT NULL,
  `display_order` tinyint(3) unsigned NOT NULL DEFAULT 0,
  PRIMARY KEY (`highlight_id`,`story_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `story_highlights`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `story_highlights` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `tenant_id` int(10) unsigned NOT NULL,
  `user_id` int(10) unsigned NOT NULL,
  `title` varchar(100) NOT NULL,
  `cover_url` text DEFAULT NULL,
  `display_order` tinyint(3) unsigned NOT NULL DEFAULT 0,
  `created_at` timestamp NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `idx_user_highlights` (`tenant_id`,`user_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `story_poll_votes`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `story_poll_votes` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `tenant_id` int(11) NOT NULL,
  `story_id` bigint(20) unsigned NOT NULL,
  `user_id` int(10) unsigned NOT NULL,
  `option_index` tinyint(3) unsigned NOT NULL,
  `created_at` timestamp NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `uk_story_poll_vote` (`story_id`,`user_id`),
  KEY `idx_story_poll_votes_tenant` (`tenant_id`),
  CONSTRAINT `fk_story_poll_votes_tenant` FOREIGN KEY (`tenant_id`) REFERENCES `tenants` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `story_reactions`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `story_reactions` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `tenant_id` int(11) NOT NULL,
  `story_id` bigint(20) unsigned NOT NULL,
  `user_id` int(10) unsigned NOT NULL,
  `reaction_type` varchar(20) NOT NULL,
  `created_at` timestamp NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `idx_story_reactions` (`story_id`),
  KEY `idx_story_reactions_tenant` (`tenant_id`),
  CONSTRAINT `fk_story_reactions_tenant` FOREIGN KEY (`tenant_id`) REFERENCES `tenants` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=4 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `story_stickers`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `story_stickers` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `story_id` bigint(20) unsigned NOT NULL,
  `sticker_type` enum('mention','location','link','emoji','text_tag') NOT NULL,
  `content` varchar(500) NOT NULL,
  `metadata` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`metadata`)),
  `position_x` float NOT NULL DEFAULT 50,
  `position_y` float NOT NULL DEFAULT 50,
  `rotation` float NOT NULL DEFAULT 0,
  `scale` float NOT NULL DEFAULT 1,
  `created_at` timestamp NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `idx_sticker_story` (`story_id`),
  CONSTRAINT `story_stickers_ibfk_1` FOREIGN KEY (`story_id`) REFERENCES `stories` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `story_views`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `story_views` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `tenant_id` int(11) NOT NULL,
  `story_id` bigint(20) unsigned NOT NULL,
  `viewer_id` int(10) unsigned NOT NULL,
  `viewed_at` timestamp NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `uk_story_viewer` (`story_id`,`viewer_id`),
  KEY `idx_story_views` (`story_id`),
  KEY `idx_viewer` (`viewer_id`),
  KEY `idx_story_views_tenant` (`tenant_id`),
  CONSTRAINT `fk_story_views_tenant` FOREIGN KEY (`tenant_id`) REFERENCES `tenants` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=11 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `stripe_webhook_events`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `stripe_webhook_events` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `event_id` varchar(255) NOT NULL,
  `event_type` varchar(100) NOT NULL,
  `status` enum('processing','processed','failed') NOT NULL DEFAULT 'processing',
  `processed_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `uk_event_id` (`event_id`),
  KEY `idx_event_type` (`event_type`),
  KEY `stripe_webhook_events_status_index` (`status`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `super_admin_audit_log`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `super_admin_audit_log` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `actor_user_id` int(10) unsigned NOT NULL,
  `actor_tenant_id` int(10) unsigned NOT NULL,
  `actor_name` varchar(255) NOT NULL,
  `actor_email` varchar(255) NOT NULL,
  `action_type` enum('tenant_created','tenant_updated','tenant_deleted','tenant_moved','hub_toggled','super_admin_granted','super_admin_revoked','user_created','user_updated','user_moved','bulk_users_moved','bulk_tenants_updated') NOT NULL,
  `target_type` enum('tenant','user','bulk') NOT NULL,
  `target_id` int(10) unsigned DEFAULT NULL,
  `target_name` varchar(255) DEFAULT NULL,
  `old_values` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`old_values`)),
  `new_values` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`new_values`)),
  `description` text DEFAULT NULL,
  `ip_address` varchar(45) DEFAULT NULL,
  `user_agent` text DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `idx_actor` (`actor_user_id`),
  KEY `idx_action` (`action_type`),
  KEY `idx_target` (`target_type`,`target_id`),
  KEY `idx_created` (`created_at`),
  KEY `idx_actor_tenant` (`actor_tenant_id`)
) ENGINE=InnoDB AUTO_INCREMENT=2084 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Audit trail for Super Admin Panel hierarchy changes';
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `team_documents`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `team_documents` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `group_id` int(10) unsigned NOT NULL,
  `tenant_id` int(10) unsigned NOT NULL,
  `title` varchar(255) NOT NULL,
  `file_path` varchar(500) NOT NULL,
  `file_type` varchar(100) DEFAULT NULL,
  `file_size` int(10) unsigned DEFAULT NULL COMMENT 'Bytes',
  `uploaded_by` int(11) NOT NULL,
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `idx_group` (`group_id`,`tenant_id`),
  KEY `idx_uploader` (`uploaded_by`)
) ENGINE=InnoDB AUTO_INCREMENT=2 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `team_tasks`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `team_tasks` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `group_id` int(10) unsigned NOT NULL,
  `tenant_id` int(10) unsigned NOT NULL,
  `title` varchar(255) NOT NULL,
  `description` text DEFAULT NULL,
  `assigned_to` int(11) DEFAULT NULL COMMENT 'User ID of assignee',
  `status` enum('todo','in_progress','done') NOT NULL DEFAULT 'todo',
  `priority` enum('low','medium','high','urgent') NOT NULL DEFAULT 'medium',
  `due_date` date DEFAULT NULL,
  `created_by` int(11) NOT NULL,
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  `completed_at` datetime DEFAULT NULL,
  `updated_at` datetime DEFAULT NULL ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `idx_group` (`group_id`,`tenant_id`),
  KEY `idx_assigned` (`assigned_to`),
  KEY `idx_status` (`group_id`,`status`),
  KEY `idx_due` (`group_id`,`due_date`)
) ENGINE=InnoDB AUTO_INCREMENT=7 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `tenant_badge_overrides`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `tenant_badge_overrides` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `tenant_id` int(10) unsigned NOT NULL,
  `badge_key` varchar(100) NOT NULL,
  `is_enabled` tinyint(1) NOT NULL DEFAULT 1,
  `custom_threshold` int(10) unsigned DEFAULT NULL,
  `custom_name` varchar(200) DEFAULT NULL,
  `custom_description` varchar(255) DEFAULT NULL,
  `custom_icon` varchar(100) DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uniq_tenant_badge_override` (`tenant_id`,`badge_key`),
  KEY `idx_tbo_tenant` (`tenant_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `tenant_consent_overrides`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `tenant_consent_overrides` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `tenant_id` int(11) NOT NULL,
  `consent_type_slug` varchar(100) NOT NULL,
  `current_version` varchar(20) NOT NULL DEFAULT '1.0',
  `current_text` text DEFAULT NULL COMMENT 'Override text, NULL = use global',
  `is_active` tinyint(1) DEFAULT 1,
  `created_at` datetime DEFAULT current_timestamp(),
  `updated_at` datetime DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `uk_tenant_consent` (`tenant_id`,`consent_type_slug`),
  KEY `idx_tenant_id` (`tenant_id`),
  KEY `idx_consent_type` (`consent_type_slug`),
  CONSTRAINT `tenant_consent_overrides_ibfk_1` FOREIGN KEY (`tenant_id`) REFERENCES `tenants` (`id`) ON DELETE CASCADE,
  CONSTRAINT `tenant_consent_overrides_ibfk_2` FOREIGN KEY (`consent_type_slug`) REFERENCES `consent_types` (`slug`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `tenant_consent_version_history`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `tenant_consent_version_history` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `tenant_id` int(11) NOT NULL,
  `consent_type_slug` varchar(100) NOT NULL,
  `version` varchar(20) NOT NULL,
  `text_content` text DEFAULT NULL,
  `text_hash` varchar(64) DEFAULT NULL COMMENT 'SHA-256 hash for comparison',
  `created_by` int(11) DEFAULT NULL COMMENT 'Admin user who made the change',
  `effective_from` datetime DEFAULT current_timestamp(),
  `created_at` datetime DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `idx_tenant_consent` (`tenant_id`,`consent_type_slug`),
  KEY `idx_version` (`version`),
  KEY `idx_effective_from` (`effective_from`),
  CONSTRAINT `tenant_consent_version_history_ibfk_1` FOREIGN KEY (`tenant_id`) REFERENCES `tenants` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `tenant_cookie_settings`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `tenant_cookie_settings` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `tenant_id` int(11) NOT NULL,
  `banner_message` text DEFAULT NULL COMMENT 'Custom banner text for this tenant',
  `analytics_enabled` tinyint(1) DEFAULT 0 COMMENT 'Whether tenant uses analytics cookies',
  `marketing_enabled` tinyint(1) DEFAULT 0 COMMENT 'Whether tenant uses marketing cookies',
  `analytics_provider` varchar(100) DEFAULT NULL COMMENT 'Analytics provider (e.g., Google Analytics, Matomo)',
  `analytics_id` varchar(255) DEFAULT NULL COMMENT 'Analytics tracking ID',
  `consent_validity_days` int(11) DEFAULT 365 COMMENT 'How long consent is valid (days)',
  `auto_block_scripts` tinyint(1) DEFAULT 1 COMMENT 'Block tracking scripts until consent given',
  `strict_mode` tinyint(1) DEFAULT 1 COMMENT 'Require explicit consent (vs. implied consent)',
  `show_reject_all` tinyint(1) DEFAULT 1 COMMENT 'Show Reject All button in banner',
  `created_at` datetime DEFAULT current_timestamp(),
  `updated_at` datetime DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `tenant_id` (`tenant_id`),
  KEY `idx_tenant_id` (`tenant_id`)
) ENGINE=InnoDB AUTO_INCREMENT=8 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Tenant-specific cookie consent configuration';
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `tenant_invite_code_uses`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `tenant_invite_code_uses` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `invite_code_id` int(11) NOT NULL,
  `user_id` int(11) NOT NULL COMMENT 'User who used the code to register',
  `used_at` datetime NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `idx_ticu_code` (`invite_code_id`),
  KEY `idx_ticu_user` (`user_id`),
  CONSTRAINT `fk_ticu_code` FOREIGN KEY (`invite_code_id`) REFERENCES `tenant_invite_codes` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_ticu_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `tenant_invite_codes`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `tenant_invite_codes` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `tenant_id` int(11) NOT NULL,
  `code` varchar(20) NOT NULL,
  `created_by` int(11) NOT NULL COMMENT 'Admin user who generated the code',
  `max_uses` int(11) NOT NULL DEFAULT 1,
  `uses_count` int(11) NOT NULL DEFAULT 0,
  `note` varchar(255) DEFAULT NULL,
  `is_active` tinyint(1) NOT NULL DEFAULT 1,
  `expires_at` datetime DEFAULT NULL,
  `last_used_at` datetime DEFAULT NULL,
  `last_used_by` int(11) DEFAULT NULL,
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `uk_tic_tenant_code` (`tenant_id`,`code`),
  KEY `idx_tic_tenant_active` (`tenant_id`,`is_active`),
  KEY `fk_tic_creator` (`created_by`),
  CONSTRAINT `fk_tic_creator` FOREIGN KEY (`created_by`) REFERENCES `users` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_tic_tenant` FOREIGN KEY (`tenant_id`) REFERENCES `tenants` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `tenant_plan_assignments`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `tenant_plan_assignments` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `tenant_id` int(11) NOT NULL,
  `pay_plan_id` int(11) NOT NULL,
  `status` enum('active','expired','cancelled','trial') DEFAULT 'active',
  `starts_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `expires_at` timestamp NULL DEFAULT NULL COMMENT 'NULL means unlimited',
  `trial_ends_at` timestamp NULL DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NULL DEFAULT NULL ON UPDATE current_timestamp(),
  `stripe_subscription_id` varchar(255) DEFAULT NULL,
  `stripe_current_period_end` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `idx_tpa_stripe_sub` (`stripe_subscription_id`),
  KEY `idx_tenant_plan_tenant` (`tenant_id`),
  KEY `idx_tenant_plan_status` (`status`),
  KEY `idx_tenant_plan_expires` (`expires_at`),
  KEY `pay_plan_id` (`pay_plan_id`),
  CONSTRAINT `tenant_plan_assignments_ibfk_1` FOREIGN KEY (`pay_plan_id`) REFERENCES `pay_plans` (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=7 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `tenant_provider_credentials`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `tenant_provider_credentials` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `tenant_id` int(10) unsigned NOT NULL,
  `provider_slug` varchar(50) NOT NULL,
  `credentials_encrypted` text NOT NULL COMMENT 'AES-256-GCM encrypted JSON with api_key, webhook_secret, etc.',
  `is_active` tinyint(1) NOT NULL DEFAULT 1,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_tenant_provider` (`tenant_id`,`provider_slug`),
  KEY `idx_tenant_active` (`tenant_id`,`is_active`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `tenant_registration_policies`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `tenant_registration_policies` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `tenant_id` int(11) NOT NULL,
  `registration_mode` enum('open','open_with_approval','verified_identity','government_id','invite_only') NOT NULL DEFAULT 'open',
  `verification_provider` varchar(50) DEFAULT NULL COMMENT 'Provider slug: mock, stripe_identity, veriff, etc.',
  `verification_level` enum('none','document_only','document_selfie','reusable_digital_id','manual_review') NOT NULL DEFAULT 'none',
  `post_verification` enum('activate','admin_approval','limited_access','reject_on_fail') NOT NULL DEFAULT 'activate',
  `fallback_mode` enum('none','admin_review','native_registration') NOT NULL DEFAULT 'none',
  `require_email_verify` tinyint(1) NOT NULL DEFAULT 1,
  `provider_config` text DEFAULT NULL COMMENT 'Encrypted JSON — provider API keys and settings',
  `is_active` tinyint(1) NOT NULL DEFAULT 1,
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  `updated_at` datetime DEFAULT NULL ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `uk_trp_tenant` (`tenant_id`),
  KEY `idx_trp_tenant` (`tenant_id`),
  CONSTRAINT `fk_trp_tenant` FOREIGN KEY (`tenant_id`) REFERENCES `tenants` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=17 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `tenant_safeguarding_options`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `tenant_safeguarding_options` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `tenant_id` int(11) NOT NULL,
  `option_key` varchar(100) NOT NULL COMMENT 'Machine-readable key e.g. works_with_children',
  `option_type` enum('checkbox','info','select') NOT NULL DEFAULT 'checkbox',
  `label` varchar(500) NOT NULL COMMENT 'Display label shown to member',
  `description` text DEFAULT NULL COMMENT 'Help text shown under the option',
  `help_url` varchar(500) DEFAULT NULL COMMENT 'Optional link to more info',
  `sort_order` int(10) unsigned NOT NULL DEFAULT 0,
  `is_active` tinyint(1) NOT NULL DEFAULT 1,
  `is_required` tinyint(1) NOT NULL DEFAULT 0 COMMENT 'Must answer before completing onboarding',
  `select_options` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL COMMENT 'For type=select: [{value, label}] array' CHECK (json_valid(`select_options`)),
  `triggers` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL COMMENT 'Behavioral triggers: {requires_vetted_interaction, requires_broker_approval, restricts_messaging, restricts_matching, notify_admin_on_selection, vetting_type_required}' CHECK (json_valid(`triggers`)),
  `preset_source` varchar(50) DEFAULT NULL COMMENT 'Country preset that created this: ireland, england_wales, scotland, northern_ireland, or NULL for custom',
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  `updated_at` datetime DEFAULT NULL ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `uk_tenant_option` (`tenant_id`,`option_key`),
  KEY `idx_tenant_active` (`tenant_id`,`is_active`,`sort_order`),
  CONSTRAINT `fk_tso_tenant` FOREIGN KEY (`tenant_id`) REFERENCES `tenants` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=10 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Admin-configured safeguarding options shown during onboarding per tenant';
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `tenant_settings`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `tenant_settings` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `tenant_id` int(10) unsigned NOT NULL DEFAULT 1 COMMENT 'Tenant identifier (multi-tenancy support)',
  `setting_key` varchar(255) NOT NULL COMMENT 'Setting key in dot notation (e.g., feature.gamification, security.2fa_required)',
  `setting_value` text DEFAULT NULL COMMENT 'Setting value (can be JSON, boolean string, or scalar value)',
  `setting_type` enum('string','boolean','integer','float','json','array') DEFAULT 'string' COMMENT 'Data type of the setting value',
  `category` varchar(100) DEFAULT 'general',
  `description` text DEFAULT NULL COMMENT 'Optional description of what this setting controls',
  `is_encrypted` tinyint(1) NOT NULL DEFAULT 0 COMMENT 'Whether the value is encrypted (1) or not (0)',
  `created_at` timestamp NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  `created_by` int(10) unsigned DEFAULT NULL COMMENT 'User ID who created this setting',
  `updated_by` int(10) unsigned DEFAULT NULL COMMENT 'User ID who last updated this setting',
  PRIMARY KEY (`id`),
  UNIQUE KEY `unique_tenant_setting` (`tenant_id`,`setting_key`),
  KEY `idx_tenant_id` (`tenant_id`),
  KEY `idx_setting_key` (`setting_key`),
  KEY `idx_setting_type` (`setting_type`),
  KEY `idx_created_at` (`created_at`)
) ENGINE=InnoDB AUTO_INCREMENT=581 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Tenant-specific configuration settings and feature flags';
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `tenant_wallet_limits`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `tenant_wallet_limits` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `tenants`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `tenants` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `name` varchar(255) NOT NULL,
  `slug` varchar(50) DEFAULT NULL,
  `default_layout` varchar(50) DEFAULT 'modern',
  `domain` varchar(255) DEFAULT NULL,
  `theme` varchar(50) DEFAULT 'modern',
  `tagline` varchar(255) DEFAULT NULL,
  `description` text DEFAULT NULL,
  `features` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`features`)),
  `created_at` datetime DEFAULT current_timestamp(),
  `updated_at` timestamp NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  `configuration` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT '{"modules":{"events":true,"polls":true,"goals":true,"volunteering":true,"resources":true}}' CHECK (json_valid(`configuration`)),
  `gamification_config` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`gamification_config`)),
  `layout` varchar(50) DEFAULT 'modern',
  `contact_email` varchar(255) DEFAULT NULL,
  `contact_phone` varchar(50) DEFAULT NULL,
  `address` varchar(500) DEFAULT NULL,
  `social_facebook` varchar(255) DEFAULT NULL,
  `social_twitter` varchar(255) DEFAULT NULL,
  `social_instagram` varchar(255) DEFAULT NULL,
  `social_linkedin` varchar(255) DEFAULT NULL,
  `social_youtube` varchar(255) DEFAULT NULL,
  `module_page_builder_enabled` tinyint(1) DEFAULT 0,
  `apply` varchar(255) DEFAULT NULL,
  `blocker_user_id` int(11) DEFAULT NULL,
  `content` text DEFAULT NULL,
  `doma` varchar(255) DEFAULT NULL,
  `errors` varchar(255) DEFAULT NULL,
  `is_active` tinyint(1) DEFAULT 1,
  `stripe_customer_id` varchar(255) DEFAULT NULL,
  `key_name` varchar(255) DEFAULT NULL,
  `match` timestamp NULL DEFAULT NULL,
  `pages` varchar(255) DEFAULT NULL,
  `meta_title` varchar(70) DEFAULT NULL COMMENT 'Custom SEO title for search results (max 60 chars)',
  `meta_description` varchar(180) DEFAULT NULL COMMENT 'Custom meta description for search results (max 160 chars)',
  `h1_headline` varchar(100) DEFAULT NULL COMMENT 'Main H1 heading for homepage hero section',
  `hero_intro` text DEFAULT NULL COMMENT 'Hero section intro text (2-3 sentences)',
  `og_image_url` varchar(500) DEFAULT NULL COMMENT 'Open Graph image URL for social sharing',
  `latitude` decimal(10,8) DEFAULT NULL COMMENT 'Headquarters latitude for local SEO',
  `longitude` decimal(11,8) DEFAULT NULL COMMENT 'Headquarters longitude for local SEO',
  `location_name` varchar(255) DEFAULT NULL COMMENT 'Human-readable location (e.g., Dublin, Ireland)',
  `country_code` char(2) DEFAULT NULL COMMENT 'ISO country code for geo-targeting (e.g., IE, GB, US)',
  `service_area` enum('local','regional','national','international') DEFAULT 'national' COMMENT 'Geographic scope: local (city), regional (county), national, international',
  `robots_directive` varchar(50) DEFAULT 'index, follow' COMMENT 'Robots meta directive (index/noindex, follow/nofollow)',
  `parent_id` int(11) DEFAULT NULL COMMENT 'Parent tenant ID. NULL = root/master level',
  `path` varchar(500) DEFAULT NULL COMMENT 'Materialized path: /1/2/5/ for fast descendant queries',
  `depth` tinyint(3) unsigned NOT NULL DEFAULT 0 COMMENT 'Hierarchy depth: 0=Master, 1=Regional, 2=Local, etc.',
  `allows_subtenants` tinyint(1) NOT NULL DEFAULT 0 COMMENT 'Can admins of this tenant create sub-tenants?',
  `max_depth` tinyint(3) unsigned NOT NULL DEFAULT 0 COMMENT 'Max depth of sub-tenants allowed below this tenant',
  `federation_contact_email` varchar(255) DEFAULT NULL,
  `federation_contact_name` varchar(200) DEFAULT NULL,
  `federation_public_description` text DEFAULT NULL,
  `federation_categories` varchar(500) DEFAULT NULL,
  `federation_member_count_public` tinyint(1) NOT NULL DEFAULT 0,
  `federation_region` varchar(100) DEFAULT NULL,
  `federation_discoverable` tinyint(1) NOT NULL DEFAULT 0,
  PRIMARY KEY (`id`),
  UNIQUE KEY `slug` (`slug`),
  UNIQUE KEY `domain` (`domain`),
  UNIQUE KEY `uq_tenants_slug` (`slug`),
  UNIQUE KEY `uq_tenants_domain` (`domain`),
  UNIQUE KEY `idx_tenants_stripe_customer` (`stripe_customer_id`),
  KEY `idx_tenant_parent` (`parent_id`),
  KEY `idx_tenant_path` (`path`(100)),
  KEY `idx_tenant_depth` (`depth`),
  KEY `idx_tenants_is_active` (`is_active`),
  CONSTRAINT `fk_tenant_parent` FOREIGN KEY (`parent_id`) REFERENCES `tenants` (`id`) ON UPDATE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=1000 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `test_runs`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `test_runs` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `tenant_id` int(11) NOT NULL,
  `user_id` int(11) DEFAULT NULL,
  `suite` varchar(50) NOT NULL,
  `tests` int(11) NOT NULL DEFAULT 0,
  `assertions` int(11) NOT NULL DEFAULT 0,
  `errors` int(11) NOT NULL DEFAULT 0,
  `failures` int(11) NOT NULL DEFAULT 0,
  `skipped` int(11) NOT NULL DEFAULT 0,
  `duration` decimal(10,2) NOT NULL DEFAULT 0.00,
  `success` tinyint(1) NOT NULL DEFAULT 0,
  `output` text DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `idx_tenant` (`tenant_id`,`created_at`),
  KEY `idx_suite` (`suite`),
  KEY `idx_success` (`success`)
) ENGINE=InnoDB AUTO_INCREMENT=4 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `totp_admin_overrides`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `totp_admin_overrides` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `user_id` int(11) NOT NULL COMMENT 'User whose 2FA was overridden',
  `admin_id` int(11) NOT NULL COMMENT 'Admin who performed the action',
  `tenant_id` int(11) NOT NULL,
  `action_type` enum('reset','disable','bypass_login') NOT NULL,
  `reason` text NOT NULL COMMENT 'Admin must provide reason',
  `ip_address` varchar(45) DEFAULT NULL,
  `user_agent` text DEFAULT NULL,
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `idx_user` (`user_id`),
  KEY `idx_admin` (`admin_id`),
  KEY `idx_tenant` (`tenant_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Audit log for admin 2FA overrides';
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `totp_verification_attempts`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `totp_verification_attempts` (
  `id` bigint(20) NOT NULL AUTO_INCREMENT,
  `user_id` int(11) NOT NULL,
  `tenant_id` int(11) NOT NULL,
  `ip_address` varchar(45) NOT NULL,
  `user_agent` text DEFAULT NULL,
  `attempt_type` enum('totp','backup_code') NOT NULL DEFAULT 'totp',
  `is_successful` tinyint(1) NOT NULL DEFAULT 0,
  `failure_reason` varchar(100) DEFAULT NULL COMMENT 'invalid_code, expired, rate_limited, etc.',
  `attempted_at` datetime NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `idx_user_attempts` (`user_id`,`attempted_at`),
  KEY `idx_ip_attempts` (`ip_address`,`attempted_at`),
  KEY `idx_tenant` (`tenant_id`),
  KEY `idx_cleanup` (`attempted_at`)
) ENGINE=InnoDB AUTO_INCREMENT=11 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='2FA verification attempts for rate limiting and audit';
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `transaction_categories`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `transaction_categories` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `tenant_id` int(11) NOT NULL,
  `name` varchar(100) NOT NULL,
  `slug` varchar(100) NOT NULL,
  `description` varchar(255) DEFAULT NULL,
  `icon` varchar(50) DEFAULT NULL COMMENT 'Lucide icon name',
  `color` varchar(7) DEFAULT NULL COMMENT 'Hex color e.g. #3B82F6',
  `sort_order` int(11) NOT NULL DEFAULT 0,
  `is_system` tinyint(1) NOT NULL DEFAULT 0 COMMENT 'System-defined, cannot be deleted',
  `is_active` tinyint(1) NOT NULL DEFAULT 1,
  `created_at` timestamp NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `uk_tenant_slug` (`tenant_id`,`slug`),
  KEY `idx_tenant_active` (`tenant_id`,`is_active`),
  CONSTRAINT `transaction_categories_ibfk_1` FOREIGN KEY (`tenant_id`) REFERENCES `tenants` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=25 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `transactions`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `transactions` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `tenant_id` int(11) NOT NULL,
  `sender_id` int(11) NOT NULL,
  `giver_id` int(11) DEFAULT NULL,
  `receiver_id` int(11) NOT NULL,
  `amount` int(11) NOT NULL,
  `description` text DEFAULT NULL,
  `created_at` datetime DEFAULT current_timestamp(),
  `updated_at` datetime DEFAULT NULL ON UPDATE current_timestamp(),
  `deleted_for_sender` tinyint(1) DEFAULT 0,
  `deleted_for_receiver` tinyint(1) DEFAULT 0,
  `source_match_id` int(11) DEFAULT NULL COMMENT 'Match history ID that led to this transaction',
  `listing_id` int(11) DEFAULT NULL,
  `status` enum('pending','completed','cancelled') DEFAULT 'completed',
  `is_federated` tinyint(1) DEFAULT 0,
  `sender_tenant_id` int(11) DEFAULT NULL,
  `receiver_tenant_id` int(11) DEFAULT NULL,
  `transaction_type` enum('exchange','volunteer','donation','other') DEFAULT 'exchange' COMMENT 'Type of transaction for scoring purposes',
  `award` varchar(255) DEFAULT NULL,
  `blocker_user_id` int(11) DEFAULT NULL,
  `click_rate` timestamp NULL DEFAULT NULL,
  `content` text DEFAULT NULL,
  `distance` varchar(255) DEFAULT NULL,
  `errors` varchar(255) DEFAULT NULL,
  `last_transaction` varchar(255) DEFAULT NULL,
  `match` timestamp NULL DEFAULT NULL,
  `open_rate` timestamp NULL DEFAULT NULL,
  `pages` varchar(255) DEFAULT NULL,
  `category_id` int(11) DEFAULT NULL,
  `prep_time` decimal(5,2) DEFAULT NULL COMMENT 'Preparation time in hours',
  PRIMARY KEY (`id`),
  KEY `tenant_id` (`tenant_id`),
  KEY `receiver_id` (`receiver_id`),
  KEY `idx_transactions_user` (`sender_id`,`receiver_id`),
  KEY `idx_transactions_sender` (`sender_id`),
  KEY `idx_transactions_receiver` (`receiver_id`),
  KEY `idx_source_match` (`source_match_id`),
  KEY `idx_transactions_tenant_created` (`tenant_id`,`created_at`),
  KEY `idx_transactions_sender_tenant` (`sender_id`,`tenant_id`),
  KEY `idx_transactions_receiver_tenant` (`receiver_id`,`tenant_id`),
  KEY `idx_transactions_sender_created` (`sender_id`,`created_at`),
  KEY `idx_transactions_receiver_created` (`receiver_id`,`created_at`),
  KEY `idx_transactions_tenant_amount` (`tenant_id`,`amount`),
  KEY `idx_giver_id` (`giver_id`),
  KEY `idx_transaction_type` (`transaction_type`),
  KEY `idx_transactions_status` (`status`),
  KEY `idx_is_federated` (`is_federated`),
  KEY `idx_sender_tenant` (`sender_tenant_id`),
  KEY `idx_receiver_tenant` (`receiver_tenant_id`),
  KEY `idx_txn_sender_status` (`sender_id`,`status`),
  KEY `idx_txn_receiver_status` (`receiver_id`,`status`),
  CONSTRAINT `fk_transactions_giver` FOREIGN KEY (`giver_id`) REFERENCES `users` (`id`) ON DELETE CASCADE,
  CONSTRAINT `transactions_ibfk_1` FOREIGN KEY (`tenant_id`) REFERENCES `tenants` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=126 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `translation_glossaries`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `translation_glossaries` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `tenant_id` int(11) NOT NULL,
  `source_term` varchar(255) NOT NULL,
  `target_term` varchar(255) NOT NULL,
  `target_language` varchar(10) NOT NULL,
  `is_active` tinyint(1) NOT NULL DEFAULT 1,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `translation_glossaries_tenant_id_index` (`tenant_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `user_active_unlockables`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `user_active_unlockables` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `user_id` int(11) NOT NULL,
  `unlockable_type` varchar(50) NOT NULL,
  `unlockable_key` varchar(50) NOT NULL,
  `activated_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `unique_user_type` (`user_id`,`unlockable_type`),
  KEY `idx_user` (`user_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb3 COLLATE=utf8mb3_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `user_backup_codes`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `user_backup_codes` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `user_id` int(11) NOT NULL,
  `tenant_id` int(11) NOT NULL,
  `code_hash` varchar(255) NOT NULL COMMENT 'password_hash() of the backup code',
  `is_used` tinyint(1) NOT NULL DEFAULT 0,
  `used_at` datetime DEFAULT NULL,
  `used_ip` varchar(45) DEFAULT NULL COMMENT 'IP address when code was used',
  `used_user_agent` text DEFAULT NULL,
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `idx_user_unused` (`user_id`,`is_used`),
  KEY `idx_tenant` (`tenant_id`)
) ENGINE=InnoDB AUTO_INCREMENT=21 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='One-time backup codes for 2FA recovery';
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `user_badges`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `user_badges` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `tenant_id` int(11) NOT NULL DEFAULT 1,
  `user_id` int(11) NOT NULL,
  `badge_key` varchar(50) NOT NULL,
  `name` varchar(100) DEFAULT NULL,
  `title` varchar(100) DEFAULT '',
  `icon` varchar(50) DEFAULT NULL,
  `awarded_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `is_showcased` tinyint(1) DEFAULT 0,
  `showcase_order` tinyint(4) DEFAULT 0,
  `claimed_at` timestamp NULL DEFAULT NULL,
  `earned_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `user_badge_unique` (`tenant_id`,`user_id`,`badge_key`),
  UNIQUE KEY `idx_user_badge` (`user_id`,`name`),
  KEY `idx_user_badges_showcase` (`user_id`,`is_showcased`),
  KEY `idx_user_badges_user` (`user_id`,`awarded_at`),
  KEY `idx_tenant` (`tenant_id`),
  KEY `idx_user_badges_tenant_user` (`tenant_id`,`user_id`),
  CONSTRAINT `user_badges_tenant_id_foreign` FOREIGN KEY (`tenant_id`) REFERENCES `tenants` (`id`) ON DELETE CASCADE,
  CONSTRAINT `user_badges_user_id_foreign` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=1553 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `user_blocks`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `user_blocks` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `user_id` int(10) unsigned NOT NULL COMMENT 'User who blocked',
  `blocked_user_id` int(10) unsigned NOT NULL COMMENT 'User who was blocked',
  `reason` text DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `blocker_user_id` int(11) DEFAULT NULL,
  `match` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `unique_block` (`user_id`,`blocked_user_id`),
  KEY `idx_user` (`user_id`),
  KEY `idx_blocked` (`blocked_user_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `user_categories`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `user_categories` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `user_id` int(11) NOT NULL,
  `category_id` int(11) NOT NULL,
  `tenant_id` int(11) NOT NULL,
  `created_at` timestamp NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `unique_user_category` (`user_id`,`category_id`,`tenant_id`),
  KEY `idx_user_id` (`user_id`),
  KEY `idx_category_id` (`category_id`),
  KEY `idx_tenant_id` (`tenant_id`),
  CONSTRAINT `user_categories_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE,
  CONSTRAINT `user_categories_ibfk_2` FOREIGN KEY (`category_id`) REFERENCES `categories` (`id`) ON DELETE CASCADE,
  CONSTRAINT `user_categories_ibfk_3` FOREIGN KEY (`tenant_id`) REFERENCES `tenants` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `user_category_affinity`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `user_category_affinity` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `user_id` int(11) NOT NULL,
  `tenant_id` int(11) NOT NULL,
  `category_id` int(11) NOT NULL,
  `view_count` int(11) DEFAULT 0 COMMENT 'Times viewed listings in this category',
  `save_count` int(11) DEFAULT 0 COMMENT 'Times saved listings in this category',
  `contact_count` int(11) DEFAULT 0 COMMENT 'Times contacted in this category',
  `transaction_count` int(11) DEFAULT 0 COMMENT 'Transactions in this category',
  `dismiss_count` int(11) DEFAULT 0 COMMENT 'Times dismissed listings in this category',
  `affinity_score` decimal(5,2) DEFAULT 50.00,
  `last_interaction` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `unique_user_category` (`user_id`,`tenant_id`,`category_id`),
  KEY `idx_user_affinity` (`user_id`,`affinity_score`),
  KEY `idx_tenant` (`tenant_id`),
  KEY `category_id` (`category_id`),
  CONSTRAINT `user_category_affinity_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE,
  CONSTRAINT `user_category_affinity_ibfk_2` FOREIGN KEY (`tenant_id`) REFERENCES `tenants` (`id`) ON DELETE CASCADE,
  CONSTRAINT `user_category_affinity_ibfk_3` FOREIGN KEY (`category_id`) REFERENCES `categories` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=4 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `user_challenge_progress`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `user_challenge_progress` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `tenant_id` int(11) NOT NULL,
  `user_id` int(11) NOT NULL,
  `challenge_id` int(11) NOT NULL,
  `current_count` int(11) DEFAULT 0,
  `completed_at` timestamp NULL DEFAULT NULL,
  `reward_claimed` tinyint(1) DEFAULT 0,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  `claimed_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `unique_user_challenge` (`user_id`,`challenge_id`),
  KEY `idx_tenant_user` (`tenant_id`,`user_id`),
  KEY `challenge_id` (`challenge_id`),
  KEY `idx_challenge_progress_user` (`user_id`,`completed_at`),
  CONSTRAINT `user_challenge_progress_ibfk_1` FOREIGN KEY (`challenge_id`) REFERENCES `challenges` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb3 COLLATE=utf8mb3_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `user_collection_completions`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `user_collection_completions` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `user_id` int(11) NOT NULL,
  `collection_id` int(11) NOT NULL,
  `completed_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `bonus_claimed` tinyint(1) DEFAULT 0,
  PRIMARY KEY (`id`),
  UNIQUE KEY `unique_user_collection` (`user_id`,`collection_id`),
  KEY `collection_id` (`collection_id`),
  CONSTRAINT `user_collection_completions_ibfk_1` FOREIGN KEY (`collection_id`) REFERENCES `badge_collections` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb3 COLLATE=utf8mb3_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `user_consents`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `user_consents` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `user_id` int(11) NOT NULL,
  `tenant_id` int(11) NOT NULL,
  `consent_type` varchar(100) NOT NULL,
  `consent_given` tinyint(1) DEFAULT 0,
  `consent_text` text NOT NULL,
  `consent_version` varchar(20) NOT NULL,
  `consent_hash` varchar(64) DEFAULT NULL,
  `ip_address` varchar(45) DEFAULT NULL,
  `user_agent` text DEFAULT NULL,
  `source` varchar(50) DEFAULT 'web',
  `given_at` datetime DEFAULT NULL,
  `withdrawn_at` datetime DEFAULT NULL,
  `expires_at` datetime DEFAULT NULL,
  `created_at` datetime DEFAULT current_timestamp(),
  `updated_at` datetime DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  `is_active` tinyint(1) DEFAULT 0,
  PRIMARY KEY (`id`),
  UNIQUE KEY `unique_user_consent_version` (`user_id`,`consent_type`,`consent_version`),
  KEY `idx_user_consent` (`user_id`,`consent_type`),
  KEY `idx_tenant_id` (`tenant_id`),
  KEY `idx_consent_type` (`consent_type`),
  KEY `idx_given_at` (`given_at`),
  KEY `idx_user_consents_version_check` (`tenant_id`,`consent_type`,`consent_given`,`consent_version`)
) ENGINE=InnoDB AUTO_INCREMENT=1032 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `user_distance_preference`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `user_distance_preference` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `user_id` int(11) NOT NULL,
  `tenant_id` int(11) NOT NULL,
  `interactions_0_2km` int(11) DEFAULT 0,
  `interactions_2_5km` int(11) DEFAULT 0,
  `interactions_5_15km` int(11) DEFAULT 0,
  `interactions_15_50km` int(11) DEFAULT 0,
  `interactions_50plus_km` int(11) DEFAULT 0,
  `learned_max_distance_km` decimal(8,2) DEFAULT NULL COMMENT 'Calculated from actual behavior',
  `stated_max_distance_km` int(11) DEFAULT 25 COMMENT 'User stated preference',
  `last_updated` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `unique_user_tenant` (`user_id`,`tenant_id`),
  KEY `tenant_id` (`tenant_id`),
  CONSTRAINT `user_distance_preference_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE,
  CONSTRAINT `user_distance_preference_ibfk_2` FOREIGN KEY (`tenant_id`) REFERENCES `tenants` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `user_effective_permissions`;
/*!50001 DROP VIEW IF EXISTS `user_effective_permissions`*/;
SET @saved_cs_client     = @@character_set_client;
SET character_set_client = utf8mb4;
/*!50001 CREATE VIEW `user_effective_permissions` AS SELECT
 1 AS `user_id`,
  1 AS `permission_id`,
  1 AS `permission_name`,
  1 AS `category`,
  1 AS `has_permission`,
  1 AS `grant_source` */;
SET character_set_client = @saved_cs_client;
DROP TABLE IF EXISTS `user_email_preferences`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `user_email_preferences` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `user_id` int(11) NOT NULL,
  `gamification_digest` tinyint(1) DEFAULT 1,
  `badge_notifications` tinyint(1) DEFAULT 1,
  `level_up_notifications` tinyint(1) DEFAULT 1,
  `challenge_notifications` tinyint(1) DEFAULT 1,
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `unique_user` (`user_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb3 COLLATE=utf8mb3_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `user_first_contacts`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `user_first_contacts` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `tenant_id` int(11) NOT NULL,
  `user1_id` int(11) NOT NULL COMMENT 'Lower user ID',
  `user2_id` int(11) NOT NULL COMMENT 'Higher user ID',
  `first_message_id` int(11) NOT NULL,
  `first_contact_at` timestamp NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `unique_user_pair` (`tenant_id`,`user1_id`,`user2_id`),
  KEY `idx_user1` (`user1_id`),
  KEY `idx_user2` (`user2_id`),
  CONSTRAINT `user_first_contacts_ibfk_1` FOREIGN KEY (`tenant_id`) REFERENCES `tenants` (`id`) ON DELETE CASCADE,
  CONSTRAINT `user_first_contacts_ibfk_2` FOREIGN KEY (`user1_id`) REFERENCES `users` (`id`) ON DELETE CASCADE,
  CONSTRAINT `user_first_contacts_ibfk_3` FOREIGN KEY (`user2_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=32 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `user_follows`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `user_follows` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `follower_id` int(10) unsigned NOT NULL COMMENT 'User who is following',
  `following_id` int(10) unsigned NOT NULL COMMENT 'User being followed',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `unique_follow` (`follower_id`,`following_id`),
  KEY `idx_follower` (`follower_id`),
  KEY `idx_following` (`following_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `user_gamification_summary`;
/*!50001 DROP VIEW IF EXISTS `user_gamification_summary`*/;
SET @saved_cs_client     = @@character_set_client;
SET character_set_client = utf8mb4;
/*!50001 CREATE VIEW `user_gamification_summary` AS SELECT
 1 AS `user_id`,
  1 AS `tenant_id`,
  1 AS `xp`,
  1 AS `level`,
  1 AS `login_streak`,
  1 AS `badge_count`,
  1 AS `challenges_completed`,
  1 AS `friend_challenges_won` */;
SET character_set_client = @saved_cs_client;
DROP TABLE IF EXISTS `user_hidden_posts`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `user_hidden_posts` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `user_id` int(10) unsigned NOT NULL COMMENT 'User who hid the post',
  `post_id` int(10) unsigned NOT NULL COMMENT 'Post that was hidden',
  `tenant_id` int(11) NOT NULL DEFAULT 0,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `uk_user_hidden_tenant` (`user_id`,`post_id`,`tenant_id`),
  KEY `idx_user` (`user_id`),
  KEY `idx_post` (`post_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `user_interests`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `user_interests` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `user_id` int(11) NOT NULL,
  `tenant_id` int(11) NOT NULL,
  `category_id` int(11) NOT NULL,
  `interest_type` enum('interest','skill_offer','skill_need') NOT NULL DEFAULT 'interest',
  `created_at` timestamp NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `uk_tenant_user_category_type` (`tenant_id`,`user_id`,`category_id`,`interest_type`),
  KEY `idx_user_id` (`user_id`),
  KEY `idx_category_id` (`category_id`),
  KEY `idx_tenant_id` (`tenant_id`),
  CONSTRAINT `fk_ui_category` FOREIGN KEY (`category_id`) REFERENCES `categories` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_ui_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=155 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `user_legal_acceptances`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `user_legal_acceptances` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `user_id` int(10) unsigned NOT NULL,
  `document_id` int(10) unsigned NOT NULL,
  `version_id` int(10) unsigned NOT NULL,
  `version_number` varchar(20) NOT NULL,
  `accepted_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `acceptance_method` enum('registration','login_prompt','settings','api','forced_update') NOT NULL DEFAULT 'registration',
  `ip_address` varchar(45) DEFAULT NULL,
  `user_agent` text DEFAULT NULL,
  `session_id` varchar(128) DEFAULT NULL,
  `additional_context` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`additional_context`)),
  PRIMARY KEY (`id`),
  UNIQUE KEY `unique_user_version` (`user_id`,`version_id`),
  KEY `idx_user_id` (`user_id`),
  KEY `idx_document_id` (`document_id`),
  KEY `idx_version_id` (`version_id`),
  KEY `idx_accepted_at` (`accepted_at`),
  CONSTRAINT `fk_acceptance_document` FOREIGN KEY (`document_id`) REFERENCES `legal_documents` (`id`) ON DELETE CASCADE ON UPDATE CASCADE,
  CONSTRAINT `fk_acceptance_version` FOREIGN KEY (`version_id`) REFERENCES `legal_document_versions` (`id`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=123 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `user_messaging_restrictions`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `user_messaging_restrictions` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `tenant_id` int(11) NOT NULL,
  `user_id` int(11) NOT NULL,
  `messaging_disabled` tinyint(1) DEFAULT 0 COMMENT 'Messaging disabled for this user',
  `requires_broker_approval` tinyint(1) DEFAULT 0 COMMENT 'All outgoing messages need approval',
  `under_monitoring` tinyint(1) DEFAULT 0 COMMENT 'Messages are copied to broker',
  `monitoring_reason` text DEFAULT NULL,
  `monitoring_started_at` timestamp NULL DEFAULT NULL,
  `monitoring_expires_at` timestamp NULL DEFAULT NULL,
  `restricted_by` int(11) DEFAULT NULL,
  `restricted_at` timestamp NULL DEFAULT NULL,
  `restriction_reason` text DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `unique_tenant_user` (`tenant_id`,`user_id`),
  KEY `idx_monitoring` (`tenant_id`,`under_monitoring`),
  KEY `idx_messaging_disabled` (`tenant_id`,`messaging_disabled`),
  KEY `user_id` (`user_id`),
  KEY `restricted_by` (`restricted_by`),
  CONSTRAINT `user_messaging_restrictions_ibfk_1` FOREIGN KEY (`tenant_id`) REFERENCES `tenants` (`id`) ON DELETE CASCADE,
  CONSTRAINT `user_messaging_restrictions_ibfk_2` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE,
  CONSTRAINT `user_messaging_restrictions_ibfk_3` FOREIGN KEY (`restricted_by`) REFERENCES `users` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB AUTO_INCREMENT=2 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `user_muted_users`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `user_muted_users` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `user_id` int(10) unsigned NOT NULL COMMENT 'User who muted',
  `muted_user_id` int(10) unsigned NOT NULL COMMENT 'User who was muted',
  `tenant_id` int(11) NOT NULL DEFAULT 0,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `uk_user_mute_tenant` (`user_id`,`muted_user_id`,`tenant_id`),
  KEY `idx_user` (`user_id`),
  KEY `idx_muted` (`muted_user_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `user_notification_preferences`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `user_notification_preferences` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `user_id` int(10) unsigned NOT NULL,
  `email_messages` tinyint(1) NOT NULL DEFAULT 1,
  `email_listings` tinyint(1) NOT NULL DEFAULT 1,
  `email_digest` tinyint(1) NOT NULL DEFAULT 1,
  `email_connections` tinyint(1) NOT NULL DEFAULT 1,
  `email_transactions` tinyint(1) NOT NULL DEFAULT 1,
  `email_reviews` tinyint(1) NOT NULL DEFAULT 1,
  `email_gamification_digest` tinyint(1) NOT NULL DEFAULT 1,
  `email_gamification_milestones` tinyint(1) NOT NULL DEFAULT 1,
  `email_org_payments` tinyint(1) NOT NULL DEFAULT 1,
  `email_org_transfers` tinyint(1) NOT NULL DEFAULT 1,
  `email_org_membership` tinyint(1) NOT NULL DEFAULT 1,
  `email_org_admin` tinyint(1) NOT NULL DEFAULT 1,
  `push_enabled` tinyint(1) NOT NULL DEFAULT 1,
  `updated_at` datetime NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `uk_user_notification_preferences_user_id` (`user_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `user_permissions`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `user_permissions` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `user_id` int(11) NOT NULL,
  `permission_id` int(10) unsigned NOT NULL,
  `granted` tinyint(1) DEFAULT 1 COMMENT 'TRUE = grant, FALSE = revoke (override)',
  `granted_by` int(11) DEFAULT NULL COMMENT 'User ID who granted/revoked this permission',
  `granted_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `expires_at` timestamp NULL DEFAULT NULL COMMENT 'Optional expiration',
  `reason` text DEFAULT NULL COMMENT 'Why this override was applied',
  `tenant_id` int(10) unsigned DEFAULT NULL COMMENT 'NULL = global, otherwise tenant-specific',
  PRIMARY KEY (`id`),
  UNIQUE KEY `unique_user_permission` (`user_id`,`permission_id`),
  KEY `idx_user` (`user_id`),
  KEY `idx_permission` (`permission_id`),
  KEY `idx_granted` (`granted`),
  KEY `idx_expires` (`expires_at`),
  CONSTRAINT `user_permissions_ibfk_1` FOREIGN KEY (`permission_id`) REFERENCES `permissions` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `user_points_log`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `user_points_log` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `tenant_id` int(11) NOT NULL,
  `user_id` int(11) NOT NULL,
  `points` int(11) NOT NULL,
  `reason` varchar(255) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `idx_user_points` (`tenant_id`,`user_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `user_presence`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `user_presence` (
  `user_id` int(10) unsigned NOT NULL,
  `tenant_id` int(10) unsigned NOT NULL,
  `status` enum('online','away','dnd','offline') NOT NULL DEFAULT 'offline',
  `custom_status` varchar(80) DEFAULT NULL,
  `status_emoji` varchar(10) DEFAULT NULL,
  `last_seen_at` timestamp NULL DEFAULT NULL,
  `last_activity_at` timestamp NULL DEFAULT NULL,
  `hide_presence` tinyint(1) NOT NULL DEFAULT 0,
  `created_at` timestamp NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`tenant_id`,`user_id`),
  KEY `idx_tenant_status` (`tenant_id`,`status`),
  KEY `idx_last_seen` (`tenant_id`,`last_seen_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `user_roles`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `user_roles` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `user_id` int(11) NOT NULL,
  `role_id` int(10) unsigned NOT NULL,
  `assigned_by` int(11) DEFAULT NULL COMMENT 'User ID who assigned this role',
  `assigned_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `expires_at` timestamp NULL DEFAULT NULL COMMENT 'Optional expiration for temporary access',
  `tenant_id` int(10) unsigned DEFAULT NULL COMMENT 'NULL = global, otherwise tenant-specific',
  PRIMARY KEY (`id`),
  UNIQUE KEY `unique_user_role` (`user_id`,`role_id`),
  KEY `idx_user` (`user_id`),
  KEY `idx_role` (`role_id`),
  KEY `idx_expires` (`expires_at`),
  CONSTRAINT `user_roles_ibfk_1` FOREIGN KEY (`role_id`) REFERENCES `roles` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `user_safeguarding_preferences`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `user_safeguarding_preferences` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `tenant_id` int(11) NOT NULL,
  `user_id` int(11) NOT NULL,
  `option_id` int(10) unsigned NOT NULL COMMENT 'FK to tenant_safeguarding_options',
  `selected_value` varchar(255) NOT NULL DEFAULT '1' COMMENT 'Checkbox: 1/0. Select: chosen value',
  `notes` text DEFAULT NULL COMMENT 'Free-text notes from member',
  `consent_given_at` datetime NOT NULL COMMENT 'GDPR consent timestamp',
  `consent_ip` varchar(45) DEFAULT NULL COMMENT 'IP at time of consent',
  `revoked_at` datetime DEFAULT NULL COMMENT 'Set when member withdraws consent',
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  `updated_at` datetime DEFAULT NULL ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `uk_user_option` (`tenant_id`,`user_id`,`option_id`),
  KEY `idx_tenant_user` (`tenant_id`,`user_id`),
  KEY `idx_option` (`option_id`),
  KEY `fk_usp_user` (`user_id`),
  CONSTRAINT `fk_usp_option` FOREIGN KEY (`option_id`) REFERENCES `tenant_safeguarding_options` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_usp_tenant` FOREIGN KEY (`tenant_id`) REFERENCES `tenants` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_usp_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Member safeguarding preference selections (access-controlled, never in public API)';
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `user_saved_listings`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `user_saved_listings` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `user_id` int(10) unsigned NOT NULL,
  `listing_id` int(10) unsigned NOT NULL,
  `tenant_id` int(10) unsigned NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_user_listing` (`user_id`,`listing_id`,`tenant_id`),
  KEY `idx_user_tenant` (`user_id`,`tenant_id`),
  KEY `idx_listing_tenant` (`listing_id`,`tenant_id`)
) ENGINE=InnoDB AUTO_INCREMENT=3 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `user_skills`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `user_skills` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `user_id` int(11) NOT NULL,
  `tenant_id` int(11) NOT NULL,
  `category_id` int(11) DEFAULT NULL,
  `skill_name` varchar(100) NOT NULL,
  `proficiency` enum('beginner','intermediate','advanced','expert') DEFAULT 'intermediate',
  `is_offering` tinyint(1) NOT NULL DEFAULT 1,
  `is_requesting` tinyint(1) NOT NULL DEFAULT 0,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `idx_user_tenant` (`user_id`,`tenant_id`),
  KEY `idx_category` (`category_id`),
  KEY `idx_skill_name` (`skill_name`),
  KEY `idx_tenant_offering` (`tenant_id`,`is_offering`),
  KEY `idx_tenant_requesting` (`tenant_id`,`is_requesting`),
  CONSTRAINT `fk_user_skills_cat` FOREIGN KEY (`category_id`) REFERENCES `skill_categories` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `user_stats_cache`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `user_stats_cache` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `tenant_id` int(11) NOT NULL,
  `user_id` int(11) NOT NULL,
  `stat_key` varchar(50) NOT NULL,
  `stat_value` int(11) DEFAULT 0,
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `unique_stat` (`tenant_id`,`user_id`,`stat_key`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `user_streaks`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `user_streaks` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `tenant_id` int(11) NOT NULL,
  `user_id` int(11) NOT NULL,
  `streak_type` enum('login','activity','giving','volunteer') NOT NULL,
  `current_streak` int(11) DEFAULT 0,
  `longest_streak` int(11) DEFAULT 0,
  `last_activity_date` date DEFAULT NULL,
  `streak_freezes_remaining` int(11) DEFAULT 1,
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `unique_streak` (`tenant_id`,`user_id`,`streak_type`),
  KEY `idx_streak_type` (`tenant_id`,`streak_type`,`current_streak`)
) ENGINE=InnoDB AUTO_INCREMENT=55 DEFAULT CHARSET=utf8mb3 COLLATE=utf8mb3_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `user_totp_settings`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `user_totp_settings` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `user_id` int(11) NOT NULL,
  `tenant_id` int(11) NOT NULL,
  `totp_secret_encrypted` text DEFAULT NULL COMMENT 'AES-256-GCM encrypted TOTP secret',
  `is_enabled` tinyint(1) NOT NULL DEFAULT 0 COMMENT '1 = 2FA active and enforced',
  `is_pending_setup` tinyint(1) NOT NULL DEFAULT 0 COMMENT '1 = Secret generated but not verified',
  `enabled_at` datetime DEFAULT NULL COMMENT 'When 2FA was successfully enabled',
  `last_verified_at` datetime DEFAULT NULL COMMENT 'Last successful TOTP verification',
  `verified_device_count` int(11) NOT NULL DEFAULT 0 COMMENT 'Number of times 2FA verified',
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  `updated_at` datetime NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `unique_user_totp` (`user_id`),
  KEY `idx_tenant` (`tenant_id`),
  KEY `idx_enabled` (`tenant_id`,`is_enabled`)
) ENGINE=InnoDB AUTO_INCREMENT=9 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='TOTP 2FA settings and encrypted secrets per user';
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `user_trusted_devices`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `user_trusted_devices` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `user_id` int(11) NOT NULL,
  `tenant_id` int(11) NOT NULL,
  `device_token_hash` varchar(255) NOT NULL COMMENT 'SHA-256 hash of the token stored in cookie',
  `device_name` varchar(255) DEFAULT NULL COMMENT 'Browser/OS derived from user agent',
  `device_fingerprint` varchar(64) DEFAULT NULL COMMENT 'Optional fingerprint for additional security',
  `ip_address` varchar(45) NOT NULL,
  `user_agent` text DEFAULT NULL,
  `trusted_at` datetime NOT NULL DEFAULT current_timestamp(),
  `expires_at` datetime NOT NULL COMMENT 'When this trust expires (default 30 days)',
  `last_used_at` datetime DEFAULT NULL COMMENT 'Last time this device was used to skip 2FA',
  `is_revoked` tinyint(1) NOT NULL DEFAULT 0,
  `revoked_at` datetime DEFAULT NULL,
  `revoked_reason` varchar(100) DEFAULT NULL COMMENT 'user_action, password_change, admin_reset, etc.',
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  `updated_at` datetime NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `unique_device_token` (`device_token_hash`),
  KEY `idx_user_active` (`user_id`,`is_revoked`,`expires_at`),
  KEY `idx_tenant` (`tenant_id`),
  KEY `idx_cleanup` (`expires_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Trusted devices that can skip 2FA verification';
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `user_xp_log`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `user_xp_log` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `tenant_id` int(11) NOT NULL,
  `user_id` int(11) NOT NULL,
  `xp_amount` int(11) NOT NULL,
  `action` varchar(50) NOT NULL,
  `description` varchar(255) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `idx_user` (`tenant_id`,`user_id`),
  KEY `idx_user_xp_log_action` (`tenant_id`,`action`)
) ENGINE=InnoDB AUTO_INCREMENT=16299 DEFAULT CHARSET=utf8mb3 COLLATE=utf8mb3_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `user_xp_purchases`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `user_xp_purchases` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `tenant_id` int(11) NOT NULL,
  `user_id` int(11) NOT NULL,
  `item_id` int(11) NOT NULL,
  `xp_spent` int(11) NOT NULL,
  `purchased_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `expires_at` timestamp NULL DEFAULT NULL,
  `is_active` tinyint(1) DEFAULT 1,
  `claimed_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `idx_user` (`user_id`),
  KEY `idx_tenant_user` (`tenant_id`,`user_id`),
  KEY `item_id` (`item_id`),
  CONSTRAINT `user_xp_purchases_ibfk_1` FOREIGN KEY (`item_id`) REFERENCES `xp_shop_items` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb3 COLLATE=utf8mb3_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `users`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `users` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `first_name` varchar(100) DEFAULT NULL,
  `last_name` varchar(100) DEFAULT NULL,
  `tenant_id` int(11) DEFAULT NULL,
  `name` varchar(255) NOT NULL,
  `email` varchar(255) NOT NULL,
  `date_of_birth` date DEFAULT NULL,
  `username` varchar(50) DEFAULT NULL,
  `password_hash` varchar(255) DEFAULT NULL,
  `password` varchar(255) DEFAULT NULL,
  `remember_token` varchar(100) DEFAULT NULL,
  `totp_enabled` tinyint(1) NOT NULL DEFAULT 0 COMMENT '1 = User has 2FA enabled',
  `totp_setup_required` tinyint(1) NOT NULL DEFAULT 1 COMMENT '1 = User must set up 2FA on next login',
  `role` varchar(50) DEFAULT 'member',
  `profile_type` varchar(50) DEFAULT 'individual',
  `organization_name` varchar(255) DEFAULT NULL,
  `is_super_admin` tinyint(1) DEFAULT 0,
  `is_approved` tinyint(1) DEFAULT 0,
  `verification_status` enum('none','pending','passed','failed','expired') NOT NULL DEFAULT 'none',
  `verification_provider` varchar(50) DEFAULT NULL,
  `verification_completed_at` datetime DEFAULT NULL,
  `status` enum('active','inactive','suspended','banned','pending') DEFAULT 'active',
  `balance` int(11) DEFAULT 0,
  `bio` text DEFAULT NULL,
  `privacy_profile` enum('public','members','connections') DEFAULT 'public',
  `privacy_search` tinyint(1) DEFAULT 1,
  `privacy_contact` tinyint(1) DEFAULT 0,
  `location` varchar(255) DEFAULT NULL,
  `phone` varchar(50) DEFAULT NULL,
  `avatar_url` varchar(255) DEFAULT NULL,
  `created_at` datetime DEFAULT current_timestamp(),
  `last_login_at` timestamp NULL DEFAULT NULL,
  `last_active_at` timestamp NULL DEFAULT NULL,
  `last_activity` datetime DEFAULT NULL,
  `points` int(11) DEFAULT 0,
  `latitude` decimal(10,8) DEFAULT NULL,
  `longitude` decimal(11,8) DEFAULT NULL,
  `push_enabled` tinyint(1) DEFAULT 1 COMMENT 'Whether user wants push notifications',
  `biometric_enabled` tinyint(1) DEFAULT 0 COMMENT 'Whether user has biometric auth enabled',
  `xp` int(11) DEFAULT 0,
  `level` int(11) DEFAULT 1,
  `show_on_leaderboard` tinyint(1) DEFAULT 1,
  `referral_code` varchar(20) DEFAULT NULL,
  `referred_by` int(11) DEFAULT NULL,
  `login_streak` int(11) DEFAULT 0,
  `last_daily_reward` date DEFAULT NULL,
  `email_preferences` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`email_preferences`)),
  `notification_preferences` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`notification_preferences`)),
  `skills` text DEFAULT NULL COMMENT 'Comma-separated skills',
  `longest_streak` int(11) DEFAULT 0,
  `gamification_enabled` tinyint(1) DEFAULT 1,
  `updated_at` timestamp NULL DEFAULT NULL ON UPDATE current_timestamp(),
  `anonymized_at` datetime DEFAULT NULL,
  `gdpr_export_requested_at` datetime DEFAULT NULL,
  `gdpr_deletion_requested_at` datetime DEFAULT NULL,
  `deleted_at` datetime DEFAULT NULL,
  `is_admin` tinyint(1) DEFAULT 0 COMMENT 'Legacy admin flag (deprecated, use roles)',
  `max_permission_level` int(10) unsigned DEFAULT 0 COMMENT 'Maximum permission level this user can grant',
  `permissions_last_updated` timestamp NULL DEFAULT NULL COMMENT 'Cache invalidation timestamp',
  `email_verified_at` timestamp NULL DEFAULT NULL COMMENT 'Timestamp when user verified their email address',
  `verification_token` varchar(255) DEFAULT NULL,
  `is_verified` tinyint(1) NOT NULL DEFAULT 0 COMMENT 'Whether user account is verified (1) or not (0)',
  `award` varchar(255) DEFAULT NULL,
  `banned_until` varchar(255) DEFAULT NULL,
  `blocker_user_id` int(11) DEFAULT NULL,
  `claimed_at` timestamp NULL DEFAULT NULL,
  `click_rate` timestamp NULL DEFAULT NULL,
  `clicked_at` timestamp NULL DEFAULT NULL,
  `content` text DEFAULT NULL,
  `distance` varchar(255) DEFAULT NULL,
  `distance_km` varchar(255) DEFAULT NULL,
  `errors` varchar(255) DEFAULT NULL,
  `event` varchar(255) DEFAULT NULL,
  `is_active` tinyint(1) DEFAULT 0,
  `key_name` varchar(255) DEFAULT NULL,
  `last_login` varchar(255) DEFAULT NULL,
  `last_transaction` varchar(255) DEFAULT NULL,
  `match` timestamp NULL DEFAULT NULL,
  `open_rate` timestamp NULL DEFAULT NULL,
  `pages` varchar(255) DEFAULT NULL,
  `preferred_layout` varchar(20) DEFAULT 'modern',
  `preferred_theme` enum('light','dark','system') DEFAULT 'dark',
  `theme_preferences` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`theme_preferences`)),
  `reset_token` varchar(255) DEFAULT NULL,
  `resolved_at` timestamp NULL DEFAULT NULL,
  `reviewed_at` timestamp NULL DEFAULT NULL,
  `is_tenant_super_admin` tinyint(1) NOT NULL DEFAULT 0 COMMENT 'Can this user access Super Admin Panel for their tenant subtree?',
  `federation_optin` tinyint(1) NOT NULL DEFAULT 0,
  `federated_profile_visible` tinyint(1) NOT NULL DEFAULT 0,
  `federation_notifications_enabled` tinyint(1) NOT NULL DEFAULT 1,
  `is_god` tinyint(1) NOT NULL DEFAULT 0 COMMENT 'God mode: can grant/revoke super admin privileges from other users',
  `onboarding_completed` tinyint(1) NOT NULL DEFAULT 0,
  `vetting_status` enum('none','pending','verified','expired') NOT NULL DEFAULT 'none',
  `vetting_expires_at` date DEFAULT NULL,
  `works_with_children` tinyint(1) NOT NULL DEFAULT 0,
  `works_with_vulnerable_adults` tinyint(1) NOT NULL DEFAULT 0,
  `no_home_visits` tinyint(1) NOT NULL DEFAULT 0,
  `requires_home_visits` tinyint(1) NOT NULL DEFAULT 0,
  `safeguarding_notes` text DEFAULT NULL,
  `safeguarding_reviewed_by` int(11) DEFAULT NULL,
  `safeguarding_reviewed_at` datetime DEFAULT NULL,
  `tagline` varchar(255) DEFAULT NULL,
  `reset_token_expiry` datetime DEFAULT NULL,
  `insurance_status` enum('none','pending','verified','expired') NOT NULL DEFAULT 'none',
  `insurance_expires_at` date DEFAULT NULL,
  `preferred_language` varchar(5) NOT NULL DEFAULT 'en',
  `availability` varchar(255) DEFAULT NULL COMMENT 'e.g. weekdays, weekends, flexible',
  `interests` text DEFAULT NULL COMMENT 'Comma-separated interest keywords',
  `resume_searchable` tinyint(1) DEFAULT 0,
  `resume_headline` varchar(255) DEFAULT NULL,
  `resume_summary` text DEFAULT NULL,
  `stripe_customer_id` varchar(255) DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `unique_email_tenant` (`email`,`tenant_id`),
  UNIQUE KEY `idx_tenant_username` (`tenant_id`,`username`),
  KEY `idx_users_email` (`email`),
  KEY `idx_users_tenant` (`tenant_id`),
  KEY `idx_privacy_profile` (`privacy_profile`),
  KEY `idx_users_coordinates` (`latitude`,`longitude`),
  KEY `idx_username` (`username`),
  KEY `idx_users_last_activity` (`last_activity`),
  KEY `idx_users_status` (`status`),
  KEY `idx_users_last_login` (`last_login_at`),
  KEY `idx_users_last_active` (`last_active_at`),
  KEY `idx_users_last_login_tenant` (`tenant_id`,`is_approved`,`last_login_at`),
  KEY `idx_users_created_tenant` (`tenant_id`,`is_approved`,`created_at`),
  KEY `idx_users_xp` (`xp`),
  KEY `idx_users_level` (`level`),
  KEY `idx_referral_code` (`referral_code`),
  KEY `idx_users_xp_leaderboard` (`tenant_id`,`is_approved`,`xp`),
  KEY `idx_user_coords` (`latitude`,`longitude`),
  KEY `idx_users_tenant_balance` (`tenant_id`,`balance`),
  KEY `idx_users_tenant_created` (`tenant_id`,`created_at`),
  KEY `idx_preferred_layout` (`tenant_id`),
  KEY `idx_email_verified` (`email_verified_at`),
  KEY `idx_is_verified` (`is_verified`),
  KEY `idx_names` (`first_name`,`last_name`),
  KEY `idx_users_is_super_admin` (`is_super_admin`),
  KEY `idx_users_is_god` (`is_god`),
  KEY `idx_users_role` (`role`),
  KEY `idx_users_tenant_role` (`tenant_id`,`role`),
  KEY `idx_users_preferred_layout` (`preferred_layout`),
  KEY `idx_users_name_tenant` (`tenant_id`,`name`),
  KEY `idx_users_email_tenant` (`tenant_id`,`email`),
  KEY `idx_users_location_tenant` (`tenant_id`,`location`),
  KEY `idx_users_active_search` (`tenant_id`,`last_active_at`,`name`),
  KEY `idx_users_totp` (`totp_enabled`),
  KEY `idx_users_theme` (`preferred_theme`),
  KEY `idx_users_verification` (`tenant_id`,`verification_status`),
  KEY `idx_users_stripe_customer` (`tenant_id`,`stripe_customer_id`),
  FULLTEXT KEY `ft_users_search` (`first_name`,`last_name`,`bio`,`skills`),
  CONSTRAINT `users_ibfk_1` FOREIGN KEY (`tenant_id`) REFERENCES `tenants` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=419 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `v_active_listings_with_coords`;
/*!50001 DROP VIEW IF EXISTS `v_active_listings_with_coords`*/;
SET @saved_cs_client     = @@character_set_client;
SET character_set_client = utf8mb4;
/*!50001 CREATE VIEW `v_active_listings_with_coords` AS SELECT
 1 AS `id`,
  1 AS `user_id`,
  1 AS `tenant_id`,
  1 AS `title`,
  1 AS `description`,
  1 AS `type`,
  1 AS `category_id`,
  1 AS `image_url`,
  1 AS `status`,
  1 AS `created_at`,
  1 AS `latitude`,
  1 AS `longitude`,
  1 AS `first_name`,
  1 AS `last_name`,
  1 AS `avatar_url`,
  1 AS `author_location`,
  1 AS `category_name`,
  1 AS `category_color` */;
SET character_set_client = @saved_cs_client;
DROP TABLE IF EXISTS `v_legal_acceptance_stats`;
/*!50001 DROP VIEW IF EXISTS `v_legal_acceptance_stats`*/;
SET @saved_cs_client     = @@character_set_client;
SET character_set_client = utf8mb4;
/*!50001 CREATE VIEW `v_legal_acceptance_stats` AS SELECT
 1 AS `document_id`,
  1 AS `tenant_id`,
  1 AS `document_type`,
  1 AS `title`,
  1 AS `version_id`,
  1 AS `version_number`,
  1 AS `effective_date`,
  1 AS `is_current`,
  1 AS `total_acceptances`,
  1 AS `first_acceptance`,
  1 AS `last_acceptance` */;
SET character_set_client = @saved_cs_client;
DROP TABLE IF EXISTS `vetting_records`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `vetting_records` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `tenant_id` int(11) NOT NULL,
  `user_id` int(11) NOT NULL,
  `vetting_type` enum('dbs_basic','dbs_standard','dbs_enhanced','garda_vetting','access_ni','pvg_scotland','international','other') NOT NULL DEFAULT 'dbs_basic',
  `status` enum('pending','submitted','verified','expired','rejected','revoked') NOT NULL DEFAULT 'pending',
  `reference_number` varchar(100) DEFAULT NULL COMMENT 'DBS certificate number or Garda ref',
  `issue_date` date DEFAULT NULL,
  `expiry_date` date DEFAULT NULL,
  `verified_by` int(11) DEFAULT NULL COMMENT 'Admin/broker who verified',
  `verified_at` datetime DEFAULT NULL,
  `document_url` varchar(500) DEFAULT NULL COMMENT 'Optional uploaded document path',
  `notes` text DEFAULT NULL COMMENT 'Internal broker notes',
  `works_with_children` tinyint(1) NOT NULL DEFAULT 0,
  `works_with_vulnerable_adults` tinyint(1) NOT NULL DEFAULT 0,
  `requires_enhanced_check` tinyint(1) NOT NULL DEFAULT 0,
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  `updated_at` datetime DEFAULT NULL ON UPDATE current_timestamp(),
  `deleted_at` timestamp NULL DEFAULT NULL,
  `rejected_by` int(11) DEFAULT NULL COMMENT 'Admin/broker who rejected',
  `rejected_at` datetime DEFAULT NULL,
  `rejection_reason` text DEFAULT NULL COMMENT 'Reason for rejection',
  PRIMARY KEY (`id`),
  KEY `idx_vetting_tenant` (`tenant_id`),
  KEY `idx_vetting_user` (`user_id`),
  KEY `idx_vetting_status` (`status`),
  KEY `idx_vetting_expiry` (`expiry_date`),
  KEY `idx_vetting_rejected_by` (`rejected_by`),
  KEY `idx_vetting_records_deleted_at` (`deleted_at`),
  CONSTRAINT `vetting_records_ibfk_1` FOREIGN KEY (`tenant_id`) REFERENCES `tenants` (`id`) ON DELETE CASCADE,
  CONSTRAINT `vetting_records_ibfk_2` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=2 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `vol_accessibility_needs`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `vol_accessibility_needs` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `tenant_id` int(10) unsigned NOT NULL,
  `user_id` int(10) unsigned NOT NULL,
  `need_type` enum('mobility','visual','hearing','cognitive','dietary','language','other') NOT NULL,
  `description` text DEFAULT NULL,
  `accommodations_required` text DEFAULT NULL,
  `emergency_contact_name` varchar(255) DEFAULT NULL,
  `emergency_contact_phone` varchar(50) DEFAULT NULL,
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  `updated_at` datetime NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `uk_vol_accessibility` (`tenant_id`,`user_id`,`need_type`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `vol_applications`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `vol_applications` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `tenant_id` int(10) unsigned NOT NULL DEFAULT 1,
  `opportunity_id` int(11) NOT NULL,
  `shift_id` int(11) DEFAULT NULL,
  `user_id` int(11) NOT NULL,
  `status` enum('pending','approved','declined') NOT NULL DEFAULT 'pending',
  `message` text DEFAULT NULL,
  `org_note` varchar(1000) DEFAULT NULL COMMENT 'Note from org owner when approving/declining',
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  `updated_at` datetime NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `idx_vol_app_opp` (`opportunity_id`),
  KEY `idx_vol_app_user` (`user_id`),
  KEY `idx_app_shift` (`shift_id`),
  KEY `idx_tenant_id` (`tenant_id`),
  CONSTRAINT `vol_applications_shift_id_foreign` FOREIGN KEY (`shift_id`) REFERENCES `vol_shifts` (`id`) ON DELETE SET NULL ON UPDATE CASCADE,
  CONSTRAINT `vol_applications_user_id_foreign` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=7 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `vol_certificates`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `vol_certificates` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `tenant_id` int(11) NOT NULL,
  `user_id` int(11) NOT NULL,
  `verification_code` varchar(32) NOT NULL COMMENT 'Unique code for QR verification',
  `total_hours` decimal(10,2) NOT NULL DEFAULT 0.00,
  `date_range_start` date NOT NULL,
  `date_range_end` date NOT NULL,
  `organizations` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL COMMENT 'JSON array of org names/hours' CHECK (json_valid(`organizations`)),
  `generated_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `downloaded_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `unique_verification` (`verification_code`),
  KEY `idx_tenant_user` (`tenant_id`,`user_id`),
  KEY `idx_user_date` (`user_id`,`date_range_start`,`date_range_end`),
  CONSTRAINT `vol_certificates_user_id_foreign` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Generated volunteer impact certificates';
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `vol_community_project_supporters`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `vol_community_project_supporters` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `tenant_id` int(10) unsigned NOT NULL,
  `project_id` int(10) unsigned NOT NULL,
  `user_id` int(10) unsigned NOT NULL,
  `message` text DEFAULT NULL,
  `supported_at` datetime NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `uk_project_supporter` (`project_id`,`user_id`),
  KEY `idx_supporters_tenant` (`tenant_id`,`project_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `vol_community_projects`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `vol_community_projects` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `tenant_id` int(10) unsigned NOT NULL,
  `proposed_by` int(10) unsigned NOT NULL,
  `title` varchar(255) NOT NULL,
  `description` text NOT NULL,
  `category` varchar(100) DEFAULT NULL,
  `location` varchar(255) DEFAULT NULL,
  `latitude` decimal(10,8) DEFAULT NULL,
  `longitude` decimal(11,8) DEFAULT NULL,
  `target_volunteers` int(10) unsigned DEFAULT NULL,
  `proposed_date` date DEFAULT NULL,
  `skills_needed` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`skills_needed`)),
  `estimated_hours` decimal(5,1) DEFAULT NULL,
  `status` enum('proposed','under_review','approved','rejected','active','completed','cancelled') NOT NULL DEFAULT 'proposed',
  `reviewed_by` int(10) unsigned DEFAULT NULL,
  `reviewed_at` datetime DEFAULT NULL,
  `review_notes` text DEFAULT NULL,
  `opportunity_id` int(10) unsigned DEFAULT NULL,
  `supporter_count` int(10) unsigned NOT NULL DEFAULT 0,
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  `updated_at` datetime NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `idx_vol_community_projects_tenant` (`tenant_id`),
  KEY `idx_vol_community_projects_status` (`tenant_id`,`status`),
  KEY `idx_vol_community_projects_proposer` (`tenant_id`,`proposed_by`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `vol_credentials`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `vol_credentials` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `tenant_id` int(10) unsigned NOT NULL,
  `user_id` int(10) unsigned NOT NULL,
  `credential_type` varchar(100) NOT NULL COMMENT 'e.g. garda_vetting, first_aid, safeguarding',
  `file_url` varchar(500) DEFAULT NULL,
  `file_name` varchar(255) DEFAULT NULL,
  `status` enum('pending','verified','rejected','expired') NOT NULL DEFAULT 'pending',
  `verified_by` int(10) unsigned DEFAULT NULL,
  `verified_at` datetime DEFAULT NULL,
  `expires_at` date DEFAULT NULL,
  `notes` text DEFAULT NULL,
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  `updated_at` datetime NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `idx_vol_cred_tenant` (`tenant_id`),
  KEY `idx_vol_cred_user` (`user_id`,`tenant_id`),
  KEY `idx_vol_cred_status` (`status`,`tenant_id`)
) ENGINE=InnoDB AUTO_INCREMENT=2 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Volunteer credential verification (V5): uploaded certs with admin review workflow';
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `vol_custom_field_values`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `vol_custom_field_values` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `tenant_id` int(10) unsigned NOT NULL,
  `custom_field_id` int(10) unsigned NOT NULL,
  `entity_type` varchar(50) NOT NULL,
  `entity_id` int(10) unsigned NOT NULL,
  `field_value` text DEFAULT NULL,
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  `updated_at` datetime NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `uk_field_entity` (`custom_field_id`,`entity_type`,`entity_id`),
  KEY `idx_vol_field_values_field` (`custom_field_id`,`entity_type`,`entity_id`),
  KEY `idx_vol_field_values_entity` (`tenant_id`,`entity_type`,`entity_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `vol_custom_fields`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `vol_custom_fields` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `tenant_id` int(10) unsigned NOT NULL,
  `organization_id` int(10) unsigned DEFAULT NULL,
  `field_label` varchar(255) NOT NULL,
  `field_key` varchar(100) NOT NULL,
  `field_type` enum('text','textarea','select','checkbox','radio','date','file','number','email','phone') NOT NULL,
  `field_options` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`field_options`)),
  `placeholder` varchar(255) DEFAULT NULL,
  `help_text` text DEFAULT NULL,
  `validation_rules` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`validation_rules`)),
  `is_required` tinyint(1) NOT NULL DEFAULT 0,
  `display_order` int(11) NOT NULL DEFAULT 0,
  `is_active` tinyint(1) NOT NULL DEFAULT 1,
  `applies_to` enum('application','opportunity','shift','profile') NOT NULL DEFAULT 'application',
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  `updated_at` datetime NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `idx_vol_custom_fields_tenant_org` (`tenant_id`,`organization_id`),
  KEY `idx_vol_custom_fields_applies` (`tenant_id`,`applies_to`,`is_active`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `vol_donations`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `vol_donations` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `tenant_id` int(10) unsigned NOT NULL,
  `user_id` int(10) unsigned DEFAULT NULL,
  `opportunity_id` int(10) unsigned DEFAULT NULL,
  `community_project_id` int(10) unsigned DEFAULT NULL,
  `giving_day_id` int(10) unsigned DEFAULT NULL,
  `amount` decimal(10,2) NOT NULL,
  `currency` varchar(10) DEFAULT NULL,
  `payment_method` varchar(50) DEFAULT NULL,
  `payment_reference` varchar(255) DEFAULT NULL,
  `donor_name` varchar(255) DEFAULT NULL,
  `donor_email` varchar(255) DEFAULT NULL,
  `message` text DEFAULT NULL,
  `is_anonymous` tinyint(1) NOT NULL DEFAULT 0,
  `status` enum('pending','completed','refunded','failed') NOT NULL DEFAULT 'pending',
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  `stripe_payment_intent_id` varchar(255) DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `idx_vd_stripe_pi` (`stripe_payment_intent_id`),
  KEY `idx_vol_donations_tenant` (`tenant_id`),
  KEY `idx_vol_donations_opportunity` (`tenant_id`,`opportunity_id`),
  KEY `idx_vol_donations_project` (`tenant_id`,`community_project_id`),
  KEY `idx_vol_donations_status` (`tenant_id`,`status`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `vol_emergency_alert_recipients`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `vol_emergency_alert_recipients` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `alert_id` int(11) NOT NULL,
  `tenant_id` int(10) unsigned NOT NULL DEFAULT 1,
  `user_id` int(11) NOT NULL,
  `notified_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `response` enum('pending','accepted','declined') NOT NULL DEFAULT 'pending',
  `responded_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `unique_alert_user` (`alert_id`,`user_id`),
  KEY `idx_user_response` (`user_id`,`response`),
  KEY `idx_ear_tenant` (`tenant_id`),
  CONSTRAINT `vol_emergency_alert_recipients_alert_id_foreign` FOREIGN KEY (`alert_id`) REFERENCES `vol_emergency_alerts` (`id`) ON DELETE CASCADE ON UPDATE CASCADE,
  CONSTRAINT `vol_emergency_alert_recipients_user_id_foreign` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Recipients of emergency volunteer alerts';
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `vol_emergency_alerts`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `vol_emergency_alerts` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `tenant_id` int(11) NOT NULL,
  `shift_id` int(11) NOT NULL,
  `created_by` int(11) NOT NULL COMMENT 'Coordinator who created the alert',
  `priority` enum('normal','urgent','critical') NOT NULL DEFAULT 'urgent',
  `message` text NOT NULL,
  `required_skills` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL COMMENT 'Skills needed for this alert' CHECK (json_valid(`required_skills`)),
  `status` enum('active','filled','expired','cancelled') NOT NULL DEFAULT 'active',
  `filled_at` timestamp NULL DEFAULT NULL,
  `expires_at` timestamp NULL DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` datetime NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `idx_tenant_status` (`tenant_id`,`status`),
  KEY `idx_shift` (`shift_id`),
  KEY `idx_priority` (`priority`,`status`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Urgent volunteer fill requests sent to qualified volunteers';
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `vol_expense_policies`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `vol_expense_policies` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `tenant_id` int(10) unsigned NOT NULL,
  `organization_id` int(10) unsigned DEFAULT NULL,
  `expense_type` varchar(50) NOT NULL,
  `max_amount` decimal(10,2) DEFAULT NULL,
  `max_monthly` decimal(10,2) DEFAULT NULL,
  `requires_receipt_above` decimal(10,2) NOT NULL DEFAULT 0.00,
  `requires_approval` tinyint(1) NOT NULL DEFAULT 1,
  `notes` text DEFAULT NULL,
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  `updated_at` datetime NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `uk_vol_expense_policy` (`tenant_id`,`organization_id`,`expense_type`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `vol_expenses`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `vol_expenses` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `tenant_id` int(10) unsigned NOT NULL,
  `user_id` int(10) unsigned NOT NULL,
  `organization_id` int(10) unsigned DEFAULT NULL,
  `opportunity_id` int(10) unsigned DEFAULT NULL,
  `shift_id` int(10) unsigned DEFAULT NULL,
  `expense_type` enum('travel','meals','supplies','equipment','parking','other') NOT NULL,
  `amount` decimal(10,2) NOT NULL,
  `currency` varchar(10) DEFAULT NULL,
  `description` text DEFAULT NULL,
  `receipt_path` varchar(500) DEFAULT NULL,
  `receipt_filename` varchar(255) DEFAULT NULL,
  `status` enum('pending','approved','rejected','paid') NOT NULL DEFAULT 'pending',
  `submitted_at` datetime NOT NULL DEFAULT current_timestamp(),
  `reviewed_by` int(10) unsigned DEFAULT NULL,
  `reviewed_at` datetime DEFAULT NULL,
  `review_notes` text DEFAULT NULL,
  `paid_at` datetime DEFAULT NULL,
  `payment_reference` varchar(100) DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `idx_vol_expenses_tenant_user` (`tenant_id`,`user_id`),
  KEY `idx_vol_expenses_tenant_org` (`tenant_id`,`organization_id`),
  KEY `idx_vol_expenses_status` (`tenant_id`,`status`),
  KEY `idx_vol_expenses_date` (`tenant_id`,`submitted_at`)
) ENGINE=InnoDB AUTO_INCREMENT=2 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `vol_giving_days`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `vol_giving_days` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `tenant_id` int(10) unsigned NOT NULL,
  `title` varchar(255) NOT NULL,
  `description` text DEFAULT NULL,
  `target_amount` decimal(10,2) DEFAULT NULL,
  `goal_amount` decimal(10,2) DEFAULT NULL,
  `raised_amount` decimal(10,2) NOT NULL DEFAULT 0.00,
  `target_hours` decimal(10,1) DEFAULT NULL,
  `start_date` date NOT NULL,
  `end_date` date NOT NULL,
  `is_active` tinyint(1) NOT NULL DEFAULT 1,
  `created_by` int(10) unsigned NOT NULL,
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `idx_vol_giving_days_tenant` (`tenant_id`),
  KEY `idx_vol_giving_days_dates` (`tenant_id`,`start_date`,`end_date`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `vol_guardian_consents`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `vol_guardian_consents` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `tenant_id` int(10) unsigned NOT NULL,
  `minor_user_id` int(10) unsigned NOT NULL,
  `guardian_name` varchar(255) NOT NULL,
  `guardian_email` varchar(255) NOT NULL,
  `guardian_phone` varchar(50) DEFAULT NULL,
  `relationship` varchar(100) NOT NULL,
  `consent_token` varchar(64) NOT NULL,
  `consent_given_at` datetime DEFAULT NULL,
  `consent_ip` varchar(45) DEFAULT NULL,
  `consent_withdrawn_at` datetime DEFAULT NULL,
  `opportunity_id` int(10) unsigned DEFAULT NULL,
  `expires_at` datetime DEFAULT NULL,
  `status` enum('pending','active','expired','withdrawn') NOT NULL DEFAULT 'pending',
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `idx_guardian_consent_minor` (`tenant_id`,`minor_user_id`),
  KEY `idx_guardian_consent_token` (`consent_token`),
  KEY `idx_guardian_consent_status` (`tenant_id`,`status`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `vol_logs`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `vol_logs` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `tenant_id` int(10) unsigned NOT NULL DEFAULT 1,
  `user_id` int(11) NOT NULL,
  `organization_id` int(11) NOT NULL,
  `opportunity_id` int(11) DEFAULT NULL,
  `date_logged` date NOT NULL,
  `hours` decimal(5,2) NOT NULL,
  `description` text DEFAULT NULL,
  `status` enum('pending','approved','declined') DEFAULT 'pending',
  `feedback` text DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `user_id` (`user_id`),
  KEY `organization_id` (`organization_id`),
  KEY `idx_tenant_id` (`tenant_id`),
  KEY `vol_logs_opportunity_id_foreign` (`opportunity_id`),
  CONSTRAINT `vol_logs_opportunity_id_foreign` FOREIGN KEY (`opportunity_id`) REFERENCES `vol_opportunities` (`id`) ON DELETE SET NULL ON UPDATE CASCADE,
  CONSTRAINT `vol_logs_user_id_foreign` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=3 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `vol_mood_checkins`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `vol_mood_checkins` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `tenant_id` int(10) unsigned NOT NULL,
  `user_id` int(10) unsigned NOT NULL,
  `mood` tinyint(3) unsigned NOT NULL,
  `note` text DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` datetime NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `idx_tenant_user` (`tenant_id`,`user_id`),
  KEY `idx_created` (`created_at`)
) ENGINE=InnoDB AUTO_INCREMENT=6 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `vol_opportunities`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `vol_opportunities` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `tenant_id` int(11) NOT NULL DEFAULT 1,
  `organization_id` int(11) NOT NULL,
  `title` varchar(255) NOT NULL,
  `description` text NOT NULL,
  `location` varchar(255) DEFAULT NULL,
  `skills_needed` varchar(255) DEFAULT NULL,
  `start_date` date DEFAULT NULL,
  `end_date` date DEFAULT NULL,
  `is_active` tinyint(1) NOT NULL DEFAULT 1,
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  `category_id` int(11) DEFAULT NULL,
  `status` varchar(20) DEFAULT 'open',
  `credits_offered` int(11) DEFAULT 0,
  `created_by` int(11) DEFAULT NULL,
  `latitude` decimal(10,8) DEFAULT NULL,
  `longitude` decimal(11,8) DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `idx_vol_opp_org` (`organization_id`),
  KEY `idx_vol_opp_active` (`is_active`),
  KEY `fk_vol_cat` (`category_id`),
  KEY `idx_vol_opp_tenant` (`tenant_id`),
  KEY `idx_vol_geo` (`latitude`,`longitude`),
  CONSTRAINT `fk_vol_cat` FOREIGN KEY (`category_id`) REFERENCES `categories` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB AUTO_INCREMENT=19 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `vol_org_transactions`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `vol_org_transactions` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `tenant_id` int(11) NOT NULL,
  `vol_organization_id` int(11) NOT NULL,
  `user_id` int(11) DEFAULT NULL COMMENT 'Volunteer or admin who triggered this',
  `vol_log_id` int(11) DEFAULT NULL COMMENT 'Links to approved hours entry',
  `type` enum('deposit','withdrawal','volunteer_payment','admin_adjustment') NOT NULL,
  `amount` decimal(10,2) NOT NULL COMMENT 'Positive=credit to org, Negative=debit from org',
  `balance_after` decimal(10,2) NOT NULL COMMENT 'Org balance after this transaction',
  `description` text DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `idx_vot_tenant` (`tenant_id`),
  KEY `idx_vot_org` (`vol_organization_id`),
  KEY `idx_vot_user` (`user_id`),
  KEY `idx_vot_log` (`vol_log_id`),
  KEY `idx_vot_date` (`created_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `vol_organizations`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `vol_organizations` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `tenant_id` int(11) NOT NULL,
  `user_id` int(11) NOT NULL COMMENT 'Owner/Manager',
  `name` varchar(255) NOT NULL,
  `slug` varchar(255) DEFAULT NULL,
  `description` text DEFAULT NULL,
  `contact_email` varchar(255) DEFAULT NULL,
  `website` varchar(255) DEFAULT NULL,
  `logo_url` varchar(255) DEFAULT NULL,
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  `status` varchar(20) DEFAULT 'pending',
  `auto_pay_enabled` tinyint(1) DEFAULT 0,
  `balance` decimal(10,2) NOT NULL DEFAULT 0.00,
  `updated_at` timestamp NULL DEFAULT NULL,
  `dlp_user_id` int(10) unsigned DEFAULT NULL,
  `deputy_dlp_user_id` int(10) unsigned DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `idx_vol_org_tenant` (`tenant_id`),
  KEY `idx_vol_org_user` (`user_id`),
  KEY `idx_vo_slug` (`tenant_id`,`slug`),
  KEY `idx_vol_org_balance` (`tenant_id`,`balance`)
) ENGINE=InnoDB AUTO_INCREMENT=10 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `vol_reminder_settings`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `vol_reminder_settings` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `tenant_id` int(10) unsigned NOT NULL,
  `reminder_type` enum('pre_shift','post_shift_feedback','lapsed_volunteer','credential_expiry','training_expiry') NOT NULL,
  `enabled` tinyint(1) NOT NULL DEFAULT 1,
  `hours_before` int(11) DEFAULT NULL,
  `hours_after` int(11) DEFAULT NULL,
  `days_inactive` int(11) DEFAULT NULL,
  `days_before_expiry` int(11) DEFAULT NULL,
  `email_template` text DEFAULT NULL,
  `push_enabled` tinyint(1) NOT NULL DEFAULT 1,
  `email_enabled` tinyint(1) NOT NULL DEFAULT 1,
  `sms_enabled` tinyint(1) NOT NULL DEFAULT 0,
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  `updated_at` datetime NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `uk_vol_reminder_setting` (`tenant_id`,`reminder_type`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `vol_reminders_sent`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `vol_reminders_sent` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `tenant_id` int(10) unsigned NOT NULL,
  `user_id` int(10) unsigned NOT NULL,
  `reminder_type` varchar(50) NOT NULL,
  `reference_id` int(10) unsigned DEFAULT NULL,
  `channel` enum('email','push','sms') NOT NULL,
  `sent_at` datetime NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `idx_vol_reminders_tenant_user` (`tenant_id`,`user_id`,`reminder_type`),
  KEY `idx_vol_reminders_ref` (`tenant_id`,`reminder_type`,`reference_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `vol_reviews`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `vol_reviews` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `tenant_id` int(10) unsigned NOT NULL DEFAULT 1,
  `reviewer_id` int(11) NOT NULL,
  `target_type` enum('organization','user') NOT NULL,
  `target_id` int(11) NOT NULL,
  `rating` int(11) NOT NULL,
  `comment` text DEFAULT NULL,
  `approved` tinyint(1) DEFAULT 1,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `target_type` (`target_type`,`target_id`),
  KEY `idx_tenant_id` (`tenant_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `vol_safeguarding_incidents`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `vol_safeguarding_incidents` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `tenant_id` int(10) unsigned NOT NULL,
  `shift_id` int(10) unsigned DEFAULT NULL,
  `opportunity_id` int(10) unsigned DEFAULT NULL,
  `organization_id` int(10) unsigned DEFAULT NULL,
  `reported_by` int(10) unsigned NOT NULL,
  `title` varchar(255) DEFAULT NULL,
  `subject_user_id` int(10) unsigned DEFAULT NULL,
  `involved_user_id` int(10) unsigned DEFAULT NULL,
  `incident_type` enum('concern','allegation','disclosure','near_miss','other') NOT NULL,
  `category` varchar(100) DEFAULT 'general',
  `severity` enum('low','medium','high','critical') NOT NULL,
  `incident_date` date DEFAULT NULL,
  `description` text NOT NULL,
  `action_taken` text DEFAULT NULL,
  `dlp_user_id` int(10) unsigned DEFAULT NULL,
  `dlp_notified_at` datetime DEFAULT NULL,
  `assigned_to` int(10) unsigned DEFAULT NULL,
  `authority_notified` tinyint(1) NOT NULL DEFAULT 0,
  `authority_reference` varchar(100) DEFAULT NULL,
  `status` enum('open','investigating','resolved','escalated','closed') NOT NULL DEFAULT 'open',
  `resolved_at` datetime DEFAULT NULL,
  `resolution_notes` text DEFAULT NULL,
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  `updated_at` datetime NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `idx_safeguarding_incidents_tenant` (`tenant_id`),
  KEY `idx_safeguarding_incidents_shift` (`tenant_id`,`shift_id`),
  KEY `idx_safeguarding_incidents_status` (`tenant_id`,`status`),
  KEY `idx_safeguarding_incidents_severity` (`tenant_id`,`severity`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `vol_safeguarding_training`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `vol_safeguarding_training` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `tenant_id` int(10) unsigned NOT NULL,
  `user_id` int(10) unsigned NOT NULL,
  `training_type` enum('children_first','vulnerable_adults','first_aid','manual_handling','other') NOT NULL,
  `training_name` varchar(255) DEFAULT NULL,
  `provider` varchar(255) DEFAULT NULL,
  `certificate_reference` varchar(255) DEFAULT NULL,
  `completed_at` date NOT NULL,
  `expires_at` date DEFAULT NULL,
  `document_path` varchar(500) DEFAULT NULL,
  `certificate_url` varchar(500) DEFAULT NULL,
  `notes` text DEFAULT NULL,
  `verified_by` int(10) unsigned DEFAULT NULL,
  `verified_at` datetime DEFAULT NULL,
  `status` enum('pending','verified','expired','rejected') NOT NULL DEFAULT 'pending',
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  `updated_at` datetime DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `idx_safeguarding_training_tenant_user` (`tenant_id`,`user_id`),
  KEY `idx_safeguarding_training_expiry` (`tenant_id`,`expires_at`),
  KEY `idx_safeguarding_training_status` (`tenant_id`,`status`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `vol_shift_checkins`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `vol_shift_checkins` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `tenant_id` int(11) NOT NULL,
  `shift_id` int(11) NOT NULL,
  `user_id` int(11) NOT NULL,
  `qr_token` varchar(64) NOT NULL COMMENT 'Unique QR code token',
  `checked_in_at` timestamp NULL DEFAULT NULL,
  `checked_out_at` timestamp NULL DEFAULT NULL,
  `status` enum('pending','checked_in','checked_out','no_show') NOT NULL DEFAULT 'pending',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` datetime NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `unique_shift_user` (`shift_id`,`user_id`),
  UNIQUE KEY `unique_qr_token` (`qr_token`),
  KEY `idx_tenant_shift` (`tenant_id`,`shift_id`),
  KEY `idx_user_status` (`user_id`,`status`),
  CONSTRAINT `vol_shift_checkins_shift_id_foreign` FOREIGN KEY (`shift_id`) REFERENCES `vol_shifts` (`id`) ON DELETE CASCADE ON UPDATE CASCADE,
  CONSTRAINT `vol_shift_checkins_user_id_foreign` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='QR-based check-in tracking for volunteer shifts';
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `vol_shift_group_members`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `vol_shift_group_members` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `reservation_id` int(11) NOT NULL,
  `tenant_id` int(10) unsigned NOT NULL DEFAULT 1,
  `user_id` int(11) NOT NULL,
  `status` enum('confirmed','cancelled') NOT NULL DEFAULT 'confirmed',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `unique_reservation_user` (`reservation_id`,`user_id`),
  KEY `idx_user` (`user_id`),
  KEY `idx_gm_tenant` (`tenant_id`),
  CONSTRAINT `vol_shift_group_members_reservation_id_foreign` FOREIGN KEY (`reservation_id`) REFERENCES `vol_shift_group_reservations` (`id`) ON DELETE CASCADE ON UPDATE CASCADE,
  CONSTRAINT `vol_shift_group_members_user_id_foreign` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Individual members within a group shift reservation';
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `vol_shift_group_reservations`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `vol_shift_group_reservations` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `tenant_id` int(11) NOT NULL,
  `shift_id` int(11) NOT NULL,
  `group_id` int(11) NOT NULL COMMENT 'References groups table',
  `reserved_slots` int(11) NOT NULL DEFAULT 1,
  `filled_slots` int(11) NOT NULL DEFAULT 0,
  `reserved_by` int(11) NOT NULL COMMENT 'Group leader who made reservation',
  `status` enum('active','cancelled','completed') NOT NULL DEFAULT 'active',
  `notes` text DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `unique_shift_group` (`shift_id`,`group_id`),
  KEY `idx_tenant_group` (`tenant_id`,`group_id`),
  KEY `idx_shift_status` (`shift_id`,`status`),
  CONSTRAINT `vol_shift_group_reservations_shift_id_foreign` FOREIGN KEY (`shift_id`) REFERENCES `vol_shifts` (`id`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Group/team reservations for volunteer shift slots';
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `vol_shift_signups`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `vol_shift_signups` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `tenant_id` int(10) unsigned NOT NULL,
  `shift_id` int(10) unsigned NOT NULL,
  `user_id` int(10) unsigned NOT NULL,
  `status` enum('confirmed','cancelled','attended','no_show') NOT NULL DEFAULT 'confirmed',
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  `updated_at` datetime NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_vol_signup_user_shift` (`tenant_id`,`shift_id`,`user_id`),
  KEY `idx_vol_signup_tenant` (`tenant_id`),
  KEY `idx_vol_signup_shift` (`shift_id`),
  KEY `idx_vol_signup_user` (`user_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `vol_shift_swap_requests`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `vol_shift_swap_requests` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `tenant_id` int(11) NOT NULL,
  `from_user_id` int(11) NOT NULL COMMENT 'User requesting the swap',
  `to_user_id` int(11) NOT NULL COMMENT 'Target user to swap with',
  `from_shift_id` int(11) NOT NULL COMMENT 'Shift the requester is giving up',
  `to_shift_id` int(11) NOT NULL COMMENT 'Shift the requester wants',
  `status` enum('pending','accepted','rejected','admin_pending','admin_approved','admin_rejected','cancelled','expired') NOT NULL DEFAULT 'pending',
  `requires_admin_approval` tinyint(1) NOT NULL DEFAULT 0,
  `admin_id` int(11) DEFAULT NULL COMMENT 'Admin who approved/rejected',
  `message` text DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `idx_tenant` (`tenant_id`),
  KEY `idx_from_user` (`from_user_id`,`status`),
  KEY `idx_to_user` (`to_user_id`,`status`),
  KEY `idx_status` (`status`),
  KEY `vol_shift_swap_requests_from_shift_id_foreign` (`from_shift_id`),
  KEY `vol_shift_swap_requests_to_shift_id_foreign` (`to_shift_id`),
  CONSTRAINT `vol_shift_swap_requests_from_shift_id_foreign` FOREIGN KEY (`from_shift_id`) REFERENCES `vol_shifts` (`id`) ON DELETE CASCADE ON UPDATE CASCADE,
  CONSTRAINT `vol_shift_swap_requests_from_user_id_foreign` FOREIGN KEY (`from_user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE ON UPDATE CASCADE,
  CONSTRAINT `vol_shift_swap_requests_to_shift_id_foreign` FOREIGN KEY (`to_shift_id`) REFERENCES `vol_shifts` (`id`) ON DELETE CASCADE ON UPDATE CASCADE,
  CONSTRAINT `vol_shift_swap_requests_to_user_id_foreign` FOREIGN KEY (`to_user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Shift swap requests between volunteers';
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `vol_shift_waitlist`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `vol_shift_waitlist` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `tenant_id` int(11) NOT NULL,
  `shift_id` int(11) NOT NULL,
  `user_id` int(11) NOT NULL,
  `position` int(11) NOT NULL DEFAULT 0 COMMENT 'Queue position (lower = higher priority)',
  `status` enum('waiting','notified','promoted','expired','cancelled') NOT NULL DEFAULT 'waiting',
  `notified_at` timestamp NULL DEFAULT NULL,
  `promoted_at` timestamp NULL DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` datetime NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `unique_shift_user_waitlist` (`shift_id`,`user_id`),
  KEY `idx_tenant_shift` (`tenant_id`,`shift_id`),
  KEY `idx_status_position` (`shift_id`,`status`,`position`),
  KEY `vol_shift_waitlist_user_id_foreign` (`user_id`),
  CONSTRAINT `vol_shift_waitlist_shift_id_foreign` FOREIGN KEY (`shift_id`) REFERENCES `vol_shifts` (`id`) ON DELETE CASCADE ON UPDATE CASCADE,
  CONSTRAINT `vol_shift_waitlist_user_id_foreign` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Waitlist for full volunteer shifts';
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `vol_shifts`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `vol_shifts` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `tenant_id` int(10) unsigned NOT NULL DEFAULT 1,
  `opportunity_id` int(11) NOT NULL,
  `recurring_pattern_id` int(11) DEFAULT NULL,
  `start_time` datetime NOT NULL,
  `end_time` datetime NOT NULL,
  `capacity` int(11) DEFAULT 1,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `required_skills` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL COMMENT 'JSON array of required skill keywords' CHECK (json_valid(`required_skills`)),
  PRIMARY KEY (`id`),
  KEY `opportunity_id` (`opportunity_id`),
  KEY `start_time` (`start_time`),
  KEY `idx_tenant_id` (`tenant_id`),
  KEY `idx_vs_recurring` (`recurring_pattern_id`),
  KEY `idx_vol_shift_recurring_pattern` (`tenant_id`,`recurring_pattern_id`),
  CONSTRAINT `vol_shifts_opportunity_id_foreign` FOREIGN KEY (`opportunity_id`) REFERENCES `vol_opportunities` (`id`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `vol_wellbeing_alerts`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `vol_wellbeing_alerts` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `tenant_id` int(11) NOT NULL,
  `user_id` int(11) NOT NULL,
  `risk_level` enum('low','moderate','high','critical') NOT NULL DEFAULT 'moderate',
  `risk_score` decimal(5,2) NOT NULL DEFAULT 0.00 COMMENT '0-100 risk score',
  `indicators` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin NOT NULL COMMENT 'JSON object with detected risk indicators' CHECK (json_valid(`indicators`)),
  `coordinator_notified` tinyint(1) NOT NULL DEFAULT 0,
  `coordinator_notes` text DEFAULT NULL,
  `status` enum('active','acknowledged','resolved','dismissed') NOT NULL DEFAULT 'active',
  `resolved_at` timestamp NULL DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `idx_tenant_user` (`tenant_id`,`user_id`),
  KEY `idx_tenant_status` (`tenant_id`,`status`),
  KEY `idx_risk_level` (`risk_level`,`status`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Burnout risk detection alerts for volunteers';
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `volunteering_organizations`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `volunteering_organizations` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `tenant_id` int(11) NOT NULL,
  `name` varchar(255) NOT NULL,
  `description` text DEFAULT NULL,
  `contact_email` varchar(255) DEFAULT NULL,
  `contact_phone` varchar(50) DEFAULT NULL,
  `website` varchar(255) DEFAULT NULL,
  `address` text DEFAULT NULL,
  `status` enum('pending','approved','rejected') NOT NULL DEFAULT 'pending',
  `submitted_by` int(11) DEFAULT NULL COMMENT 'User ID who submitted the organization',
  `reviewed_by` int(11) DEFAULT NULL COMMENT 'Admin user ID who reviewed',
  `reviewed_at` timestamp NULL DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  `content` text DEFAULT NULL,
  `errors` varchar(255) DEFAULT NULL,
  `pages` varchar(255) DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `idx_tenant_status` (`tenant_id`,`status`),
  KEY `idx_submitted_by` (`submitted_by`),
  KEY `idx_reviewed_by` (`reviewed_by`),
  CONSTRAINT `fk_vol_org_tenant` FOREIGN KEY (`tenant_id`) REFERENCES `tenants` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Volunteering organizations with approval workflow';
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `webauthn_credentials`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `webauthn_credentials` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `user_id` int(11) NOT NULL,
  `tenant_id` int(11) NOT NULL,
  `credential_id` varchar(255) NOT NULL COMMENT 'Base64url encoded credential ID',
  `public_key` text NOT NULL COMMENT 'PEM-format public key from lbuchs/WebAuthn library',
  `sign_count` int(11) DEFAULT 0 COMMENT 'Signature counter for replay protection',
  `transports` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL COMMENT 'Array of transport hints (usb, nfc, ble, internal)' CHECK (json_valid(`transports`)),
  `device_name` varchar(100) DEFAULT NULL,
  `authenticator_type` varchar(30) DEFAULT NULL,
  `attestation_type` varchar(50) DEFAULT NULL COMMENT 'Attestation type (none, indirect, direct)',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `last_used_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `unique_credential` (`credential_id`(191)),
  KEY `idx_user` (`user_id`),
  KEY `idx_tenant` (`tenant_id`),
  KEY `idx_credential` (`credential_id`(191)),
  CONSTRAINT `webauthn_credentials_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE,
  CONSTRAINT `webauthn_credentials_ibfk_2` FOREIGN KEY (`tenant_id`) REFERENCES `tenants` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=15 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `weekly_rank_snapshots`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `weekly_rank_snapshots` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `tenant_id` int(11) NOT NULL,
  `user_id` int(11) NOT NULL,
  `rank_position` int(11) NOT NULL,
  `xp` int(11) DEFAULT 0,
  `snapshot_date` date NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `unique_snapshot` (`tenant_id`,`user_id`,`snapshot_date`),
  KEY `idx_tenant_date` (`tenant_id`,`snapshot_date`),
  KEY `idx_user` (`user_id`)
) ENGINE=InnoDB AUTO_INCREMENT=11147 DEFAULT CHARSET=utf8mb3 COLLATE=utf8mb3_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `xp_history`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `xp_history` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `tenant_id` int(11) NOT NULL,
  `user_id` int(11) NOT NULL,
  `xp_amount` int(11) NOT NULL,
  `reason` varchar(100) NOT NULL,
  `description` text DEFAULT NULL,
  `reference_type` varchar(50) DEFAULT NULL,
  `reference_id` int(11) DEFAULT NULL,
  `created_at` datetime DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `idx_user` (`user_id`),
  KEY `idx_tenant` (`tenant_id`),
  KEY `idx_reason` (`reason`),
  KEY `idx_created` (`created_at`),
  KEY `idx_reference` (`reference_type`,`reference_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `xp_notifications`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `xp_notifications` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `tenant_id` int(11) NOT NULL,
  `user_id` int(11) NOT NULL,
  `xp_amount` int(11) NOT NULL,
  `action` varchar(50) DEFAULT NULL,
  `description` varchar(255) DEFAULT NULL,
  `is_read` tinyint(1) DEFAULT 0,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `idx_user_unread` (`user_id`,`is_read`),
  KEY `idx_tenant` (`tenant_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb3 COLLATE=utf8mb3_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `xp_shop_items`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `xp_shop_items` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `tenant_id` int(11) NOT NULL,
  `item_key` varchar(50) NOT NULL,
  `name` varchar(100) NOT NULL,
  `description` text DEFAULT NULL,
  `icon` varchar(50) DEFAULT NULL,
  `item_type` enum('badge','perk','feature','cosmetic') DEFAULT 'perk',
  `xp_cost` int(11) NOT NULL,
  `stock_limit` int(11) DEFAULT NULL,
  `per_user_limit` int(11) DEFAULT 1,
  `is_active` tinyint(1) DEFAULT 1,
  `display_order` int(11) DEFAULT 0,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `unique_shop_item` (`tenant_id`,`item_key`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb3 COLLATE=utf8mb3_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!50001 DROP VIEW IF EXISTS `user_effective_permissions`*/;
/*!50001 SET @saved_cs_client          = @@character_set_client */;
/*!50001 SET @saved_cs_results         = @@character_set_results */;
/*!50001 SET @saved_col_connection     = @@collation_connection */;
/*!50001 SET character_set_client      = utf8mb3 */;
/*!50001 SET character_set_results     = utf8mb3 */;
/*!50001 SET collation_connection      = utf8mb3_general_ci */;
/*!50001 CREATE ALGORITHM=UNDEFINED */
/*!50013 DEFINER=`nexus`@`%` SQL SECURITY DEFINER */
/*!50001 VIEW `user_effective_permissions` AS select distinct `u`.`id` AS `user_id`,`p`.`id` AS `permission_id`,`p`.`name` AS `permission_name`,`p`.`category` AS `category`,case when `up`.`granted` = 0 then 0 when `up`.`granted` = 1 then 1 when `rp`.`permission_id` is not null then 1 else 0 end AS `has_permission`,case when `up`.`id` is not null then 'direct' when `rp`.`id` is not null then 'role' else 'none' end AS `grant_source` from ((((`users` `u` left join `user_roles` `ur` on(`u`.`id` = `ur`.`user_id` and (`ur`.`expires_at` is null or `ur`.`expires_at` > current_timestamp()))) left join `role_permissions` `rp` on(`ur`.`role_id` = `rp`.`role_id`)) left join `permissions` `p` on(`rp`.`permission_id` = `p`.`id` or `p`.`id` in (select `user_permissions`.`permission_id` from `user_permissions` where `user_permissions`.`user_id` = `u`.`id`))) left join `user_permissions` `up` on(`u`.`id` = `up`.`user_id` and `p`.`id` = `up`.`permission_id` and (`up`.`expires_at` is null or `up`.`expires_at` > current_timestamp()))) where `p`.`id` is not null */;
/*!50001 SET character_set_client      = @saved_cs_client */;
/*!50001 SET character_set_results     = @saved_cs_results */;
/*!50001 SET collation_connection      = @saved_col_connection */;
/*!50001 DROP VIEW IF EXISTS `user_gamification_summary`*/;
/*!50001 SET @saved_cs_client          = @@character_set_client */;
/*!50001 SET @saved_cs_results         = @@character_set_results */;
/*!50001 SET @saved_col_connection     = @@collation_connection */;
/*!50001 SET character_set_client      = utf8mb3 */;
/*!50001 SET character_set_results     = utf8mb3 */;
/*!50001 SET collation_connection      = utf8mb3_general_ci */;
/*!50001 CREATE ALGORITHM=UNDEFINED */
/*!50013 DEFINER=`nexus`@`%` SQL SECURITY DEFINER */
/*!50001 VIEW `user_gamification_summary` AS select `u`.`id` AS `user_id`,`u`.`tenant_id` AS `tenant_id`,`u`.`xp` AS `xp`,`u`.`level` AS `level`,`u`.`login_streak` AS `login_streak`,count(distinct `ub`.`badge_key`) AS `badge_count`,(select count(0) from `user_challenge_progress` `ucp` where `ucp`.`user_id` = `u`.`id` and `ucp`.`completed_at` is not null) AS `challenges_completed`,(select count(0) from `friend_challenges` `fc` where (`fc`.`challenger_id` = `u`.`id` or `fc`.`challenged_id` = `u`.`id`) and `fc`.`winner_id` = `u`.`id`) AS `friend_challenges_won` from (`users` `u` left join `user_badges` `ub` on(`u`.`id` = `ub`.`user_id`)) where `u`.`is_approved` = 1 group by `u`.`id`,`u`.`tenant_id`,`u`.`xp`,`u`.`level`,`u`.`login_streak` */;
/*!50001 SET character_set_client      = @saved_cs_client */;
/*!50001 SET character_set_results     = @saved_cs_results */;
/*!50001 SET collation_connection      = @saved_col_connection */;
/*!50001 DROP VIEW IF EXISTS `v_active_listings_with_coords`*/;
/*!50001 SET @saved_cs_client          = @@character_set_client */;
/*!50001 SET @saved_cs_results         = @@character_set_results */;
/*!50001 SET @saved_col_connection     = @@collation_connection */;
/*!50001 SET character_set_client      = utf8mb3 */;
/*!50001 SET character_set_results     = utf8mb3 */;
/*!50001 SET collation_connection      = utf8mb3_general_ci */;
/*!50001 CREATE ALGORITHM=UNDEFINED */
/*!50013 DEFINER=`nexus`@`%` SQL SECURITY DEFINER */
/*!50001 VIEW `v_active_listings_with_coords` AS select `l`.`id` AS `id`,`l`.`user_id` AS `user_id`,`l`.`tenant_id` AS `tenant_id`,`l`.`title` AS `title`,`l`.`description` AS `description`,`l`.`type` AS `type`,`l`.`category_id` AS `category_id`,`l`.`image_url` AS `image_url`,`l`.`status` AS `status`,`l`.`created_at` AS `created_at`,coalesce(`l`.`latitude`,`u`.`latitude`) AS `latitude`,coalesce(`l`.`longitude`,`u`.`longitude`) AS `longitude`,`u`.`first_name` AS `first_name`,`u`.`last_name` AS `last_name`,`u`.`avatar_url` AS `avatar_url`,`u`.`location` AS `author_location`,`c`.`name` AS `category_name`,`c`.`color` AS `category_color` from ((`listings` `l` join `users` `u` on(`l`.`user_id` = `u`.`id`)) left join `categories` `c` on(`l`.`category_id` = `c`.`id`)) where `l`.`status` = 'active' */;
/*!50001 SET character_set_client      = @saved_cs_client */;
/*!50001 SET character_set_results     = @saved_cs_results */;
/*!50001 SET collation_connection      = @saved_col_connection */;
/*!50001 DROP VIEW IF EXISTS `v_legal_acceptance_stats`*/;
/*!50001 SET @saved_cs_client          = @@character_set_client */;
/*!50001 SET @saved_cs_results         = @@character_set_results */;
/*!50001 SET @saved_col_connection     = @@collation_connection */;
/*!50001 SET character_set_client      = utf8mb3 */;
/*!50001 SET character_set_results     = utf8mb3 */;
/*!50001 SET collation_connection      = utf8mb3_general_ci */;
/*!50001 CREATE ALGORITHM=UNDEFINED */
/*!50013 DEFINER=`nexus`@`%` SQL SECURITY DEFINER */
/*!50001 VIEW `v_legal_acceptance_stats` AS select `ld`.`id` AS `document_id`,`ld`.`tenant_id` AS `tenant_id`,`ld`.`document_type` AS `document_type`,`ld`.`title` AS `title`,`ldv`.`id` AS `version_id`,`ldv`.`version_number` AS `version_number`,`ldv`.`effective_date` AS `effective_date`,`ldv`.`is_current` AS `is_current`,count(distinct `ula`.`user_id`) AS `total_acceptances`,min(`ula`.`accepted_at`) AS `first_acceptance`,max(`ula`.`accepted_at`) AS `last_acceptance` from ((`legal_documents` `ld` join `legal_document_versions` `ldv` on(`ldv`.`document_id` = `ld`.`id`)) left join `user_legal_acceptances` `ula` on(`ula`.`version_id` = `ldv`.`id`)) group by `ld`.`id`,`ld`.`tenant_id`,`ld`.`document_type`,`ld`.`title`,`ldv`.`id`,`ldv`.`version_number`,`ldv`.`effective_date`,`ldv`.`is_current` */;
/*!50001 SET character_set_client      = @saved_cs_client */;
/*!50001 SET character_set_results     = @saved_cs_results */;
/*!50001 SET collation_connection      = @saved_col_connection */;
/*!40103 SET TIME_ZONE=@OLD_TIME_ZONE */;

/*!40101 SET SQL_MODE=@OLD_SQL_MODE */;
/*!40014 SET FOREIGN_KEY_CHECKS=@OLD_FOREIGN_KEY_CHECKS */;
/*!40014 SET UNIQUE_CHECKS=@OLD_UNIQUE_CHECKS */;
/*!40111 SET SQL_NOTES=@OLD_SQL_NOTES */;


-- Laravel migrations data (so fresh migrate knows what is already applied)
/*M!999999\- enable the sandbox mode */ 
/*!40103 SET @OLD_TIME_ZONE=@@TIME_ZONE */;
/*!40103 SET TIME_ZONE='+00:00' */;
/*!40014 SET @OLD_FOREIGN_KEY_CHECKS=@@FOREIGN_KEY_CHECKS, FOREIGN_KEY_CHECKS=0 */;
/*!40101 SET @OLD_SQL_MODE=@@SQL_MODE, SQL_MODE='NO_AUTO_VALUE_ON_ZERO' */;
/*!40111 SET @OLD_SQL_NOTES=@@SQL_NOTES, SQL_NOTES=0 */;

/*!40000 ALTER TABLE `laravel_migrations` DISABLE KEYS */;
INSERT INTO `laravel_migrations` VALUES
(1,'2026_03_18_000000_baseline_schema',1),
(2,'2026_03_18_000001_create_personal_access_tokens_table',1),
(3,'2026_03_18_000002_create_migration_registry',1),
(4,'2026_03_18_000003_add_laravel_columns',1),
(5,'2026_03_20_000000_add_federation_rate_limit_tracking',2),
(6,'2026_03_21_141200_add_tenant_id_to_activity_log',2),
(7,'2026_03_23_000001_add_missing_job_moderation_columns',3),
(8,'2026_03_23_000002_fix_job_table_schemas',3),
(9,'2026_03_23_000003_widen_reactions_emoji_column',4),
(10,'2026_03_23_000004_add_entity_columns_to_mentions',4),
(11,'2026_03_25_000000_fix_user_presence_composite_primary_key',4),
(13,'2026_03_25_000001_add_tenant_id_to_story_child_tables',5),
(14,'2026_03_26_000000_add_tenant_indexes_to_job_applications',5),
(15,'2026_03_26_000001_remove_hardcoded_eur_defaults',6),
(16,'2026_03_26_000002_add_volunteering_foreign_keys',6),
(17,'2026_03_26_000003_rename_tusla_columns_to_generic',6),
(18,'2026_03_27_000000_add_responded_at_to_job_offers',6),
(19,'2026_03_28_000001_add_event_id_to_polls',7),
(20,'2026_03_28_000002_add_scheduling_to_feed_posts',7),
(21,'2026_03_28_000003_enhance_group_chatrooms',7),
(22,'2026_03_28_000004_add_theme_preferences_to_users',7),
(23,'2026_03_28_000005_add_transcript_to_messages',7),
(24,'2026_03_28_000006_add_performance_indexes',7),
(25,'2026_03_28_100001_add_stripe_columns',7),
(26,'2026_03_28_100002_create_stripe_webhook_events',7),
(27,'2026_03_30_000001_gamification_redesign_phase1',8),
(28,'2026_03_30_000002_seed_badge_definitions',8),
(29,'2026_03_30_000003_gamification_engagement_recognition',8),
(30,'2026_03_30_000004_add_journey_columns_to_badge_collections',8),
(31,'2026_03_30_000005_create_peer_endorsements_table',8),
(32,'2026_03_30_100000_add_name_columns_to_newsletter_queue',9),
(33,'2026_03_31_000001_strip_html_from_phone_numbers',10),
(34,'2026_03_31_200000_create_knowledge_base_attachments',11),
(35,'2026_04_03_000001_add_assigned_to_gdpr_requests',12),
(36,'2026_04_03_000002_create_health_check_history_table',13),
(37,'2026_04_04_000001_fix_group_policies_schema_and_group_members_tenant',14),
(38,'2026_04_02_000001_create_federation_topics_tables',15),
(39,'2026_04_03_100001_create_listing_reports_table',16),
(40,'2026_04_03_200001_create_listing_images_table',17),
(41,'2026_04_03_200002_add_availability_to_listings',17),
(42,'2026_04_03_300001_add_last_notified_at_to_saved_searches',17),
(43,'2026_04_04_110000_add_missing_columns_to_events',17),
(44,'2026_04_05_104458_add_sort_order_and_is_active_to_categories_table',18),
(45,'2026_04_05_000001_create_marketplace_listings_table',19),
(46,'2026_04_05_000002_create_marketplace_support_tables',19),
(47,'2026_04_05_000003_create_marketplace_seller_profiles_table',19),
(48,'2026_04_05_000004_create_marketplace_offers_table',19),
(49,'2026_04_05_100001_create_marketplace_orders_table',19),
(50,'2026_04_05_100002_create_marketplace_ratings_and_disputes_tables',19),
(51,'2026_04_05_130000_add_verification_token_to_users',19),
(52,'2026_04_05_131000_add_pending_to_users_status_enum',19),
(53,'2026_04_05_200001_create_marketplace_payments_table',19),
(54,'2026_04_05_200002_create_marketplace_discovery_tables',19),
(55,'2026_04_05_300001_create_marketplace_phase4_tables',19),
(56,'2026_04_05_400001_create_marketplace_delivery_offers_table',19),
(57,'2026_04_05_000001_create_translation_glossaries_table',20),
(58,'2026_04_05_500001_add_video_url_to_marketplace_listings',20),
(59,'2026_04_06_000001_fix_marketplace_delivery_offers_tenant_id_type',20),
(60,'2026_04_07_000001_add_identity_verification_payment',20),
(61,'2026_04_07_000001_add_vol_org_wallet_support',20),
(62,'2026_04_07_000001_create_bookmarks_tables',21),
(63,'2026_04_07_000002_add_view_counts_to_feed_posts',22),
(64,'2026_04_07_100001_add_reminder_sent_at_to_job_interviews',22),
(65,'2026_04_07_100002_add_template_type_to_job_templates',22),
(66,'2026_04_07_100003_add_review_type_and_dimensions_to_reviews',22),
(67,'2026_04_07_200001_add_quoted_post_to_feed_posts',22),
(68,'2026_04_07_300001_create_blocked_users_table',22),
(69,'2026_04_07_300002_create_message_reactions_table',22),
(70,'2026_04_07_300003_add_group_conversations',22),
(71,'2026_04_10_170000_drop_messages_sender_fk_for_federation',23),
(72,'2026_04_10_175000_drop_transactions_sender_receiver_fk_for_federation',23),
(73,'2026_04_11_100000_add_rejected_status_to_federation_partnerships',23),
(74,'2026_04_11_200000_add_protocol_type_to_federation_external_partners',24),
(75,'2026_04_11_200001_create_federation_cc_entries_table',24),
(76,'2026_04_12_100000_create_federated_identities_table',25),
(77,'2026_04_12_110000_add_allow_flags_to_federation_external_partners',25),
(78,'2026_04_12_120000_create_federation_shadow_tables',25),
(79,'2026_04_12_130000_add_status_to_stripe_webhook_events',26),
(80,'2026_04_12_100000_add_retry_columns_to_newsletter_queue',27),
(81,'2026_04_12_140000_add_unique_index_to_reactions',28);
/*!40000 ALTER TABLE `laravel_migrations` ENABLE KEYS */;
/*!40103 SET TIME_ZONE=@OLD_TIME_ZONE */;

/*!40101 SET SQL_MODE=@OLD_SQL_MODE */;
/*!40014 SET FOREIGN_KEY_CHECKS=@OLD_FOREIGN_KEY_CHECKS */;
/*!40111 SET SQL_NOTES=@OLD_SQL_NOTES */;


/*M!999999\- enable the sandbox mode */ 

/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
/*!40101 SET @OLD_CHARACTER_SET_RESULTS=@@CHARACTER_SET_RESULTS */;
/*!40101 SET @OLD_COLLATION_CONNECTION=@@COLLATION_CONNECTION */;
/*!40101 SET NAMES utf8mb4 */;
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
  PRIMARY KEY (`id`),
  KEY `idx_abuse_tenant` (`tenant_id`),
  KEY `idx_abuse_status` (`status`),
  KEY `idx_abuse_severity` (`severity`),
  KEY `idx_abuse_user` (`user_id`),
  KEY `idx_abuse_type` (`alert_type`),
  KEY `idx_abuse_date` (`created_at`),
  KEY `idx_abuse_alerts_tenant_status` (`tenant_id`,`status`)
) ENGINE=InnoDB AUTO_INCREMENT=222 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
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
  `last_login` varchar(255) DEFAULT NULL,
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
  KEY `idx_activity_log_user_created` (`user_id`,`created_at`)
) ENGINE=InnoDB AUTO_INCREMENT=3124 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
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
) ENGINE=InnoDB AUTO_INCREMENT=141 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
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
) ENGINE=InnoDB AUTO_INCREMENT=209 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
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
) ENGINE=InnoDB AUTO_INCREMENT=294 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
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
) ENGINE=InnoDB AUTO_INCREMENT=28 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
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
) ENGINE=InnoDB AUTO_INCREMENT=41 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
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
  KEY `idx_tenant` (`tenant_id`),
  KEY `idx_category` (`category_id`)
) ENGINE=InnoDB AUTO_INCREMENT=1198 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
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
) ENGINE=InnoDB AUTO_INCREMENT=20 DEFAULT CHARSET=utf8mb3 COLLATE=utf8mb3_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `badge_collections`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `badge_collections` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `tenant_id` int(11) NOT NULL,
  `collection_key` varchar(50) NOT NULL,
  `name` varchar(100) NOT NULL,
  `description` text DEFAULT NULL,
  `icon` varchar(50) DEFAULT NULL,
  `bonus_xp` int(11) DEFAULT 100,
  `bonus_badge_key` varchar(50) DEFAULT NULL,
  `display_order` int(11) DEFAULT 0,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `unique_collection` (`tenant_id`,`collection_key`)
) ENGINE=InnoDB AUTO_INCREMENT=66 DEFAULT CHARSET=utf8mb3 COLLATE=utf8mb3_general_ci;
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
  `created_at` datetime DEFAULT current_timestamp(),
  `updated_at` datetime DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `unique_badge_key` (`tenant_id`,`badge_key`),
  KEY `idx_tenant` (`tenant_id`),
  KEY `idx_category` (`category`),
  KEY `idx_rarity` (`rarity`),
  KEY `idx_active` (`is_active`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
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
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Blog posts for tenant websites';
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `broker_message_copies`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `broker_message_copies` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `tenant_id` int(11) NOT NULL,
  `original_message_id` int(11) NOT NULL,
  `conversation_key` varchar(100) DEFAULT '' COMMENT 'Hash of sorted sender+receiver IDs',
  `sender_id` int(11) NOT NULL,
  `receiver_id` int(11) NOT NULL,
  `message_body` text DEFAULT NULL,
  `sent_at` timestamp NOT NULL,
  `copy_reason` enum('first_contact','high_risk_listing','new_member','flagged_user','manual_monitoring','random_sample') NOT NULL,
  `related_listing_id` int(11) DEFAULT NULL,
  `related_exchange_id` int(11) DEFAULT NULL,
  `reviewed_by` int(11) DEFAULT NULL,
  `reviewed_at` timestamp NULL DEFAULT NULL,
  `flagged` tinyint(1) DEFAULT 0,
  `flag_reason` varchar(255) DEFAULT NULL,
  `flag_severity` enum('info','warning','concern','urgent') DEFAULT NULL,
  `action_taken` varchar(100) DEFAULT NULL COMMENT 'e.g., no_action, warning_sent, monitoring_added',
  `action_notes` text DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT current_timestamp(),
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
  CONSTRAINT `broker_message_copies_ibfk_1` FOREIGN KEY (`tenant_id`) REFERENCES `tenants` (`id`) ON DELETE CASCADE,
  CONSTRAINT `broker_message_copies_ibfk_2` FOREIGN KEY (`sender_id`) REFERENCES `users` (`id`) ON DELETE CASCADE,
  CONSTRAINT `broker_message_copies_ibfk_3` FOREIGN KEY (`receiver_id`) REFERENCES `users` (`id`) ON DELETE CASCADE,
  CONSTRAINT `broker_message_copies_ibfk_4` FOREIGN KEY (`reviewed_by`) REFERENCES `users` (`id`) ON DELETE SET NULL,
  CONSTRAINT `broker_message_copies_ibfk_5` FOREIGN KEY (`related_listing_id`) REFERENCES `listings` (`id`) ON DELETE SET NULL,
  CONSTRAINT `broker_message_copies_ibfk_6` FOREIGN KEY (`related_exchange_id`) REFERENCES `exchange_requests` (`id`) ON DELETE SET NULL
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
DROP TABLE IF EXISTS `categories`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `categories` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `tenant_id` int(11) NOT NULL,
  `name` varchar(50) NOT NULL,
  `slug` varchar(255) NOT NULL,
  `color` varchar(50) DEFAULT 'blue',
  `created_at` datetime DEFAULT current_timestamp(),
  `type` varchar(50) NOT NULL DEFAULT 'listing',
  `blocker_user_id` int(11) DEFAULT NULL,
  `clicked_at` timestamp NULL DEFAULT NULL,
  `distance_km` varchar(255) DEFAULT NULL,
  `match` timestamp NULL DEFAULT NULL,
  `reset_token` varchar(255) DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `unique_name_tenant` (`name`,`tenant_id`),
  KEY `tenant_id` (`tenant_id`),
  CONSTRAINT `categories_ibfk_1` FOREIGN KEY (`tenant_id`) REFERENCES `tenants` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=3983 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
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
  `claimed_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `idx_tenant_active` (`tenant_id`,`is_active`),
  KEY `idx_dates` (`start_date`,`end_date`)
) ENGINE=InnoDB AUTO_INCREMENT=73 DEFAULT CHARSET=utf8mb3 COLLATE=utf8mb3_general_ci;
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
  KEY `idx_parent` (`parent_id`)
) ENGINE=InnoDB AUTO_INCREMENT=158 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
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
  `award` varchar(255) DEFAULT NULL,
  `decl` varchar(255) DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `requester_id` (`requester_id`),
  KEY `receiver_id` (`receiver_id`),
  KEY `idx_tenant_id` (`tenant_id`),
  CONSTRAINT `connections_ibfk_1` FOREIGN KEY (`requester_id`) REFERENCES `users` (`id`) ON DELETE CASCADE,
  CONSTRAINT `connections_ibfk_2` FOREIGN KEY (`receiver_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=143 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
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
) ENGINE=InnoDB AUTO_INCREMENT=33 DEFAULT CHARSET=utf8mb3 COLLATE=utf8mb3_general_ci;
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
) ENGINE=InnoDB AUTO_INCREMENT=121 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
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
) ENGINE=InnoDB AUTO_INCREMENT=101 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Inventory of all cookies used by the platform';
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
) ENGINE=InnoDB AUTO_INCREMENT=38359 DEFAULT CHARSET=utf8mb3 COLLATE=utf8mb3_general_ci;
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
  `category` timestamp NULL DEFAULT NULL,
  `claimed_at` timestamp NULL DEFAULT NULL,
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
) ENGINE=InnoDB AUTO_INCREMENT=116 DEFAULT CHARSET=utf8mb3 COLLATE=utf8mb3_general_ci;
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
) ENGINE=InnoDB AUTO_INCREMENT=73 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
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
DROP TABLE IF EXISTS `email_verification_tokens`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `email_verification_tokens` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `user_id` int(11) NOT NULL,
  `token` varchar(255) NOT NULL COMMENT 'Hashed verification token',
  `created_at` datetime DEFAULT current_timestamp(),
  `expires_at` datetime NOT NULL COMMENT 'Token expiry time',
  PRIMARY KEY (`id`),
  KEY `idx_user_id` (`user_id`),
  KEY `idx_expires_at` (`expires_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
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
) ENGINE=InnoDB AUTO_INCREMENT=10757 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `event_rsvps`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `event_rsvps` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `tenant_id` int(10) unsigned NOT NULL DEFAULT 1,
  `event_id` int(11) NOT NULL,
  `user_id` int(11) NOT NULL,
  `status` enum('going','maybe','declined','interested','not_going') NOT NULL DEFAULT 'going',
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
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
  KEY `idx_federated_events` (`is_federated`,`source_tenant_id`)
) ENGINE=InnoDB AUTO_INCREMENT=9 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
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
  `end_time` datetime DEFAULT NULL,
  `max_attendees` int(11) DEFAULT NULL,
  `cover_image` varchar(255) DEFAULT NULL,
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
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
  PRIMARY KEY (`id`),
  KEY `idx_event_tenant` (`tenant_id`),
  KEY `idx_event_start` (`start_time`),
  KEY `fk_event_group` (`group_id`),
  KEY `fk_events_category` (`category_id`),
  KEY `fk_event_opportunity` (`volunteer_opportunity_id`),
  KEY `idx_events_user_id` (`user_id`),
  CONSTRAINT `fk_event_group` FOREIGN KEY (`group_id`) REFERENCES `groups` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_event_opportunity` FOREIGN KEY (`volunteer_opportunity_id`) REFERENCES `vol_opportunities` (`id`) ON DELETE SET NULL,
  CONSTRAINT `fk_events_category` FOREIGN KEY (`category_id`) REFERENCES `categories` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB AUTO_INCREMENT=86 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
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
) ENGINE=InnoDB AUTO_INCREMENT=557 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
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
) ENGINE=InnoDB AUTO_INCREMENT=173 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
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
  `action_type` varchar(255) NOT NULL,
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
) ENGINE=InnoDB AUTO_INCREMENT=1027 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
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
) ENGINE=InnoDB AUTO_INCREMENT=325 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
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
  `created_by` int(10) unsigned DEFAULT NULL,
  `created_at` datetime DEFAULT current_timestamp(),
  `updated_at` datetime DEFAULT NULL ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `uk_tenant_url` (`tenant_id`,`base_url`(191)),
  KEY `idx_tenant` (`tenant_id`),
  KEY `idx_status` (`status`),
  KEY `idx_base_url` (`base_url`(191)),
  KEY `idx_last_message` (`last_message_at`)
) ENGINE=InnoDB AUTO_INCREMENT=8 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
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
) ENGINE=InnoDB AUTO_INCREMENT=12 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Cross-tenant messages between federated timebank members';
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
  `status` enum('pending','active','suspended','terminated') NOT NULL DEFAULT 'pending',
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
) ENGINE=InnoDB AUTO_INCREMENT=7 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
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
) ENGINE=InnoDB AUTO_INCREMENT=156 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
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
) ENGINE=InnoDB AUTO_INCREMENT=3 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
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
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
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
  `parent_id` int(11) DEFAULT 0,
  `parent_type` varchar(50) DEFAULT 'post',
  `award` varchar(255) DEFAULT NULL,
  `event` varchar(255) DEFAULT NULL,
  `lov` varchar(255) DEFAULT NULL,
  `type` varchar(50) DEFAULT 'post',
  PRIMARY KEY (`id`),
  KEY `idx_tenant_user` (`tenant_id`,`user_id`),
  KEY `idx_created` (`created_at`),
  KEY `idx_group` (`group_id`)
) ENGINE=InnoDB AUTO_INCREMENT=294 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
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
) ENGINE=InnoDB AUTO_INCREMENT=73 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
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
  KEY `idx_requested_at` (`requested_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
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
) ENGINE=InnoDB AUTO_INCREMENT=280 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
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
  `buddy_id` int(11) DEFAULT NULL,
  `completed_at` timestamp NULL DEFAULT NULL,
  `current_value` decimal(10,2) NOT NULL DEFAULT 0.00 COMMENT 'Current progress value',
  `target_value` decimal(10,2) NOT NULL DEFAULT 0.00 COMMENT 'Target value for goal completion',
  PRIMARY KEY (`id`),
  KEY `tenant_id` (`tenant_id`),
  KEY `user_id` (`user_id`),
  KEY `mentor_id` (`mentor_id`)
) ENGINE=InnoDB AUTO_INCREMENT=118 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
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
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
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
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `resolved_at` timestamp NULL DEFAULT NULL,
  `banned_until` varchar(255) DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `idx_tenant_status` (`tenant_id`,`status`),
  KEY `idx_content` (`content_type`,`content_id`),
  KEY `idx_reporter` (`tenant_id`,`reported_by`)
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
) ENGINE=InnoDB AUTO_INCREMENT=35 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
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
) ENGINE=InnoDB AUTO_INCREMENT=15 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
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
) ENGINE=InnoDB AUTO_INCREMENT=3 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
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
DROP TABLE IF EXISTS `group_feedback`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `group_feedback` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `group_id` int(11) NOT NULL,
  `user_id` int(11) NOT NULL,
  `rating` tinyint(4) NOT NULL CHECK (`rating` >= 1 and `rating` <= 5),
  `comment` text DEFAULT NULL,
  `created_at` datetime DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `unique_user_group` (`group_id`,`user_id`),
  KEY `user_id` (`user_id`),
  CONSTRAINT `group_feedback_ibfk_1` FOREIGN KEY (`group_id`) REFERENCES `groups` (`id`) ON DELETE CASCADE,
  CONSTRAINT `group_feedback_ibfk_2` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=21 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
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
DROP TABLE IF EXISTS `group_members`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `group_members` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `group_id` int(11) NOT NULL,
  `user_id` int(11) NOT NULL,
  `joined_at` datetime DEFAULT current_timestamp(),
  `status` enum('active','pending','invited','banned') NOT NULL DEFAULT 'active',
  `role` enum('member','admin','owner') NOT NULL DEFAULT 'member',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp() COMMENT 'When the user joined this group',
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
) ENGINE=InnoDB AUTO_INCREMENT=3932 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `group_policies`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `group_policies` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `category` timestamp NULL DEFAULT NULL,
  `stor` varchar(255) DEFAULT NULL,
  `tenant_id` int(11) DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  `value_type` varchar(255) DEFAULT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
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
) ENGINE=InnoDB AUTO_INCREMENT=22 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
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
) ENGINE=InnoDB AUTO_INCREMENT=26 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
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
) ENGINE=InnoDB AUTO_INCREMENT=133 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `group_user_bans`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `group_user_bans` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `tenant_id` int(11) NOT NULL,
  `user_id` int(11) NOT NULL,
  `banned_by` int(11) NOT NULL,
  `reason` text DEFAULT NULL,
  `banned_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `banned_until` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `unique_tenant_user_ban` (`tenant_id`,`user_id`),
  KEY `idx_tenant_user` (`tenant_id`,`user_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `group_user_warnings`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `group_user_warnings` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `tenant_id` int(11) NOT NULL,
  `user_id` int(11) NOT NULL,
  `warned_by` int(11) NOT NULL,
  `reason` text DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `idx_tenant_user` (`tenant_id`,`user_id`,`created_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
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
  `description` text DEFAULT NULL,
  `visibility` enum('public','private') NOT NULL DEFAULT 'public',
  `is_featured` tinyint(1) DEFAULT 0,
  `image_url` varchar(255) DEFAULT NULL,
  `cover_image_url` varchar(255) DEFAULT NULL,
  `created_at` datetime DEFAULT current_timestamp(),
  `location` varchar(255) DEFAULT NULL,
  `latitude` decimal(10,8) DEFAULT NULL,
  `longitude` decimal(11,8) DEFAULT NULL,
  `is_active` tinyint(1) NOT NULL DEFAULT 1 COMMENT 'Whether this group is active (1) or inactive (0)',
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
  PRIMARY KEY (`id`),
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
  CONSTRAINT `groups_ibfk_1` FOREIGN KEY (`tenant_id`) REFERENCES `tenants` (`id`) ON DELETE CASCADE,
  CONSTRAINT `groups_ibfk_2` FOREIGN KEY (`owner_id`) REFERENCES `users` (`id`) ON DELETE CASCADE,
  CONSTRAINT `groups_ibfk_3` FOREIGN KEY (`type_id`) REFERENCES `group_types` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB AUTO_INCREMENT=980 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
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
) ENGINE=InnoDB AUTO_INCREMENT=9 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
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
) ENGINE=InnoDB AUTO_INCREMENT=120 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
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
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `idx_tenant_active` (`tenant_id`,`is_active`),
  KEY `idx_dates` (`start_date`,`end_date`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb3 COLLATE=utf8mb3_general_ci;
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
  KEY `user_id` (`user_id`),
  KEY `target` (`target_type`,`target_id`),
  KEY `tenant_id` (`tenant_id`)
) ENGINE=InnoDB AUTO_INCREMENT=189 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
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
) ENGINE=InnoDB AUTO_INCREMENT=83 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
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
  `status` varchar(50) DEFAULT 'active',
  `created_at` datetime DEFAULT current_timestamp(),
  `image_url` varchar(255) DEFAULT NULL,
  `sdg_goals` longtext DEFAULT NULL CHECK (json_valid(`sdg_goals`)),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  `price` decimal(10,2) DEFAULT 0.00,
  `blocker_user_id` int(11) DEFAULT NULL,
  `category` timestamp NULL DEFAULT NULL,
  `click_rate` timestamp NULL DEFAULT NULL,
  `clicked_at` timestamp NULL DEFAULT NULL,
  `content` text DEFAULT NULL,
  `deleted_at` timestamp NULL DEFAULT NULL,
  `distance` varchar(255) DEFAULT NULL,
  `cached_distance_km` decimal(10,2) DEFAULT NULL,
  `errors` varchar(255) DEFAULT NULL,
  `match` timestamp NULL DEFAULT NULL,
  `open_rate` timestamp NULL DEFAULT NULL,
  `pages` varchar(255) DEFAULT NULL,
  `reset_token` varchar(255) DEFAULT NULL,
  `subcategory_id` int(11) DEFAULT NULL,
  `federated_visibility` enum('none','listed','bookable') NOT NULL DEFAULT 'none',
  `service_type` enum('physical_only','remote_only','hybrid','location_dependent') NOT NULL DEFAULT 'physical_only',
  `direct_messaging_disabled` tinyint(1) DEFAULT 0 COMMENT 'Disable direct contact for this listing, require exchange request',
  `exchange_workflow_required` tinyint(1) DEFAULT 0 COMMENT 'This listing requires formal exchange workflow',
  PRIMARY KEY (`id`),
  KEY `tenant_id` (`tenant_id`),
  KEY `user_id` (`user_id`),
  KEY `idx_listings_search` (`title`,`description`(100)),
  KEY `idx_listings_type` (`type`),
  KEY `idx_listings_category` (`category_id`),
  KEY `idx_listings_user_status` (`user_id`,`status`),
  KEY `idx_listing_coords` (`latitude`,`longitude`),
  KEY `idx_listings_messaging_disabled` (`tenant_id`,`direct_messaging_disabled`),
  CONSTRAINT `listings_ibfk_1` FOREIGN KEY (`tenant_id`) REFERENCES `tenants` (`id`) ON DELETE CASCADE,
  CONSTRAINT `listings_ibfk_2` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE,
  CONSTRAINT `listings_ibfk_3` FOREIGN KEY (`category_id`) REFERENCES `categories` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB AUTO_INCREMENT=742 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
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
) ENGINE=InnoDB AUTO_INCREMENT=2677 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
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
) ENGINE=InnoDB AUTO_INCREMENT=217 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `match_cache`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `match_cache` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `user_id` int(11) NOT NULL,
  `listing_id` int(11) NOT NULL,
  `tenant_id` int(11) NOT NULL,
  `match_score` decimal(5,2) DEFAULT 0 COMMENT 'Score 0-100',
  `distance_km` decimal(10,2) DEFAULT NULL,
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
) ENGINE=InnoDB AUTO_INCREMENT=74 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `match_history`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `match_history` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `user_id` int(11) NOT NULL,
  `listing_id` int(11) NOT NULL,
  `tenant_id` int(11) NOT NULL,
  `match_score` decimal(5,2) DEFAULT NULL,
  `distance_km` decimal(8,2) DEFAULT NULL,
  `action` enum('viewed','contacted','saved','dismissed','completed') NOT NULL,
  `resulted_in_transaction` tinyint(1) DEFAULT 0,
  `transaction_id` int(11) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `conversion_time` datetime DEFAULT NULL COMMENT 'When match resulted in transaction',
  `clicked_at` timestamp NULL DEFAULT NULL,
  `match` timestamp NULL DEFAULT NULL,
  `reset_token` varchar(255) DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `idx_user` (`user_id`),
  KEY `idx_listing` (`listing_id`),
  KEY `idx_tenant_action` (`tenant_id`,`action`),
  KEY `idx_outcomes` (`tenant_id`,`resulted_in_transaction`),
  CONSTRAINT `match_history_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE,
  CONSTRAINT `match_history_ibfk_2` FOREIGN KEY (`listing_id`) REFERENCES `listings` (`id`) ON DELETE CASCADE,
  CONSTRAINT `match_history_ibfk_3` FOREIGN KEY (`tenant_id`) REFERENCES `tenants` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=433 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
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
  `notification_frequency` enum('instant','daily','weekly','off') DEFAULT 'daily',
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
) ENGINE=InnoDB AUTO_INCREMENT=606 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `mentions`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `mentions` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `comment_id` int(11) NOT NULL COMMENT 'The comment containing the mention',
  `mentioned_user_id` int(11) NOT NULL COMMENT 'The user being mentioned',
  `mentioning_user_id` int(11) NOT NULL COMMENT 'The user who made the mention',
  `tenant_id` int(11) NOT NULL,
  `seen_at` timestamp NULL DEFAULT NULL COMMENT 'When the mentioned user saw the notification',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `unique_mention` (`comment_id`,`mentioned_user_id`),
  KEY `mentioning_user_id` (`mentioning_user_id`),
  KEY `idx_mentioned_user` (`mentioned_user_id`),
  KEY `idx_comment` (`comment_id`),
  KEY `idx_tenant` (`tenant_id`),
  KEY `idx_unseen` (`mentioned_user_id`,`seen_at`),
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
) ENGINE=InnoDB AUTO_INCREMENT=49 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
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
) ENGINE=InnoDB AUTO_INCREMENT=59 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
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
) ENGINE=InnoDB AUTO_INCREMENT=2 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `message_reactions`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `message_reactions` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `message_id` int(11) NOT NULL,
  `user_id` int(11) NOT NULL,
  `emoji` varchar(10) NOT NULL,
  `created_at` datetime DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `unique_reaction` (`message_id`,`user_id`,`emoji`),
  KEY `idx_message_id` (`message_id`),
  KEY `idx_user_id` (`user_id`),
  CONSTRAINT `message_reactions_ibfk_1` FOREIGN KEY (`message_id`) REFERENCES `messages` (`id`) ON DELETE CASCADE,
  CONSTRAINT `message_reactions_ibfk_2` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=32 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `messages`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `messages` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `tenant_id` int(11) NOT NULL,
  `sender_id` int(11) NOT NULL,
  `receiver_id` int(11) NOT NULL,
  `listing_id` int(11) DEFAULT NULL,
  `subject` varchar(255) DEFAULT NULL,
  `body` text DEFAULT NULL,
  `audio_url` varchar(500) DEFAULT NULL,
  `audio_duration` int(10) unsigned DEFAULT NULL COMMENT 'Duration in seconds',
  `is_read` tinyint(1) DEFAULT 0,
  `is_federated` tinyint(1) DEFAULT 0,
  `created_at` datetime DEFAULT current_timestamp(),
  `award` varchar(255) DEFAULT NULL,
  `archived_by_sender` datetime DEFAULT NULL COMMENT 'When sender archived this conversation',
  `archived_by_receiver` datetime DEFAULT NULL COMMENT 'When receiver archived this conversation',
  `reactions` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL COMMENT 'JSON object of emoji reactions' CHECK (json_valid(`reactions`)),
  `is_edited` tinyint(1) DEFAULT 0 COMMENT 'Whether message was edited',
  `edited_at` timestamp NULL DEFAULT NULL COMMENT 'When message was edited',
  `is_deleted` tinyint(1) DEFAULT 0 COMMENT 'Whether message was deleted',
  `deleted_at` timestamp NULL DEFAULT NULL COMMENT 'When message was deleted',
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
  CONSTRAINT `fk_messages_listing` FOREIGN KEY (`listing_id`) REFERENCES `listings` (`id`) ON DELETE SET NULL,
  CONSTRAINT `messages_ibfk_1` FOREIGN KEY (`tenant_id`) REFERENCES `tenants` (`id`) ON DELETE CASCADE,
  CONSTRAINT `messages_ibfk_2` FOREIGN KEY (`sender_id`) REFERENCES `users` (`id`) ON DELETE CASCADE,
  CONSTRAINT `messages_ibfk_3` FOREIGN KEY (`receiver_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=684 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `migrations`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `migrations` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `backups` varchar(255) DEFAULT NULL,
  `executed_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
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
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
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
) ENGINE=InnoDB AUTO_INCREMENT=26 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
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
) ENGINE=InnoDB AUTO_INCREMENT=216 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `newsletter_queue`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `newsletter_queue` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `newsletter_id` int(11) NOT NULL,
  `user_id` int(11) DEFAULT NULL,
  `email` varchar(255) NOT NULL,
  `status` enum('pending','sent','failed') DEFAULT 'pending',
  `error_message` text DEFAULT NULL,
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
  CONSTRAINT `newsletter_queue_ibfk_1` FOREIGN KEY (`newsletter_id`) REFERENCES `newsletters` (`id`) ON DELETE CASCADE,
  CONSTRAINT `newsletter_queue_ibfk_2` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB AUTO_INCREMENT=673 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
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
) ENGINE=InnoDB AUTO_INCREMENT=22 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
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
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
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
) ENGINE=InnoDB AUTO_INCREMENT=20 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
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
) ENGINE=InnoDB AUTO_INCREMENT=69 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
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
) ENGINE=InnoDB AUTO_INCREMENT=24 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Cached Nexus Score calculations for performance';
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
) ENGINE=InnoDB AUTO_INCREMENT=7551 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
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
) ENGINE=InnoDB AUTO_INCREMENT=120 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
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
  CONSTRAINT `notifications_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=14024 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
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
) ENGINE=InnoDB AUTO_INCREMENT=212 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
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
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Log of balance alerts sent to organizations';
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
) ENGINE=InnoDB AUTO_INCREMENT=547 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
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
) ENGINE=InnoDB AUTO_INCREMENT=168 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
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
) ENGINE=InnoDB AUTO_INCREMENT=244 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `org_wallet_limits`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `org_wallet_limits` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `tenant_id` int(11) NOT NULL,
  `organization_id` int(11) NOT NULL,
  `single_transaction_max` decimal(10,2) DEFAULT 500.00,
  `daily_limit` decimal(10,2) DEFAULT 1000.00,
  `weekly_limit` decimal(10,2) DEFAULT 3000.00,
  `monthly_limit` decimal(10,2) DEFAULT 10000.00,
  `org_daily_limit` decimal(10,2) DEFAULT 5000.00,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NULL DEFAULT NULL ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `unique_org_limits` (`tenant_id`,`organization_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
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
) ENGINE=InnoDB AUTO_INCREMENT=392 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
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
  PRIMARY KEY (`id`),
  UNIQUE KEY `unique_slug_tenant` (`slug`,`tenant_id`),
  KEY `tenant_id` (`tenant_id`),
  KEY `idx_pages_publish_at` (`publish_at`),
  KEY `idx_pages_sort_order` (`tenant_id`,`sort_order`),
  KEY `idx_pages_menu` (`tenant_id`,`is_published`,`show_in_menu`,`menu_location`)
) ENGINE=InnoDB AUTO_INCREMENT=44 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
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
  PRIMARY KEY (`id`),
  UNIQUE KEY `slug` (`slug`),
  KEY `idx_pay_plans_tier` (`tier_level`),
  KEY `idx_pay_plans_active` (`is_active`)
) ENGINE=InnoDB AUTO_INCREMENT=5 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
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
) ENGINE=InnoDB AUTO_INCREMENT=64 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
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
DROP TABLE IF EXISTS `poll_options`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `poll_options` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `poll_id` int(11) NOT NULL,
  `label` varchar(255) NOT NULL,
  `expires_at` timestamp NULL DEFAULT NULL,
  `votes` int(11) NOT NULL DEFAULT 0 COMMENT 'Cached vote count for this option',
  PRIMARY KEY (`id`),
  KEY `idx_opt_poll` (`poll_id`)
) ENGINE=InnoDB AUTO_INCREMENT=63 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `poll_votes`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `poll_votes` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `poll_id` int(11) NOT NULL,
  `option_id` int(11) NOT NULL,
  `user_id` int(11) NOT NULL,
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  `expires_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `idx_vote_unique` (`poll_id`,`user_id`)
) ENGINE=InnoDB AUTO_INCREMENT=35 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `polls`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `polls` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `tenant_id` int(11) NOT NULL,
  `user_id` int(11) NOT NULL,
  `question` varchar(255) NOT NULL,
  `description` text DEFAULT NULL,
  `end_date` datetime DEFAULT NULL,
  `is_active` tinyint(1) DEFAULT 1,
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  `expires_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `idx_polls_tenant` (`tenant_id`)
) ENGINE=InnoDB AUTO_INCREMENT=53 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
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
DROP TABLE IF EXISTS `post_shares`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `post_shares` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `user_id` int(10) unsigned NOT NULL COMMENT 'User who shared',
  `post_id` int(10) unsigned NOT NULL COMMENT 'Post that was shared',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `idx_post` (`post_id`),
  KEY `idx_user` (`user_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
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
) ENGINE=InnoDB AUTO_INCREMENT=206 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
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
  `emoji` varchar(10) NOT NULL COMMENT 'Emoji character or shortcode',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `unique_user_reaction` (`user_id`,`target_type`,`target_id`,`emoji`),
  KEY `idx_target` (`target_type`,`target_id`),
  KEY `idx_user_target` (`user_id`,`target_type`,`target_id`),
  KEY `idx_tenant` (`tenant_id`),
  KEY `idx_emoji` (`emoji`),
  CONSTRAINT `reactions_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE,
  CONSTRAINT `reactions_ibfk_2` FOREIGN KEY (`tenant_id`) REFERENCES `tenants` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=20 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
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
) ENGINE=InnoDB AUTO_INCREMENT=130 DEFAULT CHARSET=utf8mb3 COLLATE=utf8mb3_general_ci;
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
) ENGINE=InnoDB AUTO_INCREMENT=222 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
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
  PRIMARY KEY (`id`),
  KEY `idx_res_tenant` (`tenant_id`)
) ENGINE=InnoDB AUTO_INCREMENT=76 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
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
  `status` enum('pending','approved','rejected') DEFAULT 'approved',
  `is_anonymous` tinyint(1) NOT NULL DEFAULT 0,
  `show_cross_tenant` tinyint(1) NOT NULL DEFAULT 1,
  `created_at` datetime DEFAULT current_timestamp(),
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
) ENGINE=InnoDB AUTO_INCREMENT=219 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
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
) ENGINE=InnoDB AUTO_INCREMENT=26 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
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
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `idx_tenant_user` (`tenant_id`,`user_id`),
  KEY `idx_tenant_created` (`tenant_id`,`created_at`),
  KEY `idx_query_trending` (`tenant_id`,`query`(255),`created_at`),
  KEY `fk_search_logs_user` (`user_id`),
  CONSTRAINT `fk_search_logs_tenant` FOREIGN KEY (`tenant_id`) REFERENCES `tenants` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_search_logs_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
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
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `seo_metadata`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `seo_metadata` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `tenant_id` int(11) NOT NULL,
  `entity_type` varchar(50) NOT NULL COMMENT 'global, page, post, etc',
  `entity_id` int(11) DEFAULT NULL COMMENT 'Null for global, ID for others',
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
) ENGINE=InnoDB AUTO_INCREMENT=70 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `seo_redirects`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `seo_redirects` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `tenant_id` int(11) NOT NULL,
  `source_url` varchar(500) NOT NULL,
  `destination_url` varchar(500) NOT NULL,
  `hits` int(11) DEFAULT 0,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `dest` varchar(255) DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `unique_redirect` (`tenant_id`,`source_url`(191)),
  KEY `idx_source` (`source_url`(191)),
  KEY `idx_tenant` (`tenant_id`),
  CONSTRAINT `seo_redirects_ibfk_1` FOREIGN KEY (`tenant_id`) REFERENCES `tenants` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=1515 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
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
) ENGINE=InnoDB AUTO_INCREMENT=1422 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Audit trail for Super Admin Panel hierarchy changes';
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
  PRIMARY KEY (`id`),
  KEY `idx_tenant_plan_tenant` (`tenant_id`),
  KEY `idx_tenant_plan_status` (`status`),
  KEY `idx_tenant_plan_expires` (`expires_at`),
  KEY `pay_plan_id` (`pay_plan_id`),
  CONSTRAINT `tenant_plan_assignments_ibfk_1` FOREIGN KEY (`pay_plan_id`) REFERENCES `pay_plans` (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=7 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
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
) ENGINE=InnoDB AUTO_INCREMENT=99 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Tenant-specific configuration settings and feature flags';
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `tenant_wallet_limits`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `tenant_wallet_limits` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `tenant_id` int(11) NOT NULL,
  `single_transaction_max` decimal(10,2) DEFAULT 500.00,
  `daily_limit` decimal(10,2) DEFAULT 1000.00,
  `weekly_limit` decimal(10,2) DEFAULT 3000.00,
  `monthly_limit` decimal(10,2) DEFAULT 10000.00,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NULL DEFAULT NULL ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `unique_tenant_limits` (`tenant_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
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
  KEY `idx_tenant_parent` (`parent_id`),
  KEY `idx_tenant_path` (`path`(100)),
  KEY `idx_tenant_depth` (`depth`),
  KEY `idx_tenants_is_active` (`is_active`),
  CONSTRAINT `fk_tenant_parent` FOREIGN KEY (`parent_id`) REFERENCES `tenants` (`id`) ON UPDATE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=130 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
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
) ENGINE=InnoDB AUTO_INCREMENT=391 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='2FA verification attempts for rate limiting and audit';
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
  CONSTRAINT `fk_transactions_giver` FOREIGN KEY (`giver_id`) REFERENCES `users` (`id`) ON DELETE CASCADE,
  CONSTRAINT `transactions_ibfk_1` FOREIGN KEY (`tenant_id`) REFERENCES `tenants` (`id`) ON DELETE CASCADE,
  CONSTRAINT `transactions_ibfk_2` FOREIGN KEY (`sender_id`) REFERENCES `users` (`id`) ON DELETE CASCADE,
  CONSTRAINT `transactions_ibfk_3` FOREIGN KEY (`receiver_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=805 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
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
) ENGINE=InnoDB AUTO_INCREMENT=1941 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='One-time backup codes for 2FA recovery';
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
  `25` varchar(255) DEFAULT NULL,
  `badge` varchar(255) DEFAULT NULL,
  `claimed_at` timestamp NULL DEFAULT NULL,
  `earned_at` timestamp NULL DEFAULT NULL,
  `last_login` varchar(255) DEFAULT NULL,
  `level` varchar(255) DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `user_badge_unique` (`user_id`,`badge_key`),
  UNIQUE KEY `idx_user_badge` (`user_id`,`name`),
  KEY `idx_user_badges_showcase` (`user_id`,`is_showcased`),
  KEY `idx_user_badges_user` (`user_id`,`awarded_at`),
  KEY `idx_tenant` (`tenant_id`)
) ENGINE=InnoDB AUTO_INCREMENT=1383 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `user_blocks`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `user_blocks` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `user_id` int(10) unsigned NOT NULL COMMENT 'User who blocked',
  `blocked_user_id` int(10) unsigned NOT NULL COMMENT 'User who was blocked',
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
) ENGINE=InnoDB AUTO_INCREMENT=433 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
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
) ENGINE=InnoDB AUTO_INCREMENT=24 DEFAULT CHARSET=utf8mb3 COLLATE=utf8mb3_general_ci;
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
) ENGINE=InnoDB AUTO_INCREMENT=838 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
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
) ENGINE=InnoDB AUTO_INCREMENT=367 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
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
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
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
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `unique_hidden` (`user_id`,`post_id`),
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
  `category_id` int(11) NOT NULL,
  `interest_type` enum('interest','skill_offer','skill_need') NOT NULL DEFAULT 'interest',
  `created_at` timestamp NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `uk_user_category_type` (`user_id`,`category_id`,`interest_type`),
  KEY `idx_user_id` (`user_id`),
  KEY `idx_category_id` (`category_id`)
) ENGINE=InnoDB AUTO_INCREMENT=13 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
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
) ENGINE=InnoDB AUTO_INCREMENT=3 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
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
  `set_by` int(11) DEFAULT NULL COMMENT 'Admin who set the restriction',
  `restricted_at` timestamp NULL DEFAULT NULL,
  `restriction_reason` text DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `unique_tenant_user` (`tenant_id`,`user_id`),
  KEY `idx_monitoring` (`tenant_id`,`under_monitoring`),
  KEY `idx_messaging_disabled` (`tenant_id`,`messaging_disabled`),
  KEY `user_id` (`user_id`),
  KEY `restricted_by` (`set_by`),
  CONSTRAINT `user_messaging_restrictions_ibfk_1` FOREIGN KEY (`tenant_id`) REFERENCES `tenants` (`id`) ON DELETE CASCADE,
  CONSTRAINT `user_messaging_restrictions_ibfk_2` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE,
  CONSTRAINT `user_messaging_restrictions_ibfk_3` FOREIGN KEY (`set_by`) REFERENCES `users` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `user_muted_users`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `user_muted_users` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `user_id` int(10) unsigned NOT NULL COMMENT 'User who muted',
  `muted_user_id` int(10) unsigned NOT NULL COMMENT 'User who was muted',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `unique_mute` (`user_id`,`muted_user_id`),
  KEY `idx_user` (`user_id`),
  KEY `idx_muted` (`muted_user_id`)
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
  `award` varchar(255) DEFAULT NULL,
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
) ENGINE=InnoDB AUTO_INCREMENT=152 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='TOTP 2FA settings and encrypted secrets per user';
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
) ENGINE=InnoDB AUTO_INCREMENT=193 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Trusted devices that can skip 2FA verification';
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
  `award` varchar(255) DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `idx_user` (`tenant_id`,`user_id`)
) ENGINE=InnoDB AUTO_INCREMENT=16507 DEFAULT CHARSET=utf8mb3 COLLATE=utf8mb3_general_ci;
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
) ENGINE=InnoDB AUTO_INCREMENT=86 DEFAULT CHARSET=utf8mb3 COLLATE=utf8mb3_general_ci;
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
  `username` varchar(50) DEFAULT NULL,
  `password_hash` varchar(255) DEFAULT NULL,
  `totp_enabled` tinyint(1) NOT NULL DEFAULT 0 COMMENT '1 = User has 2FA enabled',
  `totp_setup_required` tinyint(1) NOT NULL DEFAULT 1 COMMENT '1 = User must set up 2FA on next login',
  `role` varchar(50) DEFAULT 'member',
  `profile_type` varchar(50) DEFAULT 'individual',
  `organization_name` varchar(255) DEFAULT NULL,
  `is_super_admin` tinyint(1) DEFAULT 0,
  `is_approved` tinyint(1) DEFAULT 0,
  `status` enum('active','inactive','suspended','banned') DEFAULT 'active',
  `balance` int(11) DEFAULT 0,
  `bio` text DEFAULT NULL,
  `privacy_profile` enum('public','members','connections') DEFAULT 'public',
  `privacy_search` tinyint(1) DEFAULT 1,
  `privacy_contact` tinyint(1) DEFAULT 0,
  `location` varchar(255) DEFAULT NULL,
  `phone` varchar(50) DEFAULT NULL,
  `avatar_url` varchar(255) DEFAULT NULL,
  `profile_image_url` varchar(255) DEFAULT NULL,
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
  `tagline` varchar(255) DEFAULT NULL,
  `reset_token_expiry` datetime DEFAULT NULL,
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
  CONSTRAINT `users_ibfk_1` FOREIGN KEY (`tenant_id`) REFERENCES `tenants` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=4534 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
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
  PRIMARY KEY (`id`),
  KEY `idx_vetting_tenant` (`tenant_id`),
  KEY `idx_vetting_user` (`user_id`),
  KEY `idx_vetting_status` (`status`),
  KEY `idx_vetting_expiry` (`expiry_date`),
  CONSTRAINT `vetting_records_ibfk_1` FOREIGN KEY (`tenant_id`) REFERENCES `tenants` (`id`) ON DELETE CASCADE,
  CONSTRAINT `vetting_records_ibfk_2` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
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
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `idx_vol_app_opp` (`opportunity_id`),
  KEY `idx_vol_app_user` (`user_id`),
  KEY `idx_app_shift` (`shift_id`),
  KEY `idx_tenant_id` (`tenant_id`)
) ENGINE=InnoDB AUTO_INCREMENT=116 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
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
  KEY `idx_tenant_id` (`tenant_id`)
) ENGINE=InnoDB AUTO_INCREMENT=81 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `vol_opportunities`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `vol_opportunities` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `tenant_id` int(11) DEFAULT NULL,
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
  PRIMARY KEY (`id`),
  KEY `idx_vol_opp_org` (`organization_id`),
  KEY `idx_vol_opp_active` (`is_active`),
  KEY `fk_vol_cat` (`category_id`),
  CONSTRAINT `fk_vol_cat` FOREIGN KEY (`category_id`) REFERENCES `categories` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB AUTO_INCREMENT=88 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `vol_organizations`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `vol_organizations` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `tenant_id` int(11) NOT NULL,
  `user_id` int(11) NOT NULL COMMENT 'Owner/Manager',
  `name` varchar(255) NOT NULL,
  `description` text DEFAULT NULL,
  `contact_email` varchar(255) DEFAULT NULL,
  `website` varchar(255) DEFAULT NULL,
  `logo_url` varchar(255) DEFAULT NULL,
  `slug` varchar(255) DEFAULT NULL,
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  `status` varchar(20) DEFAULT 'pending',
  `auto_pay_enabled` tinyint(1) DEFAULT 0,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `idx_vol_org_tenant` (`tenant_id`),
  KEY `idx_vol_org_user` (`user_id`)
) ENGINE=InnoDB AUTO_INCREMENT=169 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
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
) ENGINE=InnoDB AUTO_INCREMENT=37 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `vol_shifts`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `vol_shifts` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `tenant_id` int(10) unsigned NOT NULL DEFAULT 1,
  `opportunity_id` int(11) NOT NULL,
  `start_time` datetime NOT NULL,
  `end_time` datetime NOT NULL,
  `capacity` int(11) DEFAULT 1,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `opportunity_id` (`opportunity_id`),
  KEY `start_time` (`start_time`),
  KEY `idx_tenant_id` (`tenant_id`)
) ENGINE=InnoDB AUTO_INCREMENT=51 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
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
  `public_key` text NOT NULL COMMENT 'CBOR encoded public key',
  `sign_count` int(11) DEFAULT 0 COMMENT 'Signature counter for replay protection',
  `transports` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL COMMENT 'Array of transport hints (usb, nfc, ble, internal)' CHECK (json_valid(`transports`)),
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
) ENGINE=InnoDB AUTO_INCREMENT=14 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
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
) ENGINE=InnoDB AUTO_INCREMENT=1069 DEFAULT CHARSET=utf8mb3 COLLATE=utf8mb3_general_ci;
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
  `claimed_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `unique_shop_item` (`tenant_id`,`item_key`)
) ENGINE=InnoDB AUTO_INCREMENT=35 DEFAULT CHARSET=utf8mb3 COLLATE=utf8mb3_general_ci;
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
/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
/*!40111 SET SQL_NOTES=@OLD_SQL_NOTES */;


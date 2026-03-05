-- CommunityRank Settings Table
-- This stores the configuration for the CommunityRank algorithm per tenant

CREATE TABLE IF NOT EXISTS `communityrank_settings` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `tenant_id` int(11) NOT NULL,
  `is_enabled` tinyint(1) NOT NULL DEFAULT 1,
  `activity_weight` decimal(3,2) NOT NULL DEFAULT 0.25,
  `contribution_weight` decimal(3,2) NOT NULL DEFAULT 0.25,
  `reputation_weight` decimal(3,2) NOT NULL DEFAULT 0.20,
  `connectivity_weight` decimal(3,2) NOT NULL DEFAULT 0.20,
  `proximity_weight` decimal(3,2) NOT NULL DEFAULT 0.10,
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `tenant_id` (`tenant_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Insert default settings for tenant 2 (hour-timebank)
INSERT INTO `communityrank_settings` (`tenant_id`, `is_enabled`, `activity_weight`, `contribution_weight`, `reputation_weight`, `connectivity_weight`, `proximity_weight`)
VALUES (2, 1, 0.25, 0.25, 0.20, 0.20, 0.10)
ON DUPLICATE KEY UPDATE
  `is_enabled` = VALUES(`is_enabled`),
  `activity_weight` = VALUES(`activity_weight`),
  `contribution_weight` = VALUES(`contribution_weight`),
  `reputation_weight` = VALUES(`reputation_weight`),
  `connectivity_weight` = VALUES(`connectivity_weight`),
  `proximity_weight` = VALUES(`proximity_weight`);

-- Insert default settings for tenant 1 (master/platform)
INSERT INTO `communityrank_settings` (`tenant_id`, `is_enabled`, `activity_weight`, `contribution_weight`, `reputation_weight`, `connectivity_weight`, `proximity_weight`)
VALUES (1, 1, 0.25, 0.25, 0.20, 0.20, 0.10)
ON DUPLICATE KEY UPDATE
  `is_enabled` = VALUES(`is_enabled`),
  `activity_weight` = VALUES(`activity_weight`),
  `contribution_weight` = VALUES(`contribution_weight`),
  `reputation_weight` = VALUES(`reputation_weight`),
  `connectivity_weight` = VALUES(`connectivity_weight`),
  `proximity_weight` = VALUES(`proximity_weight`);

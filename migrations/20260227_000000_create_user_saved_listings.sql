-- User saved/favourite listings
-- Migration: 20260227_000000_create_user_saved_listings
-- Created: 2026-02-27

CREATE TABLE IF NOT EXISTS `user_saved_listings` (
    `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
    `user_id` INT UNSIGNED NOT NULL,
    `listing_id` INT UNSIGNED NOT NULL,
    `tenant_id` INT UNSIGNED NOT NULL,
    `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    UNIQUE KEY `uq_user_listing` (`user_id`, `listing_id`, `tenant_id`),
    KEY `idx_user_tenant` (`user_id`, `tenant_id`),
    KEY `idx_listing_tenant` (`listing_id`, `tenant_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

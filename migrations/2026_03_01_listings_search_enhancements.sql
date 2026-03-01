-- Migration: Listings & Search Enhancements
-- Date: 2026-03-01
-- Purpose: Add columns and tables for:
--   L1: Listing renewal workflow (renewed_at, renewal_count)
--   L2: Listing analytics (listing_views, listing_contacts)
--   L3: Skill tag filtering (listing_skill_tags)
--   L4: Featured/boost listings (is_featured, featured_until)
--   L5: QA/moderation workflow (moderation_status, reviewed_by, reviewed_at, rejection_reason)
--   S1: Saved searches
--   S3: Search analytics

-- =============================================
-- L1: Listing Renewal Columns
-- expires_at already exists from 2026_03_01_notification_reminder_tables.sql
-- =============================================
ALTER TABLE listings ADD COLUMN IF NOT EXISTS renewed_at DATETIME NULL DEFAULT NULL;
ALTER TABLE listings ADD COLUMN IF NOT EXISTS renewal_count INT UNSIGNED NOT NULL DEFAULT 0;

-- =============================================
-- L2: Listing Analytics Tables
-- =============================================
CREATE TABLE IF NOT EXISTS listing_views (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    tenant_id INT UNSIGNED NOT NULL,
    listing_id INT UNSIGNED NOT NULL,
    user_id INT UNSIGNED NULL COMMENT 'NULL for anonymous views',
    ip_hash VARCHAR(64) NULL COMMENT 'Hashed IP for anonymous dedup',
    viewed_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_listing_views_tenant (tenant_id),
    INDEX idx_listing_views_listing (listing_id),
    INDEX idx_listing_views_date (viewed_at),
    INDEX idx_listing_views_lookup (tenant_id, listing_id, user_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS listing_contacts (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    tenant_id INT UNSIGNED NOT NULL,
    listing_id INT UNSIGNED NOT NULL,
    user_id INT UNSIGNED NOT NULL COMMENT 'User who contacted the listing owner',
    contact_type ENUM('message', 'phone', 'email', 'exchange_request') NOT NULL DEFAULT 'message',
    contacted_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_listing_contacts_tenant (tenant_id),
    INDEX idx_listing_contacts_listing (listing_id),
    INDEX idx_listing_contacts_date (contacted_at),
    INDEX idx_listing_contacts_lookup (tenant_id, listing_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Add view_count and contact_count cache columns to listings
ALTER TABLE listings ADD COLUMN IF NOT EXISTS view_count INT UNSIGNED NOT NULL DEFAULT 0;
ALTER TABLE listings ADD COLUMN IF NOT EXISTS contact_count INT UNSIGNED NOT NULL DEFAULT 0;
ALTER TABLE listings ADD COLUMN IF NOT EXISTS save_count INT UNSIGNED NOT NULL DEFAULT 0;

-- =============================================
-- L3: Skill Tags for Listings
-- =============================================
CREATE TABLE IF NOT EXISTS listing_skill_tags (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    tenant_id INT UNSIGNED NOT NULL,
    listing_id INT UNSIGNED NOT NULL,
    tag VARCHAR(100) NOT NULL,
    INDEX idx_skill_tags_tenant (tenant_id),
    INDEX idx_skill_tags_listing (listing_id),
    INDEX idx_skill_tags_tag (tag),
    INDEX idx_skill_tags_lookup (tenant_id, tag),
    UNIQUE KEY uq_listing_skill_tag (listing_id, tag)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- =============================================
-- L4: Featured/Boost Listings
-- =============================================
ALTER TABLE listings ADD COLUMN IF NOT EXISTS is_featured TINYINT(1) NOT NULL DEFAULT 0;
ALTER TABLE listings ADD COLUMN IF NOT EXISTS featured_until DATETIME NULL DEFAULT NULL;
ALTER TABLE listings ADD INDEX IF NOT EXISTS idx_listings_featured (is_featured, featured_until);

-- =============================================
-- L5: QA/Moderation Workflow
-- =============================================
ALTER TABLE listings ADD COLUMN IF NOT EXISTS moderation_status ENUM('pending_review', 'approved', 'rejected') NULL DEFAULT NULL;
ALTER TABLE listings ADD COLUMN IF NOT EXISTS reviewed_by INT UNSIGNED NULL DEFAULT NULL;
ALTER TABLE listings ADD COLUMN IF NOT EXISTS reviewed_at DATETIME NULL DEFAULT NULL;
ALTER TABLE listings ADD COLUMN IF NOT EXISTS rejection_reason TEXT NULL DEFAULT NULL;
ALTER TABLE listings ADD INDEX IF NOT EXISTS idx_listings_moderation (tenant_id, moderation_status);

-- =============================================
-- S1: Saved Searches
-- =============================================
CREATE TABLE IF NOT EXISTS saved_searches (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    tenant_id INT UNSIGNED NOT NULL,
    user_id INT UNSIGNED NOT NULL,
    name VARCHAR(255) NOT NULL,
    query_params JSON NOT NULL COMMENT 'Search query and filters as JSON',
    notify_on_new TINYINT(1) NOT NULL DEFAULT 0,
    last_run_at DATETIME NULL DEFAULT NULL,
    last_result_count INT UNSIGNED NULL DEFAULT NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX idx_saved_searches_user (tenant_id, user_id),
    INDEX idx_saved_searches_notify (tenant_id, notify_on_new)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- =============================================
-- S3: Search Analytics
-- =============================================
CREATE TABLE IF NOT EXISTS search_logs (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    tenant_id INT UNSIGNED NOT NULL,
    user_id INT UNSIGNED NULL COMMENT 'NULL for anonymous searches',
    query VARCHAR(500) NOT NULL,
    search_type VARCHAR(50) NOT NULL DEFAULT 'all' COMMENT 'all, listings, users, events, groups',
    result_count INT UNSIGNED NOT NULL DEFAULT 0,
    filters JSON NULL COMMENT 'Applied filters as JSON',
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_search_logs_tenant (tenant_id),
    INDEX idx_search_logs_date (created_at),
    INDEX idx_search_logs_query (tenant_id, query(100)),
    INDEX idx_search_logs_user (tenant_id, user_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

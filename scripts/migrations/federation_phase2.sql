-- ============================================================
-- Federation Phase 2 Migration - Admin Panels Support
-- ============================================================
-- This migration adds support for admin panels and user preferences
-- Can be run independently or after federation_tables.sql (Phase 1)
-- ============================================================

-- ============================================================
-- 1. FEDERATION DIRECTORY PROFILES
-- Extended tenant info for the federation directory
-- ============================================================

CREATE TABLE IF NOT EXISTS federation_directory_profiles (
    tenant_id INT UNSIGNED PRIMARY KEY,

    -- Public profile info (shown in directory)
    display_name VARCHAR(200) NULL
        COMMENT 'Custom display name for directory (defaults to tenant name)',
    tagline VARCHAR(300) NULL
        COMMENT 'Short description for directory listing',
    description TEXT NULL
        COMMENT 'Detailed description of the timebank',
    logo_url VARCHAR(500) NULL,
    cover_image_url VARCHAR(500) NULL,
    website_url VARCHAR(500) NULL,

    -- Location info
    country_code CHAR(2) NULL,
    region VARCHAR(100) NULL,
    city VARCHAR(100) NULL,
    latitude DECIMAL(10, 8) NULL,
    longitude DECIMAL(11, 8) NULL,

    -- Stats (cached, updated periodically)
    member_count INT UNSIGNED NOT NULL DEFAULT 0,
    active_listings_count INT UNSIGNED NOT NULL DEFAULT 0,
    total_hours_exchanged DECIMAL(12,2) NOT NULL DEFAULT 0.00,

    -- Directory preferences
    show_member_count TINYINT(1) NOT NULL DEFAULT 1,
    show_activity_stats TINYINT(1) NOT NULL DEFAULT 0,
    show_location TINYINT(1) NOT NULL DEFAULT 1,

    -- Timestamps
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NULL ON UPDATE CURRENT_TIMESTAMP,

    INDEX idx_country (country_code),
    INDEX idx_location (latitude, longitude)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================================
-- 2. FEDERATION NOTIFICATIONS
-- Notifications for federation events (partnership requests, etc.)
-- ============================================================

CREATE TABLE IF NOT EXISTS federation_notifications (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,

    -- Target
    tenant_id INT UNSIGNED NOT NULL
        COMMENT 'Tenant receiving the notification',
    user_id INT UNSIGNED NULL
        COMMENT 'Specific user (NULL = all tenant admins)',

    -- Notification details
    type VARCHAR(50) NOT NULL
        COMMENT 'partnership_request, partnership_approved, etc.',
    title VARCHAR(200) NOT NULL,
    message TEXT NULL,
    data JSON NULL
        COMMENT 'Additional data (partnership_id, etc.)',

    -- Related entities
    related_tenant_id INT UNSIGNED NULL
        COMMENT 'Other tenant involved',
    related_partnership_id INT UNSIGNED NULL,

    -- Status
    is_read TINYINT(1) NOT NULL DEFAULT 0,
    read_at TIMESTAMP NULL,
    read_by INT UNSIGNED NULL,

    -- Timestamps
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,

    INDEX idx_tenant (tenant_id),
    INDEX idx_user (user_id),
    INDEX idx_unread (tenant_id, is_read),
    INDEX idx_type (type),
    INDEX idx_created (created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================================
-- 3. FEDERATION RATE LIMITS
-- Track rate limits for federation operations
-- ============================================================

CREATE TABLE IF NOT EXISTS federation_rate_limits (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,

    -- Scope
    tenant_id INT UNSIGNED NULL,
    user_id INT UNSIGNED NULL,
    ip_address VARCHAR(45) NULL,

    -- Rate limit tracking
    operation VARCHAR(50) NOT NULL
        COMMENT 'message, transaction, search, etc.',
    window_start TIMESTAMP NOT NULL,
    request_count INT UNSIGNED NOT NULL DEFAULT 1,

    -- Timestamps
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NULL ON UPDATE CURRENT_TIMESTAMP,

    INDEX idx_window (window_start),
    INDEX idx_operation (operation)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================================
-- 4. ADD COLUMNS TO EXISTING TABLES
-- These will error if columns already exist - that's OK, ignore errors
-- ============================================================

-- Users table - federation notification preference
-- Run this separately if it fails:
-- ALTER TABLE users ADD COLUMN federation_notifications_enabled TINYINT(1) NOT NULL DEFAULT 1;

-- Tenants table - federation contact info
-- Run these separately if they fail:
-- ALTER TABLE tenants ADD COLUMN federation_contact_email VARCHAR(255) NULL;
-- ALTER TABLE tenants ADD COLUMN federation_contact_name VARCHAR(200) NULL;

-- ============================================================
-- Phase 2 Migration Complete!
-- ============================================================
--
-- Tables created:
--   - federation_directory_profiles
--   - federation_notifications
--   - federation_rate_limits
--
-- To add columns manually (if ALTER TABLE failed):
--
-- ALTER TABLE users ADD COLUMN federation_notifications_enabled TINYINT(1) NOT NULL DEFAULT 1;
-- ALTER TABLE tenants ADD COLUMN federation_contact_email VARCHAR(255) NULL;
-- ALTER TABLE tenants ADD COLUMN federation_contact_name VARCHAR(200) NULL;
--
-- ============================================================

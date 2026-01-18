-- ============================================================
-- FEDERATION COMPLETE MIGRATION - FOR LIVE SERVER
-- ============================================================
-- Created: 2026-01-15
-- Run this ONCE on live server via phpMyAdmin
-- All features default to OFF (safe)
-- ============================================================

-- ============================================================
-- PHASE 1: CORE FEDERATION TABLES
-- ============================================================

-- 1. Master Kill Switch
CREATE TABLE IF NOT EXISTS federation_system_control (
    id INT UNSIGNED NOT NULL DEFAULT 1,
    federation_enabled TINYINT(1) NOT NULL DEFAULT 0,
    whitelist_mode_enabled TINYINT(1) NOT NULL DEFAULT 1,
    max_federation_level TINYINT UNSIGNED NOT NULL DEFAULT 0,
    cross_tenant_profiles_enabled TINYINT(1) NOT NULL DEFAULT 0,
    cross_tenant_messaging_enabled TINYINT(1) NOT NULL DEFAULT 0,
    cross_tenant_transactions_enabled TINYINT(1) NOT NULL DEFAULT 0,
    cross_tenant_listings_enabled TINYINT(1) NOT NULL DEFAULT 0,
    cross_tenant_events_enabled TINYINT(1) NOT NULL DEFAULT 0,
    cross_tenant_groups_enabled TINYINT(1) NOT NULL DEFAULT 0,
    emergency_lockdown_active TINYINT(1) NOT NULL DEFAULT 0,
    emergency_lockdown_reason TEXT NULL,
    emergency_lockdown_at TIMESTAMP NULL,
    emergency_lockdown_by INT UNSIGNED NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NULL ON UPDATE CURRENT_TIMESTAMP,
    updated_by INT UNSIGNED NULL,
    PRIMARY KEY (id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT IGNORE INTO federation_system_control (id, federation_enabled, whitelist_mode_enabled, max_federation_level)
VALUES (1, 0, 1, 0);

-- 2. Tenant Feature Flags
CREATE TABLE IF NOT EXISTS federation_tenant_features (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    tenant_id INT UNSIGNED NOT NULL,
    feature_key VARCHAR(100) NOT NULL,
    is_enabled TINYINT(1) NOT NULL DEFAULT 0,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    updated_by INT UNSIGNED NULL,
    UNIQUE KEY unique_tenant_feature (tenant_id, feature_key),
    INDEX idx_tenant (tenant_id),
    INDEX idx_feature (feature_key)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 3. Tenant Whitelist
CREATE TABLE IF NOT EXISTS federation_tenant_whitelist (
    tenant_id INT UNSIGNED PRIMARY KEY,
    approved_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    approved_by INT UNSIGNED NOT NULL,
    notes VARCHAR(500) NULL,
    INDEX idx_approved_at (approved_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 4. Partnerships
CREATE TABLE IF NOT EXISTS federation_partnerships (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    tenant_id INT UNSIGNED NOT NULL,
    partner_tenant_id INT UNSIGNED NOT NULL,
    status ENUM('pending', 'active', 'suspended', 'terminated') NOT NULL DEFAULT 'pending',
    federation_level TINYINT UNSIGNED NOT NULL DEFAULT 1,
    profiles_enabled TINYINT(1) NOT NULL DEFAULT 0,
    messaging_enabled TINYINT(1) NOT NULL DEFAULT 0,
    transactions_enabled TINYINT(1) NOT NULL DEFAULT 0,
    listings_enabled TINYINT(1) NOT NULL DEFAULT 0,
    events_enabled TINYINT(1) NOT NULL DEFAULT 0,
    groups_enabled TINYINT(1) NOT NULL DEFAULT 0,
    requested_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    requested_by INT UNSIGNED NULL,
    approved_at TIMESTAMP NULL,
    approved_by INT UNSIGNED NULL,
    terminated_at TIMESTAMP NULL,
    terminated_by INT UNSIGNED NULL,
    termination_reason VARCHAR(500) NULL,
    notes TEXT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NULL ON UPDATE CURRENT_TIMESTAMP,
    UNIQUE KEY unique_partnership (tenant_id, partner_tenant_id),
    INDEX idx_status (status),
    INDEX idx_tenant (tenant_id),
    INDEX idx_partner (partner_tenant_id),
    INDEX idx_level (federation_level)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 5. User Settings
CREATE TABLE IF NOT EXISTS federation_user_settings (
    user_id INT UNSIGNED PRIMARY KEY,
    federation_optin TINYINT(1) NOT NULL DEFAULT 0,
    profile_visible_federated TINYINT(1) NOT NULL DEFAULT 0,
    messaging_enabled_federated TINYINT(1) NOT NULL DEFAULT 0,
    transactions_enabled_federated TINYINT(1) NOT NULL DEFAULT 0,
    appear_in_federated_search TINYINT(1) NOT NULL DEFAULT 0,
    show_skills_federated TINYINT(1) NOT NULL DEFAULT 0,
    show_location_federated TINYINT(1) NOT NULL DEFAULT 0,
    service_reach ENUM('local_only', 'remote_ok', 'travel_ok') NOT NULL DEFAULT 'local_only',
    travel_radius_km INT UNSIGNED NULL DEFAULT NULL,
    opted_in_at TIMESTAMP NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NULL ON UPDATE CURRENT_TIMESTAMP,
    INDEX idx_optin (federation_optin),
    INDEX idx_searchable (appear_in_federated_search),
    INDEX idx_service_reach (service_reach)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 6. Audit Log
CREATE TABLE IF NOT EXISTS federation_audit_log (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    action_type VARCHAR(100) NOT NULL,
    category VARCHAR(50) NOT NULL,
    level ENUM('debug', 'info', 'warning', 'critical') NOT NULL DEFAULT 'info',
    source_tenant_id INT UNSIGNED NULL,
    target_tenant_id INT UNSIGNED NULL,
    actor_user_id INT UNSIGNED NULL,
    actor_name VARCHAR(200) NULL,
    actor_email VARCHAR(255) NULL,
    data JSON NULL,
    ip_address VARCHAR(45) NULL,
    user_agent VARCHAR(500) NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_action_type (action_type),
    INDEX idx_category (category),
    INDEX idx_level (level),
    INDEX idx_source_tenant (source_tenant_id),
    INDEX idx_target_tenant (target_tenant_id),
    INDEX idx_actor (actor_user_id),
    INDEX idx_created_at (created_at),
    INDEX idx_level_created (level, created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 7. Reputation
CREATE TABLE IF NOT EXISTS federation_reputation (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    user_id INT UNSIGNED NOT NULL,
    home_tenant_id INT UNSIGNED NOT NULL,
    trust_score DECIMAL(5,2) NOT NULL DEFAULT 0.00,
    reliability_score DECIMAL(5,2) NOT NULL DEFAULT 0.00,
    responsiveness_score DECIMAL(5,2) NOT NULL DEFAULT 0.00,
    review_score DECIMAL(5,2) NOT NULL DEFAULT 0.00,
    total_transactions INT UNSIGNED NOT NULL DEFAULT 0,
    successful_transactions INT UNSIGNED NOT NULL DEFAULT 0,
    reviews_received INT UNSIGNED NOT NULL DEFAULT 0,
    reviews_given INT UNSIGNED NOT NULL DEFAULT 0,
    hours_given DECIMAL(10,2) NOT NULL DEFAULT 0.00,
    hours_received DECIMAL(10,2) NOT NULL DEFAULT 0.00,
    is_verified TINYINT(1) NOT NULL DEFAULT 0,
    verified_at TIMESTAMP NULL,
    verified_by INT UNSIGNED NULL,
    share_reputation TINYINT(1) NOT NULL DEFAULT 0,
    last_calculated_at TIMESTAMP NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NULL ON UPDATE CURRENT_TIMESTAMP,
    UNIQUE KEY unique_user_tenant (user_id, home_tenant_id),
    INDEX idx_trust_score (trust_score),
    INDEX idx_verified (is_verified),
    INDEX idx_share (share_reputation)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================================
-- PHASE 2: ADMIN PANEL SUPPORT TABLES
-- ============================================================

-- 8. Directory Profiles
CREATE TABLE IF NOT EXISTS federation_directory_profiles (
    tenant_id INT UNSIGNED PRIMARY KEY,
    display_name VARCHAR(200) NULL,
    tagline VARCHAR(300) NULL,
    description TEXT NULL,
    logo_url VARCHAR(500) NULL,
    cover_image_url VARCHAR(500) NULL,
    website_url VARCHAR(500) NULL,
    country_code CHAR(2) NULL,
    region VARCHAR(100) NULL,
    city VARCHAR(100) NULL,
    latitude DECIMAL(10, 8) NULL,
    longitude DECIMAL(11, 8) NULL,
    member_count INT UNSIGNED NOT NULL DEFAULT 0,
    active_listings_count INT UNSIGNED NOT NULL DEFAULT 0,
    total_hours_exchanged DECIMAL(12,2) NOT NULL DEFAULT 0.00,
    show_member_count TINYINT(1) NOT NULL DEFAULT 1,
    show_activity_stats TINYINT(1) NOT NULL DEFAULT 0,
    show_location TINYINT(1) NOT NULL DEFAULT 1,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NULL ON UPDATE CURRENT_TIMESTAMP,
    INDEX idx_country (country_code),
    INDEX idx_location (latitude, longitude)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 9. Notifications
CREATE TABLE IF NOT EXISTS federation_notifications (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    tenant_id INT UNSIGNED NOT NULL,
    user_id INT UNSIGNED NULL,
    type VARCHAR(50) NOT NULL,
    title VARCHAR(200) NOT NULL,
    message TEXT NULL,
    data JSON NULL,
    related_tenant_id INT UNSIGNED NULL,
    related_partnership_id INT UNSIGNED NULL,
    is_read TINYINT(1) NOT NULL DEFAULT 0,
    read_at TIMESTAMP NULL,
    read_by INT UNSIGNED NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_tenant (tenant_id),
    INDEX idx_user (user_id),
    INDEX idx_unread (tenant_id, is_read),
    INDEX idx_type (type),
    INDEX idx_created (created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 10. Rate Limits
CREATE TABLE IF NOT EXISTS federation_rate_limits (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    tenant_id INT UNSIGNED NULL,
    user_id INT UNSIGNED NULL,
    ip_address VARCHAR(45) NULL,
    operation VARCHAR(50) NOT NULL,
    window_start TIMESTAMP NOT NULL,
    request_count INT UNSIGNED NOT NULL DEFAULT 1,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NULL ON UPDATE CURRENT_TIMESTAMP,
    INDEX idx_window (window_start),
    INDEX idx_operation (operation)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================================
-- DONE!
-- 10 tables created, all features OFF by default
-- ============================================================

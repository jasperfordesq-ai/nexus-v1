-- ============================================================
-- Federation Tables Migration - phpMyAdmin Compatible
-- ============================================================
-- SAFE: All features default to OFF/disabled
-- Run each section separately if needed
-- ============================================================

-- 1. FEDERATION SYSTEM CONTROL (Master Kill Switch)
CREATE TABLE IF NOT EXISTS federation_system_control (
    id INT UNSIGNED NOT NULL DEFAULT 1,

    -- Master controls (ALL DEFAULT OFF)
    federation_enabled TINYINT(1) NOT NULL DEFAULT 0
        COMMENT 'Master switch: 0 = ALL federation disabled globally',
    whitelist_mode_enabled TINYINT(1) NOT NULL DEFAULT 1
        COMMENT 'Only whitelisted tenants can use federation',
    max_federation_level TINYINT UNSIGNED NOT NULL DEFAULT 0
        COMMENT 'Maximum federation level any tenant can use (0-4)',

    -- Feature kill switches (ALL DEFAULT OFF)
    cross_tenant_profiles_enabled TINYINT(1) NOT NULL DEFAULT 0,
    cross_tenant_messaging_enabled TINYINT(1) NOT NULL DEFAULT 0,
    cross_tenant_transactions_enabled TINYINT(1) NOT NULL DEFAULT 0,
    cross_tenant_listings_enabled TINYINT(1) NOT NULL DEFAULT 0,
    cross_tenant_events_enabled TINYINT(1) NOT NULL DEFAULT 0,
    cross_tenant_groups_enabled TINYINT(1) NOT NULL DEFAULT 0,

    -- Emergency lockdown
    emergency_lockdown_active TINYINT(1) NOT NULL DEFAULT 0,
    emergency_lockdown_reason TEXT NULL,
    emergency_lockdown_at TIMESTAMP NULL,
    emergency_lockdown_by INT UNSIGNED NULL,

    -- Audit
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NULL ON UPDATE CURRENT_TIMESTAMP,
    updated_by INT UNSIGNED NULL,

    PRIMARY KEY (id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Insert default row (everything OFF)
INSERT IGNORE INTO federation_system_control (id, federation_enabled, whitelist_mode_enabled, max_federation_level)
VALUES (1, 0, 1, 0);

-- ============================================================

-- 2. FEDERATION TENANT FEATURES
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

-- ============================================================

-- 3. FEDERATION TENANT WHITELIST
CREATE TABLE IF NOT EXISTS federation_tenant_whitelist (
    tenant_id INT UNSIGNED PRIMARY KEY,
    approved_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    approved_by INT UNSIGNED NOT NULL,
    notes VARCHAR(500) NULL,

    INDEX idx_approved_at (approved_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================================

-- 4. FEDERATION PARTNERSHIPS
CREATE TABLE IF NOT EXISTS federation_partnerships (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    tenant_id INT UNSIGNED NOT NULL,
    partner_tenant_id INT UNSIGNED NOT NULL,

    -- Partnership status
    status ENUM('pending', 'active', 'suspended', 'terminated') NOT NULL DEFAULT 'pending',
    federation_level TINYINT UNSIGNED NOT NULL DEFAULT 1
        COMMENT '1=Discovery, 2=Social, 3=Economic, 4=Integrated',

    -- Permission flags (ALL DEFAULT OFF)
    profiles_enabled TINYINT(1) NOT NULL DEFAULT 0,
    messaging_enabled TINYINT(1) NOT NULL DEFAULT 0,
    transactions_enabled TINYINT(1) NOT NULL DEFAULT 0,
    listings_enabled TINYINT(1) NOT NULL DEFAULT 0,
    events_enabled TINYINT(1) NOT NULL DEFAULT 0,
    groups_enabled TINYINT(1) NOT NULL DEFAULT 0,

    -- Request tracking
    requested_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    requested_by INT UNSIGNED NULL,
    approved_at TIMESTAMP NULL,
    approved_by INT UNSIGNED NULL,
    terminated_at TIMESTAMP NULL,
    terminated_by INT UNSIGNED NULL,
    termination_reason VARCHAR(500) NULL,

    -- Notes
    notes TEXT NULL,

    -- Timestamps
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NULL ON UPDATE CURRENT_TIMESTAMP,

    UNIQUE KEY unique_partnership (tenant_id, partner_tenant_id),
    INDEX idx_status (status),
    INDEX idx_tenant (tenant_id),
    INDEX idx_partner (partner_tenant_id),
    INDEX idx_level (federation_level)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================================

-- 5. FEDERATION USER SETTINGS
CREATE TABLE IF NOT EXISTS federation_user_settings (
    user_id INT UNSIGNED PRIMARY KEY,

    -- Master opt-in (DEFAULT OFF)
    federation_optin TINYINT(1) NOT NULL DEFAULT 0
        COMMENT 'User has explicitly opted into federation',

    -- Visibility settings (ALL DEFAULT OFF)
    profile_visible_federated TINYINT(1) NOT NULL DEFAULT 0,
    messaging_enabled_federated TINYINT(1) NOT NULL DEFAULT 0,
    transactions_enabled_federated TINYINT(1) NOT NULL DEFAULT 0,

    -- Discovery preferences (ALL DEFAULT OFF)
    appear_in_federated_search TINYINT(1) NOT NULL DEFAULT 0,
    show_skills_federated TINYINT(1) NOT NULL DEFAULT 0,
    show_location_federated TINYINT(1) NOT NULL DEFAULT 0,

    -- Service preferences
    service_reach ENUM('local_only', 'remote_ok', 'travel_ok') NOT NULL DEFAULT 'local_only',
    travel_radius_km INT UNSIGNED NULL DEFAULT NULL,

    -- Timestamps
    opted_in_at TIMESTAMP NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NULL ON UPDATE CURRENT_TIMESTAMP,

    INDEX idx_optin (federation_optin),
    INDEX idx_searchable (appear_in_federated_search),
    INDEX idx_service_reach (service_reach)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================================

-- 6. FEDERATION AUDIT LOG
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

-- ============================================================

-- 7. FEDERATION REPUTATION
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
-- 8. ADD COLUMNS TO EXISTING TABLES
-- Run these separately - they will fail gracefully if columns exist
-- ============================================================

-- Users table columns
ALTER TABLE users ADD COLUMN IF NOT EXISTS federation_optin TINYINT(1) NOT NULL DEFAULT 0;
ALTER TABLE users ADD COLUMN IF NOT EXISTS federated_profile_visible TINYINT(1) NOT NULL DEFAULT 0;

-- Groups table columns
ALTER TABLE `groups` ADD COLUMN IF NOT EXISTS allow_federated_members TINYINT(1) NOT NULL DEFAULT 0;
ALTER TABLE `groups` ADD COLUMN IF NOT EXISTS federated_visibility ENUM('none', 'listed', 'joinable') NOT NULL DEFAULT 'none';

-- Listings table columns
ALTER TABLE listings ADD COLUMN IF NOT EXISTS federated_visibility ENUM('none', 'listed', 'bookable') NOT NULL DEFAULT 'none';
ALTER TABLE listings ADD COLUMN IF NOT EXISTS service_type ENUM('physical_only', 'remote_only', 'hybrid', 'location_dependent') NOT NULL DEFAULT 'physical_only';

-- Events table columns
ALTER TABLE events ADD COLUMN IF NOT EXISTS federated_visibility ENUM('none', 'listed', 'joinable') NOT NULL DEFAULT 'none';
ALTER TABLE events ADD COLUMN IF NOT EXISTS allow_remote_attendance TINYINT(1) NOT NULL DEFAULT 0;

-- ============================================================
-- VERIFICATION: Check system control is safely OFF
-- ============================================================
SELECT
    'Federation System Status' AS info,
    IF(federation_enabled = 0, 'SAFE - OFF', 'WARNING - ON') AS master_switch,
    IF(emergency_lockdown_active = 0, 'No Lockdown', 'LOCKDOWN ACTIVE') AS lockdown
FROM federation_system_control
WHERE id = 1;

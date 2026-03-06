-- Migration: Enable all features on all tenants + populate federation tables
-- Date: 2026-03-06
-- Purpose: Fix tenant onboarding gap where federation_tenant_features and
--          federation_system_control were never populated for new tenants,
--          causing "federation is not available" even when admin toggles it ON.

-- ============================================================
-- 1. Enable federation globally (master switch)
-- ============================================================

-- Ensure the system control table exists
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

-- Insert or update the system control row: enable federation + all cross-tenant features
INSERT INTO federation_system_control (
    id, federation_enabled, whitelist_mode_enabled, max_federation_level,
    cross_tenant_profiles_enabled, cross_tenant_messaging_enabled,
    cross_tenant_transactions_enabled, cross_tenant_listings_enabled,
    cross_tenant_events_enabled, cross_tenant_groups_enabled,
    emergency_lockdown_active, created_at
) VALUES (
    1, 1, 0, 4,
    1, 1, 1, 1, 1, 1,
    0, NOW()
) ON DUPLICATE KEY UPDATE
    federation_enabled = 1,
    whitelist_mode_enabled = 0,
    max_federation_level = 4,
    cross_tenant_profiles_enabled = 1,
    cross_tenant_messaging_enabled = 1,
    cross_tenant_transactions_enabled = 1,
    cross_tenant_listings_enabled = 1,
    cross_tenant_events_enabled = 1,
    cross_tenant_groups_enabled = 1,
    updated_at = NOW();


-- ============================================================
-- 2. Ensure federation_tenant_features table exists
-- ============================================================

CREATE TABLE IF NOT EXISTS federation_tenant_features (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    tenant_id INT UNSIGNED NOT NULL,
    feature_key VARCHAR(100) NOT NULL,
    is_enabled TINYINT(1) NOT NULL DEFAULT 0,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    updated_by INT UNSIGNED NULL,
    UNIQUE KEY unique_tenant_feature (tenant_id, feature_key),
    INDEX idx_tenant (tenant_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;


-- ============================================================
-- 3. Enable federation for ALL existing tenants
-- ============================================================

-- Enable tenant_federation_enabled for every tenant
INSERT INTO federation_tenant_features (tenant_id, feature_key, is_enabled, updated_at)
SELECT t.id, 'tenant_federation_enabled', 1, NOW()
FROM tenants t
ON DUPLICATE KEY UPDATE is_enabled = 1, updated_at = NOW();

-- Enable tenant_appear_in_directory for every tenant
INSERT INTO federation_tenant_features (tenant_id, feature_key, is_enabled, updated_at)
SELECT t.id, 'tenant_appear_in_directory', 1, NOW()
FROM tenants t
ON DUPLICATE KEY UPDATE is_enabled = 1, updated_at = NOW();

-- Enable cross-tenant profiles for every tenant
INSERT INTO federation_tenant_features (tenant_id, feature_key, is_enabled, updated_at)
SELECT t.id, 'tenant_profiles_enabled', 1, NOW()
FROM tenants t
ON DUPLICATE KEY UPDATE is_enabled = 1, updated_at = NOW();

-- Enable cross-tenant messaging for every tenant
INSERT INTO federation_tenant_features (tenant_id, feature_key, is_enabled, updated_at)
SELECT t.id, 'tenant_messaging_enabled', 1, NOW()
FROM tenants t
ON DUPLICATE KEY UPDATE is_enabled = 1, updated_at = NOW();

-- Enable cross-tenant transactions for every tenant
INSERT INTO federation_tenant_features (tenant_id, feature_key, is_enabled, updated_at)
SELECT t.id, 'tenant_transactions_enabled', 1, NOW()
FROM tenants t
ON DUPLICATE KEY UPDATE is_enabled = 1, updated_at = NOW();

-- Enable cross-tenant listings for every tenant
INSERT INTO federation_tenant_features (tenant_id, feature_key, is_enabled, updated_at)
SELECT t.id, 'tenant_listings_enabled', 1, NOW()
FROM tenants t
ON DUPLICATE KEY UPDATE is_enabled = 1, updated_at = NOW();

-- Enable cross-tenant events for every tenant
INSERT INTO federation_tenant_features (tenant_id, feature_key, is_enabled, updated_at)
SELECT t.id, 'tenant_events_enabled', 1, NOW()
FROM tenants t
ON DUPLICATE KEY UPDATE is_enabled = 1, updated_at = NOW();

-- Enable cross-tenant groups for every tenant
INSERT INTO federation_tenant_features (tenant_id, feature_key, is_enabled, updated_at)
SELECT t.id, 'tenant_groups_enabled', 1, NOW()
FROM tenants t
ON DUPLICATE KEY UPDATE is_enabled = 1, updated_at = NOW();


-- ============================================================
-- 4. Enable ALL features in tenants.features JSON for ALL tenants
-- ============================================================

UPDATE tenants SET features = '{"events":true,"groups":true,"gamification":true,"goals":true,"blog":true,"resources":true,"volunteering":true,"exchange_workflow":true,"organisations":true,"federation":true,"connections":true,"reviews":true,"polls":true,"job_vacancies":true,"ideation_challenges":true,"direct_messaging":true,"group_exchanges":true,"search":true,"ai_chat":true}';


-- ============================================================
-- 5. Ensure federation_tenant_whitelist table exists
--    (not needed if whitelist_mode_enabled=0, but ensure table exists)
-- ============================================================

CREATE TABLE IF NOT EXISTS federation_tenant_whitelist (
    tenant_id INT UNSIGNED PRIMARY KEY,
    approved_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    approved_by INT UNSIGNED NOT NULL,
    notes VARCHAR(500) NULL,
    INDEX idx_approved_at (approved_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

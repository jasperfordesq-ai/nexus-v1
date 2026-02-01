-- ============================================================================
-- TRUSTED DEVICES FOR 2FA ("Remember This Device")
-- ============================================================================
-- Date: February 1, 2026
-- Purpose: Allow users to skip 2FA on trusted devices for 30 days
-- This migration is IDEMPOTENT - safe to run multiple times
-- ============================================================================

SET SQL_MODE='ALLOW_INVALID_DATES';

-- ============================================================================
-- TRUSTED DEVICES TABLE
-- ============================================================================
-- Stores trusted device tokens that bypass 2FA for a period

CREATE TABLE IF NOT EXISTS user_trusted_devices (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    tenant_id INT NOT NULL,

    -- Device identification
    device_token_hash VARCHAR(255) NOT NULL COMMENT 'SHA-256 hash of the token stored in cookie',
    device_name VARCHAR(255) NULL COMMENT 'Browser/OS derived from user agent',
    device_fingerprint VARCHAR(64) NULL COMMENT 'Optional fingerprint for additional security',

    -- Context when device was trusted
    ip_address VARCHAR(45) NOT NULL,
    user_agent TEXT NULL,

    -- Trust period
    trusted_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    expires_at DATETIME NOT NULL COMMENT 'When this trust expires (default 30 days)',
    last_used_at DATETIME NULL COMMENT 'Last time this device was used to skip 2FA',

    -- Revocation
    is_revoked TINYINT(1) NOT NULL DEFAULT 0,
    revoked_at DATETIME NULL,
    revoked_reason VARCHAR(100) NULL COMMENT 'user_action, password_change, admin_reset, etc.',

    -- Timestamps
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,

    -- Constraints
    UNIQUE KEY unique_device_token (device_token_hash),
    INDEX idx_user_active (user_id, is_revoked, expires_at),
    INDEX idx_tenant (tenant_id),
    INDEX idx_cleanup (expires_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
COMMENT='Trusted devices that can skip 2FA verification';

-- ============================================================================
-- VERIFICATION QUERIES (for testing)
-- ============================================================================
-- SELECT * FROM user_trusted_devices LIMIT 5;
-- SELECT COUNT(*) FROM user_trusted_devices WHERE is_revoked = 0 AND expires_at > NOW();

-- ============================================================================
-- TOTP TWO-FACTOR AUTHENTICATION SYSTEM
-- ============================================================================
-- Date: February 1, 2026
-- Purpose: Add TOTP-based 2FA with backup codes for all users
-- This migration is IDEMPOTENT - safe to run multiple times
-- ============================================================================

SET SQL_MODE='ALLOW_INVALID_DATES';

-- ============================================================================
-- 1. USER TOTP SETTINGS TABLE
-- ============================================================================
-- Stores encrypted TOTP secrets and 2FA status per user

CREATE TABLE IF NOT EXISTS user_totp_settings (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    tenant_id INT NOT NULL,

    -- TOTP Secret (encrypted with AES-256-GCM)
    totp_secret_encrypted TEXT NULL COMMENT 'AES-256-GCM encrypted TOTP secret',

    -- 2FA Status
    is_enabled TINYINT(1) NOT NULL DEFAULT 0 COMMENT '1 = 2FA active and enforced',
    is_pending_setup TINYINT(1) NOT NULL DEFAULT 0 COMMENT '1 = Secret generated but not verified',

    -- Security metadata
    enabled_at DATETIME NULL COMMENT 'When 2FA was successfully enabled',
    last_verified_at DATETIME NULL COMMENT 'Last successful TOTP verification',
    verified_device_count INT NOT NULL DEFAULT 0 COMMENT 'Number of times 2FA verified',

    -- Timestamps
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,

    -- Constraints
    UNIQUE KEY unique_user_totp (user_id),
    INDEX idx_tenant (tenant_id),
    INDEX idx_enabled (tenant_id, is_enabled)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
COMMENT='TOTP 2FA settings and encrypted secrets per user';

-- ============================================================================
-- 2. BACKUP CODES TABLE
-- ============================================================================
-- One-time use backup codes for recovery when device is lost

CREATE TABLE IF NOT EXISTS user_backup_codes (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    tenant_id INT NOT NULL,

    -- Backup code (hashed with password_hash for timing-safe comparison)
    code_hash VARCHAR(255) NOT NULL COMMENT 'password_hash() of the backup code',

    -- Usage tracking
    is_used TINYINT(1) NOT NULL DEFAULT 0,
    used_at DATETIME NULL,
    used_ip VARCHAR(45) NULL COMMENT 'IP address when code was used',
    used_user_agent TEXT NULL,

    -- Timestamps
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,

    -- Constraints
    INDEX idx_user_unused (user_id, is_used),
    INDEX idx_tenant (tenant_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
COMMENT='One-time backup codes for 2FA recovery';

-- ============================================================================
-- 3. 2FA VERIFICATION ATTEMPTS TABLE (Rate Limiting)
-- ============================================================================
-- Track failed 2FA attempts to prevent brute force attacks

CREATE TABLE IF NOT EXISTS totp_verification_attempts (
    id BIGINT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    tenant_id INT NOT NULL,

    ip_address VARCHAR(45) NOT NULL,
    user_agent TEXT NULL,

    attempt_type ENUM('totp', 'backup_code') NOT NULL DEFAULT 'totp',
    is_successful TINYINT(1) NOT NULL DEFAULT 0,
    failure_reason VARCHAR(100) NULL COMMENT 'invalid_code, expired, rate_limited, etc.',

    attempted_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,

    -- Constraints
    INDEX idx_user_attempts (user_id, attempted_at),
    INDEX idx_ip_attempts (ip_address, attempted_at),
    INDEX idx_tenant (tenant_id),
    INDEX idx_cleanup (attempted_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
COMMENT='2FA verification attempts for rate limiting and audit';

-- ============================================================================
-- 4. ADD 2FA STATUS TO USERS TABLE
-- ============================================================================
-- Quick lookup flag to check if user requires 2FA during login

ALTER TABLE users
    ADD COLUMN IF NOT EXISTS totp_enabled TINYINT(1) NOT NULL DEFAULT 0
    COMMENT '1 = User has 2FA enabled' AFTER password_hash,
    ADD COLUMN IF NOT EXISTS totp_setup_required TINYINT(1) NOT NULL DEFAULT 1
    COMMENT '1 = User must set up 2FA on next login' AFTER totp_enabled;

-- Index for quick lookup during login
CREATE INDEX IF NOT EXISTS idx_users_totp ON users(totp_enabled);

-- ============================================================================
-- 5. ADMIN 2FA OVERRIDE LOG
-- ============================================================================
-- Audit trail when admins reset or bypass 2FA for locked-out users

CREATE TABLE IF NOT EXISTS totp_admin_overrides (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL COMMENT 'User whose 2FA was overridden',
    admin_id INT NOT NULL COMMENT 'Admin who performed the action',
    tenant_id INT NOT NULL,

    action_type ENUM('reset', 'disable', 'bypass_login') NOT NULL,
    reason TEXT NOT NULL COMMENT 'Admin must provide reason',

    -- Context
    ip_address VARCHAR(45) NULL,
    user_agent TEXT NULL,

    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,

    -- Constraints
    INDEX idx_user (user_id),
    INDEX idx_admin (admin_id),
    INDEX idx_tenant (tenant_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
COMMENT='Audit log for admin 2FA overrides';

-- ============================================================================
-- VERIFICATION QUERIES (for testing)
-- ============================================================================
-- SELECT * FROM user_totp_settings LIMIT 5;
-- SELECT * FROM user_backup_codes LIMIT 5;
-- SELECT * FROM totp_verification_attempts LIMIT 5;
-- SELECT * FROM totp_admin_overrides LIMIT 5;
-- SHOW COLUMNS FROM users LIKE 'totp%';

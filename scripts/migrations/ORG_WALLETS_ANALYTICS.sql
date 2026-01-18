-- ============================================================================
-- Migration: Organization Wallets, Admin Analytics, and User Insights
-- Created: 2026-01-07
--
-- Run this migration on your database to add:
-- 1. Organization wallet system with member approval workflow
-- 2. Abuse detection alerts
-- ============================================================================

-- ----------------------------------------------------------------------------
-- ORGANIZATION WALLETS
-- ----------------------------------------------------------------------------

-- Organization Wallet (separate balance from owner)
CREATE TABLE IF NOT EXISTS org_wallets (
    id INT AUTO_INCREMENT PRIMARY KEY,
    tenant_id INT NOT NULL,
    organization_id INT NOT NULL,
    balance DECIMAL(10,2) DEFAULT 0.00,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NULL ON UPDATE CURRENT_TIMESTAMP,
    UNIQUE KEY unique_org_wallet (tenant_id, organization_id),
    INDEX idx_org_wallet_tenant (tenant_id),
    INDEX idx_org_wallet_org (organization_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Organization Membership (role-based access)
CREATE TABLE IF NOT EXISTS org_members (
    id INT AUTO_INCREMENT PRIMARY KEY,
    tenant_id INT NOT NULL,
    organization_id INT NOT NULL,
    user_id INT NOT NULL,
    role ENUM('owner', 'admin', 'member') DEFAULT 'member',
    status ENUM('active', 'pending', 'invited', 'removed') DEFAULT 'pending',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NULL ON UPDATE CURRENT_TIMESTAMP,
    UNIQUE KEY unique_org_member (organization_id, user_id),
    INDEX idx_org_members_tenant (tenant_id),
    INDEX idx_org_members_user (user_id),
    INDEX idx_org_members_org (organization_id),
    INDEX idx_org_members_status (status)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Wallet Transfer Requests (approval workflow)
CREATE TABLE IF NOT EXISTS org_transfer_requests (
    id INT AUTO_INCREMENT PRIMARY KEY,
    tenant_id INT NOT NULL,
    organization_id INT NOT NULL,
    requester_id INT NOT NULL,
    recipient_id INT NOT NULL,
    amount DECIMAL(10,2) NOT NULL,
    description TEXT,
    status ENUM('pending', 'approved', 'rejected', 'cancelled') DEFAULT 'pending',
    approved_by INT NULL,
    approved_at TIMESTAMP NULL,
    rejection_reason TEXT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NULL ON UPDATE CURRENT_TIMESTAMP,
    INDEX idx_transfer_tenant (tenant_id),
    INDEX idx_transfer_org (organization_id),
    INDEX idx_transfer_status (status),
    INDEX idx_transfer_requester (requester_id),
    INDEX idx_transfer_recipient (recipient_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Organization Transaction Log (audit trail)
CREATE TABLE IF NOT EXISTS org_transactions (
    id INT AUTO_INCREMENT PRIMARY KEY,
    tenant_id INT NOT NULL,
    organization_id INT NOT NULL,
    transfer_request_id INT NULL,
    sender_type ENUM('organization', 'user') NOT NULL,
    sender_id INT NOT NULL,
    receiver_type ENUM('organization', 'user') NOT NULL,
    receiver_id INT NOT NULL,
    amount DECIMAL(10,2) NOT NULL,
    description TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_org_trx_tenant (tenant_id),
    INDEX idx_org_trx_org (organization_id),
    INDEX idx_org_trx_date (created_at),
    INDEX idx_org_trx_sender (sender_type, sender_id),
    INDEX idx_org_trx_receiver (receiver_type, receiver_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ----------------------------------------------------------------------------
-- USER NOTIFICATION PREFERENCES
-- ----------------------------------------------------------------------------

-- Add notification_preferences column to users table if not exists
-- This stores JSON preferences for email notifications
-- Note: Using a procedure to check if column exists before adding (MySQL doesn't support IF NOT EXISTS for columns in all versions)
SET @column_exists = (
    SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS
    WHERE TABLE_SCHEMA = DATABASE()
    AND TABLE_NAME = 'users'
    AND COLUMN_NAME = 'notification_preferences'
);

SET @sql = IF(@column_exists = 0,
    'ALTER TABLE users ADD COLUMN notification_preferences JSON NULL',
    'SELECT "Column notification_preferences already exists"'
);

PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

-- ----------------------------------------------------------------------------
-- ABUSE DETECTION
-- ----------------------------------------------------------------------------

-- Abuse Detection Alerts
CREATE TABLE IF NOT EXISTS abuse_alerts (
    id INT AUTO_INCREMENT PRIMARY KEY,
    tenant_id INT NOT NULL,
    alert_type VARCHAR(50) NOT NULL,
    severity ENUM('low', 'medium', 'high', 'critical') DEFAULT 'medium',
    user_id INT NULL,
    transaction_id INT NULL,
    details JSON,
    status ENUM('new', 'reviewing', 'resolved', 'dismissed') DEFAULT 'new',
    resolved_by INT NULL,
    resolved_at TIMESTAMP NULL,
    resolution_notes TEXT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_abuse_tenant (tenant_id),
    INDEX idx_abuse_status (status),
    INDEX idx_abuse_severity (severity),
    INDEX idx_abuse_user (user_id),
    INDEX idx_abuse_type (alert_type),
    INDEX idx_abuse_date (created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

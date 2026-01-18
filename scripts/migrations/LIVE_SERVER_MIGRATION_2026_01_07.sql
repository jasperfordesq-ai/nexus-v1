-- ============================================================================
-- LIVE SERVER MIGRATION - Organization Wallets & Analytics
-- Date: 2026-01-07
--
-- This is a COMPLETE migration script for the live server.
-- It includes:
--   1. Organization Wallet Tables (org_wallets, org_members, org_transfer_requests, org_transactions)
--   2. Abuse Detection (abuse_alerts)
--   3. Data initialization (add existing org owners to org_members)
--
-- INSTRUCTIONS:
--   1. BACKUP YOUR DATABASE FIRST!
--   2. Run this SQL file on your production database
--   3. Or use the PHP runner: php scripts/migrations/deploy_live_migration.php
--
-- ============================================================================

-- ============================================================================
-- SECTION 1: CREATE TABLES
-- ============================================================================

-- Organization Wallet (separate balance from owner's personal balance)
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

-- Organization Membership (role-based access control)
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

-- Organization Transaction Log (audit trail for all wallet movements)
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

-- Abuse Detection Alerts (admin monitoring)
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


-- ============================================================================
-- SECTION 2: INITIALIZE DATA
-- Add existing organization owners to org_members table
-- ============================================================================

-- Insert organization owners into org_members (if not already present)
INSERT INTO org_members (tenant_id, organization_id, user_id, role, status, created_at)
SELECT
    vo.tenant_id,
    vo.id AS organization_id,
    vo.user_id,
    'owner' AS role,
    'active' AS status,
    NOW() AS created_at
FROM vol_organizations vo
WHERE NOT EXISTS (
    SELECT 1 FROM org_members om
    WHERE om.organization_id = vo.id AND om.user_id = vo.user_id
);

-- Create wallets for organizations that don't have one yet
INSERT INTO org_wallets (tenant_id, organization_id, balance, created_at)
SELECT
    vo.tenant_id,
    vo.id AS organization_id,
    0.00 AS balance,
    NOW() AS created_at
FROM vol_organizations vo
WHERE NOT EXISTS (
    SELECT 1 FROM org_wallets ow
    WHERE ow.organization_id = vo.id
);


-- ============================================================================
-- SECTION 3: VERIFICATION QUERIES (Run these to confirm success)
-- ============================================================================

-- Check all tables exist
-- SELECT 'org_wallets' AS tbl, COUNT(*) AS rows FROM org_wallets
-- UNION ALL SELECT 'org_members', COUNT(*) FROM org_members
-- UNION ALL SELECT 'org_transfer_requests', COUNT(*) FROM org_transfer_requests
-- UNION ALL SELECT 'org_transactions', COUNT(*) FROM org_transactions
-- UNION ALL SELECT 'abuse_alerts', COUNT(*) FROM abuse_alerts;

-- Check all organizations have owners in org_members
-- SELECT vo.id, vo.name, vo.user_id, om.role
-- FROM vol_organizations vo
-- LEFT JOIN org_members om ON om.organization_id = vo.id AND om.user_id = vo.user_id
-- ORDER BY vo.id;

-- Check all organizations have wallets
-- SELECT vo.id, vo.name, ow.balance
-- FROM vol_organizations vo
-- LEFT JOIN org_wallets ow ON ow.organization_id = vo.id
-- ORDER BY vo.id;


-- ============================================================================
-- END OF MIGRATION
-- ============================================================================

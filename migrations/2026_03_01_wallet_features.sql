-- ============================================================
-- WALLET & EXCHANGES FEATURES MIGRATION
-- Version: 1.0
-- Date: 2026-03-01
-- Description: Adds community fund, transaction categories,
--              exchange ratings, prep time, starting balances,
--              and credit donations
-- ============================================================

-- ============================================================
-- 1. TRANSACTION CATEGORIES TABLE
-- Standard categories for labelling transactions
-- ============================================================
CREATE TABLE IF NOT EXISTS transaction_categories (
    id INT AUTO_INCREMENT PRIMARY KEY,
    tenant_id INT NOT NULL,
    name VARCHAR(100) NOT NULL,
    slug VARCHAR(100) NOT NULL,
    description VARCHAR(255) NULL,
    icon VARCHAR(50) NULL COMMENT 'Lucide icon name',
    color VARCHAR(7) NULL COMMENT 'Hex color e.g. #3B82F6',
    sort_order INT NOT NULL DEFAULT 0,
    is_system TINYINT(1) NOT NULL DEFAULT 0 COMMENT 'System-defined, cannot be deleted',
    is_active TINYINT(1) NOT NULL DEFAULT 1,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,

    UNIQUE KEY uk_tenant_slug (tenant_id, slug),
    INDEX idx_tenant_active (tenant_id, is_active),
    FOREIGN KEY (tenant_id) REFERENCES tenants(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================================
-- 2. ADD category_id TO transactions TABLE
-- ============================================================
-- ALTER TABLE transactions ADD COLUMN IF NOT EXISTS category_id INT NULL AFTER description;
-- MariaDB 10.5+ supports ADD COLUMN IF NOT EXISTS
ALTER TABLE transactions ADD COLUMN IF NOT EXISTS category_id INT NULL;

-- ============================================================
-- 3. ADD prep_time TO exchange_requests TABLE
-- Prep time in decimal hours (e.g. 0.5 = 30 minutes)
-- ============================================================
ALTER TABLE exchange_requests ADD COLUMN IF NOT EXISTS prep_time DECIMAL(5,2) NULL DEFAULT NULL COMMENT 'Preparation time in hours';

-- ============================================================
-- 4. ADD prep_time TO transactions TABLE
-- Records prep time associated with exchange transactions
-- ============================================================
ALTER TABLE transactions ADD COLUMN IF NOT EXISTS prep_time DECIMAL(5,2) NULL DEFAULT NULL COMMENT 'Preparation time in hours';

-- ============================================================
-- 5. ADD transaction_type TO transactions TABLE
-- Distinguishes exchange, transfer, donation, starting_balance, admin_grant
-- ============================================================
ALTER TABLE transactions ADD COLUMN IF NOT EXISTS transaction_type VARCHAR(30) NOT NULL DEFAULT 'transfer' COMMENT 'transfer, exchange, donation, starting_balance, admin_grant, community_fund';

-- ============================================================
-- 6. COMMUNITY FUND ACCOUNTS TABLE
-- One per tenant, holds community-owned time credits
-- ============================================================
CREATE TABLE IF NOT EXISTS community_fund_accounts (
    id INT AUTO_INCREMENT PRIMARY KEY,
    tenant_id INT NOT NULL,
    balance DECIMAL(10,2) NOT NULL DEFAULT 0.00,
    total_deposited DECIMAL(10,2) NOT NULL DEFAULT 0.00,
    total_withdrawn DECIMAL(10,2) NOT NULL DEFAULT 0.00,
    total_donated DECIMAL(10,2) NOT NULL DEFAULT 0.00,
    description VARCHAR(500) NULL COMMENT 'Description of the community fund',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,

    UNIQUE KEY uk_tenant (tenant_id),
    FOREIGN KEY (tenant_id) REFERENCES tenants(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================================
-- 7. COMMUNITY FUND TRANSACTIONS TABLE
-- Audit trail for all community fund movements
-- ============================================================
CREATE TABLE IF NOT EXISTS community_fund_transactions (
    id INT AUTO_INCREMENT PRIMARY KEY,
    tenant_id INT NOT NULL,
    fund_id INT NOT NULL,
    user_id INT NULL COMMENT 'User involved (admin for deposits/withdrawals, donor for donations)',
    type ENUM('deposit', 'withdrawal', 'donation', 'starting_balance_grant') NOT NULL,
    amount DECIMAL(10,2) NOT NULL,
    balance_after DECIMAL(10,2) NOT NULL,
    description VARCHAR(500) NULL,
    admin_id INT NULL COMMENT 'Admin who performed the action',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,

    INDEX idx_tenant (tenant_id),
    INDEX idx_fund (fund_id),
    INDEX idx_user (user_id),
    INDEX idx_type (type),
    FOREIGN KEY (tenant_id) REFERENCES tenants(id) ON DELETE CASCADE,
    FOREIGN KEY (fund_id) REFERENCES community_fund_accounts(id) ON DELETE CASCADE,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE SET NULL,
    FOREIGN KEY (admin_id) REFERENCES users(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================================
-- 8. EXCHANGE RATINGS TABLE
-- Post-completion satisfaction ratings from both parties
-- ============================================================
CREATE TABLE IF NOT EXISTS exchange_ratings (
    id INT AUTO_INCREMENT PRIMARY KEY,
    tenant_id INT NOT NULL,
    exchange_id INT NOT NULL,
    rater_id INT NOT NULL COMMENT 'User giving the rating',
    rated_id INT NOT NULL COMMENT 'User being rated',
    rating TINYINT NOT NULL COMMENT '1-5 star rating',
    comment TEXT NULL,
    role ENUM('requester', 'provider') NOT NULL COMMENT 'Role of the rater in the exchange',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,

    UNIQUE KEY uk_exchange_rater (exchange_id, rater_id),
    INDEX idx_tenant (tenant_id),
    INDEX idx_rated (rated_id),
    INDEX idx_exchange (exchange_id),
    FOREIGN KEY (tenant_id) REFERENCES tenants(id) ON DELETE CASCADE,
    FOREIGN KEY (exchange_id) REFERENCES exchange_requests(id) ON DELETE CASCADE,
    FOREIGN KEY (rater_id) REFERENCES users(id) ON DELETE CASCADE,
    FOREIGN KEY (rated_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================================
-- 9. CREDIT DONATIONS TABLE
-- Track donations between members or to community fund
-- ============================================================
CREATE TABLE IF NOT EXISTS credit_donations (
    id INT AUTO_INCREMENT PRIMARY KEY,
    tenant_id INT NOT NULL,
    donor_id INT NOT NULL,
    recipient_type ENUM('user', 'community_fund') NOT NULL DEFAULT 'community_fund',
    recipient_id INT NULL COMMENT 'User ID if donating to a user, NULL for community fund',
    amount DECIMAL(10,2) NOT NULL,
    message VARCHAR(500) NULL,
    transaction_id INT NULL COMMENT 'Link to wallet transaction',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,

    INDEX idx_tenant (tenant_id),
    INDEX idx_donor (donor_id),
    INDEX idx_recipient (recipient_type, recipient_id),
    FOREIGN KEY (tenant_id) REFERENCES tenants(id) ON DELETE CASCADE,
    FOREIGN KEY (donor_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================================
-- 10. INSERT DEFAULT TRANSACTION CATEGORIES
-- Idempotent: uses INSERT IGNORE with unique slug constraint
-- These are system categories inserted for tenant_id=0 as templates
-- ============================================================
-- Note: Default categories are created per-tenant via CommunityFundService::ensureDefaultCategories()
-- No seed data here; the service handles tenant-specific creation.

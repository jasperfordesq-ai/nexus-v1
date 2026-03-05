-- ============================================================================
-- Performance Optimization Indexes for backup-7_ Database
-- ============================================================================
-- Created: 2026-01-09
-- Purpose: Reduce query execution time for admin analytics and dashboard queries
-- Special: Adapted for database name with hyphen (backup-7_)
-- ============================================================================

-- Use the correct database (with backticks for hyphen)
USE `backup-7_`;

-- ============================================================================
-- TRANSACTIONS TABLE INDEXES
-- ============================================================================

-- Index for tenant + created_at queries (transaction volume, counts, trends)
CREATE INDEX IF NOT EXISTS idx_transactions_tenant_created
ON transactions(tenant_id, created_at);

-- Index for sender queries (top spenders, user reports)
CREATE INDEX IF NOT EXISTS idx_transactions_sender_tenant
ON transactions(sender_id, tenant_id);

-- Index for receiver queries (top earners, user reports)
CREATE INDEX IF NOT EXISTS idx_transactions_receiver_tenant
ON transactions(receiver_id, tenant_id);

-- Composite index for sender/receiver with created_at (optimizes date-filtered user reports)
CREATE INDEX IF NOT EXISTS idx_transactions_sender_created
ON transactions(sender_id, created_at);

CREATE INDEX IF NOT EXISTS idx_transactions_receiver_created
ON transactions(receiver_id, created_at);

-- Index for amount calculations (averages, sums)
CREATE INDEX IF NOT EXISTS idx_transactions_tenant_amount
ON transactions(tenant_id, amount);

-- ============================================================================
-- USERS TABLE INDEXES
-- ============================================================================

-- Index for tenant + balance queries (highest balances, total circulation)
CREATE INDEX IF NOT EXISTS idx_users_tenant_balance
ON users(tenant_id, balance);

-- Index for new user queries (registration date filters)
CREATE INDEX IF NOT EXISTS idx_users_tenant_created
ON users(tenant_id, created_at);

-- ============================================================================
-- ABUSE_ALERTS TABLE INDEXES
-- ============================================================================

-- Index for pending abuse alert queries
CREATE INDEX IF NOT EXISTS idx_abuse_alerts_tenant_status
ON abuse_alerts(tenant_id, status);

-- ============================================================================
-- ORG_WALLETS TABLE INDEXES
-- ============================================================================

-- Index for organization wallet queries (summary statistics)
CREATE INDEX IF NOT EXISTS idx_org_wallets_tenant_balance
ON org_wallets(tenant_id, balance);

-- ============================================================================
-- ORG_TRANSFER_REQUESTS TABLE INDEXES
-- ============================================================================

-- Index for pending transfer request queries
CREATE INDEX IF NOT EXISTS idx_org_transfer_requests_tenant_status
ON org_transfer_requests(tenant_id, status);

-- Index for organization-specific transfer requests
CREATE INDEX IF NOT EXISTS idx_org_transfer_requests_org_status
ON org_transfer_requests(organization_id, status);

-- ============================================================================
-- GROUP_MEMBERS TABLE INDEXES
-- ============================================================================

-- Index for group member counts (used in admin group listings)
CREATE INDEX IF NOT EXISTS idx_group_members_group_status
ON group_members(group_id, status);

-- ============================================================================
-- GROUPS TABLE INDEXES
-- ============================================================================

-- Index for tenant + featured + created_at (admin group listing with sorting)
CREATE INDEX IF NOT EXISTS idx_groups_tenant_featured_created
ON `groups`(tenant_id, is_featured, created_at);

-- Index for parent group queries (child count)
CREATE INDEX IF NOT EXISTS idx_groups_parent_tenant
ON `groups`(parent_id, tenant_id);

-- ============================================================================
-- ORG_MEMBERS TABLE INDEXES
-- ============================================================================

-- Index for active member counts per organization
CREATE INDEX IF NOT EXISTS idx_org_members_org_status
ON org_members(organization_id, status);

-- ============================================================================
-- VERIFICATION
-- ============================================================================

-- Show what was created
SELECT 'Migration complete! Checking indexes...' as status;

SELECT
    TABLE_NAME as table_name,
    COUNT(DISTINCT INDEX_NAME) as performance_indexes
FROM INFORMATION_SCHEMA.STATISTICS
WHERE TABLE_SCHEMA = 'backup-7_'
  AND INDEX_NAME LIKE 'idx_%'
GROUP BY TABLE_NAME
ORDER BY performance_indexes DESC;

SELECT
    COUNT(DISTINCT INDEX_NAME) as total_performance_indexes,
    CASE
        WHEN COUNT(DISTINCT INDEX_NAME) >= 15 THEN '✓ EXCELLENT - Migration successful!'
        ELSE '⚠ Some indexes may have failed'
    END as result
FROM INFORMATION_SCHEMA.STATISTICS
WHERE TABLE_SCHEMA = 'backup-7_'
  AND INDEX_NAME LIKE 'idx_%';

-- ============================================================================
-- EXPECTED RESULT: 16 indexes total
-- ============================================================================

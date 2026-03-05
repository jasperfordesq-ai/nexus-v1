-- =====================================================
-- FEDERATION PHASE 5: FEDERATED GROUPS
-- =====================================================
-- Run this AFTER FEDERATION_PHASE5_EVENTS.sql
-- Adds columns to group_members for federated membership
-- =====================================================
-- NOTE: Run each statement separately in phpMyAdmin
-- If a column already exists, you'll get an error - that's OK, just continue
-- =====================================================

-- Step 1: Add is_federated column to group_members
-- (If error "Duplicate column name" appears, column already exists - continue)
ALTER TABLE group_members ADD COLUMN is_federated TINYINT(1) NOT NULL DEFAULT 0;

-- Step 2: Add source_tenant_id column to group_members
-- (If error "Duplicate column name" appears, column already exists - continue)
ALTER TABLE group_members ADD COLUMN source_tenant_id INT DEFAULT NULL;

-- Step 3: Add index for federated lookups (ignore error if index exists)
ALTER TABLE group_members ADD INDEX idx_federated_groups (is_federated, source_tenant_id);

-- =====================================================
-- VERIFICATION: Run this to check columns were added
-- =====================================================
-- DESCRIBE group_members;
-- =====================================================

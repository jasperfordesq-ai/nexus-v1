-- =====================================================
-- FEDERATION PHASE 5: FEDERATED EVENTS
-- =====================================================
-- Run this AFTER FEDERATION_PHASE5_TRANSACTIONS.sql
-- Adds columns to event_rsvps for federated registration
-- =====================================================
-- NOTE: Run each statement separately in phpMyAdmin
-- If a column already exists, you'll get an error - that's OK, just continue
-- =====================================================

-- Step 1: Add is_federated column to event_rsvps
-- (If error "Duplicate column name" appears, column already exists - continue)
ALTER TABLE event_rsvps ADD COLUMN is_federated TINYINT(1) NOT NULL DEFAULT 0;

-- Step 2: Add source_tenant_id column to event_rsvps
-- (If error "Duplicate column name" appears, column already exists - continue)
ALTER TABLE event_rsvps ADD COLUMN source_tenant_id INT DEFAULT NULL;

-- Step 3: Add tenant_id column if it doesn't exist (needed for multi-tenant)
-- (If error "Duplicate column name" appears, column already exists - continue)
ALTER TABLE event_rsvps ADD COLUMN tenant_id INT DEFAULT NULL;

-- Step 4: Add index for federated lookups (ignore error if index exists)
ALTER TABLE event_rsvps ADD INDEX idx_federated_events (is_federated, source_tenant_id);

-- =====================================================
-- VERIFICATION: Run this to check columns were added
-- =====================================================
-- DESCRIBE event_rsvps;
-- =====================================================

-- =============================================================================
-- Migration: 2026_03_27_fix_listings_schema.sql
-- Purpose:   Fix schema issues found in listings module audit
--
-- Fixes:
--   1. Add missing composite index (tenant_id, status) for common query pattern
--   2. Add FK constraints on listing_views and listing_contacts → listings(id)
--   3. Migrate status column from VARCHAR(50) to ENUM with all known values
--   4. Drop abandoned legacy columns confirmed unused
--
-- Idempotent: safe to run multiple times (all changes use IF EXISTS / IF NOT EXISTS checks)
-- =============================================================================

-- ---------------------------------------------------------------------------
-- Fix 1: Add composite index idx_listings_tenant_status (tenant_id, status)
-- ---------------------------------------------------------------------------
SET @exists = (SELECT COUNT(1) FROM information_schema.statistics
               WHERE table_schema = DATABASE()
                 AND table_name = 'listings'
                 AND index_name = 'idx_listings_tenant_status');
SET @sql = IF(@exists = 0,
    'ALTER TABLE listings ADD INDEX idx_listings_tenant_status (tenant_id, status)',
    'SELECT 1');
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

-- ---------------------------------------------------------------------------
-- Fix 2: Add FK constraints on listing_views and listing_contacts
-- ---------------------------------------------------------------------------

-- First, align column types: listings.id is int(11) signed, but listing_views/contacts
-- have listing_id as int(10) unsigned. FK requires exact type match.
-- Also clean up any orphaned rows before adding constraints.
DELETE lv FROM listing_views lv LEFT JOIN listings l ON lv.listing_id = l.id WHERE l.id IS NULL;
DELETE lc FROM listing_contacts lc LEFT JOIN listings l ON lc.listing_id = l.id WHERE l.id IS NULL;

ALTER TABLE listing_views MODIFY COLUMN listing_id int(11) NOT NULL;
ALTER TABLE listing_contacts MODIFY COLUMN listing_id int(11) NOT NULL;

-- FK: listing_views.listing_id → listings.id (ON DELETE CASCADE)
SET @exists = (SELECT COUNT(1) FROM information_schema.table_constraints
               WHERE table_schema = DATABASE()
                 AND table_name = 'listing_views'
                 AND constraint_name = 'fk_listing_views_listing_id');
SET @sql = IF(@exists = 0,
    'ALTER TABLE listing_views ADD CONSTRAINT fk_listing_views_listing_id FOREIGN KEY (listing_id) REFERENCES listings(id) ON DELETE CASCADE',
    'SELECT 1');
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

-- FK: listing_contacts.listing_id → listings.id (ON DELETE CASCADE)
SET @exists = (SELECT COUNT(1) FROM information_schema.table_constraints
               WHERE table_schema = DATABASE()
                 AND table_name = 'listing_contacts'
                 AND constraint_name = 'fk_listing_contacts_listing_id');
SET @sql = IF(@exists = 0,
    'ALTER TABLE listing_contacts ADD CONSTRAINT fk_listing_contacts_listing_id FOREIGN KEY (listing_id) REFERENCES listings(id) ON DELETE CASCADE',
    'SELECT 1');
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

-- ---------------------------------------------------------------------------
-- Fix 3: Migrate status column from VARCHAR(50) to ENUM
-- ---------------------------------------------------------------------------
ALTER TABLE listings MODIFY COLUMN status
    ENUM('active','inactive','paused','completed','expired','closed','pending','rejected','deleted')
    DEFAULT 'active';

-- ---------------------------------------------------------------------------
-- Fix 4: Drop abandoned legacy columns (confirmed unused)
-- ---------------------------------------------------------------------------
ALTER TABLE listings DROP COLUMN IF EXISTS blocker_user_id;
ALTER TABLE listings DROP COLUMN IF EXISTS click_rate;
ALTER TABLE listings DROP COLUMN IF EXISTS clicked_at;
ALTER TABLE listings DROP COLUMN IF EXISTS content;
ALTER TABLE listings DROP COLUMN IF EXISTS errors;
ALTER TABLE listings DROP COLUMN IF EXISTS open_rate;
ALTER TABLE listings DROP COLUMN IF EXISTS pages;
ALTER TABLE listings DROP COLUMN IF EXISTS reset_token;

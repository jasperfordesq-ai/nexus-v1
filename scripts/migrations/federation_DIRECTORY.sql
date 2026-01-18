-- ============================================================
-- FEDERATION DIRECTORY - DATABASE MIGRATIONS
-- ============================================================
-- Enhancement 2: Federation Directory
-- Allows admins to discover and request partnerships with other timebanks
-- ============================================================

-- TENANTS TABLE - Directory listing columns
-- Run each statement ONE AT A TIME
-- If you get "Duplicate column" error, skip that one (already exists)

-- Public description shown in federation directory
ALTER TABLE tenants ADD COLUMN federation_public_description TEXT NULL;

-- Categories for directory filtering (JSON array)
ALTER TABLE tenants ADD COLUMN federation_categories VARCHAR(500) NULL;

-- Show member count in directory?
ALTER TABLE tenants ADD COLUMN federation_member_count_public TINYINT(1) NOT NULL DEFAULT 0;

-- Geographic region for filtering
ALTER TABLE tenants ADD COLUMN federation_region VARCHAR(100) NULL;

-- Is this tenant discoverable in the federation directory?
ALTER TABLE tenants ADD COLUMN federation_discoverable TINYINT(1) NOT NULL DEFAULT 0;

-- ============================================================
-- VERIFICATION QUERY
-- ============================================================
-- Run this to verify columns were added:
-- SHOW COLUMNS FROM tenants WHERE Field LIKE 'federation_%';

-- ============================================================
-- DONE! Directory columns added.
-- ============================================================

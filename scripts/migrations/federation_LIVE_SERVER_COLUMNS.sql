-- ============================================================
-- FEDERATION COLUMN ADDITIONS - FOR LIVE SERVER
-- ============================================================
-- Run AFTER federation_LIVE_SERVER.sql
-- Run each statement ONE AT A TIME
-- If you get "Duplicate column" error, skip that one (already exists)
-- ============================================================

-- USERS TABLE
ALTER TABLE users ADD COLUMN federation_optin TINYINT(1) NOT NULL DEFAULT 0;
ALTER TABLE users ADD COLUMN federated_profile_visible TINYINT(1) NOT NULL DEFAULT 0;
ALTER TABLE users ADD COLUMN federation_notifications_enabled TINYINT(1) NOT NULL DEFAULT 1;

-- TENANTS TABLE
ALTER TABLE tenants ADD COLUMN federation_contact_email VARCHAR(255) NULL;
ALTER TABLE tenants ADD COLUMN federation_contact_name VARCHAR(200) NULL;

-- GROUPS TABLE
ALTER TABLE `groups` ADD COLUMN allow_federated_members TINYINT(1) NOT NULL DEFAULT 0;
ALTER TABLE `groups` ADD COLUMN federated_visibility ENUM('none', 'listed', 'joinable') NOT NULL DEFAULT 'none';

-- LISTINGS TABLE
ALTER TABLE listings ADD COLUMN federated_visibility ENUM('none', 'listed', 'bookable') NOT NULL DEFAULT 'none';
ALTER TABLE listings ADD COLUMN service_type ENUM('physical_only', 'remote_only', 'hybrid', 'location_dependent') NOT NULL DEFAULT 'physical_only';

-- EVENTS TABLE
ALTER TABLE events ADD COLUMN federated_visibility ENUM('none', 'listed', 'joinable') NOT NULL DEFAULT 'none';
ALTER TABLE events ADD COLUMN allow_remote_attendance TINYINT(1) NOT NULL DEFAULT 0;

-- ============================================================
-- DONE! All columns added.
-- ============================================================

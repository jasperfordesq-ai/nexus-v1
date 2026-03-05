-- =====================================================
-- FEDERATION PHASE 5: EMAIL NOTIFICATIONS
-- =====================================================
-- Run this AFTER FEDERATION_PHASE5_GROUPS.sql
-- Adds email notification setting to federation_user_settings
-- =====================================================
-- NOTE: Run each statement separately in phpMyAdmin
-- If a column already exists, you'll get an error - that's OK, just continue
-- =====================================================

-- Add email notification preference to federation_user_settings
-- (If error "Duplicate column name" appears, column already exists - continue)
ALTER TABLE federation_user_settings ADD COLUMN email_notifications TINYINT(1) NOT NULL DEFAULT 1;

-- =====================================================
-- VERIFICATION: Run this to check column was added
-- =====================================================
-- DESCRIBE federation_user_settings;
-- =====================================================

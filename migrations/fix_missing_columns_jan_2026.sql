-- ============================================================================
-- FIX MISSING COLUMNS - January 2026
-- ============================================================================
-- This migration adds missing columns identified from Apache error logs:
-- 1. groups.is_active - Missing column causing query failures
-- 2. users.email_verified_at - Missing column in CommunityRank queries
--
-- Created: 2026-01-11
-- Related Errors:
--   - SQLSTATE[42S22]: Column not found: 1054 Unknown column 'is_active'
--   - SQLSTATE[42S22]: Column not found: 1054 Unknown column 'u.email_verified_at'
-- ============================================================================

-- ============================================================================
-- 1. ADD is_active TO groups TABLE
-- ============================================================================
-- Adds an is_active column to track whether a group is active/enabled
-- This column is being queried but doesn't exist in the current schema

ALTER TABLE `groups`
ADD COLUMN IF NOT EXISTS `is_active` TINYINT(1) NOT NULL DEFAULT 1
COMMENT 'Whether this group is active (1) or inactive (0)'
AFTER `is_featured`;

-- Create index for performance on queries filtering by is_active
CREATE INDEX IF NOT EXISTS `idx_tenant_active` ON `groups` (`tenant_id`, `is_active`);

-- Set all existing groups to active by default
UPDATE `groups` SET `is_active` = 1 WHERE `is_active` IS NULL OR `is_active` = 0;

SELECT 'GROUPS TABLE: Added is_active column and set all existing groups to active' AS result;

-- ============================================================================
-- 2. ADD email_verified_at TO users TABLE
-- ============================================================================
-- Adds email_verified_at column to track when a user verified their email
-- This is standard in modern authentication systems and required by CommunityRank

ALTER TABLE `users`
ADD COLUMN IF NOT EXISTS `email_verified_at` TIMESTAMP NULL DEFAULT NULL
COMMENT 'Timestamp when user verified their email address'
AFTER `email`;

-- Create index for performance on queries filtering verified users
CREATE INDEX IF NOT EXISTS `idx_email_verified` ON `users` (`email_verified_at`);

-- OPTIONAL: Mark all existing users as verified (if you trust existing data)
-- Uncomment the line below if you want to consider all existing users as verified:
-- UPDATE `users` SET `email_verified_at` = `created_at` WHERE `email_verified_at` IS NULL;

SELECT 'USERS TABLE: Added email_verified_at column (existing users set to NULL - unverified)' AS result;

-- ============================================================================
-- VERIFICATION QUERIES (Optional - run separately after migration)
-- ============================================================================
-- These are separate verification queries you can run AFTER the migration
-- Copy and paste these in a new query window AFTER running the migration above

/*
-- Verify columns exist (run in your application database, not information_schema)
SHOW COLUMNS FROM `groups` LIKE 'is_active';
SHOW COLUMNS FROM `users` LIKE 'email_verified_at';

-- Show counts
SELECT 'Active Groups' AS metric, COUNT(*) AS count FROM `groups` WHERE `is_active` = 1;
SELECT 'Inactive Groups' AS metric, COUNT(*) AS count FROM `groups` WHERE `is_active` = 0;
SELECT 'Verified Users' AS metric, COUNT(*) AS count FROM `users` WHERE `email_verified_at` IS NOT NULL;
SELECT 'Unverified Users' AS metric, COUNT(*) AS count FROM `users` WHERE `email_verified_at` IS NULL;
*/

-- ============================================================================
-- MIGRATION COMPLETE
-- ============================================================================
-- Summary:
-- ✓ Added is_active column to groups table
-- ✓ Added email_verified_at column to users table
-- ✓ Created performance indexes
-- ✓ Set default values for existing data
--
-- Next Steps:
-- 1. Update application code to populate email_verified_at during registration
-- 2. Implement email verification workflow if not already present
-- 3. Use is_active flag to soft-delete or disable groups as needed
--
-- Note: You still need to fix TenantContext::getDomain() method
--       See separate code fix required in src/Core/TenantContext.php
-- ============================================================================

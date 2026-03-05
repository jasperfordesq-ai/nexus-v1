-- ============================================================================
-- FIX MISSING COLUMNS - SIMPLE VERSION
-- ============================================================================
-- Run this in your application database (NOT information_schema)
-- Make sure you select your database first!
-- ============================================================================

-- Add is_active column to groups table
ALTER TABLE `groups`
ADD COLUMN `is_active` TINYINT(1) NOT NULL DEFAULT 1
COMMENT 'Whether this group is active (1) or inactive (0)';

-- Create index for performance
ALTER TABLE `groups`
ADD INDEX `idx_tenant_active` (`tenant_id`, `is_active`);

-- Set all existing groups to active
UPDATE `groups` SET `is_active` = 1;

-- Add email_verified_at column to users table
ALTER TABLE `users`
ADD COLUMN `email_verified_at` TIMESTAMP NULL DEFAULT NULL
COMMENT 'Timestamp when user verified their email address';

-- Create index for performance
ALTER TABLE `users`
ADD INDEX `idx_email_verified` (`email_verified_at`);

-- Done! Now test by running:
-- SHOW COLUMNS FROM groups LIKE 'is_active';
-- SHOW COLUMNS FROM users LIKE 'email_verified_at';

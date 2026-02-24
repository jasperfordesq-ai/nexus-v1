-- ============================================================================
-- FIX email_verification_tokens TABLE — ADD TENANT SCOPING
-- ============================================================================
-- Migration: Add tenant_id column to email_verification_tokens for multi-tenant isolation
-- Purpose: Prevent cross-tenant token hijacking (CRITICAL security fix)
-- Date: 2026-02-24
--
-- Bug: Tokens were stored without tenant_id, allowing any token to verify
--      any user across any tenant. This adds tenant scoping to all queries.
-- ============================================================================

-- Step 1: Add tenant_id column (nullable first for existing rows)
ALTER TABLE `email_verification_tokens`
    ADD COLUMN IF NOT EXISTS `tenant_id` INT(11) NULL AFTER `user_id`;

-- Step 2: Backfill tenant_id from the users table for any existing tokens
UPDATE `email_verification_tokens` evt
    JOIN `users` u ON evt.user_id = u.id
SET evt.tenant_id = u.tenant_id
WHERE evt.tenant_id IS NULL;

-- Step 3: Delete any orphaned tokens (user doesn't exist)
DELETE FROM `email_verification_tokens`
WHERE tenant_id IS NULL;

-- Step 4: Make tenant_id NOT NULL now that all rows have values
ALTER TABLE `email_verification_tokens`
    MODIFY COLUMN `tenant_id` INT(11) NOT NULL;

-- Step 5: Add index for tenant-scoped lookups
ALTER TABLE `email_verification_tokens`
    ADD INDEX IF NOT EXISTS `idx_tenant_id` (`tenant_id`),
    ADD INDEX IF NOT EXISTS `idx_tenant_user` (`tenant_id`, `user_id`);

SELECT 'EMAIL VERIFICATION TOKENS: tenant_id column added and backfilled' AS result;

-- ============================================================================
-- MIGRATION COMPLETE
-- ============================================================================
-- Summary:
-- ✓ Added tenant_id column to email_verification_tokens
-- ✓ Backfilled existing rows from users table
-- ✓ Made tenant_id NOT NULL
-- ✓ Added indexes for performance
-- ============================================================================

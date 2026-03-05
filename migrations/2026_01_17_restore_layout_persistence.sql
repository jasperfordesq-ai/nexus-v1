-- ============================================================================
-- RESTORE LAYOUT PERSISTENCE - Fix Race Conditions
-- ============================================================================
-- This migration restores database-level layout persistence to fix the
-- 6 race conditions identified in the dual session key system.
--
-- Changes:
-- 1. Add preferred_layout column back to users table
-- 2. Create index for efficient lookups
-- ============================================================================

-- Add preferred_layout column to users table (if it doesn't exist)
-- Default is 'modern' for all existing users
ALTER TABLE users
ADD COLUMN IF NOT EXISTS preferred_layout VARCHAR(20) DEFAULT 'modern'
AFTER avatar_url;

-- Add index for efficient lookups (optional but good for performance)
-- Only create if it doesn't exist
CREATE INDEX IF NOT EXISTS idx_users_preferred_layout ON users(preferred_layout);

-- Update any NULL values to 'modern' (safety net)
UPDATE users SET preferred_layout = 'modern' WHERE preferred_layout IS NULL;

-- Verify the column was added (for manual verification)
-- SELECT id, email, preferred_layout FROM users LIMIT 5;

-- ============================================================================
-- MIGRATION COMPLETE
-- The preferred_layout column is now available for persistent storage
-- Valid values: 'modern', 'civicone'
-- ============================================================================

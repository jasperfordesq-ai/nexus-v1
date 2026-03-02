-- Add draft support to challenge_ideas table
-- Date: 2026-03-02
-- Description: Adds updated_at column for tracking draft edits.
-- The status column already exists as VARCHAR and supports 'draft' value.

-- 1. Add updated_at column for tracking when drafts are last edited
ALTER TABLE challenge_ideas
ADD COLUMN IF NOT EXISTS updated_at TIMESTAMP NULL DEFAULT NULL AFTER created_at;

-- 2. Add index on status for efficient filtering of drafts
ALTER TABLE challenge_ideas
ADD INDEX IF NOT EXISTS idx_status (status);

-- 3. Add composite index for efficient draft queries (user + status + challenge)
ALTER TABLE challenge_ideas
ADD INDEX IF NOT EXISTS idx_user_status (user_id, status, challenge_id);

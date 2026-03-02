-- Add draft support to challenge_ideas table
-- Date: 2026-03-02
-- Description: Adds 'draft' to status ENUM, adds updated_at column, and adds indexes.
-- Run as root/admin user (ALTER TABLE requires ALTER privilege).

-- 1. Add 'draft' to the status ENUM and keep default as 'submitted'
ALTER TABLE challenge_ideas
MODIFY COLUMN status ENUM('draft','submitted','shortlisted','winner','withdrawn') NOT NULL DEFAULT 'submitted';

-- 2. Add updated_at column for tracking when drafts are last edited
ALTER TABLE challenge_ideas
ADD COLUMN IF NOT EXISTS updated_at TIMESTAMP NULL DEFAULT NULL AFTER created_at;

-- 3. Add index on status for efficient filtering of drafts
ALTER TABLE challenge_ideas
ADD INDEX idx_status (status);

-- 4. Add composite index for efficient draft queries (user + status + challenge)
ALTER TABLE challenge_ideas
ADD INDEX idx_user_status (user_id, status, challenge_id);

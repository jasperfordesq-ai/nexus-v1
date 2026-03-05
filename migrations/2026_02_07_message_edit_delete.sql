-- Migration: Add edit and delete tracking for messages
-- Date: 2026-02-07
-- Description: Adds columns for soft delete and edit tracking

-- Add is_edited column
ALTER TABLE messages
    ADD COLUMN IF NOT EXISTS is_edited TINYINT(1) DEFAULT 0 COMMENT 'Whether message was edited';

-- Add edited_at column
ALTER TABLE messages
    ADD COLUMN IF NOT EXISTS edited_at TIMESTAMP NULL DEFAULT NULL COMMENT 'When message was edited';

-- Add is_deleted column for soft delete
ALTER TABLE messages
    ADD COLUMN IF NOT EXISTS is_deleted TINYINT(1) DEFAULT 0 COMMENT 'Whether message was deleted';

-- Add deleted_at column
ALTER TABLE messages
    ADD COLUMN IF NOT EXISTS deleted_at TIMESTAMP NULL DEFAULT NULL COMMENT 'When message was deleted';

-- Add archived_by columns for per-user archiving (if not already added)
ALTER TABLE messages
    ADD COLUMN IF NOT EXISTS archived_by_sender TIMESTAMP NULL DEFAULT NULL COMMENT 'When sender archived';

ALTER TABLE messages
    ADD COLUMN IF NOT EXISTS archived_by_receiver TIMESTAMP NULL DEFAULT NULL COMMENT 'When receiver archived';

-- Index for filtering deleted messages
CREATE INDEX IF NOT EXISTS idx_messages_deleted ON messages(is_deleted);

-- Migration: Add soft delete support for messages
-- Date: 2026-02-07
-- Description: Adds archived_at columns to messages table to support soft deletion
--              of conversations per-user without losing message history.
--
-- Design:
--   - archived_by_sender: timestamp when sender archived the conversation (NULL = not archived)
--   - archived_by_receiver: timestamp when receiver archived the conversation (NULL = not archived)
--   - Each user can independently archive a conversation
--   - Messages are only truly hidden when the viewing user has archived
--   - Messages can be unarchived by clearing the timestamp
--
-- This replaces the previous hard-delete behavior which caused data loss.
--
-- NOTE: Run this migration manually if it fails. The columns may already exist.
--       Check with: DESCRIBE messages;

-- Add archived columns (per-user archival)
-- These will error if columns already exist - that's OK, just means migration already ran
ALTER TABLE messages
    ADD COLUMN archived_by_sender DATETIME DEFAULT NULL COMMENT 'When sender archived this conversation',
    ADD COLUMN archived_by_receiver DATETIME DEFAULT NULL COMMENT 'When receiver archived this conversation';

-- Add indexes for efficient filtering of archived messages
CREATE INDEX idx_messages_sender_archived ON messages(sender_id, archived_by_sender);
CREATE INDEX idx_messages_receiver_archived ON messages(receiver_id, archived_by_receiver);

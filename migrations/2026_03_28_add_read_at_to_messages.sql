-- Add read_at column to messages table.
-- Tracks when a message was read (used by markAsRead in MessageService).
-- The column was missing from the original schema — is_read existed but read_at did not.

ALTER TABLE messages
    ADD COLUMN IF NOT EXISTS read_at DATETIME NULL DEFAULT NULL AFTER is_read;

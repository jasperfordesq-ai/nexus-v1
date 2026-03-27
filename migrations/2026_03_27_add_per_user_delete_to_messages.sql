-- Add per-user soft-delete columns to messages table.
-- is_deleted_sender = 1  → sender has hidden this message from their own view (other party unaffected).
-- is_deleted_receiver = 1 → receiver has hidden this message from their own view (other party unaffected).
-- These are distinct from is_deleted (global soft-delete that shows placeholder to both parties).

ALTER TABLE messages
    ADD COLUMN IF NOT EXISTS is_deleted_sender   TINYINT(1) NOT NULL DEFAULT 0 AFTER is_deleted,
    ADD COLUMN IF NOT EXISTS is_deleted_receiver TINYINT(1) NOT NULL DEFAULT 0 AFTER is_deleted_sender;

-- Index to speed up the getMessages() filter queries:
--   NOT (sender_id = ? AND is_deleted_sender = 1)
--   NOT (receiver_id = ? AND is_deleted_receiver = 1)
ALTER TABLE messages
    ADD INDEX IF NOT EXISTS idx_messages_is_deleted_sender   (tenant_id, sender_id,   is_deleted_sender),
    ADD INDEX IF NOT EXISTS idx_messages_is_deleted_receiver (tenant_id, receiver_id, is_deleted_receiver);

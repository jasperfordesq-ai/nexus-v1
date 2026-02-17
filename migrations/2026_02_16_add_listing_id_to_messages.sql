-- Add listing_id column to messages table to track listing context
-- This enables showing which listing a conversation is about

ALTER TABLE messages
ADD COLUMN IF NOT EXISTS listing_id INT(11) NULL DEFAULT NULL,
ADD INDEX IF NOT EXISTS idx_messages_listing (listing_id);

-- Add foreign key constraint (soft reference - no cascade delete)
-- Messages should remain even if listing is deleted
ALTER TABLE messages
ADD CONSTRAINT fk_messages_listing
FOREIGN KEY IF NOT EXISTS (listing_id)
REFERENCES listings(id)
ON DELETE SET NULL;

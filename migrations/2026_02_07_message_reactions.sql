-- Migration: Add reactions support for messages
-- Date: 2026-02-07
-- Description: Adds a reactions JSON column to store emoji reactions on messages.
--
-- Design:
--   - reactions: JSON object storing { "emoji": count, "_users": { "userId_emoji": true } }
--   - The _users object tracks which users have reacted with which emojis
--   - This allows for toggle behavior (add/remove) and prevents duplicate reactions
--
-- Example stored value:
--   {"👍": 2, "❤️": 1, "_users": {"123_👍": true, "456_👍": true, "123_❤️": true}}

-- Add reactions column
ALTER TABLE messages
    ADD COLUMN reactions JSON DEFAULT NULL COMMENT 'JSON object of emoji reactions';

-- Note: No index needed as we don't query by reactions content

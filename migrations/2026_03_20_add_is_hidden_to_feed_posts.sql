-- Add is_hidden column to feed_posts for admin moderation
-- This enables global post hiding by admins (distinct from per-user feed_hidden table)
-- Also adds is_hidden to feed_activity for consistency

ALTER TABLE feed_posts ADD COLUMN IF NOT EXISTS is_hidden TINYINT(1) NOT NULL DEFAULT 0;
ALTER TABLE feed_posts ADD INDEX IF NOT EXISTS idx_is_hidden (tenant_id, is_hidden);

ALTER TABLE feed_activity ADD COLUMN IF NOT EXISTS is_hidden TINYINT(1) NOT NULL DEFAULT 0;

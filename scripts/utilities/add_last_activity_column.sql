-- =========================================================
-- Migration: Add last_activity column to users table
-- Purpose: Optimize FeedRankingService Creator Vitality calculations
-- =========================================================

-- Add last_activity column if it doesn't exist
-- This stores the timestamp of the user's most recent activity
ALTER TABLE users ADD COLUMN IF NOT EXISTS last_activity DATETIME NULL DEFAULT NULL AFTER created_at;

-- Create index for faster lookups
CREATE INDEX IF NOT EXISTS idx_users_last_activity ON users(last_activity);

-- Backfill existing data from activity_log (if table exists)
-- This updates users who have login records
UPDATE users u
SET last_activity = (
    SELECT MAX(created_at)
    FROM activity_log al
    WHERE al.user_id = u.id
    AND al.action IN ('login', 'post_created', 'comment_added', 'like_added')
)
WHERE u.last_activity IS NULL;

-- For users with no activity_log entries, use their most recent post
UPDATE users u
SET last_activity = (
    SELECT MAX(created_at)
    FROM feed_posts fp
    WHERE fp.user_id = u.id
)
WHERE u.last_activity IS NULL;

-- For users with no posts, fall back to registration date
UPDATE users
SET last_activity = created_at
WHERE last_activity IS NULL;

-- =========================================================
-- TRIGGER: Auto-update last_activity on login
-- Note: You may need to adapt this for your login handler
-- =========================================================

-- For MySQL 8.0+, create a trigger (optional - you can update in PHP instead)
-- DELIMITER //
-- CREATE TRIGGER update_last_activity_on_login
-- AFTER INSERT ON activity_log
-- FOR EACH ROW
-- BEGIN
--     IF NEW.action = 'login' THEN
--         UPDATE users SET last_activity = NOW() WHERE id = NEW.user_id;
--     END IF;
-- END//
-- DELIMITER ;

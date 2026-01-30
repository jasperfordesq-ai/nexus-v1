-- =============================================================================
-- CLEAR ALL USERS EXCEPT SPECIFIED EMAILS
-- =============================================================================
-- This script removes all users and their related data EXCEPT for:
-- - jasper.ford.esq@gmail.com
-- - jasper.ford@outlook.ie
--
-- WARNING: This is a DESTRUCTIVE operation. Backup your database first!
-- Run: mysqldump -u root truth_ > backup_before_clear.sql
-- =============================================================================

SET FOREIGN_KEY_CHECKS = 0;

-- Store the IDs of users to KEEP in a temp table to avoid subquery issues
CREATE TEMPORARY TABLE IF NOT EXISTS keep_user_ids AS
SELECT id FROM users WHERE email IN ('jasper.ford.esq@gmail.com', 'jasper.ford@outlook.ie');

-- Get the IDs to keep (for reference in queries)
SELECT id, email, first_name, last_name FROM users
WHERE email IN ('jasper.ford.esq@gmail.com', 'jasper.ford@outlook.ie');

-- =============================================================================
-- DELETE USER-RELATED DATA
-- =============================================================================

-- Messages (sender_id, receiver_id)
DELETE FROM messages
WHERE sender_id NOT IN (SELECT id FROM keep_user_ids)
   OR receiver_id NOT IN (SELECT id FROM keep_user_ids);

-- Transactions (sender_id, receiver_id)
DELETE FROM transactions
WHERE sender_id NOT IN (SELECT id FROM keep_user_ids)
   OR receiver_id NOT IN (SELECT id FROM keep_user_ids);

-- Reviews (reviewer_id, receiver_id)
DELETE FROM reviews
WHERE reviewer_id NOT IN (SELECT id FROM keep_user_ids)
   OR receiver_id NOT IN (SELECT id FROM keep_user_ids);

-- Connections (requester_id, receiver_id)
DELETE FROM connections
WHERE requester_id NOT IN (SELECT id FROM keep_user_ids)
   OR receiver_id NOT IN (SELECT id FROM keep_user_ids);

-- User blocks
DELETE FROM user_blocks
WHERE blocker_user_id NOT IN (SELECT id FROM keep_user_ids)
   OR blocked_user_id NOT IN (SELECT id FROM keep_user_ids);

-- Listings
DELETE FROM listings
WHERE user_id NOT IN (SELECT id FROM keep_user_ids);

-- Feed posts
DELETE FROM feed_posts
WHERE user_id NOT IN (SELECT id FROM keep_user_ids);

-- Likes
DELETE FROM likes
WHERE user_id NOT IN (SELECT id FROM keep_user_ids);

-- Comments
DELETE FROM comments
WHERE user_id NOT IN (SELECT id FROM keep_user_ids);

-- Events
DELETE FROM events
WHERE user_id NOT IN (SELECT id FROM keep_user_ids);

-- Event RSVPs
DELETE FROM event_rsvps
WHERE user_id NOT IN (SELECT id FROM keep_user_ids);

-- Goals
DELETE FROM goals
WHERE user_id NOT IN (SELECT id FROM keep_user_ids);

-- Polls
DELETE FROM polls
WHERE user_id NOT IN (SELECT id FROM keep_user_ids);

-- Notifications
DELETE FROM notifications
WHERE user_id NOT IN (SELECT id FROM keep_user_ids);

-- Group members
DELETE FROM group_members
WHERE user_id NOT IN (SELECT id FROM keep_user_ids);

-- Group discussions
DELETE FROM group_discussions
WHERE user_id NOT IN (SELECT id FROM keep_user_ids);

-- Group posts
DELETE FROM group_posts
WHERE user_id NOT IN (SELECT id FROM keep_user_ids);

-- Groups (owner_id) - temporarily drop triggers to avoid recursive update issues
DROP TRIGGER IF EXISTS update_has_children_on_delete;
DROP TRIGGER IF EXISTS update_has_children_on_insert;
DROP TRIGGER IF EXISTS update_has_children_on_update;

DELETE FROM groups WHERE owner_id NOT IN (260, 268);

-- Recreate triggers
DELIMITER //

CREATE TRIGGER update_has_children_on_insert AFTER INSERT ON `groups`
FOR EACH ROW
BEGIN
    IF NEW.parent_id IS NOT NULL AND NEW.parent_id > 0 THEN
        UPDATE `groups`
        SET has_children = TRUE
        WHERE id = NEW.parent_id;
    END IF;
END//

CREATE TRIGGER update_has_children_on_update AFTER UPDATE ON `groups`
FOR EACH ROW
BEGIN
    IF OLD.parent_id != NEW.parent_id OR (OLD.parent_id IS NULL AND NEW.parent_id IS NOT NULL) OR (OLD.parent_id IS NOT NULL AND NEW.parent_id IS NULL) THEN
        IF OLD.parent_id IS NOT NULL AND OLD.parent_id > 0 THEN
            UPDATE `groups`
            SET has_children = EXISTS (
                SELECT 1 FROM `groups` child
                WHERE child.parent_id = OLD.parent_id
            )
            WHERE id = OLD.parent_id;
        END IF;

        IF NEW.parent_id IS NOT NULL AND NEW.parent_id > 0 THEN
            UPDATE `groups`
            SET has_children = TRUE
            WHERE id = NEW.parent_id;
        END IF;
    END IF;
END//

CREATE TRIGGER update_has_children_on_delete AFTER DELETE ON `groups`
FOR EACH ROW
BEGIN
    IF OLD.parent_id IS NOT NULL AND OLD.parent_id > 0 THEN
        UPDATE `groups`
        SET has_children = EXISTS (
            SELECT 1 FROM `groups` child
            WHERE child.parent_id = OLD.parent_id
        )
        WHERE id = OLD.parent_id;
    END IF;
END//

DELIMITER ;

-- =============================================================================
-- GAMIFICATION DATA
-- =============================================================================

DELETE FROM user_badges
WHERE user_id NOT IN (SELECT id FROM keep_user_ids);

DELETE FROM nexus_score_cache
WHERE user_id NOT IN (SELECT id FROM keep_user_ids);

DELETE FROM nexus_score_history
WHERE user_id NOT IN (SELECT id FROM keep_user_ids);

DELETE FROM nexus_score_milestones
WHERE user_id NOT IN (SELECT id FROM keep_user_ids);

DELETE FROM user_xp_log
WHERE user_id NOT IN (SELECT id FROM keep_user_ids);

DELETE FROM user_points_log
WHERE user_id NOT IN (SELECT id FROM keep_user_ids);

DELETE FROM leaderboard_cache
WHERE user_id NOT IN (SELECT id FROM keep_user_ids);

DELETE FROM weekly_rank_snapshots
WHERE user_id NOT IN (SELECT id FROM keep_user_ids);

-- =============================================================================
-- AI DATA
-- =============================================================================

DELETE FROM ai_conversations
WHERE user_id NOT IN (SELECT id FROM keep_user_ids);

DELETE FROM ai_messages
WHERE user_id NOT IN (SELECT id FROM keep_user_ids);

-- =============================================================================
-- ADMIN/AUDIT DATA (SET NULL references to preserve audit trail)
-- =============================================================================

-- Update admin_actions to preserve audit trail but remove user reference
UPDATE admin_actions
SET target_user_id = NULL
WHERE target_user_id NOT IN (SELECT id FROM keep_user_ids);

-- Delete admin actions BY users being deleted
DELETE FROM admin_actions
WHERE admin_id NOT IN (SELECT id FROM keep_user_ids);

-- =============================================================================
-- FINALLY: DELETE THE USERS
-- =============================================================================

DELETE FROM users
WHERE email NOT IN ('jasper.ford.esq@gmail.com', 'jasper.ford@outlook.ie');

-- Cleanup temp table
DROP TEMPORARY TABLE IF EXISTS keep_user_ids;

SET FOREIGN_KEY_CHECKS = 1;

-- =============================================================================
-- VERIFICATION
-- =============================================================================

SELECT 'Remaining users:' AS status;
SELECT id, email, first_name, last_name, role FROM users;

SELECT 'Cleanup complete!' AS status;

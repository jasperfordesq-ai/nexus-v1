-- ============================================================================
-- FEED AUDIT — Add tenant_id scoping to legacy feed tables
-- ============================================================================
-- The feed audit (2026-03-27) added tenant scoping to:
-- - hidePost() / muteUser() endpoints (FeedController)
-- - batchLoadPollData() (FeedService)
--
-- These four tables need tenant_id columns to match the new code.
-- All statements are idempotent (IF NOT EXISTS / DROP IF EXISTS).
-- ============================================================================

-- ============================================================================
-- 1. user_hidden_posts — Add tenant_id column
-- ============================================================================
SET @col_exists = (SELECT COUNT(*) FROM information_schema.COLUMNS
    WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'user_hidden_posts' AND COLUMN_NAME = 'tenant_id');
SET @sql = IF(@col_exists = 0,
    'ALTER TABLE user_hidden_posts ADD COLUMN tenant_id INT(11) NOT NULL DEFAULT 0 AFTER post_id',
    'SELECT 1');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

-- Backfill tenant_id from the user's tenant
UPDATE user_hidden_posts uhp
JOIN users u ON uhp.user_id = u.id
SET uhp.tenant_id = u.tenant_id
WHERE uhp.tenant_id = 0;

-- Add tenant-scoped unique index
ALTER TABLE user_hidden_posts
    DROP INDEX IF EXISTS unique_hidden,
    ADD UNIQUE INDEX IF NOT EXISTS uk_user_hidden_tenant (user_id, post_id, tenant_id);


-- ============================================================================
-- 2. user_muted_users — Add tenant_id column
-- ============================================================================
SET @col_exists = (SELECT COUNT(*) FROM information_schema.COLUMNS
    WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'user_muted_users' AND COLUMN_NAME = 'tenant_id');
SET @sql = IF(@col_exists = 0,
    'ALTER TABLE user_muted_users ADD COLUMN tenant_id INT(11) NOT NULL DEFAULT 0 AFTER muted_user_id',
    'SELECT 1');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

-- Backfill tenant_id from the user's tenant
UPDATE user_muted_users umu
JOIN users u ON umu.user_id = u.id
SET umu.tenant_id = u.tenant_id
WHERE umu.tenant_id = 0;

-- Add tenant-scoped unique index
ALTER TABLE user_muted_users
    DROP INDEX IF EXISTS unique_mute,
    ADD UNIQUE INDEX IF NOT EXISTS uk_user_mute_tenant (user_id, muted_user_id, tenant_id);


-- ============================================================================
-- 3. poll_options — Add tenant_id column
-- ============================================================================
SET @col_exists = (SELECT COUNT(*) FROM information_schema.COLUMNS
    WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'poll_options' AND COLUMN_NAME = 'tenant_id');
SET @sql = IF(@col_exists = 0,
    'ALTER TABLE poll_options ADD COLUMN tenant_id INT(11) NOT NULL DEFAULT 0 AFTER poll_id',
    'SELECT 1');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

-- Backfill tenant_id from the parent poll's tenant
UPDATE poll_options po
JOIN polls p ON po.poll_id = p.id
SET po.tenant_id = p.tenant_id
WHERE po.tenant_id = 0;

-- Add composite index for tenant-scoped queries
ALTER TABLE poll_options
    ADD INDEX IF NOT EXISTS idx_poll_options_tenant (tenant_id, poll_id);


-- ============================================================================
-- 4. poll_votes — Add tenant_id column
-- ============================================================================
SET @col_exists = (SELECT COUNT(*) FROM information_schema.COLUMNS
    WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'poll_votes' AND COLUMN_NAME = 'tenant_id');
SET @sql = IF(@col_exists = 0,
    'ALTER TABLE poll_votes ADD COLUMN tenant_id INT(11) NOT NULL DEFAULT 0 AFTER poll_id',
    'SELECT 1');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

-- Backfill tenant_id from the parent poll's tenant
UPDATE poll_votes pv
JOIN polls p ON pv.poll_id = p.id
SET pv.tenant_id = p.tenant_id
WHERE pv.tenant_id = 0;

-- Add composite index for tenant-scoped queries
ALTER TABLE poll_votes
    ADD INDEX IF NOT EXISTS idx_poll_votes_tenant (tenant_id, poll_id);


-- ============================================================================
-- VERIFICATION
-- ============================================================================
SELECT 'Feed audit tenant scoping migration complete.' AS status;

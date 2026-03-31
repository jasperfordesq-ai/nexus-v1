-- ============================================================================
-- 2026-03-31: Schema fixes from broker/safeguarding audit
-- ============================================================================
-- Fixes:
--   1. user_blocks: rename blocker_user_id → user_id (code expects user_id)
--   2. reports: normalize 'pending' status to 'open' for existing records
--   3. reports: add reporter_id column if missing, backfill from user_id
-- ============================================================================

-- 1. user_blocks: rename blocker_user_id to user_id
--    All application code (MessageService, ConnectionService, SmartMatchingEngine,
--    GdprService) references 'user_id' as the blocker column.
--    The original migration created it as 'blocker_user_id'.

-- Drop old indexes and foreign key first (IF EXISTS for idempotency)
SET @fk_exists = (SELECT COUNT(*) FROM information_schema.TABLE_CONSTRAINTS
    WHERE CONSTRAINT_SCHEMA = DATABASE() AND TABLE_NAME = 'user_blocks'
    AND CONSTRAINT_NAME = 'user_blocks_ibfk_1' AND CONSTRAINT_TYPE = 'FOREIGN KEY');

-- We can't use IF in DDL directly, so use a procedure
DELIMITER //
DROP PROCEDURE IF EXISTS _fix_user_blocks_column//
CREATE PROCEDURE _fix_user_blocks_column()
BEGIN
    -- Check if blocker_user_id exists and user_id does not
    IF EXISTS (
        SELECT 1 FROM information_schema.COLUMNS
        WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'user_blocks' AND COLUMN_NAME = 'blocker_user_id'
    ) AND NOT EXISTS (
        SELECT 1 FROM information_schema.COLUMNS
        WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'user_blocks' AND COLUMN_NAME = 'user_id'
    ) THEN
        -- Drop foreign key if it exists
        IF EXISTS (
            SELECT 1 FROM information_schema.TABLE_CONSTRAINTS
            WHERE CONSTRAINT_SCHEMA = DATABASE() AND TABLE_NAME = 'user_blocks'
            AND CONSTRAINT_NAME = 'user_blocks_ibfk_1' AND CONSTRAINT_TYPE = 'FOREIGN KEY'
        ) THEN
            ALTER TABLE user_blocks DROP FOREIGN KEY user_blocks_ibfk_1;
        END IF;

        -- Drop old indexes
        IF EXISTS (
            SELECT 1 FROM information_schema.STATISTICS
            WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'user_blocks' AND INDEX_NAME = 'unique_block'
        ) THEN
            ALTER TABLE user_blocks DROP INDEX unique_block;
        END IF;

        IF EXISTS (
            SELECT 1 FROM information_schema.STATISTICS
            WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'user_blocks' AND INDEX_NAME = 'idx_blocker'
        ) THEN
            ALTER TABLE user_blocks DROP INDEX idx_blocker;
        END IF;

        -- Rename the column
        ALTER TABLE user_blocks CHANGE COLUMN blocker_user_id user_id INT NOT NULL COMMENT 'User who is blocking';

        -- Re-add indexes with new column name
        ALTER TABLE user_blocks ADD UNIQUE KEY unique_block (user_id, blocked_user_id, tenant_id);
        ALTER TABLE user_blocks ADD INDEX idx_blocker (user_id, tenant_id);
        ALTER TABLE user_blocks ADD FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE;

        SELECT 'user_blocks: renamed blocker_user_id to user_id' AS migration_result;
    ELSE
        SELECT 'user_blocks: column already correct (user_id exists or blocker_user_id missing)' AS migration_result;
    END IF;
END//
DELIMITER ;

CALL _fix_user_blocks_column();
DROP PROCEDURE IF EXISTS _fix_user_blocks_column;


-- 2. reports: normalize 'pending' status to 'open'
--    SocialController was inserting status='pending', but admin controller expected 'open'.
--    Code is now fixed to insert 'open', but existing rows need updating.

UPDATE reports SET status = 'open' WHERE status = 'pending';
SELECT CONCAT('reports: normalized ', ROW_COUNT(), ' rows from pending to open') AS migration_result;


-- 3. reports: ensure reporter_id column exists and backfill from user_id
--    SocialController was inserting into user_id instead of reporter_id.
--    Code is now fixed. Backfill any existing reports that have user_id but null reporter_id.

DROP PROCEDURE IF EXISTS _fix_reports_reporter_id;
DELIMITER //
CREATE PROCEDURE _fix_reports_reporter_id()
BEGIN
    -- Add reporter_id if it doesn't exist
    IF NOT EXISTS (
        SELECT 1 FROM information_schema.COLUMNS
        WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'reports' AND COLUMN_NAME = 'reporter_id'
    ) THEN
        ALTER TABLE reports ADD COLUMN reporter_id INT NULL AFTER tenant_id;
        ALTER TABLE reports ADD INDEX idx_reporter (reporter_id);
        SELECT 'reports: added reporter_id column' AS migration_result;
    END IF;

    -- Backfill reporter_id from user_id where reporter_id is null
    IF EXISTS (
        SELECT 1 FROM information_schema.COLUMNS
        WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'reports' AND COLUMN_NAME = 'user_id'
    ) THEN
        UPDATE reports SET reporter_id = user_id WHERE reporter_id IS NULL AND user_id IS NOT NULL;
        SELECT CONCAT('reports: backfilled ', ROW_COUNT(), ' reporter_id values from user_id') AS migration_result;
    END IF;
END//
DELIMITER ;

CALL _fix_reports_reporter_id();
DROP PROCEDURE IF EXISTS _fix_reports_reporter_id;

SELECT 'Migration 2026_03_31_audit_schema_fixes complete' AS status;

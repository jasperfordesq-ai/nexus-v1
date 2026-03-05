-- ============================================================================
-- ADD created_at COLUMN TO group_members TABLE
-- ============================================================================
-- Migration: Add created_at column to group_members table
-- Purpose: Fix missing column error in group analytics queries
-- Date: 2026-01-13
--
-- Error Fixed:
--   SQLSTATE[42S22]: Column not found: 1054 Unknown column 'created_at' in 'SELECT'
--   SQL: SELECT DATE(created_at) as date, COUNT(*) as count
--        FROM group_members
--        WHERE created_at >= DATE_SUB(NOW(), INTERVAL 30 DAY)
--
-- References:
--   - Group analytics dashboard queries
--   - Group member growth tracking
-- ============================================================================

-- Check if created_at column exists
SET @column_exists = (
    SELECT COUNT(*)
    FROM information_schema.COLUMNS
    WHERE TABLE_SCHEMA = DATABASE()
    AND TABLE_NAME = 'group_members'
    AND COLUMN_NAME = 'created_at'
);

-- Add created_at column if it doesn't exist
SET @sql_add_column = IF(@column_exists = 0,
    'ALTER TABLE `group_members` ADD COLUMN `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP COMMENT ''When the user joined this group''',
    'SELECT ''Column created_at already exists in group_members'' AS result'
);

PREPARE stmt FROM @sql_add_column;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

-- Create index for performance on queries filtering by created_at
SET @index_exists = (
    SELECT COUNT(*)
    FROM information_schema.STATISTICS
    WHERE TABLE_SCHEMA = DATABASE()
    AND TABLE_NAME = 'group_members'
    AND INDEX_NAME = 'idx_created_at'
);

SET @sql_add_index = IF(@index_exists = 0,
    'CREATE INDEX `idx_created_at` ON `group_members` (`created_at`)',
    'SELECT ''Index idx_created_at already exists'' AS result'
);

PREPARE stmt FROM @sql_add_index;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

-- Optional: Backfill created_at for existing records using joined_at if it exists
-- Or use current timestamp if no other date field is available
SET @joined_at_exists = (
    SELECT COUNT(*)
    FROM information_schema.COLUMNS
    WHERE TABLE_SCHEMA = DATABASE()
    AND TABLE_NAME = 'group_members'
    AND COLUMN_NAME = 'joined_at'
);

-- If joined_at exists, copy its values to created_at
SET @sql_backfill = IF(@joined_at_exists > 0,
    'UPDATE `group_members` SET `created_at` = `joined_at` WHERE `created_at` = CURRENT_TIMESTAMP',
    'SELECT ''No joined_at column found for backfill'' AS result'
);

PREPARE stmt FROM @sql_backfill;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

SELECT 'GROUP_MEMBERS TABLE: Added created_at column successfully' AS result;

-- ============================================================================
-- VERIFICATION QUERY (Optional - run separately after migration)
-- ============================================================================
-- Run these queries in a separate query window AFTER running the migration above

/*
-- Verify the column exists
SHOW COLUMNS FROM `group_members` LIKE 'created_at';

-- Check membership growth by date
SELECT
    DATE(created_at) as join_date,
    COUNT(*) as new_members
FROM `group_members`
WHERE created_at >= DATE_SUB(NOW(), INTERVAL 30 DAY)
GROUP BY DATE(created_at)
ORDER BY join_date DESC;

-- Check total members by status
SELECT
    status,
    COUNT(*) as count,
    MIN(created_at) as first_member,
    MAX(created_at) as latest_member
FROM `group_members`
GROUP BY status;
*/

-- ============================================================================
-- MIGRATION COMPLETE
-- ============================================================================
-- Summary:
-- ✓ Added created_at column to group_members table (default: CURRENT_TIMESTAMP)
-- ✓ Created performance index on created_at column
-- ✓ Backfilled existing records from joined_at if available
-- ✓ Fixed error in group analytics queries
--
-- Next Steps:
-- 1. Run this migration on your database
-- 2. Verify group analytics dashboard displays correctly
-- 3. Check member growth charts are working
-- ============================================================================

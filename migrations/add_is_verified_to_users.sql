-- ============================================================================
-- ADD is_verified COLUMN TO users TABLE
-- ============================================================================
-- Migration: Add is_verified column to users table
-- Purpose: Fix missing column error in CommunityRank and other queries
-- Date: 2026-01-11
--
-- Error Fixed:
--   SQLSTATE[42S22]: Column not found: 1054 Unknown column 'u.is_verified' in 'SELECT'
--
-- References:
--   - src/Services/MemberRankingService.php:719
--   - src/Services/SmartMatchingEngine.php:721
--   - src/Services/SmartSegmentSuggestionService.php:1233
-- ============================================================================

-- Add is_verified column to users table (if not exists)
-- This is a boolean flag indicating if the user account has been verified
-- Different from email_verified_at which tracks email verification timestamp
SET @column_exists = (
    SELECT COUNT(*)
    FROM information_schema.COLUMNS
    WHERE TABLE_SCHEMA = DATABASE()
    AND TABLE_NAME = 'users'
    AND COLUMN_NAME = 'is_verified'
);

SET @sql_add_column = IF(@column_exists = 0,
    'ALTER TABLE `users` ADD COLUMN `is_verified` TINYINT(1) NOT NULL DEFAULT 0 COMMENT ''Whether user account is verified (1) or not (0)''',
    'SELECT ''Column is_verified already exists'' AS result'
);

PREPARE stmt FROM @sql_add_column;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

-- Create index for performance on queries filtering by verified status (if not exists)
SET @index_exists = (
    SELECT COUNT(*)
    FROM information_schema.STATISTICS
    WHERE TABLE_SCHEMA = DATABASE()
    AND TABLE_NAME = 'users'
    AND INDEX_NAME = 'idx_is_verified'
);

SET @sql_add_index = IF(@index_exists = 0,
    'CREATE INDEX `idx_is_verified` ON `users` (`is_verified`)',
    'SELECT ''Index idx_is_verified already exists'' AS result'
);

PREPARE stmt FROM @sql_add_index;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

-- Optional: Mark users with verified emails as verified accounts
-- Uncomment the line below if you want to auto-verify users who have verified their email:
-- UPDATE `users` SET `is_verified` = 1 WHERE `email_verified_at` IS NOT NULL;

-- Alternatively: Mark all existing users as verified (if you trust existing data)
-- Uncomment the line below if you want to consider all existing users as verified:
-- UPDATE `users` SET `is_verified` = 1 WHERE `created_at` < NOW();

SELECT 'USERS TABLE: Added is_verified column successfully' AS result;

-- ============================================================================
-- VERIFICATION QUERY (Optional - run separately after migration)
-- ============================================================================
-- Run these queries in a separate query window AFTER running the migration above

/*
-- Verify the column exists
SHOW COLUMNS FROM `users` LIKE 'is_verified';

-- Check verification counts
SELECT
    'Verified Users' AS metric,
    COUNT(*) AS count
FROM `users`
WHERE `is_verified` = 1;

SELECT
    'Unverified Users' AS metric,
    COUNT(*) AS count
FROM `users`
WHERE `is_verified` = 0;

-- Check users with verified email but not verified account
SELECT
    'Email Verified but Account Not Verified' AS metric,
    COUNT(*) AS count
FROM `users`
WHERE `email_verified_at` IS NOT NULL
AND `is_verified` = 0;
*/

-- ============================================================================
-- MIGRATION COMPLETE
-- ============================================================================
-- Summary:
-- ✓ Added is_verified column to users table (default: 0/unverified)
-- ✓ Created performance index on is_verified column
-- ✓ Fixed error in MemberRankingService, SmartMatchingEngine, SmartSegmentSuggestionService
--
-- Next Steps:
-- 1. Decide verification policy: auto-verify existing users or require manual verification
-- 2. Update application code to set is_verified = 1 during registration/verification flow
-- 3. Consider linking is_verified with email_verified_at in your application logic
-- 4. Create admin interface to manually verify/unverify users if needed
-- ============================================================================

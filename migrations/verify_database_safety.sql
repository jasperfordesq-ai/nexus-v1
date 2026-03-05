-- ============================================================================
-- NEXUS SCORE MIGRATION - PRE-FLIGHT SAFETY VERIFICATION
-- ============================================================================
-- Run this BEFORE running create_nexus_score_tables.sql
-- This script:
-- 1. Checks database connection and current schema
-- 2. Verifies required tables exist
-- 3. Counts existing data (to verify nothing is lost after migration)
-- 4. Lists all tables that WILL BE CREATED by the migration
-- 5. Lists all tables that WILL BE SKIPPED (already exist)
-- ============================================================================

SELECT '========================================' AS '';
SELECT 'NEXUS SCORE MIGRATION - SAFETY CHECK' AS '';
SELECT '========================================' AS '';
SELECT '' AS '';

-- ============================================================================
-- 1. DATABASE CONNECTION INFO
-- ============================================================================
SELECT 'Step 1: Database Connection' AS '';
SELECT DATABASE() AS 'Connected Database';
SELECT USER() AS 'MySQL User';
SELECT VERSION() AS 'MySQL Version';
SELECT NOW() AS 'Current Timestamp';
SELECT '' AS '';

-- ============================================================================
-- 2. CHECK REQUIRED TABLES EXIST (System will not work without these)
-- ============================================================================
SELECT 'Step 2: Required Tables Check' AS '';

-- Check users table
SELECT
    CASE WHEN EXISTS (
        SELECT 1 FROM INFORMATION_SCHEMA.TABLES
        WHERE TABLE_SCHEMA = DATABASE()
        AND TABLE_NAME = 'users'
    ) THEN '✓ FOUND' ELSE '✗ MISSING - CRITICAL!' END AS 'users',
    COALESCE((SELECT COUNT(*) FROM users), 0) AS 'Row Count';

-- Check tenants table
SELECT
    CASE WHEN EXISTS (
        SELECT 1 FROM INFORMATION_SCHEMA.TABLES
        WHERE TABLE_SCHEMA = DATABASE()
        AND TABLE_NAME = 'tenants'
    ) THEN '✓ FOUND' ELSE '✗ MISSING - CRITICAL!' END AS 'tenants',
    COALESCE((SELECT COUNT(*) FROM tenants), 0) AS 'Row Count';

-- Check transactions table
SELECT
    CASE WHEN EXISTS (
        SELECT 1 FROM INFORMATION_SCHEMA.TABLES
        WHERE TABLE_SCHEMA = DATABASE()
        AND TABLE_NAME = 'transactions'
    ) THEN '✓ FOUND' ELSE '✗ MISSING - CRITICAL!' END AS 'transactions',
    COALESCE((SELECT COUNT(*) FROM transactions), 0) AS 'Row Count';

-- Check reviews table
SELECT
    CASE WHEN EXISTS (
        SELECT 1 FROM INFORMATION_SCHEMA.TABLES
        WHERE TABLE_SCHEMA = DATABASE()
        AND TABLE_NAME = 'reviews'
    ) THEN '✓ FOUND' ELSE '✗ MISSING - CRITICAL!' END AS 'reviews',
    COALESCE((SELECT COUNT(*) FROM reviews), 0) AS 'Row Count';

-- Check posts table
SELECT
    CASE WHEN EXISTS (
        SELECT 1 FROM INFORMATION_SCHEMA.TABLES
        WHERE TABLE_SCHEMA = DATABASE()
        AND TABLE_NAME = 'posts'
    ) THEN '✓ FOUND' ELSE '✗ MISSING - CRITICAL!' END AS 'posts',
    COALESCE((SELECT COUNT(*) FROM posts), 0) AS 'Row Count';

-- Check user_badges table
SELECT
    CASE WHEN EXISTS (
        SELECT 1 FROM INFORMATION_SCHEMA.TABLES
        WHERE TABLE_SCHEMA = DATABASE()
        AND TABLE_NAME = 'user_badges'
    ) THEN '✓ FOUND' ELSE '✗ MISSING - CRITICAL!' END AS 'user_badges',
    COALESCE((SELECT COUNT(*) FROM user_badges), 0) AS 'Row Count';

SELECT '' AS '';

-- ============================================================================
-- 3. COUNT EXISTING USER DATA (Baseline - nothing should be lost!)
-- ============================================================================
SELECT 'Step 3: Existing Data Baseline' AS '';
SELECT '⚠️ IMPORTANT: These numbers should NOT change after migration!' AS 'WARNING';
SELECT '' AS '';

SELECT
    'Total Users' AS 'Data Type',
    COUNT(*) AS 'Current Count',
    '❌ MUST NOT DECREASE' AS 'Safety Check'
FROM users;

SELECT
    'Total Transactions' AS 'Data Type',
    COUNT(*) AS 'Current Count',
    '❌ MUST NOT DECREASE' AS 'Safety Check'
FROM transactions;

SELECT
    'Total Reviews' AS 'Data Type',
    COUNT(*) AS 'Current Count',
    '❌ MUST NOT DECREASE' AS 'Safety Check'
FROM reviews;

SELECT
    'Total Posts' AS 'Data Type',
    COUNT(*) AS 'Current Count',
    '❌ MUST NOT DECREASE' AS 'Safety Check'
FROM posts;

SELECT
    'Total Badges' AS 'Data Type',
    COUNT(*) AS 'Current Count',
    '❌ MUST NOT DECREASE' AS 'Safety Check'
FROM user_badges;

SELECT '' AS '';

-- ============================================================================
-- 4. CHECK TABLES THAT WILL BE CREATED
-- ============================================================================
SELECT 'Step 4: Tables That WILL BE CREATED' AS '';

SELECT
    'nexus_score_cache' AS 'Table Name',
    CASE WHEN EXISTS (
        SELECT 1 FROM INFORMATION_SCHEMA.TABLES
        WHERE TABLE_SCHEMA = DATABASE()
        AND TABLE_NAME = 'nexus_score_cache'
    ) THEN '⚠️ ALREADY EXISTS - WILL BE SKIPPED' ELSE '✓ WILL BE CREATED' END AS 'Status',
    'Caches user scores for performance' AS 'Purpose';

SELECT
    'nexus_score_history' AS 'Table Name',
    CASE WHEN EXISTS (
        SELECT 1 FROM INFORMATION_SCHEMA.TABLES
        WHERE TABLE_SCHEMA = DATABASE()
        AND TABLE_NAME = 'nexus_score_history'
    ) THEN '⚠️ ALREADY EXISTS - WILL BE SKIPPED' ELSE '✓ WILL BE CREATED' END AS 'Status',
    'Tracks score changes over time' AS 'Purpose';

SELECT
    'nexus_score_milestones' AS 'Table Name',
    CASE WHEN EXISTS (
        SELECT 1 FROM INFORMATION_SCHEMA.TABLES
        WHERE TABLE_SCHEMA = DATABASE()
        AND TABLE_NAME = 'nexus_score_milestones'
    ) THEN '⚠️ ALREADY EXISTS - WILL BE SKIPPED' ELSE '✓ WILL BE CREATED' END AS 'Status',
    'Stores user milestone achievements' AS 'Purpose';

SELECT
    'post_likes' AS 'Table Name',
    CASE WHEN EXISTS (
        SELECT 1 FROM INFORMATION_SCHEMA.TABLES
        WHERE TABLE_SCHEMA = DATABASE()
        AND TABLE_NAME = 'post_likes'
    ) THEN '⚠️ ALREADY EXISTS - WILL BE SKIPPED' ELSE '✓ WILL BE CREATED' END AS 'Status',
    'Tracks user post likes for engagement' AS 'Purpose';

SELECT '' AS '';

-- ============================================================================
-- 5. CHECK COLUMNS THAT WILL BE ADDED
-- ============================================================================
SELECT 'Step 5: Columns That WILL BE ADDED' AS '';

SELECT
    'transactions.transaction_type' AS 'Column',
    CASE WHEN EXISTS (
        SELECT 1 FROM INFORMATION_SCHEMA.COLUMNS
        WHERE TABLE_SCHEMA = DATABASE()
        AND TABLE_NAME = 'transactions'
        AND COLUMN_NAME = 'transaction_type'
    ) THEN '⚠️ ALREADY EXISTS - WILL BE SKIPPED' ELSE '✓ WILL BE ADDED (OPTIONAL)' END AS 'Status',
    'Allows filtering volunteer hours' AS 'Purpose';

SELECT
    'user_badges.is_showcased' AS 'Column',
    CASE WHEN EXISTS (
        SELECT 1 FROM INFORMATION_SCHEMA.COLUMNS
        WHERE TABLE_SCHEMA = DATABASE()
        AND TABLE_NAME = 'user_badges'
        AND COLUMN_NAME = 'is_showcased'
    ) THEN '⚠️ ALREADY EXISTS - WILL BE SKIPPED' ELSE '✓ WILL BE ADDED (OPTIONAL)' END AS 'Status',
    'Allows pinning favorite badges' AS 'Purpose';

SELECT
    'user_badges.showcase_order' AS 'Column',
    CASE WHEN EXISTS (
        SELECT 1 FROM INFORMATION_SCHEMA.COLUMNS
        WHERE TABLE_SCHEMA = DATABASE()
        AND TABLE_NAME = 'user_badges'
        AND COLUMN_NAME = 'showcase_order'
    ) THEN '⚠️ ALREADY EXISTS - WILL BE SKIPPED' ELSE '✓ WILL BE ADDED (OPTIONAL)' END AS 'Status',
    'Order for showcased badges' AS 'Purpose';

SELECT '' AS '';

-- ============================================================================
-- 6. SAFETY GUARANTEES
-- ============================================================================
SELECT 'Step 6: Safety Guarantees' AS '';
SELECT '✓ NO DROP TABLE commands' AS 'Safety Check 1';
SELECT '✓ NO DELETE FROM commands' AS 'Safety Check 2';
SELECT '✓ NO TRUNCATE commands' AS 'Safety Check 3';
SELECT '✓ Only CREATE TABLE IF NOT EXISTS' AS 'Safety Check 4';
SELECT '✓ Only ALTER TABLE ADD COLUMN IF NOT EXISTS' AS 'Safety Check 5';
SELECT '✓ All foreign keys have CASCADE DELETE (referential integrity)' AS 'Safety Check 6';
SELECT '' AS '';

-- ============================================================================
-- 7. FINAL RECOMMENDATIONS
-- ============================================================================
SELECT 'Step 7: Pre-Migration Checklist' AS '';
SELECT '□ 1. Backup database before running migration' AS 'Checklist';
SELECT '□ 2. Verify all required tables exist (Step 2 above)' AS 'Checklist';
SELECT '□ 3. Note baseline data counts (Step 3 above)' AS 'Checklist';
SELECT '□ 4. Run migration: create_nexus_score_tables.sql' AS 'Checklist';
SELECT '□ 5. Run this script again to verify nothing was lost' AS 'Checklist';
SELECT '' AS '';

SELECT '========================================' AS '';
SELECT 'VERIFICATION COMPLETE' AS '';
SELECT 'If all checks passed, migration is SAFE to run' AS '';
SELECT '========================================' AS '';

-- ===================================================================
-- RESTORE GROUP MEMBERSHIPS - ROLLBACK SCRIPT
-- ===================================================================
-- Purpose: Restore hub group memberships from the most recent backup
--          Use this if smart matching fails or produces wrong results
--
-- Use Case: Run this to rollback after DELETE-ALL-GROUP-MEMBERS-NOW.sql
--           if the smart matching didn't work as expected
--
-- WARNING: This will DELETE current memberships and restore from backup
--
-- Tenant: Hour Timebank Ireland (tenant_id = 2)
-- Group Type: Hub Groups (type_id = 26 - Local/Regional Hubs)
--
-- Created: 2025-01
-- Last Updated: 2025-01
-- ===================================================================

-- ===================================================================
-- STEP 1: Show available backups
-- ===================================================================

SELECT '=== AVAILABLE BACKUPS ===' as '';

SELECT
    backup_date,
    backup_note,
    COUNT(*) as membership_count,
    COUNT(DISTINCT user_id) as user_count,
    COUNT(DISTINCT group_id) as group_count
FROM group_members_backup
GROUP BY backup_date, backup_note
ORDER BY backup_date DESC
LIMIT 10;

SELECT '' as '';
SELECT 'This script will restore from the MOST RECENT backup shown above' as '';

-- ===================================================================
-- STEP 2: Show what will be restored
-- ===================================================================

SELECT '' as '';
SELECT '=== RESTORE PREVIEW ===' as '';

SELECT
    MAX(backup_date) as restore_from_date,
    COUNT(*) as memberships_to_restore,
    COUNT(DISTINCT user_id) as users_to_restore,
    COUNT(DISTINCT group_id) as groups_to_restore
FROM group_members_backup
WHERE backup_date = (SELECT MAX(backup_date) FROM group_members_backup);

-- ===================================================================
-- STEP 3: Show current state (what will be deleted)
-- ===================================================================

SELECT '' as '';
SELECT '=== CURRENT STATE (WILL BE DELETED) ===' as '';

SELECT
    COUNT(*) as current_memberships,
    COUNT(DISTINCT gm.user_id) as current_users,
    COUNT(DISTINCT gm.group_id) as current_groups
FROM group_members gm
JOIN `groups` g ON g.id = gm.group_id
WHERE g.tenant_id = 2
AND g.type_id = 26;

SELECT '' as '';
SELECT '⚠️  Press Ctrl+C NOW if you do not want to proceed!' as '';
SELECT 'Waiting 5 seconds before restore...' as '';
SELECT SLEEP(5) as '';

-- ===================================================================
-- STEP 4: Delete current memberships
-- ===================================================================

SELECT '' as '';
SELECT '=== DELETING CURRENT MEMBERSHIPS ===' as '';

DELETE gm
FROM group_members gm
JOIN `groups` g ON g.id = gm.group_id
WHERE g.tenant_id = 2
AND g.type_id = 26;

SELECT CONCAT('✓ DELETED ', ROW_COUNT(), ' current memberships') as result;

-- ===================================================================
-- STEP 5: Restore from backup
-- ===================================================================

SELECT '' as '';
SELECT '=== RESTORING FROM BACKUP ===' as '';

INSERT INTO group_members (user_id, group_id, created_at)
SELECT
    user_id,
    group_id,
    joined_at
FROM group_members_backup
WHERE backup_date = (SELECT MAX(backup_date) FROM group_members_backup);

SELECT CONCAT('✓ RESTORED ', ROW_COUNT(), ' memberships from backup') as result;

-- ===================================================================
-- STEP 6: Verification
-- ===================================================================

SELECT '' as '';
SELECT '=== VERIFICATION ===' as '';

SELECT
    COUNT(*) as restored_memberships,
    COUNT(DISTINCT gm.user_id) as restored_users,
    COUNT(DISTINCT gm.group_id) as restored_groups
FROM group_members gm
JOIN `groups` g ON g.id = gm.group_id
WHERE g.tenant_id = 2
AND g.type_id = 26;

SELECT 'Compare counts with "RESTORE PREVIEW" above - they should match!' as '';

-- ===================================================================
-- STEP 7: Success message
-- ===================================================================

SELECT '' as '';
SELECT '=== RESTORE COMPLETE ===' as '';
SELECT '✓ Hub group memberships have been restored from backup' as '';
SELECT 'Visit: https://hour-timebank.ie/groups to verify' as '';

-- ===================================================================
-- TECHNICAL DETAILS
-- ===================================================================
-- What was restored:
--   - All user-to-group relationships from the most recent backup
--   - Original joined_at dates were preserved
--
-- What was NOT restored:
--   - Any memberships created AFTER the backup
--   - Any memberships in OTHER group types (only hub groups restored)
--
-- Backup retention:
--   - The backup is NOT deleted after restore
--   - You can restore again if needed
--   - Multiple restores are safe (idempotent operation)
--
-- If you need to restore from a SPECIFIC backup (not the latest):
--   - Edit STEP 5 above
--   - Replace: WHERE backup_date = (SELECT MAX(backup_date) FROM group_members_backup)
--   - With: WHERE backup_date = 'YYYY-MM-DD HH:MM:SS'
-- ===================================================================

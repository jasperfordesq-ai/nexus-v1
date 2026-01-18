-- ===================================================================
-- BACKUP GROUP MEMBERSHIPS - SAFETY SCRIPT
-- ===================================================================
-- Purpose: Create a backup of all hub group memberships before deletion
--          This allows for rollback if something goes wrong
--
-- Use Case: ALWAYS run this BEFORE running DELETE-ALL-GROUP-MEMBERS-NOW.sql
--
-- Tenant: Hour Timebank Ireland (tenant_id = 2)
-- Group Type: Hub Groups (type_id = 26 - Local/Regional Hubs)
--
-- Created: 2025-01
-- Last Updated: 2025-01
-- ===================================================================

-- ===================================================================
-- STEP 1: Create backup table if it doesn't exist
-- ===================================================================

CREATE TABLE IF NOT EXISTS `group_members_backup` (
    `id` int(11) NOT NULL AUTO_INCREMENT,
    `user_id` int(11) NOT NULL,
    `group_id` int(11) NOT NULL,
    `joined_at` timestamp NULL DEFAULT NULL,
    `backup_date` timestamp DEFAULT CURRENT_TIMESTAMP,
    `backup_note` varchar(255) DEFAULT NULL,
    PRIMARY KEY (`id`),
    KEY `user_id` (`user_id`),
    KEY `group_id` (`group_id`),
    KEY `backup_date` (`backup_date`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

SELECT '✓ Backup table ready' as '';

-- ===================================================================
-- STEP 2: Show what will be backed up
-- ===================================================================

SELECT '' as '';
SELECT '=== BACKUP PREVIEW ===' as '';

SELECT
    COUNT(DISTINCT gm.user_id) as users_to_backup,
    COUNT(DISTINCT gm.group_id) as groups_to_backup,
    COUNT(*) as total_memberships_to_backup,
    NOW() as backup_timestamp
FROM group_members gm
JOIN `groups` g ON g.id = gm.group_id
WHERE g.tenant_id = 2
AND g.type_id = 26;

-- ===================================================================
-- STEP 3: Create the backup
-- ===================================================================

SELECT '' as '';
SELECT '=== CREATING BACKUP ===' as '';

INSERT INTO group_members_backup (user_id, group_id, joined_at, backup_note)
SELECT
    gm.user_id,
    gm.group_id,
    gm.created_at as joined_at,
    CONCAT('Pre-cleanup backup - ', DATE_FORMAT(NOW(), '%Y-%m-%d %H:%i:%s')) as backup_note
FROM group_members gm
JOIN `groups` g ON g.id = gm.group_id
WHERE g.tenant_id = 2
AND g.type_id = 26;

SELECT CONCAT('✓ BACKED UP ', ROW_COUNT(), ' hub group memberships') as result;

-- ===================================================================
-- STEP 4: Verify backup
-- ===================================================================

SELECT '' as '';
SELECT '=== VERIFICATION ===' as '';

SELECT
    COUNT(*) as total_backed_up,
    COUNT(DISTINCT user_id) as unique_users,
    COUNT(DISTINCT group_id) as unique_groups,
    MAX(backup_date) as latest_backup
FROM group_members_backup
WHERE backup_date >= DATE_SUB(NOW(), INTERVAL 1 MINUTE);

SELECT 'If counts match preview, backup succeeded!' as '';

-- ===================================================================
-- STEP 5: Next steps
-- ===================================================================

SELECT '' as '';
SELECT '=== NEXT STEP ===' as '';
SELECT '✓ Backup complete! You can now safely run DELETE-ALL-GROUP-MEMBERS-NOW.sql' as '';
SELECT '' as '';
SELECT 'If you need to rollback later, run: RESTORE-GROUP-MEMBERS.sql' as '';

-- ===================================================================
-- TECHNICAL DETAILS
-- ===================================================================
-- Backup retention:
--   - Backups are kept indefinitely by default
--   - To clean old backups, run: DELETE FROM group_members_backup WHERE backup_date < DATE_SUB(NOW(), INTERVAL 30 DAY);
--
-- Storage:
--   - Each backup record is ~50 bytes
--   - 1000 memberships = ~50KB
--   - 10,000 memberships = ~500KB
--
-- Safety:
--   - Multiple backups can exist (each with different backup_date)
--   - Backups include timestamp and note for identification
--   - Original joined_at date is preserved
-- ===================================================================

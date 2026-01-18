-- ===================================================================
-- DELETE ALL HUB GROUP MEMBERSHIPS - CLEANUP SCRIPT
-- ===================================================================
-- Purpose: Remove all user memberships from hub groups (type_id = 26)
--          to prepare for smart matching reassignment
--
-- Use Case: Run this when group memberships are incorrect and need to be
--           regenerated based on users' GPS coordinates or location data
--
-- WARNING: This script EXECUTES IMMEDIATELY when run. There is no undo.
--          It will delete ALL hub group memberships for tenant_id = 2
--
-- Tenant: Hour Timebank Ireland (tenant_id = 2)
-- Group Type: Hub Groups (type_id = 26 - Local/Regional Hubs)
--
-- Created: 2025-01
-- Last Updated: 2025-01
-- ===================================================================

-- ===================================================================
-- STEP 1: PREVIEW - Show what will be deleted
-- ===================================================================
SELECT '=== ABOUT TO DELETE ===' as '';

SELECT
    COUNT(DISTINCT gm.user_id) as users_in_hub_groups,
    COUNT(DISTINCT gm.group_id) as hub_groups_with_members,
    COUNT(*) as total_memberships_to_delete
FROM group_members gm
JOIN `groups` g ON g.id = gm.group_id
WHERE g.tenant_id = 2           -- Hour Timebank Ireland
AND g.type_id = 26;             -- Hub groups only

SELECT '' as '';
SELECT '=== DELETING NOW ===' as '';

-- ===================================================================
-- STEP 2: DELETE - Remove all hub group memberships
-- ===================================================================
-- This deletes the join table records between users and hub groups
-- It does NOT delete:
--   - The users themselves
--   - The groups themselves
--   - Any other data
--
-- Only the group_members relationships for hub groups are removed
-- ===================================================================

DELETE gm
FROM group_members gm
JOIN `groups` g ON g.id = gm.group_id
WHERE g.tenant_id = 2           -- Hour Timebank Ireland
AND g.type_id = 26;             -- Hub groups only

SELECT CONCAT('✓ DELETED ', ROW_COUNT(), ' hub group memberships') as result;

-- ===================================================================
-- STEP 3: VERIFICATION - Confirm cleanup was successful
-- ===================================================================

SELECT '' as '';
SELECT '=== VERIFICATION ===' as '';

SELECT
    COUNT(*) as memberships_remaining
FROM group_members gm
JOIN `groups` g ON g.id = gm.group_id
WHERE g.tenant_id = 2
AND g.type_id = 26;

SELECT 'If memberships_remaining = 0, then cleanup succeeded!' as '';

-- ===================================================================
-- STEP 4: NEXT STEPS - How to reassign users to correct groups
-- ===================================================================

SELECT '' as '';
SELECT '=== NEXT STEP ===' as '';
SELECT 'Visit: https://hour-timebank.ie/admin/smart-match-users' as '';
SELECT 'OR: Admin Panel > Community > Smart Match Users' as '';
SELECT 'Click "Start Smart Matching" to assign all users to correct groups' as '';
SELECT '' as '';
SELECT 'The Smart Match process will:' as '';
SELECT '  1. Use GPS coordinates for accurate geographic matching' as '';
SELECT '  2. Fall back to fuzzy text matching if GPS unavailable' as '';
SELECT '  3. Automatically assign users to parent groups (counties)' as '';
SELECT '  4. Only assign if user is within 50km of group location' as '';

-- ===================================================================
-- TECHNICAL DETAILS
-- ===================================================================
-- Tables affected:
--   - group_members: All records linking users to hub groups are deleted
--
-- Groups affected:
--   - Only groups with type_id = 26 (Hub Groups)
--   - Other group types (interest groups, etc.) are NOT affected
--
-- Recovery:
--   - No built-in recovery mechanism
--   - Must run Smart Match Users tool to regenerate memberships
--   - Smart matching uses GPS coordinates and location data
--
-- Safe to run:
--   - Yes, if you want to reset all hub group memberships
--   - No data loss beyond the group membership associations
--   - Users and groups remain intact
-- ===================================================================

-- ===================================================================
-- SIMPLE CLEANUP - THEN USE BROWSER SMART MATCHING
-- ===================================================================
-- This approach is SIMPLER and SAFER:
-- 1. Run this SQL to delete all hub group memberships (clean slate)
-- 2. Optionally fix the 30 GPS coordinate errors
-- 3. Visit https://hour-timebank.ie/admin/smart-match-users
-- 4. Click "Start Smart Matching" button
-- 5. Watch the progress bar as it assigns all 224 users correctly
-- ===================================================================

-- STEP 1: Show current state
SELECT '=== CURRENT STATE ===' as '';
SELECT 'Hub group memberships that will be deleted:' as '';

SELECT
    COUNT(DISTINCT gm.user_id) as total_users_in_hub_groups,
    COUNT(DISTINCT gm.group_id) as total_hub_groups_with_members,
    COUNT(*) as total_hub_memberships
FROM group_members gm
JOIN `groups` g ON g.id = gm.group_id
WHERE g.tenant_id = 2
AND g.type_id = 26;

SELECT '' as '';
SELECT '=== STEP 1: DELETE ALL HUB GROUP MEMBERSHIPS ===' as '';
SELECT 'This gives you a clean slate for smart matching' as '';
SELECT '' as '';
SELECT 'UNCOMMENT BELOW TO DELETE ALL MEMBERSHIPS:' as '';

/*
DELETE gm
FROM group_members gm
JOIN `groups` g ON g.id = gm.group_id
WHERE g.tenant_id = 2
AND g.type_id = 26;

SELECT CONCAT('✓ Deleted ', ROW_COUNT(), ' hub group memberships') as result;
SELECT '✓ All users removed from hub groups' as result;
SELECT '' as '';
SELECT '=== NEXT STEP ===' as '';
SELECT 'Visit https://hour-timebank.ie/admin/smart-match-users' as '';
SELECT 'Click "Start Smart Matching" button' as '';
SELECT 'It will assign all 224 users to their correct groups automatically' as '';
*/

SELECT '' as '';
SELECT '=== OPTIONAL STEP 2: FIX GPS COORDINATES FIRST ===' as '';
SELECT 'These 30 users have wrong GPS coordinates (location text does not match GPS)' as '';
SELECT 'Recommended: Fix these BEFORE running smart matching' as '';
SELECT '' as '';

-- Preview users with wrong GPS
SELECT
    u.id,
    u.name,
    u.location,
    ROUND(u.latitude, 4) as current_lat,
    ROUND(u.longitude, 4) as current_lng,
    g.name as location_text_says,
    ROUND(g.latitude, 4) as should_be_lat,
    ROUND(g.longitude, 4) as should_be_lng,
    'Will fix GPS to match location text' as action
FROM users u
JOIN `groups` g ON g.tenant_id = 2
    AND g.type_id = 26
    AND NOT EXISTS (SELECT 1 FROM `groups` c WHERE c.parent_id = g.id)
WHERE u.tenant_id = 2
AND u.status = 'active'
AND u.latitude IS NOT NULL
-- EXACT match: location starts with town name
AND (
    u.location COLLATE utf8mb4_unicode_ci LIKE CONCAT(g.name COLLATE utf8mb4_unicode_ci, ',%')
    OR u.location COLLATE utf8mb4_unicode_ci LIKE CONCAT(g.name COLLATE utf8mb4_unicode_ci, ', Co.%')
    OR u.location COLLATE utf8mb4_unicode_ci LIKE CONCAT(g.name COLLATE utf8mb4_unicode_ci, ', County%')
)
-- GPS doesn't match (>15km difference)
AND (6371 * acos(
    cos(radians(u.latitude)) *
    cos(radians(g.latitude)) *
    cos(radians(g.longitude) - radians(u.longitude)) +
    sin(radians(u.latitude)) *
    sin(radians(g.latitude))
)) > 15
ORDER BY u.id;

SELECT '' as '';
SELECT 'UNCOMMENT BELOW TO FIX GPS COORDINATES:' as '';

/*
-- Fix GPS coordinates to match town name in location text
UPDATE users u
JOIN `groups` g ON g.tenant_id = 2
    AND g.type_id = 26
    AND NOT EXISTS (SELECT 1 FROM `groups` c WHERE c.parent_id = g.id)
SET u.latitude = g.latitude, u.longitude = g.longitude
WHERE u.tenant_id = 2
AND u.status = 'active'
AND u.latitude IS NOT NULL
-- EXACT match only
AND (
    u.location COLLATE utf8mb4_unicode_ci LIKE CONCAT(g.name COLLATE utf8mb4_unicode_ci, ',%')
    OR u.location COLLATE utf8mb4_unicode_ci LIKE CONCAT(g.name COLLATE utf8mb4_unicode_ci, ', Co.%')
    OR u.location COLLATE utf8mb4_unicode_ci LIKE CONCAT(g.name COLLATE utf8mb4_unicode_ci, ', County%')
)
-- GPS doesn't match
AND (6371 * acos(
    cos(radians(u.latitude)) *
    cos(radians(g.latitude)) *
    cos(radians(g.longitude) - radians(u.longitude)) +
    sin(radians(u.latitude)) *
    sin(radians(g.latitude))
)) > 15;

SELECT '✓ GPS coordinates fixed!' as '';
SELECT CONCAT('✓ Updated ', ROW_COUNT(), ' users') as result;
*/

SELECT '' as '';
SELECT '=== INSTRUCTIONS ===' as '';
SELECT '1. Uncomment STEP 1 (delete memberships) and run this script' as '';
SELECT '2. Optionally uncomment STEP 2 (fix GPS) and run again' as '';
SELECT '3. Visit https://hour-timebank.ie/admin/smart-match-users' as '';
SELECT '4. Click "Start Smart Matching" button' as '';
SELECT '5. Watch it assign all users to correct groups automatically!' as '';
SELECT '' as '';
SELECT 'WHY THIS IS BETTER:' as '';
SELECT '✓ You can see the progress in real-time' as '';
SELECT '✓ It handles errors gracefully' as '';
SELECT '✓ It logs everything to database' as '';
SELECT '✓ It assigns users to parent groups automatically' as '';
SELECT '✓ Much simpler than running complex SQL scripts!' as '';

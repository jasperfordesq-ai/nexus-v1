-- ===================================================================
-- COMPLETE GROUP CLEANUP AND RE-ASSIGNMENT
-- ===================================================================
-- This will:
-- 1. Remove ALL users from ALL hub groups (clean slate)
-- 2. Fix GPS coordinates for 30 users with wrong geocoding
-- 3. Re-assign ALL users to correct groups based on GPS
-- 4. Cascade to parent groups
-- ===================================================================

-- SAFETY CHECK FIRST
SELECT '=== SAFETY CHECK ===' as '';
SELECT 'This will affect ALL hub group memberships' as '';
SELECT 'Current state:' as '';

SELECT
    COUNT(DISTINCT gm.user_id) as total_users_in_hub_groups,
    COUNT(DISTINCT gm.group_id) as total_hub_groups_with_members,
    COUNT(*) as total_hub_memberships
FROM group_members gm
JOIN `groups` g ON g.id = gm.group_id
WHERE g.tenant_id = 2
AND g.type_id = 26;

SELECT '' as '';
SELECT '=== STEP 1: REMOVE ALL HUB GROUP MEMBERSHIPS ===' as '';
SELECT 'This will delete ALL hub group assignments (bottom-level and parents)' as '';

-- Preview how many will be deleted
SELECT COUNT(*) as memberships_to_delete
FROM group_members gm
JOIN `groups` g ON g.id = gm.group_id
WHERE g.tenant_id = 2
AND g.type_id = 26;

SELECT '' as '';
SELECT 'UNCOMMENT SECTION 1 BELOW TO CLEAR ALL HUB GROUPS:' as '';

/*
-- Delete ALL hub group memberships (bottom-level and parents)
DELETE gm
FROM group_members gm
JOIN `groups` g ON g.id = gm.group_id
WHERE g.tenant_id = 2
AND g.type_id = 26;

SELECT CONCAT('Deleted ', ROW_COUNT(), ' hub group memberships') as result;
*/

SELECT '' as '';
SELECT '=== STEP 2: FIX GPS COORDINATES ===' as '';
SELECT 'Will fix 30 users whose GPS does not match their location text' as '';

-- Preview
SELECT
    u.id,
    u.name,
    u.location,
    CONCAT(ROUND(u.latitude, 4), ', ', ROUND(u.longitude, 4)) as current_gps,
    g.name as town,
    CONCAT(ROUND(g.latitude, 4), ', ', ROUND(g.longitude, 4)) as correct_gps
FROM users u
JOIN `groups` g ON g.tenant_id = 2
    AND g.type_id = 26
    AND NOT EXISTS (SELECT 1 FROM `groups` c WHERE c.parent_id = g.id)
WHERE u.tenant_id = 2
AND u.status = 'active'
AND u.latitude IS NOT NULL
AND (
    u.location COLLATE utf8mb4_unicode_ci LIKE CONCAT(g.name COLLATE utf8mb4_unicode_ci, ',%')
    OR u.location COLLATE utf8mb4_unicode_ci LIKE CONCAT(g.name COLLATE utf8mb4_unicode_ci, ', Co.%')
    OR u.location COLLATE utf8mb4_unicode_ci LIKE CONCAT(g.name COLLATE utf8mb4_unicode_ci, ', County%')
)
AND (6371 * acos(
    cos(radians(u.latitude)) *
    cos(radians(g.latitude)) *
    cos(radians(g.longitude) - radians(u.longitude)) +
    sin(radians(u.latitude)) *
    sin(radians(g.latitude))
)) > 15
LIMIT 10;

SELECT '' as '';
SELECT 'UNCOMMENT SECTION 2 BELOW TO FIX GPS:' as '';

/*
-- Fix GPS coordinates
UPDATE users u
JOIN `groups` g ON g.tenant_id = 2
    AND g.type_id = 26
    AND NOT EXISTS (SELECT 1 FROM `groups` c WHERE c.parent_id = g.id)
SET u.latitude = g.latitude, u.longitude = g.longitude
WHERE u.tenant_id = 2
AND u.status = 'active'
AND u.latitude IS NOT NULL
AND (
    u.location COLLATE utf8mb4_unicode_ci LIKE CONCAT(g.name COLLATE utf8mb4_unicode_ci, ',%')
    OR u.location COLLATE utf8mb4_unicode_ci LIKE CONCAT(g.name COLLATE utf8mb4_unicode_ci, ', Co.%')
    OR u.location COLLATE utf8mb4_unicode_ci LIKE CONCAT(g.name COLLATE utf8mb4_unicode_ci, ', County%')
)
AND (6371 * acos(
    cos(radians(u.latitude)) *
    cos(radians(g.latitude)) *
    cos(radians(g.longitude) - radians(u.longitude)) +
    sin(radians(u.latitude)) *
    sin(radians(g.latitude))
)) > 15;

SELECT CONCAT('Fixed GPS for ', ROW_COUNT(), ' users') as result;
*/

SELECT '' as '';
SELECT '=== STEP 3: RE-ASSIGN ALL USERS TO CORRECT GROUPS ===' as '';
SELECT 'Will assign all users to their nearest group based on GPS' as '';

-- Preview: Show how many users will be assigned to each group
SELECT
    (
        SELECT gr.name
        FROM `groups` gr
        WHERE gr.tenant_id = 2
        AND gr.type_id = 26
        AND gr.latitude IS NOT NULL
        AND NOT EXISTS (SELECT 1 FROM `groups` c WHERE c.parent_id = gr.id)
        ORDER BY (6371 * acos(
            cos(radians(u.latitude)) *
            cos(radians(gr.latitude)) *
            cos(radians(gr.longitude) - radians(u.longitude)) +
            sin(radians(u.latitude)) *
            sin(radians(gr.latitude))
        )) ASC
        LIMIT 1
    ) as group_name,
    COUNT(*) as users_to_assign
FROM users u
WHERE u.tenant_id = 2
AND u.status = 'active'
AND u.latitude IS NOT NULL
GROUP BY group_name
ORDER BY users_to_assign DESC
LIMIT 20;

SELECT '' as '';
SELECT 'UNCOMMENT SECTION 3 BELOW TO RE-ASSIGN USERS:' as '';

/*
-- Assign all users to nearest bottom-level group
INSERT INTO group_members (group_id, user_id, joined_at, status, role)
SELECT
    (
        SELECT g.id
        FROM `groups` g
        WHERE g.tenant_id = 2
        AND g.type_id = 26
        AND g.latitude IS NOT NULL
        AND NOT EXISTS (SELECT 1 FROM `groups` c WHERE c.parent_id = g.id)
        ORDER BY (6371 * acos(
            cos(radians(u.latitude)) *
            cos(radians(g.latitude)) *
            cos(radians(g.longitude) - radians(u.longitude)) +
            sin(radians(u.latitude)) *
            sin(radians(g.latitude))
        )) ASC
        LIMIT 1
    ) as group_id,
    u.id as user_id,
    NOW() as joined_at,
    'active' as status,
    'member' as role
FROM users u
WHERE u.tenant_id = 2
AND u.status = 'active'
AND u.latitude IS NOT NULL;

SELECT CONCAT('Assigned ', ROW_COUNT(), ' users to bottom-level groups') as result;
*/

SELECT '' as '';
SELECT '=== STEP 4: CASCADE TO PARENT GROUPS ===' as '';
SELECT 'Will add users to county/province/country groups' as '';

SELECT 'UNCOMMENT SECTION 4 BELOW TO CASCADE TO PARENTS:' as '';

/*
-- Add users to all parent groups up the hierarchy
INSERT INTO group_members (group_id, user_id, joined_at, status, role)
SELECT DISTINCT
    parent.id as group_id,
    gm.user_id,
    NOW() as joined_at,
    'active' as status,
    'member' as role
FROM group_members gm
JOIN `groups` child ON child.id = gm.group_id
    AND child.tenant_id = 2
    AND child.type_id = 26
    AND NOT EXISTS (SELECT 1 FROM `groups` c WHERE c.parent_id = child.id)
JOIN `groups` parent ON (
    parent.id = child.parent_id
    OR parent.id = (SELECT parent_id FROM `groups` WHERE id = child.parent_id)
    OR parent.id = (SELECT parent_id FROM `groups` WHERE id = (SELECT parent_id FROM `groups` WHERE id = child.parent_id))
)
WHERE parent.tenant_id = 2
AND parent.type_id = 26;

SELECT CONCAT('Added users to ', ROW_COUNT(), ' parent group memberships') as result;
*/

SELECT '' as '';
SELECT '=== FINAL VERIFICATION ===' as '';
SELECT 'After all steps complete, verify:' as '';

/*
SELECT
    'Final Results:' as '',
    (SELECT COUNT(DISTINCT user_id) FROM group_members gm JOIN `groups` g ON g.id = gm.group_id WHERE g.tenant_id = 2 AND g.type_id = 26) as users_in_hub_groups,
    (SELECT COUNT(*) FROM group_members gm JOIN `groups` g ON g.id = gm.group_id WHERE g.tenant_id = 2 AND g.type_id = 26) as total_memberships;
*/

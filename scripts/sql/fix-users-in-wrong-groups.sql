-- ===================================================================
-- FIX USERS IN WRONG GROUPS
-- ===================================================================
-- This will REMOVE users from wrong groups and ADD them to correct groups
-- Based on GPS coordinates (geographic matching)
-- ===================================================================
-- IMPORTANT: This will affect 126 users
-- ===================================================================

-- Step 1: Show what will be changed (preview)
SELECT '=== PREVIEW: What will be changed ===' as '';
SELECT '(First 10 users shown as example)' as '';

SELECT
    u.id as user_id,
    u.name as user_name,
    current_group.name as will_remove_from,
    (
        SELECT g.name
        FROM `groups` g
        WHERE g.tenant_id = 2
        AND g.type_id = 26
        AND (g.visibility IS NULL OR g.visibility = 'public')
        AND g.latitude IS NOT NULL
        AND g.longitude IS NOT NULL
        AND NOT EXISTS (SELECT 1 FROM `groups` c WHERE c.parent_id = g.id)
        ORDER BY (6371 * acos(
            cos(radians(u.latitude)) *
            cos(radians(g.latitude)) *
            cos(radians(g.longitude) - radians(u.longitude)) +
            sin(radians(u.latitude)) *
            sin(radians(g.latitude))
        )) ASC
        LIMIT 1
    ) as will_add_to,
    CONCAT('Will move ', u.name, ' from ', current_group.name, ' to correct location') as action
FROM users u
JOIN group_members gm ON gm.user_id = u.id
JOIN `groups` current_group ON current_group.id = gm.group_id
    AND current_group.tenant_id = 2
    AND current_group.type_id = 26
    AND NOT EXISTS (SELECT 1 FROM `groups` c WHERE c.parent_id = current_group.id)
WHERE u.tenant_id = 2
AND u.status = 'active'
AND u.latitude IS NOT NULL
AND u.longitude IS NOT NULL
AND current_group.id != (
    SELECT g.id
    FROM `groups` g
    WHERE g.tenant_id = 2
    AND g.type_id = 26
    AND (g.visibility IS NULL OR g.visibility = 'public')
    AND g.latitude IS NOT NULL
    AND g.longitude IS NOT NULL
    AND NOT EXISTS (SELECT 1 FROM `groups` c WHERE c.parent_id = g.id)
    ORDER BY (6371 * acos(
        cos(radians(u.latitude)) *
        cos(radians(g.latitude)) *
        cos(radians(g.longitude) - radians(u.longitude)) +
        sin(radians(u.latitude)) *
        sin(radians(g.latitude))
    )) ASC
    LIMIT 1
)
LIMIT 10;

SELECT '' as '';
SELECT '=== WARNING ===' as '';
SELECT 'This will affect 126 users total' as '';
SELECT 'Review the preview above before continuing' as '';
SELECT '' as '';
SELECT '=== TO PROCEED ===' as '';
SELECT 'Uncomment the DELETE and INSERT sections below' as '';
SELECT 'Then run this script again' as '';
SELECT '' as '';

-- ===================================================================
-- Step 2: Remove from wrong groups
-- ===================================================================
-- UNCOMMENT BELOW TO EXECUTE:

/*
DELETE gm
FROM group_members gm
JOIN `groups` g ON g.id = gm.group_id
JOIN users u ON u.id = gm.user_id
WHERE g.tenant_id = 2
AND g.type_id = 26
AND NOT EXISTS (SELECT 1 FROM `groups` c WHERE c.parent_id = g.id)
AND u.tenant_id = 2
AND u.status = 'active'
AND u.latitude IS NOT NULL
AND u.longitude IS NOT NULL
AND g.id != (
    SELECT nearest.id
    FROM `groups` nearest
    WHERE nearest.tenant_id = 2
    AND nearest.type_id = 26
    AND (nearest.visibility IS NULL OR nearest.visibility = 'public')
    AND nearest.latitude IS NOT NULL
    AND nearest.longitude IS NOT NULL
    AND NOT EXISTS (SELECT 1 FROM `groups` c WHERE c.parent_id = nearest.id)
    ORDER BY (6371 * acos(
        cos(radians(u.latitude)) *
        cos(radians(nearest.latitude)) *
        cos(radians(nearest.longitude) - radians(u.longitude)) +
        sin(radians(u.latitude)) *
        sin(radians(nearest.latitude))
    )) ASC
    LIMIT 1
);
*/

-- ===================================================================
-- Step 3: Add to correct groups
-- ===================================================================
-- UNCOMMENT BELOW TO EXECUTE:

/*
INSERT INTO group_members (group_id, user_id, joined_at, status, role)
SELECT
    nearest_group.id as group_id,
    u.id as user_id,
    NOW() as joined_at,
    'active' as status,
    'member' as role
FROM users u
CROSS JOIN (
    SELECT
        u2.id as user_id,
        (
            SELECT g.id
            FROM `groups` g
            WHERE g.tenant_id = 2
            AND g.type_id = 26
            AND (g.visibility IS NULL OR g.visibility = 'public')
            AND g.latitude IS NOT NULL
            AND g.longitude IS NOT NULL
            AND NOT EXISTS (SELECT 1 FROM `groups` c WHERE c.parent_id = g.id)
            ORDER BY (6371 * acos(
                cos(radians(u2.latitude)) *
                cos(radians(g.latitude)) *
                cos(radians(g.longitude) - radians(u2.longitude)) +
                sin(radians(u2.latitude)) *
                sin(radians(g.latitude))
            )) ASC
            LIMIT 1
        ) as nearest_group_id
    FROM users u2
    WHERE u2.tenant_id = 2
    AND u2.status = 'active'
    AND u2.latitude IS NOT NULL
    AND u2.longitude IS NOT NULL
) nearest_group
JOIN `groups` g ON g.id = nearest_group.nearest_group_id
WHERE u.id = nearest_group.user_id
AND u.tenant_id = 2
AND NOT EXISTS (
    SELECT 1
    FROM group_members gm
    WHERE gm.group_id = nearest_group.nearest_group_id
    AND gm.user_id = u.id
);
*/

-- ===================================================================
-- Step 4: Add to parent groups (cascade)
-- ===================================================================
-- UNCOMMENT BELOW TO EXECUTE:

/*
-- This adds users to county/province/country groups above their town
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
AND parent.type_id = 26
AND NOT EXISTS (
    SELECT 1
    FROM group_members existing
    WHERE existing.group_id = parent.id
    AND existing.user_id = gm.user_id
);
*/

SELECT '' as '';
SELECT '=== INSTRUCTIONS ===' as '';
SELECT '1. Review the PREVIEW above' as '';
SELECT '2. Uncomment the Step 2, 3, and 4 sections (remove /* and */)' as '';
SELECT '3. Run this script again to execute the changes' as '';
SELECT '4. This will move 126 users to their correct geographic groups' as '';

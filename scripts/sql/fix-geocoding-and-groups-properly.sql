-- ===================================================================
-- FIX GEOCODING ERRORS AND GROUP ASSIGNMENTS
-- ===================================================================
-- Two-phase approach:
-- Phase 1: Fix bad GPS coordinates (users whose text doesn't match GPS)
-- Phase 2: Move users who are in genuinely wrong groups
-- ===================================================================

-- =====================================================================
-- PHASE 1: FIX BAD GPS COORDINATES
-- =====================================================================

SELECT '=== PHASE 1: FIXING BAD GPS COORDINATES ===' as '';
SELECT 'These users have location text that does not match their GPS coordinates' as '';
SELECT '' as '';

-- Preview: Users whose location text says one town but GPS says another
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
JOIN `groups` g ON (
    u.location COLLATE utf8mb4_unicode_ci LIKE CONCAT('%', g.name COLLATE utf8mb4_unicode_ci, '%')
    AND g.tenant_id = 2
    AND g.type_id = 26
    AND NOT EXISTS (SELECT 1 FROM `groups` c WHERE c.parent_id = g.id)
)
WHERE u.tenant_id = 2
AND u.status = 'active'
AND u.latitude IS NOT NULL
AND u.longitude IS NOT NULL
-- GPS doesn't match the group they mention in location text
AND (6371 * acos(
    cos(radians(u.latitude)) *
    cos(radians(g.latitude)) *
    cos(radians(g.longitude) - radians(u.longitude)) +
    sin(radians(u.latitude)) *
    sin(radians(g.latitude))
)) > 15  -- More than 15km difference = bad geocoding
LIMIT 20;

SELECT '' as '';
SELECT 'UNCOMMENT BELOW TO FIX GPS COORDINATES:' as '';

/*
-- Fix Bantry users (location says Bantry but GPS is wrong)
UPDATE users u
JOIN `groups` g ON g.name = 'Bantry' AND g.tenant_id = 2 AND g.type_id = 26
SET u.latitude = g.latitude, u.longitude = g.longitude
WHERE u.tenant_id = 2
AND u.location COLLATE utf8mb4_unicode_ci LIKE '%Bantry%'
AND (6371 * acos(
    cos(radians(u.latitude)) *
    cos(radians(g.latitude)) *
    cos(radians(g.longitude) - radians(u.longitude)) +
    sin(radians(u.latitude)) *
    sin(radians(g.latitude))
)) > 15;

-- Fix Killarney users
UPDATE users u
JOIN `groups` g ON g.name = 'Killarney' AND g.tenant_id = 2 AND g.type_id = 26
SET u.latitude = g.latitude, u.longitude = g.longitude
WHERE u.tenant_id = 2
AND u.location COLLATE utf8mb4_unicode_ci LIKE '%Killarney%'
AND (6371 * acos(
    cos(radians(u.latitude)) *
    cos(radians(g.latitude)) *
    cos(radians(g.longitude) - radians(u.longitude)) +
    sin(radians(u.latitude)) *
    sin(radians(g.latitude))
)) > 15;

-- Fix all users where location text matches a group name but GPS doesn't
UPDATE users u
JOIN `groups` g ON (
    u.location COLLATE utf8mb4_unicode_ci LIKE CONCAT('%', g.name COLLATE utf8mb4_unicode_ci, '%')
    AND g.tenant_id = 2
    AND g.type_id = 26
    AND NOT EXISTS (SELECT 1 FROM `groups` c WHERE c.parent_id = g.id)
)
SET u.latitude = g.latitude, u.longitude = g.longitude
WHERE u.tenant_id = 2
AND u.status = 'active'
AND u.latitude IS NOT NULL
AND (6371 * acos(
    cos(radians(u.latitude)) *
    cos(radians(g.latitude)) *
    cos(radians(g.longitude) - radians(u.longitude)) +
    sin(radians(u.latitude)) *
    sin(radians(g.latitude))
)) > 15;

SELECT 'GPS coordinates fixed!' as '';
*/

-- =====================================================================
-- PHASE 2: MOVE USERS IN GENUINELY WRONG GROUPS
-- =====================================================================

SELECT '' as '';
SELECT '=== PHASE 2: MOVE USERS TO CORRECT GROUPS ===' as '';
SELECT 'After fixing GPS, these users are still in wrong groups:' as '';
SELECT '' as '';

-- This will be much smaller list after Phase 1 GPS fixes
SELECT
    u.id as user_id,
    u.name,
    current_group.name as currently_in,
    (
        SELECT g.name
        FROM `groups` g
        WHERE g.tenant_id = 2
        AND g.type_id = 26
        AND (g.visibility IS NULL OR g.visibility = 'public')
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
    ) as should_be_in,
    ROUND((6371 * acos(
        cos(radians(u.latitude)) *
        cos(radians(current_group.latitude)) *
        cos(radians(current_group.longitude) - radians(u.longitude)) +
        sin(radians(u.latitude)) *
        sin(radians(current_group.latitude))
    )), 2) as distance_from_current,
    'Will move to correct group' as action
FROM users u
JOIN group_members gm ON gm.user_id = u.id
JOIN `groups` current_group ON current_group.id = gm.group_id
    AND current_group.tenant_id = 2
    AND current_group.type_id = 26
    AND NOT EXISTS (SELECT 1 FROM `groups` c WHERE c.parent_id = current_group.id)
WHERE u.tenant_id = 2
AND u.status = 'active'
AND u.latitude IS NOT NULL
AND current_group.id != (
    SELECT g.id
    FROM `groups` g
    WHERE g.tenant_id = 2
    AND g.type_id = 26
    AND (g.visibility IS NULL OR g.visibility = 'public')
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
)
LIMIT 20;

SELECT '' as '';
SELECT 'UNCOMMENT BELOW TO MOVE USERS TO CORRECT GROUPS:' as '';

/*
-- Remove from wrong bottom-level groups
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
AND g.id != (
    SELECT nearest.id
    FROM `groups` nearest
    WHERE nearest.tenant_id = 2
    AND nearest.type_id = 26
    AND (nearest.visibility IS NULL OR nearest.visibility = 'public')
    AND nearest.latitude IS NOT NULL
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

-- Add to correct bottom-level groups
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
        g.id,
        g.latitude,
        g.longitude,
        u2.id as user_id
    FROM users u2
    JOIN `groups` g ON g.tenant_id = 2
        AND g.type_id = 26
        AND (g.visibility IS NULL OR g.visibility = 'public')
        AND g.latitude IS NOT NULL
        AND NOT EXISTS (SELECT 1 FROM `groups` c WHERE c.parent_id = g.id)
    WHERE u2.tenant_id = 2
    AND u2.status = 'active'
    AND u2.latitude IS NOT NULL
    AND g.id = (
        SELECT g2.id
        FROM `groups` g2
        WHERE g2.tenant_id = 2
        AND g2.type_id = 26
        AND (g2.visibility IS NULL OR g2.visibility = 'public')
        AND g2.latitude IS NOT NULL
        AND NOT EXISTS (SELECT 1 FROM `groups` c WHERE c.parent_id = g2.id)
        ORDER BY (6371 * acos(
            cos(radians(u2.latitude)) *
            cos(radians(g2.latitude)) *
            cos(radians(g2.longitude) - radians(u2.longitude)) +
            sin(radians(u2.latitude)) *
            sin(radians(g2.latitude))
        )) ASC
        LIMIT 1
    )
) nearest_group
WHERE u.id = nearest_group.user_id
AND NOT EXISTS (
    SELECT 1
    FROM group_members gm
    WHERE gm.group_id = nearest_group.id
    AND gm.user_id = u.id
);

-- Remove from wrong parent groups
DELETE gm
FROM group_members gm
JOIN `groups` g ON g.id = gm.group_id
WHERE g.tenant_id = 2
AND g.type_id = 26
AND EXISTS (SELECT 1 FROM `groups` c WHERE c.parent_id = g.id)  -- Is a parent group
AND NOT EXISTS (
    -- User should be in this parent group
    SELECT 1
    FROM group_members child_gm
    JOIN `groups` child_g ON child_g.id = child_gm.group_id
    WHERE child_gm.user_id = gm.user_id
    AND child_g.tenant_id = 2
    AND child_g.type_id = 26
    AND (child_g.parent_id = g.id
         OR child_g.parent_id IN (SELECT id FROM `groups` WHERE parent_id = g.id)
         OR child_g.parent_id IN (SELECT id FROM `groups` WHERE parent_id IN (SELECT id FROM `groups` WHERE parent_id = g.id)))
);

-- Add to correct parent groups
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

SELECT 'Users moved to correct groups!' as '';
*/

-- =====================================================================
-- FINAL VERIFICATION
-- =====================================================================

SELECT '' as '';
SELECT '=== VERIFICATION ===' as '';
SELECT 'After running both phases, check:' as '';
SELECT '1. All users have GPS matching their location text' as '';
SELECT '2. All users are in their nearest group' as '';
SELECT '3. All users are in appropriate parent groups' as '';

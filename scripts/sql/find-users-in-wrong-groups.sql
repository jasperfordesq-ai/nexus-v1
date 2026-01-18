-- ===================================================================
-- FIND USERS IN WRONG GROUPS
-- ===================================================================
-- This query finds users who are in groups that DON'T match their GPS location
-- ===================================================================

SELECT '=== USERS WHO MAY BE IN WRONG GROUPS ===' as '';

SELECT
    u.id as user_id,
    u.name as user_name,
    u.location as user_location,
    ROUND(u.latitude, 4) as user_lat,
    ROUND(u.longitude, 4) as user_lng,
    current_group.name as currently_in_group,
    ROUND((6371 * acos(
        cos(radians(u.latitude)) *
        cos(radians(current_group.latitude)) *
        cos(radians(current_group.longitude) - radians(u.longitude)) +
        sin(radians(u.latitude)) *
        sin(radians(current_group.latitude))
    )), 2) as current_distance_km,
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
    ) as should_be_in_group,
    ROUND((
        SELECT MIN(6371 * acos(
            cos(radians(u.latitude)) *
            cos(radians(g.latitude)) *
            cos(radians(g.longitude) - radians(u.longitude)) +
            sin(radians(u.latitude)) *
            sin(radians(g.latitude))
        ))
        FROM `groups` g
        WHERE g.tenant_id = 2
        AND g.type_id = 26
        AND (g.visibility IS NULL OR g.visibility = 'public')
        AND g.latitude IS NOT NULL
        AND g.longitude IS NOT NULL
        AND NOT EXISTS (SELECT 1 FROM `groups` c WHERE c.parent_id = g.id)
    ), 2) as correct_distance_km,
    '✗ WRONG GROUP' as status
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
    -- Find nearest group ID
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
ORDER BY current_distance_km DESC;

-- Summary
SELECT '' as '';
SELECT '=== SUMMARY ===' as '';

SELECT
    (SELECT COUNT(DISTINCT u.id)
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
    ) as total_users_in_wrong_groups,
    'Run fix script to move them to correct groups' as action_needed;

-- ===================================================================
-- PRE-FLIGHT CHECK: Smart Matching Readiness
-- ===================================================================
-- Run this BEFORE running smart-match-users to verify everything is ready
-- ===================================================================

-- 1. Check groups have coordinates
SELECT '=== 1. GROUPS COORDINATE STATUS ===' as info;

SELECT
    COUNT(*) as total_hub_groups,
    COUNT(CASE WHEN latitude IS NOT NULL AND longitude IS NOT NULL THEN 1 END) as groups_with_coords,
    COUNT(CASE WHEN latitude IS NULL OR longitude IS NULL THEN 1 END) as groups_without_coords,
    ROUND(100.0 * COUNT(CASE WHEN latitude IS NOT NULL THEN 1 END) / COUNT(*), 1) as percent_ready
FROM `groups`
WHERE tenant_id = 2
AND type_id = 26
AND (visibility IS NULL OR visibility = 'public');

-- 2. Check bottom-level groups (most important for matching)
SELECT '=== 2. BOTTOM-LEVEL GROUPS STATUS ===' as info;

SELECT
    COUNT(*) as total_bottom_level,
    COUNT(CASE WHEN latitude IS NOT NULL AND longitude IS NOT NULL THEN 1 END) as with_coords,
    COUNT(CASE WHEN latitude IS NULL OR longitude IS NULL THEN 1 END) as without_coords,
    ROUND(100.0 * COUNT(CASE WHEN latitude IS NOT NULL THEN 1 END) / COUNT(*), 1) as percent_ready
FROM `groups`
WHERE tenant_id = 2
AND type_id = 26
AND (visibility IS NULL OR visibility = 'public')
AND NOT EXISTS (SELECT 1 FROM `groups` c WHERE c.parent_id = `groups`.id);

-- 3. Check users have location data
SELECT '=== 3. USER LOCATION DATA STATUS ===' as info;

SELECT
    COUNT(*) as total_active_users,
    COUNT(CASE WHEN latitude IS NOT NULL AND longitude IS NOT NULL THEN 1 END) as with_gps_coords,
    COUNT(CASE WHEN location IS NOT NULL AND location != '' THEN 1 END) as with_location_text,
    COUNT(CASE WHEN (latitude IS NOT NULL OR location IS NOT NULL) THEN 1 END) as can_be_matched,
    COUNT(CASE WHEN latitude IS NULL AND (location IS NULL OR location = '') THEN 1 END) as cannot_be_matched,
    ROUND(100.0 * COUNT(CASE WHEN (latitude IS NOT NULL OR location IS NOT NULL) THEN 1 END) / COUNT(*), 1) as percent_matchable
FROM users
WHERE tenant_id = 2
AND status = 'active';

-- 4. Current group membership status (before matching)
SELECT '=== 4. CURRENT MEMBERSHIP STATUS ===' as info;

SELECT
    (SELECT COUNT(*) FROM users WHERE tenant_id = 2 AND status = 'active') as total_users,
    COUNT(DISTINCT gm.user_id) as users_in_groups,
    COUNT(DISTINCT gm.group_id) as groups_with_members,
    COUNT(*) as total_memberships,
    (SELECT COUNT(*) FROM users WHERE tenant_id = 2 AND status = 'active') -
    COUNT(DISTINCT gm.user_id) as users_without_groups
FROM group_members gm
JOIN `groups` g ON gm.group_id = g.id
WHERE g.tenant_id = 2
AND g.type_id = 26;

-- 5. Show geographic coverage estimate (how many users can be matched)
SELECT '=== 5. GEOGRAPHIC COVERAGE ESTIMATE ===' as info;

SELECT
    COUNT(CASE WHEN can_match = 1 THEN 1 END) as users_within_50km,
    COUNT(CASE WHEN can_match = 0 THEN 1 END) as users_too_far,
    COUNT(*) as total_users_with_coords,
    ROUND(100.0 * COUNT(CASE WHEN can_match = 1 THEN 1 END) / COUNT(*), 1) as percent_can_match
FROM (
    SELECT
        u.id,
        CASE
            WHEN (
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
            ) <= 50 THEN 1
            ELSE 0
        END as can_match
    FROM users u
    WHERE u.tenant_id = 2
    AND u.status = 'active'
    AND u.latitude IS NOT NULL
    AND u.longitude IS NOT NULL
) as coverage;

-- 6. Summary readiness check
SELECT '=== 6. READINESS SUMMARY ===' as info;

SELECT
    CASE
        WHEN groups_ready >= 90 AND users_ready >= 50 THEN '✓ READY TO MATCH'
        WHEN groups_ready >= 70 THEN '⚠ MOSTLY READY (some groups missing coords)'
        ELSE '✗ NOT READY (geocode more groups first)'
    END as status,
    CONCAT(groups_ready, '% of groups have coordinates') as groups_status,
    CONCAT(users_ready, '% of users have location data') as users_status
FROM (
    SELECT
        ROUND(100.0 * (SELECT COUNT(*) FROM `groups` WHERE tenant_id = 2 AND type_id = 26 AND latitude IS NOT NULL) /
                      (SELECT COUNT(*) FROM `groups` WHERE tenant_id = 2 AND type_id = 26), 1) as groups_ready,
        ROUND(100.0 * (SELECT COUNT(*) FROM users WHERE tenant_id = 2 AND status = 'active' AND (latitude IS NOT NULL OR location IS NOT NULL)) /
                      (SELECT COUNT(*) FROM users WHERE tenant_id = 2 AND status = 'active'), 1) as users_ready
) stats;

-- ===================================================================
-- INTERPRETATION:
-- ===================================================================
-- Section 1: Shows overall group geocoding success
-- Section 2: Shows bottom-level groups (most important) geocoding
-- Section 3: Shows how many users have location data
-- Section 4: Current membership before running smart match
-- Section 5: Preview of which users will match (first 30)
-- Section 6: Overall readiness - should say "READY TO MATCH"
--
-- THRESHOLDS:
-- - Groups: Need 90%+ geocoded for good results
-- - Users: Need 50%+ with location data
-- ===================================================================

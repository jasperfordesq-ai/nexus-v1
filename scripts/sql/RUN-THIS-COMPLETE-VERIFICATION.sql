-- ===================================================================
-- COMPLETE VERIFICATION AND FIX - ALL IN ONE
-- ===================================================================
-- Copy this ENTIRE file and paste into phpMyAdmin SQL tab
-- ===================================================================

-- ===================================================================
-- PART 1: CHECK GEOCODING RESULTS
-- ===================================================================

SELECT '========================================' as '';
SELECT '=== PART 1: GEOCODING RESULTS CHECK ===' as '';
SELECT '========================================' as '';

SELECT '1. Overall Statistics:' as '';

SELECT
    COUNT(*) as total_groups,
    COUNT(CASE WHEN latitude IS NOT NULL AND longitude IS NOT NULL THEN 1 END) as with_coordinates,
    COUNT(CASE WHEN latitude IS NULL OR longitude IS NULL THEN 1 END) as missing_coordinates,
    ROUND(100.0 * COUNT(CASE WHEN latitude IS NOT NULL THEN 1 END) / COUNT(*), 1) as percent_complete
FROM `groups`
WHERE tenant_id = 2
AND type_id = 26
AND (visibility IS NULL OR visibility = 'public');

SELECT '2. Bottom-level groups (towns/localities):' as '';

SELECT
    COUNT(*) as total_bottom_level,
    COUNT(CASE WHEN latitude IS NOT NULL THEN 1 END) as with_coords,
    COUNT(CASE WHEN latitude IS NULL THEN 1 END) as missing_coords
FROM `groups`
WHERE tenant_id = 2
AND type_id = 26
AND NOT EXISTS (SELECT 1 FROM `groups` c WHERE c.parent_id = `groups`.id);

SELECT '3. Groups WITHOUT coordinates (these failed):' as '';

SELECT
    g.id,
    g.name,
    g.location
FROM `groups` g
WHERE g.tenant_id = 2
AND g.type_id = 26
AND (g.latitude IS NULL OR g.longitude IS NULL)
AND NOT EXISTS (SELECT 1 FROM `groups` c WHERE c.parent_id = g.id)
ORDER BY g.name;

-- ===================================================================
-- PART 2: FIX FAILED GROUPS
-- ===================================================================

SELECT '========================================' as '';
SELECT '=== PART 2: FIXING FAILED GROUPS ===' as '';
SELECT '========================================' as '';

SELECT 'Before fix - checking Westmeath towns:' as '';

SELECT id, name, location, latitude, longitude
FROM `groups`
WHERE tenant_id = 2
AND name IN ('Athlone', 'Kilbeggan', 'Multyfarnham', 'Rochfortbridge');

-- Add coordinates
UPDATE `groups` SET latitude = 53.4239, longitude = -7.9406
WHERE name = 'Athlone' AND tenant_id = 2 AND (latitude IS NULL OR longitude IS NULL);

UPDATE `groups` SET latitude = 53.3769, longitude = -7.5058
WHERE name = 'Kilbeggan' AND tenant_id = 2 AND (latitude IS NULL OR longitude IS NULL);

UPDATE `groups` SET latitude = 53.6406, longitude = -7.4169
WHERE name = 'Multyfarnham' AND tenant_id = 2 AND (latitude IS NULL OR longitude IS NULL);

UPDATE `groups` SET latitude = 53.4206, longitude = -7.3039
WHERE name = 'Rochfortbridge' AND tenant_id = 2 AND (latitude IS NULL OR longitude IS NULL);

SELECT 'After fix - Westmeath towns now have coordinates:' as '';

SELECT
    id,
    name,
    ROUND(latitude, 4) as latitude,
    ROUND(longitude, 4) as longitude,
    'FIXED ✓' as status
FROM `groups`
WHERE tenant_id = 2
AND name IN ('Athlone', 'Kilbeggan', 'Multyfarnham', 'Rochfortbridge');

-- ===================================================================
-- PART 3: PRE-FLIGHT CHECK
-- ===================================================================

SELECT '========================================' as '';
SELECT '=== PART 3: PRE-FLIGHT CHECK ===' as '';
SELECT '========================================' as '';

SELECT '1. Groups coordinate status (should be 100%):' as '';

SELECT
    COUNT(*) as total_hub_groups,
    COUNT(CASE WHEN latitude IS NOT NULL AND longitude IS NOT NULL THEN 1 END) as groups_with_coords,
    COUNT(CASE WHEN latitude IS NULL OR longitude IS NULL THEN 1 END) as groups_without_coords,
    ROUND(100.0 * COUNT(CASE WHEN latitude IS NOT NULL THEN 1 END) / COUNT(*), 1) as percent_ready
FROM `groups`
WHERE tenant_id = 2
AND type_id = 26
AND (visibility IS NULL OR visibility = 'public');

SELECT '2. User location data status:' as '';

SELECT
    COUNT(*) as total_active_users,
    COUNT(CASE WHEN latitude IS NOT NULL AND longitude IS NOT NULL THEN 1 END) as with_gps_coords,
    COUNT(CASE WHEN location IS NOT NULL AND location != '' THEN 1 END) as with_location_text,
    COUNT(CASE WHEN (latitude IS NOT NULL OR location IS NOT NULL) THEN 1 END) as can_be_matched,
    ROUND(100.0 * COUNT(CASE WHEN (latitude IS NOT NULL OR location IS NOT NULL) THEN 1 END) / COUNT(*), 1) as percent_matchable
FROM users
WHERE tenant_id = 2
AND status = 'active';

SELECT '3. Current membership status (before smart matching):' as '';

SELECT
    (SELECT COUNT(*) FROM users WHERE tenant_id = 2 AND status = 'active') as total_users,
    COUNT(DISTINCT gm.user_id) as users_already_in_groups,
    COUNT(DISTINCT gm.group_id) as groups_with_members,
    COUNT(*) as total_memberships
FROM group_members gm
JOIN `groups` g ON gm.group_id = g.id
WHERE g.tenant_id = 2
AND g.type_id = 26;

SELECT '4. FINAL READINESS CHECK:' as '';

SELECT
    CASE
        WHEN groups_ready >= 90 AND users_ready >= 50 THEN '✓✓✓ READY TO MATCH ✓✓✓'
        WHEN groups_ready >= 70 THEN '⚠ MOSTLY READY (some groups missing coords)'
        ELSE '✗ NOT READY (geocode more groups first)'
    END as READINESS_STATUS,
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
-- DONE!
-- ===================================================================

SELECT '========================================' as '';
SELECT '=== VERIFICATION COMPLETE ===' as '';
SELECT '========================================' as '';

SELECT 'If you see "✓✓✓ READY TO MATCH ✓✓✓" above, you can now run:' as '';
SELECT 'https://hour-timebank.ie/admin/smart-match-users' as '';
SELECT '========================================' as '';

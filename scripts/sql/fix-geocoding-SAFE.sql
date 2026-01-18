-- ===================================================================
-- FIX GEOCODING ERRORS - SAFE VERSION
-- ===================================================================
-- Only fixes EXACT town name matches (no substring matching)
-- Avoids false matches like "Knock" matching "Knockroe"
-- ===================================================================

SELECT '=== SAFE GPS COORDINATE FIXES ===' as '';
SELECT 'Only fixing users with EXACT town name matches' as '';
SELECT '' as '';

-- Preview: Show what will be fixed
SELECT
    u.id,
    u.name,
    u.location,
    ROUND(u.latitude, 4) as current_lat,
    ROUND(u.longitude, 4) as current_lng,
    g.name as town,
    ROUND(g.latitude, 4) as correct_lat,
    ROUND(g.longitude, 4) as correct_lng,
    ROUND((6371 * acos(
        cos(radians(u.latitude)) *
        cos(radians(g.latitude)) *
        cos(radians(g.longitude) - radians(u.longitude)) +
        sin(radians(u.latitude)) *
        sin(radians(g.latitude))
    )), 2) as distance_difference_km,
    'Will fix GPS' as action
FROM users u
JOIN `groups` g ON g.tenant_id = 2
    AND g.type_id = 26
    AND NOT EXISTS (SELECT 1 FROM `groups` c WHERE c.parent_id = g.id)
WHERE u.tenant_id = 2
AND u.status = 'active'
AND u.latitude IS NOT NULL
-- EXACT match: location starts with town name followed by comma or "Co."
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
SELECT 'TOTAL TO FIX:' as '';

SELECT COUNT(*) as total_users_to_fix
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
)) > 15;

SELECT '' as '';
SELECT '=== UNCOMMENT BELOW TO FIX ===' as '';
SELECT '' as '';

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

SELECT 'GPS coordinates fixed!' as '';
SELECT ROW_COUNT() as users_updated;
*/

-- ===================================================================
-- EXAMPLES OF WHAT THIS MATCHES:
-- ===================================================================
-- ✓ "Bantry, Co. Cork, Ireland" → Bantry group
-- ✓ "Clonakilty, County Cork, Ireland" → Clonakilty group
-- ✓ "Mullingar, Co. Westmeath" → Mullingar group
--
-- ✗ "Knockroe, Ballydehob, Co. Cork" → Does NOT match "Knock"
-- ✗ "Laherfineen, Inishannon, Co. Cork" → Does NOT match "Shannon"
-- ✗ "Ballyquin, Tuamgraney, Co. Clare" → Does NOT match "Tuam"
-- ===================================================================

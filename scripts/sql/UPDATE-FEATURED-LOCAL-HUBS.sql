-- ===================================================================
-- UPDATE FEATURED LOCAL HUBS - SMART RANKING
-- ===================================================================
-- This script implements intelligent featured group selection:
-- 1. Clears all existing featured flags
-- 2. Selects top 6 bottom-level hubs by member count
-- 3. Ensures geographic diversity (max 2 per county)
-- 4. Sets featured flags
--
-- Run this daily via cron or manually to keep featured hubs fresh
-- ===================================================================

-- PREVIEW MODE: Shows what will be featured
SELECT '=== SMART RANKING PREVIEW ===' as '';
SELECT 'Algorithm: Member Count + Geographic Diversity' as '';
SELECT '' as '';

-- Current featured groups (before update)
SELECT '=== CURRENTLY FEATURED GROUPS ===' as '';

SELECT
    g.id,
    g.name,
    parent.name as county,
    COUNT(DISTINCT gm.user_id) as members
FROM `groups` g
LEFT JOIN group_members gm ON gm.group_id = g.id AND gm.status = 'active'
LEFT JOIN `groups` parent ON parent.id = g.parent_id
WHERE g.tenant_id = 2
AND g.type_id = 26
AND g.is_featured = 1
GROUP BY g.id, g.name, parent.name
ORDER BY members DESC;

SELECT '' as '';
SELECT '=== NEW FEATURED GROUPS (Top 6) ===' as '';
SELECT 'Max 2 per county for geographic diversity' as '';

-- Show what WILL be featured
WITH hub_rankings AS (
    SELECT
        g.id,
        g.name,
        g.parent_id,
        COUNT(DISTINCT gm.user_id) as member_count,
        parent_group.name as county_name,
        ROW_NUMBER() OVER (
            PARTITION BY g.parent_id
            ORDER BY COUNT(DISTINCT gm.user_id) DESC
        ) as rank_in_county
    FROM `groups` g
    LEFT JOIN group_members gm ON gm.group_id = g.id AND gm.status = 'active'
    LEFT JOIN `groups` parent_group ON parent_group.id = g.parent_id
    WHERE g.tenant_id = 2
    AND g.type_id = 26
    -- Only bottom-level groups (no children)
    AND NOT EXISTS (
        SELECT 1 FROM `groups` child
        WHERE child.parent_id = g.id
    )
    -- Exclude private groups
    AND (g.visibility IS NULL OR g.visibility != 'private')
    GROUP BY g.id, g.name, g.parent_id, parent_group.name
)
SELECT
    id,
    name,
    county_name,
    member_count,
    rank_in_county,
    'WILL BE FEATURED' as status
FROM hub_rankings
WHERE rank_in_county <= 2
ORDER BY member_count DESC
LIMIT 6;

SELECT '' as '';
SELECT '=== INSTRUCTIONS ===' as '';
SELECT 'To execute this update:' as '';
SELECT '1. Review the NEW FEATURED GROUPS above' as '';
SELECT '2. Uncomment the EXECUTE section below' as '';
SELECT '3. Run this script again' as '';
SELECT '' as '';
SELECT 'OR use the web interface:' as '';
SELECT 'Visit: https://hour-timebank.ie/admin/group-ranking' as '';
SELECT 'Click: "Update Featured Groups Now"' as '';

-- ===================================================================
-- EXECUTE MODE: Uncomment to run the update
-- ===================================================================

/*
-- Step 1: Clear all featured flags for hub groups
UPDATE `groups`
SET is_featured = 0
WHERE tenant_id = 2
AND type_id = 26;

SELECT CONCAT('✓ Cleared ', ROW_COUNT(), ' featured flags') as result;

-- Step 2: Set featured flags for top 6 hubs (with geographic diversity)
UPDATE `groups` g
INNER JOIN (
    WITH hub_rankings AS (
        SELECT
            g.id,
            g.name,
            g.parent_id,
            COUNT(DISTINCT gm.user_id) as member_count,
            ROW_NUMBER() OVER (
                PARTITION BY g.parent_id
                ORDER BY COUNT(DISTINCT gm.user_id) DESC
            ) as rank_in_county
        FROM `groups` g
        LEFT JOIN group_members gm ON gm.group_id = g.id AND gm.status = 'active'
        WHERE g.tenant_id = 2
        AND g.type_id = 26
        AND NOT EXISTS (
            SELECT 1 FROM `groups` child
            WHERE child.parent_id = g.id
        )
        AND (g.visibility IS NULL OR g.visibility != 'private')
        GROUP BY g.id, g.name, g.parent_id
    )
    SELECT id
    FROM hub_rankings
    WHERE rank_in_county <= 2
    ORDER BY member_count DESC
    LIMIT 6
) featured ON featured.id = g.id
SET g.is_featured = 1;

SELECT CONCAT('✓ Featured ', ROW_COUNT(), ' hub groups') as result;

-- Step 3: Show results
SELECT '' as '';
SELECT '=== UPDATE COMPLETE ===' as '';

SELECT
    g.id,
    g.name,
    parent.name as county,
    COUNT(DISTINCT gm.user_id) as members,
    '⭐ FEATURED' as status
FROM `groups` g
LEFT JOIN group_members gm ON gm.group_id = g.id AND gm.status = 'active'
LEFT JOIN `groups` parent ON parent.id = g.parent_id
WHERE g.tenant_id = 2
AND g.type_id = 26
AND g.is_featured = 1
GROUP BY g.id, g.name, parent.name
ORDER BY members DESC;
*/

-- ===================================================================
-- VERIFICATION QUERIES (Safe to run anytime)
-- ===================================================================

SELECT '' as '';
SELECT '=== VERIFICATION ===' as '';

-- Count featured groups
SELECT
    'Total Featured Hubs' as metric,
    COUNT(*) as count
FROM `groups`
WHERE tenant_id = 2
AND type_id = 26
AND is_featured = 1;

-- Check distribution by county
SELECT
    parent.name as county,
    COUNT(*) as featured_hubs
FROM `groups` g
LEFT JOIN `groups` parent ON parent.id = g.parent_id
WHERE g.tenant_id = 2
AND g.type_id = 26
AND g.is_featured = 1
GROUP BY parent.name
ORDER BY featured_hubs DESC;

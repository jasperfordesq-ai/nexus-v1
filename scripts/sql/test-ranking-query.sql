-- Test the ranking query to see if it works

-- Check if we have hub type
SELECT * FROM group_types WHERE is_hub = 1;

-- Test the ranking query
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
AND (g.visibility IS NULL OR g.visibility = 'public')
GROUP BY g.id, g.name, g.parent_id, parent_group.name
ORDER BY member_count DESC
LIMIT 20;

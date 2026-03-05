-- ============================================================================
-- Add "Never Logged In" Newsletter Segment to All Tenants
-- ============================================================================
-- This adds the "Never Logged In" segment to all existing tenants that don't
-- already have it. This segment targets members who have never logged into
-- the app - perfect for re-engagement campaigns.
-- ============================================================================

-- Insert the segment for each tenant that doesn't already have it
INSERT INTO newsletter_segments (tenant_id, name, description, rules, is_active, created_at)
SELECT
    t.id as tenant_id,
    'Never Logged In' as name,
    'Members who have never logged in to the app - perfect for re-engagement campaigns' as description,
    '{"match":"all","conditions":[{"field":"login_recency","operator":"equals","value":"never"}]}' as rules,
    1 as is_active,
    NOW() as created_at
FROM tenants t
WHERE t.is_active = 1
AND NOT EXISTS (
    SELECT 1 FROM newsletter_segments ns
    WHERE ns.tenant_id = t.id
    AND ns.name = 'Never Logged In'
);

-- Report how many segments were created
SELECT
    CONCAT('Created "Never Logged In" segment for ', ROW_COUNT(), ' tenant(s)') as result;

-- Show count of never-logged-in users per tenant (for reference)
SELECT
    t.id as tenant_id,
    t.name as tenant_name,
    COUNT(u.id) as never_logged_in_count
FROM tenants t
LEFT JOIN users u ON u.tenant_id = t.id
    AND u.is_approved = 1
    AND u.last_login_at IS NULL
WHERE t.is_active = 1
GROUP BY t.id, t.name
ORDER BY never_logged_in_count DESC;

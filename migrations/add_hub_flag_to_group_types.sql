-- ============================================================================
-- ADD HUB FLAG TO GROUP TYPES - Migration
-- ============================================================================
-- This migration adds the is_hub flag to differentiate hub types from regular
-- group types. Hubs are admin-curated geographic communities, while regular
-- groups are user-created interest-based communities.
-- Date: 2026-01-08
-- ============================================================================

-- ============================================================================
-- 1. ADD is_hub COLUMN
-- ============================================================================
ALTER TABLE group_types
ADD COLUMN is_hub TINYINT(1) DEFAULT 0 AFTER is_active;

-- ============================================================================
-- 2. CREATE THE OFFICIAL "LOCAL HUB" TYPE
-- ============================================================================
-- Create a special hub type for each tenant
INSERT INTO group_types (tenant_id, name, slug, description, icon, color, is_hub, sort_order, is_active)
SELECT
    t.id as tenant_id,
    'Local Hub',
    'local-hub',
    'Official geographic community hubs managed by administrators',
    'fa-map-pin',
    '#6366f1',
    1,
    5,
    1
FROM tenants t
WHERE NOT EXISTS (
    SELECT 1 FROM group_types WHERE slug = 'local-hub' AND tenant_id = t.id
);

-- ============================================================================
-- 3. MIGRATE EXISTING GROUPS TO LOCAL HUB TYPE (OPTIONAL)
-- ============================================================================
-- If you have existing groups without a type that should be hubs,
-- uncomment and run this to assign them to the hub type:

-- UPDATE `groups` g
-- SET g.type_id = (
--     SELECT id FROM group_types
--     WHERE tenant_id = g.tenant_id AND slug = 'local-hub'
--     LIMIT 1
-- )
-- WHERE g.type_id IS NULL
-- AND g.tenant_id IN (SELECT id FROM tenants);

-- ============================================================================
-- MIGRATION COMPLETE
-- ============================================================================
-- Summary:
-- ✓ Added is_hub flag to group_types table
-- ✓ Created "Local Hub" type for all tenants
-- ✓ Marked Local Hub type with is_hub = 1
--
-- Next Steps:
-- 1. Update GroupType model with hub methods
-- 2. Update Group model with filtering
-- 3. Update admin interface to manage hub flag
-- 4. Split frontend into /groups (hubs) and /community-groups (regular)
-- ============================================================================

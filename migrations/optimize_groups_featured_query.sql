-- ============================================================================
-- OPTIMIZE GROUPS FEATURED QUERY - Performance Enhancement
-- ============================================================================
-- Addresses slow query in Group::getFeatured() [101ms -> target <10ms]
-- Error log: SLOW QUERY [101.16ms] at HomeController.php:54
--
-- Query being optimized:
-- SELECT g.*, COUNT(gm.id) as member_count
-- FROM `groups` g
-- LEFT JOIN group_members gm ON g.id = gm.group_id
-- LEFT JOIN `groups` child ON g.id = child.parent_id
-- WHERE g.tenant_id = ? AND child.id IS NULL
-- GROUP BY g.id
-- ORDER BY member_count DESC, g.name ASC
-- LIMIT 3
--
-- Created: 2026-01-11
-- ============================================================================

-- ============================================================================
-- 1. ADD COMPOSITE INDEXES FOR QUERY OPTIMIZATION
-- ============================================================================

-- Index for parent_id lookup (used in self-join to find children)
-- This dramatically speeds up: LEFT JOIN `groups` child ON g.id = child.parent_id
CREATE INDEX IF NOT EXISTS idx_parent_id ON `groups` (parent_id);

-- Composite index for tenant + parent lookup
-- Optimizes the main WHERE clause filtering
CREATE INDEX IF NOT EXISTS idx_tenant_parent ON `groups` (tenant_id, parent_id);

-- Index for group_members to speed up COUNT aggregation
-- Optimizes: LEFT JOIN group_members gm ON g.id = gm.group_id
CREATE INDEX IF NOT EXISTS idx_group_members_group_id ON group_members (group_id);

-- Composite index for name sorting (secondary sort criteria)
CREATE INDEX IF NOT EXISTS idx_tenant_name ON `groups` (tenant_id, name);

-- ============================================================================
-- 2. ADD CACHED MEMBER COUNT COLUMN (Denormalization for Performance)
-- ============================================================================

-- Add cached member count to avoid COUNT() on every query
ALTER TABLE `groups`
ADD COLUMN IF NOT EXISTS cached_member_count INT UNSIGNED DEFAULT 0 COMMENT 'Cached count of active members';

-- Add index on cached_member_count for faster sorting
CREATE INDEX IF NOT EXISTS idx_cached_member_count ON `groups` (cached_member_count);

-- Composite index for optimal query: tenant + cached count + name
CREATE INDEX IF NOT EXISTS idx_tenant_membercount_name ON `groups` (tenant_id, cached_member_count DESC, name ASC);

-- ============================================================================
-- 3. POPULATE INITIAL CACHED MEMBER COUNTS
-- ============================================================================

-- Update all existing groups with current member counts
UPDATE `groups` g
SET cached_member_count = (
    SELECT COUNT(*)
    FROM group_members gm
    WHERE gm.group_id = g.id AND gm.status = 'active'
);

-- ============================================================================
-- 4. ADD HAS_CHILDREN FLAG (Avoid Self-Join)
-- ============================================================================

-- Add flag to mark groups that have children (parent groups)
-- This eliminates the need for: LEFT JOIN `groups` child ON g.id = child.parent_id WHERE child.id IS NULL
ALTER TABLE `groups`
ADD COLUMN IF NOT EXISTS has_children BOOLEAN DEFAULT FALSE COMMENT 'Whether this group has sub-groups';

-- Create index on has_children for fast filtering
CREATE INDEX IF NOT EXISTS idx_has_children ON `groups` (has_children);

-- Composite index for leaf node lookup: tenant + no children
CREATE INDEX IF NOT EXISTS idx_tenant_leaf_nodes ON `groups` (tenant_id, has_children);

-- ============================================================================
-- 5. POPULATE HAS_CHILDREN FLAGS
-- ============================================================================

-- Mark all groups that have children
UPDATE `groups` g
SET has_children = TRUE
WHERE EXISTS (
    SELECT 1 FROM `groups` child
    WHERE child.parent_id = g.id
);

-- Mark all groups that don't have children (leaf nodes)
UPDATE `groups` g
SET has_children = FALSE
WHERE NOT EXISTS (
    SELECT 1 FROM `groups` child
    WHERE child.parent_id = g.id
);

-- ============================================================================
-- 6. CREATE TRIGGERS TO MAINTAIN CACHED DATA
-- ============================================================================

-- Trigger: Update cached_member_count when member is added
DROP TRIGGER IF EXISTS update_member_count_on_insert;
DELIMITER //
CREATE TRIGGER update_member_count_on_insert
AFTER INSERT ON group_members
FOR EACH ROW
BEGIN
    IF NEW.status = 'active' THEN
        UPDATE `groups`
        SET cached_member_count = cached_member_count + 1
        WHERE id = NEW.group_id;
    END IF;
END//
DELIMITER ;

-- Trigger: Update cached_member_count when member is removed
DROP TRIGGER IF EXISTS update_member_count_on_delete;
DELIMITER //
CREATE TRIGGER update_member_count_on_delete
AFTER DELETE ON group_members
FOR EACH ROW
BEGIN
    IF OLD.status = 'active' THEN
        UPDATE `groups`
        SET cached_member_count = cached_member_count - 1
        WHERE id = OLD.group_id;
    END IF;
END//
DELIMITER ;

-- Trigger: Update cached_member_count when member status changes
DROP TRIGGER IF EXISTS update_member_count_on_update;
DELIMITER //
CREATE TRIGGER update_member_count_on_update
AFTER UPDATE ON group_members
FOR EACH ROW
BEGIN
    IF OLD.status = 'active' AND NEW.status != 'active' THEN
        -- Member became inactive
        UPDATE `groups`
        SET cached_member_count = cached_member_count - 1
        WHERE id = NEW.group_id;
    ELSEIF OLD.status != 'active' AND NEW.status = 'active' THEN
        -- Member became active
        UPDATE `groups`
        SET cached_member_count = cached_member_count + 1
        WHERE id = NEW.group_id;
    END IF;
END//
DELIMITER ;

-- Trigger: Update has_children flag when child group is added
DROP TRIGGER IF EXISTS update_has_children_on_insert;
DELIMITER //
CREATE TRIGGER update_has_children_on_insert
AFTER INSERT ON `groups`
FOR EACH ROW
BEGIN
    IF NEW.parent_id IS NOT NULL AND NEW.parent_id > 0 THEN
        UPDATE `groups`
        SET has_children = TRUE
        WHERE id = NEW.parent_id;
    END IF;
END//
DELIMITER ;

-- Trigger: Update has_children flag when child group is removed
DROP TRIGGER IF EXISTS update_has_children_on_delete;
DELIMITER //
CREATE TRIGGER update_has_children_on_delete
AFTER DELETE ON `groups`
FOR EACH ROW
BEGIN
    IF OLD.parent_id IS NOT NULL AND OLD.parent_id > 0 THEN
        -- Check if parent still has other children
        UPDATE `groups`
        SET has_children = EXISTS (
            SELECT 1 FROM `groups` child
            WHERE child.parent_id = OLD.parent_id
        )
        WHERE id = OLD.parent_id;
    END IF;
END//
DELIMITER ;

-- Trigger: Update has_children flag when parent_id changes
DROP TRIGGER IF EXISTS update_has_children_on_update;
DELIMITER //
CREATE TRIGGER update_has_children_on_update
AFTER UPDATE ON `groups`
FOR EACH ROW
BEGIN
    -- If parent_id changed, update both old and new parents
    IF OLD.parent_id != NEW.parent_id OR (OLD.parent_id IS NULL AND NEW.parent_id IS NOT NULL) OR (OLD.parent_id IS NOT NULL AND NEW.parent_id IS NULL) THEN
        -- Update old parent
        IF OLD.parent_id IS NOT NULL AND OLD.parent_id > 0 THEN
            UPDATE `groups`
            SET has_children = EXISTS (
                SELECT 1 FROM `groups` child
                WHERE child.parent_id = OLD.parent_id
            )
            WHERE id = OLD.parent_id;
        END IF;

        -- Update new parent
        IF NEW.parent_id IS NOT NULL AND NEW.parent_id > 0 THEN
            UPDATE `groups`
            SET has_children = TRUE
            WHERE id = NEW.parent_id;
        END IF;
    END IF;
END//
DELIMITER ;

-- ============================================================================
-- 7. OPTIMIZATION SUMMARY
-- ============================================================================
-- BEFORE: 101.16ms
-- OPTIMIZATIONS APPLIED:
--   1. ✓ Added composite indexes for tenant + parent_id lookups
--   2. ✓ Added cached_member_count column (eliminates COUNT() aggregation)
--   3. ✓ Added has_children flag (eliminates self-join)
--   4. ✓ Created triggers to maintain cached data automatically
--   5. ✓ Added indexes optimized for the exact query pattern
--
-- EXPECTED IMPROVEMENT: 90-95% reduction (target: <10ms)
--
-- UPDATED QUERY SHOULD BE:
-- SELECT g.*
-- FROM `groups` g
-- WHERE g.tenant_id = ?
-- AND g.has_children = FALSE
-- ORDER BY g.cached_member_count DESC, g.name ASC
-- LIMIT 3
--
-- This eliminates:
-- - LEFT JOIN group_members (uses cached_member_count instead)
-- - LEFT JOIN groups child (uses has_children flag instead)
-- - GROUP BY (no longer needed)
-- - COUNT() aggregation (uses cached value)
--
-- NEXT STEPS:
-- 1. Run this migration
-- 2. Update Group::getFeatured() to use cached columns
-- 3. Monitor query performance in logs
-- ============================================================================

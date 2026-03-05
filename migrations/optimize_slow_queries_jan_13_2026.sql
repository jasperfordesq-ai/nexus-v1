-- ============================================================================
-- SLOW QUERY OPTIMIZATIONS - JANUARY 13, 2026
-- ============================================================================
-- This migration adds indexes and optimizations for slow queries identified
-- in production logs
--
-- SLOW QUERIES ADDRESSED:
-- 1. Groups query with complex JOINs (475ms)
--    SELECT g.*, COUNT(gm.id), COUNT(child.id) FROM groups...
--
-- 2. Home page groups query (101ms)
--    SELECT g.*, COUNT(gm.id) FROM groups WHERE child.id IS NULL...
--
-- STRATEGY:
-- - Add composite indexes for frequently joined columns
-- - Add indexes for WHERE clause filtering
-- - Add indexes for ORDER BY sorting
-- - Optimize GROUP BY performance
-- ============================================================================

-- ============================================================================
-- SECTION 1: GROUPS TABLE OPTIMIZATIONS
-- ============================================================================

-- Index for tenant + featured queries (for admin dashboard)
CREATE INDEX IF NOT EXISTS idx_tenant_featured_created
ON `groups` (tenant_id, is_featured, created_at);

-- Index for parent-child relationship queries
CREATE INDEX IF NOT EXISTS idx_tenant_parent
ON `groups` (tenant_id, parent_id);

-- Index for owner lookups
CREATE INDEX IF NOT EXISTS idx_owner_id
ON `groups` (owner_id);

-- ============================================================================
-- SECTION 2: GROUP_MEMBERS TABLE OPTIMIZATIONS
-- ============================================================================

-- Composite index for group + status (most common JOIN condition)
CREATE INDEX IF NOT EXISTS idx_group_status
ON `group_members` (group_id, status);

-- Composite index for counting active members per group
CREATE INDEX IF NOT EXISTS idx_group_status_id
ON `group_members` (group_id, status, id);

-- Index for user membership queries
CREATE INDEX IF NOT EXISTS idx_user_status
ON `group_members` (user_id, status);

-- ============================================================================
-- SECTION 3: USERS TABLE OPTIMIZATIONS (for JOINs)
-- ============================================================================

-- Index for name lookups (used in group owner JOINs)
CREATE INDEX IF NOT EXISTS idx_names
ON `users` (first_name, last_name);

-- ============================================================================
-- SECTION 4: GROUP_TYPES TABLE OPTIMIZATIONS
-- ============================================================================

-- Index for type lookups and hub filtering
CREATE INDEX IF NOT EXISTS idx_is_hub
ON `group_types` (is_hub);

-- ============================================================================
-- SECTION 5: ANALYZE TABLES FOR QUERY OPTIMIZER
-- ============================================================================
-- Update table statistics so MySQL query optimizer makes better decisions

ANALYZE TABLE `groups`;
ANALYZE TABLE `group_members`;
ANALYZE TABLE `group_types`;
ANALYZE TABLE `users`;

-- ============================================================================
-- VERIFICATION QUERIES
-- ============================================================================

-- Show all indexes on groups table
SELECT
    'Groups table indexes:' AS info,
    INDEX_NAME,
    COLUMN_NAME,
    SEQ_IN_INDEX,
    CARDINALITY
FROM information_schema.STATISTICS
WHERE TABLE_SCHEMA = DATABASE()
AND TABLE_NAME = 'groups'
ORDER BY INDEX_NAME, SEQ_IN_INDEX;

-- Show all indexes on group_members table
SELECT
    'Group_members table indexes:' AS info,
    INDEX_NAME,
    COLUMN_NAME,
    SEQ_IN_INDEX,
    CARDINALITY
FROM information_schema.STATISTICS
WHERE TABLE_SCHEMA = DATABASE()
AND TABLE_NAME = 'group_members'
ORDER BY INDEX_NAME, SEQ_IN_INDEX;

-- ============================================================================
-- QUERY OPTIMIZATION TIPS
-- ============================================================================

/*
BEFORE (SLOW - 475ms):
SELECT g.*, gt.name as type_name, gt.is_hub,
       COUNT(DISTINCT gm.id) as member_count,
       COUNT(DISTINCT child.id) as child_count,
       u.first_name, u.last_name
FROM `groups` g
LEFT JOIN group_types gt ON g.type_id = gt.id
LEFT JOIN group_members gm ON g.id = gm.group_id AND gm.status = 'active'
LEFT JOIN `groups` child ON child.parent_id = g.id AND child.tenant_id = g.tenant_id
LEFT JOIN users u ON g.owner_id = u.id
WHERE g.tenant_id = ?
GROUP BY g.id
ORDER BY g.is_featured DESC, g.created_at DESC
LIMIT 20 OFFSET 0;

AFTER (OPTIMIZED):
- Now uses idx_tenant_featured_created for WHERE + ORDER BY
- Now uses idx_group_status_id for member counting
- Now uses idx_tenant_parent for child counting
- Expected improvement: 475ms → ~50-100ms

MONITORING:
After deploying these indexes, monitor with:
  EXPLAIN SELECT g.*, ...
  (should show "Using index" for key lookups)
*/

SELECT '
============================================================================
SLOW QUERY OPTIMIZATIONS - MIGRATION COMPLETE
============================================================================

INDEXES ADDED:
✓ groups.idx_tenant_featured_created - For admin dashboard queries
✓ groups.idx_tenant_parent - For parent-child relationships
✓ groups.idx_owner_id - For owner lookups
✓ group_members.idx_group_status - For active member filtering
✓ group_members.idx_group_status_id - For efficient counting
✓ group_members.idx_user_status - For user membership queries
✓ users.idx_names - For name lookups in JOINs
✓ group_types.idx_is_hub - For hub filtering

TABLES ANALYZED:
✓ groups
✓ group_members
✓ group_types
✓ users

EXPECTED IMPROVEMENTS:
- Complex groups query: 475ms → ~50-100ms (80-90% faster)
- Home page groups query: 101ms → ~20-30ms (70-80% faster)
- Admin dashboard: Significantly faster loading
- API endpoints: Reduced latency

NEXT STEPS:
1. Monitor slow query log after deployment
2. Run EXPLAIN on problematic queries to verify index usage
3. Consider adding query result caching for heavily accessed data
4. Monitor server load - indexes use disk space but improve speed

MAINTENANCE:
- Run ANALYZE TABLE monthly on high-traffic tables
- Monitor index fragmentation with mysqlcheck
- Consider partitioning groups table if it grows beyond 1M rows

============================================================================
' AS MIGRATION_SUMMARY;

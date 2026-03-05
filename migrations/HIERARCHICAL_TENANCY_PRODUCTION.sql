-- =============================================================================
-- HIERARCHICAL TENANCY - PRODUCTION MIGRATION
-- =============================================================================
-- Date: 2026-01-14
-- Purpose: Add hierarchical tenancy support + fix super admin assignments
--
-- SAFE TO RUN: Uses IF NOT EXISTS / IF EXISTS checks throughout
-- IDEMPOTENT: Can be run multiple times without breaking anything
-- =============================================================================

-- -----------------------------------------------------------------------------
-- PART 1: SCHEMA CHANGES - Add hierarchy columns to tenants table
-- -----------------------------------------------------------------------------

-- Add parent_id column (for tenant hierarchy)
SET @sql = (SELECT IF(
    (SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS
     WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'tenants' AND COLUMN_NAME = 'parent_id') = 0,
    'ALTER TABLE tenants ADD COLUMN parent_id INT NULL DEFAULT NULL COMMENT "Parent tenant ID for sub-tenant relationships. NULL = root tenant"',
    'SELECT "parent_id column already exists"'
));
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

-- Add path column (materialized path for fast hierarchy queries)
SET @sql = (SELECT IF(
    (SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS
     WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'tenants' AND COLUMN_NAME = 'path') = 0,
    'ALTER TABLE tenants ADD COLUMN path VARCHAR(500) NULL DEFAULT NULL COMMENT "Materialized path: /1/2/5/ for fast descendant queries"',
    'SELECT "path column already exists"'
));
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

-- Add depth column (hierarchy level)
SET @sql = (SELECT IF(
    (SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS
     WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'tenants' AND COLUMN_NAME = 'depth') = 0,
    'ALTER TABLE tenants ADD COLUMN depth TINYINT UNSIGNED NOT NULL DEFAULT 0 COMMENT "Hierarchy depth: 0=Master, 1=Regional, 2=Local"',
    'SELECT "depth column already exists"'
));
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

-- Add allows_subtenants column (capability switch)
SET @sql = (SELECT IF(
    (SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS
     WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'tenants' AND COLUMN_NAME = 'allows_subtenants') = 0,
    'ALTER TABLE tenants ADD COLUMN allows_subtenants TINYINT(1) NOT NULL DEFAULT 0 COMMENT "Can admins of this tenant create sub-tenants?"',
    'SELECT "allows_subtenants column already exists"'
));
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

-- Add max_depth column (depth limit for sub-tenants)
SET @sql = (SELECT IF(
    (SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS
     WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'tenants' AND COLUMN_NAME = 'max_depth') = 0,
    'ALTER TABLE tenants ADD COLUMN max_depth TINYINT UNSIGNED NOT NULL DEFAULT 0 COMMENT "Max depth of sub-tenants allowed below this tenant"',
    'SELECT "max_depth column already exists"'
));
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

-- -----------------------------------------------------------------------------
-- PART 2: SCHEMA CHANGES - Add is_tenant_super_admin to users table
-- -----------------------------------------------------------------------------

SET @sql = (SELECT IF(
    (SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS
     WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'users' AND COLUMN_NAME = 'is_tenant_super_admin') = 0,
    'ALTER TABLE users ADD COLUMN is_tenant_super_admin TINYINT(1) NOT NULL DEFAULT 0 COMMENT "Can this user access Super Admin Panel for their tenant subtree?"',
    'SELECT "is_tenant_super_admin column already exists"'
));
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

-- -----------------------------------------------------------------------------
-- PART 3: ADD INDEXES (if not exist)
-- -----------------------------------------------------------------------------

-- Index on parent_id
SET @sql = (SELECT IF(
    (SELECT COUNT(*) FROM INFORMATION_SCHEMA.STATISTICS
     WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'tenants' AND INDEX_NAME = 'idx_tenant_parent_id') = 0,
    'CREATE INDEX idx_tenant_parent_id ON tenants(parent_id)',
    'SELECT "idx_tenant_parent_id already exists"'
));
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

-- Index on path
SET @sql = (SELECT IF(
    (SELECT COUNT(*) FROM INFORMATION_SCHEMA.STATISTICS
     WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'tenants' AND INDEX_NAME = 'idx_tenant_path') = 0,
    'CREATE INDEX idx_tenant_path ON tenants(path(100))',
    'SELECT "idx_tenant_path already exists"'
));
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

-- Index on allows_subtenants
SET @sql = (SELECT IF(
    (SELECT COUNT(*) FROM INFORMATION_SCHEMA.STATISTICS
     WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'tenants' AND INDEX_NAME = 'idx_tenant_allows_subtenants') = 0,
    'CREATE INDEX idx_tenant_allows_subtenants ON tenants(allows_subtenants)',
    'SELECT "idx_tenant_allows_subtenants already exists"'
));
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

-- -----------------------------------------------------------------------------
-- PART 4: INITIALIZE TENANT HIERARCHY DATA
-- -----------------------------------------------------------------------------

-- Master Tenant (ID: 1) - God Mode, root of hierarchy
UPDATE tenants SET
    parent_id = NULL,
    path = '/1/',
    depth = 0,
    allows_subtenants = 1,
    max_depth = 10
WHERE id = 1;

-- All other existing tenants - set as independent root tenants for now
-- (You can restructure hierarchy later by setting parent_id)
UPDATE tenants SET
    parent_id = NULL,
    path = CONCAT('/', id, '/'),
    depth = 0,
    allows_subtenants = 0,
    max_depth = 0
WHERE id != 1 AND (path IS NULL OR path = '');

-- -----------------------------------------------------------------------------
-- PART 5: USER MIGRATION - Ensure Tenant 2 has admin before moving super admins
-- -----------------------------------------------------------------------------

-- First, ensure there's at least one tenant_admin on Tenant 2
-- (Promote highest-ranked non-super user if none exists)
UPDATE users
SET role = 'tenant_admin'
WHERE tenant_id = 2
AND is_super_admin = 0
AND role NOT IN ('tenant_admin', 'super_admin')
AND NOT EXISTS (
    SELECT 1 FROM (SELECT id FROM users WHERE tenant_id = 2 AND role = 'tenant_admin' AND is_super_admin = 0) as t
)
ORDER BY
    CASE role WHEN 'admin' THEN 1 WHEN 'moderator' THEN 2 ELSE 3 END,
    id ASC
LIMIT 1;

-- -----------------------------------------------------------------------------
-- PART 6: USER MIGRATION - Move Global Super Admins to Master Tenant
-- -----------------------------------------------------------------------------

-- Move User 14 (and any other super admins on non-Master tenants) to Master Tenant
UPDATE users
SET tenant_id = 1
WHERE is_super_admin = 1
AND tenant_id != 1;

-- Grant is_tenant_super_admin to all users on Master Tenant who have is_super_admin
UPDATE users
SET is_tenant_super_admin = 1
WHERE tenant_id = 1
AND is_super_admin = 1;

-- -----------------------------------------------------------------------------
-- PART 7: CLEANUP - Remove super_admin flag from non-Master tenants
-- -----------------------------------------------------------------------------

-- Clear is_super_admin from anyone NOT on Master Tenant
-- (They should use is_tenant_super_admin within their scoped hierarchy)
UPDATE users
SET is_super_admin = 0
WHERE tenant_id != 1
AND is_super_admin = 1;

-- -----------------------------------------------------------------------------
-- PART 8: VERIFICATION QUERIES
-- -----------------------------------------------------------------------------

SELECT '=== TENANT HIERARCHY ===' as report;
SELECT
    id,
    name,
    parent_id,
    path,
    depth,
    CASE allows_subtenants WHEN 1 THEN 'YES' ELSE 'NO' END as can_create_subs,
    max_depth
FROM tenants
ORDER BY path;

SELECT '=== SUPER ADMINS (Should all be on Tenant 1) ===' as report;
SELECT
    id,
    CONCAT(first_name, ' ', last_name) as name,
    email,
    tenant_id,
    role,
    is_super_admin,
    is_tenant_super_admin
FROM users
WHERE is_super_admin = 1 OR is_tenant_super_admin = 1
ORDER BY tenant_id, id;

SELECT '=== TENANT 2 ADMINS (Should have at least one) ===' as report;
SELECT
    id,
    CONCAT(first_name, ' ', last_name) as name,
    email,
    role
FROM users
WHERE tenant_id = 2 AND role IN ('tenant_admin', 'admin')
ORDER BY role, id;

SELECT '=== ORPHANED SUPER ADMINS (Should be 0) ===' as report;
SELECT COUNT(*) as orphaned_count
FROM users
WHERE tenant_id != 1 AND is_super_admin = 1;

-- -----------------------------------------------------------------------------
-- MIGRATION COMPLETE
-- -----------------------------------------------------------------------------
SELECT '=== MIGRATION COMPLETE ===' as status;

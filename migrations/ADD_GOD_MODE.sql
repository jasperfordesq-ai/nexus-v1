-- =============================================================================
-- GOD MODE - Super Admin Management Privileges
-- =============================================================================
-- Date: 2026-01-15
-- Purpose: Add is_god flag for users who can manage other super admins
--
-- SAFE TO RUN: Uses IF NOT EXISTS checks
-- IDEMPOTENT: Can be run multiple times without breaking anything
-- =============================================================================

-- -----------------------------------------------------------------------------
-- PART 1: Add is_god column to users table
-- -----------------------------------------------------------------------------

SET @sql = (SELECT IF(
    (SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS
     WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'users' AND COLUMN_NAME = 'is_god') = 0,
    'ALTER TABLE users ADD COLUMN is_god TINYINT(1) NOT NULL DEFAULT 0 COMMENT "God mode: can grant/revoke super admin privileges from other users"',
    'SELECT "is_god column already exists"'
));
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

-- -----------------------------------------------------------------------------
-- PART 2: Grant god privileges to jasper.ford.esq@gmail.com
-- -----------------------------------------------------------------------------

UPDATE users
SET is_god = 1
WHERE email = 'jasper.ford.esq@gmail.com';

-- -----------------------------------------------------------------------------
-- VERIFICATION
-- -----------------------------------------------------------------------------

SELECT
    id,
    email,
    first_name,
    last_name,
    role,
    is_super_admin,
    is_tenant_super_admin,
    is_god,
    'God Mode Granted' as status
FROM users
WHERE email = 'jasper.ford.esq@gmail.com';

-- Show all god users
SELECT
    id,
    email,
    CONCAT(first_name, ' ', last_name) as name,
    'GOD' as privilege_level
FROM users
WHERE is_god = 1;

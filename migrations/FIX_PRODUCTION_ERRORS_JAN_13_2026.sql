-- ============================================================================
-- PRODUCTION ERROR FIXES - JANUARY 13, 2026
-- ============================================================================
-- This comprehensive migration fixes all database errors identified from
-- production logs dated 2026-01-11
--
-- ERRORS FIXED:
-- 1. Missing table: volunteering_organizations
-- 2. Missing table: cron_jobs
-- 3. Missing table: tenant_settings
-- 4. Missing table: group_audit_log
-- 5. Missing table: group_recommendation_interactions
-- 6. Missing table: layout_ab_tests (and related tables)
-- 7. Missing column: users.is_verified
-- 8. Missing column: group_members.created_at
--
-- EXECUTION ORDER:
-- Run migrations in this order for proper dependency handling
-- ============================================================================

-- Set SQL mode for safety
SET SQL_MODE = 'NO_AUTO_VALUE_ON_ZERO';
SET time_zone = '+00:00';

-- ============================================================================
-- SECTION 1: TENANT SETTINGS TABLE
-- ============================================================================
SOURCE create_tenant_settings_table.sql;

-- ============================================================================
-- SECTION 2: OPTIONAL FEATURE TABLES
-- ============================================================================
SOURCE create_optional_feature_tables.sql;

-- ============================================================================
-- SECTION 3: GROUPS MODULE ENHANCEMENTS
-- ============================================================================
SOURCE groups_module_enhancements.sql;

-- ============================================================================
-- SECTION 4: GROUP RECOMMENDATION SYSTEM
-- ============================================================================
SOURCE create_group_recommendation_tables.sql;

-- ============================================================================
-- SECTION 5: LAYOUT A/B TESTING SYSTEM
-- ============================================================================
SOURCE add_layout_ab_testing_fixed.sql;

-- ============================================================================
-- SECTION 6: ADD MISSING COLUMNS
-- ============================================================================
SOURCE add_is_verified_to_users.sql;
SOURCE add_created_at_to_group_members.sql;

-- ============================================================================
-- VERIFICATION QUERIES
-- ============================================================================

-- Check all tables exist
SELECT 'Verifying tables exist...' AS status;

SELECT
    CASE
        WHEN EXISTS (SELECT 1 FROM information_schema.TABLES WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'volunteering_organizations') THEN 'OK'
        ELSE 'MISSING'
    END AS volunteering_organizations,
    CASE
        WHEN EXISTS (SELECT 1 FROM information_schema.TABLES WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'cron_jobs') THEN 'OK'
        ELSE 'MISSING'
    END AS cron_jobs,
    CASE
        WHEN EXISTS (SELECT 1 FROM information_schema.TABLES WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'tenant_settings') THEN 'OK'
        ELSE 'MISSING'
    END AS tenant_settings,
    CASE
        WHEN EXISTS (SELECT 1 FROM information_schema.TABLES WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'group_audit_log') THEN 'OK'
        ELSE 'MISSING'
    END AS group_audit_log,
    CASE
        WHEN EXISTS (SELECT 1 FROM information_schema.TABLES WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'group_recommendation_interactions') THEN 'OK'
        ELSE 'MISSING'
    END AS group_recommendation_interactions,
    CASE
        WHEN EXISTS (SELECT 1 FROM information_schema.TABLES WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'layout_ab_tests') THEN 'OK'
        ELSE 'MISSING'
    END AS layout_ab_tests;

-- Check all columns exist
SELECT 'Verifying columns exist...' AS status;

SELECT
    CASE
        WHEN EXISTS (SELECT 1 FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'users' AND COLUMN_NAME = 'is_verified') THEN 'OK'
        ELSE 'MISSING'
    END AS users_is_verified,
    CASE
        WHEN EXISTS (SELECT 1 FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'group_members' AND COLUMN_NAME = 'created_at') THEN 'OK'
        ELSE 'MISSING'
    END AS group_members_created_at;

-- ============================================================================
-- MIGRATION COMPLETE
-- ============================================================================

SELECT '
============================================================================
PRODUCTION ERROR FIXES - MIGRATION COMPLETE
============================================================================

TABLES CREATED:
✓ volunteering_organizations - Volunteering org approval workflow
✓ cron_jobs - Cron job execution tracking
✓ tenant_settings - Tenant-specific configuration
✓ group_audit_log - Comprehensive group audit trail
✓ group_recommendation_interactions - Group recommendation tracking
✓ layout_ab_tests - Layout A/B testing system

COLUMNS ADDED:
✓ users.is_verified - User verification status
✓ group_members.created_at - Member join timestamp

NEXT STEPS:
1. Verify all tables and columns created successfully (see above)
2. Monitor error logs for any remaining issues
3. Test admin dashboard features that were throwing errors
4. Consider running ANALYZE TABLE on new tables for query optimization

IMPORTANT NOTES:
- All migrations use IF NOT EXISTS for safety
- Foreign keys are conditionally created
- Indexes added for performance
- Default data seeded where appropriate

If you see "OK" for all checks above, the migration was successful!
============================================================================
' AS MIGRATION_SUMMARY;

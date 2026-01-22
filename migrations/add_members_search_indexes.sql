-- Members Directory Search Optimization Indexes
-- Created: 2026-01-22
-- Purpose: Improve performance of /api/members search functionality
--
-- Usage: mysql -u your_user -p your_database < add_members_search_indexes.sql

-- Check if indexes already exist before creating (prevents errors on re-run)

-- Index for name searches
-- Improves: ?q=john queries
SET @exist := (SELECT COUNT(*) FROM INFORMATION_SCHEMA.STATISTICS
               WHERE table_schema = DATABASE()
               AND table_name = 'users'
               AND index_name = 'idx_users_name_tenant');
SET @sqlstmt := IF(@exist > 0, 'SELECT ''Index idx_users_name_tenant already exists.'' AS message',
                   'CREATE INDEX idx_users_name_tenant ON users(tenant_id, name)');
PREPARE stmt FROM @sqlstmt;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

-- Index for email searches (optional, if email search is frequently used)
-- Improves: ?q=email@example.com queries
SET @exist := (SELECT COUNT(*) FROM INFORMATION_SCHEMA.STATISTICS
               WHERE table_schema = DATABASE()
               AND table_name = 'users'
               AND index_name = 'idx_users_email_tenant');
SET @sqlstmt := IF(@exist > 0, 'SELECT ''Index idx_users_email_tenant already exists.'' AS message',
                   'CREATE INDEX idx_users_email_tenant ON users(tenant_id, email)');
PREPARE stmt FROM @sqlstmt;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

-- Index for location searches (NEW - for geolocated location field)
-- Improves: ?q=london queries searching by location
SET @exist := (SELECT COUNT(*) FROM INFORMATION_SCHEMA.STATISTICS
               WHERE table_schema = DATABASE()
               AND table_name = 'users'
               AND index_name = 'idx_users_location_tenant');
SET @sqlstmt := IF(@exist > 0, 'SELECT ''Index idx_users_location_tenant already exists.'' AS message',
                   'CREATE INDEX idx_users_location_tenant ON users(tenant_id, location)');
PREPARE stmt FROM @sqlstmt;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

-- Index for active status filtering (CRITICAL for "Active Now" tab)
-- Improves: ?active=true queries
SET @exist := (SELECT COUNT(*) FROM INFORMATION_SCHEMA.STATISTICS
               WHERE table_schema = DATABASE()
               AND table_name = 'users'
               AND index_name = 'idx_users_last_active');
SET @sqlstmt := IF(@exist > 0, 'SELECT ''Index idx_users_last_active already exists.'' AS message',
                   'CREATE INDEX idx_users_last_active ON users(tenant_id, last_active_at)');
PREPARE stmt FROM @sqlstmt;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

-- Composite index for combined active + search queries
-- Improves: ?q=john&active=true queries (most efficient)
SET @exist := (SELECT COUNT(*) FROM INFORMATION_SCHEMA.STATISTICS
               WHERE table_schema = DATABASE()
               AND table_name = 'users'
               AND index_name = 'idx_users_active_search');
SET @sqlstmt := IF(@exist > 0, 'SELECT ''Index idx_users_active_search already exists.'' AS message',
                   'CREATE INDEX idx_users_active_search ON users(tenant_id, last_active_at, name)');
PREPARE stmt FROM @sqlstmt;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

-- Display current indexes on users table
SELECT
    'Current indexes on users table:' AS message,
    INDEX_NAME,
    COLUMN_NAME,
    SEQ_IN_INDEX,
    CARDINALITY
FROM INFORMATION_SCHEMA.STATISTICS
WHERE table_schema = DATABASE()
  AND table_name = 'users'
ORDER BY INDEX_NAME, SEQ_IN_INDEX;

-- Performance recommendations
SELECT '============================================================' AS '';
SELECT 'Indexes created successfully!' AS 'Status';
SELECT 'Run ANALYZE TABLE users; to update index statistics' AS 'Next Step';
SELECT '============================================================' AS '';

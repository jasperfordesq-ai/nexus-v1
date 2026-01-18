-- ============================================================
-- FEDERATION COLUMN ADDITIONS - SAFE VERSION
-- ============================================================
-- This script safely adds columns only if they don't exist
-- Can be run multiple times without errors
-- ============================================================

-- Helper procedure to add column if not exists
DROP PROCEDURE IF EXISTS AddColumnIfNotExists;
DELIMITER //
CREATE PROCEDURE AddColumnIfNotExists(
    IN tableName VARCHAR(100),
    IN columnName VARCHAR(100),
    IN columnDefinition VARCHAR(500)
)
BEGIN
    IF NOT EXISTS (
        SELECT * FROM information_schema.COLUMNS
        WHERE TABLE_SCHEMA = DATABASE()
        AND TABLE_NAME = tableName
        AND COLUMN_NAME = columnName
    ) THEN
        SET @sql = CONCAT('ALTER TABLE `', tableName, '` ADD COLUMN `', columnName, '` ', columnDefinition);
        PREPARE stmt FROM @sql;
        EXECUTE stmt;
        DEALLOCATE PREPARE stmt;
        SELECT CONCAT('Added column: ', tableName, '.', columnName) AS Result;
    ELSE
        SELECT CONCAT('Column already exists: ', tableName, '.', columnName) AS Result;
    END IF;
END //
DELIMITER ;

-- USERS TABLE
CALL AddColumnIfNotExists('users', 'federation_optin', "TINYINT(1) NOT NULL DEFAULT 0");
CALL AddColumnIfNotExists('users', 'federated_profile_visible', "TINYINT(1) NOT NULL DEFAULT 0");
CALL AddColumnIfNotExists('users', 'federation_notifications_enabled', "TINYINT(1) NOT NULL DEFAULT 1");

-- TENANTS TABLE
CALL AddColumnIfNotExists('tenants', 'federation_contact_email', "VARCHAR(255) NULL");
CALL AddColumnIfNotExists('tenants', 'federation_contact_name', "VARCHAR(200) NULL");

-- GROUPS TABLE
CALL AddColumnIfNotExists('groups', 'allow_federated_members', "TINYINT(1) NOT NULL DEFAULT 0");
CALL AddColumnIfNotExists('groups', 'federated_visibility', "ENUM('none', 'listed', 'joinable') NOT NULL DEFAULT 'none'");

-- LISTINGS TABLE
CALL AddColumnIfNotExists('listings', 'federated_visibility', "ENUM('none', 'listed', 'bookable') NOT NULL DEFAULT 'none'");
CALL AddColumnIfNotExists('listings', 'service_type', "ENUM('physical_only', 'remote_only', 'hybrid', 'location_dependent') NOT NULL DEFAULT 'physical_only'");

-- EVENTS TABLE
CALL AddColumnIfNotExists('events', 'federated_visibility', "ENUM('none', 'listed', 'joinable') NOT NULL DEFAULT 'none'");
CALL AddColumnIfNotExists('events', 'allow_remote_attendance', "TINYINT(1) NOT NULL DEFAULT 0");

-- GROUP_MEMBERS TABLE (for federated membership tracking)
CALL AddColumnIfNotExists('group_members', 'is_federated', "TINYINT(1) NOT NULL DEFAULT 0");
CALL AddColumnIfNotExists('group_members', 'source_tenant_id', "INT DEFAULT NULL");

-- Cleanup
DROP PROCEDURE IF EXISTS AddColumnIfNotExists;

-- ============================================================
-- DONE! All columns added safely.
-- ============================================================
SELECT 'Federation columns migration completed!' AS Status;

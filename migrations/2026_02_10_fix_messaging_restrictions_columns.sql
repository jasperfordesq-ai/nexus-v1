-- ============================================================
-- HOTFIX: Fix user_messaging_restrictions column names
-- Version: 1.0
-- Date: 2026-02-10
-- Description: Fixes mismatch between migration and service code
--              - messaging_enabled -> messaging_disabled
--              - restricted_by -> set_by
-- ============================================================

-- Check if table exists and has the wrong columns, then fix

-- If messaging_enabled exists, rename to messaging_disabled and invert default
SET @col_exists = (
    SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS
    WHERE TABLE_SCHEMA = DATABASE()
    AND TABLE_NAME = 'user_messaging_restrictions'
    AND COLUMN_NAME = 'messaging_enabled'
);

-- Only run if the wrong column exists
SET @sql = IF(@col_exists > 0,
    'ALTER TABLE user_messaging_restrictions
     CHANGE COLUMN messaging_enabled messaging_disabled TINYINT(1) DEFAULT 0 COMMENT "Messaging disabled for this user"',
    'SELECT "Column already correct or table does not exist"'
);
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

-- If restricted_by exists, rename to set_by
SET @col_exists2 = (
    SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS
    WHERE TABLE_SCHEMA = DATABASE()
    AND TABLE_NAME = 'user_messaging_restrictions'
    AND COLUMN_NAME = 'restricted_by'
);

SET @sql2 = IF(@col_exists2 > 0,
    'ALTER TABLE user_messaging_restrictions
     CHANGE COLUMN restricted_by set_by INT NULL COMMENT "Admin who set the restriction"',
    'SELECT "Column already correct or table does not exist"'
);
PREPARE stmt2 FROM @sql2;
EXECUTE stmt2;
DEALLOCATE PREPARE stmt2;

-- Update index if it references the old column
SET @idx_exists = (
    SELECT COUNT(*) FROM INFORMATION_SCHEMA.STATISTICS
    WHERE TABLE_SCHEMA = DATABASE()
    AND TABLE_NAME = 'user_messaging_restrictions'
    AND INDEX_NAME = 'idx_messaging_disabled'
    AND COLUMN_NAME = 'messaging_enabled'
);

-- Drop and recreate index with correct column if needed
SET @sql3 = IF(@idx_exists > 0,
    'ALTER TABLE user_messaging_restrictions DROP INDEX idx_messaging_disabled, ADD INDEX idx_messaging_disabled (tenant_id, messaging_disabled)',
    'SELECT "Index already correct"'
);
PREPARE stmt3 FROM @sql3;
EXECUTE stmt3;
DEALLOCATE PREPARE stmt3;

-- If table doesn't exist at all, create it with correct columns
CREATE TABLE IF NOT EXISTS user_messaging_restrictions (
    id INT AUTO_INCREMENT PRIMARY KEY,
    tenant_id INT NOT NULL,
    user_id INT NOT NULL,

    -- Restrictions
    messaging_disabled TINYINT(1) DEFAULT 0 COMMENT 'Messaging disabled for this user',
    requires_broker_approval TINYINT(1) DEFAULT 0 COMMENT 'All outgoing messages need approval',
    under_monitoring TINYINT(1) DEFAULT 0 COMMENT 'Messages are copied to broker',

    -- Monitoring Details
    monitoring_reason TEXT NULL,
    monitoring_started_at TIMESTAMP NULL,
    monitoring_expires_at TIMESTAMP NULL,

    -- Audit
    set_by INT NULL COMMENT 'Admin who set the restriction',
    restricted_at TIMESTAMP NULL,
    restriction_reason TEXT NULL,

    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,

    -- Indexes
    UNIQUE KEY unique_tenant_user (tenant_id, user_id),
    INDEX idx_monitoring (tenant_id, under_monitoring),
    INDEX idx_messaging_disabled (tenant_id, messaging_disabled),

    -- Foreign Keys
    FOREIGN KEY (tenant_id) REFERENCES tenants(id) ON DELETE CASCADE,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    FOREIGN KEY (set_by) REFERENCES users(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

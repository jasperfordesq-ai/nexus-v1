-- Migration: Add tenant_id to notification_queue + race condition fix
-- Date: 2026-03-29
-- Issues fixed:
--   1. notification_queue missing tenant_id (multi-tenant leakage risk)
--   2. Race condition: add 'processing' status to prevent duplicate sends
--
-- This migration is idempotent (safe to run multiple times).

-- Step 1: Add tenant_id column (nullable first for backfill)
SET @col_exists = (SELECT COUNT(*) FROM information_schema.COLUMNS
    WHERE TABLE_SCHEMA = DATABASE()
    AND TABLE_NAME = 'notification_queue'
    AND COLUMN_NAME = 'tenant_id');

SET @sql = IF(@col_exists = 0,
    'ALTER TABLE notification_queue ADD COLUMN tenant_id INT NULL AFTER user_id',
    'SELECT "tenant_id column already exists" AS info');
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

-- Step 2: Backfill tenant_id from users table
UPDATE notification_queue q
JOIN users u ON q.user_id = u.id
SET q.tenant_id = u.tenant_id
WHERE q.tenant_id IS NULL;

-- Step 3: Make tenant_id NOT NULL (only if column exists and all rows are backfilled)
-- Check if any NULLs remain (orphaned rows with no matching user)
SET @null_count = (SELECT COUNT(*) FROM notification_queue WHERE tenant_id IS NULL);

-- Delete orphaned rows that have no matching user (can't determine tenant)
DELETE FROM notification_queue WHERE tenant_id IS NULL;

-- Now make it NOT NULL
SET @is_nullable = (SELECT IS_NULLABLE FROM information_schema.COLUMNS
    WHERE TABLE_SCHEMA = DATABASE()
    AND TABLE_NAME = 'notification_queue'
    AND COLUMN_NAME = 'tenant_id');

SET @sql = IF(@is_nullable = 'YES',
    'ALTER TABLE notification_queue MODIFY COLUMN tenant_id INT NOT NULL',
    'SELECT "tenant_id already NOT NULL" AS info');
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

-- Step 4: Add composite index for efficient tenant-scoped queue queries
SET @idx_exists = (SELECT COUNT(*) FROM information_schema.STATISTICS
    WHERE TABLE_SCHEMA = DATABASE()
    AND TABLE_NAME = 'notification_queue'
    AND INDEX_NAME = 'idx_nq_tenant_status_frequency');

SET @sql = IF(@idx_exists = 0,
    'ALTER TABLE notification_queue ADD INDEX idx_nq_tenant_status_frequency (tenant_id, status, frequency)',
    'SELECT "idx_nq_tenant_status_frequency already exists" AS info');
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

-- Step 5: Add 'processing' to status enum for race condition prevention
-- Current enum: ('pending','sent','failed')
-- New enum: ('pending','processing','sent','failed')
SET @current_type = (SELECT COLUMN_TYPE FROM information_schema.COLUMNS
    WHERE TABLE_SCHEMA = DATABASE()
    AND TABLE_NAME = 'notification_queue'
    AND COLUMN_NAME = 'status');

SET @sql = IF(@current_type NOT LIKE '%processing%',
    "ALTER TABLE notification_queue MODIFY COLUMN status ENUM('pending','processing','sent','failed') NOT NULL DEFAULT 'pending'",
    'SELECT "processing status already in enum" AS info');
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

-- Step 6: Add index on status alone for quick queue pickup
SET @idx_exists = (SELECT COUNT(*) FROM information_schema.STATISTICS
    WHERE TABLE_SCHEMA = DATABASE()
    AND TABLE_NAME = 'notification_queue'
    AND INDEX_NAME = 'idx_nq_status_frequency_created');

SET @sql = IF(@idx_exists = 0,
    'ALTER TABLE notification_queue ADD INDEX idx_nq_status_frequency_created (status, frequency, created_at)',
    'SELECT "idx_nq_status_frequency_created already exists" AS info');
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

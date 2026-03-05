-- Federation Data Exports Migration
-- Date: 2026-01-17
-- Creates table to track federation data exports for auditing and download history

CREATE TABLE IF NOT EXISTS federation_exports (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    tenant_id INT(11) NOT NULL,
    export_type ENUM('users', 'partnerships', 'transactions', 'audit', 'all') NOT NULL,
    filename VARCHAR(255) NOT NULL,
    file_size INT UNSIGNED DEFAULT NULL COMMENT 'Size in bytes',
    record_count INT UNSIGNED DEFAULT 0 COMMENT 'Number of records exported',
    status ENUM('pending', 'processing', 'completed', 'failed') DEFAULT 'pending',
    error_message TEXT DEFAULT NULL,
    filters JSON DEFAULT NULL COMMENT 'Applied filters (date range, etc.)',
    exported_by INT(11) NOT NULL COMMENT 'Admin who initiated the export',
    downloaded_at DATETIME DEFAULT NULL COMMENT 'When the file was downloaded',
    expires_at DATETIME DEFAULT NULL COMMENT 'When the file should be deleted',
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    completed_at DATETIME DEFAULT NULL,

    INDEX idx_tenant (tenant_id),
    INDEX idx_type (export_type),
    INDEX idx_status (status),
    INDEX idx_created (created_at),
    INDEX idx_exported_by (exported_by),
    INDEX idx_expires (expires_at),

    FOREIGN KEY (tenant_id) REFERENCES tenants(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Export Types:
-- 'users' - Federation-opted users with settings
-- 'partnerships' - Active partnership configurations
-- 'transactions' - Cross-tenant time credit transfers
-- 'audit' - Federation audit log entries
-- 'all' - ZIP archive containing all data types

-- Cleanup expired exports (run periodically via cron)
-- DELETE FROM federation_exports WHERE expires_at IS NOT NULL AND expires_at < NOW();

-- Example: Log an export
-- INSERT INTO federation_exports (tenant_id, export_type, filename, record_count, status, exported_by, completed_at)
-- VALUES (1, 'users', 'federation_users_2026-01-17.csv', 228, 'completed', 1, NOW());

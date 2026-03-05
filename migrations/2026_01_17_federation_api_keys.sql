-- Federation API Keys Migration
-- Date: 2026-01-17
-- Creates tables for external API authentication and logging

-- API Keys table for partner authentication
CREATE TABLE IF NOT EXISTS federation_api_keys (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    tenant_id INT(11) NOT NULL,
    name VARCHAR(100) NOT NULL COMMENT 'Human-readable key name',
    key_hash VARCHAR(64) NOT NULL COMMENT 'SHA-256 hash of API key',
    key_prefix VARCHAR(8) NOT NULL COMMENT 'First 8 chars for identification',
    permissions JSON NOT NULL DEFAULT '[]' COMMENT 'Array of permission strings',
    rate_limit INT UNSIGNED DEFAULT 1000 COMMENT 'Requests per hour',
    request_count INT UNSIGNED DEFAULT 0 COMMENT 'Current hour request count',
    status ENUM('active', 'suspended', 'revoked') DEFAULT 'active',
    expires_at DATETIME DEFAULT NULL COMMENT 'NULL = never expires',
    last_used_at DATETIME DEFAULT NULL,
    created_by INT(11) NOT NULL COMMENT 'Admin who created the key',
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,

    UNIQUE INDEX idx_key_hash (key_hash),
    INDEX idx_tenant (tenant_id),
    INDEX idx_status (status),
    INDEX idx_prefix (key_prefix),

    FOREIGN KEY (tenant_id) REFERENCES tenants(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- API Logs table for auditing
CREATE TABLE IF NOT EXISTS federation_api_logs (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    api_key_id INT UNSIGNED NOT NULL,
    endpoint VARCHAR(255) NOT NULL,
    method VARCHAR(10) NOT NULL,
    ip_address VARCHAR(45) NOT NULL,
    user_agent VARCHAR(255) DEFAULT NULL,
    response_code SMALLINT UNSIGNED DEFAULT NULL,
    response_time_ms INT UNSIGNED DEFAULT NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,

    INDEX idx_api_key (api_key_id),
    INDEX idx_created (created_at),
    INDEX idx_endpoint (endpoint(100)),

    FOREIGN KEY (api_key_id) REFERENCES federation_api_keys(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Available permissions:
-- 'timebanks:read' - List partner timebanks
-- 'members:read' - Search and view member profiles
-- 'listings:read' - Search and view listings
-- 'messages:write' - Send federated messages
-- 'transactions:write' - Initiate time credit transfers
-- '*' - All permissions (admin)

-- Example: Create an API key (run manually, replace values)
-- INSERT INTO federation_api_keys (tenant_id, name, key_hash, key_prefix, permissions, created_by)
-- VALUES (1, 'Partner Integration', SHA2('your-secret-key-here', 256), 'fed_xxxx', '["timebanks:read", "members:read", "listings:read"]', 1);

-- Cleanup old logs (run periodically via cron)
-- DELETE FROM federation_api_logs WHERE created_at < DATE_SUB(NOW(), INTERVAL 90 DAY);

-- Reset hourly rate limits (run every hour via cron)
-- UPDATE federation_api_keys SET request_count = 0;

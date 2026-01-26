-- Migration: Add Federation External Partners table
-- Date: 2026-01-26
-- Description: Stores external federation server connections with their API credentials

-- External partners table (servers outside this installation)
CREATE TABLE IF NOT EXISTS federation_external_partners (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    tenant_id INT UNSIGNED NOT NULL COMMENT 'Which tenant owns this external partner connection',

    -- Partner identification
    name VARCHAR(255) NOT NULL COMMENT 'Display name for this partner',
    description TEXT NULL COMMENT 'Optional description of the partner',

    -- Connection details
    base_url VARCHAR(500) NOT NULL COMMENT 'Base URL of the partner API (e.g., https://partner.example.com)',
    api_path VARCHAR(255) DEFAULT '/api/v1/federation' COMMENT 'API base path',

    -- Authentication
    api_key VARCHAR(500) NULL COMMENT 'Encrypted API key for authenticating with partner',
    auth_method ENUM('api_key', 'hmac', 'oauth2') DEFAULT 'api_key' COMMENT 'Authentication method',
    signing_secret VARCHAR(500) NULL COMMENT 'HMAC signing secret (if using HMAC auth)',
    oauth_client_id VARCHAR(255) NULL COMMENT 'OAuth client ID (if using OAuth)',
    oauth_client_secret VARCHAR(500) NULL COMMENT 'OAuth client secret (if using OAuth)',
    oauth_token_url VARCHAR(500) NULL COMMENT 'OAuth token endpoint URL',

    -- Status and verification
    status ENUM('pending', 'active', 'suspended', 'failed') DEFAULT 'pending' COMMENT 'Connection status',
    verified_at DATETIME NULL COMMENT 'When connection was last verified',
    last_sync_at DATETIME NULL COMMENT 'When data was last synced from partner',
    last_error TEXT NULL COMMENT 'Last error message if connection failed',
    error_count INT UNSIGNED DEFAULT 0 COMMENT 'Number of consecutive errors',

    -- Partner metadata (cached from their API)
    partner_name VARCHAR(255) NULL COMMENT 'Partner timebank name from their API',
    partner_version VARCHAR(50) NULL COMMENT 'Partner API version',
    partner_member_count INT UNSIGNED NULL COMMENT 'Cached member count from partner',
    partner_metadata JSON NULL COMMENT 'Additional metadata from partner API',

    -- Permissions (what we allow from/to this partner)
    allow_member_search TINYINT(1) DEFAULT 1 COMMENT 'Allow searching their members',
    allow_listing_search TINYINT(1) DEFAULT 1 COMMENT 'Allow searching their listings',
    allow_messaging TINYINT(1) DEFAULT 1 COMMENT 'Allow cross-platform messaging',
    allow_transactions TINYINT(1) DEFAULT 1 COMMENT 'Allow time credit transfers',
    allow_events TINYINT(1) DEFAULT 0 COMMENT 'Allow event sharing',
    allow_groups TINYINT(1) DEFAULT 0 COMMENT 'Allow group federation',

    -- Audit
    created_by INT UNSIGNED NULL COMMENT 'User who added this partner',
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME NULL ON UPDATE CURRENT_TIMESTAMP,

    -- Indexes
    INDEX idx_tenant (tenant_id),
    INDEX idx_status (status),
    INDEX idx_base_url (base_url(191)),
    UNIQUE KEY uk_tenant_url (tenant_id, base_url(191)),

    -- Foreign keys
    CONSTRAINT fk_external_partners_tenant FOREIGN KEY (tenant_id) REFERENCES tenants(id) ON DELETE CASCADE,
    CONSTRAINT fk_external_partners_creator FOREIGN KEY (created_by) REFERENCES users(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Log table for external partner API calls
CREATE TABLE IF NOT EXISTS federation_external_partner_logs (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    partner_id INT UNSIGNED NOT NULL,

    -- Request details
    endpoint VARCHAR(500) NOT NULL,
    method VARCHAR(10) NOT NULL,
    request_body TEXT NULL,

    -- Response details
    response_code INT NULL,
    response_body TEXT NULL,
    response_time_ms INT UNSIGNED NULL COMMENT 'Response time in milliseconds',

    -- Status
    success TINYINT(1) DEFAULT 0,
    error_message TEXT NULL,

    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,

    INDEX idx_partner (partner_id),
    INDEX idx_created (created_at),
    INDEX idx_success (success),

    CONSTRAINT fk_external_logs_partner FOREIGN KEY (partner_id) REFERENCES federation_external_partners(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

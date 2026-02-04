-- ============================================
-- Cookie Consent System Enhancements
-- Project NEXUS - EU Cookie Compliance
-- Version: 1.0
-- Date: 2026-01-24
-- ============================================

-- Add expiry tracking and versioning to existing cookie_consents table
-- Note: MariaDB doesn't support COMMENT with AFTER in ADD COLUMN, so we add columns without comments
ALTER TABLE cookie_consents
ADD COLUMN IF NOT EXISTS expires_at DATETIME NULL,
ADD COLUMN IF NOT EXISTS consent_version VARCHAR(20) DEFAULT '1.0',
ADD COLUMN IF NOT EXISTS last_updated_by_user DATETIME NULL,
ADD COLUMN IF NOT EXISTS withdrawal_date DATETIME NULL,
ADD COLUMN IF NOT EXISTS source VARCHAR(50) DEFAULT 'web';

-- Add indexes separately
CREATE INDEX IF NOT EXISTS idx_expires_at ON cookie_consents(expires_at);
CREATE INDEX IF NOT EXISTS idx_consent_version ON cookie_consents(consent_version);

-- ============================================
-- Cookie Inventory Table
-- Tracks all cookies used by the platform
-- ============================================
CREATE TABLE IF NOT EXISTS cookie_inventory (
    id INT PRIMARY KEY AUTO_INCREMENT,
    cookie_name VARCHAR(255) NOT NULL COMMENT 'Actual cookie name',
    category ENUM('essential', 'functional', 'analytics', 'marketing') NOT NULL COMMENT 'Cookie category',
    purpose TEXT NOT NULL COMMENT 'Plain language purpose',
    duration VARCHAR(100) NOT NULL COMMENT 'How long it lasts (e.g., Session, 1 year)',
    third_party VARCHAR(255) NULL COMMENT 'First-party or provider name (e.g., Google)',
    tenant_id INT NULL COMMENT 'NULL = global cookie, or specific tenant ID',
    is_active BOOLEAN DEFAULT TRUE COMMENT 'Whether cookie is currently in use',
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    UNIQUE KEY unique_cookie_tenant (cookie_name, tenant_id),
    INDEX idx_category (category),
    INDEX idx_tenant_id (tenant_id),
    INDEX idx_is_active (is_active)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
COMMENT='Inventory of all cookies used by the platform';

-- Insert current cookies used by NEXUS
INSERT INTO cookie_inventory (cookie_name, category, purpose, duration, third_party, tenant_id) VALUES
-- Essential Cookies
('PHPSESSID', 'essential', 'Session management and user authentication. Required for login and secure access.', 'Session (until browser closes)', 'First-party', NULL),
('cookie_consent', 'essential', 'Stores your cookie consent preferences so we remember your choices.', '1 year', 'First-party', NULL),
('nexus_active_layout', 'essential', 'Remembers which layout theme you selected (Modern or CivicOne).', 'Session', 'First-party', NULL),

-- Functional Cookies
('nexus_mode', 'functional', 'Remembers your dark/light mode preference for a better visual experience.', '1 year', 'First-party', NULL)

ON DUPLICATE KEY UPDATE
    purpose = VALUES(purpose),
    duration = VALUES(duration),
    updated_at = CURRENT_TIMESTAMP;

-- ============================================
-- Cookie Consent Audit Log
-- Detailed tracking for compliance (GDPR Article 7)
-- ============================================
CREATE TABLE IF NOT EXISTS cookie_consent_audit (
    id BIGINT PRIMARY KEY AUTO_INCREMENT,
    consent_id BIGINT NOT NULL COMMENT 'Reference to cookie_consents.id',
    action ENUM('created', 'updated', 'withdrawn', 'expired') NOT NULL COMMENT 'What happened',
    old_values JSON NULL COMMENT 'Previous consent state',
    new_values JSON NULL COMMENT 'New consent state',
    ip_address VARCHAR(45) NULL COMMENT 'IP address of requester',
    user_agent TEXT NULL COMMENT 'Browser user agent',
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_consent_id (consent_id),
    INDEX idx_action (action),
    INDEX idx_created_at (created_at),
    FOREIGN KEY (consent_id) REFERENCES cookie_consents(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
COMMENT='Audit trail of all consent changes for compliance';

-- ============================================
-- Tenant Cookie Settings
-- Per-tenant cookie configuration
-- ============================================
CREATE TABLE IF NOT EXISTS tenant_cookie_settings (
    id INT PRIMARY KEY AUTO_INCREMENT,
    tenant_id INT NOT NULL UNIQUE,
    banner_message TEXT NULL COMMENT 'Custom banner text for this tenant',
    analytics_enabled BOOLEAN DEFAULT FALSE COMMENT 'Whether tenant uses analytics cookies',
    marketing_enabled BOOLEAN DEFAULT FALSE COMMENT 'Whether tenant uses marketing cookies',
    analytics_provider VARCHAR(100) NULL COMMENT 'Analytics provider (e.g., Google Analytics, Matomo)',
    analytics_id VARCHAR(255) NULL COMMENT 'Analytics tracking ID',
    consent_validity_days INT DEFAULT 365 COMMENT 'How long consent is valid (days)',
    auto_block_scripts BOOLEAN DEFAULT TRUE COMMENT 'Block tracking scripts until consent given',
    strict_mode BOOLEAN DEFAULT TRUE COMMENT 'Require explicit consent (vs. implied consent)',
    show_reject_all BOOLEAN DEFAULT TRUE COMMENT 'Show Reject All button in banner',
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (tenant_id) REFERENCES tenants(id) ON DELETE CASCADE,
    INDEX idx_tenant_id (tenant_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
COMMENT='Tenant-specific cookie consent configuration';

-- Insert default settings for all existing tenants
INSERT IGNORE INTO tenant_cookie_settings (tenant_id, analytics_enabled, marketing_enabled)
SELECT
    id,
    FALSE, -- analytics disabled by default
    FALSE  -- marketing disabled by default
FROM tenants;

-- ============================================
-- Cookie Consent Statistics (for reporting)
-- ============================================
CREATE TABLE IF NOT EXISTS cookie_consent_stats (
    id BIGINT PRIMARY KEY AUTO_INCREMENT,
    tenant_id INT NOT NULL,
    stat_date DATE NOT NULL COMMENT 'Date of statistics',
    total_consents INT DEFAULT 0 COMMENT 'Total consent records created',
    accept_all_count INT DEFAULT 0 COMMENT 'Users who accepted all',
    reject_all_count INT DEFAULT 0 COMMENT 'Users who rejected all',
    custom_count INT DEFAULT 0 COMMENT 'Users who customized',
    functional_accepted INT DEFAULT 0 COMMENT 'Users who accepted functional',
    analytics_accepted INT DEFAULT 0 COMMENT 'Users who accepted analytics',
    marketing_accepted INT DEFAULT 0 COMMENT 'Users who accepted marketing',
    withdrawals_count INT DEFAULT 0 COMMENT 'Consent withdrawals',
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    UNIQUE KEY unique_tenant_date (tenant_id, stat_date),
    INDEX idx_tenant_id (tenant_id),
    INDEX idx_stat_date (stat_date)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
COMMENT='Daily aggregated consent statistics for analytics';

-- ============================================
-- Stored Procedure: Clean Expired Consents
-- Automatically remove or flag expired consents
-- ============================================
DELIMITER $$

DROP PROCEDURE IF EXISTS clean_expired_consents$$

CREATE PROCEDURE clean_expired_consents()
BEGIN
    DECLARE affected_rows INT DEFAULT 0;

    -- Mark expired consents
    UPDATE cookie_consents
    SET withdrawal_date = NOW(),
        updated_at = NOW()
    WHERE expires_at IS NOT NULL
      AND expires_at < NOW()
      AND withdrawal_date IS NULL;

    SET affected_rows = ROW_COUNT();

    -- Log the cleanup
    INSERT INTO gdpr_audit_log (
        tenant_id,
        action,
        entity_type,
        additional_data,
        created_at
    )
    SELECT DISTINCT
        tenant_id,
        'cookie_consent_expired',
        'cookie_consents',
        JSON_OBJECT('expired_count', affected_rows),
        NOW()
    FROM cookie_consents
    WHERE withdrawal_date = NOW()
    LIMIT 1;

    SELECT affected_rows AS expired_consents_marked;
END$$

DELIMITER ;

-- ============================================
-- Stored Procedure: Generate Daily Stats
-- Aggregate consent statistics
-- ============================================
DELIMITER $$

DROP PROCEDURE IF EXISTS generate_cookie_stats$$

CREATE PROCEDURE generate_cookie_stats(IN target_date DATE)
BEGIN
    -- Insert or update daily statistics
    INSERT INTO cookie_consent_stats (
        tenant_id,
        stat_date,
        total_consents,
        accept_all_count,
        reject_all_count,
        custom_count,
        functional_accepted,
        analytics_accepted,
        marketing_accepted,
        withdrawals_count
    )
    SELECT
        tenant_id,
        DATE(created_at) AS stat_date,
        COUNT(*) AS total_consents,
        SUM(CASE WHEN functional = 1 AND analytics = 1 AND marketing = 1 THEN 1 ELSE 0 END) AS accept_all_count,
        SUM(CASE WHEN functional = 0 AND analytics = 0 AND marketing = 0 THEN 1 ELSE 0 END) AS reject_all_count,
        SUM(CASE WHEN NOT (
            (functional = 1 AND analytics = 1 AND marketing = 1) OR
            (functional = 0 AND analytics = 0 AND marketing = 0)
        ) THEN 1 ELSE 0 END) AS custom_count,
        SUM(CASE WHEN functional = 1 THEN 1 ELSE 0 END) AS functional_accepted,
        SUM(CASE WHEN analytics = 1 THEN 1 ELSE 0 END) AS analytics_accepted,
        SUM(CASE WHEN marketing = 1 THEN 1 ELSE 0 END) AS marketing_accepted,
        SUM(CASE WHEN withdrawal_date IS NOT NULL THEN 1 ELSE 0 END) AS withdrawals_count
    FROM cookie_consents
    WHERE DATE(created_at) = target_date
    GROUP BY tenant_id, DATE(created_at)
    ON DUPLICATE KEY UPDATE
        total_consents = VALUES(total_consents),
        accept_all_count = VALUES(accept_all_count),
        reject_all_count = VALUES(reject_all_count),
        custom_count = VALUES(custom_count),
        functional_accepted = VALUES(functional_accepted),
        analytics_accepted = VALUES(analytics_accepted),
        marketing_accepted = VALUES(marketing_accepted),
        withdrawals_count = VALUES(withdrawals_count),
        updated_at = NOW();
END$$

DELIMITER ;

-- ============================================
-- Create Indexes for Performance
-- ============================================

-- Index for checking valid consents
ALTER TABLE cookie_consents
ADD INDEX IF NOT EXISTS idx_valid_consent (user_id, tenant_id, expires_at, withdrawal_date);

-- Index for session-based lookups
ALTER TABLE cookie_consents
ADD INDEX IF NOT EXISTS idx_session_tenant (session_id, tenant_id);

-- ============================================
-- Update Existing Data
-- ============================================

-- Set expires_at for existing consents (1 year from created_at)
UPDATE cookie_consents
SET expires_at = DATE_ADD(created_at, INTERVAL 365 DAY)
WHERE expires_at IS NULL;

-- Set source for existing consents
UPDATE cookie_consents
SET source = 'web'
WHERE source IS NULL;

-- ============================================
-- Migration Complete
-- ============================================

SELECT
    'Cookie Consent Schema Enhanced' AS status,
    (SELECT COUNT(*) FROM cookie_inventory) AS cookie_inventory_count,
    (SELECT COUNT(*) FROM tenant_cookie_settings) AS tenant_settings_count,
    (SELECT COUNT(*) FROM cookie_consents) AS existing_consents,
    NOW() AS migration_timestamp;

-- ============================================================================
-- Tenant-Specific Consent Versioning
-- Migration: add_tenant_consent_overrides.sql
-- Created: 2026-01-20
-- Purpose: Allow each tenant to have their own consent versions/text
-- ============================================================================

-- Create tenant consent overrides table
-- When a tenant updates their terms, a record is created here
-- If no override exists, the global consent_types version is used
CREATE TABLE IF NOT EXISTS tenant_consent_overrides (
    id INT PRIMARY KEY AUTO_INCREMENT,
    tenant_id INT NOT NULL,
    consent_type_slug VARCHAR(100) NOT NULL,
    current_version VARCHAR(20) NOT NULL DEFAULT '1.0',
    current_text TEXT NULL COMMENT 'Override text, NULL = use global',
    is_active TINYINT(1) DEFAULT 1,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,

    UNIQUE KEY uk_tenant_consent (tenant_id, consent_type_slug),
    INDEX idx_tenant_id (tenant_id),
    INDEX idx_consent_type (consent_type_slug),

    FOREIGN KEY (tenant_id)
        REFERENCES tenants(id)
        ON DELETE CASCADE,
    FOREIGN KEY (consent_type_slug)
        REFERENCES consent_types(slug)
        ON DELETE CASCADE
        ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Create tenant consent version history for audit trail
CREATE TABLE IF NOT EXISTS tenant_consent_version_history (
    id INT PRIMARY KEY AUTO_INCREMENT,
    tenant_id INT NOT NULL,
    consent_type_slug VARCHAR(100) NOT NULL,
    version VARCHAR(20) NOT NULL,
    text_content TEXT NULL,
    text_hash VARCHAR(64) NULL COMMENT 'SHA-256 hash for comparison',
    created_by INT NULL COMMENT 'Admin user who made the change',
    effective_from DATETIME DEFAULT CURRENT_TIMESTAMP,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,

    INDEX idx_tenant_consent (tenant_id, consent_type_slug),
    INDEX idx_version (version),
    INDEX idx_effective_from (effective_from),

    FOREIGN KEY (tenant_id)
        REFERENCES tenants(id)
        ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Trigger to auto-log tenant consent version changes
DROP TRIGGER IF EXISTS tenant_consent_version_change_log;

DELIMITER //

CREATE TRIGGER tenant_consent_version_change_log
AFTER UPDATE ON tenant_consent_overrides
FOR EACH ROW
BEGIN
    IF OLD.current_version != NEW.current_version OR OLD.current_text != NEW.current_text THEN
        INSERT INTO tenant_consent_version_history
            (tenant_id, consent_type_slug, version, text_content, text_hash, effective_from)
        VALUES
            (NEW.tenant_id, NEW.consent_type_slug, NEW.current_version,
             NEW.current_text, SHA2(COALESCE(NEW.current_text, ''), 256), NOW());
    END IF;
END//

-- Also log INSERT for new overrides
CREATE TRIGGER tenant_consent_version_insert_log
AFTER INSERT ON tenant_consent_overrides
FOR EACH ROW
BEGIN
    INSERT INTO tenant_consent_version_history
        (tenant_id, consent_type_slug, version, text_content, text_hash, effective_from)
    VALUES
        (NEW.tenant_id, NEW.consent_type_slug, NEW.current_version,
         NEW.current_text, SHA2(COALESCE(NEW.current_text, ''), 256), NOW());
END//

DELIMITER ;

-- ============================================================================
-- VERIFICATION QUERIES
-- ============================================================================

-- Check tables exist:
-- SELECT * FROM tenant_consent_overrides;
-- SELECT * FROM tenant_consent_version_history;

-- Check triggers:
-- SHOW TRIGGERS LIKE 'tenant_consent%';

-- Example: Create override for tenant 2 to have version 2.0 while others stay at 1.0
-- INSERT INTO tenant_consent_overrides (tenant_id, consent_type_slug, current_version)
-- VALUES (2, 'terms_of_service', '2.0');

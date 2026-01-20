-- ============================================================================
-- GDPR Consent Version History Tracking
-- Migration: add_consent_version_tracking.sql
-- Created: 2026-01-20
-- Purpose: Track historical versions of consent text for audit compliance
-- ============================================================================

-- Create consent version history table
-- This stores a record every time the consent text or version is updated
CREATE TABLE IF NOT EXISTS consent_version_history (
    id INT PRIMARY KEY AUTO_INCREMENT,
    consent_type_slug VARCHAR(100) NOT NULL,
    version VARCHAR(20) NOT NULL,
    text_content TEXT NOT NULL,
    text_hash VARCHAR(64) NOT NULL COMMENT 'SHA-256 hash for comparison',
    created_by INT NULL COMMENT 'Admin user who made the change',
    effective_from DATETIME DEFAULT CURRENT_TIMESTAMP COMMENT 'When this version became active',
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,

    INDEX idx_consent_type (consent_type_slug),
    INDEX idx_version (version),
    INDEX idx_effective_from (effective_from),
    INDEX idx_text_hash (text_hash),

    FOREIGN KEY (consent_type_slug)
        REFERENCES consent_types(slug)
        ON DELETE CASCADE
        ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Trigger to auto-log version changes when consent_types is updated
-- This ensures we have an audit trail of all consent text changes
-- Drop first if exists for idempotency
DROP TRIGGER IF EXISTS consent_version_change_log;

DELIMITER //

CREATE TRIGGER consent_version_change_log
AFTER UPDATE ON consent_types
FOR EACH ROW
BEGIN
    -- Only log if version or text actually changed
    IF OLD.current_version != NEW.current_version OR OLD.current_text != NEW.current_text THEN
        INSERT INTO consent_version_history
            (consent_type_slug, version, text_content, text_hash, effective_from)
        VALUES
            (NEW.slug, NEW.current_version, NEW.current_text, SHA2(NEW.current_text, 256), NOW());
    END IF;
END//

DELIMITER ;

-- Seed initial version history from current consent_types
-- This captures the current state as version history baseline
INSERT IGNORE INTO consent_version_history
    (consent_type_slug, version, text_content, text_hash, effective_from, created_at)
SELECT
    slug,
    current_version,
    current_text,
    SHA2(current_text, 256),
    created_at,
    created_at
FROM consent_types
WHERE is_active = TRUE;

-- Add index on user_consents for version mismatch queries
-- Check if index exists first, create if not
SET @idx_exists = (SELECT COUNT(1) FROM INFORMATION_SCHEMA.STATISTICS
    WHERE table_schema = DATABASE()
    AND table_name = 'user_consents'
    AND index_name = 'idx_user_consents_version_check');

SET @sql = IF(@idx_exists = 0,
    'CREATE INDEX idx_user_consents_version_check ON user_consents (tenant_id, consent_type, consent_given, consent_version)',
    'SELECT 1');
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

-- ============================================================================
-- VERIFICATION QUERIES
-- Run these to verify the migration worked
-- ============================================================================

-- Check consent_version_history table exists and has data:
-- SELECT * FROM consent_version_history ORDER BY created_at DESC LIMIT 10;

-- Check trigger exists:
-- SHOW TRIGGERS LIKE 'consent%';

-- Test version mismatch detection:
-- SELECT uc.user_id, uc.consent_type, uc.consent_version as user_version,
--        ct.current_version as current_version
-- FROM user_consents uc
-- JOIN consent_types ct ON uc.consent_type = ct.slug
-- WHERE uc.consent_version != ct.current_version AND ct.is_required = 1;

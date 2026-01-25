-- ============================================================================
-- CREATE LEGAL DOCUMENTS SYSTEM
-- ============================================================================
-- Migration: Create legal document versioning and acceptance tracking system
-- Purpose: GDPR & Insurance compliance for Terms of Service and Privacy Policy
-- Date: 2026-01-25
--
-- This migration creates:
--   1. legal_documents - Master table for legal document types
--   2. legal_document_versions - Version history for each document
--   3. user_legal_acceptances - User acceptance tracking for compliance
--
-- Compliance Requirements Addressed:
--   - GDPR Article 7 (Conditions for consent)
--   - GDPR Article 13/14 (Information to be provided)
--   - Insurance company requirement for version tracking
--   - Audit trail for regulatory review
-- ============================================================================

-- ============================================================================
-- TABLE 1: legal_documents
-- Master table for legal document types per tenant
-- ============================================================================
CREATE TABLE IF NOT EXISTS `legal_documents` (
    `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
    `tenant_id` INT UNSIGNED NOT NULL DEFAULT 1 COMMENT 'Tenant identifier',
    `document_type` ENUM('terms', 'privacy', 'cookies', 'accessibility', 'community_guidelines', 'acceptable_use') NOT NULL COMMENT 'Type of legal document',
    `title` VARCHAR(255) NOT NULL COMMENT 'Display title (e.g., "Terms of Service")',
    `slug` VARCHAR(100) NOT NULL COMMENT 'URL slug (e.g., "terms")',
    `current_version_id` INT UNSIGNED NULL COMMENT 'FK to current active version',
    `requires_acceptance` TINYINT(1) NOT NULL DEFAULT 1 COMMENT 'Whether users must explicitly accept this document',
    `acceptance_required_for` ENUM('registration', 'login', 'first_use', 'none') DEFAULT 'registration' COMMENT 'When acceptance is required',
    `notify_on_update` TINYINT(1) NOT NULL DEFAULT 1 COMMENT 'Email users when document is updated',
    `is_active` TINYINT(1) NOT NULL DEFAULT 1 COMMENT 'Whether document is currently active',
    `created_at` TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at` TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    `created_by` INT UNSIGNED NULL COMMENT 'User ID who created this document',
    PRIMARY KEY (`id`),
    UNIQUE KEY `unique_tenant_document` (`tenant_id`, `document_type`),
    KEY `idx_tenant_id` (`tenant_id`),
    KEY `idx_document_type` (`document_type`),
    KEY `idx_slug` (`slug`),
    KEY `idx_is_active` (`is_active`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
COMMENT='Master table for legal documents per tenant';

-- ============================================================================
-- TABLE 2: legal_document_versions
-- Version history for each legal document
-- ============================================================================
CREATE TABLE IF NOT EXISTS `legal_document_versions` (
    `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
    `document_id` INT UNSIGNED NOT NULL COMMENT 'FK to legal_documents',
    `version_number` VARCHAR(20) NOT NULL COMMENT 'Semantic version (e.g., "2.0", "2.1.1")',
    `version_label` VARCHAR(100) NULL COMMENT 'Optional human-readable label (e.g., "January 2026 Update")',
    `content` LONGTEXT NOT NULL COMMENT 'Full document content (HTML)',
    `content_plain` LONGTEXT NULL COMMENT 'Plain text version for emails/exports',
    `summary_of_changes` TEXT NULL COMMENT 'Brief summary of what changed from previous version',
    `effective_date` DATE NOT NULL COMMENT 'Date this version becomes effective',
    `published_at` TIMESTAMP NULL COMMENT 'When this version was published (NULL = draft)',
    `is_draft` TINYINT(1) NOT NULL DEFAULT 1 COMMENT 'Draft versions not visible to public',
    `is_current` TINYINT(1) NOT NULL DEFAULT 0 COMMENT 'Is this the current active version',
    `notification_sent` TINYINT(1) NOT NULL DEFAULT 0 COMMENT 'Whether update notification was sent',
    `notification_sent_at` TIMESTAMP NULL COMMENT 'When notification was sent',
    `created_at` TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at` TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    `created_by` INT UNSIGNED NOT NULL COMMENT 'User ID who created this version',
    `published_by` INT UNSIGNED NULL COMMENT 'User ID who published this version',
    PRIMARY KEY (`id`),
    UNIQUE KEY `unique_document_version` (`document_id`, `version_number`),
    KEY `idx_document_id` (`document_id`),
    KEY `idx_version_number` (`version_number`),
    KEY `idx_effective_date` (`effective_date`),
    KEY `idx_is_current` (`is_current`),
    KEY `idx_is_draft` (`is_draft`),
    KEY `idx_published_at` (`published_at`),
    CONSTRAINT `fk_legal_version_document` FOREIGN KEY (`document_id`)
        REFERENCES `legal_documents` (`id`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
COMMENT='Version history for legal documents';

-- ============================================================================
-- TABLE 3: user_legal_acceptances
-- Tracks which users accepted which version of each document
-- Critical for GDPR compliance and dispute resolution
-- ============================================================================
CREATE TABLE IF NOT EXISTS `user_legal_acceptances` (
    `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    `user_id` INT UNSIGNED NOT NULL COMMENT 'FK to users table',
    `document_id` INT UNSIGNED NOT NULL COMMENT 'FK to legal_documents',
    `version_id` INT UNSIGNED NOT NULL COMMENT 'FK to legal_document_versions',
    `version_number` VARCHAR(20) NOT NULL COMMENT 'Denormalized for quick reference',
    `accepted_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP COMMENT 'When user accepted',
    `acceptance_method` ENUM('registration', 'login_prompt', 'settings', 'api', 'forced_update') NOT NULL DEFAULT 'registration' COMMENT 'How user accepted',
    `ip_address` VARCHAR(45) NULL COMMENT 'IP address at time of acceptance (IPv4 or IPv6)',
    `user_agent` TEXT NULL COMMENT 'Browser/device info at time of acceptance',
    `session_id` VARCHAR(128) NULL COMMENT 'Session ID at time of acceptance',
    `additional_context` JSON NULL COMMENT 'Any additional context (e.g., registration source)',
    PRIMARY KEY (`id`),
    UNIQUE KEY `unique_user_version` (`user_id`, `version_id`),
    KEY `idx_user_id` (`user_id`),
    KEY `idx_document_id` (`document_id`),
    KEY `idx_version_id` (`version_id`),
    KEY `idx_accepted_at` (`accepted_at`),
    KEY `idx_user_document` (`user_id`, `document_id`),
    CONSTRAINT `fk_acceptance_document` FOREIGN KEY (`document_id`)
        REFERENCES `legal_documents` (`id`) ON DELETE CASCADE ON UPDATE CASCADE,
    CONSTRAINT `fk_acceptance_version` FOREIGN KEY (`version_id`)
        REFERENCES `legal_document_versions` (`id`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
COMMENT='User acceptance records for legal documents - GDPR compliance';

-- ============================================================================
-- INDEXES FOR COMMON QUERIES
-- ============================================================================

-- For checking if user has accepted current version of a document
ALTER TABLE `user_legal_acceptances`
ADD INDEX `idx_user_version_check` (`user_id`, `document_id`, `version_id`);

-- For audit exports - finding all acceptances in date range
ALTER TABLE `user_legal_acceptances`
ADD INDEX `idx_audit_export` (`document_id`, `accepted_at`);

-- ============================================================================
-- SEED DATA: Create default documents for tenant 1 (hour-timebank)
-- ============================================================================

-- Insert Terms of Service document
INSERT INTO `legal_documents`
    (`tenant_id`, `document_type`, `title`, `slug`, `requires_acceptance`, `acceptance_required_for`, `notify_on_update`, `is_active`, `created_by`)
VALUES
    (1, 'terms', 'Terms of Service', 'terms', 1, 'registration', 1, 1, 1),
    (1, 'privacy', 'Privacy Policy', 'privacy', 1, 'registration', 1, 1, 1),
    (1, 'cookies', 'Cookie Policy', 'cookies', 0, 'none', 0, 1, 1),
    (2, 'terms', 'Terms of Service', 'terms', 1, 'registration', 1, 1, 1),
    (2, 'privacy', 'Privacy Policy', 'privacy', 1, 'registration', 1, 1, 1),
    (2, 'cookies', 'Cookie Policy', 'cookies', 0, 'none', 0, 1, 1)
ON DUPLICATE KEY UPDATE `updated_at` = CURRENT_TIMESTAMP;

-- ============================================================================
-- STORED PROCEDURE: Get user's acceptance status for all documents
-- ============================================================================
DROP PROCEDURE IF EXISTS `sp_get_user_acceptance_status`;

DELIMITER //

CREATE PROCEDURE `sp_get_user_acceptance_status`(
    IN p_user_id INT UNSIGNED,
    IN p_tenant_id INT UNSIGNED
)
BEGIN
    SELECT
        ld.id AS document_id,
        ld.document_type,
        ld.title,
        ld.requires_acceptance,
        ldv.id AS current_version_id,
        ldv.version_number AS current_version,
        ldv.effective_date,
        ula.id AS acceptance_id,
        ula.version_id AS accepted_version_id,
        ula.version_number AS accepted_version,
        ula.accepted_at,
        CASE
            WHEN ula.version_id IS NULL THEN 'not_accepted'
            WHEN ula.version_id = ldv.id THEN 'current'
            ELSE 'outdated'
        END AS acceptance_status
    FROM legal_documents ld
    LEFT JOIN legal_document_versions ldv ON ld.current_version_id = ldv.id
    LEFT JOIN user_legal_acceptances ula ON ula.user_id = p_user_id
        AND ula.document_id = ld.id
        AND ula.version_id = (
            SELECT MAX(ula2.version_id)
            FROM user_legal_acceptances ula2
            WHERE ula2.user_id = p_user_id AND ula2.document_id = ld.id
        )
    WHERE ld.tenant_id = p_tenant_id
    AND ld.is_active = 1
    AND ld.requires_acceptance = 1;
END //

DELIMITER ;

-- ============================================================================
-- VIEW: Acceptance statistics per document version
-- ============================================================================
CREATE OR REPLACE VIEW `v_legal_acceptance_stats` AS
SELECT
    ld.id AS document_id,
    ld.tenant_id,
    ld.document_type,
    ld.title,
    ldv.id AS version_id,
    ldv.version_number,
    ldv.effective_date,
    ldv.is_current,
    COUNT(DISTINCT ula.user_id) AS total_acceptances,
    MIN(ula.accepted_at) AS first_acceptance,
    MAX(ula.accepted_at) AS last_acceptance
FROM legal_documents ld
JOIN legal_document_versions ldv ON ldv.document_id = ld.id
LEFT JOIN user_legal_acceptances ula ON ula.version_id = ldv.id
GROUP BY ld.id, ld.tenant_id, ld.document_type, ld.title,
         ldv.id, ldv.version_number, ldv.effective_date, ldv.is_current;

-- ============================================================================
-- TRIGGERS: Maintain data integrity
-- ============================================================================

-- Trigger to ensure only one current version per document
DROP TRIGGER IF EXISTS `trg_legal_version_before_update`;

DELIMITER //

CREATE TRIGGER `trg_legal_version_before_update`
BEFORE UPDATE ON `legal_document_versions`
FOR EACH ROW
BEGIN
    -- If setting this version as current, unset others
    IF NEW.is_current = 1 AND OLD.is_current = 0 THEN
        UPDATE legal_document_versions
        SET is_current = 0
        WHERE document_id = NEW.document_id
        AND id != NEW.id;

        -- Also update the current_version_id in legal_documents
        UPDATE legal_documents
        SET current_version_id = NEW.id
        WHERE id = NEW.document_id;
    END IF;
END //

DELIMITER ;

-- Trigger to update legal_documents.current_version_id when new version is published
DROP TRIGGER IF EXISTS `trg_legal_version_after_insert`;

DELIMITER //

CREATE TRIGGER `trg_legal_version_after_insert`
AFTER INSERT ON `legal_document_versions`
FOR EACH ROW
BEGIN
    -- If inserting as current version
    IF NEW.is_current = 1 THEN
        UPDATE legal_documents
        SET current_version_id = NEW.id
        WHERE id = NEW.document_id;
    END IF;
END //

DELIMITER ;

-- ============================================================================
-- VERIFICATION QUERIES (run separately after migration)
-- ============================================================================

/*
-- Check tables created
SHOW TABLES LIKE 'legal%';

-- Check legal_documents structure
DESCRIBE legal_documents;

-- Check legal_document_versions structure
DESCRIBE legal_document_versions;

-- Check user_legal_acceptances structure
DESCRIBE user_legal_acceptances;

-- Verify seed data
SELECT * FROM legal_documents;

-- Test stored procedure (replace with actual user_id and tenant_id)
CALL sp_get_user_acceptance_status(1, 2);

-- Check the view
SELECT * FROM v_legal_acceptance_stats;
*/

-- ============================================================================
-- SUCCESS MESSAGE
-- ============================================================================
SELECT 'LEGAL DOCUMENTS SYSTEM: Created successfully' AS result;
SELECT '  - legal_documents table created' AS detail;
SELECT '  - legal_document_versions table created' AS detail;
SELECT '  - user_legal_acceptances table created' AS detail;
SELECT '  - Stored procedure sp_get_user_acceptance_status created' AS detail;
SELECT '  - View v_legal_acceptance_stats created' AS detail;
SELECT '  - Triggers for version management created' AS detail;
SELECT '  - Seed data for tenants 1 and 2 inserted' AS detail;

-- ============================================================================
-- MIGRATION COMPLETE
-- ============================================================================

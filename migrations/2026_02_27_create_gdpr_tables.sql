-- Copyright © 2024–2026 Jasper Ford
-- SPDX-License-Identifier: AGPL-3.0-or-later
-- Fix 14: Create GDPR tables
-- 2026-02-27
--
-- Creates all tables required by src/Services/Enterprise/GdprService.php.
-- Column names and types are derived directly from the INSERT/SELECT/UPDATE
-- statements in that service. All tables use CREATE TABLE IF NOT EXISTS for
-- idempotency. No AFTER column_name syntax is used anywhere.

-- ============================================================
-- 1. consent_types
--    Global (non-tenant-scoped) definitions of consent types.
--    Referenced by: getConsentTypes(), getActiveConsentTypes(),
--                   getConsentType(), setTenantConsentVersion()
--    Columns from SELECT: slug, name, description, category,
--    is_required, current_version, current_text, display_order, is_active
-- ============================================================
CREATE TABLE IF NOT EXISTS consent_types (
    id              INT UNSIGNED     NOT NULL AUTO_INCREMENT,
    slug            VARCHAR(100)     NOT NULL,
    name            VARCHAR(255)     NOT NULL,
    description     TEXT             DEFAULT NULL,
    category        VARCHAR(100)     DEFAULT NULL,
    is_required     TINYINT(1)       NOT NULL DEFAULT 0,
    current_version VARCHAR(20)      NOT NULL DEFAULT '1.0',
    current_text    LONGTEXT         DEFAULT NULL,
    display_order   SMALLINT         NOT NULL DEFAULT 0,
    is_active       TINYINT(1)       NOT NULL DEFAULT 1,
    created_at      DATETIME         NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at      DATETIME         NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    UNIQUE KEY uq_consent_types_slug (slug),
    KEY idx_consent_types_active (is_active),
    KEY idx_consent_types_required_active (is_required, is_active)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================================
-- 2. tenant_consent_overrides
--    Per-tenant version/text overrides for consent types.
--    Referenced by: setTenantConsentVersion(), getTenantConsentOverrides(),
--                   resetTenantConsentVersion(), getConsentType()
--    Columns from INSERT: tenant_id, consent_type_slug,
--    current_version, current_text, is_active
-- ============================================================
CREATE TABLE IF NOT EXISTS tenant_consent_overrides (
    id                INT UNSIGNED     NOT NULL AUTO_INCREMENT,
    tenant_id         INT UNSIGNED     NOT NULL,
    consent_type_slug VARCHAR(100)     NOT NULL,
    current_version   VARCHAR(20)      NOT NULL,
    current_text      LONGTEXT         DEFAULT NULL,
    is_active         TINYINT(1)       NOT NULL DEFAULT 1,
    created_at        DATETIME         NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at        DATETIME         NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    UNIQUE KEY uq_tco_tenant_slug (tenant_id, consent_type_slug),
    KEY idx_tco_tenant_active (tenant_id, is_active),
    CONSTRAINT fk_tco_consent_type FOREIGN KEY (consent_type_slug)
        REFERENCES consent_types (slug) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================================
-- 3. user_consents
--    Per-user consent records scoped by tenant.
--    Referenced by: recordConsent(), hasConsent(), withdrawConsent(),
--                   getUserConsents(), isConsentUpToDate(),
--                   getOutdatedConsents(), backfillConsents()
--    Columns from INSERT: user_id, tenant_id, consent_type,
--    consent_given, consent_text, consent_version, consent_hash,
--    ip_address, user_agent, source, given_at
--    Columns from SELECT/UPDATE: withdrawn_at, created_at, updated_at
-- ============================================================
CREATE TABLE IF NOT EXISTS user_consents (
    id               INT UNSIGNED     NOT NULL AUTO_INCREMENT,
    user_id          INT UNSIGNED     NOT NULL,
    tenant_id        INT UNSIGNED     NOT NULL,
    consent_type     VARCHAR(100)     NOT NULL,
    consent_given    TINYINT(1)       NOT NULL DEFAULT 0,
    consent_text     LONGTEXT         DEFAULT NULL,
    consent_version  VARCHAR(20)      NOT NULL DEFAULT '1.0',
    consent_hash     VARCHAR(64)      DEFAULT NULL,
    ip_address       VARCHAR(45)      DEFAULT NULL,
    user_agent       VARCHAR(512)     DEFAULT NULL,
    source           VARCHAR(50)      NOT NULL DEFAULT 'web',
    given_at         DATETIME         DEFAULT NULL,
    withdrawn_at     DATETIME         DEFAULT NULL,
    created_at       DATETIME         NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at       DATETIME         NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    -- ON DUPLICATE KEY UPDATE targets this unique key
    UNIQUE KEY uq_user_consents_user_tenant_type (user_id, tenant_id, consent_type),
    KEY idx_user_consents_tenant (tenant_id),
    KEY idx_user_consents_user_tenant (user_id, tenant_id),
    KEY idx_user_consents_type (consent_type),
    KEY idx_user_consents_given (consent_given)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================================
-- 4. gdpr_requests
--    Data subject access/portability/erasure/rectification/restriction
--    requests.
--    Referenced by: submitRequest(), getRequest(), processRequest(),
--                   getUserRequests(), exportUserData(), deleteUser()
--    Columns from INSERT: user_id, tenant_id, request_type, status,
--    priority, verification_token, notes, metadata
--    Columns from SELECT/UPDATE: id, requested_at, processed_at,
--    acknowledged_at, export_file_path, export_expires_at, processed_by
-- ============================================================
CREATE TABLE IF NOT EXISTS gdpr_requests (
    id                    INT UNSIGNED     NOT NULL AUTO_INCREMENT,
    user_id               INT UNSIGNED     NOT NULL,
    tenant_id             INT UNSIGNED     NOT NULL,
    request_type          ENUM(
                              'access',
                              'portability',
                              'erasure',
                              'rectification',
                              'restriction'
                          )                NOT NULL,
    status                ENUM(
                              'pending',
                              'processing',
                              'completed',
                              'rejected'
                          )                NOT NULL DEFAULT 'pending',
    priority              ENUM(
                              'normal',
                              'high',
                              'urgent'
                          )                NOT NULL DEFAULT 'normal',
    verification_token    VARCHAR(64)      DEFAULT NULL,
    notes                 TEXT             DEFAULT NULL,
    metadata              JSON             DEFAULT NULL,
    export_file_path      VARCHAR(512)     DEFAULT NULL,
    export_expires_at     DATETIME         DEFAULT NULL,
    processed_by          INT UNSIGNED     DEFAULT NULL,
    requested_at          DATETIME         NOT NULL DEFAULT CURRENT_TIMESTAMP,
    acknowledged_at       DATETIME         DEFAULT NULL,
    processed_at          DATETIME         DEFAULT NULL,
    PRIMARY KEY (id),
    KEY idx_gdpr_requests_tenant_status (tenant_id, status),
    KEY idx_gdpr_requests_user_tenant (user_id, tenant_id),
    KEY idx_gdpr_requests_requested_at (requested_at),
    KEY idx_gdpr_requests_status_priority (status, priority)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================================
-- 5. data_breach_log
--    Records of data breach incidents.
--    Referenced by: reportBreach(), getBreachDeadline(),
--                   getComplianceStats()
--    Columns from INSERT: tenant_id, breach_id, breach_type, severity,
--    description, data_categories_affected, number_of_records_affected,
--    number_of_users_affected, detected_at, occurred_at, created_by, status
-- ============================================================
CREATE TABLE IF NOT EXISTS data_breach_log (
    id                          INT UNSIGNED     NOT NULL AUTO_INCREMENT,
    tenant_id                   INT UNSIGNED     NOT NULL,
    breach_id                   VARCHAR(50)      NOT NULL,
    breach_type                 VARCHAR(100)     NOT NULL,
    severity                    ENUM(
                                    'low',
                                    'medium',
                                    'high',
                                    'critical'
                                )                NOT NULL DEFAULT 'medium',
    description                 TEXT             NOT NULL,
    data_categories_affected    JSON             DEFAULT NULL,
    number_of_records_affected  INT UNSIGNED     DEFAULT NULL,
    number_of_users_affected    INT UNSIGNED     DEFAULT NULL,
    detected_at                 DATETIME         NOT NULL,
    occurred_at                 DATETIME         DEFAULT NULL,
    created_by                  INT UNSIGNED     NOT NULL,
    status                      VARCHAR(50)      NOT NULL DEFAULT 'detected',
    created_at                  DATETIME         NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at                  DATETIME         NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    UNIQUE KEY uq_data_breach_log_breach_id (breach_id),
    KEY idx_data_breach_log_tenant (tenant_id),
    KEY idx_data_breach_log_tenant_status (tenant_id, status),
    KEY idx_data_breach_log_severity (severity),
    KEY idx_data_breach_log_detected_at (detected_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================================
-- 6. gdpr_audit_log
--    Audit trail of all GDPR-related administrative actions.
--    Referenced by: logAction(), getAuditLog()
--    Columns from INSERT: user_id, admin_id, tenant_id, action,
--    entity_type, entity_id, old_value, new_value, ip_address,
--    user_agent, request_id
--    Columns from SELECT: action, entity_type, entity_id, created_at,
--    ip_address
-- ============================================================
CREATE TABLE IF NOT EXISTS gdpr_audit_log (
    id           INT UNSIGNED     NOT NULL AUTO_INCREMENT,
    user_id      INT UNSIGNED     DEFAULT NULL,
    admin_id     INT UNSIGNED     DEFAULT NULL,
    tenant_id    INT UNSIGNED     NOT NULL,
    action       VARCHAR(100)     NOT NULL,
    entity_type  VARCHAR(100)     DEFAULT NULL,
    entity_id    INT UNSIGNED     DEFAULT NULL,
    old_value    JSON             DEFAULT NULL,
    new_value    JSON             DEFAULT NULL,
    ip_address   VARCHAR(45)      DEFAULT NULL,
    user_agent   VARCHAR(512)     DEFAULT NULL,
    request_id   VARCHAR(64)      DEFAULT NULL,
    created_at   DATETIME         NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    KEY idx_gdpr_audit_log_user_tenant (user_id, tenant_id),
    KEY idx_gdpr_audit_log_tenant (tenant_id),
    KEY idx_gdpr_audit_log_action (action),
    KEY idx_gdpr_audit_log_entity (entity_type, entity_id),
    KEY idx_gdpr_audit_log_created_at (created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

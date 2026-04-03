-- Migration: SSO/SAML group mapping support
-- Idempotent

CREATE TABLE IF NOT EXISTS group_sso_mappings (
    id INT AUTO_INCREMENT PRIMARY KEY,
    tenant_id INT NOT NULL,
    saml_group_name VARCHAR(255) NOT NULL,
    group_id INT NOT NULL,
    auto_assign TINYINT(1) NOT NULL DEFAULT 1,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY uk_gsm (tenant_id, saml_group_name, group_id),
    INDEX idx_gsm_tenant (tenant_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

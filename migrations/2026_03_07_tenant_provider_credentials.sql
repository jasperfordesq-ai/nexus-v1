-- Migration: tenant_provider_credentials
-- Stores per-tenant, per-provider API credentials (encrypted at rest)
-- Allows each tenant to bring their own identity verification provider keys

CREATE TABLE IF NOT EXISTS tenant_provider_credentials (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    tenant_id INT UNSIGNED NOT NULL,
    provider_slug VARCHAR(50) NOT NULL,
    credentials_encrypted TEXT NOT NULL COMMENT 'AES-256-GCM encrypted JSON with api_key, webhook_secret, etc.',
    is_active TINYINT(1) NOT NULL DEFAULT 1,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    UNIQUE KEY uq_tenant_provider (tenant_id, provider_slug),
    KEY idx_tenant_active (tenant_id, is_active)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

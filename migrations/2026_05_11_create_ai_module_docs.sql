-- Migration: Create ai_module_docs table
--
-- Per-tenant, admin-editable "how each module works" content. Injected into
-- the AI chat system prompt when the user's question matches one of the
-- module's keywords. This is the canonical, plain-language description of
-- platform features — overrides the hardcoded SUPPORT_AREAS map in PHP.

CREATE TABLE IF NOT EXISTS ai_module_docs (
    id            INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    tenant_id     INT UNSIGNED NOT NULL,
    module_slug   VARCHAR(64) NOT NULL,
    title         VARCHAR(255) NOT NULL,
    body          TEXT NOT NULL,
    keywords      JSON NULL,
    is_active     TINYINT(1) NOT NULL DEFAULT 1,
    created_at    TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at    TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    created_by    INT UNSIGNED NULL,

    UNIQUE KEY uk_tenant_module (tenant_id, module_slug),
    INDEX idx_tenant_active (tenant_id, is_active)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

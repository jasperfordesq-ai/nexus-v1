-- Migration: Create content_embeddings table for OpenAI semantic search
-- Stores vector embeddings for listings, users, events, groups per tenant
-- Used by EmbeddingService for cosine-similarity matching

CREATE TABLE IF NOT EXISTS content_embeddings (
    id              INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    tenant_id       INT UNSIGNED NOT NULL,
    content_type    ENUM('listing', 'user', 'event', 'group') NOT NULL,
    content_id      INT UNSIGNED NOT NULL,
    model           VARCHAR(100) NOT NULL DEFAULT 'text-embedding-3-small',
    embedding       JSON NOT NULL,
    created_at      TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at      TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,

    UNIQUE KEY uk_content (tenant_id, content_type, content_id),
    INDEX idx_tenant_type (tenant_id, content_type)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

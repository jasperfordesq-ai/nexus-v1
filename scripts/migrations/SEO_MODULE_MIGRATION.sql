-- ============================================================================
-- SEO MODULE MIGRATION - Run in phpMyAdmin
-- ============================================================================
-- This migration sets up the SEO module tables and columns.
-- Errors for "Duplicate column" or "Table already exists" are NORMAL -
-- just means those already exist. Continue to the next statement.
-- ============================================================================

-- 1. SEO REDIRECTS TABLE
CREATE TABLE IF NOT EXISTS seo_redirects (
    id INT AUTO_INCREMENT PRIMARY KEY,
    tenant_id INT NOT NULL,
    source_url VARCHAR(500) NOT NULL,
    destination_url VARCHAR(500) NOT NULL,
    hits INT DEFAULT 0,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY unique_redirect (tenant_id, source_url(191)),
    INDEX idx_source (source_url(191)),
    INDEX idx_tenant (tenant_id),
    FOREIGN KEY (tenant_id) REFERENCES tenants(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 2. SEO METADATA TABLE
CREATE TABLE IF NOT EXISTS seo_metadata (
    id INT AUTO_INCREMENT PRIMARY KEY,
    tenant_id INT NOT NULL,
    entity_type VARCHAR(50) NOT NULL,
    entity_id INT NULL,
    meta_title VARCHAR(255) NULL,
    meta_description TEXT NULL,
    meta_keywords VARCHAR(500) NULL,
    canonical_url VARCHAR(500) NULL,
    og_image_url VARCHAR(500) NULL,
    noindex TINYINT(1) DEFAULT 0,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NULL ON UPDATE CURRENT_TIMESTAMP,
    UNIQUE KEY unique_entity (tenant_id, entity_type, entity_id),
    INDEX idx_entity_lookup (entity_type, entity_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================================================
-- 3. TENANT ORGANIZATION COLUMNS
-- Run each line ONE AT A TIME. Skip any that show "Duplicate column" error.
-- ============================================================================

-- ALTER TABLE tenants ADD COLUMN contact_email VARCHAR(255) DEFAULT NULL;
-- ALTER TABLE tenants ADD COLUMN contact_phone VARCHAR(50) DEFAULT NULL;
-- ALTER TABLE tenants ADD COLUMN address VARCHAR(500) DEFAULT NULL;
-- ALTER TABLE tenants ADD COLUMN social_facebook VARCHAR(255) DEFAULT NULL;
-- ALTER TABLE tenants ADD COLUMN social_twitter VARCHAR(255) DEFAULT NULL;
-- ALTER TABLE tenants ADD COLUMN social_instagram VARCHAR(255) DEFAULT NULL;
-- ALTER TABLE tenants ADD COLUMN social_linkedin VARCHAR(255) DEFAULT NULL;
-- ALTER TABLE tenants ADD COLUMN social_youtube VARCHAR(255) DEFAULT NULL;

-- ============================================================================
-- DONE! The SEO module is ready to use.
-- ============================================================================

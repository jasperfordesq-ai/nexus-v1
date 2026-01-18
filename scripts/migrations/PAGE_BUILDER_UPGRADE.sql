-- =============================================
-- PAGE BUILDER UPGRADE MIGRATION
-- Adds versioning, scheduling, and ordering
-- =============================================

-- Add new columns to pages table
ALTER TABLE pages
ADD COLUMN IF NOT EXISTS `sort_order` INT DEFAULT 0 AFTER `is_published`,
ADD COLUMN IF NOT EXISTS `publish_at` DATETIME NULL AFTER `sort_order`,
ADD COLUMN IF NOT EXISTS `updated_at` DATETIME NULL AFTER `created_at`;

-- Create page versions table for history/rollback
CREATE TABLE IF NOT EXISTS page_versions (
    id INT AUTO_INCREMENT PRIMARY KEY,
    page_id INT NOT NULL,
    tenant_id INT NOT NULL,
    version_number INT NOT NULL DEFAULT 1,
    title VARCHAR(255) NOT NULL,
    slug VARCHAR(255) NOT NULL,
    content LONGTEXT,
    created_by INT NULL,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    restore_note VARCHAR(255) NULL,
    INDEX idx_page_versions_page (page_id),
    INDEX idx_page_versions_tenant (tenant_id),
    FOREIGN KEY (page_id) REFERENCES pages(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Index for scheduled publishing queries
CREATE INDEX IF NOT EXISTS idx_pages_publish_at ON pages(publish_at);
CREATE INDEX IF NOT EXISTS idx_pages_sort_order ON pages(tenant_id, sort_order);

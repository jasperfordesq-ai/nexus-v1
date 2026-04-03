-- Migration: Group media gallery (photos/videos)
-- Date: 2026-04-03
-- Idempotent: uses IF NOT EXISTS

CREATE TABLE IF NOT EXISTS group_media (
    id INT AUTO_INCREMENT PRIMARY KEY,
    tenant_id INT NOT NULL,
    group_id INT NOT NULL,
    user_id INT NOT NULL,
    media_type ENUM('image', 'video') NOT NULL DEFAULT 'image',
    file_path VARCHAR(500) NULL,
    url VARCHAR(500) NULL COMMENT 'External URL for video embeds',
    thumbnail_path VARCHAR(500) NULL,
    caption TEXT NULL,
    file_size BIGINT NOT NULL DEFAULT 0,
    width INT NULL,
    height INT NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_gm_group (group_id, tenant_id),
    INDEX idx_gm_type (group_id, media_type)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Wiki pages for knowledge base
CREATE TABLE IF NOT EXISTS group_wiki_pages (
    id INT AUTO_INCREMENT PRIMARY KEY,
    tenant_id INT NOT NULL,
    group_id INT NOT NULL,
    parent_id INT NULL,
    title VARCHAR(500) NOT NULL,
    slug VARCHAR(500) NOT NULL,
    content LONGTEXT NULL,
    created_by INT NOT NULL,
    last_edited_by INT NULL,
    sort_order INT NOT NULL DEFAULT 0,
    is_published TINYINT(1) NOT NULL DEFAULT 1,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX idx_gwp_group (group_id, tenant_id),
    INDEX idx_gwp_parent (parent_id),
    UNIQUE KEY uk_gwp_slug (group_id, slug)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS group_wiki_revisions (
    id INT AUTO_INCREMENT PRIMARY KEY,
    page_id INT NOT NULL,
    content LONGTEXT NOT NULL,
    edited_by INT NOT NULL,
    change_summary VARCHAR(255) NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_gwr_page (page_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

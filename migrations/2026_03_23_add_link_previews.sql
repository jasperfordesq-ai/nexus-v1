-- Link Previews: tables for cached OG metadata and associations with posts/messages
-- Created: 2026-03-23

CREATE TABLE IF NOT EXISTS link_previews (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    url_hash VARCHAR(64) NOT NULL,
    url TEXT NOT NULL,
    title VARCHAR(500) DEFAULT NULL,
    description TEXT DEFAULT NULL,
    image_url TEXT DEFAULT NULL,
    site_name VARCHAR(255) DEFAULT NULL,
    favicon_url TEXT DEFAULT NULL,
    domain VARCHAR(255) NOT NULL,
    content_type VARCHAR(50) DEFAULT 'website',
    embed_html TEXT DEFAULT NULL,
    fetched_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    expires_at TIMESTAMP NULL DEFAULT NULL,
    UNIQUE KEY uk_url_hash (url_hash),
    KEY idx_domain (domain),
    KEY idx_expires (expires_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS post_link_previews (
    post_id BIGINT UNSIGNED NOT NULL,
    link_preview_id BIGINT UNSIGNED NOT NULL,
    display_order TINYINT UNSIGNED NOT NULL DEFAULT 0,
    PRIMARY KEY (post_id, link_preview_id),
    KEY idx_preview (link_preview_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS message_link_previews (
    message_id BIGINT UNSIGNED NOT NULL,
    link_preview_id BIGINT UNSIGNED NOT NULL,
    PRIMARY KEY (message_id, link_preview_id),
    KEY idx_preview (link_preview_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

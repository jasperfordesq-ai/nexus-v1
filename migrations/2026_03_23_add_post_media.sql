-- Post Media table for carousel/multi-image posts
-- Supports multiple media items per feed post with ordering and metadata

CREATE TABLE IF NOT EXISTS post_media (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    tenant_id INT NOT NULL,
    post_id BIGINT UNSIGNED NOT NULL,
    media_type ENUM('image','video') NOT NULL DEFAULT 'image',
    file_url TEXT NOT NULL,
    thumbnail_url TEXT DEFAULT NULL,
    alt_text VARCHAR(500) DEFAULT NULL,
    width INT UNSIGNED DEFAULT NULL,
    height INT UNSIGNED DEFAULT NULL,
    file_size INT UNSIGNED DEFAULT NULL,
    display_order TINYINT UNSIGNED NOT NULL DEFAULT 0,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    KEY idx_post_media (tenant_id, post_id),
    KEY idx_display_order (post_id, display_order),
    FOREIGN KEY (tenant_id) REFERENCES tenants(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

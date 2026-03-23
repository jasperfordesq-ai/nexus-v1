-- Stories feature: 24-hour disappearing content
-- Migration: 2026_03_23_add_stories.sql

CREATE TABLE IF NOT EXISTS stories (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    tenant_id INT NOT NULL,
    user_id INT UNSIGNED NOT NULL,
    media_type ENUM('image','text','poll') NOT NULL DEFAULT 'image',
    media_url TEXT DEFAULT NULL,
    thumbnail_url TEXT DEFAULT NULL,
    text_content VARCHAR(500) DEFAULT NULL,
    text_style JSON DEFAULT NULL,
    background_color VARCHAR(20) DEFAULT NULL,
    background_gradient VARCHAR(100) DEFAULT NULL,
    duration INT UNSIGNED NOT NULL DEFAULT 5,
    poll_question VARCHAR(255) DEFAULT NULL,
    poll_options JSON DEFAULT NULL,
    view_count INT UNSIGNED NOT NULL DEFAULT 0,
    is_active TINYINT(1) NOT NULL DEFAULT 1,
    expires_at TIMESTAMP NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    KEY idx_tenant_user (tenant_id, user_id),
    KEY idx_active_expires (tenant_id, is_active, expires_at),
    KEY idx_user_active (user_id, is_active, expires_at),
    FOREIGN KEY (tenant_id) REFERENCES tenants(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS story_views (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    story_id BIGINT UNSIGNED NOT NULL,
    viewer_id INT UNSIGNED NOT NULL,
    viewed_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY uk_story_viewer (story_id, viewer_id),
    KEY idx_story_views (story_id),
    KEY idx_viewer (viewer_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS story_reactions (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    story_id BIGINT UNSIGNED NOT NULL,
    user_id INT UNSIGNED NOT NULL,
    reaction_type VARCHAR(20) NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    KEY idx_story_reactions (story_id),
    FOREIGN KEY (story_id) REFERENCES stories(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS story_highlights (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    tenant_id INT NOT NULL,
    user_id INT UNSIGNED NOT NULL,
    title VARCHAR(100) NOT NULL,
    cover_url TEXT DEFAULT NULL,
    display_order TINYINT UNSIGNED NOT NULL DEFAULT 0,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    KEY idx_user_highlights (tenant_id, user_id),
    FOREIGN KEY (tenant_id) REFERENCES tenants(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS story_highlight_items (
    highlight_id BIGINT UNSIGNED NOT NULL,
    story_id BIGINT UNSIGNED NOT NULL,
    display_order TINYINT UNSIGNED NOT NULL DEFAULT 0,
    PRIMARY KEY (highlight_id, story_id),
    FOREIGN KEY (highlight_id) REFERENCES story_highlights(id) ON DELETE CASCADE,
    FOREIGN KEY (story_id) REFERENCES stories(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS story_poll_votes (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    story_id BIGINT UNSIGNED NOT NULL,
    user_id INT UNSIGNED NOT NULL,
    option_index TINYINT UNSIGNED NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY uk_story_poll_vote (story_id, user_id),
    FOREIGN KEY (story_id) REFERENCES stories(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

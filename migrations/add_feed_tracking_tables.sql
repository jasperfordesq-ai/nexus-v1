-- Feed impression and click tracking tables for EdgeRank analytics
-- Created: 2026-03-14

CREATE TABLE IF NOT EXISTS feed_impressions (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    post_id INT UNSIGNED NOT NULL,
    user_id INT UNSIGNED NOT NULL,
    tenant_id INT UNSIGNED NOT NULL,
    view_count INT UNSIGNED NOT NULL DEFAULT 1,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    UNIQUE KEY uq_impression (post_id, user_id, tenant_id),
    KEY idx_post_tenant (post_id, tenant_id),
    KEY idx_user_tenant (user_id, tenant_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS feed_clicks (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    post_id INT UNSIGNED NOT NULL,
    user_id INT UNSIGNED NOT NULL,
    tenant_id INT UNSIGNED NOT NULL,
    click_count INT UNSIGNED NOT NULL DEFAULT 1,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    UNIQUE KEY uq_click (post_id, user_id, tenant_id),
    KEY idx_post_tenant (post_id, tenant_id),
    KEY idx_user_tenant (user_id, tenant_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

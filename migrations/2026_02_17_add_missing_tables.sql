-- Migration: Add missing tables referenced in PHP codebase
-- Date: 2026-02-17
-- Tables: feed_hidden, feed_muted_users, challenge_progress, seo_audits, group_views

-- Feed: hide a post from a user's feed
CREATE TABLE IF NOT EXISTS feed_hidden (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    tenant_id INT NOT NULL,
    target_type VARCHAR(50) NOT NULL DEFAULT 'post',
    target_id INT NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY unique_hidden (user_id, tenant_id, target_type, target_id),
    INDEX idx_user_tenant (user_id, tenant_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Feed: mute a user so their posts don't appear
CREATE TABLE IF NOT EXISTS feed_muted_users (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    tenant_id INT NOT NULL,
    muted_user_id INT NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY unique_muted (user_id, tenant_id, muted_user_id),
    INDEX idx_user_tenant (user_id, tenant_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Gamification: challenge progress (used by GamificationV2ApiController reward claims)
-- Note: ChallengeService uses user_challenge_progress; this table is used by the
-- legacy GamificationV2ApiController reward-claim endpoint.
CREATE TABLE IF NOT EXISTS challenge_progress (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    tenant_id INT NOT NULL,
    challenge_id INT NOT NULL,
    status ENUM('in_progress', 'completed', 'claimed') NOT NULL DEFAULT 'in_progress',
    claimed_at TIMESTAMP NULL DEFAULT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    UNIQUE KEY unique_user_challenge (user_id, challenge_id),
    INDEX idx_user_tenant (user_id, tenant_id),
    INDEX idx_challenge (challenge_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- SEO: audit results stored by admin SEO tools
CREATE TABLE IF NOT EXISTS seo_audits (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    tenant_id INT NOT NULL,
    url VARCHAR(2048) NOT NULL DEFAULT '',
    results JSON NULL,
    score TINYINT UNSIGNED NULL DEFAULT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_tenant (tenant_id),
    INDEX idx_created (created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Groups: page view tracking for group analytics
CREATE TABLE IF NOT EXISTS group_views (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    group_id INT NOT NULL,
    tenant_id INT NOT NULL,
    user_id INT NULL DEFAULT NULL,
    viewed_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_group (group_id),
    INDEX idx_group_viewed (group_id, viewed_at),
    INDEX idx_tenant (tenant_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

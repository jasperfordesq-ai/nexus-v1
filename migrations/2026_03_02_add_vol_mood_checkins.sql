-- Add mood check-in tracking for volunteer wellbeing
-- Date: 2026-03-02

CREATE TABLE IF NOT EXISTS vol_mood_checkins (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    tenant_id INT UNSIGNED NOT NULL,
    user_id INT UNSIGNED NOT NULL,
    mood TINYINT UNSIGNED NOT NULL COMMENT '1-5 scale',
    note TEXT NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_tenant_user (tenant_id, user_id),
    INDEX idx_created (created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

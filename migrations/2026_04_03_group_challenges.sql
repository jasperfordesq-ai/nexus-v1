-- Migration: Group challenges (time-bound goals with rewards)
-- Date: 2026-04-03
-- Idempotent: uses IF NOT EXISTS

CREATE TABLE IF NOT EXISTS group_challenges (
    id INT AUTO_INCREMENT PRIMARY KEY,
    tenant_id INT NOT NULL,
    group_id INT NOT NULL,
    created_by INT NOT NULL,
    title VARCHAR(255) NOT NULL,
    description TEXT NULL,
    metric VARCHAR(50) NOT NULL COMMENT 'e.g. posts, discussions, events, members, files',
    target_value INT NOT NULL,
    current_value INT NOT NULL DEFAULT 0,
    reward_xp INT NOT NULL DEFAULT 100,
    reward_badge VARCHAR(100) NULL,
    status ENUM('active', 'completed', 'expired', 'cancelled') NOT NULL DEFAULT 'active',
    starts_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    ends_at DATETIME NOT NULL,
    completed_at DATETIME NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX idx_gc_group (group_id, status),
    INDEX idx_gc_tenant (tenant_id),
    INDEX idx_gc_active (status, ends_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

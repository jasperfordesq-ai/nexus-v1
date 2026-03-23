-- Migration: Add user_presence table for real-time online/offline presence tracking
-- Date: 2026-03-23
-- Idempotent: uses IF NOT EXISTS

CREATE TABLE IF NOT EXISTS user_presence (
    user_id INT UNSIGNED NOT NULL,
    tenant_id INT NOT NULL,
    status ENUM('online','away','dnd','offline') NOT NULL DEFAULT 'offline',
    custom_status VARCHAR(80) DEFAULT NULL,
    status_emoji VARCHAR(10) DEFAULT NULL,
    last_seen_at TIMESTAMP NULL DEFAULT NULL,
    last_activity_at TIMESTAMP NULL DEFAULT NULL,
    hide_presence TINYINT(1) NOT NULL DEFAULT 0,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (user_id),
    KEY idx_tenant_status (tenant_id, status),
    KEY idx_last_seen (tenant_id, last_seen_at),
    FOREIGN KEY (tenant_id) REFERENCES tenants(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

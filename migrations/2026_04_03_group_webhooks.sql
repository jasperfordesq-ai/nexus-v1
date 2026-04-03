-- Migration: Group webhooks for external integrations
-- Date: 2026-04-03
-- Idempotent: uses IF NOT EXISTS

CREATE TABLE IF NOT EXISTS group_webhooks (
    id INT AUTO_INCREMENT PRIMARY KEY,
    tenant_id INT NOT NULL,
    group_id INT NOT NULL,
    url VARCHAR(500) NOT NULL,
    events JSON NOT NULL COMMENT 'Array of event types to trigger on',
    secret VARCHAR(255) NULL COMMENT 'HMAC secret for signature verification',
    is_active TINYINT(1) NOT NULL DEFAULT 1,
    last_fired_at DATETIME NULL,
    failure_count INT NOT NULL DEFAULT 0,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX idx_group_webhooks_group (group_id, is_active),
    INDEX idx_group_webhooks_tenant (tenant_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

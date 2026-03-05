-- Federation Realtime Queue Migration
-- Date: 2026-01-17
-- Creates table for SSE event queue (fallback when Pusher unavailable)

CREATE TABLE IF NOT EXISTS federation_realtime_queue (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    tenant_id INT(11) NOT NULL,
    user_id INT(11) DEFAULT NULL COMMENT 'NULL for tenant-wide events',
    event_type VARCHAR(50) NOT NULL,
    event_data JSON NOT NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    delivered_at DATETIME DEFAULT NULL,

    INDEX idx_tenant_user (tenant_id, user_id),
    INDEX idx_pending_events (tenant_id, user_id, delivered_at),
    INDEX idx_cleanup (delivered_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Clean up old events (run this periodically via cron)
-- DELETE FROM federation_realtime_queue WHERE delivered_at IS NOT NULL AND delivered_at < DATE_SUB(NOW(), INTERVAL 24 HOUR);

-- Federation Webhooks tables
-- Created: 2026-03-28

CREATE TABLE IF NOT EXISTS federation_webhooks (
    id INT AUTO_INCREMENT PRIMARY KEY,
    tenant_id INT NOT NULL,
    url VARCHAR(500) NOT NULL,
    secret VARCHAR(255) NOT NULL,
    events JSON NOT NULL COMMENT 'Array of event types to subscribe to',
    status ENUM('active', 'inactive', 'failing') DEFAULT 'active',
    description VARCHAR(255) DEFAULT NULL,
    consecutive_failures INT DEFAULT 0,
    last_triggered_at TIMESTAMP NULL,
    last_success_at TIMESTAMP NULL,
    last_failure_at TIMESTAMP NULL,
    last_failure_reason VARCHAR(500) DEFAULT NULL,
    created_by INT NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX idx_tenant (tenant_id),
    INDEX idx_status (status)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS federation_webhook_logs (
    id BIGINT AUTO_INCREMENT PRIMARY KEY,
    webhook_id INT NOT NULL,
    tenant_id INT NOT NULL,
    event_type VARCHAR(100) NOT NULL,
    payload JSON NOT NULL,
    response_code INT DEFAULT NULL,
    response_body TEXT DEFAULT NULL,
    response_time_ms INT DEFAULT NULL,
    success TINYINT(1) DEFAULT 0,
    error_message VARCHAR(500) DEFAULT NULL,
    attempt_number INT DEFAULT 1,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_webhook (webhook_id),
    INDEX idx_tenant (tenant_id),
    INDEX idx_created (created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

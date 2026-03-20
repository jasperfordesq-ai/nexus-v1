DROP TABLE IF EXISTS group_content_flags;

CREATE TABLE group_content_flags (
    id INT AUTO_INCREMENT PRIMARY KEY,
    tenant_id INT NOT NULL,
    content_type VARCHAR(50) NOT NULL,
    content_id INT NOT NULL,
    reported_by INT NOT NULL,
    reason VARCHAR(50) NOT NULL,
    description TEXT NULL,
    status VARCHAR(20) DEFAULT 'pending',
    moderated_by INT NULL,
    moderation_action VARCHAR(50) NULL,
    moderator_notes TEXT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    resolved_at TIMESTAMP NULL,
    INDEX idx_tenant_status (tenant_id, status),
    INDEX idx_content (content_type, content_id),
    INDEX idx_reporter (tenant_id, reported_by)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

DROP TABLE IF EXISTS group_approval_requests;

CREATE TABLE group_approval_requests (
    id INT AUTO_INCREMENT PRIMARY KEY,
    tenant_id INT NOT NULL,
    group_id INT NOT NULL,
    submitted_by INT NOT NULL,
    submission_notes TEXT NULL,
    reviewed_by INT NULL,
    review_notes TEXT NULL,
    status VARCHAR(50) DEFAULT 'pending',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    reviewed_at TIMESTAMP NULL,
    INDEX idx_tenant_status (tenant_id, status),
    INDEX idx_group (group_id),
    INDEX idx_submitter (tenant_id, submitted_by)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

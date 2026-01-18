-- Admin Actions Audit Log Table
-- Tracks administrative actions for compliance, security, and auditing

CREATE TABLE IF NOT EXISTS admin_actions (
    id INT AUTO_INCREMENT PRIMARY KEY,
    admin_id INT NOT NULL,
    admin_name VARCHAR(255) NOT NULL,
    admin_email VARCHAR(255) NOT NULL,
    action_type VARCHAR(100) NOT NULL, -- 'impersonate', 'user_update', 'user_delete', etc.
    target_user_id INT NULL, -- User being acted upon (null for non-user actions)
    target_user_name VARCHAR(255) NULL,
    target_user_email VARCHAR(255) NULL,
    details TEXT NULL, -- JSON or text with additional context
    ip_address VARCHAR(45) NULL, -- IPv4 or IPv6
    user_agent TEXT NULL,
    tenant_id INT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,

    INDEX idx_admin_id (admin_id),
    INDEX idx_target_user_id (target_user_id),
    INDEX idx_action_type (action_type),
    INDEX idx_created_at (created_at),
    INDEX idx_tenant_id (tenant_id),

    FOREIGN KEY (admin_id) REFERENCES users(id) ON DELETE CASCADE,
    FOREIGN KEY (target_user_id) REFERENCES users(id) ON DELETE SET NULL,
    FOREIGN KEY (tenant_id) REFERENCES tenants(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

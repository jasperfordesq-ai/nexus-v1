-- ===================================================================
-- CREATE SESSIONS TABLE
-- ===================================================================
-- This table stores user session data for tracking active users,
-- session management, and real-time analytics in the Enterprise dashboard.
--
-- Date: 2026-01-11
-- ===================================================================

CREATE TABLE IF NOT EXISTS sessions (
    id VARCHAR(255) PRIMARY KEY COMMENT 'Session ID (hash)',
    user_id INT DEFAULT NULL COMMENT 'User ID if authenticated',
    tenant_id INT DEFAULT NULL COMMENT 'Tenant ID for multi-tenancy',

    -- Session data
    session_data TEXT COMMENT 'Serialized session data',

    -- Activity tracking
    ip_address VARCHAR(45) COMMENT 'IPv4 or IPv6 address',
    user_agent TEXT COMMENT 'Browser user agent string',
    last_activity TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP COMMENT 'Last activity time',

    -- Session lifecycle
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP COMMENT 'Session start time',
    expires_at TIMESTAMP NULL DEFAULT NULL COMMENT 'Session expiry time',

    -- Additional metadata
    is_authenticated BOOLEAN DEFAULT FALSE COMMENT 'Is user logged in',
    device_type ENUM('desktop', 'mobile', 'tablet', 'unknown') DEFAULT 'unknown',

    -- Indexes for performance
    INDEX idx_user_id (user_id),
    INDEX idx_tenant_id (tenant_id),
    INDEX idx_last_activity (last_activity),
    INDEX idx_expires_at (expires_at),
    INDEX idx_user_tenant (user_id, tenant_id)

    -- Foreign key (optional - uncomment if you want referential integrity)
    -- , FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE SET NULL
    -- , FOREIGN KEY (tenant_id) REFERENCES tenants(id) ON DELETE SET NULL

) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
COMMENT='User sessions for tracking active users and session management';

-- ===================================================================
-- CLEANUP QUERY (Optional - run periodically via cron)
-- ===================================================================
-- Delete expired sessions older than 30 days
-- DELETE FROM sessions WHERE expires_at < DATE_SUB(NOW(), INTERVAL 30 DAY);

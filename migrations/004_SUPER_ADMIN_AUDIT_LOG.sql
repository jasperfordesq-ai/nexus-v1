-- ============================================================================
-- SUPER ADMIN AUDIT LOG
-- Track all hierarchy changes made through the Super Admin Panel
-- ============================================================================

-- Create audit log table
CREATE TABLE IF NOT EXISTS super_admin_audit_log (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,

    -- Who made the change
    actor_user_id INT UNSIGNED NOT NULL,
    actor_tenant_id INT UNSIGNED NOT NULL,
    actor_name VARCHAR(255) NOT NULL,
    actor_email VARCHAR(255) NOT NULL,

    -- What type of action
    action_type ENUM(
        'tenant_created',
        'tenant_updated',
        'tenant_deleted',
        'tenant_moved',
        'hub_toggled',
        'super_admin_granted',
        'super_admin_revoked',
        'user_created',
        'user_updated',
        'user_moved',
        'bulk_users_moved',
        'bulk_tenants_updated'
    ) NOT NULL,

    -- Target entity
    target_type ENUM('tenant', 'user', 'bulk') NOT NULL,
    target_id INT UNSIGNED NULL,
    target_name VARCHAR(255) NULL,

    -- Change details (JSON)
    old_values JSON NULL,
    new_values JSON NULL,

    -- Additional context
    description TEXT NULL,
    ip_address VARCHAR(45) NULL,
    user_agent TEXT NULL,

    -- Timestamps
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,

    -- Indexes
    INDEX idx_actor (actor_user_id),
    INDEX idx_action (action_type),
    INDEX idx_target (target_type, target_id),
    INDEX idx_created (created_at),
    INDEX idx_actor_tenant (actor_tenant_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Add comment
ALTER TABLE super_admin_audit_log COMMENT = 'Audit trail for Super Admin Panel hierarchy changes';

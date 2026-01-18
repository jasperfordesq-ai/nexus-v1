<?php
/**
 * RBAC Permissions System Migration
 * Creates granular permission system for enterprise compliance (SOC 2, ISO, GDPR)
 *
 * This implements Permission-Based Access Control (PBAC) for:
 * - Healthcare data handling (HIPAA compliance)
 * - Financial data handling (PCI-DSS, SOX compliance)
 * - Enterprise client requirements (Fortune 500)
 * - Multi-tenant security isolation
 */

require_once dirname(__DIR__, 2) . '/bootstrap.php';

use Nexus\Core\Database;

try {
    $db = Database::getInstance();

    echo "Creating RBAC Permissions System...\n";

    // 1. Permissions Table
    echo "Creating permissions table...\n";
    $db->exec("
        CREATE TABLE IF NOT EXISTS permissions (
            id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            name VARCHAR(100) NOT NULL UNIQUE COMMENT 'Permission identifier (e.g., users.delete)',
            display_name VARCHAR(150) NOT NULL COMMENT 'Human-readable name',
            description TEXT COMMENT 'Detailed description of what this permission allows',
            category VARCHAR(50) NOT NULL COMMENT 'Permission category (users, gdpr, config, etc.)',
            is_dangerous BOOLEAN DEFAULT FALSE COMMENT 'Requires extra confirmation (delete, ban, etc.)',
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,

            INDEX idx_category (category),
            INDEX idx_name (name)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
        COMMENT='Granular permissions for PBAC system'
    ");

    // 2. Roles Table
    echo "Creating roles table...\n";
    $db->exec("
        CREATE TABLE IF NOT EXISTS roles (
            id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            name VARCHAR(100) NOT NULL UNIQUE COMMENT 'Role identifier (e.g., gdpr_officer)',
            display_name VARCHAR(150) NOT NULL COMMENT 'Human-readable role name',
            description TEXT COMMENT 'Role purpose and responsibilities',
            is_system BOOLEAN DEFAULT FALSE COMMENT 'System role (cannot be deleted)',
            level INT UNSIGNED DEFAULT 0 COMMENT 'Role hierarchy level (higher = more privileges)',
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,

            INDEX idx_name (name),
            INDEX idx_level (level)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
        COMMENT='Admin roles with specific permission sets'
    ");

    // 3. Role-Permission Mapping
    echo "Creating role_permissions table...\n";
    $db->exec("
        CREATE TABLE IF NOT EXISTS role_permissions (
            id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            role_id INT UNSIGNED NOT NULL,
            permission_id INT UNSIGNED NOT NULL,
            granted_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            granted_by INT COMMENT 'User ID who granted this permission',

            UNIQUE KEY unique_role_permission (role_id, permission_id),
            FOREIGN KEY (role_id) REFERENCES roles(id) ON DELETE CASCADE,
            FOREIGN KEY (permission_id) REFERENCES permissions(id) ON DELETE CASCADE,

            INDEX idx_role (role_id),
            INDEX idx_permission (permission_id)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
        COMMENT='Many-to-many mapping of roles to permissions'
    ");

    // 4. User-Role Mapping
    echo "Creating user_roles table...\n";
    $db->exec("
        CREATE TABLE IF NOT EXISTS user_roles (
            id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            user_id INT NOT NULL,
            role_id INT UNSIGNED NOT NULL,
            assigned_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            assigned_by INT COMMENT 'User ID who assigned this role',
            expires_at TIMESTAMP NULL COMMENT 'Optional expiration for temporary access',

            UNIQUE KEY unique_user_role (user_id, role_id),
            FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
            FOREIGN KEY (role_id) REFERENCES roles(id) ON DELETE CASCADE,

            INDEX idx_user (user_id),
            INDEX idx_role (role_id),
            INDEX idx_expires (expires_at)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
        COMMENT='Many-to-many mapping of users to roles'
    ");

    // 5. User-Permission Overrides (Direct Permissions)
    echo "Creating user_permissions table...\n";
    $db->exec("
        CREATE TABLE IF NOT EXISTS user_permissions (
            id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            user_id INT NOT NULL,
            permission_id INT UNSIGNED NOT NULL,
            granted BOOLEAN DEFAULT TRUE COMMENT 'TRUE = grant, FALSE = revoke (override)',
            granted_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            granted_by INT COMMENT 'User ID who granted/revoked this permission',
            reason TEXT COMMENT 'Why this override was applied',
            expires_at TIMESTAMP NULL COMMENT 'Optional expiration',

            UNIQUE KEY unique_user_permission (user_id, permission_id),
            FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
            FOREIGN KEY (permission_id) REFERENCES permissions(id) ON DELETE CASCADE,

            INDEX idx_user (user_id),
            INDEX idx_permission (permission_id),
            INDEX idx_expires (expires_at)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
        COMMENT='Direct permission grants/revocations for specific users'
    ");

    // 6. Permission Audit Log (Compliance Requirement)
    echo "Creating permission_audit_log table...\n";
    $db->exec("
        CREATE TABLE IF NOT EXISTS permission_audit_log (
            id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            event_type ENUM('role_assigned', 'role_revoked', 'permission_granted', 'permission_revoked', 'permission_checked', 'access_denied') NOT NULL,
            user_id INT NOT NULL COMMENT 'User affected by this event',
            actor_id INT COMMENT 'User who performed this action',
            role_id INT UNSIGNED NULL COMMENT 'Role involved (if applicable)',
            permission_id INT UNSIGNED NULL COMMENT 'Permission involved (if applicable)',
            permission_name VARCHAR(100) COMMENT 'Permission name at time of check',
            resource_type VARCHAR(50) COMMENT 'Type of resource accessed',
            resource_id INT UNSIGNED COMMENT 'ID of resource accessed',
            result ENUM('granted', 'denied') COMMENT 'Result of permission check',
            ip_address VARCHAR(45) COMMENT 'IP address of requester',
            user_agent TEXT COMMENT 'Browser/client user agent',
            metadata JSON COMMENT 'Additional context data',
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,

            FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,

            INDEX idx_user (user_id),
            INDEX idx_actor (actor_id),
            INDEX idx_event_type (event_type),
            INDEX idx_created_at (created_at),
            INDEX idx_permission_name (permission_name)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
        COMMENT='Complete audit trail of all permission events (SOC 2, ISO 27001 requirement)'
    ");

    // 7. Add tenant_id to all RBAC tables for multi-tenant isolation
    echo "Adding tenant isolation columns...\n";

    $tables = ['permissions', 'roles', 'role_permissions', 'user_roles', 'user_permissions'];
    foreach ($tables as $table) {
        try {
            $db->exec("
                ALTER TABLE {$table}
                ADD COLUMN tenant_id INT UNSIGNED NULL COMMENT 'NULL = global, otherwise tenant-specific',
                ADD INDEX idx_tenant (tenant_id)
            ");
            echo "  Added tenant_id to {$table}\n";
        } catch (Exception $e) {
            if (!str_contains($e->getMessage(), 'Duplicate column')) {
                throw $e;
            }
            echo "  tenant_id already exists in {$table}\n";
        }
    }

    // 8. Create materialized view for fast permission lookups (performance optimization)
    echo "Creating user_effective_permissions view...\n";
    $db->exec("
        CREATE OR REPLACE VIEW user_effective_permissions AS
        SELECT DISTINCT
            u.id as user_id,
            p.id as permission_id,
            p.name as permission_name,
            p.category,
            CASE
                WHEN up.granted = FALSE THEN FALSE  -- Direct revocation
                WHEN up.granted = TRUE THEN TRUE    -- Direct grant
                WHEN rp.permission_id IS NOT NULL THEN TRUE  -- Role-based grant
                ELSE FALSE
            END as has_permission,
            CASE
                WHEN up.id IS NOT NULL THEN 'direct'
                WHEN rp.id IS NOT NULL THEN 'role'
                ELSE 'none'
            END as grant_source
        FROM users u
        LEFT JOIN user_roles ur ON u.id = ur.user_id
            AND (ur.expires_at IS NULL OR ur.expires_at > NOW())
        LEFT JOIN role_permissions rp ON ur.role_id = rp.role_id
        LEFT JOIN permissions p ON rp.permission_id = p.id OR p.id IN (
            SELECT permission_id FROM user_permissions WHERE user_id = u.id
        )
        LEFT JOIN user_permissions up ON u.id = up.user_id AND p.id = up.permission_id
            AND (up.expires_at IS NULL OR up.expires_at > NOW())
        WHERE p.id IS NOT NULL
    ");

    // 9. Add permission-related columns to users table
    echo "Updating users table...\n";
    try {
        $db->exec("
            ALTER TABLE users
            ADD COLUMN is_admin BOOLEAN DEFAULT FALSE COMMENT 'Legacy admin flag (deprecated, use roles)',
            ADD COLUMN max_permission_level INT UNSIGNED DEFAULT 0 COMMENT 'Maximum permission level this user can grant',
            ADD COLUMN permissions_last_updated TIMESTAMP NULL COMMENT 'Cache invalidation timestamp',
            ADD INDEX idx_is_admin (is_admin)
        ");
        echo "  Added permission columns to users table\n";
    } catch (Exception $e) {
        if (!str_contains($e->getMessage(), 'Duplicate column')) {
            throw $e;
        }
        echo "  Permission columns already exist in users table\n";
    }

    echo "\n✅ RBAC Permissions System created successfully!\n\n";

    echo "Next steps:\n";
    echo "1. Run the seeder: php scripts/seeders/seed_permissions.php\n";
    echo "2. Assign roles to existing admins\n";
    echo "3. Update controllers to use PermissionService\n";
    echo "4. Enable permission checking in admin routes\n";

} catch (Exception $e) {
    echo "\n❌ Migration failed: " . $e->getMessage() . "\n";
    echo "Stack trace:\n" . $e->getTraceAsString() . "\n";
    exit(1);
}

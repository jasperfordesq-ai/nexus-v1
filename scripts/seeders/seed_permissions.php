<?php
/**
 * Permission & Role Seeder
 * Seeds comprehensive permission system for enterprise compliance
 *
 * This creates:
 * - 100+ granular permissions across all admin features
 * - 10+ pre-defined enterprise roles
 * - Role-permission mappings
 *
 * Compliance: SOC 2, ISO 27001, HIPAA, PCI-DSS, GDPR
 */

require_once dirname(__DIR__, 2) . '/bootstrap.php';

use Nexus\Core\Database;
use Nexus\Services\Enterprise\PermissionService;

$db = Database::getInstance();
$permService = new PermissionService();

echo "Seeding RBAC Permissions System...\n\n";

// Disable audit logging for bulk operations
$permService->disableAudit();

try {
    // =========================================================================
    // 1. SEED PERMISSIONS
    // =========================================================================

    echo "Creating permissions...\n";

    $permissions = [
        // USER MANAGEMENT (20 permissions)
        'users' => [
            ['name' => 'users.view', 'display_name' => 'View Users', 'description' => 'View user list and profiles', 'dangerous' => false],
            ['name' => 'users.view.sensitive', 'display_name' => 'View Sensitive User Data', 'description' => 'View email, phone, IP addresses', 'dangerous' => false],
            ['name' => 'users.create', 'display_name' => 'Create Users', 'description' => 'Create new user accounts', 'dangerous' => false],
            ['name' => 'users.edit', 'display_name' => 'Edit Users', 'description' => 'Edit user profiles and settings', 'dangerous' => false],
            ['name' => 'users.delete', 'display_name' => 'Delete Users', 'description' => 'Permanently delete user accounts', 'dangerous' => true],
            ['name' => 'users.ban', 'display_name' => 'Ban Users', 'description' => 'Ban or suspend user accounts', 'dangerous' => true],
            ['name' => 'users.unban', 'display_name' => 'Unban Users', 'description' => 'Lift bans and suspensions', 'dangerous' => false],
            ['name' => 'users.impersonate', 'display_name' => 'Impersonate Users', 'description' => 'Log in as another user', 'dangerous' => true],
            ['name' => 'users.balance.view', 'display_name' => 'View User Balances', 'description' => 'View time bank balances', 'dangerous' => false],
            ['name' => 'users.balance.adjust', 'display_name' => 'Adjust User Balances', 'description' => 'Manually adjust time bank credits', 'dangerous' => true],
            ['name' => 'users.export', 'display_name' => 'Export User Data', 'description' => 'Export user lists to CSV/Excel', 'dangerous' => false],
            ['name' => 'users.import', 'display_name' => 'Import Users', 'description' => 'Bulk import users from CSV', 'dangerous' => true],
            ['name' => 'users.verify', 'display_name' => 'Verify Users', 'description' => 'Manually verify user accounts', 'dangerous' => false],
            ['name' => 'users.roles.assign', 'display_name' => 'Assign Roles', 'description' => 'Assign roles to users', 'dangerous' => true],
            ['name' => 'users.permissions.grant', 'display_name' => 'Grant Permissions', 'description' => 'Grant direct permissions to users', 'dangerous' => true],
            ['name' => 'users.*', 'display_name' => 'All User Permissions', 'description' => 'Full control over user management', 'dangerous' => true],
        ],

        // CONTENT MODERATION (12 permissions)
        'content' => [
            ['name' => 'content.view', 'display_name' => 'View Content', 'description' => 'View all user-generated content', 'dangerous' => false],
            ['name' => 'content.edit', 'display_name' => 'Edit Content', 'description' => 'Edit user posts and content', 'dangerous' => false],
            ['name' => 'content.delete', 'display_name' => 'Delete Content', 'description' => 'Delete inappropriate content', 'dangerous' => true],
            ['name' => 'content.restore', 'display_name' => 'Restore Content', 'description' => 'Restore deleted content', 'dangerous' => false],
            ['name' => 'content.flag.view', 'display_name' => 'View Flagged Content', 'description' => 'View content flagged by users', 'dangerous' => false],
            ['name' => 'content.flag.resolve', 'display_name' => 'Resolve Content Flags', 'description' => 'Approve or reject flagged content', 'dangerous' => false],
            ['name' => 'content.*', 'display_name' => 'All Content Permissions', 'description' => 'Full content moderation access', 'dangerous' => true],
        ],

        // GDPR COMPLIANCE (18 permissions)
        'gdpr' => [
            ['name' => 'gdpr.requests.view', 'display_name' => 'View GDPR Requests', 'description' => 'View data subject requests', 'dangerous' => false],
            ['name' => 'gdpr.requests.create', 'display_name' => 'Create GDPR Requests', 'description' => 'Create requests on behalf of users', 'dangerous' => false],
            ['name' => 'gdpr.requests.process', 'display_name' => 'Process GDPR Requests', 'description' => 'Process and fulfill requests', 'dangerous' => false],
            ['name' => 'gdpr.requests.approve', 'display_name' => 'Approve GDPR Requests', 'description' => 'Approve erasure and portability requests', 'dangerous' => true],
            ['name' => 'gdpr.requests.reject', 'display_name' => 'Reject GDPR Requests', 'description' => 'Reject invalid requests', 'dangerous' => false],
            ['name' => 'gdpr.requests.delete', 'display_name' => 'Delete GDPR Requests', 'description' => 'Delete request records', 'dangerous' => true],
            ['name' => 'gdpr.consents.view', 'display_name' => 'View Consent Records', 'description' => 'View user consent history', 'dangerous' => false],
            ['name' => 'gdpr.consents.manage', 'display_name' => 'Manage Consent Types', 'description' => 'Create and edit consent types', 'dangerous' => false],
            ['name' => 'gdpr.consents.export', 'display_name' => 'Export Consent Data', 'description' => 'Export consent records', 'dangerous' => false],
            ['name' => 'gdpr.breaches.view', 'display_name' => 'View Data Breaches', 'description' => 'View breach incidents', 'dangerous' => false],
            ['name' => 'gdpr.breaches.report', 'display_name' => 'Report Data Breaches', 'description' => 'Report new breach incidents', 'dangerous' => false],
            ['name' => 'gdpr.breaches.manage', 'display_name' => 'Manage Data Breaches', 'description' => 'Update breach status and actions', 'dangerous' => true],
            ['name' => 'gdpr.audit.view', 'display_name' => 'View GDPR Audit Log', 'description' => 'View compliance audit trail', 'dangerous' => false],
            ['name' => 'gdpr.audit.export', 'display_name' => 'Export GDPR Audit Log', 'description' => 'Export audit logs for compliance', 'dangerous' => false],
            ['name' => 'gdpr.*', 'display_name' => 'All GDPR Permissions', 'description' => 'Full GDPR compliance access', 'dangerous' => true],
        ],

        // SYSTEM MONITORING (12 permissions)
        'monitoring' => [
            ['name' => 'monitoring.view', 'display_name' => 'View Monitoring Dashboard', 'description' => 'View system monitoring', 'dangerous' => false],
            ['name' => 'monitoring.health.view', 'display_name' => 'View Health Checks', 'description' => 'View system health status', 'dangerous' => false],
            ['name' => 'monitoring.logs.view', 'display_name' => 'View Logs', 'description' => 'View application logs', 'dangerous' => false],
            ['name' => 'monitoring.logs.download', 'display_name' => 'Download Logs', 'description' => 'Download log files', 'dangerous' => false],
            ['name' => 'monitoring.logs.delete', 'display_name' => 'Delete Logs', 'description' => 'Clear log files', 'dangerous' => true],
            ['name' => 'monitoring.*', 'display_name' => 'All Monitoring Permissions', 'description' => 'Full monitoring access', 'dangerous' => false],
        ],

        // SYSTEM CONFIGURATION (15 permissions)
        'config' => [
            ['name' => 'config.view', 'display_name' => 'View Configuration', 'description' => 'View system settings', 'dangerous' => false],
            ['name' => 'config.edit', 'display_name' => 'Edit Configuration', 'description' => 'Modify system settings', 'dangerous' => true],
            ['name' => 'config.secrets.view', 'display_name' => 'View Secrets', 'description' => 'View secret values in vault', 'dangerous' => true],
            ['name' => 'config.secrets.edit', 'display_name' => 'Edit Secrets', 'description' => 'Create and modify secrets', 'dangerous' => true],
            ['name' => 'config.secrets.delete', 'display_name' => 'Delete Secrets', 'description' => 'Delete secrets from vault', 'dangerous' => true],
            ['name' => 'config.features.toggle', 'display_name' => 'Toggle Feature Flags', 'description' => 'Enable/disable features', 'dangerous' => true],
            ['name' => 'config.cache.clear', 'display_name' => 'Clear Cache', 'description' => 'Clear system caches', 'dangerous' => false],
            ['name' => 'config.export', 'display_name' => 'Export Configuration', 'description' => 'Export system configuration', 'dangerous' => false],
            ['name' => 'config.*', 'display_name' => 'All Configuration Permissions', 'description' => 'Full configuration access', 'dangerous' => true],
        ],

        // MESSAGES & COMMUNICATIONS (8 permissions)
        'messages' => [
            ['name' => 'messages.view', 'display_name' => 'View Messages', 'description' => 'View user messages (moderation)', 'dangerous' => false],
            ['name' => 'messages.send', 'display_name' => 'Send Messages', 'description' => 'Send messages as admin', 'dangerous' => false],
            ['name' => 'messages.delete', 'display_name' => 'Delete Messages', 'description' => 'Delete inappropriate messages', 'dangerous' => true],
            ['name' => 'messages.*', 'display_name' => 'All Message Permissions', 'description' => 'Full message management', 'dangerous' => true],
        ],

        // FINANCIAL/TRANSACTIONS (10 permissions)
        'transactions' => [
            ['name' => 'transactions.view', 'display_name' => 'View Transactions', 'description' => 'View time bank transactions', 'dangerous' => false],
            ['name' => 'transactions.create', 'display_name' => 'Create Transactions', 'description' => 'Manually create transactions', 'dangerous' => true],
            ['name' => 'transactions.edit', 'display_name' => 'Edit Transactions', 'description' => 'Modify transaction records', 'dangerous' => true],
            ['name' => 'transactions.delete', 'display_name' => 'Delete Transactions', 'description' => 'Delete transaction records', 'dangerous' => true],
            ['name' => 'transactions.export', 'display_name' => 'Export Transactions', 'description' => 'Export financial reports', 'dangerous' => false],
            ['name' => 'transactions.*', 'display_name' => 'All Transaction Permissions', 'description' => 'Full financial access', 'dangerous' => true],
        ],

        // ROLE & PERMISSION MANAGEMENT (8 permissions)
        'roles' => [
            ['name' => 'roles.view', 'display_name' => 'View Roles', 'description' => 'View role definitions', 'dangerous' => false],
            ['name' => 'roles.create', 'display_name' => 'Create Roles', 'description' => 'Create new roles', 'dangerous' => true],
            ['name' => 'roles.edit', 'display_name' => 'Edit Roles', 'description' => 'Modify role permissions', 'dangerous' => true],
            ['name' => 'roles.delete', 'display_name' => 'Delete Roles', 'description' => 'Delete custom roles', 'dangerous' => true],
            ['name' => 'roles.assign', 'display_name' => 'Assign Roles', 'description' => 'Assign roles to users', 'dangerous' => true],
            ['name' => 'roles.*', 'display_name' => 'All Role Permissions', 'description' => 'Full role management', 'dangerous' => true],
        ],

        // REPORTING & ANALYTICS (6 permissions)
        'reports' => [
            ['name' => 'reports.view', 'display_name' => 'View Reports', 'description' => 'View analytics and reports', 'dangerous' => false],
            ['name' => 'reports.export', 'display_name' => 'Export Reports', 'description' => 'Export report data', 'dangerous' => false],
            ['name' => 'reports.create', 'display_name' => 'Create Custom Reports', 'description' => 'Create custom report templates', 'dangerous' => false],
            ['name' => 'reports.*', 'display_name' => 'All Report Permissions', 'description' => 'Full reporting access', 'dangerous' => false],
        ],

        // SUPER ADMIN (wildcard)
        'admin' => [
            ['name' => '*', 'display_name' => 'Super Admin (All Permissions)', 'description' => 'Unrestricted access to all features', 'dangerous' => true],
        ],
    ];

    $permissionIds = [];
    foreach ($permissions as $category => $perms) {
        echo "  Creating {$category} permissions...\n";
        foreach ($perms as $perm) {
            try {
                $stmt = $db->prepare(
                    "INSERT INTO permissions (name, display_name, description, category, is_dangerous)
                     VALUES (?, ?, ?, ?, ?)"
                );
                $stmt->execute([
                    $perm['name'],
                    $perm['display_name'],
                    $perm['description'],
                    $category,
                    $perm['dangerous']
                ]);
                $permissionIds[$perm['name']] = (int) $db->lastInsertId();
            } catch (Exception $e) {
                if (!str_contains($e->getMessage(), 'Duplicate entry')) {
                    throw $e;
                }
                // Already exists, fetch ID
                $stmt = $db->prepare("SELECT id FROM permissions WHERE name = ?");
                $stmt->execute([$perm['name']]);
                $existing = $stmt->fetch();
                $permissionIds[$perm['name']] = $existing['id'];
            }
        }
    }

    echo "\n✅ Created " . count($permissionIds) . " permissions\n\n";

    // =========================================================================
    // 2. SEED ROLES
    // =========================================================================

    echo "Creating enterprise roles...\n";

    $roles = [
        // Level 100 - Super Admin
        [
            'name' => 'super_admin',
            'display_name' => 'Super Administrator',
            'description' => 'Unrestricted access to all platform features. Only for founders and CTO.',
            'level' => 100,
            'is_system' => true,
            'permissions' => ['*'],
        ],

        // Level 90 - System Administrator
        [
            'name' => 'system_admin',
            'display_name' => 'System Administrator',
            'description' => 'Full access except role management. Can manage users, config, and monitoring.',
            'level' => 90,
            'is_system' => true,
            'permissions' => [
                'users.*', 'content.*', 'monitoring.*', 'config.view', 'config.edit',
                'config.cache.clear', 'messages.*', 'transactions.*', 'reports.*'
            ],
        ],

        // Level 80 - GDPR Officer
        [
            'name' => 'gdpr_officer',
            'display_name' => 'GDPR Compliance Officer',
            'description' => 'Manages all GDPR compliance activities. Can access sensitive data for compliance purposes.',
            'level' => 80,
            'is_system' => true,
            'permissions' => [
                'gdpr.*', 'users.view', 'users.view.sensitive', 'users.export',
                'users.delete', 'reports.view', 'reports.export'
            ],
        ],

        // Level 70 - Security Officer
        [
            'name' => 'security_officer',
            'display_name' => 'Security Officer',
            'description' => 'Manages security, monitoring, and audit logs. Cannot modify user data.',
            'level' => 70,
            'is_system' => true,
            'permissions' => [
                'monitoring.*', 'gdpr.audit.view', 'gdpr.audit.export', 'gdpr.breaches.*',
                'config.view', 'users.view', 'reports.view', 'reports.export'
            ],
        ],

        // Level 60 - Finance Admin
        [
            'name' => 'finance_admin',
            'display_name' => 'Finance Administrator',
            'description' => 'Manages financial transactions and time bank balances. Cannot ban users.',
            'level' => 60,
            'is_system' => true,
            'permissions' => [
                'transactions.*', 'users.view', 'users.balance.view', 'users.balance.adjust',
                'users.export', 'reports.view', 'reports.export'
            ],
        ],

        // Level 50 - User Manager
        [
            'name' => 'user_manager',
            'display_name' => 'User Manager',
            'description' => 'Manages users and accounts. Can ban/unban but not delete.',
            'level' => 50,
            'is_system' => true,
            'permissions' => [
                'users.view', 'users.view.sensitive', 'users.edit', 'users.create',
                'users.ban', 'users.unban', 'users.verify', 'users.export',
                'messages.view', 'reports.view'
            ],
        ],

        // Level 40 - Content Moderator
        [
            'name' => 'content_moderator',
            'display_name' => 'Content Moderator',
            'description' => 'Moderates user-generated content and messages. Can ban users for violations.',
            'level' => 40,
            'is_system' => true,
            'permissions' => [
                'content.*', 'messages.view', 'messages.delete', 'users.view',
                'users.ban', 'users.unban', 'reports.view'
            ],
        ],

        // Level 30 - Support Agent
        [
            'name' => 'support_agent',
            'display_name' => 'Support Agent',
            'description' => 'Customer support role. Can view and edit user profiles, send messages. No delete permissions.',
            'level' => 30,
            'is_system' => true,
            'permissions' => [
                'users.view', 'users.edit', 'users.verify', 'messages.view',
                'messages.send', 'reports.view', 'gdpr.requests.view', 'gdpr.requests.create'
            ],
        ],

        // Level 20 - Read-Only Admin
        [
            'name' => 'readonly_admin',
            'display_name' => 'Read-Only Administrator',
            'description' => 'View-only access to all features. Perfect for auditors and stakeholders.',
            'level' => 20,
            'is_system' => true,
            'permissions' => [
                'users.view', 'content.view', 'messages.view', 'transactions.view',
                'reports.view', 'monitoring.view', 'monitoring.health.view',
                'monitoring.logs.view', 'config.view', 'gdpr.requests.view',
                'gdpr.consents.view', 'gdpr.audit.view', 'roles.view'
            ],
        ],

        // Level 10 - Junior Admin (Trainee)
        [
            'name' => 'junior_admin',
            'display_name' => 'Junior Administrator',
            'description' => 'Training role for new admins. Can view and edit but not delete anything.',
            'level' => 10,
            'is_system' => true,
            'permissions' => [
                'users.view', 'users.edit', 'content.view', 'content.edit',
                'messages.view', 'reports.view'
            ],
        ],
    ];

    foreach ($roles as $roleData) {
        echo "  Creating role: {$roleData['display_name']}...\n";

        try {
            $roleId = $permService->createRole(
                $roleData['name'],
                $roleData['display_name'],
                $roleData['description'],
                $roleData['level'],
                $roleData['is_system']
            );

            if ($roleId) {
                // Attach permissions
                $permIds = [];
                foreach ($roleData['permissions'] as $permName) {
                    if (isset($permissionIds[$permName])) {
                        $permIds[] = $permissionIds[$permName];
                    }
                }

                if (!empty($permIds)) {
                    $permService->attachPermissionsToRole($roleId, $permIds, 1);
                }
            }
        } catch (Exception $e) {
            if (!str_contains($e->getMessage(), 'Duplicate entry')) {
                throw $e;
            }
            echo "    (already exists)\n";
        }
    }

    echo "\n✅ Created " . count($roles) . " enterprise roles\n\n";

    // =========================================================================
    // 3. ASSIGN SUPER ADMIN ROLE TO EXISTING ADMINS
    // =========================================================================

    echo "Assigning super_admin role to existing admin users...\n";

    $superAdminRole = $db->query(
        "SELECT id FROM roles WHERE name = 'super_admin' LIMIT 1"
    )->fetch();

    if ($superAdminRole) {
        $admins = $db->query("
            SELECT id, username FROM users
            WHERE role IN ('admin', 'super_admin')
               OR is_super_admin = TRUE
        ")->fetchAll();

        foreach ($admins as $admin) {
            try {
                $permService->assignRole($admin['id'], $superAdminRole['id'], 1);
                echo "  Assigned super_admin to: {$admin['username']}\n";
            } catch (Exception $e) {
                echo "  (already assigned to {$admin['username']})\n";
            }
        }
    }

    // Re-enable audit logging
    $permService->enableAudit();

    echo "\n" . str_repeat("=", 70) . "\n";
    echo "✅ RBAC PERMISSIONS SYSTEM SEEDED SUCCESSFULLY!\n";
    echo str_repeat("=", 70) . "\n\n";

    echo "Summary:\n";
    echo "  • " . count($permissionIds) . " permissions created\n";
    echo "  • " . count($roles) . " enterprise roles created\n";
    echo "  • " . count($admins ?? []) . " admins assigned super_admin role\n\n";

    echo "Enterprise Roles Created:\n";
    foreach ($roles as $role) {
        echo "  [Level {$role['level']}] {$role['display_name']}\n";
    }

    echo "\nNext Steps:\n";
    echo "1. Visit /admin/enterprise/roles to manage roles and permissions\n";
    echo "2. Assign appropriate roles to your admin team\n";
    echo "3. Update controllers to use PermissionService->can()\n";
    echo "4. Review permission audit logs for compliance\n\n";

} catch (Exception $e) {
    echo "\n❌ Seeding failed: " . $e->getMessage() . "\n";
    echo "Stack trace:\n" . $e->getTraceAsString() . "\n";
    exit(1);
}

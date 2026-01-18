<?php

namespace Nexus\Services;

use Nexus\Core\Database;
use Nexus\Middleware\SuperPanelAccess;

/**
 * TenantVisibilityService
 *
 * Provides scoped queries based on user's position in tenant hierarchy.
 * Used in Super Admin Panel to ensure users only see their subtree.
 *
 * VISIBILITY RULES:
 * - Master (id=1): Sees ALL tenants globally
 * - Regional (allows_subtenants=1): Sees own tenant + all descendants
 * - Standard: No Super Panel access (blocked by middleware)
 */
class TenantVisibilityService
{
    /**
     * Get all visible tenant IDs as array (for IN clauses)
     */
    public static function getVisibleTenantIds(): array
    {
        $access = SuperPanelAccess::getAccess();

        if (!$access['granted']) {
            return [];
        }

        if ($access['level'] === 'master') {
            return Database::query("SELECT id FROM tenants ORDER BY id")
                ->fetchAll(\PDO::FETCH_COLUMN);
        }

        return Database::query(
            "SELECT id FROM tenants WHERE path LIKE ? ORDER BY path",
            [$access['tenant_path'] . '%']
        )->fetchAll(\PDO::FETCH_COLUMN);
    }

    /**
     * Get tenant list for Super Admin Panel
     */
    public static function getTenantList(array $filters = []): array
    {
        $scope = SuperPanelAccess::getScopeClause('t');
        $access = SuperPanelAccess::getAccess();

        if (!$access['granted']) {
            return [];
        }

        $sql = "
            SELECT
                t.id,
                t.name,
                t.slug,
                t.domain,
                t.path,
                t.depth,
                t.parent_id,
                t.allows_subtenants,
                t.max_depth,
                t.is_active,
                t.tagline,
                t.created_at,
                parent.name as parent_name,

                -- Hierarchy display helpers
                CONCAT(REPEAT('    ', t.depth), t.name) as indented_name,

                -- Stats (subqueries)
                (SELECT COUNT(*) FROM tenants WHERE parent_id = t.id) as direct_children,
                (SELECT COUNT(*) FROM tenants sub WHERE sub.path LIKE CONCAT(t.path, '%') AND sub.id != t.id) as total_descendants,
                (SELECT COUNT(*) FROM users WHERE tenant_id = t.id) as user_count,
                (SELECT COUNT(*) FROM listings WHERE tenant_id = t.id AND status = 'active') as listing_count,

                -- Relationship to current user
                CASE
                    WHEN t.id = ? THEN 'self'
                    WHEN t.path LIKE CONCAT(?, '%') AND t.id != ? THEN 'descendant'
                    ELSE 'ancestor_or_peer'
                END as relationship,

                -- Can current user manage this tenant?
                CASE
                    WHEN t.id = ? THEN 0
                    WHEN t.id = 1 THEN 0
                    ELSE 1
                END as can_manage

            FROM tenants t
            LEFT JOIN tenants parent ON t.parent_id = parent.id
            WHERE {$scope['sql']}
        ";

        $params = array_merge(
            [
                $access['tenant_id'],
                $access['tenant_path'],
                $access['tenant_id'],
                $access['tenant_id']
            ],
            $scope['params']
        );

        // Apply optional filters
        if (isset($filters['is_active'])) {
            $sql .= " AND t.is_active = ?";
            $params[] = $filters['is_active'];
        }

        if (isset($filters['allows_subtenants'])) {
            $sql .= " AND t.allows_subtenants = ?";
            $params[] = $filters['allows_subtenants'];
        }

        if (!empty($filters['parent_id'])) {
            $sql .= " AND t.parent_id = ?";
            $params[] = $filters['parent_id'];
        }

        if (!empty($filters['search'])) {
            $sql .= " AND (t.name LIKE ? OR t.slug LIKE ? OR t.domain LIKE ?)";
            $searchTerm = '%' . $filters['search'] . '%';
            $params[] = $searchTerm;
            $params[] = $searchTerm;
            $params[] = $searchTerm;
        }

        $sql .= " ORDER BY t.path, t.name";

        return Database::query($sql, $params)->fetchAll(\PDO::FETCH_ASSOC);
    }

    /**
     * Get a single tenant by ID (with access check)
     */
    public static function getTenant(int $tenantId): ?array
    {
        if (!SuperPanelAccess::canAccessTenant($tenantId)) {
            return null;
        }

        $tenant = Database::query("
            SELECT
                t.*,
                parent.name as parent_name,
                (SELECT COUNT(*) FROM tenants WHERE parent_id = t.id) as direct_children,
                (SELECT COUNT(*) FROM users WHERE tenant_id = t.id) as user_count
            FROM tenants t
            LEFT JOIN tenants parent ON t.parent_id = parent.id
            WHERE t.id = ?
        ", [$tenantId])->fetch(\PDO::FETCH_ASSOC);

        return $tenant ?: null;
    }

    /**
     * Get users list scoped to visible tenants
     */
    public static function getUserList(array $filters = []): array
    {
        $scope = SuperPanelAccess::getScopeClause('t');
        $access = SuperPanelAccess::getAccess();

        if (!$access['granted']) {
            return [];
        }

        $sql = "
            SELECT
                u.id,
                u.first_name,
                u.last_name,
                u.email,
                u.role,
                u.is_super_admin,
                u.is_tenant_super_admin,
                u.is_approved,
                u.status,
                u.tenant_id,
                u.created_at,
                u.last_login_at,
                t.name as tenant_name,
                t.path as tenant_path
            FROM users u
            JOIN tenants t ON u.tenant_id = t.id
            WHERE {$scope['sql']}
        ";

        $params = $scope['params'];

        if (!empty($filters['tenant_id'])) {
            // Verify access to this tenant first
            if (!SuperPanelAccess::canAccessTenant($filters['tenant_id'])) {
                return [];
            }
            $sql .= " AND u.tenant_id = ?";
            $params[] = $filters['tenant_id'];
        }

        if (!empty($filters['role'])) {
            $sql .= " AND u.role = ?";
            $params[] = $filters['role'];
        }

        if (isset($filters['is_tenant_super_admin'])) {
            $sql .= " AND u.is_tenant_super_admin = ?";
            $params[] = $filters['is_tenant_super_admin'];
        }

        if (!empty($filters['search'])) {
            $sql .= " AND (u.first_name LIKE ? OR u.last_name LIKE ? OR u.email LIKE ?)";
            $searchTerm = '%' . $filters['search'] . '%';
            $params[] = $searchTerm;
            $params[] = $searchTerm;
            $params[] = $searchTerm;
        }

        $sql .= " ORDER BY t.path, u.last_name, u.first_name";

        // Apply limit
        $limit = $filters['limit'] ?? 100;
        $sql .= " LIMIT " . (int)$limit;

        return Database::query($sql, $params)->fetchAll(\PDO::FETCH_ASSOC);
    }

    /**
     * Get tenant admins (users who can manage a specific tenant)
     */
    public static function getTenantAdmins(int $tenantId): array
    {
        if (!SuperPanelAccess::canAccessTenant($tenantId)) {
            return [];
        }

        return Database::query("
            SELECT
                u.id,
                u.first_name,
                u.last_name,
                u.email,
                u.role,
                u.is_tenant_super_admin,
                u.last_login_at
            FROM users u
            WHERE u.tenant_id = ?
            AND (u.role IN ('admin', 'tenant_admin', 'super_admin') OR u.is_tenant_super_admin = 1)
            ORDER BY u.is_tenant_super_admin DESC, u.role, u.last_name
        ", [$tenantId])->fetchAll(\PDO::FETCH_ASSOC);
    }

    /**
     * Get hierarchy tree for display
     */
    public static function getHierarchyTree(): array
    {
        $tenants = self::getTenantList();
        return self::buildTree($tenants, null);
    }

    /**
     * Build nested tree from flat tenant list
     */
    private static function buildTree(array $tenants, ?int $parentId): array
    {
        $branch = [];

        foreach ($tenants as $tenant) {
            $tenantParentId = $tenant['parent_id'] ? (int)$tenant['parent_id'] : null;
            if ($tenantParentId === $parentId) {
                $tenant['children'] = self::buildTree($tenants, (int)$tenant['id']);
                $branch[] = $tenant;
            }
        }

        return $branch;
    }

    /**
     * Get tenant statistics summary for dashboard
     */
    public static function getDashboardStats(): array
    {
        $access = SuperPanelAccess::getAccess();

        if (!$access['granted']) {
            return [];
        }

        $scope = SuperPanelAccess::getScopeClause('t');

        // Total tenants
        $tenantCount = Database::query("
            SELECT COUNT(*) FROM tenants t WHERE {$scope['sql']}
        ", $scope['params'])->fetchColumn();

        // Active tenants
        $activeTenants = Database::query("
            SELECT COUNT(*) FROM tenants t WHERE {$scope['sql']} AND t.is_active = 1
        ", $scope['params'])->fetchColumn();

        // Total users across visible tenants
        $totalUsers = Database::query("
            SELECT COUNT(*) FROM users u
            JOIN tenants t ON u.tenant_id = t.id
            WHERE {$scope['sql']}
        ", $scope['params'])->fetchColumn();

        // Super admins across visible tenants
        $superAdmins = Database::query("
            SELECT COUNT(*) FROM users u
            JOIN tenants t ON u.tenant_id = t.id
            WHERE {$scope['sql']} AND u.is_tenant_super_admin = 1
        ", $scope['params'])->fetchColumn();

        // Tenants with sub-tenant capability
        $hubTenants = Database::query("
            SELECT COUNT(*) FROM tenants t WHERE {$scope['sql']} AND t.allows_subtenants = 1
        ", $scope['params'])->fetchColumn();

        return [
            'total_tenants' => (int)$tenantCount,
            'active_tenants' => (int)$activeTenants,
            'inactive_tenants' => (int)$tenantCount - (int)$activeTenants,
            'total_users' => (int)$totalUsers,
            'super_admins' => (int)$superAdmins,
            'hub_tenants' => (int)$hubTenants,
            'scope' => $access['scope'],
            'level' => $access['level']
        ];
    }

    /**
     * Get potential parent tenants for creating a new sub-tenant
     */
    public static function getAvailableParents(): array
    {
        $access = SuperPanelAccess::getAccess();

        if (!$access['granted']) {
            return [];
        }

        $scope = SuperPanelAccess::getScopeClause('t');

        // Get tenants that allow sub-tenants and haven't reached max depth
        $sql = "
            SELECT
                t.id,
                t.name,
                t.path,
                t.depth,
                t.max_depth,
                CONCAT(REPEAT('-- ', t.depth), t.name) as display_name
            FROM tenants t
            WHERE {$scope['sql']}
            AND t.allows_subtenants = 1
            AND (t.max_depth = 0 OR t.depth < t.max_depth)
            ORDER BY t.path
        ";

        return Database::query($sql, $scope['params'])->fetchAll(\PDO::FETCH_ASSOC);
    }
}

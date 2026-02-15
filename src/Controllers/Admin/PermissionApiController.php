<?php

declare(strict_types=1);

namespace Nexus\Controllers\Admin;

use Nexus\Core\Database;
use Nexus\Core\TenantContext;
use Nexus\Services\Enterprise\PermissionService;

/**
 * Permission API Controller
 * REST API endpoints for permission operations
 */
class PermissionApiController
{
    private PermissionService $permService;
    private \PDO $db;

    public function __construct()
    {
        // Suppress all output before JSON response
        error_reporting(E_ERROR | E_PARSE);

        $this->requireAdmin();
        $this->permService = new PermissionService();
        $this->db = Database::getInstance();

        // All responses are JSON
        @header('Content-Type: application/json');
    }

    private function requireAdmin(): void
    {
        $isAdmin = isset($_SESSION['user_role']) && in_array($_SESSION['user_role'], ['admin', 'super_admin', 'tenant_admin']);
        $isSuperAdmin = !empty($_SESSION['is_super_admin']);

        if (!$isAdmin && !$isSuperAdmin) {
            http_response_code(401);
            echo json_encode(['error' => 'Unauthorized']);
            exit;
        }
    }

    private function getCurrentUserId(): int
    {
        return (int) ($_SESSION['user_id'] ?? 0);
    }

    private function getJsonInput(): array
    {
        $input = file_get_contents('php://input');
        return json_decode($input, true) ?? [];
    }

    /**
     * GET /admin-legacy/api/permissions/check
     * Check if current user has a permission
     * Query params: permission (required)
     */
    public function checkPermission(): void
    {
        $permission = $_GET['permission'] ?? '';

        if (empty($permission)) {
            http_response_code(400);
            echo json_encode(['error' => 'Permission parameter required']);
            return;
        }

        $userId = $this->getCurrentUserId();
        $hasPermission = $this->permService->can($userId, $permission);

        echo json_encode([
            'user_id' => $userId,
            'permission' => $permission,
            'has_permission' => $hasPermission
        ]);
    }

    /**
     * GET /admin-legacy/api/permissions
     * Get all permissions grouped by category
     */
    public function getAllPermissions(): void
    {
        if (!$this->permService->can($this->getCurrentUserId(), 'roles.view')) {
            http_response_code(403);
            echo json_encode(['error' => 'Permission denied']);
            return;
        }

        $permissions = $this->permService->getAllPermissions();

        echo json_encode([
            'permissions' => $permissions,
            'total' => array_sum(array_map('count', $permissions))
        ]);
    }

    /**
     * GET /admin-legacy/api/roles
     * Get all roles with stats
     */
    public function getAllRoles(): void
    {
        if (!$this->permService->can($this->getCurrentUserId(), 'roles.view')) {
            http_response_code(403);
            echo json_encode(['error' => 'Permission denied']);
            return;
        }

        $roles = $this->permService->getAllRoles();

        echo json_encode([
            'roles' => $roles,
            'total' => count($roles)
        ]);
    }

    /**
     * GET /admin-legacy/api/users/{userId}/permissions
     * Get all permissions for a specific user
     */
    public function getUserPermissions(int $userId): void
    {
        if (!$this->permService->can($this->getCurrentUserId(), 'users.view')) {
            http_response_code(403);
            echo json_encode(['error' => 'Permission denied']);
            return;
        }

        $permissions = $this->permService->getUserPermissions($userId);

        echo json_encode([
            'user_id' => $userId,
            'permissions' => $permissions,
            'total' => count($permissions)
        ]);
    }

    /**
     * GET /admin-legacy/api/users/{userId}/roles
     * Get all roles assigned to a user
     */
    public function getUserRoles(int $userId): void
    {
        if (!$this->permService->can($this->getCurrentUserId(), 'users.view')) {
            http_response_code(403);
            echo json_encode(['error' => 'Permission denied']);
            return;
        }

        $roles = $this->permService->getUserRoles($userId);

        echo json_encode([
            'user_id' => $userId,
            'roles' => $roles,
            'total' => count($roles)
        ]);
    }

    /**
     * POST /admin-legacy/api/users/{userId}/roles
     * Assign a role to a user
     * Body: { role_id: int, expires_at?: string }
     */
    public function assignRoleToUser(int $userId): void
    {
        try {
            $currentUserId = $this->getCurrentUserId();
            $isSuperAdmin = !empty($_SESSION['is_super_admin']);

            // Super admins bypass permission checks
            if (!$isSuperAdmin && !$this->permService->can($currentUserId, 'roles.assign')) {
                http_response_code(403);
                echo json_encode(['error' => 'Permission denied - requires roles.assign']);
                return;
            }
        } catch (\Exception $e) {
            http_response_code(503);
            echo json_encode(['error' => 'Permission check failed: ' . $e->getMessage()]);
            return;
        }

        $data = $this->getJsonInput();

        if (empty($data['role_id'])) {
            http_response_code(400);
            echo json_encode(['error' => 'role_id is required']);
            return;
        }

        try {
            $success = $this->permService->assignRole(
                $userId,
                (int) $data['role_id'],
                $this->getCurrentUserId(),
                $data['expires_at'] ?? null
            );

            if ($success) {
                echo json_encode([
                    'success' => true,
                    'message' => 'Role assigned successfully',
                    'user_id' => $userId,
                    'role_id' => $data['role_id']
                ]);
            } else {
                throw new \Exception('Failed to assign role');
            }
        } catch (\Exception $e) {
            http_response_code(500);
            echo json_encode(['error' => $e->getMessage()]);
        }
    }

    /**
     * DELETE /admin-legacy/api/users/{userId}/roles/{roleId}
     * Revoke a role from a user
     */
    public function revokeRoleFromUser(int $userId, int $roleId): void
    {
        if (!$this->permService->can($this->getCurrentUserId(), 'roles.assign')) {
            http_response_code(403);
            echo json_encode(['error' => 'Permission denied']);
            return;
        }

        try {
            $success = $this->permService->revokeRole(
                $userId,
                $roleId,
                $this->getCurrentUserId()
            );

            if ($success) {
                echo json_encode([
                    'success' => true,
                    'message' => 'Role revoked successfully',
                    'user_id' => $userId,
                    'role_id' => $roleId
                ]);
            } else {
                throw new \Exception('Failed to revoke role');
            }
        } catch (\Exception $e) {
            http_response_code(500);
            echo json_encode(['error' => $e->getMessage()]);
        }
    }

    /**
     * POST /admin-legacy/api/users/{userId}/permissions
     * Grant a direct permission to a user
     * Body: { permission_id: int, expires_at?: string }
     */
    public function grantPermissionToUser(int $userId): void
    {
        try {
            $currentUserId = $this->getCurrentUserId();
            $isSuperAdmin = !empty($_SESSION['is_super_admin']);

            // Super admins bypass permission checks
            if (!$isSuperAdmin && !$this->permService->can($currentUserId, 'users.edit_permissions')) {
                http_response_code(403);
                echo json_encode(['error' => 'Permission denied - requires users.edit_permissions']);
                return;
            }
        } catch (\Exception $e) {
            http_response_code(503);
            echo json_encode(['error' => 'Permission check failed: ' . $e->getMessage()]);
            return;
        }

        $data = $this->getJsonInput();

        if (empty($data['permission_id'])) {
            http_response_code(400);
            echo json_encode(['error' => 'permission_id is required']);
            return;
        }

        try {
            $success = $this->permService->grantPermission(
                $userId,
                (int) $data['permission_id'],
                $this->getCurrentUserId(),
                $data['reason'] ?? 'Direct grant',
                $data['expires_at'] ?? null
            );

            if ($success) {
                echo json_encode([
                    'success' => true,
                    'message' => 'Permission granted successfully',
                    'user_id' => $userId,
                    'permission_id' => $data['permission_id']
                ]);
            } else {
                throw new \Exception('Failed to grant permission');
            }
        } catch (\Exception $e) {
            http_response_code(500);
            echo json_encode(['error' => $e->getMessage()]);
        }
    }

    /**
     * DELETE /admin-legacy/api/users/{userId}/permissions/{permissionId}
     * Revoke a direct permission from a user
     */
    public function revokePermissionFromUser(int $userId, int $permissionId): void
    {
        if (!$this->permService->can($this->getCurrentUserId(), 'users.edit_permissions')) {
            http_response_code(403);
            echo json_encode(['error' => 'Permission denied']);
            return;
        }

        try {
            $success = $this->permService->revokePermission(
                $userId,
                $permissionId,
                $this->getCurrentUserId()
            );

            if ($success) {
                echo json_encode([
                    'success' => true,
                    'message' => 'Permission revoked successfully',
                    'user_id' => $userId,
                    'permission_id' => $permissionId
                ]);
            } else {
                throw new \Exception('Failed to revoke permission');
            }
        } catch (\Exception $e) {
            http_response_code(500);
            echo json_encode(['error' => $e->getMessage()]);
        }
    }

    /**
     * GET /admin-legacy/api/audit/permissions
     * Get permission audit log with filters
     * Query params: user_id, permission, event_type, from_date, to_date, limit, offset
     */
    public function getAuditLog(): void
    {
        if (!$this->permService->can($this->getCurrentUserId(), 'system.audit_logs')) {
            http_response_code(403);
            echo json_encode(['error' => 'Permission denied']);
            return;
        }

        $userId = isset($_GET['user_id']) ? (int) $_GET['user_id'] : null;
        $permission = $_GET['permission'] ?? null;
        $eventType = $_GET['event_type'] ?? null;
        $fromDate = $_GET['from_date'] ?? null;
        $toDate = $_GET['to_date'] ?? null;
        $limit = isset($_GET['limit']) ? (int) $_GET['limit'] : 50;
        $offset = isset($_GET['offset']) ? (int) $_GET['offset'] : 0;

        // Build query
        $sql = "
            SELECT
                pal.*,
                u.username,
                u.email,
                ab.username as assigned_by_username
            FROM permission_audit_log pal
            LEFT JOIN users u ON pal.user_id = u.id
            LEFT JOIN users ab ON pal.assigned_by = ab.id
            WHERE 1=1
        ";

        $params = [];

        if ($userId) {
            $sql .= " AND pal.user_id = ?";
            $params[] = $userId;
        }

        if ($permission) {
            $sql .= " AND pal.permission_name = ?";
            $params[] = $permission;
        }

        if ($eventType) {
            $sql .= " AND pal.event_type = ?";
            $params[] = $eventType;
        }

        if ($fromDate) {
            $sql .= " AND pal.created_at >= ?";
            $params[] = $fromDate;
        }

        if ($toDate) {
            $sql .= " AND pal.created_at <= ?";
            $params[] = $toDate;
        }

        // Get total count
        $countSql = "SELECT COUNT(*) as total FROM (" . $sql . ") as subquery";
        $total = $this->db->query($countSql, $params)->fetch()['total'];

        // Add pagination
        $sql .= " ORDER BY pal.created_at DESC LIMIT ? OFFSET ?";
        $params[] = $limit;
        $params[] = $offset;

        $logs = $this->db->query($sql, $params)->fetchAll();

        echo json_encode([
            'logs' => $logs,
            'total' => $total,
            'limit' => $limit,
            'offset' => $offset,
            'has_more' => ($offset + $limit) < $total
        ]);
    }

    /**
     * GET /admin-legacy/api/users/{userId}/effective-permissions
     * Get user's complete effective permissions (roles + direct grants - revocations)
     */
    public function getUserEffectivePermissions(int $userId): void
    {
        if (!$this->permService->can($this->getCurrentUserId(), 'users.view')) {
            http_response_code(403);
            echo json_encode(['error' => 'Permission denied']);
            return;
        }

        try {
            // Get all permissions via roles
            $rolePermissions = $this->db->query("
                SELECT DISTINCT p.id, p.name, p.display_name, p.category, p.is_dangerous,
                       'role' as source, r.display_name as source_name
                FROM permissions p
                JOIN role_permissions rp ON p.id = rp.permission_id
                JOIN roles r ON rp.role_id = r.id
                JOIN user_roles ur ON r.id = ur.role_id
                WHERE ur.user_id = ?
                    AND (ur.expires_at IS NULL OR ur.expires_at > NOW())
                ORDER BY p.category, p.name
            ", [$userId])->fetchAll();

            // Get direct grants
            $directGrants = $this->db->query("
                SELECT DISTINCT p.id, p.name, p.display_name, p.category, p.is_dangerous,
                       'direct_grant' as source, u.username as source_name
                FROM permissions p
                JOIN user_permissions up ON p.id = up.permission_id
                LEFT JOIN users u ON up.granted_by = u.id
                WHERE up.user_id = ?
                    AND up.is_revocation = 0
                    AND (up.expires_at IS NULL OR up.expires_at > NOW())
                ORDER BY p.category, p.name
            ", [$userId])->fetchAll();

            // Get revocations
            $revocations = $this->db->query("
                SELECT DISTINCT p.id, p.name, p.display_name, p.category,
                       u.username as revoked_by_username
                FROM permissions p
                JOIN user_permissions up ON p.id = up.permission_id
                LEFT JOIN users u ON up.granted_by = u.id
                WHERE up.user_id = ?
                    AND up.is_revocation = 1
                    AND (up.expires_at IS NULL OR up.expires_at > NOW())
            ", [$userId])->fetchAll();

            $revocationIds = array_column($revocations, 'id');

            // Merge and filter
            $allPermissions = array_merge($rolePermissions, $directGrants);

            // Remove revoked permissions
            $effectivePermissions = array_filter($allPermissions, function($perm) use ($revocationIds) {
                return !in_array($perm['id'], $revocationIds);
            });

            // Group by category
            $grouped = [];
            foreach ($effectivePermissions as $perm) {
                $category = $perm['category'];
                if (!isset($grouped[$category])) {
                    $grouped[$category] = [];
                }
                $grouped[$category][] = $perm;
            }

            echo json_encode([
                'user_id' => $userId,
                'permissions' => $grouped,
                'total' => count($effectivePermissions),
                'revocations' => $revocations
            ]);

        } catch (\Exception $e) {
            http_response_code(500);
            echo json_encode(['error' => $e->getMessage()]);
        }
    }

    /**
     * GET /admin-legacy/api/roles/{roleId}/permissions
     * Get all permissions for a specific role
     */
    public function getRolePermissions(int $roleId): void
    {
        if (!$this->permService->can($this->getCurrentUserId(), 'roles.view')) {
            http_response_code(403);
            echo json_encode(['error' => 'Permission denied']);
            return;
        }

        $permissions = $this->db->query("
            SELECT p.*
            FROM permissions p
            JOIN role_permissions rp ON p.id = rp.permission_id
            WHERE rp.role_id = ?
            ORDER BY p.category, p.name
        ", [$roleId])->fetchAll();

        echo json_encode([
            'role_id' => $roleId,
            'permissions' => $permissions,
            'total' => count($permissions)
        ]);
    }

    /**
     * GET /admin-legacy/api/stats/permissions
     * Get permission system statistics
     */
    public function getPermissionStats(): void
    {
        if (!$this->permService->can($this->getCurrentUserId(), 'system.dashboard')) {
            http_response_code(403);
            echo json_encode(['error' => 'Permission denied']);
            return;
        }

        try {
            $stats = [
                'total_permissions' => $this->db->query("SELECT COUNT(*) as count FROM permissions")->fetch()['count'],
                'total_roles' => $this->db->query("SELECT COUNT(*) as count FROM roles")->fetch()['count'],
                'users_with_roles' => $this->db->query("SELECT COUNT(DISTINCT user_id) as count FROM user_roles WHERE expires_at IS NULL OR expires_at > NOW()")->fetch()['count'],
                'dangerous_permissions' => $this->db->query("SELECT COUNT(*) as count FROM permissions WHERE is_dangerous = 1")->fetch()['count'],
                'audit_log_entries_today' => $this->db->query("SELECT COUNT(*) as count FROM permission_audit_log WHERE DATE(created_at) = CURDATE()")->fetch()['count'],
                'active_temporary_grants' => $this->db->query("SELECT COUNT(*) as count FROM user_roles WHERE expires_at > NOW()")->fetch()['count'] +
                                            $this->db->query("SELECT COUNT(*) as count FROM user_permissions WHERE expires_at > NOW()")->fetch()['count']
            ];

            echo json_encode(['stats' => $stats]);

        } catch (\Exception $e) {
            http_response_code(500);
            echo json_encode(['error' => $e->getMessage()]);
        }
    }
}

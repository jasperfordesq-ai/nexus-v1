<?php

declare(strict_types=1);

namespace Nexus\Controllers\Admin;

use Nexus\Core\View;
use Nexus\Core\Database;
use Nexus\Core\TenantContext;
use Nexus\Services\Enterprise\PermissionService;

/**
 * Roles & Permissions Controller
 * Manages role-based access control for enterprise compliance
 */
class RolesController
{
    private PermissionService $permService;

    public function __construct()
    {
        $this->requireAdmin();
        $this->permService = new PermissionService();
    }

    private function requireAdmin(): void
    {
        $isAdmin = isset($_SESSION['user_role']) && in_array($_SESSION['user_role'], ['admin', 'super_admin', 'tenant_admin']);
        $isSuperAdmin = !empty($_SESSION['is_super_admin']);

        if (!$isAdmin && !$isSuperAdmin) {
            header('Location: ' . TenantContext::getBasePath() . '/login');
            exit;
        }
    }

    private function getCurrentUserId(): int
    {
        return (int) ($_SESSION['user_id'] ?? 0);
    }

    /**
     * GET /admin-legacy/enterprise/roles
     * Role management dashboard
     */
    public function index(): void
    {
        // Check permission
        if (!$this->permService->can($this->getCurrentUserId(), 'roles.view')) {
            http_response_code(403);
            echo "Permission denied: You need 'roles.view' permission to access this page.";
            return;
        }

        View::render('admin/enterprise/roles/dashboard');
    }

    /**
     * GET /admin-legacy/enterprise/audit/permissions
     * Permission audit log viewer
     */
    public function auditLog(): void
    {
        // Check permission
        if (!$this->permService->can($this->getCurrentUserId(), 'system.audit_logs')) {
            http_response_code(403);
            echo "Permission denied: You need 'system.audit_logs' permission to access this page.";
            return;
        }

        View::render('admin/enterprise/audit/permissions');
    }

    /**
     * GET /admin-legacy/enterprise/permissions
     * Permissions browser
     */
    public function permissions(): void
    {
        if (!$this->permService->can($this->getCurrentUserId(), 'roles.view')) {
            http_response_code(403);
            echo 'Permission denied';
            return;
        }

        View::render('admin/enterprise/permissions/index');
    }

    /**
     * GET /admin-legacy/enterprise/roles/{id}
     * View role details
     */
    public function show(int $id): void
    {
        if (!$this->permService->can($this->getCurrentUserId(), 'roles.view')) {
            http_response_code(403);
            echo json_encode(['error' => 'Permission denied']);
            return;
        }

        $role = Database::query(
            "SELECT * FROM roles WHERE id = ?",
            [$id]
        )->fetch();

        if (!$role) {
            http_response_code(404);
            echo json_encode(['error' => 'Role not found']);
            return;
        }

        // Get role permissions
        $permissions = Database::query("
            SELECT p.*
            FROM permissions p
            JOIN role_permissions rp ON p.id = rp.permission_id
            WHERE rp.role_id = ?
            ORDER BY p.category, p.name
        ", [$id])->fetchAll();

        // Get users with this role
        $users = Database::query("
            SELECT u.id, u.username, u.email, ur.assigned_at, ur.expires_at
            FROM users u
            JOIN user_roles ur ON u.id = ur.user_id
            WHERE ur.role_id = ?
                AND (ur.expires_at IS NULL OR ur.expires_at > NOW())
            ORDER BY ur.assigned_at DESC
            LIMIT 20
        ", [$id])->fetchAll();

        // Get total user count
        $userCount = Database::query("
            SELECT COUNT(*) as count
            FROM user_roles ur
            WHERE ur.role_id = ?
                AND (ur.expires_at IS NULL OR ur.expires_at > NOW())
        ", [$id])->fetch()['count'];

        if ($this->wantsJson()) {
            header('Content-Type: application/json');
            echo json_encode([
                'role' => $role,
                'permissions' => $permissions,
                'users' => $users,
                'userCount' => $userCount
            ]);
            return;
        }

        View::render('admin/enterprise/roles/show');
    }

    /**
     * GET /admin-legacy/enterprise/roles/create
     * Show create role form
     */
    public function create(): void
    {
        if (!$this->permService->can($this->getCurrentUserId(), 'roles.create')) {
            http_response_code(403);
            echo "Permission denied: You need 'roles.create' permission.";
            return;
        }

        View::render('admin/enterprise/roles/create');
    }

    /**
     * POST /admin-legacy/enterprise/roles
     * Store new role
     */
    public function store(): void
    {
        header('Content-Type: application/json');

        if (!$this->permService->can($this->getCurrentUserId(), 'roles.create')) {
            http_response_code(403);
            echo json_encode(['error' => 'Permission denied']);
            return;
        }

        $data = $this->getJsonInput();

        // Validate required fields
        if (empty($data['name']) || empty($data['display_name']) || empty($data['description'])) {
            http_response_code(400);
            echo json_encode(['error' => 'Name, display name, and description are required']);
            return;
        }

        try {
            // Create role
            $roleId = $this->permService->createRole(
                name: $data['name'],
                displayName: $data['display_name'],
                description: $data['description'],
                level: (int) ($data['level'] ?? 0),
                isSystem: false // User-created roles are never system roles
            );

            if (!$roleId) {
                throw new \Exception('Failed to create role');
            }

            // Attach permissions if provided
            if (!empty($data['permission_ids']) && is_array($data['permission_ids'])) {
                $this->permService->attachPermissionsToRole(
                    $roleId,
                    array_map('intval', $data['permission_ids']),
                    $this->getCurrentUserId()
                );
            }

            echo json_encode([
                'success' => true,
                'id' => $roleId,
                'message' => 'Role created successfully'
            ]);

        } catch (\Exception $e) {
            http_response_code(500);
            echo json_encode(['error' => $e->getMessage()]);
        }
    }

    /**
     * GET /admin-legacy/enterprise/roles/{id}/edit
     * Show edit role form
     */
    public function edit(int $id): void
    {
        if (!$this->permService->can($this->getCurrentUserId(), 'roles.edit')) {
            http_response_code(403);
            echo "Permission denied: You need 'roles.edit' permission.";
            return;
        }

        $role = Database::query("SELECT * FROM roles WHERE id = ?", [$id])->fetch();

        if (!$role) {
            http_response_code(404);
            echo "Role not found";
            return;
        }

        // Cannot edit system roles
        if ($role['is_system']) {
            http_response_code(403);
            echo "Cannot edit system roles";
            return;
        }

        View::render('admin/enterprise/roles/edit');
    }

    /**
     * PATCH /admin-legacy/enterprise/roles/{id}
     * Update role
     */
    public function update(int $id): void
    {
        header('Content-Type: application/json');

        if (!$this->permService->can($this->getCurrentUserId(), 'roles.edit')) {
            http_response_code(403);
            echo json_encode(['error' => 'Permission denied']);
            return;
        }

        $role = Database::query("SELECT * FROM roles WHERE id = ?", [$id])->fetch();

        if (!$role) {
            http_response_code(404);
            echo json_encode(['error' => 'Role not found']);
            return;
        }

        if ($role['is_system']) {
            http_response_code(403);
            echo json_encode(['error' => 'Cannot edit system roles']);
            return;
        }

        $data = $this->getJsonInput();

        try {
            // Update role details
            Database::query("
                UPDATE roles
                SET display_name = ?,
                    description = ?,
                    level = ?
                WHERE id = ?
            ", [
                $data['display_name'] ?? $role['display_name'],
                $data['description'] ?? $role['description'],
                (int) ($data['level'] ?? $role['level']),
                $id
            ]);

            // Update permissions if provided
            if (isset($data['permission_ids']) && is_array($data['permission_ids'])) {
                // Remove existing permissions
                Database::query("DELETE FROM role_permissions WHERE role_id = ?", [$id]);

                // Add new permissions
                $this->permService->attachPermissionsToRole(
                    $id,
                    array_map('intval', $data['permission_ids']),
                    $this->getCurrentUserId()
                );

                // Clear permission cache for all users with this role
                $users = Database::query(
                    "SELECT DISTINCT user_id FROM user_roles WHERE role_id = ?",
                    [$id]
                )->fetchAll();

                foreach ($users as $user) {
                    $this->permService->clearUserPermissionCache($user['user_id']);
                }
            }

            echo json_encode([
                'success' => true,
                'message' => 'Role updated successfully'
            ]);

        } catch (\Exception $e) {
            http_response_code(500);
            echo json_encode(['error' => $e->getMessage()]);
        }
    }

    /**
     * DELETE /admin-legacy/enterprise/roles/{id}
     * Delete role
     */
    public function destroy(int $id): void
    {
        header('Content-Type: application/json');

        if (!$this->permService->can($this->getCurrentUserId(), 'roles.delete')) {
            http_response_code(403);
            echo json_encode(['error' => 'Permission denied']);
            return;
        }

        $role = Database::query("SELECT * FROM roles WHERE id = ?", [$id])->fetch();

        if (!$role) {
            http_response_code(404);
            echo json_encode(['error' => 'Role not found']);
            return;
        }

        if ($role['is_system']) {
            http_response_code(403);
            echo json_encode(['error' => 'Cannot delete system roles']);
            return;
        }

        try {
            // Delete role (CASCADE will remove role_permissions and user_roles)
            Database::query("DELETE FROM roles WHERE id = ?", [$id]);

            echo json_encode([
                'success' => true,
                'message' => 'Role deleted successfully'
            ]);

        } catch (\Exception $e) {
            http_response_code(500);
            echo json_encode(['error' => $e->getMessage()]);
        }
    }

    /**
     * POST /admin-legacy/enterprise/roles/{id}/users/{userId}
     * Assign role to user
     */
    public function assignToUser(int $roleId, int $userId): void
    {
        header('Content-Type: application/json');

        if (!$this->permService->can($this->getCurrentUserId(), 'roles.assign')) {
            http_response_code(403);
            echo json_encode(['error' => 'Permission denied']);
            return;
        }

        $data = $this->getJsonInput();
        $expiresAt = $data['expires_at'] ?? null;

        try {
            $success = $this->permService->assignRole(
                $userId,
                $roleId,
                $this->getCurrentUserId(),
                $expiresAt
            );

            if ($success) {
                echo json_encode([
                    'success' => true,
                    'message' => 'Role assigned successfully'
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
     * DELETE /admin-legacy/enterprise/roles/{id}/users/{userId}
     * Revoke role from user
     */
    public function revokeFromUser(int $roleId, int $userId): void
    {
        header('Content-Type: application/json');

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
                    'message' => 'Role revoked successfully'
                ]);
            } else {
                throw new \Exception('Failed to revoke role');
            }

        } catch (\Exception $e) {
            http_response_code(500);
            echo json_encode(['error' => $e->getMessage()]);
        }
    }

    private function getJsonInput(): array
    {
        $input = file_get_contents('php://input');
        return json_decode($input, true) ?? [];
    }

    private function wantsJson(): bool
    {
        return isset($_SERVER['HTTP_ACCEPT']) &&
               str_contains($_SERVER['HTTP_ACCEPT'], 'application/json');
    }
}

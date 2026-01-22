<?php

namespace Nexus\Controllers\SuperAdmin;

use Nexus\Core\View;
use Nexus\Core\Csrf;
use Nexus\Core\Database;
use Nexus\Middleware\SuperPanelAccess;
use Nexus\Services\TenantVisibilityService;
use Nexus\Services\TenantHierarchyService;
use Nexus\Services\SuperAdminAuditService;
use Nexus\Models\User;
use Nexus\Models\Tenant;

/**
 * Super Admin User Controller
 *
 * Manages users across tenants in the Super Admin Panel.
 * Operations are scoped to user's visibility in the hierarchy.
 */
class UserController
{
    public function __construct()
    {
        SuperPanelAccess::handle();
    }

    /**
     * List users across visible tenants
     */
    public function index()
    {
        $access = SuperPanelAccess::getAccess();

        $filters = [
            'search' => $_GET['search'] ?? null,
            'tenant_id' => !empty($_GET['tenant_id']) ? (int)$_GET['tenant_id'] : null,
            'role' => $_GET['role'] ?? null,
            'is_tenant_super_admin' => isset($_GET['super_admins']) ? 1 : null,
            'limit' => 100
        ];

        $users = TenantVisibilityService::getUserList(array_filter($filters));
        $tenants = TenantVisibilityService::getTenantList();

        View::render('super-admin/users/index', [
            'access' => $access,
            'users' => $users,
            'tenants' => $tenants,
            'filters' => $filters,
            'pageTitle' => 'Manage Users'
        ]);
    }

    /**
     * View user details
     */
    public function show($id)
    {
        $userId = (int)$id;
        $user = User::findById($userId, false); // Don't enforce tenant

        if (!$user) {
            http_response_code(404);
            View::render('errors/404', ['message' => 'User not found']);
            return;
        }

        // Check if we can access user's tenant
        if (!SuperPanelAccess::canAccessTenant((int)$user['tenant_id'])) {
            http_response_code(403);
            View::render('errors/403', ['message' => 'You cannot access this user']);
            return;
        }

        $tenant = Tenant::find($user['tenant_id']);
        $access = SuperPanelAccess::getAccess();

        View::render('super-admin/users/show', [
            'access' => $access,
            'user' => $user,
            'tenant' => $tenant,
            'canManage' => SuperPanelAccess::canManageTenant((int)$user['tenant_id']),
            'pageTitle' => ($user['first_name'] ?? '') . ' ' . ($user['last_name'] ?? '')
        ]);
    }

    /**
     * Show create user form (with tenant selector)
     */
    public function create()
    {
        $access = SuperPanelAccess::getAccess();
        $tenantId = (int)($_GET['tenant_id'] ?? 0);

        $tenants = TenantVisibilityService::getTenantList();
        $selectedTenant = $tenantId ? Tenant::find($tenantId) : null;

        View::render('super-admin/users/create', [
            'access' => $access,
            'tenants' => $tenants,
            'selectedTenant' => $selectedTenant,
            'tenantId' => $tenantId,
            'pageTitle' => 'Create User'
        ]);
    }

    /**
     * Store new user
     */
    public function store()
    {
        Csrf::verifyOrDie();

        $tenantId = (int)($_POST['tenant_id'] ?? 0);

        // Verify access to target tenant
        if (!$tenantId || !SuperPanelAccess::canAccessTenant($tenantId)) {
            $this->redirectWithError('/super-admin/users/create', 'Invalid or inaccessible tenant');
            return;
        }

        $firstName = trim($_POST['first_name'] ?? '');
        $lastName = trim($_POST['last_name'] ?? '');
        $email = trim($_POST['email'] ?? '');
        $password = $_POST['password'] ?? '';
        $role = $_POST['role'] ?? 'member';

        // Validation
        if (empty($firstName) || empty($email) || empty($password)) {
            $this->redirectWithError(
                '/super-admin/users/create?tenant_id=' . $tenantId,
                'First name, email, and password are required'
            );
            return;
        }

        // Check if email exists
        $existing = User::findGlobalByEmail($email);
        if ($existing) {
            $this->redirectWithError(
                '/super-admin/users/create?tenant_id=' . $tenantId,
                'Email already exists in the system'
            );
            return;
        }

        $options = [
            'location' => $_POST['location'] ?? null,
            'phone' => $_POST['phone'] ?? null,
            'is_approved' => 1,
            'is_tenant_super_admin' => isset($_POST['is_tenant_super_admin']) ? 1 : 0
        ];

        // Only allow granting super admin if we can manage the tenant
        if ($options['is_tenant_super_admin'] && !SuperPanelAccess::canManageTenant($tenantId)) {
            $options['is_tenant_super_admin'] = 0;
        }

        $userId = User::createWithTenant($tenantId, $firstName, $lastName, $email, $password, $role, $options);

        if ($userId) {
            // Audit log
            SuperAdminAuditService::log(
                'user_created',
                'user',
                $userId,
                "{$firstName} {$lastName}",
                null,
                ['tenant_id' => $tenantId, 'email' => $email, 'role' => $role],
                "Created user '{$firstName} {$lastName}' in tenant ID {$tenantId}"
            );

            $_SESSION['flash_success'] = "User '{$firstName} {$lastName}' created successfully";
            header('Location: /super-admin/users/' . $userId);
        } else {
            $this->redirectWithError(
                '/super-admin/users/create?tenant_id=' . $tenantId,
                'Failed to create user'
            );
        }
    }

    /**
     * Show edit user form (Master Super Admin feature)
     */
    public function edit($id)
    {
        $userId = (int)$id;
        $user = User::findById($userId, false);

        if (!$user) {
            http_response_code(404);
            View::render('errors/404', ['message' => 'User not found']);
            return;
        }

        if (!SuperPanelAccess::canAccessTenant((int)$user['tenant_id'])) {
            http_response_code(403);
            View::render('errors/403', ['message' => 'You cannot access this user']);
            return;
        }

        $tenant = Tenant::find($user['tenant_id']);
        $access = SuperPanelAccess::getAccess();
        $tenants = TenantVisibilityService::getTenantList();

        // Get hub tenants (those that allow sub-tenants) for super admin assignment
        $hubTenants = array_filter($tenants, fn($t) => $t['allows_subtenants']);

        View::render('super-admin/users/edit', [
            'access' => $access,
            'user' => $user,
            'tenant' => $tenant,
            'tenants' => $tenants,
            'hubTenants' => array_values($hubTenants),
            'canManage' => SuperPanelAccess::canManageTenant((int)$user['tenant_id']),
            'pageTitle' => 'Edit: ' . ($user['first_name'] ?? '') . ' ' . ($user['last_name'] ?? '')
        ]);
    }

    /**
     * Update user details
     */
    public function update($id)
    {
        Csrf::verifyOrDie();

        $userId = (int)$id;
        $user = User::findById($userId, false);

        if (!$user) {
            $this->redirectWithError('/super-admin/users', 'User not found');
            return;
        }

        if (!SuperPanelAccess::canManageTenant((int)$user['tenant_id'])) {
            $this->redirectWithError('/super-admin/users/' . $userId, 'Cannot modify this user');
            return;
        }

        $firstName = trim($_POST['first_name'] ?? '');
        $lastName = trim($_POST['last_name'] ?? '');
        $email = trim($_POST['email'] ?? '');
        $role = $_POST['role'] ?? $user['role'];
        $location = trim($_POST['location'] ?? '');
        $phone = trim($_POST['phone'] ?? '');

        if (empty($firstName) || empty($email)) {
            $this->redirectWithError('/super-admin/users/' . $userId . '/edit', 'First name and email are required');
            return;
        }

        // Check email uniqueness if changed
        if ($email !== $user['email']) {
            $existing = User::findGlobalByEmail($email);
            if ($existing && $existing['id'] != $userId) {
                $this->redirectWithError('/super-admin/users/' . $userId . '/edit', 'Email already exists');
                return;
            }
        }

        try {
            Database::query("
                UPDATE users SET
                    first_name = ?, last_name = ?, email = ?, role = ?,
                    location = ?, phone = ?, updated_at = NOW()
                WHERE id = ?
            ", [$firstName, $lastName, $email, $role, $location ?: null, $phone ?: null, $userId]);

            $_SESSION['flash_success'] = 'User updated successfully';
            header('Location: /super-admin/users/' . $userId);
        } catch (\Exception $e) {
            $this->redirectWithError('/super-admin/users/' . $userId . '/edit', 'Failed to update user');
        }
    }

    /**
     * Move user to a Hub tenant AND grant Super Admin privileges (combo action)
     * This is the KEY workflow for Master admins to create regional super admins
     */
    public function moveAndPromote($id)
    {
        Csrf::verifyOrDie();

        $userId = (int)$id;
        $targetTenantId = (int)($_POST['target_tenant_id'] ?? 0);

        $user = User::findById($userId, false);

        if (!$user) {
            $this->redirectWithError('/super-admin/users', 'User not found');
            return;
        }

        // Must be able to manage source tenant
        if (!SuperPanelAccess::canManageTenant((int)$user['tenant_id'])) {
            $this->redirectWithError('/super-admin/users/' . $userId, 'Cannot modify this user');
            return;
        }

        // Target tenant must exist and be accessible
        if (!$targetTenantId || !SuperPanelAccess::canAccessTenant($targetTenantId)) {
            $this->redirectWithError('/super-admin/users/' . $userId . '/edit', 'Invalid target tenant');
            return;
        }

        // Target tenant must allow sub-tenants (Hub tenant)
        $targetTenant = Tenant::find($targetTenantId);
        if (!$targetTenant || !$targetTenant['allows_subtenants']) {
            $this->redirectWithError('/super-admin/users/' . $userId . '/edit',
                'Target tenant must be a Hub tenant (allows sub-tenants) to assign Super Admin');
            return;
        }

        try {
            $oldTenantId = $user['tenant_id'];

            // Step 1: Move user and all their content to target tenant
            if (!User::moveTenant($userId, $targetTenantId)) {
                $this->redirectWithError('/super-admin/users/' . $userId . '/edit', 'Failed to move user to new tenant');
                return;
            }

            // Step 2: Grant super admin privileges
            Database::query("UPDATE users SET is_tenant_super_admin = 1, role = 'tenant_admin' WHERE id = ?", [$userId]);

            // Audit log
            $userName = trim(($user['first_name'] ?? '') . ' ' . ($user['last_name'] ?? '')) ?: $user['email'];
            SuperAdminAuditService::log(
                'user_moved',
                'user',
                $userId,
                $userName,
                ['tenant_id' => $oldTenantId, 'is_tenant_super_admin' => $user['is_tenant_super_admin'] ?? 0],
                ['tenant_id' => $targetTenantId, 'is_tenant_super_admin' => 1],
                "Moved '{$userName}' to '{$targetTenant['name']}' and granted Super Admin privileges (with all content)"
            );

            $_SESSION['flash_success'] = sprintf(
                "User moved to '%s' and granted Super Admin privileges. All their content has been moved as well.",
                $targetTenant['name']
            );
            header('Location: /super-admin/users/' . $userId);

        } catch (\Exception $e) {
            error_log("moveAndPromote error: " . $e->getMessage());
            $this->redirectWithError('/super-admin/users/' . $userId . '/edit', 'Failed to move and promote user');
        }
    }

    /**
     * Grant tenant super admin privileges (is_tenant_super_admin)
     * Any super admin can grant this to users within their manageable scope
     * Note: This is different from is_super_admin (global) which requires god mode
     */
    public function grantSuperAdmin($id)
    {
        Csrf::verifyOrDie();

        $userId = (int)$id;
        $user = User::findById($userId, false);

        if (!$user) {
            $this->redirectWithError('/super-admin/users', 'User not found');
            return;
        }

        // Must be able to manage user's tenant
        if (!SuperPanelAccess::canManageTenant((int)$user['tenant_id'])) {
            $this->redirectWithError('/super-admin/users/' . $userId, 'Cannot modify this user');
            return;
        }

        // Tenant must allow sub-tenants for user to become super admin
        $tenant = Tenant::find($user['tenant_id']);
        if (!$tenant || !$tenant['allows_subtenants']) {
            $this->redirectWithError(
                '/super-admin/users/' . $userId,
                'Cannot grant super admin: tenant does not allow sub-tenants'
            );
            return;
        }

        $result = TenantHierarchyService::assignTenantSuperAdmin($userId, (int)$user['tenant_id']);

        if ($result['success']) {
            $_SESSION['flash_success'] = 'Super admin privileges granted';
        } else {
            $_SESSION['flash_error'] = $result['error'];
        }

        header('Location: /super-admin/users/' . $userId);
        exit;
    }

    /**
     * Revoke tenant super admin privileges (is_tenant_super_admin)
     * Any super admin can revoke this from users within their manageable scope
     * Note: This is different from is_super_admin (global) which requires god mode
     */
    public function revokeSuperAdmin($id)
    {
        Csrf::verifyOrDie();

        $userId = (int)$id;
        $user = User::findById($userId, false);

        if (!$user) {
            $this->redirectWithError('/super-admin/users', 'User not found');
            return;
        }

        // Must be able to manage user's tenant
        if (!SuperPanelAccess::canManageTenant((int)$user['tenant_id'])) {
            $this->redirectWithError('/super-admin/users/' . $userId, 'Cannot modify this user');
            return;
        }

        // Cannot revoke from a global super admin (is_super_admin) - only god can do that
        if (!empty($user['is_super_admin']) && !User::isGod()) {
            $this->redirectWithError('/super-admin/users/' . $userId, 'Cannot revoke privileges from a global super admin');
            return;
        }

        $result = TenantHierarchyService::revokeTenantSuperAdmin($userId);

        if ($result['success']) {
            $_SESSION['flash_success'] = 'Super admin privileges revoked';
        } else {
            $_SESSION['flash_error'] = $result['error'];
        }

        header('Location: /super-admin/users/' . $userId);
        exit;
    }

    /**
     * Grant GLOBAL super admin privileges (is_super_admin)
     * GOD ONLY - this gives access to ALL tenants
     */
    public function grantGlobalSuperAdmin($id)
    {
        Csrf::verifyOrDie();

        // GOD ONLY
        if (!User::isGod()) {
            $this->redirectWithError('/super-admin/users/' . $id, 'Only god users can grant global super admin');
            return;
        }

        $userId = (int)$id;
        $user = User::findById($userId, false);

        if (!$user) {
            $this->redirectWithError('/super-admin/users', 'User not found');
            return;
        }

        // Grant global super admin
        Database::query(
            "UPDATE users SET is_super_admin = 1, role = CASE WHEN role = 'member' THEN 'admin' ELSE role END WHERE id = ?",
            [$userId]
        );

        $_SESSION['flash_success'] = 'Global super admin privileges granted - user can now access ALL tenants';

        header('Location: /super-admin/users/' . $userId);
        exit;
    }

    /**
     * Revoke GLOBAL super admin privileges (is_super_admin)
     * GOD ONLY
     */
    public function revokeGlobalSuperAdmin($id)
    {
        Csrf::verifyOrDie();

        // GOD ONLY
        if (!User::isGod()) {
            $this->redirectWithError('/super-admin/users/' . $id, 'Only god users can revoke global super admin');
            return;
        }

        $userId = (int)$id;
        $user = User::findById($userId, false);

        if (!$user) {
            $this->redirectWithError('/super-admin/users', 'User not found');
            return;
        }

        // Cannot revoke from yourself
        if ($userId == $_SESSION['user_id']) {
            $this->redirectWithError('/super-admin/users/' . $userId, 'Cannot revoke your own global super admin status');
            return;
        }

        // Revoke global super admin
        Database::query("UPDATE users SET is_super_admin = 0 WHERE id = ?", [$userId]);

        $_SESSION['flash_success'] = 'Global super admin privileges revoked';

        header('Location: /super-admin/users/' . $userId);
        exit;
    }

    /**
     * Move user to different tenant
     */
    public function moveTenant($id)
    {
        Csrf::verifyOrDie();

        $userId = (int)$id;
        $newTenantId = (int)($_POST['new_tenant_id'] ?? 0);

        $user = User::findById($userId, false);

        if (!$user) {
            $this->redirectWithError('/super-admin/users', 'User not found');
            return;
        }

        // Must be able to manage both source and target tenants
        if (!SuperPanelAccess::canManageTenant((int)$user['tenant_id'])) {
            $this->redirectWithError('/super-admin/users/' . $userId, 'Cannot modify this user');
            return;
        }

        if (!SuperPanelAccess::canAccessTenant($newTenantId)) {
            $this->redirectWithError('/super-admin/users/' . $userId, 'Cannot access target tenant');
            return;
        }

        $oldTenantId = $user['tenant_id'];

        if (User::moveTenant($userId, $newTenantId)) {
            // Revoke super admin if moving to a tenant without sub-tenant capability
            $newTenant = Tenant::find($newTenantId);
            if (!$newTenant['allows_subtenants']) {
                User::revokeTenantSuperAdmin($userId);
            }

            // Audit log
            $userName = trim(($user['first_name'] ?? '') . ' ' . ($user['last_name'] ?? '')) ?: $user['email'];
            SuperAdminAuditService::log(
                'user_moved',
                'user',
                $userId,
                $userName,
                ['tenant_id' => $oldTenantId],
                ['tenant_id' => $newTenantId],
                "Moved '{$userName}' to tenant '{$newTenant['name']}' (with all content)"
            );

            $_SESSION['flash_success'] = 'User and all their content moved to new tenant';
        } else {
            $_SESSION['flash_error'] = 'Failed to move user';
        }

        header('Location: /super-admin/users/' . $userId);
    }

    /**
     * API: Search users across tenants
     */
    public function apiSearch()
    {
        header('Content-Type: application/json');

        $query = $_GET['q'] ?? '';
        $tenantId = !empty($_GET['tenant_id']) ? (int)$_GET['tenant_id'] : null;

        $users = TenantVisibilityService::getUserList([
            'search' => $query,
            'tenant_id' => $tenantId,
            'limit' => 20
        ]);

        echo json_encode([
            'success' => true,
            'users' => $users
        ]);
    }

    /**
     * Helper: Redirect with error message
     */
    private function redirectWithError(string $url, string $error): void
    {
        $_SESSION['flash_error'] = $error;
        header('Location: ' . $url);
        exit;
    }
}

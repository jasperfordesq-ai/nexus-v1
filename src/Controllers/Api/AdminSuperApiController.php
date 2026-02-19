<?php
// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

namespace Nexus\Controllers\Api;

use Nexus\Core\Database;
use Nexus\Core\ApiErrorCodes;
use Nexus\Services\TenantVisibilityService;
use Nexus\Services\TenantHierarchyService;
use Nexus\Services\SuperAdminAuditService;
use Nexus\Services\FederationFeatureService;
use Nexus\Services\FederationPartnershipService;
use Nexus\Services\FederationAuditService;
use Nexus\Models\User;
use Nexus\Models\Tenant;

/**
 * AdminSuperApiController - V2 API for the Super Admin Panel
 *
 * Provides JWT-authenticated endpoints for cross-tenant management:
 * - Dashboard stats
 * - Tenant CRUD (list, create, edit, delete, reactivate, toggle-hub, move, hierarchy)
 * - User management (cross-tenant CRUD, super admin grant/revoke, move tenant)
 * - Bulk operations (move users, update tenants)
 * - Audit log
 * - Federation system controls (lockdown, whitelist, partnerships, tenant features)
 *
 * All endpoints require super_admin or god role via $this->requireSuperAdmin().
 */
class AdminSuperApiController extends BaseApiController
{
    protected bool $isV2Api = true;

    // ─────────────────────────────────────────────────────────────────────────
    // Helpers
    // ─────────────────────────────────────────────────────────────────────────

    /**
     * Extract a numeric {id} from the current request URI for a given route pattern.
     *
     * @param string $segment The path segment before the ID (e.g. 'tenants', 'users')
     * @return int
     */
    private function extractId(string $segment): int
    {
        $uri = parse_url($_SERVER['REQUEST_URI'] ?? '', PHP_URL_PATH);
        if (preg_match('#/api/v2/admin/super/' . preg_quote($segment, '#') . '/(\d+)#', $uri, $m)) {
            return (int) $m[1];
        }
        $this->respondWithError(ApiErrorCodes::VALIDATION_ERROR, 'Missing or invalid ID', 'id', 400);
        return 0; // unreachable
    }

    /**
     * Override requireSuperAdmin to also sync JWT context to $_SESSION.
     *
     * SuperPanelAccess::getAccess() reads from $_SESSION to determine visibility scope.
     * When using Bearer token auth (stateless), $_SESSION is empty. This method bridges
     * the gap by populating $_SESSION with the JWT user's data so TenantVisibilityService
     * and other session-dependent services work correctly with API requests.
     */
    protected function requireSuperAdmin(): int
    {
        $userId = parent::requireSuperAdmin();

        // Sync JWT context to session for SuperPanelAccess compatibility
        if (session_status() === PHP_SESSION_NONE) {
            @session_start();
        }

        if (empty($_SESSION['user_id'])) {
            $user = Database::query(
                "SELECT id, tenant_id, role, is_super_admin, is_tenant_super_admin FROM users WHERE id = ?",
                [$userId]
            )->fetch(\PDO::FETCH_ASSOC);

            if ($user) {
                $_SESSION['user_id'] = (int) $user['id'];
                $_SESSION['tenant_id'] = (int) $user['tenant_id'];
                $_SESSION['user_role'] = $user['role'];
                $_SESSION['is_super_admin'] = (int) $user['is_super_admin'];
                $_SESSION['is_tenant_super_admin'] = (int) $user['is_tenant_super_admin'];

                // Reset cached access so it re-evaluates with session data
                \Nexus\Middleware\SuperPanelAccess::reset();
            }
        }

        return $userId;
    }

    /**
     * Require god role (is_god flag in DB). Uses the authenticated user ID from JWT.
     */
    private function requireGod(int $userId): void
    {
        if (!User::isGod($userId)) {
            $this->respondWithError(
                ApiErrorCodes::AUTH_INSUFFICIENT_PERMISSIONS,
                'God-level access required for this action',
                null,
                403
            );
        }
    }

    // =========================================================================
    // DASHBOARD
    // =========================================================================

    /**
     * GET /api/v2/admin/super/dashboard
     */
    public function dashboard(): void
    {
        $this->requireSuperAdmin();

        $stats = TenantVisibilityService::getDashboardStats();

        $this->respondWithData($stats);
    }

    // =========================================================================
    // TENANTS
    // =========================================================================

    /**
     * GET /api/v2/admin/super/tenants
     */
    public function tenantList(): void
    {
        $this->requireSuperAdmin();

        $filters = array_filter([
            'search' => $this->query('search'),
            'is_active' => $this->query('is_active') !== null ? (int) $this->query('is_active') : null,
            'allows_subtenants' => $this->query('hub') !== null ? 1 : null,
        ], fn($v) => $v !== null);

        $tenants = TenantVisibilityService::getTenantList($filters);

        $this->respondWithData($tenants);
    }

    /**
     * GET /api/v2/admin/super/tenants/hierarchy
     */
    public function tenantHierarchy(): void
    {
        $this->requireSuperAdmin();

        $tree = TenantVisibilityService::getHierarchyTree();

        $this->respondWithData($tree);
    }

    /**
     * GET /api/v2/admin/super/tenants/{id}
     */
    public function tenantShow(): void
    {
        $this->requireSuperAdmin();

        $tenantId = $this->extractId('tenants');

        $tenant = TenantVisibilityService::getTenant($tenantId);
        if (!$tenant) {
            $this->respondWithError(ApiErrorCodes::RESOURCE_NOT_FOUND, 'Tenant not found', null, 404);
        }

        $children = Tenant::getChildren($tenantId);
        $admins = TenantVisibilityService::getTenantAdmins($tenantId);
        $breadcrumb = Tenant::getBreadcrumb($tenantId);

        $this->respondWithData([
            'tenant' => $tenant,
            'children' => $children,
            'admins' => $admins,
            'breadcrumb' => $breadcrumb,
        ]);
    }

    /**
     * POST /api/v2/admin/super/tenants
     */
    public function tenantCreate(): void
    {
        $userId = $this->requireSuperAdmin();

        $input = $this->getAllInput();

        $parentId = (int) ($input['parent_id'] ?? 0);
        if (!$parentId) {
            $this->respondWithError(ApiErrorCodes::VALIDATION_ERROR, 'parent_id is required', 'parent_id', 422);
        }

        $name = trim($input['name'] ?? '');
        if (empty($name)) {
            $this->respondWithError(ApiErrorCodes::VALIDATION_ERROR, 'name is required', 'name', 422);
        }

        $data = [
            'name' => $name,
            'slug' => trim($input['slug'] ?? ''),
            'domain' => trim($input['domain'] ?? ''),
            'tagline' => $input['tagline'] ?? '',
            'description' => $input['description'] ?? '',
            'allows_subtenants' => !empty($input['allows_subtenants']),
            'max_depth' => (int) ($input['max_depth'] ?? 2),
            'is_active' => isset($input['is_active']) ? (int) (bool) $input['is_active'] : 1,
        ];

        $result = TenantHierarchyService::createTenant($data, $parentId);

        if ($result['success']) {
            $this->respondWithData([
                'tenant_id' => $result['tenant_id'],
                'message' => "Tenant '{$name}' created successfully",
            ], null, 201);
        } else {
            $this->respondWithError(ApiErrorCodes::VALIDATION_ERROR, $result['error'], null, 422);
        }
    }

    /**
     * PUT /api/v2/admin/super/tenants/{id}
     */
    public function tenantUpdate(): void
    {
        $this->requireSuperAdmin();

        $tenantId = $this->extractId('tenants');
        $input = $this->getAllInput();

        if (empty($input)) {
            $this->respondWithError(ApiErrorCodes::VALIDATION_ERROR, 'Request body is empty', null, 422);
        }

        $result = TenantHierarchyService::updateTenant($tenantId, $input);

        if ($result['success']) {
            $this->respondWithData(['updated' => true, 'tenant_id' => $tenantId]);
        } else {
            $this->respondWithError(ApiErrorCodes::VALIDATION_ERROR, $result['error'], null, 422);
        }
    }

    /**
     * DELETE /api/v2/admin/super/tenants/{id}
     */
    public function tenantDelete(): void
    {
        $this->requireSuperAdmin();

        $tenantId = $this->extractId('tenants');
        $input = $this->getAllInput();
        $hardDelete = !empty($input['hard_delete']);

        $result = TenantHierarchyService::deleteTenant($tenantId, $hardDelete);

        if ($result['success']) {
            $this->respondWithData(['deleted' => true, 'tenant_id' => $tenantId]);
        } else {
            $this->respondWithError(ApiErrorCodes::VALIDATION_ERROR, $result['error'], null, 422);
        }
    }

    /**
     * POST /api/v2/admin/super/tenants/{id}/reactivate
     */
    public function tenantReactivate(): void
    {
        $this->requireSuperAdmin();

        $tenantId = $this->extractId('tenants');

        $result = TenantHierarchyService::updateTenant($tenantId, ['is_active' => 1]);

        if ($result['success']) {
            $this->respondWithData(['reactivated' => true, 'tenant_id' => $tenantId]);
        } else {
            $this->respondWithError(ApiErrorCodes::VALIDATION_ERROR, $result['error'], null, 422);
        }
    }

    /**
     * POST /api/v2/admin/super/tenants/{id}/toggle-hub
     *
     * Body: { "enable": true|false }
     */
    public function tenantToggleHub(): void
    {
        $this->requireSuperAdmin();

        $tenantId = $this->extractId('tenants');
        $input = $this->getAllInput();
        $enable = !empty($input['enable']);

        $result = TenantHierarchyService::toggleSubtenantCapability($tenantId, $enable);

        if ($result['success']) {
            $this->respondWithData([
                'tenant_id' => $tenantId,
                'hub_enabled' => $enable,
            ]);
        } else {
            $this->respondWithError(ApiErrorCodes::VALIDATION_ERROR, $result['error'], null, 422);
        }
    }

    /**
     * POST /api/v2/admin/super/tenants/{id}/move
     *
     * Body: { "new_parent_id": 3 }
     */
    public function tenantMove(): void
    {
        $this->requireSuperAdmin();

        $tenantId = $this->extractId('tenants');
        $input = $this->getAllInput();
        $newParentId = (int) ($input['new_parent_id'] ?? 0);

        if (!$newParentId) {
            $this->respondWithError(ApiErrorCodes::VALIDATION_ERROR, 'new_parent_id is required', 'new_parent_id', 422);
        }

        $result = TenantHierarchyService::moveTenant($tenantId, $newParentId);

        if ($result['success']) {
            $this->respondWithData([
                'moved' => true,
                'tenant_id' => $tenantId,
                'new_parent_id' => $newParentId,
            ]);
        } else {
            $this->respondWithError(ApiErrorCodes::VALIDATION_ERROR, $result['error'], null, 422);
        }
    }

    // =========================================================================
    // USERS (Cross-Tenant)
    // =========================================================================

    /**
     * GET /api/v2/admin/super/users
     */
    public function userList(): void
    {
        $this->requireSuperAdmin();

        $page = $this->queryInt('page', 1, 1);
        $limit = $this->queryInt('limit', 50, 1, 200);

        $filters = array_filter([
            'search' => $this->query('search'),
            'tenant_id' => $this->query('tenant_id') !== null ? (int) $this->query('tenant_id') : null,
            'role' => $this->query('role'),
            'is_tenant_super_admin' => $this->query('super_admins') !== null ? 1 : null,
            'limit' => $limit,
            'offset' => ($page - 1) * $limit,
        ], fn($v) => $v !== null);

        $users = TenantVisibilityService::getUserList($filters);

        $this->respondWithData($users);
    }

    /**
     * GET /api/v2/admin/super/users/{id}
     */
    public function userShow(): void
    {
        $this->requireSuperAdmin();

        $userId = $this->extractId('users');
        $user = User::findById($userId, false);

        if (!$user) {
            $this->respondWithError(ApiErrorCodes::RESOURCE_NOT_FOUND, 'User not found', null, 404);
        }

        $tenant = Tenant::find($user['tenant_id']);

        $this->respondWithData([
            'user' => $user,
            'tenant' => $tenant,
        ]);
    }

    /**
     * POST /api/v2/admin/super/users
     *
     * Body: { "tenant_id": 2, "first_name": "...", "last_name": "...", "email": "...", "password": "...", "role": "member", ... }
     */
    public function userCreate(): void
    {
        $userId = $this->requireSuperAdmin();

        $input = $this->getAllInput();

        $tenantId = (int) ($input['tenant_id'] ?? 0);
        if (!$tenantId) {
            $this->respondWithError(ApiErrorCodes::VALIDATION_ERROR, 'tenant_id is required', 'tenant_id', 422);
        }

        $firstName = trim($input['first_name'] ?? '');
        $lastName = trim($input['last_name'] ?? '');
        $email = trim($input['email'] ?? '');
        $password = $input['password'] ?? '';
        $role = $input['role'] ?? 'member';

        if (empty($firstName) || empty($email) || empty($password)) {
            $this->respondWithError(ApiErrorCodes::VALIDATION_ERROR, 'first_name, email, and password are required', null, 422);
        }

        // Check email uniqueness
        $existing = User::findGlobalByEmail($email);
        if ($existing) {
            $this->respondWithError(ApiErrorCodes::VALIDATION_ERROR, 'Email already exists in the system', 'email', 422);
        }

        $options = [
            'location' => $input['location'] ?? null,
            'phone' => $input['phone'] ?? null,
            'is_approved' => 1,
            'is_tenant_super_admin' => !empty($input['is_tenant_super_admin']) ? 1 : 0,
        ];

        $newUserId = User::createWithTenant($tenantId, $firstName, $lastName, $email, $password, $role, $options);

        if ($newUserId) {
            SuperAdminAuditService::log(
                'user_created',
                'user',
                $newUserId,
                "{$firstName} {$lastName}",
                null,
                ['tenant_id' => $tenantId, 'email' => $email, 'role' => $role],
                "Created user '{$firstName} {$lastName}' in tenant ID {$tenantId}"
            );

            $this->respondWithData(['user_id' => $newUserId], null, 201);
        } else {
            $this->respondWithError(ApiErrorCodes::SERVER_INTERNAL_ERROR, 'Failed to create user', null, 500);
        }
    }

    /**
     * PUT /api/v2/admin/super/users/{id}
     *
     * Body: { "first_name": "...", "last_name": "...", "email": "...", "role": "...", "location": "...", "phone": "..." }
     */
    public function userUpdate(): void
    {
        $this->requireSuperAdmin();

        $targetUserId = $this->extractId('users');
        $user = User::findById($targetUserId, false);

        if (!$user) {
            $this->respondWithError(ApiErrorCodes::RESOURCE_NOT_FOUND, 'User not found', null, 404);
        }

        $input = $this->getAllInput();

        $firstName = trim($input['first_name'] ?? $user['first_name']);
        $lastName = trim($input['last_name'] ?? $user['last_name'] ?? '');
        $email = trim($input['email'] ?? $user['email']);
        $role = $input['role'] ?? $user['role'];
        $location = isset($input['location']) ? trim($input['location']) : ($user['location'] ?? null);
        $phone = isset($input['phone']) ? trim($input['phone']) : ($user['phone'] ?? null);

        if (empty($firstName) || empty($email)) {
            $this->respondWithError(ApiErrorCodes::VALIDATION_ERROR, 'first_name and email are required', null, 422);
        }

        // Check email uniqueness if changed
        if ($email !== $user['email']) {
            $existing = User::findGlobalByEmail($email);
            if ($existing && (int) $existing['id'] !== $targetUserId) {
                $this->respondWithError(ApiErrorCodes::VALIDATION_ERROR, 'Email already exists', 'email', 422);
            }
        }

        Database::query(
            "UPDATE users SET first_name = ?, last_name = ?, email = ?, role = ?, location = ?, phone = ?, updated_at = NOW() WHERE id = ?",
            [$firstName, $lastName, $email, $role, $location ?: null, $phone ?: null, $targetUserId]
        );

        $this->respondWithData(['updated' => true, 'user_id' => $targetUserId]);
    }

    /**
     * POST /api/v2/admin/super/users/{id}/grant-super-admin
     *
     * Grants tenant super admin (is_tenant_super_admin) to a user.
     */
    public function userGrantSuperAdmin(): void
    {
        $this->requireSuperAdmin();

        $targetUserId = $this->extractId('users');
        $user = User::findById($targetUserId, false);

        if (!$user) {
            $this->respondWithError(ApiErrorCodes::RESOURCE_NOT_FOUND, 'User not found', null, 404);
        }

        // Tenant must allow sub-tenants
        $tenant = Tenant::find($user['tenant_id']);
        if (!$tenant || !$tenant['allows_subtenants']) {
            $this->respondWithError(
                ApiErrorCodes::VALIDATION_ERROR,
                'Cannot grant super admin: tenant does not allow sub-tenants',
                null,
                422
            );
        }

        $result = TenantHierarchyService::assignTenantSuperAdmin($targetUserId, (int) $user['tenant_id']);

        if ($result['success']) {
            $this->respondWithData(['granted' => true, 'user_id' => $targetUserId]);
        } else {
            $this->respondWithError(ApiErrorCodes::VALIDATION_ERROR, $result['error'], null, 422);
        }
    }

    /**
     * POST /api/v2/admin/super/users/{id}/revoke-super-admin
     *
     * Revokes tenant super admin (is_tenant_super_admin) from a user.
     */
    public function userRevokeSuperAdmin(): void
    {
        $userId = $this->requireSuperAdmin();

        $targetUserId = $this->extractId('users');
        $user = User::findById($targetUserId, false);

        if (!$user) {
            $this->respondWithError(ApiErrorCodes::RESOURCE_NOT_FOUND, 'User not found', null, 404);
        }

        // Cannot revoke from a global super admin unless caller is god
        if (!empty($user['is_super_admin']) && !User::isGod($userId)) {
            $this->respondWithError(
                ApiErrorCodes::AUTH_INSUFFICIENT_PERMISSIONS,
                'Cannot revoke privileges from a global super admin',
                null,
                403
            );
        }

        $result = TenantHierarchyService::revokeTenantSuperAdmin($targetUserId);

        if ($result['success']) {
            $this->respondWithData(['revoked' => true, 'user_id' => $targetUserId]);
        } else {
            $this->respondWithError(ApiErrorCodes::VALIDATION_ERROR, $result['error'], null, 422);
        }
    }

    /**
     * POST /api/v2/admin/super/users/{id}/grant-global-super-admin
     *
     * Grants global super admin (is_super_admin). GOD ONLY.
     */
    public function userGrantGlobalSuperAdmin(): void
    {
        $userId = $this->requireSuperAdmin();
        $this->requireGod($userId);

        $targetUserId = $this->extractId('users');
        $user = User::findById($targetUserId, false);

        if (!$user) {
            $this->respondWithError(ApiErrorCodes::RESOURCE_NOT_FOUND, 'User not found', null, 404);
        }

        Database::query(
            "UPDATE users SET is_super_admin = 1, role = CASE WHEN role = 'member' THEN 'admin' ELSE role END WHERE id = ?",
            [$targetUserId]
        );

        $this->respondWithData(['granted' => true, 'user_id' => $targetUserId, 'level' => 'global']);
    }

    /**
     * POST /api/v2/admin/super/users/{id}/revoke-global-super-admin
     *
     * Revokes global super admin (is_super_admin). GOD ONLY.
     */
    public function userRevokeGlobalSuperAdmin(): void
    {
        $userId = $this->requireSuperAdmin();
        $this->requireGod($userId);

        $targetUserId = $this->extractId('users');
        $user = User::findById($targetUserId, false);

        if (!$user) {
            $this->respondWithError(ApiErrorCodes::RESOURCE_NOT_FOUND, 'User not found', null, 404);
        }

        // Cannot revoke from yourself
        if ($targetUserId === $userId) {
            $this->respondWithError(
                ApiErrorCodes::VALIDATION_ERROR,
                'Cannot revoke your own global super admin status',
                null,
                422
            );
        }

        Database::query("UPDATE users SET is_super_admin = 0 WHERE id = ?", [$targetUserId]);

        $this->respondWithData(['revoked' => true, 'user_id' => $targetUserId, 'level' => 'global']);
    }

    /**
     * POST /api/v2/admin/super/users/{id}/move-tenant
     *
     * Body: { "new_tenant_id": 3 }
     */
    public function userMoveTenant(): void
    {
        $userId = $this->requireSuperAdmin();

        $targetUserId = $this->extractId('users');
        $user = User::findById($targetUserId, false);

        if (!$user) {
            $this->respondWithError(ApiErrorCodes::RESOURCE_NOT_FOUND, 'User not found', null, 404);
        }

        $input = $this->getAllInput();
        $newTenantId = (int) ($input['new_tenant_id'] ?? 0);

        if (!$newTenantId) {
            $this->respondWithError(ApiErrorCodes::VALIDATION_ERROR, 'new_tenant_id is required', 'new_tenant_id', 422);
        }

        $oldTenantId = $user['tenant_id'];

        if (!User::moveTenant($targetUserId, $newTenantId)) {
            $this->respondWithError(ApiErrorCodes::SERVER_INTERNAL_ERROR, 'Failed to move user', null, 500);
        }

        // Revoke super admin if moving to a tenant without sub-tenant capability
        $newTenant = Tenant::find($newTenantId);
        if ($newTenant && !$newTenant['allows_subtenants']) {
            Database::query("UPDATE users SET is_tenant_super_admin = 0 WHERE id = ?", [$targetUserId]);
        }

        // Audit
        $userName = trim(($user['first_name'] ?? '') . ' ' . ($user['last_name'] ?? '')) ?: $user['email'];
        SuperAdminAuditService::log(
            'user_moved',
            'user',
            $targetUserId,
            $userName,
            ['tenant_id' => $oldTenantId],
            ['tenant_id' => $newTenantId],
            "Moved '{$userName}' to tenant '{$newTenant['name']}' (with all content)"
        );

        $this->respondWithData([
            'moved' => true,
            'user_id' => $targetUserId,
            'old_tenant_id' => $oldTenantId,
            'new_tenant_id' => $newTenantId,
        ]);
    }

    /**
     * POST /api/v2/admin/super/users/{id}/move-and-promote
     *
     * Body: { "target_tenant_id": 3 }
     */
    public function userMoveAndPromote(): void
    {
        $userId = $this->requireSuperAdmin();

        $targetUserId = $this->extractId('users');
        $user = User::findById($targetUserId, false);

        if (!$user) {
            $this->respondWithError(ApiErrorCodes::RESOURCE_NOT_FOUND, 'User not found', null, 404);
        }

        $input = $this->getAllInput();
        $targetTenantId = (int) ($input['target_tenant_id'] ?? 0);

        if (!$targetTenantId) {
            $this->respondWithError(ApiErrorCodes::VALIDATION_ERROR, 'target_tenant_id is required', 'target_tenant_id', 422);
        }

        // Target must be a hub tenant
        $targetTenant = Tenant::find($targetTenantId);
        if (!$targetTenant || !$targetTenant['allows_subtenants']) {
            $this->respondWithError(
                ApiErrorCodes::VALIDATION_ERROR,
                'Target tenant must be a Hub tenant (allows sub-tenants)',
                'target_tenant_id',
                422
            );
        }

        $oldTenantId = $user['tenant_id'];

        // Step 1: Move user
        if (!User::moveTenant($targetUserId, $targetTenantId)) {
            $this->respondWithError(ApiErrorCodes::SERVER_INTERNAL_ERROR, 'Failed to move user', null, 500);
        }

        // Step 2: Grant super admin
        Database::query(
            "UPDATE users SET is_tenant_super_admin = 1, role = 'tenant_admin' WHERE id = ?",
            [$targetUserId]
        );

        // Audit
        $userName = trim(($user['first_name'] ?? '') . ' ' . ($user['last_name'] ?? '')) ?: $user['email'];
        SuperAdminAuditService::log(
            'user_moved',
            'user',
            $targetUserId,
            $userName,
            ['tenant_id' => $oldTenantId, 'is_tenant_super_admin' => $user['is_tenant_super_admin'] ?? 0],
            ['tenant_id' => $targetTenantId, 'is_tenant_super_admin' => 1],
            "Moved '{$userName}' to '{$targetTenant['name']}' and granted Super Admin privileges (with all content)"
        );

        $this->respondWithData([
            'moved' => true,
            'promoted' => true,
            'user_id' => $targetUserId,
            'old_tenant_id' => $oldTenantId,
            'new_tenant_id' => $targetTenantId,
        ]);
    }

    // =========================================================================
    // BULK OPERATIONS
    // =========================================================================

    /**
     * POST /api/v2/admin/super/bulk/move-users
     *
     * Body: { "user_ids": [1, 2, 3], "target_tenant_id": 5, "grant_super_admin": false }
     */
    public function bulkMoveUsers(): void
    {
        $userId = $this->requireSuperAdmin();

        $input = $this->getAllInput();

        $userIds = $input['user_ids'] ?? [];
        $targetTenantId = (int) ($input['target_tenant_id'] ?? 0);
        $grantSuperAdmin = !empty($input['grant_super_admin']);

        if (empty($userIds) || !is_array($userIds)) {
            $this->respondWithError(ApiErrorCodes::VALIDATION_ERROR, 'user_ids array is required', 'user_ids', 422);
        }

        if (!$targetTenantId) {
            $this->respondWithError(ApiErrorCodes::VALIDATION_ERROR, 'target_tenant_id is required', 'target_tenant_id', 422);
        }

        $targetTenant = Tenant::find($targetTenantId);
        if (!$targetTenant) {
            $this->respondWithError(ApiErrorCodes::RESOURCE_NOT_FOUND, 'Target tenant not found', null, 404);
        }

        if ($grantSuperAdmin && !$targetTenant['allows_subtenants']) {
            $this->respondWithError(
                ApiErrorCodes::VALIDATION_ERROR,
                'Cannot grant Super Admin: target tenant is not a Hub',
                null,
                422
            );
        }

        $movedCount = 0;
        $errors = [];

        foreach ($userIds as $uid) {
            $uid = (int) $uid;
            $user = User::findById($uid, false);

            if (!$user) {
                $errors[] = "User ID {$uid} not found";
                continue;
            }

            try {
                if (!User::moveTenant($uid, $targetTenantId)) {
                    $errors[] = "Failed to move user ID {$uid}";
                    continue;
                }

                if ($grantSuperAdmin) {
                    Database::query(
                        "UPDATE users SET is_tenant_super_admin = 1, role = 'tenant_admin' WHERE id = ?",
                        [$uid]
                    );
                } elseif (!$targetTenant['allows_subtenants']) {
                    Database::query("UPDATE users SET is_tenant_super_admin = 0 WHERE id = ?", [$uid]);
                }

                $movedCount++;
            } catch (\Exception $e) {
                $errors[] = "Failed to move user ID {$uid}";
            }
        }

        SuperAdminAuditService::log(
            'bulk_users_moved',
            'bulk',
            null,
            "Bulk move to {$targetTenant['name']}",
            ['user_count' => count($userIds)],
            ['target_tenant_id' => $targetTenantId, 'moved_count' => $movedCount, 'grant_super_admin' => $grantSuperAdmin],
            "Moved {$movedCount} users to '{$targetTenant['name']}'" . ($grantSuperAdmin ? ' with Super Admin privileges' : '')
        );

        $this->respondWithData([
            'moved_count' => $movedCount,
            'total_requested' => count($userIds),
            'errors' => $errors,
        ]);
    }

    /**
     * POST /api/v2/admin/super/bulk/update-tenants
     *
     * Body: { "tenant_ids": [2, 3], "action": "activate"|"deactivate"|"enable_hub"|"disable_hub" }
     */
    public function bulkUpdateTenants(): void
    {
        $userId = $this->requireSuperAdmin();

        $input = $this->getAllInput();

        $tenantIds = $input['tenant_ids'] ?? [];
        $action = $input['action'] ?? '';

        if (empty($tenantIds) || !is_array($tenantIds)) {
            $this->respondWithError(ApiErrorCodes::VALIDATION_ERROR, 'tenant_ids array is required', 'tenant_ids', 422);
        }

        if (!in_array($action, ['activate', 'deactivate', 'enable_hub', 'disable_hub'], true)) {
            $this->respondWithError(ApiErrorCodes::VALIDATION_ERROR, 'action must be one of: activate, deactivate, enable_hub, disable_hub', 'action', 422);
        }

        $updatedCount = 0;
        $errors = [];

        foreach ($tenantIds as $tid) {
            $tid = (int) $tid;

            if ($tid === 1) {
                $errors[] = 'Cannot modify Master tenant';
                continue;
            }

            $tenant = Tenant::find($tid);
            if (!$tenant) {
                $errors[] = "Tenant ID {$tid} not found";
                continue;
            }

            try {
                switch ($action) {
                    case 'activate':
                        Database::query("UPDATE tenants SET is_active = 1 WHERE id = ?", [$tid]);
                        break;
                    case 'deactivate':
                        Database::query("UPDATE tenants SET is_active = 0 WHERE id = ?", [$tid]);
                        break;
                    case 'enable_hub':
                        Database::query("UPDATE tenants SET allows_subtenants = 1, max_depth = 2 WHERE id = ?", [$tid]);
                        break;
                    case 'disable_hub':
                        Database::query("UPDATE tenants SET allows_subtenants = 0, max_depth = 0 WHERE id = ?", [$tid]);
                        break;
                }
                $updatedCount++;
            } catch (\Exception $e) {
                $errors[] = "Failed to update tenant ID {$tid}";
            }
        }

        $actionLabels = [
            'activate' => 'Activated',
            'deactivate' => 'Deactivated',
            'enable_hub' => 'Hub Enabled',
            'disable_hub' => 'Hub Disabled',
        ];

        SuperAdminAuditService::log(
            'bulk_tenants_updated',
            'bulk',
            null,
            "Bulk {$action}",
            ['tenant_count' => count($tenantIds)],
            ['action' => $action, 'updated_count' => $updatedCount],
            "{$actionLabels[$action]} {$updatedCount} tenant(s)"
        );

        $this->respondWithData([
            'updated_count' => $updatedCount,
            'total_requested' => count($tenantIds),
            'action' => $action,
            'errors' => $errors,
        ]);
    }

    // =========================================================================
    // AUDIT
    // =========================================================================

    /**
     * GET /api/v2/admin/super/audit
     */
    public function audit(): void
    {
        $this->requireSuperAdmin();

        $page = $this->queryInt('page', 1, 1);
        $limit = $this->queryInt('limit', 50, 1, 100);

        $filters = array_filter([
            'action_type' => $this->query('action_type'),
            'target_type' => $this->query('target_type'),
            'search' => $this->query('search'),
            'date_from' => $this->query('date_from'),
            'date_to' => $this->query('date_to'),
            'limit' => $limit,
            'offset' => ($page - 1) * $limit,
        ], fn($v) => $v !== null);

        $logs = SuperAdminAuditService::getLog($filters);

        // Map DB columns to frontend field names
        $mapped = array_map(function ($row) {
            return [
                'id' => $row['id'] ?? null,
                'action_type' => $row['action_type'] ?? '',
                'target_type' => $row['target_type'] ?? '',
                'target_id' => $row['target_id'] ?? null,
                'target_label' => $row['target_name'] ?? '',
                'actor_id' => $row['actor_user_id'] ?? null,
                'actor_name' => $row['actor_name'] ?? null,
                'actor_email' => $row['actor_email'] ?? null,
                'old_value' => $row['old_values'] ?? null,
                'new_value' => $row['new_values'] ?? null,
                'description' => $row['description'] ?? '',
                'created_at' => $row['created_at'] ?? '',
            ];
        }, $logs);

        $this->respondWithData($mapped);
    }

    // =========================================================================
    // FEDERATION CONTROLS
    // =========================================================================

    /**
     * GET /api/v2/admin/super/federation
     *
     * Federation overview: system status, partnership stats, whitelist count, recent audit.
     */
    public function federationOverview(): void
    {
        $this->requireSuperAdmin();

        $systemStatus = FederationFeatureService::getSystemControls();
        $partnershipStats = FederationPartnershipService::getStats();
        $whitelistedTenants = FederationFeatureService::getWhitelistedTenants();
        $recentAudit = FederationAuditService::getLog(['limit' => 20]);

        $this->respondWithData([
            'system_status' => $systemStatus,
            'partnership_stats' => $partnershipStats,
            'whitelisted_tenants' => $whitelistedTenants,
            'recent_audit' => $recentAudit,
        ]);
    }

    /**
     * GET /api/v2/admin/super/federation/system-controls
     */
    public function federationGetSystemControls(): void
    {
        $this->requireSuperAdmin();

        $controls = FederationFeatureService::getSystemControls();

        $this->respondWithData($controls);
    }

    /**
     * PUT /api/v2/admin/super/federation/system-controls
     *
     * Body: { "federation_enabled": true, "whitelist_mode_enabled": false, ... }
     */
    public function federationUpdateSystemControls(): void
    {
        $userId = $this->requireSuperAdmin();

        $input = $this->getAllInput();

        $allowedFields = [
            'federation_enabled',
            'whitelist_mode_enabled',
            'max_federation_level',
            'cross_tenant_profiles_enabled',
            'cross_tenant_messaging_enabled',
            'cross_tenant_transactions_enabled',
            'cross_tenant_listings_enabled',
            'cross_tenant_events_enabled',
            'cross_tenant_groups_enabled',
        ];

        $updates = [];
        $params = [];

        foreach ($allowedFields as $field) {
            if (isset($input[$field])) {
                $updates[] = "{$field} = ?";
                if ($field === 'max_federation_level') {
                    $params[] = max(0, min(4, (int) $input[$field]));
                } else {
                    $params[] = $input[$field] ? 1 : 0;
                }
            }
        }

        if (empty($updates)) {
            $this->respondWithError(ApiErrorCodes::VALIDATION_ERROR, 'No valid fields to update', null, 422);
        }

        $updates[] = "updated_at = NOW()";
        $updates[] = "updated_by = ?";
        $params[] = $userId;

        Database::query(
            "UPDATE federation_system_control SET " . implode(', ', $updates) . " WHERE id = 1",
            $params
        );

        FederationFeatureService::clearCache();

        FederationAuditService::log(
            'system_controls_updated',
            null,
            null,
            $userId,
            ['changes' => $input],
            FederationAuditService::LEVEL_WARNING
        );

        $this->respondWithData(['updated' => true]);
    }

    /**
     * POST /api/v2/admin/super/federation/emergency-lockdown
     *
     * Body: { "reason": "..." }
     */
    public function federationEmergencyLockdown(): void
    {
        $userId = $this->requireSuperAdmin();

        $input = $this->getAllInput();
        $reason = $input['reason'] ?? 'Emergency lockdown triggered via API';

        $result = FederationFeatureService::triggerEmergencyLockdown($userId, $reason);

        if ($result) {
            $this->respondWithData(['lockdown' => true, 'message' => 'Emergency lockdown activated']);
        } else {
            $this->respondWithError(ApiErrorCodes::SERVER_INTERNAL_ERROR, 'Failed to activate lockdown', null, 500);
        }
    }

    /**
     * POST /api/v2/admin/super/federation/lift-lockdown
     */
    public function federationLiftLockdown(): void
    {
        $userId = $this->requireSuperAdmin();

        $result = FederationFeatureService::liftEmergencyLockdown($userId);

        if ($result) {
            $this->respondWithData(['lockdown' => false, 'message' => 'Emergency lockdown lifted']);
        } else {
            $this->respondWithError(ApiErrorCodes::SERVER_INTERNAL_ERROR, 'Failed to lift lockdown', null, 500);
        }
    }

    /**
     * GET /api/v2/admin/super/federation/whitelist
     */
    public function federationGetWhitelist(): void
    {
        $this->requireSuperAdmin();

        $whitelisted = FederationFeatureService::getWhitelistedTenants();

        $this->respondWithData($whitelisted);
    }

    /**
     * POST /api/v2/admin/super/federation/whitelist
     *
     * Body: { "tenant_id": 3, "notes": "..." }
     */
    public function federationAddToWhitelist(): void
    {
        $userId = $this->requireSuperAdmin();

        $input = $this->getAllInput();
        $tenantId = (int) ($input['tenant_id'] ?? 0);
        $notes = $input['notes'] ?? null;

        if ($tenantId <= 0) {
            $this->respondWithError(ApiErrorCodes::VALIDATION_ERROR, 'tenant_id is required', 'tenant_id', 422);
        }

        $result = FederationFeatureService::addToWhitelist($tenantId, $userId, $notes);

        if ($result) {
            $this->respondWithData(['added' => true, 'tenant_id' => $tenantId]);
        } else {
            $this->respondWithError(ApiErrorCodes::SERVER_INTERNAL_ERROR, 'Failed to add tenant to whitelist', null, 500);
        }
    }

    /**
     * DELETE /api/v2/admin/super/federation/whitelist/{tenantId}
     */
    public function federationRemoveFromWhitelist(): void
    {
        $userId = $this->requireSuperAdmin();

        // Extract tenant ID from the whitelist URL
        $uri = parse_url($_SERVER['REQUEST_URI'] ?? '', PHP_URL_PATH);
        $tenantId = 0;
        if (preg_match('#/api/v2/admin/super/federation/whitelist/(\d+)#', $uri, $m)) {
            $tenantId = (int) $m[1];
        }

        if ($tenantId <= 0) {
            $this->respondWithError(ApiErrorCodes::VALIDATION_ERROR, 'Invalid tenant ID', 'tenantId', 400);
        }

        $result = FederationFeatureService::removeFromWhitelist($tenantId, $userId);

        if ($result) {
            $this->respondWithData(['removed' => true, 'tenant_id' => $tenantId]);
        } else {
            $this->respondWithError(ApiErrorCodes::SERVER_INTERNAL_ERROR, 'Failed to remove tenant from whitelist', null, 500);
        }
    }

    /**
     * GET /api/v2/admin/super/federation/partnerships
     */
    public function federationPartnerships(): void
    {
        $this->requireSuperAdmin();

        $partnerships = FederationPartnershipService::getAllPartnerships(null, 100);
        $stats = FederationPartnershipService::getStats();

        $this->respondWithData([
            'partnerships' => $partnerships,
            'stats' => $stats,
        ]);
    }

    /**
     * POST /api/v2/admin/super/federation/partnerships/{id}/suspend
     *
     * Body: { "reason": "..." }
     */
    public function federationSuspendPartnership(): void
    {
        $userId = $this->requireSuperAdmin();

        $uri = parse_url($_SERVER['REQUEST_URI'] ?? '', PHP_URL_PATH);
        $partnershipId = 0;
        if (preg_match('#/api/v2/admin/super/federation/partnerships/(\d+)/suspend#', $uri, $m)) {
            $partnershipId = (int) $m[1];
        }

        if ($partnershipId <= 0) {
            $this->respondWithError(ApiErrorCodes::VALIDATION_ERROR, 'Invalid partnership ID', 'id', 400);
        }

        $input = $this->getAllInput();
        $reason = $input['reason'] ?? 'Suspended by super admin via API';

        $result = FederationPartnershipService::suspendPartnership($partnershipId, $userId, $reason);

        if (is_array($result) && !empty($result['success'])) {
            $this->respondWithData(['suspended' => true, 'partnership_id' => $partnershipId]);
        } else {
            $error = is_array($result) ? ($result['error'] ?? 'Failed to suspend partnership') : 'Failed to suspend partnership';
            $this->respondWithError(ApiErrorCodes::SERVER_INTERNAL_ERROR, $error, null, 500);
        }
    }

    /**
     * POST /api/v2/admin/super/federation/partnerships/{id}/terminate
     *
     * Body: { "reason": "..." }
     */
    public function federationTerminatePartnership(): void
    {
        $userId = $this->requireSuperAdmin();

        $uri = parse_url($_SERVER['REQUEST_URI'] ?? '', PHP_URL_PATH);
        $partnershipId = 0;
        if (preg_match('#/api/v2/admin/super/federation/partnerships/(\d+)/terminate#', $uri, $m)) {
            $partnershipId = (int) $m[1];
        }

        if ($partnershipId <= 0) {
            $this->respondWithError(ApiErrorCodes::VALIDATION_ERROR, 'Invalid partnership ID', 'id', 400);
        }

        $input = $this->getAllInput();
        $reason = $input['reason'] ?? 'Terminated by super admin via API';

        $result = FederationPartnershipService::terminatePartnership($partnershipId, $userId, $reason);

        if (is_array($result) && !empty($result['success'])) {
            $this->respondWithData(['terminated' => true, 'partnership_id' => $partnershipId]);
        } else {
            $error = is_array($result) ? ($result['error'] ?? 'Failed to terminate partnership') : 'Failed to terminate partnership';
            $this->respondWithError(ApiErrorCodes::SERVER_INTERNAL_ERROR, $error, null, 500);
        }
    }

    /**
     * GET /api/v2/admin/super/federation/tenant/{id}/features
     */
    public function federationGetTenantFeatures(): void
    {
        $this->requireSuperAdmin();

        $uri = parse_url($_SERVER['REQUEST_URI'] ?? '', PHP_URL_PATH);
        $tenantId = 0;
        if (preg_match('#/api/v2/admin/super/federation/tenant/(\d+)/features#', $uri, $m)) {
            $tenantId = (int) $m[1];
        }

        if ($tenantId <= 0) {
            $this->respondWithError(ApiErrorCodes::VALIDATION_ERROR, 'Invalid tenant ID', 'id', 400);
        }

        $tenant = Tenant::find($tenantId);
        if (!$tenant) {
            $this->respondWithError(ApiErrorCodes::RESOURCE_NOT_FOUND, 'Tenant not found', null, 404);
        }

        $features = FederationFeatureService::getAllTenantFeatures($tenantId);
        $isWhitelisted = FederationFeatureService::isTenantWhitelisted($tenantId);
        $partnerships = FederationPartnershipService::getTenantPartnerships($tenantId);

        $this->respondWithData([
            'tenant' => $tenant,
            'features' => $features,
            'is_whitelisted' => $isWhitelisted,
            'partnerships' => $partnerships,
        ]);
    }

    /**
     * PUT /api/v2/admin/super/federation/tenant/{id}/features
     *
     * Body: { "feature": "cross_tenant_messaging", "enabled": true }
     */
    public function federationUpdateTenantFeature(): void
    {
        $this->requireSuperAdmin();

        $uri = parse_url($_SERVER['REQUEST_URI'] ?? '', PHP_URL_PATH);
        $tenantId = 0;
        if (preg_match('#/api/v2/admin/super/federation/tenant/(\d+)/features#', $uri, $m)) {
            $tenantId = (int) $m[1];
        }

        if ($tenantId <= 0) {
            $this->respondWithError(ApiErrorCodes::VALIDATION_ERROR, 'Invalid tenant ID', 'id', 400);
        }

        $input = $this->getAllInput();
        $feature = $input['feature'] ?? '';
        $enabled = (bool) ($input['enabled'] ?? false);

        if (empty($feature)) {
            $this->respondWithError(ApiErrorCodes::VALIDATION_ERROR, 'feature is required', 'feature', 422);
        }

        if ($enabled) {
            $result = FederationFeatureService::enableTenantFeature($feature, $tenantId);
        } else {
            $result = FederationFeatureService::disableTenantFeature($feature, $tenantId);
        }

        if ($result) {
            $this->respondWithData([
                'updated' => true,
                'tenant_id' => $tenantId,
                'feature' => $feature,
                'enabled' => $enabled,
            ]);
        } else {
            $this->respondWithError(ApiErrorCodes::SERVER_INTERNAL_ERROR, 'Failed to update feature', null, 500);
        }
    }
}

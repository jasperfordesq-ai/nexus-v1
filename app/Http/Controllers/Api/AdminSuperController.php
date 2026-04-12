<?php
// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

namespace App\Http\Controllers\Api;

use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;
use App\Core\ApiErrorCodes;
use App\Core\Validator;
use App\Core\SuperPanelAccess;
use App\Models\Tenant;
use App\Models\User;
use App\Services\FederationAuditService;
use App\Services\FederationFeatureService;
use App\Services\FederationPartnershipService;
use App\Services\SuperAdminAuditService;
use App\Services\TenantHierarchyService;
use App\Services\TenantVisibilityService;

/**
 * AdminSuperController -- Super-admin tenant and cross-tenant user management.
 *
 * This controller manages ~38 endpoints for the super admin panel including:
 * - Dashboard stats across all tenants
 * - Tenant CRUD (create, list, edit, delete, reactivate, toggle-hub, move, hierarchy)
 * - Cross-tenant user management (create, edit, super admin grant/revoke, move tenant)
 * - Bulk operations (move users, update tenants)
 * - Audit log
 * - Federation system controls (lockdown, whitelist, partnerships, tenant features)
 *
 * All methods are native Laravel — no legacy delegation remains.
 */
class AdminSuperController extends BaseApiController
{
    protected bool $isV2Api = true;

    public function __construct(
        private readonly FederationAuditService $federationAuditService,
        private readonly FederationFeatureService $federationFeatureService,
        private readonly FederationPartnershipService $federationPartnershipService,
        private readonly SuperAdminAuditService $superAdminAuditService,
        private readonly TenantHierarchyService $tenantHierarchyService,
        private readonly TenantVisibilityService $tenantVisibilityService,
        private readonly \App\Services\TenantSettingsService $tenantSettingsService,
    ) {}

    // ─────────────────────────────────────────────────────────────────────────
    // Helpers
    // ─────────────────────────────────────────────────────────────────────────

    /**
     * Override requireSuperAdmin for stateless JWT API auth.
     *
     * Passes the JWT user ID directly to SuperPanelAccess::getAccess($userId),
     * completely bypassing $_SESSION dependency. This is critical because
     * session_start() can fail silently in containerized/Docker environments,
     * causing all super admin API calls to return 403.
     *
     * Also syncs to $_SESSION as a fallback for any code that still reads it.
     */
    protected function requireSuperAdmin(): int
    {
        $userId = parent::requireSuperAdmin();

        // Reset any stale cached access from earlier in this request
        SuperPanelAccess::reset();

        // Pass user ID directly — no session dependency
        $access = SuperPanelAccess::getAccess($userId);

        if (!$access['granted']) {
            throw new \Illuminate\Http\Exceptions\HttpResponseException(
                $this->respondWithError(ApiErrorCodes::SUPER_PANEL_ACCESS_DENIED, __('api.super_panel_access_denied', ['reason' => $access['reason'] ?? __('api.unknown_reason')]),
                    null,
                    403
                )
            );
        }

        // Best-effort session sync for any legacy code that reads session data
        try {
            $session = request()->session();
            $session->put('user_id', $userId);
            $session->put('tenant_id', $access['tenant_id']);
            $session->put('user_role', 'admin');
            $session->put('is_super_admin', 1);
            $session->put('is_tenant_super_admin', 1);
        } catch (\Throwable $e) {
            // Session sync is best-effort — API works without it
        }

        return $userId;
    }

    /**
     * Require god role (is_god flag in DB). Uses the authenticated user ID from JWT.
     */
    private function requireGod(int $userId): void
    {
        if (!User::isGod($userId)) {
            throw new \Illuminate\Http\Exceptions\HttpResponseException(
                $this->respondWithError(
                    ApiErrorCodes::AUTH_INSUFFICIENT_PERMISSIONS,
                    __('api.god_level_access_required'),
                    null,
                    403
                )
            );
        }
    }

    // ─────────────────────────────────────────────────────────────────────────
    // Dashboard
    // ─────────────────────────────────────────────────────────────────────────

    /** GET /api/v2/super-admin/dashboard */
    public function dashboard(): JsonResponse
    {
        $this->requireSuperAdmin();

        $stats = $this->tenantVisibilityService->getDashboardStats();

        return $this->respondWithData($stats);
    }

    // ─────────────────────────────────────────────────────────────────────────
    // Tenant Management
    // ─────────────────────────────────────────────────────────────────────────

    /** GET /api/v2/super-admin/tenants */
    public function tenantList(): JsonResponse
    {
        $this->requireSuperAdmin();

        $filters = array_filter([
            'search' => $this->query('search'),
            'is_active' => $this->query('is_active') !== null ? (int) $this->query('is_active') : null,
            'allows_subtenants' => $this->query('hub') !== null ? 1 : null,
        ], fn($v) => $v !== null);

        $tenants = $this->tenantVisibilityService->getTenantList($filters);

        return $this->respondWithData($tenants);
    }

    /** GET /api/v2/super-admin/tenants/hierarchy */
    public function tenantHierarchy(): JsonResponse
    {
        $this->requireSuperAdmin();

        $tree = $this->tenantVisibilityService->getHierarchyTree();

        return $this->respondWithData($tree);
    }

    /** GET /api/v2/super-admin/tenants/{id} */
    public function tenantShow(int $id): JsonResponse
    {
        $this->requireSuperAdmin();

        $tenant = $this->tenantVisibilityService->getTenant($id);
        if (!$tenant) {
            return $this->respondWithError(ApiErrorCodes::RESOURCE_NOT_FOUND, __('api.tenant_not_found'), null, 404);
        }

        $children = Tenant::getChildren($id);
        $admins = $this->tenantVisibilityService->getTenantAdmins($id);
        $breadcrumb = Tenant::getBreadcrumb($id);

        // Decode features JSON for the frontend
        $features = [];
        if (!empty($tenant['features'])) {
            $features = is_string($tenant['features'])
                ? (json_decode($tenant['features'], true) ?: [])
                : $tenant['features'];
        }
        unset($tenant['features']);

        // Decode configuration JSON for the frontend
        $configuration = [];
        if (!empty($tenant['configuration'])) {
            $configuration = is_string($tenant['configuration'])
                ? (json_decode($tenant['configuration'], true) ?: [])
                : $tenant['configuration'];
        }
        unset($tenant['configuration']);

        return $this->respondWithData(array_merge($tenant, [
            'features' => $features,
            'configuration' => $configuration,
            'children' => $children,
            'admins' => $admins,
            'breadcrumb' => $breadcrumb,
        ]));
    }

    /** POST /api/v2/super-admin/tenants */
    public function tenantCreate(): JsonResponse
    {
        $userId = $this->requireSuperAdmin();

        $input = $this->getAllInput();

        $parentId = (int) ($input['parent_id'] ?? 0);
        if (!$parentId) {
            // Default to Master tenant (ID 1) for god/master-level admins
            $access = SuperPanelAccess::getAccess();
            if ($access['level'] === 'master') {
                $parentId = 1;
            } else {
                // Regional admins must specify their own tenant as parent
                $parentId = $access['tenant_id'] ?? 0;
            }
            if (!$parentId) {
                return $this->respondWithError(ApiErrorCodes::VALIDATION_ERROR, __('api.parent_id_required'), 'parent_id', 422);
            }
        }

        // Verify access to parent tenant regardless of source
        if (!SuperPanelAccess::canAccessTenant($parentId)) {
            return $this->respondWithError(ApiErrorCodes::SUPER_PANEL_ACCESS_DENIED, __('api.super_no_access_parent_tenant'), null, 403);
        }

        $name = trim($input['name'] ?? '');
        if (empty($name)) {
            return $this->respondWithError(ApiErrorCodes::VALIDATION_ERROR, __('api.name_is_required'), 'name', 422);
        }

        // Validate slug format (alphanumeric + hyphens only, no leading/trailing hyphens)
        $slug = trim($input['slug'] ?? '');
        if ($slug !== '' && !preg_match('/^[a-z0-9][a-z0-9-]*[a-z0-9]$|^[a-z0-9]$/', $slug)) {
            return $this->respondWithError(ApiErrorCodes::VALIDATION_ERROR, __('api.slug_format_invalid'), 'slug', 422);
        }

        // Validate domain format if provided
        $domain = trim($input['domain'] ?? '');
        if ($domain !== '' && !preg_match('/^[a-z0-9]([a-z0-9-]*[a-z0-9])?(\.[a-z0-9]([a-z0-9-]*[a-z0-9])?)+$/i', $domain)) {
            return $this->respondWithError(ApiErrorCodes::VALIDATION_ERROR, __('api.domain_format_invalid'), 'domain', 422);
        }

        // Validate contact email if provided
        $contactEmail = trim($input['contact_email'] ?? '');
        if ($contactEmail !== '' && !filter_var($contactEmail, FILTER_VALIDATE_EMAIL)) {
            return $this->respondWithError(ApiErrorCodes::VALIDATION_ERROR, __('api.contact_email_invalid'), 'contact_email', 422);
        }

        // Validate contact phone if provided (international E.164 format)
        $contactPhone = trim($input['contact_phone'] ?? '');
        if ($contactPhone !== '' && !Validator::isPhone($contactPhone)) {
            return $this->respondWithError(ApiErrorCodes::VALIDATION_ERROR, __('api.contact_phone_invalid'), 'contact_phone', 422);
        }

        // Validate latitude/longitude if provided
        $latitude = $input['latitude'] ?? '';
        $longitude = $input['longitude'] ?? '';
        if ($latitude !== '' && (float)$latitude !== 0.0 && ((float)$latitude < -90 || (float)$latitude > 90)) {
            return $this->respondWithError(ApiErrorCodes::VALIDATION_ERROR, __('api.latitude_range'), 'latitude', 422);
        }
        if ($longitude !== '' && (float)$longitude !== 0.0 && ((float)$longitude < -180 || (float)$longitude > 180)) {
            return $this->respondWithError(ApiErrorCodes::VALIDATION_ERROR, __('api.longitude_range'), 'longitude', 422);
        }

        $data = [
            'name' => $name,
            'slug' => $slug,
            'domain' => $domain,
            'tagline' => $input['tagline'] ?? '',
            'description' => $input['description'] ?? '',
            'allows_subtenants' => !empty($input['allows_subtenants']),
            'max_depth' => (int) ($input['max_depth'] ?? 2),
            'is_active' => isset($input['is_active']) ? (int) (bool) $input['is_active'] : 1,
            'features' => $input['features'] ?? null,
            'contact_email' => $contactEmail,
            'contact_phone' => $contactPhone,
            'address' => $input['address'] ?? '',
            // SEO fields
            'meta_title' => $input['meta_title'] ?? '',
            'meta_description' => $input['meta_description'] ?? '',
            'h1_headline' => $input['h1_headline'] ?? '',
            'hero_intro' => $input['hero_intro'] ?? '',
            'og_image_url' => $input['og_image_url'] ?? '',
            'robots_directive' => $input['robots_directive'] ?? '',
            // Location fields
            'location_name' => $input['location_name'] ?? '',
            'country_code' => $input['country_code'] ?? '',
            'service_area' => $input['service_area'] ?? '',
            'latitude' => $latitude,
            'longitude' => $longitude,
            // Social media fields
            'social_facebook' => $input['social_facebook'] ?? '',
            'social_twitter' => $input['social_twitter'] ?? '',
            'social_instagram' => $input['social_instagram'] ?? '',
            'social_linkedin' => $input['social_linkedin'] ?? '',
            'social_youtube' => $input['social_youtube'] ?? '',
            // Configuration JSON (languages, etc.)
            'configuration' => $input['configuration'] ?? null,
        ];

        $result = $this->tenantHierarchyService->createTenant($data, $parentId);

        if ($result['success']) {
            return $this->respondWithData([
                'tenant_id' => $result['tenant_id'],
                'message' => __('api_controllers_1.admin_super.tenant_created', ['name' => $name]),
            ], null, 201);
        }

        return $this->respondWithError(ApiErrorCodes::VALIDATION_ERROR, $result['error'], null, 422);
    }

    /** PUT /api/v2/super-admin/tenants/{id} */
    public function tenantUpdate(int $id): JsonResponse
    {
        $this->requireSuperAdmin();

        if (!SuperPanelAccess::canAccessTenant($id)) {
            return $this->respondWithError(ApiErrorCodes::SUPER_PANEL_ACCESS_DENIED, __('api.super_no_access_tenant'), null, 403);
        }

        $input = $this->getAllInput();

        if (empty($input)) {
            return $this->respondWithError(ApiErrorCodes::VALIDATION_ERROR, __('api.request_body_empty'), null, 422);
        }

        // Validate slug format if being updated
        if (isset($input['slug']) && trim($input['slug']) !== '') {
            $slug = trim($input['slug']);
            if (!preg_match('/^[a-z0-9][a-z0-9-]*[a-z0-9]$|^[a-z0-9]$/', $slug)) {
                return $this->respondWithError(ApiErrorCodes::VALIDATION_ERROR, __('api.slug_format_invalid'), 'slug', 422);
            }
        }

        // Validate domain format if being updated
        if (isset($input['domain']) && trim($input['domain']) !== '') {
            $domain = trim($input['domain']);
            if (!preg_match('/^[a-z0-9]([a-z0-9-]*[a-z0-9])?(\.[a-z0-9]([a-z0-9-]*[a-z0-9])?)+$/i', $domain)) {
                return $this->respondWithError(ApiErrorCodes::VALIDATION_ERROR, __('api.domain_format_invalid'), 'domain', 422);
            }
        }

        // Validate contact email if being updated
        if (isset($input['contact_email']) && trim($input['contact_email']) !== '') {
            if (!filter_var(trim($input['contact_email']), FILTER_VALIDATE_EMAIL)) {
                return $this->respondWithError(ApiErrorCodes::VALIDATION_ERROR, __('api.contact_email_invalid'), 'contact_email', 422);
            }
        }

        // Validate contact phone if being updated (international E.164 format)
        if (isset($input['contact_phone']) && trim($input['contact_phone']) !== '') {
            if (!Validator::isPhone(trim($input['contact_phone']))) {
                return $this->respondWithError(ApiErrorCodes::VALIDATION_ERROR, __('api.contact_phone_invalid'), 'contact_phone', 422);
            }
        }

        // Validate latitude/longitude if being updated
        if (isset($input['latitude']) && $input['latitude'] !== '' && (float)$input['latitude'] !== 0.0) {
            if ((float)$input['latitude'] < -90 || (float)$input['latitude'] > 90) {
                return $this->respondWithError(ApiErrorCodes::VALIDATION_ERROR, __('api.latitude_range'), 'latitude', 422);
            }
        }
        if (isset($input['longitude']) && $input['longitude'] !== '' && (float)$input['longitude'] !== 0.0) {
            if ((float)$input['longitude'] < -180 || (float)$input['longitude'] > 180) {
                return $this->respondWithError(ApiErrorCodes::VALIDATION_ERROR, __('api.longitude_range'), 'longitude', 422);
            }
        }

        $result = $this->tenantHierarchyService->updateTenant($id, $input);

        if ($result['success']) {
            return $this->respondWithData(['updated' => true, 'tenant_id' => $id]);
        }

        return $this->respondWithError(ApiErrorCodes::VALIDATION_ERROR, $result['error'], null, 422);
    }

    /** DELETE /api/v2/super-admin/tenants/{id} */
    public function tenantDelete(int $id): JsonResponse
    {
        $userId = $this->requireSuperAdmin();

        if (!SuperPanelAccess::canAccessTenant($id)) {
            return $this->respondWithError(ApiErrorCodes::SUPER_PANEL_ACCESS_DENIED, __('api.super_no_access_tenant'), null, 403);
        }

        $input = $this->getAllInput();
        $hardDelete = !empty($input['hard_delete']);

        // Hard delete is destructive and can orphan data — restrict to god-level only
        if ($hardDelete) {
            $this->requireGod($userId);
        }

        $result = $this->tenantHierarchyService->deleteTenant($id, $hardDelete);

        if ($result['success']) {
            return $this->respondWithData(['deleted' => true, 'tenant_id' => $id]);
        }

        return $this->respondWithError(ApiErrorCodes::VALIDATION_ERROR, $result['error'], null, 422);
    }

    /** POST /api/v2/super-admin/tenants/{id}/reactivate */
    public function tenantReactivate(int $id): JsonResponse
    {
        $this->requireSuperAdmin();

        if (!SuperPanelAccess::canAccessTenant($id)) {
            return $this->respondWithError(ApiErrorCodes::SUPER_PANEL_ACCESS_DENIED, __('api.super_no_access_tenant'), null, 403);
        }

        $result = $this->tenantHierarchyService->updateTenant($id, ['is_active' => 1]);

        if ($result['success']) {
            return $this->respondWithData(['reactivated' => true, 'tenant_id' => $id]);
        }

        return $this->respondWithError(ApiErrorCodes::VALIDATION_ERROR, $result['error'], null, 422);
    }

    /** POST /api/v2/super-admin/tenants/{id}/toggle-hub */
    public function tenantToggleHub(int $id): JsonResponse
    {
        $this->requireSuperAdmin();

        if (!SuperPanelAccess::canAccessTenant($id)) {
            return $this->respondWithError(ApiErrorCodes::SUPER_PANEL_ACCESS_DENIED, __('api.super_no_access_tenant'), null, 403);
        }

        $input = $this->getAllInput();
        $enable = !empty($input['enable']);

        $result = $this->tenantHierarchyService->toggleSubtenantCapability($id, $enable);

        if ($result['success']) {
            return $this->respondWithData([
                'tenant_id' => $id,
                'hub_enabled' => $enable,
            ]);
        }

        return $this->respondWithError(ApiErrorCodes::VALIDATION_ERROR, $result['error'], null, 422);
    }

    /** POST /api/v2/super-admin/tenants/{id}/move */
    public function tenantMove(int $id): JsonResponse
    {
        $this->requireSuperAdmin();

        if (!SuperPanelAccess::canAccessTenant($id)) {
            return $this->respondWithError(ApiErrorCodes::SUPER_PANEL_ACCESS_DENIED, __('api.super_no_access_tenant'), null, 403);
        }

        $input = $this->getAllInput();
        $newParentId = (int) ($input['new_parent_id'] ?? 0);

        if (!$newParentId) {
            return $this->respondWithError(ApiErrorCodes::VALIDATION_ERROR, __('api.new_parent_id_required'), 'new_parent_id', 422);
        }

        if (!SuperPanelAccess::canAccessTenant($newParentId)) {
            return $this->respondWithError(ApiErrorCodes::SUPER_PANEL_ACCESS_DENIED, __('api.super_no_access_destination'), null, 403);
        }

        $result = $this->tenantHierarchyService->moveTenant($id, $newParentId);

        if ($result['success']) {
            return $this->respondWithData([
                'moved' => true,
                'tenant_id' => $id,
                'new_parent_id' => $newParentId,
            ]);
        }

        return $this->respondWithError(ApiErrorCodes::VALIDATION_ERROR, $result['error'], null, 422);
    }

    // ─────────────────────────────────────────────────────────────────────────
    // User Management
    // ─────────────────────────────────────────────────────────────────────────

    /** GET /api/v2/super-admin/users */
    public function userList(): JsonResponse
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

        $users = $this->tenantVisibilityService->getUserList($filters);

        return $this->respondWithData($users);
    }

    /** GET /api/v2/super-admin/users/{id} */
    public function userShow(int $id): JsonResponse
    {
        $this->requireSuperAdmin();

        // Super-admin: cross-tenant by design — tenant scope enforced via SuperPanelAccess::canAccessTenant() below
        $user = User::findById($id, false);

        if (!$user) {
            return $this->respondWithError(ApiErrorCodes::RESOURCE_NOT_FOUND, __('api.user_not_found'), null, 404);
        }

        if (!SuperPanelAccess::canAccessTenant((int) $user['tenant_id'])) {
            return $this->respondWithError(ApiErrorCodes::SUPER_PANEL_ACCESS_DENIED, __('api.super_no_access_user_tenant'), null, 403);
        }

        $tenant = Tenant::find($user['tenant_id']);

        return $this->respondWithData(array_merge($user, [
            'tenant_name' => $tenant['name'] ?? null,
            'name' => trim(($user['first_name'] ?? '') . ' ' . ($user['last_name'] ?? '')),
        ]));
    }

    /** POST /api/v2/super-admin/users */
    public function userCreate(): JsonResponse
    {
        $userId = $this->requireSuperAdmin();

        $input = $this->getAllInput();

        $tenantId = (int) ($input['tenant_id'] ?? 0);
        if (!$tenantId) {
            return $this->respondWithError(ApiErrorCodes::VALIDATION_ERROR, __('api.tenant_id_is_required'), 'tenant_id', 422);
        }

        if (!SuperPanelAccess::canAccessTenant($tenantId)) {
            return $this->respondWithError(ApiErrorCodes::SUPER_PANEL_ACCESS_DENIED, __('api.super_no_access_tenant'), null, 403);
        }

        $firstName = trim($input['first_name'] ?? '');
        $lastName = trim($input['last_name'] ?? '');
        $email = trim($input['email'] ?? '');
        $password = $input['password'] ?? '';
        $role = $input['role'] ?? 'member';

        if (empty($firstName) || empty($email) || empty($password)) {
            return $this->respondWithError(ApiErrorCodes::VALIDATION_ERROR, __('api.super_user_create_required_fields'), null, 422);
        }

        $allowedRoles = ['member', 'admin', 'tenant_admin', 'broker', 'super_admin'];
        if (!in_array($role, $allowedRoles, true)) {
            return $this->respondWithError(ApiErrorCodes::VALIDATION_ERROR, __('api.invalid_role_allowed', ['roles' => implode(', ', $allowedRoles)]), 'role', 422);
        }

        // Check email uniqueness
        $existing = User::findGlobalByEmail($email);
        if ($existing) {
            return $this->respondWithError(ApiErrorCodes::VALIDATION_ERROR, __('api.email_already_exists_system'), 'email', 422);
        }

        $options = [
            'location' => $input['location'] ?? null,
            'phone' => $input['phone'] ?? null,
            'is_approved' => 1,
            'is_tenant_super_admin' => !empty($input['is_tenant_super_admin']) ? 1 : 0,
        ];

        $newUserId = User::createWithTenant($tenantId, $firstName, $lastName, $email, $password, $role, $options);

        if ($newUserId) {
            $this->superAdminAuditService->log(
                'user_created',
                'user',
                $newUserId,
                "{$firstName} {$lastName}",
                null,
                ['tenant_id' => $tenantId, 'email' => $email, 'role' => $role],
                "Created user '{$firstName} {$lastName}' in tenant ID {$tenantId}"
            );

            return $this->respondWithData(['user_id' => $newUserId], null, 201);
        }

        return $this->respondWithError(ApiErrorCodes::SERVER_INTERNAL_ERROR, __('api.user_created_failed'), null, 500);
    }

    /** PUT /api/v2/super-admin/users/{id} */
    public function userUpdate(int $id): JsonResponse
    {
        $this->requireSuperAdmin();

        // Super-admin: cross-tenant by design — tenant scope enforced via SuperPanelAccess::canAccessTenant() below
        $user = User::findById($id, false);

        if (!$user) {
            return $this->respondWithError(ApiErrorCodes::RESOURCE_NOT_FOUND, __('api.user_not_found'), null, 404);
        }

        if (!SuperPanelAccess::canAccessTenant((int) $user['tenant_id'])) {
            return $this->respondWithError(ApiErrorCodes::SUPER_PANEL_ACCESS_DENIED, __('api.super_no_access_user_tenant'), null, 403);
        }

        $input = $this->getAllInput();

        $firstName = trim($input['first_name'] ?? $user['first_name']);
        $lastName = trim($input['last_name'] ?? $user['last_name'] ?? '');
        $email = trim($input['email'] ?? $user['email']);
        $role = $input['role'] ?? $user['role'];
        $location = isset($input['location']) ? trim($input['location']) : ($user['location'] ?? null);
        $phone = isset($input['phone']) ? trim($input['phone']) : ($user['phone'] ?? null);

        if (empty($firstName) || empty($email)) {
            return $this->respondWithError(ApiErrorCodes::VALIDATION_ERROR, __('api.super_user_edit_required_fields'), null, 422);
        }

        $allowedRoles = ['member', 'admin', 'tenant_admin', 'broker', 'super_admin'];
        if (!in_array($role, $allowedRoles, true)) {
            return $this->respondWithError(ApiErrorCodes::VALIDATION_ERROR, __('api.invalid_role_allowed', ['roles' => implode(', ', $allowedRoles)]), 'role', 422);
        }

        // Check email uniqueness if changed
        if ($email !== $user['email']) {
            $existing = User::findGlobalByEmail($email);
            if ($existing && (int) $existing['id'] !== $id) {
                return $this->respondWithError(ApiErrorCodes::VALIDATION_ERROR, __('api.email_already_exists'), 'email', 422);
            }
        }

        DB::update(
            "UPDATE users SET first_name = ?, last_name = ?, email = ?, role = ?, location = ?, phone = ?, updated_at = NOW() WHERE id = ?",
            [$firstName, $lastName, $email, $role, $location ?: null, $phone ?: null, $id]
        );

        return $this->respondWithData(['updated' => true, 'user_id' => $id]);
    }

    /** POST /api/v2/super-admin/users/{id}/grant-super */
    public function userGrantSuperAdmin(int $id): JsonResponse
    {
        $this->requireSuperAdmin();

        // Super-admin: cross-tenant by design — tenant scope enforced via SuperPanelAccess::canAccessTenant() below
        $user = User::findById($id, false);

        if (!$user) {
            return $this->respondWithError(ApiErrorCodes::RESOURCE_NOT_FOUND, __('api.user_not_found'), null, 404);
        }

        if (!SuperPanelAccess::canAccessTenant((int) $user['tenant_id'])) {
            return $this->respondWithError(ApiErrorCodes::SUPER_PANEL_ACCESS_DENIED, __('api.super_no_access_user_tenant'), null, 403);
        }

        // Tenant must allow sub-tenants
        $tenant = Tenant::find($user['tenant_id']);
        if (!$tenant || !$tenant['allows_subtenants']) {
            return $this->respondWithError(
                ApiErrorCodes::VALIDATION_ERROR,
                __('api.super_grant_denied_not_hub'),
                null,
                422
            );
        }

        $result = $this->tenantHierarchyService->assignTenantSuperAdmin($id, (int) $user['tenant_id']);

        if ($result['success']) {
            return $this->respondWithData(['granted' => true, 'user_id' => $id]);
        }

        return $this->respondWithError(ApiErrorCodes::VALIDATION_ERROR, $result['error'], null, 422);
    }

    /** POST /api/v2/super-admin/users/{id}/revoke-super */
    public function userRevokeSuperAdmin(int $id): JsonResponse
    {
        $userId = $this->requireSuperAdmin();

        // Super-admin: cross-tenant by design — tenant scope enforced via SuperPanelAccess::canAccessTenant() below
        $user = User::findById($id, false);

        if (!$user) {
            return $this->respondWithError(ApiErrorCodes::RESOURCE_NOT_FOUND, __('api.user_not_found'), null, 404);
        }

        if (!SuperPanelAccess::canAccessTenant((int) $user['tenant_id'])) {
            return $this->respondWithError(ApiErrorCodes::SUPER_PANEL_ACCESS_DENIED, __('api.super_no_access_user_tenant'), null, 403);
        }

        // Cannot revoke from a global super admin unless caller is god
        if (!empty($user['is_super_admin']) && !User::isGod($userId)) {
            return $this->respondWithError(
                ApiErrorCodes::AUTH_INSUFFICIENT_PERMISSIONS,
                __('api.super_revoke_denied_global'),
                null,
                403
            );
        }

        $result = $this->tenantHierarchyService->revokeTenantSuperAdmin($id);

        if ($result['success']) {
            return $this->respondWithData(['revoked' => true, 'user_id' => $id]);
        }

        return $this->respondWithError(ApiErrorCodes::VALIDATION_ERROR, $result['error'], null, 422);
    }

    /** POST /api/v2/super-admin/users/{id}/grant-global */
    public function userGrantGlobalSuperAdmin(int $id): JsonResponse
    {
        $userId = $this->requireSuperAdmin();
        $this->requireGod($userId);

        // Super-admin: cross-tenant by design — restricted to "god" users via requireGod() above
        $user = User::findById($id, false);

        if (!$user) {
            return $this->respondWithError(ApiErrorCodes::RESOURCE_NOT_FOUND, __('api.user_not_found'), null, 404);
        }

        DB::update(
            "UPDATE users SET is_super_admin = 1, role = CASE WHEN role = 'member' THEN 'admin' ELSE role END WHERE id = ?",
            [$id]
        );

        SuperAdminAuditService::log(
            'global_super_admin_granted',
            'user',
            $id,
            ($user['first_name'] ?? '') . ' ' . ($user['last_name'] ?? ''),
            ['is_super_admin' => 0],
            ['is_super_admin' => 1],
            "Granted global super admin to user #{$id}"
        );

        return $this->respondWithData(['granted' => true, 'user_id' => $id, 'level' => 'global']);
    }

    /** POST /api/v2/super-admin/users/{id}/revoke-global */
    public function userRevokeGlobalSuperAdmin(int $id): JsonResponse
    {
        $userId = $this->requireSuperAdmin();
        $this->requireGod($userId);

        // Super-admin: cross-tenant by design — restricted to "god" users via requireGod() above
        $user = User::findById($id, false);

        if (!$user) {
            return $this->respondWithError(ApiErrorCodes::RESOURCE_NOT_FOUND, __('api.user_not_found'), null, 404);
        }

        // Cannot revoke from yourself
        if ($id === $userId) {
            return $this->respondWithError(
                ApiErrorCodes::VALIDATION_ERROR,
                __('api.super_cannot_revoke_self'),
                null,
                422
            );
        }

        DB::update("UPDATE users SET is_super_admin = 0 WHERE id = ?", [$id]);

        SuperAdminAuditService::log(
            'global_super_admin_revoked',
            'user',
            $id,
            ($user['first_name'] ?? '') . ' ' . ($user['last_name'] ?? ''),
            ['is_super_admin' => 1],
            ['is_super_admin' => 0],
            "Revoked global super admin from user #{$id}"
        );

        return $this->respondWithData(['revoked' => true, 'user_id' => $id, 'level' => 'global']);
    }

    /** POST /api/v2/super-admin/users/{id}/move-tenant */
    public function userMoveTenant(int $id): JsonResponse
    {
        $userId = $this->requireSuperAdmin();

        // Super-admin: cross-tenant by design — tenant scope enforced via SuperPanelAccess::canAccessTenant() below (both source and destination tenants)
        $user = User::findById($id, false);

        if (!$user) {
            return $this->respondWithError(ApiErrorCodes::RESOURCE_NOT_FOUND, __('api.user_not_found'), null, 404);
        }

        if (!SuperPanelAccess::canAccessTenant((int) $user['tenant_id'])) {
            return $this->respondWithError(ApiErrorCodes::SUPER_PANEL_ACCESS_DENIED, __('api.super_no_access_user_source_tenant'), null, 403);
        }

        $input = $this->getAllInput();
        $newTenantId = (int) ($input['new_tenant_id'] ?? 0);

        if (!$newTenantId) {
            return $this->respondWithError(ApiErrorCodes::VALIDATION_ERROR, __('api.new_tenant_id_required'), 'new_tenant_id', 422);
        }

        if (!SuperPanelAccess::canAccessTenant($newTenantId)) {
            return $this->respondWithError(ApiErrorCodes::SUPER_PANEL_ACCESS_DENIED, __('api.super_no_access_destination'), null, 403);
        }

        $oldTenantId = $user['tenant_id'];

        $moveResult = User::moveTenant($id, $newTenantId);
        if (!$moveResult['success']) {
            return $this->respondWithError(ApiErrorCodes::SERVER_INTERNAL_ERROR, __('api.super_move_user_failed'), null, 500);
        }

        // Revoke super admin if moving to a tenant without sub-tenant capability
        $newTenant = Tenant::find($newTenantId);
        if ($newTenant && !$newTenant['allows_subtenants']) {
            DB::update("UPDATE users SET is_tenant_super_admin = 0 WHERE id = ?", [$id]);
        }

        // Audit
        $userName = trim(($user['first_name'] ?? '') . ' ' . ($user['last_name'] ?? '')) ?: $user['email'];
        $this->superAdminAuditService->log(
            'user_moved',
            'user',
            $id,
            $userName,
            ['tenant_id' => $oldTenantId],
            ['tenant_id' => $newTenantId],
            "Moved '{$userName}' to tenant '{$newTenant['name']}' (with all content)"
        );

        return $this->respondWithData([
            'moved' => true,
            'user_id' => $id,
            'old_tenant_id' => $oldTenantId,
            'new_tenant_id' => $newTenantId,
            'records_moved' => $moveResult['moved'],
            'tables_failed' => $moveResult['failed'],
        ]);
    }

    /** POST /api/v2/super-admin/users/{id}/move-promote */
    public function userMoveAndPromote(int $id): JsonResponse
    {
        $userId = $this->requireSuperAdmin();

        $user = User::findById($id, false);

        if (!$user) {
            return $this->respondWithError(ApiErrorCodes::RESOURCE_NOT_FOUND, __('api.user_not_found'), null, 404);
        }

        if (!SuperPanelAccess::canAccessTenant((int) $user['tenant_id'])) {
            return $this->respondWithError(ApiErrorCodes::SUPER_PANEL_ACCESS_DENIED, __('api.super_no_access_user_source_tenant'), null, 403);
        }

        $input = $this->getAllInput();
        $targetTenantId = (int) ($input['target_tenant_id'] ?? 0);

        if (!$targetTenantId) {
            return $this->respondWithError(ApiErrorCodes::VALIDATION_ERROR, __('api.target_tenant_id_required'), 'target_tenant_id', 422);
        }

        if (!SuperPanelAccess::canAccessTenant($targetTenantId)) {
            return $this->respondWithError(ApiErrorCodes::SUPER_PANEL_ACCESS_DENIED, __('api.super_no_access_target'), null, 403);
        }

        // Target must be a hub tenant
        $targetTenant = Tenant::find($targetTenantId);
        if (!$targetTenant || !$targetTenant['allows_subtenants']) {
            return $this->respondWithError(ApiErrorCodes::VALIDATION_ERROR, __('api.target_must_be_hub'),
                'target_tenant_id',
                422
            );
        }

        $oldTenantId = $user['tenant_id'];

        // Step 1: Move user
        $moveResult = User::moveTenant($id, $targetTenantId);
        if (!$moveResult['success']) {
            return $this->respondWithError(ApiErrorCodes::SERVER_INTERNAL_ERROR, __('api.super_move_user_failed'), null, 500);
        }

        // Step 2: Grant super admin
        DB::update(
            "UPDATE users SET is_tenant_super_admin = 1, role = 'tenant_admin' WHERE id = ?",
            [$id]
        );

        // Audit
        $userName = trim(($user['first_name'] ?? '') . ' ' . ($user['last_name'] ?? '')) ?: $user['email'];
        $this->superAdminAuditService->log(
            'user_moved',
            'user',
            $id,
            $userName,
            ['tenant_id' => $oldTenantId, 'is_tenant_super_admin' => $user['is_tenant_super_admin'] ?? 0],
            ['tenant_id' => $targetTenantId, 'is_tenant_super_admin' => 1],
            "Moved '{$userName}' to '{$targetTenant['name']}' and granted Super Admin privileges (with all content)"
        );

        return $this->respondWithData([
            'moved' => true,
            'promoted' => true,
            'user_id' => $id,
            'old_tenant_id' => $oldTenantId,
            'new_tenant_id' => $targetTenantId,
        ]);
    }

    // ─────────────────────────────────────────────────────────────────────────
    // Bulk Operations
    // ─────────────────────────────────────────────────────────────────────────

    /** POST /api/v2/super-admin/bulk/move-users */
    public function bulkMoveUsers(): JsonResponse
    {
        $userId = $this->requireSuperAdmin();

        $input = $this->getAllInput();

        $userIds = $input['user_ids'] ?? [];
        $targetTenantId = (int) ($input['target_tenant_id'] ?? 0);
        $grantSuperAdmin = !empty($input['grant_super_admin']);

        if (empty($userIds) || !is_array($userIds)) {
            return $this->respondWithError(ApiErrorCodes::VALIDATION_ERROR, __('api.user_ids_required'), 'user_ids', 422);
        }

        if (!$targetTenantId) {
            return $this->respondWithError(ApiErrorCodes::VALIDATION_ERROR, __('api.target_tenant_id_required'), 'target_tenant_id', 422);
        }

        $targetTenant = Tenant::find($targetTenantId);
        if (!$targetTenant) {
            return $this->respondWithError(ApiErrorCodes::RESOURCE_NOT_FOUND, __('api.target_tenant_not_found'), null, 404);
        }

        if (!SuperPanelAccess::canAccessTenant($targetTenantId)) {
            return $this->respondWithError(ApiErrorCodes::SUPER_PANEL_ACCESS_DENIED, __('api.super_no_access_target'), null, 403);
        }

        if ($grantSuperAdmin && !$targetTenant['allows_subtenants']) {
            return $this->respondWithError(ApiErrorCodes::VALIDATION_ERROR, __('api.super_grant_denied_not_hub_target'),
                null,
                422
            );
        }

        // Pre-validate ALL users before moving any (prevents partial failures)
        $validatedUsers = [];
        $errors = [];

        foreach ($userIds as $uid) {
            $uid = (int) $uid;
            $user = User::findById($uid, false);

            if (!$user) {
                $errors[] = "User ID {$uid} not found";
                continue;
            }

            if ((int) $user['tenant_id'] === $targetTenantId) {
                $errors[] = "User ID {$uid} is already in the target tenant";
                continue;
            }

            if (!SuperPanelAccess::canAccessTenant((int) $user['tenant_id'])) {
                $errors[] = "No access to user ID {$uid}'s tenant";
                continue;
            }

            $validatedUsers[] = $uid;
        }

        // If pre-validation found errors and no valid users, return early
        if (empty($validatedUsers) && !empty($errors)) {
            return $this->respondWithData([
                'updated_count' => 0,
                'errors' => $errors,
            ]);
        }

        // Move all validated users
        $movedCount = 0;

        foreach ($validatedUsers as $uid) {
            try {
                $moveResult = User::moveTenant($uid, $targetTenantId);
                if (!$moveResult['success']) {
                    $errors[] = "Failed to move user ID {$uid}";
                    continue;
                }

                if ($grantSuperAdmin) {
                    DB::update(
                        "UPDATE users SET is_tenant_super_admin = 1, role = 'tenant_admin' WHERE id = ?",
                        [$uid]
                    );
                } elseif (!$targetTenant['allows_subtenants']) {
                    DB::update("UPDATE users SET is_tenant_super_admin = 0 WHERE id = ?", [$uid]);
                }

                $movedCount++;
            } catch (\Exception $e) {
                $errors[] = "Failed to move user ID {$uid}";
            }
        }

        $this->superAdminAuditService->log(
            'bulk_users_moved',
            'bulk',
            null,
            "Bulk move to {$targetTenant['name']}",
            ['user_count' => count($userIds)],
            ['target_tenant_id' => $targetTenantId, 'moved_count' => $movedCount, 'grant_super_admin' => $grantSuperAdmin],
            "Moved {$movedCount} users to '{$targetTenant['name']}'" . ($grantSuperAdmin ? ' with Super Admin privileges' : '')
        );

        return $this->respondWithData([
            'moved_count' => $movedCount,
            'total_requested' => count($userIds),
            'errors' => $errors,
        ]);
    }

    /** POST /api/v2/super-admin/bulk/update-tenants */
    public function bulkUpdateTenants(): JsonResponse
    {
        $userId = $this->requireSuperAdmin();

        $input = $this->getAllInput();

        $tenantIds = $input['tenant_ids'] ?? [];
        $action = $input['action'] ?? '';

        if (empty($tenantIds) || !is_array($tenantIds)) {
            return $this->respondWithError(ApiErrorCodes::VALIDATION_ERROR, __('api.tenant_ids_array_required'), 'tenant_ids', 422);
        }

        if (!in_array($action, ['activate', 'deactivate', 'enable_hub', 'disable_hub'], true)) {
            return $this->respondWithError(ApiErrorCodes::VALIDATION_ERROR, __('api.super_bulk_invalid_action'), 'action', 422);
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

            if (!SuperPanelAccess::canAccessTenant($tid)) {
                $errors[] = "No access to tenant ID {$tid}";
                continue;
            }

            try {
                switch ($action) {
                    case 'activate':
                        DB::update("UPDATE tenants SET is_active = 1 WHERE id = ?", [$tid]);
                        break;
                    case 'deactivate':
                        DB::update("UPDATE tenants SET is_active = 0 WHERE id = ?", [$tid]);
                        break;
                    case 'enable_hub':
                        DB::update("UPDATE tenants SET allows_subtenants = 1, max_depth = 2 WHERE id = ?", [$tid]);
                        break;
                    case 'disable_hub':
                        DB::update("UPDATE tenants SET allows_subtenants = 0, max_depth = 0 WHERE id = ?", [$tid]);
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

        $this->superAdminAuditService->log(
            'bulk_tenants_updated',
            'bulk',
            null,
            "Bulk {$action}",
            ['tenant_count' => count($tenantIds)],
            ['action' => $action, 'updated_count' => $updatedCount],
            "{$actionLabels[$action]} {$updatedCount} tenant(s)"
        );

        return $this->respondWithData([
            'updated_count' => $updatedCount,
            'total_requested' => count($tenantIds),
            'action' => $action,
            'errors' => $errors,
        ]);
    }

    // ─────────────────────────────────────────────────────────────────────────
    // Audit
    // ─────────────────────────────────────────────────────────────────────────

    /** GET /api/v2/super-admin/audit */
    public function audit(): JsonResponse
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

        $logs = $this->superAdminAuditService->getLog($filters);

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

        return $this->respondWithData($mapped);
    }

    // ─────────────────────────────────────────────────────────────────────────
    // Federation System Controls
    // ─────────────────────────────────────────────────────────────────────────

    /** GET /api/v2/super-admin/federation */
    public function federationOverview(): JsonResponse
    {
        $this->requireSuperAdmin();

        $systemControls = $this->federationFeatureService->getSystemControls();
        $partnershipStats = $this->federationPartnershipService->getStats();
        $whitelistedTenants = $this->federationFeatureService->getWhitelistedTenants();
        $recentAudit = $this->federationAuditService->getLog(['limit' => 20]);

        return $this->respondWithData([
            'system_controls' => $systemControls,
            'partnership_stats' => $partnershipStats,
            'whitelisted_count' => count($whitelistedTenants),
            'recent_audit' => $recentAudit,
        ]);
    }

    /** GET /api/v2/super-admin/federation/system-controls */
    public function federationGetSystemControls(): JsonResponse
    {
        $this->requireSuperAdmin();

        $controls = $this->federationFeatureService->getSystemControls();

        return $this->respondWithData($controls);
    }

    /** PUT /api/v2/super-admin/federation/system-controls */
    public function federationUpdateSystemControls(): JsonResponse
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
            return $this->respondWithError(ApiErrorCodes::VALIDATION_ERROR, __('api.no_valid_fields'), null, 422);
        }

        $updates[] = "updated_at = NOW()";
        $updates[] = "updated_by = ?";
        $params[] = $userId;

        DB::update(
            "UPDATE federation_system_control SET " . implode(', ', $updates) . " WHERE id = 1",
            $params
        );

        $this->federationFeatureService->clearCache();

        $this->federationAuditService->log(
            'system_controls_updated',
            null,
            null,
            $userId,
            ['changes' => $input],
            FederationAuditService::LEVEL_WARNING
        );

        return $this->respondWithData(['updated' => true]);
    }

    /** POST /api/v2/super-admin/federation/emergency-lockdown */
    public function federationEmergencyLockdown(): JsonResponse
    {
        $userId = $this->requireSuperAdmin();

        $input = $this->getAllInput();
        $reason = $input['reason'] ?? 'Emergency lockdown triggered via API';

        $result = $this->federationFeatureService->triggerEmergencyLockdown($userId, $reason);

        if ($result) {
            return $this->respondWithData(['lockdown' => true, 'message' => __('api_controllers_1.admin_super.lockdown_activated')]);
        }

        return $this->respondWithError(ApiErrorCodes::SERVER_INTERNAL_ERROR, __('api.federation_lockdown_activate_failed'), null, 500);
    }

    /** POST /api/v2/super-admin/federation/lift-lockdown */
    public function federationLiftLockdown(): JsonResponse
    {
        $userId = $this->requireSuperAdmin();

        $result = $this->federationFeatureService->liftEmergencyLockdown($userId);

        if ($result) {
            return $this->respondWithData(['lockdown' => false, 'message' => __('api_controllers_1.admin_super.lockdown_lifted')]);
        }

        return $this->respondWithError(ApiErrorCodes::SERVER_INTERNAL_ERROR, __('api.federation_lockdown_lift_failed'), null, 500);
    }

    /** GET /api/v2/super-admin/federation/whitelist */
    public function federationGetWhitelist(): JsonResponse
    {
        $this->requireSuperAdmin();

        $whitelisted = $this->federationFeatureService->getWhitelistedTenants();

        return $this->respondWithData($whitelisted);
    }

    /** POST /api/v2/super-admin/federation/whitelist */
    public function federationAddToWhitelist(): JsonResponse
    {
        $userId = $this->requireSuperAdmin();

        $input = $this->getAllInput();
        $tenantId = (int) ($input['tenant_id'] ?? 0);
        $notes = $input['notes'] ?? null;

        if ($tenantId <= 0) {
            return $this->respondWithError(ApiErrorCodes::VALIDATION_ERROR, __('api.tenant_id_is_required'), 'tenant_id', 422);
        }

        $result = $this->federationFeatureService->addToWhitelist($tenantId, $userId, $notes);

        if ($result) {
            return $this->respondWithData(['added' => true, 'tenant_id' => $tenantId]);
        }

        return $this->respondWithError(ApiErrorCodes::SERVER_INTERNAL_ERROR, __('api.federation_whitelist_add_failed'), null, 500);
    }

    /** DELETE /api/v2/super-admin/federation/whitelist/{tenantId} */
    public function federationRemoveFromWhitelist($tenantId): JsonResponse
    {
        $userId = $this->requireSuperAdmin();

        $tenantId = (int) $tenantId;

        if ($tenantId <= 0) {
            return $this->respondWithError(ApiErrorCodes::VALIDATION_ERROR, __('api.invalid_tenant_id'), 'tenantId', 400);
        }

        $result = $this->federationFeatureService->removeFromWhitelist($tenantId, $userId);

        if ($result) {
            return $this->respondWithData(['removed' => true, 'tenant_id' => $tenantId]);
        }

        return $this->respondWithError(ApiErrorCodes::SERVER_INTERNAL_ERROR, __('api.federation_whitelist_remove_failed'), null, 500);
    }

    /** GET /api/v2/super-admin/federation/partnerships */
    public function federationPartnerships(): JsonResponse
    {
        $this->requireSuperAdmin();

        $partnerships = $this->federationPartnershipService->getAllPartnerships(null, 100);
        $stats = $this->federationPartnershipService->getStats();

        return $this->respondWithData([
            'partnerships' => $partnerships,
            'stats' => $stats,
        ]);
    }

    /** POST /api/v2/super-admin/federation/partnerships/{id}/suspend */
    public function federationSuspendPartnership($id): JsonResponse
    {
        $userId = $this->requireSuperAdmin();

        $partnershipId = (int) $id;

        if ($partnershipId <= 0) {
            return $this->respondWithError(ApiErrorCodes::VALIDATION_ERROR, __('api.invalid_partnership_id'), 'id', 400);
        }

        $input = $this->getAllInput();
        $reason = $input['reason'] ?? 'Suspended by super admin via API';

        $result = $this->federationPartnershipService->suspendPartnership($partnershipId, $userId, $reason);

        if (is_array($result) && !empty($result['success'])) {
            return $this->respondWithData(['suspended' => true, 'partnership_id' => $partnershipId]);
        }

        $error = is_array($result) ? ($result['error'] ?? 'Failed to suspend partnership') : 'Failed to suspend partnership';
        return $this->respondWithError(ApiErrorCodes::SERVER_INTERNAL_ERROR, $error, null, 500);
    }

    /** POST /api/v2/super-admin/federation/partnerships/{id}/terminate */
    public function federationTerminatePartnership($id): JsonResponse
    {
        $userId = $this->requireSuperAdmin();

        $partnershipId = (int) $id;

        if ($partnershipId <= 0) {
            return $this->respondWithError(ApiErrorCodes::VALIDATION_ERROR, __('api.invalid_partnership_id'), 'id', 400);
        }

        $input = $this->getAllInput();
        $reason = $input['reason'] ?? 'Terminated by super admin via API';

        $result = $this->federationPartnershipService->terminatePartnership($partnershipId, $userId, $reason);

        if (is_array($result) && !empty($result['success'])) {
            return $this->respondWithData(['terminated' => true, 'partnership_id' => $partnershipId]);
        }

        $error = is_array($result) ? ($result['error'] ?? 'Failed to terminate partnership') : 'Failed to terminate partnership';
        return $this->respondWithError(ApiErrorCodes::SERVER_INTERNAL_ERROR, $error, null, 500);
    }

    /** GET /api/v2/super-admin/federation/tenant/{id}/features */
    public function federationGetTenantFeatures($id): JsonResponse
    {
        $this->requireSuperAdmin();

        $tenantId = (int) $id;

        if ($tenantId <= 0) {
            return $this->respondWithError(ApiErrorCodes::VALIDATION_ERROR, __('api.invalid_tenant_id'), 'id', 400);
        }

        $tenant = Tenant::find($tenantId);
        if (!$tenant) {
            return $this->respondWithError(ApiErrorCodes::RESOURCE_NOT_FOUND, __('api.tenant_not_found'), null, 404);
        }

        if (!SuperPanelAccess::canAccessTenant($tenantId)) {
            return $this->respondWithError(ApiErrorCodes::SUPER_PANEL_ACCESS_DENIED, __('api.super_no_access_tenant'), null, 403);
        }

        $features = $this->federationFeatureService->getAllTenantFeatures($tenantId);
        $isWhitelisted = $this->federationFeatureService->isTenantWhitelisted($tenantId);
        $partnerships = $this->federationPartnershipService->getTenantPartnerships($tenantId);

        return $this->respondWithData([
            'tenant' => $tenant,
            'features' => $features,
            'is_whitelisted' => $isWhitelisted,
            'partnerships' => $partnerships,
        ]);
    }

    /** PUT /api/v2/super-admin/federation/tenant/{id}/features */
    public function federationUpdateTenantFeature($id): JsonResponse
    {
        $this->requireSuperAdmin();

        $tenantId = (int) $id;

        if ($tenantId <= 0) {
            return $this->respondWithError(ApiErrorCodes::VALIDATION_ERROR, __('api.invalid_tenant_id'), 'id', 400);
        }

        if (!SuperPanelAccess::canAccessTenant($tenantId)) {
            return $this->respondWithError(ApiErrorCodes::SUPER_PANEL_ACCESS_DENIED, __('api.super_no_access_tenant'), null, 403);
        }

        $input = $this->getAllInput();
        $feature = $input['feature'] ?? '';
        $enabled = (bool) ($input['enabled'] ?? false);

        if (empty($feature)) {
            return $this->respondWithError(ApiErrorCodes::VALIDATION_ERROR, __('api.feature_name_required'), 'feature', 422);
        }

        if ($enabled) {
            $result = $this->federationFeatureService->enableTenantFeature($feature, $tenantId);
        } else {
            $result = $this->federationFeatureService->disableTenantFeature($feature, $tenantId);
        }

        if ($result) {
            return $this->respondWithData([
                'updated' => true,
                'tenant_id' => $tenantId,
                'feature' => $feature,
                'enabled' => $enabled,
            ]);
        }

        return $this->respondWithError(ApiErrorCodes::SERVER_INTERNAL_ERROR, __('api.feature_update_failed'), null, 500);
    }

    /**
     * PUT /api/v2/admin/super/identity/fee
     *
     * Set the identity verification fee for a tenant. Super admin only.
     * Body: { "tenant_id": 2, "fee_cents": 500 }
     * Set fee_cents to 0 for free verification.
     */
    public function setIdentityVerificationFee(): JsonResponse
    {
        $this->requireSuperAdmin();

        $input = $this->getAllInput();
        $tenantId = (int) ($input['tenant_id'] ?? 0);
        $feeCents = (int) ($input['fee_cents'] ?? -1);

        if ($tenantId <= 0) {
            return $this->respondWithError('VALIDATION_REQUIRED_FIELD', 'tenant_id is required.', 'tenant_id', 422);
        }
        if ($feeCents < 0) {
            return $this->respondWithError('VALIDATION_INVALID_FORMAT', 'fee_cents must be 0 or greater.', 'fee_cents', 422);
        }

        $this->tenantSettingsService->set($tenantId, 'identity_verification_fee_cents', (string) $feeCents, 'integer');

        return $this->respondWithData([
            'tenant_id' => $tenantId,
            'fee_cents' => $feeCents,
            'fee_display' => '€' . number_format($feeCents / 100, 2),
        ]);
    }
}

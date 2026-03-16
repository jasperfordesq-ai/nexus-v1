<?php
// Copyright © 2024-2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

namespace App\Http\Controllers\Api;

use Illuminate\Http\JsonResponse;

/**
 * AdminSuperController -- Super-admin tenant and cross-tenant user management.
 *
 * Delegates to legacy controller during migration.
 */
class AdminSuperController extends BaseApiController
{
    protected bool $isV2Api = true;

    public function __construct() {}

    /**
     * Delegate to legacy controller via output buffering.
     */
    private function delegate(string $legacyClass, string $method, array $params = []): JsonResponse
    {
        $controller = new $legacyClass();
        ob_start();
        $controller->$method(...$params);
        $output = ob_get_clean();
        $status = http_response_code();
        return response()->json(json_decode($output, true) ?: $output, $status ?: 200);
    }

    /** GET /api/v2/super-admin/dashboard */
    public function dashboard(): JsonResponse
    {
        return $this->delegate(\Nexus\Controllers\Api\AdminSuperApiController::class, 'dashboard');
    }

    /** GET /api/v2/super-admin/tenants */
    public function tenantList(): JsonResponse
    {
        return $this->delegate(\Nexus\Controllers\Api\AdminSuperApiController::class, 'tenantList');
    }

    /** GET /api/v2/super-admin/tenants/hierarchy */
    public function tenantHierarchy(): JsonResponse
    {
        return $this->delegate(\Nexus\Controllers\Api\AdminSuperApiController::class, 'tenantHierarchy');
    }

    /** GET /api/v2/super-admin/tenants/{id} */
    public function tenantShow(int $id): JsonResponse
    {
        return $this->delegate(\Nexus\Controllers\Api\AdminSuperApiController::class, 'tenantShow', [$id]);
    }

    /** POST /api/v2/super-admin/tenants */
    public function tenantCreate(): JsonResponse
    {
        return $this->delegate(\Nexus\Controllers\Api\AdminSuperApiController::class, 'tenantCreate');
    }

    /** PUT /api/v2/super-admin/tenants/{id} */
    public function tenantUpdate(int $id): JsonResponse
    {
        return $this->delegate(\Nexus\Controllers\Api\AdminSuperApiController::class, 'tenantUpdate', [$id]);
    }

    /** DELETE /api/v2/super-admin/tenants/{id} */
    public function tenantDelete(int $id): JsonResponse
    {
        return $this->delegate(\Nexus\Controllers\Api\AdminSuperApiController::class, 'tenantDelete', [$id]);
    }

    /** POST /api/v2/super-admin/tenants/{id}/reactivate */
    public function tenantReactivate(int $id): JsonResponse
    {
        return $this->delegate(\Nexus\Controllers\Api\AdminSuperApiController::class, 'tenantReactivate', [$id]);
    }

    /** POST /api/v2/super-admin/tenants/{id}/toggle-hub */
    public function tenantToggleHub(int $id): JsonResponse
    {
        return $this->delegate(\Nexus\Controllers\Api\AdminSuperApiController::class, 'tenantToggleHub', [$id]);
    }

    /** POST /api/v2/super-admin/tenants/{id}/move */
    public function tenantMove(int $id): JsonResponse
    {
        return $this->delegate(\Nexus\Controllers\Api\AdminSuperApiController::class, 'tenantMove', [$id]);
    }

    /** GET /api/v2/super-admin/users */
    public function userList(): JsonResponse
    {
        return $this->delegate(\Nexus\Controllers\Api\AdminSuperApiController::class, 'userList');
    }

    /** GET /api/v2/super-admin/users/{id} */
    public function userShow(int $id): JsonResponse
    {
        return $this->delegate(\Nexus\Controllers\Api\AdminSuperApiController::class, 'userShow', [$id]);
    }

    /** POST /api/v2/super-admin/users */
    public function userCreate(): JsonResponse
    {
        return $this->delegate(\Nexus\Controllers\Api\AdminSuperApiController::class, 'userCreate');
    }

    /** PUT /api/v2/super-admin/users/{id} */
    public function userUpdate(int $id): JsonResponse
    {
        return $this->delegate(\Nexus\Controllers\Api\AdminSuperApiController::class, 'userUpdate', [$id]);
    }

    /** POST /api/v2/super-admin/users/{id}/grant-super */
    public function userGrantSuperAdmin(int $id): JsonResponse
    {
        return $this->delegate(\Nexus\Controllers\Api\AdminSuperApiController::class, 'userGrantSuperAdmin', [$id]);
    }

    /** POST /api/v2/super-admin/users/{id}/revoke-super */
    public function userRevokeSuperAdmin(int $id): JsonResponse
    {
        return $this->delegate(\Nexus\Controllers\Api\AdminSuperApiController::class, 'userRevokeSuperAdmin', [$id]);
    }

    /** POST /api/v2/super-admin/users/{id}/grant-global */
    public function userGrantGlobalSuperAdmin(int $id): JsonResponse
    {
        return $this->delegate(\Nexus\Controllers\Api\AdminSuperApiController::class, 'userGrantGlobalSuperAdmin', [$id]);
    }

    /** POST /api/v2/super-admin/users/{id}/revoke-global */
    public function userRevokeGlobalSuperAdmin(int $id): JsonResponse
    {
        return $this->delegate(\Nexus\Controllers\Api\AdminSuperApiController::class, 'userRevokeGlobalSuperAdmin', [$id]);
    }

    /** POST /api/v2/super-admin/users/{id}/move-tenant */
    public function userMoveTenant(int $id): JsonResponse
    {
        return $this->delegate(\Nexus\Controllers\Api\AdminSuperApiController::class, 'userMoveTenant', [$id]);
    }

    /** POST /api/v2/super-admin/users/{id}/move-promote */
    public function userMoveAndPromote(int $id): JsonResponse
    {
        return $this->delegate(\Nexus\Controllers\Api\AdminSuperApiController::class, 'userMoveAndPromote', [$id]);
    }
}

<?php
// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

namespace App\Http\Controllers\Api;

use Illuminate\Http\JsonResponse;

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
 * The legacy controller has its own requireSuperAdmin() override with JWT/session sync
 * logic and SuperPanelAccess middleware integration. All methods delegate to the legacy
 * controller which uses Database::query(), TenantVisibilityService, TenantHierarchyService,
 * SuperAdminAuditService, FederationFeatureService, FederationPartnershipService, and
 * FederationAuditService.
 */
class AdminSuperController extends BaseApiController
{
    protected bool $isV2Api = true;

    public function __construct() {}

    /**
     * Delegate to legacy controller via output buffering.
     */
    private function delegate(string $method, array $params = []): JsonResponse
    {
        $controller = new \Nexus\Controllers\Api\AdminSuperApiController();
        ob_start();
        $controller->$method(...$params);
        $output = ob_get_clean();
        $status = http_response_code();
        return response()->json(json_decode($output, true) ?: $output, $status ?: 200);
    }

    // ─────────────────────────────────────────────────────────────────────────
    // Dashboard
    // ─────────────────────────────────────────────────────────────────────────

    /** GET /api/v2/super-admin/dashboard */
    public function dashboard(): JsonResponse { return $this->delegate('dashboard'); }

    // ─────────────────────────────────────────────────────────────────────────
    // Tenant Management
    // ─────────────────────────────────────────────────────────────────────────

    /** GET /api/v2/super-admin/tenants */
    public function tenantList(): JsonResponse { return $this->delegate('tenantList'); }

    /** GET /api/v2/super-admin/tenants/hierarchy */
    public function tenantHierarchy(): JsonResponse { return $this->delegate('tenantHierarchy'); }

    /** GET /api/v2/super-admin/tenants/{id} */
    public function tenantShow(int $id): JsonResponse { return $this->delegate('tenantShow', [$id]); }

    /** POST /api/v2/super-admin/tenants */
    public function tenantCreate(): JsonResponse { return $this->delegate('tenantCreate'); }

    /** PUT /api/v2/super-admin/tenants/{id} */
    public function tenantUpdate(int $id): JsonResponse { return $this->delegate('tenantUpdate', [$id]); }

    /** DELETE /api/v2/super-admin/tenants/{id} */
    public function tenantDelete(int $id): JsonResponse { return $this->delegate('tenantDelete', [$id]); }

    /** POST /api/v2/super-admin/tenants/{id}/reactivate */
    public function tenantReactivate(int $id): JsonResponse { return $this->delegate('tenantReactivate', [$id]); }

    /** POST /api/v2/super-admin/tenants/{id}/toggle-hub */
    public function tenantToggleHub(int $id): JsonResponse { return $this->delegate('tenantToggleHub', [$id]); }

    /** POST /api/v2/super-admin/tenants/{id}/move */
    public function tenantMove(int $id): JsonResponse { return $this->delegate('tenantMove', [$id]); }

    // ─────────────────────────────────────────────────────────────────────────
    // User Management
    // ─────────────────────────────────────────────────────────────────────────

    /** GET /api/v2/super-admin/users */
    public function userList(): JsonResponse { return $this->delegate('userList'); }

    /** GET /api/v2/super-admin/users/{id} */
    public function userShow(int $id): JsonResponse { return $this->delegate('userShow', [$id]); }

    /** POST /api/v2/super-admin/users */
    public function userCreate(): JsonResponse { return $this->delegate('userCreate'); }

    /** PUT /api/v2/super-admin/users/{id} */
    public function userUpdate(int $id): JsonResponse { return $this->delegate('userUpdate', [$id]); }

    /** POST /api/v2/super-admin/users/{id}/grant-super */
    public function userGrantSuperAdmin(int $id): JsonResponse { return $this->delegate('userGrantSuperAdmin', [$id]); }

    /** POST /api/v2/super-admin/users/{id}/revoke-super */
    public function userRevokeSuperAdmin(int $id): JsonResponse { return $this->delegate('userRevokeSuperAdmin', [$id]); }

    /** POST /api/v2/super-admin/users/{id}/grant-global */
    public function userGrantGlobalSuperAdmin(int $id): JsonResponse { return $this->delegate('userGrantGlobalSuperAdmin', [$id]); }

    /** POST /api/v2/super-admin/users/{id}/revoke-global */
    public function userRevokeGlobalSuperAdmin(int $id): JsonResponse { return $this->delegate('userRevokeGlobalSuperAdmin', [$id]); }

    /** POST /api/v2/super-admin/users/{id}/move-tenant */
    public function userMoveTenant(int $id): JsonResponse { return $this->delegate('userMoveTenant', [$id]); }

    /** POST /api/v2/super-admin/users/{id}/move-promote */
    public function userMoveAndPromote(int $id): JsonResponse { return $this->delegate('userMoveAndPromote', [$id]); }

    // ─────────────────────────────────────────────────────────────────────────
    // Bulk Operations
    // ─────────────────────────────────────────────────────────────────────────

    public function bulkMoveUsers(): JsonResponse { return $this->delegate('bulkMoveUsers'); }
    public function bulkUpdateTenants(): JsonResponse { return $this->delegate('bulkUpdateTenants'); }

    // ─────────────────────────────────────────────────────────────────────────
    // Audit
    // ─────────────────────────────────────────────────────────────────────────

    public function audit(): JsonResponse { return $this->delegate('audit'); }

    // ─────────────────────────────────────────────────────────────────────────
    // Federation System Controls
    // ─────────────────────────────────────────────────────────────────────────

    public function federationOverview(): JsonResponse { return $this->delegate('federationOverview'); }
    public function federationGetSystemControls(): JsonResponse { return $this->delegate('federationGetSystemControls'); }
    public function federationUpdateSystemControls(): JsonResponse { return $this->delegate('federationUpdateSystemControls'); }
    public function federationEmergencyLockdown(): JsonResponse { return $this->delegate('federationEmergencyLockdown'); }
    public function federationLiftLockdown(): JsonResponse { return $this->delegate('federationLiftLockdown'); }
    public function federationGetWhitelist(): JsonResponse { return $this->delegate('federationGetWhitelist'); }
    public function federationAddToWhitelist(): JsonResponse { return $this->delegate('federationAddToWhitelist'); }
    public function federationRemoveFromWhitelist($tenantId): JsonResponse { return $this->delegate('federationRemoveFromWhitelist', [(int)$tenantId]); }
    public function federationPartnerships(): JsonResponse { return $this->delegate('federationPartnerships'); }
    public function federationSuspendPartnership($id): JsonResponse { return $this->delegate('federationSuspendPartnership', [(int)$id]); }
    public function federationTerminatePartnership($id): JsonResponse { return $this->delegate('federationTerminatePartnership', [(int)$id]); }
    public function federationGetTenantFeatures($id): JsonResponse { return $this->delegate('federationGetTenantFeatures', [(int)$id]); }
    public function federationUpdateTenantFeature($id): JsonResponse { return $this->delegate('federationUpdateTenantFeature', [(int)$id]); }
}

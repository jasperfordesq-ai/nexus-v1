<?php
// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

namespace App\Http\Controllers\Api;

use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;

/**
 * AdminFederationNeighborhoodsController -- Federation neighborhood management.
 *
 * Handles CRUD for federation neighborhoods and lists available tenants
 * that can be added to neighborhoods.
 */
class AdminFederationNeighborhoodsController extends BaseApiController
{
    protected bool $isV2Api = true;

    /**
     * GET /api/v2/admin/federation/neighborhoods
     *
     * List all federation neighborhoods.
     */
    public function index(): JsonResponse
    {
        $this->requireAdmin();

        if (class_exists(\Nexus\Services\FederationNeighborhoodService::class)) {
            try {
                $neighborhoods = \Nexus\Services\FederationNeighborhoodService::listAll();
                return $this->respondWithData($neighborhoods);
            } catch (\Exception $e) {
                return $this->respondWithError('FETCH_FAILED', 'Failed to load neighborhoods', null, 500);
            }
        }

        return $this->respondWithError('SERVICE_UNAVAILABLE', 'FederationNeighborhoodService not available', null, 503);
    }

    /**
     * POST /api/v2/admin/federation/neighborhoods
     *
     * Create a new federation neighborhood.
     */
    public function store(): JsonResponse
    {
        $adminId = $this->requireAdmin();
        $input = $this->getAllInput();

        $name = trim($input['name'] ?? '');
        if ($name === '') {
            return $this->respondWithError('VALIDATION_ERROR', 'Name is required', 'name');
        }

        $description = isset($input['description']) ? trim($input['description']) : null;

        if (class_exists(\Nexus\Services\FederationNeighborhoodService::class)) {
            try {
                $neighborhood = \Nexus\Services\FederationNeighborhoodService::create($name, $description, null, $adminId);
                return $this->respondWithData($neighborhood, null, 201);
            } catch (\Exception $e) {
                return $this->respondWithError('CREATE_FAILED', 'Failed to create neighborhood: ' . $e->getMessage());
            }
        }

        return $this->respondWithError('SERVICE_UNAVAILABLE', 'FederationNeighborhoodService not available', null, 503);
    }

    /**
     * DELETE /api/v2/admin/federation/neighborhoods/{id}
     *
     * Delete a federation neighborhood.
     */
    public function destroy(int $id): JsonResponse
    {
        $this->requireAdmin();
        $tenantId = $this->getTenantId();

        try {
            $deleted = DB::delete(
                "DELETE FROM federation_neighborhoods WHERE id = ? AND tenant_id = ?",
                [$id, $tenantId]
            );

            if ($deleted === 0) {
                return $this->respondWithError('NOT_FOUND', 'Neighborhood not found', null, 404);
            }

            return $this->respondWithData(['success' => true]);
        } catch (\Exception $e) {
            return $this->respondWithError('DELETE_FAILED', 'Failed to delete neighborhood', null, 500);
        }
    }

    /**
     * POST /api/v2/admin/federation/neighborhoods/{id}/tenants
     *
     * Add a tenant to a federation neighborhood.
     */
    public function addTenant(int $id): JsonResponse
    {
        $this->requireAdmin();
        $input = $this->getAllInput();

        $tenantId = (int) ($input['tenant_id'] ?? 0);
        if ($tenantId <= 0) {
            return $this->respondWithError('VALIDATION_ERROR', 'Tenant ID is required', 'tenant_id');
        }

        try {
            DB::insert(
                "INSERT INTO federation_neighborhood_tenants (neighborhood_id, tenant_id) VALUES (?, ?)",
                [$id, $tenantId]
            );

            return $this->respondWithData(['success' => true]);
        } catch (\Exception $e) {
            return $this->respondWithError('ADD_FAILED', 'Failed to add tenant to neighborhood: ' . $e->getMessage(), null, 500);
        }
    }

    /**
     * DELETE /api/v2/admin/federation/neighborhoods/{id}/tenants/{tenantId}
     *
     * Remove a tenant from a federation neighborhood.
     */
    public function removeTenant(int $id, int $tenantId): JsonResponse
    {
        $this->requireAdmin();

        try {
            $deleted = DB::delete(
                "DELETE FROM federation_neighborhood_tenants WHERE neighborhood_id = ? AND tenant_id = ?",
                [$id, $tenantId]
            );

            if ($deleted === 0) {
                return $this->respondWithError('NOT_FOUND', 'Tenant not found in neighborhood', null, 404);
            }

            return $this->respondWithData(['success' => true]);
        } catch (\Exception $e) {
            return $this->respondWithError('DELETE_FAILED', 'Failed to remove tenant from neighborhood', null, 500);
        }
    }

    /**
     * GET /api/v2/admin/federation/available-tenants
     *
     * List tenants that can be added to neighborhoods (active tenants excluding current).
     */
    public function availableTenants(): JsonResponse
    {
        $this->requireAdmin();
        $currentTenantId = $this->getTenantId();

        try {
            $tenants = DB::select(
                "SELECT id, name, slug, domain FROM tenants WHERE is_active = 1 AND id != ? ORDER BY name",
                [$currentTenantId]
            );

            return $this->respondWithData(array_map(fn($r) => (array) $r, $tenants));
        } catch (\Exception $e) {
            return $this->respondWithError('FETCH_FAILED', 'Failed to load available tenants', null, 500);
        }
    }
}

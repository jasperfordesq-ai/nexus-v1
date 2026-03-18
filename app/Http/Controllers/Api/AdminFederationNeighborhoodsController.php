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

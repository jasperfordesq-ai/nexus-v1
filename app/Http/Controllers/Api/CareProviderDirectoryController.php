<?php
// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Core\TenantContext;
use App\Services\CaringCommunity\CareProviderDirectoryService;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Validator;

/**
 * CareProviderDirectoryController — AG64 Unified Care-Provider Directory API
 *
 * Member-facing and admin endpoints for browsing and managing care providers
 * (Spitex, Tagesstätten, private services, Vereine, volunteer groups).
 *
 * All routes are gated behind the `caring_community` tenant feature.
 */
class CareProviderDirectoryController extends BaseApiController
{
    protected bool $isV2Api = true;

    public function __construct(
        private readonly CareProviderDirectoryService $service,
    ) {
    }

    // -------------------------------------------------------------------------
    // Member-facing endpoints
    // -------------------------------------------------------------------------

    /**
     * GET /api/v2/caring-community/providers
     * Public-ish member endpoint — returns active providers with filters.
     */
    public function index(): JsonResponse
    {
        $this->requireAuth();

        if (!TenantContext::hasFeature('caring_community')) {
            return $this->respondWithError('FEATURE_DISABLED', __('api.service_unavailable'), null, 403);
        }

        try {
            $filters = [
                'type'          => request()->query('type'),
                'search'        => request()->query('search'),
                'sub_region_id' => request()->query('sub_region_id'),
                'verified_only' => filter_var(request()->query('verified_only', false), FILTER_VALIDATE_BOOLEAN),
                'page'          => (int) request()->query('page', 1),
            ];

            return $this->respondWithData(
                $this->service->list(TenantContext::getId(), $filters)
            );
        } catch (\RuntimeException $e) {
            return $this->respondWithError('FEATURE_DISABLED', $e->getMessage(), null, 403);
        }
    }

    /**
     * GET /api/v2/caring-community/providers/{id}
     * Get a single provider (member-facing).
     */
    public function show(int $id): JsonResponse
    {
        $this->requireAuth();

        if (!TenantContext::hasFeature('caring_community')) {
            return $this->respondWithError('FEATURE_DISABLED', __('api.service_unavailable'), null, 403);
        }

        try {
            $provider = $this->service->getActive($id, TenantContext::getId());

            if ($provider === null) {
                return $this->respondNotFound(__('api.resource_not_found'));
            }

            return $this->respondWithData($provider);
        } catch (\RuntimeException $e) {
            return $this->respondWithError('FEATURE_DISABLED', $e->getMessage(), null, 403);
        }
    }

    // -------------------------------------------------------------------------
    // Admin endpoints
    // -------------------------------------------------------------------------

    /**
     * GET /api/v2/admin/caring-community/providers
     * Admin listing — all statuses.
     */
    public function adminIndex(): JsonResponse
    {
        $this->requireAuth();
        $this->requireAdmin();

        if (!TenantContext::hasFeature('caring_community')) {
            return $this->respondWithError('FEATURE_DISABLED', __('api.service_unavailable'), null, 403);
        }

        try {
            $filters = [
                'page' => (int) request()->query('page', 1),
            ];

            return $this->respondWithData(
                $this->service->adminList(TenantContext::getId(), $filters)
            );
        } catch (\RuntimeException $e) {
            return $this->respondWithError('FEATURE_DISABLED', $e->getMessage(), null, 403);
        }
    }

    /**
     * POST /api/v2/admin/caring-community/providers
     * Create a new care provider.
     */
    public function store(): JsonResponse
    {
        $userId = $this->requireAuth();
        $this->requireAdmin();

        if (!TenantContext::hasFeature('caring_community')) {
            return $this->respondWithError('FEATURE_DISABLED', __('api.service_unavailable'), null, 403);
        }

        $input = $this->getAllInput();

        $validator = Validator::make($input, [
            'name'          => 'required|string|max:255',
            'type'          => 'required|string|in:spitex,tagesstätte,private,verein,volunteer',
            'description'   => 'nullable|string',
            'address'       => 'nullable|string|max:255',
            'sub_region_id' => 'nullable|integer|min:1',
            'contact_phone' => 'nullable|string|max:50',
            'contact_email' => 'nullable|email|max:255',
            'website_url'   => 'nullable|url|max:255',
            'status'        => 'sometimes|string|in:active,inactive',
        ]);

        if ($validator->fails()) {
            $errors = [];
            foreach ($validator->errors()->toArray() as $field => $messages) {
                $errors[] = [
                    'code'    => 'VALIDATION_ERROR',
                    'message' => $messages[0],
                    'field'   => $field,
                ];
            }
            return $this->respondWithErrors($errors, 422);
        }

        try {
            $provider = $this->service->create(TenantContext::getId(), $input, $userId);
            return $this->respondWithData($provider, null, 201);
        } catch (\InvalidArgumentException $e) {
            return $this->respondWithError('VALIDATION_ERROR', $e->getMessage(), 'sub_region_id', 422);
        } catch (\RuntimeException $e) {
            return $this->respondWithError('FEATURE_DISABLED', $e->getMessage(), null, 403);
        }
    }

    /**
     * PUT /api/v2/admin/caring-community/providers/{id}
     * Update an existing care provider.
     */
    public function adminUpdate(int $id): JsonResponse
    {
        $this->requireAuth();
        $this->requireAdmin();

        if (!TenantContext::hasFeature('caring_community')) {
            return $this->respondWithError('FEATURE_DISABLED', __('api.service_unavailable'), null, 403);
        }

        $input = $this->getAllInput();

        $validator = Validator::make($input, [
            'name'          => 'sometimes|required|string|max:255',
            'type'          => 'sometimes|required|string|in:spitex,tagesstätte,private,verein,volunteer',
            'description'   => 'nullable|string',
            'address'       => 'nullable|string|max:255',
            'sub_region_id' => 'nullable|integer|min:1',
            'contact_phone' => 'nullable|string|max:50',
            'contact_email' => 'nullable|email|max:255',
            'website_url'   => 'nullable|url|max:255',
            'status'        => 'sometimes|string|in:active,inactive',
        ]);

        if ($validator->fails()) {
            $errors = [];
            foreach ($validator->errors()->toArray() as $field => $messages) {
                $errors[] = [
                    'code'    => 'VALIDATION_ERROR',
                    'message' => $messages[0],
                    'field'   => $field,
                ];
            }
            return $this->respondWithErrors($errors, 422);
        }

        try {
            $tenantId = TenantContext::getId();

            if ($this->service->get($id, $tenantId) === null) {
                return $this->respondNotFound(__('api.resource_not_found'));
            }

            $provider = $this->service->update($id, $tenantId, $input);
            return $this->respondWithData($provider);
        } catch (\InvalidArgumentException $e) {
            return $this->respondWithError('VALIDATION_ERROR', $e->getMessage(), 'sub_region_id', 422);
        } catch (\RuntimeException $e) {
            return $this->respondWithError('FEATURE_DISABLED', $e->getMessage(), null, 403);
        }
    }

    /**
     * DELETE /api/v2/admin/caring-community/providers/{id}
     * Soft-delete (status = inactive).
     */
    public function adminDelete(int $id): JsonResponse
    {
        $this->requireAuth();
        $this->requireAdmin();

        if (!TenantContext::hasFeature('caring_community')) {
            return $this->respondWithError('FEATURE_DISABLED', __('api.service_unavailable'), null, 403);
        }

        try {
            $tenantId = TenantContext::getId();

            if ($this->service->get($id, $tenantId) === null) {
                return $this->respondNotFound(__('api.resource_not_found'));
            }

            $this->service->delete($id, $tenantId);
            return $this->respondWithData(['deleted' => true]);
        } catch (\RuntimeException $e) {
            return $this->respondWithError('FEATURE_DISABLED', $e->getMessage(), null, 403);
        }
    }

    /**
     * GET /api/v2/admin/caring-community/providers/duplicates
     * AG64 follow-up — list potential duplicate / overlapping provider pairs.
     */
    public function adminDuplicates(): JsonResponse
    {
        $this->requireAuth();
        $this->requireAdmin();

        if (!TenantContext::hasFeature('caring_community')) {
            return $this->respondWithError('FEATURE_DISABLED', __('api.service_unavailable'), null, 403);
        }

        try {
            $threshold = (float) request()->query('threshold', 0.65);
            if ($threshold < 0.30) {
                $threshold = 0.30;
            }
            if ($threshold > 0.95) {
                $threshold = 0.95;
            }

            return $this->respondWithData(
                $this->service->findPotentialDuplicates(TenantContext::getId(), $threshold)
            );
        } catch (\RuntimeException $e) {
            return $this->respondWithError('FEATURE_DISABLED', $e->getMessage(), null, 403);
        }
    }

    /**
     * POST /api/v2/admin/caring-community/providers/{id}/verify
     * Mark a provider as verified.
     */
    public function adminVerify(int $id): JsonResponse
    {
        $this->requireAuth();
        $this->requireAdmin();

        if (!TenantContext::hasFeature('caring_community')) {
            return $this->respondWithError('FEATURE_DISABLED', __('api.service_unavailable'), null, 403);
        }

        try {
            $tenantId = TenantContext::getId();

            if ($this->service->get($id, $tenantId) === null) {
                return $this->respondNotFound(__('api.resource_not_found'));
            }

            $this->service->verify($id, $tenantId);
            return $this->respondWithData(['verified' => true]);
        } catch (\RuntimeException $e) {
            return $this->respondWithError('FEATURE_DISABLED', $e->getMessage(), null, 403);
        }
    }
}

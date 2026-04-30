<?php
// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Core\TenantContext;
use App\Services\CaringCommunity\CaringSubRegionService;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Validator;
use RuntimeException;

class CaringSubRegionController extends BaseApiController
{
    protected bool $isV2Api = true;

    public function __construct(
        private readonly CaringSubRegionService $service,
    ) {
    }

    public function index(): JsonResponse
    {
        $this->requireAuth();

        if (! TenantContext::hasFeature('caring_community')) {
            return $this->respondWithError('FEATURE_DISABLED', __('api.service_unavailable'), null, 403);
        }

        return $this->respondWithData($this->service->list(TenantContext::getId(), [
            'type' => request()->query('type'),
            'search' => request()->query('search'),
            'page' => (int) request()->query('page', 1),
        ]));
    }

    public function adminIndex(): JsonResponse
    {
        $this->requireAuth();
        $this->requireAdmin();

        if (! TenantContext::hasFeature('caring_community')) {
            return $this->respondWithError('FEATURE_DISABLED', __('api.service_unavailable'), null, 403);
        }

        return $this->respondWithData($this->service->list(TenantContext::getId(), [
            'type' => request()->query('type'),
            'search' => request()->query('search'),
            'page' => (int) request()->query('page', 1),
        ], true));
    }

    public function store(): JsonResponse
    {
        $userId = $this->requireAuth();
        $this->requireAdmin();

        if (! TenantContext::hasFeature('caring_community')) {
            return $this->respondWithError('FEATURE_DISABLED', __('api.service_unavailable'), null, 403);
        }

        $input = $this->getAllInput();
        $errors = $this->validateInput($input, false);
        if ($errors !== []) {
            return $this->respondWithErrors($errors, 422);
        }

        try {
            return $this->respondWithData(
                $this->service->create(TenantContext::getId(), $input, $userId),
                null,
                201
            );
        } catch (RuntimeException $e) {
            return $this->respondWithError('SUB_REGION_INVALID', $e->getMessage(), null, 422);
        }
    }

    public function update(int $id): JsonResponse
    {
        $this->requireAuth();
        $this->requireAdmin();

        if (! TenantContext::hasFeature('caring_community')) {
            return $this->respondWithError('FEATURE_DISABLED', __('api.service_unavailable'), null, 403);
        }

        $tenantId = TenantContext::getId();
        if ($this->service->get($id, $tenantId) === null) {
            return $this->respondNotFound(__('api.resource_not_found'));
        }

        $input = $this->getAllInput();
        $errors = $this->validateInput($input, true);
        if ($errors !== []) {
            return $this->respondWithErrors($errors, 422);
        }

        try {
            return $this->respondWithData($this->service->update($id, $tenantId, $input));
        } catch (RuntimeException $e) {
            return $this->respondWithError('SUB_REGION_INVALID', $e->getMessage(), null, 422);
        }
    }

    public function delete(int $id): JsonResponse
    {
        $this->requireAuth();
        $this->requireAdmin();

        if (! TenantContext::hasFeature('caring_community')) {
            return $this->respondWithError('FEATURE_DISABLED', __('api.service_unavailable'), null, 403);
        }

        $tenantId = TenantContext::getId();
        if ($this->service->get($id, $tenantId) === null) {
            return $this->respondNotFound(__('api.resource_not_found'));
        }

        $this->service->delete($id, $tenantId);

        return $this->respondWithData(['deleted' => true]);
    }

    /**
     * @return array<int, array{code: string, message: string, field?: string}>
     */
    private function validateInput(array $input, bool $partial): array
    {
        $required = $partial ? 'sometimes|required' : 'required';
        $validator = Validator::make($input, [
            'name' => $required . '|string|max:255',
            'slug' => 'sometimes|nullable|string|max:255',
            'type' => 'sometimes|string|in:quartier,ortsteil,municipality,canton,other',
            'description' => 'nullable|string',
            'postal_codes' => 'nullable',
            'boundary_geojson' => 'nullable|array',
            'center_latitude' => 'nullable|numeric|min:-90|max:90',
            'center_longitude' => 'nullable|numeric|min:-180|max:180',
            'status' => 'sometimes|string|in:active,inactive',
        ]);

        if (! $validator->fails()) {
            return [];
        }

        $errors = [];
        foreach ($validator->errors()->toArray() as $field => $messages) {
            $errors[] = [
                'code' => 'VALIDATION_ERROR',
                'message' => $messages[0],
                'field' => $field,
            ];
        }

        return $errors;
    }
}

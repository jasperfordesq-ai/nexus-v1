<?php
// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

declare(strict_types=1);

namespace App\Http\Controllers\Api\Admin;

use App\Core\TenantContext;
use App\Http\Controllers\Api\BaseApiController;
use App\Services\CaringCommunity\CommercialBoundaryService;
use Illuminate\Http\JsonResponse;

/**
 * AG82 — Commercial Boundary Map admin controller.
 *
 * Exposes the canonical capability classification matrix and per-tenant
 * override mutations. All routes are admin-gated and require the
 * caring_community feature to be enabled on the calling tenant.
 */
class CommercialBoundaryController extends BaseApiController
{
    protected bool $isV2Api = true;

    public function __construct(
        private readonly CommercialBoundaryService $service,
    ) {
    }

    /**
     * GET /v2/admin/caring-community/commercial-boundary
     *
     * Return the full classification matrix with this tenant's overrides
     * applied.
     */
    public function matrix(): JsonResponse
    {
        $this->requireAdmin();

        if (!TenantContext::hasFeature('caring_community')) {
            return $this->respondWithError('FEATURE_DISABLED', __('api.service_unavailable'), null, 403);
        }

        $tenantId = TenantContext::getId();

        return $this->respondWithData($this->service->matrix($tenantId));
    }

    /**
     * PUT /v2/admin/caring-community/commercial-boundary/override
     *
     * Body:
     *   { "capability_key": "<key>", "classification": "agpl_public" | "tenant_config"
     *     | "private_deployment" | "commercial" | null }
     *
     * Pass classification = null to clear an override and revert to the
     * canonical default.
     */
    public function setOverride(): JsonResponse
    {
        $this->requireAdmin();

        if (!TenantContext::hasFeature('caring_community')) {
            return $this->respondWithError('FEATURE_DISABLED', __('api.service_unavailable'), null, 403);
        }

        $capabilityKey = $this->input('capability_key');
        if (!is_string($capabilityKey) || $capabilityKey === '') {
            return $this->respondWithError('VALIDATION_REQUIRED_FIELD', 'capability_key is required', 'capability_key', 422);
        }

        // classification may be a string or explicit null (clear). We must
        // distinguish "field not present" (also treat as clear) from
        // "field present but malformed".
        $rawClassification = $this->input('classification', null);
        $classification = null;
        if ($rawClassification !== null) {
            if (!is_string($rawClassification)) {
                return $this->respondWithError('VALIDATION_INVALID', 'classification must be a string or null', 'classification', 422);
            }
            $classification = $rawClassification;
        }

        $tenantId = TenantContext::getId();
        $result = $this->service->setOverride($tenantId, $capabilityKey, $classification);

        if (isset($result['errors'])) {
            $errors = [];
            foreach ($result['errors'] as $err) {
                $errors[] = [
                    'code'    => 'VALIDATION_ERROR',
                    'message' => $err['message'],
                    'field'   => $err['field'],
                ];
            }
            return $this->respondWithErrors($errors, 422);
        }

        return $this->respondWithData($result['matrix'] ?? []);
    }
}

/*
 * Routes to register in routes/api.php:
 *   GET /v2/admin/caring-community/commercial-boundary => matrix
 *   PUT /v2/admin/caring-community/commercial-boundary/override => setOverride
 */

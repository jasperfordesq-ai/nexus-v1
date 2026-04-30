<?php
// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Core\TenantContext;
use App\Services\CaringCommunity\CivicDigestService;
use Illuminate\Http\JsonResponse;

/**
 * AG90 — Personalised Civic Information Filter and Regional Digest.
 *
 * Member-facing endpoints: GET digest, GET/PUT user prefs.
 * Admin endpoints: GET/PUT tenant default cadence.
 */
class CivicDigestController extends BaseApiController
{
    protected bool $isV2Api = true;

    public function __construct(
        private readonly CivicDigestService $service,
    ) {
    }

    public function myDigest(): JsonResponse
    {
        $userId = $this->requireAuth();

        if (! TenantContext::hasFeature('caring_community')) {
            return $this->respondWithError('FEATURE_DISABLED', __('api.service_unavailable'), null, 403);
        }

        $tenantId = TenantContext::getId();
        $limit = $this->queryInt('limit', 50, 1, 100) ?? 50;

        $items = $this->service->digestForMember($tenantId, $userId, $limit);
        $prefs = $this->service->getUserPrefs($tenantId, $userId);

        return $this->respondWithData([
            'items' => $items,
            'prefs' => $prefs,
            'tenant_default_cadence' => $this->service->getTenantCadence($tenantId),
        ]);
    }

    public function myPrefs(): JsonResponse
    {
        $userId = $this->requireAuth();

        if (! TenantContext::hasFeature('caring_community')) {
            return $this->respondWithError('FEATURE_DISABLED', __('api.service_unavailable'), null, 403);
        }

        $tenantId = TenantContext::getId();

        return $this->respondWithData([
            'prefs' => $this->service->getUserPrefs($tenantId, $userId),
            'tenant_default_cadence' => $this->service->getTenantCadence($tenantId),
        ]);
    }

    public function updateMyPrefs(): JsonResponse
    {
        $userId = $this->requireAuth();

        if (! TenantContext::hasFeature('caring_community')) {
            return $this->respondWithError('FEATURE_DISABLED', __('api.service_unavailable'), null, 403);
        }

        $tenantId = TenantContext::getId();

        $allInput = $this->getAllInput();
        $payload = [];
        if (array_key_exists('cadence', $allInput)) {
            $payload['cadence'] = $allInput['cadence'];
        }
        if (array_key_exists('preferred_sub_region_id', $allInput)) {
            $payload['preferred_sub_region_id'] = $allInput['preferred_sub_region_id'];
        }
        if (array_key_exists('opt_out_sources', $allInput)) {
            $payload['opt_out_sources'] = $allInput['opt_out_sources'];
        }

        $result = $this->service->setUserPrefs($tenantId, $userId, $payload);

        if (isset($result['errors'])) {
            $errors = [];
            foreach ($result['errors'] as $err) {
                $errors[] = [
                    'code' => 'VALIDATION_ERROR',
                    'message' => $err['message'],
                    'field' => $err['field'],
                ];
            }
            return $this->respondWithErrors($errors, 422);
        }

        return $this->respondWithData(['prefs' => $result['prefs']]);
    }

    public function tenantCadence(): JsonResponse
    {
        $this->requireAdmin();

        if (! TenantContext::hasFeature('caring_community')) {
            return $this->respondWithError('FEATURE_DISABLED', __('api.service_unavailable'), null, 403);
        }

        $tenantId = TenantContext::getId();

        return $this->respondWithData([
            'cadence' => $this->service->getTenantCadence($tenantId),
        ]);
    }

    public function setTenantCadence(): JsonResponse
    {
        $this->requireAdmin();

        if (! TenantContext::hasFeature('caring_community')) {
            return $this->respondWithError('FEATURE_DISABLED', __('api.service_unavailable'), null, 403);
        }

        $tenantId = TenantContext::getId();
        $cadence = (string) $this->input('cadence', '');

        $result = $this->service->setTenantCadence($tenantId, $cadence);

        if (isset($result['errors'])) {
            $errors = [];
            foreach ($result['errors'] as $err) {
                $errors[] = [
                    'code' => 'VALIDATION_ERROR',
                    'message' => $err['message'],
                    'field' => $err['field'],
                ];
            }
            return $this->respondWithErrors($errors, 422);
        }

        return $this->respondWithData(['cadence' => $result['cadence']]);
    }
}

/*
 * Routes to register in routes/api.php (member routes need ->withoutMiddleware(EnsureIsAdmin)):
 *   GET  /v2/caring-community/digest => myDigest
 *   GET  /v2/caring-community/digest/prefs => myPrefs
 *   PUT  /v2/caring-community/digest/prefs => updateMyPrefs
 *   GET  /v2/admin/caring-community/digest/cadence => tenantCadence
 *   PUT  /v2/admin/caring-community/digest/cadence => setTenantCadence
 */

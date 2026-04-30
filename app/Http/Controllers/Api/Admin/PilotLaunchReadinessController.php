<?php
// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

declare(strict_types=1);

namespace App\Http\Controllers\Api\Admin;

use App\Core\TenantContext;
use App\Http\Controllers\Api\BaseApiController;
use App\Services\CaringCommunity\PilotLaunchReadinessService;
use Illuminate\Http\JsonResponse;

/**
 * AG95 — Pilot Launch Readiness Dashboard admin endpoints.
 *
 * Aggregates AG80/AG81/AG82/AG83/AG84/AG85/AG87 statuses into a single go/no-go
 * report so a coordinator can see whether the pilot is ready to launch without
 * clicking through seven separate admin screens.
 */
class PilotLaunchReadinessController extends BaseApiController
{
    protected bool $isV2Api = true;

    public function __construct(
        private readonly PilotLaunchReadinessService $service,
    ) {
    }

    /** GET /v2/admin/caring-community/launch-readiness */
    public function index(): JsonResponse
    {
        $guard = $this->guard();
        if ($guard !== null) {
            return $guard;
        }

        return $this->respondWithData(
            $this->service->report(TenantContext::getId()),
        );
    }

    /** POST /v2/admin/caring-community/launch-readiness/acknowledge-boundary */
    public function acknowledgeBoundary(): JsonResponse
    {
        $guard = $this->guard();
        if ($guard !== null) {
            return $guard;
        }

        $result = $this->service->acknowledgeBoundary(TenantContext::getId());

        if (isset($result['error'])) {
            return $this->respondWithError(
                'STORAGE_UNAVAILABLE',
                'Tenant settings storage is not available.',
                null,
                503,
            );
        }

        return $this->respondWithData([
            'acknowledged' => true,
            'report'       => $this->service->report(TenantContext::getId()),
        ]);
    }

    private function guard(): ?JsonResponse
    {
        $this->requireAdmin();

        if (!TenantContext::hasFeature('caring_community')) {
            return $this->respondForbidden('Caring Community feature is not enabled for this tenant.');
        }

        return null;
    }
}

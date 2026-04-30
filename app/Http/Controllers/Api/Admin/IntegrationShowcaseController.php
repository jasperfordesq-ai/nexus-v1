<?php
// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

declare(strict_types=1);

namespace App\Http\Controllers\Api\Admin;

use App\Core\TenantContext;
use App\Http\Controllers\Api\BaseApiController;
use App\Services\CaringCommunity\IntegrationShowcaseService;
use Illuminate\Http\JsonResponse;

/**
 * AG93 — Open-Standards and Integration Showcase admin endpoint.
 *
 * Returns a static manifest of every public integration surface the
 * platform exposes (OpenAPI, Partner API v1, OAuth, webhooks, federation
 * aggregates, sample payloads, and a partner-onboarding checklist).
 */
class IntegrationShowcaseController extends BaseApiController
{
    protected bool $isV2Api = true;

    public function __construct(
        private readonly IntegrationShowcaseService $service,
    ) {}

    public function index(): JsonResponse
    {
        $this->requireAdmin();

        if (!TenantContext::hasFeature('caring_community')) {
            return $this->respondWithError(
                'FEATURE_DISABLED',
                __('api.service_unavailable'),
                null,
                403,
            );
        }

        return $this->respondWithData($this->service->showcase());
    }
}

/*
 * Routes to register in routes/api.php:
 *   GET /v2/admin/caring-community/integration-showcase => index
 */

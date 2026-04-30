<?php
// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Core\TenantContext;
use App\Services\CaringCommunity\LeadNurtureService;
use Illuminate\Http\JsonResponse;

/**
 * Public/member capture endpoint for AG94 lead nurture.
 * No auth required (intentionally — this is top-of-funnel capture from the
 * marketing site). Validates email, segment, and explicit consent.
 */
class LeadCaptureController extends BaseApiController
{
    protected bool $isV2Api = true;

    public function __construct(
        private readonly LeadNurtureService $service,
    ) {}

    public function capture(): JsonResponse
    {
        if (!TenantContext::hasFeature('caring_community')) {
            return $this->respondWithError('FEATURE_DISABLED', __('api.service_unavailable'), null, 403);
        }

        $payload = (array) request()->all();
        $sourceIp = (string) request()->ip();

        $result = $this->service->capture(TenantContext::getId(), $payload, $sourceIp);

        if (isset($result['errors']) && $result['errors'] !== []) {
            return $this->respondWithErrors(array_map(
                fn ($e) => ['code' => 'VALIDATION_ERROR', 'message' => $e['message'], 'field' => $e['field']],
                $result['errors'],
            ), 422);
        }

        return $this->respondWithData([
            'contact_id' => $result['contact']['id']    ?? null,
            'duplicate'  => $result['duplicate']         ?? false,
            'segment'    => $result['contact']['segment'] ?? null,
            'stage'      => $result['contact']['stage']   ?? null,
        ]);
    }
}

/*
 * Routes to register in routes/api.php (NO admin middleware):
 *   POST /v2/caring-community/leads/capture => capture (->withoutMiddleware EnsureIsAdmin)
 */

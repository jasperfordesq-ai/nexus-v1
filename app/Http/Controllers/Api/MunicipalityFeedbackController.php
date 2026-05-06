<?php
// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Core\TenantContext;
use App\Services\CaringCommunity\MunicipalityFeedbackService;
use Illuminate\Http\JsonResponse;

/**
 * MunicipalityFeedbackController — AG92 member endpoints.
 *
 * Members may submit feedback (questions / ideas / issue_reports / sentiment)
 * and view their own previous submissions. Submission requires authentication
 * even when is_anonymous is true — anonymity hides the submitter from admin
 * list views, not from abuse-prevention auditing.
 *
 * Routes (registered by orchestrator in routes/api.php):
 *   POST /v2/caring-community/feedback      => submit (->withoutMiddleware EnsureIsAdmin)
 *   GET  /v2/caring-community/feedback/mine => myList (->withoutMiddleware EnsureIsAdmin)
 */
class MunicipalityFeedbackController extends BaseApiController
{
    protected bool $isV2Api = true;

    public function __construct(private readonly MunicipalityFeedbackService $service)
    {
    }

    /**
     * POST /v2/caring-community/feedback
     */
    public function submit(): JsonResponse
    {
        $disabled = $this->guardCaringCommunity();
        if ($disabled) {
            return $disabled;
        }

        $userId = $this->requireAuth();
        $tenantId = $this->getTenantId();

        $payload = [
            'category'      => $this->input('category'),
            'subject'       => $this->input('subject'),
            'body'          => $this->input('body'),
            'sentiment_tag' => $this->input('sentiment_tag'),
            'sub_region_id' => $this->input('sub_region_id'),
            'is_anonymous'  => $this->input('is_anonymous', false),
            'is_public'     => $this->input('is_public', false),
        ];

        $result = $this->service->submit($tenantId, $userId, $payload);

        if (isset($result['errors'])) {
            return $this->respondWithErrors($result['errors'], 422);
        }

        return $this->respondWithData($result['feedback'] ?? null, null, 201);
    }

    /**
     * GET /v2/caring-community/feedback/mine
     */
    public function myList(): JsonResponse
    {
        $disabled = $this->guardCaringCommunity();
        if ($disabled) {
            return $disabled;
        }

        $userId = $this->requireAuth();
        $tenantId = $this->getTenantId();

        $limit = $this->queryInt('limit', 50, 1, 200) ?? 50;

        $items = $this->service->listForMember($tenantId, $userId, $limit);

        return $this->respondWithData($items);
    }

    private function guardCaringCommunity(): ?JsonResponse
    {
        if (!TenantContext::hasFeature('caring_community')) {
            return $this->respondWithError('FEATURE_DISABLED', __('api.service_unavailable'), null, 403);
        }

        return null;
    }
}

<?php
// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Core\TenantContext;
use App\Services\CaringCommunityWorkflowService;
use Illuminate\Http\JsonResponse;

class AdminCaringCommunityController extends BaseApiController
{
    protected bool $isV2Api = true;

    public function __construct(private readonly CaringCommunityWorkflowService $workflowService)
    {
    }

    public function workflow(): JsonResponse
    {
        $this->requireAdmin();

        if (!TenantContext::hasFeature('caring_community')) {
            return $this->respondWithError('FEATURE_DISABLED', __('api.service_unavailable'), null, 403);
        }

        return $this->respondWithData($this->workflowService->summary(TenantContext::getId()));
    }
}

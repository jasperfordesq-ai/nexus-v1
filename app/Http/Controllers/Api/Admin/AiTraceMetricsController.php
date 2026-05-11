<?php
// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Api\BaseApiController;
use App\Services\AI\AiTurnTraceService;
use Illuminate\Http\JsonResponse;

class AiTraceMetricsController extends BaseApiController
{
    protected bool $isV2Api = true;

    public function __construct(private readonly AiTurnTraceService $traces) {}

    /** GET /api/v2/admin/ai-traces/metrics?days=30 */
    public function metrics(): JsonResponse
    {
        $this->requireAdmin();
        $tenantId = $this->getTenantId();
        $days = $this->queryInt('days', 30, 1, 365);
        return $this->respondWithData($this->traces->metricsFor($tenantId, $days));
    }
}

<?php
// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

namespace App\Http\Controllers\Api;

use App\Services\MetricsService;
use Illuminate\Http\JsonResponse;

/**
 * MetricsController — Platform metrics collection and reporting.
 */
class MetricsController extends BaseApiController
{
    protected bool $isV2Api = true;

    public function __construct(
        private readonly MetricsService $metricsService,
    ) {}

    /**
     * POST /api/v2/metrics
     *
     * Record a metric event (page view, feature usage, performance, etc.).
     * Body: event (required), properties (optional object), duration_ms (optional).
     */
    public function store(): JsonResponse
    {
        $userId = $this->getOptionalUserId();
        $tenantId = $this->getTenantId();
        $this->rateLimit('metrics_store', 60, 60);

        $event = $this->requireInput('event');
        $properties = $this->input('properties', []);
        $durationMs = $this->inputInt('duration_ms');

        $this->metricsService->record($tenantId, $event, [
            'user_id' => $userId,
            'properties' => $properties,
            'duration_ms' => $durationMs,
            'ip' => request()->ip(),
            'user_agent' => request()->userAgent(),
        ]);

        return $this->respondWithData(['recorded' => true], null, 201);
    }

    /**
     * GET /api/v2/metrics/summary
     *
     * Get aggregated metrics summary (admin only).
     * Query params: period (day|week|month), start_date, end_date.
     */
    public function summary(): JsonResponse
    {
        $this->requireAdmin();
        $tenantId = $this->getTenantId();

        $period = $this->query('period', 'week');
        $startDate = $this->query('start_date');
        $endDate = $this->query('end_date');

        $data = $this->metricsService->getSummary($tenantId, $period, $startDate, $endDate);

        return $this->respondWithData($data);
    }
}

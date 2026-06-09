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

        if (!is_string($event) || !preg_match('/^[A-Za-z0-9_.:-]{1,80}$/', $event)) {
            return $this->respondWithError('VALIDATION_ERROR', __('api.metrics_event_invalid'), 'event', 422);
        }

        if (!is_array($properties)) {
            return $this->respondWithError('VALIDATION_ERROR', __('api.metrics_properties_invalid'), 'properties', 422);
        }

        $properties = $this->sanitizeProperties($properties);
        if (strlen(json_encode($properties, JSON_THROW_ON_ERROR)) > 4096) {
            return $this->respondWithError('VALIDATION_ERROR', __('api.metrics_properties_too_large'), 'properties', 422);
        }

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
     * @param array<string|int,mixed> $properties
     * @return array<string,mixed>
     */
    private function sanitizeProperties(array $properties, int $depth = 0): array
    {
        if ($depth >= 2) {
            return [];
        }

        $sanitized = [];
        foreach (array_slice($properties, 0, 25, true) as $key => $value) {
            if (!is_string($key) && !is_int($key)) {
                continue;
            }
            $key = preg_replace('/[^A-Za-z0-9_.:-]/', '_', (string) $key) ?? '';
            $key = substr($key, 0, 80);
            if ($key === '') {
                continue;
            }

            if (is_array($value)) {
                $sanitized[$key] = $this->sanitizeProperties($value, $depth + 1);
                continue;
            }
            if (is_string($value)) {
                $sanitized[$key] = substr($value, 0, 255);
                continue;
            }
            if (is_bool($value) || is_int($value) || is_float($value) || $value === null) {
                $sanitized[$key] = $value;
            }
        }

        return $sanitized;
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

<?php
// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

namespace Nexus\Controllers\Api;

use Nexus\Services\PerformanceMonitorService;

/**
 * Metrics API Controller
 *
 * Handles frontend performance metrics collection
 */
class MetricsApiController extends BaseApiController
{
    /**
     * Store frontend performance metrics
     *
     * POST /api/v2/metrics
     */
    public function store()
    {
        // Performance monitoring must be enabled
        if (!PerformanceMonitorService::isEnabled()) {
            $this->jsonResponse(['success' => true, 'message' => 'Performance monitoring is disabled'], 204);
            if (!defined('TESTING')) { if (!defined('TESTING')) { exit; } }
        }

        // Get JSON input
        $input = json_decode(file_get_contents('php://input'), true);

        if (!isset($input['metrics']) || !is_array($input['metrics'])) {
            $this->jsonResponse(['success' => false, 'error' => 'Invalid metrics payload'], 400);
            if (!defined('TESTING')) { if (!defined('TESTING')) { exit; } }
        }

        // Get user ID if authenticated (optional)
        $userId = $this->getOptionalUserId();

        // Add user ID and page info to each metric
        $metrics = $input['metrics'];
        foreach ($metrics as $metric) {
            if (!is_array($metric)) continue;

            $metricData = $metric;
            $metricData['user_id'] = $userId;
            $metricData['page_url'] = $input['page_url'] ?? null;
            $metricData['user_agent'] = $input['user_agent'] ?? $_SERVER['HTTP_USER_AGENT'] ?? null;

            PerformanceMonitorService::trackFrontendMetrics($metricData);
        }

        $this->jsonResponse([
            'success' => true,
            'message' => 'Metrics recorded',
            'count' => count($metrics)
        ], 201);
        if (!defined('TESTING')) { if (!defined('TESTING')) { exit; } }
    }

    /**
     * Get performance summary (admin only)
     *
     * GET /api/v2/metrics/summary?hours=24
     */
    public function summary()
    {
        // Require admin authentication
        $this->requireAdmin();

        // Get hours parameter
        $hours = isset($_GET['hours']) ? (int)$_GET['hours'] : 24;
        if ($hours < 1) $hours = 1;
        if ($hours > 168) $hours = 168; // Max 7 days

        $summary = PerformanceMonitorService::getSummary($hours);

        $this->jsonResponse([
            'success' => true,
            'data' => $summary,
            'hours' => $hours
        ]);
        if (!defined('TESTING')) { if (!defined('TESTING')) { exit; } }
    }
}

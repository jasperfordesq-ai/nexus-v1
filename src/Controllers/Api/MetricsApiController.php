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
        header('Content-Type: application/json');

        // Performance monitoring must be enabled
        if (!PerformanceMonitorService::isEnabled()) {
            http_response_code(204); // No Content
            echo json_encode(['success' => true, 'message' => 'Performance monitoring is disabled']);
            exit;
        }

        // Get JSON input
        $input = json_decode(file_get_contents('php://input'), true);

        if (!isset($input['metrics']) || !is_array($input['metrics'])) {
            http_response_code(400);
            echo json_encode(['success' => false, 'error' => 'Invalid metrics payload']);
            exit;
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

        http_response_code(201);
        echo json_encode([
            'success' => true,
            'message' => 'Metrics recorded',
            'count' => count($metrics)
        ]);
        exit;
    }

    /**
     * Get performance summary (admin only)
     *
     * GET /api/v2/metrics/summary?hours=24
     */
    public function summary()
    {
        header('Content-Type: application/json');

        // Require admin authentication
        $this->requireAdmin();

        // Get hours parameter
        $hours = isset($_GET['hours']) ? (int)$_GET['hours'] : 24;
        if ($hours < 1) $hours = 1;
        if ($hours > 168) $hours = 168; // Max 7 days

        $summary = PerformanceMonitorService::getSummary($hours);

        echo json_encode([
            'success' => true,
            'data' => $summary,
            'hours' => $hours
        ]);
        exit;
    }
}

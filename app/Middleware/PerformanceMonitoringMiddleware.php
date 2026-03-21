<?php
// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

namespace App\Middleware;

use App\Services\PerformanceMonitorService;

/**
 * Performance Monitoring Middleware
 *
 * Tracks request-level performance metrics:
 * - Request duration
 * - Memory usage
 * - Database query count
 * - Slow endpoints
 *
 * Automatically injects performance headers in development mode.
 */
class PerformanceMonitoringMiddleware
{
    /**
     * Initialize performance tracking for the current request
     */
    public static function before(): void
    {
        if (!PerformanceMonitorService::isEnabled()) {
            return;
        }

        PerformanceMonitorService::startRequest();
    }

    /**
     * Finalize performance tracking and log metrics
     */
    public static function after(): void
    {
        if (!PerformanceMonitorService::isEnabled()) {
            return;
        }

        PerformanceMonitorService::endRequest();

        // In debug mode, add performance headers to response
        if (getenv('DEBUG') === 'true' || getenv('DB_PROFILING') === 'true') {
            self::addPerformanceHeaders();
        }
    }

    /**
     * Add performance metrics to response headers (dev mode only)
     */
    private static function addPerformanceHeaders(): void
    {
        // Get database query stats if profiling is enabled
        // Uses Laravel's query log when DB profiling is active
        $queryLog = \Illuminate\Support\Facades\DB::getQueryLog();
        if (!empty($queryLog)) {
            $totalQueries = count($queryLog);
            $totalTime = array_sum(array_column($queryLog, 'time'));
            $slowest = !empty($queryLog) ? max(array_column($queryLog, 'time')) : 0;

            header('X-Query-Count: ' . $totalQueries);
            header('X-Query-Time: ' . round($totalTime, 2) . 'ms');

            if ($slowest > 0) {
                header('X-Slowest-Query: ' . round($slowest, 2) . 'ms');
            }
        }

        // Add memory usage
        $memoryMB = round(memory_get_peak_usage(true) / 1024 / 1024, 2);
        header('X-Memory-Peak-MB: ' . $memoryMB);
    }
}

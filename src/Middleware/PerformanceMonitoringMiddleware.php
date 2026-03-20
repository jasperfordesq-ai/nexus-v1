<?php
// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

namespace Nexus\Middleware;

use App\Middleware\PerformanceMonitoringMiddleware as AppPerformanceMonitoringMiddleware;

/**
 * Legacy delegate — real implementation is now in App\Middleware\PerformanceMonitoringMiddleware.
 *
 * @deprecated Use App\Middleware\PerformanceMonitoringMiddleware directly.
 */
class PerformanceMonitoringMiddleware
{
    public static function before(): void
    {
        AppPerformanceMonitoringMiddleware::before();
    }

    public static function after(): void
    {
        AppPerformanceMonitoringMiddleware::after();
    }
}

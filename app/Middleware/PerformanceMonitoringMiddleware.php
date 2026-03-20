<?php
// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

namespace App\Middleware;

use Nexus\Middleware\PerformanceMonitoringMiddleware as LegacyPerformanceMonitoringMiddleware;

/**
 * App-namespace wrapper for Nexus\Middleware\PerformanceMonitoringMiddleware.
 *
 * Delegates to the legacy implementation.
 */
class PerformanceMonitoringMiddleware
{
    public static function before(): void
    {
        if (!class_exists(LegacyPerformanceMonitoringMiddleware::class)) { return; }
        LegacyPerformanceMonitoringMiddleware::before();
    }

    public static function after(): void
    {
        if (!class_exists(LegacyPerformanceMonitoringMiddleware::class)) { return; }
        LegacyPerformanceMonitoringMiddleware::after();
    }
}

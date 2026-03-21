<?php
// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

namespace Tests\Laravel\Unit\Middleware;

use App\Middleware\PerformanceMonitoringMiddleware;
use Tests\Laravel\TestCase;

/**
 * Tests for PerformanceMonitoringMiddleware.
 *
 * This middleware delegates to PerformanceMonitorService which is a legacy service
 * that may not be available in all environments. Tests verify the middleware
 * can be invoked and are marked as skipped when the dependency is unavailable.
 */
class PerformanceMonitoringMiddlewareTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        if (!class_exists(\App\Services\PerformanceMonitorService::class)) {
            $this->markTestSkipped('PerformanceMonitorService not available (legacy class removed during migration)');
        }
    }

    public function test_before_can_be_invoked(): void
    {
        // The before() method should not throw when called
        PerformanceMonitoringMiddleware::before();
        $this->assertTrue(true);
    }

    public function test_after_can_be_invoked(): void
    {
        // Ensure debug mode is off to avoid header() calls
        putenv('DEBUG=false');
        putenv('DB_PROFILING=false');

        PerformanceMonitoringMiddleware::after();
        $this->assertTrue(true);
    }

    public function test_before_and_after_lifecycle(): void
    {
        PerformanceMonitoringMiddleware::before();

        putenv('DEBUG=false');
        putenv('DB_PROFILING=false');

        PerformanceMonitoringMiddleware::after();
        $this->assertTrue(true);
    }
}

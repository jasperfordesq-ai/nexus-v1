<?php
// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

namespace App\Services;

/**
 * PerformanceMonitorService — Laravel DI wrapper for legacy \Nexus\Services\PerformanceMonitorService.
 *
 * Provides dependency-injectable access to the legacy static service methods.
 */
class PerformanceMonitorService
{
    public function __construct()
    {
    }

    /**
     * Delegates to legacy PerformanceMonitorService::isEnabled().
     */
    public function isEnabled(): bool
    {
        return \Nexus\Services\PerformanceMonitorService::isEnabled();
    }

    /**
     * Delegates to legacy PerformanceMonitorService::startRequest().
     */
    public function startRequest(): void
    {
        \Nexus\Services\PerformanceMonitorService::startRequest();
    }

    /**
     * Delegates to legacy PerformanceMonitorService::endRequest().
     */
    public function endRequest(): void
    {
        \Nexus\Services\PerformanceMonitorService::endRequest();
    }

    /**
     * Delegates to legacy PerformanceMonitorService::trackQuery().
     */
    public function trackQuery(string $sql, array $params, float $duration): void
    {
        \Nexus\Services\PerformanceMonitorService::trackQuery($sql, $params, $duration);
    }

    /**
     * Delegates to legacy PerformanceMonitorService::trackMetric().
     */
    public function trackMetric(string $name, $value, array $context = []): void
    {
        \Nexus\Services\PerformanceMonitorService::trackMetric($name, $value, $context);
    }
}

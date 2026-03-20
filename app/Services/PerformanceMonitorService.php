<?php
// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

namespace App\Services;

/**
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
        \Illuminate\Support\Facades\Log::warning('Legacy delegation removed: ' . __METHOD__);
        return false;
    }

    /**
     * Delegates to legacy PerformanceMonitorService::startRequest().
     */
    public function startRequest(): void
    {
        \Illuminate\Support\Facades\Log::warning('Legacy delegation removed: ' . __METHOD__);
    }

    /**
     * Delegates to legacy PerformanceMonitorService::endRequest().
     */
    public function endRequest(): void
    {
        \Illuminate\Support\Facades\Log::warning('Legacy delegation removed: ' . __METHOD__);
    }

    /**
     * Delegates to legacy PerformanceMonitorService::trackQuery().
     */
    public function trackQuery(string $sql, array $params, float $duration): void
    {
        \Illuminate\Support\Facades\Log::warning('Legacy delegation removed: ' . __METHOD__);
    }

    /**
     * Static convenience wrapper for trackQuery().
     *
     * Called from App\Core\Database::query() which cannot resolve a DI instance.
     * Silently no-ops to avoid breaking query execution when monitoring is not configured.
     */
    public static function trackQueryStatic(string $sql, array $params, float $duration): void
    {
        // No-op: performance monitoring is not yet wired in the Laravel migration.
        // When a real implementation is added, resolve via the container:
        //   app(static::class)->trackQuery($sql, $params, $duration);
    }

    /**
     * Delegates to legacy PerformanceMonitorService::trackMetric().
     */
    public function trackMetric(string $name, $value, array $context = []): void
    {
        \Illuminate\Support\Facades\Log::warning('Legacy delegation removed: ' . __METHOD__);
    }
}

<?php
// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

namespace Nexus\Services;

use Nexus\Core\TenantContext;

/**
 * Performance Monitoring Service
 *
 * Tracks and logs application performance metrics:
 * - Slow database queries (>100ms)
 * - Slow API endpoints (>500ms)
 * - Memory usage spikes (>50MB)
 * - Request-level metrics
 */
class PerformanceMonitorService
{
    /**
     * Configuration constants
     */
    private const LOG_FILE = 'performance.log';
    private const SLOW_QUERY_THRESHOLD_MS = 100;
    private const SLOW_REQUEST_THRESHOLD_MS = 500;
    private const MEMORY_SPIKE_THRESHOLD_MB = 50;
    private const N_PLUS_ONE_QUERY_THRESHOLD = 10;

    /**
     * Performance data for current request
     */
    private static $requestStartTime = null;
    private static $requestStartMemory = null;
    private static $queryCount = 0;
    private static $slowQueries = [];
    private static $enabled = null;

    /**
     * Check if performance monitoring is enabled
     */
    public static function isEnabled(): bool
    {
        if (self::$enabled === null) {
            self::$enabled = getenv('PERFORMANCE_MONITORING_ENABLED') === 'true';
        }
        return self::$enabled;
    }

    /**
     * Initialize request tracking
     */
    public static function startRequest(): void
    {
        if (!self::isEnabled()) {
            return;
        }

        self::$requestStartTime = microtime(true);
        self::$requestStartMemory = memory_get_usage(true);
        self::$queryCount = 0;
        self::$slowQueries = [];
    }

    /**
     * Log request completion metrics
     */
    public static function endRequest(): void
    {
        if (!self::isEnabled() || self::$requestStartTime === null) {
            return;
        }

        $duration = (microtime(true) - self::$requestStartTime) * 1000; // Convert to ms
        $memoryUsed = (memory_get_usage(true) - self::$requestStartMemory) / 1024 / 1024; // Convert to MB
        $peakMemory = memory_get_peak_usage(true) / 1024 / 1024; // MB

        $data = [
            'type' => 'request',
            'timestamp' => date('Y-m-d H:i:s'),
            'endpoint' => $_SERVER['REQUEST_URI'] ?? 'unknown',
            'method' => $_SERVER['REQUEST_METHOD'] ?? 'unknown',
            'duration_ms' => round($duration, 2),
            'memory_mb' => round($memoryUsed, 2),
            'peak_memory_mb' => round($peakMemory, 2),
            'query_count' => self::$queryCount,
            'tenant_id' => TenantContext::getId(),
            'user_agent' => $_SERVER['HTTP_USER_AGENT'] ?? null,
        ];

        // Add slow queries if any
        if (!empty(self::$slowQueries)) {
            $data['slow_queries'] = self::$slowQueries;
        }

        // Add warnings
        $warnings = [];
        if ($duration > self::getSlowRequestThreshold()) {
            $warnings[] = 'SLOW_REQUEST';
        }
        if ($memoryUsed > self::getMemorySpikeThreshold()) {
            $warnings[] = 'MEMORY_SPIKE';
        }
        if (self::$queryCount > self::getNPlusOneQueryThreshold()) {
            $warnings[] = 'POSSIBLE_N_PLUS_ONE';
        }

        if (!empty($warnings)) {
            $data['warnings'] = $warnings;
        }

        // Log if there are warnings or if verbose logging is enabled
        if (!empty($warnings) || getenv('PERFORMANCE_LOG_LEVEL') === 'verbose') {
            self::log($data);
        }
    }

    /**
     * Track a database query
     *
     * @param string $sql Query SQL
     * @param array $params Query parameters
     * @param float $duration Duration in seconds
     */
    public static function trackQuery(string $sql, array $params, float $duration): void
    {
        if (!self::isEnabled()) {
            return;
        }

        self::$queryCount++;

        $durationMs = $duration * 1000;

        // Check if query is slow
        if ($durationMs > self::getSlowQueryThreshold()) {
            // Get calling location from backtrace
            $backtrace = debug_backtrace(DEBUG_BACKTRACE_IGNORE_ARGS, 5);
            $caller = null;

            // Find first non-Database class caller
            foreach ($backtrace as $trace) {
                if (isset($trace['class']) &&
                    $trace['class'] !== 'Nexus\Core\Database' &&
                    strpos($trace['class'], 'Nexus') === 0) {
                    $caller = [
                        'class' => $trace['class'],
                        'function' => $trace['function'] ?? 'unknown',
                        'file' => basename($trace['file'] ?? 'unknown'),
                        'line' => $trace['line'] ?? 0,
                    ];
                    break;
                }
            }

            $queryData = [
                'type' => 'slow_query',
                'timestamp' => date('Y-m-d H:i:s'),
                'duration_ms' => round($durationMs, 2),
                'sql' => trim(preg_replace('/\s+/', ' ', $sql)),
                'params' => $params,
                'caller' => $caller,
                'tenant_id' => TenantContext::getId(),
            ];

            self::$slowQueries[] = $queryData;
            self::log($queryData);
        }
    }

    /**
     * Track a custom metric
     *
     * @param string $name Metric name
     * @param mixed $value Metric value
     * @param array $context Additional context
     */
    public static function trackMetric(string $name, $value, array $context = []): void
    {
        if (!self::isEnabled()) {
            return;
        }

        $data = [
            'type' => 'custom_metric',
            'timestamp' => date('Y-m-d H:i:s'),
            'metric_name' => $name,
            'value' => $value,
            'tenant_id' => TenantContext::getId(),
        ];

        if (!empty($context)) {
            $data['context'] = $context;
        }

        self::log($data);
    }

    /**
     * Track frontend performance metrics (from /api/v2/metrics endpoint)
     *
     * @param array $metrics Frontend metrics payload
     */
    public static function trackFrontendMetrics(array $metrics): void
    {
        if (!self::isEnabled()) {
            return;
        }

        $data = [
            'type' => 'frontend_metric',
            'timestamp' => date('Y-m-d H:i:s'),
            'tenant_id' => TenantContext::getId(),
            'user_id' => $metrics['user_id'] ?? null,
        ];

        // Merge frontend metrics
        $data = array_merge($data, $metrics);

        self::log($data);
    }

    /**
     * Write performance data to log file
     *
     * @param array $data Performance data
     */
    private static function log(array $data): void
    {
        $logDir = __DIR__ . '/../../storage/logs';
        $logFile = $logDir . '/' . self::LOG_FILE;

        // Ensure directory exists
        if (!is_dir($logDir)) {
            mkdir($logDir, 0755, true);
        }

        // Write JSON log entry
        $json = json_encode($data, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
        file_put_contents($logFile, $json . "\n", FILE_APPEND | LOCK_EX);
    }

    /**
     * Get slow query threshold from env or default
     */
    public static function getSlowQueryThreshold(): int
    {
        $value = getenv('SLOW_QUERY_THRESHOLD_MS');
        return $value !== false ? (int)$value : self::SLOW_QUERY_THRESHOLD_MS;
    }

    /**
     * Get slow request threshold from env or default
     */
    public static function getSlowRequestThreshold(): int
    {
        $value = getenv('SLOW_REQUEST_THRESHOLD_MS');
        return $value !== false ? (int)$value : self::SLOW_REQUEST_THRESHOLD_MS;
    }

    /**
     * Get memory spike threshold from env or default
     */
    public static function getMemorySpikeThreshold(): int
    {
        $value = getenv('MEMORY_SPIKE_THRESHOLD_MB');
        return $value !== false ? (int)$value : self::MEMORY_SPIKE_THRESHOLD_MB;
    }

    /**
     * Get N+1 query threshold from env or default
     */
    public static function getNPlusOneQueryThreshold(): int
    {
        $value = getenv('N_PLUS_ONE_QUERY_THRESHOLD');
        return $value !== false ? (int)$value : self::N_PLUS_ONE_QUERY_THRESHOLD;
    }

    /**
     * Get performance metrics summary for admin dashboard
     *
     * @param int $hours Number of hours to analyze (default 24)
     * @return array Performance summary
     */
    public static function getSummary(int $hours = 24): array
    {
        $logFile = __DIR__ . '/../../storage/logs/' . self::LOG_FILE;

        if (!file_exists($logFile)) {
            return [
                'slowest_requests' => [],
                'slowest_queries' => [],
                'memory_spikes' => [],
                'request_volume' => [],
                'n_plus_one_warnings' => 0,
            ];
        }

        $cutoffTime = time() - ($hours * 3600);
        $lines = file($logFile, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);

        $requests = [];
        $queries = [];
        $memorySpikes = [];
        $hourlyVolume = [];
        $nPlusOneCount = 0;

        foreach (array_reverse($lines) as $line) {
            $data = json_decode($line, true);
            if (!$data) continue;

            $timestamp = strtotime($data['timestamp']);
            if ($timestamp < $cutoffTime) continue;

            // Track by type
            if ($data['type'] === 'request') {
                $requests[] = $data;

                // Count N+1 warnings
                if (isset($data['warnings']) && in_array('POSSIBLE_N_PLUS_ONE', $data['warnings'])) {
                    $nPlusOneCount++;
                }

                // Track memory spikes
                if (isset($data['warnings']) && in_array('MEMORY_SPIKE', $data['warnings'])) {
                    $memorySpikes[] = $data;
                }

                // Track hourly volume
                $hour = date('Y-m-d H:00', $timestamp);
                if (!isset($hourlyVolume[$hour])) {
                    $hourlyVolume[$hour] = 0;
                }
                $hourlyVolume[$hour]++;
            } elseif ($data['type'] === 'slow_query') {
                $queries[] = $data;
            }
        }

        // Sort and limit results
        usort($requests, fn($a, $b) => $b['duration_ms'] <=> $a['duration_ms']);
        usort($queries, fn($a, $b) => $b['duration_ms'] <=> $a['duration_ms']);
        usort($memorySpikes, fn($a, $b) => $b['memory_mb'] <=> $a['memory_mb']);

        return [
            'slowest_requests' => array_slice($requests, 0, 20),
            'slowest_queries' => array_slice($queries, 0, 20),
            'memory_spikes' => array_slice($memorySpikes, 0, 10),
            'request_volume' => $hourlyVolume,
            'n_plus_one_warnings' => $nPlusOneCount,
            'total_requests' => count($requests),
            'total_slow_queries' => count($queries),
        ];
    }
}

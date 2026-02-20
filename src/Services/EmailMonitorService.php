<?php
// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

namespace Nexus\Services;

use Nexus\Core\TenantContext;

/**
 * Email monitoring and metrics tracking service
 * Tracks email send success/failure rates, provider usage, token refresh frequency
 */
class EmailMonitorService
{
    // Redis cache keys for metrics
    private const METRICS_PREFIX = 'email_metrics';
    private const HOURLY_WINDOW = 3600; // 1 hour
    private const DAILY_WINDOW = 86400; // 24 hours

    // Metric types
    private const METRIC_GMAIL_SUCCESS = 'gmail_api_success';
    private const METRIC_GMAIL_FAILURE = 'gmail_api_failure';
    private const METRIC_SMTP_SUCCESS = 'smtp_success';
    private const METRIC_SMTP_FAILURE = 'smtp_failure';
    private const METRIC_TOKEN_REFRESH = 'token_refresh';
    private const METRIC_TOKEN_REFRESH_FAILURE = 'token_refresh_failure';
    private const METRIC_FALLBACK_TO_SMTP = 'fallback_to_smtp';
    private const METRIC_CIRCUIT_BREAKER_OPEN = 'circuit_breaker_open';
    private const METRIC_RATE_LIMIT_HIT = 'rate_limit_hit';

    /**
     * Record email send attempt
     */
    public static function recordEmailSend(string $provider, bool $success, ?int $tenantId = null): void
    {
        $tenantId = $tenantId ?? TenantContext::getId();

        if ($provider === 'gmail_api') {
            $metric = $success ? self::METRIC_GMAIL_SUCCESS : self::METRIC_GMAIL_FAILURE;
        } else {
            $metric = $success ? self::METRIC_SMTP_SUCCESS : self::METRIC_SMTP_FAILURE;
        }

        self::incrementMetric($metric, $tenantId);
    }

    /**
     * Record token refresh event
     */
    public static function recordTokenRefresh(bool $success, ?int $tenantId = null): void
    {
        $tenantId = $tenantId ?? TenantContext::getId();
        $metric = $success ? self::METRIC_TOKEN_REFRESH : self::METRIC_TOKEN_REFRESH_FAILURE;
        self::incrementMetric($metric, $tenantId);
    }

    /**
     * Record fallback to SMTP
     */
    public static function recordFallbackToSmtp(string $reason, ?int $tenantId = null): void
    {
        $tenantId = $tenantId ?? TenantContext::getId();
        self::incrementMetric(self::METRIC_FALLBACK_TO_SMTP, $tenantId);

        // Store reason in a rolling list (keep last 20 via get/set)
        $key = self::getMetricKey('fallback_reasons', $tenantId);
        $existing = RedisCache::get($key, null);
        $reasons = is_array($existing) ? $existing : [];

        array_unshift($reasons, [
            'timestamp' => time(),
            'reason' => $reason
        ]);

        // Keep last 20 entries
        $reasons = array_slice($reasons, 0, 20);
        RedisCache::set($key, $reasons, self::DAILY_WINDOW, null);
    }

    /**
     * Record circuit breaker state change
     */
    public static function recordCircuitBreakerOpen(?int $tenantId = null): void
    {
        $tenantId = $tenantId ?? TenantContext::getId();
        self::incrementMetric(self::METRIC_CIRCUIT_BREAKER_OPEN, $tenantId);
    }

    /**
     * Record rate limit hit
     */
    public static function recordRateLimitHit(?int $tenantId = null): void
    {
        $tenantId = $tenantId ?? TenantContext::getId();
        self::incrementMetric(self::METRIC_RATE_LIMIT_HIT, $tenantId);
    }

    /**
     * Get metrics for a specific window (hourly or daily)
     */
    public static function getMetrics(?int $tenantId = null, string $window = 'hourly'): array
    {
        $tenantId = $tenantId ?? TenantContext::getId();
        $ttl = $window === 'hourly' ? self::HOURLY_WINDOW : self::DAILY_WINDOW;
        $suffix = $window === 'hourly' ? 'h' : 'd';

        $metrics = [
            'gmail_api' => [
                'success' => self::getMetricValue(self::METRIC_GMAIL_SUCCESS . "_{$suffix}", $tenantId),
                'failure' => self::getMetricValue(self::METRIC_GMAIL_FAILURE . "_{$suffix}", $tenantId),
            ],
            'smtp' => [
                'success' => self::getMetricValue(self::METRIC_SMTP_SUCCESS . "_{$suffix}", $tenantId),
                'failure' => self::getMetricValue(self::METRIC_SMTP_FAILURE . "_{$suffix}", $tenantId),
            ],
            'token_refresh' => [
                'success' => self::getMetricValue(self::METRIC_TOKEN_REFRESH . "_{$suffix}", $tenantId),
                'failure' => self::getMetricValue(self::METRIC_TOKEN_REFRESH_FAILURE . "_{$suffix}", $tenantId),
            ],
            'fallback_to_smtp' => self::getMetricValue(self::METRIC_FALLBACK_TO_SMTP . "_{$suffix}", $tenantId),
            'circuit_breaker_open' => self::getMetricValue(self::METRIC_CIRCUIT_BREAKER_OPEN . "_{$suffix}", $tenantId),
            'rate_limit_hit' => self::getMetricValue(self::METRIC_RATE_LIMIT_HIT . "_{$suffix}", $tenantId),
            'window' => $window,
            'tenant_id' => $tenantId,
        ];

        // Calculate derived metrics
        $gmailTotal = $metrics['gmail_api']['success'] + $metrics['gmail_api']['failure'];
        $smtpTotal = $metrics['smtp']['success'] + $metrics['smtp']['failure'];

        $metrics['gmail_api']['total'] = $gmailTotal;
        $metrics['gmail_api']['success_rate'] = $gmailTotal > 0
            ? round(($metrics['gmail_api']['success'] / $gmailTotal) * 100, 2)
            : 0;

        $metrics['smtp']['total'] = $smtpTotal;
        $metrics['smtp']['success_rate'] = $smtpTotal > 0
            ? round(($metrics['smtp']['success'] / $smtpTotal) * 100, 2)
            : 0;

        $tokenRefreshTotal = $metrics['token_refresh']['success'] + $metrics['token_refresh']['failure'];
        $metrics['token_refresh']['total'] = $tokenRefreshTotal;
        $metrics['token_refresh']['success_rate'] = $tokenRefreshTotal > 0
            ? round(($metrics['token_refresh']['success'] / $tokenRefreshTotal) * 100, 2)
            : 0;

        return $metrics;
    }

    /**
     * Get recent fallback reasons
     */
    public static function getFallbackReasons(?int $tenantId = null, int $limit = 20): array
    {
        $tenantId = $tenantId ?? TenantContext::getId();
        $key = self::getMetricKey('fallback_reasons', $tenantId);

        $reasons = RedisCache::get($key, null);
        if (!is_array($reasons)) {
            return [];
        }

        return array_slice($reasons, 0, $limit);
    }

    /**
     * Get email health summary
     */
    public static function getHealthSummary(?int $tenantId = null): array
    {
        $hourly = self::getMetrics($tenantId, 'hourly');
        $daily = self::getMetrics($tenantId, 'daily');

        // Determine health status
        $status = 'healthy';
        $issues = [];

        // Check Gmail API health (hourly)
        if ($hourly['gmail_api']['total'] > 0 && $hourly['gmail_api']['success_rate'] < 90) {
            $status = 'degraded';
            $issues[] = "Gmail API success rate below 90% (hourly): {$hourly['gmail_api']['success_rate']}%";
        }

        // Check for excessive fallbacks
        if ($hourly['fallback_to_smtp'] > 5) {
            $status = 'degraded';
            $issues[] = "High fallback rate to SMTP (hourly): {$hourly['fallback_to_smtp']} fallbacks";
        }

        // Check circuit breaker
        if ($hourly['circuit_breaker_open'] > 0) {
            $status = 'degraded';
            $issues[] = "Circuit breaker opened {$hourly['circuit_breaker_open']} time(s) in the last hour";
        }

        // Check token refresh failures
        if ($hourly['token_refresh']['failure'] > 3) {
            $status = 'critical';
            $issues[] = "Token refresh failures (hourly): {$hourly['token_refresh']['failure']}";
        }

        return [
            'status' => $status,
            'issues' => $issues,
            'hourly_metrics' => $hourly,
            'daily_metrics' => $daily,
            'recent_fallbacks' => self::getFallbackReasons($tenantId, 5),
        ];
    }

    /**
     * Increment a metric counter
     */
    private static function incrementMetric(string $metric, ?int $tenantId): void
    {
        // Increment hourly counter
        $hourlyKey = self::getMetricKey("{$metric}_h", $tenantId);
        $currentValue = (int) RedisCache::get($hourlyKey, $tenantId) ?: 0;
        RedisCache::set($hourlyKey, (string) ($currentValue + 1), self::HOURLY_WINDOW, $tenantId);

        // Increment daily counter
        $dailyKey = self::getMetricKey("{$metric}_d", $tenantId);
        $currentValue = (int) RedisCache::get($dailyKey, $tenantId) ?: 0;
        RedisCache::set($dailyKey, (string) ($currentValue + 1), self::DAILY_WINDOW, $tenantId);
    }

    /**
     * Get metric value
     */
    private static function getMetricValue(string $metric, ?int $tenantId): int
    {
        $key = self::getMetricKey($metric, $tenantId);
        return (int) RedisCache::get($key, $tenantId) ?: 0;
    }

    /**
     * Get full metric key with prefix
     */
    private static function getMetricKey(string $metric, ?int $tenantId): string
    {
        return self::METRICS_PREFIX . ':' . ($tenantId ?? 'global') . ':' . $metric;
    }

    /**
     * Clear all metrics for a tenant (for testing)
     */
    public static function clearMetrics(?int $tenantId = null): void
    {
        $tenantId = $tenantId ?? TenantContext::getId();

        // Clear all known metric keys for this tenant
        $metrics = [
            self::METRIC_GMAIL_SUCCESS, self::METRIC_GMAIL_FAILURE,
            self::METRIC_SMTP_SUCCESS, self::METRIC_SMTP_FAILURE,
            self::METRIC_TOKEN_REFRESH, self::METRIC_TOKEN_REFRESH_FAILURE,
            self::METRIC_FALLBACK_TO_SMTP, self::METRIC_CIRCUIT_BREAKER_OPEN,
            self::METRIC_RATE_LIMIT_HIT,
        ];

        foreach ($metrics as $metric) {
            foreach (['_h', '_d'] as $suffix) {
                $key = self::getMetricKey("{$metric}{$suffix}", $tenantId);
                RedisCache::delete($key, null);
            }
        }

        // Clear fallback reasons
        RedisCache::delete(self::getMetricKey('fallback_reasons', $tenantId), null);
    }
}

<?php
// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

namespace App\Services;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Cache;

/**
 * EmailMonitorService — Tracks email delivery health metrics.
 *
 * Records send success/failure, token refreshes, SMTP fallbacks, circuit breaker
 * events, and rate limit hits. Stores counters in Redis/cache for fast aggregation
 * and provides a health summary for the admin dashboard.
 *
 * Self-contained native Laravel implementation — no legacy delegation.
 */
class EmailMonitorService
{
    /** Cache key prefix for email monitoring counters. */
    private const CACHE_PREFIX = 'email_monitor:';

    /** TTL for rolling counters (24 hours). */
    private const COUNTER_TTL_SECONDS = 86400;

    public function __construct()
    {
    }

    /**
     * Record an email send attempt (success or failure).
     *
     * Increments rolling counters in cache, keyed by provider and tenant.
     */
    public function recordEmailSend(string $provider, bool $success, ?int $tenantId = null): void
    {
        static::recordEmailSendStatic($provider, $success, $tenantId);
    }

    /**
     * Static proxy for recordEmailSend — used by code that cannot inject an instance.
     */
    public static function recordEmailSendStatic(string $provider, bool $success, ?int $tenantId = null): void
    {
        try {
            $scope = $tenantId ? "tenant:{$tenantId}" : 'global';
            $status = $success ? 'success' : 'failure';

            $keys = [
                self::CACHE_PREFIX . "{$scope}:{$provider}:{$status}",
                self::CACHE_PREFIX . "{$scope}:total:{$status}",
                self::CACHE_PREFIX . "{$scope}:{$provider}:total",
            ];

            foreach ($keys as $key) {
                $current = (int) Cache::get($key, 0);
                Cache::put($key, $current + 1, self::COUNTER_TTL_SECONDS);
            }

            // Also track the last send timestamp
            Cache::put(
                self::CACHE_PREFIX . "{$scope}:{$provider}:last_send",
                now()->toIso8601String(),
                self::COUNTER_TTL_SECONDS
            );

            if (!$success) {
                Cache::put(
                    self::CACHE_PREFIX . "{$scope}:{$provider}:last_failure",
                    now()->toIso8601String(),
                    self::COUNTER_TTL_SECONDS
                );
            }
        } catch (\Throwable $e) {
            // Non-fatal — monitoring should never break sending
            Log::debug('EmailMonitorService::recordEmailSend error', ['error' => $e->getMessage()]);
        }
    }

    /**
     * Record an OAuth token refresh attempt (e.g. Gmail API).
     */
    public function recordTokenRefresh(bool $success, ?int $tenantId = null): void
    {
        try {
            $scope = $tenantId ? "tenant:{$tenantId}" : 'global';
            $status = $success ? 'success' : 'failure';

            $key = self::CACHE_PREFIX . "{$scope}:token_refresh:{$status}";
            $current = (int) Cache::get($key, 0);
            Cache::put($key, $current + 1, self::COUNTER_TTL_SECONDS);

            Cache::put(
                self::CACHE_PREFIX . "{$scope}:token_refresh:last_attempt",
                now()->toIso8601String(),
                self::COUNTER_TTL_SECONDS
            );
        } catch (\Throwable $e) {
            Log::debug('EmailMonitorService::recordTokenRefresh error', ['error' => $e->getMessage()]);
        }
    }

    /**
     * Record a fallback from primary provider to SMTP.
     */
    public function recordFallbackToSmtp(string $reason, ?int $tenantId = null): void
    {
        try {
            $scope = $tenantId ? "tenant:{$tenantId}" : 'global';

            $key = self::CACHE_PREFIX . "{$scope}:smtp_fallback:count";
            $current = (int) Cache::get($key, 0);
            Cache::put($key, $current + 1, self::COUNTER_TTL_SECONDS);

            Cache::put(
                self::CACHE_PREFIX . "{$scope}:smtp_fallback:last_reason",
                $reason,
                self::COUNTER_TTL_SECONDS
            );

            Cache::put(
                self::CACHE_PREFIX . "{$scope}:smtp_fallback:last_at",
                now()->toIso8601String(),
                self::COUNTER_TTL_SECONDS
            );
        } catch (\Throwable $e) {
            Log::debug('EmailMonitorService::recordFallbackToSmtp error', ['error' => $e->getMessage()]);
        }
    }

    /**
     * Record a circuit breaker open event (provider considered unreliable).
     */
    public function recordCircuitBreakerOpen(?int $tenantId = null): void
    {
        try {
            $scope = $tenantId ? "tenant:{$tenantId}" : 'global';

            $key = self::CACHE_PREFIX . "{$scope}:circuit_breaker:opens";
            $current = (int) Cache::get($key, 0);
            Cache::put($key, $current + 1, self::COUNTER_TTL_SECONDS);

            Cache::put(
                self::CACHE_PREFIX . "{$scope}:circuit_breaker:last_opened",
                now()->toIso8601String(),
                self::COUNTER_TTL_SECONDS
            );
        } catch (\Throwable $e) {
            Log::debug('EmailMonitorService::recordCircuitBreakerOpen error', ['error' => $e->getMessage()]);
        }
    }

    /**
     * Record a rate limit hit event.
     */
    public function recordRateLimitHit(?int $tenantId = null): void
    {
        try {
            $scope = $tenantId ? "tenant:{$tenantId}" : 'global';

            $key = self::CACHE_PREFIX . "{$scope}:rate_limit:hits";
            $current = (int) Cache::get($key, 0);
            Cache::put($key, $current + 1, self::COUNTER_TTL_SECONDS);

            Cache::put(
                self::CACHE_PREFIX . "{$scope}:rate_limit:last_hit",
                now()->toIso8601String(),
                self::COUNTER_TTL_SECONDS
            );
        } catch (\Throwable $e) {
            Log::debug('EmailMonitorService::recordRateLimitHit error', ['error' => $e->getMessage()]);
        }
    }

    /**
     * Get email health summary for the admin dashboard.
     *
     * Aggregates rolling counters from cache for the given tenant (or globally).
     *
     * @return array{
     *     total_sent: int,
     *     total_failed: int,
     *     success_rate: float,
     *     providers: array<string, array{sent: int, failed: int, total: int, last_send: ?string, last_failure: ?string}>,
     *     token_refreshes: array{success: int, failure: int, last_attempt: ?string},
     *     smtp_fallbacks: array{count: int, last_reason: ?string, last_at: ?string},
     *     circuit_breaker: array{opens: int, last_opened: ?string},
     *     rate_limits: array{hits: int, last_hit: ?string},
     *     period: string,
     * }
     */
    public function getHealthSummary(?int $tenantId = null): array
    {
        try {
            $scope = $tenantId ? "tenant:{$tenantId}" : 'global';
            $providers = ['gmail_api', 'sendgrid', 'smtp'];

            $totalSuccess = (int) Cache::get(self::CACHE_PREFIX . "{$scope}:total:success", 0);
            $totalFailure = (int) Cache::get(self::CACHE_PREFIX . "{$scope}:total:failure", 0);
            $totalSent = $totalSuccess + $totalFailure;

            $providerStats = [];
            foreach ($providers as $provider) {
                $pSuccess = (int) Cache::get(self::CACHE_PREFIX . "{$scope}:{$provider}:success", 0);
                $pFailure = (int) Cache::get(self::CACHE_PREFIX . "{$scope}:{$provider}:failure", 0);
                $pTotal = (int) Cache::get(self::CACHE_PREFIX . "{$scope}:{$provider}:total", 0);

                // Only include providers that have activity
                if ($pTotal > 0 || $pSuccess > 0 || $pFailure > 0) {
                    $providerStats[$provider] = [
                        'sent' => $pSuccess,
                        'failed' => $pFailure,
                        'total' => $pTotal ?: ($pSuccess + $pFailure),
                        'last_send' => Cache::get(self::CACHE_PREFIX . "{$scope}:{$provider}:last_send"),
                        'last_failure' => Cache::get(self::CACHE_PREFIX . "{$scope}:{$provider}:last_failure"),
                    ];
                }
            }

            return [
                'total_sent' => $totalSuccess,
                'total_failed' => $totalFailure,
                'success_rate' => $totalSent > 0 ? round(($totalSuccess / $totalSent) * 100, 1) : 100.0,
                'providers' => $providerStats,
                'token_refreshes' => [
                    'success' => (int) Cache::get(self::CACHE_PREFIX . "{$scope}:token_refresh:success", 0),
                    'failure' => (int) Cache::get(self::CACHE_PREFIX . "{$scope}:token_refresh:failure", 0),
                    'last_attempt' => Cache::get(self::CACHE_PREFIX . "{$scope}:token_refresh:last_attempt"),
                ],
                'smtp_fallbacks' => [
                    'count' => (int) Cache::get(self::CACHE_PREFIX . "{$scope}:smtp_fallback:count", 0),
                    'last_reason' => Cache::get(self::CACHE_PREFIX . "{$scope}:smtp_fallback:last_reason"),
                    'last_at' => Cache::get(self::CACHE_PREFIX . "{$scope}:smtp_fallback:last_at"),
                ],
                'circuit_breaker' => [
                    'opens' => (int) Cache::get(self::CACHE_PREFIX . "{$scope}:circuit_breaker:opens", 0),
                    'last_opened' => Cache::get(self::CACHE_PREFIX . "{$scope}:circuit_breaker:last_opened"),
                ],
                'rate_limits' => [
                    'hits' => (int) Cache::get(self::CACHE_PREFIX . "{$scope}:rate_limit:hits", 0),
                    'last_hit' => Cache::get(self::CACHE_PREFIX . "{$scope}:rate_limit:last_hit"),
                ],
                'period' => 'rolling_24h',
            ];
        } catch (\Throwable $e) {
            Log::warning('EmailMonitorService::getHealthSummary error', ['error' => $e->getMessage()]);
            return [
                'total_sent' => 0,
                'total_failed' => 0,
                'success_rate' => 100.0,
                'providers' => [],
                'token_refreshes' => ['success' => 0, 'failure' => 0, 'last_attempt' => null],
                'smtp_fallbacks' => ['count' => 0, 'last_reason' => null, 'last_at' => null],
                'circuit_breaker' => ['opens' => 0, 'last_opened' => null],
                'rate_limits' => ['hits' => 0, 'last_hit' => null],
                'period' => 'rolling_24h',
                'error' => 'Cache unavailable',
            ];
        }
    }
}

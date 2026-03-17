<?php
// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

namespace App\Core;

use Nexus\Core\RateLimiter as LegacyRateLimiter;

/**
 * App-namespace wrapper for Nexus\Core\RateLimiter.
 *
 * Delegates to the legacy implementation. Once the Laravel migration is
 * complete this can be replaced with Laravel's RateLimiter facade.
 */
class RateLimiter
{
    /** Default rate limits by request type (requests per minute) */
    public const DEFAULT_LIMITS = [
        'read'   => 120,
        'write'  => 60,
        'upload' => 20,
        'auth'   => 10,
        'search' => 30,
    ];

    /**
     * Check if an IP/email combination is currently rate limited.
     *
     * @param string $identifier Email or IP address
     * @param string $type       'email' or 'ip'
     * @return array{limited: bool, remaining_attempts: int, retry_after: int|null}
     */
    public static function check(string $identifier, string $type = 'email'): array
    {
        return LegacyRateLimiter::check($identifier, $type);
    }

    /**
     * Record a login attempt.
     *
     * @param string $identifier Email or IP address
     * @param string $type       'email' or 'ip'
     * @param bool   $success    Whether the attempt was successful
     */
    public static function recordAttempt(string $identifier, string $type = 'email', bool $success = false): void
    {
        LegacyRateLimiter::recordAttempt($identifier, $type, $success);
    }

    /**
     * Clear failed attempts for an identifier (called on successful login).
     */
    public static function clearAttempts(string $identifier, string $type = 'email'): void
    {
        LegacyRateLimiter::clearAttempts($identifier, $type);
    }

    /**
     * Get formatted retry message for users.
     */
    public static function getRetryMessage(int $retryAfter): string
    {
        return LegacyRateLimiter::getRetryMessage($retryAfter);
    }

    /**
     * Check and record an API request attempt (cache-based throttling).
     *
     * @param string $key           Unique key (e.g. "api:listings:user:123")
     * @param int    $maxAttempts   Maximum requests allowed in the window
     * @param int    $windowSeconds Time window in seconds
     * @return bool True if request is allowed, false if rate limited
     */
    public static function attempt(string $key, int $maxAttempts = 60, int $windowSeconds = 60): bool
    {
        return LegacyRateLimiter::attempt($key, $maxAttempts, $windowSeconds);
    }

    /**
     * Get rate limit state without consuming an attempt.
     *
     * @return array{limit: int, remaining: int, reset: int, window: int}
     */
    public static function getApiRateLimitState(string $key, int $maxAttempts = 60, int $windowSeconds = 60): array
    {
        return LegacyRateLimiter::getApiRateLimitState($key, $maxAttempts, $windowSeconds);
    }

    /**
     * Get the current rate limit state from the last attempt() call.
     *
     * @return array|null
     */
    public static function getCurrentState(): ?array
    {
        return LegacyRateLimiter::getCurrentState();
    }

    /**
     * Clean up old rate limit cache files.
     */
    public static function cleanupApiCache(): void
    {
        LegacyRateLimiter::cleanupApiCache();
    }
}

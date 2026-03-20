<?php
// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

namespace App\Services;

use Illuminate\Support\Facades\RateLimiter;

/**
 * RateLimitService — Rate limiting using Laravel's RateLimiter (Redis-backed).
 *
 * Provides tenant-aware rate limiting for API endpoints and auth flows.
 */
class RateLimitService
{
    public function __construct()
    {
    }

    /**
     * Check if a key has exceeded the rate limit.
     *
     * @param string $key Unique identifier (e.g., "auth:login:192.168.1.1")
     * @param int $maxAttempts Maximum number of attempts allowed
     * @param int $windowSeconds Time window in seconds
     * @return bool True if allowed, false if rate-limited
     */
    public static function check(string $key, int $maxAttempts, int $windowSeconds = 60): bool
    {
        return RateLimiter::remaining($key, $maxAttempts) > 0;
    }

    /**
     * Increment the attempt counter for a given key.
     *
     * Returns true if the attempt is allowed (under the limit), false if rate-limited.
     *
     * @param string $key Unique identifier
     * @param int $maxAttempts Maximum number of attempts allowed
     * @param int $windowSeconds Time window in seconds
     * @return bool True if allowed, false if rate-limited
     */
    public static function increment(string $key, int $maxAttempts = 10, int $windowSeconds = 60): bool
    {
        return RateLimiter::attempt(
            $key,
            $maxAttempts,
            function () {
                // no-op — we just want to record the attempt
            },
            $windowSeconds
        );
    }

    /**
     * Get remaining attempts for a key.
     *
     * @param string $key Unique identifier
     * @param int $maxAttempts Maximum allowed attempts
     * @param int $windowSeconds Time window in seconds
     * @return int Remaining attempts
     */
    public static function remaining(string $key, int $maxAttempts, int $windowSeconds = 60): int
    {
        return RateLimiter::remaining($key, $maxAttempts);
    }

    /**
     * Hit (increment) a rate limit key.
     *
     * @param string $key Unique identifier
     * @param int $decaySeconds Window in seconds
     * @return int Current count
     */
    public static function hit(string $key, int $decaySeconds = 60): int
    {
        return RateLimiter::hit($key, $decaySeconds);
    }

    /**
     * Clear rate limit counter for a key.
     */
    public static function clear(string $key): void
    {
        RateLimiter::clear($key);
    }

    /**
     * Reset the rate limit counter for a given key.
     */
    public static function reset(string $key): void
    {
        RateLimiter::clear($key);
    }
}

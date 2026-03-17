<?php
// Copyright � 2024�2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

namespace App\Services;

/**
 * RateLimitService � Laravel DI wrapper for legacy \Nexus\Services\RateLimitService.
 *
 * Provides dependency-injectable access to the legacy static service methods.
 */
class RateLimitService
{
    public function __construct()
    {
    }

    /**
     * Delegates to legacy RateLimitService::check().
     */
    public function check(string $key, int $maxAttempts, int $decaySeconds = 60): bool
    {
        return \Nexus\Services\RateLimitService::check($key, $maxAttempts, $decaySeconds);
    }

    /**
     * Delegates to legacy RateLimitService::hit().
     */
    public function hit(string $key, int $decaySeconds = 60): int
    {
        return \Nexus\Services\RateLimitService::hit($key, $decaySeconds);
    }

    /**
     * Delegates to legacy RateLimitService::remaining().
     */
    public function remaining(string $key, int $maxAttempts): int
    {
        return \Nexus\Services\RateLimitService::remaining($key, $maxAttempts);
    }

    /**
     * Delegates to legacy RateLimitService::clear().
     */
    public function clear(string $key): void
    {
        \Nexus\Services\RateLimitService::clear($key);
    }

    /**
     * Increment the attempt counter for a given key.
     *
     * Checks if the limit has been exceeded first, then increments.
     * Returns true if the request is ALLOWED, false if rate-limited.
     *
     * @param string $key    Unique identifier (e.g., "auth:login:192.168.1.1")
     * @param int    $limit  Maximum number of attempts allowed in the window
     * @param int    $window Time window in seconds
     * @return bool True if allowed, false if rate-limited
     */
    public function increment(string $key, int $limit, int $window): bool
    {
        $cacheKey = 'ratelimit:' . $key;

        // Check current count
        $count = \Illuminate\Support\Facades\Cache::get($cacheKey);

        if ($count !== null && (int) $count >= $limit) {
            return false; // Rate limited
        }

        // Increment (or create with TTL)
        if ($count === null) {
            \Illuminate\Support\Facades\Cache::put($cacheKey, 1, $window);
        } else {
            \Illuminate\Support\Facades\Cache::increment($cacheKey);
        }

        return true; // Allowed
    }

    /**
     * Reset the rate limit counter for a given key.
     *
     * @param string $key Unique identifier (e.g., "auth:login:192.168.1.1")
     * @return void
     */
    public function reset(string $key): void
    {
        $cacheKey = 'ratelimit:' . $key;
        \Illuminate\Support\Facades\Cache::forget($cacheKey);
    }
}

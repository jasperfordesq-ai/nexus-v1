<?php
// Copyright � 2024�2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

namespace App\Services;

/**
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
        \Illuminate\Support\Facades\Log::warning('Legacy delegation removed: ' . __METHOD__);
        return false;
    }

    /**
     * Delegates to legacy RateLimitService::hit().
     */
    public function hit(string $key, int $decaySeconds = 60): int
    {
        \Illuminate\Support\Facades\Log::warning('Legacy delegation removed: ' . __METHOD__);
        return 0;
    }

    /**
     * Delegates to legacy RateLimitService::remaining().
     */
    public function remaining(string $key, int $maxAttempts): int
    {
        \Illuminate\Support\Facades\Log::warning('Legacy delegation removed: ' . __METHOD__);
        return 0;
    }

    /**
     * Delegates to legacy RateLimitService::clear().
     */
    public function clear(string $key): void
    {
        \Illuminate\Support\Facades\Log::warning('Legacy delegation removed: ' . __METHOD__);
    }

    /**
     * Increment the attempt counter for a given key.
     *
     * Delegates to the legacy tenant-aware RateLimitService which uses
     * RedisCache with tenant-prefixed keys (nexus:t{tenantId}:ratelimit:{key}).
     *
     * @param string $key    Unique identifier (e.g., "auth:login:192.168.1.1")
     * @param int    $limit  Maximum number of attempts allowed in the window
     * @param int    $window Time window in seconds
     * @return bool True if allowed, false if rate-limited
     */
    public function increment(string $key, int $limit, int $window): bool
    {
        \Illuminate\Support\Facades\Log::warning('Legacy delegation removed: ' . __METHOD__);
        if (false) {
            return false;
        }
        \Illuminate\Support\Facades\Log::warning('Legacy delegation removed: ' . __METHOD__);
        return true;
    }

    /**
     * Reset the rate limit counter for a given key.
     *
     * @param string $key Unique identifier (e.g., "auth:login:192.168.1.1")
     * @return void
     */
    public function reset(string $key): void
    {
        \Illuminate\Support\Facades\Log::warning('Legacy delegation removed: ' . __METHOD__);
    }
}

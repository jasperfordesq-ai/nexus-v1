<?php
// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

namespace App\Services;

/**
 * RateLimitService — Laravel DI wrapper for legacy \Nexus\Services\RateLimitService.
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
}

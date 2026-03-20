<?php
// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

namespace Nexus\Core;

/**
 * Thin delegate — forwards all calls to \App\Core\RateLimiter which
 * holds the real implementation.
 *
 * This class is kept for backward compatibility: legacy Nexus\ namespace
 * code references it. The public API is identical.
 *
 * @see \App\Core\RateLimiter  The authoritative implementation.
 * @deprecated Use \App\Core\RateLimiter instead.
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

    public static function check(string $identifier, string $type = 'email'): array
    {
        return \App\Core\RateLimiter::check($identifier, $type);
    }

    public static function recordAttempt(string $identifier, string $type = 'email', bool $success = false): void
    {
        \App\Core\RateLimiter::recordAttempt($identifier, $type, $success);
    }

    public static function clearAttempts(string $identifier, string $type = 'email'): void
    {
        \App\Core\RateLimiter::clearAttempts($identifier, $type);
    }

    public static function getRetryMessage(int $retryAfter): string
    {
        return \App\Core\RateLimiter::getRetryMessage($retryAfter);
    }

    public static function attempt(string $key, int $maxAttempts = 60, int $windowSeconds = 60): bool
    {
        return \App\Core\RateLimiter::attempt($key, $maxAttempts, $windowSeconds);
    }

    public static function getApiRateLimitState(string $key, int $maxAttempts = 60, int $windowSeconds = 60): array
    {
        return \App\Core\RateLimiter::getApiRateLimitState($key, $maxAttempts, $windowSeconds);
    }

    public static function getCurrentState(): ?array
    {
        return \App\Core\RateLimiter::getCurrentState();
    }

    public static function cleanupApiCache(): void
    {
        \App\Core\RateLimiter::cleanupApiCache();
    }
}

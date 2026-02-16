<?php

namespace Nexus\Services;

/**
 * RateLimitService - Redis-based rate limiting for API endpoints
 *
 * Uses RedisCache to track request counts per key (typically IP + endpoint).
 * Provides a simple sliding-window counter pattern for rate limiting.
 *
 * This service complements the database-based \Nexus\Core\RateLimiter by
 * providing a faster, cache-based approach suitable for high-frequency
 * endpoints like authentication and token refresh.
 *
 * Usage:
 *   $ip = $_SERVER['REMOTE_ADDR'] ?? 'unknown';
 *   if (RateLimitService::check("auth:login:$ip", 5, 60)) {
 *       // Rate limited - return 429
 *   }
 *   RateLimitService::increment("auth:login:$ip", 60);
 *
 * @package Nexus\Services
 */
class RateLimitService
{
    /**
     * Redis key prefix for rate limiting counters
     */
    private const KEY_PREFIX = 'ratelimit:';

    /**
     * Check if the rate limit has been exceeded for a given key.
     *
     * @param string $key Unique identifier (e.g., "auth:login:192.168.1.1")
     * @param int $maxAttempts Maximum number of attempts allowed in the window
     * @param int $windowSeconds Time window in seconds
     * @return bool True if the limit is EXCEEDED (caller should block), false if allowed
     */
    public static function check(string $key, int $maxAttempts, int $windowSeconds): bool
    {
        $cacheKey = self::KEY_PREFIX . $key;
        $count = RedisCache::get($cacheKey, null);

        if ($count === null) {
            return false;
        }

        return (int) $count >= $maxAttempts;
    }

    /**
     * Increment the attempt counter for a given key.
     *
     * Uses atomic Redis INCR to increment the counter without resetting TTL.
     * The TTL is only set on the first increment (when the key is created),
     * ensuring the rate limit window is a fixed duration from the first attempt.
     *
     * @param string $key Unique identifier (e.g., "auth:login:192.168.1.1")
     * @param int $windowSeconds Time window in seconds (used as TTL for new keys)
     * @return void
     */
    public static function increment(string $key, int $windowSeconds): void
    {
        $cacheKey = self::KEY_PREFIX . $key;
        RedisCache::increment($cacheKey, $windowSeconds, null);
    }

    /**
     * Get the number of remaining attempts for a given key.
     *
     * @param string $key Unique identifier (e.g., "auth:login:192.168.1.1")
     * @param int $maxAttempts Maximum number of attempts allowed in the window
     * @param int $windowSeconds Time window in seconds (unused but included for API consistency)
     * @return int Number of remaining attempts (0 if limit reached/exceeded)
     */
    public static function remaining(string $key, int $maxAttempts, int $windowSeconds): int
    {
        $cacheKey = self::KEY_PREFIX . $key;
        $count = RedisCache::get($cacheKey, null);

        if ($count === null) {
            return $maxAttempts;
        }

        return max(0, $maxAttempts - (int) $count);
    }

    /**
     * Reset the rate limit counter for a given key.
     *
     * Useful for clearing limits after a successful authentication,
     * or for administrative purposes.
     *
     * @param string $key Unique identifier (e.g., "auth:login:192.168.1.1")
     * @return void
     */
    public static function reset(string $key): void
    {
        $cacheKey = self::KEY_PREFIX . $key;
        RedisCache::delete($cacheKey, null);
    }
}

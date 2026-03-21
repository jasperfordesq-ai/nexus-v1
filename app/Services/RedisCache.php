<?php
// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

namespace App\Services;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Redis;
use Illuminate\Support\Facades\Log;

/**
 * Native Laravel Redis cache service.
 *
 * Replaces the legacy static \Nexus\RedisCache with Laravel's Cache facade
 * backed by the Redis store. Supports both instance and static calls for
 * backward compatibility with callers that use RedisCache::method() directly.
 *
 * Keys are prefixed with "t{tenantId}:" when a tenant ID is provided,
 * matching the legacy key-scoping convention.
 */
class RedisCache
{
    /** Default cache TTL in seconds (5 minutes) — matches legacy. */
    private const DEFAULT_TTL = 300;

    public function __construct()
    {
    }

    // ─────────────────────────────────────────────────────────────────────────
    // Key helpers
    // ─────────────────────────────────────────────────────────────────────────

    /**
     * Build a tenant-scoped cache key.
     */
    private static function buildKey(string $key, ?int $tenantId = null): string
    {
        if ($tenantId !== null) {
            return "t{$tenantId}:{$key}";
        }

        return $key;
    }

    // ─────────────────────────────────────────────────────────────────────────
    // Core operations — all support both instance ($this->method) and static
    // (RedisCache::method) invocation via __call / __callStatic.
    // ─────────────────────────────────────────────────────────────────────────

    /**
     * Retrieve a value from cache.
     *
     * @return mixed|null  The unserialized value, or null if not found.
     */
    public function get(string $key, ?int $tenantId = null): mixed
    {
        try {
            return Cache::store('redis')->get(self::buildKey($key, $tenantId));
        } catch (\Throwable $e) {
            Log::warning('[RedisCache] get failed: ' . $e->getMessage());
            return null;
        }
    }

    /**
     * Store a value in cache.
     */
    public function set(string $key, mixed $value, int $ttl = self::DEFAULT_TTL, ?int $tenantId = null): bool
    {
        try {
            return Cache::store('redis')->put(self::buildKey($key, $tenantId), $value, $ttl);
        } catch (\Throwable $e) {
            Log::warning('[RedisCache] set failed: ' . $e->getMessage());
            return false;
        }
    }

    /**
     * Delete a value from cache.
     */
    public function delete(string $key, ?int $tenantId = null): bool
    {
        try {
            return Cache::store('redis')->forget(self::buildKey($key, $tenantId));
        } catch (\Throwable $e) {
            Log::warning('[RedisCache] delete failed: ' . $e->getMessage());
            return false;
        }
    }

    /**
     * Increment a counter key by 1 and set TTL on first creation.
     *
     * @return int  The new counter value, or 0 on failure.
     */
    public function increment(string $key, int $ttl = self::DEFAULT_TTL, ?int $tenantId = null): int
    {
        try {
            $fullKey = self::buildKey($key, $tenantId);
            $store = Cache::store('redis');

            $newValue = $store->increment($fullKey);

            // Set expiry if this is a fresh key (value is 1 after increment)
            if ($newValue === 1) {
                $store->put($fullKey, $newValue, $ttl);
            }

            return (int) $newValue;
        } catch (\Throwable $e) {
            Log::warning('[RedisCache] increment failed: ' . $e->getMessage());
            return 0;
        }
    }

    /**
     * Check whether a key exists in cache.
     */
    public function has(string $key, ?int $tenantId = null): bool
    {
        try {
            return Cache::store('redis')->has(self::buildKey($key, $tenantId));
        } catch (\Throwable $e) {
            Log::warning('[RedisCache] has failed: ' . $e->getMessage());
            return false;
        }
    }

    /**
     * Clear all cache keys for a given tenant.
     *
     * Scans for keys matching the tenant prefix and deletes them.
     *
     * @return int Number of keys deleted.
     */
    public function clearTenant(?int $tenantId = null): int
    {
        if ($tenantId === null) {
            return 0;
        }

        try {
            $redis = Redis::connection();
            $prefix = config('cache.prefix', '') . ':' . "t{$tenantId}:";
            $deleted = 0;
            $cursor = '0';

            do {
                [$cursor, $keys] = $redis->scan($cursor, ['match' => $prefix . '*', 'count' => 200]);

                if (!empty($keys)) {
                    $redis->del(...$keys);
                    $deleted += count($keys);
                }
            } while ($cursor !== '0' && $cursor !== 0);

            return $deleted;
        } catch (\Throwable $e) {
            Log::warning('[RedisCache] clearTenant failed: ' . $e->getMessage());
            return 0;
        }
    }

    /**
     * Get Redis server statistics.
     *
     * Returns an array with 'enabled', 'memory_used', and other info fields
     * that callers (AdminConfigController, AdminToolsController,
     * AdminEnterpriseController) rely on.
     */
    public function getStats(): array
    {
        try {
            $redis = Redis::connection();
            $info = $redis->info();

            // phpredis returns info as a flat associative array
            $memoryUsed = $info['used_memory_human'] ?? 'N/A';
            $connectedClients = $info['connected_clients'] ?? 0;
            $uptimeSeconds = $info['uptime_in_seconds'] ?? 0;
            $totalKeys = $info['db0']['keys'] ?? ($info['keyspace_hits'] ?? 'N/A');
            $version = $info['redis_version'] ?? 'unknown';

            // Try to get a more accurate key count from db0
            if (isset($info['db0']) && is_string($info['db0'])) {
                // phpredis may return "keys=123,expires=45,avg_ttl=6789"
                if (preg_match('/keys=(\d+)/', $info['db0'], $m)) {
                    $totalKeys = (int) $m[1];
                }
            }

            return [
                'enabled' => true,
                'memory_used' => $memoryUsed,
                'connected_clients' => (int) $connectedClients,
                'uptime_seconds' => (int) $uptimeSeconds,
                'total_keys' => $totalKeys,
                'version' => $version,
            ];
        } catch (\Throwable $e) {
            Log::warning('[RedisCache] getStats failed: ' . $e->getMessage());
            return [
                'enabled' => false,
                'error' => $e->getMessage(),
            ];
        }
    }

    // ─────────────────────────────────────────────────────────────────────────
    // Static call support
    //
    // Several controllers call RedisCache::getStats(), RedisCache::delete(),
    // etc. as static methods. This magic method resolves the singleton from
    // the container and forwards the call.
    // ─────────────────────────────────────────────────────────────────────────

    /**
     * @param  string  $method
     * @param  array   $args
     * @return mixed
     */
    public static function __callStatic(string $method, array $args): mixed
    {
        /** @var static $instance */
        $instance = app(static::class);

        return $instance->{$method}(...$args);
    }
}

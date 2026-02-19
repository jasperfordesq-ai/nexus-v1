<?php
// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

namespace Nexus\Services;

use Nexus\Core\TenantContext;
use Nexus\Services\Enterprise\ConfigService;

/**
 * RedisCache - High-performance caching service using Redis
 *
 * Provides fast, distributed caching for dashboard analytics,
 * query results, and other frequently accessed data.
 *
 * Falls back to file-based caching if Redis is unavailable.
 */
class RedisCache
{
    private static $redis = null;
    private static $enabled = null;
    private static $useFallback = false;

    /**
     * Default cache duration in seconds (5 minutes)
     */
    private const DEFAULT_TTL = 300;

    /**
     * Initialize Redis connection
     *
     * @return \Redis|null
     */
    private static function getRedis(): ?\Redis
    {
        // Check if explicitly disabled
        if (self::$enabled === false) {
            return null;
        }

        // Return existing connection
        if (self::$redis !== null) {
            return self::$redis;
        }

        // Check if Redis extension is available
        if (!class_exists('Redis')) {
            self::$enabled = false;
            self::$useFallback = true;
            error_log('RedisCache: Redis extension not available, using file-based fallback');
            return null;
        }

        try {
            // Get Redis configuration
            $config = ConfigService::getInstance()->getRedis();
            $host = $config['host'] ?? '127.0.0.1';
            $port = $config['port'] ?? 6379;
            $database = $config['database'] ?? 0;
            $password = $config['password'] ?? null;

            // Create Redis instance
            $redis = new \Redis();

            // Connect with timeout
            $connected = @$redis->connect($host, $port, 2.0);
            if (!$connected) {
                throw new \Exception("Failed to connect to Redis at {$host}:{$port}");
            }

            // Authenticate if password provided
            if ($password && !empty($password)) {
                if (!$redis->auth($password)) {
                    throw new \Exception("Redis authentication failed");
                }
            }

            // Select database
            $redis->select($database);

            // Set key prefix for this application
            $redis->setOption(\Redis::OPT_PREFIX, 'nexus:');

            self::$redis = $redis;
            self::$enabled = true;

            return $redis;
        } catch (\Exception $e) {
            error_log('RedisCache initialization failed: ' . $e->getMessage());
            self::$enabled = false;
            self::$useFallback = true;
            return null;
        }
    }

    /**
     * Generate cache key with tenant context
     *
     * @param string $key Base key name
     * @param int|null $tenantId Optional tenant ID (defaults to current)
     * @return string Full cache key
     */
    private static function getCacheKey(string $key, ?int $tenantId = null): string
    {
        $tenantId = $tenantId ?? TenantContext::getId();
        return "t{$tenantId}:{$key}";
    }

    /**
     * Get value from cache
     *
     * @param string $key Cache key
     * @param int|null $tenantId Optional tenant ID
     * @return mixed|null Cached value or null if not found
     */
    public static function get(string $key, ?int $tenantId = null)
    {
        $redis = self::getRedis();

        if ($redis === null) {
            return self::getFallback($key, $tenantId);
        }

        try {
            $cacheKey = self::getCacheKey($key, $tenantId);
            $value = $redis->get($cacheKey);

            if ($value === false) {
                return null;
            }

            // Unserialize if needed
            $decoded = @json_decode($value, true);
            return $decoded !== null ? $decoded : $value;
        } catch (\Exception $e) {
            error_log('RedisCache::get error: ' . $e->getMessage());
            return self::getFallback($key, $tenantId);
        }
    }

    /**
     * Set value in cache
     *
     * @param string $key Cache key
     * @param mixed $value Value to cache
     * @param int $ttl Time to live in seconds (default: 5 minutes)
     * @param int|null $tenantId Optional tenant ID
     * @return bool Success status
     */
    public static function set(string $key, $value, int $ttl = self::DEFAULT_TTL, ?int $tenantId = null): bool
    {
        $redis = self::getRedis();

        if ($redis === null) {
            return self::setFallback($key, $value, $ttl, $tenantId);
        }

        try {
            $cacheKey = self::getCacheKey($key, $tenantId);

            // Serialize complex data structures
            $serialized = is_array($value) || is_object($value)
                ? json_encode($value)
                : $value;

            return $redis->setex($cacheKey, $ttl, $serialized);
        } catch (\Exception $e) {
            error_log('RedisCache::set error: ' . $e->getMessage());
            return self::setFallback($key, $value, $ttl, $tenantId);
        }
    }

    /**
     * Delete value from cache
     *
     * @param string $key Cache key
     * @param int|null $tenantId Optional tenant ID
     * @return bool Success status
     */
    public static function delete(string $key, ?int $tenantId = null): bool
    {
        $redis = self::getRedis();

        if ($redis === null) {
            return self::deleteFallback($key, $tenantId);
        }

        try {
            $cacheKey = self::getCacheKey($key, $tenantId);
            return $redis->del($cacheKey) > 0;
        } catch (\Exception $e) {
            error_log('RedisCache::delete error: ' . $e->getMessage());
            return self::deleteFallback($key, $tenantId);
        }
    }

    /**
     * Atomically increment a counter in cache.
     *
     * Uses Redis INCR (preserves existing TTL). Sets TTL only on first increment.
     * Falls back to get-then-set when Redis is unavailable.
     *
     * @param string $key Cache key
     * @param int $ttl Time to live in seconds (only applied when key is first created)
     * @param int|null $tenantId Optional tenant ID
     * @return int New counter value
     */
    public static function increment(string $key, int $ttl = self::DEFAULT_TTL, ?int $tenantId = null): int
    {
        $redis = self::getRedis();

        if ($redis === null) {
            // Fallback: get → increment → set (not atomic, but functional)
            $current = (int) self::get($key, $tenantId);
            $newValue = $current + 1;
            self::set($key, $newValue, $ttl, $tenantId);
            return $newValue;
        }

        try {
            $cacheKey = self::getCacheKey($key, $tenantId);
            $newValue = $redis->incr($cacheKey);

            // Set TTL only on first increment (when key was just created)
            if ($newValue === 1) {
                $redis->expire($cacheKey, $ttl);
            }

            return $newValue;
        } catch (\Exception $e) {
            error_log('RedisCache::increment error: ' . $e->getMessage());
            // Fallback on error
            $current = (int) self::get($key, $tenantId);
            $newValue = $current + 1;
            self::set($key, $newValue, $ttl, $tenantId);
            return $newValue;
        }
    }

    /**
     * Check if key exists in cache
     *
     * @param string $key Cache key
     * @param int|null $tenantId Optional tenant ID
     * @return bool
     */
    public static function has(string $key, ?int $tenantId = null): bool
    {
        $redis = self::getRedis();

        if ($redis === null) {
            return self::hasFallback($key, $tenantId);
        }

        try {
            $cacheKey = self::getCacheKey($key, $tenantId);
            return $redis->exists($cacheKey) > 0;
        } catch (\Exception $e) {
            error_log('RedisCache::has error: ' . $e->getMessage());
            return self::hasFallback($key, $tenantId);
        }
    }

    /**
     * Remember pattern - get from cache or execute callback and cache result
     *
     * @param string $key Cache key
     * @param callable $callback Function to execute if cache miss
     * @param int $ttl Time to live in seconds
     * @param int|null $tenantId Optional tenant ID
     * @return mixed Cached or computed value
     */
    public static function remember(string $key, callable $callback, int $ttl = self::DEFAULT_TTL, ?int $tenantId = null)
    {
        // Try to get from cache
        $cached = self::get($key, $tenantId);
        if ($cached !== null) {
            return $cached;
        }

        // Cache miss - execute callback
        $value = $callback();

        // Store in cache
        self::set($key, $value, $ttl, $tenantId);

        return $value;
    }

    /**
     * Clear all cache for a tenant
     *
     * @param int|null $tenantId Optional tenant ID
     * @return int Number of keys deleted
     */
    public static function clearTenant(?int $tenantId = null): int
    {
        $redis = self::getRedis();
        $tenantId = $tenantId ?? TenantContext::getId();

        if ($redis === null) {
            return self::clearTenantFallback($tenantId);
        }

        try {
            $pattern = "nexus:t{$tenantId}:*";
            $keys = $redis->keys($pattern);

            if (empty($keys)) {
                return 0;
            }

            // Remove prefix from keys before deleting
            $keysToDelete = array_map(function ($key) {
                return str_replace('nexus:', '', $key);
            }, $keys);

            return $redis->del(...$keysToDelete);
        } catch (\Exception $e) {
            error_log('RedisCache::clearTenant error: ' . $e->getMessage());
            return self::clearTenantFallback($tenantId);
        }
    }

    /**
     * Get cache statistics
     *
     * @return array Cache statistics
     */
    public static function getStats(): array
    {
        $redis = self::getRedis();

        if ($redis === null) {
            return [
                'enabled' => false,
                'fallback_active' => self::$useFallback,
                'type' => 'file'
            ];
        }

        try {
            $info = $redis->info();
            $tenantId = TenantContext::getId();
            $pattern = "nexus:t{$tenantId}:*";
            $keys = $redis->keys($pattern);

            return [
                'enabled' => true,
                'fallback_active' => false,
                'type' => 'redis',
                'tenant_keys' => count($keys),
                'total_keys' => $info['db0']['keys'] ?? 0,
                'memory_used' => $info['used_memory_human'] ?? 'N/A',
                'uptime_seconds' => $info['uptime_in_seconds'] ?? 0,
            ];
        } catch (\Exception $e) {
            error_log('RedisCache::getStats error: ' . $e->getMessage());
            return ['enabled' => false, 'error' => $e->getMessage()];
        }
    }

    // ========================================================================
    // FILE-BASED FALLBACK METHODS
    // ========================================================================

    private static function getFallbackDir(): string
    {
        $cacheDir = dirname(__DIR__, 2) . '/cache/redis-fallback';
        if (!is_dir($cacheDir)) {
            @mkdir($cacheDir, 0755, true);
        }
        return $cacheDir;
    }

    private static function getFallbackPath(string $key, ?int $tenantId): string
    {
        $tenantId = $tenantId ?? TenantContext::getId();
        $safeKey = preg_replace('/[^a-zA-Z0-9_-]/', '_', $key);
        return self::getFallbackDir() . "/t{$tenantId}_{$safeKey}.cache";
    }

    private static function getFallback(string $key, ?int $tenantId)
    {
        $file = self::getFallbackPath($key, $tenantId);
        if (!file_exists($file)) {
            return null;
        }

        $content = @file_get_contents($file);
        if ($content === false) {
            return null;
        }

        $data = @json_decode($content, true);
        if ($data === null) {
            return null;
        }

        // Check expiration
        if (isset($data['expires_at']) && time() > $data['expires_at']) {
            @unlink($file);
            return null;
        }

        return $data['value'] ?? null;
    }

    private static function setFallback(string $key, $value, int $ttl, ?int $tenantId): bool
    {
        $file = self::getFallbackPath($key, $tenantId);
        $data = [
            'value' => $value,
            'expires_at' => time() + $ttl,
            'created_at' => time()
        ];
        return @file_put_contents($file, json_encode($data)) !== false;
    }

    private static function hasFallback(string $key, ?int $tenantId): bool
    {
        return self::getFallback($key, $tenantId) !== null;
    }

    private static function deleteFallback(string $key, ?int $tenantId): bool
    {
        $file = self::getFallbackPath($key, $tenantId);
        if (file_exists($file)) {
            return @unlink($file);
        }
        return true;
    }

    private static function clearTenantFallback(?int $tenantId): int
    {
        $tenantId = $tenantId ?? TenantContext::getId();
        $pattern = self::getFallbackDir() . "/t{$tenantId}_*.cache";
        $files = glob($pattern);
        $deleted = 0;

        foreach ($files as $file) {
            if (@unlink($file)) {
                $deleted++;
            }
        }

        return $deleted;
    }
}

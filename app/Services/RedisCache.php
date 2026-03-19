<?php
// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

namespace App\Services;

/**
 * RedisCache — Laravel DI wrapper for legacy \Nexus\Services\RedisCache.
 *
 * Provides dependency-injectable access to the legacy static service methods.
 */
class RedisCache
{
    /** Default cache TTL in seconds (5 minutes) — matches legacy. */
    private const DEFAULT_TTL = 300;

    public function __construct()
    {
    }

    /**
     * Delegates to legacy RedisCache::get().
     */
    public function get(string $key, ?int $tenantId = null)
    {
        return \Nexus\Services\RedisCache::get($key, $tenantId);
    }

    /**
     * Delegates to legacy RedisCache::set().
     */
    public function set(string $key, $value, int $ttl = self::DEFAULT_TTL, ?int $tenantId = null): bool
    {
        return \Nexus\Services\RedisCache::set($key, $value, $ttl, $tenantId);
    }

    /**
     * Delegates to legacy RedisCache::delete().
     */
    public function delete(string $key, ?int $tenantId = null): bool
    {
        return \Nexus\Services\RedisCache::delete($key, $tenantId);
    }

    /**
     * Delegates to legacy RedisCache::increment().
     */
    public function increment(string $key, int $ttl = self::DEFAULT_TTL, ?int $tenantId = null): int
    {
        return \Nexus\Services\RedisCache::increment($key, $ttl, $tenantId);
    }

    /**
     * Delegates to legacy RedisCache::has().
     */
    public function has(string $key, ?int $tenantId = null): bool
    {
        return \Nexus\Services\RedisCache::has($key, $tenantId);
    }

    /**
     * Clear all cache for a tenant — delegates to legacy RedisCache.
     *
     * @return int Number of keys deleted
     */
    public function clearTenant(?int $tenantId = null): int
    {
        return \Nexus\Services\RedisCache::clearTenant($tenantId);
    }

    /**
     * Get cache statistics — delegates to legacy RedisCache.
     */
    public function getStats(): array
    {
        return \Nexus\Services\RedisCache::getStats();
    }
}

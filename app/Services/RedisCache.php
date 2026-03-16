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
}

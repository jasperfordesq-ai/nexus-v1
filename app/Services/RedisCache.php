<?php
// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

namespace App\Services;

/**
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
        \Illuminate\Support\Facades\Log::warning('Legacy delegation removed: ' . __METHOD__);
        return null;
    }

    /**
     * Delegates to legacy RedisCache::set().
     */
    public function set(string $key, $value, int $ttl = self::DEFAULT_TTL, ?int $tenantId = null): bool
    {
        \Illuminate\Support\Facades\Log::warning('Legacy delegation removed: ' . __METHOD__);
        return false;
    }

    /**
     * Delegates to legacy RedisCache::delete().
     */
    public function delete(string $key, ?int $tenantId = null): bool
    {
        \Illuminate\Support\Facades\Log::warning('Legacy delegation removed: ' . __METHOD__);
        return false;
    }

    /**
     * Delegates to legacy RedisCache::increment().
     */
    public function increment(string $key, int $ttl = self::DEFAULT_TTL, ?int $tenantId = null): int
    {
        \Illuminate\Support\Facades\Log::warning('Legacy delegation removed: ' . __METHOD__);
        return 0;
    }

    /**
     * Delegates to legacy RedisCache::has().
     */
    public function has(string $key, ?int $tenantId = null): bool
    {
        \Illuminate\Support\Facades\Log::warning('Legacy delegation removed: ' . __METHOD__);
        return false;
    }

    /**
     * Clear all cache for a tenant — delegates to legacy RedisCache.
     *
     * @return int Number of keys deleted
     */
    public function clearTenant(?int $tenantId = null): int
    {
        \Illuminate\Support\Facades\Log::warning('Legacy delegation removed: ' . __METHOD__);
        return 0;
    }

    /**
     * Get cache statistics — delegates to legacy RedisCache.
     */
    public function getStats(): array
    {
        \Illuminate\Support\Facades\Log::warning('Legacy delegation removed: ' . __METHOD__);
        return [];
    }
}

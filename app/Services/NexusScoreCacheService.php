<?php
// Copyright � 2024�2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

namespace App\Services;

/**
 * NexusScoreCacheService � Laravel DI wrapper for legacy \Nexus\Services\NexusScoreCacheService.
 *
 * Provides dependency-injectable access to the legacy static service methods.
 */
class NexusScoreCacheService
{
    public function __construct()
    {
    }

    /**
     * Delegates to legacy NexusScoreCacheService::get().
     */
    public function get(int $tenantId, int $userId): ?float
    {
        return \Nexus\Services\NexusScoreCacheService::get($tenantId, $userId);
    }

    /**
     * Delegates to legacy NexusScoreCacheService::set().
     */
    public function set(int $tenantId, int $userId, float $score): void
    {
        \Nexus\Services\NexusScoreCacheService::set($tenantId, $userId, $score);
    }

    /**
     * Delegates to legacy NexusScoreCacheService::invalidate().
     */
    public function invalidate(int $tenantId, int $userId): void
    {
        \Nexus\Services\NexusScoreCacheService::invalidate($tenantId, $userId);
    }

    /**
     * Delegates to legacy NexusScoreCacheService::warmCache().
     */
    public function warmCache(int $tenantId): int
    {
        return \Nexus\Services\NexusScoreCacheService::warmCache($tenantId);
    }

    /**
     * Delegates to legacy NexusScoreCacheService::getScore().
     *
     * Returns score data from cache or calculates fresh.
     * The legacy service requires a PDO instance passed to the constructor.
     */
    public function getScore(int $userId, int $tenantId, bool $forceRecalculate = false): array
    {
        $db = \Illuminate\Support\Facades\DB::getPdo();
        $legacyService = new \Nexus\Services\NexusScoreCacheService($db);
        return $legacyService->getScore($userId, $tenantId, $forceRecalculate);
    }

    /**
     * Delegates to legacy NexusScoreCacheService::invalidateCache().
     */
    public function invalidateCache(int $userId, int $tenantId): void
    {
        $db = \Illuminate\Support\Facades\DB::getPdo();
        $legacyService = new \Nexus\Services\NexusScoreCacheService($db);
        $legacyService->invalidateCache($userId, $tenantId);
    }

    /**
     * Delegates to legacy NexusScoreCacheService::getCachedLeaderboard().
     */
    public function getCachedLeaderboard(int $tenantId, int $limit = 10): array
    {
        $db = \Illuminate\Support\Facades\DB::getPdo();
        $legacyService = new \Nexus\Services\NexusScoreCacheService($db);
        return $legacyService->getCachedLeaderboard($tenantId, $limit);
    }

    /**
     * Delegates to legacy NexusScoreCacheService::getCachedRank().
     */
    public function getCachedRank(int $userId, int $tenantId): array
    {
        $db = \Illuminate\Support\Facades\DB::getPdo();
        $legacyService = new \Nexus\Services\NexusScoreCacheService($db);
        return $legacyService->getCachedRank($userId, $tenantId);
    }
}

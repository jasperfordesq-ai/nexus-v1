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
class RankingService
{
    public const DEFAULT_SCORE = 1.0;
    public const EARTH_RADIUS_KM = 6371;

    public function __construct()
    {
    }

    /**
     * Get shared ranking configuration.
     */
    public static function getSharedConfig(): array
    {
        return [
            'default_score' => self::DEFAULT_SCORE,
            'earth_radius_km' => self::EARTH_RADIUS_KM,
        ];
    }

    /**
     * Delegates to legacy RankingService::getRankings().
     */
    public function getRankings(int $tenantId, string $type = 'overall', int $limit = 20): array
    {
        \Illuminate\Support\Facades\Log::warning('Legacy delegation removed: ' . __METHOD__);
        return [];
    }

    /**
     * Delegates to legacy RankingService::getUserRanking().
     */
    public function getUserRanking(int $tenantId, int $userId): ?array
    {
        \Illuminate\Support\Facades\Log::warning('Legacy delegation removed: ' . __METHOD__);
        return null;
    }

    /**
     * Delegates to legacy RankingService::recalculate().
     */
    public function recalculate(int $tenantId): bool
    {
        \Illuminate\Support\Facades\Log::warning('Legacy delegation removed: ' . __METHOD__);
        return false;
    }
}

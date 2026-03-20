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
class RankingService
{
    public function __construct()
    {
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

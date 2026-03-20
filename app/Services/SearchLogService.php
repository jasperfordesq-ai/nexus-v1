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
class SearchLogService
{
    public function __construct()
    {
    }

    /**
     * Delegates to legacy SearchLogService::log().
     */
    public function log(string $query, string $searchType = 'all', int $resultCount = 0, ?int $userId = null, ?array $filters = null): void
    {
        \Illuminate\Support\Facades\Log::warning('Legacy delegation removed: ' . __METHOD__);
    }

    /**
     * Delegates to legacy SearchLogService::getTrendingSearches().
     */
    public function getTrendingSearches(int $days = 7, int $limit = 20): array
    {
        \Illuminate\Support\Facades\Log::warning('Legacy delegation removed: ' . __METHOD__);
        return [];
    }

    /**
     * Delegates to legacy SearchLogService::getZeroResultSearches().
     */
    public function getZeroResultSearches(int $days = 30, int $limit = 20): array
    {
        \Illuminate\Support\Facades\Log::warning('Legacy delegation removed: ' . __METHOD__);
        return [];
    }

    /**
     * Delegates to legacy SearchLogService::getAnalyticsSummary().
     */
    public function getAnalyticsSummary(int $days = 30): array
    {
        \Illuminate\Support\Facades\Log::warning('Legacy delegation removed: ' . __METHOD__);
        return [];
    }

    /**
     * Delegates to legacy SearchLogService::cleanupOldLogs().
     */
    public function cleanupOldLogs(): int
    {
        \Illuminate\Support\Facades\Log::warning('Legacy delegation removed: ' . __METHOD__);
        return 0;
    }
}

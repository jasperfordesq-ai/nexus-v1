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
class MatchLearningService
{
    public function __construct()
    {
    }

    /**
     * Delegates to legacy MatchLearningService::getHistoricalBoost().
     */
    public function getHistoricalBoost($userId, $candidateListing): float
    {
        \Illuminate\Support\Facades\Log::warning('Legacy delegation removed: ' . __METHOD__);
        return 0.0;
    }

    /**
     * Delegates to legacy MatchLearningService::recordInteraction().
     */
    public function recordInteraction($userId, $listingId, $action, array $metadata = []): bool
    {
        \Illuminate\Support\Facades\Log::warning('Legacy delegation removed: ' . __METHOD__);
        return false;
    }

    /**
     * Delegates to legacy MatchLearningService::getCategoryAffinities().
     */
    public function getCategoryAffinities($userId): array
    {
        \Illuminate\Support\Facades\Log::warning('Legacy delegation removed: ' . __METHOD__);
        return [];
    }

    /**
     * Delegates to legacy MatchLearningService::getLearnedDistancePreference().
     */
    public function getLearnedDistancePreference($userId): array
    {
        \Illuminate\Support\Facades\Log::warning('Legacy delegation removed: ' . __METHOD__);
        return [];
    }

    /**
     * Delegates to legacy MatchLearningService::getLearningStats().
     */
    public function getLearningStats(): array
    {
        \Illuminate\Support\Facades\Log::warning('Legacy delegation removed: ' . __METHOD__);
        return [];
    }
}

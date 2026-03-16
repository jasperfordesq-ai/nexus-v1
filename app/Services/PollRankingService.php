<?php
// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

namespace App\Services;

/**
 * PollRankingService — Laravel DI wrapper for legacy \Nexus\Services\PollRankingService.
 *
 * Provides dependency-injectable access to the legacy static service methods.
 */
class PollRankingService
{
    public function __construct()
    {
    }

    /**
     * Delegates to legacy PollRankingService::getErrors().
     */
    public function getErrors(): array
    {
        return \Nexus\Services\PollRankingService::getErrors();
    }

    /**
     * Delegates to legacy PollRankingService::submitRanking().
     */
    public function submitRanking(int $pollId, int $userId, array $rankings): bool
    {
        return \Nexus\Services\PollRankingService::submitRanking($pollId, $userId, $rankings);
    }

    /**
     * Delegates to legacy PollRankingService::calculateResults().
     */
    public function calculateResults(int $pollId): array
    {
        return \Nexus\Services\PollRankingService::calculateResults($pollId);
    }

    /**
     * Delegates to legacy PollRankingService::getUserRankings().
     */
    public function getUserRankings(int $pollId, int $userId): ?array
    {
        return \Nexus\Services\PollRankingService::getUserRankings($pollId, $userId);
    }
}

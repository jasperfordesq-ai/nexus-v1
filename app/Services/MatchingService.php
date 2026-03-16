<?php
// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

namespace App\Services;

/**
 * MatchingService — Laravel DI wrapper for legacy \Nexus\Services\MatchingService.
 *
 * Provides dependency-injectable access to the legacy static service methods.
 */
class MatchingService
{
    public function __construct()
    {
    }

    /**
     * Delegates to legacy MatchingService::getSuggestionsForUser().
     */
    public function getSuggestionsForUser($userId, $limit = 5, array $options = [])
    {
        return \Nexus\Services\MatchingService::getSuggestionsForUser($userId, $limit, $options);
    }

    /**
     * Delegates to legacy MatchingService::getHotMatches().
     */
    public function getHotMatches($userId, $limit = 5)
    {
        return \Nexus\Services\MatchingService::getHotMatches($userId, $limit);
    }

    /**
     * Delegates to legacy MatchingService::getMutualMatches().
     */
    public function getMutualMatches($userId, $limit = 10)
    {
        return \Nexus\Services\MatchingService::getMutualMatches($userId, $limit);
    }

    /**
     * Delegates to legacy MatchingService::getMatchesByType().
     */
    public function getMatchesByType($userId)
    {
        return \Nexus\Services\MatchingService::getMatchesByType($userId);
    }

    /**
     * Delegates to legacy MatchingService::savePreferences().
     */
    public function savePreferences($userId, array $preferences)
    {
        return \Nexus\Services\MatchingService::savePreferences($userId, $preferences);
    }
}

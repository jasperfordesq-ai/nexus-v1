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
        \Illuminate\Support\Facades\Log::warning('Legacy delegation removed: ' . __METHOD__);
        return null;
    }

    /**
     * Delegates to legacy MatchingService::getHotMatches().
     */
    public function getHotMatches($userId, $limit = 5)
    {
        \Illuminate\Support\Facades\Log::warning('Legacy delegation removed: ' . __METHOD__);
        return null;
    }

    /**
     * Delegates to legacy MatchingService::getMutualMatches().
     */
    public function getMutualMatches($userId, $limit = 10)
    {
        \Illuminate\Support\Facades\Log::warning('Legacy delegation removed: ' . __METHOD__);
        return null;
    }

    /**
     * Delegates to legacy MatchingService::getMatchesByType().
     */
    public function getMatchesByType($userId)
    {
        \Illuminate\Support\Facades\Log::warning('Legacy delegation removed: ' . __METHOD__);
        return null;
    }

    /**
     * Delegates to legacy MatchingService::savePreferences().
     */
    public function savePreferences($userId, array $preferences)
    {
        \Illuminate\Support\Facades\Log::warning('Legacy delegation removed: ' . __METHOD__);
        return null;
    }

    /**
     * Delegates to legacy MatchingService::getPreferences().
     */
    public function getPreferences($userId)
    {
        return static::getPreferencesStatic($userId);
    }

    /**
     * Static proxy for getPreferences — used by code that cannot inject an instance.
     */
    public static function getPreferencesStatic($userId)
    {
        \Illuminate\Support\Facades\Log::warning('Legacy delegation removed: ' . __METHOD__);
        return null;
    }
}

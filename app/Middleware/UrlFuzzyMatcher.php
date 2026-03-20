<?php
// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

namespace App\Middleware;

use Nexus\Middleware\UrlFuzzyMatcher as LegacyUrlFuzzyMatcher;

/**
 * App-namespace wrapper for Nexus\Middleware\UrlFuzzyMatcher.
 *
 * Delegates to the legacy implementation.
 */
class UrlFuzzyMatcher
{
    /**
     * @return string|null
     */
    public static function findSuggestion($requestedUrl)
    {
        if (!class_exists(LegacyUrlFuzzyMatcher::class)) { return null; }
        return LegacyUrlFuzzyMatcher::findSuggestion($requestedUrl);
    }

    /**
     * @return int
     */
    public static function calculateDistance($str1, $str2)
    {
        if (!class_exists(LegacyUrlFuzzyMatcher::class)) { return levenshtein(strtolower($str1), strtolower($str2)); }
        return LegacyUrlFuzzyMatcher::calculateDistance($str1, $str2);
    }
}

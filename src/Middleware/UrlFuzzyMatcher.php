<?php
// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

namespace Nexus\Middleware;

use App\Middleware\UrlFuzzyMatcher as AppUrlFuzzyMatcher;

/**
 * Legacy delegate — real implementation is now in App\Middleware\UrlFuzzyMatcher.
 *
 * @deprecated Use App\Middleware\UrlFuzzyMatcher directly.
 */
class UrlFuzzyMatcher
{
    public static function findSuggestion($requestedUrl)
    {
        return AppUrlFuzzyMatcher::findSuggestion($requestedUrl);
    }

    public static function calculateDistance($str1, $str2)
    {
        return AppUrlFuzzyMatcher::calculateDistance($str1, $str2);
    }
}

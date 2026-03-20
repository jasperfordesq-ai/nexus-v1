<?php
// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

namespace App\Helpers;

use Nexus\Helpers\HtmlSanitizer as LegacyHtmlSanitizer;

/**
 * App-namespace wrapper for Nexus\Helpers\HtmlSanitizer.
 *
 * Delegates to the legacy implementation. Once the Laravel migration is
 * complete this can be replaced with a Laravel-native sanitizer.
 */
class HtmlSanitizer
{
    public static function sanitize(string $html, bool $allowImages = true): string
    {
        if (!class_exists(LegacyHtmlSanitizer::class)) { return htmlspecialchars($html, ENT_QUOTES, 'UTF-8'); }
        return LegacyHtmlSanitizer::sanitize($html, $allowImages);
    }

    public static function stripAll(string $html): string
    {
        if (!class_exists(LegacyHtmlSanitizer::class)) { return htmlspecialchars(strip_tags($html), ENT_QUOTES, 'UTF-8'); }
        return LegacyHtmlSanitizer::stripAll($html);
    }
}

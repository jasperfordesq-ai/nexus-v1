<?php
// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

namespace Nexus\Helpers;

use App\Helpers\HtmlSanitizer as AppHtmlSanitizer;

/**
 * Legacy delegate — real implementation is now in App\Helpers\HtmlSanitizer.
 *
 * @deprecated Use App\Helpers\HtmlSanitizer directly.
 */
class HtmlSanitizer
{
    public static function sanitize(string $html, bool $allowImages = true): string
    {
        return AppHtmlSanitizer::sanitize($html, $allowImages);
    }

    public static function stripAll(string $html): string
    {
        return AppHtmlSanitizer::stripAll($html);
    }
}

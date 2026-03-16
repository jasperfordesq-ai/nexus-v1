<?php
// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

namespace App\Services;

/**
 * HtmlSanitizer — Laravel DI wrapper for legacy \Nexus\Services\HtmlSanitizer.
 *
 * Provides dependency-injectable access to the legacy static service methods.
 */
class HtmlSanitizer
{
    public function __construct()
    {
    }

    /**
     * Delegates to legacy HtmlSanitizer::sanitize().
     */
    public function sanitize(string $html): string
    {
        return \Nexus\Services\HtmlSanitizer::sanitize($html);
    }

    /**
     * Delegates to legacy HtmlSanitizer::containsHtml().
     */
    public function containsHtml(string $content): bool
    {
        return \Nexus\Services\HtmlSanitizer::containsHtml($content);
    }

    /**
     * Delegates to legacy HtmlSanitizer::toPlainText().
     */
    public function toPlainText(string $html): string
    {
        return \Nexus\Services\HtmlSanitizer::toPlainText($html);
    }
}

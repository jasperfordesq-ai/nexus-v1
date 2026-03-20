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
        \Illuminate\Support\Facades\Log::warning('Legacy delegation removed: ' . __METHOD__);
        return '';
    }

    /**
     * Delegates to legacy HtmlSanitizer::containsHtml().
     */
    public function containsHtml(string $content): bool
    {
        \Illuminate\Support\Facades\Log::warning('Legacy delegation removed: ' . __METHOD__);
        return false;
    }

    /**
     * Delegates to legacy HtmlSanitizer::toPlainText().
     */
    public function toPlainText(string $html): string
    {
        \Illuminate\Support\Facades\Log::warning('Legacy delegation removed: ' . __METHOD__);
        return '';
    }
}

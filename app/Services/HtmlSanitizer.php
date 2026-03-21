<?php
// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

namespace App\Services;

use App\Helpers\HtmlSanitizer as HtmlSanitizerHelper;

/**
 * HTML Sanitization Service
 *
 * Provides dependency-injectable access to HTML sanitization methods.
 * Delegates to App\Helpers\HtmlSanitizer for the actual sanitization logic.
 */
class HtmlSanitizer
{
    /**
     * Sanitize HTML content, removing dangerous tags and attributes while
     * preserving safe formatting elements.
     *
     * @param string $html The HTML content to sanitize
     * @return string Sanitized HTML
     */
    public function sanitize(string $html): string
    {
        return HtmlSanitizerHelper::sanitize($html);
    }

    /**
     * Check whether a string contains HTML tags.
     *
     * Returns true if the string contains any HTML-like markup (opening or
     * self-closing tags). Plain text and HTML entities (e.g. &amp;) do not
     * count as HTML.
     *
     * @param string $content The content to inspect
     * @return bool True if HTML tags are detected
     */
    public function containsHtml(string $content): bool
    {
        if (empty($content)) {
            return false;
        }

        // Match any opening, closing, or self-closing HTML tag
        return (bool) preg_match('/<[a-z][a-z0-9]*\b[^>]*\/?>/i', $content);
    }

    /**
     * Convert HTML content to plain text.
     *
     * Strips all HTML tags (including script/style blocks), decodes HTML
     * entities, and normalizes whitespace.
     *
     * @param string $html The HTML content
     * @return string Plain text
     */
    public function toPlainText(string $html): string
    {
        if (empty($html)) {
            return '';
        }

        return HtmlSanitizerHelper::stripTags($html);
    }

    /**
     * Sanitize HTML content with optional style attribute support (CMS mode).
     *
     * @param string $html        The HTML content to sanitize
     * @param bool   $allowStyles Whether to keep safe CSS style attributes
     * @return string Sanitized HTML
     */
    public function sanitizeCms(string $html, bool $allowStyles = false): string
    {
        return HtmlSanitizerHelper::sanitizeCms($html, $allowStyles);
    }

    /**
     * Strip all HTML tags and return escaped plain text.
     *
     * @param string $html The HTML content
     * @return string Escaped plain text
     */
    public function stripAll(string $html): string
    {
        return HtmlSanitizerHelper::stripAll($html);
    }

    /**
     * Create a plain-text excerpt from HTML content.
     *
     * @param string $html   The HTML content
     * @param int    $length Maximum excerpt length
     * @return string Truncated plain text with '...' if shortened
     */
    public function excerpt(string $html, int $length = 160): string
    {
        return HtmlSanitizerHelper::excerpt($html, $length);
    }

    /**
     * Sanitize a CSS style string, removing dangerous expressions.
     *
     * @param string $style The CSS style string
     * @return string Sanitized style string
     */
    public function sanitizeStyle(string $style): string
    {
        return HtmlSanitizerHelper::sanitizeStyle($style);
    }
}

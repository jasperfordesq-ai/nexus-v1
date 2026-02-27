<?php
// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

namespace Nexus\Services;

/**
 * HtmlSanitizer — Whitelist-based HTML sanitizer for user-generated rich text.
 *
 * Allows a minimal set of formatting tags (bold, italic, underline, lists, links)
 * and strips everything else. Used by FeedService for rich text posts.
 */
class HtmlSanitizer
{
    /** Tags allowed in rich text content */
    private const ALLOWED_TAGS = [
        'p', 'br', 'strong', 'em', 'u', 'ul', 'ol', 'li', 'a',
    ];

    /** Attributes allowed per tag */
    private const ALLOWED_ATTRS = [
        'a' => ['href', 'target', 'rel'],
    ];

    /**
     * Sanitize HTML content using a whitelist approach.
     *
     * @param string $html Raw HTML from the client
     * @return string Sanitized HTML safe for storage and rendering
     */
    public static function sanitize(string $html): string
    {
        $html = trim($html);
        if (empty($html)) {
            return '';
        }

        // Strip tags not in the whitelist
        $allowedTagStr = implode('', array_map(fn($t) => "<{$t}>", self::ALLOWED_TAGS));
        $html = strip_tags($html, $allowedTagStr);

        // Parse and clean attributes
        $html = self::cleanAttributes($html);

        // Force safe link attributes
        $html = self::forceSafeLinkAttributes($html);

        return $html;
    }

    /**
     * Check if content contains HTML tags (vs plain text).
     */
    public static function containsHtml(string $content): bool
    {
        return (bool) preg_match('/<[a-z][\s\S]*>/i', $content);
    }

    /**
     * Extract plain text from HTML for search indexing, previews, etc.
     */
    public static function toPlainText(string $html): string
    {
        $text = strip_tags($html);
        $text = html_entity_decode($text, ENT_QUOTES | ENT_HTML5, 'UTF-8');
        $text = preg_replace('/\s+/', ' ', $text);
        return trim($text);
    }

    /**
     * Remove all attributes except those whitelisted per tag.
     */
    private static function cleanAttributes(string $html): string
    {
        return preg_replace_callback(
            '/<(\w+)([^>]*)>/i',
            function ($matches) {
                $tag = strtolower($matches[1]);
                $attrString = $matches[2];

                if (!in_array($tag, self::ALLOWED_TAGS, true)) {
                    return $matches[0];
                }

                $allowedAttrs = self::ALLOWED_ATTRS[$tag] ?? [];

                if (empty($allowedAttrs) || empty(trim($attrString))) {
                    return "<{$tag}>";
                }

                // Parse out allowed attributes
                $cleanAttrs = [];
                foreach ($allowedAttrs as $attr) {
                    if (preg_match("/\b{$attr}\s*=\s*([\"'])(.*?)\\1/i", $attrString, $m)) {
                        $value = htmlspecialchars($m[2], ENT_QUOTES | ENT_HTML5, 'UTF-8');
                        $cleanAttrs[] = "{$attr}=\"{$value}\"";
                    }
                }

                $attrStr = $cleanAttrs ? ' ' . implode(' ', $cleanAttrs) : '';
                return "<{$tag}{$attrStr}>";
            },
            $html
        ) ?? $html;
    }

    /**
     * Force target="_blank" and rel="noopener noreferrer" on all links.
     */
    private static function forceSafeLinkAttributes(string $html): string
    {
        return preg_replace_callback(
            '/<a\b([^>]*)>/i',
            function ($matches) {
                $attrs = $matches[1];

                // Extract href
                $href = '';
                if (preg_match('/href\s*=\s*"([^"]*)"/i', $attrs, $m)) {
                    $href = $m[1];
                }

                if (empty($href)) {
                    return '<a>';
                }

                // Block javascript: URIs
                if (preg_match('/^\s*javascript\s*:/i', $href)) {
                    return '<a>';
                }

                return "<a href=\"{$href}\" target=\"_blank\" rel=\"noopener noreferrer\">";
            },
            $html
        ) ?? $html;
    }
}

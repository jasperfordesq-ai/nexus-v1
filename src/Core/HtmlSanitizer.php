<?php
// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

namespace Nexus\Core;

/**
 * HTML Sanitizer for CMS Content
 *
 * Provides safe HTML output by allowing only specific tags and attributes.
 * Used for page builder content, blog posts, and other user-generated HTML.
 */
class HtmlSanitizer
{
    /**
     * Allowed HTML tags for CMS content
     */
    private static array $allowedTags = [
        // Structure
        'div', 'span', 'section', 'article', 'header', 'footer', 'main', 'aside', 'nav',
        // Text
        'p', 'h1', 'h2', 'h3', 'h4', 'h5', 'h6', 'br', 'hr',
        // Formatting
        'strong', 'b', 'em', 'i', 'u', 's', 'strike', 'sub', 'sup', 'small', 'mark',
        // Lists
        'ul', 'ol', 'li', 'dl', 'dt', 'dd',
        // Tables
        'table', 'thead', 'tbody', 'tfoot', 'tr', 'th', 'td', 'caption', 'colgroup', 'col',
        // Media
        'img', 'figure', 'figcaption', 'video', 'audio', 'source', 'picture',
        // Links
        'a',
        // Quotes
        'blockquote', 'q', 'cite',
        // Code
        'pre', 'code',
        // Forms (display only, not functional)
        'button',
        // Styles
        'style',
    ];

    /**
     * Allowed attributes per tag
     */
    private static array $allowedAttributes = [
        '*' => ['class', 'id', 'style', 'data-*', 'title', 'lang', 'dir'],
        'a' => ['href', 'target', 'rel', 'download'],
        'img' => ['src', 'alt', 'width', 'height', 'loading', 'srcset', 'sizes'],
        'video' => ['src', 'width', 'height', 'controls', 'autoplay', 'muted', 'loop', 'poster', 'preload'],
        'audio' => ['src', 'controls', 'autoplay', 'muted', 'loop', 'preload'],
        'source' => ['src', 'type', 'srcset', 'sizes', 'media'],
        'table' => ['border', 'cellpadding', 'cellspacing', 'width'],
        'td' => ['colspan', 'rowspan', 'width', 'height', 'valign', 'align'],
        'th' => ['colspan', 'rowspan', 'width', 'height', 'valign', 'align', 'scope'],
        'col' => ['span', 'width'],
        'colgroup' => ['span'],
        'button' => ['type', 'disabled'],
        'style' => ['type'],
    ];

    /**
     * Dangerous patterns to remove
     */
    private static array $dangerousPatterns = [
        // JavaScript event handlers
        '/\s*on\w+\s*=\s*["\'][^"\']*["\']/i',
        // JavaScript URLs
        '/javascript\s*:/i',
        // Data URLs (except images)
        '/data\s*:(?!image\/)/i',
        // VBScript
        '/vbscript\s*:/i',
        // Expression (IE)
        '/expression\s*\(/i',
        // Behavior (IE)
        '/behavior\s*:/i',
        // -moz-binding
        '/-moz-binding/i',
    ];

    /**
     * Sanitize HTML content for safe output
     *
     * @param string $html Raw HTML content
     * @param bool $allowStyles Whether to allow inline styles (default true for page builder)
     * @return string Sanitized HTML
     */
    public static function sanitize(string $html, bool $allowStyles = true): string
    {
        if (empty($html)) {
            return '';
        }

        // Remove null bytes
        $html = str_replace("\0", '', $html);

        // Remove dangerous patterns
        foreach (self::$dangerousPatterns as $pattern) {
            $html = preg_replace($pattern, '', $html);
        }

        // Use DOMDocument for proper HTML parsing
        $dom = new \DOMDocument('1.0', 'UTF-8');

        // Suppress warnings from malformed HTML
        libxml_use_internal_errors(true);

        // Disable external entity loading to prevent XXE attacks
        $previousEntityLoader = libxml_disable_entity_loader(true);

        // Wrap in container to preserve structure
        $wrapped = '<div id="__sanitizer_root__">' . $html . '</div>';
        $dom->loadHTML('<?xml encoding="UTF-8">' . $wrapped, LIBXML_HTML_NOIMPLIED | LIBXML_HTML_NODEFDTD | LIBXML_NOENT | LIBXML_NONET);

        // Restore previous entity loader state
        libxml_disable_entity_loader($previousEntityLoader);

        libxml_clear_errors();

        // Process all nodes
        self::processNode($dom->documentElement, $allowStyles);

        // Extract the sanitized content
        $root = $dom->getElementById('__sanitizer_root__');
        if ($root) {
            $output = '';
            foreach ($root->childNodes as $child) {
                $output .= $dom->saveHTML($child);
            }
            return $output;
        }

        return $dom->saveHTML();
    }

    /**
     * Process a DOM node recursively
     */
    private static function processNode(\DOMNode $node, bool $allowStyles): void
    {
        if ($node->nodeType !== XML_ELEMENT_NODE) {
            return;
        }

        /** @var \DOMElement $node */
        $tagName = strtolower($node->tagName);

        // Check if tag is allowed
        if (!in_array($tagName, self::$allowedTags) && $tagName !== 'div') {
            // Remove disallowed tags but keep their text content
            $parent = $node->parentNode;
            while ($node->firstChild) {
                $parent->insertBefore($node->firstChild, $node);
            }
            $parent->removeChild($node);
            return;
        }

        // Process attributes
        $attributesToRemove = [];
        foreach ($node->attributes as $attr) {
            $attrName = strtolower($attr->name);
            $attrValue = $attr->value;

            // Check if attribute is allowed
            $isAllowed = false;

            // Check global attributes
            if (isset(self::$allowedAttributes['*'])) {
                foreach (self::$allowedAttributes['*'] as $allowedAttr) {
                    if ($allowedAttr === $attrName ||
                        (str_ends_with($allowedAttr, '*') && str_starts_with($attrName, rtrim($allowedAttr, '*')))) {
                        $isAllowed = true;
                        break;
                    }
                }
            }

            // Check tag-specific attributes
            if (!$isAllowed && isset(self::$allowedAttributes[$tagName])) {
                $isAllowed = in_array($attrName, self::$allowedAttributes[$tagName]);
            }

            // Special handling for style attribute
            if ($attrName === 'style') {
                if (!$allowStyles) {
                    $isAllowed = false;
                } else {
                    // Sanitize style content
                    $attrValue = self::sanitizeStyle($attrValue);
                    $node->setAttribute($attrName, $attrValue);
                }
            }

            // Special handling for href/src
            if (in_array($attrName, ['href', 'src'])) {
                $attrValue = self::sanitizeUrl($attrValue);
                if ($attrValue === false) {
                    $attributesToRemove[] = $attrName;
                    continue;
                }
                $node->setAttribute($attrName, $attrValue);
            }

            if (!$isAllowed) {
                $attributesToRemove[] = $attr->name;
            }
        }

        // Remove disallowed attributes
        foreach ($attributesToRemove as $attrName) {
            $node->removeAttribute($attrName);
        }

        // Add security attributes for links
        if ($tagName === 'a' && $node->hasAttribute('target') && $node->getAttribute('target') === '_blank') {
            $rel = $node->getAttribute('rel') ?: '';
            if (strpos($rel, 'noopener') === false) {
                $rel .= ' noopener';
            }
            if (strpos($rel, 'noreferrer') === false) {
                $rel .= ' noreferrer';
            }
            $node->setAttribute('rel', trim($rel));
        }

        // Process child nodes (copy to array first as DOM may change)
        $children = [];
        foreach ($node->childNodes as $child) {
            $children[] = $child;
        }
        foreach ($children as $child) {
            self::processNode($child, $allowStyles);
        }
    }

    /**
     * Sanitize a URL
     *
     * @param string $url The URL to sanitize
     * @return string|false Sanitized URL or false if dangerous
     */
    private static function sanitizeUrl(string $url): string|false
    {
        $url = trim($url);

        // Allow relative URLs
        if (str_starts_with($url, '/') || str_starts_with($url, '#') || str_starts_with($url, '?')) {
            return $url;
        }

        // Allow http/https
        if (preg_match('/^https?:\/\//i', $url)) {
            return $url;
        }

        // Allow mailto and tel
        if (preg_match('/^(mailto|tel):/i', $url)) {
            return $url;
        }

        // Allow data: images only
        if (preg_match('/^data:image\/(png|jpe?g|gif|webp|svg\+xml);base64,/i', $url)) {
            return $url;
        }

        // Block everything else (javascript:, vbscript:, etc.)
        return false;
    }

    /**
     * Sanitize inline CSS
     *
     * @param string $style CSS style string
     * @return string Sanitized style
     */
    private static function sanitizeStyle(string $style): string
    {
        // Remove dangerous CSS patterns
        $dangerous = [
            '/expression\s*\(/i',
            '/javascript\s*:/i',
            '/vbscript\s*:/i',
            '/-moz-binding/i',
            '/behavior\s*:/i',
            '/url\s*\(\s*["\']?\s*javascript/i',
            '/url\s*\(\s*["\']?\s*data:(?!image)/i',
        ];

        foreach ($dangerous as $pattern) {
            $style = preg_replace($pattern, '', $style);
        }

        return $style;
    }

    /**
     * Strip all HTML tags, keeping only text
     *
     * @param string $html HTML content
     * @return string Plain text
     */
    public static function stripTags(string $html): string
    {
        // Decode entities first
        $text = html_entity_decode($html, ENT_QUOTES | ENT_HTML5, 'UTF-8');

        // Remove script and style content
        $text = preg_replace('/<script[^>]*>.*?<\/script>/is', '', $text);
        $text = preg_replace('/<style[^>]*>.*?<\/style>/is', '', $text);

        // Strip remaining tags
        $text = strip_tags($text);

        // Normalize whitespace
        $text = preg_replace('/\s+/', ' ', $text);

        return trim($text);
    }

    /**
     * Create excerpt from HTML content
     *
     * @param string $html HTML content
     * @param int $length Maximum length
     * @return string Plain text excerpt
     */
    public static function excerpt(string $html, int $length = 160): string
    {
        $text = self::stripTags($html);

        if (mb_strlen($text) <= $length) {
            return $text;
        }

        // Cut at word boundary
        $excerpt = mb_substr($text, 0, $length);
        $lastSpace = mb_strrpos($excerpt, ' ');

        if ($lastSpace !== false && $lastSpace > $length * 0.8) {
            $excerpt = mb_substr($excerpt, 0, $lastSpace);
        }

        return $excerpt . '...';
    }
}

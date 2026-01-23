<?php

namespace Nexus\Helpers;

/**
 * HTML Sanitizer - Removes potentially dangerous HTML while preserving safe formatting
 *
 * This is a lightweight sanitizer for user-generated content. For maximum security,
 * consider using HTMLPurifier for complex content requiring extensive HTML support.
 */
class HtmlSanitizer
{
    /**
     * Allowed HTML tags for basic content
     */
    private static array $allowedTags = [
        'p', 'br', 'strong', 'b', 'em', 'i', 'u', 's', 'strike',
        'h1', 'h2', 'h3', 'h4', 'h5', 'h6',
        'ul', 'ol', 'li',
        'blockquote', 'pre', 'code',
        'a', 'img',
        'table', 'thead', 'tbody', 'tr', 'th', 'td',
        'div', 'span',
        'hr', 'figure', 'figcaption'
    ];

    /**
     * Allowed attributes per tag
     */
    private static array $allowedAttributes = [
        'a' => ['href', 'title', 'target', 'rel'],
        'img' => ['src', 'alt', 'title', 'width', 'height', 'loading'],
        'td' => ['colspan', 'rowspan'],
        'th' => ['colspan', 'rowspan', 'scope'],
        'div' => ['class'],
        'span' => ['class'],
        'p' => ['class'],
        'figure' => ['class'],
        'blockquote' => ['class', 'cite'],
        'pre' => ['class'],
        'code' => ['class'],
        'table' => ['class']
    ];

    /**
     * Dangerous protocols to block in URLs
     */
    private static array $dangerousProtocols = [
        'javascript:', 'vbscript:', 'data:', 'file:'
    ];

    /**
     * Sanitize HTML content
     *
     * @param string $html The HTML content to sanitize
     * @param bool $allowImages Whether to allow img tags
     * @return string Sanitized HTML
     */
    public static function sanitize(string $html, bool $allowImages = true): string
    {
        if (empty($html)) {
            return '';
        }

        // Create allowed tags string for strip_tags
        $allowedTagList = self::$allowedTags;
        if (!$allowImages) {
            $allowedTagList = array_diff($allowedTagList, ['img']);
        }
        $tagString = '<' . implode('><', $allowedTagList) . '>';

        // First pass: strip disallowed tags
        $html = strip_tags($html, $tagString);

        // Second pass: sanitize attributes using DOMDocument
        $html = self::sanitizeAttributes($html, $allowImages);

        return $html;
    }

    /**
     * Sanitize attributes in HTML content
     *
     * @param string $html The HTML content
     * @param bool $allowImages Whether to allow img tags
     * @return string HTML with sanitized attributes
     */
    private static function sanitizeAttributes(string $html, bool $allowImages): string
    {
        // Suppress DOM warnings for malformed HTML
        libxml_use_internal_errors(true);

        $dom = new \DOMDocument('1.0', 'UTF-8');

        // Wrap in a container to preserve content
        $wrappedHtml = '<!DOCTYPE html><html><head><meta charset="UTF-8"></head><body>' . $html . '</body></html>';
        $dom->loadHTML($wrappedHtml, LIBXML_HTML_NOIMPLIED | LIBXML_HTML_NODEFDTD);

        libxml_clear_errors();

        // Process all elements
        $xpath = new \DOMXPath($dom);
        $elements = $xpath->query('//*');

        foreach ($elements as $element) {
            if (!($element instanceof \DOMElement)) {
                continue;
            }

            $tagName = strtolower($element->tagName);

            // Skip structural elements
            if (in_array($tagName, ['html', 'head', 'body', 'meta'])) {
                continue;
            }

            // Remove event handlers and dangerous attributes
            $attributesToRemove = [];
            foreach ($element->attributes as $attr) {
                $attrName = strtolower($attr->name);
                $attrValue = $attr->value;

                // Always remove event handlers
                if (strpos($attrName, 'on') === 0) {
                    $attributesToRemove[] = $attr->name;
                    continue;
                }

                // Remove style attribute (prevents CSS injection)
                if ($attrName === 'style') {
                    $attributesToRemove[] = $attr->name;
                    continue;
                }

                // Check if attribute is allowed for this tag
                $allowedAttrs = self::$allowedAttributes[$tagName] ?? [];
                if (!in_array($attrName, $allowedAttrs) && $attrName !== 'class') {
                    $attributesToRemove[] = $attr->name;
                    continue;
                }

                // Sanitize URL attributes
                if (in_array($attrName, ['href', 'src'])) {
                    $sanitizedUrl = self::sanitizeUrl($attrValue);
                    if ($sanitizedUrl === false) {
                        $attributesToRemove[] = $attr->name;
                    } else {
                        $element->setAttribute($attr->name, $sanitizedUrl);
                    }
                }
            }

            // Remove marked attributes
            foreach ($attributesToRemove as $attrName) {
                $element->removeAttribute($attrName);
            }

            // Add security attributes to links
            if ($tagName === 'a') {
                $element->setAttribute('rel', 'noopener noreferrer');
            }
        }

        // Extract body content
        $body = $dom->getElementsByTagName('body')->item(0);
        if (!$body) {
            return htmlspecialchars($html, ENT_QUOTES, 'UTF-8');
        }

        $result = '';
        foreach ($body->childNodes as $child) {
            $result .= $dom->saveHTML($child);
        }

        return $result;
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

        // Block dangerous protocols
        $lowerUrl = strtolower(trim(preg_replace('/\s+/', '', $url)));
        foreach (self::$dangerousProtocols as $protocol) {
            if (strpos($lowerUrl, $protocol) === 0) {
                return false;
            }
        }

        // Allow relative URLs, http, https, mailto
        if (preg_match('/^(https?:\/\/|mailto:|\/|#)/', $lowerUrl) || !preg_match('/^[a-z]+:/i', $url)) {
            return htmlspecialchars($url, ENT_QUOTES, 'UTF-8');
        }

        return false;
    }

    /**
     * Strip all HTML tags and return plain text
     *
     * @param string $html The HTML content
     * @return string Plain text
     */
    public static function stripAll(string $html): string
    {
        return htmlspecialchars(strip_tags($html), ENT_QUOTES, 'UTF-8');
    }
}

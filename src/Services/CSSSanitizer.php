<?php
// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

namespace Nexus\Services;

/**
 * CSS Sanitizer
 *
 * Prevents XSS attacks via CSS injection by validating and sanitizing
 * custom CSS from layout builder.
 *
 * Part of Custom Layout Builder (125/100 LEGENDARY)
 */
class CSSSanitizer
{
    /**
     * Dangerous patterns that should never appear in CSS
     */
    private array $blacklist = [
        'expression',      // IE expression()
        'javascript:',     // javascript: protocol
        'vbscript:',      // vbscript: protocol
        'data:text/html', // data URIs with HTML
        '-moz-binding',   // XBL bindings
        'behavior:',      // IE behaviors
        '@import',        // External imports (can load external CSS)
        'document.',      // JavaScript DOM access
        'window.',        // JavaScript window access
        '<script',        // Script tags
        'eval(',          // JavaScript eval
    ];

    /**
     * Allowed CSS properties (whitelist approach)
     */
    private array $allowedProperties = [
        // Colors
        'color', 'background', 'background-color', 'background-image',
        'background-size', 'background-position', 'background-repeat',
        'border-color', 'outline-color',

        // Typography
        'font-family', 'font-size', 'font-weight', 'font-style',
        'line-height', 'letter-spacing', 'text-align', 'text-decoration',
        'text-transform', 'text-shadow',

        // Box Model
        'margin', 'margin-top', 'margin-right', 'margin-bottom', 'margin-left',
        'padding', 'padding-top', 'padding-right', 'padding-bottom', 'padding-left',
        'width', 'height', 'min-width', 'max-width', 'min-height', 'max-height',

        // Borders
        'border', 'border-top', 'border-right', 'border-bottom', 'border-left',
        'border-width', 'border-style', 'border-radius',

        // Display & Positioning
        'display', 'position', 'top', 'right', 'bottom', 'left',
        'flex', 'flex-direction', 'flex-wrap', 'justify-content', 'align-items',
        'grid', 'grid-template-columns', 'grid-template-rows', 'grid-gap',

        // Visual Effects
        'opacity', 'box-shadow', 'filter', 'transform', 'transition',
        'animation', 'animation-name', 'animation-duration',

        // Overflow
        'overflow', 'overflow-x', 'overflow-y',

        // Cursor
        'cursor',

        // Z-index
        'z-index',
    ];

    /**
     * Sanitize CSS input
     *
     * @param string $css Raw CSS from user
     * @return string Sanitized CSS
     * @throws \Exception If dangerous patterns detected
     */
    public function sanitize(string $css): string
    {
        // Remove comments
        $css = preg_replace('/\/\*.*?\*\//s', '', $css);

        // Check for blacklisted terms
        foreach ($this->blacklist as $term) {
            if (stripos($css, $term) !== false) {
                throw new \Exception("Dangerous CSS detected: $term is not allowed");
            }
        }

        // Parse and validate rules
        $sanitized = '';
        preg_match_all('/([^{]+)\{([^}]+)\}/s', $css, $matches, PREG_SET_ORDER);

        foreach ($matches as $match) {
            $selector = trim($match[1]);
            $properties = trim($match[2]);

            // Validate selector
            if (!$this->isValidSelector($selector)) {
                continue; // Skip invalid selectors
            }

            // Sanitize properties
            $sanitizedProps = $this->sanitizeProperties($properties);
            if (!empty($sanitizedProps)) {
                $sanitized .= "$selector {\n  $sanitizedProps\n}\n\n";
            }
        }

        return $sanitized;
    }

    /**
     * Validate CSS selector
     *
     * @param string $selector CSS selector
     * @return bool
     */
    private function isValidSelector(string $selector): bool
    {
        // Block selectors with dangerous patterns
        $dangerousPatterns = [
            '/javascript:/i',
            '/expression/i',
            '/<script/i',
            '/document\./i',
            '/window\./i',
        ];

        foreach ($dangerousPatterns as $pattern) {
            if (preg_match($pattern, $selector)) {
                return false;
            }
        }

        // Allow: .class, #id, element, [attr], :pseudo, ::pseudo
        // This is a simplified check - real selector validation is complex
        return true;
    }

    /**
     * Sanitize CSS properties
     *
     * @param string $properties Raw CSS properties
     * @return string Sanitized properties
     */
    private function sanitizeProperties(string $properties): string
    {
        $sanitized = [];
        $lines = explode(';', $properties);

        foreach ($lines as $line) {
            $parts = explode(':', $line, 2);
            if (count($parts) !== 2) continue;

            $property = trim($parts[0]);
            $value = trim($parts[1]);

            // Only allow whitelisted properties
            if (!in_array($property, $this->allowedProperties)) {
                continue;
            }

            // Sanitize value
            if ($this->isValidValue($value)) {
                $sanitized[] = "$property: $value";
            }
        }

        return implode(";\n  ", $sanitized);
    }

    /**
     * Validate CSS value
     *
     * @param string $value CSS property value
     * @return bool
     */
    private function isValidValue(string $value): bool
    {
        // Check for dangerous patterns in value
        foreach ($this->blacklist as $term) {
            if (stripos($value, $term) !== false) {
                return false;
            }
        }

        // Block data URIs except for safe image types
        if (stripos($value, 'data:') !== false) {
            // Allow only image data URIs
            if (!preg_match('/data:image\/(png|jpg|jpeg|gif|svg\+xml|webp);base64,/', $value)) {
                return false;
            }
        }

        return true;
    }

    /**
     * Quick validation check (for API validation endpoint)
     *
     * @param string $css CSS to validate
     * @return array ['valid' => bool, 'errors' => string[]]
     */
    public function validate(string $css): array
    {
        $errors = [];

        try {
            $this->sanitize($css);
        } catch (\Exception $e) {
            $errors[] = $e->getMessage();
        }

        return [
            'valid' => empty($errors),
            'errors' => $errors
        ];
    }
}

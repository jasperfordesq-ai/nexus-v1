<?php
// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

namespace App\Helpers;

/**
 * Image Helper
 *
 * Provides utilities for serving optimized images with WebP support
 */
class ImageHelper
{
    /**
     * Generate a <picture> tag with WebP source and fallback
     *
     * @param string $imagePath Original image path (e.g., "/assets/img/logo.png")
     * @param string $alt Alt text for accessibility
     * @param string $class CSS classes to apply
     * @param array $attributes Additional HTML attributes
     * @return string HTML <picture> tag
     */
    public static function webp(
        string $imagePath,
        string $alt = '',
        string $class = '',
        array $attributes = []
    ): string {
        $imagePath = trim($imagePath);
        if (empty($imagePath)) {
            $imagePath = '/assets/img/defaults/default_avatar.png';
        }

        $webpPath = self::getWebPPath($imagePath);
        $webpExists = self::webpExists($imagePath);

        $attrString = self::buildAttributesString($attributes);

        if (!isset($attributes['loading'])) {
            $attrString .= ' loading="lazy"';
        }

        if (isset($attributes['fetchpriority'])) {
            $attrString .= ' fetchpriority="' . htmlspecialchars($attributes['fetchpriority']) . '"';
        }

        if (!$webpExists) {
            return sprintf(
                '<img src="%s" alt="%s"%s%s>',
                htmlspecialchars($imagePath),
                htmlspecialchars($alt),
                $class ? ' class="' . htmlspecialchars($class) . '"' : '',
                $attrString
            );
        }

        return sprintf(
            '<picture><source srcset="%s" type="image/webp"><img src="%s" alt="%s"%s%s></picture>',
            htmlspecialchars($webpPath),
            htmlspecialchars($imagePath),
            htmlspecialchars($alt),
            $class ? ' class="' . htmlspecialchars($class) . '"' : '',
            $attrString
        );
    }

    /**
     * Get WebP version of image path
     */
    private static function getWebPPath(string $imagePath): string
    {
        return preg_replace('/\.(jpe?g|png)$/i', '.webp', $imagePath);
    }

    /**
     * Check if WebP version exists
     */
    private static function webpExists(string $imagePath): bool
    {
        if (self::isExternalUrl($imagePath)) {
            return false;
        }

        if (strpos($imagePath, '..') !== false) {
            return false;
        }

        $webpPath = self::getWebPPath($imagePath);

        $documentRoot = $_SERVER['DOCUMENT_ROOT'] ?? __DIR__ . '/../../httpdocs'; // nosemgrep: tainted-filename
        $filePath = $documentRoot . $webpPath;

        return file_exists($filePath); // nosemgrep: tainted-filename
    }

    /**
     * Check if a path is an external URL
     */
    private static function isExternalUrl(string $path): bool
    {
        return preg_match('#^https?://#i', $path) === 1;
    }

    /**
     * Build HTML attributes string from array
     */
    private static function buildAttributesString(array $attributes): string
    {
        $attrString = '';

        foreach ($attributes as $key => $value) {
            if (in_array($key, ['loading', 'fetchpriority'])) {
                continue;
            }

            if (is_bool($value)) {
                if ($value) {
                    $attrString .= ' ' . htmlspecialchars($key);
                }
            } else {
                $attrString .= sprintf(
                    ' %s="%s"',
                    htmlspecialchars($key),
                    htmlspecialchars($value)
                );
            }
        }

        return $attrString;
    }

    /**
     * Generate responsive image with srcset
     *
     * @param string $imagePath Base image path
     * @param string $alt Alt text
     * @param array $sizes Array of widths (e.g., [320, 640, 1024])
     * @param string $class CSS classes
     * @return string HTML <picture> tag with srcset
     */
    public static function responsive(
        string $imagePath,
        string $alt = '',
        array $sizes = [320, 640, 1024, 1920],
        string $class = ''
    ): string {
        $ext = pathinfo($imagePath, PATHINFO_EXTENSION);
        $basePath = substr($imagePath, 0, -strlen($ext) - 1);

        $webpSrcset = [];
        foreach ($sizes as $width) {
            $webpSrcset[] = "{$basePath}-{$width}w.webp {$width}w";
        }

        $fallbackSrcset = [];
        foreach ($sizes as $width) {
            $fallbackSrcset[] = "{$basePath}-{$width}w.{$ext} {$width}w";
        }

        return sprintf(
            '<picture>' .
            '<source srcset="%s" type="image/webp" sizes="100vw">' .
            '<img src="%s" srcset="%s" alt="%s"%s loading="lazy">' .
            '</picture>',
            implode(', ', $webpSrcset),
            $imagePath,
            implode(', ', $fallbackSrcset),
            htmlspecialchars($alt),
            $class ? ' class="' . htmlspecialchars($class) . '"' : ''
        );
    }

    /**
     * Get image dimensions
     *
     * @param string $imagePath Image path
     * @return array|false Array with 'width' and 'height', or false on failure
     */
    public static function getDimensions(string $imagePath)
    {
        $documentRoot = $_SERVER['DOCUMENT_ROOT'] ?? __DIR__ . '/../../httpdocs'; // nosemgrep: tainted-filename
        if (strpos($imagePath, '..') !== false) {
            return false;
        }
        $filePath = $documentRoot . $imagePath;
        $realPath = realpath($filePath);
        $realDocRoot = realpath($documentRoot);
        if ($realPath === false || $realDocRoot === false || strpos($realPath, $realDocRoot) !== 0) {
            return false;
        }

        $size = @getimagesize($realPath);

        if ($size === false) {
            return false;
        }

        return [
            'width' => $size[0],
            'height' => $size[1],
            'type' => $size[2],
            'mime' => $size['mime']
        ];
    }

    /**
     * Generate optimized avatar image
     *
     * @param string|null $avatarPath Avatar image path
     * @param string $userName User's name for alt text
     * @param int $size Size in pixels (default: 40)
     * @param array $attributes Optional HTML attributes
     * @return string HTML for avatar
     */
    public static function avatar(
        ?string $avatarPath,
        string $userName = 'User',
        int $size = 40,
        array $attributes = []
    ): string {
        $avatarPath = is_string($avatarPath) ? trim($avatarPath) : '';
        if (empty($avatarPath) || $avatarPath === 'null' || $avatarPath === 'undefined') {
            $avatarPath = '/assets/img/defaults/default_avatar.png';
        }

        $class = "avatar-img";
        $alt = htmlspecialchars($userName);

        $attrs = array_merge([
            'width' => $size,
            'height' => $size
        ], $attributes);

        return self::webp($avatarPath, $alt, $class, $attrs);
    }

    /**
     * Check if browser supports WebP
     *
     * @return bool True if browser accepts WebP
     */
    public static function browserSupportsWebP(): bool
    {
        if (!isset($_SERVER['HTTP_ACCEPT'])) {
            return false;
        }

        return strpos($_SERVER['HTTP_ACCEPT'], 'image/webp') !== false;
    }
}

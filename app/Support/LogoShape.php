<?php
// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

namespace App\Support;

/**
 * Classifies a tenant header logo by aspect ratio so the front ends can size it
 * sensibly: a wide wordmark needs little height, a square/stacked crest needs
 * more (and the header may grow to keep it readable).
 *
 * Returns one of: 'wide' (>= 2.8:1), 'landscape' (1.9–2.8:1), 'square' (< 1.9:1).
 * Anything that can't be measured falls back to 'landscape'.
 */
class LogoShape
{
    public static function classify(?string $url): string
    {
        $ratio = self::ratio($url);
        if ($ratio === null) {
            return 'landscape';
        }
        if ($ratio >= 2.8) {
            return 'wide';
        }
        if ($ratio >= 1.9) {
            return 'landscape';
        }
        return 'square';
    }

    /**
     * Brightness bucket of a logo so the front end can add a contrast backdrop
     * only where the logo would wash out against the navbar of a given theme:
     *  - 'light' (mostly white/bright) → needs a dark backdrop on a light header
     *  - 'dark'  (mostly dark)         → needs a light backdrop on a dark header
     *  - null    (mid-tone / unknown / SVG) → contrasts on both, no backdrop
     *
     * Computed from the average WCAG relative luminance of the opaque pixels.
     */
    public static function tone(?string $url): ?string
    {
        $file = self::localFile($url);
        if ($file === null) {
            return null;
        }

        // Only raster formats can be sampled; SVG/unknown → no backdrop.
        $ext = strtolower(pathinfo($file, PATHINFO_EXTENSION));
        if (!in_array($ext, ['png', 'jpg', 'jpeg', 'gif', 'webp'], true)) {
            return null;
        }
        if (!function_exists('imagecreatefromstring')) {
            return null;
        }

        $data = @file_get_contents($file);
        if ($data === false || $data === '') {
            return null;
        }
        $img = @imagecreatefromstring($data);
        if (!$img) {
            return null;
        }
        if (function_exists('imagepalettetotruecolor')) {
            @imagepalettetotruecolor($img); // so imagecolorat returns RGBA, not a palette index
        }

        $w = imagesx($img);
        $h = imagesy($img);
        if ($w < 1 || $h < 1) {
            imagedestroy($img);
            return null;
        }

        $steps = 20;
        $sum = 0.0;
        $count = 0;
        for ($i = 0; $i < $steps; $i++) {
            for ($j = 0; $j < $steps; $j++) {
                $x = min($w - 1, (int) (($i + 0.5) / $steps * $w));
                $y = min($h - 1, (int) (($j + 0.5) / $steps * $h));
                $c = imagecolorat($img, $x, $y);
                if ((($c >> 24) & 0x7F) > 90) {
                    continue; // skip mostly-transparent pixels
                }
                $sum += self::relativeLuminance(($c >> 16) & 0xFF, ($c >> 8) & 0xFF, $c & 0xFF);
                $count++;
            }
        }
        imagedestroy($img);

        if ($count === 0) {
            return null;
        }
        $avg = $sum / $count;
        if ($avg >= 0.6) {
            return 'light';
        }
        if ($avg <= 0.25) {
            return 'dark';
        }
        return null;
    }

    private static function relativeLuminance(int $r, int $g, int $b): float
    {
        $lin = static function (int $c): float {
            $s = $c / 255;
            return $s <= 0.03928 ? $s / 12.92 : (($s + 0.055) / 1.055) ** 2.4;
        };
        return 0.2126 * $lin($r) + 0.7152 * $lin($g) + 0.0722 * $lin($b);
    }

    /**
     * Resolve a local /uploads URL to an on-disk file path, or null.
     */
    private static function localFile(?string $url): ?string
    {
        if (!is_string($url) || $url === '') {
            return null;
        }
        $path = parse_url($url, PHP_URL_PATH) ?: $url;
        if (!is_string($path) || !str_starts_with($path, '/uploads/')) {
            return null;
        }
        $file = base_path('httpdocs' . $path);
        return is_file($file) ? $file : null;
    }

    /**
     * Width / height ratio of a local /uploads logo, or null if unmeasurable.
     */
    private static function ratio(?string $url): ?float
    {
        $file = self::localFile($url);
        if ($file === null) {
            return null;
        }

        // SVGs have no raster dimensions — derive the ratio from width/height
        // attributes or the viewBox instead.
        if (strtolower(pathinfo($file, PATHINFO_EXTENSION)) === 'svg') {
            return self::svgRatio($file);
        }

        $info = @getimagesize($file);
        if ($info === false || empty($info[0]) || empty($info[1])) {
            return null;
        }

        return $info[0] / $info[1];
    }

    private static function svgRatio(string $file): ?float
    {
        $svg = @file_get_contents($file, false, null, 0, 4096);
        if ($svg === false || $svg === '') {
            return null;
        }

        // Prefer explicit width/height (only when both are plain numbers/px).
        if (
            preg_match('/\bwidth\s*=\s*"([\d.]+)(px)?"/i', $svg, $w)
            && preg_match('/\bheight\s*=\s*"([\d.]+)(px)?"/i', $svg, $h)
            && (float) $h[1] > 0.0
        ) {
            return (float) $w[1] / (float) $h[1];
        }

        // Fall back to the viewBox (min-x min-y width height).
        if (preg_match('/viewBox\s*=\s*"\s*[\d.+-]+\s+[\d.+-]+\s+([\d.]+)\s+([\d.]+)\s*"/i', $svg, $vb)
            && (float) $vb[2] > 0.0
        ) {
            return (float) $vb[1] / (float) $vb[2];
        }

        return null;
    }
}

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
     * Width / height ratio of a local /uploads logo, or null if unmeasurable.
     */
    private static function ratio(?string $url): ?float
    {
        if (!is_string($url) || $url === '') {
            return null;
        }

        $path = parse_url($url, PHP_URL_PATH) ?: $url;
        if (!is_string($path) || !str_starts_with($path, '/uploads/')) {
            return null;
        }

        $file = base_path('httpdocs' . $path);
        if (!is_file($file)) {
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

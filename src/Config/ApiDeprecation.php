<?php
// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

namespace Nexus\Config;

/**
 * API Deprecation Configuration
 *
 * Maps v1 endpoints to their v2 replacements for deprecation signaling.
 * When a v1 endpoint is accessed, the API will add:
 * - X-API-Deprecated: true header
 * - Sunset: <date> header
 * - _deprecated object in response body (if applicable)
 *
 * Sunset date: August 1, 2026 (6 months from migration start)
 */
class ApiDeprecation
{
    /**
     * Sunset date for deprecated v1 endpoints
     * RFC 7231 format: Day, DD Mon YYYY HH:MM:SS GMT
     */
    public const SUNSET_DATE = 'Sat, 01 Aug 2026 00:00:00 GMT';

    /**
     * ISO date format for response body
     */
    public const SUNSET_DATE_ISO = '2026-08-01';

    /**
     * Mapping of v1 endpoints to their v2 replacements
     * Format: 'METHOD /v1/path' => 'v2/replacement/path'
     *
     * Use '*' as method to match any HTTP method
     * Use null as replacement to indicate no direct replacement
     */
    public const DEPRECATED_ENDPOINTS = [
        // Auth
        'POST /api/auth/register' => '/api/v2/auth/register',
        'POST /api/auth/restore-session' => null, // Use Bearer tokens directly

        // Listings
        'GET /api/listings' => '/api/v2/listings',
        'POST /api/listings/delete' => 'DELETE /api/v2/listings/{id}',

        // Wallet
        'GET /api/wallet/balance' => '/api/v2/wallet/balance',
        'GET /api/wallet/transactions' => '/api/v2/wallet/transactions',
        'POST /api/wallet/transfer' => '/api/v2/wallet/transfer',
        'POST /api/wallet/user-search' => '/api/v2/wallet/user-search',

        // Social/Feed
        'POST /api/social/feed' => '/api/v2/feed',
        'POST /api/social/like' => '/api/v2/feed/like',
        'POST /api/social/create-post' => '/api/v2/feed/posts',
    ];

    /**
     * Check if an endpoint is deprecated
     *
     * @param string $method HTTP method
     * @param string $path Request path
     * @return array|null Deprecation info or null if not deprecated
     */
    public static function getDeprecationInfo(string $method, string $path): ?array
    {
        // Normalize method to uppercase
        $method = strtoupper($method);

        // Check exact match first
        $key = $method . ' ' . $path;
        if (isset(self::DEPRECATED_ENDPOINTS[$key])) {
            return self::buildDeprecationInfo($key, self::DEPRECATED_ENDPOINTS[$key]);
        }

        // Check wildcard method
        $wildcardKey = '* ' . $path;
        if (isset(self::DEPRECATED_ENDPOINTS[$wildcardKey])) {
            return self::buildDeprecationInfo($wildcardKey, self::DEPRECATED_ENDPOINTS[$wildcardKey]);
        }

        return null;
    }

    /**
     * Build deprecation info array
     *
     * @param string $key The deprecated endpoint key
     * @param string|null $replacement The v2 replacement (or null)
     * @return array Deprecation info
     */
    private static function buildDeprecationInfo(string $key, ?string $replacement): array
    {
        $info = [
            'deprecated' => true,
            'sunset' => self::SUNSET_DATE,
            'sunset_iso' => self::SUNSET_DATE_ISO,
            'message' => 'This endpoint is deprecated.',
        ];

        if ($replacement !== null) {
            $info['replacement'] = $replacement;
            $info['message'] = 'This endpoint is deprecated. Use ' . $replacement . ' instead.';
        } else {
            $info['message'] = 'This endpoint is deprecated and will be removed.';
        }

        return $info;
    }

    /**
     * Get deprecation headers to add to response
     *
     * @param string $method HTTP method
     * @param string $path Request path
     * @return array Headers to add (empty if not deprecated)
     */
    public static function getDeprecationHeaders(string $method, string $path): array
    {
        $info = self::getDeprecationInfo($method, $path);

        if ($info === null) {
            return [];
        }

        return [
            'X-API-Deprecated' => 'true',
            'Sunset' => self::SUNSET_DATE,
        ];
    }

    /**
     * Get deprecation notice for response body
     *
     * @param string $method HTTP method
     * @param string $path Request path
     * @return array|null _deprecated object for response body
     */
    public static function getDeprecationNotice(string $method, string $path): ?array
    {
        $info = self::getDeprecationInfo($method, $path);

        if ($info === null) {
            return null;
        }

        $notice = [
            'message' => $info['message'],
            'sunset' => $info['sunset_iso'],
        ];

        if (isset($info['replacement'])) {
            $notice['replacement'] = $info['replacement'];
        }

        return $notice;
    }

    /**
     * Check if the current request is to a deprecated endpoint
     *
     * @return bool
     */
    public static function isCurrentRequestDeprecated(): bool
    {
        $method = $_SERVER['REQUEST_METHOD'] ?? 'GET';
        $path = parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH);

        return self::getDeprecationInfo($method, $path) !== null;
    }

    /**
     * Apply deprecation headers to current response
     * Call this from controllers or middleware
     */
    public static function applyDeprecationHeaders(): void
    {
        $method = $_SERVER['REQUEST_METHOD'] ?? 'GET';
        $path = parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH);

        $headers = self::getDeprecationHeaders($method, $path);

        foreach ($headers as $name => $value) {
            header($name . ': ' . $value);
        }
    }
}

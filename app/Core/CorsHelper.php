<?php
// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

namespace App\Core;

use Nexus\Helpers\CorsHelper as LegacyCorsHelper;

/**
 * App-namespace wrapper for Nexus\Helpers\CorsHelper.
 *
 * Delegates to the legacy implementation. Once the Laravel migration is
 * complete this can be replaced with Laravel's CORS middleware.
 */
class CorsHelper
{
    /**
     * Set CORS headers for the current request.
     *
     * @param array $additionalOrigins Additional allowed origins for this request
     * @param array $methods Allowed HTTP methods
     * @param array $headers Allowed request headers
     * @return bool True if origin was allowed, false if blocked
     */
    public static function setHeaders(
        array $additionalOrigins = [],
        array $methods = ['GET', 'POST', 'OPTIONS'],
        array $headers = ['Content-Type', 'Authorization']
    ): bool {
        return LegacyCorsHelper::setHeaders($additionalOrigins, $methods, $headers);
    }

    /**
     * Handle preflight OPTIONS request.
     */
    public static function handlePreflight(
        array $additionalOrigins = [],
        array $methods = ['GET', 'POST', 'OPTIONS'],
        array $headers = ['Content-Type', 'Authorization'],
        int $maxAge = 86400
    ): void {
        LegacyCorsHelper::handlePreflight($additionalOrigins, $methods, $headers, $maxAge);
    }

    /**
     * Check if an origin is in the allowed list.
     */
    public static function isOriginAllowed(string $origin, array $allowedOrigins = []): bool
    {
        return LegacyCorsHelper::isOriginAllowed($origin, $allowedOrigins);
    }

    /**
     * Add an origin to the allowed list dynamically.
     */
    public static function addAllowedOrigin(string $origin): void
    {
        LegacyCorsHelper::addAllowedOrigin($origin);
    }

    /**
     * Get the list of allowed origins.
     */
    public static function getAllowedOrigins(): array
    {
        return LegacyCorsHelper::getAllowedOrigins();
    }
}

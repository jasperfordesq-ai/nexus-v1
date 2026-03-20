<?php
// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

namespace App\Helpers;

use Nexus\Helpers\CorsHelper as LegacyCorsHelper;

/**
 * App-namespace wrapper for Nexus\Helpers\CorsHelper.
 *
 * Delegates to the legacy implementation. Once the Laravel migration is
 * complete this can be replaced with Laravel's CORS middleware.
 */
class CorsHelper
{
    public static function setHeaders(
        array $additionalOrigins = [],
        array $methods = ['GET', 'POST', 'OPTIONS'],
        array $headers = ['Content-Type', 'Authorization']
    ): bool {
        if (!class_exists(LegacyCorsHelper::class)) { return true; }
        return LegacyCorsHelper::setHeaders($additionalOrigins, $methods, $headers);
    }

    public static function handlePreflight(
        array $additionalOrigins = [],
        array $methods = ['GET', 'POST', 'OPTIONS'],
        array $headers = ['Content-Type', 'Authorization'],
        int $maxAge = 86400
    ): void {
        if (!class_exists(LegacyCorsHelper::class)) { return; }
        LegacyCorsHelper::handlePreflight($additionalOrigins, $methods, $headers, $maxAge);
    }

    public static function isOriginAllowed(string $origin, array $allowedOrigins = []): bool
    {
        if (!class_exists(LegacyCorsHelper::class)) { return false; }
        return LegacyCorsHelper::isOriginAllowed($origin, $allowedOrigins);
    }

    public static function addAllowedOrigin(string $origin): void
    {
        if (!class_exists(LegacyCorsHelper::class)) { return; }
        LegacyCorsHelper::addAllowedOrigin($origin);
    }

    public static function getAllowedOrigins(): array
    {
        if (!class_exists(LegacyCorsHelper::class)) { return []; }
        return LegacyCorsHelper::getAllowedOrigins();
    }
}

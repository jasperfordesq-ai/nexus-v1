<?php
// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

namespace Nexus\Helpers;

use App\Helpers\CorsHelper as AppCorsHelper;

/**
 * Legacy delegate — real implementation is now in App\Helpers\CorsHelper.
 *
 * @deprecated Use App\Helpers\CorsHelper directly.
 */
class CorsHelper
{
    public static function setHeaders(
        array $additionalOrigins = [],
        array $methods = ['GET', 'POST', 'OPTIONS'],
        array $headers = ['Content-Type', 'Authorization']
    ): bool {
        return AppCorsHelper::setHeaders($additionalOrigins, $methods, $headers);
    }

    public static function handlePreflight(
        array $additionalOrigins = [],
        array $methods = ['GET', 'POST', 'OPTIONS'],
        array $headers = ['Content-Type', 'Authorization'],
        int $maxAge = 86400
    ): void {
        AppCorsHelper::handlePreflight($additionalOrigins, $methods, $headers, $maxAge);
    }

    public static function isOriginAllowed(string $origin, array $allowedOrigins = []): bool
    {
        return AppCorsHelper::isOriginAllowed($origin, $allowedOrigins);
    }

    public static function addAllowedOrigin(string $origin): void
    {
        AppCorsHelper::addAllowedOrigin($origin);
    }

    public static function getAllowedOrigins(): array
    {
        return AppCorsHelper::getAllowedOrigins();
    }
}

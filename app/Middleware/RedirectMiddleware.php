<?php
// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

namespace App\Middleware;

use Nexus\Middleware\RedirectMiddleware as LegacyRedirectMiddleware;

/**
 * App-namespace wrapper for Nexus\Middleware\RedirectMiddleware.
 *
 * Delegates to the legacy implementation.
 */
class RedirectMiddleware
{
    public static function handle(): void
    {
        if (!class_exists(LegacyRedirectMiddleware::class)) { return; }
        LegacyRedirectMiddleware::handle();
    }

    public static function safeRedirect(string $url, int $statusCode = 302): void
    {
        if (!class_exists(LegacyRedirectMiddleware::class)) { return; }
        LegacyRedirectMiddleware::safeRedirect($url, $statusCode);
    }
}

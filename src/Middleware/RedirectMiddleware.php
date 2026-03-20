<?php
// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

namespace Nexus\Middleware;

use App\Middleware\RedirectMiddleware as AppRedirectMiddleware;

/**
 * Legacy delegate — real implementation is now in App\Middleware\RedirectMiddleware.
 *
 * @deprecated Use App\Middleware\RedirectMiddleware directly.
 */
class RedirectMiddleware
{
    public static function handle(): void
    {
        AppRedirectMiddleware::handle();
    }

    public static function safeRedirect(string $url, int $statusCode = 302): void
    {
        AppRedirectMiddleware::safeRedirect($url, $statusCode);
    }
}

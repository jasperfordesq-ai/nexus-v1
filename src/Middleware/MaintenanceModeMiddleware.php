<?php
// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

namespace Nexus\Middleware;

use App\Middleware\MaintenanceModeMiddleware as AppMaintenanceModeMiddleware;

/**
 * Legacy delegate — real implementation is now in App\Middleware\MaintenanceModeMiddleware.
 *
 * @deprecated Use App\Middleware\MaintenanceModeMiddleware directly.
 */
class MaintenanceModeMiddleware
{
    public static function check(): void
    {
        AppMaintenanceModeMiddleware::check();
    }
}

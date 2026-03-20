<?php
// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

namespace App\Middleware;

use Nexus\Middleware\MaintenanceModeMiddleware as LegacyMaintenanceModeMiddleware;

/**
 * App-namespace wrapper for Nexus\Middleware\MaintenanceModeMiddleware.
 *
 * Delegates to the legacy implementation.
 */
class MaintenanceModeMiddleware
{
    public static function check(): void
    {
        if (!class_exists(LegacyMaintenanceModeMiddleware::class)) { return; }
        LegacyMaintenanceModeMiddleware::check();
    }
}

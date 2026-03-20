<?php
// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

namespace Nexus\Middleware;

use App\Middleware\TenantModuleMiddleware as AppTenantModuleMiddleware;

/**
 * Legacy delegate — real implementation is now in App\Middleware\TenantModuleMiddleware.
 *
 * @deprecated Use App\Middleware\TenantModuleMiddleware directly.
 */
class TenantModuleMiddleware
{
    public static function isEnabled(string $module): bool
    {
        return AppTenantModuleMiddleware::isEnabled($module);
    }

    public static function check(string $module, ?string $customMessage = null)
    {
        return AppTenantModuleMiddleware::check($module, $customMessage);
    }

    public static function require(string $module, ?string $customMessage = null): void
    {
        AppTenantModuleMiddleware::require($module, $customMessage);
    }

    public static function getAllModuleStates(): array
    {
        return AppTenantModuleMiddleware::getAllModuleStates();
    }

    public static function getModuleDefinition(string $module): ?array
    {
        return AppTenantModuleMiddleware::getModuleDefinition($module);
    }

    public static function getAllModuleDefinitions(): array
    {
        return AppTenantModuleMiddleware::getAllModuleDefinitions();
    }

    public static function can(string $module): bool
    {
        return AppTenantModuleMiddleware::can($module);
    }
}

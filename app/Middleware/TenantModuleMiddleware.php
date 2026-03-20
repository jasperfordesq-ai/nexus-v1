<?php
// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

namespace App\Middleware;

use Nexus\Middleware\TenantModuleMiddleware as LegacyTenantModuleMiddleware;

/**
 * App-namespace wrapper for Nexus\Middleware\TenantModuleMiddleware.
 *
 * Delegates to the legacy implementation.
 */
class TenantModuleMiddleware
{
    public static function isEnabled(string $module): bool
    {
        if (!class_exists(LegacyTenantModuleMiddleware::class)) { return true; }
        return LegacyTenantModuleMiddleware::isEnabled($module);
    }

    /**
     * @return bool|array
     */
    public static function check(string $module, ?string $customMessage = null)
    {
        if (!class_exists(LegacyTenantModuleMiddleware::class)) { return true; }
        return LegacyTenantModuleMiddleware::check($module, $customMessage);
    }

    public static function require(string $module, ?string $customMessage = null): void
    {
        if (!class_exists(LegacyTenantModuleMiddleware::class)) { return; }
        LegacyTenantModuleMiddleware::require($module, $customMessage);
    }

    public static function getAllModuleStates(): array
    {
        if (!class_exists(LegacyTenantModuleMiddleware::class)) { return []; }
        return LegacyTenantModuleMiddleware::getAllModuleStates();
    }

    public static function getModuleDefinition(string $module): ?array
    {
        if (!class_exists(LegacyTenantModuleMiddleware::class)) { return null; }
        return LegacyTenantModuleMiddleware::getModuleDefinition($module);
    }

    public static function getAllModuleDefinitions(): array
    {
        if (!class_exists(LegacyTenantModuleMiddleware::class)) { return []; }
        return LegacyTenantModuleMiddleware::getAllModuleDefinitions();
    }

    public static function can(string $module): bool
    {
        if (!class_exists(LegacyTenantModuleMiddleware::class)) { return true; }
        return LegacyTenantModuleMiddleware::can($module);
    }
}

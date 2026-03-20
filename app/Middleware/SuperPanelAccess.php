<?php
// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

namespace App\Middleware;

use Nexus\Middleware\SuperPanelAccess as LegacySuperPanelAccess;

/**
 * App-namespace wrapper for Nexus\Middleware\SuperPanelAccess.
 *
 * Delegates to the legacy implementation. Note: App\Core\SuperPanelAccess
 * also exists as a direct Laravel implementation — this wrapper is for
 * code that specifically references the Middleware namespace.
 */
class SuperPanelAccess
{
    public static function handle(): void
    {
        if (!class_exists(LegacySuperPanelAccess::class)) { return; }
        LegacySuperPanelAccess::handle();
    }

    public static function check(): bool
    {
        if (!class_exists(LegacySuperPanelAccess::class)) { return false; }
        return LegacySuperPanelAccess::check();
    }

    public static function getAccess(?int $userId = null): array
    {
        if (!class_exists(LegacySuperPanelAccess::class)) {
            return ['granted' => false, 'level' => 'none', 'reason' => 'Legacy class unavailable'];
        }
        return LegacySuperPanelAccess::getAccess($userId);
    }

    public static function canAccessTenant(int $targetTenantId): bool
    {
        if (!class_exists(LegacySuperPanelAccess::class)) { return false; }
        return LegacySuperPanelAccess::canAccessTenant($targetTenantId);
    }

    public static function canManageTenant(int $targetTenantId): bool
    {
        if (!class_exists(LegacySuperPanelAccess::class)) { return false; }
        return LegacySuperPanelAccess::canManageTenant($targetTenantId);
    }

    public static function canCreateSubtenantUnder(int $parentTenantId): array
    {
        if (!class_exists(LegacySuperPanelAccess::class)) {
            return ['allowed' => false, 'reason' => 'Legacy class unavailable'];
        }
        return LegacySuperPanelAccess::canCreateSubtenantUnder($parentTenantId);
    }

    public static function getScopeClause(string $tableAlias = 't'): array
    {
        if (!class_exists(LegacySuperPanelAccess::class)) {
            return ['sql' => '1 = 0', 'params' => []];
        }
        return LegacySuperPanelAccess::getScopeClause($tableAlias);
    }

    public static function reset(): void
    {
        if (!class_exists(LegacySuperPanelAccess::class)) { return; }
        LegacySuperPanelAccess::reset();
    }
}

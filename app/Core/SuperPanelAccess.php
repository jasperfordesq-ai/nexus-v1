<?php
// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

namespace App\Core;

use Nexus\Middleware\SuperPanelAccess as LegacySuperPanelAccess;

/**
 * Thin wrapper that delegates all static calls to \Nexus\Middleware\SuperPanelAccess.
 */
class SuperPanelAccess
{
    public static function handle(): void
    {
        LegacySuperPanelAccess::handle();
    }

    public static function check(): bool
    {
        return LegacySuperPanelAccess::check();
    }

    public static function getAccess(?int $userId = null): array
    {
        return LegacySuperPanelAccess::getAccess($userId);
    }

    public static function canAccessTenant(int $targetTenantId): bool
    {
        return LegacySuperPanelAccess::canAccessTenant($targetTenantId);
    }

    public static function canManageTenant(int $targetTenantId): bool
    {
        return LegacySuperPanelAccess::canManageTenant($targetTenantId);
    }

    public static function canCreateSubtenantUnder(int $parentTenantId): array
    {
        return LegacySuperPanelAccess::canCreateSubtenantUnder($parentTenantId);
    }

    public static function getScopeClause(string $tableAlias = 't'): array
    {
        return LegacySuperPanelAccess::getScopeClause($tableAlias);
    }

    public static function reset(): void
    {
        LegacySuperPanelAccess::reset();
    }
}

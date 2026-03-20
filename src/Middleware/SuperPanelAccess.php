<?php
// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

namespace Nexus\Middleware;

use App\Middleware\SuperPanelAccess as AppSuperPanelAccess;

/**
 * Legacy delegate — real implementation is now in App\Middleware\SuperPanelAccess.
 *
 * @deprecated Use App\Middleware\SuperPanelAccess directly.
 */
class SuperPanelAccess
{
    public static function handle(): void
    {
        AppSuperPanelAccess::handle();
    }

    public static function check(): bool
    {
        return AppSuperPanelAccess::check();
    }

    public static function getAccess(?int $userId = null): array
    {
        return AppSuperPanelAccess::getAccess($userId);
    }

    public static function canAccessTenant(int $targetTenantId): bool
    {
        return AppSuperPanelAccess::canAccessTenant($targetTenantId);
    }

    public static function canManageTenant(int $targetTenantId): bool
    {
        return AppSuperPanelAccess::canManageTenant($targetTenantId);
    }

    public static function canCreateSubtenantUnder(int $parentTenantId): array
    {
        return AppSuperPanelAccess::canCreateSubtenantUnder($parentTenantId);
    }

    public static function getScopeClause(string $tableAlias = 't'): array
    {
        return AppSuperPanelAccess::getScopeClause($tableAlias);
    }

    public static function reset(): void
    {
        AppSuperPanelAccess::reset();
    }
}

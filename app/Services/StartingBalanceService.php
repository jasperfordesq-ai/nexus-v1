<?php
// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

namespace App\Services;

/**
 * StartingBalanceService — Laravel DI wrapper for legacy \Nexus\Services\StartingBalanceService.
 *
 * Provides dependency-injectable access to the legacy static service methods.
 */
class StartingBalanceService
{
    public function __construct()
    {
    }

    /**
     * Delegates to legacy StartingBalanceService::getDefault().
     */
    public function getDefault(int $tenantId): float
    {
        return \Nexus\Services\StartingBalanceService::getDefault($tenantId);
    }

    /**
     * Delegates to legacy StartingBalanceService::setDefault().
     */
    public function setDefault(int $tenantId, float $amount): bool
    {
        return \Nexus\Services\StartingBalanceService::setDefault($tenantId, $amount);
    }

    /**
     * Delegates to legacy StartingBalanceService::applyToUser().
     */
    public function applyToUser(int $tenantId, int $userId): bool
    {
        return \Nexus\Services\StartingBalanceService::applyToUser($tenantId, $userId);
    }
}

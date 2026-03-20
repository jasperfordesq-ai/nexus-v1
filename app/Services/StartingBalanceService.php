<?php
// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

namespace App\Services;

/**
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
        \Illuminate\Support\Facades\Log::warning('Legacy delegation removed: ' . __METHOD__);
        return 0.0;
    }

    /**
     * Delegates to legacy StartingBalanceService::setDefault().
     */
    public function setDefault(int $tenantId, float $amount): bool
    {
        \Illuminate\Support\Facades\Log::warning('Legacy delegation removed: ' . __METHOD__);
        return false;
    }

    /**
     * Delegates to legacy StartingBalanceService::applyToUser().
     */
    public function applyToUser(int $tenantId, int $userId): bool
    {
        \Illuminate\Support\Facades\Log::warning('Legacy delegation removed: ' . __METHOD__);
        return false;
    }
}

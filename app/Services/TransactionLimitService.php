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
class TransactionLimitService
{
    public function __construct()
    {
    }

    /**
     * Delegates to legacy TransactionLimitService::getLimit().
     */
    public function getLimit(int $tenantId, int $userId): ?float
    {
        \Illuminate\Support\Facades\Log::warning('Legacy delegation removed: ' . __METHOD__);
        return null;
    }

    /**
     * Delegates to legacy TransactionLimitService::setLimit().
     */
    public function setLimit(int $tenantId, int $userId, float $limit): bool
    {
        \Illuminate\Support\Facades\Log::warning('Legacy delegation removed: ' . __METHOD__);
        return false;
    }

    /**
     * Delegates to legacy TransactionLimitService::checkLimit().
     */
    public function checkLimit(int $tenantId, int $userId, float $amount): bool
    {
        \Illuminate\Support\Facades\Log::warning('Legacy delegation removed: ' . __METHOD__);
        return false;
    }
}

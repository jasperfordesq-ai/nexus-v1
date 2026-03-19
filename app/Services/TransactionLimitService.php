<?php
// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

namespace App\Services;

/**
 * TransactionLimitService — Laravel DI wrapper for legacy \Nexus\Services\TransactionLimitService.
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
        return \Nexus\Services\TransactionLimitService::getLimit($tenantId, $userId);
    }

    /**
     * Delegates to legacy TransactionLimitService::setLimit().
     */
    public function setLimit(int $tenantId, int $userId, float $limit): bool
    {
        return \Nexus\Services\TransactionLimitService::setLimit($tenantId, $userId, $limit);
    }

    /**
     * Delegates to legacy TransactionLimitService::checkLimit().
     */
    public function checkLimit(int $tenantId, int $userId, float $amount): bool
    {
        return \Nexus\Services\TransactionLimitService::checkLimit($tenantId, $userId, $amount);
    }
}

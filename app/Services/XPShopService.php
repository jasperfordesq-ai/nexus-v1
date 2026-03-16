<?php
// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

namespace App\Services;

/**
 * XPShopService — Laravel DI wrapper for legacy \Nexus\Services\XPShopService.
 *
 * Provides dependency-injectable access to the legacy static service methods.
 */
class XPShopService
{
    public function __construct()
    {
    }

    /**
     * Delegates to legacy XPShopService::getItems().
     */
    public function getItems(int $tenantId): array
    {
        return \Nexus\Services\XPShopService::getItems($tenantId);
    }

    /**
     * Delegates to legacy XPShopService::purchase().
     */
    public function purchase(int $tenantId, int $userId, int $itemId): bool
    {
        return \Nexus\Services\XPShopService::purchase($tenantId, $userId, $itemId);
    }

    /**
     * Delegates to legacy XPShopService::getUserPurchases().
     */
    public function getUserPurchases(int $tenantId, int $userId): array
    {
        return \Nexus\Services\XPShopService::getUserPurchases($tenantId, $userId);
    }

    /**
     * Delegates to legacy XPShopService::getBalance().
     */
    public function getBalance(int $tenantId, int $userId): int
    {
        return \Nexus\Services\XPShopService::getBalance($tenantId, $userId);
    }
}

<?php
// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

namespace App\Services;

/**
 * CookieInventoryService — Laravel DI wrapper for legacy \Nexus\Services\CookieInventoryService.
 *
 * Provides dependency-injectable access to the legacy static service methods.
 */
class CookieInventoryService
{
    public function __construct()
    {
    }

    /**
     * Delegates to legacy CookieInventoryService::getCookiesByCategory().
     */
    public function getCookiesByCategory(string $category, ?int $tenantId = null): array
    {
        return \Nexus\Services\CookieInventoryService::getCookiesByCategory($category, $tenantId);
    }

    /**
     * Delegates to legacy CookieInventoryService::getAllCookies().
     */
    public function getAllCookies(?int $tenantId = null): array
    {
        return \Nexus\Services\CookieInventoryService::getAllCookies($tenantId);
    }

    /**
     * Delegates to legacy CookieInventoryService::getBannerCookieList().
     */
    public function getBannerCookieList(?int $tenantId = null): array
    {
        return \Nexus\Services\CookieInventoryService::getBannerCookieList($tenantId);
    }

    /**
     * Delegates to legacy CookieInventoryService::addCookie().
     */
    public function addCookie(array $data): int
    {
        return \Nexus\Services\CookieInventoryService::addCookie($data);
    }

    /**
     * Delegates to legacy CookieInventoryService::updateCookie().
     */
    public function updateCookie(int $id, array $data): bool
    {
        return \Nexus\Services\CookieInventoryService::updateCookie($id, $data);
    }
}

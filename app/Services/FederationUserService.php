<?php
// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

namespace App\Services;

/**
 * FederationUserService — Laravel DI wrapper for legacy \Nexus\Services\FederationUserService.
 *
 * Provides dependency-injectable access to the legacy static service methods.
 */
class FederationUserService
{
    public function __construct()
    {
    }

    /**
     * Delegates to legacy FederationUserService::getUserSettings().
     */
    public function getUserSettings(int $userId): array
    {
        return \Nexus\Services\FederationUserService::getUserSettings($userId);
    }

    /**
     * Delegates to legacy FederationUserService::updateSettings().
     */
    public function updateSettings(int $userId, array $settings): bool
    {
        return \Nexus\Services\FederationUserService::updateSettings($userId, $settings);
    }

    /**
     * Delegates to legacy FederationUserService::hasOptedIn().
     */
    public function hasOptedIn(int $userId, ?int $tenantId = null): bool
    {
        return \Nexus\Services\FederationUserService::hasOptedIn($userId, $tenantId);
    }

    /**
     * Delegates to legacy FederationUserService::optOut().
     */
    public function optOut(int $userId): bool
    {
        return \Nexus\Services\FederationUserService::optOut($userId);
    }

    /**
     * Delegates to legacy FederationUserService::getFederatedUsers().
     */
    public function getFederatedUsers(int $tenantId, array $filters = []): array
    {
        return \Nexus\Services\FederationUserService::getFederatedUsers($tenantId, $filters);
    }
}

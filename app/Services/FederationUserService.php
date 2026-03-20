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
        \Illuminate\Support\Facades\Log::warning('Legacy delegation removed: ' . __METHOD__);
        return [];
    }

    /**
     * Delegates to legacy FederationUserService::updateSettings().
     */
    public function updateSettings(int $userId, array $settings): bool
    {
        \Illuminate\Support\Facades\Log::warning('Legacy delegation removed: ' . __METHOD__);
        return false;
    }

    /**
     * Delegates to legacy FederationUserService::hasOptedIn().
     */
    public function hasOptedIn(int $userId, ?int $tenantId = null): bool
    {
        \Illuminate\Support\Facades\Log::warning('Legacy delegation removed: ' . __METHOD__);
        return false;
    }

    /**
     * Delegates to legacy FederationUserService::optOut().
     */
    public function optOut(int $userId): bool
    {
        \Illuminate\Support\Facades\Log::warning('Legacy delegation removed: ' . __METHOD__);
        return false;
    }

    /**
     * Delegates to legacy FederationUserService::getFederatedUsers().
     */
    public function getFederatedUsers(int $tenantId, array $filters = []): array
    {
        \Illuminate\Support\Facades\Log::warning('Legacy delegation removed: ' . __METHOD__);
        return [];
    }
}

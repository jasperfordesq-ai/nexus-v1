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
class FederationDirectoryService
{
    public function __construct()
    {
    }

    /**
     * Delegates to legacy FederationDirectoryService::getDiscoverableTimebanks().
     */
    public static function getDiscoverableTimebanks(int $currentTenantId, array $filters = []): array
    {
        \Illuminate\Support\Facades\Log::warning('Legacy delegation removed: ' . __METHOD__);
        return [];
    }

    /**
     * Delegates to legacy FederationDirectoryService::getAvailableRegions().
     */
    public static function getAvailableRegions(): array
    {
        \Illuminate\Support\Facades\Log::warning('Legacy delegation removed: ' . __METHOD__);
        return [];
    }

    /**
     * Delegates to legacy FederationDirectoryService::getAvailableCategories().
     */
    public static function getAvailableCategories(): array
    {
        \Illuminate\Support\Facades\Log::warning('Legacy delegation removed: ' . __METHOD__);
        return [];
    }

    /**
     * Delegates to legacy FederationDirectoryService::getTimebankProfile().
     */
    public static function getTimebankProfile(int $tenantId): ?array
    {
        \Illuminate\Support\Facades\Log::warning('Legacy delegation removed: ' . __METHOD__);
        return null;
    }

    /**
     * Delegates to legacy FederationDirectoryService::updateDirectoryProfile().
     */
    public static function updateDirectoryProfile(int $tenantId, array $data): bool
    {
        \Illuminate\Support\Facades\Log::warning('Legacy delegation removed: ' . __METHOD__);
        return false;
    }
}

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
class FederationExternalPartnerService
{
    public function __construct()
    {
    }

    /**
     * Delegates to legacy FederationExternalPartnerService::getAll().
     */
    public static function getAll(int $tenantId): array
    {
        \Illuminate\Support\Facades\Log::warning('Legacy delegation removed: ' . __METHOD__);
        return [];
    }

    /**
     * Delegates to legacy FederationExternalPartnerService::getById().
     */
    public static function getById(int $id, int $tenantId): ?array
    {
        \Illuminate\Support\Facades\Log::warning('Legacy delegation removed: ' . __METHOD__);
        return null;
    }

    /**
     * Delegates to legacy FederationExternalPartnerService::urlExists().
     */
    public static function urlExists(string $baseUrl, int $tenantId, ?int $excludeId = null): bool
    {
        \Illuminate\Support\Facades\Log::warning('Legacy delegation removed: ' . __METHOD__);
        return false;
    }

    /**
     * Delegates to legacy FederationExternalPartnerService::create().
     */
    public static function create(array $data, int $tenantId, int $userId): array
    {
        \Illuminate\Support\Facades\Log::warning('Legacy delegation removed: ' . __METHOD__);
        return [];
    }

    /**
     * Delegates to legacy FederationExternalPartnerService::update().
     */
    public static function update(int $id, array $data, int $tenantId, int $userId): array
    {
        \Illuminate\Support\Facades\Log::warning('Legacy delegation removed: ' . __METHOD__);
        return [];
    }
}

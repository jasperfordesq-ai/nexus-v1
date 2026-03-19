<?php
// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

namespace App\Services;

/**
 * FederationExternalPartnerService — Laravel DI wrapper for legacy \Nexus\Services\FederationExternalPartnerService.
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
    public function getAll(int $tenantId): array
    {
        return \Nexus\Services\FederationExternalPartnerService::getAll($tenantId);
    }

    /**
     * Delegates to legacy FederationExternalPartnerService::getById().
     */
    public function getById(int $id, int $tenantId): ?array
    {
        return \Nexus\Services\FederationExternalPartnerService::getById($id, $tenantId);
    }

    /**
     * Delegates to legacy FederationExternalPartnerService::urlExists().
     */
    public function urlExists(string $baseUrl, int $tenantId, ?int $excludeId = null): bool
    {
        return \Nexus\Services\FederationExternalPartnerService::urlExists($baseUrl, $tenantId, $excludeId);
    }

    /**
     * Delegates to legacy FederationExternalPartnerService::create().
     */
    public function create(array $data, int $tenantId, int $userId): array
    {
        return \Nexus\Services\FederationExternalPartnerService::create($data, $tenantId, $userId);
    }

    /**
     * Delegates to legacy FederationExternalPartnerService::update().
     */
    public function update(int $id, array $data, int $tenantId, int $userId): array
    {
        return \Nexus\Services\FederationExternalPartnerService::update($id, $data, $tenantId, $userId);
    }
}

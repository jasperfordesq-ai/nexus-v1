<?php
// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

namespace App\Services\Identity;

use Nexus\Services\Identity\TenantProviderCredentialService as LegacyService;

/**
 * TenantProviderCredentialService — Laravel DI wrapper for legacy service.
 *
 * Delegates to \Nexus\Services\Identity\TenantProviderCredentialService.
 */
class TenantProviderCredentialService
{
    public function __construct()
    {
    }

    /**
     * Get decrypted credentials for a tenant + provider.
     */
    public function get(int $tenantId, string $providerSlug): ?array
    {
        if (!class_exists(LegacyService::class)) {
            return null;
        }
        return LegacyService::get($tenantId, $providerSlug);
    }

    /**
     * Save (upsert) encrypted credentials for a tenant + provider.
     */
    public function save(int $tenantId, string $providerSlug, array $credentials): bool
    {
        if (!class_exists(LegacyService::class)) {
            return false;
        }
        return LegacyService::save($tenantId, $providerSlug, $credentials);
    }

    /**
     * Delete credentials for a tenant + provider.
     */
    public function delete(int $tenantId, string $providerSlug): bool
    {
        if (!class_exists(LegacyService::class)) {
            return false;
        }
        return LegacyService::delete($tenantId, $providerSlug);
    }

    /**
     * Check if a tenant has credentials stored for a provider.
     */
    public function hasCredentials(int $tenantId, string $providerSlug): bool
    {
        if (!class_exists(LegacyService::class)) {
            return false;
        }
        return LegacyService::hasCredentials($tenantId, $providerSlug);
    }

    /**
     * List which providers have credentials configured for a tenant.
     *
     * @return array<string, bool>
     */
    public function listConfigured(int $tenantId): array
    {
        if (!class_exists(LegacyService::class)) {
            return [];
        }
        return LegacyService::listConfigured($tenantId);
    }

    /**
     * Get the credential field names a provider requires.
     */
    public function getRequiredFields(string $providerSlug): array
    {
        if (!class_exists(LegacyService::class)) {
            return [];
        }
        return LegacyService::getRequiredFields($providerSlug);
    }
}

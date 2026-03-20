<?php
// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

namespace Nexus\Services\Identity;

/**
 * TenantProviderCredentialService — Thin delegate forwarding to \App\Services\Identity\TenantProviderCredentialService.
 *
 * The full implementation now lives in the App namespace.
 * This file exists for backwards compatibility only.
 *
 * @see \App\Services\Identity\TenantProviderCredentialService
 */
class TenantProviderCredentialService
{

    public static function get(int $tenantId, string $providerSlug): ?array
    {
        return \App\Services\Identity\TenantProviderCredentialService::get($tenantId, $providerSlug);
    }

    public static function save(int $tenantId, string $providerSlug, array $credentials): bool
    {
        return \App\Services\Identity\TenantProviderCredentialService::save($tenantId, $providerSlug, $credentials);
    }

    public static function delete(int $tenantId, string $providerSlug): bool
    {
        return \App\Services\Identity\TenantProviderCredentialService::delete($tenantId, $providerSlug);
    }

    public static function hasCredentials(int $tenantId, string $providerSlug): bool
    {
        return \App\Services\Identity\TenantProviderCredentialService::hasCredentials($tenantId, $providerSlug);
    }

    public static function listConfigured(int $tenantId): array
    {
        return \App\Services\Identity\TenantProviderCredentialService::listConfigured($tenantId);
    }

    public static function getRequiredFields(string $providerSlug): array
    {
        return \App\Services\Identity\TenantProviderCredentialService::getRequiredFields($providerSlug);
    }
}

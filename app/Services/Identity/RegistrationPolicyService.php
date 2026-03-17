<?php
// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

namespace App\Services\Identity;

use Nexus\Services\Identity\RegistrationPolicyService as LegacyService;

/**
 * RegistrationPolicyService — Laravel DI wrapper for legacy service.
 *
 * Delegates to \Nexus\Services\Identity\RegistrationPolicyService.
 */
class RegistrationPolicyService
{
    /** Valid registration modes — mirrored from legacy */
    public const MODES = LegacyService::MODES;

    /** Valid verification levels — mirrored from legacy */
    public const VERIFICATION_LEVELS = LegacyService::VERIFICATION_LEVELS;

    /** Valid post-verification actions — mirrored from legacy */
    public const POST_VERIFICATION_ACTIONS = LegacyService::POST_VERIFICATION_ACTIONS;

    /** Valid fallback modes — mirrored from legacy */
    public const FALLBACK_MODES = LegacyService::FALLBACK_MODES;

    public function __construct()
    {
    }

    /**
     * Get the registration policy for a tenant.
     */
    public function getPolicy(int $tenantId): ?array
    {
        return LegacyService::getPolicy($tenantId);
    }

    /**
     * Get the effective registration mode for a tenant.
     */
    public function getEffectivePolicy(int $tenantId): array
    {
        return LegacyService::getEffectivePolicy($tenantId);
    }

    /**
     * Create or update the registration policy for a tenant.
     *
     * @throws \InvalidArgumentException On invalid input
     */
    public function upsertPolicy(int $tenantId, array $data): array
    {
        return LegacyService::upsertPolicy($tenantId, $data);
    }

    /**
     * Encrypt provider config JSON for storage at rest.
     */
    public function encryptConfig(array $config): string
    {
        return LegacyService::encryptConfig($config);
    }

    /**
     * Decrypt provider config from storage.
     */
    public function decryptConfig(string $encrypted): array
    {
        return LegacyService::decryptConfig($encrypted);
    }
}

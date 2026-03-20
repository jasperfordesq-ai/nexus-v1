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
 * Methods are static to match the legacy API that tests and other callers expect.
 */
class RegistrationPolicyService
{
    /** Valid registration modes (inlined — safe when legacy is removed) */
    public const MODES = [
        'open',
        'open_with_approval',
        'verified_identity',
        'government_id',
        'invite_only',
        'waitlist',
    ];

    /** Valid verification levels (inlined — safe when legacy is removed) */
    public const VERIFICATION_LEVELS = [
        'none',
        'document_only',
        'document_selfie',
        'reusable_digital_id',
        'manual_review',
    ];

    /** Valid post-verification actions (inlined — safe when legacy is removed) */
    public const POST_VERIFICATION_ACTIONS = [
        'activate',
        'admin_approval',
        'limited_access',
        'reject_on_fail',
    ];

    /** Valid fallback modes (inlined — safe when legacy is removed) */
    public const FALLBACK_MODES = [
        'none',
        'admin_review',
        'native_registration',
    ];

    /**
     * Get the registration policy for a tenant.
     */
    public static function getPolicy(int $tenantId): ?array
    {
        if (!class_exists(LegacyService::class)) {
            return null;
        }
        return LegacyService::getPolicy($tenantId);
    }

    /**
     * Get the effective registration mode for a tenant.
     */
    public static function getEffectivePolicy(int $tenantId): array
    {
        if (!class_exists(LegacyService::class)) {
            return [];
        }
        return LegacyService::getEffectivePolicy($tenantId);
    }

    /**
     * Create or update the registration policy for a tenant.
     *
     * @throws \InvalidArgumentException On invalid input
     */
    public static function upsertPolicy(int $tenantId, array $data): array
    {
        if (!class_exists(LegacyService::class)) {
            return [];
        }
        return LegacyService::upsertPolicy($tenantId, $data);
    }

    /**
     * Encrypt provider config JSON for storage at rest.
     */
    public static function encryptConfig(array $config): string
    {
        if (!class_exists(LegacyService::class)) {
            return '';
        }
        return LegacyService::encryptConfig($config);
    }

    /**
     * Decrypt provider config from storage.
     */
    public static function decryptConfig(string $encrypted): array
    {
        if (!class_exists(LegacyService::class)) {
            return [];
        }
        return LegacyService::decryptConfig($encrypted);
    }
}

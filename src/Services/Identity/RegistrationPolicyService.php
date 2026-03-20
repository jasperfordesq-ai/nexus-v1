<?php
// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

namespace Nexus\Services\Identity;

/**
 * RegistrationPolicyService — Thin delegate forwarding to \App\Services\Identity\RegistrationPolicyService.
 *
 * The full implementation now lives in the App namespace.
 * This file exists for backwards compatibility only.
 *
 * @see \App\Services\Identity\RegistrationPolicyService
 */
class RegistrationPolicyService
{
    public const MODES = \App\Services\Identity\RegistrationPolicyService::MODES;
    public const VERIFICATION_LEVELS = \App\Services\Identity\RegistrationPolicyService::VERIFICATION_LEVELS;
    public const POST_VERIFICATION_ACTIONS = \App\Services\Identity\RegistrationPolicyService::POST_VERIFICATION_ACTIONS;
    public const FALLBACK_MODES = \App\Services\Identity\RegistrationPolicyService::FALLBACK_MODES;

    public static function getPolicy(int $tenantId): ?array
    {
        return \App\Services\Identity\RegistrationPolicyService::getPolicy($tenantId);
    }

    public static function getEffectivePolicy(int $tenantId): array
    {
        return \App\Services\Identity\RegistrationPolicyService::getEffectivePolicy($tenantId);
    }

    public static function upsertPolicy(int $tenantId, array $data): array
    {
        return \App\Services\Identity\RegistrationPolicyService::upsertPolicy($tenantId, $data);
    }

    public static function encryptConfig(array $config): string
    {
        return \App\Services\Identity\RegistrationPolicyService::encryptConfig($config);
    }

    public static function decryptConfig(string $encrypted): array
    {
        return \App\Services\Identity\RegistrationPolicyService::decryptConfig($encrypted);
    }
}

<?php
// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

namespace App\Services;

/**
 * TotpService — Laravel DI wrapper for legacy \Nexus\Services\TotpService.
 *
 * Provides dependency-injectable access to the legacy static service methods.
 */
class TotpService
{
    public function __construct()
    {
    }

    /**
     * Delegates to legacy TotpService::generateSecret().
     */
    public function generateSecret(): string
    {
        return \Nexus\Services\TotpService::generateSecret();
    }

    /**
     * Delegates to legacy TotpService::getProvisioningUri().
     */
    public function getProvisioningUri(string $secret, string $email, ?string $issuer = null): string
    {
        return \Nexus\Services\TotpService::getProvisioningUri($secret, $email, $issuer);
    }

    /**
     * Delegates to legacy TotpService::generateQrCode().
     */
    public function generateQrCode(string $provisioningUri): string
    {
        return \Nexus\Services\TotpService::generateQrCode($provisioningUri);
    }

    /**
     * Delegates to legacy TotpService::verifyCode().
     */
    public function verifyCode(string $secret, string $code, int $window = 1): bool
    {
        return \Nexus\Services\TotpService::verifyCode($secret, $code, $window);
    }

    /**
     * Delegates to legacy TotpService::checkRateLimit().
     */
    public function checkRateLimit(int $userId): array
    {
        return \Nexus\Services\TotpService::checkRateLimit($userId);
    }
}

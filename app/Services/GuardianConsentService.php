<?php
// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

namespace App\Services;

/**
 * GuardianConsentService — Laravel DI wrapper for legacy \Nexus\Services\GuardianConsentService.
 *
 * Provides dependency-injectable access to the legacy static service methods.
 */
class GuardianConsentService
{
    public function __construct()
    {
    }

    /**
     * Delegates to legacy GuardianConsentService::requestConsent().
     */
    public function requestConsent(int $minorUserId, array $guardianData, ?int $opportunityId = null): array
    {
        return \Nexus\Services\GuardianConsentService::requestConsent($minorUserId, $guardianData, $opportunityId);
    }

    /**
     * Delegates to legacy GuardianConsentService::grantConsent().
     */
    public function grantConsent(string $token, string $ip): bool
    {
        return \Nexus\Services\GuardianConsentService::grantConsent($token, $ip);
    }

    /**
     * Delegates to legacy GuardianConsentService::withdrawConsent().
     */
    public function withdrawConsent(int $consentId, int $userId): bool
    {
        return \Nexus\Services\GuardianConsentService::withdrawConsent($consentId, $userId);
    }

    /**
     * Delegates to legacy GuardianConsentService::checkConsent().
     */
    public function checkConsent(int $minorUserId, ?int $opportunityId = null): bool
    {
        return \Nexus\Services\GuardianConsentService::checkConsent($minorUserId, $opportunityId);
    }

    /**
     * Delegates to legacy GuardianConsentService::getConsentsForMinor().
     */
    public function getConsentsForMinor(int $minorUserId): array
    {
        return \Nexus\Services\GuardianConsentService::getConsentsForMinor($minorUserId);
    }

    /**
     * Delegates to legacy GuardianConsentService::getConsentsForAdmin().
     */
    public function getConsentsForAdmin(array $filters = []): array
    {
        return \Nexus\Services\GuardianConsentService::getConsentsForAdmin($filters);
    }
}

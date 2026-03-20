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
        \Illuminate\Support\Facades\Log::warning('Legacy delegation removed: ' . __METHOD__);
        return [];
    }

    /**
     * Delegates to legacy GuardianConsentService::grantConsent().
     */
    public function grantConsent(string $token, string $ip): bool
    {
        \Illuminate\Support\Facades\Log::warning('Legacy delegation removed: ' . __METHOD__);
        return false;
    }

    /**
     * Delegates to legacy GuardianConsentService::withdrawConsent().
     */
    public function withdrawConsent(int $consentId, int $userId): bool
    {
        \Illuminate\Support\Facades\Log::warning('Legacy delegation removed: ' . __METHOD__);
        return false;
    }

    /**
     * Delegates to legacy GuardianConsentService::checkConsent().
     */
    public function checkConsent(int $minorUserId, ?int $opportunityId = null): bool
    {
        \Illuminate\Support\Facades\Log::warning('Legacy delegation removed: ' . __METHOD__);
        return false;
    }

    /**
     * Delegates to legacy GuardianConsentService::getConsentsForMinor().
     */
    public function getConsentsForMinor(int $minorUserId): array
    {
        \Illuminate\Support\Facades\Log::warning('Legacy delegation removed: ' . __METHOD__);
        return [];
    }

    /**
     * Delegates to legacy GuardianConsentService::getConsentsForAdmin().
     */
    public function getConsentsForAdmin(array $filters = []): array
    {
        \Illuminate\Support\Facades\Log::warning('Legacy delegation removed: ' . __METHOD__);
        return [];
    }
}

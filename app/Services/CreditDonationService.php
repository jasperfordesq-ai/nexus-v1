<?php
// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

namespace App\Services;

/**
 * CreditDonationService — Laravel DI wrapper for legacy \Nexus\Services\CreditDonationService.
 *
 * Provides dependency-injectable access to the legacy static service methods.
 */
class CreditDonationService
{
    public function __construct()
    {
    }

    /**
     * Delegates to legacy CreditDonationService::donate().
     */
    public function donate(int $tenantId, int $fromUserId, int $toUserId, float $amount, ?string $message = null): bool
    {
        return \Nexus\Services\CreditDonationService::donate($tenantId, $fromUserId, $toUserId, $amount, $message);
    }

    /**
     * Delegates to legacy CreditDonationService::getDonations().
     */
    public function getDonations(int $tenantId, int $userId, string $direction = 'sent'): array
    {
        return \Nexus\Services\CreditDonationService::getDonations($tenantId, $userId, $direction);
    }

    /**
     * Delegates to legacy CreditDonationService::getTotalDonated().
     */
    public function getTotalDonated(int $tenantId, int $userId): float
    {
        return \Nexus\Services\CreditDonationService::getTotalDonated($tenantId, $userId);
    }
}

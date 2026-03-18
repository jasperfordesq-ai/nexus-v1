<?php
// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

namespace App\Services;

/**
 * CommunityFundService — Laravel DI wrapper for legacy \Nexus\Services\CommunityFundService.
 *
 * Provides dependency-injectable access to the legacy static service methods.
 */
class CommunityFundService
{
    public function __construct()
    {
    }

    /**
     * Delegates to legacy CommunityFundService::getOrCreateFund().
     */
    public function getOrCreateFund(): array
    {
        if (!class_exists('\Nexus\Services\CommunityFundService')) { return []; }
        return \Nexus\Services\CommunityFundService::getOrCreateFund();
    }

    /**
     * Delegates to legacy CommunityFundService::getBalance().
     */
    public function getBalance(): array
    {
        if (!class_exists('\Nexus\Services\CommunityFundService')) { return []; }
        return \Nexus\Services\CommunityFundService::getBalance();
    }

    /**
     * Delegates to legacy CommunityFundService::adminDeposit().
     */
    public function adminDeposit(int $adminId, float $amount, string $description = ''): array
    {
        if (!class_exists('\Nexus\Services\CommunityFundService')) { return []; }
        return \Nexus\Services\CommunityFundService::adminDeposit($adminId, $amount, $description);
    }

    /**
     * Delegates to legacy CommunityFundService::adminWithdraw().
     */
    public function adminWithdraw(int $adminId, int $recipientId, float $amount, string $description = ''): array
    {
        if (!class_exists('\Nexus\Services\CommunityFundService')) { return []; }
        return \Nexus\Services\CommunityFundService::adminWithdraw($adminId, $recipientId, $amount, $description);
    }

    /**
     * Delegates to legacy CommunityFundService::receiveDonation().
     */
    public function receiveDonation(int $donorId, float $amount, string $message = ''): array
    {
        if (!class_exists('\Nexus\Services\CommunityFundService')) { return []; }
        return \Nexus\Services\CommunityFundService::receiveDonation($donorId, $amount, $message);
    }
}

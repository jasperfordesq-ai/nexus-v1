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
class FederationEmailService
{
    public function __construct()
    {
    }

    /**
     * Delegates to legacy FederationEmailService::sendNewMessageNotification().
     */
    public function sendNewMessageNotification(int $recipientUserId, int $senderUserId, int $senderTenantId, string $messagePreview): bool
    {
        \Illuminate\Support\Facades\Log::warning('Legacy delegation removed: ' . __METHOD__);
        return false;
    }

    /**
     * Delegates to legacy FederationEmailService::sendTransactionNotification().
     */
    public function sendTransactionNotification(int $recipientUserId, int $senderUserId, int $senderTenantId, float $amount, string $description): bool
    {
        \Illuminate\Support\Facades\Log::warning('Legacy delegation removed: ' . __METHOD__);
        return false;
    }

    /**
     * Delegates to legacy FederationEmailService::sendTransactionConfirmation().
     */
    public function sendTransactionConfirmation(int $senderUserId, int $recipientUserId, int $recipientTenantId, float $amount, string $description, float $newBalance): bool
    {
        \Illuminate\Support\Facades\Log::warning('Legacy delegation removed: ' . __METHOD__);
        return false;
    }

    /**
     * Delegates to legacy FederationEmailService::sendWeeklyDigest().
     */
    public function sendWeeklyDigest(int $userId, int $tenantId): bool
    {
        \Illuminate\Support\Facades\Log::warning('Legacy delegation removed: ' . __METHOD__);
        return false;
    }

    /**
     * Delegates to legacy FederationEmailService::sendPartnershipRequestNotification().
     */
    public function sendPartnershipRequestNotification(int $targetTenantId, int $requestingTenantId, string $requestingTenantName, int $requestedLevel, ?string $notes = null): bool
    {
        \Illuminate\Support\Facades\Log::warning('Legacy delegation removed: ' . __METHOD__);
        return false;
    }
}

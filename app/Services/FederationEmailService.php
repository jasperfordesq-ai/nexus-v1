<?php
// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

namespace App\Services;

/**
 * FederationEmailService — Laravel DI wrapper for legacy \Nexus\Services\FederationEmailService.
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
        return \Nexus\Services\FederationEmailService::sendNewMessageNotification($recipientUserId, $senderUserId, $senderTenantId, $messagePreview);
    }

    /**
     * Delegates to legacy FederationEmailService::sendTransactionNotification().
     */
    public function sendTransactionNotification(int $recipientUserId, int $senderUserId, int $senderTenantId, float $amount, string $description): bool
    {
        return \Nexus\Services\FederationEmailService::sendTransactionNotification($recipientUserId, $senderUserId, $senderTenantId, $amount, $description);
    }

    /**
     * Delegates to legacy FederationEmailService::sendTransactionConfirmation().
     */
    public function sendTransactionConfirmation(int $senderUserId, int $recipientUserId, int $recipientTenantId, float $amount, string $description, float $newBalance): bool
    {
        return \Nexus\Services\FederationEmailService::sendTransactionConfirmation($senderUserId, $recipientUserId, $recipientTenantId, $amount, $description, $newBalance);
    }

    /**
     * Delegates to legacy FederationEmailService::sendWeeklyDigest().
     */
    public function sendWeeklyDigest(int $userId, int $tenantId): bool
    {
        return \Nexus\Services\FederationEmailService::sendWeeklyDigest($userId, $tenantId);
    }

    /**
     * Delegates to legacy FederationEmailService::sendPartnershipRequestNotification().
     */
    public function sendPartnershipRequestNotification(int $targetTenantId, int $requestingTenantId, string $requestingTenantName, int $requestedLevel, ?string $notes = null): bool
    {
        return \Nexus\Services\FederationEmailService::sendPartnershipRequestNotification($targetTenantId, $requestingTenantId, $requestingTenantName, $requestedLevel, $notes);
    }
}

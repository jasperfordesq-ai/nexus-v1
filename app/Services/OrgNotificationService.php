<?php
// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

namespace App\Services;

/**
 * OrgNotificationService — Laravel DI wrapper for legacy \Nexus\Services\OrgNotificationService.
 *
 * Provides dependency-injectable access to the legacy static service methods.
 */
class OrgNotificationService
{
    public function __construct()
    {
    }

    /**
     * Delegates to legacy OrgNotificationService::notifyPaymentReceived().
     */
    public function notifyPaymentReceived($recipientId, $organizationId, $amount, $description = '', $senderId = null)
    {
        return \Nexus\Services\OrgNotificationService::notifyPaymentReceived($recipientId, $organizationId, $amount, $description, $senderId);
    }

    /**
     * Delegates to legacy OrgNotificationService::notifyDepositReceived().
     */
    public function notifyDepositReceived($organizationId, $depositorId, $amount, $description = '')
    {
        return \Nexus\Services\OrgNotificationService::notifyDepositReceived($organizationId, $depositorId, $amount, $description);
    }

    /**
     * Delegates to legacy OrgNotificationService::notifyTransferRequestCreated().
     */
    public function notifyTransferRequestCreated($organizationId, $requesterId, $recipientId, $amount, $description = '')
    {
        return \Nexus\Services\OrgNotificationService::notifyTransferRequestCreated($organizationId, $requesterId, $recipientId, $amount, $description);
    }

    /**
     * Delegates to legacy OrgNotificationService::notifyTransferRequestApproved().
     */
    public function notifyTransferRequestApproved($requesterId, $recipientId, $organizationId, $amount, $approverId)
    {
        return \Nexus\Services\OrgNotificationService::notifyTransferRequestApproved($requesterId, $recipientId, $organizationId, $amount, $approverId);
    }

    /**
     * Delegates to legacy OrgNotificationService::notifyTransferRequestRejected().
     */
    public function notifyTransferRequestRejected($requesterId, $organizationId, $amount, $approverId, $reason = '')
    {
        return \Nexus\Services\OrgNotificationService::notifyTransferRequestRejected($requesterId, $organizationId, $amount, $approverId, $reason);
    }
}

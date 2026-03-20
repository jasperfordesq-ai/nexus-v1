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
        \Illuminate\Support\Facades\Log::warning('Legacy delegation removed: ' . __METHOD__);
        return null;
    }

    /**
     * Delegates to legacy OrgNotificationService::notifyDepositReceived().
     */
    public function notifyDepositReceived($organizationId, $depositorId, $amount, $description = '')
    {
        \Illuminate\Support\Facades\Log::warning('Legacy delegation removed: ' . __METHOD__);
        return null;
    }

    /**
     * Delegates to legacy OrgNotificationService::notifyTransferRequestCreated().
     */
    public function notifyTransferRequestCreated($organizationId, $requesterId, $recipientId, $amount, $description = '')
    {
        \Illuminate\Support\Facades\Log::warning('Legacy delegation removed: ' . __METHOD__);
        return null;
    }

    /**
     * Delegates to legacy OrgNotificationService::notifyTransferRequestApproved().
     */
    public function notifyTransferRequestApproved($requesterId, $recipientId, $organizationId, $amount, $approverId)
    {
        \Illuminate\Support\Facades\Log::warning('Legacy delegation removed: ' . __METHOD__);
        return null;
    }

    /**
     * Delegates to legacy OrgNotificationService::notifyTransferRequestRejected().
     */
    public function notifyTransferRequestRejected($requesterId, $organizationId, $amount, $approverId, $reason = '')
    {
        \Illuminate\Support\Facades\Log::warning('Legacy delegation removed: ' . __METHOD__);
        return null;
    }
}

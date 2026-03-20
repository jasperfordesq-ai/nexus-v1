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
class GroupApprovalWorkflowService
{
    public function __construct()
    {
    }

    /**
     * Delegates to legacy GroupApprovalWorkflowService::submitForApproval().
     */
    public function submitForApproval($groupId, $submittedBy, $notes = '')
    {
        \Illuminate\Support\Facades\Log::warning('Legacy delegation removed: ' . __METHOD__);
        return null;
    }

    /**
     * Delegates to legacy GroupApprovalWorkflowService::approveGroup().
     */
    public function approveGroup($requestId, $approvedBy, $notes = '')
    {
        \Illuminate\Support\Facades\Log::warning('Legacy delegation removed: ' . __METHOD__);
        return null;
    }

    /**
     * Delegates to legacy GroupApprovalWorkflowService::rejectGroup().
     */
    public function rejectGroup($requestId, $rejectedBy, $reason = '')
    {
        \Illuminate\Support\Facades\Log::warning('Legacy delegation removed: ' . __METHOD__);
        return null;
    }

    /**
     * Delegates to legacy GroupApprovalWorkflowService::requestChanges().
     */
    public function requestChanges($requestId, $reviewedBy, $changes = '')
    {
        \Illuminate\Support\Facades\Log::warning('Legacy delegation removed: ' . __METHOD__);
        return null;
    }

    /**
     * Delegates to legacy GroupApprovalWorkflowService::resubmit().
     */
    public function resubmit($groupId, $userId, $notes = '')
    {
        \Illuminate\Support\Facades\Log::warning('Legacy delegation removed: ' . __METHOD__);
        return null;
    }
}

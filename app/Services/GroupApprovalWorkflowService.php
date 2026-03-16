<?php
// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

namespace App\Services;

/**
 * GroupApprovalWorkflowService — Laravel DI wrapper for legacy \Nexus\Services\GroupApprovalWorkflowService.
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
        return \Nexus\Services\GroupApprovalWorkflowService::submitForApproval($groupId, $submittedBy, $notes);
    }

    /**
     * Delegates to legacy GroupApprovalWorkflowService::approveGroup().
     */
    public function approveGroup($requestId, $approvedBy, $notes = '')
    {
        return \Nexus\Services\GroupApprovalWorkflowService::approveGroup($requestId, $approvedBy, $notes);
    }

    /**
     * Delegates to legacy GroupApprovalWorkflowService::rejectGroup().
     */
    public function rejectGroup($requestId, $rejectedBy, $reason = '')
    {
        return \Nexus\Services\GroupApprovalWorkflowService::rejectGroup($requestId, $rejectedBy, $reason);
    }

    /**
     * Delegates to legacy GroupApprovalWorkflowService::requestChanges().
     */
    public function requestChanges($requestId, $reviewedBy, $changes = '')
    {
        return \Nexus\Services\GroupApprovalWorkflowService::requestChanges($requestId, $reviewedBy, $changes);
    }

    /**
     * Delegates to legacy GroupApprovalWorkflowService::resubmit().
     */
    public function resubmit($groupId, $userId, $notes = '')
    {
        return \Nexus\Services\GroupApprovalWorkflowService::resubmit($groupId, $userId, $notes);
    }
}

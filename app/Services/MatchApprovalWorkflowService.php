<?php
// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

namespace App\Services;

/**
 * MatchApprovalWorkflowService — Laravel DI wrapper for legacy \Nexus\Services\MatchApprovalWorkflowService.
 *
 * Provides dependency-injectable access to the legacy static service methods.
 */
class MatchApprovalWorkflowService
{
    public function __construct()
    {
    }

    /**
     * Delegates to legacy MatchApprovalWorkflowService::submitForApproval().
     */
    public function submitForApproval(int $userId, int $listingId, array $matchData): ?int
    {
        return \Nexus\Services\MatchApprovalWorkflowService::submitForApproval($userId, $listingId, $matchData);
    }

    /**
     * Delegates to legacy MatchApprovalWorkflowService::approveMatch().
     */
    public function approveMatch(int $requestId, int $approvedBy, string $notes = ''): bool
    {
        return \Nexus\Services\MatchApprovalWorkflowService::approveMatch($requestId, $approvedBy, $notes);
    }

    /**
     * Delegates to legacy MatchApprovalWorkflowService::rejectMatch().
     */
    public function rejectMatch(int $requestId, int $rejectedBy, string $reason = ''): bool
    {
        return \Nexus\Services\MatchApprovalWorkflowService::rejectMatch($requestId, $rejectedBy, $reason);
    }

    /**
     * Delegates to legacy MatchApprovalWorkflowService::bulkApprove().
     */
    public function bulkApprove(array $requestIds, int $approvedBy, string $notes = ''): int
    {
        return \Nexus\Services\MatchApprovalWorkflowService::bulkApprove($requestIds, $approvedBy, $notes);
    }

    /**
     * Delegates to legacy MatchApprovalWorkflowService::bulkReject().
     */
    public function bulkReject(array $requestIds, int $rejectedBy, string $reason = ''): int
    {
        return \Nexus\Services\MatchApprovalWorkflowService::bulkReject($requestIds, $rejectedBy, $reason);
    }
}

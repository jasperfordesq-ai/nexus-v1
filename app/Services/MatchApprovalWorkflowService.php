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
class MatchApprovalWorkflowService
{
    public function __construct()
    {
    }

    /**
     * Delegates to legacy MatchApprovalWorkflowService::submitForApproval().
     */
    public static function submitForApproval(int $userId, int $listingId, array $matchData): ?int
    {
        \Illuminate\Support\Facades\Log::warning('Legacy delegation removed: ' . __METHOD__);
        return null;
    }

    /**
     * Delegates to legacy MatchApprovalWorkflowService::approveMatch().
     */
    public static function approveMatch(int $requestId, int $approvedBy, string $notes = ''): bool
    {
        \Illuminate\Support\Facades\Log::warning('Legacy delegation removed: ' . __METHOD__);
        return false;
    }

    /**
     * Delegates to legacy MatchApprovalWorkflowService::rejectMatch().
     */
    public static function rejectMatch(int $requestId, int $rejectedBy, string $reason = ''): bool
    {
        \Illuminate\Support\Facades\Log::warning('Legacy delegation removed: ' . __METHOD__);
        return false;
    }

    /**
     * Delegates to legacy MatchApprovalWorkflowService::bulkApprove().
     */
    public static function bulkApprove(array $requestIds, int $approvedBy, string $notes = ''): int
    {
        \Illuminate\Support\Facades\Log::warning('Legacy delegation removed: ' . __METHOD__);
        return 0;
    }

    /**
     * Delegates to legacy MatchApprovalWorkflowService::bulkReject().
     */
    public static function bulkReject(array $requestIds, int $rejectedBy, string $reason = ''): int
    {
        \Illuminate\Support\Facades\Log::warning('Legacy delegation removed: ' . __METHOD__);
        return 0;
    }

    /**
     * Delegates to legacy MatchApprovalWorkflowService::getStatistics().
     */
    public static function getStatistics(int $days = 30): array
    {
        \Illuminate\Support\Facades\Log::warning('Legacy delegation removed: ' . __METHOD__);
        return [];
    }
}

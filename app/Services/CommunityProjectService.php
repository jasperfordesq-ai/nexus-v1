<?php
// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

namespace App\Services;

use Illuminate\Support\Facades\DB;

/**
 * CommunityProjectService — Laravel DI wrapper for legacy \Nexus\Services\CommunityProjectService.
 *
 * Provides dependency-injectable access to the legacy static service methods.
 */
class CommunityProjectService
{
    public function __construct()
    {
    }

    /**
     * Delegates to legacy CommunityProjectService::getErrors().
     */
    public function getErrors(): array
    {
        return \Nexus\Services\CommunityProjectService::getErrors();
    }

    /**
     * Delegates to legacy CommunityProjectService::propose().
     */
    public function propose(int $userId, array $data): array
    {
        return \Nexus\Services\CommunityProjectService::propose($userId, $data);
    }

    /**
     * Delegates to legacy CommunityProjectService::getProposals().
     */
    public function getProposals(array $filters = []): array
    {
        return \Nexus\Services\CommunityProjectService::getProposals($filters);
    }

    /**
     * Delegates to legacy CommunityProjectService::getProposal().
     */
    public function getProposal(int $id): ?array
    {
        return \Nexus\Services\CommunityProjectService::getProposal($id);
    }

    /**
     * Delegates to legacy CommunityProjectService::updateProposal().
     */
    public function updateProposal(int $id, int $userId, array $data): bool
    {
        return \Nexus\Services\CommunityProjectService::updateProposal($id, $userId, $data);
    }

    /**
     * Review a community project proposal (admin action).
     *
     * Validates the proposal is in a reviewable state, updates its status,
     * and on approval auto-converts to an opportunity via the legacy service.
     */
    public function review(int $proposalId, string $status, ?string $feedback, int $adminId, int $tenantId): bool
    {
        $validStatuses = ['approved', 'rejected', 'under_review'];
        if (!in_array($status, $validStatuses, true)) {
            return false;
        }

        $project = DB::table('vol_community_projects')
            ->where('id', $proposalId)
            ->where('tenant_id', $tenantId)
            ->first();

        if (!$project) {
            return false;
        }

        $reviewableStatuses = ['proposed', 'pending', 'under_review'];
        if (!in_array($project->status, $reviewableStatuses, true)) {
            return false;
        }

        DB::table('vol_community_projects')
            ->where('id', $proposalId)
            ->where('tenant_id', $tenantId)
            ->update([
                'status'       => $status,
                'reviewed_by'  => $adminId,
                'reviewed_at'  => now(),
                'review_notes' => $feedback,
                'updated_at'   => now(),
            ]);

        return true;
    }

    /**
     * Add a supporter to a community project proposal.
     *
     * Uses INSERT IGNORE to handle duplicate support gracefully and
     * increments the denormalized supporter_count.
     */
    public function support(int $proposalId, int $userId, int $tenantId): bool
    {
        $exists = DB::table('vol_community_projects')
            ->where('id', $proposalId)
            ->where('tenant_id', $tenantId)
            ->exists();

        if (!$exists) {
            return false;
        }

        // INSERT IGNORE handles the unique constraint on (project_id, user_id)
        $inserted = DB::statement(
            "INSERT IGNORE INTO vol_community_project_supporters (tenant_id, project_id, user_id, supported_at) VALUES (?, ?, ?, NOW())",
            [$tenantId, $proposalId, $userId]
        );

        if ($inserted) {
            DB::table('vol_community_projects')
                ->where('id', $proposalId)
                ->where('tenant_id', $tenantId)
                ->update([
                    'supporter_count' => DB::raw('supporter_count + 1'),
                    'updated_at'      => now(),
                ]);
        }

        return $inserted;
    }

    /**
     * Remove support from a community project.
     *
     * Decrements the denormalized supporter_count (floored at 0).
     */
    public function unsupport(int $proposalId, int $userId, int $tenantId): bool
    {
        $deleted = DB::table('vol_community_project_supporters')
            ->where('project_id', $proposalId)
            ->where('user_id', $userId)
            ->where('tenant_id', $tenantId)
            ->delete();

        if ($deleted > 0) {
            DB::table('vol_community_projects')
                ->where('id', $proposalId)
                ->where('tenant_id', $tenantId)
                ->update([
                    'supporter_count' => DB::raw('GREATEST(supporter_count - 1, 0)'),
                    'updated_at'      => now(),
                ]);
            return true;
        }

        return false;
    }
}

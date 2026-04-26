<?php
// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

declare(strict_types=1);

namespace App\Services;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Aggregates KISS-style coordinator workflow signals for caring communities.
 */
class CaringCommunityWorkflowService
{
    public function __construct(private readonly CaringCommunityRolePresetService $rolePresetService)
    {
    }

    public function summary(int $tenantId): array
    {
        return [
            'stats' => $this->stats($tenantId),
            'pending_reviews' => $this->pendingReviews($tenantId),
            'recent_decisions' => $this->recentDecisions($tenantId),
            'coordinator_signals' => $this->coordinatorSignals($tenantId),
            'role_pack' => $this->rolePresetService->status($tenantId),
        ];
    }

    private function stats(int $tenantId): array
    {
        if (!Schema::hasTable('vol_logs')) {
            return [
                'pending_count' => 0,
                'pending_hours' => 0.0,
                'overdue_count' => 0,
                'approved_30d_hours' => 0.0,
                'declined_30d_count' => 0,
                'coordinator_count' => $this->coordinatorCount($tenantId),
            ];
        }

        $row = DB::selectOne(
            "SELECT
                COUNT(CASE WHEN status = 'pending' THEN 1 END) AS pending_count,
                COALESCE(SUM(CASE WHEN status = 'pending' THEN hours ELSE 0 END), 0) AS pending_hours,
                COUNT(CASE WHEN status = 'pending' AND created_at < DATE_SUB(NOW(), INTERVAL 7 DAY) THEN 1 END) AS overdue_count,
                COALESCE(SUM(CASE WHEN status = 'approved' AND created_at >= DATE_SUB(NOW(), INTERVAL 30 DAY) THEN hours ELSE 0 END), 0) AS approved_30d_hours,
                COUNT(CASE WHEN status = 'declined' AND created_at >= DATE_SUB(NOW(), INTERVAL 30 DAY) THEN 1 END) AS declined_30d_count
             FROM vol_logs
             WHERE tenant_id = ?",
            [$tenantId]
        );

        return [
            'pending_count' => (int) ($row->pending_count ?? 0),
            'pending_hours' => round((float) ($row->pending_hours ?? 0), 1),
            'overdue_count' => (int) ($row->overdue_count ?? 0),
            'approved_30d_hours' => round((float) ($row->approved_30d_hours ?? 0), 1),
            'declined_30d_count' => (int) ($row->declined_30d_count ?? 0),
            'coordinator_count' => $this->coordinatorCount($tenantId),
        ];
    }

    private function pendingReviews(int $tenantId): array
    {
        if (!Schema::hasTable('vol_logs')) {
            return [];
        }

        $rows = DB::select(
            "SELECT
                vl.id,
                vl.hours,
                vl.date_logged,
                vl.created_at,
                vl.description,
                u.name AS member_name,
                u.first_name,
                u.last_name,
                vo.name AS organisation_name,
                opp.title AS opportunity_title
             FROM vol_logs vl
             LEFT JOIN users u ON u.id = vl.user_id AND u.tenant_id = vl.tenant_id
             LEFT JOIN vol_organizations vo ON vo.id = vl.organization_id AND vo.tenant_id = vl.tenant_id
             LEFT JOIN vol_opportunities opp ON opp.id = vl.opportunity_id AND opp.tenant_id = vl.tenant_id
             WHERE vl.tenant_id = ? AND vl.status = 'pending'
             ORDER BY vl.created_at ASC, vl.id ASC
             LIMIT 12",
            [$tenantId]
        );

        return array_map(function ($row) {
            $fullName = trim((string) ($row->first_name ?? '') . ' ' . (string) ($row->last_name ?? ''));
            return [
                'id' => (int) $row->id,
                'member_name' => $fullName !== '' ? $fullName : (string) ($row->member_name ?? ''),
                'organisation_name' => (string) ($row->organisation_name ?? ''),
                'opportunity_title' => (string) ($row->opportunity_title ?? ''),
                'hours' => round((float) $row->hours, 1),
                'date_logged' => (string) $row->date_logged,
                'created_at' => (string) $row->created_at,
                'is_overdue' => strtotime((string) $row->created_at) < strtotime('-7 days'),
            ];
        }, $rows);
    }

    private function recentDecisions(int $tenantId): array
    {
        if (!Schema::hasTable('vol_logs')) {
            return [];
        }

        $rows = DB::select(
            "SELECT
                vl.id,
                vl.hours,
                vl.status,
                vl.updated_at,
                u.name AS member_name,
                u.first_name,
                u.last_name,
                vo.name AS organisation_name
             FROM vol_logs vl
             LEFT JOIN users u ON u.id = vl.user_id AND u.tenant_id = vl.tenant_id
             LEFT JOIN vol_organizations vo ON vo.id = vl.organization_id AND vo.tenant_id = vl.tenant_id
             WHERE vl.tenant_id = ? AND vl.status IN ('approved', 'declined')
             ORDER BY COALESCE(vl.updated_at, vl.created_at) DESC, vl.id DESC
             LIMIT 8",
            [$tenantId]
        );

        return array_map(function ($row) {
            $fullName = trim((string) ($row->first_name ?? '') . ' ' . (string) ($row->last_name ?? ''));
            return [
                'id' => (int) $row->id,
                'member_name' => $fullName !== '' ? $fullName : (string) ($row->member_name ?? ''),
                'organisation_name' => (string) ($row->organisation_name ?? ''),
                'hours' => round((float) $row->hours, 1),
                'status' => (string) $row->status,
                'decided_at' => (string) ($row->updated_at ?? ''),
            ];
        }, $rows);
    }

    private function coordinatorSignals(int $tenantId): array
    {
        $activeRequests = 0;
        $activeOffers = 0;
        $trustedOrganisations = 0;

        if (Schema::hasTable('listings')) {
            $listingRow = DB::selectOne(
                "SELECT
                    COUNT(CASE WHEN type IN ('request', 'need') THEN 1 END) AS active_requests,
                    COUNT(CASE WHEN type IN ('offer', 'service') THEN 1 END) AS active_offers
                 FROM listings
                 WHERE tenant_id = ? AND status = 'active'",
                [$tenantId]
            );
            $activeRequests = (int) ($listingRow->active_requests ?? 0);
            $activeOffers = (int) ($listingRow->active_offers ?? 0);
        }

        if (Schema::hasTable('vol_organizations')) {
            $trustedOrganisations = (int) DB::selectOne(
                "SELECT COUNT(*) AS count
                 FROM vol_organizations
                 WHERE tenant_id = ? AND status IN ('approved', 'active')",
                [$tenantId]
            )->count;
        }

        return [
            'active_requests' => $activeRequests,
            'active_offers' => $activeOffers,
            'trusted_organisations' => $trustedOrganisations,
        ];
    }

    private function coordinatorCount(int $tenantId): int
    {
        $row = DB::selectOne(
            "SELECT COUNT(*) AS count
             FROM users
             WHERE tenant_id = ?
                AND status = 'active'
                AND (
                    role IN ('admin', 'tenant_admin', 'broker', 'super_admin')
                    OR is_admin = 1
                    OR is_tenant_super_admin = 1
                )",
            [$tenantId]
        );

        return (int) ($row->count ?? 0);
    }
}

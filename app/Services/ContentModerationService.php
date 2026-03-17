<?php
// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

namespace App\Services;

use Illuminate\Support\Facades\DB;

/**
 * ContentModerationService — Laravel DI-based service for content moderation.
 *
 * Eloquent/DI counterpart to the legacy static \Nexus\Services\ContentModerationService.
 * Manages reported content review, approval, and rejection workflows.
 */
class ContentModerationService
{
    /**
     * Get all content reports for a tenant with pagination.
     *
     * @return array{items: array, total: int}
     */
    public function getReports(int $tenantId, array $filters = []): array
    {
        $limit = min((int) ($filters['limit'] ?? 20), 100);
        $offset = max(0, (int) ($filters['offset'] ?? 0));
        $status = $filters['status'] ?? null;

        $query = DB::table('content_reports as cr')
            ->leftJoin('users as reporter', 'cr.reporter_id', '=', 'reporter.id')
            ->where('cr.tenant_id', $tenantId)
            ->select('cr.*', 'reporter.name as reporter_name');

        if ($status !== null) {
            $query->where('cr.status', $status);
        }

        $total = $query->count();
        $items = $query->orderByDesc('cr.created_at')
            ->offset($offset)
            ->limit($limit)
            ->get()
            ->map(fn ($r) => (array) $r)
            ->all();

        return ['items' => $items, 'total' => $total];
    }

    /**
     * Approve reported content (dismiss the report).
     */
    public function approve(int $reportId, int $tenantId, int $moderatorId): bool
    {
        return DB::table('content_reports')
            ->where('id', $reportId)
            ->where('tenant_id', $tenantId)
            ->where('status', 'pending')
            ->update([
                'status'       => 'approved',
                'resolved_by'  => $moderatorId,
                'resolved_at'  => now(),
                'updated_at'   => now(),
            ]) > 0;
    }

    /**
     * Reject reported content (take action — hide or remove).
     */
    public function reject(int $reportId, int $tenantId, int $moderatorId, ?string $reason = null): bool
    {
        return DB::table('content_reports')
            ->where('id', $reportId)
            ->where('tenant_id', $tenantId)
            ->where('status', 'pending')
            ->update([
                'status'       => 'rejected',
                'resolved_by'  => $moderatorId,
                'resolved_at'  => now(),
                'action_notes' => $reason,
                'updated_at'   => now(),
            ]) > 0;
    }

    /**
     * Get moderation statistics for a tenant.
     */
    public function getStats(int $tenantId): array
    {
        $rows = DB::table('content_reports')
            ->where('tenant_id', $tenantId)
            ->selectRaw('status, COUNT(*) as count')
            ->groupBy('status')
            ->pluck('count', 'status')
            ->all();

        return [
            'pending'  => (int) ($rows['pending'] ?? 0),
            'approved' => (int) ($rows['approved'] ?? 0),
            'rejected' => (int) ($rows['rejected'] ?? 0),
            'total'    => array_sum(array_map('intval', $rows)),
        ];
    }

    // =========================================================================
    // Legacy delegation methods — used by AdminAnalyticsReportsController
    // =========================================================================

    /**
     * Delegates to legacy ContentModerationService::getQueue().
     */
    public function getQueue(int $tenantId, array $filters = [], int $limit = 50, int $offset = 0): array
    {
        return \Nexus\Services\ContentModerationService::getQueue($tenantId, $filters, $limit, $offset);
    }

    /**
     * Delegates to legacy ContentModerationService::review().
     */
    public function review(int $id, int $tenantId, int $adminId, string $decision, ?string $rejectionReason = null): array
    {
        return \Nexus\Services\ContentModerationService::review($id, $tenantId, $adminId, $decision, $rejectionReason);
    }

    /**
     * Delegates to legacy ContentModerationService::getModerationSettings().
     */
    public function getModerationSettings(int $tenantId): array
    {
        return \Nexus\Services\ContentModerationService::getModerationSettings($tenantId);
    }

    /**
     * Delegates to legacy ContentModerationService::updateSettings().
     */
    public function updateSettings(int $tenantId, array $settings): bool
    {
        return \Nexus\Services\ContentModerationService::updateSettings($tenantId, $settings);
    }
}

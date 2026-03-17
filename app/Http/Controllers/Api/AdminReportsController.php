<?php
// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

namespace App\Http\Controllers\Api;

use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;
use App\Models\ActivityLog;

/**
 * AdminReportsController -- Admin user and content report handling.
 *
 * Manage user-submitted reports (flagged content).
 * All endpoints require admin authentication.
 */
class AdminReportsController extends BaseApiController
{
    protected bool $isV2Api = true;

    /**
     * Check if the current user is a super admin.
     */
    private function isSuperAdmin(): bool
    {
        $userId = $this->getUserId();
        $user = DB::selectOne(
            "SELECT is_super_admin, is_tenant_super_admin FROM users WHERE id = ?",
            [$userId]
        );
        return $user && (!empty($user->is_super_admin) || !empty($user->is_tenant_super_admin));
    }

    /**
     * Resolve effective tenant ID for admin filtering.
     */
    private function resolveEffectiveTenantId(bool $isSuperAdmin, int $tenantId): ?int
    {
        $filterRaw = $this->query('tenant_id');

        if ($isSuperAdmin) {
            if ($filterRaw === 'all') {
                return null;
            }
            if ($filterRaw !== null && is_numeric($filterRaw)) {
                return (int) $filterRaw;
            }
            return $tenantId;
        }

        return $tenantId;
    }

    /**
     * GET /api/v2/admin/reports
     *
     * Query params: page, limit, type, status, search
     */
    public function index(): JsonResponse
    {
        $this->requireAdmin();
        $superAdmin = $this->isSuperAdmin();
        $tenantId = $this->getTenantId();

        $page = $this->queryInt('page', 1, 1);
        $limit = $this->queryInt('limit', 20, 1, 100);
        $offset = ($page - 1) * $limit;
        $type = $this->query('type');
        $status = $this->query('status');
        $search = $this->query('search');

        $conditions = [];
        $params = [];

        $effectiveTenantId = $this->resolveEffectiveTenantId($superAdmin, $tenantId);
        if ($effectiveTenantId !== null) {
            $conditions[] = 'r.tenant_id = ?';
            $params[] = $effectiveTenantId;
        }

        if ($type && in_array($type, ['listing', 'user', 'message'], true)) {
            $conditions[] = 'r.target_type = ?';
            $params[] = $type;
        }

        if ($status && in_array($status, ['open', 'resolved', 'dismissed'], true)) {
            $conditions[] = 'r.status = ?';
            $params[] = $status;
        }

        if ($search) {
            $conditions[] = '(r.reason LIKE ? OR reporter.name LIKE ?)';
            $searchPattern = '%' . $search . '%';
            $params[] = $searchPattern;
            $params[] = $searchPattern;
        }

        $where = !empty($conditions) ? implode(' AND ', $conditions) : '1=1';

        $total = (int) DB::selectOne(
            "SELECT COUNT(*) as total FROM reports r LEFT JOIN users reporter ON r.reporter_id = reporter.id WHERE {$where}",
            $params
        )->total;

        $reports = DB::select(
            "SELECT r.*, reporter.name as reporter_name, reporter.avatar_url as reporter_avatar, t.name as tenant_name
             FROM reports r
             LEFT JOIN users reporter ON r.reporter_id = reporter.id
             LEFT JOIN tenants t ON r.tenant_id = t.id
             WHERE {$where}
             ORDER BY r.created_at DESC
             LIMIT ? OFFSET ?",
            array_merge($params, [$limit, $offset])
        );

        $formatted = array_map(function ($report) {
            return [
                'id' => (int) $report->id,
                'tenant_id' => (int) $report->tenant_id,
                'tenant_name' => $report->tenant_name ?? 'Unknown',
                'reporter_id' => (int) $report->reporter_id,
                'reporter_name' => $report->reporter_name ?? 'Unknown',
                'reporter_avatar' => $report->reporter_avatar,
                'content_type' => $report->target_type,
                'target_id' => (int) $report->target_id,
                'reason' => $report->reason,
                'description' => $report->reason,
                'status' => $report->status,
                'created_at' => $report->created_at,
                'updated_at' => $report->updated_at ?? $report->created_at,
            ];
        }, $reports);

        return $this->respondWithPaginatedCollection($formatted, $total, $page, $limit);
    }

    /**
     * GET /api/v2/admin/reports/{id}
     */
    public function show(int $id): JsonResponse
    {
        $this->requireAdmin();
        $superAdmin = $this->isSuperAdmin();
        $tenantId = $this->getTenantId();

        if ($superAdmin) {
            $report = DB::selectOne(
                "SELECT r.*, reporter.name as reporter_name, reporter.avatar_url as reporter_avatar, t.name as tenant_name
                 FROM reports r
                 LEFT JOIN users reporter ON r.reporter_id = reporter.id
                 LEFT JOIN tenants t ON r.tenant_id = t.id
                 WHERE r.id = ?",
                [$id]
            );
        } else {
            $report = DB::selectOne(
                "SELECT r.*, reporter.name as reporter_name, reporter.avatar_url as reporter_avatar, t.name as tenant_name
                 FROM reports r
                 LEFT JOIN users reporter ON r.reporter_id = reporter.id
                 LEFT JOIN tenants t ON r.tenant_id = t.id
                 WHERE r.id = ? AND r.tenant_id = ?",
                [$id, $tenantId]
            );
        }

        if (!$report) {
            return $this->respondWithError('NOT_FOUND', 'Report not found', null, 404);
        }

        return $this->respondWithData([
            'id' => (int) $report->id,
            'tenant_id' => (int) $report->tenant_id,
            'tenant_name' => $report->tenant_name ?? 'Unknown',
            'reporter_id' => (int) $report->reporter_id,
            'reporter_name' => $report->reporter_name ?? 'Unknown',
            'reporter_avatar' => $report->reporter_avatar,
            'content_type' => $report->target_type,
            'target_id' => (int) $report->target_id,
            'reason' => $report->reason,
            'description' => $report->reason,
            'status' => $report->status,
            'created_at' => $report->created_at,
            'updated_at' => $report->updated_at ?? $report->created_at,
        ]);
    }

    /**
     * POST /api/v2/admin/reports/{id}/resolve
     */
    public function resolve(int $id): JsonResponse
    {
        $adminId = $this->requireAdmin();
        $superAdmin = $this->isSuperAdmin();
        $tenantId = $this->getTenantId();

        if ($superAdmin) {
            $report = DB::selectOne("SELECT id, status, tenant_id FROM reports WHERE id = ?", [$id]);
        } else {
            $report = DB::selectOne("SELECT id, status, tenant_id FROM reports WHERE id = ? AND tenant_id = ?", [$id, $tenantId]);
        }

        if (!$report) {
            return $this->respondWithError('NOT_FOUND', 'Report not found', null, 404);
        }

        if ($report->status !== 'open') {
            return $this->respondWithError('ALREADY_PROCESSED', 'Report is already ' . $report->status, null, 400);
        }

        $reportTenantId = (int) $report->tenant_id;

        DB::update(
            "UPDATE reports SET status = 'resolved', updated_at = NOW() WHERE id = ? AND tenant_id = ?",
            [$id, $reportTenantId]
        );

        ActivityLog::log(
            $adminId,
            'resolve_report',
            "Resolved report ID {$id}" . ($superAdmin ? " (tenant {$reportTenantId})" : '')
        );

        return $this->respondWithData(['success' => true, 'message' => 'Report resolved']);
    }

    /**
     * POST /api/v2/admin/reports/{id}/dismiss
     */
    public function dismiss(int $id): JsonResponse
    {
        $adminId = $this->requireAdmin();
        $superAdmin = $this->isSuperAdmin();
        $tenantId = $this->getTenantId();

        if ($superAdmin) {
            $report = DB::selectOne("SELECT id, status, tenant_id FROM reports WHERE id = ?", [$id]);
        } else {
            $report = DB::selectOne("SELECT id, status, tenant_id FROM reports WHERE id = ? AND tenant_id = ?", [$id, $tenantId]);
        }

        if (!$report) {
            return $this->respondWithError('NOT_FOUND', 'Report not found', null, 404);
        }

        if ($report->status !== 'open') {
            return $this->respondWithError('ALREADY_PROCESSED', 'Report is already ' . $report->status, null, 400);
        }

        $reportTenantId = (int) $report->tenant_id;

        DB::update(
            "UPDATE reports SET status = 'dismissed', updated_at = NOW() WHERE id = ? AND tenant_id = ?",
            [$id, $reportTenantId]
        );

        ActivityLog::log(
            $adminId,
            'dismiss_report',
            "Dismissed report ID {$id}" . ($superAdmin ? " (tenant {$reportTenantId})" : '')
        );

        return $this->respondWithData(['success' => true, 'message' => 'Report dismissed']);
    }

    /**
     * GET /api/v2/admin/reports/stats
     */
    public function stats(): JsonResponse
    {
        $this->requireAdmin();
        $superAdmin = $this->isSuperAdmin();
        $tenantId = $this->getTenantId();

        $effectiveTenantId = $this->resolveEffectiveTenantId($superAdmin, $tenantId);
        $tenantWhere = $effectiveTenantId !== null ? 'tenant_id = ?' : '1=1';
        $tenantParams = $effectiveTenantId !== null ? [$effectiveTenantId] : [];

        $stats = DB::selectOne(
            "SELECT
                COUNT(*) as total,
                SUM(CASE WHEN status = 'open' THEN 1 ELSE 0 END) as pending,
                SUM(CASE WHEN status = 'resolved' THEN 1 ELSE 0 END) as resolved,
                SUM(CASE WHEN status = 'dismissed' THEN 1 ELSE 0 END) as dismissed
             FROM reports
             WHERE {$tenantWhere}",
            $tenantParams
        );

        return $this->respondWithData([
            'total' => (int) ($stats->total ?? 0),
            'pending' => (int) ($stats->pending ?? 0),
            'resolved' => (int) ($stats->resolved ?? 0),
            'dismissed' => (int) ($stats->dismissed ?? 0),
        ]);
    }
}

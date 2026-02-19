<?php
// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

namespace Nexus\Controllers\Api;

use Nexus\Core\Database;
use Nexus\Core\TenantContext;
use Nexus\Models\ActivityLog;

/**
 * AdminReportsApiController - V2 API for reports moderation
 *
 * Manage user-submitted reports (flagged content).
 * All endpoints require admin authentication.
 *
 * Schema notes:
 * - target_type ENUM('listing','user','message')
 * - status ENUM('open','resolved','dismissed')
 * - reason is TEXT field (no separate description)
 *
 * Endpoints:
 * - GET    /api/v2/admin/reports           - List reports
 * - GET    /api/v2/admin/reports/{id}      - Get report detail
 * - POST   /api/v2/admin/reports/{id}/resolve  - Resolve report
 * - POST   /api/v2/admin/reports/{id}/dismiss  - Dismiss report
 * - GET    /api/v2/admin/reports/stats     - Get report stats
 */
class AdminReportsApiController extends BaseApiController
{
    protected bool $isV2Api = true;

    /**
     * GET /api/v2/admin/reports
     *
     * Query params: page, limit, type, status, search
     */
    public function index(): void
    {
        $this->requireAdmin();
        $tenantId = TenantContext::getId();

        $page = max(1, (int) ($_GET['page'] ?? 1));
        $limit = min(100, max(1, (int) ($_GET['limit'] ?? 20)));
        $offset = ($page - 1) * $limit;
        $type = $_GET['type'] ?? null;
        $status = $_GET['status'] ?? null;
        $search = $_GET['search'] ?? null;

        $conditions = ['r.tenant_id = ?'];
        $params = [$tenantId];

        // Type filter (listing, user, message)
        if ($type && in_array($type, ['listing', 'user', 'message'], true)) {
            $conditions[] = 'r.target_type = ?';
            $params[] = $type;
        }

        // Status filter (open, resolved, dismissed)
        if ($status && in_array($status, ['open', 'resolved', 'dismissed'], true)) {
            $conditions[] = 'r.status = ?';
            $params[] = $status;
        }

        // Search filter (reason + reporter name)
        if ($search) {
            $conditions[] = '(r.reason LIKE ? OR reporter.name LIKE ?)';
            $searchPattern = '%' . $search . '%';
            $params[] = $searchPattern;
            $params[] = $searchPattern;
        }

        $where = implode(' AND ', $conditions);

        // Get total count
        $countQuery = "SELECT COUNT(*) as total
                       FROM reports r
                       LEFT JOIN users reporter ON r.reporter_id = reporter.id
                       WHERE {$where}";
        $countStmt = Database::query($countQuery, $params);
        $total = (int) $countStmt->fetch()['total'];

        // Get paginated results
        $query = "SELECT r.*,
                         reporter.name as reporter_name,
                         reporter.avatar_url as reporter_avatar
                  FROM reports r
                  LEFT JOIN users reporter ON r.reporter_id = reporter.id
                  WHERE {$where}
                  ORDER BY r.created_at DESC
                  LIMIT ? OFFSET ?";

        $params[] = $limit;
        $params[] = $offset;
        $stmt = Database::query($query, $params);
        $reports = $stmt->fetchAll();

        // Format for frontend (map target_type → content_type for React compatibility)
        $formatted = array_map(function ($report) {
            return [
                'id' => (int) $report['id'],
                'reporter_id' => (int) $report['reporter_id'],
                'reporter_name' => $report['reporter_name'] ?? 'Unknown',
                'reporter_avatar' => $report['reporter_avatar'],
                'content_type' => $report['target_type'], // Map target_type → content_type
                'target_id' => (int) $report['target_id'],
                'reason' => $report['reason'],
                'description' => $report['reason'], // Use reason as description for compatibility
                'status' => $report['status'],
                'created_at' => $report['created_at'],
                'updated_at' => $report['updated_at'] ?? $report['created_at'],
            ];
        }, $reports);

        $this->respondWithPaginatedCollection($formatted, $total, $page, $limit);
    }

    /**
     * GET /api/v2/admin/reports/{id}
     */
    public function show(int $id): void
    {
        $this->requireAdmin();
        $tenantId = TenantContext::getId();

        $query = "SELECT r.*,
                         reporter.name as reporter_name,
                         reporter.avatar_url as reporter_avatar
                  FROM reports r
                  LEFT JOIN users reporter ON r.reporter_id = reporter.id
                  WHERE r.id = ? AND r.tenant_id = ?";

        $stmt = Database::query($query, [$id, $tenantId]);
        $report = $stmt->fetch();

        if (!$report) {
            $this->respondWithError('NOT_FOUND', 'Report not found', null, 404);
            return;
        }

        $formatted = [
            'id' => (int) $report['id'],
            'reporter_id' => (int) $report['reporter_id'],
            'reporter_name' => $report['reporter_name'] ?? 'Unknown',
            'reporter_avatar' => $report['reporter_avatar'],
            'content_type' => $report['target_type'],
            'target_id' => (int) $report['target_id'],
            'reason' => $report['reason'],
            'description' => $report['reason'],
            'status' => $report['status'],
            'created_at' => $report['created_at'],
            'updated_at' => $report['updated_at'] ?? $report['created_at'],
        ];

        $this->respondWithData($formatted);
    }

    /**
     * POST /api/v2/admin/reports/{id}/resolve
     */
    public function resolve(int $id): void
    {
        $this->requireAdmin();
        $tenantId = TenantContext::getId();
        $adminId = $this->getAuthenticatedUserId();

        // Check report exists and is not already resolved
        $stmt = Database::query(
            "SELECT id, status FROM reports WHERE id = ? AND tenant_id = ?",
            [$id, $tenantId]
        );
        $report = $stmt->fetch();

        if (!$report) {
            $this->respondWithError('NOT_FOUND', 'Report not found', null, 404);
            return;
        }

        if ($report['status'] !== 'open') {
            $this->respondWithError('ALREADY_PROCESSED', 'Report is already ' . $report['status'], null, 400);
            return;
        }

        // Update status to resolved
        Database::query(
            "UPDATE reports SET status = 'resolved', updated_at = NOW() WHERE id = ? AND tenant_id = ?",
            [$id, $tenantId]
        );

        // Log activity
        ActivityLog::log($adminId, 'resolve_report', "Resolved report ID {$id}");

        $this->respondWithData(['success' => true, 'message' => 'Report resolved']);
    }

    /**
     * POST /api/v2/admin/reports/{id}/dismiss
     */
    public function dismiss(int $id): void
    {
        $this->requireAdmin();
        $tenantId = TenantContext::getId();
        $adminId = $this->getAuthenticatedUserId();

        // Check report exists and is not already dismissed
        $stmt = Database::query(
            "SELECT id, status FROM reports WHERE id = ? AND tenant_id = ?",
            [$id, $tenantId]
        );
        $report = $stmt->fetch();

        if (!$report) {
            $this->respondWithError('NOT_FOUND', 'Report not found', null, 404);
            return;
        }

        if ($report['status'] !== 'open') {
            $this->respondWithError('ALREADY_PROCESSED', 'Report is already ' . $report['status'], null, 400);
            return;
        }

        // Update status to dismissed
        Database::query(
            "UPDATE reports SET status = 'dismissed', updated_at = NOW() WHERE id = ? AND tenant_id = ?",
            [$id, $tenantId]
        );

        // Log activity
        ActivityLog::log($adminId, 'dismiss_report', "Dismissed report ID {$id}");

        $this->respondWithData(['success' => true, 'message' => 'Report dismissed']);
    }

    /**
     * GET /api/v2/admin/reports/stats
     */
    public function stats(): void
    {
        $this->requireAdmin();
        $tenantId = TenantContext::getId();

        $query = "SELECT
                    COUNT(*) as total,
                    SUM(CASE WHEN status = 'open' THEN 1 ELSE 0 END) as pending,
                    SUM(CASE WHEN status = 'resolved' THEN 1 ELSE 0 END) as resolved,
                    SUM(CASE WHEN status = 'dismissed' THEN 1 ELSE 0 END) as dismissed
                  FROM reports
                  WHERE tenant_id = ?";

        $stmt = Database::query($query, [$tenantId]);
        $stats = $stmt->fetch();

        $formatted = [
            'total' => (int) ($stats['total'] ?? 0),
            'pending' => (int) ($stats['pending'] ?? 0),
            'resolved' => (int) ($stats['resolved'] ?? 0),
            'dismissed' => (int) ($stats['dismissed'] ?? 0),
        ];

        $this->respondWithData($formatted);
    }
}

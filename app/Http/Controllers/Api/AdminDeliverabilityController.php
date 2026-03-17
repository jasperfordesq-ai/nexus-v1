<?php
// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

namespace App\Http\Controllers\Api;

use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;
use Nexus\Core\TenantContext;

/**
 * AdminDeliverabilityController -- Admin deliverability dashboard, analytics, and CRUD.
 *
 * All methods require admin authentication.
 * Uses legacy static services for complex operations (history logging, comment creation).
 */
class AdminDeliverabilityController extends BaseApiController
{
    protected bool $isV2Api = true;

    public function __construct() {}

    private const VALID_STATUSES = ['draft', 'ready', 'in_progress', 'blocked', 'review', 'completed', 'cancelled', 'on_hold'];
    private const VALID_PRIORITIES = ['low', 'medium', 'high', 'urgent'];

    // =========================================================================
    // Dashboard & Analytics
    // =========================================================================

    /** GET /api/v2/admin/deliverability/dashboard */
    public function getDashboard(): JsonResponse
    {
        $this->requireAdmin();
        $tenantId = $this->getTenantId();

        $total = (int) DB::selectOne("SELECT COUNT(*) as cnt FROM deliverables WHERE tenant_id = ?", [$tenantId])->cnt;

        $statusRows = DB::select("SELECT status, COUNT(*) as cnt FROM deliverables WHERE tenant_id = ? GROUP BY status", [$tenantId]);
        $byStatus = [];
        foreach ($statusRows as $row) { $byStatus[$row->status] = (int) $row->cnt; }

        $overdue = (int) DB::selectOne(
            "SELECT COUNT(*) as cnt FROM deliverables WHERE tenant_id = ? AND due_date < CURDATE() AND status NOT IN ('completed', 'cancelled')",
            [$tenantId]
        )->cnt;

        $completed = (int) ($byStatus['completed'] ?? 0);
        $completionRate = $total > 0 ? round(($completed / $total) * 100, 1) : 0;

        $recentActivity = DB::select(
            "SELECT h.id, h.deliverable_id, h.action_type, h.field_name, h.change_description, h.action_timestamp,
                    d.title as deliverable_title, CONCAT(COALESCE(u.first_name, ''), ' ', COALESCE(u.last_name, '')) as user_name
             FROM deliverable_history h LEFT JOIN deliverables d ON h.deliverable_id = d.id LEFT JOIN users u ON h.user_id = u.id
             WHERE h.tenant_id = ? ORDER BY h.action_timestamp DESC LIMIT 10",
            [$tenantId]
        );

        return $this->respondWithData([
            'total' => $total, 'by_status' => $byStatus, 'overdue' => $overdue, 'completion_rate' => $completionRate,
            'recent_activity' => array_map(fn($r) => [
                'id' => (int) $r->id, 'deliverable_id' => (int) $r->deliverable_id, 'deliverable_title' => $r->deliverable_title ?? '',
                'action_type' => $r->action_type, 'field_name' => $r->field_name, 'change_description' => $r->change_description,
                'user_name' => trim($r->user_name ?? ''), 'action_timestamp' => $r->action_timestamp,
            ], $recentActivity),
        ]);
    }

    /** GET /api/v2/admin/deliverability/analytics */
    public function getAnalytics(): JsonResponse
    {
        $this->requireAdmin();
        $tenantId = $this->getTenantId();

        $completionTrends = DB::select(
            "SELECT DATE(completed_at) as date, COUNT(*) as count FROM deliverables
             WHERE tenant_id = ? AND completed_at IS NOT NULL AND completed_at >= DATE_SUB(CURDATE(), INTERVAL 30 DAY)
             GROUP BY DATE(completed_at) ORDER BY date ASC",
            [$tenantId]
        );

        $priorityRows = DB::select("SELECT priority, COUNT(*) as cnt FROM deliverables WHERE tenant_id = ? GROUP BY priority", [$tenantId]);
        $priorityDistribution = [];
        foreach ($priorityRows as $r) { $priorityDistribution[$r->priority] = (int) $r->cnt; }

        $avgTime = DB::selectOne(
            "SELECT AVG(DATEDIFF(completed_at, created_at)) as avg_days FROM deliverables WHERE tenant_id = ? AND completed_at IS NOT NULL AND status = 'completed'",
            [$tenantId]
        );

        $riskRows = DB::select("SELECT risk_level, COUNT(*) as cnt FROM deliverables WHERE tenant_id = ? AND risk_level IS NOT NULL GROUP BY risk_level", [$tenantId]);
        $riskDistribution = [];
        foreach ($riskRows as $r) { $riskDistribution[$r->risk_level] = (int) $r->cnt; }

        return $this->respondWithData([
            'completion_trends' => array_map(fn($r) => ['date' => $r->date, 'count' => (int) $r->count], $completionTrends),
            'priority_distribution' => $priorityDistribution,
            'avg_days_to_complete' => $avgTime->avg_days !== null ? round((float) $avgTime->avg_days, 1) : null,
            'risk_distribution' => $riskDistribution,
        ]);
    }

    // =========================================================================
    // CRUD (delegate to legacy for complex history-logging operations)
    // =========================================================================

    /** GET /api/v2/admin/deliverability */
    public function getDeliverables(): JsonResponse
    {
        return $this->delegate(\Nexus\Controllers\Api\AdminDeliverabilityApiController::class, 'getDeliverables');
    }

    /** GET /api/v2/admin/deliverability/{id} */
    public function getDeliverable(int $id): JsonResponse
    {
        return $this->delegate(\Nexus\Controllers\Api\AdminDeliverabilityApiController::class, 'getDeliverable', [$id]);
    }

    /** POST /api/v2/admin/deliverability */
    public function createDeliverable(): JsonResponse
    {
        return $this->delegate(\Nexus\Controllers\Api\AdminDeliverabilityApiController::class, 'createDeliverable');
    }

    /** PUT /api/v2/admin/deliverability/{id} */
    public function updateDeliverable(int $id): JsonResponse
    {
        return $this->delegate(\Nexus\Controllers\Api\AdminDeliverabilityApiController::class, 'updateDeliverable', [$id]);
    }

    /** DELETE /api/v2/admin/deliverability/{id} */
    public function deleteDeliverable(int $id): JsonResponse
    {
        $this->requireAdmin();
        $tenantId = $this->getTenantId();

        $deliverable = DB::selectOne("SELECT id, title FROM deliverables WHERE id = ? AND tenant_id = ?", [$id, $tenantId]);
        if (!$deliverable) {
            return $this->respondWithError('NOT_FOUND', 'Deliverable not found', null, 404);
        }

        DB::delete("DELETE FROM deliverable_comments WHERE deliverable_id = ? AND tenant_id = ?", [$id, $tenantId]);
        DB::delete("DELETE FROM deliverable_milestones WHERE deliverable_id = ? AND tenant_id = ?", [$id, $tenantId]);
        DB::delete("DELETE FROM deliverable_history WHERE deliverable_id = ? AND tenant_id = ?", [$id, $tenantId]);
        DB::delete("DELETE FROM deliverables WHERE id = ? AND tenant_id = ?", [$id, $tenantId]);

        return $this->respondWithData(['deleted' => true, 'id' => $id]);
    }

    /** POST /api/v2/admin/deliverability/{id}/comments */
    public function addComment(int $id): JsonResponse
    {
        return $this->delegate(\Nexus\Controllers\Api\AdminDeliverabilityApiController::class, 'addComment', [$id]);
    }

    private function delegate(string $legacyClass, string $method, array $params = []): JsonResponse
    {
        $controller = new $legacyClass();
        ob_start();
        $controller->$method(...$params);
        $output = ob_get_clean();
        $status = http_response_code();
        return response()->json(json_decode($output, true) ?: $output, $status ?: 200);
    }
}

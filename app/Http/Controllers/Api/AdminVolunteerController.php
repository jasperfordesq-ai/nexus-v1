<?php
// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

namespace App\Http\Controllers\Api;

use App\Services\VolunteerService;
use App\Services\VolunteerReminderService;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use App\Core\TenantContext;

/**
 * AdminVolunteerController -- Admin volunteer management.
 *
 * Converted from legacy delegation to direct DB/service calls.
 */
class AdminVolunteerController extends BaseApiController
{
    protected bool $isV2Api = true;

    public function __construct(
        private readonly VolunteerService $volunteerService,
        private readonly VolunteerReminderService $volunteerReminderService,
    ) {}

    private const ALLOWED_TABLES = [
        'vol_opportunities', 'vol_applications', 'vol_shifts', 'vol_shift_signups',
        'vol_organizations', 'vol_logs', 'vol_shift_checkins', 'vol_mood_checkins',
        'vol_emergency_alerts',
    ];

    private function tableExists(string $table): bool
    {
        if (!in_array($table, self::ALLOWED_TABLES, true)) {
            return false;
        }
        return Schema::hasTable($table);
    }

    private function columnExists(string $table, string $column): bool
    {
        $allowed = ['vol_opportunities' => ['created_by', 'user_id', 'is_active', 'status', 'title', 'created_at', 'tenant_id']];
        if (!isset($allowed[$table]) || !in_array($column, $allowed[$table], true)) {
            return false;
        }
        try {
            $result = DB::selectOne(
                "SELECT COUNT(*) as cnt FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ? AND COLUMN_NAME = ?",
                [$table, $column]
            );
            return ((int)($result->cnt ?? 0)) > 0;
        } catch (\Throwable $e) {
            return false;
        }
    }

    private function getOpportunityAuthorColumn(): ?string
    {
        if ($this->columnExists('vol_opportunities', 'created_by')) return 'created_by';
        if ($this->columnExists('vol_opportunities', 'user_id')) return 'user_id';
        return null;
    }

    /** GET /api/v2/admin/volunteering/opportunities */
    public function opportunities(): JsonResponse
    {
        $this->requireAdmin();
        if (!TenantContext::hasFeature('volunteering')) {
            return $this->respondWithError('FEATURE_DISABLED', 'Feature not available', null, 403);
        }
        $tenantId = $this->getTenantId();

        $perPage = $this->queryInt('per_page', 20, 1, 50);
        $search = $this->query('search');
        $status = $this->query('status');
        $cursor = $this->query('cursor');

        if (!$this->tableExists('vol_opportunities')) {
            return $this->respondWithCollection([], null, $perPage, false);
        }

        try {
            $sql = "SELECT opp.*, org.name as org_name, org.logo_url as org_logo,
                           org.status as org_status, cat.name as category_name
                    FROM vol_opportunities opp
                    LEFT JOIN vol_organizations org ON opp.organization_id = org.id
                    LEFT JOIN categories cat ON opp.category_id = cat.id
                    WHERE opp.tenant_id = ?";
            $params = [$tenantId];

            if ($status && in_array($status, ['open', 'active', 'closed', 'draft'], true)) {
                $sql .= " AND opp.status = ?";
                $params[] = $status;
            }

            if ($search) {
                $escapedSearch = addcslashes($search, '%_');
                $searchTerm = '%' . $escapedSearch . '%';
                $sql .= " AND (opp.title LIKE ? OR opp.description LIKE ?)";
                $params[] = $searchTerm;
                $params[] = $searchTerm;
            }

            if ($cursor) {
                $decoded = base64_decode($cursor, true);
                if ($decoded && is_numeric($decoded)) {
                    $sql .= " AND opp.id < ?";
                    $params[] = (int) $decoded;
                }
            }

            $sql .= " ORDER BY opp.created_at DESC, opp.id DESC LIMIT ?";
            $params[] = $perPage + 1;

            $results = DB::select($sql, $params);
            $rows = array_map(fn($r) => (array)$r, $results);

            $hasMore = count($rows) > $perPage;
            if ($hasMore) {
                array_pop($rows);
            }

            $nextCursor = null;
            if ($hasMore && !empty($rows)) {
                $lastRow = end($rows);
                $nextCursor = base64_encode((string) $lastRow['id']);
            }

            return $this->respondWithCollection($rows, $nextCursor, $perPage, $hasMore);
        } catch (\Exception $e) {
            return $this->respondWithCollection([], null, $perPage, false);
        }
    }

    /** GET /api/v2/admin/volunteering/applications */
    public function applications(): JsonResponse
    {
        $this->requireAdmin();
        if (!TenantContext::hasFeature('volunteering')) {
            return $this->respondWithError('FEATURE_DISABLED', 'Feature not available', null, 403);
        }
        $tenantId = $this->getTenantId();

        $perPage = $this->queryInt('per_page', 20, 1, 50);
        $status = $this->query('status');
        $cursor = $this->query('cursor');

        if (!$this->tableExists('vol_applications')) {
            return $this->respondWithCollection([], null, $perPage, false);
        }

        try {
            $sql = "SELECT a.*, u.first_name, u.last_name, u.email as user_email, u.avatar_url as user_avatar,
                           vo.title as opportunity_title
                    FROM vol_applications a
                    INNER JOIN vol_opportunities vo ON a.opportunity_id = vo.id
                    LEFT JOIN users u ON a.user_id = u.id
                    WHERE vo.tenant_id = ? AND a.tenant_id = ?";
            $params = [$tenantId, $tenantId];

            if ($status && in_array($status, ['pending', 'approved', 'declined', 'withdrawn'], true)) {
                $sql .= " AND a.status = ?";
                $params[] = $status;
            }

            if ($cursor) {
                $decoded = base64_decode($cursor, true);
                if ($decoded && is_numeric($decoded)) {
                    $sql .= " AND a.id < ?";
                    $params[] = (int) $decoded;
                }
            }

            $sql .= " ORDER BY a.created_at DESC, a.id DESC LIMIT ?";
            $params[] = $perPage + 1;

            $results = DB::select($sql, $params);
            $rows = array_map(fn($r) => (array)$r, $results);

            $hasMore = count($rows) > $perPage;
            if ($hasMore) {
                array_pop($rows);
            }

            $nextCursor = null;
            if ($hasMore && !empty($rows)) {
                $lastRow = end($rows);
                $nextCursor = base64_encode((string) $lastRow['id']);
            }

            return $this->respondWithCollection($rows, $nextCursor, $perPage, $hasMore);
        } catch (\Exception $e) {
            return $this->respondWithCollection([], null, $perPage, false);
        }
    }

    /** POST /api/v2/admin/volunteering/hours/{id}/verify */
    public function verifyHours(int $id): JsonResponse
    {
        $this->requireAdmin();
        if (!TenantContext::hasFeature('volunteering')) {
            return $this->respondWithError('FEATURE_DISABLED', 'Feature not available', null, 403);
        }
        $tenantId = $this->getTenantId();

        $action = $this->input('action');
        if (!$action || !in_array($action, ['approve', 'decline'], true)) {
            return $this->respondWithError('VALIDATION_ERROR', 'Action is required (approve or decline)', 'action', 400);
        }

        if (!$this->tableExists('vol_logs')) {
            return $this->respondWithError('NOT_FOUND', 'Hours log not found', null, 404);
        }

        try {
            $log = DB::selectOne(
                "SELECT id, user_id, status FROM vol_logs WHERE id = ? AND tenant_id = ?",
                [$id, $tenantId]
            );

            if (!$log) {
                return $this->respondWithError('NOT_FOUND', 'Hours log not found', null, 404);
            }

            $newStatus = $action === 'approve' ? 'approved' : 'declined';
            DB::update(
                "UPDATE vol_logs SET status = ?, updated_at = NOW() WHERE id = ? AND tenant_id = ?",
                [$newStatus, $id, $tenantId]
            );

            return $this->respondWithData([
                'id' => $id,
                'status' => $newStatus,
            ]);
        } catch (\Exception $e) {
            return $this->respondWithError('SERVER_ERROR', 'Failed to verify hours', null, 500);
        }
    }

    /** GET /api/v2/admin/volunteering */
    public function index(): JsonResponse
    {
        $this->requireAdmin();
        if (!TenantContext::hasFeature('volunteering')) {
            return $this->respondWithError('FEATURE_DISABLED', 'Feature not available', null, 403);
        }
        $tenantId = TenantContext::getId();

        $data = [
            'stats' => [
                'total_opportunities' => 0, 'active_opportunities' => 0,
                'total_applications' => 0, 'pending_applications' => 0,
                'total_hours_logged' => 0, 'active_volunteers' => 0,
            ],
            'recent_opportunities' => [],
        ];

        if (!$this->tableExists('vol_opportunities')) {
            return $this->respondWithData($data);
        }

        try {
            $row = DB::selectOne(
                "SELECT COUNT(*) as total,
                        SUM(CASE WHEN status IN ('open', 'active') AND is_active=1 THEN 1 ELSE 0 END) as active_count
                 FROM vol_opportunities WHERE tenant_id = ?",
                [$tenantId]
            );
            $data['stats']['total_opportunities'] = (int) ($row->total ?? 0);
            $data['stats']['active_opportunities'] = (int) ($row->active_count ?? 0);
        } catch (\Exception $e) {}

        if ($this->tableExists('vol_applications')) {
            try {
                $row = DB::selectOne(
                    "SELECT COUNT(*) as total, SUM(CASE WHEN va.status='pending' THEN 1 ELSE 0 END) as pending
                     FROM vol_applications va WHERE va.tenant_id = ?",
                    [$tenantId]
                );
                $data['stats']['total_applications'] = (int) ($row->total ?? 0);
                $data['stats']['pending_applications'] = (int) ($row->pending ?? 0);
            } catch (\Exception $e) {}
        }

        if ($this->tableExists('vol_logs')) {
            try {
                $row = DB::selectOne(
                    "SELECT COALESCE(SUM(vl.hours), 0) as total_hours, COUNT(DISTINCT vl.user_id) as volunteers
                     FROM vol_logs vl WHERE vl.tenant_id = ?",
                    [$tenantId]
                );
                $data['stats']['total_hours_logged'] = round((float) ($row->total_hours ?? 0), 1);
                $data['stats']['active_volunteers'] = (int) ($row->volunteers ?? 0);
            } catch (\Exception $e) {}
        }

        try {
            $authorColumn = $this->getOpportunityAuthorColumn();
            if ($authorColumn !== null) {
                $results = DB::select(
                    "SELECT vo.id, vo.title, vo.status, vo.is_active, vo.created_at,
                            CASE WHEN vo.is_active = 1 AND (vo.status = 'open' OR vo.status = 'active') THEN 'active' ELSE vo.status END as ui_status,
                            u.first_name, u.last_name
                     FROM vol_opportunities vo LEFT JOIN users u ON vo.{$authorColumn} = u.id
                     WHERE vo.tenant_id = ? ORDER BY vo.created_at DESC LIMIT 10",
                    [$tenantId]
                );
            } else {
                $results = DB::select(
                    "SELECT vo.id, vo.title, vo.status, vo.is_active, vo.created_at,
                            CASE WHEN vo.is_active = 1 AND (vo.status = 'open' OR vo.status = 'active') THEN 'active' ELSE vo.status END as ui_status,
                            NULL as first_name, NULL as last_name
                     FROM vol_opportunities vo WHERE vo.tenant_id = ? ORDER BY vo.created_at DESC LIMIT 10",
                    [$tenantId]
                );
            }
            $rows = array_map(fn($r) => (array)$r, $results);
            $data['recent_opportunities'] = array_map(static function (array $row): array {
                $row['status'] = $row['ui_status'] ?? $row['status'];
                unset($row['ui_status']);
                return $row;
            }, $rows);
        } catch (\Exception $e) {}

        return $this->respondWithData($data);
    }

    /** GET /api/v2/admin/volunteering/approvals */
    public function approvals(): JsonResponse
    {
        $this->requireAdmin();
        if (!TenantContext::hasFeature('volunteering')) {
            return $this->respondWithError('FEATURE_DISABLED', 'Feature not available', null, 403);
        }
        $tenantId = TenantContext::getId();

        if (!$this->tableExists('vol_applications')) {
            return $this->respondWithData([]);
        }

        try {
            $results = DB::select(
                "SELECT va.*, u.first_name, u.last_name, u.email, vo.title as opportunity_title
                 FROM vol_applications va
                 INNER JOIN vol_opportunities vo ON va.opportunity_id = vo.id
                 LEFT JOIN users u ON va.user_id = u.id
                 WHERE vo.tenant_id = ? AND va.tenant_id = ? AND va.status = 'pending'
                 ORDER BY va.created_at DESC LIMIT 50",
                [$tenantId, $tenantId]
            );
            return $this->respondWithData(array_map(fn($r) => (array)$r, $results));
        } catch (\Exception $e) {
            return $this->respondWithData([]);
        }
    }

    /** GET /api/v2/admin/volunteering/organizations */
    public function organizations(): JsonResponse
    {
        $this->requireAdmin();
        if (!TenantContext::hasFeature('volunteering')) {
            return $this->respondWithError('FEATURE_DISABLED', 'Feature not available', null, 403);
        }
        $tenantId = TenantContext::getId();

        if ($this->tableExists('vol_organizations')) {
            try {
                $results = DB::select(
                    "SELECT vo.id, vo.id as org_id, vo.name as org_name, vo.status, vo.created_at,
                            COALESCE((SELECT COUNT(*) FROM org_members om WHERE om.tenant_id = vo.tenant_id AND om.organization_id = vo.id AND om.status = 'active'), 0) as member_count,
                            COALESCE((SELECT COUNT(*) FROM vol_opportunities opp WHERE opp.tenant_id = vo.tenant_id AND opp.organization_id = vo.id AND opp.is_active = 1), 0) as opportunity_count,
                            COALESCE((SELECT SUM(vl.hours) FROM vol_logs vl WHERE vl.tenant_id = vo.tenant_id AND vl.organization_id = vo.id AND vl.status = 'approved'), 0) as total_hours,
                            0 as balance, 0 as total_in, 0 as total_out
                     FROM vol_organizations vo WHERE vo.tenant_id = ? ORDER BY vo.name ASC LIMIT 100",
                    [$tenantId]
                );
                return $this->respondWithData(array_map(fn($r) => (array)$r, $results));
            } catch (\Throwable $e) {}
        }

        try {
            $results = DB::select(
                "SELECT ow.*, o.name as org_name FROM org_wallets ow LEFT JOIN organizations o ON ow.org_id = o.id
                 WHERE ow.tenant_id = ? ORDER BY o.name ASC LIMIT 50",
                [$tenantId]
            );
            return $this->respondWithData(array_map(fn($r) => (array)$r, $results));
        } catch (\Exception $e) {
            return $this->respondWithData([]);
        }
    }

    /** POST /api/v2/admin/volunteering/applications/{id}/approve */
    public function approveApplication($id): JsonResponse
    {
        $this->requireAdmin();
        if (!TenantContext::hasFeature('volunteering')) {
            return $this->respondWithError('FEATURE_DISABLED', 'Feature not available', null, 403);
        }
        $tenantId = TenantContext::getId();
        $id = (int) $id;

        if (!$id || !$this->tableExists('vol_applications')) {
            return $this->respondWithError('NOT_FOUND', 'Application not found', null, 404);
        }

        try {
            $app = DB::selectOne(
                "SELECT va.id, va.status, va.user_id, vo.title as opportunity_title
                 FROM vol_applications va INNER JOIN vol_opportunities vo ON va.opportunity_id = vo.id
                 WHERE va.id = ? AND vo.tenant_id = ?",
                [$id, $tenantId]
            );

            if (!$app) {
                return $this->respondWithError('NOT_FOUND', 'Application not found', null, 404);
            }

            DB::update("UPDATE vol_applications SET status = 'approved', updated_at = NOW() WHERE id = ? AND tenant_id = ?", [$id, $tenantId]);

            return $this->respondWithData(['message' => 'Application approved']);
        } catch (\Exception $e) {
            return $this->respondWithError('SERVER_ERROR', 'Failed to approve application', null, 500);
        }
    }

    /** POST /api/v2/admin/volunteering/applications/{id}/decline */
    public function declineApplication($id): JsonResponse
    {
        $this->requireAdmin();
        if (!TenantContext::hasFeature('volunteering')) {
            return $this->respondWithError('FEATURE_DISABLED', 'Feature not available', null, 403);
        }
        $tenantId = TenantContext::getId();
        $id = (int) $id;

        if (!$id || !$this->tableExists('vol_applications')) {
            return $this->respondWithError('NOT_FOUND', 'Application not found', null, 404);
        }

        try {
            $app = DB::selectOne(
                "SELECT va.id FROM vol_applications va INNER JOIN vol_opportunities vo ON va.opportunity_id = vo.id
                 WHERE va.id = ? AND vo.tenant_id = ?",
                [$id, $tenantId]
            );

            if (!$app) {
                return $this->respondWithError('NOT_FOUND', 'Application not found', null, 404);
            }

            DB::update("UPDATE vol_applications SET status = 'declined', updated_at = NOW() WHERE id = ? AND tenant_id = ?", [$id, $tenantId]);

            return $this->respondWithData(['message' => 'Application declined']);
        } catch (\Exception $e) {
            return $this->respondWithError('SERVER_ERROR', 'Failed to decline application', null, 500);
        }
    }

    /** POST /api/v2/admin/volunteering/send-shift-reminders -- delegates to service (email sending) */
    public function sendShiftReminders(): JsonResponse
    {
        $this->requireSuperAdmin();

        $tenantId = TenantContext::getId();

        // Send reminders for all active opportunities in this tenant
        $opportunityIds = DB::table('vol_opportunities')
            ->where('tenant_id', $tenantId)
            ->where('is_active', 1)
            ->whereIn('status', ['open', 'active'])
            ->pluck('id');

        $sent = 0;
        foreach ($opportunityIds as $oppId) {
            $sent += VolunteerReminderService::sendReminders($tenantId, (int) $oppId);
        }

        return $this->respondWithData(['reminders_sent' => $sent]);
    }

}

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
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;
use App\Core\TenantContext;
use App\I18n\LocaleContext;
use App\Models\Notification;

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
            return $this->respondWithError('FEATURE_DISABLED', __('api.service_unavailable'), null, 403);
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
            return $this->respondWithError('FEATURE_DISABLED', __('api.service_unavailable'), null, 403);
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

    /** GET /api/v2/admin/volunteering/hours */
    public function listHours(): JsonResponse
    {
        $this->requireAdmin();
        if (!TenantContext::hasFeature('volunteering')) {
            return $this->respondWithError('FEATURE_DISABLED', __('api.service_unavailable'), null, 403);
        }
        $tenantId = $this->getTenantId();

        $perPage = $this->queryInt('per_page', 20, 1, 50);
        $status = $this->query('status');
        $cursor = $this->query('cursor');

        if (!$this->tableExists('vol_logs')) {
            return $this->respondWithCollection([], null, $perPage, false);
        }

        try {
            // Stats summary — paid/paid_amount columns may not exist
            $hasPaidCol = Schema::hasColumn('vol_logs', 'paid');
            $paidSelect = $hasPaidCol
                ? "COALESCE(SUM(CASE WHEN status = 'approved' AND paid = 1 THEN paid_amount ELSE 0 END), 0)"
                : '0';

            $statsRow = DB::selectOne(
                "SELECT
                    COALESCE(SUM(hours), 0) as total_hours,
                    COALESCE(SUM(CASE WHEN status = 'approved' THEN hours ELSE 0 END), 0) as approved_hours,
                    COALESCE(SUM(CASE WHEN status = 'pending' THEN hours ELSE 0 END), 0) as pending_hours,
                    {$paidSelect} as total_paid
                 FROM vol_logs WHERE tenant_id = ?",
                [$tenantId]
            );

            $paidCols = $hasPaidCol ? ', vl.paid, vl.paid_amount' : ', 0 as paid, 0 as paid_amount';
            $sql = "SELECT vl.id, vl.hours, vl.status, vl.created_at{$paidCols},
                           u.first_name, u.last_name,
                           vo.name as org_name
                    FROM vol_logs vl
                    LEFT JOIN users u ON vl.user_id = u.id
                    LEFT JOIN vol_organizations vo ON vl.organization_id = vo.id
                    WHERE vl.tenant_id = ?";
            $params = [$tenantId];

            if ($status && in_array($status, ['pending', 'approved', 'declined'], true)) {
                $sql .= " AND vl.status = ?";
                $params[] = $status;
            }

            if ($cursor) {
                $decoded = base64_decode($cursor, true);
                if ($decoded && is_numeric($decoded)) {
                    $sql .= " AND vl.id < ?";
                    $params[] = (int) $decoded;
                }
            }

            $sql .= " ORDER BY vl.created_at DESC, vl.id DESC LIMIT ?";
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

            $stats = [
                'total_hours' => round((float) ($statsRow->total_hours ?? 0), 1),
                'approved_hours' => round((float) ($statsRow->approved_hours ?? 0), 1),
                'pending_hours' => round((float) ($statsRow->pending_hours ?? 0), 1),
                'total_paid' => round((float) ($statsRow->total_paid ?? 0), 2),
            ];
            $meta = [
                'per_page' => $perPage,
                'has_more' => $hasMore,
                'next_cursor' => $nextCursor,
            ];

            return $this->respondWithData([
                'items' => $rows,
                'stats' => $stats,
                'meta' => $meta,
            ], $meta);
        } catch (\Exception $e) {
            Log::error("AdminVolunteerController::listHours error: " . $e->getMessage());
            return $this->respondWithData([
                'items' => [],
                'stats' => ['total_hours' => 0, 'approved_hours' => 0, 'pending_hours' => 0, 'total_paid' => 0],
                'meta' => ['per_page' => $perPage, 'has_more' => false, 'next_cursor' => null],
            ], ['per_page' => $perPage, 'has_more' => false, 'next_cursor' => null]);
        }
    }

    /** POST /api/v2/admin/volunteering/hours/{id}/verify */
    public function verifyHours(int $id): JsonResponse
    {
        $this->requireAdmin();
        if (!TenantContext::hasFeature('volunteering')) {
            return $this->respondWithError('FEATURE_DISABLED', __('api.service_unavailable'), null, 403);
        }
        $tenantId = $this->getTenantId();

        $action = $this->input('action');
        if (!$action || !in_array($action, ['approve', 'decline'], true)) {
            return $this->respondWithError('VALIDATION_ERROR', __('api.decision_required'), 'action', 400);
        }

        if (!$this->tableExists('vol_logs')) {
            return $this->respondWithError('NOT_FOUND', __('api.log_not_found'), null, 404);
        }

        try {
            $log = DB::selectOne(
                "SELECT id, user_id, status FROM vol_logs WHERE id = ? AND tenant_id = ?",
                [$id, $tenantId]
            );

            if (!$log) {
                return $this->respondWithError('NOT_FOUND', __('api.log_not_found'), null, 404);
            }

            if ($log->status !== 'pending') {
                return $this->respondWithError('VALIDATION_ERROR', __('api.only_pending_can_be_verified'), null, 422);
            }

            $newStatus = $action === 'approve' ? 'approved' : 'declined';
            DB::update(
                "UPDATE vol_logs SET status = ?, updated_at = NOW() WHERE id = ? AND tenant_id = ?",
                [$newStatus, $id, $tenantId]
            );

            // Send hours approved/declined email notification
            try {
                $volDetail = DB::selectOne(
                    "SELECT u.email, u.first_name, u.name, u.preferred_language, vl.hours, vo.title as opportunity_title
                     FROM vol_logs vl
                     LEFT JOIN users u ON vl.user_id = u.id AND u.tenant_id = ?
                     LEFT JOIN vol_opportunities vo ON vl.opportunity_id = vo.id
                     WHERE vl.id = ? AND vl.tenant_id = ?",
                    [$tenantId, $id, $tenantId]
                );

                if ($volDetail && !empty($volDetail->email)) {
                    LocaleContext::withLocale($volDetail, function () use ($volDetail, $newStatus) {
                        $firstName = $volDetail->first_name ?? $volDetail->name ?? __('emails.common.fallback_name');
                        $oppTitle = htmlspecialchars($volDetail->opportunity_title ?? 'your volunteering', ENT_QUOTES, 'UTF-8');
                        $url = TenantContext::getFrontendUrl() . TenantContext::getSlugPrefix() . '/volunteering';
                        $emailKey = $newStatus === 'approved' ? 'hours_approved' : 'hours_declined';

                        $html = \App\Core\EmailTemplateBuilder::make()
                            ->theme($newStatus === 'approved' ? 'success' : 'brand')
                            ->title(__("emails.volunteer_approval.{$emailKey}_title"))
                            ->previewText(__("emails.volunteer_approval.{$emailKey}_preview"))
                            ->greeting($firstName)
                            ->paragraph(__("emails.volunteer_approval.{$emailKey}_body", ['opportunity' => $oppTitle]))
                            ->button(__("emails.volunteer_approval.{$emailKey}_cta"), $url)
                            ->render();

                        \App\Core\Mailer::forCurrentTenant()->send(
                            $volDetail->email,
                            __("emails.volunteer_approval.{$emailKey}_subject"),
                            $html
                        );
                    });
                }
            } catch (\Throwable $emailEx) {
                Log::warning('AdminVolunteerController: hours status email failed: ' . $emailEx->getMessage());
            }

            return $this->respondWithData([
                'id' => $id,
                'status' => $newStatus,
            ]);
        } catch (\Exception $e) {
            return $this->respondWithError('SERVER_ERROR', __('api.update_failed', ['resource' => 'hours verification']), null, 500);
        }
    }

    /** GET /api/v2/admin/volunteering */
    public function index(): JsonResponse
    {
        $this->requireAdmin();
        if (!TenantContext::hasFeature('volunteering')) {
            return $this->respondWithError('FEATURE_DISABLED', __('api.service_unavailable'), null, 403);
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
        } catch (\Exception $e) { Log::warning('Stats query failed in ' . __METHOD__, ['error' => $e->getMessage()]); }

        if ($this->tableExists('vol_applications')) {
            try {
                $row = DB::selectOne(
                    "SELECT COUNT(*) as total, SUM(CASE WHEN va.status='pending' THEN 1 ELSE 0 END) as pending
                     FROM vol_applications va WHERE va.tenant_id = ?",
                    [$tenantId]
                );
                $data['stats']['total_applications'] = (int) ($row->total ?? 0);
                $data['stats']['pending_applications'] = (int) ($row->pending ?? 0);
            } catch (\Exception $e) { Log::warning('Stats query failed in ' . __METHOD__, ['error' => $e->getMessage()]); }
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
            } catch (\Exception $e) { Log::warning('Stats query failed in ' . __METHOD__, ['error' => $e->getMessage()]); }
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
        } catch (\Exception $e) { Log::warning('Stats query failed in ' . __METHOD__, ['error' => $e->getMessage()]); }

        return $this->respondWithData($data);
    }

    /** GET /api/v2/admin/volunteering/approvals */
    public function approvals(): JsonResponse
    {
        $this->requireAdmin();
        if (!TenantContext::hasFeature('volunteering')) {
            return $this->respondWithError('FEATURE_DISABLED', __('api.service_unavailable'), null, 403);
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
            return $this->respondWithError('FEATURE_DISABLED', __('api.service_unavailable'), null, 403);
        }
        $tenantId = TenantContext::getId();

        if ($this->tableExists('vol_organizations')) {
            try {
                // Single query: LEFT JOIN grouped aggregates per org instead of per-row correlated subqueries
                $results = DB::select(
                    "SELECT vo.id, vo.id as org_id, vo.name as org_name, vo.description,
                            vo.contact_email, vo.website, vo.org_type, vo.meeting_schedule,
                            vo.status, vo.created_at, vo.balance,
                            COALESCE(mc.member_count, 0) as member_count,
                            COALESCE(oc.opportunity_count, 0) as opportunity_count,
                            COALESCE(hc.total_hours, 0) as total_hours,
                            COALESCE(tx.total_in, 0) as total_in,
                            COALESCE(tx.total_out, 0) as total_out
                     FROM vol_organizations vo
                     LEFT JOIN (
                         SELECT organization_id, COUNT(*) as member_count
                         FROM org_members
                         WHERE tenant_id = ? AND status = 'active'
                         GROUP BY organization_id
                     ) mc ON mc.organization_id = vo.id
                     LEFT JOIN (
                         SELECT organization_id, COUNT(*) as opportunity_count
                         FROM vol_opportunities
                         WHERE tenant_id = ? AND is_active = 1
                         GROUP BY organization_id
                     ) oc ON oc.organization_id = vo.id
                     LEFT JOIN (
                         SELECT organization_id, SUM(hours) as total_hours
                         FROM vol_logs
                         WHERE tenant_id = ? AND status = 'approved'
                         GROUP BY organization_id
                     ) hc ON hc.organization_id = vo.id
                     LEFT JOIN (
                         SELECT vol_organization_id,
                                SUM(CASE WHEN amount > 0 THEN amount ELSE 0 END) as total_in,
                                SUM(CASE WHEN amount < 0 THEN ABS(amount) ELSE 0 END) as total_out
                         FROM vol_org_transactions
                         WHERE tenant_id = ?
                         GROUP BY vol_organization_id
                     ) tx ON tx.vol_organization_id = vo.id
                     WHERE vo.tenant_id = ?
                     ORDER BY vo.name ASC
                     LIMIT 100",
                    [$tenantId, $tenantId, $tenantId, $tenantId, $tenantId]
                );
                return $this->respondWithData(array_map(fn($r) => (array)$r, $results));
            } catch (\Throwable $e) { Log::warning('Stats query failed in ' . __METHOD__, ['error' => $e->getMessage()]); }
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

    private function ensureFeature(): void
    {
        if (!TenantContext::hasFeature('volunteering')) {
            throw new \Illuminate\Http\Exceptions\HttpResponseException(
                $this->respondWithError('FEATURE_DISABLED', __('api.volunteering_feature_disabled'), null, 403)
            );
        }
    }

    /** POST /api/v2/admin/volunteering/organizations */
    public function createOrganization(): JsonResponse
    {
        $this->requireAdmin();
        if (!TenantContext::hasFeature('volunteering')) {
            return $this->respondWithError('FEATURE_DISABLED', __('api.service_unavailable'), null, 403);
        }

        $orgId = $this->volunteerService->createOrganization($this->getUserId(), [
            'name' => trim((string) $this->input('name', '')),
            'description' => trim((string) $this->input('description', '')),
            'contact_email' => trim((string) $this->input('contact_email', '')),
            'website' => trim((string) $this->input('website', '')),
        ]);

        if ($orgId === null) {
            return $this->respondWithErrors($this->volunteerService->getErrors(), 422);
        }

        $tenantId = $this->getTenantId();
        $updates = ['status = ?'];
        $params = ['active'];
        foreach (['org_type', 'meeting_schedule'] as $field) {
            if (Schema::hasColumn('vol_organizations', $field) && $this->input($field) !== null) {
                $updates[] = "{$field} = ?";
                $params[] = trim((string) $this->input($field));
            }
        }
        $params[] = $orgId;
        $params[] = $tenantId;

        DB::update(
            "UPDATE vol_organizations SET " . implode(', ', $updates) . ", updated_at = NOW() WHERE id = ? AND tenant_id = ?",
            $params
        );

        return $this->respondWithData($this->volunteerService->getOrganizationById($orgId, true), null, 201);
    }

    /** PUT /api/v2/admin/volunteering/organizations/{id} */
    public function updateOrganization(int $id): JsonResponse
    {
        $this->requireAdmin();
        if (!TenantContext::hasFeature('volunteering')) {
            return $this->respondWithError('FEATURE_DISABLED', __('api.service_unavailable'), null, 403);
        }

        $tenantId = $this->getTenantId();
        if (!DB::selectOne("SELECT id FROM vol_organizations WHERE id = ? AND tenant_id = ?", [$id, $tenantId])) {
            return $this->respondWithError('NOT_FOUND', __('api.organization_not_found'), null, 404);
        }

        $updates = [];
        $params = [];
        foreach (['name', 'description', 'contact_email', 'website'] as $field) {
            if ($this->input($field) !== null) {
                $value = trim((string) $this->input($field));
                if ($field === 'contact_email' && $value !== '' && !filter_var($value, FILTER_VALIDATE_EMAIL)) {
                    return $this->respondWithError('VALIDATION_ERROR', __('api.valid_email_address_required'), $field, 422);
                }
                if ($field === 'website' && $value !== '' && !filter_var($value, FILTER_VALIDATE_URL)) {
                    return $this->respondWithError('VALIDATION_ERROR', __('api.valid_url_required'), $field, 422);
                }
                $updates[] = "{$field} = ?";
                $params[] = $value === '' ? null : $value;
            }
        }

        foreach (['org_type', 'meeting_schedule'] as $field) {
            if (Schema::hasColumn('vol_organizations', $field) && $this->input($field) !== null) {
                $updates[] = "{$field} = ?";
                $value = trim((string) $this->input($field));
                $params[] = $value === '' ? null : $value;
            }
        }

        if (empty($updates)) {
            return $this->respondWithError('VALIDATION_ERROR', __('api.no_fields_to_update'), null, 400);
        }

        $params[] = $id;
        $params[] = $tenantId;
        DB::update(
            "UPDATE vol_organizations SET " . implode(', ', $updates) . ", updated_at = NOW() WHERE id = ? AND tenant_id = ?",
            $params
        );

        return $this->respondWithData($this->volunteerService->getOrganizationById($id, true));
    }

    /** GET /api/v2/admin/volunteering/organizations/{id}/members */
    public function organizationMembers(int $id): JsonResponse
    {
        $this->requireAdmin();
        if (!TenantContext::hasFeature('volunteering')) {
            return $this->respondWithError('FEATURE_DISABLED', __('api.service_unavailable'), null, 403);
        }

        $tenantId = $this->getTenantId();
        if (!DB::selectOne("SELECT id FROM vol_organizations WHERE id = ? AND tenant_id = ?", [$id, $tenantId])) {
            return $this->respondWithError('NOT_FOUND', __('api.organization_not_found'), null, 404);
        }

        $rows = DB::select(
            "SELECT om.id, om.user_id,
                    COALESCE(u.first_name, SUBSTRING_INDEX(u.name, ' ', 1), '') as first_name,
                    COALESCE(u.last_name, TRIM(SUBSTRING(u.name, LENGTH(SUBSTRING_INDEX(u.name, ' ', 1)) + 1)), '') as last_name,
                    om.role,
                    COALESCE(SUM(CASE WHEN vl.status = 'approved' THEN vl.hours ELSE 0 END), 0) as total_hours
             FROM org_members om
             INNER JOIN users u ON u.id = om.user_id AND u.tenant_id = om.tenant_id
             LEFT JOIN vol_logs vl ON vl.user_id = om.user_id
                AND vl.organization_id = om.organization_id
                AND vl.tenant_id = om.tenant_id
             WHERE om.organization_id = ? AND om.tenant_id = ? AND om.status = 'active'
             GROUP BY om.id, om.user_id, u.first_name, u.last_name, u.name, om.role
             ORDER BY om.id DESC
             LIMIT 100",
            [$id, $tenantId]
        );

        return $this->respondWithData(array_map(fn ($row) => (array) $row, $rows));
    }

    /** POST /api/v2/admin/volunteering/applications/{id}/approve */
    public function approveApplication($id): JsonResponse
    {
        $this->requireAdmin();
        if (!TenantContext::hasFeature('volunteering')) {
            return $this->respondWithError('FEATURE_DISABLED', __('api.service_unavailable'), null, 403);
        }
        $tenantId = TenantContext::getId();
        $id = (int) $id;

        if (!$id || !$this->tableExists('vol_applications')) {
            return $this->respondWithError('NOT_FOUND', __('api.not_found', ['model' => 'Application']), null, 404);
        }

        try {
            $app = DB::selectOne(
                "SELECT va.id, va.status, va.user_id, va.shift_id, vo.title as opportunity_title
                 FROM vol_applications va INNER JOIN vol_opportunities vo ON va.opportunity_id = vo.id
                 WHERE va.id = ? AND va.tenant_id = ? AND vo.tenant_id = ?",
                [$id, $tenantId, $tenantId]
            );

            if (!$app) {
                return $this->respondWithError('NOT_FOUND', __('api.not_found', ['model' => 'Application']), null, 404);
            }

            if ($app->status !== 'pending') {
                return $this->respondWithError('VALIDATION_ERROR', __('api.only_pending_can_be_approved', ['status' => 'pending']), null, 422);
            }

            DB::transaction(function () use ($id, $tenantId, $app) {
                if (!empty($app->shift_id)) {
                    $shift = DB::selectOne(
                        "SELECT id, capacity FROM vol_shifts WHERE id = ? AND tenant_id = ? FOR UPDATE",
                        [(int) $app->shift_id, $tenantId]
                    );

                    if (!$shift) {
                        throw new \DomainException(__('api.volunteer_shift_not_found'));
                    }

                    if (!empty($shift->capacity)) {
                        $approvedCount = (int) DB::selectOne(
                            "SELECT COUNT(*) as cnt FROM vol_applications WHERE shift_id = ? AND status = 'approved' AND tenant_id = ?",
                            [(int) $app->shift_id, $tenantId]
                        )->cnt;

                        if ($approvedCount >= (int) $shift->capacity) {
                            throw new \DomainException(__('api.volunteer_shift_at_capacity'));
                        }
                    }
                }

                DB::update(
                    "UPDATE vol_applications SET status = 'approved', updated_at = NOW() WHERE id = ? AND tenant_id = ?",
                    [$id, $tenantId]
                );
            });

            // Load applicant once with preferred_language for both notifications and email
            $applicant = DB::table('users')
                ->where('id', (int) $app->user_id)
                ->where('tenant_id', $tenantId)
                ->select(['email', 'first_name', 'name', 'preferred_language'])
                ->first();

            // Notify the applicant (in-app bell)
            try {
                $applicantId = (int) $app->user_id;

                if ($applicantId) {
                    LocaleContext::withLocale($applicant, function () use ($applicantId, $tenantId) {
                        Notification::createNotification(
                            $applicantId,
                            __('api_controllers_3.admin_bells.volunteer_approved'),
                            '/volunteering',
                            'moderation',
                            true,
                            $tenantId
                        );
                    });
                }
            } catch (\Throwable $e) {
                Log::warning("AdminVolunteerController::approveApplication notification failed: " . $e->getMessage());
            }

            // Send approval email to the applicant
            try {
                $oppTitle = htmlspecialchars($app->opportunity_title ?? 'the opportunity', ENT_QUOTES, 'UTF-8');

                if ($applicant && !empty($applicant->email)) {
                    LocaleContext::withLocale($applicant, function () use ($applicant, $oppTitle) {
                        $firstName = $applicant->first_name ?? $applicant->name ?? __('emails.common.fallback_name');
                        $url = TenantContext::getFrontendUrl() . TenantContext::getSlugPrefix() . '/volunteering';

                        $html = \App\Core\EmailTemplateBuilder::make()
                            ->theme('success')
                            ->title(__('emails.volunteer_approval.application_approved_title'))
                            ->previewText(__('emails.volunteer_approval.application_approved_preview', ['opportunity' => $oppTitle]))
                            ->greeting($firstName)
                            ->paragraph(__('emails.volunteer_approval.application_approved_body', ['opportunity' => $oppTitle]))
                            ->button(__('emails.volunteer_approval.application_approved_cta'), $url)
                            ->render();

                        \App\Core\Mailer::forCurrentTenant()->send(
                            $applicant->email,
                            __('emails.volunteer_approval.application_approved_subject'),
                            $html
                        );
                    });
                }
            } catch (\Throwable $emailEx) {
                Log::warning('AdminVolunteerController: approval email failed: ' . $emailEx->getMessage());
            }

            return $this->respondWithData(['message' => __('api_controllers_1.admin_volunteer.application_approved')]);
        } catch (\DomainException $e) {
            return $this->respondWithError('VALIDATION_ERROR', $e->getMessage(), 'shift_id', 422);
        } catch (\Exception $e) {
            return $this->respondWithError('SERVER_ERROR', __('api.approve_failed', ['resource' => 'application']), null, 500);
        }
    }

    /** POST /api/v2/admin/volunteering/applications/{id}/decline */
    public function declineApplication($id): JsonResponse
    {
        $this->requireAdmin();
        if (!TenantContext::hasFeature('volunteering')) {
            return $this->respondWithError('FEATURE_DISABLED', __('api.service_unavailable'), null, 403);
        }
        $tenantId = TenantContext::getId();
        $id = (int) $id;

        if (!$id || !$this->tableExists('vol_applications')) {
            return $this->respondWithError('NOT_FOUND', __('api.not_found', ['model' => 'Application']), null, 404);
        }

        try {
            $app = DB::selectOne(
                "SELECT va.id, va.status, va.user_id FROM vol_applications va INNER JOIN vol_opportunities vo ON va.opportunity_id = vo.id
                 WHERE va.id = ? AND va.tenant_id = ? AND vo.tenant_id = ?",
                [$id, $tenantId, $tenantId]
            );

            if (!$app) {
                return $this->respondWithError('NOT_FOUND', __('api.not_found', ['model' => 'Application']), null, 404);
            }

            if ($app->status !== 'pending') {
                return $this->respondWithError('VALIDATION_ERROR', __('api.only_pending_can_be_rejected', ['status' => 'pending']), null, 422);
            }

            DB::update("UPDATE vol_applications SET status = 'declined', updated_at = NOW() WHERE id = ? AND tenant_id = ?", [$id, $tenantId]);

            // Notify the applicant
            try {
                $applicantId = (int) $app->user_id;

                if ($applicantId) {
                    $applicant = DB::table('users')
                        ->where('id', $applicantId)
                        ->where('tenant_id', $tenantId)
                        ->select(['preferred_language'])
                        ->first();
                    LocaleContext::withLocale($applicant, function () use ($applicantId, $tenantId) {
                        Notification::createNotification(
                            $applicantId,
                            __('api_controllers_3.admin_bells.volunteer_declined'),
                            null,
                            'moderation',
                            true,
                            $tenantId
                        );
                    });
                }
            } catch (\Throwable $e) {
                Log::warning("AdminVolunteerController::declineApplication notification failed: " . $e->getMessage());
            }

            return $this->respondWithData(['message' => __('api_controllers_1.admin_volunteer.application_declined')]);
        } catch (\Exception $e) {
            return $this->respondWithError('SERVER_ERROR', __('api.reject_failed', ['resource' => 'application']), null, 500);
        }
    }

    /** POST /api/v2/admin/volunteering/send-shift-reminders -- delegates to service (email sending) */
    public function sendShiftReminders(): JsonResponse
    {
        $this->requireSuperAdmin();
        $this->ensureFeature();

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

    /**
     * Admin: adjust a volunteer organization's wallet balance (top-up or deduct).
     */
    public function adjustOrgWallet(int $id): JsonResponse
    {
        $this->requireSuperAdmin();
        $this->ensureFeature();
        // Defence-in-depth: even super-admin adjustments should be rate-limited
        // so an accidental loop or compromised session cannot drain/inflate a
        // vol org wallet in seconds.
        $this->rateLimit('vol_org_admin_adjust', 20, 60);

        $amount = (float) $this->input('amount', 0);
        $reason = trim((string) $this->input('reason', ''));

        if ($amount == 0) {
            return $this->respondWithError('VALIDATION_ERROR', __('api_controllers_1.admin_volunteer.amount_cannot_be_zero'), 'amount', 400);
        }
        if (empty($reason)) {
            return $this->respondWithError('VALIDATION_ERROR', __('api_controllers_1.admin_volunteer.reason_required'), 'reason', 400);
        }

        $adminId = $this->getUserId();
        $result = \App\Services\VolOrgWalletService::adminAdjustment($id, $amount, $adminId, $reason);

        if (!$result['success']) {
            return $this->respondWithError('VALIDATION_ERROR', $result['message'], null, 400);
        }

        return $this->respondWithData([
            'message' => $result['message'],
            'new_balance' => $result['new_balance'],
        ]);
    }

    /** PUT /api/v2/admin/volunteering/organizations/{id}/status */
    public function updateOrgStatus(int $id): JsonResponse
    {
        $this->requireAdmin();
        if (!TenantContext::hasFeature('volunteering')) {
            return $this->respondWithError('FEATURE_DISABLED', __('api.service_unavailable'), null, 403);
        }
        $tenantId = TenantContext::getId();

        $status = $this->input('status');
        if (!$status || !in_array($status, ['active', 'suspended'], true)) {
            return $this->respondWithError('VALIDATION_ERROR', __('api_controllers_1.admin_volunteer.status_must_be_active_or_suspended'), 'status', 400);
        }

        if (!$this->tableExists('vol_organizations')) {
            return $this->respondWithError('NOT_FOUND', __('api_controllers_1.admin_volunteer.organization_not_found'), null, 404);
        }

        try {
            $org = DB::selectOne(
                "SELECT id, status FROM vol_organizations WHERE id = ? AND tenant_id = ?",
                [$id, $tenantId]
            );

            if (!$org) {
                return $this->respondWithError('NOT_FOUND', __('api_controllers_1.admin_volunteer.organization_not_found'), null, 404);
            }

            DB::update(
                "UPDATE vol_organizations SET status = ?, updated_at = NOW() WHERE id = ? AND tenant_id = ?",
                [$status, $id, $tenantId]
            );

            return $this->respondWithData([
                'id' => $id,
                'status' => $status,
                'message' => __('api_controllers_1.admin_volunteer.org_status_updated', ['status' => $status]),
            ]);
        } catch (\Exception $e) {
            return $this->respondWithError('SERVER_ERROR', __('api_controllers_1.admin_volunteer.org_status_update_failed'), null, 500);
        }
    }

    /**
     * Admin: get transaction history for a volunteer organization's wallet.
     */
    public function orgWalletTransactions(int $id): JsonResponse
    {
        $this->requireSuperAdmin();
        $this->ensureFeature();

        $filters = [
            'limit' => $this->queryInt('per_page', 20, 1, 50),
            'cursor' => $this->query('cursor'),
        ];

        $result = \App\Services\VolOrgWalletService::getTransactions($id, $filters);
        return $this->respondWithCollection($result['items'], $result['cursor'], $filters['limit'], $result['has_more']);
    }

    // ========================================
    // TRENDS & ANALYTICS
    // ========================================

    /** GET /api/v2/admin/volunteering/trends */
    public function trends(): JsonResponse
    {
        $this->requireAdmin();
        $this->ensureFeature();
        $tenantId = TenantContext::getId();
        $period = $this->query('period', 'week');
        $format = $period === 'month' ? '%Y-%m' : '%Y-%u';
        $labelFormat = $period === 'month' ? '%Y-%m' : '%Y-W%u';
        $days = $period === 'month' ? 365 : 90;

        try {
            $hoursByPeriod = DB::select("
                SELECT DATE_FORMAT(created_at, ?) as period_key,
                       DATE_FORMAT(created_at, ?) as period_label,
                       ROUND(COALESCE(SUM(hours), 0), 1) as hours,
                       COUNT(*) as count
                FROM vol_logs
                WHERE tenant_id = ? AND created_at >= DATE_SUB(NOW(), INTERVAL ? DAY)
                GROUP BY period_key, period_label ORDER BY period_key ASC
            ", [$format, $labelFormat, $tenantId, $days]);

            $applicationsByPeriod = DB::select("
                SELECT DATE_FORMAT(va.created_at, ?) as period_key,
                       DATE_FORMAT(va.created_at, ?) as period_label,
                       COUNT(*) as count,
                       SUM(CASE WHEN va.status = 'approved' THEN 1 ELSE 0 END) as approved
                FROM vol_applications va
                JOIN vol_opportunities vo ON va.opportunity_id = vo.id
                WHERE va.tenant_id = ? AND va.created_at >= DATE_SUB(NOW(), INTERVAL ? DAY)
                GROUP BY period_key, period_label ORDER BY period_key ASC
            ", [$format, $labelFormat, $tenantId, $days]);

            $volunteersByPeriod = DB::select("
                SELECT DATE_FORMAT(created_at, ?) as period_key,
                       DATE_FORMAT(created_at, ?) as period_label,
                       COUNT(DISTINCT user_id) as count
                FROM vol_logs
                WHERE tenant_id = ? AND created_at >= DATE_SUB(NOW(), INTERVAL ? DAY)
                GROUP BY period_key, period_label ORDER BY period_key ASC
            ", [$format, $labelFormat, $tenantId, $days]);

            return $this->respondWithData([
                'period' => $period,
                'hours_by_period' => array_map(fn($r) => ['period' => $r->period_label, 'hours' => (float) $r->hours, 'count' => (int) $r->count], $hoursByPeriod),
                'applications_by_period' => array_map(fn($r) => ['period' => $r->period_label, 'count' => (int) $r->count, 'approved' => (int) $r->approved], $applicationsByPeriod),
                'volunteers_by_period' => array_map(fn($r) => ['period' => $r->period_label, 'count' => (int) $r->count], $volunteersByPeriod),
            ]);
        } catch (\Exception $e) {
            Log::error("AdminVolunteerController::trends error: " . $e->getMessage());
            return $this->respondWithData(['period' => $period, 'hours_by_period' => [], 'applications_by_period' => [], 'volunteers_by_period' => []]);
        }
    }

    /** GET /api/v2/admin/volunteering/reminder-logs */
    public function reminderLogs(): JsonResponse
    {
        $this->requireAdmin();
        $this->ensureFeature();
        $tenantId = TenantContext::getId();
        $perPage = $this->queryInt('per_page', 20, 1, 50);
        $cursor = $this->query('cursor');
        $type = $this->query('type');
        $channel = $this->query('channel');

        try {
            // Stats
            $stats = DB::selectOne("
                SELECT COUNT(*) as total_sent,
                       SUM(CASE WHEN channel = 'email' THEN 1 ELSE 0 END) as email_count,
                       SUM(CASE WHEN channel = 'push' THEN 1 ELSE 0 END) as push_count,
                       SUM(CASE WHEN channel = 'sms' THEN 1 ELSE 0 END) as sms_count
                FROM vol_reminders_sent WHERE tenant_id = ?
            ", [$tenantId]);

            $typeStats = DB::select("
                SELECT reminder_type, COUNT(*) as count
                FROM vol_reminders_sent WHERE tenant_id = ?
                GROUP BY reminder_type
            ", [$tenantId]);

            // Paginated logs
            $sql = "SELECT vrs.*, u.name as user_name, u.avatar_url
                    FROM vol_reminders_sent vrs
                    LEFT JOIN users u ON vrs.user_id = u.id
                    WHERE vrs.tenant_id = ?";
            $params = [$tenantId];

            if ($type) { $sql .= " AND vrs.reminder_type = ?"; $params[] = $type; }
            if ($channel) { $sql .= " AND vrs.channel = ?"; $params[] = $channel; }
            if ($cursor) {
                $decoded = base64_decode($cursor, true);
                if ($decoded && is_numeric($decoded)) { $sql .= " AND vrs.id < ?"; $params[] = (int) $decoded; }
            }

            $sql .= " ORDER BY vrs.sent_at DESC, vrs.id DESC LIMIT ?";
            $params[] = $perPage + 1;

            $rows = DB::select($sql, $params);
            $hasMore = count($rows) > $perPage;
            if ($hasMore) array_pop($rows);

            $items = array_map(fn($r) => [
                'id' => (int) $r->id,
                'user_id' => (int) $r->user_id,
                'user_name' => $r->user_name ?? 'Unknown',
                'user_avatar' => $r->avatar_url,
                'reminder_type' => $r->reminder_type,
                'channel' => $r->channel,
                'reference_id' => $r->reference_id ? (int) $r->reference_id : null,
                'sent_at' => $r->sent_at,
            ], $rows);

            $lastItem = end($items);
            $nextCursor = ($hasMore && $lastItem) ? base64_encode((string) $lastItem['id']) : null;

            $byType = [];
            foreach ($typeStats as $ts) { $byType[$ts->reminder_type] = (int) $ts->count; }

            return $this->respondWithData($items, [
                'stats' => [
                    'total_sent' => (int) ($stats->total_sent ?? 0),
                    'by_channel' => ['email' => (int) ($stats->email_count ?? 0), 'push' => (int) ($stats->push_count ?? 0), 'sms' => (int) ($stats->sms_count ?? 0)],
                    'by_type' => $byType,
                ],
                'per_page' => $perPage,
                'has_more' => $hasMore,
                'cursor' => $nextCursor,
            ]);
        } catch (\Exception $e) {
            Log::error("AdminVolunteerController::reminderLogs error: " . $e->getMessage());
            return $this->respondWithData([], [
                'stats' => ['total_sent' => 0, 'by_channel' => ['email' => 0, 'push' => 0, 'sms' => 0], 'by_type' => []],
                'per_page' => $perPage,
                'has_more' => false,
                'cursor' => null,
            ]);
        }
    }

    /** POST /api/v2/admin/volunteering/custom-fields/reorder */
    public function reorderCustomFields(): JsonResponse
    {
        $this->requireAdmin();
        $this->ensureFeature();
        $tenantId = TenantContext::getId();
        $fieldIds = $this->input('field_ids');

        if (!is_array($fieldIds) || empty($fieldIds)) {
            return $this->respondWithError('VALIDATION_ERROR', __('api.field_ids_required'), 'field_ids', 400);
        }
        if (count($fieldIds) > 100) {
            return $this->respondWithError('VALIDATION_ERROR', __('api.too_many_fields', ['max' => 100]), 'field_ids', 400);
        }

        try {
            $updated = 0;
            foreach ($fieldIds as $order => $fieldId) {
                $affected = DB::update(
                    "UPDATE vol_custom_fields SET display_order = ? WHERE id = ? AND tenant_id = ?",
                    [$order, (int) $fieldId, $tenantId]
                );
                $updated += $affected;
            }
            return $this->respondWithData(['success' => true, 'updated_count' => $updated]);
        } catch (\Exception $e) {
            Log::error("AdminVolunteerController::reorderCustomFields error: " . $e->getMessage());
            return $this->respondWithError('SERVER_ERROR', __('api.failed_reorder_fields'), null, 500);
        }
    }

    /** GET /api/v2/admin/volunteering/giving-days/{id}/donors */
    public function givingDayDonors(int $id): JsonResponse
    {
        $this->requireAdmin();
        $this->ensureFeature();
        $tenantId = TenantContext::getId();

        // Verify giving day belongs to this tenant
        $givingDay = DB::selectOne("SELECT id FROM vol_giving_days WHERE id = ? AND tenant_id = ?", [$id, $tenantId]);
        if (!$givingDay) {
            return $this->respondWithError('NOT_FOUND', __('api.giving_day_not_found'), null, 404);
        }

        $perPage = $this->queryInt('per_page', 20, 1, 50);
        $cursor = $this->query('cursor');

        try {
            // Stats
            $stats = DB::selectOne("
                SELECT COUNT(*) as total_donors,
                       SUM(CASE WHEN is_anonymous = 1 THEN 1 ELSE 0 END) as anonymous_count,
                       COALESCE(SUM(amount), 0) as total_raised
                FROM vol_donations
                WHERE giving_day_id = ? AND tenant_id = ? AND status = 'completed'
            ", [$id, $tenantId]);

            // Paginated donor list
            $sql = "SELECT vd.id, vd.user_id, vd.amount, vd.is_anonymous, vd.created_at as donated_at,
                           u.name as user_name, u.email as user_email, u.avatar_url
                    FROM vol_donations vd
                    LEFT JOIN users u ON vd.user_id = u.id
                    WHERE vd.giving_day_id = ? AND vd.tenant_id = ? AND vd.status = 'completed'";
            $params = [$id, $tenantId];

            if ($cursor) {
                $decoded = base64_decode($cursor, true);
                if ($decoded && is_numeric($decoded)) { $sql .= " AND vd.id < ?"; $params[] = (int) $decoded; }
            }

            $sql .= " ORDER BY vd.created_at DESC, vd.id DESC LIMIT ?";
            $params[] = $perPage + 1;

            $rows = DB::select($sql, $params);
            $hasMore = count($rows) > $perPage;
            if ($hasMore) array_pop($rows);

            $items = array_map(fn($r) => [
                'id' => (int) $r->id,
                'user_id' => $r->user_id ? (int) $r->user_id : null,
                'name' => $r->is_anonymous ? 'Anonymous' : ($r->user_name ?? 'Guest'),
                'email' => $r->is_anonymous ? null : $r->user_email,
                'avatar_url' => $r->is_anonymous ? null : $r->avatar_url,
                'amount' => (float) $r->amount,
                'is_anonymous' => (bool) $r->is_anonymous,
                'donated_at' => $r->donated_at,
            ], $rows);

            $lastItem = end($items);
            $nextCursor = ($hasMore && $lastItem) ? base64_encode((string) $lastItem['id']) : null;

            return response()->json([
                'success' => true,
                'data' => $items,
                'stats' => [
                    'total_donors' => (int) ($stats->total_donors ?? 0),
                    'anonymous_count' => (int) ($stats->anonymous_count ?? 0),
                    'total_raised' => (float) ($stats->total_raised ?? 0),
                ],
                'meta' => [
                    'per_page' => $perPage,
                    'has_more' => $hasMore,
                    'cursor' => $nextCursor,
                    'stats' => [
                        'total_donors' => (int) ($stats->total_donors ?? 0),
                        'anonymous_count' => (int) ($stats->anonymous_count ?? 0),
                        'total_raised' => (float) ($stats->total_raised ?? 0),
                    ],
                ],
            ]);
        } catch (\Exception $e) {
            Log::error("AdminVolunteerController::givingDayDonors error: " . $e->getMessage());
            return response()->json([
                'success' => true, 'data' => [],
                'stats' => ['total_donors' => 0, 'anonymous_count' => 0, 'total_raised' => 0],
                'meta' => [
                    'per_page' => $perPage,
                    'has_more' => false,
                    'cursor' => null,
                    'stats' => ['total_donors' => 0, 'anonymous_count' => 0, 'total_raised' => 0],
                ],
            ]);
        }
    }

    /** GET /api/v2/admin/volunteering/activity-feed */
    public function activityFeed(): JsonResponse
    {
        $this->requireAdmin();
        $this->ensureFeature();
        $tenantId = TenantContext::getId();
        $limit = $this->queryInt('limit', 50, 1, 100);
        $days = $this->queryInt('days', 30, 1, 365);

        try {
            // UNION of recent volunteering activities from multiple tables
            $activities = DB::select("
                (SELECT 'hours_logged' as type, vl.created_at as timestamp,
                        u.name as user_name, u.avatar_url,
                        CONCAT(u.name, ' logged ', vl.hours, 'h') as description,
                        'vol_logs' as entity_type, vl.id as entity_id
                 FROM vol_logs vl JOIN users u ON vl.user_id = u.id
                 WHERE vl.tenant_id = ? AND vl.created_at >= DATE_SUB(NOW(), INTERVAL ? DAY))
                UNION ALL
                (SELECT CONCAT('application_', va.status) as type, va.created_at as timestamp,
                        u.name as user_name, u.avatar_url,
                        CONCAT(u.name, ' ', IF(va.status='pending','applied for',''), IF(va.status='approved','was approved for',''), IF(va.status='declined','was declined for',''), ' \"', vo.title, '\"') as description,
                        'vol_applications' as entity_type, va.id as entity_id
                 FROM vol_applications va
                 JOIN users u ON va.user_id = u.id
                 JOIN vol_opportunities vo ON va.opportunity_id = vo.id
                 WHERE va.tenant_id = ? AND va.created_at >= DATE_SUB(NOW(), INTERVAL ? DAY))
                UNION ALL
                (SELECT 'donation' as type, vd.created_at as timestamp,
                        COALESCE(u.name, vd.donor_name, 'Anonymous') as user_name, u.avatar_url,
                        CONCAT(COALESCE(u.name, vd.donor_name, 'Anonymous'), ' donated ', vd.amount) as description,
                        'vol_donations' as entity_type, vd.id as entity_id
                 FROM vol_donations vd
                 LEFT JOIN users u ON vd.user_id = u.id
                 WHERE vd.tenant_id = ? AND vd.created_at >= DATE_SUB(NOW(), INTERVAL ? DAY) AND vd.status = 'completed')
                ORDER BY timestamp DESC LIMIT ?
            ", [$tenantId, $days, $tenantId, $days, $tenantId, $days, $limit]);

            $items = array_map(fn($r) => [
                'type' => $r->type,
                'timestamp' => $r->timestamp,
                'user_name' => $r->user_name,
                'avatar_url' => $r->avatar_url,
                'description' => $r->description,
                'entity_type' => $r->entity_type,
                'entity_id' => (int) $r->entity_id,
            ], $activities);

            return $this->respondWithData(['activities' => $items]);
        } catch (\Exception $e) {
            Log::error("AdminVolunteerController::activityFeed error: " . $e->getMessage());
            return $this->respondWithData(['activities' => []]);
        }
    }

    /** GET /api/v2/admin/volunteering/giving-days/{id}/trends */
    public function givingDayTrends(int $id): JsonResponse
    {
        $this->requireAdmin();
        $this->ensureFeature();
        $tenantId = TenantContext::getId();

        // Verify giving day belongs to this tenant
        $givingDay = DB::selectOne("SELECT id FROM vol_giving_days WHERE id = ? AND tenant_id = ?", [$id, $tenantId]);
        if (!$givingDay) {
            return $this->respondWithError('NOT_FOUND', __('api.giving_day_not_found'), null, 404);
        }

        $granularity = $this->query('granularity', 'day');
        $format = $granularity === 'week' ? '%Y-W%u' : '%Y-%m-%d';

        try {
            $rows = DB::select("
                SELECT DATE_FORMAT(created_at, ?) as period,
                       COUNT(DISTINCT user_id) as donors,
                       COALESCE(SUM(amount), 0) as amount
                FROM vol_donations
                WHERE giving_day_id = ? AND tenant_id = ? AND status = 'completed'
                GROUP BY period ORDER BY period ASC
            ", [$format, $id, $tenantId]);

            $cumulative = 0;
            $trends = array_map(function ($r) use (&$cumulative) {
                $cumulative += (float) $r->amount;
                return [
                    'period' => $r->period,
                    'donors' => (int) $r->donors,
                    'amount' => (float) $r->amount,
                    'cumulative' => round($cumulative, 2),
                ];
            }, $rows);

            return $this->respondWithData(['trends' => $trends]);
        } catch (\Exception $e) {
            Log::error("AdminVolunteerController::givingDayTrends error: " . $e->getMessage());
            return $this->respondWithData(['trends' => []]);
        }
    }
}

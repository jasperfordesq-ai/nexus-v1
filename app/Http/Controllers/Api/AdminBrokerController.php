<?php
// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

namespace App\Http\Controllers\Api;

use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;
use App\Core\TenantContext;
use App\Services\AuditLogService;
use App\Services\BrokerControlConfigService;
use App\Services\ExchangeWorkflowService;
use App\Services\ListingRiskTagService;
use App\Services\BrokerMessageVisibilityService;
use App\Services\NotificationDispatcher;
use App\Models\Notification;

/**
 * AdminBrokerController -- Admin time-broker exchange monitoring and risk management.
 *
 * Converted from legacy delegation to direct DB/service calls.
 */
class AdminBrokerController extends BaseApiController
{
    protected bool $isV2Api = true;

    public function __construct(
        private readonly BrokerControlConfigService $brokerControlConfigService,
        private readonly BrokerMessageVisibilityService $brokerMessageVisibilityService,
        private readonly ExchangeWorkflowService $exchangeWorkflowService,
        private readonly NotificationDispatcher $notificationDispatcher,
    ) {}

    // ============================================
    // HELPERS
    // ============================================

    private function isSuperAdmin(): bool
    {
        $user = $this->resolveUserObject();
        $role = $user->role ?? 'member';
        if (in_array($role, ['super_admin', 'god'])) {
            return true;
        }
        return ($user->is_super_admin ?? false) || ($user->is_tenant_super_admin ?? false);
    }

    private function resolveUserObject(): object
    {
        $user = \Illuminate\Support\Facades\Auth::user();
        if ($user) {
            return $user;
        }
        $userId = \Illuminate\Support\Facades\Auth::id() ?? $this->getOptionalUserId();
        if ($userId) {
            $row = DB::selectOne(
                "SELECT id, role, is_super_admin, is_tenant_super_admin FROM users WHERE id = ?",
                [$userId]
            );
            if ($row) {
                return $row;
            }
        }
        return (object) ['role' => 'member'];
    }

    private function resolveEffectiveTenantId(bool $isSuperAdmin, int $tenantId): ?int
    {
        $filterRaw = request()->query('tenant_id');
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

    // ============================================
    // DASHBOARD
    // ============================================

    /** GET /api/v2/admin/broker/dashboard */
    public function dashboard(): JsonResponse
    {
        $this->requireAdmin();
        $isSuperAdmin = $this->isSuperAdmin();
        $tenantId = TenantContext::getId();
        $effectiveTenantId = $this->resolveEffectiveTenantId($isSuperAdmin, $tenantId);
        $tenantWhere = $effectiveTenantId !== null ? 'tenant_id = ?' : '1=1';
        $tenantParams = $effectiveTenantId !== null ? [$effectiveTenantId] : [];

        $pendingExchanges = 0;
        try {
            $row = DB::selectOne(
                "SELECT COUNT(*) as cnt FROM exchange_requests WHERE {$tenantWhere} AND status IN ('pending_broker', 'disputed')",
                $tenantParams
            );
            $pendingExchanges = (int) ($row->cnt ?? 0);
        } catch (\Exception $e) { \Illuminate\Support\Facades\Log::warning('[AdminBroker] Dashboard query failed: ' . $e->getMessage()); }

        $unreviewedMessages = 0;
        try {
            $row = DB::selectOne(
                "SELECT COUNT(*) as cnt FROM broker_message_copies WHERE {$tenantWhere} AND reviewed_by IS NULL",
                $tenantParams
            );
            $unreviewedMessages = (int) ($row->cnt ?? 0);
        } catch (\Exception $e) { \Illuminate\Support\Facades\Log::warning('[AdminBroker] Dashboard query failed: ' . $e->getMessage()); }

        $highRiskListings = 0;
        try {
            $row = DB::selectOne(
                "SELECT COUNT(*) as cnt FROM listing_risk_tags WHERE {$tenantWhere} AND risk_level IN ('high', 'critical')",
                $tenantParams
            );
            $highRiskListings = (int) ($row->cnt ?? 0);
        } catch (\Exception $e) { \Illuminate\Support\Facades\Log::warning('[AdminBroker] Dashboard query failed: ' . $e->getMessage()); }

        $monitoredUsers = 0;
        try {
            $row = DB::selectOne(
                "SELECT COUNT(*) as cnt FROM user_messaging_restrictions WHERE {$tenantWhere} AND under_monitoring = 1 AND (monitoring_expires_at IS NULL OR monitoring_expires_at > NOW())",
                $tenantParams
            );
            $monitoredUsers = (int) ($row->cnt ?? 0);
        } catch (\Exception $e) { \Illuminate\Support\Facades\Log::warning('[AdminBroker] Dashboard query failed: ' . $e->getMessage()); }

        $vettingPending = 0;
        $vettingExpiring = 0;
        try {
            $row = DB::selectOne(
                "SELECT
                    SUM(CASE WHEN status IN ('pending', 'submitted') THEN 1 ELSE 0 END) as pending,
                    SUM(CASE WHEN status = 'verified' AND expiry_date BETWEEN CURDATE() AND DATE_ADD(CURDATE(), INTERVAL 30 DAY) THEN 1 ELSE 0 END) as expiring
                 FROM vetting_records WHERE {$tenantWhere}",
                $tenantParams
            );
            $vettingPending = (int) ($row->pending ?? 0);
            $vettingExpiring = (int) ($row->expiring ?? 0);
        } catch (\Exception $e) { \Illuminate\Support\Facades\Log::warning('[AdminBroker] Dashboard query failed: ' . $e->getMessage()); }

        $safeguardingAlerts = 0;
        try {
            $row = DB::selectOne(
                "SELECT COUNT(*) as cnt FROM abuse_alerts WHERE {$tenantWhere} AND status = 'open'",
                $tenantParams
            );
            $safeguardingAlerts = (int) ($row->cnt ?? 0);
        } catch (\Exception $e) { \Illuminate\Support\Facades\Log::warning('[AdminBroker] Dashboard query failed: ' . $e->getMessage()); }

        $onboardingSafeguardingFlags = 0;
        try {
            $row = DB::selectOne(
                "SELECT COUNT(DISTINCT usp.user_id) as cnt
                 FROM user_safeguarding_preferences usp
                 JOIN tenant_safeguarding_options tso ON tso.id = usp.option_id
                 WHERE usp.tenant_id = ? AND usp.revoked_at IS NULL AND tso.is_active = 1
                 AND tso.triggers IS NOT NULL
                 AND NOT EXISTS (
                     SELECT 1 FROM activity_log al
                     WHERE al.entity_type = 'user' AND al.entity_id = usp.user_id
                     AND al.action = 'safeguarding_flag_reviewed'
                 )",
                [$effectiveTenantId ?? TenantContext::getId()]
            );
            $onboardingSafeguardingFlags = (int) ($row->cnt ?? 0);
        } catch (\Exception $e) { \Illuminate\Support\Facades\Log::warning('[AdminBroker] Dashboard query failed: ' . $e->getMessage()); }

        $recentActivity = [];
        try {
            $actWhere = $effectiveTenantId !== null ? 'al.tenant_id = ?' : '1=1';
            $actParams = $effectiveTenantId !== null ? [$effectiveTenantId] : [];
            $recentActivity = DB::select(
                "SELECT al.*, u.first_name, u.last_name, t.name as tenant_name
                 FROM activity_log al
                 LEFT JOIN users u ON u.id = al.user_id
                 LEFT JOIN tenants t ON al.tenant_id = t.id
                 WHERE {$actWhere} AND al.action_type IN (
                     'exchange_approved', 'exchange_rejected', 'message_reviewed',
                     'risk_tag_added', 'user_monitored', 'vetting_verified', 'vetting_rejected',
                     'user_banned', 'user_unbanned', 'balance_adjusted'
                 )
                 ORDER BY al.created_at DESC
                 LIMIT 20",
                $actParams
            );
            $recentActivity = array_map(fn($r) => (array)$r, $recentActivity);
        } catch (\Exception $e) { \Illuminate\Support\Facades\Log::warning('[AdminBroker] Dashboard query failed: ' . $e->getMessage()); }

        return $this->respondWithData([
            'pending_exchanges' => $pendingExchanges,
            'unreviewed_messages' => $unreviewedMessages,
            'high_risk_listings' => $highRiskListings,
            'monitored_users' => $monitoredUsers,
            'vetting_pending' => $vettingPending,
            'vetting_expiring' => $vettingExpiring,
            'safeguarding_alerts' => $safeguardingAlerts,
            'onboarding_safeguarding_flags' => $onboardingSafeguardingFlags,
            'recent_activity' => $recentActivity,
        ]);
    }

    // ============================================
    // EXCHANGES
    // ============================================

    /** GET /api/v2/admin/broker/exchanges */
    public function exchanges(): JsonResponse
    {
        $this->requireAdmin();
        $isSuperAdmin = $this->isSuperAdmin();
        $tenantId = TenantContext::getId();
        $page = $this->queryInt('page', 1, 1);
        $perPage = $this->queryInt('per_page', 20, 1, 100);
        $status = $this->query('status');
        $offset = ($page - 1) * $perPage;

        try {
            $conditions = [];
            $params = [];

            $effectiveTenantId = $this->resolveEffectiveTenantId($isSuperAdmin, $tenantId);
            if ($effectiveTenantId !== null) {
                $conditions[] = 'er.tenant_id = ?';
                $params[] = $effectiveTenantId;
            }

            if ($status && $status !== 'all') {
                $conditions[] = 'er.status = ?';
                $params[] = $status;
            }

            $where = !empty($conditions) ? implode(' AND ', $conditions) : '1=1';

            $countRow = DB::selectOne(
                "SELECT COUNT(*) as cnt FROM exchange_requests er WHERE {$where}",
                $params
            );
            $total = (int) ($countRow->cnt ?? 0);

            $queryParams = array_merge($params, [$perPage, $offset]);
            $items = DB::select(
                "SELECT er.*,
                    CONCAT(req.first_name, ' ', req.last_name) as requester_name,
                    CONCAT(prov.first_name, ' ', prov.last_name) as provider_name,
                    l.title as listing_title,
                    t.name as tenant_name
                FROM exchange_requests er
                JOIN users req ON er.requester_id = req.id
                JOIN users prov ON er.provider_id = prov.id
                LEFT JOIN listings l ON er.listing_id = l.id
                LEFT JOIN tenants t ON er.tenant_id = t.id
                WHERE {$where}
                ORDER BY er.created_at DESC
                LIMIT ? OFFSET ?",
                $queryParams
            );
            $items = array_map(fn($r) => (array)$r, $items);

            foreach ($items as &$item) {
                $item['tenant_name'] = $item['tenant_name'] ?? 'Unknown';
            }
            unset($item);

            return $this->respondWithPaginatedCollection($items, $total, $page, $perPage);
        } catch (\Exception $e) {
            return $this->respondWithPaginatedCollection([], 0, $page, $perPage);
        }
    }

    /** GET /api/v2/admin/broker/exchanges/{id} */
    public function showExchange(int $id): JsonResponse
    {
        $this->requireAdmin();
        $isSuperAdmin = $this->isSuperAdmin();
        $tenantId = TenantContext::getId();

        try {
            if ($isSuperAdmin) {
                $exchange = DB::selectOne(
                    "SELECT er.*,
                        CONCAT(req.first_name, ' ', req.last_name) as requester_name,
                        req.email as requester_email, req.avatar as requester_avatar,
                        CONCAT(prov.first_name, ' ', prov.last_name) as provider_name,
                        prov.email as provider_email, prov.avatar as provider_avatar,
                        l.title as listing_title, l.listing_type, l.hours_offered,
                        t.name as tenant_name
                    FROM exchange_requests er
                    JOIN users req ON er.requester_id = req.id
                    JOIN users prov ON er.provider_id = prov.id
                    LEFT JOIN listings l ON er.listing_id = l.id
                    LEFT JOIN tenants t ON er.tenant_id = t.id
                    WHERE er.id = ?",
                    [$id]
                );
            } else {
                $exchange = DB::selectOne(
                    "SELECT er.*,
                        CONCAT(req.first_name, ' ', req.last_name) as requester_name,
                        req.email as requester_email, req.avatar as requester_avatar,
                        CONCAT(prov.first_name, ' ', prov.last_name) as provider_name,
                        prov.email as provider_email, prov.avatar as provider_avatar,
                        l.title as listing_title, l.listing_type, l.hours_offered,
                        t.name as tenant_name
                    FROM exchange_requests er
                    JOIN users req ON er.requester_id = req.id
                    JOIN users prov ON er.provider_id = prov.id
                    LEFT JOIN listings l ON er.listing_id = l.id
                    LEFT JOIN tenants t ON er.tenant_id = t.id
                    WHERE er.id = ? AND er.tenant_id = ?",
                    [$id, $tenantId]
                );
            }

            if (!$exchange) {
                return $this->respondWithError('NOT_FOUND', 'Exchange request not found', null, 404);
            }

            $exchange = (array) $exchange;
            $exchange['tenant_name'] = $exchange['tenant_name'] ?? 'Unknown';
            $exchangeTenantId = (int) $exchange['tenant_id'];

            $history = [];
            try {
                $history = DB::select(
                    "SELECT eh.*, CONCAT(u.first_name, ' ', u.last_name) as actor_name
                    FROM exchange_history eh
                    LEFT JOIN users u ON eh.actor_id = u.id
                    WHERE eh.exchange_id = ?
                    ORDER BY eh.created_at ASC",
                    [$id]
                );
                $history = array_map(fn($r) => (array)$r, $history);
            } catch (\Exception $e) { \Illuminate\Support\Facades\Log::warning('[AdminBroker] Dashboard query failed: ' . $e->getMessage()); }

            $riskTag = null;
            if (!empty($exchange['listing_id'])) {
                try {
                    $riskTagRow = DB::selectOne(
                        "SELECT * FROM listing_risk_tags WHERE listing_id = ? AND tenant_id = ?",
                        [$exchange['listing_id'], $exchangeTenantId]
                    );
                    $riskTag = $riskTagRow ? (array) $riskTagRow : null;
                } catch (\Exception $e) { \Illuminate\Support\Facades\Log::warning('[AdminBroker] Dashboard query failed: ' . $e->getMessage()); }
            }

            return $this->respondWithData([
                'exchange' => $exchange,
                'history' => $history,
                'risk_tag' => $riskTag,
            ]);
        } catch (\Exception $e) {
            return $this->respondWithError('SERVER_ERROR', 'Failed to load exchange', null, 500);
        }
    }

    /** POST /api/v2/admin/broker/exchanges/{id}/approve */
    public function approveExchange(int $id): JsonResponse
    {
        $adminId = $this->requireAdmin();
        $isSuperAdmin = $this->isSuperAdmin();
        $tenantId = TenantContext::getId();
        $notes = $this->input('notes', '');

        try {
            if ($isSuperAdmin) {
                $exchange = DB::selectOne(
                    "SELECT id, status, tenant_id FROM exchange_requests WHERE id = ?", [$id]
                );
            } else {
                $exchange = DB::selectOne(
                    "SELECT id, status, tenant_id FROM exchange_requests WHERE id = ? AND tenant_id = ?", [$id, $tenantId]
                );
            }

            if (!$exchange) {
                return $this->respondWithError('NOT_FOUND', 'Exchange request not found', null, 404);
            }
            if ($exchange->status !== 'pending_broker') {
                return $this->respondWithError('INVALID_STATUS', 'Exchange is not pending broker approval');
            }

            $success = $this->exchangeWorkflowService->approveExchange($id, $adminId, $notes);
            if (!$success) {
                return $this->respondWithError('SERVER_ERROR', 'Failed to approve exchange', null, 500);
            }

            return $this->respondWithData(['id' => $id, 'status' => 'accepted']);
        } catch (\Exception $e) {
            return $this->respondWithError('SERVER_ERROR', 'Failed to approve exchange', null, 500);
        }
    }

    /** POST /api/v2/admin/broker/exchanges/{id}/reject */
    public function rejectExchange(int $id): JsonResponse
    {
        $adminId = $this->requireAdmin();
        $isSuperAdmin = $this->isSuperAdmin();
        $tenantId = TenantContext::getId();
        $reason = $this->input('reason', '');

        if (empty($reason)) {
            return $this->respondWithError('VALIDATION_ERROR', 'A reason is required to reject an exchange', 'reason');
        }

        try {
            if ($isSuperAdmin) {
                $exchange = DB::selectOne(
                    "SELECT id, status, tenant_id FROM exchange_requests WHERE id = ?", [$id]
                );
            } else {
                $exchange = DB::selectOne(
                    "SELECT id, status, tenant_id FROM exchange_requests WHERE id = ? AND tenant_id = ?", [$id, $tenantId]
                );
            }

            if (!$exchange) {
                return $this->respondWithError('NOT_FOUND', 'Exchange request not found', null, 404);
            }
            if ($exchange->status !== 'pending_broker') {
                return $this->respondWithError('INVALID_STATUS', 'Exchange is not pending broker approval');
            }

            $success = $this->exchangeWorkflowService->rejectExchange($id, $adminId, $reason);
            if (!$success) {
                return $this->respondWithError('SERVER_ERROR', 'Failed to reject exchange', null, 500);
            }

            return $this->respondWithData(['id' => $id, 'status' => 'cancelled']);
        } catch (\Exception $e) {
            return $this->respondWithError('SERVER_ERROR', 'Failed to reject exchange', null, 500);
        }
    }

    // ============================================
    // RISK TAGS
    // ============================================

    /** GET /api/v2/admin/broker/risk-tags */
    public function riskTags(): JsonResponse
    {
        $this->requireAdmin();
        $isSuperAdmin = $this->isSuperAdmin();
        $tenantId = TenantContext::getId();
        $riskLevel = $this->query('risk_level');

        try {
            $conditions = [];
            $params = [];

            $effectiveTenantId = $this->resolveEffectiveTenantId($isSuperAdmin, $tenantId);
            if ($effectiveTenantId !== null) {
                $conditions[] = 'rt.tenant_id = ?';
                $params[] = $effectiveTenantId;
            }

            if ($riskLevel && $riskLevel !== 'all') {
                $conditions[] = 'rt.risk_level = ?';
                $params[] = $riskLevel;
            }

            $where = !empty($conditions) ? implode(' AND ', $conditions) : '1=1';

            $items = DB::select(
                "SELECT rt.*, l.title as listing_title, u.name as owner_name,
                    tagger.name as tagged_by_name, t.name as tenant_name
                FROM listing_risk_tags rt
                LEFT JOIN listings l ON rt.listing_id = l.id
                LEFT JOIN users u ON l.user_id = u.id
                LEFT JOIN users tagger ON rt.tagged_by = tagger.id
                LEFT JOIN tenants t ON rt.tenant_id = t.id
                WHERE {$where}
                ORDER BY FIELD(rt.risk_level, 'critical', 'high', 'medium', 'low'), rt.created_at DESC",
                $params
            );
            $items = array_map(fn($r) => (array)$r, $items);

            foreach ($items as &$item) {
                $item['tenant_name'] = $item['tenant_name'] ?? 'Unknown';
            }
            unset($item);

            return $this->respondWithData($items);
        } catch (\Exception $e) {
            return $this->respondWithData([]);
        }
    }

    /** POST /api/v2/admin/broker/listings/{lid}/risk-tag */
    public function saveRiskTag(int $listingId): JsonResponse
    {
        $adminId = $this->requireAdmin();
        $isSuperAdmin = $this->isSuperAdmin();
        $tenantId = TenantContext::getId();

        $riskLevel = $this->input('risk_level', 'low');
        $riskCategory = trim($this->input('risk_category', ''));
        $riskNotes = trim($this->input('risk_notes', ''));
        $memberVisibleNotes = trim($this->input('member_visible_notes', ''));
        $requiresApproval = (bool) $this->input('requires_approval', false);
        $insuranceRequired = (bool) $this->input('insurance_required', false);
        $dbsRequired = (bool) $this->input('dbs_required', false);

        $allowedLevels = ['low', 'medium', 'high', 'critical'];
        if (!in_array($riskLevel, $allowedLevels)) {
            return $this->respondWithError('VALIDATION_ERROR', 'Invalid risk level', 'risk_level');
        }
        if (empty($riskCategory)) {
            return $this->respondWithError('VALIDATION_ERROR', 'Risk category is required', 'risk_category');
        }

        try {
            if ($isSuperAdmin) {
                $listing = DB::selectOne("SELECT id, tenant_id FROM listings WHERE id = ?", [$listingId]);
            } else {
                $listing = DB::selectOne("SELECT id, tenant_id FROM listings WHERE id = ? AND tenant_id = ?", [$listingId, $tenantId]);
            }

            if (!$listing) {
                return $this->respondWithError('NOT_FOUND', 'Listing not found', null, 404);
            }

            $listingTenantId = (int) $listing->tenant_id;

            $existing = DB::selectOne(
                "SELECT id, risk_level FROM listing_risk_tags WHERE listing_id = ? AND tenant_id = ?",
                [$listingId, $listingTenantId]
            );

            if ($existing) {
                $oldRiskLevel = $existing->risk_level;
                DB::update(
                    "UPDATE listing_risk_tags SET risk_level = ?, risk_category = ?, risk_notes = ?, member_visible_notes = ?, requires_approval = ?, insurance_required = ?, dbs_required = ?, tagged_by = ?, updated_at = NOW() WHERE listing_id = ? AND tenant_id = ?",
                    [$riskLevel, $riskCategory, $riskNotes, $memberVisibleNotes, $requiresApproval ? 1 : 0, $insuranceRequired ? 1 : 0, $dbsRequired ? 1 : 0, $adminId, $listingId, $listingTenantId]
                );
                $tagId = $existing->id;
                AuditLogService::log('listing_risk_tag_updated', null, $adminId, ['listing_id' => $listingId, 'old_risk_level' => $oldRiskLevel, 'new_risk_level' => $riskLevel]);

                $highLevels = [ListingRiskTagService::RISK_HIGH, ListingRiskTagService::RISK_CRITICAL];
                if (in_array($riskLevel, $highLevels, true) && !in_array($oldRiskLevel, $highLevels, true)) {
                    $this->notifyAdminsOfRiskTagChange($listingId, $riskLevel, $adminId);
                }
            } else {
                DB::insert(
                    "INSERT INTO listing_risk_tags (listing_id, tenant_id, risk_level, risk_category, risk_notes, member_visible_notes, requires_approval, insurance_required, dbs_required, tagged_by, created_at, updated_at) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, NOW(), NOW())",
                    [$listingId, $listingTenantId, $riskLevel, $riskCategory, $riskNotes, $memberVisibleNotes, $requiresApproval ? 1 : 0, $insuranceRequired ? 1 : 0, $dbsRequired ? 1 : 0, $adminId]
                );
                $tagId = (int) DB::getPdo()->lastInsertId();
                AuditLogService::log('listing_risk_tag_created', null, $adminId, ['listing_id' => $listingId, 'tag_id' => $tagId, 'risk_level' => $riskLevel]);

                if (in_array($riskLevel, [ListingRiskTagService::RISK_HIGH, ListingRiskTagService::RISK_CRITICAL], true)) {
                    $this->notifyAdminsOfRiskTagChange($listingId, $riskLevel, $adminId);
                }
            }

            return $this->respondWithData(['id' => $tagId, 'listing_id' => $listingId, 'risk_level' => $riskLevel]);
        } catch (\Exception $e) {
            return $this->respondWithError('SERVER_ERROR', 'Failed to save risk tag', null, 500);
        }
    }

    /** DELETE /api/v2/admin/broker/listings/{lid}/risk-tag */
    public function removeRiskTag(int $listingId): JsonResponse
    {
        $adminId = $this->requireAdmin();
        $isSuperAdmin = $this->isSuperAdmin();
        $tenantId = TenantContext::getId();

        try {
            if ($isSuperAdmin) {
                $existing = DB::selectOne("SELECT id, tenant_id, risk_level FROM listing_risk_tags WHERE listing_id = ?", [$listingId]);
            } else {
                $existing = DB::selectOne("SELECT id, tenant_id, risk_level FROM listing_risk_tags WHERE listing_id = ? AND tenant_id = ?", [$listingId, $tenantId]);
            }

            if (!$existing) {
                return $this->respondWithError('NOT_FOUND', 'Risk tag not found', null, 404);
            }

            $recordTenantId = (int) $existing->tenant_id;
            DB::delete("DELETE FROM listing_risk_tags WHERE listing_id = ? AND tenant_id = ?", [$listingId, $recordTenantId]);

            AuditLogService::log('listing_risk_tag_removed', null, $adminId, ['listing_id' => $listingId, 'previous_risk_level' => $existing->risk_level ?? null]);

            return $this->respondWithData(['listing_id' => $listingId, 'removed' => true]);
        } catch (\Exception $e) {
            return $this->respondWithError('SERVER_ERROR', 'Failed to remove risk tag', null, 500);
        }
    }

    // ============================================
    // MESSAGES
    // ============================================

    /** GET /api/v2/admin/broker/messages */
    public function messages(): JsonResponse
    {
        $this->requireAdmin();
        $isSuperAdmin = $this->isSuperAdmin();
        $tenantId = TenantContext::getId();
        $page = $this->queryInt('page', 1, 1);
        $perPage = $this->queryInt('per_page', 20, 1, 100);
        $filter = $this->query('filter', 'all');
        $offset = ($page - 1) * $perPage;

        try {
            $conditions = [];
            $params = [];

            $effectiveTenantId = $this->resolveEffectiveTenantId($isSuperAdmin, $tenantId);
            if ($effectiveTenantId !== null) {
                $conditions[] = 'bmc.tenant_id = ?';
                $params[] = $effectiveTenantId;
            }

            if ($filter === 'unreviewed') {
                $conditions[] = 'bmc.reviewed_by IS NULL';
            } elseif ($filter === 'flagged') {
                $conditions[] = 'bmc.flagged = 1';
            } elseif ($filter === 'reviewed') {
                $conditions[] = 'bmc.reviewed_by IS NOT NULL';
            }

            $where = !empty($conditions) ? implode(' AND ', $conditions) : '1=1';

            $countRow = DB::selectOne("SELECT COUNT(*) as cnt FROM broker_message_copies bmc WHERE {$where}", $params);
            $total = (int) ($countRow->cnt ?? 0);

            $queryParams = array_merge($params, [$perPage, $offset]);
            $items = DB::select(
                "SELECT bmc.*, CONCAT(s.first_name, ' ', s.last_name) as sender_name,
                    CONCAT(r.first_name, ' ', r.last_name) as receiver_name,
                    l.title as listing_title, t.name as tenant_name
                FROM broker_message_copies bmc
                LEFT JOIN users s ON bmc.sender_id = s.id
                LEFT JOIN users r ON bmc.receiver_id = r.id
                LEFT JOIN listings l ON bmc.related_listing_id = l.id
                LEFT JOIN tenants t ON bmc.tenant_id = t.id
                WHERE {$where}
                ORDER BY bmc.created_at DESC
                LIMIT ? OFFSET ?",
                $queryParams
            );
            $items = array_map(fn($r) => (array)$r, $items);

            foreach ($items as &$item) {
                $item['flagged'] = (bool) ($item['flagged'] ?? false);
                $item['tenant_name'] = $item['tenant_name'] ?? 'Unknown';
            }
            unset($item);

            return $this->respondWithPaginatedCollection($items, $total, $page, $perPage);
        } catch (\Exception $e) {
            return $this->respondWithPaginatedCollection([], 0, $page, $perPage);
        }
    }

    /** GET /api/v2/admin/broker/messages/{id} */
    public function showMessage(int $id): JsonResponse
    {
        $this->requireAdmin();
        $isSuperAdmin = $this->isSuperAdmin();
        $tenantId = TenantContext::getId();

        try {
            $baseSelect = "SELECT bmc.*, CONCAT(s.first_name, ' ', s.last_name) as sender_name,
                CONCAT(r.first_name, ' ', r.last_name) as receiver_name,
                l.title as listing_title, t.name as tenant_name
                FROM broker_message_copies bmc
                LEFT JOIN users s ON bmc.sender_id = s.id
                LEFT JOIN users r ON bmc.receiver_id = r.id
                LEFT JOIN listings l ON bmc.related_listing_id = l.id
                LEFT JOIN tenants t ON bmc.tenant_id = t.id";

            if ($isSuperAdmin) {
                $copy = DB::selectOne("{$baseSelect} WHERE bmc.id = ?", [$id]);
            } else {
                $copy = DB::selectOne("{$baseSelect} WHERE bmc.id = ? AND bmc.tenant_id = ?", [$id, $tenantId]);
            }

            if (!$copy) {
                return $this->respondWithError('NOT_FOUND', 'Broker message copy not found', null, 404);
            }

            $copy = (array) $copy;
            $copy['flagged'] = (bool) ($copy['flagged'] ?? false);
            $copy['tenant_name'] = $copy['tenant_name'] ?? 'Unknown';
            $copyTenantId = (int) $copy['tenant_id'];

            $thread = DB::select(
                "SELECT m.id, m.sender_id, m.receiver_id, m.body, m.created_at, m.is_deleted,
                    CONCAT(u.first_name, ' ', u.last_name) as sender_name
                FROM messages m LEFT JOIN users u ON m.sender_id = u.id
                WHERE m.tenant_id = ?
                  AND ((m.sender_id = ? AND m.receiver_id = ?) OR (m.sender_id = ? AND m.receiver_id = ?))
                ORDER BY m.created_at ASC LIMIT 200",
                [$copyTenantId, $copy['sender_id'], $copy['receiver_id'], $copy['receiver_id'], $copy['sender_id']]
            );
            $thread = array_map(fn($r) => (array)$r, $thread);

            foreach ($thread as &$msg) {
                if (!empty($msg['is_deleted'])) {
                    $msg['body'] = '[Message deleted]';
                }
            }
            unset($msg);

            $archive = null;
            if (!empty($copy['archive_id'])) {
                $archiveRow = DB::selectOne(
                    "SELECT id, decision, decision_notes, decided_by_name, decided_at, flag_reason, flag_severity
                    FROM broker_review_archives WHERE id = ? AND tenant_id = ?",
                    [$copy['archive_id'], $copyTenantId]
                );
                $archive = $archiveRow ? (array) $archiveRow : null;
            }

            return $this->respondWithData(['copy' => $copy, 'thread' => $thread, 'archive' => $archive]);
        } catch (\Exception $e) {
            return $this->respondWithError('SERVER_ERROR', 'Failed to load message detail', null, 500);
        }
    }

    /** POST /api/v2/admin/broker/messages/{id}/review */
    public function reviewMessage(int $id): JsonResponse
    {
        $adminId = $this->requireAdmin();
        $isSuperAdmin = $this->isSuperAdmin();
        $tenantId = TenantContext::getId();

        try {
            if ($isSuperAdmin) {
                $message = DB::selectOne("SELECT id, tenant_id FROM broker_message_copies WHERE id = ?", [$id]);
            } else {
                $message = DB::selectOne("SELECT id, tenant_id FROM broker_message_copies WHERE id = ? AND tenant_id = ?", [$id, $tenantId]);
            }

            if (!$message) {
                return $this->respondWithError('NOT_FOUND', 'Message not found', null, 404);
            }

            $recordTenantId = (int) $message->tenant_id;
            DB::update(
                "UPDATE broker_message_copies SET reviewed_by = ?, reviewed_at = NOW() WHERE id = ? AND tenant_id = ?",
                [$adminId, $id, $recordTenantId]
            );

            return $this->respondWithData(['id' => $id, 'reviewed' => true]);
        } catch (\Exception $e) {
            return $this->respondWithError('SERVER_ERROR', 'Failed to mark message as reviewed', null, 500);
        }
    }

    /** POST /api/v2/admin/broker/messages/{id}/approve */
    public function approveMessage(int $id): JsonResponse
    {
        $adminId = $this->requireAdmin();
        $isSuperAdmin = $this->isSuperAdmin();
        $tenantId = TenantContext::getId();
        $notes = trim($this->input('notes', ''));

        try {
            $baseSelect = "SELECT bmc.*, CONCAT(s.first_name, ' ', s.last_name) as sender_name,
                CONCAT(r.first_name, ' ', r.last_name) as receiver_name, l.title as listing_title
                FROM broker_message_copies bmc
                LEFT JOIN users s ON bmc.sender_id = s.id
                LEFT JOIN users r ON bmc.receiver_id = r.id
                LEFT JOIN listings l ON bmc.related_listing_id = l.id";

            if ($isSuperAdmin) {
                $copy = DB::selectOne("{$baseSelect} WHERE bmc.id = ?", [$id]);
            } else {
                $copy = DB::selectOne("{$baseSelect} WHERE bmc.id = ? AND bmc.tenant_id = ?", [$id, $tenantId]);
            }

            if (!$copy) {
                return $this->respondWithError('NOT_FOUND', 'Broker message copy not found', null, 404);
            }

            $copy = (array) $copy;
            $copyTenantId = (int) $copy['tenant_id'];

            if (!empty($copy['archive_id'])) {
                return $this->respondWithError('ALREADY_ARCHIVED', 'This message copy has already been archived', null, 409);
            }

            $adminRow = DB::selectOne("SELECT CONCAT(first_name, ' ', last_name) as name FROM users WHERE id = ? AND tenant_id = ?", [$adminId, $this->getTenantId()]);
            $adminName = $adminRow->name ?? 'Unknown';

            $conversationRows = DB::select(
                "SELECT m.id, m.sender_id, m.body, m.created_at, m.is_deleted,
                    CONCAT(u.first_name, ' ', u.last_name) as sender_name
                FROM messages m LEFT JOIN users u ON m.sender_id = u.id
                WHERE m.tenant_id = ?
                  AND ((m.sender_id = ? AND m.receiver_id = ?) OR (m.sender_id = ? AND m.receiver_id = ?))
                ORDER BY m.created_at ASC LIMIT 500",
                [$copyTenantId, $copy['sender_id'], $copy['receiver_id'], $copy['receiver_id'], $copy['sender_id']]
            );
            $conversationRows = array_map(fn($r) => (array)$r, $conversationRows);

            foreach ($conversationRows as &$msg) {
                if (!empty($msg['is_deleted'])) {
                    $msg['body'] = '[Message deleted]';
                }
                unset($msg['is_deleted']);
            }
            unset($msg);

            $conversationSnapshot = json_encode($conversationRows);
            $decision = !empty($copy['flagged']) ? 'flagged' : 'approved';

            DB::insert(
                "INSERT INTO broker_review_archives
                    (tenant_id, broker_copy_id, sender_id, sender_name, receiver_id, receiver_name,
                     related_listing_id, listing_title, copy_reason, target_message_body, target_message_sent_at,
                     conversation_snapshot, decision, decision_notes, decided_by, decided_by_name,
                     decided_at, flag_reason, flag_severity, created_at)
                VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, NOW(), ?, ?, NOW())",
                [
                    $copyTenantId, $id, $copy['sender_id'], $copy['sender_name'] ?? '', $copy['receiver_id'],
                    $copy['receiver_name'] ?? '', $copy['related_listing_id'], $copy['listing_title'],
                    $copy['copy_reason'], $copy['message_body'] ?? '', $copy['sent_at'],
                    $conversationSnapshot, $decision, $notes ?: null, $adminId, $adminName,
                    $copy['flag_reason'], $copy['flag_severity'],
                ]
            );

            $archiveId = (int) DB::getPdo()->lastInsertId();

            DB::update(
                "UPDATE broker_message_copies SET archived_at = NOW(), archive_id = ?,
                     reviewed_by = COALESCE(reviewed_by, ?), reviewed_at = COALESCE(reviewed_at, NOW())
                 WHERE id = ? AND tenant_id = ?",
                [$archiveId, $adminId, $id, $copyTenantId]
            );

            return $this->respondWithData(['id' => $id, 'archive_id' => $archiveId, 'decision' => $decision, 'decided_by' => $adminName]);
        } catch (\Exception $e) {
            return $this->respondWithError('SERVER_ERROR', 'Failed to archive message', null, 500);
        }
    }

    /** POST /api/v2/admin/broker/messages/{id}/flag */
    public function flagMessage(int $id): JsonResponse
    {
        $adminId = $this->requireAdmin();
        $isSuperAdmin = $this->isSuperAdmin();
        $tenantId = TenantContext::getId();
        $reason = trim($this->input('reason', ''));
        $severity = $this->input('severity', 'concern');

        if (empty($reason)) {
            return $this->respondWithError('VALIDATION_ERROR', 'A reason is required to flag a message', 'reason');
        }

        $allowedSeverities = ['info', 'warning', 'concern', 'urgent'];
        if (!in_array($severity, $allowedSeverities)) {
            $severity = 'concern';
        }

        try {
            if ($isSuperAdmin) {
                $message = DB::selectOne("SELECT id, tenant_id FROM broker_message_copies WHERE id = ?", [$id]);
            } else {
                $message = DB::selectOne("SELECT id, tenant_id FROM broker_message_copies WHERE id = ? AND tenant_id = ?", [$id, $tenantId]);
            }

            if (!$message) {
                return $this->respondWithError('NOT_FOUND', 'Message not found', null, 404);
            }

            $recordTenantId = (int) $message->tenant_id;
            DB::update(
                "UPDATE broker_message_copies SET flagged = 1, flag_reason = ?, flag_severity = ?, reviewed_by = ?, reviewed_at = NOW() WHERE id = ? AND tenant_id = ?",
                [$reason, $severity, $adminId, $id, $recordTenantId]
            );

            return $this->respondWithData(['id' => $id, 'flagged' => true, 'flag_reason' => $reason, 'flag_severity' => $severity]);
        } catch (\Exception $e) {
            return $this->respondWithError('SERVER_ERROR', 'Failed to flag message', null, 500);
        }
    }

    // ============================================
    // MONITORING
    // ============================================

    /** GET /api/v2/admin/broker/monitoring */
    public function monitoring(): JsonResponse
    {
        $this->requireAdmin();
        $isSuperAdmin = $this->isSuperAdmin();
        $tenantId = TenantContext::getId();

        try {
            $conditions = ['umr.under_monitoring = 1', '(umr.monitoring_expires_at IS NULL OR umr.monitoring_expires_at > NOW())'];
            $params = [];

            $effectiveTenantId = $this->resolveEffectiveTenantId($isSuperAdmin, $tenantId);
            if ($effectiveTenantId !== null) {
                $conditions[] = 'umr.tenant_id = ?';
                $params[] = $effectiveTenantId;
            }

            $where = implode(' AND ', $conditions);

            $items = DB::select(
                "SELECT umr.*, CONCAT(u.first_name, ' ', u.last_name) as user_name, t.name as tenant_name
                FROM user_messaging_restrictions umr
                LEFT JOIN users u ON umr.user_id = u.id
                LEFT JOIN tenants t ON umr.tenant_id = t.id
                WHERE {$where}
                ORDER BY umr.monitoring_started_at DESC",
                $params
            );
            $items = array_map(fn($r) => (array)$r, $items);

            foreach ($items as &$item) {
                $item['under_monitoring'] = (bool) ($item['under_monitoring'] ?? false);
                $item['messaging_disabled'] = (bool) ($item['messaging_disabled'] ?? false);
                $item['tenant_name'] = $item['tenant_name'] ?? 'Unknown';
            }
            unset($item);

            return $this->respondWithData($items);
        } catch (\Exception $e) {
            return $this->respondWithData([]);
        }
    }

    /** POST /api/v2/admin/broker/users/{userId}/monitoring */
    public function setMonitoring(int $userId): JsonResponse
    {
        $adminId = $this->requireAdmin();
        $isSuperAdmin = $this->isSuperAdmin();
        $tenantId = TenantContext::getId();
        $underMonitoring = (bool) $this->input('under_monitoring', true);
        $reason = trim($this->input('reason', ''));
        $messagingDisabled = (bool) $this->input('messaging_disabled', false);
        $expiresDays = $this->input('expires_days', null);

        try {
            if ($isSuperAdmin) {
                $user = DB::selectOne("SELECT id, tenant_id, first_name, last_name FROM users WHERE id = ?", [$userId]);
            } else {
                $user = DB::selectOne("SELECT id, tenant_id, first_name, last_name FROM users WHERE id = ? AND tenant_id = ?", [$userId, $tenantId]);
            }

            if (!$user) {
                return $this->respondWithError('NOT_FOUND', 'User not found', null, 404);
            }

            $userTenantId = (int) $user->tenant_id;
            $userName = trim(($user->first_name ?? '') . ' ' . ($user->last_name ?? ''));

            $existing = DB::selectOne("SELECT id FROM user_messaging_restrictions WHERE user_id = ? AND tenant_id = ?", [$userId, $userTenantId]);

            if ($underMonitoring) {
                if (empty($reason)) {
                    return $this->respondWithError('VALIDATION_ERROR', 'A reason is required to set monitoring', 'reason');
                }

                $expiresAt = null;
                if ($expiresDays !== null && (int) $expiresDays > 0) {
                    $expiresAt = date('Y-m-d H:i:s', strtotime('+' . (int) $expiresDays . ' days'));
                }

                if ($existing) {
                    DB::update(
                        "UPDATE user_messaging_restrictions SET under_monitoring = 1, monitoring_reason = ?, restriction_reason = ?, messaging_disabled = ?, monitoring_started_at = NOW(), monitoring_expires_at = ?, restricted_by = ? WHERE user_id = ? AND tenant_id = ?",
                        [$reason, $reason, $messagingDisabled ? 1 : 0, $expiresAt, $adminId, $userId, $userTenantId]
                    );
                } else {
                    DB::insert(
                        "INSERT INTO user_messaging_restrictions (user_id, tenant_id, under_monitoring, monitoring_reason, restriction_reason, messaging_disabled, monitoring_started_at, monitoring_expires_at, restricted_by) VALUES (?, ?, 1, ?, ?, ?, NOW(), ?, ?)",
                        [$userId, $userTenantId, $reason, $reason, $messagingDisabled ? 1 : 0, $expiresAt, $adminId]
                    );
                }

                AuditLogService::log('user_monitoring_added', null, $adminId, [
                    'user_id' => $userId, 'user_name' => $userName, 'reason' => $reason,
                    'messaging_disabled' => $messagingDisabled,
                    'expires_days' => $expiresDays ? (int) $expiresDays : null, 'expires_at' => $expiresAt,
                ]);

                try {
                    $msg = $messagingDisabled
                        ? 'Your messaging has been temporarily restricted by your timebank coordinator.'
                        : 'Your account has been placed under review by your timebank coordinator.';
                    Notification::createNotification($userId, $msg, '/messages', 'system', true);
                } catch (\Throwable $e) { \Log::warning('[AdminBroker] monitoring notification failed', ['user_id' => $userId, 'error' => $e->getMessage()]); }

                return $this->respondWithData(['user_id' => $userId, 'under_monitoring' => true]);
            } else {
                if ($existing) {
                    // Check if monitoring was set by safeguarding triggers — warn admin
                    $existingRow = DB::selectOne(
                        "SELECT monitoring_reason, requires_broker_approval FROM user_messaging_restrictions WHERE user_id = ? AND tenant_id = ?",
                        [$userId, $userTenantId]
                    );
                    $isSafeguardingSet = $existingRow && str_starts_with($existingRow->monitoring_reason ?? '', 'Safeguarding:');

                    // Preserve audit trail: don't null out monitoring_reason or monitoring_started_at
                    // They record WHY monitoring was originally set, which is legally required
                    DB::update(
                        "UPDATE user_messaging_restrictions
                         SET under_monitoring = 0, messaging_disabled = 0, monitoring_expires_at = NULL,
                             restriction_reason = CONCAT(COALESCE(restriction_reason, ''), ' [Removed by admin {$adminId}]')
                         WHERE user_id = ? AND tenant_id = ?",
                        [$userId, $userTenantId]
                    );

                    // If safeguarding-set, also clear requires_broker_approval
                    // (admin explicitly chose to remove protections)
                    if ($isSafeguardingSet) {
                        DB::update(
                            "UPDATE user_messaging_restrictions SET requires_broker_approval = 0 WHERE user_id = ? AND tenant_id = ?",
                            [$userId, $userTenantId]
                        );
                    }
                }

                AuditLogService::log('user_monitoring_removed', null, $adminId, ['user_id' => $userId, 'user_name' => $userName]);

                try {
                    Notification::createNotification($userId, 'Your messaging restrictions have been lifted.', '/messages', 'system', true);
                } catch (\Throwable $e) { \Log::warning('[AdminBroker] restrictions-lifted notification failed', ['user_id' => $userId, 'error' => $e->getMessage()]); }

                return $this->respondWithData(['user_id' => $userId, 'under_monitoring' => false]);
            }
        } catch (\Exception $e) {
            return $this->respondWithError('SERVER_ERROR', 'Failed to update monitoring', null, 500);
        }
    }

    // ============================================
    // ARCHIVES
    // ============================================

    /** GET /api/v2/admin/broker/archives */
    public function archives(): JsonResponse
    {
        $this->requireAdmin();
        $isSuperAdmin = $this->isSuperAdmin();
        $tenantId = TenantContext::getId();

        $page = $this->queryInt('page', 1, 1);
        $perPage = $this->queryInt('per_page', 20, 1, 100);
        $decision = $this->query('decision');
        $search = $this->query('search');
        $from = $this->query('from');
        $to = $this->query('to');
        $offset = ($page - 1) * $perPage;

        try {
            $conditions = [];
            $params = [];

            $effectiveTenantId = $this->resolveEffectiveTenantId($isSuperAdmin, $tenantId);
            if ($effectiveTenantId !== null) {
                $conditions[] = 'bra.tenant_id = ?';
                $params[] = $effectiveTenantId;
            }
            if ($decision && $decision !== 'all') {
                $conditions[] = 'bra.decision = ?';
                $params[] = $decision;
            }
            if ($search) {
                $conditions[] = '(bra.sender_name LIKE ? OR bra.receiver_name LIKE ?)';
                $params[] = "%{$search}%";
                $params[] = "%{$search}%";
            }
            if ($from) {
                $conditions[] = 'bra.decided_at >= ?';
                $params[] = $from;
            }
            if ($to) {
                $conditions[] = 'bra.decided_at <= ?';
                $params[] = $to . ' 23:59:59';
            }

            $where = !empty($conditions) ? implode(' AND ', $conditions) : '1=1';

            $countRow = DB::selectOne("SELECT COUNT(*) as cnt FROM broker_review_archives bra WHERE {$where}", $params);
            $total = (int) ($countRow->cnt ?? 0);

            $queryParams = array_merge($params, [$perPage, $offset]);
            $items = DB::select(
                "SELECT bra.id, bra.tenant_id, bra.broker_copy_id, bra.sender_id, bra.sender_name, bra.receiver_id, bra.receiver_name,
                    bra.related_listing_id, bra.listing_title, bra.copy_reason, bra.decision, bra.decision_notes,
                    bra.decided_by, bra.decided_by_name, bra.decided_at, bra.flag_reason, bra.flag_severity, bra.created_at,
                    t.name as tenant_name
                FROM broker_review_archives bra
                LEFT JOIN tenants t ON bra.tenant_id = t.id
                WHERE {$where}
                ORDER BY bra.decided_at DESC
                LIMIT ? OFFSET ?",
                $queryParams
            );
            $items = array_map(fn($r) => (array)$r, $items);

            foreach ($items as &$item) {
                $item['tenant_name'] = $item['tenant_name'] ?? 'Unknown';
            }
            unset($item);

            return $this->respondWithPaginatedCollection($items, $total, $page, $perPage);
        } catch (\Exception $e) {
            return $this->respondWithPaginatedCollection([], 0, $page, $perPage);
        }
    }

    /** GET /api/v2/admin/broker/archives/{id} */
    public function showArchive(int $id): JsonResponse
    {
        $this->requireAdmin();
        $isSuperAdmin = $this->isSuperAdmin();
        $tenantId = TenantContext::getId();

        try {
            if ($isSuperAdmin) {
                $archive = DB::selectOne(
                    "SELECT bra.*, t.name as tenant_name FROM broker_review_archives bra LEFT JOIN tenants t ON bra.tenant_id = t.id WHERE bra.id = ?",
                    [$id]
                );
            } else {
                $archive = DB::selectOne(
                    "SELECT bra.*, t.name as tenant_name FROM broker_review_archives bra LEFT JOIN tenants t ON bra.tenant_id = t.id WHERE bra.id = ? AND bra.tenant_id = ?",
                    [$id, $tenantId]
                );
            }

            if (!$archive) {
                return $this->respondWithError('NOT_FOUND', 'Archive not found', null, 404);
            }

            $archive = (array) $archive;
            $archive['tenant_name'] = $archive['tenant_name'] ?? 'Unknown';

            if (!empty($archive['conversation_snapshot'])) {
                $archive['conversation_snapshot'] = json_decode($archive['conversation_snapshot'], true) ?? [];
            } else {
                $archive['conversation_snapshot'] = [];
            }

            return $this->respondWithData($archive);
        } catch (\Exception $e) {
            return $this->respondWithError('SERVER_ERROR', 'Failed to load archive', null, 500);
        }
    }

    // ============================================
    // CONFIGURATION
    // ============================================

    /** GET /api/v2/admin/broker/configuration */
    public function getConfiguration(): JsonResponse
    {
        $this->requireAdmin();
        $isSuperAdmin = $this->isSuperAdmin();
        $tenantId = TenantContext::getId();
        $effectiveTenantId = $this->resolveEffectiveTenantId($isSuperAdmin, $tenantId);

        $defaults = [
            'broker_messaging_enabled' => true, 'broker_copy_all_messages' => false,
            'broker_copy_threshold_hours' => 5, 'new_member_monitoring_days' => 30,
            'require_exchange_for_listings' => false, 'risk_tagging_enabled' => true,
            'auto_flag_high_risk' => true, 'require_approval_high_risk' => false,
            'notify_on_high_risk_match' => true, 'broker_approval_required' => true,
            'auto_approve_low_risk' => false, 'exchange_timeout_days' => 7,
            'max_hours_without_approval' => 5, 'confirmation_deadline_hours' => 48,
            'allow_hour_adjustment' => false, 'max_hour_variance_percent' => 20,
            'expiry_hours' => 168, 'broker_visible_to_members' => false,
            'show_broker_name' => false, 'broker_contact_email' => '',
            'copy_first_contact' => true, 'copy_new_member_messages' => true,
            'copy_high_risk_listing_messages' => true, 'random_sample_percentage' => 0,
            'retention_days' => 90, 'vetting_enabled' => false,
            'insurance_enabled' => false, 'enforce_vetting_on_exchanges' => false,
            'enforce_insurance_on_exchanges' => false, 'vetting_expiry_warning_days' => 30,
            'insurance_expiry_warning_days' => 30,
        ];

        $scopeTenantId = $effectiveTenantId ?? $tenantId;

        try {
            if ($effectiveTenantId === null) {
                $rows = DB::select(
                    "SELECT ts.tenant_id, ts.setting_value, t.name as tenant_name
                     FROM tenant_settings ts LEFT JOIN tenants t ON ts.tenant_id = t.id
                     WHERE ts.setting_key = 'broker_config' ORDER BY t.name ASC"
                );

                $allConfigs = [];
                foreach ($rows as $r) {
                    $saved = json_decode($r->setting_value, true) ?? [];
                    $allConfigs[] = [
                        'tenant_id' => (int) $r->tenant_id,
                        'tenant_name' => $r->tenant_name ?? 'Unknown',
                        'config' => array_merge($defaults, $saved),
                    ];
                }
                return $this->respondWithData($allConfigs);
            }

            $row = DB::selectOne(
                "SELECT setting_value FROM tenant_settings WHERE tenant_id = ? AND setting_key = 'broker_config'",
                [$scopeTenantId]
            );

            $config = $defaults;
            if ($row && !empty($row->setting_value)) {
                $saved = json_decode($row->setting_value, true) ?? [];
                $config = array_merge($defaults, $saved);
            }

            return $this->respondWithData($config);
        } catch (\Exception $e) {
            return $this->respondWithData($defaults);
        }
    }

    /** PUT /api/v2/admin/broker/configuration */
    public function saveConfiguration(): JsonResponse
    {
        $this->requireAdmin();
        $isSuperAdmin = $this->isSuperAdmin();
        $tenantId = TenantContext::getId();

        $body = $this->getAllInput();

        $targetTenantId = $tenantId;
        if ($isSuperAdmin && !empty($body['tenant_id'])) {
            $targetTenantId = (int) $body['tenant_id'];
        }

        $allowedKeys = [
            'broker_messaging_enabled', 'broker_copy_all_messages', 'broker_copy_threshold_hours',
            'new_member_monitoring_days', 'require_exchange_for_listings',
            'risk_tagging_enabled', 'auto_flag_high_risk', 'require_approval_high_risk',
            'notify_on_high_risk_match', 'broker_approval_required', 'auto_approve_low_risk',
            'exchange_timeout_days', 'max_hours_without_approval', 'confirmation_deadline_hours',
            'allow_hour_adjustment', 'max_hour_variance_percent', 'expiry_hours',
            'broker_visible_to_members', 'show_broker_name', 'broker_contact_email',
            'copy_first_contact', 'copy_new_member_messages', 'copy_high_risk_listing_messages',
            'random_sample_percentage', 'retention_days',
            'vetting_enabled', 'insurance_enabled',
            'enforce_vetting_on_exchanges', 'enforce_insurance_on_exchanges',
            'vetting_expiry_warning_days', 'insurance_expiry_warning_days',
        ];

        $config = [];
        foreach ($allowedKeys as $key) {
            if (array_key_exists($key, $body)) {
                $config[$key] = $body[$key];
            }
        }

        try {
            $existing = DB::selectOne(
                "SELECT id FROM tenant_settings WHERE tenant_id = ? AND setting_key = 'broker_config'",
                [$targetTenantId]
            );

            $json = json_encode($config);
            if ($existing) {
                DB::update(
                    "UPDATE tenant_settings SET setting_value = ?, updated_at = NOW() WHERE tenant_id = ? AND setting_key = 'broker_config'",
                    [$json, $targetTenantId]
                );
            } else {
                DB::insert(
                    "INSERT INTO tenant_settings (tenant_id, setting_key, setting_value, created_at, updated_at) VALUES (?, 'broker_config', ?, NOW(), NOW())",
                    [$targetTenantId, $json]
                );
            }

            $workflowKeys = [
                'require_broker_approval', 'auto_approve_low_risk', 'max_hours_without_approval',
                'exchange_workflow_enabled', 'require_broker_approval_new_members',
                'require_broker_approval_high_risk', 'require_broker_approval_over_hours',
            ];
            $workflowConfig = [];
            foreach ($workflowKeys as $key) {
                if (array_key_exists($key, $body)) {
                    $workflowConfig[$key] = $body[$key];
                }
            }
            if (!empty($workflowConfig)) {
                $this->brokerControlConfigService->updateConfig($workflowConfig);
            }

            return $this->respondWithData($config);
        } catch (\Exception $e) {
            return $this->respondWithError('SERVER_ERROR', 'Failed to save configuration', null, 500);
        }
    }

    /** GET /api/v2/admin/broker/unreviewed-count */
    public function unreviewedCount(): JsonResponse
    {
        $this->requireAdmin();

        $count = $this->brokerMessageVisibilityService->countUnreviewed();

        return $this->respondWithData(['count' => $count]);
    }

    // ============================================
    // PRIVATE HELPERS
    // ============================================

    private function notifyAdminsOfRiskTagChange(int $listingId, string $riskLevel, int $brokerId): void
    {
        try {
            $listing = DB::selectOne(
                "SELECT l.title, u.name as owner_name FROM listings l LEFT JOIN users u ON l.user_id = u.id WHERE l.id = ?",
                [$listingId]
            );

            if ($listing) {
                $this->notificationDispatcher->notifyAdmins(
                    'listing_risk_tagged',
                    [
                        'listing_id' => $listingId,
                        'listing_title' => $listing->title ?? 'Unknown',
                        'owner_name' => $listing->owner_name ?? 'Unknown',
                        'risk_level' => $riskLevel,
                        'tagged_by' => $brokerId,
                    ],
                    "Listing '{$listing->title}' tagged as {$riskLevel} risk"
                );
            }
        } catch (\Exception $e) {
            error_log("Failed to notify admins of risk tag: " . $e->getMessage());
        }
    }
}

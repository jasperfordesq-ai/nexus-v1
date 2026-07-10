<?php
// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

namespace App\Http\Controllers\Api;

use App\Services\AuditLogService;
use App\Services\SmartMatchingAnalyticsService;
use App\Services\SmartMatchingEngine;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;
use App\Core\TenantContext;
use App\Services\MatchApprovalWorkflowService;

/**
 * AdminMatchingController -- Admin matching approval, configuration, cache, and statistics.
 *
 * The five approval endpoints (index/approvalStats/show/approve/reject) are
 * broker-or-admin — reviewing proposed matches is a core broker duty and the
 * broker panel surfaces them at /broker/match-approvals. Configuration, cache
 * and analytics stay admin-only.
 */
class AdminMatchingController extends BaseApiController
{
    protected bool $isV2Api = true;

    public function __construct(
        private readonly SmartMatchingEngine $smartMatchingEngine,
        private readonly SmartMatchingAnalyticsService $smartMatchingAnalyticsService,
        private readonly MatchApprovalWorkflowService $matchApprovalWorkflowService,
        private readonly AuditLogService $auditLogService,
    ) {}

    /**
     * Self-dealing guard for approve/reject: a broker must not review a match
     * they are a party to (either as the matched member or as the listing
     * owner) — they could otherwise wave through matches that benefit
     * themselves. Admin-tier callers retain full latitude, mirroring the
     * adjust-balance guard in AdminTimebankingController.
     *
     * Returns an error response to short-circuit with, or null when allowed.
     */
    private function guardBrokerNotParty(int $approvalId, int $callerId): ?JsonResponse
    {
        if ($this->callerIsAdminTier()) {
            return null;
        }

        $row = DB::selectOne(
            "SELECT user_id, listing_owner_id FROM match_approvals WHERE id = ? AND tenant_id = ?",
            [$approvalId, $this->getTenantId()]
        );

        if ($row && ($callerId === (int) $row->user_id || $callerId === (int) $row->listing_owner_id)) {
            return $this->respondWithError('AUTH_INSUFFICIENT_PERMISSIONS', __('api.broker_cannot_review_own_match'), null, 403);
        }

        return null;
    }

    /** GET /api/v2/admin/matching */
    public function index(): JsonResponse
    {
        $this->requireBrokerOrAdmin();
        $tenantId = $this->getTenantId();

        $page = $this->queryInt('page', 1, 1);
        $limit = $this->queryInt('limit', 20, 1, 100);
        $offset = ($page - 1) * $limit;
        $status = $this->query('status', 'all');

        $conditions = ['ma.tenant_id = ?'];
        $params = [$tenantId];

        if ($status && $status !== 'all' && in_array($status, ['pending', 'approved', 'rejected'])) {
            $conditions[] = 'ma.status = ?';
            $params[] = $status;
        }

        $where = implode(' AND ', $conditions);

        $total = (int) DB::selectOne("SELECT COUNT(*) as cnt FROM match_approvals ma WHERE {$where}", $params)->cnt;

        $items = DB::select(
            "SELECT ma.id, ma.user_id, ma.listing_id, ma.listing_owner_id,
                    ma.match_score, ma.match_type, ma.match_reasons, ma.distance_km,
                    ma.status, ma.submitted_at, ma.reviewed_by, ma.reviewed_at, ma.review_notes,
                    CONCAT(COALESCE(u.first_name, ''), ' ', COALESCE(u.last_name, '')) as user_1_name,
                    u.email as user_1_email, u.avatar_url as user_1_avatar,
                    CONCAT(COALESCE(o.first_name, ''), ' ', COALESCE(o.last_name, '')) as user_2_name,
                    o.email as user_2_email, o.avatar_url as user_2_avatar,
                    l.title as listing_title, l.type as listing_type, l.description as listing_description,
                    CONCAT(COALESCE(r.first_name, ''), ' ', COALESCE(r.last_name, '')) as reviewer_name
             FROM match_approvals ma
             JOIN users u ON ma.user_id = u.id
             JOIN users o ON ma.listing_owner_id = o.id
             LEFT JOIN listings l ON ma.listing_id = l.id
             LEFT JOIN users r ON ma.reviewed_by = r.id
             WHERE {$where}
             ORDER BY CASE WHEN ma.status = 'pending' THEN 0 ELSE 1 END, ma.submitted_at DESC
             LIMIT ? OFFSET ?",
            array_merge($params, [$limit, $offset])
        );

        $formatted = array_map(function ($row) {
            $reasons = $row->match_reasons ?? null;
            if (is_string($reasons)) $reasons = json_decode($reasons, true) ?: [];
            return [
                'id' => (int) $row->id,
                'user_1_id' => (int) $row->user_id, 'user_1_name' => trim($row->user_1_name ?? ''),
                'user_1_email' => $row->user_1_email ?? '', 'user_1_avatar' => $row->user_1_avatar ?? null,
                'user_2_id' => (int) $row->listing_owner_id, 'user_2_name' => trim($row->user_2_name ?? ''),
                'user_2_email' => $row->user_2_email ?? '', 'user_2_avatar' => $row->user_2_avatar ?? null,
                'listing_id' => $row->listing_id ? (int) $row->listing_id : null,
                'listing_title' => $row->listing_title ?? null, 'listing_type' => $row->listing_type ?? null,
                'listing_description' => $row->listing_description ?? null,
                'match_score' => (float) ($row->match_score ?? 0), 'match_type' => $row->match_type ?? 'one_way',
                'match_reasons' => $reasons,
                'distance_km' => $row->distance_km !== null ? (float) $row->distance_km : null,
                'status' => $row->status ?? 'pending', 'notes' => $row->review_notes ?? null,
                'created_at' => $row->submitted_at ?? null, 'reviewed_at' => $row->reviewed_at ?? null,
                'reviewer_id' => $row->reviewed_by ? (int) $row->reviewed_by : null,
                'reviewer_name' => $row->reviewer_name ? trim($row->reviewer_name) : null,
            ];
        }, $items);

        return $this->respondWithPaginatedCollection($formatted, $total, $page, $limit);
    }

    /** GET /api/v2/admin/matching/approval-stats */
    public function approvalStats(): JsonResponse
    {
        $this->requireBrokerOrAdmin();
        $days = $this->queryInt('days', 30, 1);
        return $this->respondWithData($this->matchApprovalWorkflowService->getStatistics($days));
    }

    /** GET /api/v2/admin/matching/{id} */
    public function show(int $id): JsonResponse
    {
        $this->requireBrokerOrAdmin();
        $tenantId = $this->getTenantId();

        $rowObj = DB::selectOne(
            "SELECT ma.*,
                    CONCAT(COALESCE(u.first_name, ''), ' ', COALESCE(u.last_name, '')) as user_1_name,
                    u.email as user_1_email, u.avatar_url as user_1_avatar, u.bio as user_1_bio, u.location as user_1_location,
                    CONCAT(COALESCE(o.first_name, ''), ' ', COALESCE(o.last_name, '')) as user_2_name,
                    o.email as user_2_email, o.avatar_url as user_2_avatar, o.bio as user_2_bio, o.location as user_2_location,
                    l.title as listing_title, l.type as listing_type, l.description as listing_description, l.status as listing_status,
                    c.name as category_name,
                    CONCAT(COALESCE(r.first_name, ''), ' ', COALESCE(r.last_name, '')) as reviewer_name
             FROM match_approvals ma
             JOIN users u ON ma.user_id = u.id
             JOIN users o ON ma.listing_owner_id = o.id
             LEFT JOIN listings l ON ma.listing_id = l.id
             LEFT JOIN categories c ON l.category_id = c.id
             LEFT JOIN users r ON ma.reviewed_by = r.id
             WHERE ma.id = ? AND ma.tenant_id = ?",
            [$id, $tenantId]
        );
        $row = $rowObj ? (array)$rowObj : null;

        if (!$row) {
            return $this->respondWithError('NOT_FOUND', __('api.not_found', ['model' => 'Match approval']), null, 404);
        }

        $reasons = $row['match_reasons'] ?? null;
        if (is_string($reasons)) { $reasons = json_decode($reasons, true) ?: []; }

        return $this->respondWithData([
            'id' => (int) $row['id'],
            'user_1_id' => (int) $row['user_id'], 'user_1_name' => trim($row['user_1_name'] ?? ''),
            'user_1_email' => $row['user_1_email'] ?? '', 'user_1_avatar' => $row['user_1_avatar'] ?? null,
            'user_1_bio' => $row['user_1_bio'] ?? null, 'user_1_location' => $row['user_1_location'] ?? null,
            'user_2_id' => (int) $row['listing_owner_id'], 'user_2_name' => trim($row['user_2_name'] ?? ''),
            'user_2_email' => $row['user_2_email'] ?? '', 'user_2_avatar' => $row['user_2_avatar'] ?? null,
            'user_2_bio' => $row['user_2_bio'] ?? null, 'user_2_location' => $row['user_2_location'] ?? null,
            'listing_id' => $row['listing_id'] ? (int) $row['listing_id'] : null,
            'listing_title' => $row['listing_title'] ?? null, 'listing_type' => $row['listing_type'] ?? null,
            'listing_description' => $row['listing_description'] ?? null, 'listing_status' => $row['listing_status'] ?? null,
            'category_name' => $row['category_name'] ?? null,
            'match_score' => (float) ($row['match_score'] ?? 0), 'match_type' => $row['match_type'] ?? 'one_way',
            'match_reasons' => $reasons, 'distance_km' => $row['distance_km'] !== null ? (float) $row['distance_km'] : null,
            'status' => $row['status'] ?? 'pending', 'notes' => $row['review_notes'] ?? null,
            'created_at' => $row['submitted_at'] ?? $row['created_at'] ?? null,
            'reviewed_at' => $row['reviewed_at'] ?? null,
            'reviewer_id' => $row['reviewed_by'] ? (int) $row['reviewed_by'] : null,
            'reviewer_name' => $row['reviewer_name'] ? trim($row['reviewer_name']) : null,
        ]);
    }

    /** POST /api/v2/admin/matching/{id}/approve */
    public function approve(int $id): JsonResponse
    {
        $reviewerId = $this->requireBrokerOrAdmin();
        if ($guard = $this->guardBrokerNotParty($id, $reviewerId)) return $guard;

        $notes = trim($this->input('notes', ''));
        $success = $this->matchApprovalWorkflowService->approveMatch($id, $reviewerId, $notes);
        if (!$success) return $this->respondWithError('NOT_FOUND', __('api.not_found', ['model' => 'Match approval or already reviewed']), null, 404);

        $this->auditLogService->log('match_approved', null, $reviewerId, ['approval_id' => $id]);

        return $this->respondWithData(['approved' => true, 'id' => $id]);
    }

    /** POST /api/v2/admin/matching/{id}/reject */
    public function reject(int $id): JsonResponse
    {
        $reviewerId = $this->requireBrokerOrAdmin();
        if ($guard = $this->guardBrokerNotParty($id, $reviewerId)) return $guard;

        $reason = trim($this->input('reason', ''));
        if (empty($reason)) return $this->respondWithError('VALIDATION_ERROR', __('api.reason_required'), 'reason', 422);

        $success = $this->matchApprovalWorkflowService->rejectMatch($id, $reviewerId, $reason);
        if (!$success) return $this->respondWithError('NOT_FOUND', __('api.not_found', ['model' => 'Match approval or already reviewed']), null, 404);

        $this->auditLogService->log('match_rejected', null, $reviewerId, ['approval_id' => $id, 'reason' => $reason]);

        return $this->respondWithData(['rejected' => true, 'id' => $id]);
    }

    /** GET /api/v2/admin/matching/config */
    public function getConfig(): JsonResponse
    {
        $this->requireAdmin();
        $config = $this->smartMatchingEngine->getConfig();
        $weights = $config['weights'] ?? [];
        $proximity = $config['proximity'] ?? [];

        return $this->respondWithData([
            'category_weight' => (float) ($weights['category'] ?? 0.25),
            'skill_weight' => (float) ($weights['skill'] ?? 0.20),
            'proximity_weight' => (float) ($weights['proximity'] ?? 0.25),
            'freshness_weight' => (float) ($weights['freshness'] ?? 0.10),
            'reciprocity_weight' => (float) ($weights['reciprocity'] ?? 0.15),
            'quality_weight' => (float) ($weights['quality'] ?? 0.05),
            'proximity_bands' => [
                ['distance_km' => (int) ($proximity['walking_km'] ?? 5), 'score' => 1.0],
                ['distance_km' => (int) ($proximity['local_km'] ?? 15), 'score' => 0.9],
                ['distance_km' => (int) ($proximity['city_km'] ?? 30), 'score' => 0.7],
                ['distance_km' => (int) ($proximity['regional_km'] ?? 50), 'score' => 0.5],
                ['distance_km' => (int) ($proximity['max_km'] ?? 100), 'score' => 0.2],
            ],
            'enabled' => (bool) ($config['enabled'] ?? true),
            'broker_approval_enabled' => (bool) ($config['broker_approval_enabled'] ?? true),
            'max_distance_km' => (int) ($config['max_distance_km'] ?? 50),
            'min_match_score' => (int) ($config['min_match_score'] ?? 40),
            'hot_match_threshold' => (int) ($config['hot_match_threshold'] ?? 80),
            'gates' => [
                'geo_hard_gate' => (bool) ($config['gates']['geo_hard_gate'] ?? true),
                'missing_coords_mode' => (string) ($config['gates']['missing_coords_mode'] ?? 'remote_only'),
                'dormancy_days' => (int) ($config['gates']['dormancy_days'] ?? 90),
                'owner_dismissal_threshold' => (int) ($config['gates']['owner_dismissal_threshold'] ?? 3),
            ],
            'engine_version' => (int) ($config['engine_version'] ?? 2),
            'pillars' => [
                'relevance' => (float) ($config['pillars']['relevance'] ?? 0.45),
                'feasibility' => (float) ($config['pillars']['feasibility'] ?? 0.35),
                'trust' => (float) ($config['pillars']['trust'] ?? 0.20),
            ],
            'adjustments' => [
                'mutual_bonus' => (float) ($config['adjustments']['mutual_bonus'] ?? 8),
                'freshness_max' => (float) ($config['adjustments']['freshness_max'] ?? 4),
                'semantic_boost' => (float) ($config['adjustments']['semantic_boost'] ?? 8),
                'knn_boost' => (float) ($config['adjustments']['knn_boost'] ?? 6),
            ],
            'ai' => [
                'semantic_signal' => (bool) ($config['ai']['semantic_signal'] ?? true),
                'llm_explanations' => (bool) ($config['ai']['llm_explanations'] ?? true),
                'explanation_top_n' => (int) ($config['ai']['explanation_top_n'] ?? 5),
                // Whether the AI layer can actually run for this tenant
                // (keys + cost limits) — read-only status for the admin UI.
                'available' => \App\Services\AI\AIServiceFactory::isEnabled(),
            ],
        ]);
    }

    /** PUT /api/v2/admin/matching/config */
    public function updateConfig(): JsonResponse
    {
        $this->requireAdmin();
        $input = $this->getAllInput();
        $tenantId = $this->getTenantId();

        $tenantRow = DB::selectOne("SELECT configuration FROM tenants WHERE id = ?", [$tenantId]);
        $tenant = $tenantRow ? (array)$tenantRow : null;
        $config = $tenant && $tenant['configuration'] ? json_decode($tenant['configuration'], true) : [];
        $config['algorithms'] = $config['algorithms'] ?? [];
        $existing = $config['algorithms']['smart_matching'] ?? [];

        $proximityBands = $input['proximity_bands'] ?? null;
        $proximity = $existing['proximity'] ?? ['walking_km' => 5, 'local_km' => 15, 'city_km' => 30, 'regional_km' => 50, 'max_km' => 100];

        if (is_array($proximityBands) && count($proximityBands) >= 5) {
            usort($proximityBands, fn($a, $b) => ($a['distance_km'] ?? 0) - ($b['distance_km'] ?? 0));
            $proximity = [
                'walking_km' => (int) ($proximityBands[0]['distance_km'] ?? 5),
                'local_km' => (int) ($proximityBands[1]['distance_km'] ?? 15),
                'city_km' => (int) ($proximityBands[2]['distance_km'] ?? 30),
                'regional_km' => (int) ($proximityBands[3]['distance_km'] ?? 50),
                'max_km' => (int) ($proximityBands[4]['distance_km'] ?? 100),
            ];
        }

        // Hard-gate settings (additive; UI may send a partial gates object).
        $gatesIn = is_array($input['gates'] ?? null) ? $input['gates'] : [];
        $gatesExisting = is_array($existing['gates'] ?? null) ? $existing['gates'] : [];
        $missingCoordsMode = (string) ($gatesIn['missing_coords_mode'] ?? $gatesExisting['missing_coords_mode'] ?? 'remote_only');
        if (!in_array($missingCoordsMode, ['remote_only', 'tenant_wide'], true)) {
            $missingCoordsMode = 'remote_only';
        }

        // Merge over the existing block so keys this endpoint doesn't manage
        // (e.g. future pillar/AI settings) survive a save from this UI.
        $config['algorithms']['smart_matching'] = array_merge($existing, [
            'enabled' => (bool) ($input['enabled'] ?? $existing['enabled'] ?? true),
            'broker_approval_enabled' => (bool) ($input['broker_approval_enabled'] ?? $existing['broker_approval_enabled'] ?? true),
            'max_distance_km' => (int) ($input['max_distance_km'] ?? $existing['max_distance_km'] ?? 50),
            'min_match_score' => (int) ($input['min_match_score'] ?? $existing['min_match_score'] ?? 40),
            'hot_match_threshold' => (int) ($input['hot_match_threshold'] ?? $existing['hot_match_threshold'] ?? 80),
            'gates' => [
                'geo_hard_gate' => (bool) ($gatesIn['geo_hard_gate'] ?? $gatesExisting['geo_hard_gate'] ?? true),
                'missing_coords_mode' => $missingCoordsMode,
                'dormancy_days' => max(0, min(3650, (int) ($gatesIn['dormancy_days'] ?? $gatesExisting['dormancy_days'] ?? 90))),
                'owner_dismissal_threshold' => max(1, min(100, (int) ($gatesIn['owner_dismissal_threshold'] ?? $gatesExisting['owner_dismissal_threshold'] ?? 3))),
            ],
            'engine_version' => in_array((int) ($input['engine_version'] ?? $existing['engine_version'] ?? 2), [1, 2], true)
                ? (int) ($input['engine_version'] ?? $existing['engine_version'] ?? 2) : 2,
            'ai' => [
                'semantic_signal' => (bool) ($input['ai']['semantic_signal'] ?? $existing['ai']['semantic_signal'] ?? true),
                'llm_explanations' => (bool) ($input['ai']['llm_explanations'] ?? $existing['ai']['llm_explanations'] ?? true),
                'explanation_top_n' => max(1, min(10, (int) ($input['ai']['explanation_top_n'] ?? $existing['ai']['explanation_top_n'] ?? 5))),
            ],
            'pillars' => [
                'relevance' => max(0.0, min(1.0, (float) ($input['pillars']['relevance'] ?? $existing['pillars']['relevance'] ?? 0.45))),
                'feasibility' => max(0.0, min(1.0, (float) ($input['pillars']['feasibility'] ?? $existing['pillars']['feasibility'] ?? 0.35))),
                'trust' => max(0.0, min(1.0, (float) ($input['pillars']['trust'] ?? $existing['pillars']['trust'] ?? 0.20))),
            ],
            'weights' => [
                'category' => (float) ($input['category_weight'] ?? $existing['weights']['category'] ?? 0.25),
                'skill' => (float) ($input['skill_weight'] ?? $existing['weights']['skill'] ?? 0.20),
                'proximity' => (float) ($input['proximity_weight'] ?? $existing['weights']['proximity'] ?? 0.25),
                'freshness' => (float) ($input['freshness_weight'] ?? $existing['weights']['freshness'] ?? 0.10),
                'reciprocity' => (float) ($input['reciprocity_weight'] ?? $existing['weights']['reciprocity'] ?? 0.15),
                'quality' => (float) ($input['quality_weight'] ?? $existing['weights']['quality'] ?? 0.05),
            ],
            'proximity' => $proximity,
        ]);

        DB::update("UPDATE tenants SET configuration = ? WHERE id = ?", [json_encode($config), $tenantId]);
        $this->smartMatchingEngine->clearCache();

        return $this->respondWithData(['message' => __('api.matching_config_updated')]);
    }

    /** POST /api/v2/admin/matching/clear-cache */
    public function clearCache(): JsonResponse
    {
        $this->requireAdmin();
        $tenantId = $this->getTenantId();

        try {
            $deleted = (int) DB::selectOne("SELECT COUNT(*) as cnt FROM match_cache WHERE tenant_id = ?", [$tenantId])->cnt;
            DB::delete("DELETE FROM match_cache WHERE tenant_id = ?", [$tenantId]);
            $this->smartMatchingEngine->clearCache();
            return $this->respondWithData(['message' => __('api.match_cache_cleared'), 'entries_cleared' => $deleted]);
        } catch (\Exception $e) {
            return $this->respondWithError('SERVER_ERROR', __('api.failed_to_clear_cache'), null, 500);
        }
    }

    /** GET /api/v2/admin/matching/stats */
    public function getStats(): JsonResponse
    {
        $this->requireAdmin();

        try {
            $stats = $this->smartMatchingAnalyticsService->getOverallStats();
            $scoreDistribution = $this->smartMatchingAnalyticsService->getScoreDistribution();
            $distanceDistribution = $this->smartMatchingAnalyticsService->getDistanceDistribution();

            $config = $this->smartMatchingEngine->getConfig();
            $brokerEnabled = (bool) ($config['broker_approval_enabled'] ?? true);

            $pendingApprovals = 0;
            $approvedCount = 0;
            $rejectedCount = 0;
            $tenantId = $this->getTenantId();
            $pendingApprovals = (int) DB::selectOne("SELECT COUNT(*) as cnt FROM match_approvals WHERE tenant_id = ? AND status = 'pending'", [$tenantId])->cnt;
            $approvedCount = (int) DB::selectOne("SELECT COUNT(*) as cnt FROM match_approvals WHERE tenant_id = ? AND status = 'approved'", [$tenantId])->cnt;
            $rejectedCount = (int) DB::selectOne("SELECT COUNT(*) as cnt FROM match_approvals WHERE tenant_id = ? AND status = 'rejected'", [$tenantId])->cnt;

            $totalReviewed = $approvedCount + $rejectedCount;
            $approvalRate = $totalReviewed > 0 ? round(($approvedCount / $totalReviewed) * 100, 1) : 0;

            return $this->respondWithData([
                'overview' => $stats, 'score_distribution' => $scoreDistribution,
                'distance_distribution' => $distanceDistribution,
                'broker_approval_enabled' => $brokerEnabled,
                'pending_approvals' => $pendingApprovals, 'approved_count' => $approvedCount,
                'rejected_count' => $rejectedCount, 'approval_rate' => $approvalRate,
                'gate_impact' => $this->smartMatchingAnalyticsService->getGateImpact(),
                'pillar_averages' => $this->smartMatchingAnalyticsService->getPillarAverages(),
            ]);
        } catch (\Throwable $e) {
            return $this->respondWithError('SERVER_ERROR', __('api.server_error'), null, 500);
        }
    }

}

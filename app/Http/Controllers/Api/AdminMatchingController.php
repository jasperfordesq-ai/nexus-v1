<?php
// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

namespace App\Http\Controllers\Api;

use App\Services\SmartMatchingAnalyticsService;
use App\Services\SmartMatchingEngine;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;
use App\Core\TenantContext;
use App\Services\MatchApprovalWorkflowService;

/**
 * AdminMatchingController -- Admin matching approval, configuration, cache, and statistics.
 *
 * All methods require admin authentication.
 */
class AdminMatchingController extends BaseApiController
{
    protected bool $isV2Api = true;

    public function __construct(
        private readonly SmartMatchingEngine $smartMatchingEngine,
        private readonly SmartMatchingAnalyticsService $smartMatchingAnalyticsService,
        private readonly MatchApprovalWorkflowService $matchApprovalWorkflowService,
    ) {}

    /** GET /api/v2/admin/matching */
    public function index(): JsonResponse
    {
        $this->requireAdmin();
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
        $this->requireAdmin();
        $days = $this->queryInt('days', 30, 1);
        return $this->respondWithData($this->matchApprovalWorkflowService->getStatistics($days));
    }

    /** GET /api/v2/admin/matching/{id} */
    public function show(int $id): JsonResponse
    {
        $this->requireAdmin();
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
        $adminId = $this->requireAdmin();
        $notes = trim($this->input('notes', ''));
        $success = $this->matchApprovalWorkflowService->approveMatch($id, $adminId, $notes);
        if (!$success) return $this->respondWithError('NOT_FOUND', __('api.not_found', ['model' => 'Match approval or already reviewed']), null, 404);
        return $this->respondWithData(['approved' => true, 'id' => $id]);
    }

    /** POST /api/v2/admin/matching/{id}/reject */
    public function reject(int $id): JsonResponse
    {
        $adminId = $this->requireAdmin();
        $reason = trim($this->input('reason', ''));
        if (empty($reason)) return $this->respondWithError('VALIDATION_ERROR', __('api.reason_required'), 'reason', 422);

        $success = $this->matchApprovalWorkflowService->rejectMatch($id, $adminId, $reason);
        if (!$success) return $this->respondWithError('NOT_FOUND', __('api.not_found', ['model' => 'Match approval or already reviewed']), null, 404);
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

        $config['algorithms']['smart_matching'] = [
            'enabled' => (bool) ($input['enabled'] ?? $existing['enabled'] ?? true),
            'broker_approval_enabled' => (bool) ($input['broker_approval_enabled'] ?? $existing['broker_approval_enabled'] ?? true),
            'max_distance_km' => (int) ($input['max_distance_km'] ?? $existing['max_distance_km'] ?? 50),
            'min_match_score' => (int) ($input['min_match_score'] ?? $existing['min_match_score'] ?? 40),
            'hot_match_threshold' => (int) ($input['hot_match_threshold'] ?? $existing['hot_match_threshold'] ?? 80),
            'weights' => [
                'category' => (float) ($input['category_weight'] ?? $existing['weights']['category'] ?? 0.25),
                'skill' => (float) ($input['skill_weight'] ?? $existing['weights']['skill'] ?? 0.20),
                'proximity' => (float) ($input['proximity_weight'] ?? $existing['weights']['proximity'] ?? 0.25),
                'freshness' => (float) ($input['freshness_weight'] ?? $existing['weights']['freshness'] ?? 0.10),
                'reciprocity' => (float) ($input['reciprocity_weight'] ?? $existing['weights']['reciprocity'] ?? 0.15),
                'quality' => (float) ($input['quality_weight'] ?? $existing['weights']['quality'] ?? 0.05),
            ],
            'proximity' => $proximity,
        ];

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
            try {
                $tenantId = $this->getTenantId();
                $pendingApprovals = (int) DB::selectOne("SELECT COUNT(*) as cnt FROM match_approvals WHERE tenant_id = ? AND status = 'pending'", [$tenantId])->cnt;
                $approvedCount = (int) DB::selectOne("SELECT COUNT(*) as cnt FROM match_approvals WHERE tenant_id = ? AND status = 'approved'", [$tenantId])->cnt;
                $rejectedCount = (int) DB::selectOne("SELECT COUNT(*) as cnt FROM match_approvals WHERE tenant_id = ? AND status = 'rejected'", [$tenantId])->cnt;
            } catch (\Throwable $e) {
                \Log::warning('Matching stats approval query failed', ['error' => $e->getMessage()]);
            }

            $totalReviewed = $approvedCount + $rejectedCount;
            $approvalRate = $totalReviewed > 0 ? round(($approvedCount / $totalReviewed) * 100, 1) : 0;

            return $this->respondWithData([
                'overview' => $stats, 'score_distribution' => $scoreDistribution,
                'distance_distribution' => $distanceDistribution,
                'broker_approval_enabled' => $brokerEnabled,
                'pending_approvals' => $pendingApprovals, 'approved_count' => $approvedCount,
                'rejected_count' => $rejectedCount, 'approval_rate' => $approvalRate,
            ]);
        } catch (\Throwable $e) {
            return $this->respondWithError('SERVER_ERROR', __('api.fetch_failed', ['resource' => 'matching stats']), null, 500);
        }
    }

}

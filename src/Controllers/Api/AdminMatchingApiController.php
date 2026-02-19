<?php
// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

namespace Nexus\Controllers\Api;

use Nexus\Core\Database;
use Nexus\Core\TenantContext;
use Nexus\Core\ApiErrorCodes;
use Nexus\Services\MatchApprovalWorkflowService;
use Nexus\Services\SmartMatchingEngine;
use Nexus\Services\SmartMatchingAnalyticsService;

/**
 * AdminMatchingApiController - V2 API for React admin Smart Matching module
 *
 * Provides match approval management, algorithm configuration, analytics,
 * and cache management for the admin panel.
 *
 * Endpoints:
 * - GET    /api/v2/admin/matching/approvals              - List approvals (paginated, filterable by status)
 * - GET    /api/v2/admin/matching/approvals/stats         - Get approval statistics
 * - GET    /api/v2/admin/matching/approvals/{id}          - Get single approval detail
 * - POST   /api/v2/admin/matching/approvals/{id}/approve  - Approve a match
 * - POST   /api/v2/admin/matching/approvals/{id}/reject   - Reject a match
 * - GET    /api/v2/admin/matching/config                  - Get matching config
 * - PUT    /api/v2/admin/matching/config                  - Update matching config
 * - POST   /api/v2/admin/matching/cache/clear             - Clear matching cache
 * - GET    /api/v2/admin/matching/stats                   - Get matching statistics
 */
class AdminMatchingApiController extends BaseApiController
{
    protected bool $isV2Api = true;

    // =========================================================================
    // MATCH APPROVALS
    // =========================================================================

    /**
     * GET /api/v2/admin/matching/approvals
     *
     * Query params: page, limit, status (pending|approved|rejected|all)
     */
    public function index(): void
    {
        $this->requireAdmin();
        $tenantId = TenantContext::getId();

        $page = max(1, (int) ($_GET['page'] ?? 1));
        $limit = min(100, max(1, (int) ($_GET['limit'] ?? 20)));
        $offset = ($page - 1) * $limit;
        $status = $_GET['status'] ?? 'all';

        $conditions = ['ma.tenant_id = ?'];
        $params = [$tenantId];

        if ($status && $status !== 'all') {
            $allowed = ['pending', 'approved', 'rejected'];
            if (in_array($status, $allowed)) {
                $conditions[] = 'ma.status = ?';
                $params[] = $status;
            }
        }

        $where = implode(' AND ', $conditions);

        // Total count
        $total = (int) Database::query(
            "SELECT COUNT(*) as cnt FROM match_approvals ma WHERE {$where}",
            $params
        )->fetch()['cnt'];

        // Paginated results with user + listing details
        $items = Database::query(
            "SELECT ma.id, ma.user_id, ma.listing_id, ma.listing_owner_id,
                    ma.match_score, ma.match_type, ma.match_reasons, ma.distance_km,
                    ma.status, ma.submitted_at, ma.reviewed_by, ma.reviewed_at, ma.review_notes,
                    CONCAT(COALESCE(u.first_name, ''), ' ', COALESCE(u.last_name, '')) as user_1_name,
                    u.email as user_1_email,
                    u.avatar_url as user_1_avatar,
                    CONCAT(COALESCE(o.first_name, ''), ' ', COALESCE(o.last_name, '')) as user_2_name,
                    o.email as user_2_email,
                    o.avatar_url as user_2_avatar,
                    l.title as listing_title,
                    l.type as listing_type,
                    l.description as listing_description,
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
        )->fetchAll();

        $formatted = array_map(function ($row) {
            $reasons = $row['match_reasons'] ?? null;
            if (is_string($reasons)) {
                $reasons = json_decode($reasons, true) ?: [];
            }

            return [
                'id' => (int) $row['id'],
                'user_1_id' => (int) $row['user_id'],
                'user_1_name' => trim($row['user_1_name'] ?? ''),
                'user_1_email' => $row['user_1_email'] ?? '',
                'user_1_avatar' => $row['user_1_avatar'] ?? null,
                'user_2_id' => (int) $row['listing_owner_id'],
                'user_2_name' => trim($row['user_2_name'] ?? ''),
                'user_2_email' => $row['user_2_email'] ?? '',
                'user_2_avatar' => $row['user_2_avatar'] ?? null,
                'listing_id' => $row['listing_id'] ? (int) $row['listing_id'] : null,
                'listing_title' => $row['listing_title'] ?? null,
                'listing_type' => $row['listing_type'] ?? null,
                'listing_description' => $row['listing_description'] ?? null,
                'match_score' => (float) ($row['match_score'] ?? 0),
                'match_type' => $row['match_type'] ?? 'one_way',
                'match_reasons' => $reasons,
                'distance_km' => $row['distance_km'] !== null ? (float) $row['distance_km'] : null,
                'status' => $row['status'] ?? 'pending',
                'notes' => $row['review_notes'] ?? null,
                'created_at' => $row['submitted_at'] ?? $row['created_at'] ?? null,
                'reviewed_at' => $row['reviewed_at'] ?? null,
                'reviewer_id' => $row['reviewed_by'] ? (int) $row['reviewed_by'] : null,
                'reviewer_name' => $row['reviewer_name'] ? trim($row['reviewer_name']) : null,
            ];
        }, $items);

        $this->respondWithPaginatedCollection($formatted, $total, $page, $limit);
    }

    /**
     * GET /api/v2/admin/matching/approvals/stats
     */
    public function approvalStats(): void
    {
        $this->requireAdmin();

        $days = max(1, (int) ($_GET['days'] ?? 30));
        $stats = MatchApprovalWorkflowService::getStatistics($days);

        $this->respondWithData($stats);
    }

    /**
     * GET /api/v2/admin/matching/approvals/{id}
     */
    public function show(int $id): void
    {
        $this->requireAdmin();
        $tenantId = TenantContext::getId();

        $row = Database::query(
            "SELECT ma.*,
                    CONCAT(COALESCE(u.first_name, ''), ' ', COALESCE(u.last_name, '')) as user_1_name,
                    u.email as user_1_email,
                    u.avatar_url as user_1_avatar,
                    u.bio as user_1_bio,
                    u.location as user_1_location,
                    CONCAT(COALESCE(o.first_name, ''), ' ', COALESCE(o.last_name, '')) as user_2_name,
                    o.email as user_2_email,
                    o.avatar_url as user_2_avatar,
                    o.bio as user_2_bio,
                    o.location as user_2_location,
                    l.title as listing_title,
                    l.type as listing_type,
                    l.description as listing_description,
                    l.status as listing_status,
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
        )->fetch();

        if (!$row) {
            $this->respondWithError(ApiErrorCodes::RESOURCE_NOT_FOUND, 'Match approval not found', null, 404);
            return;
        }

        $reasons = $row['match_reasons'] ?? null;
        if (is_string($reasons)) {
            $reasons = json_decode($reasons, true) ?: [];
        }

        $this->respondWithData([
            'id' => (int) $row['id'],
            'user_1_id' => (int) $row['user_id'],
            'user_1_name' => trim($row['user_1_name'] ?? ''),
            'user_1_email' => $row['user_1_email'] ?? '',
            'user_1_avatar' => $row['user_1_avatar'] ?? null,
            'user_1_bio' => $row['user_1_bio'] ?? null,
            'user_1_location' => $row['user_1_location'] ?? null,
            'user_2_id' => (int) $row['listing_owner_id'],
            'user_2_name' => trim($row['user_2_name'] ?? ''),
            'user_2_email' => $row['user_2_email'] ?? '',
            'user_2_avatar' => $row['user_2_avatar'] ?? null,
            'user_2_bio' => $row['user_2_bio'] ?? null,
            'user_2_location' => $row['user_2_location'] ?? null,
            'listing_id' => $row['listing_id'] ? (int) $row['listing_id'] : null,
            'listing_title' => $row['listing_title'] ?? null,
            'listing_type' => $row['listing_type'] ?? null,
            'listing_description' => $row['listing_description'] ?? null,
            'listing_status' => $row['listing_status'] ?? null,
            'category_name' => $row['category_name'] ?? null,
            'match_score' => (float) ($row['match_score'] ?? 0),
            'match_type' => $row['match_type'] ?? 'one_way',
            'match_reasons' => $reasons,
            'distance_km' => $row['distance_km'] !== null ? (float) $row['distance_km'] : null,
            'status' => $row['status'] ?? 'pending',
            'notes' => $row['review_notes'] ?? null,
            'created_at' => $row['submitted_at'] ?? $row['created_at'] ?? null,
            'reviewed_at' => $row['reviewed_at'] ?? null,
            'reviewer_id' => $row['reviewed_by'] ? (int) $row['reviewed_by'] : null,
            'reviewer_name' => $row['reviewer_name'] ? trim($row['reviewer_name']) : null,
        ]);
    }

    /**
     * POST /api/v2/admin/matching/approvals/{id}/approve
     */
    public function approve(int $id): void
    {
        $adminId = $this->requireAdmin();

        $input = $this->getAllInput();
        $notes = trim($input['notes'] ?? '');

        $success = MatchApprovalWorkflowService::approveMatch($id, $adminId, $notes);

        if (!$success) {
            $this->respondWithError(
                ApiErrorCodes::RESOURCE_NOT_FOUND,
                'Match approval not found or already reviewed',
                null,
                404
            );
            return;
        }

        $this->respondWithData(['approved' => true, 'id' => $id]);
    }

    /**
     * POST /api/v2/admin/matching/approvals/{id}/reject
     */
    public function reject(int $id): void
    {
        $adminId = $this->requireAdmin();

        $input = $this->getAllInput();
        $reason = trim($input['reason'] ?? '');

        if (empty($reason)) {
            $this->respondWithError(
                ApiErrorCodes::VALIDATION_ERROR,
                'Rejection reason is required',
                'reason',
                422
            );
            return;
        }

        $success = MatchApprovalWorkflowService::rejectMatch($id, $adminId, $reason);

        if (!$success) {
            $this->respondWithError(
                ApiErrorCodes::RESOURCE_NOT_FOUND,
                'Match approval not found or already reviewed',
                null,
                404
            );
            return;
        }

        $this->respondWithData(['rejected' => true, 'id' => $id]);
    }

    // =========================================================================
    // ALGORITHM CONFIGURATION
    // =========================================================================

    /**
     * GET /api/v2/admin/matching/config
     *
     * Returns the current smart matching algorithm configuration including
     * weights, proximity settings, and toggles. Uses SmartMatchingEngine::getConfig()
     * which reads from tenants.configuration -> algorithms.smart_matching.
     */
    public function getConfig(): void
    {
        $this->requireAdmin();

        $config = SmartMatchingEngine::getConfig();

        // Normalise weights into the flat format the frontend expects
        $weights = $config['weights'] ?? [];
        $proximity = $config['proximity'] ?? [];

        // Build proximity_bands array from the proximity config
        $proximityBands = [
            ['distance_km' => (int) ($proximity['walking_km'] ?? 5),  'score' => 1.0],
            ['distance_km' => (int) ($proximity['local_km'] ?? 15),   'score' => 0.9],
            ['distance_km' => (int) ($proximity['city_km'] ?? 30),    'score' => 0.7],
            ['distance_km' => (int) ($proximity['regional_km'] ?? 50), 'score' => 0.5],
            ['distance_km' => (int) ($proximity['max_km'] ?? 100),    'score' => 0.2],
        ];

        $this->respondWithData([
            'category_weight'         => (float) ($weights['category'] ?? 0.25),
            'skill_weight'            => (float) ($weights['skill'] ?? 0.20),
            'proximity_weight'        => (float) ($weights['proximity'] ?? 0.25),
            'freshness_weight'        => (float) ($weights['freshness'] ?? 0.10),
            'reciprocity_weight'      => (float) ($weights['reciprocity'] ?? 0.15),
            'quality_weight'          => (float) ($weights['quality'] ?? 0.05),
            'proximity_bands'         => $proximityBands,
            'enabled'                 => (bool) ($config['enabled'] ?? true),
            'broker_approval_enabled' => (bool) ($config['broker_approval_enabled'] ?? true),
            'max_distance_km'         => (int) ($config['max_distance_km'] ?? 50),
            'min_match_score'         => (int) ($config['min_match_score'] ?? 40),
            'hot_match_threshold'     => (int) ($config['hot_match_threshold'] ?? 80),
        ]);
    }

    /**
     * PUT /api/v2/admin/matching/config
     *
     * Updates the smart matching algorithm configuration.
     * Saves to tenants.configuration JSON under algorithms.smart_matching.
     */
    public function updateConfig(): void
    {
        $this->requireAdmin();

        $input = $this->getAllInput();
        $tenantId = TenantContext::getId();

        // Get current tenant configuration
        $tenant = Database::query(
            "SELECT configuration FROM tenants WHERE id = ?",
            [$tenantId]
        )->fetch();

        $config = $tenant && $tenant['configuration']
            ? json_decode($tenant['configuration'], true)
            : [];

        $config['algorithms'] = $config['algorithms'] ?? [];
        $existing = $config['algorithms']['smart_matching'] ?? [];

        // Build proximity from bands if provided
        $proximityBands = $input['proximity_bands'] ?? null;
        $proximity = $existing['proximity'] ?? [
            'walking_km'  => 5,
            'local_km'    => 15,
            'city_km'     => 30,
            'regional_km' => 50,
            'max_km'      => 100,
        ];

        if (is_array($proximityBands) && count($proximityBands) >= 5) {
            usort($proximityBands, fn($a, $b) => ($a['distance_km'] ?? 0) - ($b['distance_km'] ?? 0));
            $proximity = [
                'walking_km'  => (int) ($proximityBands[0]['distance_km'] ?? 5),
                'local_km'    => (int) ($proximityBands[1]['distance_km'] ?? 15),
                'city_km'     => (int) ($proximityBands[2]['distance_km'] ?? 30),
                'regional_km' => (int) ($proximityBands[3]['distance_km'] ?? 50),
                'max_km'      => (int) ($proximityBands[4]['distance_km'] ?? 100),
            ];
        }

        // Merge into smart_matching config
        $config['algorithms']['smart_matching'] = [
            'enabled'                 => (bool) ($input['enabled'] ?? $existing['enabled'] ?? true),
            'broker_approval_enabled' => (bool) ($input['broker_approval_enabled'] ?? $existing['broker_approval_enabled'] ?? true),
            'max_distance_km'         => (int) ($input['max_distance_km'] ?? $existing['max_distance_km'] ?? 50),
            'min_match_score'         => (int) ($input['min_match_score'] ?? $existing['min_match_score'] ?? 40),
            'hot_match_threshold'     => (int) ($input['hot_match_threshold'] ?? $existing['hot_match_threshold'] ?? 80),
            'weights' => [
                'category'    => (float) ($input['category_weight'] ?? $existing['weights']['category'] ?? 0.25),
                'skill'       => (float) ($input['skill_weight'] ?? $existing['weights']['skill'] ?? 0.20),
                'proximity'   => (float) ($input['proximity_weight'] ?? $existing['weights']['proximity'] ?? 0.25),
                'freshness'   => (float) ($input['freshness_weight'] ?? $existing['weights']['freshness'] ?? 0.10),
                'reciprocity' => (float) ($input['reciprocity_weight'] ?? $existing['weights']['reciprocity'] ?? 0.15),
                'quality'     => (float) ($input['quality_weight'] ?? $existing['weights']['quality'] ?? 0.05),
            ],
            'proximity' => $proximity,
        ];

        // Save to database
        Database::query(
            "UPDATE tenants SET configuration = ? WHERE id = ?",
            [json_encode($config), $tenantId]
        );

        // Clear engine cache so new config takes effect
        SmartMatchingEngine::clearCache();

        $this->respondWithData(['message' => 'Matching configuration updated successfully']);
    }

    // =========================================================================
    // CACHE MANAGEMENT
    // =========================================================================

    /**
     * POST /api/v2/admin/matching/cache/clear
     *
     * Clears the match_cache table for the current tenant
     * and resets the SmartMatchingEngine internal cache.
     */
    public function clearCache(): void
    {
        $this->requireAdmin();

        $tenantId = TenantContext::getId();

        try {
            $deleted = (int) Database::query(
                "SELECT COUNT(*) FROM match_cache WHERE tenant_id = ?",
                [$tenantId]
            )->fetchColumn();

            Database::query(
                "DELETE FROM match_cache WHERE tenant_id = ?",
                [$tenantId]
            );

            SmartMatchingEngine::clearCache();

            $this->respondWithData([
                'message' => 'Match cache cleared successfully',
                'entries_cleared' => $deleted,
            ]);
        } catch (\Exception $e) {
            $this->respondWithError(ApiErrorCodes::SERVER_INTERNAL_ERROR, 'Failed to clear cache: ' . $e->getMessage(), null, 500);
        }
    }

    // =========================================================================
    // ANALYTICS & STATISTICS
    // =========================================================================

    /**
     * GET /api/v2/admin/matching/stats
     *
     * Returns aggregate matching statistics for the admin dashboard.
     * Combines data from SmartMatchingAnalyticsService with approval counts.
     */
    public function getStats(): void
    {
        $this->requireAdmin();

        try {
            $stats = SmartMatchingAnalyticsService::getOverallStats();
            $scoreDistribution = SmartMatchingAnalyticsService::getScoreDistribution();
            $distanceDistribution = SmartMatchingAnalyticsService::getDistanceDistribution();

            // Determine broker approval status from config
            $config = SmartMatchingEngine::getConfig();
            $brokerEnabled = (bool) ($config['broker_approval_enabled'] ?? true);

            // Count pending approvals if match_approvals table exists
            $pendingApprovals = 0;
            $approvedCount = 0;
            $rejectedCount = 0;
            try {
                $tenantId = TenantContext::getId();
                $pendingApprovals = (int) Database::query(
                    "SELECT COUNT(*) FROM match_approvals WHERE tenant_id = ? AND status = 'pending'",
                    [$tenantId]
                )->fetchColumn();
                $approvedCount = (int) Database::query(
                    "SELECT COUNT(*) FROM match_approvals WHERE tenant_id = ? AND status = 'approved'",
                    [$tenantId]
                )->fetchColumn();
                $rejectedCount = (int) Database::query(
                    "SELECT COUNT(*) FROM match_approvals WHERE tenant_id = ? AND status = 'rejected'",
                    [$tenantId]
                )->fetchColumn();
            } catch (\Exception $e) {
                // match_approvals table may not exist yet
            }

            $totalReviewed = $approvedCount + $rejectedCount;
            $approvalRate = $totalReviewed > 0
                ? round(($approvedCount / $totalReviewed) * 100, 1)
                : 0;

            $this->respondWithData([
                'overview' => $stats,
                'score_distribution' => $scoreDistribution,
                'distance_distribution' => $distanceDistribution,
                'broker_approval_enabled' => $brokerEnabled,
                'pending_approvals' => $pendingApprovals,
                'approved_count' => $approvedCount,
                'rejected_count' => $rejectedCount,
                'approval_rate' => $approvalRate,
            ]);
        } catch (\Exception $e) {
            $this->respondWithError(ApiErrorCodes::SERVER_INTERNAL_ERROR, 'Failed to load matching stats: ' . $e->getMessage(), null, 500);
        }
    }
}

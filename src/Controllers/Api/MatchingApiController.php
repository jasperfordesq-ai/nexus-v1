<?php
// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

namespace Nexus\Controllers\Api;

use Nexus\Services\CrossModuleMatchingService;
use Nexus\Services\MatchLearningService;
use Nexus\Core\Database;
use Nexus\Core\TenantContext;

/**
 * MatchingApiController - Cross-module matching API
 *
 * Endpoints:
 * - GET  /api/v2/matches/all            - Get unified matches across all modules
 * - POST /api/v2/matches/{id}/dismiss   - Dismiss a listing match (negative signal)
 */
class MatchingApiController extends BaseApiController
{
    /**
     * GET /api/v2/matches/all
     *
     * Get all matches for the authenticated user across listings, jobs,
     * volunteering, and groups.
     *
     * Query Parameters:
     * - limit: int (default 20, max 100)
     * - min_score: int (default 30, minimum match score 0-100)
     * - modules: string (comma-separated: 'listings,jobs,volunteering,groups')
     */
    public function allMatches(): void
    {
        $userId = $this->requireAuth();

        // Admin override: allow viewing any user's matches for MatchDebugPanel
        $adminRoles = ['admin', 'tenant_admin', 'super_admin', 'god'];
        $callerRole = $this->getAuthenticatedUserRole() ?? '';
        if (!empty($_GET['user_id']) && in_array($callerRole, $adminRoles)) {
            $userId = (int)$_GET['user_id'];
        }

        $options = [
            'limit' => min(100, max(1, (int)($_GET['limit'] ?? 20))),
            'min_score' => max(0, min(100, (int)($_GET['min_score'] ?? 30))),
            'debug' => ($_GET['debug'] ?? '') === 'true' && in_array($callerRole, $adminRoles),
        ];

        if (!empty($_GET['modules'])) {
            $allowed = ['listings', 'jobs', 'volunteering', 'groups'];
            $requested = array_map('trim', explode(',', $_GET['modules']));
            $options['modules'] = array_values(array_intersect($requested, $allowed));
        }

        $matches = CrossModuleMatchingService::getAllMatches($userId, $options);

        $this->respondWithData($matches);
    }

    /**
     * POST /api/v2/matches/{id}/dismiss
     *
     * Dismiss a listing match — records a negative signal so the listing
     * is ranked lower in future match results for this user.
     *
     * Route parameter:
     * - id: int (listing_id)
     *
     * Request body (JSON, all optional):
     * - reason: string ('not_relevant' | 'too_far' | 'already_done' | 'other')
     */
    public function dismiss(int $listingId): void
    {
        $userId   = $this->requireAuth();
        $tenantId = TenantContext::getId();

        $this->verifyCsrf();
        $this->rateLimit('match_dismiss', 200, 60);

        if ($listingId <= 0) {
            $this->respondWithError('VALIDATION_ERROR', 'Invalid listing ID', 'id', 400);
        }

        $reason = $this->input('reason');

        $validReasons = ['not_relevant', 'too_far', 'already_done', 'other', null];
        if (!in_array($reason, $validReasons, true)) {
            $reason = 'other';
        }

        try {
            // Upsert — ignore duplicate (user may re-dismiss)
            Database::query(
                "INSERT IGNORE INTO match_dismissals (tenant_id, user_id, listing_id, reason)
                 VALUES (?, ?, ?, ?)",
                [$tenantId, $userId, $listingId, $reason]
            );
        } catch (\Throwable $e) {
            // Table may not exist yet — degrade gracefully
            error_log('MatchingApiController::dismiss DB error — ' . $e->getMessage());
        }

        // Record negative signal in MatchLearningService
        MatchLearningService::recordInteraction($userId, $listingId, 'dismissed', []);

        $this->respondWithData(['dismissed' => true, 'listing_id' => $listingId]);
    }
}

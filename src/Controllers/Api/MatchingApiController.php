<?php
// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

namespace Nexus\Controllers\Api;

use Nexus\Services\CrossModuleMatchingService;

/**
 * MatchingApiController - Cross-module matching API
 *
 * Endpoints:
 * - GET /api/v2/matches/all - Get unified matches across all modules
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
}

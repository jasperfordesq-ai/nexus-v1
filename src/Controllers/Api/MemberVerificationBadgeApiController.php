<?php
// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

namespace Nexus\Controllers\Api;

use Nexus\Services\MemberVerificationBadgeService;

/**
 * MemberVerificationBadgeApiController - API for verification badges
 *
 * Endpoints:
 * - GET    /api/v2/users/{id}/verification-badges  - Get user's badges
 * - POST   /api/v2/admin/users/{id}/badges         - Grant badge (admin)
 * - DELETE /api/v2/admin/users/{id}/badges/{type}   - Revoke badge (admin)
 * - GET    /api/v2/admin/users/{id}/badges          - Admin badge list
 */
class MemberVerificationBadgeApiController extends BaseApiController
{
    protected bool $isV2Api = true;

    /**
     * GET /api/v2/users/{id}/verification-badges
     */
    public function getUserBadges(int $id): void
    {
        $this->rateLimit('verification_badges', 30, 60);

        $badges = MemberVerificationBadgeService::getUserBadges($id);
        $this->respondWithData($badges);
    }

    /**
     * POST /api/v2/admin/users/{id}/badges
     * Grant a verification badge (admin only)
     *
     * Request Body:
     * {
     *   "badge_type": "id_verified",
     *   "note": "Government ID verified in person",
     *   "expires_at": "2027-03-01 00:00:00"  // optional
     * }
     */
    public function grantBadge(int $id): void
    {
        $adminId = $this->getUserId();
        $this->verifyCsrf();
        $this->rateLimit('admin_badge_grant', 20, 60);

        // Admin check is handled by route-level middleware/guard

        $data = $this->getAllInput();
        $badgeType = $data['badge_type'] ?? '';
        $note = $data['note'] ?? null;
        $expiresAt = $data['expires_at'] ?? null;

        if (empty($badgeType)) {
            $this->respondWithError('VALIDATION_ERROR', 'badge_type is required', 'badge_type', 400);
            return;
        }

        $badgeId = MemberVerificationBadgeService::grantBadge($id, $badgeType, $adminId, $note, $expiresAt);

        if ($badgeId === null) {
            $this->respondWithErrors(MemberVerificationBadgeService::getErrors(), 422);
        }

        $badges = MemberVerificationBadgeService::getUserBadges($id);
        $this->respondWithData($badges, null, 201);
    }

    /**
     * DELETE /api/v2/admin/users/{id}/badges/{type}
     * Revoke a verification badge (admin only)
     */
    public function revokeBadge(int $id, string $type): void
    {
        $adminId = $this->getUserId();
        $this->verifyCsrf();

        MemberVerificationBadgeService::revokeBadge($id, $type, $adminId);

        $badges = MemberVerificationBadgeService::getUserBadges($id);
        $this->respondWithData($badges);
    }

    /**
     * GET /api/v2/admin/users/{id}/badges
     * Get all badges including revoked (admin view)
     */
    public function getAdminBadgeList(int $id): void
    {
        $this->getUserId(); // Must be authenticated
        $this->rateLimit('admin_badge_list', 30, 60);

        $badges = MemberVerificationBadgeService::getAdminBadgeList($id);

        $this->respondWithData([
            'badges' => $badges,
            'available_types' => MemberVerificationBadgeService::BADGE_TYPES,
            'labels' => MemberVerificationBadgeService::BADGE_LABELS,
        ]);
    }
}

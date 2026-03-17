<?php
// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

namespace App\Http\Controllers\Api;

use Illuminate\Http\JsonResponse;
use Nexus\Services\MemberVerificationBadgeService;

/**
 * MemberVerificationBadgeController -- Member verification badges.
 *
 * Native implementation using legacy MemberVerificationBadgeService static methods.
 *
 * Endpoints:
 *   GET    /api/v2/users/{id}/verification-badges   getUserBadges()
 *   POST   /api/v2/admin/users/{id}/badges          grantBadge()
 *   DELETE /api/v2/admin/users/{id}/badges/{type}    revokeBadge()
 *   GET    /api/v2/admin/users/{id}/badges           getAdminBadgeList()
 */
class MemberVerificationBadgeController extends BaseApiController
{
    protected bool $isV2Api = true;

    /**
     * GET /api/v2/users/{id}/verification-badges
     *
     * Get a user's verification badges. Public endpoint (rate-limited).
     */
    public function getUserBadges(int $id): JsonResponse
    {
        $this->rateLimit('verification_badges', 30, 60);

        $badges = MemberVerificationBadgeService::getUserBadges($id);

        return $this->respondWithData($badges);
    }

    /**
     * POST /api/v2/admin/users/{id}/badges
     *
     * Grant a verification badge (admin only).
     *
     * Body:
     * {
     *   "badge_type": "id_verified",
     *   "note": "Government ID verified in person",
     *   "expires_at": "2027-03-01 00:00:00"  // optional
     * }
     */
    public function grantBadge(int $id): JsonResponse
    {
        $adminId = $this->requireAdmin();
        $this->rateLimit('admin_badge_grant', 20, 60);

        $data = $this->getAllInput();
        $badgeType = $data['badge_type'] ?? '';
        $note = $data['note'] ?? null;
        $expiresAt = $data['expires_at'] ?? null;

        if (empty($badgeType)) {
            return $this->respondWithError('VALIDATION_ERROR', 'badge_type is required', 'badge_type', 400);
        }

        $badgeId = MemberVerificationBadgeService::grantBadge($id, $badgeType, $adminId, $note, $expiresAt);

        if ($badgeId === null) {
            return $this->respondWithErrors(MemberVerificationBadgeService::getErrors(), 422);
        }

        $badges = MemberVerificationBadgeService::getUserBadges($id);

        return $this->respondWithData($badges, null, 201);
    }

    /**
     * DELETE /api/v2/admin/users/{id}/badges/{type}
     *
     * Revoke a verification badge (admin only).
     */
    public function revokeBadge(int $id, string $type): JsonResponse
    {
        $adminId = $this->requireAdmin();

        MemberVerificationBadgeService::revokeBadge($id, $type, $adminId);

        $badges = MemberVerificationBadgeService::getUserBadges($id);

        return $this->respondWithData($badges);
    }

    /**
     * GET /api/v2/admin/users/{id}/badges
     *
     * Get all badges including revoked (admin view).
     */
    public function getAdminBadgeList(int $id): JsonResponse
    {
        $this->requireAdmin();
        $this->rateLimit('admin_badge_list', 30, 60);

        $badges = MemberVerificationBadgeService::getAdminBadgeList($id);

        return $this->respondWithData([
            'badges' => $badges,
            'available_types' => MemberVerificationBadgeService::BADGE_TYPES,
            'labels' => MemberVerificationBadgeService::BADGE_LABELS,
        ]);
    }
}

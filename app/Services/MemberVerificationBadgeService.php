<?php
// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

namespace App\Services;

/**
 * MemberVerificationBadgeService — Laravel DI wrapper for legacy \Nexus\Services\MemberVerificationBadgeService.
 *
 * Provides dependency-injectable access to the legacy static service methods.
 */
class MemberVerificationBadgeService
{
    public const BADGE_TYPES = \Nexus\Services\MemberVerificationBadgeService::BADGE_TYPES;
    public const BADGE_LABELS = \Nexus\Services\MemberVerificationBadgeService::BADGE_LABELS;

    public function __construct()
    {
    }

    /**
     * Delegates to legacy MemberVerificationBadgeService::getErrors().
     */
    public function getErrors(): array
    {
        return \Nexus\Services\MemberVerificationBadgeService::getErrors();
    }

    /**
     * Delegates to legacy MemberVerificationBadgeService::grantBadge().
     */
    public function grantBadge(int $userId, string $badgeType, int $adminId, ?string $note = null, ?string $expiresAt = null): ?int
    {
        return \Nexus\Services\MemberVerificationBadgeService::grantBadge($userId, $badgeType, $adminId, $note, $expiresAt);
    }

    /**
     * Delegates to legacy MemberVerificationBadgeService::revokeBadge().
     */
    public function revokeBadge(int $userId, string $badgeType, int $adminId): bool
    {
        return \Nexus\Services\MemberVerificationBadgeService::revokeBadge($userId, $badgeType, $adminId);
    }

    /**
     * Delegates to legacy MemberVerificationBadgeService::getUserBadges().
     */
    public function getUserBadges(int $userId): array
    {
        return \Nexus\Services\MemberVerificationBadgeService::getUserBadges($userId);
    }

    /**
     * Delegates to legacy MemberVerificationBadgeService::getBatchUserBadges().
     */
    public function getBatchUserBadges(array $userIds): array
    {
        return \Nexus\Services\MemberVerificationBadgeService::getBatchUserBadges($userIds);
    }

    /**
     * Delegates to legacy MemberVerificationBadgeService::getAdminBadgeList().
     */
    public function getAdminBadgeList(int $userId): array
    {
        return \Nexus\Services\MemberVerificationBadgeService::getAdminBadgeList($userId);
    }
}

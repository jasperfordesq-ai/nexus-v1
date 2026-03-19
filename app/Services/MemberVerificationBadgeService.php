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
    public const BADGE_TYPES = [
        'email_verified',
        'phone_verified',
        'id_verified',
        'address_verified',
        'admin_verified',
    ];

    public const BADGE_LABELS = [
        'email_verified' => 'Email Verified',
        'phone_verified' => 'Phone Verified',
        'id_verified' => 'ID Verified',
        'address_verified' => 'Address Verified',
        'admin_verified' => 'Admin Verified',
    ];

    public function __construct()
    {
    }

    /**
     * Delegates to legacy MemberVerificationBadgeService::getErrors().
     */
    public function getErrors(): array
    {
        if (!class_exists('\Nexus\Services\MemberVerificationBadgeService')) { return []; }
        return \Nexus\Services\MemberVerificationBadgeService::getErrors();
    }

    /**
     * Delegates to legacy MemberVerificationBadgeService::grantBadge().
     */
    public function grantBadge(int $userId, string $badgeType, int $adminId, ?string $note = null, ?string $expiresAt = null): ?int
    {
        if (!class_exists('\Nexus\Services\MemberVerificationBadgeService')) { return null; }
        return \Nexus\Services\MemberVerificationBadgeService::grantBadge($userId, $badgeType, $adminId, $note, $expiresAt);
    }

    /**
     * Delegates to legacy MemberVerificationBadgeService::revokeBadge().
     */
    public function revokeBadge(int $userId, string $badgeType, int $adminId): bool
    {
        if (!class_exists('\Nexus\Services\MemberVerificationBadgeService')) { return false; }
        return \Nexus\Services\MemberVerificationBadgeService::revokeBadge($userId, $badgeType, $adminId);
    }

    /**
     * Delegates to legacy MemberVerificationBadgeService::getUserBadges().
     */
    public function getUserBadges(int $userId): array
    {
        if (!class_exists('\Nexus\Services\MemberVerificationBadgeService')) { return []; }
        return \Nexus\Services\MemberVerificationBadgeService::getUserBadges($userId);
    }

    /**
     * Delegates to legacy MemberVerificationBadgeService::getBatchUserBadges().
     */
    public function getBatchUserBadges(array $userIds): array
    {
        if (!class_exists('\Nexus\Services\MemberVerificationBadgeService')) { return []; }
        return \Nexus\Services\MemberVerificationBadgeService::getBatchUserBadges($userIds);
    }

    /**
     * Delegates to legacy MemberVerificationBadgeService::getAdminBadgeList().
     */
    public function getAdminBadgeList(int $userId): array
    {
        if (!class_exists('\Nexus\Services\MemberVerificationBadgeService')) { return []; }
        return \Nexus\Services\MemberVerificationBadgeService::getAdminBadgeList($userId);
    }
}

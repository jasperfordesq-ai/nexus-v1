<?php
// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

namespace App\Services;

/**
 * InactiveMemberService — Laravel DI wrapper for legacy \Nexus\Services\InactiveMemberService.
 *
 * Provides dependency-injectable access to the legacy static service methods.
 */
class InactiveMemberService
{
    public function __construct()
    {
    }

    /**
     * Delegates to legacy InactiveMemberService::detectInactive().
     */
    public function detectInactive(int $tenantId, int $thresholdDays = 90): array
    {
        if (!class_exists('\Nexus\Services\InactiveMemberService')) { return []; }
        return \Nexus\Services\InactiveMemberService::detectInactive($tenantId, $thresholdDays);
    }

    /**
     * Delegates to legacy InactiveMemberService::getInactiveMembers().
     */
    public function getInactiveMembers(int $tenantId, int $days = 90, ?string $flagType = null, int $limit = 50, int $offset = 0): array
    {
        if (!class_exists('\Nexus\Services\InactiveMemberService')) { return []; }
        return \Nexus\Services\InactiveMemberService::getInactiveMembers($tenantId, $days, $flagType, $limit, $offset);
    }

    /**
     * Delegates to legacy InactiveMemberService::getInactivityStats().
     */
    public function getInactivityStats(int $tenantId): array
    {
        if (!class_exists('\Nexus\Services\InactiveMemberService')) { return []; }
        return \Nexus\Services\InactiveMemberService::getInactivityStats($tenantId);
    }

    /**
     * Delegates to legacy InactiveMemberService::markNotified().
     */
    public function markNotified(int $tenantId, array $userIds): int
    {
        if (!class_exists('\Nexus\Services\InactiveMemberService')) { return 0; }
        return \Nexus\Services\InactiveMemberService::markNotified($tenantId, $userIds);
    }
}

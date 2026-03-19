<?php
// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

namespace App\Services;

/**
 * FederationActivityService — Laravel DI wrapper for legacy \Nexus\Services\FederationActivityService.
 *
 * Provides dependency-injectable access to the legacy static service methods.
 */
class FederationActivityService
{
    public function __construct()
    {
    }

    /**
     * Delegates to legacy FederationActivityService::getActivityFeed().
     */
    public function getActivityFeed(int $userId, int $limit = 50, int $offset = 0): array
    {
        return \Nexus\Services\FederationActivityService::getActivityFeed($userId, $limit, $offset);
    }

    /**
     * Delegates to legacy FederationActivityService::getUnreadCount().
     */
    public function getUnreadCount(int $userId): int
    {
        return \Nexus\Services\FederationActivityService::getUnreadCount($userId);
    }

    /**
     * Delegates to legacy FederationActivityService::getActivityStats().
     */
    public function getActivityStats(int $userId): array
    {
        return \Nexus\Services\FederationActivityService::getActivityStats($userId);
    }
}

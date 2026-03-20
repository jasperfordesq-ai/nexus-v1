<?php
// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

namespace App\Services;

/**
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
    public static function getActivityFeed(int $userId, int $limit = 50, int $offset = 0): array
    {
        \Illuminate\Support\Facades\Log::warning('Legacy delegation removed: ' . __METHOD__);
        return [];
    }

    /**
     * Delegates to legacy FederationActivityService::getUnreadCount().
     */
    public static function getUnreadCount(int $userId): int
    {
        \Illuminate\Support\Facades\Log::warning('Legacy delegation removed: ' . __METHOD__);
        return 0;
    }

    /**
     * Delegates to legacy FederationActivityService::getActivityStats().
     */
    public static function getActivityStats(int $userId): array
    {
        \Illuminate\Support\Facades\Log::warning('Legacy delegation removed: ' . __METHOD__);
        return [];
    }
}

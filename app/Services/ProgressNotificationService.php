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
class ProgressNotificationService
{
    public function __construct()
    {
    }

    /**
     * Delegates to legacy ProgressNotificationService::checkProgressNotifications().
     */
    public function checkProgressNotifications($userId)
    {
        \Illuminate\Support\Facades\Log::warning('Legacy delegation removed: ' . __METHOD__);
        return null;
    }

    /**
     * Delegates to legacy ProgressNotificationService::getNearCompletionBadges().
     */
    public function getNearCompletionBadges($userId, $minPercent = 50, $limit = 5)
    {
        \Illuminate\Support\Facades\Log::warning('Legacy delegation removed: ' . __METHOD__);
        return null;
    }

    /**
     * Delegates to legacy ProgressNotificationService::getProgressNudge().
     */
    public function getProgressNudge($userId, $badgeKey)
    {
        \Illuminate\Support\Facades\Log::warning('Legacy delegation removed: ' . __METHOD__);
        return null;
    }

    /**
     * Delegates to legacy ProgressNotificationService::batchCheckProgress().
     */
    public function batchCheckProgress($limit = 100)
    {
        \Illuminate\Support\Facades\Log::warning('Legacy delegation removed: ' . __METHOD__);
        return null;
    }

    /**
     * Delegates to legacy ProgressNotificationService::cleanupOldRecords().
     */
    public function cleanupOldRecords($days = 30)
    {
        \Illuminate\Support\Facades\Log::warning('Legacy delegation removed: ' . __METHOD__);
        return null;
    }
}

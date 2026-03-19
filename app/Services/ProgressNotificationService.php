<?php
// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

namespace App\Services;

/**
 * ProgressNotificationService — Laravel DI wrapper for legacy \Nexus\Services\ProgressNotificationService.
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
        return \Nexus\Services\ProgressNotificationService::checkProgressNotifications($userId);
    }

    /**
     * Delegates to legacy ProgressNotificationService::getNearCompletionBadges().
     */
    public function getNearCompletionBadges($userId, $minPercent = 50, $limit = 5)
    {
        return \Nexus\Services\ProgressNotificationService::getNearCompletionBadges($userId, $minPercent, $limit);
    }

    /**
     * Delegates to legacy ProgressNotificationService::getProgressNudge().
     */
    public function getProgressNudge($userId, $badgeKey)
    {
        return \Nexus\Services\ProgressNotificationService::getProgressNudge($userId, $badgeKey);
    }

    /**
     * Delegates to legacy ProgressNotificationService::batchCheckProgress().
     */
    public function batchCheckProgress($limit = 100)
    {
        return \Nexus\Services\ProgressNotificationService::batchCheckProgress($limit);
    }

    /**
     * Delegates to legacy ProgressNotificationService::cleanupOldRecords().
     */
    public function cleanupOldRecords($days = 30)
    {
        return \Nexus\Services\ProgressNotificationService::cleanupOldRecords($days);
    }
}

<?php
// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

namespace App\Services;

/**
 * NotificationDispatcher — Laravel DI wrapper for legacy \Nexus\Services\NotificationDispatcher.
 *
 * Provides dependency-injectable access to the legacy static service methods.
 */
class NotificationDispatcher
{
    public function __construct()
    {
    }

    /**
     * Delegates to legacy NotificationDispatcher::dispatch().
     */
    public function dispatch($userId, $contextType, $contextId, $activityType, $content, $link, $htmlContent, $isOrganizer = false)
    {
        return \Nexus\Services\NotificationDispatcher::dispatch($userId, $contextType, $contextId, $activityType, $content, $link, $htmlContent, $isOrganizer);
    }

    /**
     * Delegates to legacy NotificationDispatcher::dispatchHotMatch().
     */
    public function dispatchHotMatch($userId, $match)
    {
        return \Nexus\Services\NotificationDispatcher::dispatchHotMatch($userId, $match);
    }

    /**
     * Delegates to legacy NotificationDispatcher::dispatchMutualMatch().
     */
    public function dispatchMutualMatch($userId, $match, $reciprocalInfo = [])
    {
        return \Nexus\Services\NotificationDispatcher::dispatchMutualMatch($userId, $match, $reciprocalInfo);
    }

    /**
     * Delegates to legacy NotificationDispatcher::dispatchMatchDigest().
     */
    public function dispatchMatchDigest($userId, $matches, $period = 'fortnightly')
    {
        return \Nexus\Services\NotificationDispatcher::dispatchMatchDigest($userId, $matches, $period);
    }

    /**
     * Delegates to legacy NotificationDispatcher::dispatchMatchApprovalRequest().
     */
    public function dispatchMatchApprovalRequest($brokerId, $userName, $listingTitle, $requestId)
    {
        return \Nexus\Services\NotificationDispatcher::dispatchMatchApprovalRequest($brokerId, $userName, $listingTitle, $requestId);
    }
}

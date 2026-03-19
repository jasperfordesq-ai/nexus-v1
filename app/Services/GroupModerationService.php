<?php
// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

namespace App\Services;

/**
 * GroupModerationService — Laravel DI wrapper for legacy \Nexus\Services\GroupModerationService.
 *
 * Provides dependency-injectable access to the legacy static service methods.
 */
class GroupModerationService
{
    public function __construct()
    {
    }

    /**
     * Delegates to legacy GroupModerationService::flagContent().
     */
    public function flagContent($contentType, $contentId, $reportedBy, $reason = self::REASON_OTHER, $description = '')
    {
        return \Nexus\Services\GroupModerationService::flagContent($contentType, $contentId, $reportedBy, $reason, $description);
    }

    /**
     * Delegates to legacy GroupModerationService::moderateContent().
     */
    public function moderateContent($flagId, $action, $moderatorId, $moderatorNotes = '')
    {
        return \Nexus\Services\GroupModerationService::moderateContent($flagId, $action, $moderatorId, $moderatorNotes);
    }

    /**
     * Delegates to legacy GroupModerationService::isUserBanned().
     */
    public function isUserBanned($userId)
    {
        return \Nexus\Services\GroupModerationService::isUserBanned($userId);
    }

    /**
     * Delegates to legacy GroupModerationService::getPendingFlags().
     */
    public function getPendingFlags($filters = [], $limit = 50, $offset = 0)
    {
        return \Nexus\Services\GroupModerationService::getPendingFlags($filters, $limit, $offset);
    }

    /**
     * Delegates to legacy GroupModerationService::getModerationHistory().
     */
    public function getModerationHistory($filters = [], $limit = 50, $offset = 0)
    {
        return \Nexus\Services\GroupModerationService::getModerationHistory($filters, $limit, $offset);
    }
}

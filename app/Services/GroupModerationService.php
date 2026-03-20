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
        \Illuminate\Support\Facades\Log::warning('Legacy delegation removed: ' . __METHOD__);
        return null;
    }

    /**
     * Delegates to legacy GroupModerationService::moderateContent().
     */
    public function moderateContent($flagId, $action, $moderatorId, $moderatorNotes = '')
    {
        \Illuminate\Support\Facades\Log::warning('Legacy delegation removed: ' . __METHOD__);
        return null;
    }

    /**
     * Delegates to legacy GroupModerationService::isUserBanned().
     */
    public function isUserBanned($userId)
    {
        \Illuminate\Support\Facades\Log::warning('Legacy delegation removed: ' . __METHOD__);
        return null;
    }

    /**
     * Delegates to legacy GroupModerationService::getPendingFlags().
     */
    public function getPendingFlags($filters = [], $limit = 50, $offset = 0)
    {
        \Illuminate\Support\Facades\Log::warning('Legacy delegation removed: ' . __METHOD__);
        return null;
    }

    /**
     * Delegates to legacy GroupModerationService::getModerationHistory().
     */
    public function getModerationHistory($filters = [], $limit = 50, $offset = 0)
    {
        \Illuminate\Support\Facades\Log::warning('Legacy delegation removed: ' . __METHOD__);
        return null;
    }
}

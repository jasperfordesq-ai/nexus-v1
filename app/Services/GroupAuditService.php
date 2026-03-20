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
class GroupAuditService
{
    public function __construct()
    {
    }

    /**
     * Delegates to legacy GroupAuditService::log().
     */
    public static function log($action, $groupId = null, $userId = null, $details = [], $targetUserId = null)
    {
        \Illuminate\Support\Facades\Log::warning('Legacy delegation removed: ' . __METHOD__);
        return null;
    }

    /**
     * Delegates to legacy GroupAuditService::logGroupCreated().
     */
    public static function logGroupCreated($groupId, $userId, $groupData = [])
    {
        \Illuminate\Support\Facades\Log::warning('Legacy delegation removed: ' . __METHOD__);
        return null;
    }

    /**
     * Delegates to legacy GroupAuditService::logGroupUpdated().
     */
    public static function logGroupUpdated($groupId, $userId, $changes = [])
    {
        \Illuminate\Support\Facades\Log::warning('Legacy delegation removed: ' . __METHOD__);
        return null;
    }

    /**
     * Delegates to legacy GroupAuditService::logGroupDeleted().
     */
    public static function logGroupDeleted($groupId, $userId, $reason = '')
    {
        \Illuminate\Support\Facades\Log::warning('Legacy delegation removed: ' . __METHOD__);
        return null;
    }

    /**
     * Delegates to legacy GroupAuditService::logGroupFeatured().
     */
    public static function logGroupFeatured($groupId, $userId, $featured = true)
    {
        \Illuminate\Support\Facades\Log::warning('Legacy delegation removed: ' . __METHOD__);
        return null;
    }
}

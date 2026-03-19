<?php
// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

namespace App\Services;

/**
 * GroupAuditService — Laravel DI wrapper for legacy \Nexus\Services\GroupAuditService.
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
    public function log($action, $groupId = null, $userId = null, $details = [], $targetUserId = null)
    {
        return \Nexus\Services\GroupAuditService::log($action, $groupId, $userId, $details, $targetUserId);
    }

    /**
     * Delegates to legacy GroupAuditService::logGroupCreated().
     */
    public function logGroupCreated($groupId, $userId, $groupData = [])
    {
        return \Nexus\Services\GroupAuditService::logGroupCreated($groupId, $userId, $groupData);
    }

    /**
     * Delegates to legacy GroupAuditService::logGroupUpdated().
     */
    public function logGroupUpdated($groupId, $userId, $changes = [])
    {
        return \Nexus\Services\GroupAuditService::logGroupUpdated($groupId, $userId, $changes);
    }

    /**
     * Delegates to legacy GroupAuditService::logGroupDeleted().
     */
    public function logGroupDeleted($groupId, $userId, $reason = '')
    {
        return \Nexus\Services\GroupAuditService::logGroupDeleted($groupId, $userId, $reason);
    }

    /**
     * Delegates to legacy GroupAuditService::logGroupFeatured().
     */
    public function logGroupFeatured($groupId, $userId, $featured = true)
    {
        return \Nexus\Services\GroupAuditService::logGroupFeatured($groupId, $userId, $featured);
    }
}

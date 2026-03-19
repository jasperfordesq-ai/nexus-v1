<?php
// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

namespace App\Services;

/**
 * GroupNotificationService — Laravel DI wrapper for legacy \Nexus\Services\GroupNotificationService.
 *
 * Provides dependency-injectable access to the legacy static service methods.
 */
class GroupNotificationService
{
    public function __construct()
    {
    }

    /**
     * Delegates to legacy GroupNotificationService::notifyJoinRequest().
     */
    public function notifyJoinRequest(int $groupId, int $userId): void
    {
        \Nexus\Services\GroupNotificationService::notifyJoinRequest($groupId, $userId);
    }

    /**
     * Delegates to legacy GroupNotificationService::notifyJoined().
     */
    public function notifyJoined(int $groupId, int $userId): void
    {
        \Nexus\Services\GroupNotificationService::notifyJoined($groupId, $userId);
    }

    /**
     * Delegates to legacy GroupNotificationService::notifyJoinRejected().
     */
    public function notifyJoinRejected(int $groupId, int $userId): void
    {
        \Nexus\Services\GroupNotificationService::notifyJoinRejected($groupId, $userId);
    }

    /**
     * Delegates to legacy GroupNotificationService::notifyNewDiscussion().
     */
    public function notifyNewDiscussion(int $groupId, int $discussionId, int $authorId, string $title): void
    {
        \Nexus\Services\GroupNotificationService::notifyNewDiscussion($groupId, $discussionId, $authorId, $title);
    }

    /**
     * Delegates to legacy GroupNotificationService::notifyNewAnnouncement().
     */
    public function notifyNewAnnouncement(int $groupId, int $authorId, string $title): void
    {
        \Nexus\Services\GroupNotificationService::notifyNewAnnouncement($groupId, $authorId, $title);
    }
}

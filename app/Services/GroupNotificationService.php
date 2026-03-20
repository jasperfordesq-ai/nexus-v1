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
        \Illuminate\Support\Facades\Log::warning('Legacy delegation removed: ' . __METHOD__);
    }

    /**
     * Delegates to legacy GroupNotificationService::notifyJoined().
     */
    public function notifyJoined(int $groupId, int $userId): void
    {
        \Illuminate\Support\Facades\Log::warning('Legacy delegation removed: ' . __METHOD__);
    }

    /**
     * Delegates to legacy GroupNotificationService::notifyJoinRejected().
     */
    public function notifyJoinRejected(int $groupId, int $userId): void
    {
        \Illuminate\Support\Facades\Log::warning('Legacy delegation removed: ' . __METHOD__);
    }

    /**
     * Delegates to legacy GroupNotificationService::notifyNewDiscussion().
     */
    public function notifyNewDiscussion(int $groupId, int $discussionId, int $authorId, string $title): void
    {
        \Illuminate\Support\Facades\Log::warning('Legacy delegation removed: ' . __METHOD__);
    }

    /**
     * Delegates to legacy GroupNotificationService::notifyNewAnnouncement().
     */
    public function notifyNewAnnouncement(int $groupId, int $authorId, string $title): void
    {
        \Illuminate\Support\Facades\Log::warning('Legacy delegation removed: ' . __METHOD__);
    }
}

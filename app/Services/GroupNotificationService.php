<?php
// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

namespace App\Services;

use App\Core\TenantContext;
use App\Models\Notification;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * GroupNotificationService — sends in-app notifications for group events.
 *
 * Native Laravel implementation (replaces legacy wrapper).
 * Creates notifications in the `notifications` table for relevant users.
 */
class GroupNotificationService
{
    public function __construct()
    {
    }

    /**
     * Notify group admins/owners that a user has requested to join.
     */
    public function notifyJoinRequest(int $groupId, int $userId): void
    {
        $tenantId = TenantContext::getId();

        $group = $this->getGroupName($groupId, $tenantId);
        $userName = $this->getUserName($userId, $tenantId);

        if (!$group || !$userName) {
            Log::warning('GroupNotification: missing group or user', [
                'group_id' => $groupId,
                'user_id' => $userId,
            ]);
            return;
        }

        $admins = $this->getGroupAdmins($groupId, $tenantId);

        $message = "{$userName} has requested to join \"{$group->name}\"";
        $link = "/groups/{$groupId}/members?tab=requests";

        foreach ($admins as $admin) {
            if ((int) $admin->user_id === $userId) {
                continue; // Don't notify the requester if they're somehow an admin
            }
            Notification::createNotification(
                (int) $admin->user_id,
                $message,
                $link,
                'group_join_request'
            );
        }
    }

    /**
     * Notify a user that they have been accepted into a group.
     */
    public function notifyJoined(int $groupId, int $userId): void
    {
        $tenantId = TenantContext::getId();

        $group = $this->getGroupName($groupId, $tenantId);
        if (!$group) {
            return;
        }

        $message = "You have been accepted into \"{$group->name}\"";
        $link = "/groups/{$groupId}";

        Notification::createNotification(
            $userId,
            $message,
            $link,
            'group_joined'
        );
    }

    /**
     * Notify a user that their join request was rejected.
     */
    public function notifyJoinRejected(int $groupId, int $userId): void
    {
        $tenantId = TenantContext::getId();

        $group = $this->getGroupName($groupId, $tenantId);
        if (!$group) {
            return;
        }

        $message = "Your request to join \"{$group->name}\" was not approved";
        $link = "/groups";

        Notification::createNotification(
            $userId,
            $message,
            $link,
            'group_join_rejected'
        );
    }

    /**
     * Notify group members about a new discussion.
     */
    public function notifyNewDiscussion(int $groupId, int $discussionId, int $authorId, string $title): void
    {
        $tenantId = TenantContext::getId();

        $group = $this->getGroupName($groupId, $tenantId);
        $authorName = $this->getUserName($authorId, $tenantId);

        if (!$group || !$authorName) {
            return;
        }

        $message = "{$authorName} started a new discussion \"{$title}\" in \"{$group->name}\"";
        $link = "/groups/{$groupId}/discussions/{$discussionId}";

        $members = $this->getActiveMembers($groupId, $tenantId);

        foreach ($members as $member) {
            if ((int) $member->user_id === $authorId) {
                continue; // Don't notify the author
            }
            Notification::createNotification(
                (int) $member->user_id,
                $message,
                $link,
                'group_new_discussion'
            );
        }
    }

    /**
     * Notify group members about a new announcement.
     */
    public function notifyNewAnnouncement(int $groupId, int $authorId, string $title): void
    {
        $tenantId = TenantContext::getId();

        $group = $this->getGroupName($groupId, $tenantId);
        $authorName = $this->getUserName($authorId, $tenantId);

        if (!$group || !$authorName) {
            return;
        }

        $message = "{$authorName} posted an announcement \"{$title}\" in \"{$group->name}\"";
        $link = "/groups/{$groupId}/announcements";

        $members = $this->getActiveMembers($groupId, $tenantId);

        foreach ($members as $member) {
            if ((int) $member->user_id === $authorId) {
                continue; // Don't notify the author
            }
            Notification::createNotification(
                (int) $member->user_id,
                $message,
                $link,
                'group_new_announcement'
            );
        }
    }

    // =========================================================================
    // Private helpers
    // =========================================================================

    /**
     * Get group name by ID (tenant-scoped).
     */
    private function getGroupName(int $groupId, int $tenantId): ?object
    {
        return DB::selectOne(
            "SELECT id, name FROM `groups` WHERE id = ? AND tenant_id = ?",
            [$groupId, $tenantId]
        );
    }

    /**
     * Get a user's display name.
     */
    private function getUserName(int $userId, int $tenantId): ?string
    {
        $user = DB::selectOne(
            "SELECT CONCAT(COALESCE(first_name, ''), ' ', COALESCE(last_name, '')) as name
             FROM users WHERE id = ? AND tenant_id = ?",
            [$userId, $tenantId]
        );

        return $user ? trim($user->name) : null;
    }

    /**
     * Get admin/owner members of a group.
     *
     * @return array<object>
     */
    private function getGroupAdmins(int $groupId, int $tenantId): array
    {
        return DB::select(
            "SELECT gm.user_id FROM group_members gm
             JOIN `groups` g ON gm.group_id = g.id
             WHERE gm.group_id = ? AND g.tenant_id = ?
             AND gm.role IN ('admin', 'owner')
             AND gm.status IN ('active', 'approved')",
            [$groupId, $tenantId]
        );
    }

    /**
     * Get all active members of a group.
     *
     * @return array<object>
     */
    private function getActiveMembers(int $groupId, int $tenantId): array
    {
        return DB::select(
            "SELECT gm.user_id FROM group_members gm
             JOIN `groups` g ON gm.group_id = g.id
             WHERE gm.group_id = ? AND g.tenant_id = ?
             AND gm.status IN ('active', 'approved')",
            [$groupId, $tenantId]
        );
    }
}

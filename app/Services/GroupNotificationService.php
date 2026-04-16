<?php
// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

namespace App\Services;

use App\Core\TenantContext;
use App\Services\NotificationDispatcher;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * GroupNotificationService — sends in-app + email + push notifications for group events.
 *
 * Native Laravel implementation (replaces legacy wrapper).
 * Uses NotificationDispatcher::dispatch() to create in-app bell notifications
 * AND trigger email/push delivery based on user frequency settings.
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

        $safeUserName  = htmlspecialchars($userName, ENT_QUOTES, 'UTF-8');
        $safeGroupName = htmlspecialchars($group->name, ENT_QUOTES, 'UTF-8');
        $message = __('notifications.group_join_request', ['name' => $userName, 'group' => $group->name]);
        $link = "/groups/{$groupId}/members?tab=requests";
        $htmlContent = "<p>" . __('notifications.group_join_request', ['name' => "<strong>{$safeUserName}</strong>", 'group' => "<strong>{$safeGroupName}</strong>"]) . "</p>"
            . "<p><a href=\"{$link}\">" . __('notifications.group_join_request_review') . "</a></p>";

        foreach ($admins as $admin) {
            if ((int) $admin->user_id === $userId) {
                continue; // Don't notify the requester if they're somehow an admin
            }
            try {
                NotificationDispatcher::dispatch(
                    (int) $admin->user_id,
                    'group',
                    $groupId,
                    'group_join_request',
                    $message,
                    $link,
                    $htmlContent
                );
            } catch (\Throwable $e) {
                Log::error('GroupNotification: failed to dispatch join request notification', [
                    'group_id' => $groupId,
                    'admin_user_id' => $admin->user_id,
                    'error' => $e->getMessage(),
                ]);
            }
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

        $safeGroupName = htmlspecialchars($group->name, ENT_QUOTES, 'UTF-8');
        $message = __('notifications.group_joined', ['group' => $group->name]);
        $link = "/groups/{$groupId}";
        $htmlContent = "<p>" . __('notifications.group_joined', ['group' => "<strong>{$safeGroupName}</strong>"]) . "</p>"
            . "<p><a href=\"{$link}\">" . __('notifications.group_joined_visit') . "</a></p>";

        try {
            NotificationDispatcher::dispatch(
                $userId,
                'group',
                $groupId,
                'group_join',
                $message,
                $link,
                $htmlContent
            );
        } catch (\Throwable $e) {
            Log::error('GroupNotification: failed to dispatch joined notification', [
                'group_id' => $groupId,
                'user_id' => $userId,
                'error' => $e->getMessage(),
            ]);
        }
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

        $safeGroupName = htmlspecialchars($group->name, ENT_QUOTES, 'UTF-8');
        $message = __('notifications.group_join_rejected', ['group' => $group->name]);
        $link = "/groups";
        $htmlContent = "<p>" . __('notifications.group_join_rejected', ['group' => "<strong>{$safeGroupName}</strong>"]) . "</p>"
            . "<p><a href=\"{$link}\">" . __('notifications.group_browse_others') . "</a></p>";

        try {
            NotificationDispatcher::dispatch(
                $userId,
                'group',
                $groupId,
                'group_join_rejected',
                $message,
                $link,
                $htmlContent
            );
        } catch (\Throwable $e) {
            Log::error('GroupNotification: failed to dispatch join rejected notification', [
                'group_id' => $groupId,
                'user_id' => $userId,
                'error' => $e->getMessage(),
            ]);
        }
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

        $safeTitle     = htmlspecialchars($title, ENT_QUOTES, 'UTF-8');
        $safeAuthor    = htmlspecialchars($authorName, ENT_QUOTES, 'UTF-8');
        $safeGroupName = htmlspecialchars($group->name, ENT_QUOTES, 'UTF-8');
        $message = __('notifications.group_new_discussion', ['author' => $authorName, 'title' => $title, 'group' => $group->name]);
        $link = "/groups/{$groupId}/discussions/{$discussionId}";
        $htmlContent = "<p>" . __('notifications.group_new_discussion', ['author' => "<strong>{$safeAuthor}</strong>", 'title' => "<strong>\"{$safeTitle}\"</strong>", 'group' => "<strong>{$safeGroupName}</strong>"]) . "</p>"
            . "<p><a href=\"{$link}\">" . __('notifications.group_view_discussion') . "</a></p>";

        $members = $this->getActiveMembers($groupId, $tenantId);

        foreach ($members as $member) {
            if ((int) $member->user_id === $authorId) {
                continue; // Don't notify the author
            }
            try {
                NotificationDispatcher::dispatch(
                    (int) $member->user_id,
                    'group',
                    $groupId,
                    'new_topic',
                    $message,
                    $link,
                    $htmlContent
                );
            } catch (\Throwable $e) {
                Log::error('GroupNotification: failed to dispatch new discussion notification', [
                    'group_id' => $groupId,
                    'discussion_id' => $discussionId,
                    'member_user_id' => $member->user_id,
                    'error' => $e->getMessage(),
                ]);
            }
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

        $safeTitle     = htmlspecialchars($title, ENT_QUOTES, 'UTF-8');
        $safeAuthor    = htmlspecialchars($authorName, ENT_QUOTES, 'UTF-8');
        $safeGroupName = htmlspecialchars($group->name, ENT_QUOTES, 'UTF-8');
        $message = __('notifications.group_new_announcement', ['author' => $authorName, 'title' => $title, 'group' => $group->name]);
        $link = "/groups/{$groupId}/announcements";
        $htmlContent = "<p>" . __('notifications.group_new_announcement', ['author' => "<strong>{$safeAuthor}</strong>", 'title' => "<strong>\"{$safeTitle}\"</strong>", 'group' => "<strong>{$safeGroupName}</strong>"]) . "</p>"
            . "<p><a href=\"{$link}\">" . __('notifications.group_view_announcement') . "</a></p>";

        $members = $this->getActiveMembers($groupId, $tenantId);

        foreach ($members as $member) {
            if ((int) $member->user_id === $authorId) {
                continue; // Don't notify the author
            }
            try {
                NotificationDispatcher::dispatch(
                    (int) $member->user_id,
                    'group',
                    $groupId,
                    'new_topic',
                    $message,
                    $link,
                    $htmlContent,
                    true // isOrganizer: announcements are admin-only, treat as organizer priority
                );
            } catch (\Throwable $e) {
                Log::error('GroupNotification: failed to dispatch announcement notification', [
                    'group_id' => $groupId,
                    'member_user_id' => $member->user_id,
                    'error' => $e->getMessage(),
                ]);
            }
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

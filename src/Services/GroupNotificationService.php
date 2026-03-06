<?php
// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

namespace Nexus\Services;

use Nexus\Core\Database;
use Nexus\Core\Mailer;
use Nexus\Core\EmailTemplate;
use Nexus\Core\TenantContext;
use Nexus\Models\Notification;
use Nexus\Models\User;

/**
 * GroupNotificationService
 *
 * Handles in-app (bell) and email notifications for group-related actions.
 *
 * IMPORTANT: In-app notification links must be bare paths (e.g., "/groups/5")
 * because the React frontend's tenantPath() adds the tenant slug prefix.
 * Email links need the full path with slug prefix (e.g., "/hour-timebank/groups/5")
 * because sendEmail() only prepends the frontend domain.
 */
class GroupNotificationService
{
    /**
     * Notify group admins when someone requests to join
     */
    public static function notifyJoinRequest(int $groupId, int $userId): void
    {
        try {
            $tenantId = TenantContext::getId();
            $group = self::getGroup($groupId, $tenantId);
            $user = User::findById($userId);
            if (!$group || !$user) return;

            $userName = $user['name'] ?? trim(($user['first_name'] ?? '') . ' ' . ($user['last_name'] ?? ''));
            $groupName = $group['name'];
            $path = '/groups/' . $groupId;
            $message = "{$userName} requested to join {$groupName}";

            $admins = self::getGroupAdmins($groupId, $tenantId);
            foreach ($admins as $admin) {
                $adminId = (int)$admin['user_id'];
                if ($adminId === $userId) continue;

                Notification::create($adminId, $message, $path, 'group_join_request');

                self::sendEmail(
                    $adminId,
                    "Join Request - {$groupName}",
                    "{$userName} wants to join your group",
                    "<strong>Group:</strong> " . htmlspecialchars($groupName) . "<br><br>" .
                    "<strong>Member:</strong> " . htmlspecialchars($userName) . "<br><br>" .
                    "Please review this membership request.",
                    "Review Request",
                    $path
                );
            }
        } catch (\Throwable $e) {
            error_log("GroupNotificationService::notifyJoinRequest error: " . $e->getMessage());
        }
    }

    /**
     * Notify user when they join a group (instant) or are accepted
     */
    public static function notifyJoined(int $groupId, int $userId): void
    {
        try {
            $tenantId = TenantContext::getId();
            $group = self::getGroup($groupId, $tenantId);
            if (!$group) return;

            $groupName = $group['name'];
            $path = '/groups/' . $groupId;
            $message = "Welcome to {$groupName}! You are now a member.";

            Notification::create($userId, $message, $path, 'group_joined');

            self::sendEmail(
                $userId,
                "Welcome to {$groupName}!",
                "You are now a member",
                "You've been accepted into <strong>" . htmlspecialchars($groupName) . "</strong>.<br><br>" .
                "You can now participate in group discussions, events, and activities.",
                "View Group",
                $path
            );
        } catch (\Throwable $e) {
            error_log("GroupNotificationService::notifyJoined error: " . $e->getMessage());
        }
    }

    /**
     * Notify user when their join request is rejected
     */
    public static function notifyJoinRejected(int $groupId, int $userId): void
    {
        try {
            $tenantId = TenantContext::getId();
            $group = self::getGroup($groupId, $tenantId);
            if (!$group) return;

            $groupName = $group['name'];
            $path = '/groups';
            $message = "Your request to join {$groupName} was not approved";

            Notification::create($userId, $message, $path, 'group_join_rejected');

            self::sendEmail(
                $userId,
                "Group Request Update",
                "Your request was not approved",
                "Your request to join <strong>" . htmlspecialchars($groupName) . "</strong> was not approved at this time.<br><br>" .
                "You can browse other groups to find communities that match your interests.",
                "Browse Groups",
                $path
            );
        } catch (\Throwable $e) {
            error_log("GroupNotificationService::notifyJoinRejected error: " . $e->getMessage());
        }
    }

    /**
     * Notify group members when a new discussion is created
     */
    public static function notifyNewDiscussion(int $groupId, int $discussionId, int $authorId, string $title): void
    {
        try {
            $tenantId = TenantContext::getId();
            $group = self::getGroup($groupId, $tenantId);
            $author = User::findById($authorId);
            if (!$group || !$author) return;

            $authorName = $author['name'] ?? trim(($author['first_name'] ?? '') . ' ' . ($author['last_name'] ?? ''));
            $groupName = $group['name'];
            $path = '/groups/' . $groupId;
            $message = "{$authorName} started a discussion in {$groupName}: \"{$title}\"";

            $members = self::getGroupMembers($groupId, $tenantId);
            foreach ($members as $member) {
                $memberId = (int)$member['user_id'];
                if ($memberId === $authorId) continue;

                Notification::create($memberId, $message, $path, 'group_discussion');
            }

            // Email group members (batch — limit to first 50 to avoid overload)
            $emailCount = 0;
            foreach ($members as $member) {
                $memberId = (int)$member['user_id'];
                if ($memberId === $authorId) continue;
                if ($emailCount >= 50) break;

                self::sendEmail(
                    $memberId,
                    "New Discussion in {$groupName}",
                    "{$authorName} started a new discussion",
                    "<strong>Group:</strong> " . htmlspecialchars($groupName) . "<br><br>" .
                    "<strong>Topic:</strong> " . htmlspecialchars($title) . "<br><br>" .
                    "Join the conversation and share your thoughts.",
                    "View Discussion",
                    $path
                );
                $emailCount++;
            }
        } catch (\Throwable $e) {
            error_log("GroupNotificationService::notifyNewDiscussion error: " . $e->getMessage());
        }
    }

    /**
     * Notify group members when a new announcement is posted
     */
    public static function notifyNewAnnouncement(int $groupId, int $authorId, string $title): void
    {
        try {
            $tenantId = TenantContext::getId();
            $group = self::getGroup($groupId, $tenantId);
            $author = User::findById($authorId);
            if (!$group || !$author) return;

            $authorName = $author['name'] ?? trim(($author['first_name'] ?? '') . ' ' . ($author['last_name'] ?? ''));
            $groupName = $group['name'];
            $path = '/groups/' . $groupId;
            $message = "New announcement in {$groupName}: \"{$title}\"";

            $members = self::getGroupMembers($groupId, $tenantId);
            foreach ($members as $member) {
                $memberId = (int)$member['user_id'];
                if ($memberId === $authorId) continue;

                Notification::create($memberId, $message, $path, 'group_announcement');
            }

            // Email all members
            $emailCount = 0;
            foreach ($members as $member) {
                $memberId = (int)$member['user_id'];
                if ($memberId === $authorId) continue;
                if ($emailCount >= 50) break;

                self::sendEmail(
                    $memberId,
                    "Announcement: {$groupName}",
                    "New announcement from {$authorName}",
                    "<strong>Group:</strong> " . htmlspecialchars($groupName) . "<br><br>" .
                    "<strong>Announcement:</strong> " . htmlspecialchars($title) . "<br><br>" .
                    "Check the group page for full details.",
                    "View Announcement",
                    $path
                );
                $emailCount++;
            }
        } catch (\Throwable $e) {
            error_log("GroupNotificationService::notifyNewAnnouncement error: " . $e->getMessage());
        }
    }

    // =========================================================================
    // PRIVATE HELPERS
    // =========================================================================

    private static function getGroup(int $groupId, int $tenantId): ?array
    {
        $group = Database::query(
            "SELECT id, name, user_id FROM groups_table WHERE id = ? AND tenant_id = ?",
            [$groupId, $tenantId]
        )->fetch();
        return $group ?: null;
    }

    private static function getGroupAdmins(int $groupId, int $tenantId): array
    {
        return Database::query(
            "SELECT user_id FROM group_members WHERE group_id = ? AND tenant_id = ? AND role IN ('admin', 'owner', 'moderator') AND status = 'active'",
            [$groupId, $tenantId]
        )->fetchAll(\PDO::FETCH_ASSOC);
    }

    private static function getGroupMembers(int $groupId, int $tenantId): array
    {
        return Database::query(
            "SELECT user_id FROM group_members WHERE group_id = ? AND tenant_id = ? AND status = 'active'",
            [$groupId, $tenantId]
        )->fetchAll(\PDO::FETCH_ASSOC);
    }

    /**
     * Send email — automatically adds tenant slug prefix to the path for the full URL.
     * Callers pass bare paths like "/groups/5", this method builds the full email URL.
     */
    private static function sendEmail(int $userId, string $title, string $subtitle, string $body, string $btnText, string $path): void
    {
        try {
            $user = User::findById($userId);
            if (!$user || empty($user['email'])) return;

            $tenantName = TenantContext::getSetting('site_name', 'Project NEXUS');
            $frontendUrl = TenantContext::getFrontendUrl();
            $basePath = TenantContext::getSlugPrefix();
            $fullUrl = $frontendUrl . $basePath . $path;

            $html = EmailTemplate::render($title, $subtitle, $body, $btnText, $fullUrl, $tenantName);

            $mailer = Mailer::forCurrentTenant();
            $mailer->send($user['email'], "{$title} - {$tenantName}", $html);
        } catch (\Throwable $e) {
            error_log("GroupNotificationService::sendEmail error: " . $e->getMessage());
        }
    }
}

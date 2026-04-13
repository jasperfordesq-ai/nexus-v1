<?php
// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

namespace App\Services;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use App\Core\TenantContext;
use App\Models\Notification;

/**
 * GroupMentionService — Handles @mention parsing, notification, and member
 * suggestions within the context of a group.
 *
 * All queries are tenant-scoped via TenantContext::getId().
 */
class GroupMentionService
{
    /**
     * Extract @username mentions from content and resolve them to valid
     * tenant users.
     *
     * @param string $content Text that may contain @username mentions
     * @return array<int, array{username: string, user_id: int}> Resolved mentions
     */
    public static function parseMentions(string $content): array
    {
        preg_match_all('/@(\w+)/', $content, $matches);

        $usernames = array_unique($matches[1] ?? []);

        if (empty($usernames)) {
            return [];
        }

        $tenantId = TenantContext::getId();

        $users = DB::table('users')
            ->where('tenant_id', $tenantId)
            ->where('status', '!=', 'banned')
            ->whereIn('username', $usernames)
            ->select(['id', 'username'])
            ->get();

        $results = [];
        foreach ($users as $user) {
            $results[] = [
                'username' => $user->username,
                'user_id'  => (int) $user->id,
            ];
        }

        return $results;
    }

    /**
     * Parse mentions from content and create a notification for each valid
     * mentioned user.
     *
     * @param int    $groupId     The group where the mention occurred
     * @param int    $authorId    The user who authored the content
     * @param string $content     Text that may contain @username mentions
     * @param string $contextType Context label (e.g. 'discussion', 'comment')
     * @param int    $contextId   ID of the contextual entity
     */
    public static function notifyMentioned(
        int $groupId,
        int $authorId,
        string $content,
        string $contextType,
        int $contextId
    ): void {
        $tenantId = TenantContext::getId();
        $mentions = self::parseMentions($content);

        if (empty($mentions)) {
            return;
        }

        // Fetch group name for the notification message
        $group = DB::selectOne(
            "SELECT name FROM `groups` WHERE id = ? AND tenant_id = ?",
            [$groupId, $tenantId]
        );

        $groupName = $group->name ?? 'a group';
        $link = '/groups/' . $groupId;

        foreach ($mentions as $mention) {
            // Don't notify the author about their own mention
            if ($mention['user_id'] === $authorId) {
                continue;
            }

            try {
                Notification::createNotification(
                    $mention['user_id'],
                    __('svc_notifications.group_mention.mentioned_in_group', ['group' => $groupName]),
                    $link,
                    'group_mention'
                );
            } catch (\Exception $e) {
                Log::debug("GroupMentionService::notifyMentioned error: " . $e->getMessage());
            }
        }
    }

    /**
     * Search group members for @mention autocomplete.
     *
     * Returns members whose name or username matches the query string,
     * limited to a specific group.
     *
     * @param int    $groupId Group to search within
     * @param string $query   Search term to match against name/username
     * @param int    $limit   Maximum results to return
     * @return array<int, array{id: int, name: string, username: string|null, avatar_url: string|null}>
     */
    public static function getMemberSuggestions(int $groupId, string $query, int $limit = 10): array
    {
        $tenantId = TenantContext::getId();
        $searchTerm = '%' . $query . '%';

        $results = DB::select(
            "SELECT u.id,
                    COALESCE(u.name, CONCAT(u.first_name, ' ', COALESCE(u.last_name, ''))) as name,
                    u.username,
                    u.avatar_url
             FROM group_members gm
             JOIN users u ON gm.user_id = u.id
             WHERE gm.group_id = ?
               AND u.tenant_id = ?
               AND u.status != 'banned'
               AND (u.name LIKE ? OR u.first_name LIKE ? OR u.last_name LIKE ? OR u.username LIKE ?)
             ORDER BY u.first_name ASC
             LIMIT ?",
            [$groupId, $tenantId, $searchTerm, $searchTerm, $searchTerm, $searchTerm, $limit]
        );

        return array_map(function ($user) {
            return [
                'id'         => (int) $user->id,
                'name'       => $user->name ?? '',
                'username'   => $user->username,
                'avatar_url' => $user->avatar_url,
            ];
        }, $results);
    }
}

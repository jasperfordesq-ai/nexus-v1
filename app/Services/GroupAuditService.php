<?php
// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

declare(strict_types=1);

namespace App\Services;

use Illuminate\Support\Facades\DB;
use App\Core\TenantContext;

/**
 * GroupAuditService — Comprehensive audit logging for group actions.
 *
 * Tracks group lifecycle events (create, update, delete, feature),
 * member management (join, leave, kick, ban), and content moderation
 * (discussions, posts). All operations are tenant-scoped.
 */
class GroupAuditService
{
    // ─── Group action constants ─────────────────────────────────────
    public const ACTION_GROUP_CREATED = 'group_created';
    public const ACTION_GROUP_UPDATED = 'group_updated';
    public const ACTION_GROUP_DELETED = 'group_deleted';
    public const ACTION_GROUP_FEATURED = 'group_featured';
    public const ACTION_GROUP_IMAGE_UPDATED = 'group_image_updated';

    // ─── Member action constants ────────────────────────────────────
    public const ACTION_MEMBER_JOINED = 'member_joined';
    public const ACTION_MEMBER_JOIN_REQUESTED = 'member_join_requested';
    public const ACTION_MEMBER_JOIN_REJECTED = 'member_join_rejected';
    public const ACTION_MEMBER_LEFT = 'member_left';
    public const ACTION_MEMBER_KICKED = 'member_kicked';
    public const ACTION_MEMBER_BANNED = 'member_banned';
    public const ACTION_MEMBER_ROLE_CHANGED = 'member_role_changed';
    public const ACTION_MEMBER_REMOVED = 'member_removed';
    public const ACTION_INVITE_REVOKED = 'invite_revoked';

    // ─── Content action constants ───────────────────────────────────
    public const ACTION_DISCUSSION_CREATED = 'discussion_created';
    public const ACTION_POST_CREATED = 'post_created';
    public const ACTION_POST_MODERATED = 'post_moderated';
    public const ACTION_FILE_UPLOADED = 'file_uploaded';
    public const ACTION_FILE_DELETED = 'file_deleted';
    public const ACTION_MEDIA_UPLOADED = 'media_uploaded';
    public const ACTION_MEDIA_DELETED = 'media_deleted';
    public const ACTION_ANNOUNCEMENT_DELETED = 'announcement_deleted';
    public const ACTION_QA_QUESTION_DELETED = 'qa_question_deleted';
    public const ACTION_QA_ANSWER_DELETED = 'qa_answer_deleted';
    public const ACTION_QA_ANSWER_ACCEPTED = 'qa_answer_accepted';
    public const ACTION_WIKI_PAGE_DELETED = 'wiki_page_deleted';
    public const ACTION_CHATROOM_DELETED = 'chatroom_deleted';
    public const ACTION_CHATROOM_MESSAGE_DELETED = 'chatroom_message_deleted';
    public const ACTION_CHATROOM_MESSAGE_PINNED = 'chatroom_message_pinned';
    public const ACTION_CHATROOM_MESSAGE_UNPINNED = 'chatroom_message_unpinned';
    public const ACTION_TEAM_TASK_DELETED = 'team_task_deleted';
    public const ACTION_SCHEDULED_POST_CANCELLED = 'scheduled_post_cancelled';
    public const ACTION_WEBHOOK_DELETED = 'webhook_deleted';
    public const ACTION_WEBHOOK_TOGGLED = 'webhook_toggled';

    // Challenge economy actions
    public const ACTION_CHALLENGE_CREATED = 'challenge_created';
    public const ACTION_CHALLENGE_COMPLETED = 'challenge_completed';
    public const ACTION_CHALLENGE_REWARD_AWARDED = 'challenge_reward_awarded';
    public const ACTION_CHALLENGE_CANCELLED = 'challenge_cancelled';

    /**
     * Log an audit entry for a group action.
     */
    public static function log(string $action, int $groupId, int $userId, array $details = []): int
    {
        $tenantId = TenantContext::getId();
        $ipAddress = $_SERVER['REMOTE_ADDR'] ?? request()->ip() ?? null;
        $detailsJson = !empty($details)
            ? json_encode(self::sanitizeDetails($details), JSON_THROW_ON_ERROR)
            : null;

        return DB::table('group_audit_log')->insertGetId([
            'tenant_id'  => $tenantId,
            'group_id'   => $groupId,
            'user_id'    => $userId,
            'action'     => $action,
            'details'    => $detailsJson,
            'ip_address' => $ipAddress,
            'created_at' => now(),
        ]);
    }

    /**
     * Get the audit log for a specific group.
     */
    public static function getGroupLog(int $groupId, array $filters = []): array
    {
        $tenantId = TenantContext::getId();

        $query = DB::table('group_audit_log')
            ->where('group_id', $groupId)
            ->where('tenant_id', $tenantId);

        if (!empty($filters['action'])) {
            $query->where('action', $filters['action']);
        }

        return $query->orderByDesc('created_at')
            ->get()
            ->map(static fn (object $row): array => self::sanitizeRowForOutput((array) $row))
            ->toArray();
    }

    /**
     * Return a bounded, tenant-scoped audit page for the admin UI.
     *
     * @return array{items: list<array<string, mixed>>, actions: list<string>, pagination: array{page: int, per_page: int, total: int, total_pages: int, has_more: bool}}
     */
    public static function getGroupLogPage(int $groupId, array $filters = []): array
    {
        $tenantId = (int) TenantContext::getId();
        $page = max(1, (int) ($filters['page'] ?? 1));
        $perPage = max(1, min((int) ($filters['per_page'] ?? 25), 100));
        $action = isset($filters['action']) && is_string($filters['action'])
            ? trim($filters['action'])
            : '';

        $base = DB::table('group_audit_log')
            ->where('group_id', $groupId)
            ->where('tenant_id', $tenantId);
        $actions = (clone $base)
            ->whereNotNull('action')
            ->distinct()
            ->orderBy('action')
            ->pluck('action')
            ->filter(static fn (mixed $value): bool => is_string($value) && $value !== '')
            ->values()
            ->all();

        if ($action !== '') {
            $base->where('action', $action);
        }

        $total = (int) (clone $base)->count();
        $totalPages = max(1, (int) ceil($total / $perPage));
        $items = $base
            ->orderByDesc('created_at')
            ->orderByDesc('id')
            ->forPage($page, $perPage)
            ->get()
            ->map(static fn (object $row): array => self::sanitizeRowForOutput((array) $row))
            ->values()
            ->all();

        return [
            'items' => $items,
            'actions' => $actions,
            'pagination' => [
                'page' => $page,
                'per_page' => $perPage,
                'total' => $total,
                'total_pages' => $totalPages,
                'has_more' => $page < $totalPages,
            ],
        ];
    }

    /**
     * Get a specific user's activity within a group.
     */
    public static function getUserGroupActivity(int $groupId, int $userId): array
    {
        $tenantId = TenantContext::getId();

        return DB::table('group_audit_log')
            ->where('group_id', $groupId)
            ->where('user_id', $userId)
            ->where('tenant_id', $tenantId)
            ->orderByDesc('created_at')
            ->get()
            ->map(static fn (object $row): array => self::sanitizeRowForOutput((array) $row))
            ->toArray();
    }

    /**
     * Get an activity summary for a group.
     */
    public static function getActivitySummary(int $groupId): array
    {
        $tenantId = TenantContext::getId();

        $totalActions = (int) DB::table('group_audit_log')
            ->where('group_id', $groupId)
            ->where('tenant_id', $tenantId)
            ->count();

        $actionsByType = DB::table('group_audit_log')
            ->where('group_id', $groupId)
            ->where('tenant_id', $tenantId)
            ->select('action', DB::raw('COUNT(*) as count'))
            ->groupBy('action')
            ->orderByDesc('count')
            ->get()
            ->map(fn ($row) => (array) $row)
            ->toArray();

        $mostActiveUsers = DB::table('group_audit_log')
            ->where('group_id', $groupId)
            ->where('tenant_id', $tenantId)
            ->select('user_id', DB::raw('COUNT(*) as action_count'))
            ->groupBy('user_id')
            ->orderByDesc('action_count')
            ->limit(5)
            ->get()
            ->map(fn ($row) => (array) $row)
            ->toArray();

        return [
            'total_actions'     => $totalActions,
            'actions_by_type'   => $actionsByType,
            'most_active_users' => $mostActiveUsers,
        ];
    }

    /** @return array<string, mixed> */
    private static function sanitizeDetails(array $details): array
    {
        $sensitiveKeys = [
            'password',
            'passphrase',
            'token',
            'secret',
            'api_key',
            'apikey',
            'authorization',
            'bearer',
            'cookie',
            'session_id',
            'csrf',
        ];

        foreach ($details as $key => $value) {
            $normalizedKey = strtolower((string) $key);
            $isSensitive = false;
            foreach ($sensitiveKeys as $needle) {
                if (str_contains($normalizedKey, $needle)) {
                    $isSensitive = true;
                    break;
                }
            }
            if ($isSensitive) {
                $details[$key] = '[REDACTED]';
                continue;
            }
            if (is_array($value)) {
                $details[$key] = self::sanitizeDetails($value);
                continue;
            }
            if (self::isSensitiveValue($value)) {
                $details[$key] = '[REDACTED]';
            }
        }

        return $details;
    }

    private static function isSensitiveValue(mixed $value): bool
    {
        if (! is_string($value)) {
            return false;
        }

        $value = trim($value);
        if ($value === '') {
            return false;
        }

        return preg_match('/\bBearer\s+[A-Za-z0-9._~+\/=:-]{8,}/i', $value) === 1
            || preg_match('/\b(?:token|secret|api[_-]?key|authorization|cookie|passphrase)\s*[:=]\s*\S+/i', $value) === 1
            || preg_match('/(?:^|[-_])(?:token|secret|passphrase|api[_-]?key)(?:[-_]|$)/i', $value) === 1
            || preg_match('/^(?:sk|gh[pousr]|xox[baprs])[-_][A-Za-z0-9_-]{12,}$/i', $value) === 1
            || preg_match('/^eyJ[A-Za-z0-9_-]+\.[A-Za-z0-9_-]+\.[A-Za-z0-9_-]+$/', $value) === 1;
    }

    /**
     * Sanitize a stored audit row before it crosses an API/export boundary.
     * Legacy invalid JSON is treated as wholly sensitive because its structure
     * cannot be inspected safely.
     *
     * @param array<string, mixed> $row
     * @return array<string, mixed>
     */
    public static function sanitizeRowForOutput(array $row): array
    {
        if (array_key_exists('details', $row)) {
            $row['details'] = self::sanitizeStoredDetails($row['details']);
        }

        return $row;
    }

    private static function sanitizeStoredDetails(mixed $details): string|null
    {
        if ($details === null || $details === '') {
            return null;
        }

        if (! is_string($details)) {
            return json_encode(['legacy_details' => '[REDACTED]'], JSON_THROW_ON_ERROR);
        }

        try {
            $decoded = json_decode($details, true, 512, JSON_THROW_ON_ERROR);
        } catch (\JsonException) {
            return json_encode(['legacy_details' => '[REDACTED]'], JSON_THROW_ON_ERROR);
        }
        if (! is_array($decoded)) {
            return json_encode(['legacy_details' => '[REDACTED]'], JSON_THROW_ON_ERROR);
        }

        return json_encode(self::sanitizeDetails($decoded), JSON_THROW_ON_ERROR);
    }
}

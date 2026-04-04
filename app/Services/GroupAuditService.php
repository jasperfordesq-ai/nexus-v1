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

    // ─── Member action constants ────────────────────────────────────
    public const ACTION_MEMBER_JOINED = 'member_joined';
    public const ACTION_MEMBER_LEFT = 'member_left';
    public const ACTION_MEMBER_KICKED = 'member_kicked';
    public const ACTION_MEMBER_BANNED = 'member_banned';

    // ─── Content action constants ───────────────────────────────────
    public const ACTION_DISCUSSION_CREATED = 'discussion_created';
    public const ACTION_POST_CREATED = 'post_created';
    public const ACTION_POST_MODERATED = 'post_moderated';

    /**
     * Log an audit entry for a group action.
     */
    public static function log(string $action, int $groupId, int $userId, array $details = []): int
    {
        $tenantId = TenantContext::getId();
        $ipAddress = $_SERVER['REMOTE_ADDR'] ?? request()->ip() ?? null;
        $detailsJson = !empty($details) ? json_encode($details) : null;

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
            ->map(fn ($row) => (array) $row)
            ->toArray();
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
            ->map(fn ($row) => (array) $row)
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
}

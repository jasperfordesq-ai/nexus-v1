<?php
// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

namespace App\Services;

use Illuminate\Support\Facades\DB;
use App\Core\TenantContext;

/**
 * GroupNotificationPreferenceService — Per-group notification preferences.
 */
class GroupNotificationPreferenceService
{
    public static function get(int $userId, int $groupId): array
    {
        $tenantId = TenantContext::getId();
        $pref = DB::table('group_notification_preferences')
            ->where('user_id', $userId)
            ->where('group_id', $groupId)
            ->where('tenant_id', $tenantId)
            ->first();

        return $pref ? (array) $pref : [
            'frequency' => 'instant',
            'email_enabled' => true,
            'push_enabled' => true,
        ];
    }

    public static function set(int $userId, int $groupId, array $data): void
    {
        $tenantId = TenantContext::getId();
        DB::table('group_notification_preferences')->updateOrInsert(
            ['user_id' => $userId, 'group_id' => $groupId],
            [
                'tenant_id' => $tenantId,
                'frequency' => $data['frequency'] ?? 'instant',
                'email_enabled' => $data['email_enabled'] ?? true,
                'push_enabled' => $data['push_enabled'] ?? true,
                'updated_at' => now(),
            ]
        );
    }

    /**
     * Check if user should receive notification for this group.
     */
    public static function shouldNotify(int $userId, int $groupId, string $channel = 'in_app'): bool
    {
        $pref = self::get($userId, $groupId);
        if ($pref['frequency'] === 'muted') return false;
        if ($channel === 'email' && !($pref['email_enabled'] ?? true)) return false;
        if ($channel === 'push' && !($pref['push_enabled'] ?? true)) return false;
        return true;
    }

    /**
     * Get all preferences for a user across groups.
     */
    public static function getAllForUser(int $userId): array
    {
        $tenantId = TenantContext::getId();
        return DB::table('group_notification_preferences as gnp')
            ->join('groups as g', 'gnp.group_id', '=', 'g.id')
            ->where('gnp.user_id', $userId)
            ->where('gnp.tenant_id', $tenantId)
            ->select('gnp.*', 'g.name as group_name')
            ->get()
            ->map(fn ($r) => (array) $r)
            ->toArray();
    }
}

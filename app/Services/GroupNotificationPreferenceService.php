<?php
// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

namespace App\Services;

use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;
use App\Core\TenantContext;

/**
 * GroupNotificationPreferenceService — Per-group notification preferences.
 */
class GroupNotificationPreferenceService
{
    private const FREQUENCIES = ['instant', 'digest', 'muted'];

    /** @return array{frequency: string, email_enabled: bool, push_enabled: bool, updated_at: string|null} */
    public static function get(int $userId, int $groupId): array
    {
        $tenantId = TenantContext::getId();
        $pref = DB::table('group_notification_preferences')
            ->where('user_id', $userId)
            ->where('group_id', $groupId)
            ->where('tenant_id', $tenantId)
            ->first();

        return self::formatPreference($pref);
    }

    /**
     * @param list<int> $userIds
     * @return array<int, array{frequency: string, email_enabled: bool, push_enabled: bool, updated_at: string|null}>
     */
    public static function getForUsers(array $userIds, int $groupId): array
    {
        $tenantId = (int) TenantContext::getId();
        $userIds = array_values(array_unique(array_filter(
            array_map('intval', $userIds),
            static fn (int $userId): bool => $userId > 0,
        )));
        if ($userIds === []) {
            return [];
        }

        $preferences = [];
        foreach ($userIds as $userId) {
            $preferences[$userId] = self::formatPreference(null);
        }

        $rows = DB::table('group_notification_preferences')
            ->where('tenant_id', $tenantId)
            ->where('group_id', $groupId)
            ->whereIn('user_id', $userIds)
            ->get();
        foreach ($rows as $row) {
            $preferences[(int) $row->user_id] = self::formatPreference($row);
        }

        return $preferences;
    }

    /** @return array{frequency: string, email_enabled: bool, push_enabled: bool, updated_at: string|null} */
    public static function set(int $userId, int $groupId, array $data): array
    {
        $tenantId = (int) TenantContext::getId();
        $frequency = $data['frequency'] ?? null;
        if (! is_string($frequency) || ! in_array($frequency, self::FREQUENCIES, true)) {
            throw new \InvalidArgumentException('Invalid group notification frequency');
        }
        $emailEnabled = self::requiredBoolean($data['email_enabled'] ?? null);
        $pushEnabled = self::requiredBoolean($data['push_enabled'] ?? null);

        DB::table('group_notification_preferences')->updateOrInsert(
            ['tenant_id' => $tenantId, 'user_id' => $userId, 'group_id' => $groupId],
            [
                'frequency' => $frequency,
                'email_enabled' => $emailEnabled,
                'push_enabled' => $pushEnabled,
                'updated_at' => now(),
            ]
        );

        return self::get($userId, $groupId);
    }

    /** @return array{frequency: string, email_enabled: bool, push_enabled: bool, updated_at: string|null} */
    private static function formatPreference(?object $pref): array
    {
        if ($pref === null) {
            return [
                'frequency' => 'instant',
                'email_enabled' => true,
                'push_enabled' => true,
                'updated_at' => null,
            ];
        }

        $frequency = is_string($pref->frequency ?? null)
            && in_array($pref->frequency, self::FREQUENCIES, true)
            ? $pref->frequency
            : 'instant';
        $updatedAt = null;
        if (($pref->updated_at ?? null) !== null) {
            try {
                $updatedAt = CarbonImmutable::parse((string) $pref->updated_at)->utc()->toISOString();
            } catch (\Throwable) {
                $updatedAt = null;
            }
        }

        return [
            'frequency' => $frequency,
            'email_enabled' => (bool) ($pref->email_enabled ?? true),
            'push_enabled' => (bool) ($pref->push_enabled ?? true),
            'updated_at' => $updatedAt,
        ];
    }

    private static function requiredBoolean(mixed $value): bool
    {
        return match ($value) {
            true, 1, '1' => true,
            false, 0, '0' => false,
            default => throw new \InvalidArgumentException('Invalid group notification channel flag'),
        };
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
            ->map(static function (object $row): array {
                return [
                    'group_id' => (int) $row->group_id,
                    'group_name' => (string) $row->group_name,
                    ...self::formatPreference($row),
                ];
            })
            ->toArray();
    }
}

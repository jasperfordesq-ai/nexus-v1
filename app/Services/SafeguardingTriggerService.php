<?php
// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

namespace App\Services;

use App\Core\TenantContext;
use App\Events\SafeguardingFlaggedEvent;
use App\Models\TenantSafeguardingOption;
use App\Models\User;
use App\Models\UserSafeguardingPreference;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * Computes and activates behavioral triggers from safeguarding preferences.
 *
 * When a member selects safeguarding options during onboarding, this service:
 * 1. Merges triggers from all selected (non-revoked) options
 * 2. Writes to user_messaging_restrictions (existing broker infrastructure)
 * 3. Syncs user-level safeguarding flags (works_with_children, etc.)
 * 4. Dispatches SafeguardingFlaggedEvent for admin/broker notification
 *
 * The existing BrokerMessageVisibilityService already reads user_messaging_restrictions
 * — no changes needed there. This service just populates the flags.
 */
class SafeguardingTriggerService
{
    private const CACHE_PREFIX = 'safeguarding_triggers:';
    private const CACHE_TTL = 300; // 5 minutes

    /**
     * Internal DB marker for monitoring rows created by the safeguarding system.
     * Must start with 'Safeguarding:' — the cleanup query uses LIKE 'Safeguarding:%'.
     * Do NOT translate this constant — it is stored in the database and matched programmatically.
     */
    public const MONITORING_REASON_ONBOARDING = 'Safeguarding: self-identified during onboarding';

    /**
     * Known trigger keys with their defaults.
     */
    private const TRIGGER_DEFAULTS = [
        'requires_vetted_interaction' => false,
        'requires_broker_approval' => false,
        'restricts_messaging' => false,
        'restricts_matching' => false,
        'notify_admin_on_selection' => false,
    ];

    /**
     * Get the merged active triggers for a user. Cached for performance.
     *
     * @return array<string, bool|string>
     */
    public static function getActiveTriggers(int $userId, ?int $tenantId = null): array
    {
        $tenantId = $tenantId ?? TenantContext::getId();
        $cacheKey = self::CACHE_PREFIX . "{$tenantId}:{$userId}";

        return Cache::remember($cacheKey, self::CACHE_TTL, function () use ($userId, $tenantId) {
            // Get all active (non-revoked) preferences with their option triggers
            $preferences = UserSafeguardingPreference::where('tenant_id', $tenantId)
                ->where('user_id', $userId)
                ->active()
                ->with('option')
                ->get();

            $merged = self::TRIGGER_DEFAULTS;
            $vettingTypes = [];

            foreach ($preferences as $pref) {
                $option = $pref->option;
                if (!$option || !$option->is_active) {
                    continue;
                }

                $triggers = $option->triggers ?? [];

                // OR-merge boolean triggers
                foreach (self::TRIGGER_DEFAULTS as $key => $default) {
                    if (!empty($triggers[$key])) {
                        $merged[$key] = true;
                    }
                }

                // Collect vetting type requirements
                if (!empty($triggers['vetting_type_required'])) {
                    $vettingTypes[] = $triggers['vetting_type_required'];
                }
            }

            if (!empty($vettingTypes)) {
                $merged['vetting_types_required'] = array_unique($vettingTypes);
            }

            return $merged;
        });
    }

    /**
     * Activate broker protections based on a user's safeguarding preferences.
     *
     * Called after preferences are saved (during onboarding or settings update).
     * Writes to user_messaging_restrictions and syncs user flags.
     */
    public static function activateTriggersForUser(int $userId, ?int $tenantId = null): void
    {
        $tenantId = $tenantId ?? TenantContext::getId();

        // Invalidate cache first
        self::invalidateCache($userId, $tenantId);

        // Get fresh merged triggers
        $triggers = self::getActiveTriggers($userId, $tenantId);

        $needsMonitoring = $triggers['restricts_messaging'] || $triggers['requires_vetted_interaction'];
        $needsBrokerApproval = $triggers['requires_broker_approval'];
        $needsNotification = $triggers['notify_admin_on_selection'];

        // Update user_messaging_restrictions (the table that BrokerMessageVisibilityService reads)
        if ($needsMonitoring || $needsBrokerApproval) {
            DB::statement(
                "INSERT INTO user_messaging_restrictions (tenant_id, user_id, under_monitoring, requires_broker_approval, monitoring_reason, monitoring_started_at)
                 VALUES (?, ?, ?, ?, ?, NOW())
                 ON DUPLICATE KEY UPDATE
                   under_monitoring = VALUES(under_monitoring),
                   requires_broker_approval = VALUES(requires_broker_approval),
                   monitoring_reason = VALUES(monitoring_reason),
                   monitoring_started_at = COALESCE(monitoring_started_at, NOW())",
                [
                    $tenantId,
                    $userId,
                    $needsMonitoring ? 1 : 0,
                    $needsBrokerApproval ? 1 : 0,
                    self::MONITORING_REASON_ONBOARDING,
                ]
            );
        } else {
            // If no triggers active, clear monitoring (but don't clear if set by other means)
            DB::update(
                "UPDATE user_messaging_restrictions
                 SET under_monitoring = 0, requires_broker_approval = 0
                 WHERE tenant_id = ? AND user_id = ? AND monitoring_reason LIKE 'Safeguarding:%'", // matches MONITORING_REASON_ONBOARDING prefix
                [$tenantId, $userId]
            );
        }

        // Sync user-level safeguarding flags (transactional to keep flags consistent)
        DB::transaction(function () use ($userId, $tenantId, $triggers) {
            $user = User::find($userId);
            if ($user) {
                $updates = [];
                if ($triggers['restricts_matching']) {
                    // Check if any of the selected options specifically flag children or vulnerable adults
                    $prefs = UserSafeguardingPreference::where('tenant_id', $tenantId)
                        ->where('user_id', $userId)
                        ->active()
                        ->with('option')
                        ->get();

                    foreach ($prefs as $pref) {
                        $key = $pref->option?->option_key;
                        if ($key === 'works_with_children') {
                            $updates['works_with_children'] = true;
                        }
                        if ($key === 'works_with_vulnerable_adults') {
                            $updates['works_with_vulnerable_adults'] = true;
                        }
                        if ($key === 'no_home_visits') {
                            $updates['no_home_visits'] = true;
                        }
                    }
                }
                if (!empty($updates)) {
                    User::where('id', $userId)->update($updates);
                }
            }
        });

        // Notify admins/brokers if any trigger requires it
        if ($needsNotification) {
            try {
                event(new SafeguardingFlaggedEvent($userId, $tenantId, $triggers));
            } catch (\Throwable $e) {
                Log::error('SafeguardingTriggerService: failed to dispatch SafeguardingFlaggedEvent', [
                    'user_id' => $userId,
                    'error' => $e->getMessage(),
                ]);
            }
        }

        // Audit log trigger activation
        try {
            DB::table('activity_log')->insert([
                'tenant_id' => $tenantId,
                'user_id' => $userId,
                'action' => 'safeguarding_triggers_activated',
                'action_type' => 'safeguarding',
                'entity_type' => 'user',
                'entity_id' => $userId,
                'details' => json_encode([
                    'needs_monitoring' => $needsMonitoring,
                    'needs_broker_approval' => $needsBrokerApproval,
                    'triggers' => $triggers,
                ]),
                'ip_address' => request()?->ip(),
                'created_at' => now(),
            ]);
        } catch (\Throwable $e) {
            Log::error('Failed to log trigger activation', ['error' => $e->getMessage()]);
        }
    }

    // =========================================================================
    // Convenience check methods (used by other services)
    // =========================================================================

    public static function requiresVettedInteraction(int $userId, ?int $tenantId = null): bool
    {
        return (bool) (self::getActiveTriggers($userId, $tenantId)['requires_vetted_interaction'] ?? false);
    }

    public static function requiresBrokerApproval(int $userId, ?int $tenantId = null): bool
    {
        return (bool) (self::getActiveTriggers($userId, $tenantId)['requires_broker_approval'] ?? false);
    }

    public static function isMessagingRestricted(int $userId, ?int $tenantId = null): bool
    {
        return (bool) (self::getActiveTriggers($userId, $tenantId)['restricts_messaging'] ?? false);
    }

    public static function isMatchingRestricted(int $userId, ?int $tenantId = null): bool
    {
        return (bool) (self::getActiveTriggers($userId, $tenantId)['restricts_matching'] ?? false);
    }

    /**
     * Get vetting types required for interactions with this user.
     *
     * @return string[] e.g. ['garda_vetting'] or ['dbs_enhanced']
     */
    public static function getRequiredVettingTypes(int $userId, ?int $tenantId = null): array
    {
        return self::getActiveTriggers($userId, $tenantId)['vetting_types_required'] ?? [];
    }

    /**
     * Bulk variant of getRequiredVettingTypes — resolves required vetting types
     * for many users in a single query. Designed for SmartMatchingEngine's
     * post-filter loop where 200–400 candidates are checked per match request
     * and 200 per-user Cache::remember calls would add measurable latency.
     *
     * Returns a map keyed by user_id with a (possibly empty) array of vetting
     * types each user requires. Every input user_id is present in the output,
     * even users with no safeguarding preferences (empty array).
     *
     * Does NOT populate the per-user getActiveTriggers cache — that cache holds
     * the full merged trigger map (boolean flags + vetting types), and warming
     * it from this method would require fetching all trigger fields. Callers
     * that need the full trigger map should still call getActiveTriggers() per
     * user; the bulk method is specifically for discovery-time gating where
     * only the vetting-type list is needed.
     *
     * @param int[] $userIds
     * @return array<int, string[]> user_id => vetting_type[]
     */
    public static function getRequiredVettingTypesForUsers(array $userIds, ?int $tenantId = null): array
    {
        if (empty($userIds)) {
            return [];
        }

        $tenantId = $tenantId ?? TenantContext::getId();
        $uniqueIds = array_values(array_unique(array_map('intval', $userIds)));
        $uniqueIds = array_filter($uniqueIds, fn ($id) => $id > 0);

        $result = array_fill_keys($uniqueIds, []);

        if (empty($uniqueIds)) {
            return $result;
        }

        try {
            $rows = DB::table('user_safeguarding_preferences as p')
                ->join('tenant_safeguarding_options as o', function ($join) {
                    $join->on('o.id', '=', 'p.option_id')
                         ->where('o.is_active', 1);
                })
                ->where('p.tenant_id', $tenantId)
                ->whereIn('p.user_id', $uniqueIds)
                ->whereNull('p.revoked_at')
                ->select('p.user_id', 'o.triggers')
                ->get();

            foreach ($rows as $row) {
                $userId = (int) $row->user_id;
                $triggers = is_string($row->triggers)
                    ? (json_decode($row->triggers, true) ?: [])
                    : (array) ($row->triggers ?? []);

                $vettingType = $triggers['vetting_type_required'] ?? null;
                if (is_string($vettingType) && $vettingType !== ''
                    && !in_array($vettingType, $result[$userId], true)) {
                    $result[$userId][] = $vettingType;
                }
            }
        } catch (\Throwable $e) {
            Log::error('SafeguardingTriggerService::getRequiredVettingTypesForUsers failed', [
                'error' => $e->getMessage(),
                'tenant_id' => $tenantId,
                'user_count' => count($uniqueIds),
            ]);
        }

        return $result;
    }

    /**
     * Invalidate the trigger cache for a user (called when preferences change).
     */
    public static function invalidateCache(int $userId, ?int $tenantId = null): void
    {
        $tenantId = $tenantId ?? TenantContext::getId();
        Cache::forget(self::CACHE_PREFIX . "{$tenantId}:{$userId}");
    }
}

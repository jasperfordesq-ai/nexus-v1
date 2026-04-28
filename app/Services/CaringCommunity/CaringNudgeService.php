<?php
// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

declare(strict_types=1);

namespace App\Services\CaringCommunity;

use App\Models\Notification;
use App\Services\CaringTandemMatchingService;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class CaringNudgeService
{
    private const SETTING_PREFIX = 'caring_community.nudges.';
    private const DEFAULT_MIN_SCORE = 0.55;
    private const DEFAULT_COOLDOWN_DAYS = 14;
    private const DEFAULT_DAILY_LIMIT = 25;

    public function __construct(
        private readonly CaringTandemMatchingService $tandemMatchingService,
    ) {
    }

    /**
     * @return array{enabled:bool,min_score:float,cooldown_days:int,daily_limit:int}
     */
    public function config(int $tenantId): array
    {
        return [
            'enabled' => $this->settingBool($tenantId, 'enabled', false),
            'min_score' => $this->settingFloat($tenantId, 'min_score', self::DEFAULT_MIN_SCORE, 0.4, 0.95),
            'cooldown_days' => $this->settingInt($tenantId, 'cooldown_days', self::DEFAULT_COOLDOWN_DAYS, 1, 90),
            'daily_limit' => $this->settingInt($tenantId, 'daily_limit', self::DEFAULT_DAILY_LIMIT, 1, 250),
        ];
    }

    /**
     * @param array<string,mixed> $input
     * @return array{enabled:bool,min_score:float,cooldown_days:int,daily_limit:int}
     */
    public function updateConfig(int $tenantId, array $input): array
    {
        $current = $this->config($tenantId);
        $updates = [
            'enabled' => array_key_exists('enabled', $input) ? (bool) $input['enabled'] : $current['enabled'],
            'min_score' => array_key_exists('min_score', $input)
                ? max(0.4, min(0.95, (float) $input['min_score']))
                : $current['min_score'],
            'cooldown_days' => array_key_exists('cooldown_days', $input)
                ? max(1, min(90, (int) $input['cooldown_days']))
                : $current['cooldown_days'],
            'daily_limit' => array_key_exists('daily_limit', $input)
                ? max(1, min(250, (int) $input['daily_limit']))
                : $current['daily_limit'],
        ];

        foreach ($updates as $key => $value) {
            DB::table('tenant_settings')->updateOrInsert(
                ['tenant_id' => $tenantId, 'setting_key' => self::SETTING_PREFIX . $key],
                [
                    'setting_value' => is_bool($value) ? ($value ? '1' : '0') : (string) $value,
                    'setting_type' => is_bool($value) ? 'boolean' : (is_int($value) ? 'integer' : 'decimal'),
                    'category' => 'caring_community',
                    'updated_at' => now(),
                ],
            );
        }

        return $this->config($tenantId);
    }

    /**
     * @return list<array<string,mixed>>
     */
    public function previewCandidates(int $tenantId, ?int $limit = null): array
    {
        if (!Schema::hasTable('caring_smart_nudges')) {
            return [];
        }

        $config = $this->config($tenantId);
        $cooldownDays = $config['cooldown_days'];
        $cap = max(1, min(250, $limit ?? $config['daily_limit']));

        $suggestions = $this->tandemMatchingService->suggestTandems($tenantId, 100);
        $candidates = [];
        foreach ($suggestions as $suggestion) {
            $score = (float) ($suggestion['score'] ?? 0);
            if ($score < $config['min_score']) {
                continue;
            }

            $targetId = (int) ($suggestion['supporter']['id'] ?? 0);
            $relatedId = (int) ($suggestion['recipient']['id'] ?? 0);
            if ($targetId <= 0 || $relatedId <= 0 || $targetId === $relatedId) {
                continue;
            }
            if ($this->memberOptedOut($tenantId, $targetId) || $this->memberOptedOut($tenantId, $relatedId)) {
                continue;
            }
            if ($this->recentlyNudged($tenantId, $targetId, $relatedId, $cooldownDays)) {
                continue;
            }

            $candidates[] = [
                'target_user' => $suggestion['supporter'],
                'related_user' => $suggestion['recipient'],
                'score' => round($score, 3),
                'signals' => $suggestion['signals'] ?? [],
                'reason' => (string) ($suggestion['reason'] ?? ''),
            ];

            if (count($candidates) >= $cap) {
                break;
            }
        }

        return $candidates;
    }

    /**
     * @return array{enabled:bool,dry_run:bool,candidates:int,sent:int,skipped:int,items:list<array<string,mixed>>}
     */
    public function dispatchDue(int $tenantId, ?int $limit = null, bool $dryRun = false): array
    {
        $config = $this->config($tenantId);
        if (!$config['enabled']) {
            return [
                'enabled' => false,
                'dry_run' => $dryRun,
                'candidates' => 0,
                'sent' => 0,
                'skipped' => 0,
                'items' => [],
            ];
        }

        $candidates = $this->previewCandidates($tenantId, $limit);
        $sent = 0;
        $items = [];

        foreach ($candidates as $candidate) {
            if ($dryRun) {
                $items[] = $candidate + ['status' => 'preview'];
                continue;
            }

            $targetId = (int) $candidate['target_user']['id'];
            $relatedId = (int) $candidate['related_user']['id'];
            $notificationId = Notification::createNotification(
                $targetId,
                __('api.caring_nudge_notification', ['name' => (string) $candidate['related_user']['name']]),
                '/caring-community/request-help',
                'caring_smart_nudge',
                false,
                $tenantId,
            );

            $nudgeId = (int) DB::table('caring_smart_nudges')->insertGetId([
                'tenant_id' => $tenantId,
                'target_user_id' => $targetId,
                'related_user_id' => $relatedId,
                'source_type' => 'tandem_candidate',
                'score' => $candidate['score'],
                'signals' => json_encode($candidate['signals']),
                'notification_id' => $notificationId,
                'status' => 'sent',
                'sent_at' => now(),
                'created_at' => now(),
                'updated_at' => now(),
            ]);

            $items[] = $candidate + [
                'status' => 'sent',
                'nudge_id' => $nudgeId,
                'notification_id' => $notificationId,
            ];
            $sent++;
        }

        return [
            'enabled' => true,
            'dry_run' => $dryRun,
            'candidates' => count($candidates),
            'sent' => $sent,
            'skipped' => max(0, count($candidates) - $sent),
            'items' => $items,
        ];
    }

    /**
     * @return array<string,mixed>
     */
    public function analytics(int $tenantId): array
    {
        $this->markConversions($tenantId);

        $config = $this->config($tenantId);
        if (!Schema::hasTable('caring_smart_nudges')) {
            return [
                'config' => $config,
                'stats' => $this->emptyStats(),
                'recent' => [],
                'eligible_candidates' => 0,
            ];
        }

        $row = DB::selectOne(
            "SELECT
                COUNT(*) AS sent_total,
                COUNT(CASE WHEN sent_at >= DATE_SUB(NOW(), INTERVAL 30 DAY) THEN 1 END) AS sent_30d,
                COUNT(CASE WHEN status = 'converted' THEN 1 END) AS converted_total,
                COUNT(CASE WHEN status = 'converted' AND converted_at >= DATE_SUB(NOW(), INTERVAL 30 DAY) THEN 1 END) AS converted_30d
             FROM caring_smart_nudges
             WHERE tenant_id = ?",
            [$tenantId],
        );

        $sent30 = (int) ($row->sent_30d ?? 0);
        $converted30 = (int) ($row->converted_30d ?? 0);

        $recent = DB::table('caring_smart_nudges as n')
            ->leftJoin('users as target', function ($join) use ($tenantId): void {
                $join->on('target.id', '=', 'n.target_user_id')->where('target.tenant_id', '=', $tenantId);
            })
            ->leftJoin('users as related', function ($join) use ($tenantId): void {
                $join->on('related.id', '=', 'n.related_user_id')->where('related.tenant_id', '=', $tenantId);
            })
            ->where('n.tenant_id', $tenantId)
            ->orderByDesc('n.sent_at')
            ->limit(25)
            ->get([
                'n.id',
                'n.target_user_id',
                'n.related_user_id',
                'n.score',
                'n.status',
                'n.sent_at',
                'n.converted_at',
                'target.name as target_name',
                'related.name as related_name',
            ])
            ->map(fn (object $row): array => [
                'id' => (int) $row->id,
                'target_user' => [
                    'id' => (int) $row->target_user_id,
                    'name' => (string) ($row->target_name ?? ''),
                ],
                'related_user' => [
                    'id' => (int) $row->related_user_id,
                    'name' => (string) ($row->related_name ?? ''),
                ],
                'score' => round((float) $row->score, 3),
                'status' => (string) $row->status,
                'sent_at' => (string) $row->sent_at,
                'converted_at' => $row->converted_at ? (string) $row->converted_at : null,
            ])
            ->all();

        return [
            'config' => $config,
            'stats' => [
                'sent_total' => (int) ($row->sent_total ?? 0),
                'sent_30d' => $sent30,
                'converted_total' => (int) ($row->converted_total ?? 0),
                'converted_30d' => $converted30,
                'conversion_rate_30d' => $sent30 > 0 ? round($converted30 / $sent30, 3) : 0.0,
                'opted_out_members' => $this->optedOutCount($tenantId),
            ],
            'recent' => $recent,
            'eligible_candidates' => count($this->previewCandidates($tenantId)),
        ];
    }

    private function markConversions(int $tenantId): void
    {
        if (!Schema::hasTable('caring_smart_nudges') || !Schema::hasTable('caring_support_relationships')) {
            return;
        }

        $rows = DB::table('caring_smart_nudges')
            ->where('tenant_id', $tenantId)
            ->where('status', 'sent')
            ->whereNotNull('related_user_id')
            ->limit(500)
            ->get(['id', 'target_user_id', 'related_user_id']);

        foreach ($rows as $row) {
            $exists = DB::table('caring_support_relationships')
                ->where('tenant_id', $tenantId)
                ->where('status', 'active')
                ->where(function ($query) use ($row): void {
                    $query
                        ->where(function ($q) use ($row): void {
                            $q->where('supporter_id', (int) $row->target_user_id)
                                ->where('recipient_id', (int) $row->related_user_id);
                        })
                        ->orWhere(function ($q) use ($row): void {
                            $q->where('supporter_id', (int) $row->related_user_id)
                                ->where('recipient_id', (int) $row->target_user_id);
                        });
                })
                ->exists();

            if ($exists) {
                DB::table('caring_smart_nudges')
                    ->where('tenant_id', $tenantId)
                    ->where('id', (int) $row->id)
                    ->update([
                        'status' => 'converted',
                        'converted_at' => now(),
                        'updated_at' => now(),
                    ]);
            }
        }
    }

    private function recentlyNudged(int $tenantId, int $targetId, int $relatedId, int $cooldownDays): bool
    {
        return DB::table('caring_smart_nudges')
            ->where('tenant_id', $tenantId)
            ->where('target_user_id', $targetId)
            ->where('related_user_id', $relatedId)
            ->where('sent_at', '>=', now()->subDays($cooldownDays))
            ->exists();
    }

    private function memberOptedOut(int $tenantId, int $userId): bool
    {
        $raw = DB::table('users')
            ->where('tenant_id', $tenantId)
            ->where('id', $userId)
            ->value('notification_preferences');

        if (!$raw) {
            return false;
        }

        $prefs = is_string($raw) ? json_decode($raw, true) : $raw;
        if (is_string($prefs)) {
            $prefs = json_decode($prefs, true);
        }
        if (!is_array($prefs) || !array_key_exists('caring_smart_nudges', $prefs)) {
            return false;
        }

        return filter_var($prefs['caring_smart_nudges'], FILTER_VALIDATE_BOOLEAN) === false;
    }

    private function optedOutCount(int $tenantId): int
    {
        if (!Schema::hasColumn('users', 'notification_preferences')) {
            return 0;
        }

        $rows = DB::table('users')
            ->where('tenant_id', $tenantId)
            ->whereNotNull('notification_preferences')
            ->get(['notification_preferences']);

        $count = 0;
        foreach ($rows as $row) {
            $prefs = is_string($row->notification_preferences)
                ? json_decode($row->notification_preferences, true)
                : $row->notification_preferences;
            if (is_string($prefs)) {
                $prefs = json_decode($prefs, true);
            }
            if (
                is_array($prefs)
                && array_key_exists('caring_smart_nudges', $prefs)
                && filter_var($prefs['caring_smart_nudges'], FILTER_VALIDATE_BOOLEAN) === false
            ) {
                $count++;
            }
        }

        return $count;
    }

    /**
     * @return array{sent_total:int,sent_30d:int,converted_total:int,converted_30d:int,conversion_rate_30d:float,opted_out_members:int}
     */
    private function emptyStats(): array
    {
        return [
            'sent_total' => 0,
            'sent_30d' => 0,
            'converted_total' => 0,
            'converted_30d' => 0,
            'conversion_rate_30d' => 0.0,
            'opted_out_members' => 0,
        ];
    }

    private function settingBool(int $tenantId, string $key, bool $default): bool
    {
        $value = $this->setting($tenantId, $key);
        if ($value === null || $value === '') {
            return $default;
        }
        return filter_var($value, FILTER_VALIDATE_BOOLEAN);
    }

    private function settingFloat(int $tenantId, string $key, float $default, float $min, float $max): float
    {
        $value = $this->setting($tenantId, $key);
        if ($value === null || $value === '') {
            return $default;
        }
        return max($min, min($max, (float) $value));
    }

    private function settingInt(int $tenantId, string $key, int $default, int $min, int $max): int
    {
        $value = $this->setting($tenantId, $key);
        if ($value === null || $value === '') {
            return $default;
        }
        return max($min, min($max, (int) $value));
    }

    private function setting(int $tenantId, string $key): ?string
    {
        if (!Schema::hasTable('tenant_settings')) {
            return null;
        }

        $value = DB::table('tenant_settings')
            ->where('tenant_id', $tenantId)
            ->where('setting_key', self::SETTING_PREFIX . $key)
            ->value('setting_value');

        return $value === null ? null : (string) $value;
    }
}

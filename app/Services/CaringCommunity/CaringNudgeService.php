<?php
// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

declare(strict_types=1);

namespace App\Services\CaringCommunity;

use App\I18n\LocaleContext;
use App\Models\Notification;
use App\Services\CaringTandemMatchingService;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Adds three trigger families on top of the original tandem-suggestion engine:
 *  - helper_at_risk (helper inactive 21+ days but active in the prior 30)
 *  - unfulfilled_help_request (caring_help_requests pending > 7 days)
 *  - low_coverage_subregion (sub-region coverage_ratio < 0.5)
 *
 * Each trigger emits the same candidate shape as the existing tandem
 * candidates so the dispatcher does not need to branch by source.
 */

class CaringNudgeService
{
    private const SETTING_PREFIX = 'caring_community.nudges.';
    private const DEFAULT_MIN_SCORE = 0.55;
    private const DEFAULT_COOLDOWN_DAYS = 14;
    private const DEFAULT_DAILY_LIMIT = 25;

    /** Helper considered at risk if last log >= this many days ago. */
    private const HELPER_AT_RISK_DAYS = 21;
    /** … but only if they were active some time in the previous window. */
    private const HELPER_AT_RISK_PRIOR_WINDOW_DAYS = 60;

    /** Help request considered unfulfilled if pending for this many days. */
    private const UNFULFILLED_HELP_REQUEST_DAYS = 7;

    /** Synthetic score reported for non-tandem nudges. */
    private const SIGNAL_SCORE_HIGH = 0.85;
    private const SIGNAL_SCORE_MEDIUM = 0.7;

    /** @var array<int,bool> userId => optedOut (per-tenant ephemeral cache) */
    private array $optOutCache = [];

    /** @var array<string,bool> "tenant:source:target" => recently-nudged */
    private array $recentNudgeBySourceCache = [];

    /** @var array<string,bool> "tenant:target:related" => recently-nudged */
    private array $recentNudgePairCache = [];

    /** @var array<int,string> userId => name */
    private array $userNameCache = [];

    public function __construct(
        private readonly CaringTandemMatchingService $tandemMatchingService,
        private readonly ?CaringCommunityForecastService $forecastService = null,
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

        // ── Trigger: helper_at_risk ──────────────────────────────────────
        foreach ($this->helperAtRiskCandidates($tenantId, $cooldownDays) as $candidate) {
            $candidates[] = $candidate;
            if (count($candidates) >= $cap) {
                return $candidates;
            }
        }

        // ── Trigger: unfulfilled_help_request ───────────────────────────
        foreach ($this->unfulfilledHelpRequestCandidates($tenantId, $cooldownDays) as $candidate) {
            $candidates[] = $candidate;
            if (count($candidates) >= $cap) {
                return $candidates;
            }
        }

        // ── Trigger: low_coverage_subregion ─────────────────────────────
        foreach ($this->lowCoverageSubRegionCandidates($tenantId, $cooldownDays) as $candidate) {
            $candidates[] = $candidate;
            if (count($candidates) >= $cap) {
                return $candidates;
            }
        }

        // ── Existing trigger: tandem_candidate ──────────────────────────
        // Bulk-warm opt-out + recently-nudged caches across the whole
        // suggestion set so the inner loop avoids per-row N+1 queries.
        $tandemUserIds = [];
        $tandemPairs = [];
        foreach ($suggestions as $suggestion) {
            $t = (int) ($suggestion['supporter']['id'] ?? 0);
            $r = (int) ($suggestion['recipient']['id'] ?? 0);
            if ($t > 0) {
                $tandemUserIds[] = $t;
            }
            if ($r > 0) {
                $tandemUserIds[] = $r;
            }
            if ($t > 0 && $r > 0) {
                $tandemPairs[] = [$t, $r];
            }
        }
        $this->preloadOptOuts($tenantId, $tandemUserIds);
        $this->preloadRecentPairs($tenantId, $tandemPairs, $cooldownDays);

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
                'source_type' => 'tandem_candidate',
                'notification_url' => '/caring-community/request-help',
                // Render the message inside LocaleContext at dispatch time so
                // it resolves in the recipient's preferred_language.
                'notification_key' => 'api.caring_nudge_notification',
                'notification_params' => ['name' => (string) ($suggestion['recipient']['name'] ?? '')],
                'notification_message' => __('api.caring_nudge_notification', ['name' => (string) ($suggestion['recipient']['name'] ?? '')]),
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

        // Bulk-load preferred_language for all unique target users so we can
        // wrap each Notification::createNotification render in the recipient's
        // locale (avoids leaking the queue worker's default).
        $targetIds = array_values(array_unique(array_filter(
            array_map(fn ($c) => (int) ($c['target_user']['id'] ?? 0), $candidates),
            fn ($id) => $id > 0,
        )));
        $preferredLang = [];
        if (count($targetIds) > 0) {
            $rows = DB::table('users')
                ->where('tenant_id', $tenantId)
                ->whereIn('id', $targetIds)
                ->get(['id', 'preferred_language']);
            foreach ($rows as $r) {
                $preferredLang[(int) $r->id] = (string) ($r->preferred_language ?? '');
            }
        }

        foreach ($candidates as $candidate) {
            if ($dryRun) {
                $items[] = $candidate + ['status' => 'preview'];
                continue;
            }

            $targetId = (int) $candidate['target_user']['id'];
            $relatedId = (int) ($candidate['related_user']['id'] ?? 0);
            $sourceType = (string) ($candidate['source_type'] ?? 'tandem_candidate');
            $url = (string) ($candidate['notification_url'] ?? '/caring-community/request-help');

            $notificationKey = (string) ($candidate['notification_key']
                ?? 'api.caring_nudge_notification');
            $notificationParams = (array) ($candidate['notification_params']
                ?? ['name' => (string) ($candidate['related_user']['name'] ?? '')]);

            $lang = $preferredLang[$targetId] ?? '';

            // Wrap the bell render + insert in the recipient's locale so the
            // message is translated to their preferred_language regardless of
            // the queue worker's default locale.
            $notificationId = LocaleContext::withLocale(
                $lang !== '' ? $lang : null,
                fn () => Notification::createNotification(
                    $targetId,
                    (string) __($notificationKey, $notificationParams),
                    $url,
                    'caring_smart_nudge',
                    false,
                    $tenantId,
                ),
            );

            $nudgeId = (int) DB::table('caring_smart_nudges')->insertGetId([
                'tenant_id' => $tenantId,
                'target_user_id' => $targetId,
                'related_user_id' => $relatedId > 0 ? $relatedId : null,
                'source_type' => $sourceType,
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
            ->where('source_type', 'tandem_candidate')
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
        $key = $tenantId . ':' . $targetId . ':' . $relatedId;
        if (array_key_exists($key, $this->recentNudgePairCache)) {
            return $this->recentNudgePairCache[$key];
        }

        $query = DB::table('caring_smart_nudges')
            ->where('tenant_id', $tenantId)
            ->where('target_user_id', $targetId)
            ->where('sent_at', '>=', now()->subDays($cooldownDays));

        if ($relatedId > 0) {
            $query->where('related_user_id', $relatedId);
        } else {
            $query->whereNull('related_user_id');
        }

        $result = $query->exists();
        $this->recentNudgePairCache[$key] = $result;
        return $result;
    }

    /**
     * Has this target received a nudge of this source type recently?
     */
    private function recentlyNudgedBySource(int $tenantId, int $targetId, string $sourceType, int $cooldownDays): bool
    {
        $key = $tenantId . ':' . $sourceType . ':' . $targetId;
        if (array_key_exists($key, $this->recentNudgeBySourceCache)) {
            return $this->recentNudgeBySourceCache[$key];
        }

        $exists = DB::table('caring_smart_nudges')
            ->where('tenant_id', $tenantId)
            ->where('target_user_id', $targetId)
            ->where('source_type', $sourceType)
            ->where('sent_at', '>=', now()->subDays($cooldownDays))
            ->exists();

        $this->recentNudgeBySourceCache[$key] = $exists;
        return $exists;
    }

    // ─────────────────────────────────────────────────────────────────────────
    // New trigger: helper at risk of churn
    // ─────────────────────────────────────────────────────────────────────────

    /**
     * @return list<array<string,mixed>>
     */
    private function helperAtRiskCandidates(int $tenantId, int $cooldownDays): array
    {
        if (!Schema::hasTable('vol_logs')) {
            return [];
        }

        $lapsedSince = now()->subDays(self::HELPER_AT_RISK_DAYS)->toDateString();
        $priorWindowStart = now()
            ->subDays(self::HELPER_AT_RISK_DAYS + self::HELPER_AT_RISK_PRIOR_WINDOW_DAYS)
            ->toDateString();

        // Helpers active in the prior window (60 days before the lapse cutoff).
        $priorActive = DB::table('vol_logs')
            ->where('tenant_id', $tenantId)
            ->where('status', 'approved')
            ->whereBetween('date_logged', [$priorWindowStart, $lapsedSince])
            ->distinct()
            ->pluck('user_id')
            ->map(fn ($v) => (int) $v)
            ->all();

        if (count($priorActive) === 0) {
            return [];
        }

        // Of those, who has NOT logged anything since the lapse cutoff?
        $stillActive = DB::table('vol_logs')
            ->where('tenant_id', $tenantId)
            ->where('status', 'approved')
            ->where('date_logged', '>', $lapsedSince)
            ->whereIn('user_id', $priorActive)
            ->distinct()
            ->pluck('user_id')
            ->map(fn ($v) => (int) $v)
            ->all();

        $atRisk = array_values(array_diff($priorActive, $stillActive));
        if (count($atRisk) === 0) {
            return [];
        }

        $users = DB::table('users')
            ->where('tenant_id', $tenantId)
            ->whereIn('id', $atRisk)
            ->get(['id', 'name'])
            ->keyBy('id');

        // Bulk-load opt-out status and recently-nudged-by-source map in one
        // query each — replaces per-row N+1 lookups inside the loop below.
        $atRiskInts = array_map(fn ($v) => (int) $v, $atRisk);
        $this->preloadOptOuts($tenantId, $atRiskInts);
        $this->preloadRecentBySource($tenantId, 'helper_at_risk', $atRiskInts, $cooldownDays);

        $out = [];
        foreach ($atRisk as $userId) {
            $user = $users->get($userId);
            if ($user === null) {
                continue;
            }
            if ($this->memberOptedOut($tenantId, $userId)) {
                continue;
            }
            if ($this->recentlyNudgedBySource($tenantId, $userId, 'helper_at_risk', $cooldownDays)) {
                continue;
            }

            $out[] = [
                'target_user' => ['id' => $userId, 'name' => (string) $user->name],
                'related_user' => ['id' => 0, 'name' => ''],
                'score' => self::SIGNAL_SCORE_MEDIUM,
                'signals' => [
                    'lapsed_days_threshold' => self::HELPER_AT_RISK_DAYS,
                    'prior_window_days' => self::HELPER_AT_RISK_PRIOR_WINDOW_DAYS,
                ],
                'reason' => 'helper_at_risk',
                'source_type' => 'helper_at_risk',
                'notification_url' => '/caring-community',
                'notification_key' => 'caring_community.nudges.helper_at_risk.message',
                'notification_params' => [],
                'notification_message' => __('caring_community.nudges.helper_at_risk.message'),
            ];
        }

        return $out;
    }

    // ─────────────────────────────────────────────────────────────────────────
    // New trigger: unfulfilled help request
    // ─────────────────────────────────────────────────────────────────────────

    /**
     * @return list<array<string,mixed>>
     */
    private function unfulfilledHelpRequestCandidates(int $tenantId, int $cooldownDays): array
    {
        if (!Schema::hasTable('caring_help_requests')) {
            return [];
        }

        $cutoff = now()->subDays(self::UNFULFILLED_HELP_REQUEST_DAYS)->toDateTimeString();

        $rows = DB::table('caring_help_requests as hr')
            ->leftJoin('users as u', function ($join) use ($tenantId): void {
                $join->on('u.id', '=', 'hr.user_id')->where('u.tenant_id', '=', $tenantId);
            })
            ->where('hr.tenant_id', $tenantId)
            ->where('hr.status', 'pending')
            ->where('hr.created_at', '<=', $cutoff)
            ->orderBy('hr.created_at')
            ->limit(50)
            ->get(['hr.id', 'hr.user_id', 'hr.what', 'hr.created_at', 'u.name as requester_name']);

        if ($rows->isEmpty()) {
            return [];
        }

        $coordinatorIds = $this->coordinatorTargets($tenantId);
        if (count($coordinatorIds) === 0) {
            return [];
        }

        // Bulk-load coordinator opt-outs, names, and recently-nudged pairs.
        $this->preloadOptOuts($tenantId, $coordinatorIds);
        $this->preloadUserNames($tenantId, $coordinatorIds);
        $pairs = [];
        foreach ($rows as $row) {
            foreach ($coordinatorIds as $coordId) {
                $pairs[] = [(int) $coordId, (int) $row->user_id];
            }
        }
        $this->preloadRecentPairs($tenantId, $pairs, $cooldownDays);

        $out = [];
        foreach ($rows as $row) {
            $requesterId = (int) $row->user_id;
            $requesterName = (string) ($row->requester_name ?? '');

            // Pick the first coordinator who is not opted out and not on cooldown.
            foreach ($coordinatorIds as $coordId) {
                if ($this->memberOptedOut($tenantId, $coordId)) {
                    continue;
                }
                if ($this->recentlyNudged($tenantId, $coordId, $requesterId, $cooldownDays)) {
                    continue;
                }

                $out[] = [
                    'target_user' => [
                        'id' => $coordId,
                        'name' => $this->userName($tenantId, $coordId),
                    ],
                    'related_user' => ['id' => $requesterId, 'name' => $requesterName],
                    'score' => self::SIGNAL_SCORE_HIGH,
                    'signals' => [
                        'help_request_id' => (int) $row->id,
                        'pending_since' => (string) $row->created_at,
                        'pending_days_threshold' => self::UNFULFILLED_HELP_REQUEST_DAYS,
                    ],
                    'reason' => 'unfulfilled_help_request',
                    'source_type' => 'unfulfilled_help_request',
                    'notification_url' => '/admin/caring-community/workflow',
                    'notification_key' => 'caring_community.nudges.unfulfilled_help_request.message',
                    'notification_params' => [
                        'name' => $requesterName,
                        'days' => self::UNFULFILLED_HELP_REQUEST_DAYS,
                    ],
                    'notification_message' => __('caring_community.nudges.unfulfilled_help_request.message', [
                        'name' => $requesterName,
                        'days' => self::UNFULFILLED_HELP_REQUEST_DAYS,
                    ]),
                ];
                break;
            }
        }

        return $out;
    }

    // ─────────────────────────────────────────────────────────────────────────
    // New trigger: low-coverage sub-region
    // ─────────────────────────────────────────────────────────────────────────

    /**
     * @return list<array<string,mixed>>
     */
    private function lowCoverageSubRegionCandidates(int $tenantId, int $cooldownDays): array
    {
        if ($this->forecastService === null) {
            return [];
        }

        $demand = $this->forecastService->subRegionDemand();
        $flagged = array_values(array_filter(
            $demand['sub_regions'] ?? [],
            static fn ($r) => !empty($r['flagged']),
        ));

        if (count($flagged) === 0) {
            return [];
        }

        $admins = $this->coordinatorTargets($tenantId);
        if (count($admins) === 0) {
            return [];
        }

        // Bulk-load admin opt-outs, names, and recently-nudged-by-source.
        $this->preloadOptOuts($tenantId, $admins);
        $this->preloadUserNames($tenantId, $admins);
        $this->preloadRecentBySource($tenantId, 'low_coverage_subregion', $admins, $cooldownDays);

        $out = [];
        foreach ($flagged as $region) {
            foreach ($admins as $adminId) {
                if ($this->memberOptedOut($tenantId, $adminId)) {
                    continue;
                }
                if ($this->recentlyNudgedBySource($tenantId, $adminId, 'low_coverage_subregion', $cooldownDays)) {
                    continue;
                }

                $out[] = [
                    'target_user' => [
                        'id' => $adminId,
                        'name' => $this->userName($tenantId, $adminId),
                    ],
                    'related_user' => ['id' => 0, 'name' => (string) $region['name']],
                    'score' => self::SIGNAL_SCORE_MEDIUM,
                    'signals' => [
                        'sub_region_id' => (int) $region['id'],
                        'sub_region_name' => (string) $region['name'],
                        'coverage_ratio_90d' => (float) $region['coverage_ratio_90d'],
                        'requested_90d' => (float) $region['requested_90d'],
                        'fulfilled_90d' => (float) $region['fulfilled_90d'],
                    ],
                    'reason' => 'low_coverage_subregion',
                    'source_type' => 'low_coverage_subregion',
                    'notification_url' => '/admin/caring-community/sub-regions',
                    'notification_key' => 'caring_community.nudges.low_coverage_subregion.message',
                    'notification_params' => ['name' => (string) $region['name']],
                    'notification_message' => __('caring_community.nudges.low_coverage_subregion.message', [
                        'name' => (string) $region['name'],
                    ]),
                ];
                break;
            }
        }

        return $out;
    }

    /**
     * Resolve a list of coordinator/admin user IDs for the tenant.
     * Used as fan-out targets for non-tandem nudges.
     *
     * @return list<int>
     */
    private function coordinatorTargets(int $tenantId): array
    {
        if (!Schema::hasTable('users')) {
            return [];
        }

        $hasRole = Schema::hasColumn('users', 'role');
        $query = DB::table('users')->where('tenant_id', $tenantId);
        if ($hasRole) {
            $query->whereIn('role', ['admin', 'super_admin', 'coordinator']);
        }
        if (Schema::hasColumn('users', 'status')) {
            $query->where('status', 'active');
        }

        return $query
            ->limit(10)
            ->pluck('id')
            ->map(fn ($v) => (int) $v)
            ->all();
    }

    private function memberOptedOut(int $tenantId, int $userId): bool
    {
        if (array_key_exists($userId, $this->optOutCache)) {
            return $this->optOutCache[$userId];
        }

        $raw = DB::table('users')
            ->where('tenant_id', $tenantId)
            ->where('id', $userId)
            ->value('notification_preferences');

        $result = $this->parseOptedOut($raw);
        $this->optOutCache[$userId] = $result;
        return $result;
    }

    /**
     * Parse a notification_preferences JSON blob and return whether the user
     * has explicitly opted out of caring_smart_nudges.
     */
    private function parseOptedOut(mixed $raw): bool
    {
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

    /**
     * Bulk-load notification_preferences for a set of users in a single query
     * and warm $optOutCache. Avoids N+1 from per-row memberOptedOut() calls.
     *
     * @param  list<int> $userIds
     */
    private function preloadOptOuts(int $tenantId, array $userIds): void
    {
        $userIds = array_values(array_unique(array_filter($userIds, fn ($v) => (int) $v > 0)));
        if (count($userIds) === 0) {
            return;
        }

        $missing = array_values(array_filter(
            $userIds,
            fn ($id) => !array_key_exists((int) $id, $this->optOutCache),
        ));
        if (count($missing) === 0) {
            return;
        }

        $rows = DB::table('users')
            ->where('tenant_id', $tenantId)
            ->whereIn('id', $missing)
            ->get(['id', 'notification_preferences']);

        $seen = [];
        foreach ($rows as $row) {
            $id = (int) $row->id;
            $this->optOutCache[$id] = $this->parseOptedOut($row->notification_preferences);
            $seen[$id] = true;
        }
        // Users with no row default to NOT opted out.
        foreach ($missing as $id) {
            if (!isset($seen[(int) $id])) {
                $this->optOutCache[(int) $id] = false;
            }
        }
    }

    /**
     * Bulk-load the set of (target_user_id) pairs that have already received
     * a nudge of $sourceType within $cooldownDays. Warms $recentNudgeBySourceCache.
     *
     * @param  list<int> $userIds
     */
    private function preloadRecentBySource(int $tenantId, string $sourceType, array $userIds, int $cooldownDays): void
    {
        $userIds = array_values(array_unique(array_filter($userIds, fn ($v) => (int) $v > 0)));
        if (count($userIds) === 0) {
            return;
        }

        $hits = DB::table('caring_smart_nudges')
            ->where('tenant_id', $tenantId)
            ->where('source_type', $sourceType)
            ->where('sent_at', '>=', now()->subDays($cooldownDays))
            ->whereIn('target_user_id', $userIds)
            ->distinct()
            ->pluck('target_user_id')
            ->map(fn ($v) => (int) $v)
            ->all();

        $hitSet = array_flip($hits);
        foreach ($userIds as $id) {
            $key = $tenantId . ':' . $sourceType . ':' . (int) $id;
            $this->recentNudgeBySourceCache[$key] = isset($hitSet[(int) $id]);
        }
    }

    /**
     * Bulk-load the set of (target_user_id, related_user_id) pairs that have
     * already been nudged within $cooldownDays. Warms $recentNudgePairCache.
     *
     * @param  list<array{0:int,1:int}> $pairs
     */
    private function preloadRecentPairs(int $tenantId, array $pairs, int $cooldownDays): void
    {
        if (count($pairs) === 0) {
            return;
        }

        $targetIds = array_values(array_unique(array_map(fn ($p) => (int) $p[0], $pairs)));
        if (count($targetIds) === 0) {
            return;
        }

        $rows = DB::table('caring_smart_nudges')
            ->where('tenant_id', $tenantId)
            ->where('sent_at', '>=', now()->subDays($cooldownDays))
            ->whereIn('target_user_id', $targetIds)
            ->get(['target_user_id', 'related_user_id']);

        $hitSet = [];
        foreach ($rows as $r) {
            $t = (int) $r->target_user_id;
            $rel = $r->related_user_id !== null ? (int) $r->related_user_id : 0;
            $hitSet[$t . ':' . $rel] = true;
        }

        foreach ($pairs as [$t, $rel]) {
            $key = $tenantId . ':' . (int) $t . ':' . (int) $rel;
            $this->recentNudgePairCache[$key] = isset($hitSet[(int) $t . ':' . (int) $rel]);
        }
    }

    /**
     * Bulk-load names for a set of user IDs and warm $userNameCache.
     *
     * @param  list<int> $userIds
     */
    private function preloadUserNames(int $tenantId, array $userIds): void
    {
        $userIds = array_values(array_unique(array_filter(
            $userIds,
            fn ($v) => (int) $v > 0 && !isset($this->userNameCache[(int) $v]),
        )));
        if (count($userIds) === 0) {
            return;
        }

        $rows = DB::table('users')
            ->where('tenant_id', $tenantId)
            ->whereIn('id', $userIds)
            ->get(['id', 'name']);

        foreach ($rows as $r) {
            $this->userNameCache[(int) $r->id] = (string) ($r->name ?? '');
        }
        // Fill in any missing IDs as empty string.
        foreach ($userIds as $id) {
            if (!isset($this->userNameCache[(int) $id])) {
                $this->userNameCache[(int) $id] = '';
            }
        }
    }

    private function userName(int $tenantId, int $userId): string
    {
        if ($userId <= 0) {
            return '';
        }
        if (!isset($this->userNameCache[$userId])) {
            $this->preloadUserNames($tenantId, [$userId]);
        }
        return $this->userNameCache[$userId] ?? '';
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

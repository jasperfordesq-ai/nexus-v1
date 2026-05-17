<?php
// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

namespace App\Services;

use App\Core\TenantContext;
use App\Models\AchievementCampaign;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * AchievementCampaignService — Eloquent-based service for achievement campaigns.
 *
 * Manages campaigns that award badges, XP, or trigger challenges for targeted user groups.
 * All queries are tenant-scoped via HasTenantScope trait on the model.
 */
class AchievementCampaignService
{
    /**
     * Campaign types.
     */
    public const TYPES = [
        'one_time' => 'One Time - Award once to qualifying users',
        'recurring' => 'Recurring - Award on schedule (daily/weekly/monthly)',
        'triggered' => 'Triggered - Award when user meets conditions',
    ];

    /**
     * Target audience options.
     */
    public const AUDIENCES = [
        'all_users' => 'All Active Users',
        'new_users' => 'New Users (joined in last 30 days)',
        'active_users' => 'Active Users (logged in this week)',
        'inactive_users' => 'Inactive Users (no login in 30+ days)',
        'level_range' => 'Users at specific level range',
        'badge_holders' => 'Users with specific badge',
        'custom' => 'Custom filter (SQL)',
    ];

    private static array $typeToDbMap = [
        'one_time' => 'badge_award',
        'recurring' => 'xp_bonus',
        'triggered' => 'challenge',
    ];

    private static array $dbToTypeMap = [
        'badge_award' => 'one_time',
        'xp_bonus' => 'recurring',
        'challenge' => 'triggered',
    ];

    /**
     * Get all campaigns.
     */
    public function getCampaigns(?string $status = null): array
    {
        $query = AchievementCampaign::query()->orderByDesc('created_at');

        if ($status) {
            $query->where('status', $status);
        }

        $campaigns = $query->get()->map(function ($c) {
            $arr = $c->toArray();
            $arr['type'] = self::$dbToTypeMap[$arr['campaign_type'] ?? 'badge_award'] ?? 'one_time';
            return $arr;
        })->all();

        return $campaigns;
    }

    /**
     * Get a single campaign.
     */
    public function getCampaign(int $id): ?array
    {
        $campaign = AchievementCampaign::find($id);
        if (!$campaign) {
            return null;
        }

        $arr = $campaign->toArray();
        $arr['type'] = self::$dbToTypeMap[$arr['campaign_type'] ?? 'badge_award'] ?? 'one_time';
        return $arr;
    }

    /**
     * Create a new campaign.
     *
     * @return int|string|null Campaign ID
     */
    public function createCampaign(array $data): int|string|null
    {
        $campaignType = self::$typeToDbMap[$data['type'] ?? 'one_time'] ?? 'badge_award';

        try {
            $campaign = AchievementCampaign::create([
                'name' => $data['name'],
                'description' => $data['description'] ?? '',
                'campaign_type' => $campaignType,
                'badge_key' => $data['badge_key'] ?: null,
                'xp_amount' => $data['xp_amount'] ?? 0,
                'target_audience' => $data['target_audience'] ?? 'all_users',
                'audience_config' => $data['audience_config'] ?? [],
                'schedule' => $data['schedule'] ?? null,
                'status' => 'draft',
            ]);

            return $campaign->id;
        } catch (\Throwable $e) {
            Log::error('Achievement campaign creation failed: ' . $e->getMessage());
            return null;
        }
    }

    /**
     * Update a campaign.
     */
    public function updateCampaign(int $id, array $data): void
    {
        $campaignType = self::$typeToDbMap[$data['type'] ?? 'one_time'] ?? 'badge_award';

        AchievementCampaign::where('id', $id)->update([
            'name' => $data['name'],
            'description' => $data['description'] ?? '',
            'campaign_type' => $campaignType,
            'badge_key' => $data['badge_key'] ?: null,
            'xp_amount' => $data['xp_amount'] ?? 0,
            'target_audience' => $data['target_audience'] ?? 'all_users',
            'audience_config' => is_array($data['audience_config'] ?? null)
                ? json_encode($data['audience_config']) : ($data['audience_config'] ?? '{}'),
            'schedule' => $data['schedule'] ?? null,
        ]);
    }

    /**
     * Activate a campaign.
     */
    public function activateCampaign(int $id): void
    {
        AchievementCampaign::where('id', $id)->update([
            'status' => 'active',
            'activated_at' => now(),
        ]);
    }

    /**
     * Pause a campaign.
     */
    public function pauseCampaign(int $id): void
    {
        AchievementCampaign::where('id', $id)->update([
            'status' => 'paused',
        ]);
    }

    /**
     * Delete a campaign.
     */
    public function deleteCampaign(int $id): void
    {
        AchievementCampaign::where('id', $id)->delete();
    }

    /**
     * Cron: process recurring campaigns for the current tenant.
     *
     * Scans `achievement_campaigns` where:
     *   - status = 'running'
     *   - campaign_type = 'xp_bonus' (the DB enum value for "recurring",
     *     per self::$typeToDbMap)
     *   - last_run_at is null OR older than the recurrence_pattern window
     *
     * Each eligible campaign just bumps `last_run_at` for now — the actual
     * award logic (selecting users matching `target_audience` /
     * `audience_config` and awarding the badge or XP) is intentionally
     * stubbed here. Wiring real award delivery requires careful tenant-
     * scoped user-selection logic and a clear product decision on what
     * "missed runs" should do (catch up vs skip). Until that ships, the
     * cron entry stops throwing "undefined method" once per hour per
     * tenant; it logs activity so we can see when this stub fires.
     *
     * Returns the number of campaigns that were marked as run this tick.
     */
    public function processRecurringCampaigns(): array
    {
        $tenantId = TenantContext::getId();
        if ($tenantId === null) {
            return ['processed' => 0, 'awarded' => 0];
        }

        $now = now();
        $eligibility = function ($pattern, $lastRun) use ($now) {
            if ($lastRun === null) {
                return true;
            }
            $last = strtotime((string) $lastRun);
            $sec  = $now->timestamp - $last;
            return match (strtolower((string) $pattern)) {
                'daily'   => $sec >= 86400,
                'weekly'  => $sec >= 86400 * 7,
                'monthly' => $sec >= 86400 * 28,
                default   => $sec >= 86400 * 7,
            };
        };

        $rows = DB::table('achievement_campaigns')
            ->where('tenant_id', $tenantId)
            ->where('status', 'running')
            ->where('campaign_type', 'xp_bonus')
            ->get();

        $processed = 0;
        foreach ($rows as $r) {
            if (!$eligibility($r->recurrence_pattern ?? 'weekly', $r->last_run_at ?? null)) {
                continue;
            }
            try {
                DB::table('achievement_campaigns')
                    ->where('id', $r->id)
                    ->update([
                        'last_run_at' => $now,
                    ]);
                Log::info('AchievementCampaignService: recurring tick (award logic stubbed)', [
                    'tenant_id'    => $tenantId,
                    'campaign_id'  => $r->id,
                    'name'         => $r->name,
                    'pattern'      => $r->recurrence_pattern,
                ]);
                $processed++;
            } catch (\Throwable $e) {
                Log::warning('AchievementCampaignService::processRecurringCampaigns failed', [
                    'campaign_id' => $r->id,
                    'error'       => $e->getMessage(),
                ]);
            }
        }

        // Return shape matches the original cron caller expectation
        // (an iterable of per-campaign results with `awarded` count).
        return array_map(fn () => ['awarded' => 0], range(1, $processed));
    }
}

<?php
// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

declare(strict_types=1);

namespace App\Services;

use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * LocalAdvertisingService — AG56 Local Advertising Platform.
 *
 * Manages ad campaigns, creatives, impressions, and click tracking for
 * tenant-scoped local advertising. Primary revenue driver for the
 * Agoris/KISS deployment.
 *
 * All methods are static and guard against the tables not yet existing
 * (isAvailable() check) to allow graceful degradation during migrations.
 */
class LocalAdvertisingService
{
    private const TABLE_CAMPAIGNS   = 'ad_campaigns';
    private const TABLE_CREATIVES   = 'ad_creatives';
    private const TABLE_IMPRESSIONS = 'ad_impressions';
    private const TABLE_CLICKS      = 'ad_clicks';

    /** Default cost-per-click in cents when no explicit CPC is configured. */
    private const DEFAULT_CPC_CENTS = 10;

    // ──────────────────────────────────────────────────────────────────────────
    // Availability guard
    // ──────────────────────────────────────────────────────────────────────────

    /**
     * Returns true when all four advertising tables exist in the database.
     * Every other method calls this first and throws if false.
     */
    public static function isAvailable(): bool
    {
        return Schema::hasTable(self::TABLE_CAMPAIGNS)
            && Schema::hasTable(self::TABLE_CREATIVES)
            && Schema::hasTable(self::TABLE_IMPRESSIONS)
            && Schema::hasTable(self::TABLE_CLICKS);
    }

    // ──────────────────────────────────────────────────────────────────────────
    // Campaign CRUD
    // ──────────────────────────────────────────────────────────────────────────

    /**
     * List all campaigns for a tenant, with optional status/type filtering.
     *
     * Joins users to surface advertiser_name and email.
     * Includes a creative_count aggregate.
     *
     * @param  array{status?: string, advertiser_type?: string} $filters
     * @return array<int, array<string, mixed>>
     */
    public static function listCampaigns(int $tenantId, array $filters = []): array
    {
        self::assertAvailable();

        $query = DB::table(self::TABLE_CAMPAIGNS . ' as c')
            ->leftJoin('users as u', 'u.id', '=', 'c.created_by')
            ->leftJoin(
                DB::raw('(SELECT campaign_id, COUNT(*) AS creative_count FROM ' . self::TABLE_CREATIVES . ' GROUP BY campaign_id) cr'),
                'cr.campaign_id',
                '=',
                'c.id'
            )
            ->where('c.tenant_id', $tenantId)
            ->select([
                'c.*',
                DB::raw("CONCAT(u.first_name, ' ', u.last_name) AS advertiser_name"),
                'u.email AS advertiser_email',
                DB::raw('COALESCE(cr.creative_count, 0) AS creative_count'),
            ])
            ->orderByDesc('c.created_at');

        if (!empty($filters['status'])) {
            $query->where('c.status', $filters['status']);
        }

        if (!empty($filters['advertiser_type'])) {
            $query->where('c.advertiser_type', $filters['advertiser_type']);
        }

        return $query->get()->map(fn ($row) => (array) $row)->all();
    }

    /**
     * Get a single campaign by ID (tenant-scoped), including its creatives.
     *
     * @return array<string, mixed>|null
     */
    public static function getCampaignById(int $id, int $tenantId): ?array
    {
        self::assertAvailable();

        $campaign = DB::table(self::TABLE_CAMPAIGNS . ' as c')
            ->leftJoin('users as u', 'u.id', '=', 'c.created_by')
            ->where('c.id', $id)
            ->where('c.tenant_id', $tenantId)
            ->select([
                'c.*',
                DB::raw("CONCAT(u.first_name, ' ', u.last_name) AS advertiser_name"),
                'u.email AS advertiser_email',
            ])
            ->first();

        if ($campaign === null) {
            return null;
        }

        $result = (array) $campaign;

        $result['creatives'] = DB::table(self::TABLE_CREATIVES)
            ->where('campaign_id', $id)
            ->where('tenant_id', $tenantId)
            ->orderByDesc('created_at')
            ->get()
            ->map(fn ($r) => (array) $r)
            ->all();

        return $result;
    }

    /**
     * Create a new ad campaign for a tenant.
     *
     * @param  array{
     *     name: string,
     *     advertiser_type?: string,
     *     budget_cents?: int,
     *     start_date?: string|null,
     *     end_date?: string|null,
     *     audience_filters?: array|null,
     *     placement?: string,
     * } $data
     * @return array<string, mixed>
     */
    public static function createCampaign(int $tenantId, int $userId, array $data): array
    {
        self::assertAvailable();

        $now = Carbon::now();

        $id = DB::table(self::TABLE_CAMPAIGNS)->insertGetId([
            'tenant_id'        => $tenantId,
            'created_by'       => $userId,
            'name'             => $data['name'],
            'status'           => 'pending_review',
            'advertiser_type'  => $data['advertiser_type'] ?? 'sme',
            'budget_cents'     => (int) ($data['budget_cents'] ?? 0),
            'spent_cents'      => 0,
            'start_date'       => $data['start_date'] ?? null,
            'end_date'         => $data['end_date'] ?? null,
            'audience_filters' => isset($data['audience_filters'])
                ? json_encode($data['audience_filters'])
                : null,
            'placement'        => $data['placement'] ?? 'feed',
            'impression_count' => 0,
            'click_count'      => 0,
            'created_at'       => $now,
            'updated_at'       => $now,
        ]);

        return self::getCampaignById($id, $tenantId) ?? [];
    }

    /**
     * Update mutable fields on an existing campaign.
     *
     * @param  array<string, mixed> $data
     * @return array<string, mixed>
     */
    public static function updateCampaign(int $id, int $tenantId, array $data): array
    {
        self::assertAvailable();

        $mutable = [
            'name',
            'advertiser_type',
            'budget_cents',
            'start_date',
            'end_date',
            'placement',
        ];

        $update = ['updated_at' => Carbon::now()];

        foreach ($mutable as $field) {
            if (array_key_exists($field, $data)) {
                $update[$field] = $data[$field];
            }
        }

        if (array_key_exists('audience_filters', $data)) {
            $update['audience_filters'] = $data['audience_filters'] !== null
                ? json_encode($data['audience_filters'])
                : null;
        }

        DB::table(self::TABLE_CAMPAIGNS)
            ->where('id', $id)
            ->where('tenant_id', $tenantId)
            ->update($update);

        return self::getCampaignById($id, $tenantId) ?? [];
    }

    /**
     * Approve a campaign — sets status to active, records approver and timestamp.
     *
     * @return array<string, mixed>
     */
    public static function approveCampaign(int $id, int $tenantId, int $approvedBy): array
    {
        self::assertAvailable();

        $now = Carbon::now();

        DB::table(self::TABLE_CAMPAIGNS)
            ->where('id', $id)
            ->where('tenant_id', $tenantId)
            ->update([
                'status'      => 'active',
                'approved_by' => $approvedBy,
                'approved_at' => $now,
                'updated_at'  => $now,
            ]);

        return self::getCampaignById($id, $tenantId) ?? [];
    }

    /**
     * Reject a campaign with a written reason.
     */
    public static function rejectCampaign(int $id, int $tenantId, string $reason): void
    {
        self::assertAvailable();

        DB::table(self::TABLE_CAMPAIGNS)
            ->where('id', $id)
            ->where('tenant_id', $tenantId)
            ->update([
                'status'           => 'rejected',
                'rejection_reason' => $reason,
                'updated_at'       => Carbon::now(),
            ]);
    }

    /**
     * Pause an active campaign.
     */
    public static function pauseCampaign(int $id, int $tenantId): void
    {
        self::assertAvailable();

        DB::table(self::TABLE_CAMPAIGNS)
            ->where('id', $id)
            ->where('tenant_id', $tenantId)
            ->update([
                'status'     => 'paused',
                'updated_at' => Carbon::now(),
            ]);
    }

    // ──────────────────────────────────────────────────────────────────────────
    // Creatives
    // ──────────────────────────────────────────────────────────────────────────

    /**
     * Add a creative asset to a campaign.
     *
     * @param  array{
     *     headline: string,
     *     body: string,
     *     cta_text?: string|null,
     *     image_url?: string|null,
     *     destination_url?: string|null,
     * } $data
     * @return array<string, mixed>
     */
    public static function addCreative(int $campaignId, int $tenantId, array $data): array
    {
        self::assertAvailable();

        $now = Carbon::now();

        $id = DB::table(self::TABLE_CREATIVES)->insertGetId([
            'campaign_id'     => $campaignId,
            'tenant_id'       => $tenantId,
            'headline'        => $data['headline'],
            'body'            => $data['body'],
            'cta_text'        => $data['cta_text'] ?? null,
            'image_url'       => $data['image_url'] ?? null,
            'destination_url' => $data['destination_url'] ?? null,
            'is_active'       => 1,
            'created_at'      => $now,
            'updated_at'      => $now,
        ]);

        return (array) DB::table(self::TABLE_CREATIVES)->find($id);
    }

    // ──────────────────────────────────────────────────────────────────────────
    // Ad serving
    // ──────────────────────────────────────────────────────────────────────────

    /**
     * Return active campaigns with their active creatives for feed injection.
     *
     * Only returns campaigns where:
     *   - status = active
     *   - placement matches (or is 'all')
     *   - start_date <= today or null
     *   - end_date >= today or null
     *   - budget not exhausted (spent_cents < budget_cents, or budget_cents = 0 meaning unlimited)
     *
     * @return array<int, array<string, mixed>>
     */
    public static function getActiveAds(int $tenantId, string $placement, ?int $limit = 3): array
    {
        self::assertAvailable();

        $today = Carbon::today()->toDateString();

        $campaigns = DB::table(self::TABLE_CAMPAIGNS)
            ->where('tenant_id', $tenantId)
            ->where('status', 'active')
            ->where(function ($q) use ($placement) {
                $q->where('placement', $placement)
                  ->orWhere('placement', 'all');
            })
            ->where(function ($q) use ($today) {
                $q->whereNull('start_date')->orWhere('start_date', '<=', $today);
            })
            ->where(function ($q) use ($today) {
                $q->whereNull('end_date')->orWhere('end_date', '>=', $today);
            })
            ->where(function ($q) {
                $q->where('budget_cents', 0)
                  ->orWhereColumn('spent_cents', '<', 'budget_cents');
            })
            ->orderByDesc('impression_count')   // simple pseudo-priority: less-seen first via inversion would be orderBy; orderByDesc here keeps high performers top
            ->limit($limit ?? 3)
            ->get();

        $results = [];

        foreach ($campaigns as $campaign) {
            $row = (array) $campaign;

            $row['creatives'] = DB::table(self::TABLE_CREATIVES)
                ->where('campaign_id', $campaign->id)
                ->where('tenant_id', $tenantId)
                ->where('is_active', 1)
                ->get()
                ->map(fn ($r) => (array) $r)
                ->all();

            $results[] = $row;
        }

        return $results;
    }

    // ──────────────────────────────────────────────────────────────────────────
    // Event tracking
    // ──────────────────────────────────────────────────────────────────────────

    /**
     * Record that an ad was shown to a user (or anonymous visitor).
     *
     * Atomically increments the campaign's impression_count.
     *
     * @return int  The new impression row ID.
     */
    public static function recordImpression(
        int $campaignId,
        int $creativeId,
        int $tenantId,
        string $placement,
        ?int $userId = null
    ): int {
        self::assertAvailable();

        $impressionId = DB::table(self::TABLE_IMPRESSIONS)->insertGetId([
            'campaign_id' => $campaignId,
            'creative_id' => $creativeId,
            'tenant_id'   => $tenantId,
            'user_id'     => $userId,
            'placement'   => $placement,
            'created_at'  => Carbon::now(),
        ]);

        DB::table(self::TABLE_CAMPAIGNS)
            ->where('id', $campaignId)
            ->where('tenant_id', $tenantId)
            ->increment('impression_count');

        return $impressionId;
    }

    /**
     * Record a click on an ad impression.
     *
     * Atomically increments the campaign's click_count and deducts CPC from
     * budget (default 10 cents if campaign has no explicit CPC set).
     */
    public static function recordClick(
        int $impressionId,
        int $campaignId,
        int $tenantId,
        ?int $userId = null
    ): void {
        self::assertAvailable();

        DB::table(self::TABLE_CLICKS)->insert([
            'impression_id' => $impressionId,
            'campaign_id'   => $campaignId,
            'tenant_id'     => $tenantId,
            'user_id'       => $userId,
            'created_at'    => Carbon::now(),
        ]);

        DB::table(self::TABLE_CAMPAIGNS)
            ->where('id', $campaignId)
            ->where('tenant_id', $tenantId)
            ->update([
                'click_count'  => DB::raw('click_count + 1'),
                'spent_cents'  => DB::raw('spent_cents + ' . self::DEFAULT_CPC_CENTS),
                'updated_at'   => Carbon::now(),
            ]);
    }

    // ──────────────────────────────────────────────────────────────────────────
    // Stats & Analytics
    // ──────────────────────────────────────────────────────────────────────────

    /**
     * Return detailed stats for a single campaign including 30-day daily breakdown.
     *
     * @return array<string, mixed>
     */
    public static function getCampaignStats(int $id, int $tenantId): array
    {
        self::assertAvailable();

        $campaign = DB::table(self::TABLE_CAMPAIGNS)
            ->where('id', $id)
            ->where('tenant_id', $tenantId)
            ->first();

        if ($campaign === null) {
            return [];
        }

        $impressions    = (int) $campaign->impression_count;
        $clicks         = (int) $campaign->click_count;
        $budgetCents    = (int) $campaign->budget_cents;
        $spentCents     = (int) $campaign->spent_cents;
        $ctr            = $impressions > 0 ? round(($clicks / $impressions) * 100, 2) : 0.0;
        $budgetRemaining = $budgetCents > 0 ? max(0, $budgetCents - $spentCents) : null;

        // Daily breakdown — last 30 days
        $since = Carbon::now()->subDays(30)->startOfDay();

        $dailyImpressions = DB::table(self::TABLE_IMPRESSIONS)
            ->where('campaign_id', $id)
            ->where('tenant_id', $tenantId)
            ->where('created_at', '>=', $since)
            ->selectRaw('DATE(created_at) AS day, COUNT(*) AS count')
            ->groupBy('day')
            ->orderBy('day')
            ->pluck('count', 'day')
            ->all();

        $dailyClicks = DB::table(self::TABLE_CLICKS)
            ->where('campaign_id', $id)
            ->where('tenant_id', $tenantId)
            ->where('created_at', '>=', $since)
            ->selectRaw('DATE(created_at) AS day, COUNT(*) AS count')
            ->groupBy('day')
            ->orderBy('day')
            ->pluck('count', 'day')
            ->all();

        // Build a full 30-day series (fill gaps with zeros)
        $daily = [];
        for ($i = 29; $i >= 0; $i--) {
            $day = Carbon::now()->subDays($i)->toDateString();
            $daily[] = [
                'date'        => $day,
                'impressions' => (int) ($dailyImpressions[$day] ?? 0),
                'clicks'      => (int) ($dailyClicks[$day] ?? 0),
            ];
        }

        return [
            'campaign_id'      => $id,
            'impressions'      => $impressions,
            'clicks'           => $clicks,
            'ctr_percent'      => $ctr,
            'budget_cents'     => $budgetCents,
            'spent_cents'      => $spentCents,
            'budget_remaining' => $budgetRemaining,
            'daily'            => $daily,
        ];
    }

    /**
     * Tenant-level overview stats for the admin dashboard.
     *
     * @return array{
     *     active_campaigns: int,
     *     impressions_today: int,
     *     clicks_today: int,
     *     total_revenue_cents: int,
     * }
     */
    public static function getOverviewStats(int $tenantId): array
    {
        self::assertAvailable();

        $todayStart = Carbon::today();

        $activeCampaigns = (int) DB::table(self::TABLE_CAMPAIGNS)
            ->where('tenant_id', $tenantId)
            ->where('status', 'active')
            ->count();

        $impressionsToday = (int) DB::table(self::TABLE_IMPRESSIONS)
            ->where('tenant_id', $tenantId)
            ->where('created_at', '>=', $todayStart)
            ->count();

        $clicksToday = (int) DB::table(self::TABLE_CLICKS)
            ->where('tenant_id', $tenantId)
            ->where('created_at', '>=', $todayStart)
            ->count();

        $totalRevenueCents = (int) DB::table(self::TABLE_CAMPAIGNS)
            ->where('tenant_id', $tenantId)
            ->sum('spent_cents');

        return [
            'active_campaigns'    => $activeCampaigns,
            'impressions_today'   => $impressionsToday,
            'clicks_today'        => $clicksToday,
            'total_revenue_cents' => $totalRevenueCents,
        ];
    }

    // ──────────────────────────────────────────────────────────────────────────
    // Internal helpers
    // ──────────────────────────────────────────────────────────────────────────

    /**
     * Throw a RuntimeException if the advertising tables have not been created.
     *
     * @throws \RuntimeException
     */
    private static function assertAvailable(): void
    {
        if (!self::isAvailable()) {
            throw new \RuntimeException(
                'LocalAdvertisingService: advertising tables do not exist. Run migrations first.'
            );
        }
    }
}

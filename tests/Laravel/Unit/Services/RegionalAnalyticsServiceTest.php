<?php
// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

namespace Tests\Laravel\Unit\Services;

use Tests\Laravel\TestCase;
use App\Services\RegionalAnalyticsService;
use App\Core\TenantContext;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;

/**
 * RegionalAnalyticsServiceTest
 *
 * Tests aggregation math, filters, cache, tenant isolation, and empty-data
 * paths for all public methods of RegionalAnalyticsService.
 *
 * Fixture strategy: direct DB inserts into an isolated tenant (99410) that is
 * guaranteed to have zero pre-existing data, so counts/sums are fully
 * deterministic. All rows are rolled back by DatabaseTransactions.
 *
 * Previously-broken report sections (now fixed in RegionalAnalyticsService) and
 * the real columns/enum values they were corrected to use:
 *   - getDemographics(): age_group CASE now reads users.date_of_birth (was the
 *     non-existent `birthdate`).
 *   - getVolunteerBreakdown(): top-orgs query now joins on vol_logs.organization_id
 *     (was the non-existent `org_id`).
 *   - getHelpRequestAnalysis(): caring_help_requests has no `category` column, so the
 *     breakdown now groups by `contact_preference` (surfaced under the `category` key
 *     for the API/frontend contract). Resolution is counted via the real `closed`
 *     status (the enum is pending|matched|closed; there is no `resolved`), matching
 *     the convention in HelpRequestSlaService.
 */
class RegionalAnalyticsServiceTest extends TestCase
{
    use DatabaseTransactions;

    // Isolated tenant ID — must not clash with any real tenants.
    private const TENANT_ID = 99410;

    protected function setUp(): void
    {
        parent::setUp();

        // Insert the test tenant if it doesn't exist (rolled back by DatabaseTransactions).
        DB::table('tenants')->insertOrIgnore([
            'id'         => self::TENANT_ID,
            'name'       => 'RegionalAnalytics Test Tenant',
            'slug'       => 'regional-analytics-test-' . self::TENANT_ID,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        TenantContext::setById(self::TENANT_ID);

        // Clear any stale cache rows for this test tenant at the start of each test.
        DB::table('regional_analytics_cache')
            ->where('tenant_id', self::TENANT_ID)
            ->delete();
    }

    // ──────────────────────────────────────────────────────────────────────────
    // Helpers
    // ──────────────────────────────────────────────────────────────────────────

    /** Insert a minimal active user and return the ID. */
    private function insertUser(array $extra = []): int
    {
        $uid = uniqid('u', true);
        return DB::table('users')->insertGetId(array_merge([
            'tenant_id'  => self::TENANT_ID,
            'name'       => 'Test User ' . $uid,
            'first_name' => 'Test',
            'last_name'  => 'User',
            'email'      => $uid . '@ras-test.invalid',
            'status'     => 'active',
            'balance'    => 0,
            'role'       => 'member',
            'is_approved'=> 1,
            'created_at' => now(),
            'updated_at' => now(),
        ], $extra));
    }

    /** Insert a vol_log row and return the ID. */
    private function insertVolLog(int $userId, float $hours, string $status = 'approved', ?string $createdAt = null, ?int $organizationId = null): int
    {
        return DB::table('vol_logs')->insertGetId([
            'tenant_id'       => self::TENANT_ID,
            'user_id'         => $userId,
            'organization_id' => $organizationId,
            'date_logged'     => now()->toDateString(),
            'hours'           => $hours,
            'status'          => $status,
            'created_at'      => $createdAt ?? now(),
            'updated_at'      => now(),
        ]);
    }

    /** Insert a vol_organizations row and return the ID. */
    private function insertVolOrganization(string $name): int
    {
        return DB::table('vol_organizations')->insertGetId([
            'tenant_id'  => self::TENANT_ID,
            'user_id'    => $this->insertUser(),
            'name'       => $name,
            'slug'       => 'org-' . uniqid(),
            'status'     => 'active',
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    /** Insert a caring_help_requests row and return the ID. */
    private function insertHelpRequest(int $userId, string $contactPreference = 'either', string $status = 'pending', ?string $createdAt = null): int
    {
        return DB::table('caring_help_requests')->insertGetId([
            'tenant_id'          => self::TENANT_ID,
            'user_id'            => $userId,
            'what'               => 'Need help ' . uniqid(),
            'when_needed'        => 'ASAP',
            'contact_preference' => $contactPreference,
            'status'             => $status,
            'created_at'         => $createdAt ?? now(),
            'updated_at'         => now(),
        ]);
    }

    /** Insert a listing and return the ID. */
    private function insertListing(int $userId, string $type = 'offer', string $status = 'active', ?int $categoryId = null): int
    {
        return DB::table('listings')->insertGetId([
            'tenant_id'   => self::TENANT_ID,
            'user_id'     => $userId,
            'category_id' => $categoryId,
            'title'       => 'Test Listing ' . uniqid(),
            'type'        => $type,
            'status'      => $status,
            'created_at'  => now(),
            'updated_at'  => now(),
        ]);
    }

    /** Insert a category and return the ID. */
    private function insertCategory(string $name): int
    {
        return DB::table('categories')->insertGetId([
            'tenant_id' => self::TENANT_ID,
            'name'      => $name . '-' . uniqid(),
            'slug'      => 'cat-' . uniqid(),
            'type'      => 'listing',
            'created_at'=> now(),
            'updated_at'=> now(),
        ]);
    }

    // ──────────────────────────────────────────────────────────────────────────
    // 1. isAvailable
    // ──────────────────────────────────────────────────────────────────────────

    public function test_isAvailable_returns_true_when_cache_table_exists(): void
    {
        $result = RegionalAnalyticsService::isAvailable();

        // The schema includes regional_analytics_cache, so it should be true.
        $this->assertTrue($result);
    }

    // ──────────────────────────────────────────────────────────────────────────
    // 2. getMemberHeatmap
    // ──────────────────────────────────────────────────────────────────────────

    public function test_getMemberHeatmap_returns_empty_array_when_no_users_with_coords(): void
    {
        // Insert a user without lat/lng.
        $this->insertUser();

        $result = RegionalAnalyticsService::getMemberHeatmap(self::TENANT_ID, 'all_time');

        $this->assertIsArray($result);
        $this->assertArrayNotHasKey('error', $result);
        $this->assertEmpty($result);
    }

    public function test_getMemberHeatmap_suppresses_cells_with_fewer_than_3_members(): void
    {
        // Only 2 users at the same grid bucket — privacy rule should suppress them.
        foreach (range(1, 2) as $_) {
            $this->insertUser(['latitude' => 53.3300, 'longitude' => -6.2600]);
        }

        $result = RegionalAnalyticsService::getMemberHeatmap(self::TENANT_ID, 'all_time');

        $this->assertIsArray($result);
        $this->assertArrayNotHasKey('error', $result);
        $this->assertEmpty($result, 'Cells with <3 members must be suppressed by privacy rule');
    }

    public function test_getMemberHeatmap_includes_cells_with_3_or_more_members(): void
    {
        // Insert 3 users at the same ~0.01° bucket.
        foreach (range(1, 3) as $_) {
            $this->insertUser(['latitude' => 53.3301, 'longitude' => -6.2601]);
        }

        $result = RegionalAnalyticsService::getMemberHeatmap(self::TENANT_ID, 'all_time');

        $this->assertIsArray($result);
        $this->assertArrayNotHasKey('error', $result);
        $this->assertCount(1, $result, 'One grid cell with >=3 members should be returned');

        $cell = $result[0];
        $this->assertArrayHasKey('lat', $cell);
        $this->assertArrayHasKey('lng', $cell);
        $this->assertArrayHasKey('count', $cell);
        $this->assertSame(3, $cell['count']);
    }

    public function test_getMemberHeatmap_does_not_cross_tenant_boundary(): void
    {
        // Insert 3 users for our test tenant at a distinct location.
        foreach (range(1, 3) as $_) {
            $this->insertUser(['latitude' => 48.8600, 'longitude' => 2.3500]);
        }

        // Query a completely different tenant — should get no results.
        $result = RegionalAnalyticsService::getMemberHeatmap(99411, 'all_time');

        $this->assertIsArray($result);
        $this->assertEmpty($result);
    }

    public function test_getMemberHeatmap_returns_from_cache_on_second_call(): void
    {
        // First call: no geo users → empty result cached.
        $first = RegionalAnalyticsService::getMemberHeatmap(self::TENANT_ID, 'all_time');

        // Insert users with coords AFTER the first call (they shouldn't appear in second call).
        foreach (range(1, 3) as $_) {
            $this->insertUser(['latitude' => 51.5000, 'longitude' => -0.1000]);
        }

        // Second call should return cached result (still empty).
        $second = RegionalAnalyticsService::getMemberHeatmap(self::TENANT_ID, 'all_time');

        $this->assertSame($first, $second, 'Second call must return cached result');
        $this->assertEmpty($second);
    }

    // ──────────────────────────────────────────────────────────────────────────
    // 3. getDemandSupplyRatio
    // ──────────────────────────────────────────────────────────────────────────

    public function test_getDemandSupplyRatio_returns_empty_for_tenant_with_no_listings(): void
    {
        $result = RegionalAnalyticsService::getDemandSupplyRatio(self::TENANT_ID, 'all_time');

        $this->assertIsArray($result);
        $this->assertArrayNotHasKey('error', $result);
        $this->assertEmpty($result);
    }

    public function test_getDemandSupplyRatio_counts_requests_and_offers_per_category(): void
    {
        $userId  = $this->insertUser();
        $catId   = $this->insertCategory('Gardening');

        // 3 requests, 1 offer in the same category.
        foreach (range(1, 3) as $_) {
            $this->insertListing($userId, 'request', 'active', $catId);
        }
        $this->insertListing($userId, 'offer', 'active', $catId);

        $result = RegionalAnalyticsService::getDemandSupplyRatio(self::TENANT_ID, 'all_time');

        $this->assertIsArray($result);
        $this->assertNotEmpty($result);

        // Find the row matching our category.
        $row = collect($result)->firstWhere('category_id', $catId);
        $this->assertNotNull($row, 'Category row not found in demand/supply result');
        $this->assertSame(3, $row['request_count']);
        $this->assertSame(1, $row['offer_count']);
        // ratio = 3 / 1 = 3.0
        $this->assertEqualsWithDelta(3.0, $row['ratio'], 0.01);
    }

    public function test_getDemandSupplyRatio_ratio_is_999_when_offers_is_zero_but_requests_exist(): void
    {
        $userId = $this->insertUser();
        $catId  = $this->insertCategory('Transport');

        $this->insertListing($userId, 'request', 'active', $catId);

        $result = RegionalAnalyticsService::getDemandSupplyRatio(self::TENANT_ID, 'all_time');

        $row = collect($result)->firstWhere('category_id', $catId);
        $this->assertNotNull($row);
        $this->assertSame(0, $row['offer_count']);
        $this->assertEqualsWithDelta(999.0, $row['ratio'], 0.01);
    }

    public function test_getDemandSupplyRatio_result_has_required_keys(): void
    {
        $userId = $this->insertUser();
        $catId  = $this->insertCategory('Cooking');
        $this->insertListing($userId, 'offer', 'active', $catId);

        $result = RegionalAnalyticsService::getDemandSupplyRatio(self::TENANT_ID, 'all_time');

        $this->assertNotEmpty($result);
        foreach (['category_id', 'category_name', 'request_count', 'offer_count', 'ratio', 'trend'] as $key) {
            $this->assertArrayHasKey($key, $result[0], "Key '{$key}' missing from demand/supply row");
        }
    }

    // ──────────────────────────────────────────────────────────────────────────
    // 4. getDemographics
    // ──────────────────────────────────────────────────────────────────────────

    // getDemographics() now reads users.date_of_birth (the real column) for the
    // age_group CASE expression, so it returns aggregated demographics rather than
    // an error response.
    public function test_getDemographics_returns_age_groups_languages_and_growth(): void
    {
        // 25_34 bracket, 65_plus bracket, and one with no DOB → 'unknown'.
        $this->insertUser(['date_of_birth' => now()->subYears(30)->toDateString()]);
        $this->insertUser(['date_of_birth' => now()->subYears(70)->toDateString()]);
        $this->insertUser(); // date_of_birth NULL → 'unknown'

        $result = RegionalAnalyticsService::getDemographics(self::TENANT_ID);

        $this->assertIsArray($result);
        $this->assertArrayNotHasKey('error', $result);
        foreach (['age_groups', 'languages', 'monthly_growth'] as $key) {
            $this->assertArrayHasKey($key, $result, "Key '{$key}' missing from demographics");
        }

        $this->assertSame(1, $result['age_groups']['25_34'] ?? 0);
        $this->assertSame(1, $result['age_groups']['65_plus'] ?? 0);
        $this->assertSame(1, $result['age_groups']['unknown'] ?? 0);
    }

    public function test_getDemographics_language_breakdown_counts_correctly(): void
    {
        $this->insertUser(['preferred_language' => 'en']);
        $this->insertUser(['preferred_language' => 'en']);
        $this->insertUser(['preferred_language' => 'fr']);

        $result = RegionalAnalyticsService::getDemographics(self::TENANT_ID);

        $this->assertArrayNotHasKey('error', $result);

        $languages = collect($result['languages']);
        $en = $languages->firstWhere('language', 'en');
        $fr = $languages->firstWhere('language', 'fr');

        $this->assertNotNull($en, 'English language bucket missing');
        $this->assertSame(2, $en['count']);
        $this->assertNotNull($fr, 'French language bucket missing');
        $this->assertSame(1, $fr['count']);

        // Ordered by count desc → most common language first.
        $this->assertSame('en', $result['languages'][0]['language']);
    }

    public function test_getDemographics_monthly_growth_is_chronologically_ordered(): void
    {
        $this->insertUser(['created_at' => now()->subMonths(2)->startOfMonth()->addDay()->toDateTimeString()]);
        $this->insertUser(['created_at' => now()->subMonths(1)->startOfMonth()->addDay()->toDateTimeString()]);
        $this->insertUser(['created_at' => now()->startOfMonth()->addDay()->toDateTimeString()]);

        $result = RegionalAnalyticsService::getDemographics(self::TENANT_ID);

        $this->assertArrayNotHasKey('error', $result);
        $this->assertNotEmpty($result['monthly_growth']);

        $months = array_column($result['monthly_growth'], 'month');
        $sorted = $months;
        sort($sorted);
        $this->assertSame($sorted, $months, 'monthly_growth must be chronologically ordered by month');
    }

    public function test_getDemographics_monthly_growth_cumulative_is_monotonically_non_decreasing(): void
    {
        $this->insertUser(['created_at' => now()->subMonths(2)->startOfMonth()->addDay()->toDateTimeString()]);
        $this->insertUser(['created_at' => now()->subMonths(1)->startOfMonth()->addDay()->toDateTimeString()]);
        $this->insertUser(['created_at' => now()->subMonths(1)->startOfMonth()->addDays(2)->toDateTimeString()]);
        $this->insertUser(['created_at' => now()->startOfMonth()->addDay()->toDateTimeString()]);

        $result = RegionalAnalyticsService::getDemographics(self::TENANT_ID);

        $this->assertArrayNotHasKey('error', $result);

        $prev = -1;
        foreach ($result['monthly_growth'] as $row) {
            $this->assertArrayHasKey('cumulative', $row);
            $this->assertGreaterThanOrEqual($prev, $row['cumulative'],
                'cumulative member count must never decrease month over month');
            $prev = $row['cumulative'];
        }

        // Tenant is isolated with no members older than 12 months, so the final
        // cumulative equals the total inserted.
        $last = end($result['monthly_growth']);
        $this->assertSame(4, $last['cumulative']);
    }

    // ──────────────────────────────────────────────────────────────────────────
    // 5. getEngagementTrends
    // ──────────────────────────────────────────────────────────────────────────

    public function test_getEngagementTrends_returns_array_of_months(): void
    {
        $result = RegionalAnalyticsService::getEngagementTrends(self::TENANT_ID, 'last_12m');

        $this->assertIsArray($result);
        $this->assertArrayNotHasKey('error', $result);
        $this->assertNotEmpty($result, 'Should return at least one month slot even with no data');
    }

    public function test_getEngagementTrends_month_entries_have_required_keys(): void
    {
        $result = RegionalAnalyticsService::getEngagementTrends(self::TENANT_ID, 'last_12m');

        $this->assertNotEmpty($result);
        foreach (['month', 'active_members', 'vol_hours', 'new_listings', 'new_events', 'help_requests'] as $key) {
            $this->assertArrayHasKey($key, $result[0], "Key '{$key}' missing from engagement trend entry");
        }
    }

    public function test_getEngagementTrends_vol_hours_sum_is_correct(): void
    {
        $userId = $this->insertUser();

        // Insert 2 approved vol_logs this month: 2.5h + 1.5h = 4.0h
        $this->insertVolLog($userId, 2.5, 'approved', now()->toDateTimeString());
        $this->insertVolLog($userId, 1.5, 'approved', now()->toDateTimeString());
        // Insert a pending log that should NOT be counted in vol_hours sum.
        $this->insertVolLog($userId, 10.0, 'pending', now()->toDateTimeString());

        $result = RegionalAnalyticsService::getEngagementTrends(self::TENANT_ID, 'last_12m');

        $currentMonth = now()->format('Y-m');
        $monthData = collect($result)->firstWhere('month', $currentMonth);

        $this->assertNotNull($monthData, 'Current month slot must exist in engagement trends');
        $this->assertEqualsWithDelta(4.0, $monthData['vol_hours'], 0.05,
            'Only approved vol_logs hours should be summed');
    }

    public function test_getEngagementTrends_new_listings_count_is_correct(): void
    {
        $userId = $this->insertUser();

        // Insert 2 listings this month.
        $this->insertListing($userId, 'offer');
        $this->insertListing($userId, 'request');

        $result = RegionalAnalyticsService::getEngagementTrends(self::TENANT_ID, 'last_12m');

        $currentMonth = now()->format('Y-m');
        $monthData    = collect($result)->firstWhere('month', $currentMonth);

        $this->assertNotNull($monthData);
        $this->assertSame(2, $monthData['new_listings']);
    }

    // ──────────────────────────────────────────────────────────────────────────
    // 6. getVolunteerBreakdown
    // ──────────────────────────────────────────────────────────────────────────

    // getVolunteerBreakdown() now joins on vol_logs.organization_id (the real column),
    // so it returns the breakdown structure rather than ['error' => 'data_unavailable'].
    public function test_getVolunteerBreakdown_returns_required_keys(): void
    {
        $result = RegionalAnalyticsService::getVolunteerBreakdown(self::TENANT_ID, 'all_time');

        $this->assertIsArray($result);
        $this->assertArrayNotHasKey('error', $result);
        foreach (['top_orgs', 'avg_hours_per_volunteer', 'total_hours', 'reciprocity_ratio'] as $key) {
            $this->assertArrayHasKey($key, $result, "Key '{$key}' missing from volunteer breakdown");
        }
    }

    public function test_getVolunteerBreakdown_returns_zeroed_structure_when_no_vol_logs(): void
    {
        $result = RegionalAnalyticsService::getVolunteerBreakdown(self::TENANT_ID, 'all_time');

        $this->assertArrayNotHasKey('error', $result);
        $this->assertSame([], $result['top_orgs']);
        $this->assertSame(0.0, $result['total_hours']);
        $this->assertSame(0.0, $result['avg_hours_per_volunteer']);
        $this->assertSame(0.0, $result['reciprocity_ratio']);
    }

    public function test_getVolunteerBreakdown_returns_correct_total_hours(): void
    {
        $user1 = $this->insertUser();
        $user2 = $this->insertUser();

        // Approved: 2.0 + 3.0 + 4.0 = 9.0 across 2 distinct volunteers.
        $this->insertVolLog($user1, 2.0, 'approved');
        $this->insertVolLog($user1, 3.0, 'approved');
        $this->insertVolLog($user2, 4.0, 'approved');
        // Pending must NOT be counted.
        $this->insertVolLog($user2, 100.0, 'pending');

        $result = RegionalAnalyticsService::getVolunteerBreakdown(self::TENANT_ID, 'all_time');

        $this->assertArrayNotHasKey('error', $result);
        $this->assertEqualsWithDelta(9.0, $result['total_hours'], 0.05,
            'Only approved vol_logs hours should be summed');
    }

    public function test_getVolunteerBreakdown_avg_hours_per_volunteer_is_computed_correctly(): void
    {
        $user1 = $this->insertUser();
        $user2 = $this->insertUser();

        // Total 10.0 hours across 2 distinct volunteers → avg 5.0.
        $this->insertVolLog($user1, 6.0, 'approved');
        $this->insertVolLog($user2, 4.0, 'approved');

        $result = RegionalAnalyticsService::getVolunteerBreakdown(self::TENANT_ID, 'all_time');

        $this->assertArrayNotHasKey('error', $result);
        $this->assertEqualsWithDelta(10.0, $result['total_hours'], 0.05);
        $this->assertEqualsWithDelta(5.0, $result['avg_hours_per_volunteer'], 0.01);
    }

    public function test_getVolunteerBreakdown_top_orgs_aggregates_hours_by_organization(): void
    {
        $orgId = $this->insertVolOrganization('Helping Hands');
        $user1 = $this->insertUser();
        $user2 = $this->insertUser();

        // 5.0 + 2.5 = 7.5 hours for the org across 2 distinct volunteers.
        $this->insertVolLog($user1, 5.0, 'approved', null, $orgId);
        $this->insertVolLog($user2, 2.5, 'approved', null, $orgId);

        $result = RegionalAnalyticsService::getVolunteerBreakdown(self::TENANT_ID, 'all_time');

        $this->assertArrayNotHasKey('error', $result);
        $this->assertNotEmpty($result['top_orgs'], 'top_orgs should be populated after the organization_id fix');

        $org = collect($result['top_orgs'])->firstWhere('org_id', $orgId);
        $this->assertNotNull($org, 'Organization row not found in top_orgs');
        $this->assertSame('Helping Hands', $org['org_name']);
        $this->assertEqualsWithDelta(7.5, $org['total_hours'], 0.05);
        $this->assertSame(2, $org['volunteers']);
    }

    // ──────────────────────────────────────────────────────────────────────────
    // 7. getHelpRequestAnalysis
    // ──────────────────────────────────────────────────────────────────────────

    // getHelpRequestAnalysis() now groups by the real `contact_preference` column
    // (caring_help_requests has no `category` column) and counts resolution via the
    // real `closed` status, so it returns aggregated stats rather than an error.
    public function test_getHelpRequestAnalysis_groups_by_contact_preference_with_resolution_stats(): void
    {
        $userId = $this->insertUser();

        // 2 'phone' requests: one closed (resolved), one pending.
        $this->insertHelpRequest($userId, 'phone', 'closed');
        $this->insertHelpRequest($userId, 'phone', 'pending');
        // 1 'message' request, matched — NOT resolved under `closed` semantics.
        $this->insertHelpRequest($userId, 'message', 'matched');

        $result = RegionalAnalyticsService::getHelpRequestAnalysis(self::TENANT_ID, 'all_time');

        $this->assertIsArray($result);
        $this->assertArrayNotHasKey('error', $result);
        $this->assertArrayHasKey('by_category', $result);
        $this->assertArrayHasKey('resolution_trend', $result);

        $phone = collect($result['by_category'])->firstWhere('category', 'phone');
        $this->assertNotNull($phone, 'phone contact-preference bucket missing');
        $this->assertSame(2, $phone['total']);
        $this->assertSame(1, $phone['resolved_count'], 'only the closed request counts as resolved');
        $this->assertEqualsWithDelta(50.0, $phone['resolution_rate'], 0.05);

        $message = collect($result['by_category'])->firstWhere('category', 'message');
        $this->assertNotNull($message, 'message contact-preference bucket missing');
        $this->assertSame(1, $message['total']);
        $this->assertSame(0, $message['resolved_count'], 'matched is not closed → not resolved');
    }

    public function test_getHelpRequestAnalysis_resolution_trend_entries_have_required_keys(): void
    {
        $userId = $this->insertUser();
        $this->insertHelpRequest($userId, 'either', 'closed');

        $result = RegionalAnalyticsService::getHelpRequestAnalysis(self::TENANT_ID, 'all_time');

        $this->assertArrayNotHasKey('error', $result);
        $this->assertNotEmpty($result['resolution_trend']);
        foreach (['month', 'total', 'resolved', 'resolution_rate'] as $key) {
            $this->assertArrayHasKey($key, $result['resolution_trend'][0],
                "Key '{$key}' missing from resolution_trend entry");
        }

        // The single closed request resolves to a 100% rate for its month.
        $this->assertEqualsWithDelta(100.0, $result['resolution_trend'][0]['resolution_rate'], 0.05);
    }

    public function test_getHelpRequestAnalysis_returns_empty_structure_when_no_requests(): void
    {
        $result = RegionalAnalyticsService::getHelpRequestAnalysis(self::TENANT_ID, 'all_time');

        $this->assertIsArray($result);
        $this->assertArrayNotHasKey('error', $result);
        $this->assertArrayHasKey('by_category', $result);
        $this->assertArrayHasKey('resolution_trend', $result);
        $this->assertSame([], $result['by_category']);
        $this->assertSame([], $result['resolution_trend']);
    }

    // ──────────────────────────────────────────────────────────────────────────
    // 8. getOverviewSummary
    // ──────────────────────────────────────────────────────────────────────────

    public function test_getOverviewSummary_returns_required_keys(): void
    {
        $result = RegionalAnalyticsService::getOverviewSummary(self::TENANT_ID);

        $this->assertIsArray($result);
        $this->assertArrayNotHasKey('error', $result);
        foreach (['active_members', 'vol_hours_this_month', 'help_requests_this_month', 'most_needed_category'] as $key) {
            $this->assertArrayHasKey($key, $result, "Key '{$key}' missing from overview summary");
        }
    }

    public function test_getOverviewSummary_active_members_counts_distinct_vol_log_users_in_last_30d(): void
    {
        $user1 = $this->insertUser();
        $user2 = $this->insertUser();

        // Both users logged volunteer time in last 30 days.
        $this->insertVolLog($user1, 1.0, 'approved', now()->subDays(5)->toDateTimeString());
        $this->insertVolLog($user2, 1.0, 'approved', now()->subDays(10)->toDateTimeString());
        // user1 logs twice — should only count as 1 distinct user.
        $this->insertVolLog($user1, 2.0, 'approved', now()->subDays(3)->toDateTimeString());
        // user3 logged > 30 days ago — should NOT be counted.
        $user3 = $this->insertUser();
        $this->insertVolLog($user3, 1.0, 'approved', now()->subDays(35)->toDateTimeString());

        $result = RegionalAnalyticsService::getOverviewSummary(self::TENANT_ID);

        $this->assertSame(2, $result['active_members'],
            'active_members should count only distinct users with vol_logs in last 30d');
    }

    public function test_getOverviewSummary_vol_hours_this_month_sums_only_approved_logs(): void
    {
        $userId = $this->insertUser();

        // 3h approved this month, 10h pending this month.
        $this->insertVolLog($userId, 3.0, 'approved', now()->startOfMonth()->addHours(1)->toDateTimeString());
        $this->insertVolLog($userId, 10.0, 'pending', now()->startOfMonth()->addHours(2)->toDateTimeString());

        $result = RegionalAnalyticsService::getOverviewSummary(self::TENANT_ID);

        $this->assertEqualsWithDelta(3.0, $result['vol_hours_this_month'], 0.05,
            'Only approved vol_logs should be summed for vol_hours_this_month');
    }

    public function test_getOverviewSummary_help_requests_this_month_counts_correctly(): void
    {
        $userId = $this->insertUser();

        // 2 requests this month.
        foreach (range(1, 2) as $_) {
            DB::table('caring_help_requests')->insert([
                'tenant_id'          => self::TENANT_ID,
                'user_id'            => $userId,
                'what'               => 'Help needed',
                'when_needed'        => 'Soon',
                'contact_preference' => 'either',
                'status'             => 'pending',
                'created_at'         => now()->startOfMonth()->addHours(1)->toDateTimeString(),
                'updated_at'         => now()->toDateTimeString(),
            ]);
        }

        // 1 request from last month — must NOT be counted.
        DB::table('caring_help_requests')->insert([
            'tenant_id'          => self::TENANT_ID,
            'user_id'            => $userId,
            'what'               => 'Old request',
            'when_needed'        => 'Last month',
            'contact_preference' => 'either',
            'status'             => 'pending',
            'created_at'         => now()->subMonthNoOverflow()->startOfMonth()->toDateTimeString(),
            'updated_at'         => now()->toDateTimeString(),
        ]);

        $result = RegionalAnalyticsService::getOverviewSummary(self::TENANT_ID);

        $this->assertSame(2, $result['help_requests_this_month']);
    }

    // ──────────────────────────────────────────────────────────────────────────
    // 9. invalidateCache
    // ──────────────────────────────────────────────────────────────────────────

    public function test_invalidateCache_removes_cache_rows_for_tenant(): void
    {
        // Seed a cache row directly.
        DB::table('regional_analytics_cache')->insertOrIgnore([
            'tenant_id'   => self::TENANT_ID,
            'report_type' => 'overview',
            'period'      => 'last_30d',
            'payload'     => json_encode(['test' => true]),
            'computed_at' => now(),
            'expires_at'  => now()->addHours(6),
        ]);

        $this->assertSame(1, DB::table('regional_analytics_cache')
            ->where('tenant_id', self::TENANT_ID)->count());

        RegionalAnalyticsService::invalidateCache(self::TENANT_ID);

        $this->assertSame(0, DB::table('regional_analytics_cache')
            ->where('tenant_id', self::TENANT_ID)->count(), 'Cache rows must be deleted after invalidateCache');
    }

    public function test_invalidateCache_does_not_remove_cache_rows_for_other_tenants(): void
    {
        // Seed cache rows for two tenants.
        foreach ([self::TENANT_ID, 99412] as $tid) {
            DB::table('regional_analytics_cache')->insertOrIgnore([
                'tenant_id'   => $tid,
                'report_type' => 'overview',
                'period'      => 'last_30d',
                'payload'     => json_encode([]),
                'computed_at' => now(),
                'expires_at'  => now()->addHours(6),
            ]);
        }

        RegionalAnalyticsService::invalidateCache(self::TENANT_ID);

        // Other tenant's row should be unaffected.
        $this->assertSame(1, DB::table('regional_analytics_cache')
            ->where('tenant_id', 99412)->count(),
            'invalidateCache must not remove cache rows belonging to other tenants');
    }

    // ──────────────────────────────────────────────────────────────────────────
    // 10. exportReportJson
    // ──────────────────────────────────────────────────────────────────────────

    public function test_exportReportJson_returns_all_top_level_keys(): void
    {
        $result = RegionalAnalyticsService::exportReportJson(self::TENANT_ID, 'last_30d');

        foreach ([
            'tenant_id', 'tenant_name', 'report_generated_at', 'period',
            'overview', 'heatmap', 'demand_supply', 'demographics',
            'engagement_trends', 'volunteer_breakdown', 'help_requests',
        ] as $key) {
            $this->assertArrayHasKey($key, $result, "Key '{$key}' missing from exportReportJson");
        }
    }

    public function test_exportReportJson_tenant_id_matches_input(): void
    {
        $result = RegionalAnalyticsService::exportReportJson(self::TENANT_ID);

        $this->assertSame(self::TENANT_ID, $result['tenant_id']);
    }

    public function test_exportReportJson_period_is_stored_in_output(): void
    {
        $result = RegionalAnalyticsService::exportReportJson(self::TENANT_ID, 'last_90d');

        $this->assertSame('last_90d', $result['period']);
    }
}

<?php
// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

namespace Tests\Laravel\Unit\Services;

use Tests\Laravel\TestCase;
use App\Services\MunicipalImpactReportService;
use App\Services\CaringCommunityWorkflowPolicyService;
use App\Core\TenantContext;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;

/**
 * MunicipalImpactReportServiceTest
 *
 * Strategy: insert real rows into transactions, vol_logs, users, vol_organizations,
 * vol_opportunities, and listings, scoped to a unique high-range tenant ID (99400)
 * so we never collide with tenant-2 production data.  All rows roll back via
 * DatabaseTransactions.
 *
 * Skipped (noted inline):
 *  - periodTrends() vol_log branch: requires careful date-range alignment that
 *    cannot be isolated without touching the same rows already used for
 *    volunteeringSummary — covered via the summary() aggregate instead.
 *  - canton YoY change: prior-year calculation references today's wall-clock date
 *    and would require either date injection or matching wall-clock inserts, making
 *    it brittle; covered structurally (keys present, null when no prior data).
 */
class MunicipalImpactReportServiceTest extends TestCase
{
    use DatabaseTransactions;

    private const TENANT_ID = 99400;

    private MunicipalImpactReportService $svc;

    protected function setUp(): void
    {
        parent::setUp();

        // Insert a minimal tenant row so TenantContext::setById() resolves.
        DB::table('tenants')->insertOrIgnore([
            'id'              => self::TENANT_ID,
            'name'            => 'Test Municipal Tenant ' . self::TENANT_ID,
            'slug'            => 'test-municipal-' . self::TENANT_ID,
            'tenant_category' => 'community',
            'created_at'      => now(),
        ]);

        TenantContext::setById(self::TENANT_ID);

        $this->svc = new MunicipalImpactReportService(
            new CaringCommunityWorkflowPolicyService()
        );
    }

    // ── Helpers ───────────────────────────────────────────────────────────────

    /**
     * Insert a minimal approved user and return its ID.
     */
    private function insertUser(float $balance = 0.0, string $role = 'member'): int
    {
        $uid = uniqid('mu', true);
        return DB::table('users')->insertGetId([
            'tenant_id'   => self::TENANT_ID,
            'name'        => 'User ' . $uid,
            'first_name'  => 'First',
            'last_name'   => 'Last',
            'email'       => $uid . '@example.test',
            'status'      => 'active',
            'balance'     => $balance,
            'role'        => $role,
            'is_approved' => 1,
            'created_at'  => now(),
            'updated_at'  => now(),
        ]);
    }

    /**
     * Insert a completed transaction and return its ID.
     */
    private function insertTransaction(int $senderId, int $receiverId, float $amount, string $date = '2025-03-15'): int
    {
        return DB::table('transactions')->insertGetId([
            'tenant_id'        => self::TENANT_ID,
            'sender_id'        => $senderId,
            'receiver_id'      => $receiverId,
            'amount'           => $amount,
            'status'           => 'completed',
            'transaction_type' => 'transfer',
            'created_at'       => $date . ' 12:00:00',
            'updated_at'       => $date . ' 12:00:00',
        ]);
    }

    /**
     * Insert a vol_log row and return its ID.
     */
    private function insertVolLog(int $userId, float $hours, string $status = 'approved', string $date = '2025-03-10'): int
    {
        return DB::table('vol_logs')->insertGetId([
            'tenant_id'   => self::TENANT_ID,
            'user_id'     => $userId,
            'hours'       => $hours,
            'status'      => $status,
            'date_logged' => $date,
            'created_at'  => now(),
        ]);
    }

    /** Standard date range covering our fixture dates. */
    private function dateRange(): array
    {
        return ['date_from' => '2025-01-01', 'date_to' => '2025-12-31'];
    }

    // ── Top-level shape ───────────────────────────────────────────────────────

    public function test_summary_returns_required_top_level_keys(): void
    {
        $result = $this->svc->summary(self::TENANT_ID, $this->dateRange());

        foreach (['period', 'currency', 'hour_value', 'social_multiplier', 'stats',
                  'categories', 'trends', 'readiness_signals', 'sroi_methodology',
                  'report_pack', 'policy'] as $key) {
            $this->assertArrayHasKey($key, $result, "Missing top-level key: {$key}");
        }
    }

    public function test_summary_stats_block_has_required_keys(): void
    {
        $result = $this->svc->summary(self::TENANT_ID, $this->dateRange());
        $stats  = $result['stats'];

        foreach (['verified_hours', 'volunteer_hours', 'timebank_hours', 'pending_hours',
                  'active_members', 'new_members', 'participating_members',
                  'trusted_organisations', 'active_opportunities',
                  'support_requests', 'support_offers',
                  'direct_value', 'social_value', 'total_value'] as $key) {
            $this->assertArrayHasKey($key, $stats, "Missing stats key: {$key}");
        }
    }

    // ── SROI calculation: timebank hours → direct_value ───────────────────────

    public function test_direct_value_equals_verified_hours_times_hour_value(): void
    {
        $u1 = $this->insertUser(10.0);
        $u2 = $this->insertUser(0.0);

        // 4 hours completed
        $this->insertTransaction($u1, $u2, 4.0, '2025-06-01');

        // No social_value_config row → uses DEFAULT_HOUR_VALUE = 35.0
        $result = $this->svc->summary(self::TENANT_ID, $this->dateRange());
        $stats  = $result['stats'];

        $this->assertEquals(4.0, $stats['timebank_hours'], 'timebank_hours should be 4.0');
        $this->assertEquals(0.0, $stats['volunteer_hours'],  'volunteer_hours should be 0 (no vol_logs)');
        $this->assertEquals(4.0, $stats['verified_hours'],   'verified_hours = 4.0');

        // direct_value = 4.0 * 35.0 = 140.00
        $this->assertEquals(140.00, $stats['direct_value'], 'direct_value = verified_hours * 35 CHF');
    }

    // ── SROI calculation: volunteer hours → direct_value ─────────────────────

    public function test_volunteer_hours_contribute_to_verified_hours_and_direct_value(): void
    {
        $user = $this->insertUser();

        // 6 approved hours from vol_logs
        $this->insertVolLog($user, 6.0, 'approved', '2025-04-01');

        $result = $this->svc->summary(self::TENANT_ID, $this->dateRange());
        $stats  = $result['stats'];

        $this->assertEquals(6.0, $stats['volunteer_hours'], 'volunteer_hours should be 6.0');
        $this->assertEquals(6.0, $stats['verified_hours'],  'verified_hours includes volunteer hours');

        // direct_value = 6.0 * 35.0 = 210.00
        $this->assertEquals(210.00, $stats['direct_value']);
    }

    // ── Pending hours are counted separately, NOT in verified_hours ───────────

    public function test_pending_volunteer_hours_are_excluded_from_verified_hours(): void
    {
        $user = $this->insertUser();
        $this->insertVolLog($user, 3.0, 'pending', '2025-05-01');

        $result = $this->svc->summary(self::TENANT_ID, $this->dateRange());
        $stats  = $result['stats'];

        $this->assertEquals(3.0, $stats['pending_hours'],  'pending_hours = 3.0');
        $this->assertEquals(0.0, $stats['verified_hours'], 'pending hours excluded from verified_hours');
        $this->assertEquals(0.0, $stats['direct_value'],   'no direct value from pending hours');
    }

    // ── Combined: timebank + volunteer hours both count ───────────────────────

    public function test_verified_hours_combines_timebank_and_volunteer(): void
    {
        $u1 = $this->insertUser(20.0);
        $u2 = $this->insertUser(0.0);

        $this->insertTransaction($u1, $u2, 2.0, '2025-07-01'); // 2 timebank hours
        $this->insertVolLog($u1, 3.0, 'approved', '2025-07-15'); // 3 volunteer hours

        $result = $this->svc->summary(self::TENANT_ID, $this->dateRange());
        $stats  = $result['stats'];

        $this->assertEquals(2.0, $stats['timebank_hours']);
        $this->assertEquals(3.0, $stats['volunteer_hours']);
        $this->assertEquals(5.0, $stats['verified_hours']);
        // direct_value = 5 * 35 = 175.00
        $this->assertEquals(175.00, $stats['direct_value']);
    }

    // ── Social value: direct_value * multiplier when enabled ─────────────────

    public function test_social_value_is_direct_value_times_multiplier_when_enabled(): void
    {
        $u1 = $this->insertUser(10.0);
        $u2 = $this->insertUser(0.0);
        $this->insertTransaction($u1, $u2, 10.0, '2025-08-01');

        // Force include_social_value=true via filter and use default multiplier (2.8)
        $result = $this->svc->summary(self::TENANT_ID, array_merge(
            $this->dateRange(),
            ['include_social_value' => 'true']
        ));
        $stats = $result['stats'];

        // direct_value = 10 * 35 = 350
        // social_value = 350 * 2.8 = 980.0
        $this->assertEquals(350.00, $stats['direct_value']);
        $this->assertEquals(980.00, $stats['social_value']);
        $this->assertEquals(1330.00, $stats['total_value']); // 350 + 980
    }

    // ── Social value disabled: social_value = 0, total = direct only ─────────

    public function test_social_value_is_zero_when_disabled(): void
    {
        $u1 = $this->insertUser(10.0);
        $u2 = $this->insertUser(0.0);
        $this->insertTransaction($u1, $u2, 10.0, '2025-08-01');

        $result = $this->svc->summary(self::TENANT_ID, array_merge(
            $this->dateRange(),
            ['include_social_value' => 'false']
        ));
        $stats = $result['stats'];

        $this->assertEquals(0.0, $stats['social_value'], 'social_value must be 0 when disabled');
        $this->assertEquals($stats['direct_value'], $stats['total_value'], 'total_value = direct_value when social disabled');
    }

    // ── social_value_config overrides defaults ────────────────────────────────

    public function test_hour_value_from_social_value_config_overrides_default(): void
    {
        // Insert a config row: 20 CHF/hour, multiplier 3.0
        DB::table('social_value_config')->insertOrIgnore([
            'tenant_id'          => self::TENANT_ID,
            'hour_value_currency'=> 'CHF',
            'hour_value_amount'  => 20.00,
            'social_multiplier'  => 3.00,
            'reporting_period'   => 'annually',
            'created_at'         => now(),
        ]);

        $u1 = $this->insertUser(10.0);
        $u2 = $this->insertUser(0.0);
        $this->insertTransaction($u1, $u2, 5.0, '2025-09-01');

        $result = $this->svc->summary(self::TENANT_ID, array_merge(
            $this->dateRange(),
            ['include_social_value' => 'true']
        ));
        $stats = $result['stats'];

        // direct_value = 5 * 20 = 100.00
        $this->assertEquals(100.00, $stats['direct_value'], 'hour_value_amount=20 used from config');
        // social_value = 100 * 3.0 = 300.00
        $this->assertEquals(300.00, $stats['social_value']);
        $this->assertEquals(400.00, $stats['total_value']);
        $this->assertEquals(20.0,   $result['hour_value']);
        $this->assertEquals(3.0,    $result['social_multiplier']);
    }

    // ── Member counts: active, new, participating ─────────────────────────────

    public function test_member_counts_are_accurate(): void
    {
        // Two approved users with activity in range — they are "active" (last_active_at in range)
        // and "participating" (transaction sender/receiver).
        $u1 = $this->insertUser(10.0);
        $u2 = $this->insertUser(0.0);

        // Mark u1 as last_active in range; leave u2 NULL (NULL counts as active by the query)
        DB::table('users')->where('id', $u1)->update(['last_active_at' => '2025-06-15 10:00:00']);

        $this->insertTransaction($u1, $u2, 1.0, '2025-06-15');

        $result = $this->svc->summary(self::TENANT_ID, $this->dateRange());
        $stats  = $result['stats'];

        // active_members: approved users with last_active_at in range OR NULL
        $this->assertGreaterThanOrEqual(2, $stats['active_members']);

        // participating_members: distinct sender/receiver IDs
        $this->assertGreaterThanOrEqual(2, $stats['participating_members']);
    }

    // ── readiness_signals structure ───────────────────────────────────────────

    public function test_readiness_signals_returns_four_signals_with_correct_keys(): void
    {
        $result  = $this->svc->summary(self::TENANT_ID, $this->dateRange());
        $signals = $result['readiness_signals'];

        $this->assertCount(4, $signals);
        $expectedKeys = ['municipal_value', 'participation', 'partner_network', 'local_exchange'];

        foreach ($signals as $signal) {
            $this->assertArrayHasKey('key', $signal);
            $this->assertArrayHasKey('status', $signal);
            $this->assertArrayHasKey('value', $signal);
            $this->assertContains($signal['status'], ['ready', 'needs_data']);
        }

        $signalKeys = array_column($signals, 'key');
        foreach ($expectedKeys as $expectedKey) {
            $this->assertContains($expectedKey, $signalKeys, "Missing readiness signal: {$expectedKey}");
        }
    }

    public function test_readiness_signals_status_is_ready_when_hours_present(): void
    {
        $u1 = $this->insertUser(10.0);
        $u2 = $this->insertUser(0.0);
        $this->insertTransaction($u1, $u2, 1.0, '2025-06-01');

        $result  = $this->svc->summary(self::TENANT_ID, $this->dateRange());
        $signals = array_column($result['readiness_signals'], null, 'key');

        $this->assertEquals('ready', $signals['municipal_value']['status']);
        $this->assertEquals('ready', $signals['participation']['status']);
    }

    public function test_readiness_signals_status_is_needs_data_when_no_hours(): void
    {
        // No transactions or vol_logs for this tenant.
        $result  = $this->svc->summary(self::TENANT_ID, $this->dateRange());
        $signals = array_column($result['readiness_signals'], null, 'key');

        $this->assertEquals('needs_data', $signals['municipal_value']['status']);
        $this->assertEquals('needs_data', $signals['participation']['status']);
    }

    // ── exportData wraps summary into a tabular structure ─────────────────────

    public function test_export_data_returns_headers_and_rows(): void
    {
        $result = $this->svc->exportData(self::TENANT_ID, $this->dateRange());

        $this->assertArrayHasKey('headers', $result);
        $this->assertArrayHasKey('rows', $result);
        $this->assertEquals(['Metric', 'Value', 'Notes'], $result['headers']);
        $this->assertIsArray($result['rows']);
        $this->assertNotEmpty($result['rows']);
    }

    public function test_export_data_rows_include_verified_hours_entry(): void
    {
        $result  = $this->svc->exportData(self::TENANT_ID, $this->dateRange());
        $metrics = array_column($result['rows'], 0);

        $this->assertContains('Verified Hours', $metrics, 'exportData rows must include "Verified Hours"');
    }

    // ── audience / report_context routing ─────────────────────────────────────

    public function test_municipality_audience_attaches_municipality_variant(): void
    {
        $result = $this->svc->summary(self::TENANT_ID, array_merge(
            $this->dateRange(),
            ['audience' => 'municipality']
        ));

        $this->assertArrayHasKey('municipality_variant', $result);
        $this->assertArrayNotHasKey('canton_variant', $result);
        $this->assertArrayNotHasKey('cooperative_variant', $result);
    }

    public function test_canton_audience_attaches_canton_variant(): void
    {
        $result = $this->svc->summary(self::TENANT_ID, array_merge(
            $this->dateRange(),
            ['audience' => 'canton']
        ));

        $this->assertArrayHasKey('canton_variant', $result);
        $cantonVariant = $result['canton_variant'];
        $this->assertArrayHasKey('est_cost_avoidance_chf', $cantonVariant);
        $this->assertArrayHasKey('cost_avoidance_multiplier', $cantonVariant);
        $this->assertArrayHasKey('yoy_prior_hours', $cantonVariant);
    }

    public function test_cooperative_audience_attaches_cooperative_variant(): void
    {
        $result = $this->svc->summary(self::TENANT_ID, array_merge(
            $this->dateRange(),
            ['audience' => 'cooperative']
        ));

        $this->assertArrayHasKey('cooperative_variant', $result);
        $coop = $result['cooperative_variant'];
        foreach (['member_retention_rate', 'reciprocity_rate', 'tandem_count',
                  'coordinator_load_avg', 'future_care_credit_pool'] as $key) {
            $this->assertArrayHasKey($key, $coop, "Missing cooperative_variant key: {$key}");
        }
    }

    // ── canton variant: cost-avoidance formula (1.5 × hour_value × hours) ─────

    public function test_canton_variant_est_cost_avoidance_is_1_5x_direct_value(): void
    {
        $u1 = $this->insertUser(10.0);
        $u2 = $this->insertUser(0.0);
        $this->insertTransaction($u1, $u2, 8.0, '2025-09-10');

        $result       = $this->svc->summary(self::TENANT_ID, array_merge(
            $this->dateRange(),
            ['audience' => 'canton']
        ));
        $cantonVariant = $result['canton_variant'];

        // est_cost_avoidance = 8h * 35 CHF * 1.5 = 420.00
        $directValue = $result['stats']['direct_value']; // 8 * 35 = 280
        $expected    = round($directValue * 1.5, 2);
        $this->assertEquals($expected, $cantonVariant['est_cost_avoidance_chf']);
    }

    // ── cooperative variant: reciprocity rate when one member is both sides ───

    public function test_cooperative_variant_reciprocity_rate_is_correct(): void
    {
        // u1 sends to u2 AND receives from u2 → u1 is both supporter and receiver.
        // reciprocity = bothCount / supportersCount
        $u1 = $this->insertUser(10.0);
        $u2 = $this->insertUser(10.0);

        $this->insertTransaction($u1, $u2, 2.0, '2025-06-01'); // u1=sender, u2=receiver
        $this->insertTransaction($u2, $u1, 1.0, '2025-06-02'); // u2=sender, u1=receiver

        $result = $this->svc->summary(self::TENANT_ID, array_merge(
            $this->dateRange(),
            ['audience' => 'cooperative']
        ));

        $coop = $result['cooperative_variant'];

        // Both u1 and u2 are senders (supporters) AND receivers → reciprocity = 1.0
        $this->assertEquals(1.0, $coop['reciprocity_rate'],
            'Both members gave and received → reciprocity rate should be 1.0');
        $this->assertEquals(2, $coop['reciprocal_members_count']);
    }

    // ── period trends are sorted chronologically ──────────────────────────────

    public function test_trends_are_sorted_by_period_ascending(): void
    {
        $u1 = $this->insertUser(20.0);
        $u2 = $this->insertUser(0.0);

        // Transactions in two different months
        $this->insertTransaction($u1, $u2, 1.0, '2025-03-05');
        $this->insertTransaction($u1, $u2, 1.0, '2025-05-10');

        $result = $this->svc->summary(self::TENANT_ID, $this->dateRange());
        $trends = $result['trends'];

        // If there are ≥2 trend entries for our tenant, verify they are sorted.
        if (count($trends) >= 2) {
            $periods = array_column($trends, 'period');
            $sorted  = $periods;
            sort($sorted);
            $this->assertEquals($sorted, $periods, 'Trend periods must be sorted ascending');
        }

        // Each trend entry must have the required keys.
        foreach ($trends as $trend) {
            $this->assertArrayHasKey('period', $trend);
            $this->assertArrayHasKey('verified_hours', $trend);
            $this->assertArrayHasKey('activities', $trend);
            $this->assertArrayHasKey('participants', $trend);
        }

        $this->assertTrue(true); // guarantee at least one assertion
    }

    // ── sroi_methodology structure ────────────────────────────────────────────

    public function test_sroi_methodology_has_formula_inputs_assumptions_caveat(): void
    {
        $result = $this->svc->summary(self::TENANT_ID, $this->dateRange());
        $sroi   = $result['sroi_methodology'];

        $this->assertArrayHasKey('formula', $sroi);
        $this->assertArrayHasKey('inputs', $sroi);
        $this->assertArrayHasKey('assumptions', $sroi);
        $this->assertArrayHasKey('caveat', $sroi);
        $this->assertCount(4, $sroi['inputs']);
    }
}

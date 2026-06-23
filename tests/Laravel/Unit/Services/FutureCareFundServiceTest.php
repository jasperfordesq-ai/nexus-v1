<?php
// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

declare(strict_types=1);

namespace Tests\Laravel\Unit\Services;

use App\Core\TenantContext;
use App\Services\CaringCommunityWorkflowPolicyService;
use App\Services\FutureCareFundService;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Tests\Laravel\TestCase;

/**
 * FutureCareFundServiceTest
 *
 * Tests the "Future Care Fund" summary logic: lifetime given/received hours,
 * CHF value estimate, reciprocity ratio, net balance, by-year rollup,
 * partner-organisations count, and active-months calculation.
 *
 * Data strategy:
 *   - Insert minimal users (no balance required — this service reads vol_logs
 *     and transactions, not wallet balance).
 *   - Insert vol_logs rows for "given" hours (user as volunteer).
 *   - Insert transaction rows for "received" hours (user as sender, spending
 *     time credits to receive care).
 *   - Insert caring_support_relationships + matching vol_logs for the
 *     "received via caring relationship" path.
 *   - All rows are tenant-scoped to tenant 2; DatabaseTransactions rolls them back.
 *
 * The default CHF value from CaringCommunityWorkflowPolicyService is 35 (no
 * tenant_settings rows needed — defaults apply).
 */
class FutureCareFundServiceTest extends TestCase
{
    use DatabaseTransactions;

    private const TENANT_ID = 2;
    private const DEFAULT_HOUR_VALUE_CHF = 35;

    private FutureCareFundService $service;

    protected function setUp(): void
    {
        parent::setUp();
        TenantContext::setById(self::TENANT_ID);
        $this->service = new FutureCareFundService(
            new CaringCommunityWorkflowPolicyService()
        );
    }

    // ─── helpers ──────────────────────────────────────────────────────────────

    private function insertUser(): int
    {
        $uid = uniqid('fcf_', true);
        return DB::table('users')->insertGetId([
            'tenant_id'  => self::TENANT_ID,
            'name'       => 'FCF Test ' . $uid,
            'first_name' => 'FCF',
            'last_name'  => 'Test',
            'email'      => 'fcf.' . $uid . '@example.test',
            'status'     => 'active',
            'balance'    => 0,
            'role'       => 'member',
            'is_approved' => 1,
            'created_at' => now(),
        ]);
    }

    private function insertVolLog(int $userId, float $hours, string $status = 'approved', string $date = '2025-03-15', ?int $orgId = null): int
    {
        return DB::table('vol_logs')->insertGetId([
            'tenant_id'       => self::TENANT_ID,
            'user_id'         => $userId,
            'hours'           => $hours,
            'status'          => $status,
            'date_logged'     => $date,
            'organization_id' => $orgId,
            'created_at'      => now(),
        ]);
    }

    private function insertTransaction(int $senderId, int $receiverId, float $amount, string $status = 'completed', string $createdAt = '2025-04-10 10:00:00'): int
    {
        return DB::table('transactions')->insertGetId([
            'tenant_id'        => self::TENANT_ID,
            'sender_id'        => $senderId,
            'receiver_id'      => $receiverId,
            'amount'           => $amount,
            'status'           => $status,
            'transaction_type' => 'transfer',
            'created_at'       => $createdAt,
            'updated_at'       => $createdAt,
        ]);
    }

    private function insertCaringRelationship(int $supporterId, int $recipientId): int
    {
        return DB::table('caring_support_relationships')->insertGetId([
            'tenant_id'      => self::TENANT_ID,
            'supporter_id'   => $supporterId,
            'recipient_id'   => $recipientId,
            'title'          => 'Test Support Relationship',
            'frequency'      => 'weekly',
            'expected_hours' => 2.00,
            'start_date'     => '2025-01-01',
            'status'         => 'active',
            'created_at'     => now(),
            'updated_at'     => now(),
        ]);
    }

    // ─── test: empty user has all-zero summary ────────────────────────────────

    public function test_summary_returns_all_zeros_for_user_with_no_activity(): void
    {
        $userId  = $this->insertUser();
        $summary = $this->service->summary(self::TENANT_ID, $userId);

        $this->assertSame(0.0, $summary['total_banked_hours']);
        $this->assertSame(0.0, $summary['hours_received']);
        $this->assertSame(0.0, $summary['net_balance']);
        $this->assertSame(0.0, $summary['chf_value_estimate']);
        $this->assertSame(0.0, $summary['lifetime_given']);
        $this->assertSame(0.0, $summary['lifetime_received']);
        $this->assertSame(0.0, $summary['reciprocity_ratio']);
        $this->assertNull($summary['first_contribution_date']);
        $this->assertSame(0, $summary['active_months']);
        $this->assertSame(0, $summary['partner_organisations_helped']);
        $this->assertSame(0.0, $summary['this_month_hours_given']);
        $this->assertSame(0.0, $summary['this_month_hours_received']);
        $this->assertIsArray($summary['by_year']);
        $this->assertEmpty($summary['by_year']);
    }

    // ─── test: lifetime_given aggregates approved vol_logs ───────────────────

    public function test_summary_lifetime_given_sums_approved_vol_logs(): void
    {
        $userId = $this->insertUser();
        $this->insertVolLog($userId, 3.0, 'approved', '2025-02-01');
        $this->insertVolLog($userId, 2.0, 'approved', '2025-03-01');
        // Pending and declined rows must NOT be counted.
        $this->insertVolLog($userId, 10.0, 'pending',  '2025-02-15');
        $this->insertVolLog($userId, 10.0, 'declined', '2025-03-20');

        $summary = $this->service->summary(self::TENANT_ID, $userId);

        $this->assertSame(5.0, $summary['lifetime_given']);
        $this->assertSame(5.0, $summary['total_banked_hours']);
    }

    // ─── test: only tenant-scoped vol_logs count ──────────────────────────────

    public function test_lifetime_given_ignores_other_tenants_vol_logs(): void
    {
        $userId = $this->insertUser();
        // Cross-tenant row (tenant 99) must not be aggregated.
        DB::table('vol_logs')->insert([
            'tenant_id'   => 99,
            'user_id'     => $userId,
            'hours'       => 100.0,
            'status'      => 'approved',
            'date_logged' => '2025-01-01',
            'created_at'  => now(),
        ]);
        $this->insertVolLog($userId, 4.0, 'approved', '2025-06-01');

        $summary = $this->service->summary(self::TENANT_ID, $userId);

        $this->assertSame(4.0, $summary['lifetime_given']);
    }

    // ─── test: lifetime_received sums completed transactions (sender path) ───

    public function test_summary_lifetime_received_sums_completed_transactions(): void
    {
        $userId    = $this->insertUser();
        $otherId   = $this->insertUser();

        $this->insertTransaction($userId, $otherId, 2.0, 'completed');
        $this->insertTransaction($userId, $otherId, 3.0, 'completed');
        // Pending transaction must NOT be counted.
        $this->insertTransaction($userId, $otherId, 50.0, 'pending');

        $summary = $this->service->summary(self::TENANT_ID, $userId);

        $this->assertSame(5.0, $summary['lifetime_received']);
        $this->assertSame(5.0, $summary['hours_received']);
    }

    // ─── test: net_balance = given − received ────────────────────────────────

    public function test_net_balance_equals_given_minus_received(): void
    {
        $userId  = $this->insertUser();
        $otherId = $this->insertUser();

        $this->insertVolLog($userId,  8.0, 'approved', '2025-01-10');
        $this->insertTransaction($userId, $otherId, 3.0, 'completed');

        $summary = $this->service->summary(self::TENANT_ID, $userId);

        $this->assertSame(8.0, $summary['lifetime_given']);
        $this->assertSame(3.0, $summary['lifetime_received']);
        $this->assertSame(5.0, $summary['net_balance']);   // 8 - 3
    }

    // ─── test: CHF value = net_balance × default_hour_value_chf (35) ─────────

    public function test_chf_value_estimate_equals_net_balance_times_hour_value(): void
    {
        $userId  = $this->insertUser();
        $otherId = $this->insertUser();

        $this->insertVolLog($userId,  10.0, 'approved', '2025-01-05');
        $this->insertTransaction($userId, $otherId, 4.0, 'completed');

        $summary = $this->service->summary(self::TENANT_ID, $userId);

        $expectedNet = 6.0;  // 10 - 4
        $expectedChf = round($expectedNet * self::DEFAULT_HOUR_VALUE_CHF, 2);   // 210.0

        $this->assertSame($expectedNet, $summary['net_balance']);
        $this->assertSame($expectedChf, $summary['chf_value_estimate']);
        $this->assertSame(self::DEFAULT_HOUR_VALUE_CHF, $summary['hour_value_chf']);
    }

    // ─── test: chf_value_estimate is negative when received > given ──────────

    public function test_chf_value_estimate_is_negative_when_received_exceeds_given(): void
    {
        $userId  = $this->insertUser();
        $otherId = $this->insertUser();

        $this->insertVolLog($userId,  2.0, 'approved', '2025-03-01');
        $this->insertTransaction($userId, $otherId, 5.0, 'completed');

        $summary = $this->service->summary(self::TENANT_ID, $userId);

        $this->assertSame(-3.0, $summary['net_balance']);
        $this->assertSame(round(-3.0 * self::DEFAULT_HOUR_VALUE_CHF, 2), $summary['chf_value_estimate']);
    }

    // ─── test: reciprocity_ratio = received / given, capped at 2.0 ──────────

    public function test_reciprocity_ratio_equals_received_over_given(): void
    {
        $userId  = $this->insertUser();
        $otherId = $this->insertUser();

        $this->insertVolLog($userId,  10.0, 'approved', '2025-01-01');
        $this->insertTransaction($userId, $otherId, 4.0, 'completed');

        $summary = $this->service->summary(self::TENANT_ID, $userId);

        $expected = round(4.0 / 10.0, 3);  // 0.4
        $this->assertSame($expected, $summary['reciprocity_ratio']);
    }

    public function test_reciprocity_ratio_is_capped_at_two_when_received_far_exceeds_given(): void
    {
        $userId  = $this->insertUser();
        $otherId = $this->insertUser();

        $this->insertVolLog($userId,  1.0, 'approved', '2025-01-01');
        $this->insertTransaction($userId, $otherId, 100.0, 'completed');

        $summary = $this->service->summary(self::TENANT_ID, $userId);

        // min(2.0, received/given) → min(2.0, 100.0) = 2.0
        $this->assertSame(2.0, $summary['reciprocity_ratio']);
    }

    public function test_reciprocity_ratio_is_zero_when_both_given_and_received_are_zero(): void
    {
        $userId  = $this->insertUser();
        $summary = $this->service->summary(self::TENANT_ID, $userId);

        $this->assertSame(0.0, $summary['reciprocity_ratio']);
    }

    public function test_reciprocity_ratio_is_two_when_given_is_zero_but_received_is_positive(): void
    {
        $userId  = $this->insertUser();
        $otherId = $this->insertUser();

        // No vol_logs (given = 0) but received > 0.
        $this->insertTransaction($userId, $otherId, 5.0, 'completed');

        $summary = $this->service->summary(self::TENANT_ID, $userId);

        $this->assertSame(2.0, $summary['reciprocity_ratio']);
    }

    // ─── test: partner organisations helped (distinct org_ids in vol_logs) ───

    public function test_partner_organisations_helped_counts_distinct_organisations(): void
    {
        $ownerId = $this->insertUser();
        $userId  = $this->insertUser();

        // Insert two real vol_organizations rows so FK constraint is satisfied.
        $orgA = DB::table('vol_organizations')->insertGetId([
            'tenant_id'  => self::TENANT_ID,
            'user_id'    => $ownerId,
            'name'       => 'Org A ' . uniqid(),
            'status'     => 'active',
            'created_at' => now(),
        ]);
        $orgB = DB::table('vol_organizations')->insertGetId([
            'tenant_id'  => self::TENANT_ID,
            'user_id'    => $ownerId,
            'name'       => 'Org B ' . uniqid(),
            'status'     => 'active',
            'created_at' => now(),
        ]);
        $orgC = DB::table('vol_organizations')->insertGetId([
            'tenant_id'  => self::TENANT_ID,
            'user_id'    => $ownerId,
            'name'       => 'Org C ' . uniqid(),
            'status'     => 'active',
            'created_at' => now(),
        ]);

        // Two rows for orgA → counts as 1 distinct, one row for orgB → +1.
        $this->insertVolLog($userId, 1.0, 'approved', '2025-01-01', $orgA);
        $this->insertVolLog($userId, 1.0, 'approved', '2025-02-01', $orgA);
        $this->insertVolLog($userId, 1.0, 'approved', '2025-03-01', $orgB);
        // Row without org_id must NOT be counted.
        $this->insertVolLog($userId, 1.0, 'approved', '2025-04-01', null);
        // Declined row for orgC must NOT be counted.
        $this->insertVolLog($userId, 1.0, 'declined', '2025-05-01', $orgC);

        $summary = $this->service->summary(self::TENANT_ID, $userId);

        $this->assertSame(2, $summary['partner_organisations_helped']);
    }

    // ─── test: first_contribution_date is the earliest date across sources ───

    public function test_first_contribution_date_returns_earliest_across_vol_logs_and_transactions(): void
    {
        $userId  = $this->insertUser();
        $otherId = $this->insertUser();

        // vol_log from 2024; transaction from 2025 → expected earliest = 2024.
        $this->insertVolLog($userId, 1.0, 'approved', '2024-06-15');
        $this->insertTransaction($userId, $otherId, 1.0, 'completed', '2025-01-10 08:00:00');

        $summary = $this->service->summary(self::TENANT_ID, $userId);

        $this->assertSame('2024-06-15', $summary['first_contribution_date']);
    }

    // ─── test: by_year rollup aggregates hours per calendar year ─────────────

    public function test_by_year_aggregates_hours_given_and_received_by_year(): void
    {
        $userId  = $this->insertUser();
        $otherId = $this->insertUser();

        // 2024: given 3h
        $this->insertVolLog($userId, 3.0, 'approved', '2024-11-01');
        // 2025: given 5h + received 2h
        $this->insertVolLog($userId, 5.0, 'approved', '2025-04-01');
        $this->insertTransaction($userId, $otherId, 2.0, 'completed', '2025-04-15 09:00:00');

        $summary = $this->service->summary(self::TENANT_ID, $userId);
        $byYear  = $summary['by_year'];

        // Newest year first.
        $yearMap = array_column($byYear, null, 'year');

        $this->assertArrayHasKey(2024, $yearMap);
        $this->assertArrayHasKey(2025, $yearMap);

        $this->assertSame(3.0, $yearMap[2024]['hours_given']);
        $this->assertSame(0.0, $yearMap[2024]['hours_received']);

        $this->assertSame(5.0, $yearMap[2025]['hours_given']);
        $this->assertSame(2.0, $yearMap[2025]['hours_received']);
    }

    // ─── test: received via caring_support_relationship path ─────────────────

    public function test_lifetime_received_includes_caring_support_relationship_vol_logs(): void
    {
        $supporter = $this->insertUser();
        $recipient = $this->insertUser();

        $relId = $this->insertCaringRelationship($supporter, $recipient);

        // Log hours by supporter against the relationship → should appear
        // as "received" for the recipient.
        DB::table('vol_logs')->insert([
            'tenant_id'                      => self::TENANT_ID,
            'user_id'                        => $supporter,
            'caring_support_relationship_id' => $relId,
            'hours'                          => 4.0,
            'status'                         => 'approved',
            'date_logged'                    => '2025-05-10',
            'created_at'                     => now(),
        ]);

        $summary = $this->service->summary(self::TENANT_ID, $recipient);

        // The recipient hasn't given or spent anything — only received via relationship.
        $this->assertSame(4.0, $summary['lifetime_received']);
        $this->assertSame(0.0, $summary['lifetime_given']);
        $this->assertSame(-4.0, $summary['net_balance']);
    }

    // ─── test: decimal precision on fractional hours ─────────────────────────

    public function test_summary_rounds_hours_to_two_decimal_places(): void
    {
        $userId  = $this->insertUser();

        // The vol_logs.hours column is decimal(5,2): MySQL rounds 1.255 → 1.26
        // and 1.245 → 1.25 (banker-rounding may apply in MariaDB), so use
        // values that store exactly at 2dp to avoid DB-level rounding surprises.
        // 1.25 + 1.50 = 2.75 — stored and retrieved exactly.
        $this->insertVolLog($userId, 1.25, 'approved', '2025-01-01');
        $this->insertVolLog($userId, 1.50, 'approved', '2025-01-02');

        $summary = $this->service->summary(self::TENANT_ID, $userId);

        $this->assertSame(2.75, $summary['lifetime_given']);
    }

    // ─── test: active_months is non-negative ─────────────────────────────────

    public function test_active_months_is_non_negative_and_increases_with_older_activity(): void
    {
        $userId  = $this->insertUser();
        $otherId = $this->insertUser();

        // Activity from 2 years ago: active_months should be >= 24.
        $this->insertVolLog($userId, 1.0, 'approved', '2023-01-01');

        $summary = $this->service->summary(self::TENANT_ID, $userId);

        $this->assertGreaterThanOrEqual(24, $summary['active_months']);
    }

    // ─── test: summary keys are all present ──────────────────────────────────

    public function test_summary_contains_all_expected_keys(): void
    {
        $userId  = $this->insertUser();
        $summary = $this->service->summary(self::TENANT_ID, $userId);

        $requiredKeys = [
            'total_banked_hours',
            'hours_received',
            'net_balance',
            'chf_value_estimate',
            'hour_value_chf',
            'lifetime_given',
            'lifetime_received',
            'reciprocity_ratio',
            'first_contribution_date',
            'active_months',
            'partner_organisations_helped',
            'this_month_hours_given',
            'this_month_hours_received',
            'by_year',
        ];

        foreach ($requiredKeys as $key) {
            $this->assertArrayHasKey($key, $summary, "Missing key: {$key}");
        }
    }
}

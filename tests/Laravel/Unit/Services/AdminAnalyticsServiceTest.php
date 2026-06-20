<?php
// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

namespace Tests\Laravel\Unit\Services;

use App\Core\TenantContext;
use App\Models\Transaction;
use App\Models\User;
use App\Services\AdminAnalyticsService;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Tests\Laravel\TestCase;

/**
 * Real-DB tests for AdminAnalyticsService (admin dashboard analytics).
 *
 * Previously seven of eight tests were Mockery / DB::shouldReceive stubs or
 * markTestIncomplete. They asserted nothing about real query behaviour. They
 * are now real assertions against the test DB.
 *
 * Isolation strategy
 * ------------------
 * Tenant 2 (the default test tenant) already carries ~932 users + 10
 * transactions in nexus_test, so absolute counts/sums there are not
 * deterministic. Every test instead creates a brand-new, otherwise-empty
 * tenant inside the surrounding DatabaseTransactions transaction, seeds a
 * known set of users/transactions there, and asserts exact values. The new
 * tenant + all its rows roll back at end of test.
 *
 * Tenant scoping (the #1 false-zero trap)
 * ---------------------------------------
 * User and Transaction both use HasTenantScope -> a global TenantScope that
 * adds `WHERE <table>.tenant_id = TenantContext::getId()`. getDashboard() and
 * getUserStats() run through Eloquent (->newQuery()), so they are filtered by
 * BOTH the passed $tenantId AND the current TenantContext. getOverallStats()
 * and the trend/earner/spender methods read TenantContext::getId() directly.
 * Therefore TenantContext is re-pinned to the isolated tenant immediately
 * before every service call.
 *
 * Whole-hour amounts only: nexus_test stores users.balance and
 * transactions.amount as INT (prod is decimal), so fractional values round
 * and break exact assertions.
 */
class AdminAnalyticsServiceTest extends TestCase
{
    use DatabaseTransactions;

    private AdminAnalyticsService $service;

    /** Isolated, empty tenant id created per test (rolled back). */
    private int $isoTenantId;

    /** Seeded user ids for the isolated tenant. */
    private int $userA;
    private int $userB;
    private int $userC;

    protected function setUp(): void
    {
        parent::setUp();

        // The service is constructed with real Eloquent models (Laravel DI).
        $this->service = new AdminAnalyticsService(new User(), new Transaction());

        $this->seedIsolatedTenant();
    }

    /**
     * Create a fresh empty tenant and a deterministic data set:
     *
     *   users: A (active, balance 10), B (active, balance 5), C (pending, balance 0)
     *          A and B are "active in the last week"; C is not.
     *          All three created just now (within the 30-day window).
     *   transactions (last 30 days): A -> B amount 3, B -> A amount 7
     *
     * Expected derived values (verified against the live service):
     *   total_credits_circulation = 15
     *   transaction_volume_30d    = 10
     *   transaction_count_30d     = 2
     *   new_users_30d             = 3
     *   avg_transaction_size      = 5
     *   active_traders_30d        = 2 (A and B)
     *   by_status                 = [active => 2, pending => 1], total = 3
     *   active_last_week          = 2
     *   top earner                = A (7), then B (3)
     *   top spender               = B (7), then A (3)
     */
    private function seedIsolatedTenant(): void
    {
        // High id far outside any seeded range; lives only inside this txn.
        $this->isoTenantId = 770000 + random_int(1, 9999);

        DB::table('tenants')->insert([
            'id' => $this->isoTenantId,
            'name' => 'Analytics Iso Tenant',
            'slug' => 'analytics-iso-' . $this->isoTenantId,
            'is_active' => true,
            'depth' => 0,
            'allows_subtenants' => false,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $now = now();

        $this->userA = (int) DB::table('users')->insertGetId([
            'tenant_id' => $this->isoTenantId,
            'first_name' => 'Alice',
            'last_name' => 'Earner',
            'name' => 'Alice Earner',
            'email' => 'a' . $this->isoTenantId . '@iso.test',
            'status' => 'active',
            'balance' => 10,
            'created_at' => $now,
            'last_active_at' => $now,
        ]);

        $this->userB = (int) DB::table('users')->insertGetId([
            'tenant_id' => $this->isoTenantId,
            'first_name' => 'Bob',
            'last_name' => 'Spender',
            'name' => 'Bob Spender',
            'email' => 'b' . $this->isoTenantId . '@iso.test',
            'status' => 'active',
            'balance' => 5,
            'created_at' => $now,
            'last_active_at' => $now,
        ]);

        // Pending status, and NOT active in the last week (last_active_at null).
        $this->userC = (int) DB::table('users')->insertGetId([
            'tenant_id' => $this->isoTenantId,
            'first_name' => 'Carol',
            'last_name' => 'Pending',
            'name' => 'Carol Pending',
            'email' => 'c' . $this->isoTenantId . '@iso.test',
            'status' => 'pending',
            'balance' => 0,
            'created_at' => $now,
        ]);

        DB::table('transactions')->insert([
            'tenant_id' => $this->isoTenantId,
            'sender_id' => $this->userA,
            'receiver_id' => $this->userB,
            'amount' => 3,
            'transaction_type' => 'transfer',
            'status' => 'completed',
            'created_at' => $now,
        ]);

        DB::table('transactions')->insert([
            'tenant_id' => $this->isoTenantId,
            'sender_id' => $this->userB,
            'receiver_id' => $this->userA,
            'amount' => 7,
            'transaction_type' => 'transfer',
            'status' => 'completed',
            'created_at' => $now,
        ]);
    }

    /** Re-pin TenantContext to the isolated tenant before a scoped service call. */
    private function pin(): void
    {
        TenantContext::setById($this->isoTenantId);
    }

    // --- getDashboard (Eloquent path: explicit tenant arg + global scope) ---

    public function test_getDashboard_returns_expected_keys_and_values(): void
    {
        $this->pin();
        $result = $this->service->getDashboard($this->isoTenantId);

        $this->assertArrayHasKey('total_credits_circulation', $result);
        $this->assertArrayHasKey('transaction_volume_30d', $result);
        $this->assertArrayHasKey('transaction_count_30d', $result);
        $this->assertArrayHasKey('new_users_30d', $result);
        $this->assertArrayHasKey('avg_transaction_size', $result);

        $this->assertEqualsWithDelta(15.0, (float) $result['total_credits_circulation'], 0.001, 'Sum of all balances (10 + 5 + 0)');
        $this->assertEqualsWithDelta(10.0, (float) $result['transaction_volume_30d'], 0.001, 'Sum of amounts (3 + 7)');
        $this->assertSame(2, $result['transaction_count_30d']);
        $this->assertSame(3, $result['new_users_30d']);
        $this->assertEqualsWithDelta(5.0, (float) $result['avg_transaction_size'], 0.001, 'Mean of 3 and 7');
    }

    // --- getUserStats (Eloquent path: status breakdown + active-last-week) ---

    public function test_getUserStats_returns_status_breakdown(): void
    {
        $this->pin();
        $result = $this->service->getUserStats($this->isoTenantId);

        $this->assertArrayHasKey('total', $result);
        $this->assertArrayHasKey('by_status', $result);
        $this->assertArrayHasKey('active_last_week', $result);

        $this->assertSame(3, $result['total']);
        $this->assertSame(2, (int) $result['by_status']['active']);
        $this->assertSame(1, (int) $result['by_status']['pending']);
        // Only A and B have last_active_at within 7 days; C has none.
        $this->assertSame(2, $result['active_last_week']);
    }

    // --- getOverallStats (raw DB path, reads TenantContext::getId()) ---

    public function test_getOverallStats_returns_expected_structure_and_values(): void
    {
        $this->pin();
        $result = $this->service->getOverallStats();

        foreach ([
            'total_credits_circulation',
            'transaction_volume_30d',
            'transaction_count_30d',
            'active_traders_30d',
            'avg_transaction_size',
            'new_users_30d',
            'pending_abuse_alerts',
        ] as $key) {
            $this->assertArrayHasKey($key, $result);
        }

        $this->assertEqualsWithDelta(15.0, (float) $result['total_credits_circulation'], 0.001);
        $this->assertEqualsWithDelta(10.0, (float) $result['transaction_volume_30d'], 0.001);
        $this->assertSame(2, $result['transaction_count_30d']);
        // Unique senders+receivers in last 30 days = {A, B} = 2.
        $this->assertSame(2, $result['active_traders_30d']);
        $this->assertEqualsWithDelta(5.0, (float) $result['avg_transaction_size'], 0.001);
        $this->assertSame(3, $result['new_users_30d']);
        // No abuse_alerts rows for this fresh tenant.
        $this->assertSame(0, $result['pending_abuse_alerts']);
    }

    public function test_getOverallStats_counts_pending_abuse_alerts(): void
    {
        // Seed one 'new' and one 'reviewing' alert (both counted) plus one
        // 'resolved' (must NOT be counted) for the isolated tenant.
        DB::table('abuse_alerts')->insert([
            'tenant_id' => $this->isoTenantId,
            'alert_type' => 'rapid_transactions',
            'severity' => 'medium',
            'user_id' => $this->userA,
            'status' => 'new',
            'created_at' => now(),
        ]);
        DB::table('abuse_alerts')->insert([
            'tenant_id' => $this->isoTenantId,
            'alert_type' => 'rapid_transactions',
            'severity' => 'high',
            'user_id' => $this->userB,
            'status' => 'reviewing',
            'created_at' => now(),
        ]);
        DB::table('abuse_alerts')->insert([
            'tenant_id' => $this->isoTenantId,
            'alert_type' => 'rapid_transactions',
            'severity' => 'low',
            'user_id' => $this->userA,
            'status' => 'resolved',
            'created_at' => now(),
        ]);

        $this->pin();
        $result = $this->service->getOverallStats();

        $this->assertSame(2, $result['pending_abuse_alerts'], "Only 'new' and 'reviewing' alerts count");
    }

    // --- getMonthlyTrends (raw DB + selectRaw grouping) ---

    public function test_getMonthlyTrends_returns_grouped_rows(): void
    {
        $this->pin();
        $trends = $this->service->getMonthlyTrends(12);

        $this->assertIsArray($trends);
        // Both seeded transactions fall in the current month -> exactly one bucket.
        $this->assertCount(1, $trends);

        $row = $trends[0];
        $this->assertArrayHasKey('month', $row);
        $this->assertSame(2, (int) $row['transaction_count']);
        $this->assertEqualsWithDelta(10.0, (float) $row['total_volume'], 0.001);
        $this->assertSame(2, (int) $row['unique_senders']);
        $this->assertSame(2, (int) $row['unique_receivers']);
    }

    // --- getWeeklyTrends (raw DB + YEARWEEK grouping) ---

    public function test_getWeeklyTrends_returns_grouped_rows(): void
    {
        $this->pin();
        $trends = $this->service->getWeeklyTrends(12);

        $this->assertIsArray($trends);
        // Both seeded transactions fall in the current ISO week -> one bucket.
        $this->assertCount(1, $trends);

        $row = $trends[0];
        $this->assertArrayHasKey('week', $row);
        $this->assertArrayHasKey('week_start', $row);
        $this->assertSame(2, (int) $row['transaction_count']);
        $this->assertEqualsWithDelta(10.0, (float) $row['total_volume'], 0.001);
    }

    // --- getTopEarners (raw DB join on receiver_id) ---

    public function test_getTopEarners_returns_ordered_receivers(): void
    {
        $this->pin();
        $earners = $this->service->getTopEarners(30, 10);

        $this->assertIsArray($earners);
        $this->assertCount(2, $earners);

        // A received 7 (from B), B received 3 (from A); ordered by total desc.
        $this->assertSame($this->userA, (int) $earners[0]['id']);
        $this->assertEqualsWithDelta(7.0, (float) $earners[0]['total_earned'], 0.001);
        $this->assertSame(1, (int) $earners[0]['transaction_count']);

        $this->assertSame($this->userB, (int) $earners[1]['id']);
        $this->assertEqualsWithDelta(3.0, (float) $earners[1]['total_earned'], 0.001);

        foreach (['id', 'first_name', 'last_name', 'email', 'total_earned', 'transaction_count'] as $key) {
            $this->assertArrayHasKey($key, $earners[0]);
        }
    }

    // --- getTopSpenders (raw DB join on sender_id) ---

    public function test_getTopSpenders_returns_ordered_senders(): void
    {
        $this->pin();
        $spenders = $this->service->getTopSpenders(30, 10);

        $this->assertIsArray($spenders);
        $this->assertCount(2, $spenders);

        // B sent 7, A sent 3; ordered by total spent desc.
        $this->assertSame($this->userB, (int) $spenders[0]['id']);
        $this->assertEqualsWithDelta(7.0, (float) $spenders[0]['total_spent'], 0.001);
        $this->assertSame(1, (int) $spenders[0]['transaction_count']);

        $this->assertSame($this->userA, (int) $spenders[1]['id']);
        $this->assertEqualsWithDelta(3.0, (float) $spenders[1]['total_spent'], 0.001);

        foreach (['id', 'first_name', 'last_name', 'email', 'total_spent', 'transaction_count'] as $key) {
            $this->assertArrayHasKey($key, $spenders[0]);
        }
    }
}

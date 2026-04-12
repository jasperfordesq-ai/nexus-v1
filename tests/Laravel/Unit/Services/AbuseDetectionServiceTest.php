<?php
// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

namespace Tests\Laravel\Unit\Services;

use Tests\Laravel\TestCase;
use App\Services\AbuseDetectionService;
use App\Core\TenantContext;
use App\Models\AbuseAlert;
use App\Models\User;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Mockery;

class AbuseDetectionServiceTest extends TestCase
{
    use DatabaseTransactions;

    private AbuseDetectionService $service;

    protected function setUp(): void
    {
        parent::setUp();
        $this->service = new AbuseDetectionService();
    }

    // ── Constants ────────────────────────────────────────────────────

    public function test_constants_have_expected_values(): void
    {
        $this->assertSame(50, AbuseDetectionService::LARGE_TRANSFER_THRESHOLD);
        $this->assertSame(10, AbuseDetectionService::HIGH_VELOCITY_THRESHOLD);
        $this->assertSame(24, AbuseDetectionService::CIRCULAR_WINDOW_HOURS);
        $this->assertSame(90, AbuseDetectionService::INACTIVE_DAYS_THRESHOLD);
        $this->assertSame(10, AbuseDetectionService::HIGH_BALANCE_THRESHOLD);
    }

    // ── runAllChecks ─────────────────────────────────────────────────

    public function test_runAllChecks_returns_array_with_all_check_keys(): void
    {
        // Use a fresh tenant ID with no user/transaction fixtures so every
        // check returns 0 without needing to mock DB.
        $emptyTenant = 99001;
        DB::table('tenants')->insertOrIgnore([
            'id' => $emptyTenant, 'name' => 'Empty', 'slug' => 'test-99001',
            'is_active' => true, 'depth' => 0, 'allows_subtenants' => false,
            'created_at' => now(), 'updated_at' => now(),
        ]);
        TenantContext::setById($emptyTenant);

        $result = $this->service->runAllChecks();

        $this->assertArrayHasKey('large_transfers', $result);
        $this->assertArrayHasKey('high_velocity', $result);
        $this->assertArrayHasKey('circular_transfers', $result);
        $this->assertArrayHasKey('inactive_high_balance', $result);
        $this->assertSame(0, $result['large_transfers']);
        $this->assertSame(0, $result['high_velocity']);
        $this->assertSame(0, $result['circular_transfers']);
        $this->assertSame(0, $result['inactive_high_balance']);
    }

    // ── N+1 regression & tenant isolation (real DB) ──────────────────

    /**
     * Regression test for the N+1 fix in checkHighVelocity:
     * when no users match, service must still return 0 without calling
     * User::whereIn with an empty array (which the fix explicitly guards).
     * Uses empty tenant 999.
     */
    public function test_checkHighVelocity_returns_zero_for_empty_tenant_without_n_plus_1(): void
    {
        $emptyTenant = 99002;
        DB::table('tenants')->insertOrIgnore([
            'id' => $emptyTenant, 'name' => 'Empty', 'slug' => 'test-99002',
            'is_active' => true, 'depth' => 0, 'allows_subtenants' => false,
            'created_at' => now(), 'updated_at' => now(),
        ]);
        TenantContext::setById($emptyTenant);
        $this->assertSame(0, $this->service->checkHighVelocity());
    }

    /**
     * Tenant isolation — circular transfer check should only see the
     * configured tenant's data, regardless of other tenants' activity.
     */
    public function test_checkCircularTransfers_scopes_queries_to_active_tenant(): void
    {
        $tenantA = 99003;
        $tenantB = 99004;
        DB::table('tenants')->insertOrIgnore([
            'id' => $tenantA, 'name' => 'A', 'slug' => 'test-99003',
            'is_active' => true, 'depth' => 0, 'allows_subtenants' => false,
            'created_at' => now(), 'updated_at' => now(),
        ]);
        DB::table('tenants')->insertOrIgnore([
            'id' => $tenantB, 'name' => 'B', 'slug' => 'test-99004',
            'is_active' => true, 'depth' => 0, 'allows_subtenants' => false,
            'created_at' => now(), 'updated_at' => now(),
        ]);

        $alice = DB::table('users')->insertGetId([
            'tenant_id' => $tenantB, 'email' => 'alice'.uniqid().'@t.test',
            'name' => 'Alice', 'first_name' => 'Alice', 'last_name' => 'A',
            'created_at' => now(), 'updated_at' => now(),
        ]);
        $bob = DB::table('users')->insertGetId([
            'tenant_id' => $tenantB, 'email' => 'bob'.uniqid().'@t.test',
            'name' => 'Bob', 'first_name' => 'Bob', 'last_name' => 'B',
            'created_at' => now(), 'updated_at' => now(),
        ]);

        // Circular transfers exist in tenant B
        DB::table('transactions')->insert([
            'tenant_id' => $tenantB, 'sender_id' => $alice, 'receiver_id' => $bob,
            'amount' => 5, 'created_at' => now()->subMinutes(10), 'updated_at' => now(),
        ]);
        DB::table('transactions')->insert([
            'tenant_id' => $tenantB, 'sender_id' => $bob, 'receiver_id' => $alice,
            'amount' => 5, 'created_at' => now()->subMinutes(5), 'updated_at' => now(),
        ]);

        // But we're checking tenant A — should find 0
        TenantContext::setById($tenantA);
        $created = $this->service->checkCircularTransfers();
        $this->assertSame(0, $created, 'Tenant A check must not see tenant B circular transfers');
    }

    // ── checkLargeTransfers ──────────────────────────────────────────

    public function test_checkLargeTransfers_returns_zero_when_no_large_transactions(): void
    {
        DB::shouldReceive('table')->andReturnSelf();
        DB::shouldReceive('join')->andReturnSelf();
        DB::shouldReceive('where')->andReturnSelf();
        DB::shouldReceive('whereNotIn')->andReturnSelf();
        DB::shouldReceive('select')->andReturnSelf();
        DB::shouldReceive('get')->andReturn(collect([]));
        DB::shouldReceive('raw')->andReturn('');

        $result = $this->service->checkLargeTransfers();
        $this->assertSame(0, $result);
    }

    public function test_checkLargeTransfers_respects_custom_threshold(): void
    {
        DB::shouldReceive('table')->andReturnSelf();
        DB::shouldReceive('join')->andReturnSelf();
        DB::shouldReceive('where')->andReturnSelf();
        DB::shouldReceive('whereNotIn')->andReturnSelf();
        DB::shouldReceive('select')->andReturnSelf();
        DB::shouldReceive('get')->andReturn(collect([]));
        DB::shouldReceive('raw')->andReturn('');

        $result = $this->service->checkLargeTransfers(100);
        $this->assertSame(0, $result);
    }

    // ── checkHighVelocity ────────────────────────────────────────────

    public function test_checkHighVelocity_returns_zero_when_no_high_velocity_users(): void
    {
        DB::shouldReceive('select')->andReturn([]);

        $result = $this->service->checkHighVelocity();
        $this->assertSame(0, $result);
    }

    // ── checkCircularTransfers ───────────────────────────────────────

    public function test_checkCircularTransfers_returns_zero_when_no_circular_transfers(): void
    {
        DB::shouldReceive('select')->andReturn([]);

        $result = $this->service->checkCircularTransfers();
        $this->assertSame(0, $result);
    }

    public function test_checkCircularTransfers_accepts_custom_window(): void
    {
        DB::shouldReceive('select')->andReturn([]);

        $result = $this->service->checkCircularTransfers(48);
        $this->assertSame(0, $result);
    }

    // ── checkInactiveHighBalances ────────────────────────────────────

    public function test_checkInactiveHighBalances_returns_zero_when_no_inactive_users(): void
    {
        DB::shouldReceive('select')->andReturn([]);

        $result = $this->service->checkInactiveHighBalances();
        $this->assertSame(0, $result);
    }

    // ── createAlert ──────────────────────────────────────────────────

    public function test_createAlert_creates_abuse_alert_and_returns_id(): void
    {
        $this->markTestIncomplete('Requires integration test — Eloquent models cannot use shouldReceive()');
    }

    // ── updateAlertStatus ────────────────────────────────────────────

    public function test_updateAlertStatus_returns_false_when_alert_not_found(): void
    {
        $this->markTestIncomplete('Requires integration test — Eloquent models cannot use shouldReceive()');
    }

    public function test_updateAlertStatus_sets_resolved_at_for_resolved_status(): void
    {
        $this->markTestIncomplete('Requires integration test — Eloquent models cannot use shouldReceive()');
    }

    public function test_updateAlertStatus_nulls_resolved_at_for_non_terminal_status(): void
    {
        $this->markTestIncomplete('Requires integration test — Eloquent models cannot use shouldReceive()');
    }

    public function test_updateAlertStatus_sets_resolved_at_for_dismissed_status(): void
    {
        $this->markTestIncomplete('Requires integration test — Eloquent models cannot use shouldReceive()');
    }
}

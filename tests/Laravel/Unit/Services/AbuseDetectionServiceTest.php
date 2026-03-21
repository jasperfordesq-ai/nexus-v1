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
use Illuminate\Support\Facades\DB;
use Mockery;

class AbuseDetectionServiceTest extends TestCase
{
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
        DB::shouldReceive('table')->andReturnSelf();
        DB::shouldReceive('join')->andReturnSelf();
        DB::shouldReceive('where')->andReturnSelf();
        DB::shouldReceive('whereNotIn')->andReturnSelf();
        DB::shouldReceive('select')->andReturnSelf();
        DB::shouldReceive('get')->andReturn(collect([]));
        DB::shouldReceive('raw')->andReturn('');
        DB::shouldReceive('selectRaw')->andReturnSelf();
        DB::shouldReceive('groupBy')->andReturnSelf();
        DB::shouldReceive('orderBy')->andReturnSelf();
        DB::shouldReceive('limit')->andReturnSelf();
        DB::shouldReceive('pluck')->andReturn(collect([]));

        // Mock the raw selects that return empty arrays
        DB::shouldReceive('select')->andReturn([]);

        $result = $this->service->runAllChecks();

        $this->assertArrayHasKey('large_transfers', $result);
        $this->assertArrayHasKey('high_velocity', $result);
        $this->assertArrayHasKey('circular_transfers', $result);
        $this->assertArrayHasKey('inactive_high_balance', $result);
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

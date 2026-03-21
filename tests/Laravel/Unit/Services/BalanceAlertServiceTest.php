<?php
// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

namespace Tests\Laravel\Unit\Services;

use Tests\Laravel\TestCase;
use App\Services\BalanceAlertService;
use Illuminate\Support\Facades\DB;

class BalanceAlertServiceTest extends TestCase
{
    private BalanceAlertService $service;

    protected function setUp(): void
    {
        parent::setUp();
        $this->service = new BalanceAlertService();
    }

    public function test_constants_have_expected_values(): void
    {
        $this->assertSame(50, BalanceAlertService::DEFAULT_LOW_BALANCE_THRESHOLD);
        $this->assertSame(10, BalanceAlertService::DEFAULT_CRITICAL_BALANCE_THRESHOLD);
    }

    public function test_getThresholds_returns_defaults_when_no_settings(): void
    {
        DB::shouldReceive('table')->with('org_alert_settings')->andReturnSelf();
        DB::shouldReceive('where')->andReturnSelf();
        DB::shouldReceive('first')->andReturnNull();

        $result = $this->service->getThresholds(1);

        $this->assertSame(50.0, $result['low']);
        $this->assertSame(10.0, $result['critical']);
    }

    public function test_getThresholds_returns_custom_values(): void
    {
        DB::shouldReceive('table')->with('org_alert_settings')->andReturnSelf();
        DB::shouldReceive('where')->andReturnSelf();
        DB::shouldReceive('first')->andReturn((object) [
            'low_balance_threshold' => 100,
            'critical_balance_threshold' => 25,
        ]);

        $result = $this->service->getThresholds(1);

        $this->assertSame(100.0, $result['low']);
        $this->assertSame(25.0, $result['critical']);
    }

    public function test_areAlertsEnabled_returns_true_by_default(): void
    {
        DB::shouldReceive('table')->with('org_alert_settings')->andReturnSelf();
        DB::shouldReceive('where')->andReturnSelf();
        DB::shouldReceive('first')->andReturnNull();

        $this->assertTrue($this->service->areAlertsEnabled(1));
    }

    public function test_areAlertsEnabled_returns_false_when_disabled(): void
    {
        DB::shouldReceive('table')->with('org_alert_settings')->andReturnSelf();
        DB::shouldReceive('where')->andReturnSelf();
        DB::shouldReceive('first')->andReturn((object) ['alerts_enabled' => 0]);

        $this->assertFalse($this->service->areAlertsEnabled(1));
    }

    public function test_setThresholds_upserts_and_returns_true(): void
    {
        DB::shouldReceive('table')->with('org_alert_settings')->andReturnSelf();
        DB::shouldReceive('updateOrInsert')->once()->andReturn(true);

        $result = $this->service->setThresholds(1, 75.0, 15.0);
        $this->assertTrue($result);
    }

    public function test_checkBalance_returns_expected_structure(): void
    {
        // Mock getThresholds
        DB::shouldReceive('table')->with('org_alert_settings')->andReturnSelf();
        DB::shouldReceive('where')->andReturnSelf();
        DB::shouldReceive('first')->andReturnNull();

        // Mock hasAlertedToday
        DB::shouldReceive('table')->with('org_balance_alerts')->andReturnSelf();
        DB::shouldReceive('whereDate')->andReturnSelf();
        DB::shouldReceive('pluck')->andReturn(collect([]));
        DB::shouldReceive('toArray')->andReturn([]);
        DB::shouldReceive('raw')->andReturn('');

        $result = $this->service->checkBalance(1, 100.0, 'Test Org');

        $this->assertArrayHasKey('balance', $result);
        $this->assertArrayHasKey('thresholds', $result);
        $this->assertArrayHasKey('alert_type', $result);
        $this->assertArrayHasKey('alert_sent', $result);
        $this->assertSame(100.0, $result['balance']);
        $this->assertNull($result['alert_type']);
        $this->assertFalse($result['alert_sent']);
    }

    public function test_checkAllBalances_returns_integer(): void
    {
        $this->markTestIncomplete('Requires integration test — complex join queries with subqueries');
    }
}

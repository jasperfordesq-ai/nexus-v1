<?php
// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

namespace Tests\Laravel\Unit\Services;

use Tests\Laravel\TestCase;
use App\Services\SocialValueService;
use Illuminate\Support\Facades\DB;

class SocialValueServiceTest extends TestCase
{
    private SocialValueService $service;

    protected function setUp(): void
    {
        parent::setUp();
        $this->service = new SocialValueService();
    }

    // ── getConfig ──

    public function test_getConfig_returns_defaults_when_no_record(): void
    {
        DB::shouldReceive('table->where->first')->andReturnNull();

        $config = $this->service->getConfig($this->testTenantId);
        $this->assertEquals('GBP', $config['hour_value_currency']);
        $this->assertEquals(15.00, $config['hour_value_amount']);
        $this->assertEquals(3.50, $config['social_multiplier']);
        $this->assertEquals('annually', $config['reporting_period']);
    }

    public function test_getConfig_returns_custom_values(): void
    {
        $row = (object) [
            'hour_value_currency' => 'EUR',
            'hour_value_amount' => 20.00,
            'social_multiplier' => 4.00,
            'reporting_period' => 'quarterly',
        ];
        DB::shouldReceive('table->where->first')->andReturn($row);

        $config = $this->service->getConfig($this->testTenantId);
        $this->assertEquals('EUR', $config['hour_value_currency']);
        $this->assertEquals(20.00, $config['hour_value_amount']);
    }

    // ── saveConfig ──

    public function test_saveConfig_creates_new_record(): void
    {
        DB::shouldReceive('table->where->exists')->andReturn(false);
        DB::shouldReceive('table->insert')->once();

        $result = $this->service->saveConfig($this->testTenantId, [
            'hour_value_currency' => 'USD',
            'hour_value_amount' => 25.00,
        ]);
        $this->assertTrue($result);
    }

    public function test_saveConfig_updates_existing_record(): void
    {
        DB::shouldReceive('table->where->exists')->andReturn(true);
        DB::shouldReceive('table->where->update')->once();

        $result = $this->service->saveConfig($this->testTenantId, [
            'social_multiplier' => 5.0,
        ]);
        $this->assertTrue($result);
    }

    // ── calculateSROI ──

    public function test_calculateSROI_returns_expected_structure(): void
    {
        DB::shouldReceive('table->where->first')->andReturnNull();
        DB::shouldReceive('selectOne')->andReturn(
            (object) ['total_hours' => 100, 'total_transactions' => 50],
            (object) ['active_members' => 20],
            (object) ['total_events' => 5, 'event_hours' => 30],
            (object) ['total_listings' => 40],
        );
        DB::shouldReceive('select')->andReturn([], []);

        $result = $this->service->calculateSROI($this->testTenantId);
        $this->assertArrayHasKey('config', $result);
        $this->assertArrayHasKey('summary', $result);
        $this->assertArrayHasKey('categories', $result);
        $this->assertArrayHasKey('monthly_trend', $result);

        $summary = $result['summary'];
        $this->assertEquals(100.0, $summary['total_hours']);
        $this->assertEquals(1500.0, $summary['direct_value']); // 100 * 15
        $this->assertEquals(5250.0, $summary['social_value']); // 1500 * 3.5
    }
}

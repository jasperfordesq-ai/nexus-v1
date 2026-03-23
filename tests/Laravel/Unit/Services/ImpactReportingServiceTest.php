<?php
// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

namespace Tests\Laravel\Unit\Services;

use App\Services\ImpactReportingService;
use Illuminate\Support\Facades\DB;
use Tests\Laravel\TestCase;

class ImpactReportingServiceTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        // All service methods use DB::raw() for aggregate SQL expressions
        DB::shouldReceive('raw')->andReturnUsing(fn ($v) => new \Illuminate\Database\Query\Expression($v));
    }

    public function test_calculateSROI_returns_expected_structure(): void
    {
        DB::shouldReceive('table')->with('transactions')->andReturnSelf();
        DB::shouldReceive('where')->andReturnSelf();
        DB::shouldReceive('select')->andReturnSelf();
        DB::shouldReceive('first')->andReturn((object) [
            'total_hours' => 100,
            'total_transactions' => 50,
            'unique_givers' => 20,
            'unique_receivers' => 25,
        ]);

        $result = ImpactReportingService::calculateSROI();

        $this->assertArrayHasKey('total_hours', $result);
        $this->assertArrayHasKey('monetary_value', $result);
        $this->assertArrayHasKey('social_value', $result);
        $this->assertArrayHasKey('sroi_ratio', $result);
        $this->assertSame(100.0, $result['total_hours']);
        $this->assertSame(1500.0, $result['monetary_value']); // 100 * 15
        $this->assertSame(5250.0, $result['social_value']); // 1500 * 3.5
        $this->assertSame(3.5, $result['sroi_ratio']);
    }

    public function test_calculateSROI_custom_config(): void
    {
        DB::shouldReceive('table')->andReturnSelf();
        DB::shouldReceive('where')->andReturnSelf();
        DB::shouldReceive('select')->andReturnSelf();
        DB::shouldReceive('first')->andReturn((object) [
            'total_hours' => 50,
            'total_transactions' => 10,
            'unique_givers' => 5,
            'unique_receivers' => 5,
        ]);

        $result = ImpactReportingService::calculateSROI([
            'months' => 6,
            'hourly_value' => 20,
            'social_multiplier' => 4,
        ]);

        $this->assertSame(50.0, $result['total_hours']);
        $this->assertSame(1000.0, $result['monetary_value']); // 50 * 20
        $this->assertSame(4000.0, $result['social_value']); // 1000 * 4
        $this->assertSame(6, $result['period_months']);
    }

    public function test_calculateSROI_zero_hours(): void
    {
        DB::shouldReceive('table')->andReturnSelf();
        DB::shouldReceive('where')->andReturnSelf();
        DB::shouldReceive('select')->andReturnSelf();
        DB::shouldReceive('first')->andReturn((object) [
            'total_hours' => 0, 'total_transactions' => 0,
            'unique_givers' => 0, 'unique_receivers' => 0,
        ]);

        $result = ImpactReportingService::calculateSROI();
        $this->assertSame(0, $result['sroi_ratio']);
    }

    public function test_getImpactTimeline_returns_monthly_data(): void
    {
        DB::shouldReceive('table')->with('transactions')->andReturnSelf();
        DB::shouldReceive('where')->andReturnSelf();
        DB::shouldReceive('select')->andReturnSelf();
        DB::shouldReceive('groupBy')->andReturnSelf();
        DB::shouldReceive('orderBy')->andReturnSelf();
        DB::shouldReceive('get')->andReturn(collect([
            (object) ['month' => '2026-01', 'hours_exchanged' => 10, 'transactions' => 5],
        ]));

        DB::shouldReceive('table')->with('users')->andReturnSelf();
        DB::shouldReceive('where')->andReturnSelf();
        DB::shouldReceive('select')->andReturnSelf();
        DB::shouldReceive('groupBy')->andReturnSelf();
        DB::shouldReceive('pluck')->andReturn(collect(['2026-01' => 3]));

        $result = ImpactReportingService::getImpactTimeline(12);

        $this->assertIsArray($result);
        $this->assertCount(1, $result);
        $this->assertSame('2026-01', $result[0]['month']);
        $this->assertSame(3, $result[0]['new_users']);
    }

    public function test_getReportConfig_returns_defaults_when_no_config(): void
    {
        DB::shouldReceive('table')->with('tenants')->andReturnSelf();
        DB::shouldReceive('where')->andReturnSelf();
        DB::shouldReceive('select')->andReturnSelf();
        DB::shouldReceive('first')->andReturn((object) [
            'name' => 'Hour Timebank',
            'slug' => 'hour-timebank',
            'configuration' => null,
        ]);

        $result = ImpactReportingService::getReportConfig();

        $this->assertSame('Hour Timebank', $result['tenant_name']);
        $this->assertSame(15.0, $result['hourly_value']);
        $this->assertSame(3.5, $result['social_multiplier']);
    }
}

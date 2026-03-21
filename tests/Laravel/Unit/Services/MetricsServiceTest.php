<?php
// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

namespace Tests\Laravel\Unit\Services;

use App\Services\MetricsService;
use Illuminate\Support\Facades\DB;
use Tests\Laravel\TestCase;

class MetricsServiceTest extends TestCase
{
    private MetricsService $service;

    protected function setUp(): void
    {
        parent::setUp();
        $this->service = new MetricsService();
    }

    public function test_store_inserts_metric(): void
    {
        DB::shouldReceive('table')->with('metrics')->andReturnSelf();
        DB::shouldReceive('insert')->once()->with(\Mockery::on(function ($data) {
            return $data['name'] === 'page_load_time'
                && $data['value'] === 1.5
                && $data['tags'] === '{"page":"home"}';
        }))->andReturn(true);

        $this->service->store('page_load_time', 1.5, ['page' => 'home']);
    }

    public function test_store_null_tags_when_empty(): void
    {
        DB::shouldReceive('table')->with('metrics')->andReturnSelf();
        DB::shouldReceive('insert')->once()->with(\Mockery::on(function ($data) {
            return $data['tags'] === null;
        }))->andReturn(true);

        $this->service->store('counter', 1.0);
    }

    public function test_getSummary_returns_aggregates(): void
    {
        DB::shouldReceive('table')->with('metrics')->andReturnSelf();
        DB::shouldReceive('where')->andReturnSelf();
        DB::shouldReceive('selectRaw')->andReturnSelf();
        DB::shouldReceive('first')->andReturn((object) [
            'count' => 10, 'sum' => 50.0, 'avg' => 5.0, 'min' => 1.0, 'max' => 10.0,
        ]);

        $result = $this->service->getSummary('page_load_time');

        $this->assertSame(10, $result['count']);
        $this->assertSame(50.0, $result['sum']);
        $this->assertSame(5.0, $result['avg']);
    }

    public function test_getSummary_with_date_range(): void
    {
        DB::shouldReceive('table')->with('metrics')->andReturnSelf();
        DB::shouldReceive('where')->andReturnSelf();
        DB::shouldReceive('selectRaw')->andReturnSelf();
        DB::shouldReceive('first')->andReturn((object) [
            'count' => 5, 'sum' => 25.0, 'avg' => 5.0, 'min' => 3.0, 'max' => 7.0,
        ]);

        $result = $this->service->getSummary('metric', '2026-01-01', '2026-03-01');
        $this->assertSame(5, $result['count']);
    }

    public function test_getTimeSeries_returns_recent_values(): void
    {
        DB::shouldReceive('table')->with('metrics')->andReturnSelf();
        DB::shouldReceive('where')->andReturnSelf();
        DB::shouldReceive('orderByDesc')->andReturnSelf();
        DB::shouldReceive('limit')->andReturnSelf();
        DB::shouldReceive('get')->andReturn(collect([
            (object) ['name' => 'metric', 'value' => 1.0, 'recorded_at' => '2026-03-21'],
        ]));

        $result = $this->service->getTimeSeries('metric', 10);
        $this->assertCount(1, $result);
    }
}

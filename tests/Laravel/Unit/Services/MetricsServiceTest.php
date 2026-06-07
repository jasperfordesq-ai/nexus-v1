<?php
// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

namespace Tests\Laravel\Unit\Services;

use App\Services\MetricsService;
use Illuminate\Support\Facades\DB;
use Tests\Laravel\TestCase;

/**
 * MetricsService unit tests.
 *
 * The service API changed during the Laravel migration: every method is now
 * tenant-scoped (first argument is int $tenantId) and rows are persisted to the
 * `metrics` table as `event` + JSON `data` columns rather than the legacy
 * name/value/tags columns. store() is a thin wrapper around record().
 */
class MetricsServiceTest extends TestCase
{
    private MetricsService $service;

    private int $tenantId = 2;

    protected function setUp(): void
    {
        parent::setUp();
        $this->service = new MetricsService();
    }

    public function test_record_inserts_event_row(): void
    {
        DB::shouldReceive('table')->with('metrics')->andReturnSelf();
        DB::shouldReceive('insert')->once()->with(\Mockery::on(function ($data) {
            return $data['tenant_id'] === $this->tenantId
                && $data['event'] === 'page_load'
                && $data['data'] === '{"page":"home"}';
        }))->andReturn(true);

        $this->service->record($this->tenantId, 'page_load', ['page' => 'home']);
    }

    public function test_record_null_data_when_empty(): void
    {
        DB::shouldReceive('table')->with('metrics')->andReturnSelf();
        DB::shouldReceive('insert')->once()->with(\Mockery::on(function ($data) {
            return $data['data'] === null;
        }))->andReturn(true);

        $this->service->record($this->tenantId, 'counter');
    }

    public function test_store_wraps_value_and_tags_into_record(): void
    {
        DB::shouldReceive('table')->with('metrics')->andReturnSelf();
        DB::shouldReceive('insert')->once()->with(\Mockery::on(function ($data) {
            return $data['tenant_id'] === $this->tenantId
                && $data['event'] === 'page_load_time'
                && $data['data'] === '{"value":1.5,"tags":{"page":"home"}}';
        }))->andReturn(true);

        $this->service->store($this->tenantId, 'page_load_time', 1.5, ['page' => 'home']);
    }

    public function test_getSummary_returns_aggregates(): void
    {
        DB::shouldReceive('table')->with('metrics')->andReturnSelf();
        DB::shouldReceive('where')->andReturnSelf();
        DB::shouldReceive('whereBetween')->andReturnSelf();
        DB::shouldReceive('count')->andReturn(10);
        DB::shouldReceive('select')->andReturnSelf();
        DB::shouldReceive('raw')->andReturn('COUNT(*) as count');
        DB::shouldReceive('groupBy')->andReturnSelf();
        DB::shouldReceive('orderByDesc')->andReturnSelf();
        DB::shouldReceive('limit')->andReturnSelf();
        DB::shouldReceive('pluck')->andReturn(collect(['page_load' => 10]));

        $result = $this->service->getSummary($this->tenantId, 'week');

        $this->assertSame('week', $result['period']);
        $this->assertSame(10, $result['total_events']);
        $this->assertArrayHasKey('events_by_type', $result);
        $this->assertArrayHasKey('start', $result);
        $this->assertArrayHasKey('end', $result);
    }

    public function test_getTimeSeries_returns_recent_values(): void
    {
        DB::shouldReceive('table')->with('metrics')->andReturnSelf();
        DB::shouldReceive('where')->andReturnSelf();
        DB::shouldReceive('orderByDesc')->andReturnSelf();
        DB::shouldReceive('limit')->andReturnSelf();
        DB::shouldReceive('get')->andReturn(collect([
            (object) ['event' => 'page_load', 'data' => null, 'created_at' => '2026-03-21'],
        ]));

        $result = $this->service->getTimeSeries($this->tenantId, 'page_load', 10);
        $this->assertCount(1, $result);
        $this->assertSame('page_load', $result[0]['event']);
    }
}

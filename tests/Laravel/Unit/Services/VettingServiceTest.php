<?php
// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

namespace Tests\Laravel\Unit\Services;

use Tests\Laravel\TestCase;
use App\Services\VettingService;
use App\Core\TenantContext;
use Illuminate\Support\Facades\DB;

class VettingServiceTest extends TestCase
{
    private VettingService $service;

    protected function setUp(): void
    {
        parent::setUp();
        $this->service = new VettingService();
    }

    public function test_getUserRecords_returns_empty_on_error(): void
    {
        DB::shouldReceive('table')->andThrow(new \Exception('DB error'));

        $result = $this->service->getUserRecords(1);
        $this->assertEmpty($result);
    }

    public function test_getById_returns_null_when_not_found(): void
    {
        DB::shouldReceive('table')->with('vetting_records')->andReturnSelf();
        DB::shouldReceive('join')->andReturnSelf();
        DB::shouldReceive('where')->andReturnSelf();
        DB::shouldReceive('select')->andReturnSelf();
        DB::shouldReceive('first')->andReturn(null);

        $this->assertNull($this->service->getById(999));
    }

    public function test_getAll_returns_expected_structure(): void
    {
        $query = DB::shouldReceive('table')->with('vetting_records')->andReturnSelf();
        DB::shouldReceive('join')->andReturnSelf();
        DB::shouldReceive('where')->andReturnSelf();
        DB::shouldReceive('select')->andReturnSelf();
        DB::shouldReceive('count')->andReturn(0);
        DB::shouldReceive('orderByDesc')->andReturnSelf();
        DB::shouldReceive('limit')->andReturnSelf();
        DB::shouldReceive('offset')->andReturnSelf();
        DB::shouldReceive('get')->andReturn(collect([]));

        $result = $this->service->getAll();

        $this->assertArrayHasKey('data', $result);
        $this->assertArrayHasKey('pagination', $result);
        $this->assertEquals(0, $result['pagination']['total']);
    }

    public function test_getStats_returns_expected_keys(): void
    {
        DB::shouldReceive('table')->with('vetting_records')->andReturnSelf();
        DB::shouldReceive('where')->andReturnSelf();
        DB::shouldReceive('count')->andReturn(0);
        DB::shouldReceive('select')->andReturnSelf();
        DB::shouldReceive('groupBy')->andReturnSelf();
        DB::shouldReceive('pluck')->andReturn(collect([]));
        DB::shouldReceive('whereIn')->andReturnSelf();

        $result = $this->service->getStats();

        $this->assertArrayHasKey('total', $result);
        $this->assertArrayHasKey('by_status', $result);
        $this->assertArrayHasKey('by_type', $result);
        $this->assertArrayHasKey('expiring_soon', $result);
        $this->assertArrayHasKey('expired', $result);
        $this->assertArrayHasKey('pending', $result);
        $this->assertArrayHasKey('verified', $result);
        $this->assertArrayHasKey('rejected', $result);
    }

    public function test_getStats_returns_defaults_on_error(): void
    {
        DB::shouldReceive('table')->andThrow(new \Exception('DB error'));

        $result = $this->service->getStats();

        $this->assertEquals(0, $result['total']);
        $this->assertEmpty($result['by_status']);
    }
}

<?php
// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

namespace Tests\Laravel\Unit\Services;

use Tests\Laravel\TestCase;
use App\Services\TenantService;
use App\Models\Tenant;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;
use Mockery;

class TenantServiceTest extends TestCase
{
    private TenantService $service;
    private $mockTenant;

    protected function setUp(): void
    {
        parent::setUp();
        $this->mockTenant = Mockery::mock(Tenant::class);
        $this->service = new TenantService($this->mockTenant);
    }

    public function test_bootstrap_returns_null_when_not_found(): void
    {
        $builder = Mockery::mock(Builder::class);
        $builder->shouldReceive('where')->with('slug', 'nonexistent')->andReturnSelf();
        $builder->shouldReceive('where')->with('is_active', true)->andReturnSelf();
        $builder->shouldReceive('first')->andReturn(null);

        $this->mockTenant->shouldReceive('newQuery')->andReturn($builder);

        $this->assertNull($this->service->bootstrap('nonexistent'));
    }

    public function test_bootstrap_returns_tenant_and_settings(): void
    {
        $tenantModel = Mockery::mock(Tenant::class);
        $tenantModel->id = 2;
        $tenantModel->shouldReceive('toArray')->andReturn(['id' => 2, 'name' => 'Test']);

        $builder = Mockery::mock(Builder::class);
        $builder->shouldReceive('where')->andReturnSelf();
        $builder->shouldReceive('first')->andReturn($tenantModel);

        $this->mockTenant->shouldReceive('newQuery')->andReturn($builder);

        DB::shouldReceive('table')->with('tenant_settings')->andReturnSelf();
        DB::shouldReceive('where')->with('tenant_id', 2)->andReturnSelf();
        DB::shouldReceive('pluck')->andReturn(collect(['key1' => 'val1']));

        $result = $this->service->bootstrap('hour-timebank');

        $this->assertArrayHasKey('tenant', $result);
        $this->assertArrayHasKey('settings', $result);
        $this->assertEquals(2, $result['tenant']['id']);
    }

    public function test_getAll_returns_collection(): void
    {
        $builder = Mockery::mock(Builder::class);
        $builder->shouldReceive('where')->with('is_active', true)->andReturnSelf();
        $builder->shouldReceive('orderBy')->with('name')->andReturnSelf();
        $builder->shouldReceive('get')->andReturn(collect([]));

        $this->mockTenant->shouldReceive('newQuery')->andReturn($builder);

        $result = $this->service->getAll();
        $this->assertCount(0, $result);
    }

    public function test_getSettings_returns_array(): void
    {
        DB::shouldReceive('table')->with('tenant_settings')->andReturnSelf();
        DB::shouldReceive('where')->with('tenant_id', 2)->andReturnSelf();
        DB::shouldReceive('pluck')->andReturn(collect(['key' => 'value']));

        $result = $this->service->getSettings(2);

        $this->assertIsArray($result);
        $this->assertEquals('value', $result['key']);
    }
}

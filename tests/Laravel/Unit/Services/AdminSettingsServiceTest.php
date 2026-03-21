<?php
// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

namespace Tests\Laravel\Unit\Services;

use Tests\Laravel\TestCase;
use App\Services\AdminSettingsService;
use Illuminate\Support\Facades\DB;

class AdminSettingsServiceTest extends TestCase
{
    private AdminSettingsService $service;

    protected function setUp(): void
    {
        parent::setUp();
        $this->service = new AdminSettingsService();
    }

    public function test_getAll_returns_array(): void
    {
        DB::shouldReceive('table')->with('tenant_settings')->andReturnSelf();
        DB::shouldReceive('where')->andReturnSelf();
        DB::shouldReceive('pluck')->andReturn(collect(['key1' => 'val1']));
        DB::shouldReceive('all')->andReturn(['key1' => 'val1']);

        $result = $this->service->getAll(2);
        $this->assertIsArray($result);
        $this->assertSame('val1', $result['key1']);
    }

    public function test_update_returns_true(): void
    {
        DB::shouldReceive('table')->with('tenant_settings')->andReturnSelf();
        DB::shouldReceive('updateOrInsert')->twice()->andReturn(true);

        $result = $this->service->update(2, ['key1' => 'val1', 'key2' => 'val2']);
        $this->assertTrue($result);
    }

    public function test_update_handles_array_values(): void
    {
        DB::shouldReceive('table')->with('tenant_settings')->andReturnSelf();
        DB::shouldReceive('updateOrInsert')->once()->andReturn(true);

        $result = $this->service->update(2, ['config' => ['nested' => true]]);
        $this->assertTrue($result);
    }

    public function test_getFeatures_returns_empty_array_when_tenant_not_found(): void
    {
        DB::shouldReceive('table')->with('tenants')->andReturnSelf();
        DB::shouldReceive('where')->andReturnSelf();
        DB::shouldReceive('first')->andReturnNull();

        $result = $this->service->getFeatures(999);
        $this->assertSame([], $result);
    }

    public function test_getFeatures_returns_decoded_features(): void
    {
        DB::shouldReceive('table')->with('tenants')->andReturnSelf();
        DB::shouldReceive('where')->andReturnSelf();
        DB::shouldReceive('first')->andReturn((object) ['features' => '{"events":true,"blog":false}']);

        $result = $this->service->getFeatures(2);
        $this->assertTrue($result['events']);
        $this->assertFalse($result['blog']);
    }

    public function test_toggleFeature_returns_false_when_tenant_not_found(): void
    {
        DB::shouldReceive('table')->with('tenants')->andReturnSelf();
        DB::shouldReceive('where')->andReturnSelf();
        DB::shouldReceive('first')->andReturnNull();

        $result = $this->service->toggleFeature(999, 'events', true);
        $this->assertFalse($result);
    }

    public function test_toggleFeature_updates_feature_flag(): void
    {
        DB::shouldReceive('table')->with('tenants')->andReturnSelf();
        DB::shouldReceive('where')->andReturnSelf();
        DB::shouldReceive('first')->andReturn((object) ['features' => '{"events":false}']);
        DB::shouldReceive('update')->andReturn(1);

        $result = $this->service->toggleFeature(2, 'events', true);
        $this->assertTrue($result);
    }
}

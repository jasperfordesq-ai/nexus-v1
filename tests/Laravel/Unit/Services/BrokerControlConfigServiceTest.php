<?php
// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

namespace Tests\Laravel\Unit\Services;

use Tests\Laravel\TestCase;
use App\Services\BrokerControlConfigService;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Cache;

class BrokerControlConfigServiceTest extends TestCase
{
    public function test_getConfig_returns_defaults_when_no_config_exists(): void
    {
        DB::shouldReceive('table')->with('tenants')->andReturnSelf();
        DB::shouldReceive('where')->andReturnSelf();
        DB::shouldReceive('first')->andReturn((object) ['configuration' => null]);

        DB::shouldReceive('table')->with('tenant_settings')->andReturnSelf();
        DB::shouldReceive('first')->andReturnNull();

        $config = BrokerControlConfigService::getConfig();

        $this->assertArrayHasKey('messaging', $config);
        $this->assertArrayHasKey('risk_tagging', $config);
        $this->assertArrayHasKey('exchange_workflow', $config);
        $this->assertArrayHasKey('broker_visibility', $config);
        $this->assertTrue($config['messaging']['direct_messaging_enabled']);
        $this->assertFalse($config['exchange_workflow']['enabled']);
    }

    public function test_getConfig_returns_specific_section(): void
    {
        DB::shouldReceive('table')->with('tenants')->andReturnSelf();
        DB::shouldReceive('where')->andReturnSelf();
        DB::shouldReceive('first')->andReturn((object) ['configuration' => null]);

        DB::shouldReceive('table')->with('tenant_settings')->andReturnSelf();
        DB::shouldReceive('first')->andReturnNull();

        $messaging = BrokerControlConfigService::getConfig('messaging');

        $this->assertArrayHasKey('direct_messaging_enabled', $messaging);
        $this->assertArrayNotHasKey('risk_tagging', $messaging);
    }

    public function test_isDirectMessagingEnabled_returns_true_by_default(): void
    {
        DB::shouldReceive('table')->andReturnSelf();
        DB::shouldReceive('where')->andReturnSelf();
        DB::shouldReceive('first')->andReturn((object) ['configuration' => null]);

        $this->assertTrue(BrokerControlConfigService::isDirectMessagingEnabled());
    }

    public function test_isExchangeWorkflowEnabled_returns_false_by_default(): void
    {
        DB::shouldReceive('table')->andReturnSelf();
        DB::shouldReceive('where')->andReturnSelf();
        DB::shouldReceive('first')->andReturn((object) ['configuration' => null]);

        $this->assertFalse(BrokerControlConfigService::isExchangeWorkflowEnabled());
    }

    public function test_isBrokerVisibilityEnabled_returns_true_by_default(): void
    {
        DB::shouldReceive('table')->andReturnSelf();
        DB::shouldReceive('where')->andReturnSelf();
        DB::shouldReceive('first')->andReturn((object) ['configuration' => null]);

        $this->assertTrue(BrokerControlConfigService::isBrokerVisibilityEnabled());
    }

    public function test_updateConfig_updates_tenant_configuration(): void
    {
        DB::shouldReceive('table')->with('tenants')->andReturnSelf();
        DB::shouldReceive('where')->andReturnSelf();
        DB::shouldReceive('first')->andReturn((object) ['configuration' => '{}']);
        DB::shouldReceive('update')->once()->andReturn(1);

        Cache::shouldReceive('forget')->twice();

        $result = BrokerControlConfigService::updateConfig(['messaging' => ['direct_messaging_enabled' => false]]);
        $this->assertTrue($result);
    }

    public function test_isVettingEnabled_returns_false_by_default(): void
    {
        DB::shouldReceive('table')->with('tenant_settings')->andReturnSelf();
        DB::shouldReceive('where')->andReturnSelf();
        DB::shouldReceive('first')->andReturnNull();

        $this->assertFalse(BrokerControlConfigService::isVettingEnabled());
    }

    public function test_isInsuranceEnabled_returns_false_by_default(): void
    {
        DB::shouldReceive('table')->with('tenant_settings')->andReturnSelf();
        DB::shouldReceive('where')->andReturnSelf();
        DB::shouldReceive('first')->andReturnNull();

        $this->assertFalse(BrokerControlConfigService::isInsuranceEnabled());
    }
}

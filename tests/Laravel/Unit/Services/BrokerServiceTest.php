<?php
// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

namespace Tests\Laravel\Unit\Services;

use Tests\Laravel\TestCase;
use App\Services\BrokerService;
use Illuminate\Support\Facades\DB;

class BrokerServiceTest extends TestCase
{
    private BrokerService $service;

    protected function setUp(): void
    {
        parent::setUp();
        $this->service = new BrokerService();
    }

    public function test_getConfig_returns_defaults_when_no_config_exists(): void
    {
        DB::shouldReceive('table')->with('broker_config')->andReturnSelf();
        DB::shouldReceive('where')->andReturnSelf();
        DB::shouldReceive('first')->andReturnNull();

        $result = $this->service->getConfig(2);

        $this->assertFalse($result['enabled']);
        $this->assertFalse($result['auto_match']);
        $this->assertSame('admin_only', $result['visibility']);
        $this->assertSame([], $result['notification_emails']);
    }

    public function test_getConfig_returns_stored_config(): void
    {
        DB::shouldReceive('table')->with('broker_config')->andReturnSelf();
        DB::shouldReceive('where')->andReturnSelf();
        DB::shouldReceive('first')->andReturn((object) [
            'enabled' => true,
            'auto_match' => true,
            'visibility' => 'brokers',
        ]);

        $result = $this->service->getConfig(2);
        $this->assertTrue($result['enabled']);
    }

    public function test_getMessages_returns_items_and_total(): void
    {
        DB::shouldReceive('table')->with('broker_messages')->andReturnSelf();
        DB::shouldReceive('where')->andReturnSelf();
        DB::shouldReceive('count')->andReturn(0);
        DB::shouldReceive('orderByDesc')->andReturnSelf();
        DB::shouldReceive('offset')->andReturnSelf();
        DB::shouldReceive('limit')->andReturnSelf();
        DB::shouldReceive('get')->andReturn(collect([]));

        $result = $this->service->getMessages(2);

        $this->assertArrayHasKey('items', $result);
        $this->assertArrayHasKey('total', $result);
    }

    public function test_updateVisibility_returns_false_for_invalid_visibility(): void
    {
        $result = $this->service->updateVisibility(2, 'invalid');
        $this->assertFalse($result);
    }

    public function test_updateVisibility_returns_true_for_valid_visibility(): void
    {
        DB::shouldReceive('table')->with('broker_config')->andReturnSelf();
        DB::shouldReceive('updateOrInsert')->once()->andReturn(true);

        $result = $this->service->updateVisibility(2, 'brokers');
        $this->assertTrue($result);
    }

    public function test_updateVisibility_accepts_all_allowed_values(): void
    {
        foreach (['admin_only', 'brokers', 'members', 'public'] as $visibility) {
            DB::shouldReceive('table')->with('broker_config')->andReturnSelf();
            DB::shouldReceive('updateOrInsert')->andReturn(true);

            $result = $this->service->updateVisibility(2, $visibility);
            $this->assertTrue($result, "Failed for visibility: $visibility");
        }
    }
}

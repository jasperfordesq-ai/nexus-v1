<?php
// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

namespace Tests\Laravel\Unit\Services;

use Tests\Laravel\TestCase;
use App\Services\RealtimeService;

class RealtimeServiceTest extends TestCase
{
    private RealtimeService $service;

    protected function setUp(): void
    {
        parent::setUp();
        $this->service = new RealtimeService();
    }

    public function test_getConfig_returns_expected_keys(): void
    {
        $result = $this->service->getConfig();
        $this->assertArrayHasKey('driver', $result);
        $this->assertArrayHasKey('key', $result);
        $this->assertArrayHasKey('cluster', $result);
        $this->assertArrayHasKey('encrypted', $result);
    }

    public function test_getFrontendConfig_returns_expected_keys(): void
    {
        $result = $this->service->getFrontendConfig();
        $this->assertArrayHasKey('key', $result);
        $this->assertArrayHasKey('cluster', $result);
        $this->assertArrayHasKey('authEndpoint', $result);
        $this->assertArrayHasKey('enabled', $result);
        $this->assertEquals('/api/pusher/auth', $result['authEndpoint']);
    }

    public function test_tenantChannel_returns_correct_format(): void
    {
        $result = $this->service->tenantChannel(2, 'notifications');
        $this->assertEquals('private-tenant.2.notifications', $result);
    }

    public function test_broadcast_returns_false_on_exception(): void
    {
        // With no Pusher config, it should throw and return false
        config(['broadcasting.connections.pusher.key' => '']);
        config(['broadcasting.connections.pusher.secret' => '']);
        config(['broadcasting.connections.pusher.app_id' => '']);

        $result = $this->service->broadcast('test-channel', 'test-event', ['foo' => 'bar']);
        $this->assertFalse($result);
    }
}

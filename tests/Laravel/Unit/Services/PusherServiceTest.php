<?php
// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

namespace Tests\Laravel\Unit\Services;

use Tests\Laravel\TestCase;
use App\Services\PusherService;
use App\Core\TenantContext;
use Mockery;

class PusherServiceTest extends TestCase
{
    private PusherService $service;

    protected function setUp(): void
    {
        parent::setUp();
        $this->service = new PusherService();
        // Reset the static instance between tests
        $ref = new \ReflectionClass(PusherService::class);
        $prop = $ref->getProperty('instance');
        $prop->setAccessible(true);
        $prop->setValue(null, null);
    }

    public function test_getInstance_returns_null_when_not_configured(): void
    {
        config(['broadcasting.connections.pusher.key' => '']);
        config(['broadcasting.connections.pusher.secret' => '']);
        config(['broadcasting.connections.pusher.app_id' => '']);

        $result = PusherService::getInstance();
        $this->assertNull($result);
    }

    public function test_getConfig_returns_expected_keys(): void
    {
        config(['broadcasting.connections.pusher.key' => 'test-key']);
        config(['broadcasting.connections.pusher.secret' => 'test-secret']);
        config(['broadcasting.connections.pusher.app_id' => '12345']);
        config(['broadcasting.connections.pusher.options.cluster' => 'eu']);

        $result = $this->service->getConfig();
        $this->assertEquals('test-key', $result['key']);
        $this->assertEquals('test-secret', $result['secret']);
        $this->assertEquals('12345', $result['app_id']);
        $this->assertEquals('eu', $result['cluster']);
        $this->assertTrue($result['encrypted']);
    }

    public function test_getPublicKey_returns_key(): void
    {
        config(['broadcasting.connections.pusher.key' => 'pub-key-123']);
        $this->assertEquals('pub-key-123', $this->service->getPublicKey());
    }

    public function test_getCluster_defaults_to_eu(): void
    {
        config(['broadcasting.connections.pusher.options.cluster' => null]);
        $this->assertEquals('eu', $this->service->getCluster());
    }

    public function test_isConfigured_returns_false_when_empty(): void
    {
        config(['broadcasting.connections.pusher.key' => '']);
        $this->assertFalse($this->service->isConfigured());
    }

    public function test_isConfigured_returns_true_when_all_set(): void
    {
        config(['broadcasting.connections.pusher.key' => 'k']);
        config(['broadcasting.connections.pusher.secret' => 's']);
        config(['broadcasting.connections.pusher.app_id' => 'a']);
        $this->assertTrue($this->service->isConfigured());
    }

    public function test_getUserChannel_returns_correct_format(): void
    {
        $this->assertEquals('private-user.42', $this->service->getUserChannel(42));
    }

    public function test_getPresenceChannel_includes_tenant_id(): void
    {
        $result = $this->service->getPresenceChannel();
        $this->assertStringStartsWith('presence-tenant.', $result);
    }

    public function test_authPrivateChannel_returns_null_when_not_configured(): void
    {
        config(['broadcasting.connections.pusher.key' => '']);
        config(['broadcasting.connections.pusher.secret' => '']);
        config(['broadcasting.connections.pusher.app_id' => '']);

        $result = $this->service->authPrivateChannel('private-user.1', 'socket123', 1);
        $this->assertNull($result);
    }

    public function test_authPresenceChannel_returns_null_when_not_configured(): void
    {
        config(['broadcasting.connections.pusher.key' => '']);
        $result = $this->service->authPresenceChannel('presence-tenant.2', 'socket123', 1);
        $this->assertNull($result);
    }
}

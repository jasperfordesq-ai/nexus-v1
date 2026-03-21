<?php
// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

declare(strict_types=1);

namespace App\Tests\Services;

use App\Tests\TestCase;
use App\Services\PusherService;
use App\Core\TenantContext;

/**
 * PusherServiceTest — tests for Pusher channel auth logic, naming, and configuration.
 *
 * The Pusher SDK itself is not instantiated in tests (no credentials in test env).
 * Tests focus on channel naming, access control logic, and config accessors.
 */
class PusherServiceTest extends TestCase
{
    private PusherService $service;

    protected function setUp(): void
    {
        parent::setUp();
        $this->service = new PusherService();
        TenantContext::setById(1);

        // Reset the singleton between tests
        $reflection = new \ReflectionClass(PusherService::class);
        $prop = $reflection->getProperty('instance');
        $prop->setAccessible(true);
        $prop->setValue(null, null);
    }

    // =========================================================================
    // isConfigured
    // =========================================================================

    public function testIsConfiguredReturnsFalseWithoutCredentials(): void
    {
        // Test env has no Pusher credentials
        config(['broadcasting.connections.pusher.key' => '']);
        config(['broadcasting.connections.pusher.secret' => '']);
        config(['broadcasting.connections.pusher.app_id' => '']);

        $this->assertFalse($this->service->isConfigured());
    }

    public function testIsConfiguredReturnsTrueWithCredentials(): void
    {
        config(['broadcasting.connections.pusher.key' => 'test_key']);
        config(['broadcasting.connections.pusher.secret' => 'test_secret']);
        config(['broadcasting.connections.pusher.app_id' => 'test_app_id']);

        $this->assertTrue($this->service->isConfigured());
    }

    // =========================================================================
    // getInstance
    // =========================================================================

    public function testGetInstanceReturnsNullWhenNotConfigured(): void
    {
        config(['broadcasting.connections.pusher.key' => '']);
        config(['broadcasting.connections.pusher.secret' => '']);
        config(['broadcasting.connections.pusher.app_id' => '']);

        $this->assertNull(PusherService::getInstance());
    }

    // =========================================================================
    // Channel naming
    // =========================================================================

    public function testGetUserChannelReturnsCorrectFormat(): void
    {
        $channel = $this->service->getUserChannel(42);
        $this->assertEquals('private-user.42', $channel);
    }

    public function testGetPresenceChannelReturnsCorrectFormat(): void
    {
        TenantContext::setById(5);
        $channel = $this->service->getPresenceChannel();
        $this->assertEquals('presence-tenant.5', $channel);
    }

    // =========================================================================
    // Config accessors
    // =========================================================================

    public function testGetPublicKeyReturnsConfigValue(): void
    {
        config(['broadcasting.connections.pusher.key' => 'my_public_key']);
        $this->assertEquals('my_public_key', $this->service->getPublicKey());
    }

    public function testGetClusterReturnsConfigValue(): void
    {
        config(['broadcasting.connections.pusher.options.cluster' => 'us2']);
        $this->assertEquals('us2', $this->service->getCluster());
    }

    public function testGetClusterDefaultsToEu(): void
    {
        config(['broadcasting.connections.pusher.options.cluster' => null]);
        $this->assertEquals('eu', $this->service->getCluster());
    }

    public function testGetConfigReturnsArray(): void
    {
        config(['broadcasting.connections.pusher.key' => 'k']);
        config(['broadcasting.connections.pusher.secret' => 's']);
        config(['broadcasting.connections.pusher.app_id' => 'a']);

        $config = $this->service->getConfig();

        $this->assertIsArray($config);
        $this->assertEquals('k', $config['key']);
        $this->assertEquals('s', $config['secret']);
        $this->assertEquals('a', $config['app_id']);
        $this->assertTrue($config['encrypted']);
        $this->assertTrue($config['useTLS']);
    }

    // =========================================================================
    // canAccessPrivateChannel (via reflection)
    // =========================================================================

    public function testCanAccessUserChannelOnlyByOwner(): void
    {
        // User 42 can access private-user.42
        $this->assertTrue(
            $this->callPrivateMethod($this->service, 'canAccessPrivateChannel', ['private-user.42', 42])
        );

        // User 99 cannot access private-user.42
        $this->assertFalse(
            $this->callPrivateMethod($this->service, 'canAccessPrivateChannel', ['private-user.42', 99])
        );
    }

    public function testCanAccessTenantChannelByTenantMember(): void
    {
        TenantContext::setById(5);

        // User in tenant 5 can access private-tenant.5.anything
        $this->assertTrue(
            $this->callPrivateMethod($this->service, 'canAccessPrivateChannel', ['private-tenant.5.messages', 1])
        );

        // Wrong tenant
        $this->assertFalse(
            $this->callPrivateMethod($this->service, 'canAccessPrivateChannel', ['private-tenant.99.messages', 1])
        );
    }

    public function testCanAccessUnknownChannelPatternDenied(): void
    {
        $this->assertFalse(
            $this->callPrivateMethod($this->service, 'canAccessPrivateChannel', ['private-unknown.channel', 1])
        );
    }

    // =========================================================================
    // authPrivateChannel — SDK null case
    // =========================================================================

    public function testAuthPrivateChannelReturnsNullWhenNotConfigured(): void
    {
        config(['broadcasting.connections.pusher.key' => '']);
        config(['broadcasting.connections.pusher.secret' => '']);
        config(['broadcasting.connections.pusher.app_id' => '']);

        $result = $this->service->authPrivateChannel('private-user.1', 'socket-123', 1);
        $this->assertNull($result);
    }

    public function testAuthPrivateChannelReturnsNullForForbiddenChannel(): void
    {
        config(['broadcasting.connections.pusher.key' => 'k']);
        config(['broadcasting.connections.pusher.secret' => 's']);
        config(['broadcasting.connections.pusher.app_id' => 'a']);

        // User 99 trying to access user 1's channel — forbidden before SDK is even called
        $result = $this->service->authPrivateChannel('private-user.1', 'socket-123', 99);
        $this->assertNull($result);
    }

    // =========================================================================
    // authPresenceChannel — SDK null case
    // =========================================================================

    public function testAuthPresenceChannelReturnsNullWhenNotConfigured(): void
    {
        config(['broadcasting.connections.pusher.key' => '']);
        config(['broadcasting.connections.pusher.secret' => '']);
        config(['broadcasting.connections.pusher.app_id' => '']);

        $result = $this->service->authPresenceChannel('presence-tenant.1', 'socket-123', 1);
        $this->assertNull($result);
    }
}

<?php
// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

declare(strict_types=1);

namespace App\Tests\Services;

use App\Tests\TestCase;
use App\Services\FCMPushService;
use App\Core\TenantContext;
use Illuminate\Support\Facades\DB;

/**
 * FCMPushServiceTest — tests for FCM push notification device registration
 * and sending logic.
 *
 * HTTP calls are faked. Firebase service account is not present in test env,
 * so sending tests verify the "not configured" path. Device registration/
 * unregistration tests mock the DB layer.
 */
class FCMPushServiceTest extends TestCase
{
    private FCMPushService $service;

    protected function setUp(): void
    {
        parent::setUp();
        $this->service = new FCMPushService();
        TenantContext::setById(1);

        // Reset static token cache between tests
        $reflection = new \ReflectionClass(FCMPushService::class);
        $tokenProp = $reflection->getProperty('accessToken');
        $tokenProp->setAccessible(true);
        $tokenProp->setValue(null, null);
        $expiryProp = $reflection->getProperty('tokenExpiry');
        $expiryProp->setAccessible(true);
        $expiryProp->setValue(null, null);
    }

    // =========================================================================
    // isConfigured
    // =========================================================================

    public function testIsConfiguredReturnsFalseWithoutCredentials(): void
    {
        // No service account file and no server key in test env
        $this->assertFalse($this->service->isConfigured());
    }

    // =========================================================================
    // sendToUser — not configured
    // =========================================================================

    public function testSendToUserReturnsNotConfiguredError(): void
    {
        $result = FCMPushService::sendToUser(1, 'Test Title', 'Test Body');

        $this->assertEquals(0, $result['sent']);
        $this->assertEquals(0, $result['failed']);
        $this->assertContains('FCM not configured', $result['errors']);
    }

    // =========================================================================
    // sendToUsers — empty user list
    // =========================================================================

    public function testSendToUsersWithEmptyListReturnsZero(): void
    {
        $result = FCMPushService::sendToUsers([], 'Title', 'Body');

        $this->assertEquals(0, $result['sent']);
        $this->assertEquals(0, $result['failed']);
        $this->assertEmpty($result['errors']);
    }

    // =========================================================================
    // registerDevice
    // =========================================================================

    public function testRegisterDeviceReturnsTrueOnSuccess(): void
    {
        DB::shouldReceive('statement')
            ->once()
            ->andReturn(true);

        $result = $this->service->registerDevice(1, 'fcm-token-abc123', 'android');
        $this->assertTrue($result);
    }

    public function testRegisterDeviceReturnsFalseOnException(): void
    {
        DB::shouldReceive('statement')
            ->once()
            ->andThrow(new \RuntimeException('DB error'));

        $result = $this->service->registerDevice(1, 'fcm-token-abc123');
        $this->assertFalse($result);
    }

    public function testRegisterDeviceDefaultsPlatformToAndroid(): void
    {
        DB::shouldReceive('statement')
            ->once()
            ->withArgs(function ($sql, $params) {
                return $params[3] === 'android';
            })
            ->andReturn(true);

        $this->service->registerDevice(1, 'token123');
    }

    public function testRegisterDeviceAcceptsIosPlatform(): void
    {
        DB::shouldReceive('statement')
            ->once()
            ->withArgs(function ($sql, $params) {
                return $params[3] === 'ios';
            })
            ->andReturn(true);

        $this->service->registerDevice(1, 'token123', 'ios');
    }

    public function testRegisterDevicePassesTenantId(): void
    {
        TenantContext::setById(42);

        DB::shouldReceive('statement')
            ->once()
            ->withArgs(function ($sql, $params) {
                return $params[1] === 42;
            })
            ->andReturn(true);

        $this->service->registerDevice(1, 'token123');
    }

    // =========================================================================
    // unregisterDevice
    // =========================================================================

    public function testUnregisterDeviceReturnsTrueWhenDeleted(): void
    {
        DB::shouldReceive('table')->once()->andReturnSelf();
        DB::shouldReceive('where')->once()->andReturnSelf();
        DB::shouldReceive('delete')->once()->andReturn(1);

        $result = $this->service->unregisterDevice('fcm-token-abc123');
        $this->assertTrue($result);
    }

    public function testUnregisterDeviceReturnsFalseWhenNotFound(): void
    {
        DB::shouldReceive('table')->once()->andReturnSelf();
        DB::shouldReceive('where')->once()->andReturnSelf();
        DB::shouldReceive('delete')->once()->andReturn(0);

        $result = $this->service->unregisterDevice('nonexistent-token');
        $this->assertFalse($result);
    }

    public function testUnregisterDeviceReturnsFalseOnException(): void
    {
        DB::shouldReceive('table')->once()->andReturnSelf();
        DB::shouldReceive('where')->once()->andReturnSelf();
        DB::shouldReceive('delete')
            ->once()
            ->andThrow(new \RuntimeException('DB error'));

        $result = $this->service->unregisterDevice('token');
        $this->assertFalse($result);
    }

    // =========================================================================
    // ensureTableExists
    // =========================================================================

    public function testEnsureTableExistsIsNoOp(): void
    {
        // Should not throw
        $this->service->ensureTableExists();
        $this->assertTrue(true);
    }

    // =========================================================================
    // Method signatures
    // =========================================================================

    public function testSendToUserMethodIsStatic(): void
    {
        $ref = new \ReflectionMethod(FCMPushService::class, 'sendToUser');
        $this->assertTrue($ref->isStatic());
    }

    public function testSendToUsersMethodIsStatic(): void
    {
        $ref = new \ReflectionMethod(FCMPushService::class, 'sendToUsers');
        $this->assertTrue($ref->isStatic());
    }

    public function testSendToUserSignature(): void
    {
        $ref = new \ReflectionMethod(FCMPushService::class, 'sendToUser');
        $params = $ref->getParameters();
        $this->assertEquals('userId', $params[0]->getName());
        $this->assertEquals('title', $params[1]->getName());
        $this->assertEquals('body', $params[2]->getName());
        $this->assertEquals('data', $params[3]->getName());
    }

    // =========================================================================
    // base64UrlEncode (private, via reflection)
    // =========================================================================

    public function testBase64UrlEncodeProducesUrlSafeOutput(): void
    {
        $result = $this->callPrivateMethod(new FCMPushService(), 'base64UrlEncode', ['test data +/=']);
        $this->assertStringNotContainsString('+', $result);
        $this->assertStringNotContainsString('/', $result);
        $this->assertStringNotContainsString('=', $result);
    }

    public function testBase64UrlEncodeHandlesEmptyString(): void
    {
        $result = $this->callPrivateMethod(new FCMPushService(), 'base64UrlEncode', ['']);
        $this->assertIsString($result);
    }
}

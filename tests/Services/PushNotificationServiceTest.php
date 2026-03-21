<?php
// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

namespace App\Tests\Services;

use App\Tests\TestCase;
use App\Services\PushNotificationService;

class PushNotificationServiceTest extends TestCase
{
    private PushNotificationService $service;

    protected function setUp(): void
    {
        parent::setUp();
        $this->service = new PushNotificationService();
    }

    public function testClassExists(): void
    {
        $this->assertTrue(class_exists(PushNotificationService::class));
    }

    public function testSubscribeReturnsFalseForEmptyEndpoint(): void
    {
        $result = $this->service->subscribe(1, ['endpoint' => '', 'keys' => ['p256dh' => '', 'auth' => '']]);
        $this->assertFalse($result);
    }

    public function testSubscribeReturnsFalseForMissingEndpoint(): void
    {
        $result = $this->service->subscribe(1, ['keys' => ['p256dh' => '', 'auth' => '']]);
        $this->assertFalse($result);
    }

    public function testGetVapidKeyReturnsStringOrNull(): void
    {
        $result = $this->service->getVapidKey();
        $this->assertTrue($result === null || is_string($result));
    }

    public function testGetSubscriptionCountReturnsIntForNonExistentUser(): void
    {
        $count = $this->service->getSubscriptionCount(999999);
        $this->assertIsInt($count);
        $this->assertEquals(0, $count);
    }

    public function testSendMethodExists(): void
    {
        $this->assertTrue(method_exists(PushNotificationService::class, 'send'));
    }

    public function testUnsubscribeMethodExists(): void
    {
        $this->assertTrue(method_exists(PushNotificationService::class, 'unsubscribe'));
    }

    public function testSendSignature(): void
    {
        $ref = new \ReflectionMethod(PushNotificationService::class, 'send');
        $params = $ref->getParameters();
        $this->assertCount(4, $params);
        $this->assertEquals('userId', $params[0]->getName());
        $this->assertEquals('title', $params[1]->getName());
        $this->assertEquals('body', $params[2]->getName());
        $this->assertEquals('link', $params[3]->getName());
        $this->assertTrue($params[3]->isOptional());
    }

    public function testUnsubscribeReturnsFalseForNonExistentSubscription(): void
    {
        $result = $this->service->unsubscribe(999999, 'https://example.com/push');
        $this->assertFalse($result);
    }
}

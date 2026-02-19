<?php
// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

namespace Tests\Services;

use PHPUnit\Framework\TestCase;
use Nexus\Services\FCMPushService;

class FCMPushServiceTest extends TestCase
{
    public function testClassExists(): void
    {
        $this->assertTrue(class_exists(FCMPushService::class));
    }

    public function testSendToUserMethodExists(): void
    {
        $this->assertTrue(method_exists(FCMPushService::class, 'sendToUser'));
        $ref = new \ReflectionMethod(FCMPushService::class, 'sendToUser');
        $this->assertTrue($ref->isStatic());
    }

    public function testSendToUsersMethodExists(): void
    {
        $this->assertTrue(method_exists(FCMPushService::class, 'sendToUsers'));
    }

    public function testSendToUserSignature(): void
    {
        $ref = new \ReflectionMethod(FCMPushService::class, 'sendToUser');
        $params = $ref->getParameters();
        $this->assertEquals('userId', $params[0]->getName());
        $this->assertEquals('title', $params[1]->getName());
        $this->assertEquals('body', $params[2]->getName());
    }
}

<?php
// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

namespace Tests\Services;

use PHPUnit\Framework\TestCase;
use Nexus\Services\WebPushService;

class WebPushServiceTest extends TestCase
{
    public function testClassExists(): void
    {
        $this->assertTrue(class_exists(WebPushService::class));
    }

    public function testSendToUserMethodExists(): void
    {
        $this->assertTrue(method_exists(WebPushService::class, 'sendToUser'));
        $ref = new \ReflectionMethod(WebPushService::class, 'sendToUser');
        $this->assertTrue($ref->isStatic());
    }

    public function testSendToUsersMethodExists(): void
    {
        $this->assertTrue(method_exists(WebPushService::class, 'sendToUsers'));
    }
}

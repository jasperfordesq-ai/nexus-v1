<?php
// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

namespace Tests\Services;

use PHPUnit\Framework\TestCase;
use Nexus\Services\RealtimeService;

class RealtimeServiceTest extends TestCase
{
    public function testClassExists(): void
    {
        $this->assertTrue(class_exists(RealtimeService::class));
    }

    public function testBroadcastNotificationMethodExists(): void
    {
        $this->assertTrue(method_exists(RealtimeService::class, 'broadcastNotification'));
        $ref = new \ReflectionMethod(RealtimeService::class, 'broadcastNotification');
        $this->assertTrue($ref->isStatic());
    }

    public function testBroadcastMessageMethodExists(): void
    {
        $this->assertTrue(method_exists(RealtimeService::class, 'broadcastMessage'));
    }
}

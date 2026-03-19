<?php
// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

declare(strict_types=1);

namespace Tests\Laravel\Migrated\Controllers\Api;

use Tests\Laravel\LegacyBridgeTestCase;
use App\Http\Controllers\Api\NotificationsController as NotificationsApiController;

/**
 * Tests for NotificationsApiController (Laravel migration)
 *
 * Migrated from: Nexus\Tests\Controllers\Api\NotificationsApiControllerTest
 * Original base: PHPUnit\Framework\TestCase -> now LegacyBridgeTestCase
 */
class NotificationsApiControllerTest extends LegacyBridgeTestCase
{
    public function testControllerClassExists(): void
    {
        $this->assertTrue(class_exists(NotificationsApiController::class));
    }

    public function testHasIndexMethod(): void
    {
        $reflection = new \ReflectionClass(NotificationsApiController::class);
        $this->assertTrue($reflection->hasMethod('index'));
        $this->assertTrue($reflection->getMethod('index')->isPublic());
    }

    public function testHasCountsMethod(): void
    {
        $reflection = new \ReflectionClass(NotificationsApiController::class);
        $this->assertTrue($reflection->hasMethod('counts'));
        $this->assertTrue($reflection->getMethod('counts')->isPublic());
    }

    public function testHasShowMethod(): void
    {
        $reflection = new \ReflectionClass(NotificationsApiController::class);
        $this->assertTrue($reflection->hasMethod('show'));
    }

    public function testHasMarkReadMethod(): void
    {
        $reflection = new \ReflectionClass(NotificationsApiController::class);
        $this->assertTrue($reflection->hasMethod('markRead'));
        $this->assertTrue($reflection->getMethod('markRead')->isPublic());
    }

    public function testHasMarkAllReadMethod(): void
    {
        $reflection = new \ReflectionClass(NotificationsApiController::class);
        $this->assertTrue($reflection->hasMethod('markAllRead'));
        $this->assertTrue($reflection->getMethod('markAllRead')->isPublic());
    }

    public function testHasDestroyMethod(): void
    {
        $reflection = new \ReflectionClass(NotificationsApiController::class);
        $this->assertTrue($reflection->hasMethod('destroy'));
    }

    public function testHasDestroyAllMethod(): void
    {
        $reflection = new \ReflectionClass(NotificationsApiController::class);
        $this->assertTrue($reflection->hasMethod('destroyAll'));
        $this->assertTrue($reflection->getMethod('destroyAll')->isPublic());
    }
}

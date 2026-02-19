<?php
// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

declare(strict_types=1);

namespace Nexus\Tests\Controllers\Api;

use PHPUnit\Framework\TestCase;
use Nexus\Controllers\Api\UsersApiController;

class UsersApiControllerTest extends TestCase
{
    public function testControllerClassExists(): void
    {
        $this->assertTrue(class_exists(UsersApiController::class));
    }

    public function testExtendsBaseApiController(): void
    {
        $reflection = new \ReflectionClass(UsersApiController::class);
        $this->assertTrue($reflection->isSubclassOf(\Nexus\Controllers\Api\BaseApiController::class));
    }

    public function testHasMeMethod(): void
    {
        $reflection = new \ReflectionClass(UsersApiController::class);
        $this->assertTrue($reflection->hasMethod('me'));
        $this->assertTrue($reflection->getMethod('me')->isPublic());
    }

    public function testHasShowMethod(): void
    {
        $reflection = new \ReflectionClass(UsersApiController::class);
        $this->assertTrue($reflection->hasMethod('show'));
        $method = $reflection->getMethod('show');
        $this->assertTrue($method->isPublic());
        $params = $method->getParameters();
        $this->assertCount(1, $params);
        $this->assertEquals('id', $params[0]->getName());
    }

    public function testHasListingsMethod(): void
    {
        $reflection = new \ReflectionClass(UsersApiController::class);
        $this->assertTrue($reflection->hasMethod('listings'));
        $method = $reflection->getMethod('listings');
        $this->assertTrue($method->isPublic());
        $params = $method->getParameters();
        $this->assertEquals('id', $params[0]->getName());
    }

    public function testHasUpdateMethod(): void
    {
        $reflection = new \ReflectionClass(UsersApiController::class);
        $this->assertTrue($reflection->hasMethod('update'));
        $this->assertTrue($reflection->getMethod('update')->isPublic());
    }

    public function testHasUpdatePreferencesMethod(): void
    {
        $reflection = new \ReflectionClass(UsersApiController::class);
        $this->assertTrue($reflection->hasMethod('updatePreferences'));
        $this->assertTrue($reflection->getMethod('updatePreferences')->isPublic());
    }

    public function testHasUpdateAvatarMethod(): void
    {
        $reflection = new \ReflectionClass(UsersApiController::class);
        $this->assertTrue($reflection->hasMethod('updateAvatar'));
        $this->assertTrue($reflection->getMethod('updateAvatar')->isPublic());
    }

    public function testHasUpdatePasswordMethod(): void
    {
        $reflection = new \ReflectionClass(UsersApiController::class);
        $this->assertTrue($reflection->hasMethod('updatePassword'));
        $this->assertTrue($reflection->getMethod('updatePassword')->isPublic());
    }

    public function testHasDeleteAccountMethod(): void
    {
        $reflection = new \ReflectionClass(UsersApiController::class);
        $this->assertTrue($reflection->hasMethod('deleteAccount'));
        $this->assertTrue($reflection->getMethod('deleteAccount')->isPublic());
    }

    public function testHasNotificationPreferencesMethod(): void
    {
        $reflection = new \ReflectionClass(UsersApiController::class);
        $this->assertTrue($reflection->hasMethod('notificationPreferences'));
        $this->assertTrue($reflection->getMethod('notificationPreferences')->isPublic());
    }

    public function testHasUpdateNotificationPreferencesMethod(): void
    {
        $reflection = new \ReflectionClass(UsersApiController::class);
        $this->assertTrue($reflection->hasMethod('updateNotificationPreferences'));
        $this->assertTrue($reflection->getMethod('updateNotificationPreferences')->isPublic());
    }

    public function testHasNearbyMethod(): void
    {
        $reflection = new \ReflectionClass(UsersApiController::class);
        $this->assertTrue($reflection->hasMethod('nearby'));
        $this->assertTrue($reflection->getMethod('nearby')->isPublic());
    }

    public function testHasUpdateThemeMethod(): void
    {
        $reflection = new \ReflectionClass(UsersApiController::class);
        $this->assertTrue($reflection->hasMethod('updateTheme'));
        $this->assertTrue($reflection->getMethod('updateTheme')->isPublic());
    }
}

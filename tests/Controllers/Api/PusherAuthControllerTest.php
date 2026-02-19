<?php
// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

declare(strict_types=1);

namespace Nexus\Tests\Controllers\Api;

use PHPUnit\Framework\TestCase;
use Nexus\Controllers\Api\PusherAuthController;

class PusherAuthControllerTest extends TestCase
{
    public function testControllerClassExists(): void
    {
        $this->assertTrue(class_exists(PusherAuthController::class));
    }

    public function testExtendsBaseApiController(): void
    {
        $reflection = new \ReflectionClass(PusherAuthController::class);
        $this->assertTrue($reflection->isSubclassOf(\Nexus\Controllers\Api\BaseApiController::class));
    }

    public function testHasAuthMethod(): void
    {
        $reflection = new \ReflectionClass(PusherAuthController::class);
        $this->assertTrue($reflection->hasMethod('auth'));
        $this->assertTrue($reflection->getMethod('auth')->isPublic());
    }

    public function testHasConfigMethod(): void
    {
        $reflection = new \ReflectionClass(PusherAuthController::class);
        $this->assertTrue($reflection->hasMethod('config'));
        $this->assertTrue($reflection->getMethod('config')->isPublic());
    }

    public function testHasDebugMethod(): void
    {
        $reflection = new \ReflectionClass(PusherAuthController::class);
        $this->assertTrue($reflection->hasMethod('debug'));
        $this->assertTrue($reflection->getMethod('debug')->isPublic());
    }
}

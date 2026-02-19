<?php
// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

declare(strict_types=1);

namespace Nexus\Tests\Controllers\Api;

use PHPUnit\Framework\TestCase;
use Nexus\Controllers\Api\AppController;

class AppControllerTest extends TestCase
{
    public function testControllerClassExists(): void
    {
        $this->assertTrue(class_exists(AppController::class));
    }

    public function testExtendsBaseApiController(): void
    {
        $reflection = new \ReflectionClass(AppController::class);
        $this->assertTrue($reflection->isSubclassOf(\Nexus\Controllers\Api\BaseApiController::class));
    }

    public function testHasCheckVersionMethod(): void
    {
        $reflection = new \ReflectionClass(AppController::class);
        $this->assertTrue($reflection->hasMethod('checkVersion'));
        $this->assertTrue($reflection->getMethod('checkVersion')->isPublic());
    }

    public function testHasVersionMethod(): void
    {
        $reflection = new \ReflectionClass(AppController::class);
        $this->assertTrue($reflection->hasMethod('version'));
        $this->assertTrue($reflection->getMethod('version')->isPublic());
    }

    public function testHasLogMethod(): void
    {
        $reflection = new \ReflectionClass(AppController::class);
        $this->assertTrue($reflection->hasMethod('log'));
        $this->assertTrue($reflection->getMethod('log')->isPublic());
    }
}

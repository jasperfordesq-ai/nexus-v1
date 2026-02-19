<?php
// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

declare(strict_types=1);

namespace Nexus\Tests\Controllers\Api;

use PHPUnit\Framework\TestCase;
use Nexus\Controllers\Api\MenuApiController;

class MenuApiControllerTest extends TestCase
{
    public function testControllerClassExists(): void
    {
        $this->assertTrue(class_exists(MenuApiController::class));
    }

    public function testExtendsBaseApiController(): void
    {
        $reflection = new \ReflectionClass(MenuApiController::class);
        $this->assertTrue($reflection->isSubclassOf(\Nexus\Controllers\Api\BaseApiController::class));
    }

    public function testHasIndexMethod(): void
    {
        $reflection = new \ReflectionClass(MenuApiController::class);
        $this->assertTrue($reflection->hasMethod('index'));
        $this->assertTrue($reflection->getMethod('index')->isPublic());
    }

    public function testHasShowMethod(): void
    {
        $reflection = new \ReflectionClass(MenuApiController::class);
        $this->assertTrue($reflection->hasMethod('show'));
        $method = $reflection->getMethod('show');
        $this->assertTrue($method->isPublic());
        $params = $method->getParameters();
        $this->assertCount(1, $params);
        $this->assertEquals('slug', $params[0]->getName());
    }

    public function testHasConfigMethod(): void
    {
        $reflection = new \ReflectionClass(MenuApiController::class);
        $this->assertTrue($reflection->hasMethod('config'));
        $this->assertTrue($reflection->getMethod('config')->isPublic());
    }

    public function testHasMobileMethod(): void
    {
        $reflection = new \ReflectionClass(MenuApiController::class);
        $this->assertTrue($reflection->hasMethod('mobile'));
        $this->assertTrue($reflection->getMethod('mobile')->isPublic());
    }

    public function testHasClearCacheMethod(): void
    {
        $reflection = new \ReflectionClass(MenuApiController::class);
        $this->assertTrue($reflection->hasMethod('clearCache'));
        $this->assertTrue($reflection->getMethod('clearCache')->isPublic());
    }
}

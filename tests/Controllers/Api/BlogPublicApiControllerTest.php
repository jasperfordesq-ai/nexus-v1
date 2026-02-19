<?php
// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

declare(strict_types=1);

namespace Nexus\Tests\Controllers\Api;

use PHPUnit\Framework\TestCase;
use Nexus\Controllers\Api\BlogPublicApiController;

class BlogPublicApiControllerTest extends TestCase
{
    public function testControllerClassExists(): void
    {
        $this->assertTrue(class_exists(BlogPublicApiController::class));
    }

    public function testExtendsBaseApiController(): void
    {
        $reflection = new \ReflectionClass(BlogPublicApiController::class);
        $this->assertTrue($reflection->isSubclassOf(\Nexus\Controllers\Api\BaseApiController::class));
    }

    public function testHasIndexMethod(): void
    {
        $reflection = new \ReflectionClass(BlogPublicApiController::class);
        $this->assertTrue($reflection->hasMethod('index'));
        $this->assertTrue($reflection->getMethod('index')->isPublic());
    }

    public function testHasCategoriesMethod(): void
    {
        $reflection = new \ReflectionClass(BlogPublicApiController::class);
        $this->assertTrue($reflection->hasMethod('categories'));
        $this->assertTrue($reflection->getMethod('categories')->isPublic());
    }

    public function testHasShowMethod(): void
    {
        $reflection = new \ReflectionClass(BlogPublicApiController::class);
        $this->assertTrue($reflection->hasMethod('show'));
        $method = $reflection->getMethod('show');
        $this->assertTrue($method->isPublic());
        $params = $method->getParameters();
        $this->assertCount(1, $params);
        $this->assertEquals('slug', $params[0]->getName());
        $this->assertEquals('string', $params[0]->getType()->getName());
    }
}

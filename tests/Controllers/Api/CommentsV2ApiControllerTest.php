<?php
// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

declare(strict_types=1);

namespace Nexus\Tests\Controllers\Api;

use PHPUnit\Framework\TestCase;
use Nexus\Controllers\Api\CommentsV2ApiController;

class CommentsV2ApiControllerTest extends TestCase
{
    public function testControllerClassExists(): void
    {
        $this->assertTrue(class_exists(CommentsV2ApiController::class));
    }

    public function testExtendsBaseApiController(): void
    {
        $reflection = new \ReflectionClass(CommentsV2ApiController::class);
        $this->assertTrue($reflection->isSubclassOf(\Nexus\Controllers\Api\BaseApiController::class));
    }

    public function testHasCrudMethods(): void
    {
        $reflection = new \ReflectionClass(CommentsV2ApiController::class);
        $methods = ['index', 'store', 'update', 'destroy'];

        foreach ($methods as $methodName) {
            $this->assertTrue($reflection->hasMethod($methodName), "Should have {$methodName}");
            $this->assertTrue($reflection->getMethod($methodName)->isPublic());
        }
    }

    public function testHasReactionsMethod(): void
    {
        $reflection = new \ReflectionClass(CommentsV2ApiController::class);
        $this->assertTrue($reflection->hasMethod('reactions'));
        $method = $reflection->getMethod('reactions');
        $this->assertTrue($method->isPublic());
        $params = $method->getParameters();
        $this->assertCount(1, $params);
        $this->assertEquals('id', $params[0]->getName());
    }

    public function testUpdateMethodAcceptsIntId(): void
    {
        $reflection = new \ReflectionClass(CommentsV2ApiController::class);
        $method = $reflection->getMethod('update');
        $params = $method->getParameters();
        $this->assertCount(1, $params);
        $this->assertEquals('id', $params[0]->getName());
        $this->assertEquals('int', $params[0]->getType()->getName());
    }
}

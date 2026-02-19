<?php
// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

declare(strict_types=1);

namespace Nexus\Tests\Controllers\Api;

use PHPUnit\Framework\TestCase;
use Nexus\Controllers\Api\GoalsApiController;

class GoalsApiControllerTest extends TestCase
{
    public function testControllerClassExists(): void
    {
        $this->assertTrue(class_exists(GoalsApiController::class));
    }

    public function testExtendsBaseApiController(): void
    {
        $reflection = new \ReflectionClass(GoalsApiController::class);
        $this->assertTrue($reflection->isSubclassOf(\Nexus\Controllers\Api\BaseApiController::class));
    }

    public function testHasCrudMethods(): void
    {
        $reflection = new \ReflectionClass(GoalsApiController::class);
        $methods = ['index', 'show', 'store', 'update', 'destroy'];

        foreach ($methods as $methodName) {
            $this->assertTrue($reflection->hasMethod($methodName), "Should have {$methodName}");
            $this->assertTrue($reflection->getMethod($methodName)->isPublic());
        }
    }

    public function testHasDiscoverMethod(): void
    {
        $reflection = new \ReflectionClass(GoalsApiController::class);
        $this->assertTrue($reflection->hasMethod('discover'));
        $this->assertTrue($reflection->getMethod('discover')->isPublic());
    }

    public function testHasProgressMethod(): void
    {
        $reflection = new \ReflectionClass(GoalsApiController::class);
        $this->assertTrue($reflection->hasMethod('progress'));
        $method = $reflection->getMethod('progress');
        $params = $method->getParameters();
        $this->assertCount(1, $params);
        $this->assertEquals('id', $params[0]->getName());
    }

    public function testHasBuddyMethod(): void
    {
        $reflection = new \ReflectionClass(GoalsApiController::class);
        $this->assertTrue($reflection->hasMethod('buddy'));
        $method = $reflection->getMethod('buddy');
        $params = $method->getParameters();
        $this->assertEquals('id', $params[0]->getName());
    }

    public function testHasCompleteMethod(): void
    {
        $reflection = new \ReflectionClass(GoalsApiController::class);
        $this->assertTrue($reflection->hasMethod('complete'));
        $method = $reflection->getMethod('complete');
        $params = $method->getParameters();
        $this->assertEquals('id', $params[0]->getName());
    }
}

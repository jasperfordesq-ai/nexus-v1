<?php
// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

declare(strict_types=1);

namespace Nexus\Tests\Controllers\Api;

use PHPUnit\Framework\TestCase;
use Nexus\Controllers\Api\ExchangesApiController;

class ExchangesApiControllerTest extends TestCase
{
    public function testControllerClassExists(): void
    {
        $this->assertTrue(class_exists(ExchangesApiController::class));
    }

    public function testExtendsBaseApiController(): void
    {
        $reflection = new \ReflectionClass(ExchangesApiController::class);
        $this->assertTrue($reflection->isSubclassOf(\Nexus\Controllers\Api\BaseApiController::class));
    }

    public function testHasCrudMethods(): void
    {
        $reflection = new \ReflectionClass(ExchangesApiController::class);
        $methods = ['index', 'show', 'store'];

        foreach ($methods as $methodName) {
            $this->assertTrue($reflection->hasMethod($methodName), "Should have {$methodName}");
            $this->assertTrue($reflection->getMethod($methodName)->isPublic());
        }
    }

    public function testHasWorkflowMethods(): void
    {
        $reflection = new \ReflectionClass(ExchangesApiController::class);
        $methods = ['accept', 'decline', 'start', 'complete', 'confirm', 'cancel'];

        foreach ($methods as $methodName) {
            $this->assertTrue($reflection->hasMethod($methodName), "Should have {$methodName}");
            $method = $reflection->getMethod($methodName);
            $this->assertTrue($method->isPublic());
            $params = $method->getParameters();
            $this->assertCount(1, $params);
            $this->assertEquals('id', $params[0]->getName());
        }
    }

    public function testHasCheckMethod(): void
    {
        $reflection = new \ReflectionClass(ExchangesApiController::class);
        $this->assertTrue($reflection->hasMethod('check'));
        $this->assertTrue($reflection->getMethod('check')->isPublic());
    }

    public function testHasConfigMethod(): void
    {
        $reflection = new \ReflectionClass(ExchangesApiController::class);
        $this->assertTrue($reflection->hasMethod('config'));
        $this->assertTrue($reflection->getMethod('config')->isPublic());
    }
}

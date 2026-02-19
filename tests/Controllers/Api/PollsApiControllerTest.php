<?php
// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

declare(strict_types=1);

namespace Nexus\Tests\Controllers\Api;

use PHPUnit\Framework\TestCase;
use Nexus\Controllers\Api\PollsApiController;

class PollsApiControllerTest extends TestCase
{
    public function testControllerClassExists(): void
    {
        $this->assertTrue(class_exists(PollsApiController::class));
    }

    public function testExtendsBaseApiController(): void
    {
        $reflection = new \ReflectionClass(PollsApiController::class);
        $this->assertTrue($reflection->isSubclassOf(\Nexus\Controllers\Api\BaseApiController::class));
    }

    public function testHasCrudMethods(): void
    {
        $reflection = new \ReflectionClass(PollsApiController::class);
        $methods = ['index', 'show', 'store', 'update', 'destroy'];

        foreach ($methods as $methodName) {
            $this->assertTrue($reflection->hasMethod($methodName), "Should have {$methodName}");
            $this->assertTrue($reflection->getMethod($methodName)->isPublic());
        }
    }

    public function testHasVoteMethod(): void
    {
        $reflection = new \ReflectionClass(PollsApiController::class);
        $this->assertTrue($reflection->hasMethod('vote'));
        $method = $reflection->getMethod('vote');
        $this->assertTrue($method->isPublic());
        $params = $method->getParameters();
        $this->assertCount(1, $params);
        $this->assertEquals('id', $params[0]->getName());
    }
}

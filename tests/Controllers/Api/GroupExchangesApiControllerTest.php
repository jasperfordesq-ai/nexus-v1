<?php
// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

declare(strict_types=1);

namespace Nexus\Tests\Controllers\Api;

use PHPUnit\Framework\TestCase;
use Nexus\Controllers\Api\GroupExchangesApiController;

class GroupExchangesApiControllerTest extends TestCase
{
    public function testControllerClassExists(): void
    {
        $this->assertTrue(class_exists(GroupExchangesApiController::class));
    }

    public function testExtendsBaseApiController(): void
    {
        $reflection = new \ReflectionClass(GroupExchangesApiController::class);
        $this->assertTrue($reflection->isSubclassOf(\Nexus\Controllers\Api\BaseApiController::class));
    }

    public function testHasCrudMethods(): void
    {
        $reflection = new \ReflectionClass(GroupExchangesApiController::class);
        $methods = ['index', 'show', 'store', 'update', 'destroy'];

        foreach ($methods as $methodName) {
            $this->assertTrue($reflection->hasMethod($methodName), "Should have {$methodName}");
            $this->assertTrue($reflection->getMethod($methodName)->isPublic());
        }
    }

    public function testHasParticipantMethods(): void
    {
        $reflection = new \ReflectionClass(GroupExchangesApiController::class);
        $this->assertTrue($reflection->hasMethod('addParticipant'));
        $this->assertTrue($reflection->hasMethod('removeParticipant'));

        $method = $reflection->getMethod('removeParticipant');
        $params = $method->getParameters();
        $this->assertCount(2, $params);
        $this->assertEquals('id', $params[0]->getName());
        $this->assertEquals('userId', $params[1]->getName());
    }

    public function testHasWorkflowMethods(): void
    {
        $reflection = new \ReflectionClass(GroupExchangesApiController::class);
        $this->assertTrue($reflection->hasMethod('confirm'));
        $this->assertTrue($reflection->hasMethod('complete'));
    }
}

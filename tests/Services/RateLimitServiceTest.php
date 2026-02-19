<?php
// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

namespace Tests\Services;

use PHPUnit\Framework\TestCase;
use Nexus\Services\RateLimitService;

class RateLimitServiceTest extends TestCase
{
    public function testClassExists(): void
    {
        $this->assertTrue(class_exists(RateLimitService::class));
    }

    public function testPublicMethodsExist(): void
    {
        $methods = ['check', 'increment', 'remaining'];

        foreach ($methods as $method) {
            $this->assertTrue(
                method_exists(RateLimitService::class, $method),
                "Method {$method} should exist on RateLimitService"
            );
        }
    }

    public function testCheckMethodSignature(): void
    {
        $ref = new \ReflectionMethod(RateLimitService::class, 'check');
        $this->assertTrue($ref->isStatic());

        $params = $ref->getParameters();
        $this->assertCount(3, $params);
        $this->assertEquals('key', $params[0]->getName());
        $this->assertEquals('maxAttempts', $params[1]->getName());
        $this->assertEquals('windowSeconds', $params[2]->getName());
    }

    public function testIncrementMethodSignature(): void
    {
        $ref = new \ReflectionMethod(RateLimitService::class, 'increment');
        $this->assertTrue($ref->isStatic());

        $params = $ref->getParameters();
        $this->assertCount(2, $params);
        $this->assertEquals('key', $params[0]->getName());
        $this->assertEquals('windowSeconds', $params[1]->getName());
    }

    public function testRemainingMethodSignature(): void
    {
        $ref = new \ReflectionMethod(RateLimitService::class, 'remaining');
        $this->assertTrue($ref->isStatic());

        $params = $ref->getParameters();
        $this->assertCount(3, $params);
    }

    public function testCheckReturnsBool(): void
    {
        $ref = new \ReflectionMethod(RateLimitService::class, 'check');
        $returnType = $ref->getReturnType();
        $this->assertNotNull($returnType);
        $this->assertEquals('bool', $returnType->getName());
    }

    public function testRemainingReturnsInt(): void
    {
        $ref = new \ReflectionMethod(RateLimitService::class, 'remaining');
        $returnType = $ref->getReturnType();
        $this->assertNotNull($returnType);
        $this->assertEquals('int', $returnType->getName());
    }
}

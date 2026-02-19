<?php
// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

declare(strict_types=1);

namespace Nexus\Tests\Controllers\Api;

use PHPUnit\Framework\TestCase;
use Nexus\Controllers\Api\TenantBootstrapController;

class TenantBootstrapControllerTest extends TestCase
{
    public function testControllerClassExists(): void
    {
        $this->assertTrue(class_exists(TenantBootstrapController::class));
    }

    public function testExtendsBaseApiController(): void
    {
        $reflection = new \ReflectionClass(TenantBootstrapController::class);
        $this->assertTrue($reflection->isSubclassOf(\Nexus\Controllers\Api\BaseApiController::class));
    }

    public function testHasBootstrapMethod(): void
    {
        $reflection = new \ReflectionClass(TenantBootstrapController::class);
        $this->assertTrue($reflection->hasMethod('bootstrap'));
        $this->assertTrue($reflection->getMethod('bootstrap')->isPublic());
    }

    public function testHasListMethod(): void
    {
        $reflection = new \ReflectionClass(TenantBootstrapController::class);
        $this->assertTrue($reflection->hasMethod('list'));
        $this->assertTrue($reflection->getMethod('list')->isPublic());
    }

    public function testHasPlatformStatsMethod(): void
    {
        $reflection = new \ReflectionClass(TenantBootstrapController::class);
        $this->assertTrue($reflection->hasMethod('platformStats'));
        $this->assertTrue($reflection->getMethod('platformStats')->isPublic());
    }

    public function testBootstrapMethodReturnsVoid(): void
    {
        $reflection = new \ReflectionClass(TenantBootstrapController::class);
        $method = $reflection->getMethod('bootstrap');
        $returnType = $method->getReturnType();
        $this->assertNotNull($returnType);
        $this->assertEquals('void', $returnType->getName());
    }
}

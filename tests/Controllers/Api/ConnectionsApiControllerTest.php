<?php
// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

declare(strict_types=1);

namespace Nexus\Tests\Controllers\Api;

use PHPUnit\Framework\TestCase;
use Nexus\Controllers\Api\ConnectionsApiController;

class ConnectionsApiControllerTest extends TestCase
{
    public function testControllerClassExists(): void
    {
        $this->assertTrue(class_exists(ConnectionsApiController::class));
    }

    public function testExtendsBaseApiController(): void
    {
        $reflection = new \ReflectionClass(ConnectionsApiController::class);
        $this->assertTrue($reflection->isSubclassOf(\Nexus\Controllers\Api\BaseApiController::class));
    }

    public function testHasIndexMethod(): void
    {
        $reflection = new \ReflectionClass(ConnectionsApiController::class);
        $this->assertTrue($reflection->hasMethod('index'));
        $this->assertTrue($reflection->getMethod('index')->isPublic());
    }

    public function testHasPendingCountsMethod(): void
    {
        $reflection = new \ReflectionClass(ConnectionsApiController::class);
        $this->assertTrue($reflection->hasMethod('pendingCounts'));
        $this->assertTrue($reflection->getMethod('pendingCounts')->isPublic());
    }

    public function testHasStatusMethod(): void
    {
        $reflection = new \ReflectionClass(ConnectionsApiController::class);
        $this->assertTrue($reflection->hasMethod('status'));
        $method = $reflection->getMethod('status');
        $this->assertTrue($method->isPublic());
        $params = $method->getParameters();
        $this->assertCount(1, $params);
        $this->assertEquals('otherUserId', $params[0]->getName());
    }

    public function testHasRequestMethod(): void
    {
        $reflection = new \ReflectionClass(ConnectionsApiController::class);
        $this->assertTrue($reflection->hasMethod('request'));
        $this->assertTrue($reflection->getMethod('request')->isPublic());
    }

    public function testHasAcceptMethod(): void
    {
        $reflection = new \ReflectionClass(ConnectionsApiController::class);
        $this->assertTrue($reflection->hasMethod('accept'));
        $method = $reflection->getMethod('accept');
        $this->assertTrue($method->isPublic());
        $params = $method->getParameters();
        $this->assertCount(1, $params);
        $this->assertEquals('connectionId', $params[0]->getName());
    }

    public function testHasDestroyMethod(): void
    {
        $reflection = new \ReflectionClass(ConnectionsApiController::class);
        $this->assertTrue($reflection->hasMethod('destroy'));
        $method = $reflection->getMethod('destroy');
        $this->assertTrue($method->isPublic());
        $params = $method->getParameters();
        $this->assertCount(1, $params);
        $this->assertEquals('connectionId', $params[0]->getName());
    }
}

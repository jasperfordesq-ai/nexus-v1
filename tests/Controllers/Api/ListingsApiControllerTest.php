<?php
// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

declare(strict_types=1);

namespace Nexus\Tests\Controllers\Api;

use PHPUnit\Framework\TestCase;
use Nexus\Controllers\Api\ListingsApiController;

class ListingsApiControllerTest extends TestCase
{
    public function testControllerClassExists(): void
    {
        $this->assertTrue(class_exists(ListingsApiController::class));
    }

    public function testExtendsBaseApiController(): void
    {
        $reflection = new \ReflectionClass(ListingsApiController::class);
        $this->assertTrue($reflection->isSubclassOf(\Nexus\Controllers\Api\BaseApiController::class));
    }

    public function testHasIndexMethod(): void
    {
        $reflection = new \ReflectionClass(ListingsApiController::class);
        $this->assertTrue($reflection->hasMethod('index'));
        $method = $reflection->getMethod('index');
        $this->assertTrue($method->isPublic());
        $this->assertEquals(0, $method->getNumberOfParameters());
    }

    public function testHasNearbyMethod(): void
    {
        $reflection = new \ReflectionClass(ListingsApiController::class);
        $this->assertTrue($reflection->hasMethod('nearby'));
        $method = $reflection->getMethod('nearby');
        $this->assertTrue($method->isPublic());
    }

    public function testHasShowMethod(): void
    {
        $reflection = new \ReflectionClass(ListingsApiController::class);
        $this->assertTrue($reflection->hasMethod('show'));
        $method = $reflection->getMethod('show');
        $this->assertTrue($method->isPublic());
        $this->assertEquals(1, $method->getNumberOfParameters());
        $params = $method->getParameters();
        $this->assertEquals('id', $params[0]->getName());
        $this->assertEquals('int', $params[0]->getType()->getName());
    }

    public function testHasStoreMethod(): void
    {
        $reflection = new \ReflectionClass(ListingsApiController::class);
        $this->assertTrue($reflection->hasMethod('store'));
        $method = $reflection->getMethod('store');
        $this->assertTrue($method->isPublic());
    }

    public function testHasUpdateMethod(): void
    {
        $reflection = new \ReflectionClass(ListingsApiController::class);
        $this->assertTrue($reflection->hasMethod('update'));
        $method = $reflection->getMethod('update');
        $this->assertTrue($method->isPublic());
        $params = $method->getParameters();
        $this->assertEquals('id', $params[0]->getName());
    }

    public function testHasDestroyMethod(): void
    {
        $reflection = new \ReflectionClass(ListingsApiController::class);
        $this->assertTrue($reflection->hasMethod('destroy'));
        $method = $reflection->getMethod('destroy');
        $this->assertTrue($method->isPublic());
        $params = $method->getParameters();
        $this->assertEquals('id', $params[0]->getName());
    }

    public function testHasUploadImageMethod(): void
    {
        $reflection = new \ReflectionClass(ListingsApiController::class);
        $this->assertTrue($reflection->hasMethod('uploadImage'));
        $method = $reflection->getMethod('uploadImage');
        $this->assertTrue($method->isPublic());
        $params = $method->getParameters();
        $this->assertEquals('id', $params[0]->getName());
    }

    public function testAllMethodsReturnVoid(): void
    {
        $reflection = new \ReflectionClass(ListingsApiController::class);
        $methods = ['index', 'nearby', 'show', 'store', 'update', 'destroy', 'uploadImage'];

        foreach ($methods as $methodName) {
            $method = $reflection->getMethod($methodName);
            $returnType = $method->getReturnType();
            $this->assertNotNull($returnType, "Method {$methodName} should have a return type");
            $this->assertEquals('void', $returnType->getName(), "Method {$methodName} should return void");
        }
    }
}

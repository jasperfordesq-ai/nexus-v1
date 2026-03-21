<?php
// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

namespace App\Tests\Services;

use App\Models\Category;
use App\Services\CategoryService;
use App\Tests\TestCase;
use Illuminate\Database\Eloquent\Collection;

class CategoryServiceTest extends TestCase
{
    private CategoryService $service;

    protected function setUp(): void
    {
        parent::setUp();
        $this->service = new CategoryService(new Category());
    }

    public function testClassExists(): void
    {
        $this->assertTrue(class_exists(CategoryService::class));
    }

    public function testGetByTypeReturnsCollection(): void
    {
        $result = $this->service->getByType('listing');
        $this->assertInstanceOf(Collection::class, $result);
    }

    public function testGetByTypeAcceptsVariousTypes(): void
    {
        $types = ['listing', 'event', 'blog', 'resource'];
        foreach ($types as $type) {
            $result = $this->service->getByType($type);
            $this->assertInstanceOf(Collection::class, $result);
        }
    }

    public function testGetAllReturnsCollection(): void
    {
        $result = $this->service->getAll();
        $this->assertInstanceOf(Collection::class, $result);
    }

    public function testConstructorAcceptsCategoryModel(): void
    {
        $ref = new \ReflectionClass(CategoryService::class);
        $constructor = $ref->getConstructor();
        $this->assertNotNull($constructor);
        $params = $constructor->getParameters();
        $this->assertCount(1, $params);
        $this->assertSame('category', $params[0]->getName());
    }

    public function testGetByTypeMethodSignature(): void
    {
        $ref = new \ReflectionMethod(CategoryService::class, 'getByType');
        $params = $ref->getParameters();
        $this->assertCount(1, $params);
        $this->assertSame('type', $params[0]->getName());
    }

    public function testGetAllMethodSignature(): void
    {
        $ref = new \ReflectionMethod(CategoryService::class, 'getAll');
        $params = $ref->getParameters();
        $this->assertCount(0, $params);
    }
}

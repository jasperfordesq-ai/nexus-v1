<?php
// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

namespace App\Tests\Services;

use App\Tests\TestCase;
use App\Services\PageService;
use App\Models\Page;

class PageServiceTest extends TestCase
{
    public function testClassExists(): void
    {
        $this->assertTrue(class_exists(PageService::class));
    }

    public function testCanBeInstantiatedWithPageModel(): void
    {
        $page = new Page();
        $service = new PageService($page);
        $this->assertInstanceOf(PageService::class, $service);
    }

    public function testGetBySlugMethodExists(): void
    {
        $this->assertTrue(method_exists(PageService::class, 'getBySlug'));
    }

    public function testGetBySlugReturnTypeIsNullablePage(): void
    {
        $ref = new \ReflectionMethod(PageService::class, 'getBySlug');
        $returnType = $ref->getReturnType();
        $this->assertNotNull($returnType);
        $this->assertTrue($returnType->allowsNull());
    }

    public function testGetBySlugAcceptsStringParameter(): void
    {
        $ref = new \ReflectionMethod(PageService::class, 'getBySlug');
        $params = $ref->getParameters();
        $this->assertCount(1, $params);
        $this->assertEquals('slug', $params[0]->getName());
        $this->assertEquals('string', $params[0]->getType()->getName());
    }
}

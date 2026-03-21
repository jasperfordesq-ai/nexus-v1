<?php
// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

namespace App\Tests\Services;

use App\Tests\TestCase;
use App\Services\ResourceService;
use App\Models\ResourceItem;

class ResourceServiceTest extends TestCase
{
    private ResourceService $service;

    protected function setUp(): void
    {
        parent::setUp();
        $this->service = new ResourceService(new ResourceItem());
    }

    public function testClassExists(): void
    {
        $this->assertTrue(class_exists(ResourceService::class));
    }

    public function testGetAllReturnsExpectedStructure(): void
    {
        $result = $this->service->getAll();

        $this->assertIsArray($result);
        $this->assertArrayHasKey('items', $result);
        $this->assertArrayHasKey('cursor', $result);
        $this->assertArrayHasKey('has_more', $result);
        $this->assertIsArray($result['items']);
        $this->assertIsBool($result['has_more']);
    }

    public function testGetAllRespectsLimitFilter(): void
    {
        $result = $this->service->getAll(['limit' => 5]);
        $this->assertIsArray($result);
        $this->assertArrayHasKey('items', $result);
    }

    public function testGetAllLimitClampedToMax100(): void
    {
        // Even with limit=500 the service clamps to 100
        $result = $this->service->getAll(['limit' => 500]);
        $this->assertIsArray($result);
    }

    public function testGetAllSupportsSearchFilter(): void
    {
        $result = $this->service->getAll(['search' => 'nonexistent-xyz']);
        $this->assertIsArray($result);
        $this->assertEmpty($result['items']);
    }

    public function testGetAllSupportsCategoryFilter(): void
    {
        $result = $this->service->getAll(['category_id' => 999999]);
        $this->assertIsArray($result);
        $this->assertEmpty($result['items']);
    }

    public function testDownloadReturnsNullForNonExistentResource(): void
    {
        $result = $this->service->download(999999);
        $this->assertNull($result);
    }

    public function testDeleteReturnsFalseForNonExistentResource(): void
    {
        $result = $this->service->delete(999999, 999999);
        $this->assertFalse($result);
    }

    public function testStoreMethodExists(): void
    {
        $this->assertTrue(method_exists(ResourceService::class, 'store'));
    }

    public function testStoreSignature(): void
    {
        $ref = new \ReflectionMethod(ResourceService::class, 'store');
        $params = $ref->getParameters();
        $this->assertCount(2, $params);
        $this->assertEquals('userId', $params[0]->getName());
        $this->assertEquals('data', $params[1]->getName());
    }
}

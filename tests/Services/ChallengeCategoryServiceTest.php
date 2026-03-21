<?php
// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

namespace App\Tests\Services;

use App\Services\ChallengeCategoryService;
use App\Tests\TestCase;

class ChallengeCategoryServiceTest extends TestCase
{
    private ChallengeCategoryService $service;

    protected function setUp(): void
    {
        parent::setUp();
        $this->service = new ChallengeCategoryService();
    }

    public function testClassExists(): void
    {
        $this->assertTrue(class_exists(ChallengeCategoryService::class));
    }

    public function testGetAllReturnsArray(): void
    {
        $result = $this->service->getAll();
        $this->assertIsArray($result);
    }

    public function testGetByIdReturnsNullForNonExistent(): void
    {
        $result = $this->service->getById(999999);
        $this->assertNull($result);
    }

    public function testCreateReturnsNullForNonAdmin(): void
    {
        $result = $this->service->create(999999, ['name' => 'Test Category']);
        $this->assertNull($result);
        $errors = $this->service->getErrors();
        $this->assertNotEmpty($errors);
        $this->assertSame('RESOURCE_FORBIDDEN', $errors[0]['code']);
    }

    public function testCreateRequiresName(): void
    {
        // Non-admin check comes first, so we test that error
        $result = $this->service->create(999999, ['name' => '']);
        $this->assertNull($result);
    }

    public function testUpdateReturnsFalseForNonAdmin(): void
    {
        $result = $this->service->update(1, 999999, ['name' => 'Updated']);
        $this->assertFalse($result);
        $errors = $this->service->getErrors();
        $this->assertSame('RESOURCE_FORBIDDEN', $errors[0]['code']);
    }

    public function testDeleteReturnsFalseForNonAdmin(): void
    {
        $result = $this->service->delete(1, 999999);
        $this->assertFalse($result);
    }

    public function testGetErrorsReturnsEmptyArrayInitially(): void
    {
        $service = new ChallengeCategoryService();
        $this->assertEmpty($service->getErrors());
    }

    public function testErrorsClearedBetweenOperations(): void
    {
        $this->service->create(999999, ['name' => 'Test']);
        $this->assertNotEmpty($this->service->getErrors());

        $this->service->create(999999, ['name' => 'Test 2']);
        $this->assertCount(1, $this->service->getErrors());
    }

    public function testGenerateSlugPrivateMethod(): void
    {
        $slug = $this->callPrivateMethod($this->service, 'generateSlug', ['Hello World!']);
        $this->assertSame('hello-world', $slug);

        $slug = $this->callPrivateMethod($this->service, 'generateSlug', ['Test & More']);
        $this->assertSame('test--more', $slug);

        $slug = $this->callPrivateMethod($this->service, 'generateSlug', ['simple']);
        $this->assertSame('simple', $slug);
    }

    public function testGenerateSlugHandlesSpecialCharacters(): void
    {
        $slug = $this->callPrivateMethod($this->service, 'generateSlug', ['Café & Restaurant']);
        $this->assertIsString($slug);
        $this->assertStringNotContainsString(' ', $slug);
    }

    public function testIsAdminReturnsFalseForNonExistentUser(): void
    {
        $result = $this->callPrivateMethod($this->service, 'isAdmin', [999999]);
        $this->assertFalse($result);
    }
}

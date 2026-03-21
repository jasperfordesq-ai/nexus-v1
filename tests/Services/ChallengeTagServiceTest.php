<?php
// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

namespace App\Tests\Services;

use App\Services\ChallengeTagService;
use App\Tests\TestCase;

class ChallengeTagServiceTest extends TestCase
{
    private ChallengeTagService $service;

    protected function setUp(): void
    {
        parent::setUp();
        $this->service = new ChallengeTagService();
    }

    public function testClassExists(): void
    {
        $this->assertTrue(class_exists(ChallengeTagService::class));
    }

    public function testGetAllReturnsArray(): void
    {
        $result = $this->service->getAll();
        $this->assertIsArray($result);
    }

    public function testGetAllWithTypeFilter(): void
    {
        $types = ['interest', 'skill', 'general'];
        foreach ($types as $type) {
            $result = $this->service->getAll($type);
            $this->assertIsArray($result);
        }
    }

    public function testGetAllIgnoresInvalidType(): void
    {
        // Invalid type should be ignored (no filter applied)
        $result = $this->service->getAll('invalid_type');
        $this->assertIsArray($result);
    }

    public function testGetAllWithNullType(): void
    {
        $result = $this->service->getAll(null);
        $this->assertIsArray($result);
    }

    public function testGetByIdReturnsNullForNonExistent(): void
    {
        $result = $this->service->getById(999999);
        $this->assertNull($result);
    }

    public function testCreateReturnsNullForNonAdmin(): void
    {
        $result = $this->service->create(999999, ['name' => 'Test Tag']);
        $this->assertNull($result);
        $errors = $this->service->getErrors();
        $this->assertNotEmpty($errors);
        $this->assertSame('RESOURCE_FORBIDDEN', $errors[0]['code']);
    }

    public function testCreateRequiresName(): void
    {
        $result = $this->service->create(999999, ['name' => '']);
        $this->assertNull($result);
    }

    public function testDeleteReturnsFalseForNonAdmin(): void
    {
        $result = $this->service->delete(1, 999999);
        $this->assertFalse($result);
        $errors = $this->service->getErrors();
        $this->assertSame('RESOURCE_FORBIDDEN', $errors[0]['code']);
    }

    public function testGetErrorsReturnsEmptyArrayInitially(): void
    {
        $service = new ChallengeTagService();
        $this->assertEmpty($service->getErrors());
    }

    public function testErrorsClearedBetweenCalls(): void
    {
        $this->service->create(999999, ['name' => 'Test']);
        $this->assertNotEmpty($this->service->getErrors());

        $this->service->delete(1, 999999);
        $this->assertCount(1, $this->service->getErrors());
    }

    public function testGenerateSlugPrivateMethod(): void
    {
        $slug = $this->callPrivateMethod($this->service, 'generateSlug', ['Hello World!']);
        $this->assertSame('hello-world', $slug);

        $slug = $this->callPrivateMethod($this->service, 'generateSlug', ['simple']);
        $this->assertSame('simple', $slug);

        $slug = $this->callPrivateMethod($this->service, 'generateSlug', ['Multiple   Spaces']);
        $this->assertSame('multiple-spaces', $slug);
    }

    public function testIsAdminReturnsFalseForNonExistentUser(): void
    {
        $result = $this->callPrivateMethod($this->service, 'isAdmin', [999999]);
        $this->assertFalse($result);
    }
}

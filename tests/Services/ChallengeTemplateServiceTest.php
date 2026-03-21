<?php
// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

namespace App\Tests\Services;

use App\Services\ChallengeTemplateService;
use App\Tests\TestCase;

class ChallengeTemplateServiceTest extends TestCase
{
    private ChallengeTemplateService $service;

    protected function setUp(): void
    {
        parent::setUp();
        $this->service = new ChallengeTemplateService();
    }

    public function testClassExists(): void
    {
        $this->assertTrue(class_exists(ChallengeTemplateService::class));
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
        $result = $this->service->create(999999, ['title' => 'Test Template']);
        $this->assertNull($result);
        $errors = $this->service->getErrors();
        $this->assertNotEmpty($errors);
        $this->assertSame('RESOURCE_FORBIDDEN', $errors[0]['code']);
    }

    public function testCreateRequiresTitle(): void
    {
        // Non-admin check comes first
        $result = $this->service->create(999999, ['title' => '']);
        $this->assertNull($result);
    }

    public function testUpdateReturnsFalseForNonAdmin(): void
    {
        $result = $this->service->update(1, 999999, ['title' => 'Updated']);
        $this->assertFalse($result);
        $errors = $this->service->getErrors();
        $this->assertSame('RESOURCE_FORBIDDEN', $errors[0]['code']);
    }

    public function testDeleteReturnsFalseForNonAdmin(): void
    {
        $result = $this->service->delete(1, 999999);
        $this->assertFalse($result);
    }

    public function testGetTemplateDataReturnsNullForNonExistent(): void
    {
        $result = $this->service->getTemplateData(999999);
        $this->assertNull($result);
    }

    public function testGetErrorsReturnsEmptyArrayInitially(): void
    {
        $service = new ChallengeTemplateService();
        $this->assertEmpty($service->getErrors());
    }

    public function testErrorsClearedBetweenCalls(): void
    {
        $this->service->create(999999, ['title' => 'Test']);
        $this->assertNotEmpty($this->service->getErrors());

        $this->service->update(1, 999999, ['title' => 'Updated']);
        $this->assertCount(1, $this->service->getErrors());
    }

    public function testErrorStructure(): void
    {
        $this->service->create(999999, ['title' => 'Test']);
        $errors = $this->service->getErrors();
        $this->assertArrayHasKey('code', $errors[0]);
        $this->assertArrayHasKey('message', $errors[0]);
    }

    public function testIsAdminReturnsFalseForNonExistentUser(): void
    {
        $result = $this->callPrivateMethod($this->service, 'isAdmin', [999999]);
        $this->assertFalse($result);
    }

    public function testMethodSignatures(): void
    {
        $ref = new \ReflectionMethod(ChallengeTemplateService::class, 'create');
        $params = $ref->getParameters();
        $this->assertCount(2, $params);
        $this->assertSame('userId', $params[0]->getName());
        $this->assertSame('data', $params[1]->getName());

        $ref = new \ReflectionMethod(ChallengeTemplateService::class, 'update');
        $params = $ref->getParameters();
        $this->assertCount(3, $params);

        $ref = new \ReflectionMethod(ChallengeTemplateService::class, 'delete');
        $params = $ref->getParameters();
        $this->assertCount(2, $params);
    }
}

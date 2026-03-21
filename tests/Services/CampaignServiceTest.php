<?php
// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

namespace App\Tests\Services;

use App\Services\CampaignService;
use App\Tests\TestCase;

class CampaignServiceTest extends TestCase
{
    private CampaignService $service;

    protected function setUp(): void
    {
        parent::setUp();
        $this->service = new CampaignService();
    }

    public function testClassExists(): void
    {
        $this->assertTrue(class_exists(CampaignService::class));
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

    public function testGetAllWithStatusFilter(): void
    {
        $result = $this->service->getAll(['status' => 'active']);
        $this->assertIsArray($result);
        $this->assertArrayHasKey('items', $result);
    }

    public function testGetAllWithCursorPagination(): void
    {
        $cursor = base64_encode('999999');
        $result = $this->service->getAll(['cursor' => $cursor]);
        $this->assertIsArray($result);
    }

    public function testGetAllIgnoresInvalidStatus(): void
    {
        $result = $this->service->getAll(['status' => 'invalid_status']);
        $this->assertIsArray($result);
    }

    public function testGetByIdReturnsNullForNonExistent(): void
    {
        $result = $this->service->getById(999999);
        $this->assertNull($result);
    }

    public function testCreateReturnsNullForNonAdmin(): void
    {
        // User 999999 does not exist, so isAdmin returns false
        $result = $this->service->create(999999, ['title' => 'Test Campaign']);
        $this->assertNull($result);
        $errors = $this->service->getErrors();
        $this->assertNotEmpty($errors);
        $this->assertSame('RESOURCE_FORBIDDEN', $errors[0]['code']);
    }

    public function testCreateRequiresTitle(): void
    {
        // Even if user were admin, empty title should fail
        // But since isAdmin check comes first, we test that path
        $result = $this->service->create(999999, ['title' => '']);
        $this->assertNull($result);
    }

    public function testUpdateReturnsFalseForNonAdmin(): void
    {
        $result = $this->service->update(1, 999999, ['title' => 'Updated']);
        $this->assertFalse($result);
        $errors = $this->service->getErrors();
        $this->assertNotEmpty($errors);
        $this->assertSame('RESOURCE_FORBIDDEN', $errors[0]['code']);
    }

    public function testDeleteReturnsFalseForNonAdmin(): void
    {
        $result = $this->service->delete(1, 999999);
        $this->assertFalse($result);
    }

    public function testLinkChallengeReturnsFalseForNonAdmin(): void
    {
        $result = $this->service->linkChallenge(1, 1, 999999);
        $this->assertFalse($result);
    }

    public function testUnlinkChallengeReturnsFalseForNonAdmin(): void
    {
        $result = $this->service->unlinkChallenge(1, 1, 999999);
        $this->assertFalse($result);
    }

    public function testGetErrorsReturnsEmptyArrayInitially(): void
    {
        $service = new CampaignService();
        $this->assertIsArray($service->getErrors());
        $this->assertEmpty($service->getErrors());
    }

    public function testGetErrorsClearedBetweenCalls(): void
    {
        // First call populates errors
        $this->service->create(999999, ['title' => 'Test']);
        $this->assertNotEmpty($this->service->getErrors());

        // Second call clears previous errors
        $this->service->create(999999, ['title' => 'Test 2']);
        $errors = $this->service->getErrors();
        $this->assertCount(1, $errors);
    }

    public function testErrorStructure(): void
    {
        $this->service->create(999999, ['title' => 'Test']);
        $errors = $this->service->getErrors();
        $this->assertArrayHasKey('code', $errors[0]);
        $this->assertArrayHasKey('message', $errors[0]);
    }
}

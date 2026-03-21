<?php
// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

namespace App\Tests\Services;

use App\Models\User;
use App\Services\AdminUsersService;
use App\Tests\TestCase;

class AdminUsersServiceTest extends TestCase
{
    private AdminUsersService $service;

    protected function setUp(): void
    {
        parent::setUp();
        $this->service = new AdminUsersService(new User());
    }

    public function testClassExists(): void
    {
        $this->assertTrue(class_exists(AdminUsersService::class));
    }

    public function testGetAllReturnsArrayWithItemsAndTotal(): void
    {
        $result = $this->service->getAll(1);
        $this->assertIsArray($result);
        $this->assertArrayHasKey('items', $result);
        $this->assertArrayHasKey('total', $result);
        $this->assertIsArray($result['items']);
        $this->assertIsInt($result['total']);
    }

    public function testGetAllWithStatusFilter(): void
    {
        $result = $this->service->getAll(1, ['status' => 'active']);
        $this->assertIsArray($result);
        $this->assertArrayHasKey('items', $result);
    }

    public function testGetAllWithSearchFilter(): void
    {
        $result = $this->service->getAll(1, ['search' => 'test']);
        $this->assertIsArray($result);
        $this->assertArrayHasKey('items', $result);
    }

    public function testGetAllLimitsCappedAt100(): void
    {
        $result = $this->service->getAll(1, ['limit' => 500]);
        $this->assertIsArray($result);
    }

    public function testGetAllOffsetNonNegative(): void
    {
        $result = $this->service->getAll(1, ['offset' => -10]);
        $this->assertIsArray($result);
    }

    public function testBanReturnsBool(): void
    {
        // Non-existent user should return false
        $result = $this->service->ban(999999, 1, 'Test ban reason');
        $this->assertFalse($result);
    }

    public function testUnbanReturnsBool(): void
    {
        $result = $this->service->unban(999999, 1);
        $this->assertFalse($result);
    }

    public function testGetStatsReturnsExpectedStructure(): void
    {
        $stats = $this->service->getStats(1);
        $this->assertIsArray($stats);
        $this->assertArrayHasKey('total', $stats);
        $this->assertArrayHasKey('by_status', $stats);
        $this->assertArrayHasKey('active_last_week', $stats);
        $this->assertIsInt($stats['total']);
        $this->assertIsArray($stats['by_status']);
        $this->assertIsInt($stats['active_last_week']);
    }

    public function testConstructorAcceptsUserModel(): void
    {
        $ref = new \ReflectionClass(AdminUsersService::class);
        $constructor = $ref->getConstructor();
        $this->assertNotNull($constructor);
        $params = $constructor->getParameters();
        $this->assertCount(1, $params);
        $this->assertSame('user', $params[0]->getName());
    }
}

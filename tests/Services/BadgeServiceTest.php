<?php
// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

namespace App\Tests\Services;

use App\Models\UserBadge;
use App\Services\BadgeService;
use App\Tests\TestCase;

class BadgeServiceTest extends TestCase
{
    private BadgeService $service;

    protected function setUp(): void
    {
        parent::setUp();
        $this->service = new BadgeService(new UserBadge());
    }

    public function testClassExists(): void
    {
        $this->assertTrue(class_exists(BadgeService::class));
    }

    public function testGetAllReturnsArray(): void
    {
        $result = $this->service->getAll(1);
        $this->assertIsArray($result);
    }

    public function testGetAllReturnsArrayOfArrays(): void
    {
        $result = $this->service->getAll(1);
        foreach ($result as $item) {
            $this->assertIsArray($item);
        }
    }

    public function testAwardReturnsBool(): void
    {
        $result = $this->service->award(999999, 999999, 1);
        $this->assertIsBool($result);
    }

    public function testRevokeReturnsFalseForNonExistentBadge(): void
    {
        $result = $this->service->revoke(999999, 999999, 1);
        $this->assertFalse($result);
    }

    public function testGetUserBadgesReturnsArray(): void
    {
        $result = $this->service->getUserBadges(999999, 1);
        $this->assertIsArray($result);
    }

    public function testGetUserBadgesReturnsEmptyForNonExistentUser(): void
    {
        $result = $this->service->getUserBadges(999999, 1);
        $this->assertEmpty($result);
    }

    public function testConstructorAcceptsUserBadgeModel(): void
    {
        $ref = new \ReflectionClass(BadgeService::class);
        $constructor = $ref->getConstructor();
        $this->assertNotNull($constructor);
        $params = $constructor->getParameters();
        $this->assertCount(1, $params);
        $this->assertSame('userBadge', $params[0]->getName());
    }

    public function testAwardMethodSignature(): void
    {
        $ref = new \ReflectionMethod(BadgeService::class, 'award');
        $params = $ref->getParameters();
        $this->assertCount(4, $params);
        $this->assertSame('userId', $params[0]->getName());
        $this->assertSame('badgeId', $params[1]->getName());
        $this->assertSame('tenantId', $params[2]->getName());
        $this->assertSame('awardedBy', $params[3]->getName());
        $this->assertTrue($params[3]->isOptional());
    }
}

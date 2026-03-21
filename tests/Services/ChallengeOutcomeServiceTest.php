<?php
// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

namespace App\Tests\Services;

use App\Services\ChallengeOutcomeService;
use App\Tests\TestCase;

class ChallengeOutcomeServiceTest extends TestCase
{
    private ChallengeOutcomeService $service;

    protected function setUp(): void
    {
        parent::setUp();
        $this->service = new ChallengeOutcomeService();
    }

    public function testClassExists(): void
    {
        $this->assertTrue(class_exists(ChallengeOutcomeService::class));
    }

    public function testGetForChallengeReturnsNullForNonExistent(): void
    {
        $result = $this->service->getForChallenge(999999);
        $this->assertNull($result);
    }

    public function testUpsertReturnsNullForNonAdmin(): void
    {
        $result = $this->service->upsert(1, 999999, ['status' => 'not_started']);
        $this->assertNull($result);
        $errors = $this->service->getErrors();
        $this->assertNotEmpty($errors);
        $this->assertSame('RESOURCE_FORBIDDEN', $errors[0]['code']);
    }

    public function testGetDashboardReturnsExpectedStructure(): void
    {
        $result = $this->service->getDashboard();
        $this->assertIsArray($result);
        $this->assertArrayHasKey('outcomes', $result);
        $this->assertArrayHasKey('stats', $result);
        $this->assertIsArray($result['outcomes']);
        $this->assertIsArray($result['stats']);
    }

    public function testGetDashboardStatsHaveExpectedKeys(): void
    {
        $result = $this->service->getDashboard();
        $stats = $result['stats'];
        $this->assertArrayHasKey('total', $stats);
        $this->assertArrayHasKey('implemented', $stats);
        $this->assertArrayHasKey('in_progress', $stats);
        $this->assertArrayHasKey('not_started', $stats);
        $this->assertArrayHasKey('abandoned', $stats);
    }

    public function testGetDashboardStatsAreIntegers(): void
    {
        $result = $this->service->getDashboard();
        foreach ($result['stats'] as $key => $value) {
            $this->assertIsInt($value, "Stats key '{$key}' should be int");
        }
    }

    public function testGetErrorsReturnsEmptyArrayInitially(): void
    {
        $service = new ChallengeOutcomeService();
        $this->assertEmpty($service->getErrors());
    }

    public function testErrorsClearedBetweenCalls(): void
    {
        $this->service->upsert(1, 999999, []);
        $this->assertNotEmpty($this->service->getErrors());

        $this->service->upsert(2, 999999, []);
        $this->assertCount(1, $this->service->getErrors());
    }

    public function testErrorStructure(): void
    {
        $this->service->upsert(1, 999999, []);
        $errors = $this->service->getErrors();
        $this->assertArrayHasKey('code', $errors[0]);
        $this->assertArrayHasKey('message', $errors[0]);
    }

    public function testIsAdminReturnsFalseForNonExistentUser(): void
    {
        $result = $this->callPrivateMethod($this->service, 'isAdmin', [999999]);
        $this->assertFalse($result);
    }
}

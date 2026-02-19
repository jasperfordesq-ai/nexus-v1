<?php
// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

namespace Tests\Services;

use PHPUnit\Framework\TestCase;
use Nexus\Services\NexusScoreService;

class NexusScoreServiceTest extends TestCase
{
    public function testClassExists(): void
    {
        $this->assertTrue(class_exists(NexusScoreService::class));
    }

    public function testScoreCategoryConstants(): void
    {
        $this->assertEquals(250, NexusScoreService::MAX_ENGAGEMENT);
        $this->assertEquals(200, NexusScoreService::MAX_QUALITY);
        $this->assertEquals(200, NexusScoreService::MAX_VOLUNTEER);
        $this->assertEquals(150, NexusScoreService::MAX_ACTIVITY);
        $this->assertEquals(100, NexusScoreService::MAX_BADGES);
        $this->assertEquals(100, NexusScoreService::MAX_IMPACT);
    }

    public function testMaxScoreTotals1000(): void
    {
        $total = NexusScoreService::MAX_ENGAGEMENT
               + NexusScoreService::MAX_QUALITY
               + NexusScoreService::MAX_VOLUNTEER
               + NexusScoreService::MAX_ACTIVITY
               + NexusScoreService::MAX_BADGES
               + NexusScoreService::MAX_IMPACT;

        $this->assertEquals(1000, $total, 'Total max score should be 1000');
    }

    public function testCalculateNexusScoreMethodExists(): void
    {
        $this->assertTrue(method_exists(NexusScoreService::class, 'calculateNexusScore'));
    }

    public function testConstructorRequiresPDO(): void
    {
        $ref = new \ReflectionClass(NexusScoreService::class);
        $constructor = $ref->getConstructor();
        $this->assertNotNull($constructor);

        $params = $constructor->getParameters();
        $this->assertCount(1, $params);
        $this->assertEquals('db', $params[0]->getName());
    }
}

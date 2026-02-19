<?php
// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

namespace Tests\Services;

use PHPUnit\Framework\TestCase;
use Nexus\Services\RankingService;

class RankingServiceTest extends TestCase
{
    public function testClassExists(): void
    {
        $this->assertTrue(class_exists(RankingService::class));
    }

    public function testDefaultScoreConstant(): void
    {
        $this->assertEquals(1.0, RankingService::DEFAULT_SCORE);
    }

    public function testEarthRadiusConstant(): void
    {
        $this->assertEquals(6371, RankingService::EARTH_RADIUS_KM);
    }

    public function testGetSharedConfigMethodExists(): void
    {
        $this->assertTrue(method_exists(RankingService::class, 'getSharedConfig'));
    }

    public function testGetSharedConfigIsStatic(): void
    {
        $ref = new \ReflectionMethod(RankingService::class, 'getSharedConfig');
        $this->assertTrue($ref->isStatic());
    }
}

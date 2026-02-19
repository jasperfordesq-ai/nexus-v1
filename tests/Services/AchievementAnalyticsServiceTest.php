<?php
// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

namespace Tests\Services;

use PHPUnit\Framework\TestCase;
use Nexus\Services\AchievementAnalyticsService;

class AchievementAnalyticsServiceTest extends TestCase
{
    public function testClassExists(): void
    {
        $this->assertTrue(class_exists(AchievementAnalyticsService::class));
    }

    public function testGetOverallStatsMethodExists(): void
    {
        $this->assertTrue(method_exists(AchievementAnalyticsService::class, 'getOverallStats'));
        $ref = new \ReflectionMethod(AchievementAnalyticsService::class, 'getOverallStats');
        $this->assertTrue($ref->isStatic());
    }
}

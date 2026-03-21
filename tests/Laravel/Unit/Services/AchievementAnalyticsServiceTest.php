<?php
// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

namespace Tests\Laravel\Unit\Services;

use Tests\Laravel\TestCase;
use App\Services\AchievementAnalyticsService;
use Illuminate\Support\Facades\DB;

class AchievementAnalyticsServiceTest extends TestCase
{
    private AchievementAnalyticsService $service;

    protected function setUp(): void
    {
        parent::setUp();
        $this->service = new AchievementAnalyticsService();
    }

    public function test_getOverallStats_returns_expected_structure(): void
    {
        $this->markTestIncomplete('Requires integration test — heavy DB query builder mocking needed');
    }

    public function test_getBadgeTrends_returns_array(): void
    {
        $this->markTestIncomplete('Requires integration test — heavy DB query builder mocking needed');
    }

    public function test_getPopularBadges_returns_array_with_default_limit(): void
    {
        $this->markTestIncomplete('Requires integration test — heavy DB query builder mocking needed');
    }

    public function test_getRarestBadges_returns_array(): void
    {
        $this->markTestIncomplete('Requires integration test — heavy DB query builder mocking needed');
    }

    public function test_getTopEarners_returns_array(): void
    {
        $this->markTestIncomplete('Requires integration test — heavy DB query builder mocking needed');
    }
}

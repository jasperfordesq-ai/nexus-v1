<?php
// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

namespace Tests\Laravel\Unit\Services;

use Tests\Laravel\TestCase;
use App\Services\GamificationService;

class GamificationServiceTest extends TestCase
{
    public function test_xp_values_are_defined(): void
    {
        $this->assertArrayHasKey('send_credits', GamificationService::XP_VALUES);
        $this->assertArrayHasKey('daily_login', GamificationService::XP_VALUES);
        $this->assertArrayHasKey('complete_transaction', GamificationService::XP_VALUES);
        $this->assertArrayHasKey('create_listing', GamificationService::XP_VALUES);
        $this->assertEquals(5, GamificationService::XP_VALUES['daily_login']);
        $this->assertEquals(25, GamificationService::XP_VALUES['complete_transaction']);
    }

    public function test_level_thresholds_are_progressive(): void
    {
        $thresholds = GamificationService::LEVEL_THRESHOLDS;
        $this->assertEquals(0, $thresholds[1]);

        $prev = -1;
        foreach ($thresholds as $level => $xp) {
            $this->assertGreaterThan($prev, $xp, "Level {$level} threshold should be greater than previous");
            $prev = $xp;
        }
    }

    public function test_level_thresholds_has_10_levels(): void
    {
        $this->assertCount(10, GamificationService::LEVEL_THRESHOLDS);
    }

    // Static methods use Eloquent models extensively
    public function test_getProfile_requires_integration_test(): void
    {
        $this->markTestIncomplete('Requires integration test with User model and HasTenantScope');
    }
}

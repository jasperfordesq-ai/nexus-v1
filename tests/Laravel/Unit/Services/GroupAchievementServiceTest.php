<?php
// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

namespace Tests\Laravel\Unit\Services;

use Tests\Laravel\TestCase;
use App\Services\GroupAchievementService;

class GroupAchievementServiceTest extends TestCase
{
    public function test_group_achievements_constants_are_defined(): void
    {
        $achievements = GroupAchievementService::GROUP_ACHIEVEMENTS;
        $this->assertArrayHasKey('community_builders', $achievements);
        $this->assertArrayHasKey('active_hub', $achievements);
        $this->assertArrayHasKey('event_masters', $achievements);
        $this->assertArrayHasKey('first_steps', $achievements);
        $this->assertArrayHasKey('discussion_starters', $achievements);
    }

    public function test_community_builders_requires_50_members(): void
    {
        $achievement = GroupAchievementService::GROUP_ACHIEVEMENTS['community_builders'];
        $this->assertEquals('member_count', $achievement['target_type']);
        $this->assertEquals(50, $achievement['target_value']);
        $this->assertEquals(500, $achievement['xp_reward']);
    }

    public function test_first_steps_requires_10_members(): void
    {
        $achievement = GroupAchievementService::GROUP_ACHIEVEMENTS['first_steps'];
        $this->assertEquals('member_count', $achievement['target_type']);
        $this->assertEquals(10, $achievement['target_value']);
    }

    public function test_all_achievements_have_required_keys(): void
    {
        foreach (GroupAchievementService::GROUP_ACHIEVEMENTS as $key => $achievement) {
            $this->assertArrayHasKey('name', $achievement, "Achievement {$key} missing 'name'");
            $this->assertArrayHasKey('description', $achievement, "Achievement {$key} missing 'description'");
            $this->assertArrayHasKey('target_type', $achievement, "Achievement {$key} missing 'target_type'");
            $this->assertArrayHasKey('target_value', $achievement, "Achievement {$key} missing 'target_value'");
            $this->assertArrayHasKey('xp_reward', $achievement, "Achievement {$key} missing 'xp_reward'");
            $this->assertArrayHasKey('icon', $achievement, "Achievement {$key} missing 'icon'");
        }
    }

    public function test_getGroupAchievements_requires_integration_test(): void
    {
        $this->markTestIncomplete('Requires DB for calculateProgress and getEarnedAchievements');
    }
}

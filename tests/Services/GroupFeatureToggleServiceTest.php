<?php
// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

namespace Tests\Services;

use PHPUnit\Framework\TestCase;
use Nexus\Services\GroupFeatureToggleService;

class GroupFeatureToggleServiceTest extends TestCase
{
    public function testClassExists(): void
    {
        $this->assertTrue(class_exists(GroupFeatureToggleService::class));
    }

    public function testMainModuleToggleConstant(): void
    {
        $this->assertEquals('groups_module', GroupFeatureToggleService::FEATURE_GROUPS_MODULE);
    }

    public function testCoreFeatureConstants(): void
    {
        $this->assertEquals('group_creation', GroupFeatureToggleService::FEATURE_GROUP_CREATION);
        $this->assertEquals('hub_groups', GroupFeatureToggleService::FEATURE_HUB_GROUPS);
        $this->assertEquals('regular_groups', GroupFeatureToggleService::FEATURE_REGULAR_GROUPS);
        $this->assertEquals('private_groups', GroupFeatureToggleService::FEATURE_PRIVATE_GROUPS);
        $this->assertEquals('sub_groups', GroupFeatureToggleService::FEATURE_SUB_GROUPS);
    }

    public function testInteractionFeatureConstants(): void
    {
        $this->assertEquals('discussions', GroupFeatureToggleService::FEATURE_DISCUSSIONS);
        $this->assertEquals('feedback', GroupFeatureToggleService::FEATURE_FEEDBACK);
        $this->assertEquals('member_invites', GroupFeatureToggleService::FEATURE_MEMBER_INVITES);
        $this->assertEquals('join_requests', GroupFeatureToggleService::FEATURE_JOIN_REQUESTS);
    }

    public function testGamificationFeatureConstants(): void
    {
        $this->assertEquals('achievements', GroupFeatureToggleService::FEATURE_ACHIEVEMENTS);
        $this->assertEquals('badges', GroupFeatureToggleService::FEATURE_BADGES);
        $this->assertEquals('leaderboard', GroupFeatureToggleService::FEATURE_LEADERBOARD);
    }

    public function testAdvancedFeatureConstants(): void
    {
        $this->assertEquals('analytics', GroupFeatureToggleService::FEATURE_ANALYTICS);
        $this->assertEquals('moderation', GroupFeatureToggleService::FEATURE_MODERATION);
        $this->assertEquals('approval_workflow', GroupFeatureToggleService::FEATURE_APPROVAL_WORKFLOW);
    }

    public function testIsEnabledMethodExists(): void
    {
        $this->assertTrue(method_exists(GroupFeatureToggleService::class, 'isEnabled'));
    }
}

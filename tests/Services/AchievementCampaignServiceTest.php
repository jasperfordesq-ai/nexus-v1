<?php
// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

namespace Tests\Services;

use PHPUnit\Framework\TestCase;
use Nexus\Services\AchievementCampaignService;

class AchievementCampaignServiceTest extends TestCase
{
    public function testClassExists(): void
    {
        $this->assertTrue(class_exists(AchievementCampaignService::class));
    }

    public function testTypesConstant(): void
    {
        $types = AchievementCampaignService::TYPES;

        $this->assertIsArray($types);
        $this->assertArrayHasKey('one_time', $types);
        $this->assertArrayHasKey('recurring', $types);
        $this->assertArrayHasKey('triggered', $types);
    }

    public function testAudiencesConstant(): void
    {
        $audiences = AchievementCampaignService::AUDIENCES;

        $this->assertIsArray($audiences);
        $this->assertArrayHasKey('all_users', $audiences);
        $this->assertArrayHasKey('new_users', $audiences);
        $this->assertArrayHasKey('active_users', $audiences);
        $this->assertArrayHasKey('inactive_users', $audiences);
        $this->assertArrayHasKey('level_range', $audiences);
        $this->assertArrayHasKey('badge_holders', $audiences);
        $this->assertArrayHasKey('custom', $audiences);
    }

    public function testGetCampaignsMethodExists(): void
    {
        $this->assertTrue(method_exists(AchievementCampaignService::class, 'getCampaigns'));
        $ref = new \ReflectionMethod(AchievementCampaignService::class, 'getCampaigns');
        $this->assertTrue($ref->isStatic());
    }
}

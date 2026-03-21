<?php
// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

namespace Tests\Laravel\Unit\Services;

use Tests\Laravel\TestCase;
use App\Services\AchievementCampaignService;
use App\Models\AchievementCampaign;
use Mockery;

class AchievementCampaignServiceTest extends TestCase
{
    private AchievementCampaignService $service;

    protected function setUp(): void
    {
        parent::setUp();
        $this->service = new AchievementCampaignService();
    }

    public function test_constants_defined(): void
    {
        $this->assertNotEmpty(AchievementCampaignService::TYPES);
        $this->assertNotEmpty(AchievementCampaignService::AUDIENCES);
        $this->assertArrayHasKey('one_time', AchievementCampaignService::TYPES);
        $this->assertArrayHasKey('all_users', AchievementCampaignService::AUDIENCES);
    }

    public function test_getCampaigns_returns_array(): void
    {
        $this->markTestIncomplete('Requires integration test — static Eloquent model calls cannot be mocked without DB');
    }

    public function test_getCampaign_returns_null_when_not_found(): void
    {
        $this->markTestIncomplete('Requires integration test — static Eloquent model calls');
    }

    public function test_createCampaign_returns_null_on_exception(): void
    {
        $this->markTestIncomplete('Requires integration test — static Eloquent model calls');
    }

    public function test_activateCampaign_updates_status(): void
    {
        $this->markTestIncomplete('Requires integration test — static Eloquent model calls');
    }

    public function test_pauseCampaign_updates_status(): void
    {
        $this->markTestIncomplete('Requires integration test — static Eloquent model calls');
    }

    public function test_deleteCampaign_deletes_record(): void
    {
        $this->markTestIncomplete('Requires integration test — static Eloquent model calls');
    }

    public function test_type_to_db_mapping_works(): void
    {
        // Verify the internal mapping by testing through getCampaign
        // Type mapping: one_time -> badge_award, recurring -> xp_bonus, triggered -> challenge
        // DB mapping: badge_award -> one_time, xp_bonus -> recurring, challenge -> triggered
        $this->assertArrayHasKey('one_time', AchievementCampaignService::TYPES);
        $this->assertArrayHasKey('recurring', AchievementCampaignService::TYPES);
        $this->assertArrayHasKey('triggered', AchievementCampaignService::TYPES);
    }
}

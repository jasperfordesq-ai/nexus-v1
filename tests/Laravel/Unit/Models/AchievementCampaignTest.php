<?php
// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

namespace Tests\Laravel\Unit\Models;

use App\Models\AchievementCampaign;
use App\Models\Concerns\HasTenantScope;
use Tests\Laravel\TestCase;

class AchievementCampaignTest extends TestCase
{
    public function test_table_name(): void
    {
        $model = new AchievementCampaign();
        $this->assertEquals('achievement_campaigns', $model->getTable());
    }

    public function test_fillable_contains_expected_fields(): void
    {
        $model = new AchievementCampaign();
        $expected = [
            'tenant_id', 'name', 'description', 'campaign_type', 'badge_key',
            'xp_amount', 'target_audience', 'audience_config', 'schedule',
            'status', 'activated_at', 'last_run_at', 'total_awards',
        ];
        $this->assertEquals($expected, $model->getFillable());
    }

    public function test_casts_contain_correct_types(): void
    {
        $model = new AchievementCampaign();
        $casts = $model->getCasts();
        $this->assertEquals('integer', $casts['xp_amount']);
        $this->assertEquals('integer', $casts['total_awards']);
        $this->assertEquals('array', $casts['audience_config']);
        $this->assertEquals('datetime', $casts['activated_at']);
        $this->assertEquals('datetime', $casts['last_run_at']);
    }

    public function test_uses_has_tenant_scope_trait(): void
    {
        $this->assertContains(
            HasTenantScope::class,
            class_uses_recursive(AchievementCampaign::class)
        );
    }
}

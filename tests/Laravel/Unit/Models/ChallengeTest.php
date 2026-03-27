<?php
// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

namespace Tests\Laravel\Unit\Models;

use App\Models\Challenge;
use App\Models\Concerns\HasTenantScope;
use App\Models\UserChallengeProgress;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Tests\Laravel\TestCase;

class ChallengeTest extends TestCase
{
    public function test_table_name(): void
    {
        $model = new Challenge();
        $this->assertEquals('challenges', $model->getTable());
    }

    public function test_fillable_contains_expected_fields(): void
    {
        $model = new Challenge();
        $expected = [
            'tenant_id', 'title', 'description', 'challenge_type', 'action_type',
            'target_count', 'xp_reward', 'badge_reward',
            'start_date', 'end_date',
            'is_active',
        ];
        $this->assertEquals($expected, $model->getFillable());
    }

    public function test_casts_contain_correct_types(): void
    {
        $model = new Challenge();
        $casts = $model->getCasts();
        $this->assertEquals('integer', $casts['target_count']);
        $this->assertEquals('integer', $casts['xp_reward']);
        $this->assertEquals('boolean', $casts['is_active']);
        $this->assertEquals('date', $casts['start_date']);
        $this->assertEquals('date', $casts['end_date']);
    }

    public function test_updated_at_is_null(): void
    {
        $this->assertNull(Challenge::UPDATED_AT);
    }

    public function test_uses_has_tenant_scope_trait(): void
    {
        $this->assertContains(
            HasTenantScope::class,
            class_uses_recursive(Challenge::class)
        );
    }

    public function test_progress_relationship_returns_has_many(): void
    {
        $model = new Challenge();
        $this->assertInstanceOf(HasMany::class, $model->progress());
    }
}

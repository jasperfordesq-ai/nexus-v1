<?php
// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

namespace Tests\Laravel\Unit\Models;

use App\Models\Concerns\HasTenantScope;
use App\Models\Goal;
use App\Models\GoalCheckin;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Tests\Laravel\TestCase;

class GoalCheckinTest extends TestCase
{
    public function test_table_name(): void
    {
        $model = new GoalCheckin();
        $this->assertEquals('goal_checkins', $model->getTable());
    }

    public function test_fillable_contains_expected_fields(): void
    {
        $model = new GoalCheckin();
        $expected = [
            'goal_id', 'user_id', 'tenant_id', 'progress_percent', 'note', 'mood',
        ];
        $this->assertEquals($expected, $model->getFillable());
    }

    public function test_casts_contain_correct_types(): void
    {
        $model = new GoalCheckin();
        $casts = $model->getCasts();
        $this->assertEquals('float', $casts['progress_percent']);
    }

    public function test_uses_has_tenant_scope_trait(): void
    {
        $this->assertContains(
            HasTenantScope::class,
            class_uses_recursive(GoalCheckin::class)
        );
    }

    public function test_goal_relationship_returns_belongs_to(): void
    {
        $model = new GoalCheckin();
        $this->assertInstanceOf(BelongsTo::class, $model->goal());
    }

    public function test_user_relationship_returns_belongs_to(): void
    {
        $model = new GoalCheckin();
        $this->assertInstanceOf(BelongsTo::class, $model->user());
    }
}

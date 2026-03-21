<?php
// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

namespace Tests\Laravel\Unit\Models;

use App\Models\Concerns\HasTenantScope;
use App\Models\GoalTemplate;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Tests\Laravel\TestCase;

class GoalTemplateTest extends TestCase
{
    public function test_table_name(): void
    {
        $model = new GoalTemplate();
        $this->assertEquals('goal_templates', $model->getTable());
    }

    public function test_fillable_contains_expected_fields(): void
    {
        $model = new GoalTemplate();
        $expected = [
            'tenant_id', 'title', 'description', 'category',
            'default_target_value', 'default_milestones', 'is_public',
            'created_by',
        ];
        $this->assertEquals($expected, $model->getFillable());
    }

    public function test_casts_contain_correct_types(): void
    {
        $model = new GoalTemplate();
        $casts = $model->getCasts();
        $this->assertEquals('float', $casts['default_target_value']);
        $this->assertEquals('array', $casts['default_milestones']);
        $this->assertEquals('boolean', $casts['is_public']);
    }

    public function test_uses_has_tenant_scope_trait(): void
    {
        $this->assertContains(
            HasTenantScope::class,
            class_uses_recursive(GoalTemplate::class)
        );
    }

    public function test_creator_relationship_returns_belongs_to(): void
    {
        $model = new GoalTemplate();
        $this->assertInstanceOf(BelongsTo::class, $model->creator());
        $this->assertEquals('created_by', $model->creator()->getForeignKeyName());
    }
}

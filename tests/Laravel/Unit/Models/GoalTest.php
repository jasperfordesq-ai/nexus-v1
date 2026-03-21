<?php
// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

namespace Tests\Laravel\Unit\Models;

use App\Models\Concerns\HasTenantScope;
use App\Models\Goal;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Tests\Laravel\TestCase;

class GoalTest extends TestCase
{
    public function test_table_name(): void
    {
        $model = new Goal();
        $this->assertEquals('goals', $model->getTable());
    }

    public function test_fillable_contains_expected_fields(): void
    {
        $model = new Goal();
        $expected = [
            'tenant_id', 'user_id', 'title', 'description',
            'deadline', 'is_public', 'status', 'mentor_id',
        ];
        $this->assertEquals($expected, $model->getFillable());
    }

    public function test_casts_contain_correct_types(): void
    {
        $model = new Goal();
        $casts = $model->getCasts();
        $this->assertEquals('integer', $casts['user_id']);
        $this->assertEquals('integer', $casts['mentor_id']);
        $this->assertEquals('boolean', $casts['is_public']);
        $this->assertEquals('date', $casts['deadline']);
    }

    public function test_uses_has_tenant_scope_trait(): void
    {
        $this->assertContains(
            HasTenantScope::class,
            class_uses_recursive(Goal::class)
        );
    }

    public function test_user_relationship_returns_belongs_to(): void
    {
        $model = new Goal();
        $this->assertInstanceOf(BelongsTo::class, $model->user());
    }

    public function test_mentor_relationship_returns_belongs_to(): void
    {
        $model = new Goal();
        $this->assertInstanceOf(BelongsTo::class, $model->mentor());
        $this->assertEquals('mentor_id', $model->mentor()->getForeignKeyName());
    }
}

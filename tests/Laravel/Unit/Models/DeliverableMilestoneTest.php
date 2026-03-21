<?php
// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

namespace Tests\Laravel\Unit\Models;

use App\Models\DeliverableMilestone;
use App\Models\Concerns\HasTenantScope;
use App\Models\Deliverable;
use App\Models\User;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Tests\Laravel\TestCase;

class DeliverableMilestoneTest extends TestCase
{
    public function test_table_name(): void
    {
        $model = new DeliverableMilestone();
        $this->assertEquals('deliverable_milestones', $model->getTable());
    }

    public function test_fillable_contains_expected_fields(): void
    {
        $model = new DeliverableMilestone();
        $expected = [
            'tenant_id', 'deliverable_id', 'title', 'description',
            'order_position', 'status', 'due_date', 'estimated_hours',
            'completed_at', 'completed_by', 'depends_on_milestone_ids',
        ];
        $this->assertEquals($expected, $model->getFillable());
    }

    public function test_casts_contain_correct_types(): void
    {
        $model = new DeliverableMilestone();
        $casts = $model->getCasts();
        $this->assertEquals('integer', $casts['deliverable_id']);
        $this->assertEquals('integer', $casts['order_position']);
        $this->assertEquals('float', $casts['estimated_hours']);
        $this->assertEquals('integer', $casts['completed_by']);
        $this->assertEquals('date', $casts['due_date']);
        $this->assertEquals('datetime', $casts['completed_at']);
        $this->assertEquals('array', $casts['depends_on_milestone_ids']);
    }

    public function test_uses_has_tenant_scope_trait(): void
    {
        $this->assertContains(
            HasTenantScope::class,
            class_uses_recursive(DeliverableMilestone::class)
        );
    }

    public function test_deliverable_relationship_returns_belongs_to(): void
    {
        $model = new DeliverableMilestone();
        $this->assertInstanceOf(BelongsTo::class, $model->deliverable());
    }

    public function test_completed_by_user_relationship_returns_belongs_to(): void
    {
        $model = new DeliverableMilestone();
        $this->assertInstanceOf(BelongsTo::class, $model->completedByUser());
        $this->assertEquals('completed_by', $model->completedByUser()->getForeignKeyName());
    }
}

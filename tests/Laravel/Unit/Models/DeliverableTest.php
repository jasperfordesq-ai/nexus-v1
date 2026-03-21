<?php
// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

namespace Tests\Laravel\Unit\Models;

use App\Models\Deliverable;
use App\Models\DeliverableComment;
use App\Models\DeliverableMilestone;
use App\Models\Concerns\HasTenantScope;
use App\Models\Group;
use App\Models\User;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Tests\Laravel\TestCase;

class DeliverableTest extends TestCase
{
    public function test_table_name(): void
    {
        $model = new Deliverable();
        $this->assertEquals('deliverables', $model->getTable());
    }

    public function test_fillable_contains_expected_fields(): void
    {
        $model = new Deliverable();
        $expected = [
            'tenant_id', 'owner_id', 'title', 'description', 'category',
            'priority', 'assigned_to', 'assigned_group_id', 'start_date',
            'due_date', 'status', 'progress_percentage', 'estimated_hours',
            'actual_hours', 'parent_deliverable_id', 'tags', 'custom_fields',
            'delivery_confidence', 'risk_level', 'risk_notes',
            'blocking_deliverable_ids', 'depends_on_deliverable_ids',
            'watchers', 'collaborators', 'attachment_urls', 'external_links',
            'completed_at',
        ];
        $this->assertEquals($expected, $model->getFillable());
    }

    public function test_casts_contain_correct_types(): void
    {
        $model = new Deliverable();
        $casts = $model->getCasts();
        $this->assertEquals('integer', $casts['owner_id']);
        $this->assertEquals('integer', $casts['assigned_to']);
        $this->assertEquals('integer', $casts['assigned_group_id']);
        $this->assertEquals('integer', $casts['parent_deliverable_id']);
        $this->assertEquals('float', $casts['progress_percentage']);
        $this->assertEquals('float', $casts['estimated_hours']);
        $this->assertEquals('float', $casts['actual_hours']);
        $this->assertEquals('date', $casts['start_date']);
        $this->assertEquals('date', $casts['due_date']);
        $this->assertEquals('datetime', $casts['completed_at']);
        $this->assertEquals('array', $casts['tags']);
        $this->assertEquals('array', $casts['custom_fields']);
        $this->assertEquals('array', $casts['blocking_deliverable_ids']);
        $this->assertEquals('array', $casts['depends_on_deliverable_ids']);
        $this->assertEquals('array', $casts['watchers']);
        $this->assertEquals('array', $casts['collaborators']);
        $this->assertEquals('array', $casts['attachment_urls']);
        $this->assertEquals('array', $casts['external_links']);
    }

    public function test_uses_has_tenant_scope_trait(): void
    {
        $this->assertContains(
            HasTenantScope::class,
            class_uses_recursive(Deliverable::class)
        );
    }

    public function test_owner_relationship_returns_belongs_to(): void
    {
        $model = new Deliverable();
        $this->assertInstanceOf(BelongsTo::class, $model->owner());
        $this->assertEquals('owner_id', $model->owner()->getForeignKeyName());
    }

    public function test_assignee_relationship_returns_belongs_to(): void
    {
        $model = new Deliverable();
        $this->assertInstanceOf(BelongsTo::class, $model->assignee());
        $this->assertEquals('assigned_to', $model->assignee()->getForeignKeyName());
    }

    public function test_assigned_group_relationship_returns_belongs_to(): void
    {
        $model = new Deliverable();
        $this->assertInstanceOf(BelongsTo::class, $model->assignedGroup());
        $this->assertEquals('assigned_group_id', $model->assignedGroup()->getForeignKeyName());
    }

    public function test_parent_relationship_returns_belongs_to(): void
    {
        $model = new Deliverable();
        $this->assertInstanceOf(BelongsTo::class, $model->parent());
        $this->assertEquals('parent_deliverable_id', $model->parent()->getForeignKeyName());
    }

    public function test_children_relationship_returns_has_many(): void
    {
        $model = new Deliverable();
        $this->assertInstanceOf(HasMany::class, $model->children());
        $this->assertEquals('parent_deliverable_id', $model->children()->getForeignKeyName());
    }

    public function test_comments_relationship_returns_has_many(): void
    {
        $model = new Deliverable();
        $this->assertInstanceOf(HasMany::class, $model->comments());
    }

    public function test_milestones_relationship_returns_has_many(): void
    {
        $model = new Deliverable();
        $this->assertInstanceOf(HasMany::class, $model->milestones());
    }
}

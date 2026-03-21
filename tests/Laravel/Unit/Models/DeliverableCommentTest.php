<?php
// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

namespace Tests\Laravel\Unit\Models;

use App\Models\DeliverableComment;
use App\Models\Concerns\HasTenantScope;
use App\Models\Deliverable;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Tests\Laravel\TestCase;

class DeliverableCommentTest extends TestCase
{
    public function test_table_name(): void
    {
        $model = new DeliverableComment();
        $this->assertEquals('deliverable_comments', $model->getTable());
    }

    public function test_fillable_contains_expected_fields(): void
    {
        $model = new DeliverableComment();
        $expected = [
            'tenant_id', 'deliverable_id', 'user_id', 'comment_text',
            'comment_type', 'parent_comment_id', 'mentioned_user_ids',
            'reactions', 'is_pinned', 'is_edited', 'edited_at',
            'is_deleted', 'deleted_at',
        ];
        $this->assertEquals($expected, $model->getFillable());
    }

    public function test_casts_contain_correct_types(): void
    {
        $model = new DeliverableComment();
        $casts = $model->getCasts();
        $this->assertEquals('integer', $casts['deliverable_id']);
        $this->assertEquals('integer', $casts['user_id']);
        $this->assertEquals('integer', $casts['parent_comment_id']);
        $this->assertEquals('array', $casts['mentioned_user_ids']);
        $this->assertEquals('array', $casts['reactions']);
        $this->assertEquals('boolean', $casts['is_pinned']);
        $this->assertEquals('boolean', $casts['is_edited']);
        $this->assertEquals('boolean', $casts['is_deleted']);
        $this->assertEquals('datetime', $casts['edited_at']);
        $this->assertEquals('datetime', $casts['deleted_at']);
    }

    public function test_uses_has_tenant_scope_trait(): void
    {
        $this->assertContains(
            HasTenantScope::class,
            class_uses_recursive(DeliverableComment::class)
        );
    }

    public function test_deliverable_relationship_returns_belongs_to(): void
    {
        $model = new DeliverableComment();
        $this->assertInstanceOf(BelongsTo::class, $model->deliverable());
    }

    public function test_user_relationship_returns_belongs_to(): void
    {
        $model = new DeliverableComment();
        $this->assertInstanceOf(BelongsTo::class, $model->user());
    }

    public function test_parent_relationship_returns_belongs_to(): void
    {
        $model = new DeliverableComment();
        $this->assertInstanceOf(BelongsTo::class, $model->parent());
        $this->assertEquals('parent_comment_id', $model->parent()->getForeignKeyName());
    }

    public function test_replies_relationship_returns_has_many(): void
    {
        $model = new DeliverableComment();
        $this->assertInstanceOf(HasMany::class, $model->replies());
        $this->assertEquals('parent_comment_id', $model->replies()->getForeignKeyName());
    }
}

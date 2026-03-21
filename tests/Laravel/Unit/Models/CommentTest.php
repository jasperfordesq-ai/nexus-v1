<?php
// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

namespace Tests\Laravel\Unit\Models;

use App\Models\Comment;
use App\Models\Concerns\HasTenantScope;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Tests\Laravel\TestCase;

class CommentTest extends TestCase
{
    public function test_table_name(): void
    {
        $model = new Comment();
        $this->assertEquals('comments', $model->getTable());
    }

    public function test_fillable_contains_expected_fields(): void
    {
        $model = new Comment();
        $expected = [
            'tenant_id', 'user_id', 'target_type', 'target_id',
            'content', 'parent_id',
        ];
        $this->assertEquals($expected, $model->getFillable());
    }

    public function test_casts_contain_correct_types(): void
    {
        $model = new Comment();
        $casts = $model->getCasts();
        $this->assertEquals('integer', $casts['user_id']);
        $this->assertEquals('integer', $casts['target_id']);
        $this->assertEquals('integer', $casts['parent_id']);
    }

    public function test_uses_has_tenant_scope_trait(): void
    {
        $this->assertContains(
            HasTenantScope::class,
            class_uses_recursive(Comment::class)
        );
    }

    public function test_user_relationship_returns_belongs_to(): void
    {
        $model = new Comment();
        $this->assertInstanceOf(BelongsTo::class, $model->user());
    }

    public function test_parent_relationship_returns_belongs_to(): void
    {
        $model = new Comment();
        $this->assertInstanceOf(BelongsTo::class, $model->parent());
        $this->assertEquals('parent_id', $model->parent()->getForeignKeyName());
    }

    public function test_replies_relationship_returns_has_many(): void
    {
        $model = new Comment();
        $this->assertInstanceOf(HasMany::class, $model->replies());
        $this->assertEquals('parent_id', $model->replies()->getForeignKeyName());
    }
}

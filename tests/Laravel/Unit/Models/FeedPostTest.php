<?php
// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

namespace Tests\Laravel\Unit\Models;

use App\Models\Concerns\HasTenantScope;
use App\Models\FeedPost;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Tests\Laravel\TestCase;

class FeedPostTest extends TestCase
{
    public function test_table_name(): void
    {
        $model = new FeedPost();
        $this->assertEquals('feed_posts', $model->getTable());
    }

    public function test_fillable_contains_expected_fields(): void
    {
        $model = new FeedPost();
        $expected = [
            'tenant_id', 'user_id', 'content', 'emoji', 'image', 'type',
            'parent_id', 'parent_type', 'visibility', 'group_id',
        ];
        $this->assertEquals($expected, $model->getFillable());
    }

    public function test_casts_contain_correct_types(): void
    {
        $model = new FeedPost();
        $casts = $model->getCasts();
        $this->assertEquals('integer', $casts['user_id']);
        $this->assertEquals('integer', $casts['parent_id']);
        $this->assertEquals('integer', $casts['group_id']);
        $this->assertEquals('integer', $casts['likes_count']);
        $this->assertEquals('integer', $casts['comments_count']);
        $this->assertEquals('boolean', $casts['is_pinned']);
        $this->assertEquals('boolean', $casts['is_hidden']);
    }

    public function test_uses_has_tenant_scope_trait(): void
    {
        $this->assertContains(
            HasTenantScope::class,
            class_uses_recursive(FeedPost::class)
        );
    }

    public function test_user_relationship_returns_belongs_to(): void
    {
        $model = new FeedPost();
        $this->assertInstanceOf(BelongsTo::class, $model->user());
    }

    public function test_parent_relationship_returns_belongs_to(): void
    {
        $model = new FeedPost();
        $this->assertInstanceOf(BelongsTo::class, $model->parent());
        $this->assertEquals('parent_id', $model->parent()->getForeignKeyName());
    }
}

<?php
// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

namespace Tests\Laravel\Unit\Models;

use App\Models\Discussion;
use App\Models\Concerns\HasTenantScope;
use App\Models\Group;
use App\Models\GroupPost;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Tests\Laravel\TestCase;

class DiscussionTest extends TestCase
{
    public function test_table_name(): void
    {
        $model = new Discussion();
        $this->assertEquals('group_discussions', $model->getTable());
    }

    public function test_fillable_contains_expected_fields(): void
    {
        $model = new Discussion();
        $expected = [
            'tenant_id', 'group_id', 'user_id', 'title',
            'is_pinned', 'is_locked', 'status',
        ];
        $this->assertEquals($expected, $model->getFillable());
    }

    public function test_casts_contain_correct_types(): void
    {
        $model = new Discussion();
        $casts = $model->getCasts();
        $this->assertEquals('boolean', $casts['is_pinned']);
        $this->assertEquals('boolean', $casts['is_locked']);
    }

    public function test_uses_has_tenant_scope_trait(): void
    {
        $this->assertContains(
            HasTenantScope::class,
            class_uses_recursive(Discussion::class)
        );
    }

    public function test_group_relationship_returns_belongs_to(): void
    {
        $model = new Discussion();
        $this->assertInstanceOf(BelongsTo::class, $model->group());
    }

    public function test_user_relationship_returns_belongs_to(): void
    {
        $model = new Discussion();
        $this->assertInstanceOf(BelongsTo::class, $model->user());
    }

    public function test_posts_relationship_returns_has_many(): void
    {
        $model = new Discussion();
        $this->assertInstanceOf(HasMany::class, $model->posts());
        $this->assertEquals('discussion_id', $model->posts()->getForeignKeyName());
    }
}

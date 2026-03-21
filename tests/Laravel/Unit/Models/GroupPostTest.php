<?php
// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

namespace Tests\Laravel\Unit\Models;

use App\Models\Concerns\HasTenantScope;
use App\Models\GroupDiscussion;
use App\Models\GroupPost;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Tests\Laravel\TestCase;

class GroupPostTest extends TestCase
{
    public function test_table_name(): void
    {
        $model = new GroupPost();
        $this->assertEquals('group_posts', $model->getTable());
    }

    public function test_fillable_contains_expected_fields(): void
    {
        $model = new GroupPost();
        $expected = [
            'tenant_id', 'discussion_id', 'user_id', 'content',
        ];
        $this->assertEquals($expected, $model->getFillable());
    }

    public function test_casts_contain_correct_types(): void
    {
        $model = new GroupPost();
        $casts = $model->getCasts();
        $this->assertEquals('integer', $casts['discussion_id']);
        $this->assertEquals('integer', $casts['user_id']);
    }

    public function test_uses_has_tenant_scope_trait(): void
    {
        $this->assertContains(
            HasTenantScope::class,
            class_uses_recursive(GroupPost::class)
        );
    }

    public function test_discussion_relationship_returns_belongs_to(): void
    {
        $model = new GroupPost();
        $this->assertInstanceOf(BelongsTo::class, $model->discussion());
        $this->assertEquals('discussion_id', $model->discussion()->getForeignKeyName());
    }

    public function test_user_relationship_returns_belongs_to(): void
    {
        $model = new GroupPost();
        $this->assertInstanceOf(BelongsTo::class, $model->user());
    }
}

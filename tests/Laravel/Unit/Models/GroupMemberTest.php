<?php
// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

namespace Tests\Laravel\Unit\Models;

use App\Models\Concerns\HasTenantScope;
use App\Models\Group;
use App\Models\GroupMember;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Tests\Laravel\TestCase;

class GroupMemberTest extends TestCase
{
    public function test_table_name(): void
    {
        $model = new GroupMember();
        $this->assertEquals('group_members', $model->getTable());
    }

    public function test_fillable_contains_expected_fields(): void
    {
        $model = new GroupMember();
        $expected = [
            'tenant_id', 'group_id', 'user_id', 'role', 'status',
        ];
        $this->assertEquals($expected, $model->getFillable());
    }

    public function test_uses_has_tenant_scope_trait(): void
    {
        $this->assertContains(
            HasTenantScope::class,
            class_uses_recursive(GroupMember::class)
        );
    }

    public function test_group_relationship_returns_belongs_to(): void
    {
        $model = new GroupMember();
        $this->assertInstanceOf(BelongsTo::class, $model->group());
    }

    public function test_user_relationship_returns_belongs_to(): void
    {
        $model = new GroupMember();
        $this->assertInstanceOf(BelongsTo::class, $model->user());
    }
}

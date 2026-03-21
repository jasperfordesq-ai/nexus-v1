<?php
// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

namespace Tests\Laravel\Unit\Models;

use App\Models\Concerns\HasTenantScope;
use App\Models\Group;
use App\Models\GroupType;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Tests\Laravel\TestCase;

class GroupTypeTest extends TestCase
{
    public function test_table_name(): void
    {
        $model = new GroupType();
        $this->assertEquals('group_types', $model->getTable());
    }

    public function test_fillable_contains_expected_fields(): void
    {
        $model = new GroupType();
        $expected = [
            'tenant_id', 'name', 'slug', 'description', 'icon',
            'color', 'image_url', 'sort_order', 'is_active', 'is_hub',
        ];
        $this->assertEquals($expected, $model->getFillable());
    }

    public function test_casts_contain_correct_types(): void
    {
        $model = new GroupType();
        $casts = $model->getCasts();
        $this->assertEquals('integer', $casts['sort_order']);
        $this->assertEquals('boolean', $casts['is_active']);
        $this->assertEquals('boolean', $casts['is_hub']);
    }

    public function test_uses_has_tenant_scope_trait(): void
    {
        $this->assertContains(
            HasTenantScope::class,
            class_uses_recursive(GroupType::class)
        );
    }

    public function test_groups_relationship_returns_has_many(): void
    {
        $model = new GroupType();
        $this->assertInstanceOf(HasMany::class, $model->groups());
        $this->assertEquals('type_id', $model->groups()->getForeignKeyName());
    }
}

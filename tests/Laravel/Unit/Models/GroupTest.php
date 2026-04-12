<?php
// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

namespace Tests\Laravel\Unit\Models;

use App\Models\Concerns\HasTenantScope;
use App\Models\Event;
use App\Models\Group;
use App\Models\GroupDiscussion;
use App\Models\GroupType;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Tests\Laravel\TestCase;

class GroupTest extends TestCase
{
    public function test_table_name(): void
    {
        $model = new Group();
        $this->assertEquals('groups', $model->getTable());
    }

    public function test_appends_contains_expected_attributes(): void
    {
        $model = new Group();
        $appends = $model->getAppends();
        $this->assertContains('members_count', $appends);
        $this->assertContains('member_count', $appends);
    }

    public function test_casts_contain_correct_types(): void
    {
        $model = new Group();
        $casts = $model->getCasts();
        $this->assertEquals('float', $casts['latitude']);
        $this->assertEquals('float', $casts['longitude']);
        $this->assertEquals('boolean', $casts['is_featured']);
        $this->assertEquals('boolean', $casts['has_children']);
        $this->assertEquals('integer', $casts['cached_member_count']);
    }

    public function test_uses_has_tenant_scope_trait(): void
    {
        $this->assertContains(
            HasTenantScope::class,
            class_uses_recursive(Group::class)
        );
    }

    public function test_uses_has_factory_trait(): void
    {
        $this->assertContains(
            HasFactory::class,
            class_uses_recursive(Group::class)
        );
    }

    public function test_creator_relationship_returns_belongs_to(): void
    {
        $model = new Group();
        $this->assertInstanceOf(BelongsTo::class, $model->creator());
        $this->assertEquals('owner_id', $model->creator()->getForeignKeyName());
    }

    public function test_members_relationship_returns_belongs_to_many(): void
    {
        $model = new Group();
        $this->assertInstanceOf(BelongsToMany::class, $model->members());
    }

    public function test_active_members_relationship_returns_belongs_to_many(): void
    {
        $model = new Group();
        $this->assertInstanceOf(BelongsToMany::class, $model->activeMembers());
    }

    public function test_parent_relationship_returns_belongs_to(): void
    {
        $model = new Group();
        $this->assertInstanceOf(BelongsTo::class, $model->parent());
        $this->assertEquals('parent_id', $model->parent()->getForeignKeyName());
    }

    public function test_children_relationship_returns_has_many(): void
    {
        $model = new Group();
        $this->assertInstanceOf(HasMany::class, $model->children());
        $this->assertEquals('parent_id', $model->children()->getForeignKeyName());
    }

    public function test_type_relationship_returns_belongs_to(): void
    {
        $model = new Group();
        $this->assertInstanceOf(BelongsTo::class, $model->type());
        $this->assertEquals('type_id', $model->type()->getForeignKeyName());
    }

    public function test_events_relationship_returns_has_many(): void
    {
        $model = new Group();
        $this->assertInstanceOf(HasMany::class, $model->events());
    }

    public function test_discussions_relationship_returns_has_many(): void
    {
        $model = new Group();
        $this->assertInstanceOf(HasMany::class, $model->discussions());
    }

    public function test_scope_public(): void
    {
        $query = Group::withoutGlobalScopes()->public();
        $this->assertStringContainsString('`visibility`', $query->toSql());
    }

    public function test_scope_top_level(): void
    {
        $query = Group::withoutGlobalScopes()->topLevel();
        $sql = $query->toSql();
        $this->assertStringContainsString('parent_id', $sql);
    }

    public function test_scope_featured(): void
    {
        $query = Group::withoutGlobalScopes()->featured();
        $this->assertStringContainsString('`is_featured`', $query->toSql());
    }

    public function test_members_count_accessor_returns_zero_when_null(): void
    {
        $model = new Group();
        $this->assertEquals(0, $model->members_count);
    }

    public function test_member_count_accessor_returns_zero_when_null(): void
    {
        $model = new Group();
        $this->assertEquals(0, $model->member_count);
    }
}

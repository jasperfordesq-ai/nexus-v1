<?php
// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

namespace Tests\Laravel\Unit\Models;

use App\Models\Category;
use App\Models\Concerns\HasTenantScope;
use App\Models\Event;
use App\Models\Listing;
use App\Models\Post;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Tests\Laravel\TestCase;

class CategoryTest extends TestCase
{
    public function test_table_name(): void
    {
        $model = new Category();
        $this->assertEquals('categories', $model->getTable());
    }

    public function test_fillable_contains_expected_fields(): void
    {
        $model = new Category();
        $expected = [
            'tenant_id', 'name', 'slug', 'color', 'type',
            'parent_id',
        ];
        $this->assertEquals($expected, $model->getFillable());
    }

    public function test_casts_contain_correct_types(): void
    {
        $model = new Category();
        $casts = $model->getCasts();
        // Category currently has no custom casts
        $this->assertIsArray($casts);
    }

    public function test_uses_has_tenant_scope_trait(): void
    {
        $this->assertContains(
            HasTenantScope::class,
            class_uses_recursive(Category::class)
        );
    }

    public function test_listings_relationship_returns_has_many(): void
    {
        $model = new Category();
        $this->assertInstanceOf(HasMany::class, $model->listings());
    }

    public function test_events_relationship_returns_has_many(): void
    {
        $model = new Category();
        $this->assertInstanceOf(HasMany::class, $model->events());
    }

    public function test_posts_relationship_returns_has_many(): void
    {
        $model = new Category();
        $this->assertInstanceOf(HasMany::class, $model->posts());
    }

    public function test_parent_relationship_returns_belongs_to(): void
    {
        $model = new Category();
        $this->assertInstanceOf(BelongsTo::class, $model->parent());
        $this->assertEquals('parent_id', $model->parent()->getForeignKeyName());
    }

    public function test_children_relationship_returns_has_many(): void
    {
        $model = new Category();
        $this->assertInstanceOf(HasMany::class, $model->children());
        $this->assertEquals('parent_id', $model->children()->getForeignKeyName());
    }

    public function test_scope_of_type(): void
    {
        $query = Category::withoutGlobalScopes()->ofType('listing');
        $this->assertStringContainsString('`type`', $query->toSql());
    }

    public function test_scope_active(): void
    {
        $query = Category::withoutGlobalScopes()->active();
        $this->assertStringContainsString('`status`', $query->toSql());
    }
}

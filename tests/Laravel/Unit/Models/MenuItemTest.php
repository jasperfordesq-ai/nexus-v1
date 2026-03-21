<?php
// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

namespace Tests\Laravel\Unit\Models;

use App\Models\Concerns\HasTenantScope;
use App\Models\MenuItem;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Tests\Laravel\TestCase;

class MenuItemTest extends TestCase
{
    private MenuItem $model;

    protected function setUp(): void
    {
        parent::setUp();
        $this->model = new MenuItem();
    }

    public function test_table_name(): void
    {
        $this->assertEquals('menu_items', $this->model->getTable());
    }

    public function test_fillable_contains_expected_fields(): void
    {
        $expected = [
            'menu_id', 'parent_id', 'type', 'label', 'url', 'route_name',
            'page_id', 'icon', 'css_class', 'target', 'sort_order',
            'visibility_rules', 'is_active',
        ];
        $this->assertEquals($expected, $this->model->getFillable());
    }

    public function test_casts_are_correct(): void
    {
        $casts = $this->model->getCasts();
        $this->assertEquals('integer', $casts['menu_id']);
        $this->assertEquals('integer', $casts['parent_id']);
        $this->assertEquals('integer', $casts['page_id']);
        $this->assertEquals('integer', $casts['sort_order']);
        $this->assertEquals('array', $casts['visibility_rules']);
        $this->assertEquals('boolean', $casts['is_active']);
    }

    public function test_uses_has_tenant_scope(): void
    {
        $traits = class_uses_recursive(MenuItem::class);
        $this->assertContains(HasTenantScope::class, $traits);
    }

    public function test_menu_relationship(): void
    {
        $this->assertInstanceOf(BelongsTo::class, $this->model->menu());
    }

    public function test_parent_relationship(): void
    {
        $this->assertInstanceOf(BelongsTo::class, $this->model->parent());
    }

    public function test_children_relationship(): void
    {
        $this->assertInstanceOf(HasMany::class, $this->model->children());
    }

    public function test_page_relationship(): void
    {
        $this->assertInstanceOf(BelongsTo::class, $this->model->page());
    }
}

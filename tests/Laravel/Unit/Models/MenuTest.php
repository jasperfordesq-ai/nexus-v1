<?php
// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

namespace Tests\Laravel\Unit\Models;

use App\Models\Concerns\HasTenantScope;
use App\Models\Menu;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Tests\Laravel\TestCase;

class MenuTest extends TestCase
{
    private Menu $model;

    protected function setUp(): void
    {
        parent::setUp();
        $this->model = new Menu();
    }

    public function test_table_name(): void
    {
        $this->assertEquals('menus', $this->model->getTable());
    }

    public function test_fillable_contains_expected_fields(): void
    {
        $expected = [
            'tenant_id', 'name', 'slug', 'description', 'location',
            'layout', 'min_plan_tier', 'is_active',
        ];
        $this->assertEquals($expected, $this->model->getFillable());
    }

    public function test_casts_are_correct(): void
    {
        $casts = $this->model->getCasts();
        $this->assertEquals('integer', $casts['min_plan_tier']);
        $this->assertEquals('boolean', $casts['is_active']);
    }

    public function test_uses_has_tenant_scope(): void
    {
        $traits = class_uses_recursive(Menu::class);
        $this->assertContains(HasTenantScope::class, $traits);
    }

    public function test_items_relationship(): void
    {
        $this->assertInstanceOf(HasMany::class, $this->model->items());
    }
}

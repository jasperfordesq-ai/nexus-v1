<?php
// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

namespace Tests\Laravel\Unit\Models;

use App\Models\Tenant;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Tests\Laravel\TestCase;

class TenantTest extends TestCase
{
    private Tenant $model;

    protected function setUp(): void
    {
        parent::setUp();
        $this->model = new Tenant();
    }

    public function test_table_name(): void
    {
        $this->assertEquals('tenants', $this->model->getTable());
    }

    public function test_fillable_contains_expected_fields(): void
    {
        $expected = [
            'name', 'slug', 'domain', 'configuration', 'path', 'depth',
            'parent_id', 'allows_subtenants', 'is_active',
        ];
        $this->assertEquals($expected, $this->model->getFillable());
    }

    public function test_casts_are_correct(): void
    {
        $casts = $this->model->getCasts();
        $this->assertEquals('array', $casts['configuration']);
        $this->assertEquals('integer', $casts['depth']);
        $this->assertEquals('integer', $casts['parent_id']);
        $this->assertEquals('boolean', $casts['allows_subtenants']);
        $this->assertEquals('boolean', $casts['is_active']);
    }

    public function test_does_not_use_has_tenant_scope(): void
    {
        $traits = class_uses_recursive(Tenant::class);
        $this->assertNotContains(\App\Models\Concerns\HasTenantScope::class, $traits);
    }

    public function test_parent_relationship(): void
    {
        $this->assertInstanceOf(BelongsTo::class, $this->model->parent());
    }

    public function test_children_relationship(): void
    {
        $this->assertInstanceOf(HasMany::class, $this->model->children());
    }

    public function test_users_relationship(): void
    {
        $this->assertInstanceOf(HasMany::class, $this->model->users());
    }
}

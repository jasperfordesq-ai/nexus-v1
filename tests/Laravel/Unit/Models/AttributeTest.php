<?php
// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

namespace Tests\Laravel\Unit\Models;

use App\Models\Attribute;
use App\Models\Category;
use App\Models\Concerns\HasTenantScope;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Tests\Laravel\TestCase;

class AttributeTest extends TestCase
{
    public function test_table_name(): void
    {
        $model = new Attribute();
        $this->assertEquals('attributes', $model->getTable());
    }

    public function test_fillable_contains_expected_fields(): void
    {
        $model = new Attribute();
        $expected = [
            'tenant_id', 'name', 'category_id', 'input_type',
            'target_type', 'is_active',
        ];
        $this->assertEquals($expected, $model->getFillable());
    }

    public function test_casts_contain_correct_types(): void
    {
        $model = new Attribute();
        $casts = $model->getCasts();
        $this->assertEquals('integer', $casts['category_id']);
        $this->assertEquals('boolean', $casts['is_active']);
    }

    public function test_uses_has_tenant_scope_trait(): void
    {
        $this->assertContains(
            HasTenantScope::class,
            class_uses_recursive(Attribute::class)
        );
    }

    public function test_category_relationship_returns_belongs_to(): void
    {
        $model = new Attribute();
        $this->assertInstanceOf(BelongsTo::class, $model->category());
    }
}

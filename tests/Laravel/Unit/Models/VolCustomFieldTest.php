<?php
// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

namespace Tests\Laravel\Unit\Models;

use App\Models\Concerns\HasTenantScope;
use App\Models\VolCustomField;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Tests\Laravel\TestCase;

class VolCustomFieldTest extends TestCase
{
    private VolCustomField $model;

    protected function setUp(): void
    {
        parent::setUp();
        $this->model = new VolCustomField();
    }

    public function test_table_name(): void
    {
        $this->assertEquals('vol_custom_fields', $this->model->getTable());
    }

    public function test_fillable_contains_expected_fields(): void
    {
        $expected = [
            'tenant_id', 'organization_id', 'field_key', 'field_label',
            'field_type', 'applies_to', 'is_required', 'field_options',
            'display_order', 'placeholder', 'help_text', 'is_active',
        ];
        $this->assertEquals($expected, $this->model->getFillable());
    }

    public function test_casts_are_correct(): void
    {
        $casts = $this->model->getCasts();
        $this->assertEquals('boolean', $casts['is_required']);
        $this->assertEquals('boolean', $casts['is_active']);
        $this->assertEquals('integer', $casts['display_order']);
    }

    public function test_uses_has_tenant_scope(): void
    {
        $traits = class_uses_recursive(VolCustomField::class);
        $this->assertContains(HasTenantScope::class, $traits);
    }

    public function test_organization_relationship(): void
    {
        $this->assertInstanceOf(BelongsTo::class, $this->model->organization());
    }

    public function test_values_relationship(): void
    {
        $this->assertInstanceOf(HasMany::class, $this->model->values());
    }
}

<?php
// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

namespace Tests\Laravel\Unit\Models;

use App\Models\Concerns\HasTenantScope;
use App\Models\VolOpportunity;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Tests\Laravel\TestCase;

class VolOpportunityTest extends TestCase
{
    private VolOpportunity $model;

    protected function setUp(): void
    {
        parent::setUp();
        $this->model = new VolOpportunity();
    }

    public function test_table_name(): void
    {
        $this->assertEquals('vol_opportunities', $this->model->getTable());
    }

    public function test_fillable_contains_expected_fields(): void
    {
        $expected = [
            'tenant_id', 'created_by', 'organization_id', 'title', 'description',
            'location', 'skills_needed', 'start_date', 'end_date', 'category_id',
            'status', 'is_active',
        ];
        $this->assertEquals($expected, $this->model->getFillable());
    }

    public function test_casts_are_correct(): void
    {
        $casts = $this->model->getCasts();
        $this->assertEquals('date', $casts['start_date']);
        $this->assertEquals('date', $casts['end_date']);
        $this->assertEquals('boolean', $casts['is_active']);
    }

    public function test_uses_has_tenant_scope(): void
    {
        $traits = class_uses_recursive(VolOpportunity::class);
        $this->assertContains(HasTenantScope::class, $traits);
    }

    public function test_creator_relationship(): void
    {
        $this->assertInstanceOf(BelongsTo::class, $this->model->creator());
    }

    public function test_organization_relationship(): void
    {
        $this->assertInstanceOf(BelongsTo::class, $this->model->organization());
    }

    public function test_category_relationship(): void
    {
        $this->assertInstanceOf(BelongsTo::class, $this->model->category());
    }

    public function test_shifts_relationship(): void
    {
        $this->assertInstanceOf(HasMany::class, $this->model->shifts());
    }

    public function test_applications_relationship(): void
    {
        $this->assertInstanceOf(HasMany::class, $this->model->applications());
    }
}

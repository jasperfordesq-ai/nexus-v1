<?php
// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

namespace Tests\Laravel\Unit\Models;

use App\Models\Concerns\HasTenantScope;
use App\Models\VolShift;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Tests\Laravel\TestCase;

class VolShiftTest extends TestCase
{
    private VolShift $model;

    protected function setUp(): void
    {
        parent::setUp();
        $this->model = new VolShift();
    }

    public function test_table_name(): void
    {
        $this->assertEquals('vol_shifts', $this->model->getTable());
    }

    public function test_fillable_contains_expected_fields(): void
    {
        $expected = [
            'tenant_id', 'opportunity_id', 'start_time', 'end_time', 'capacity',
        ];
        $this->assertEquals($expected, $this->model->getFillable());
    }

    public function test_casts_are_correct(): void
    {
        $casts = $this->model->getCasts();
        $this->assertEquals('datetime', $casts['start_time']);
        $this->assertEquals('datetime', $casts['end_time']);
        $this->assertEquals('integer', $casts['capacity']);
    }

    public function test_uses_has_tenant_scope(): void
    {
        $traits = class_uses_recursive(VolShift::class);
        $this->assertContains(HasTenantScope::class, $traits);
    }

    public function test_opportunity_relationship(): void
    {
        $this->assertInstanceOf(BelongsTo::class, $this->model->opportunity());
    }
}

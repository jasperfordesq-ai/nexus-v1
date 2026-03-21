<?php
// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

namespace Tests\Laravel\Unit\Models;

use App\Models\Concerns\HasTenantScope;
use App\Models\VolShiftCheckin;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Tests\Laravel\TestCase;

class VolShiftCheckinTest extends TestCase
{
    private VolShiftCheckin $model;

    protected function setUp(): void
    {
        parent::setUp();
        $this->model = new VolShiftCheckin();
    }

    public function test_table_name(): void
    {
        $this->assertEquals('vol_shift_checkins', $this->model->getTable());
    }

    public function test_fillable_contains_expected_fields(): void
    {
        $expected = [
            'tenant_id', 'shift_id', 'user_id', 'qr_token',
            'status', 'checked_in_at', 'checked_out_at',
        ];
        $this->assertEquals($expected, $this->model->getFillable());
    }

    public function test_casts_are_correct(): void
    {
        $casts = $this->model->getCasts();
        $this->assertEquals('datetime', $casts['checked_in_at']);
        $this->assertEquals('datetime', $casts['checked_out_at']);
    }

    public function test_uses_has_tenant_scope(): void
    {
        $traits = class_uses_recursive(VolShiftCheckin::class);
        $this->assertContains(HasTenantScope::class, $traits);
    }

    public function test_shift_relationship(): void
    {
        $this->assertInstanceOf(BelongsTo::class, $this->model->shift());
    }

    public function test_user_relationship(): void
    {
        $this->assertInstanceOf(BelongsTo::class, $this->model->user());
    }
}

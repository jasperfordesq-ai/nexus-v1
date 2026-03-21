<?php
// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

namespace Tests\Laravel\Unit\Models;

use App\Models\Concerns\HasTenantScope;
use App\Models\VolApplication;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Tests\Laravel\TestCase;

class VolApplicationTest extends TestCase
{
    private VolApplication $model;

    protected function setUp(): void
    {
        parent::setUp();
        $this->model = new VolApplication();
    }

    public function test_table_name(): void
    {
        $this->assertEquals('vol_applications', $this->model->getTable());
    }

    public function test_fillable_contains_expected_fields(): void
    {
        $expected = [
            'tenant_id', 'opportunity_id', 'user_id', 'message', 'shift_id',
        ];
        $this->assertEquals($expected, $this->model->getFillable());
    }

    public function test_uses_has_tenant_scope(): void
    {
        $traits = class_uses_recursive(VolApplication::class);
        $this->assertContains(HasTenantScope::class, $traits);
    }

    public function test_user_relationship(): void
    {
        $this->assertInstanceOf(BelongsTo::class, $this->model->user());
    }

    public function test_opportunity_relationship(): void
    {
        $this->assertInstanceOf(BelongsTo::class, $this->model->opportunity());
    }

    public function test_shift_relationship(): void
    {
        $this->assertInstanceOf(BelongsTo::class, $this->model->shift());
    }
}

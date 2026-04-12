<?php
// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

namespace Tests\Laravel\Unit\Models;

use App\Models\Concerns\HasTenantScope;
use App\Models\VolExpense;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Tests\Laravel\TestCase;

class VolExpenseTest extends TestCase
{
    private VolExpense $model;

    protected function setUp(): void
    {
        parent::setUp();
        $this->model = new VolExpense();
    }

    public function test_table_name(): void
    {
        $this->assertEquals('vol_expenses', $this->model->getTable());
    }

    public function test_timestamps_disabled(): void
    {
        $this->assertFalse($this->model->usesTimestamps());
    }

    public function test_casts_are_correct(): void
    {
        $casts = $this->model->getCasts();
        $this->assertEquals('decimal:2', $casts['amount']);
        $this->assertEquals('datetime', $casts['submitted_at']);
        $this->assertEquals('datetime', $casts['reviewed_at']);
        $this->assertEquals('datetime', $casts['paid_at']);
    }

    public function test_uses_has_tenant_scope(): void
    {
        $traits = class_uses_recursive(VolExpense::class);
        $this->assertContains(HasTenantScope::class, $traits);
    }

    public function test_user_relationship(): void
    {
        $this->assertInstanceOf(BelongsTo::class, $this->model->user());
    }

    public function test_organization_relationship(): void
    {
        $this->assertInstanceOf(BelongsTo::class, $this->model->organization());
    }

    public function test_opportunity_relationship(): void
    {
        $this->assertInstanceOf(BelongsTo::class, $this->model->opportunity());
    }

    public function test_shift_relationship(): void
    {
        $this->assertInstanceOf(BelongsTo::class, $this->model->shift());
    }

    public function test_reviewer_relationship(): void
    {
        $this->assertInstanceOf(BelongsTo::class, $this->model->reviewer());
    }
}

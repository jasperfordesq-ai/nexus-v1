<?php
// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

namespace Tests\Laravel\Unit\Models;

use App\Models\Concerns\HasTenantScope;
use App\Models\VolLog;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Tests\Laravel\TestCase;

class VolLogTest extends TestCase
{
    private VolLog $model;

    protected function setUp(): void
    {
        parent::setUp();
        $this->model = new VolLog();
    }

    public function test_table_name(): void
    {
        $this->assertEquals('vol_logs', $this->model->getTable());
    }

    public function test_fillable_contains_expected_fields(): void
    {
        $expected = [
            'tenant_id', 'user_id', 'organization_id', 'opportunity_id',
            'date_logged', 'hours', 'description', 'status',
        ];
        $this->assertEquals($expected, $this->model->getFillable());
    }

    public function test_casts_are_correct(): void
    {
        $casts = $this->model->getCasts();
        $this->assertEquals('date', $casts['date_logged']);
        $this->assertEquals('decimal:2', $casts['hours']);
    }

    public function test_uses_has_tenant_scope(): void
    {
        $traits = class_uses_recursive(VolLog::class);
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
}

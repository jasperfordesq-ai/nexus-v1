<?php
// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

namespace Tests\Laravel\Unit\Models;

use App\Models\Concerns\HasTenantScope;
use App\Models\Report;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Tests\Laravel\TestCase;

class ReportTest extends TestCase
{
    private Report $model;

    protected function setUp(): void
    {
        parent::setUp();
        $this->model = new Report();
    }

    public function test_table_name(): void
    {
        $this->assertEquals('reports', $this->model->getTable());
    }

    public function test_fillable_contains_expected_fields(): void
    {
        $expected = [
            'tenant_id', 'reporter_id', 'target_type', 'target_id',
            'reason', 'status',
        ];
        $this->assertEquals($expected, $this->model->getFillable());
    }

    public function test_casts_are_correct(): void
    {
        $casts = $this->model->getCasts();
        $this->assertEquals('integer', $casts['reporter_id']);
        $this->assertEquals('integer', $casts['target_id']);
    }

    public function test_uses_has_tenant_scope(): void
    {
        $traits = class_uses_recursive(Report::class);
        $this->assertContains(HasTenantScope::class, $traits);
    }

    public function test_reporter_relationship(): void
    {
        $this->assertInstanceOf(BelongsTo::class, $this->model->reporter());
    }
}

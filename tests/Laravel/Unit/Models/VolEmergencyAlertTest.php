<?php
// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

namespace Tests\Laravel\Unit\Models;

use App\Models\Concerns\HasTenantScope;
use App\Models\VolEmergencyAlert;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Tests\Laravel\TestCase;

class VolEmergencyAlertTest extends TestCase
{
    private VolEmergencyAlert $model;

    protected function setUp(): void
    {
        parent::setUp();
        $this->model = new VolEmergencyAlert();
    }

    public function test_table_name(): void
    {
        $this->assertEquals('vol_emergency_alerts', $this->model->getTable());
    }

    public function test_timestamps_disabled(): void
    {
        $this->assertFalse($this->model->usesTimestamps());
    }

    public function test_fillable_contains_expected_fields(): void
    {
        $expected = [
            'tenant_id', 'shift_id', 'created_by', 'priority', 'message',
            'required_skills', 'status', 'expires_at', 'filled_at', 'created_at',
        ];
        $this->assertEquals($expected, $this->model->getFillable());
    }

    public function test_casts_are_correct(): void
    {
        $casts = $this->model->getCasts();
        $this->assertEquals('array', $casts['required_skills']);
        $this->assertEquals('datetime', $casts['expires_at']);
        $this->assertEquals('datetime', $casts['filled_at']);
        $this->assertEquals('datetime', $casts['created_at']);
    }

    public function test_uses_has_tenant_scope(): void
    {
        $traits = class_uses_recursive(VolEmergencyAlert::class);
        $this->assertContains(HasTenantScope::class, $traits);
    }

    public function test_shift_relationship(): void
    {
        $this->assertInstanceOf(BelongsTo::class, $this->model->shift());
    }

    public function test_creator_relationship(): void
    {
        $this->assertInstanceOf(BelongsTo::class, $this->model->creator());
    }

    public function test_recipients_relationship(): void
    {
        $this->assertInstanceOf(HasMany::class, $this->model->recipients());
    }
}

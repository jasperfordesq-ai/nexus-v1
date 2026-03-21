<?php
// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

namespace Tests\Laravel\Unit\Models;

use App\Models\Concerns\HasTenantScope;
use App\Models\SafeguardingAssignment;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Tests\Laravel\TestCase;

class SafeguardingAssignmentTest extends TestCase
{
    private SafeguardingAssignment $model;

    protected function setUp(): void
    {
        parent::setUp();
        $this->model = new SafeguardingAssignment();
    }

    public function test_table_name(): void
    {
        $this->assertEquals('safeguarding_assignments', $this->model->getTable());
    }

    public function test_timestamps_disabled(): void
    {
        $this->assertFalse($this->model->usesTimestamps());
    }

    public function test_fillable_contains_expected_fields(): void
    {
        $expected = [
            'tenant_id', 'guardian_user_id', 'ward_user_id', 'assigned_by',
            'assigned_at', 'consent_given_at', 'revoked_at', 'notes',
        ];
        $this->assertEquals($expected, $this->model->getFillable());
    }

    public function test_casts_are_correct(): void
    {
        $casts = $this->model->getCasts();
        $this->assertEquals('integer', $casts['guardian_user_id']);
        $this->assertEquals('integer', $casts['ward_user_id']);
        $this->assertEquals('integer', $casts['assigned_by']);
        $this->assertEquals('datetime', $casts['assigned_at']);
        $this->assertEquals('datetime', $casts['consent_given_at']);
        $this->assertEquals('datetime', $casts['revoked_at']);
    }

    public function test_uses_has_tenant_scope(): void
    {
        $traits = class_uses_recursive(SafeguardingAssignment::class);
        $this->assertContains(HasTenantScope::class, $traits);
    }

    public function test_guardian_relationship(): void
    {
        $this->assertInstanceOf(BelongsTo::class, $this->model->guardian());
    }

    public function test_ward_relationship(): void
    {
        $this->assertInstanceOf(BelongsTo::class, $this->model->ward());
    }

    public function test_assigner_relationship(): void
    {
        $this->assertInstanceOf(BelongsTo::class, $this->model->assigner());
    }
}

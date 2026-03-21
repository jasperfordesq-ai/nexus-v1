<?php
// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

namespace Tests\Laravel\Unit\Models;

use App\Models\Concerns\HasTenantScope;
use App\Models\MemberAvailability;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Tests\Laravel\TestCase;

class MemberAvailabilityTest extends TestCase
{
    private MemberAvailability $model;

    protected function setUp(): void
    {
        parent::setUp();
        $this->model = new MemberAvailability();
    }

    public function test_table_name(): void
    {
        $this->assertEquals('member_availability', $this->model->getTable());
    }

    public function test_fillable_contains_expected_fields(): void
    {
        $expected = [
            'tenant_id', 'user_id', 'day_of_week', 'start_time', 'end_time',
            'is_recurring', 'specific_date', 'note',
        ];
        $this->assertEquals($expected, $this->model->getFillable());
    }

    public function test_casts_are_correct(): void
    {
        $casts = $this->model->getCasts();
        $this->assertEquals('integer', $casts['user_id']);
        $this->assertEquals('integer', $casts['day_of_week']);
        $this->assertEquals('boolean', $casts['is_recurring']);
    }

    public function test_uses_has_tenant_scope(): void
    {
        $traits = class_uses_recursive(MemberAvailability::class);
        $this->assertContains(HasTenantScope::class, $traits);
    }

    public function test_user_relationship(): void
    {
        $this->assertInstanceOf(BelongsTo::class, $this->model->user());
    }
}

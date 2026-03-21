<?php
// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

namespace Tests\Laravel\Unit\Models;

use App\Models\Concerns\HasTenantScope;
use App\Models\VolAccessibilityNeed;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Tests\Laravel\TestCase;

class VolAccessibilityNeedTest extends TestCase
{
    private VolAccessibilityNeed $model;

    protected function setUp(): void
    {
        parent::setUp();
        $this->model = new VolAccessibilityNeed();
    }

    public function test_table_name(): void
    {
        $this->assertEquals('vol_accessibility_needs', $this->model->getTable());
    }

    public function test_fillable_contains_expected_fields(): void
    {
        $expected = [
            'tenant_id', 'user_id', 'need_type', 'description',
            'accommodations_required', 'emergency_contact_name',
            'emergency_contact_phone',
        ];
        $this->assertEquals($expected, $this->model->getFillable());
    }

    public function test_has_no_explicit_casts(): void
    {
        $casts = $this->model->getCasts();
        // Only default casts (id, etc.) should be present
        $this->assertArrayNotHasKey('user_id', $casts);
    }

    public function test_uses_has_tenant_scope(): void
    {
        $traits = class_uses_recursive(VolAccessibilityNeed::class);
        $this->assertContains(HasTenantScope::class, $traits);
    }

    public function test_user_relationship(): void
    {
        $this->assertInstanceOf(BelongsTo::class, $this->model->user());
    }
}

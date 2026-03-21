<?php
// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

namespace Tests\Laravel\Unit\Models;

use App\Models\Concerns\HasTenantScope;
use App\Models\VolDonation;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Tests\Laravel\TestCase;

class VolDonationTest extends TestCase
{
    private VolDonation $model;

    protected function setUp(): void
    {
        parent::setUp();
        $this->model = new VolDonation();
    }

    public function test_table_name(): void
    {
        $this->assertEquals('vol_donations', $this->model->getTable());
    }

    public function test_timestamps_disabled(): void
    {
        $this->assertFalse($this->model->usesTimestamps());
    }

    public function test_fillable_contains_expected_fields(): void
    {
        $expected = [
            'tenant_id', 'user_id', 'opportunity_id', 'giving_day_id',
            'amount', 'currency', 'payment_method', 'payment_reference',
            'message', 'is_anonymous', 'status', 'created_at',
        ];
        $this->assertEquals($expected, $this->model->getFillable());
    }

    public function test_casts_are_correct(): void
    {
        $casts = $this->model->getCasts();
        $this->assertEquals('decimal:2', $casts['amount']);
        $this->assertEquals('boolean', $casts['is_anonymous']);
        $this->assertEquals('datetime', $casts['created_at']);
    }

    public function test_uses_has_tenant_scope(): void
    {
        $traits = class_uses_recursive(VolDonation::class);
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

    public function test_giving_day_relationship(): void
    {
        $this->assertInstanceOf(BelongsTo::class, $this->model->givingDay());
    }
}

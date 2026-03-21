<?php
// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

namespace Tests\Laravel\Unit\Models;

use App\Models\Concerns\HasTenantScope;
use App\Models\VolExpensePolicy;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Tests\Laravel\TestCase;

class VolExpensePolicyTest extends TestCase
{
    private VolExpensePolicy $model;

    protected function setUp(): void
    {
        parent::setUp();
        $this->model = new VolExpensePolicy();
    }

    public function test_table_name(): void
    {
        $this->assertEquals('vol_expense_policies', $this->model->getTable());
    }

    public function test_fillable_contains_expected_fields(): void
    {
        $expected = [
            'tenant_id', 'organization_id', 'expense_type', 'max_amount',
            'max_monthly', 'requires_receipt_above', 'requires_approval',
        ];
        $this->assertEquals($expected, $this->model->getFillable());
    }

    public function test_casts_are_correct(): void
    {
        $casts = $this->model->getCasts();
        $this->assertEquals('decimal:2', $casts['max_amount']);
        $this->assertEquals('decimal:2', $casts['max_monthly']);
        $this->assertEquals('decimal:2', $casts['requires_receipt_above']);
        $this->assertEquals('boolean', $casts['requires_approval']);
    }

    public function test_uses_has_tenant_scope(): void
    {
        $traits = class_uses_recursive(VolExpensePolicy::class);
        $this->assertContains(HasTenantScope::class, $traits);
    }

    public function test_organization_relationship(): void
    {
        $this->assertInstanceOf(BelongsTo::class, $this->model->organization());
    }
}

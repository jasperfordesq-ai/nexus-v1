<?php
// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

namespace Tests\Laravel\Unit\Models;

use App\Models\PayPlan;
use Tests\Laravel\TestCase;

class PayPlanTest extends TestCase
{
    private PayPlan $model;

    protected function setUp(): void
    {
        parent::setUp();
        $this->model = new PayPlan();
    }

    public function test_table_name(): void
    {
        $this->assertEquals('pay_plans', $this->model->getTable());
    }

    public function test_casts_are_correct(): void
    {
        $casts = $this->model->getCasts();
        $this->assertEquals('integer', $casts['tier_level']);
        $this->assertEquals('decimal:2', $casts['price_monthly']);
        $this->assertEquals('decimal:2', $casts['price_yearly']);
        $this->assertEquals('array', $casts['features']);
        $this->assertEquals('array', $casts['allowed_layouts']);
        $this->assertEquals('integer', $casts['max_menus']);
        $this->assertEquals('integer', $casts['max_menu_items']);
        $this->assertEquals('boolean', $casts['is_active']);
    }

    public function test_does_not_use_has_tenant_scope(): void
    {
        $traits = class_uses_recursive(PayPlan::class);
        $this->assertNotContains(\App\Models\Concerns\HasTenantScope::class, $traits);
    }
}

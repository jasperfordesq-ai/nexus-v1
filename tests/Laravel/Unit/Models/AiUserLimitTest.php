<?php
// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

namespace Tests\Laravel\Unit\Models;

use App\Models\AiUserLimit;
use App\Models\Concerns\HasTenantScope;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Tests\Laravel\TestCase;

class AiUserLimitTest extends TestCase
{
    public function test_table_name(): void
    {
        $model = new AiUserLimit();
        $this->assertEquals('ai_user_limits', $model->getTable());
    }

    public function test_fillable_contains_expected_fields(): void
    {
        $model = new AiUserLimit();
        $expected = [
            'tenant_id', 'user_id', 'daily_limit', 'monthly_limit',
            'daily_used', 'monthly_used', 'last_reset_daily', 'last_reset_monthly',
        ];
        $this->assertEquals($expected, $model->getFillable());
    }

    public function test_casts_contain_correct_types(): void
    {
        $model = new AiUserLimit();
        $casts = $model->getCasts();
        $this->assertEquals('integer', $casts['daily_limit']);
        $this->assertEquals('integer', $casts['monthly_limit']);
        $this->assertEquals('integer', $casts['daily_used']);
        $this->assertEquals('integer', $casts['monthly_used']);
        $this->assertEquals('date', $casts['last_reset_daily']);
        $this->assertEquals('date', $casts['last_reset_monthly']);
    }

    public function test_uses_has_tenant_scope_trait(): void
    {
        $this->assertContains(
            HasTenantScope::class,
            class_uses_recursive(AiUserLimit::class)
        );
    }

    public function test_user_relationship_returns_belongs_to(): void
    {
        $model = new AiUserLimit();
        $this->assertInstanceOf(BelongsTo::class, $model->user());
    }
}

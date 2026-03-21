<?php
// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

namespace Tests\Laravel\Unit\Models;

use App\Models\Concerns\HasTenantScope;
use App\Models\Gamification;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Tests\Laravel\TestCase;

class GamificationTest extends TestCase
{
    public function test_table_name(): void
    {
        $model = new Gamification();
        $this->assertEquals('gamification_actions', $model->getTable());
    }

    public function test_fillable_contains_expected_fields(): void
    {
        $model = new Gamification();
        $expected = [
            'tenant_id', 'user_id', 'action', 'points', 'reason',
        ];
        $this->assertEquals($expected, $model->getFillable());
    }

    public function test_casts_contain_correct_types(): void
    {
        $model = new Gamification();
        $casts = $model->getCasts();
        $this->assertEquals('integer', $casts['points']);
    }

    public function test_uses_has_tenant_scope_trait(): void
    {
        $this->assertContains(
            HasTenantScope::class,
            class_uses_recursive(Gamification::class)
        );
    }

    public function test_user_relationship_returns_belongs_to(): void
    {
        $model = new Gamification();
        $this->assertInstanceOf(BelongsTo::class, $model->user());
    }
}

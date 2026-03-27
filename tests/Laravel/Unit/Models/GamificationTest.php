<?php
// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

namespace Tests\Laravel\Unit\Models;

use App\Models\Concerns\HasTenantScope;
use App\Models\Gamification;
use Tests\Laravel\TestCase;

class GamificationTest extends TestCase
{
    public function test_table_name(): void
    {
        $model = new Gamification();
        $this->assertEquals('gamifications', $model->getTable());
    }

    public function test_fillable_is_empty(): void
    {
        $model = new Gamification();
        $this->assertEquals([], $model->getFillable());
    }

    public function test_updated_at_is_null(): void
    {
        $this->assertNull(Gamification::UPDATED_AT);
    }

    public function test_uses_has_tenant_scope_trait(): void
    {
        $this->assertContains(
            HasTenantScope::class,
            class_uses_recursive(Gamification::class)
        );
    }

    public function test_award_points_method_exists(): void
    {
        $this->assertTrue(
            method_exists(Gamification::class, 'awardPoints'),
            'Gamification::awardPoints() should exist'
        );
    }
}

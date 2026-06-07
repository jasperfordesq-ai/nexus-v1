<?php
// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

namespace Tests\Laravel\Unit\Models;

use App\Models\Concerns\HasTenantScope;
use App\Models\UserStreak;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Tests\Laravel\TestCase;

class UserStreakTest extends TestCase
{
    private UserStreak $model;

    protected function setUp(): void
    {
        parent::setUp();
        $this->model = new UserStreak();
    }

    public function test_table_name(): void
    {
        $this->assertEquals('user_streaks', $this->model->getTable());
    }

    public function test_created_at_disabled_but_updated_at_tracked(): void
    {
        // The user_streaks table has only an `updated_at` column (no `created_at`),
        // so the model disables CREATED_AT while still tracking updated_at.
        $this->assertNull(UserStreak::CREATED_AT);
        $this->assertTrue($this->model->usesTimestamps());
        $this->assertSame('updated_at', $this->model->getUpdatedAtColumn());
    }

    public function test_fillable_contains_expected_fields(): void
    {
        $expected = [
            'tenant_id', 'user_id', 'streak_type',
            'current_streak', 'longest_streak',
            'last_activity_date', 'streak_freezes_remaining',
        ];
        $this->assertEquals($expected, $this->model->getFillable());
    }

    public function test_casts_are_correct(): void
    {
        $casts = $this->model->getCasts();
        $this->assertEquals('integer', $casts['current_streak']);
        $this->assertEquals('integer', $casts['longest_streak']);
        $this->assertEquals('integer', $casts['streak_freezes_remaining']);
        $this->assertEquals('date', $casts['last_activity_date']);
    }

    public function test_uses_has_tenant_scope(): void
    {
        $traits = class_uses_recursive(UserStreak::class);
        $this->assertContains(HasTenantScope::class, $traits);
    }

    public function test_user_relationship(): void
    {
        $this->assertInstanceOf(BelongsTo::class, $this->model->user());
    }
}

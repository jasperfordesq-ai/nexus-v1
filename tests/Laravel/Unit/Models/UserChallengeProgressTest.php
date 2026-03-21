<?php
// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

namespace Tests\Laravel\Unit\Models;

use App\Models\Concerns\HasTenantScope;
use App\Models\UserChallengeProgress;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Tests\Laravel\TestCase;

class UserChallengeProgressTest extends TestCase
{
    private UserChallengeProgress $model;

    protected function setUp(): void
    {
        parent::setUp();
        $this->model = new UserChallengeProgress();
    }

    public function test_table_name(): void
    {
        $this->assertEquals('user_challenge_progress', $this->model->getTable());
    }

    public function test_fillable_contains_expected_fields(): void
    {
        $expected = [
            'tenant_id', 'user_id', 'challenge_id', 'current_count',
            'completed_at', 'reward_claimed',
        ];
        $this->assertEquals($expected, $this->model->getFillable());
    }

    public function test_casts_are_correct(): void
    {
        $casts = $this->model->getCasts();
        $this->assertEquals('integer', $casts['current_count']);
        $this->assertEquals('boolean', $casts['reward_claimed']);
        $this->assertEquals('datetime', $casts['completed_at']);
    }

    public function test_uses_has_tenant_scope(): void
    {
        $traits = class_uses_recursive(UserChallengeProgress::class);
        $this->assertContains(HasTenantScope::class, $traits);
    }

    public function test_challenge_relationship(): void
    {
        $this->assertInstanceOf(BelongsTo::class, $this->model->challenge());
    }

    public function test_user_relationship(): void
    {
        $this->assertInstanceOf(BelongsTo::class, $this->model->user());
    }
}

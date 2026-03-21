<?php
// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

namespace Tests\Laravel\Unit\Models;

use App\Models\Concerns\HasTenantScope;
use App\Models\UserBadge;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Tests\Laravel\TestCase;

class UserBadgeTest extends TestCase
{
    private UserBadge $model;

    protected function setUp(): void
    {
        parent::setUp();
        $this->model = new UserBadge();
    }

    public function test_table_name(): void
    {
        $this->assertEquals('user_badges', $this->model->getTable());
    }

    public function test_created_at_constant(): void
    {
        $this->assertEquals('awarded_at', UserBadge::CREATED_AT);
    }

    public function test_updated_at_constant(): void
    {
        $this->assertNull(UserBadge::UPDATED_AT);
    }

    public function test_fillable_contains_expected_fields(): void
    {
        $expected = [
            'user_id', 'badge_key', 'name', 'icon', 'is_showcased', 'showcase_order',
        ];
        $this->assertEquals($expected, $this->model->getFillable());
    }

    public function test_casts_are_correct(): void
    {
        $casts = $this->model->getCasts();
        $this->assertEquals('boolean', $casts['is_showcased']);
        $this->assertEquals('integer', $casts['showcase_order']);
    }

    public function test_uses_has_tenant_scope(): void
    {
        $traits = class_uses_recursive(UserBadge::class);
        $this->assertContains(HasTenantScope::class, $traits);
    }

    public function test_user_relationship(): void
    {
        $this->assertInstanceOf(BelongsTo::class, $this->model->user());
    }
}

<?php
// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

namespace Tests\Laravel\Unit\Models;

use App\Models\Concerns\HasTenantScope;
use App\Models\UserXpLog;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Tests\Laravel\TestCase;

class UserXpLogTest extends TestCase
{
    private UserXpLog $model;

    protected function setUp(): void
    {
        parent::setUp();
        $this->model = new UserXpLog();
    }

    public function test_table_name(): void
    {
        $this->assertEquals('user_xp_log', $this->model->getTable());
    }

    public function test_updated_at_constant(): void
    {
        $this->assertNull(UserXpLog::UPDATED_AT);
    }

    public function test_fillable_contains_expected_fields(): void
    {
        $expected = ['tenant_id', 'user_id', 'xp_amount', 'action', 'description'];
        $this->assertEquals($expected, $this->model->getFillable());
    }

    public function test_casts_are_correct(): void
    {
        $casts = $this->model->getCasts();
        $this->assertEquals('integer', $casts['xp_amount']);
    }

    public function test_uses_has_tenant_scope(): void
    {
        $traits = class_uses_recursive(UserXpLog::class);
        $this->assertContains(HasTenantScope::class, $traits);
    }

    public function test_user_relationship(): void
    {
        $this->assertInstanceOf(BelongsTo::class, $this->model->user());
    }
}

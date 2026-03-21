<?php
// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

namespace Tests\Laravel\Unit\Models;

use App\Models\Concerns\HasTenantScope;
use App\Models\OrgMember;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Tests\Laravel\TestCase;

class OrgMemberTest extends TestCase
{
    private OrgMember $model;

    protected function setUp(): void
    {
        parent::setUp();
        $this->model = new OrgMember();
    }

    public function test_table_name(): void
    {
        $this->assertEquals('org_members', $this->model->getTable());
    }

    public function test_fillable_contains_expected_fields(): void
    {
        $expected = ['tenant_id', 'organization_id', 'user_id', 'role', 'status'];
        $this->assertEquals($expected, $this->model->getFillable());
    }

    public function test_casts_are_correct(): void
    {
        $casts = $this->model->getCasts();
        $this->assertEquals('integer', $casts['organization_id']);
        $this->assertEquals('integer', $casts['user_id']);
    }

    public function test_uses_has_tenant_scope(): void
    {
        $traits = class_uses_recursive(OrgMember::class);
        $this->assertContains(HasTenantScope::class, $traits);
    }

    public function test_user_relationship(): void
    {
        $this->assertInstanceOf(BelongsTo::class, $this->model->user());
    }
}
